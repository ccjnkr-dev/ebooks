<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['brand_name' => 'Muse & Folio', 'tagline' => 'For the stories, sounds, and art that stay with you.', 'watermark' => 'MUSE & FOLIO - SAMPLE', 'contact_email' => 'hello@example.test', 'pickup_instructions' => "Demo pickup location — replace this in Store settings.\nWe will mark your order ready when it can be collected. Bring your order reference."] as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
        $categories = [];
        foreach (['Fiction', 'Mindful living', 'Indie & acoustic', 'Ambient', 'Abstract art', 'Landscape'] as $name) {
            $categories[$name] = Category::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id;
        }
        $works = [
            ['book', 'The Art of Slowing Down', 'Elena Reyes', 'Mindful living', 34900, 'teal', 'An invitation to notice the small things. A thoughtful collection of reflections on finding stillness, making room, and living at your own pace.', 'Sample book · PDF upload required'],
            ['music', 'Somewhere, Softly', 'Theo Santos', 'Indie & acoustic', 14900, 'rose', 'Warm acoustic melodies for slow mornings and long journeys. A little company for the moments in between.', 'Sample single · MP3 upload required'],
            ['painting', 'Where the Light Falls', 'Mara Villanueva', 'Abstract art', 650000, 'amber', 'A study in warm light and quiet movement. An original work for a space that needs a little soul.', 'Acrylic on canvas · 40 × 50 cm · Pickup only'],
            ['book', 'Letters to the Sea', 'Isabel Cruz', 'Fiction', 29900, 'violet', 'A coastal town, an unopened letter, and the stories we carry home. Discover a tender new voice in independent fiction.', 'Sample novel · PDF upload required'],
            ['music', 'After the Rain', 'Luna Collective', 'Ambient', 19900, 'teal', 'Gentle textures and spacious sounds. A meditative piece to help you settle into the present moment.', 'Sample ambient track · MP3 upload required'],
            ['painting', 'A Quiet Horizon', 'Nico del Sol', 'Landscape', 820000, 'rose', 'An original landscape inspired by the place where earth meets sky. Soft color and spacious forms, painted by hand.', 'Oil on canvas · 60 × 40 cm · Pickup only'],
            ['book', 'A Garden of Small Things', 'Amara Flores', 'Mindful living', 39900, 'rose', 'Notes on attention, everyday rituals, and the joy of beginning again. A small companion for a more considered life.', 'Sample essays · PDF upload required'],
            ['music', 'Sunday Windows', 'Paolo & the Pines', 'Indie & acoustic', 12900, 'amber', 'An intimate independent recording with easy rhythms and a familiar warmth. Made for the light that comes through your windows.', 'Sample acoustic track · MP3 upload required'],
            ['painting', 'Still, in Bloom', 'Sofia Mercado', 'Abstract art', 540000, 'teal', 'Organic shapes and muted greens come together in an original canvas inspired by a garden at rest.', 'Acrylic on canvas · 30 × 40 cm · Pickup only'],
        ];
        foreach ($works as $i => [$type,$title,$creator,$category,$price,$accent,$description,$details]) {
            Product::firstOrCreate(['slug' => Str::slug($title)], compact('type', 'title', 'creator', 'price', 'accent', 'description', 'details') + ['category_id' => $categories[$category], 'stock' => 1, 'status' => 'published', 'featured' => $i < 6]);
        }
        if (! User::where('email', 'demo-collector@example.test')->exists()) {
            $user = User::create(['name' => 'Demo Collector', 'email' => 'demo-collector@example.test', 'password' => Str::random(40)]);
            $user->is_active = false;
            $user->save();
            $painting = Product::where('slug', 'where-the-light-falls')->firstOrFail();
            $order = Order::create(['number' => (string) Str::uuid(), 'user_id' => $user->id, 'status' => 'paid', 'total' => $painting->price, 'fulfillment_status' => 'collected', 'pickup_instructions' => 'Historical demo order. No real purchase.', 'expires_at' => now()->subDays(4), 'paid_at' => now()->subDays(4), 'created_at' => now()->subDays(4)]);
            $order->items()->create(['product_id' => $painting->id, 'title' => $painting->title.' [DEMO]', 'type' => 'painting', 'quantity' => 1, 'unit_price' => $painting->price]);
            $order->payment()->create(['gateway' => 'simulated', 'reference' => (string) Str::uuid(), 'amount' => $order->total, 'status' => 'paid']);
            $book = Product::where('type', 'book')->firstOrFail();
            $refunded = Order::create(['number' => (string) Str::uuid(), 'user_id' => $user->id, 'status' => 'refunded', 'total' => $book->price, 'expires_at' => now()->subDays(2), 'paid_at' => now()->subDays(2), 'created_at' => now()->subDays(2)]);
            $refunded->items()->create(['product_id' => $book->id, 'title' => $book->title.' [DEMO]', 'type' => 'book', 'quantity' => 1, 'unit_price' => $book->price]);
            $refunded->payment()->create(['gateway' => 'simulated', 'reference' => (string) Str::uuid(), 'amount' => $refunded->total, 'status' => 'refunded']);
            Entitlement::create(['user_id' => $user->id, 'product_id' => $book->id, 'order_id' => $refunded->id, 'revoked_at' => now()]);
        }
    }
}
