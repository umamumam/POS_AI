<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaNotification extends Model
{
    protected $table = 'wa_notifications';

    protected $fillable = [
        'device',
        'sender',
        'name',
        'message',
        'is_read',
        'is_outgoing',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'is_outgoing' => 'boolean',
        ];
    }
}
