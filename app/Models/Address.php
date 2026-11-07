<?php

namespace App\Models;

use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $label
 * @property string $full_name
 * @property string $phone
 * @property string $line1
 * @property string|null $line2
 * @property string|null $landmark
 * @property string $city
 * @property string $state
 * @property string $pincode
 * @property string $country
 * @property bool $is_default
 * @property-read User $user
 */
#[Fillable([
    'user_id',
    'label',
    'full_name',
    'phone',
    'line1',
    'line2',
    'landmark',
    'city',
    'state',
    'pincode',
    'country',
    'is_default',
])]
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Snapshot of the address stored on an order, so later edits do not change past orders.
     *
     * @return array{full_name: string, phone: string, line1: string, line2: string|null, landmark: string|null, city: string, state: string, pincode: string, country: string}
     */
    public function toOrderSnapshot(): array
    {
        return [
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'landmark' => $this->landmark,
            'city' => $this->city,
            'state' => $this->state,
            'pincode' => $this->pincode,
            'country' => $this->country,
        ];
    }
}
