<?php

namespace Tests\Feature;

use App\Jobs\ProcessAsset;
use App\Models\Asset;
use App\Models\Product;
use App\Models\User;
use App\Services\MediaProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class MediaProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function asset(string $type, string $path, string $bytes): Asset
    {
        $p = Product::factory()->create(['category_id' => null, 'type' => $type, 'title' => 'Pipeline test', 'slug' => uniqid('pipeline-'), 'creator' => 'Original Creator', 'description' => 'Original test fixture', 'price' => 100, 'stock' => 1, 'status' => 'draft']);
        Storage::disk('local')->put($path, $bytes);

        return $p->assets()->create(['original_path' => $path, 'original_name' => basename($path)]);
    }

    public function test_painting_preview_is_resized_and_watermark_burned_in(): void
    {
        $image = imagecreatetruecolor(1500, 900);
        $color = imagecolorallocate($image, 130, 80, 90);
        imagefill($image, 0, 0, $color);
        ob_start();
        imagepng($image);
        $original = ob_get_clean();
        imagedestroy($image);
        $asset = $this->asset('painting', 'originals/art.png', $original);
        (new ProcessAsset($asset->id))->handle(app(MediaProcessor::class));
        $asset->refresh();
        $this->assertSame('ready', $asset->status);
        $preview = Storage::disk('local')->get($asset->manifest['preview']);
        $this->assertNotSame($original, $preview);
        $info = getimagesizefromstring($preview);
        $this->assertSame(1100, $info[0]);
        $out = imagecreatefromstring($preview);
        $this->assertNotSame(imagecolorat($out, 1, 1), imagecolorat($out, 14, imagesy($out) - 20));
        imagedestroy($out);
        $this->assertSame($asset->id, $asset->product->fresh()->active_asset_id);
    }

    public function test_processing_failure_is_visible_and_original_is_retained(): void
    {
        $asset = $this->asset('painting', 'originals/invalid.png', 'not an image');
        try {
            app(MediaProcessor::class)->process($asset);
            $this->fail('Invalid image should fail');
        } catch (\Throwable $e) {
            $this->assertSame('failed', $asset->fresh()->status);
            $this->assertNotEmpty($asset->fresh()->error);
            Storage::disk('local')->assertExists('originals/invalid.png');
        } (new ProcessAsset($asset->id))->failed(new \RuntimeException('Test failure'));
        $this->assertSame('Test failure', $asset->fresh()->error);
    }

    public function test_reprocessing_ready_asset_is_idempotent(): void
    {
        $im = imagecreatetruecolor(100, 100);
        ob_start();
        imagepng($im);
        $bytes = ob_get_clean();
        imagedestroy($im);
        $a = $this->asset('painting', 'originals/a.png', $bytes);
        app(MediaProcessor::class)->process($a);
        $a->refresh();
        $before = $a->manifest;
        app(MediaProcessor::class)->process($a);
        $this->assertSame($before, $a->fresh()->manifest);
    }

    public function test_pdf_renders_only_first_page_as_public_preview(): void
    {
        $binary = new Process([config('commerce.pdfinfo'), '-v']);
        try {
            $binary->run();
        } catch (\Throwable) {
            $this->markTestSkipped('Poppler unavailable.');
        } if (! $binary->isSuccessful()) {
            $this->markTestSkipped('Poppler unavailable.');
        }
        $pdf = $this->bookBytes();
        $a = $this->asset('book', 'originals/test.pdf', $pdf);
        app(MediaProcessor::class)->process($a);
        $a->refresh();
        $this->assertSame('ready', $a->status);
        $this->assertCount(2, $a->manifest['pages']);
        $this->assertSame($a->manifest['pages'][0], $a->manifest['preview']);
        $this->assertNotSame(Storage::disk('local')->get($a->manifest['pages'][0]), Storage::disk('local')->get($a->manifest['pages'][1]));
    }

    private function bookBytes(): string
    {
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 400] /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 400] /Resources << /Font << /F1 5 0 R >> >> /Contents 7 0 R >>', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
        foreach (['PUBLIC FIRST PAGE', 'PRIVATE SECOND PAGE'] as $text) {
            $s = "BT /F1 16 Tf 30 300 Td ($text) Tj ET";
            $objects[] = '<< /Length '.strlen($s)." >>\nstream\n".$s."\nendstream";
        }
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $o) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$o."\nendobj\n";
        } $start = strlen($pdf);
        $pdf .= "xref\n0 8\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        } $pdf .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n$start\n%%EOF";

        return $pdf;
    }

    public function test_pdf_uploaded_with_publication_choice_becomes_readable_after_one_save(): void
    {
        $this->withoutVite();
        Queue::fake([ProcessAsset::class]);
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $file = UploadedFile::fake()->createWithContent('new-book.pdf', $this->bookBytes());

        $this->actingAs($admin)->post('/admin/products', [
            'title' => 'One save book', 'creator' => 'An Author', 'type' => 'book',
            'description' => 'A complete PDF book.', 'price' => '120.50',
            'status' => 'published', 'accent' => 'violet', 'content' => $file,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $asset = $product->assets()->firstOrFail();
        $this->assertTrue($product->publication_requested);
        Queue::assertPushed(ProcessAsset::class, fn (ProcessAsset $job): bool => $job->assetId === $asset->id);
        (new ProcessAsset($asset->id))->handle(app(MediaProcessor::class));
        $this->assertSame('published', $product->fresh()->status);
        $this->assertFalse($product->fresh()->publication_requested);
        $this->assertCount(2, $asset->fresh()->manifest['pages']);
        $this->get('/products/'.$product->slug)->assertOk()->assertSee('Pay to read full book')->assertDontSee('Available after content upload');
    }

    public function test_music_preview_is_clipped_to_30_seconds(): void
    {
        $process = new Process([config('commerce.ffmpeg'), '-version']);
        try {
            $process->run();
        } catch (\Throwable) {
            $this->markTestSkipped('FFmpeg unavailable.');
        } if (! $process->isSuccessful()) {
            $this->markTestSkipped('FFmpeg unavailable.');
        }
        $a = $this->asset('music', 'originals/test.mp3', '');
        $disk = Storage::disk('local');
        $process = new Process([config('commerce.ffmpeg'), '-y', '-f', 'lavfi', '-i', 'sine=frequency=440:duration=35', '-codec:a', 'libmp3lame', $disk->path($a->original_path)]);
        $process->mustRun();
        app(MediaProcessor::class)->process($a);
        $a->refresh();
        $this->assertSame('ready', $a->status);
        $preview = $a->manifest['preview'];
        $this->assertNotSame($a->original_path, $preview);
        $process = new Process([config('commerce.ffmpeg'), '-i', $disk->path($preview), '-f', 'null', '-']);
        $process->mustRun();
        $this->assertMatchesRegularExpression('/Duration: 00:00:30\./', $process->getErrorOutput());
    }
}
