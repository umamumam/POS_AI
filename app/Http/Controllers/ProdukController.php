<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use App\Models\Kategori;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProdukController extends Controller
{
    /**
     * Display a listing of the products.
     */
    public function index(Request $request): Response
    {
        $query = $request->input('q');
        $categoryId = $request->input('category_id');

        $productsQuery = Produk::with('kategori');

        if (!empty($query)) {
            $productsQuery->where(function ($q) use ($query) {
                $q->where('nama', 'like', '%' . $query . '%')
                  ->orWhere('kode', 'like', '%' . $query . '%');
            });
        }

        if (!empty($categoryId)) {
            $productsQuery->where('kategori_id', $categoryId);
        }

        $products = $productsQuery->orderBy('nama', 'asc')->paginate(10)->withQueryString();
        $categories = Kategori::orderBy('nama', 'asc')->get();

        return Inertia::render('ProductsCrud', [
            'products' => $products,
            'categories' => $categories,
            'filters' => [
                'q' => $query,
                'category_id' => $categoryId,
            ]
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kode' => 'required|string|max:100|unique:produks,kode',
            'harga_beli' => 'required|integer|min:0',
            'harga_jual' => 'required|integer|min:0',
            'stok' => 'required|integer|min:0',
            'kategori_id' => 'required|exists:kategoris,id',
        ]);

        Produk::create($validated);

        return redirect()->back()->with('success', 'Produk berhasil ditambahkan!');
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, Produk $products_crud)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kode' => 'required|string|max:100|unique:produks,kode,' . $products_crud->id,
            'harga_beli' => 'required|integer|min:0',
            'harga_jual' => 'required|integer|min:0',
            'stok' => 'required|integer|min:0',
            'kategori_id' => 'required|exists:kategoris,id',
        ]);

        $products_crud->update($validated);

        return redirect()->back()->with('success', 'Produk berhasil diperbarui!');
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Produk $products_crud)
    {
        $products_crud->delete();

        return redirect()->back()->with('success', 'Produk berhasil dihapus!');
    }
}
