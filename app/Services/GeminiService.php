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
        $prompt .= "Cari produk dari daftar yang paling mirip atau persis sama dengan produk di foto tersebut.\n";
        $prompt .= "Jika ada beberapa kecocokan, pilih satu yang memiliki tingkat kecocokan tertinggi.\n";
        $prompt .= "Jika tidak ada produk yang cocok sama sekali dari daftar, kembalikan null untuk matched_id.\n\n";
        $prompt .= $productListStr;

        try {
            // We call Gemini 2.5 Flash as it is the fastest, cheapest, and supports multimodal inputs + structured outputs.
            // If the user prefers, we can also use gemini-2.0-flash as a fallback.
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$this->apiKey}", [
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
                            'matched_id' => [
                                'type' => 'INTEGER',
                                'description' => 'ID produk yang cocok dari daftar produk. Jika tidak ada kecocokan, kembalikan null.'
                            ],
                            'matched_kode' => [
                                'type' => 'STRING',
                                'description' => 'Kode (barcode) produk yang cocok. Jika tidak ada kecocokan, kembalikan string kosong atau null.'
                            ],
                            'matched_name' => [
                                'type' => 'STRING',
                                'description' => 'Nama produk yang cocok.'
                            ],
                            'confidence' => [
                                'type' => 'NUMBER',
                                'description' => 'Tingkat keyakinan/probabilitas kecocokan antara 0.0 hingga 1.0.'
                            ],
                            'reason' => [
                                'type' => 'STRING',
                                'description' => 'Alasan singkat dalam bahasa Indonesia mengapa produk ini dipilih.'
                            ]
                        ],
                        'required' => ['matched_id', 'matched_kode', 'matched_name', 'confidence', 'reason']
                    ]
                ]
            ]);

            if ($response->failed()) {
                Log::error('Gemini API Request Failed: ' . $response->body());
                throw new \Exception('Gagal menghubungi Gemini API: ' . ($response->json('error.message') ?? 'Unknown error'));
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
