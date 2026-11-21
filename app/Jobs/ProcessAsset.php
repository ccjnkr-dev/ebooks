<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Services\MediaProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class ProcessAsset implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public readonly int $assetId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('asset-'.$this->assetId))->expireAfter(660)];
    }

    public function handle(MediaProcessor $processor): void
    {
        $asset = Asset::find($this->assetId);
        if ($asset) {
            $processor->process($asset);
        }
    }

    public function failed(?\Throwable $e): void
    {
        Asset::whereKey($this->assetId)->where('status', '!=', 'ready')->update(['status' => 'failed', 'error' => mb_substr($e?->getMessage() ?? 'Processing failed.', 0, 2000)]);
        logger()->error('Media processing failed', ['asset' => $this->assetId, 'error' => $e?->getMessage()]);
    }
}
