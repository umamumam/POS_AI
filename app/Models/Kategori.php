<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kategori extends Model
{
    protected $table = 'kategoris';

    protected $fillable = [
        'id', // Kita izinkan ID diisi karena akan mengimpor dari DB lama
        'nama',
    ];

    /**
     * Get the products for the category.
     */
    public function produks(): HasMany
    {
        return $this->hasMany(Produk::class, 'kategori_id');
    }
}
