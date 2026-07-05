<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class POSController extends Controller
{
    protected GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Display the POS screen.
     */
    public function index(): Response
    {
        $categories = Kategori::all();
        $initialProducts = Produk::with('kategori')
            ->orderBy('nama', 'asc')
            ->limit(30)
            ->get();

        // Ambil 10 transaksi terbaru, urutkan dari ID terbesar (descending)
        $recentTransactions = Transaksi::with(['details.produk'])
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        return Inertia::render('Welcome', [
            'categories' => $categories,
            'initialProducts' => $initialProducts,
            'recentTransactions' => $recentTransactions,
            'apiKeyConfigured' => !empty(env('GEMINI_API_KEY')),
        ]);
    }

    /**
     * Search products manually.
     */
    public function search(Request $request)
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

        $products = $productsQuery->orderBy('nama', 'asc')->paginate(24);

        return response()->json($products);
    }

    /**
     * Analyze product photo using Gemini API.
     */
    public function analyzeImage(Request $request)
    {
        $request->validate([
            'image' => 'required|string', // base64 string
        ]);

        try {
            // Get all products to provide context to Gemini
            // We only retrieve id, nama, and kode to optimize token usage
            $products = Produk::select('id', 'nama', 'kode')->get()->toArray();

            if (count($products) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada produk di database. Harap lakukan impor data produk terlebih dahulu.'
                ], 400);
            }

            // Call Gemini Service
            $matchResult = $this->geminiService->identifyProductFromImage($request->image, $products);

            if (!$matchResult || empty($matchResult['matches'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk tidak dapat dikenali. Silakan arahkan kamera lebih dekat atau cari secara manual.',
                    'details' => $matchResult
                ]);
            }

            // Retrieve full details of each matched product
            $resolvedMatches = [];
            foreach ($matchResult['matches'] as $match) {
                if (empty($match['matched_id'])) continue;
                
                $product = Produk::with('kategori')->find($match['matched_id']);
                if ($product) {
                    $resolvedMatches[] = [
                        'product' => $product,
                        'qty' => $match['qty'] ?? 1,
                        'confidence' => $match['confidence'] ?? 1.0,
                        'reason' => $match['reason'] ?? 'Ditemukan kecocokan.',
                    ];
                }
            }

            if (count($resolvedMatches) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk terdeteksi tapi tidak ada ID yang cocok ditemukan di database.',
                    'details' => $matchResult
                ]);
            }

            return response()->json([
                'success' => true,
                'matches' => $resolvedMatches,
            ]);

        } catch (\Exception $e) {
            Log::error('POS Image Analysis Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Checkout transaction and update stock.
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:produks,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.harga' => 'required|integer',
            'total' => 'required|integer',
            'bayar' => 'required|integer|min:0',
        ]);

        $items = $request->input('items');
        $total = $request->input('total');
        $bayar = $request->input('bayar');

        if ($bayar < $total) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah pembayaran kurang dari total belanja.'
            ], 422);
        }

        $kembalian = $bayar - $total;

        // Generate Transaction Code (e.g. 20260705/01)
        $todayStr = date('Ymd');
        $transaksiCountToday = Transaksi::whereDate('created_at', today())->count();
        $sequence = str_pad($transaksiCountToday + 1, 2, '0', STR_PAD_LEFT);
        $kodeTransaksi = "{$todayStr}/{$sequence}";

        DB::beginTransaction();

        try {
            // 1. Create Transaction
            $transaksi = Transaksi::create([
                'kode' => $kodeTransaksi,
                'total' => $total,
                'bayar' => $bayar,
                'kembalian' => $kembalian,
                'tanggaltransaksi' => now(),
            ]);

            // 2. Process items
            foreach ($items as $item) {
                $produk = Produk::find($item['id']);

                if ($produk->stok < $item['qty']) {
                    throw new \Exception("Stok produk '{$produk->nama}' tidak mencukupi. Tersisa: {$produk->stok}.");
                }

                // Create Transaction Detail
                DetailTransaksi::create([
                    'transaksi_id' => $transaksi->id,
                    'produk_id' => $produk->id,
                    'jumlah' => $item['qty'],
                    'harga' => $item['harga'],
                    'subtotal' => $item['harga'] * $item['qty'],
                ]);

                // Decrement Stock
                $produk->decrement('stok', $item['qty']);
            }

            DB::commit();

            // Load transaction details and products for the printed receipt
            $receipt = Transaksi::with(['details.produk'])->find($transaksi->id);

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil diproses!',
                'receipt' => $receipt,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('POS Checkout Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
