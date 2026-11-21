<?php

namespace App\Services;

use App\Jobs\ProcessAsset;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SaveProduct
{
    public function save(array $data, ?UploadedFile $content, ?Product $product = null): Product
    {
        $existingId = $product?->id;
        $folder = match ($data['type']) {
            'book' => 'books', 'music' => 'music', default => 'paintings'
        };
        $path = $content?->store('originals/'.$folder, 'local');
        if ($content && ! $path) {
            throw ValidationException::withMessages(['content' => 'The file could not be stored. Please try again.']);
        }
        unset($data['content']);
        $parts = explode('.', (string) $data['price']);
        $data['price'] = ((int) $parts[0]) * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
        $data['featured'] = (bool) ($data['featured'] ?? false);
        $data['stock'] = $data['type'] === 'painting' ? (int) $data['stock'] : 0;
        try {
            [$saved, $queuedAssetId] = DB::transaction(function () use ($existingId, $data, $content, $path): array {
                $product = $existingId ? Product::with('asset')->lockForUpdate()->findOrFail($existingId) : new Product;
                if ($product->exists && $data['type'] !== $product->type && ($product->assets()->exists() || $product->orderItems()->exists())) {
                    throw ValidationException::withMessages(['type' => 'Create a new product to change the content type after an upload or purchase.']);
                }
                $latest = $product->exists ? $product->assets()->latest('id')->first() : null;
                if ($content && $latest && in_array($latest->status, ['pending', 'processing'], true)) {
                    throw ValidationException::withMessages(['content' => 'Your current file is still being prepared. You can save its publication choice now; wait for preparation to finish before replacing the file.']);
                }
                if ($data['status'] === 'published' && ! $content && ! $product->ready && ! $latest) {
                    throw ValidationException::withMessages(['content' => 'Choose a content file before publishing.']);
                }
                $waiting = $content || ($latest && in_array($latest->status, ['pending', 'processing', 'failed'], true));
                $publishWhenReady = $data['status'] === 'published' && $waiting;
                if ($publishWhenReady) {
                    $data['status'] = $product->exists && $product->status === 'published' ? 'published' : 'draft';
                }
                $data['publication_requested'] = $publishWhenReady;
                if (! $product->exists) {
                    $data['slug'] = Str::slug($data['title']).'-'.Str::lower(Str::random(6));
                }
                $product->fill($data)->save();
                $queuedAssetId = null;
                if ($content) {
                    $asset = $product->assets()->create(['original_path' => $path, 'original_name' => $content->getClientOriginalName()]);
                    $queuedAssetId = $asset->id;
                    Audit::record('content.uploaded', (string) $product->id, ['asset' => $asset->id]);
                } elseif ($latest?->status === 'failed' && $data['status'] !== 'archived') {
                    $latest->update(['status' => 'pending', 'error' => null]);
                    $queuedAssetId = $latest->id;
                    Audit::record('content.retried', (string) $latest->id);
                }
                Audit::record($existingId ? 'product.updated' : 'product.created', (string) $product->id);

                return [$product, $queuedAssetId];
            }, 5);
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
        if ($queuedAssetId !== null) {
            ProcessAsset::dispatch($queuedAssetId)->afterCommit();
        }

        return $saved;
    }
}
