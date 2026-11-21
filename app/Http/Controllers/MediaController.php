<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Setting;
use App\Services\MediaProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class MediaController extends Controller
{
    private function headers(): array
    {
        return ['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache', 'X-Content-Type-Options' => 'nosniff', 'Cross-Origin-Resource-Policy' => 'same-origin'];
    }

    public function preview(Product $product): BinaryFileResponse
    {
        abort_unless($product->status === 'published', 404);
        $product->load('asset');
        abort_unless($product->ready, 404);
        $path = $product->asset->manifest['preview'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), $this->headers());
    }

    public function protectedMedia(Request $request, string $product, MediaProcessor $processor): Response|BinaryFileResponse
    {
        $product = Product::withTrashed()->with('asset')->findOrFail($product);
        Gate::authorize('consume', $product);
        abort_unless((string) $request->query('uid') === (string) $request->user()->id && $product->ready, 403);
        if ($product->type === 'book') {
            $data = $request->validate(['page' => 'required|integer|min:1']);
            $path = $product->asset->manifest['pages'][$data['page'] - 1] ?? null;
            abort_unless($path && Storage::disk('local')->exists($path), 404);
            $image = imagecreatefromstring(Storage::disk('local')->get($path));
            $processor->watermark($image, 'Licensed to #'.$request->user()->id.' | '.Setting::read('brand_name', config('app.name')));
            ob_start();
            imagejpeg($image, null, 85);
            $bytes = ob_get_clean();
            imagedestroy($image);

            return response($bytes, 200, ['Content-Type' => 'image/jpeg', ...$this->headers()]);
        }
        $path = $product->asset->original_path;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Content-Type' => 'audio/mpeg', ...$this->headers()]);
    }
}
