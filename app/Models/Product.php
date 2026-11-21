<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['publication_requested' => 'boolean', 'featured' => 'boolean', 'price' => 'integer', 'stock' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'active_asset_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getReadyAttribute(): bool
    {
        return $this->asset?->status === 'ready';
    }

    public function getPriceLabelAttribute(): string
    {
        return '₱'.number_format($this->price / 100, 2);
    }
}
