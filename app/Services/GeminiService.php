<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY') ?? '';
    }

    /**
     * Identify a product from a base64 image data.
     *
     * @param string $base64Data (Format: data:image/jpeg;base64,/9j/4AAQSkZJRg...)
     * @param array $products List of products to match against, e.g. [['id' => 1, 'kode' => '899...', 'nama' => 'Energen']]
     * @return array|null
     */
    public function identifyProductFromImage(string $base64Data, array $products): ?array
    {
        if (empty($this->apiKey)) {
            Log::error('Gemini API key is not configured in .env (GEMINI_API_KEY).');
            throw new \Exception('Gemini API key belum dikonfigurasi. Harap masukkan GEMINI_API_KEY di file .env.');
        }

        // Clean base64 data to extract mime type and raw data
        if (preg_match('/^data:([^;]+);base64,(.*)$/', $base64Data, $matches)) {
            $mimeType = $matches[1];
            $rawBase64 = $matches[2];
        } else {
            // Assume it's already raw base64 jpeg if not matching
            $mimeType = 'image/jpeg';
            $rawBase64 = $base64Data;
        }

        // Format product list in a highly compact text format to save tokens
        $productListStr = "Daftar Produk:\n";
        $productListStr .= "ID | Kode | Nama Produk\n";
        foreach ($products as $p) {
            $productListStr .= "{$p['id']} | {$p['kode']} | {$p['nama']}\n";
        }

        $prompt = "Tugas Anda adalah menganalisis foto produk yang dikirimkan oleh kasir dan mencocokkannya dengan daftar produk di bawah ini.\n";
        $prompt .= "Temukan semua produk yang ada di dalam foto tersebut (bisa berupa beberapa unit dari produk yang sama, atau beberapa produk berbeda).\n";
        $prompt .= "Untuk setiap produk yang berhasil Anda kenali dan temukan kecocokannya di dalam daftar:\n";
        $prompt .= "1. Temukan ID produk yang paling cocok dari daftar.\n";
        $prompt .= "2. Hitung jumlah unit (quantity/qty) produk tersebut yang tampak jelas di foto.\n";
        $prompt .= "3. Tentukan tingkat keyakinan (confidence) dan berikan penjelasan singkat (reason) mengapa Anda mencocokkannya.\n\n";
        $prompt .= "Jika tidak ada produk dari daftar yang cocok sama sekali di foto, kembalikan daftar matches kosong.\n\n";
        $prompt .= $productListStr;

        try {
            $models = [
                'gemini-2.5-flash',
                'gemini-2.0-flash',
                'gemini-1.5-flash'
            ];

            $response = null;
            $lastErrorMessage = '';

            foreach ($models as $model) {
                try {
                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                    ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}", [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt],
                                    [
                                        'inlineData' => [
                                            'mimeType' => $mimeType,
                                            'data' => $rawBase64
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'responseMimeType' => 'application/json',
                            'responseSchema' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'matches' => [
                                        'type' => 'ARRAY',
                                        'description' => 'Daftar kecocokan produk yang berhasil diidentifikasi di foto.',
                                        'items' => [
                                            'type' => 'OBJECT',
                                            'properties' => [
                                                'matched_id' => [
                                                    'type' => 'INTEGER',
                                                    'description' => 'ID produk yang cocok dari daftar produk.'
                                                ],
                                                'qty' => [
                                                    'type' => 'INTEGER',
                                                    'description' => 'Jumlah barang jenis ini yang terdeteksi di foto. Minimal 1.'
                                                ],
                                                'confidence' => [
                                                    'type' => 'NUMBER',
                                                    'description' => 'Tingkat keyakinan/probabilitas kecocokan antara 0.0 hingga 1.0.'
                                                ],
                                                'reason' => [
                                                    'type' => 'STRING',
                                                    'description' => 'Alasan singkat mengapa produk ini dipilih beserta deteksi kuantitasnya.'
                                                ]
                                            ],
                                            'required' => ['matched_id', 'qty', 'confidence', 'reason']
                                        ]
                                    ]
                                ],
                                'required' => ['matches']
                            ]
                        ]
                    ]);

                    if ($response->successful()) {
                        break;
                    } else {
                        $errData = $response->json();
                        $lastErrorMessage = $errData['error']['message'] ?? $response->body();
                        Log::warning("Gemini model {$model} failed: " . $lastErrorMessage);
                    }
                } catch (\Exception $e) {
                    $lastErrorMessage = $e->getMessage();
                    Log::warning("Gemini request for model {$model} failed: " . $lastErrorMessage);
                }
            }

            if (!$response || !$response->successful()) {
                throw new \Exception('Gagal menghubungi Gemini API setelah mencoba beberapa model (2.5, 2.0, 1.5). Error terakhir: ' . $lastErrorMessage);
            }

            $result = $response->json();
            $textResponse = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (empty($textResponse)) {
                Log::warning('Gemini API returned an empty content candidate.');
                return null;
            }

            $parsedData = json_decode($textResponse, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse Gemini response JSON: ' . $textResponse);
                return null;
            }

            return $parsedData;

        } catch (\Exception $e) {
            Log::error('GeminiService Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
