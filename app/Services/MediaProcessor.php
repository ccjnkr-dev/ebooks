<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

final class MediaProcessor
{
    public function process(Asset $asset): void
    {
        $asset->load('product');
        if ($asset->status === 'ready' || ! $asset->product || $asset->product->trashed()) {
            return;
        }
        $asset->update(['status' => 'processing', 'error' => null]);
        $disk = Storage::disk('local');
        $dir = 'processed/'.$asset->id;
        $disk->deleteDirectory($dir);
        $disk->makeDirectory($dir);
        try {
            $source = $disk->path($asset->original_path);
            $type = $asset->product->type;
            if ($type === 'book') {
                $info = $this->run([config('commerce.pdfinfo'), $source]);
                if (preg_match('/Encrypted:\s+yes/i', $info)) {
                    throw new RuntimeException('Password-protected PDFs are not supported.');
                }
                if (! preg_match('/Pages:\s+(\d+)/', $info, $pageMatch) || (int) $pageMatch[1] < 1 || (int) $pageMatch[1] > config('commerce.max_book_pages')) {
                    throw new RuntimeException('Use a PDF containing 1–500 pages.');
                }
                $this->run([config('commerce.pdftoppm'), '-jpeg', '-r', '110', '-scale-to', '1600', $source, $disk->path($dir.'/page')], 540);
                $pages = collect($disk->files($dir))->filter(fn (string $path): bool => str_ends_with($path, '.jpg'))->sort(SORT_NATURAL)->values()->all();
                if (count($pages) !== (int) $pageMatch[1]) {
                    throw new RuntimeException('Not all PDF pages could be rendered.');
                }
                $manifest = ['pages' => $pages, 'preview' => $pages[0]];
            } elseif ($type === 'music') {
                $preview = $dir.'/preview.mp3';
                $this->run([config('commerce.ffmpeg'), '-nostdin', '-y', '-i', $source, '-t', (string) config('commerce.preview_seconds'), '-vn', '-map_metadata', '-1', '-codec:a', 'libmp3lame', '-b:a', '128k', $disk->path($preview)]);
                if (! $disk->exists($preview) || $disk->size($preview) < 100) {
                    throw new RuntimeException('No playable audio was found.');
                }
                $manifest = ['preview' => $preview];
            } else {
                $preview = $dir.'/preview.jpg';
                $info = getimagesize($source);
                if (! $info || $info[0] * $info[1] > 40000000) {
                    throw new RuntimeException('Use an image with no more than 40 megapixels.');
                }
                $image = imagecreatefromstring($disk->get($asset->original_path));
                if (! $image) {
                    throw new RuntimeException('The image could not be decoded.');
                }
                $scaled = imagescale($image, min(1100, imagesx($image)));
                imagedestroy($image);
                $this->watermark($scaled, Setting::read('watermark', 'MUSE & FOLIO • SAMPLE'));
                imagejpeg($scaled, $disk->path($preview), 80);
                imagedestroy($scaled);
                $manifest = ['preview' => $preview];
            }
            DB::transaction(function () use ($asset, $manifest): void {
                $asset->update(['status' => 'ready', 'manifest' => $manifest, 'error' => null]);
                $product = Product::withTrashed()->lockForUpdate()->find($asset->product_id);
                if (! $product || $product->trashed()) {
                    return;
                }
                if ((int) $product->assets()->max('id') === $asset->id) {
                    $changes = ['active_asset_id' => $asset->id];
                    if ($product->publication_requested && $product->status !== 'archived') {
                        $changes['status'] = 'published';
                        $changes['publication_requested'] = false;
                    }
                    $product->update($changes);
                }
            });
        } catch (\Throwable $e) {
            $asset->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000)]);
            throw $e;
        }
    }

    private function run(array $command, int $timeout = 180): string
    {
        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->mustRun();

        return $process->getOutput();
    }

    public function watermark(\GdImage $image, string $text): void
    {
        $text = substr(preg_replace('/[^\x20-\x7E]/', ' ', $text), 0, 80);
        $font = 4;
        $width = imagesx($image);
        $height = imagesy($image);
        $ink = imagecolorallocatealpha($image, 255, 255, 255, 42);
        $shadow = imagecolorallocatealpha($image, 35, 20, 55, 50);
        $step = max(260, strlen($text) * imagefontwidth($font) + 45);
        for ($y = 70; $y < $height; $y += 160) {
            for ($x = 15; $x < $width; $x += $step) {
                imagestring($image, $font, $x + 1, $y + 1, $text, $shadow);
                imagestring($image, $font, $x, $y, $text, $ink);
            }
        }
        $bar = imagecolorallocatealpha($image, 25, 15, 40, 25);
        imagefilledrectangle($image, 0, max(0, $height - 40), $width, $height, $bar);
        imagestring($image, 4, 14, max(0, $height - 28), $text, $ink);
    }
}
