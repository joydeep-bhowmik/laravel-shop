<?php

namespace App\Models;

use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Stores every verified webhook. The unique event_id makes redelivered events idempotent.
 *
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property Carbon|null $processed_at
 */
#[Fillable(['provider', 'event_id', 'event', 'payload', 'processed_at'])]
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
