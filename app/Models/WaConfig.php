<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaConfig extends Model
{
    protected $table = 'wa_configs';

    protected $fillable = [
        'monitored_devices',
        'allowed_senders',
        'is_active',
        'api_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monitored_devices' => 'array',
            'allowed_senders' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
