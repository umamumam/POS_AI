<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailTransaksi extends Model
{
    protected $table = 'detail_transaksis';

    protected $fillable = [
        'id', // Kita izinkan ID diisi karena akan mengimpor dari DB lama
        'transaksi_id',
        'produk_id',
        'jumlah',
        'harga',
        'subtotal',
    ];

    /**
     * Get the transaction that owns the detail.
     */
    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    /**
     * Get the product for the detail.
     */
    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }
}
