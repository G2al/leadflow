<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    /** Together with Product::MAX_PRICE keeps total_price within decimal(10,2). */
    public const MAX_QUANTITY = 999;

    /**
     * Only customer data is fillable: status, assignment and prices are written
     * exclusively by the Actions in App\Actions\Orders.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'address',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'nuovo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_to === $user->getKey();
    }

    public function totalFor(?int $quantity): ?string
    {
        if ($quantity === null) {
            return null;
        }

        return (string) BigDecimal::of($this->unit_price)->multipliedBy($quantity)->toScale(2);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function customerName(): Attribute
    {
        return Attribute::get(fn (): string => "{$this->first_name} {$this->last_name}");
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function sourceHost(): Attribute
    {
        return Attribute::get(function (): ?string {
            $host = parse_url((string) $this->source_url, PHP_URL_HOST);

            return is_string($host) ? $host : null;
        });
    }
}
