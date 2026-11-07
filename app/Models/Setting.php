<?php

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Key/value store for store configuration. Rows flagged is_encrypted hold ciphertext at rest.
 *
 * Set is_encrypted before value when mass-assigning, because the value mutator reads it.
 *
 * @property int $id
 * @property string $group
 * @property string $key
 * @property string|null $value
 * @property bool $is_encrypted
 */
#[Fillable(['group', 'key', 'value', 'is_encrypted'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory, LogsActivity;

    /**
     * Log which settings changed without recording their values, since secrets live here.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['group', 'key'])
            ->logOnlyDirty();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    /**
     * Decrypt on read and encrypt on write for rows flagged as encrypted.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value, array $attributes): ?string => ($attributes['is_encrypted'] ?? false) && $value !== null
                ? Crypt::decryptString($value)
                : $value,
            set: fn (?string $value, array $attributes): ?string => ($attributes['is_encrypted'] ?? false) && $value !== null
                ? Crypt::encryptString($value)
                : $value,
        );
    }
}
