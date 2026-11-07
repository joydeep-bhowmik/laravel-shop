<?php

namespace App\Models;

use Database\Factories\PincodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $city
 * @property string $state
 * @property bool $is_serviceable
 * @property bool $cod_available
 * @property int $delivery_days
 */
#[Fillable(['code', 'city', 'state', 'is_serviceable', 'cod_available', 'delivery_days'])]
class Pincode extends Model
{
    /** @use HasFactory<PincodeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_serviceable' => 'boolean',
            'cod_available' => 'boolean',
            'delivery_days' => 'integer',
        ];
    }

    /**
     * @param  Builder<Pincode>  $query
     * @return Builder<Pincode>
     */
    public function scopeServiceable(Builder $query): Builder
    {
        return $query->where('is_serviceable', true);
    }
}
