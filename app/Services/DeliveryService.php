<?php

namespace App\Services;

use App\Models\Pincode;
use Illuminate\Support\Carbon;

class DeliveryService
{
    /**
     * Check whether a pincode can be delivered to and when.
     *
     * @return array{pincode: string, city: string, state: string, serviceable: bool, cod: bool, delivery_days: int, estimated_on: string}|null Null when the pincode is unknown.
     */
    public function check(string $pincode): ?array
    {
        $pincode = trim($pincode);

        if (! preg_match('/^[1-9]\d{5}$/', $pincode)) {
            return null;
        }

        $row = Pincode::query()->where('code', $pincode)->first();

        if ($row === null) {
            return null;
        }

        return [
            'pincode' => $row->code,
            'city' => $row->city,
            'state' => $row->state,
            'serviceable' => $row->is_serviceable,
            'cod' => $row->is_serviceable && $row->cod_available,
            'delivery_days' => $row->delivery_days,
            'estimated_on' => Carbon::now()->addDays($row->delivery_days)->format('D, d M Y'),
        ];
    }
}
