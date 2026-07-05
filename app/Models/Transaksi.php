<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaksi extends Model
{
    protected $table = 'transaksis';

    protected $fillable = [
        'id', // Kita izinkan ID diisi karena akan mengimpor dari DB lama
        'kode',
        'total',
        'bayar',
        'kembalian',
        'tanggaltransaksi',
    ];

    protected $casts = [
        'tanggaltransaksi' => 'datetime',
    ];

    /**
     * Get the details for the transaction.
     */
    public function details(): HasMany
    {
        return $this->hasMany(DetailTransaksi::class, 'transaksi_id');
    }
}
