<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Offer extends Model
{
    public const TOKEN_LENGTH = 64;

    /**
     * public_token is server-generated and deliberately not fillable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'product_id',
        'custom_price',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (Offer $offer): void {
            $offer->public_token = Str::random(self::TOKEN_LENGTH);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'custom_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function effectivePrice(): string
    {
        return $this->custom_price ?? $this->product->selling_price;
    }

    public function acceptsRequests(): bool
    {
        return $this->is_active && $this->product->is_active;
    }
}
