<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produk extends Model
{
    protected $table = 'produks';

    protected $fillable = [
        'id', // Kita izinkan ID diisi karena akan mengimpor dari DB lama
        'nama',
        'kode',
        'harga_beli',
        'harga_jual',
        'stok',
        'kategori_id',
    ];

    /**
     * Get the category that owns the product.
     */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'kategori_id');
    }

    /**
     * Get the detail transactions for the product.
     */
    public function detailTransaksis(): HasMany
    {
        return $this->hasMany(DetailTransaksi::class, 'produk_id');
    }
}
