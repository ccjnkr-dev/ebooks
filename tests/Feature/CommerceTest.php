<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Jobs\ProcessAsset;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CommerceService;
use App\Services\MediaProcessor;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class CommerceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function user(bool $admin = false): User
    {
        $u = User::factory()->create();
        $u->is_admin = $admin;
        $u->save();

        return $u;
    }

    private function product(string $type = 'book', bool $ready = true): Product
    {
        $p = Product::factory()->create(['category_id' => null, 'type' => $type, 'title' => 'Private Test '.$type, 'slug' => 'work-'.uniqid(), 'creator' => 'Test Artist', 'description' => 'A carefully made creative work.', 'price' => 34900, 'stock' => 1, 'status' => 'published', 'accent' => 'violet']);
        if ($ready) {
            $im = imagecreatetruecolor(200, 300);
            ob_start();
            imagejpeg($im);
            $bytes = ob_get_clean();
            imagedestroy($im);
            Storage::disk('local')->put('processed/'.$p->id.'/page-1.jpg', $bytes);
            Storage::disk('local')->put('processed/'.$p->id.'/page-2.jpg', $bytes);
            Storage::disk('local')->put('originals/'.$p->id.'.mp3', 'PRIVATE-MP3-CONTENT');
            Storage::disk('local')->put('processed/'.$p->id.'/preview.mp3', 'SHORT-PREVIEW');
            $a = $p->assets()->create(['original_path' => 'originals/'.$p->id.'.mp3', 'original_name' => 'original.mp3', 'status' => 'ready', 'manifest' => ['preview' => $type === 'music' ? 'processed/'.$p->id.'/preview.mp3' : 'processed/'.$p->id.'/page-1.jpg', 'pages' => ['processed/'.$p->id.'/page-1.jpg', 'processed/'.$p->id.'/page-2.jpg']]]);
            $p->update(['active_asset_id' => $a->id]);
        }

        return $p->fresh(['asset']);
    }

    private function order(User $u, Product $p, string $outcome = 'success'): Order
    {
        $o = app(CommerceService::class)->checkout($u, [$p->id => 1]);
        $payment = app(PaymentGateway::class)->begin($o);
        if ($outcome !== 'pending') {
            app(PaymentGateway::class)->confirm($payment, $outcome);
        }

        return $o->fresh(['items', 'payment']);
    }

    private function data(array $extra = []): array
    {
        return $extra + ['title' => 'New Work', 'creator' => 'Artist', 'type' => 'book', 'description' => 'A complete description.', 'price' => '99.95', 'stock' => 1, 'status' => 'draft', 'accent' => 'teal'];
    }

    public function test_storefront_and_seeded_placeholder_catalog_render(): void
    {
        $this->seed();
        $this->get('/')->assertOk()->assertSee('beautiful obsession');
        $this->get('/catalog')->assertOk()->assertSee('The collection.');
        $this->get('/products/the-art-of-slowing-down')->assertOk()->assertSee('Pay to read full book')->assertSee('Available after content upload');
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_catalog_filters_search_and_sort(): void
    {
        $this->product('book');
        $this->product('music');
        $this->get('/catalog?type=music&q=Private&sort=price_high')->assertOk()->assertSee('Private Test music')->assertDontSee('Private Test book');
        $this->get('/catalog?type=invalid')->assertRedirect();
    }

    public function test_unpublished_and_deleted_products_are_hidden(): void
    {
        $p = $this->product();
        $p->update(['status' => 'draft']);
        $this->get('/products/'.$p->slug)->assertNotFound();
        $this->get('/preview/'.$p->id)->assertNotFound();
        $p->delete();
        $this->get('/products/'.$p->slug)->assertNotFound();
    }

    public function test_first_page_preview_cannot_request_a_second_page(): void
    {
        $p = $this->product();
        $this->assertSame(Storage::disk('local')->path('processed/'.$p->id.'/page-1.jpg'), $this->get('/preview/'.$p->id.'?page=2')->assertOk()->baseResponse->getFile()->getPathname());
        $this->get('/media/'.$p->id)->assertRedirect('/login');
        $this->get('/storage/originals/'.$p->id.'.mp3')->assertNotFound();
    }

    public function test_music_preview_uses_separate_clip(): void
    {
        $p = $this->product('music');
        $this->assertSame(Storage::disk('local')->path('processed/'.$p->id.'/preview.mp3'), $this->get('/preview/'.$p->id)->assertOk()->baseResponse->getFile()->getPathname());
        $this->get('/products/'.$p->slug)->assertDontSee('originals/')->assertSee('Pay to listen to the full track');
    }

    public function test_unready_products_cannot_enter_cart(): void
    {
        $p = $this->product('book', false);
        $this->post('/cart/'.$p->id)->assertUnprocessable();
    }

    public function test_cart_add_render_and_remove(): void
    {
        $p = $this->product();
        $this->post('/cart/'.$p->id)->assertRedirect('/cart')->assertSessionHas('cart.'.$p->id, 1);
        $this->get('/cart')->assertOk()->assertSee($p->title);
        $this->delete('/cart/'.$p->id)->assertRedirect()->assertSessionMissing('cart.'.$p->id);
    }

    public function test_checkout_uses_server_price_and_requires_login(): void
    {
        $u = $this->user();
        $p = $this->product();
        $this->post('/checkout')->assertRedirect('/login');
        $this->actingAs($u)->withSession(['cart' => [$p->id => 1]])->post('/checkout', ['accept_pickup' => 1, 'total' => 1])->assertRedirect();
        $this->assertDatabaseHas('orders', ['user_id' => $u->id, 'total' => 34900, 'status' => 'pending']);
        $this->assertDatabaseHas('payments', ['amount' => 34900]);
    }

    public function test_empty_cart_and_missing_acceptance_are_rejected(): void
    {
        $this->actingAs($this->user())->post('/checkout', ['accept_pickup' => 1])->assertSessionHasErrors('cart');
        $this->post('/checkout')->assertSessionHasErrors('accept_pickup');
    }

    public function test_success_grants_access_and_is_idempotent(): void
    {
        $u = $this->user();
        $p = $this->product();
        $o = $this->order($u, $p);
        app(PaymentGateway::class)->confirm($o->payment, 'success');
        $this->assertTrue($u->owns($p));
        $this->assertDatabaseCount('entitlements', 1);
        $this->actingAs($u)->get('/library')->assertOk()->assertSee($p->title);
        $this->get('/library/'.$p->id)->assertOk()->assertSee('Page 1 of 2');
    }

    public function test_non_owner_cannot_use_a_signed_url_or_view_order(): void
    {
        $owner = $this->user();
        $p = $this->product();
        $o = $this->order($owner, $p);
        $link = URL::temporarySignedRoute('media', now()->addMinute(), ['product' => $p->id, 'uid' => $owner->id, 'page' => 2]);
        $this->actingAs($this->user())->get($link)->assertForbidden();
        $this->get('/orders/'.$o->id)->assertForbidden();
        $this->post('/payment/'.$o->id, ['outcome' => 'success'])->assertForbidden();
    }

    public function test_owner_can_get_watermarked_page_but_not_expired_or_tampered_link(): void
    {
        $u = $this->user();
        $p = $this->product();
        $this->order($u, $p);
        $this->actingAs($u);
        $url = URL::temporarySignedRoute('media', now()->addMinute(), ['product' => $p->id, 'uid' => $u->id, 'page' => 2]);
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertNotSame(Storage::disk('local')->get('processed/'.$p->id.'/page-2.jpg'), $response->getContent());
        $this->get($url.'&page=1')->assertForbidden();
        $this->travel(2)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_music_full_stream_and_painting_original_restriction(): void
    {
        $u = $this->user();
        $music = $this->product('music');
        $this->order($u, $music);
        $this->actingAs($u)->get('/library/'.$music->id)->assertOk();
        $url = URL::temporarySignedRoute('media', now()->addMinute(), ['product' => $music->id, 'uid' => $u->id]);
        $this->assertSame(Storage::disk('local')->path($music->asset->original_path), $this->get($url)->assertOk()->baseResponse->getFile()->getPathname());
        $painting = $this->product('painting');
        $this->order($u, $painting);
        $url = URL::temporarySignedRoute('media', now()->addMinute(), ['product' => $painting->id, 'uid' => $u->id]);
        $this->get($url)->assertForbidden();
    }

    public function test_reader_progress_and_invalid_pages(): void
    {
        $u = $this->user();
        $p = $this->product();
        $this->order($u, $p);
        $this->actingAs($u)->get('/library/'.$p->id.'?page=2')->assertOk();
        $this->assertDatabaseHas('entitlements', ['user_id' => $u->id, 'progress' => 2]);
        $this->get('/library/'.$p->id)->assertSee('Page 2 of 2');
        $url = URL::temporarySignedRoute('media', now()->addMinute(), ['product' => $p->id, 'uid' => $u->id, 'page' => 99]);
        $this->get($url)->assertNotFound();
    }

    public function test_failure_and_cancellation_release_painting_stock_once(): void
    {
        foreach (['failure' => 'failed', 'cancel' => 'cancelled'] as $input => $status) {
            $p = $this->product('painting');
            $u = $this->user();
            $o = $this->order($u, $p, 'pending');
            $this->assertSame(0, $p->fresh()->stock);
            app(PaymentGateway::class)->confirm($o->payment, $input);
            app(PaymentGateway::class)->confirm($o->payment, $input);
            $this->assertSame(1, $p->fresh()->stock);
            $this->assertSame($status, $o->fresh()->status);
        }
    }

    public function test_stock_cannot_be_oversold(): void
    {
        $p = $this->product('painting');
        $this->order($this->user(), $p, 'pending');
        $this->actingAs($this->user())->withSession(['cart' => [$p->id => 1]])->post('/checkout', ['accept_pickup' => 1])->assertSessionHasErrors('cart');
        $this->assertSame(0, $p->fresh()->stock);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_expired_reservation_cannot_be_paid_and_releases_stock(): void
    {
        $u = $this->user();
        $p = $this->product('painting');
        $o = $this->order($u, $p, 'pending');
        $this->travel(16)->minutes();
        app(PaymentGateway::class)->confirm($o->payment, 'success');
        $this->assertSame('expired', $o->fresh()->status);
        $this->assertSame(1, $p->fresh()->stock);
        $this->artisan('commerce:expire')->assertSuccessful();
        $this->assertSame(1, $p->fresh()->stock);
    }

    public function test_duplicate_digital_purchase_is_rejected(): void
    {
        $u = $this->user();
        $p = $this->product();
        $this->order($u, $p);
        $this->actingAs($u)->withSession(['cart' => [$p->id => 1]])->post('/checkout', ['accept_pickup' => 1])->assertSessionHasErrors('cart');
    }

    public function test_refund_revokes_access_and_restores_uncollected_stock(): void
    {
        $u = $this->user();
        $p = $this->product();
        $o = $this->order($u, $p);
        $this->actingAs($this->user(true))->post('/admin/orders/'.$o->id.'/refund')->assertRedirect();
        $this->assertFalse($u->owns($p));
        $this->actingAs($u)->get('/library/'.$p->id)->assertForbidden();
        $painting = $this->product('painting');
        $physical = $this->order($u, $painting);
        app(PaymentGateway::class)->refund($physical->payment);
        app(PaymentGateway::class)->refund($physical->payment);
        $this->assertSame(1, $painting->fresh()->stock);
    }

    public function test_pickup_progress_and_collected_refund_block(): void
    {
        $p = $this->product('painting');
        $o = $this->order($this->user(), $p);
        $this->actingAs($this->user(true))->patch('/admin/orders/'.$o->id.'/fulfillment', ['fulfillment_status' => 'collected'])->assertUnprocessable();
        $this->patch('/admin/orders/'.$o->id.'/fulfillment', ['fulfillment_status' => 'ready'])->assertRedirect();
        $this->patch('/admin/orders/'.$o->id.'/fulfillment', ['fulfillment_status' => 'collected'])->assertRedirect();
        $this->post('/admin/orders/'.$o->id.'/refund')->assertSessionHasErrors('refund');
        $this->assertSame('paid', $o->fresh()->status);
    }

    public function test_payment_views_and_confirmation_controller(): void
    {
        $u = $this->user();
        $p = $this->product();
        $o = $this->order($u, $p, 'pending');
        $this->actingAs($u)->get('/payment/'.$o->id)->assertOk()->assertSee('No payment information');
        $this->post('/payment/'.$o->id, ['outcome' => 'success'])->assertRedirect('/orders/'.$o->id);
        $this->get('/orders/'.$o->id)->assertOk()->assertSee('Paid');
        $this->get('/orders')->assertOk()->assertSee($p->title);
    }

    public function test_production_simulator_is_disabled_and_checkout_rolls_back(): void
    {
        $u = $this->user();
        $p = $this->product('painting');
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->app->detectEnvironment(fn () => 'production');
        $this->actingAs($u)->withSession(['cart' => [$p->id => 1]])->post('/checkout', ['accept_pickup' => 1])->assertStatus(503);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $p->fresh()->stock);
    }

    public function test_payment_total_mismatch_is_rejected(): void
    {
        $u = $this->user();
        $p = $this->product();
        $o = $this->order($u, $p, 'pending');
        $o->payment->update(['amount' => 1]);
        $this->actingAs($u)->post('/payment/'.$o->id, ['outcome' => 'success'])->assertSessionHasErrors('payment');
        $this->assertFalse($u->owns($p));
    }

    public function test_all_admin_pages_reject_customers(): void
    {
        $this->actingAs($this->user());
        foreach (['/admin', '/admin/products', '/admin/orders', '/admin/payments', '/admin/reports', '/admin/customers', '/admin/settings', '/admin/audit', '/admin/trash'] as $path) {
            $this->get($path)->assertForbidden();
        } $this->post('/admin/products', $this->data())->assertForbidden();
    }

    public function test_all_admin_pages_render(): void
    {
        $this->seed();
        $this->actingAs($this->user(true));
        foreach (['/admin', '/admin/products', '/admin/products/create', '/admin/orders', '/admin/payments', '/admin/reports', '/admin/customers', '/admin/settings', '/admin/audit', '/admin/trash'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_admin_product_crud_and_publication_checks(): void
    {
        $this->actingAs($this->user(true));
        $this->post('/admin/products', $this->data(['status' => 'published']))->assertSessionHasErrors('content');
        $this->post('/admin/products', $this->data())->assertRedirect();
        $p = Product::firstOrFail();
        $this->assertSame(9995, $p->price);
        $this->get('/admin/products/'.$p->id.'/edit')->assertOk();
        $this->put('/admin/products/'.$p->id, $this->data(['price' => '12.3']))->assertRedirect();
        $this->assertSame(1230, $p->fresh()->price);
        $this->delete('/admin/products/'.$p->id)->assertRedirect('/admin/products');
        $this->assertSoftDeleted($p);
        $this->delete('/admin/trash/'.$p->id)->assertRedirect();
        $this->assertDatabaseMissing('products', ['id' => $p->id]);
    }

    public function test_purchased_product_is_archived_and_remains_readable(): void
    {
        $u = $this->user();
        $p = $this->product();
        $this->order($u, $p);
        $this->actingAs($this->user(true))->delete('/admin/products/'.$p->id)->assertRedirect();
        $this->assertSame('archived', $p->fresh()->status);
        $this->get('/products/'.$p->slug)->assertNotFound();
        $this->actingAs($u)->get('/library/'.$p->id)->assertOk();
    }

    public function test_admin_upload_validation_dispatch_and_retry(): void
    {
        Queue::fake([ProcessAsset::class]);
        $product = $this->product('painting', false);
        $this->actingAs($this->user(true));
        $url = '/admin/products/'.$product->id;
        $this->put($url, $this->data(['type' => 'painting', 'content' => UploadedFile::fake()->create('fake.php', 1, 'text/plain')]))->assertSessionHasErrors('content');
        $this->assertDatabaseCount('assets', 0);
        $this->put($url, $this->data(['type' => 'painting', 'content' => UploadedFile::fake()->image('art.png', 100, 100)]))->assertRedirect('/admin/products/'.$product->id.'/edit');
        $asset = Asset::firstOrFail();
        $this->assertSame('pending', $asset->status);
        $this->put($url, $this->data(['type' => 'painting', 'title' => 'Should not change', 'content' => UploadedFile::fake()->image('replacement.png')]))->assertSessionHasErrors('content');
        $this->assertSame('New Work', $product->fresh()->title);
        $this->assertDatabaseCount('assets', 1);
        $asset->update(['status' => 'failed']);
        $this->put($url, $this->data(['type' => 'painting']))->assertRedirect();
        $this->assertSame('pending', $asset->fresh()->status);
        Queue::assertPushed(ProcessAsset::class, 2);
    }

    public function test_one_save_accepts_a_file_and_publishes_after_preparation(): void
    {
        Queue::fake([ProcessAsset::class]);
        $this->actingAs($this->user(true));
        $this->post('/admin/products', $this->data(['type' => 'painting', 'status' => 'published', 'stock' => 3, 'content' => UploadedFile::fake()->image('original.png', 200, 300)]))->assertRedirect();
        $product = Product::firstOrFail();
        $asset = $product->assets()->firstOrFail();
        $this->assertSame('draft', $product->status);
        $this->assertTrue($product->publication_requested);
        $this->assertSame(3, $product->stock);
        Queue::assertPushed(ProcessAsset::class, fn (ProcessAsset $job): bool => $job->assetId === $asset->id);

        (new ProcessAsset($asset->id))->handle(app(MediaProcessor::class));

        $this->assertSame('published', $product->fresh()->status);
        $this->assertFalse($product->fresh()->publication_requested);
        $this->get('/products/'.$product->slug)->assertOk()->assertSee('Collect this painting');
    }

    public function test_existing_pending_file_accepts_publication_without_another_upload(): void
    {
        Queue::fake([ProcessAsset::class]);
        $product = $this->product('book', false);
        $product->update(['status' => 'draft']);
        $asset = $product->assets()->create(['original_path' => 'originals/books/uploaded.pdf', 'original_name' => 'uploaded.pdf', 'status' => 'pending']);
        $payload = $this->data(['status' => 'published']);
        unset($payload['stock']);

        $this->actingAs($this->user(true))->put('/admin/products/'.$product->id, $payload)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue($product->fresh()->publication_requested);
        $this->assertSame('draft', $product->fresh()->status);
        $this->assertDatabaseCount('assets', 1);
        $this->assertSame($asset->id, $product->assets()->first()->id);
        Queue::assertNothingPushed();
        $this->getJson('/admin/products/'.$product->id.'/edit')->assertOk()->assertExactJson(['processing' => true, 'ready' => false, 'status' => 'draft', 'publication_requested' => true]);
    }

    public function test_digital_products_do_not_require_or_accept_painting_stock(): void
    {
        $this->actingAs($this->user(true));
        foreach (['book', 'music'] as $type) {
            $payload = $this->data(['type' => $type]);
            unset($payload['stock']);
            $this->post('/admin/products', $payload)->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('products', ['type' => $type, 'stock' => 0]);
        }
        $this->post('/admin/products', $this->data(['stock' => -500]))->assertRedirect()->assertSessionHasNoErrors();
        $payload = $this->data(['type' => 'painting']);
        unset($payload['stock']);
        $this->post('/admin/products', $payload)->assertSessionHasErrors('stock');
        $this->post('/admin/products', $this->data(['type' => 'painting', 'stock' => -1]))->assertSessionHasErrors('stock');
    }

    public function test_saving_draft_or_archive_cancels_pending_publication(): void
    {
        Queue::fake([ProcessAsset::class]);
        $this->actingAs($this->user(true));
        foreach (['draft', 'archived'] as $status) {
            $product = $this->product('painting', false);
            $product->update(['status' => 'draft', 'publication_requested' => true]);
            $file = UploadedFile::fake()->image('pending.png', 100, 100);
            $path = $file->store('originals/paintings', 'local');
            $asset = $product->assets()->create(['original_path' => $path, 'original_name' => 'pending.png']);
            $this->put('/admin/products/'.$product->id, $this->data(['type' => 'painting', 'status' => $status]))->assertRedirect();

            (new ProcessAsset($asset->id))->handle(app(MediaProcessor::class));

            $this->assertSame($status, $product->fresh()->status);
            $this->assertFalse($product->fresh()->publication_requested);
        }
        Queue::assertNothingPushed();
    }

    public function test_admin_cannot_change_type_after_upload(): void
    {
        $p = $this->product();
        $this->actingAs($this->user(true))->put('/admin/products/'.$p->id, $this->data(['type' => 'music']))->assertSessionHasErrors('type');
    }

    public function test_admin_can_publish_ready_content(): void
    {
        $p = $this->product();
        $p->update(['status' => 'draft']);
        $this->actingAs($this->user(true))->put('/admin/products/'.$p->id, $this->data(['status' => 'published']))->assertRedirect();
        $this->assertSame('published', $p->fresh()->status);
    }

    public function test_purge_removes_unreferenced_assets_and_rejects_processing(): void
    {
        $p = $this->product();
        $path = $p->asset->original_path;
        $p->delete();
        $this->actingAs($this->user(true))->delete('/admin/trash/'.$p->id)->assertRedirect();
        Storage::disk('local')->assertMissing($path);
        $p = $this->product('book', false);
        $p->assets()->create(['original_path' => 'test', 'original_name' => 'test', 'status' => 'processing']);
        $p->delete();
        $this->delete('/admin/trash/'.$p->id)->assertSessionHasErrors('product');
    }

    public function test_settings_categories_and_csv_reports(): void
    {
        $admin = $this->user(true);
        $this->actingAs($admin);
        $this->put('/admin/settings', ['brand_name' => 'New Brand', 'tagline' => 'New tagline', 'contact_email' => 'hello@example.test', 'watermark' => 'NEW BRAND SAMPLE', 'pickup_instructions' => 'Collect by appointment.'])->assertRedirect();
        $this->assertSame('New Brand', Setting::read('brand_name'));
        $this->post('/admin/categories', ['name' => 'New category'])->assertRedirect();
        $this->post('/admin/categories', ['name' => 'New category'])->assertSessionHasErrors('name');
        $category = Category::firstOrFail();
        $this->delete('/admin/categories/'.$category->id)->assertRedirect();
        $p = $this->product();
        $this->order($this->user(), $p);
        $csv = $this->get('/admin/reports?export=csv')->assertOk()->assertDownload();
        $this->assertStringContainsString('paid', $csv->streamedContent());
        $this->get('/admin/reports?from=2026-11-10&to=2026-11-01')->assertSessionHasErrors('to');
    }

    public function test_customer_disable_and_access_revocation(): void
    {
        $u = $this->user();
        $p = $this->product();
        $this->order($u, $p);
        $admin = $this->user(true);
        $this->actingAs($admin)->get('/admin/customers/'.$u->id.'/access')->assertOk();
        $entry = Entitlement::firstOrFail();
        $this->delete('/admin/access/'.$entry->id)->assertRedirect();
        $this->assertFalse($u->owns($p));
        $this->patch('/admin/customers/'.$u->id, ['is_active' => 0])->assertRedirect();
        $this->patch('/admin/customers/'.$admin->id, ['is_active' => 0])->assertUnprocessable();
        $this->actingAs($u->fresh())->get('/library')->assertForbidden();
    }

    public function test_registration_cannot_grant_admin_and_login_logout_work(): void
    {
        $this->get('/register')->assertOk();
        $this->post('/register', ['name' => 'Reader', 'email' => 'reader@example.test', 'password' => 'password1234', 'password_confirmation' => 'password1234', 'is_admin' => 1])->assertRedirect('/library');
        $u = User::where('email', 'reader@example.test')->firstOrFail();
        $this->assertFalse($u->is_admin);
        $this->post('/logout')->assertRedirect('/');
        $this->get('/login')->assertOk();
        $this->post('/login', ['email' => $u->email, 'password' => 'bad'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $u->email, 'password' => 'password1234'])->assertRedirect();
        $this->get('/account')->assertOk();
        $this->patch('/account', ['name' => 'Updated', 'current_password' => 'password1234', 'password' => 'different1234', 'password_confirmation' => 'different1234'])->assertRedirect();
        $this->assertTrue(Hash::check('different1234', $u->fresh()->password));
    }

    public function test_disabled_user_cannot_login(): void
    {
        $u = $this->user();
        $u->is_active = false;
        $u->save();
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_admin_provisioning_command_has_no_default_password(): void
    {
        $this->artisan('admin:create owner@example.test')->expectsQuestion('Password (at least 12 characters, with letters and numbers)', 'SecurePassword123!')->expectsOutput('Administrator created. Sign in at /login, then open /admin.')->assertSuccessful();
        $this->assertDatabaseHas('users', ['email' => 'owner@example.test', 'is_admin' => true]);
        $this->artisan('admin:create owner@example.test')->expectsQuestion('Password (at least 12 characters, with letters and numbers)', 'SecurePassword123!')->assertFailed();
    }
}
