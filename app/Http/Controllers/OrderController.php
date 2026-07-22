<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Produk;
use App\Models\Kategori;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Display a listing of orders and form for creating new order.
     */
    public function index(): Response
    {
        $orders = Order::with('items')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $products = Produk::with('kategori')
            ->select('id', 'nama', 'kode', 'kategori_id')
            ->orderBy('nama', 'asc')
            ->get();

        $categories = Kategori::orderBy('nama', 'asc')->get();

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created order.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'nullable|string|max:255',
            'tanggal' => 'nullable|date',
            'catatan' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.nama_item' => 'required|string|max:255',
            'items.*.jumlah' => 'required|numeric|min:1',
            'items.*.satuan' => 'required|string|max:50',
            'items.*.kolom' => 'required|in:kiri,kanan',
            'items.*.keterangan' => 'nullable|string|max:255',
            'items.*.produk_id' => 'nullable|exists:produks,id',
            'items.*.save_to_master' => 'nullable|boolean',
            'items.*.kategori_id' => 'nullable|exists:kategoris,id',
        ]);

        $judul = $validated['judul'] ?? 'NYUWUN KIRIMAN';
        $tanggal = $validated['tanggal'] ?? now();
        $nomorOrder = 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(4));

        $order = Order::create([
            'nomor_order' => $nomorOrder,
            'judul' => $judul,
            'tanggal' => $tanggal,
            'catatan' => $validated['catatan'] ?? null,
            'status' => 'draft',
        ]);

        // Default fallback category if user doesn't pick category for custom product
        $defaultKategoriId = $validated['items'][0]['kategori_id'] ?? null;
        if (!$defaultKategoriId) {
            $firstCat = Kategori::first();
            if ($firstCat) {
                $defaultKategoriId = $firstCat->id;
            } else {
                $newCat = Kategori::create(['nama' => 'Umum']);
                $defaultKategoriId = $newCat->id;
            }
        }

        foreach ($validated['items'] as $itemData) {
            $produkId = $itemData['produk_id'] ?? null;
            $saveToMaster = !empty($itemData['save_to_master']);

            // If product does not exist in master yet and user wants to save it
            if (!$produkId && $saveToMaster) {
                // Check if product with same name already exists to avoid duplicates
                $existingProduct = Produk::where('nama', $itemData['nama_item'])->first();

                if ($existingProduct) {
                    $produkId = $existingProduct->id;
                } else {
                    $kategoriId = $itemData['kategori_id'] ?? $defaultKategoriId;
                    $kode = 'PRD-' . strtoupper(Str::random(6));

                    $newProduct = Produk::create([
                        'nama' => trim($itemData['nama_item']),
                        'kode' => $kode,
                        'harga_beli' => 0,
                        'harga_jual' => 0,
                        'stok' => 0,
                        'kategori_id' => $kategoriId,
                    ]);

                    $produkId = $newProduct->id;
                }
            }

            OrderItem::create([
                'order_id' => $order->id,
                'produk_id' => $produkId,
                'nama_item' => trim($itemData['nama_item']),
                'jumlah' => $itemData['jumlah'],
                'satuan' => trim($itemData['satuan']),
                'kolom' => $itemData['kolom'],
                'keterangan' => $itemData['keterangan'] ?? null,
            ]);
        }

        return redirect()->back()->with('success', 'Order kiriman berhasil disimpan!');
    }

    /**
     * Remove the specified order from storage.
     */
    public function destroy(Order $order)
    {
        $order->delete();

        return redirect()->back()->with('success', 'Order berhasil dihapus!');
    }
}
