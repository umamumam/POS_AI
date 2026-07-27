<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'produk_id',
        'nama_item',
        'jumlah',
        'satuan',
        'kolom',
        'keterangan',
    ];

    /**
     * Mutator to format item name into Title Case (e.g., "sosis okey 1 kg" -> "Sosis Okey 1 Kg")
     */
    public function setNamaItemAttribute($value)
    {
        $this->attributes['nama_item'] = ucwords(mb_strtolower(trim($value)));
    }

    /**
     * Mutator to format unit into Title Case (e.g., "sak" -> "Sak", "kg" -> "Kg")
     */
    public function setSatuanAttribute($value)
    {
        $this->attributes['satuan'] = ucwords(mb_strtolower(trim($value)));
    }

    /**
     * Get the order that owns the item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Get the product associated with the item (if any).
     */
    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }
}
