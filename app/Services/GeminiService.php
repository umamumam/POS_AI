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

        $prompt = "TUGAS UTAMA:\n";
        $prompt .= "Anda adalah AI kasir POS yang sangat teliti. Tugas Anda adalah menganalisis foto produk yang dikirimkan oleh kasir dan mencocokkannya secara AKURAT dengan daftar produk di database di bawah ini.\n\n";
        $prompt .= "PETUNJUK ANALISIS SANGAT KETAT:\n";
        $prompt .= "1. **Perbedaan Ukuran (Besar vs Kecil, Cup vs Dus, Berat Gram/ml)**: Perhatikan ukuran fisik dan berat bersih yang tertera di kemasan. Produk dengan merek sama tetapi ukuran berbeda (misal: Pop Mie Cup Kecil vs Pop Mie Cup Besar/Jumbo, Mie Sedaap Cup vs Dus) adalah produk yang BERBEDA dengan ID yang berbeda di database. Jangan satukan mereka di bawah ID yang sama!\n";
        $prompt .= "2. **Varian Rasa & Warna Kemasan**: Perhatikan varian rasa (Soto, Kari, Ayam, Goreng, dll.) dan warna grafis kemasan. Warna dan tulisan yang berbeda menunjukkan rasa atau variasi yang berbeda. Cocokkan dengan nama produk di database yang mengandung kata kunci rasa tersebut.\n";
        $prompt .= "3. **Hitung Jumlah (Quantity) Secara Spesifik**: Hitung dengan tepat berapa unit yang terlihat untuk setiap varian produk secara individual di dalam foto.\n";
        $prompt .= "4. **Pencocokan ID yang Tepat**: Pilih ID produk dari daftar di bawah yang paling spesifik. Jika di foto ada 1 Pop Mie Besar dan 1 Pop Mie Kecil, maka Anda harus mengembalikan DUA entri di array matches (satu untuk ID Pop Mie Besar dengan qty 1, dan satu untuk ID Pop Mie Kecil dengan qty 1). Jangan digabungkan menjadi qty 2 di salah satu ID!\n";
        $prompt .= "5. **Produk Rentengan (Sachet Renteng)**: Perhatikan jika produk berupa rentengan (sachet panjang yang tersambung, biasanya kopi sachet, sampo sachet, atau bumbu penyedap). Satu deret renteng panjang yang saling menyatu dihitung sebagai **1 unit (1 renteng)**. Jangan menghitung jumlah sachet kecilnya sebagai kuantitas terpisah! Kecuali jika terdapat lebih dari satu renteng panjang terpisah yang dijajarkan atau ditumpuk, barulah dihitung sesuai jumlah rentengnya (misal: 2 renteng terpisah = qty 2).\n";
        $prompt .= "6. Jika tidak ada produk dari daftar yang cocok sama sekali, kembalikan daftar matches kosong.\n\n";
        $prompt .= $productListStr;

        try {
            $models = [
                'gemini-2.5-flash',
                'gemini-2.0-flash',
                'gemini-1.5-flash',
                'gemini-1.5-pro'
            ];

            $response = null;
            $lastErrorMessage = '';

            foreach ($models as $model) {
                try {
                    $payload = [
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
                    ];

                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                    ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}", $payload);

                    if ($response->successful()) {
                        break;
                    } else {
                        $errData = $response->json();
                        $lastErrorMessage = $errData['error']['message'] ?? $response->body();
                        Log::warning("Gemini model {$model} failed: " . $lastErrorMessage);

                        // Jika gagal karena skema atau generateContent tidak didukung, coba sekali lagi tanpa responseSchema
                        if (
                            str_contains(strtolower($lastErrorMessage), 'schema') || 
                            str_contains(strtolower($lastErrorMessage), 'not supported') || 
                            str_contains(strtolower($lastErrorMessage), 'not found')
                        ) {
                            Log::info("Retrying Gemini model {$model} without responseSchema...");
                            unset($payload['generationConfig']['responseSchema']);
                            
                            $response = Http::withHeaders([
                                'Content-Type' => 'application/json',
                            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}", $payload);

                            if ($response->successful()) {
                                break;
                            } else {
                                $errData = $response->json();
                                $lastErrorMessage = $errData['error']['message'] ?? $response->body();
                                Log::warning("Gemini model {$model} retry failed: " . $lastErrorMessage);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $lastErrorMessage = $e->getMessage();
                    Log::warning("Gemini request for model {$model} failed: " . $lastErrorMessage);
                }
            }

            $deepseekError = null;
            if (!$response || !$response->successful()) {
                // Gemini failed. Let's try DeepSeek API!
                $deepseekApiKey = env('DEEPSEEK_API_KEY') ?? 'sk-44e4fa1b746e4b26ab9473dbab855873';
                if (!empty($deepseekApiKey)) {
                    Log::info('Gemini failed. Attempting fallback to DeepSeek API...');
                    
                    $deepseekPrompt = $prompt . "\n\nCRITICAL: Anda HARUS membalas HANYA dengan format JSON yang valid, tanpa markdown wrapper (seperti ```json), tanpa penjelasan teks tambahan di luar JSON. Format JSON harus berupa objek dengan key 'matches' berisi array, di mana setiap item memiliki key: 'matched_id' (integer), 'qty' (integer), 'confidence' (float), dan 'reason' (string).";
                    
                    try {
                        $dsResponse = Http::withHeaders([
                            'Authorization' => "Bearer {$deepseekApiKey}",
                            'Content-Type' => 'application/json',
                        ])->post('https://api.deepseek.com/v1/chat/completions', [
                            'model' => 'deepseek-chat',
                            'messages' => [
                                [
                                    'role' => 'user',
                                    'content' => [
                                        [
                                            'type' => 'text',
                                            'text' => $deepseekPrompt
                                        ],
                                        [
                                            'type' => 'image_url',
                                            'image_url' => [
                                                'url' => "data:{$mimeType};base64,{$rawBase64}"
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            'response_format' => [
                                'type' => 'json_object'
                            ]
                        ]);

                        if ($dsResponse->successful()) {
                            $dsResult = $dsResponse->json();
                            $textResponse = $dsResult['choices'][0]['message']['content'] ?? null;
                            if (!empty($textResponse)) {
                                // Clean markdown formatting if any
                                $textResponse = preg_replace('/^```json\s*/i', '', $textResponse);
                                $textResponse = preg_replace('/```$/', '', $textResponse);
                                $textResponse = trim($textResponse);

                                $parsedData = json_decode($textResponse, true);
                                if (json_last_error() === JSON_ERROR_NONE && isset($parsedData['matches'])) {
                                    Log::info('Successfully processed with DeepSeek API.');
                                    return $parsedData;
                                }
                            }
                        } else {
                            $deepseekError = "Status " . $dsResponse->status() . ": " . $dsResponse->body();
                            Log::error('DeepSeek API failed: ' . $deepseekError);
                        }
                    } catch (\Exception $dsEx) {
                        $deepseekError = $dsEx->getMessage();
                        Log::error('DeepSeek API request exception: ' . $deepseekError);
                    }
                }
                
                $errMsg = 'Gagal menghubungi Gemini API setelah mencoba beberapa model (2.5, 2.0, 1.5, 1.5-pro). Error Gemini: ' . $lastErrorMessage;
                if ($deepseekError) {
                    $errMsg .= ' | Error DeepSeek Fallback: ' . $deepseekError;
                }
                throw new \Exception($errMsg);
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

    /**
     * Analyze voice/text commands to find matching products and quantities.
     */
    public function analyzeTextCommand(string $text, $products)
    {
        // Format daftar produk
        $productListStr = "Daftar Produk:\n";
        $productListStr .= "ID | Kode | Nama Produk\n";
        foreach ($products as $p) {
            $productListStr .= "{$p['id']} | {$p['kode']} | {$p['nama']}\n";
        }

        $prompt = "TUGAS UTAMA:\n";
        $prompt .= "Anda adalah AI kasir POS yang sangat cerdas dan teliti. Tugas Anda adalah memproses perintah suara/teks dari kasir dan mencocokkannya secara AKURAT dengan daftar produk di database di bawah ini.\n\n";
        $prompt .= "PETUNJUK ANALISIS SANGAT KETAT:\n";
        $prompt .= "1. Analisis kalimat kasir berikut: \"{$text}\". Temukan produk apa saja yang ingin dimasukkan ke keranjang belanja dan berapa jumlahnya (qty).\n";
        $prompt .= "2. **Pencocokan ID**: Temukan produk di daftar database di bawah yang paling cocok dengan nama produk yang diucapkan kasir.\n";
        $prompt .= "3. **Kuantitas (qty)**: Perhatikan kata bilangan yang menyertai nama produk (misal: 'dua', '2', '3 pcs', 'satu renteng'). Jika tidak disebutkan angka kuantitasnya, asumsikan qty adalah 1.\n";
        $prompt .= "4. **Produk Rentengan (Sachet Renteng)**: Jika kasir menyebutkan kata 'renteng' atau 'strip' (misal: 'kopi sachet satu renteng'), tentukan qty berdasarkan jumlah rentengnya (1 renteng = qty 1), jangan dihitung per sachet kecil!\n";
        $prompt .= "5. Kembalikan respons dalam format JSON objek dengan key 'matches' berisi array, di mana setiap item memiliki key: 'matched_id' (integer), 'qty' (integer), 'confidence' (float), dan 'reason' (string) berisi penjelasan singkat mengapa produk ini dipilih.\n\n";
        $prompt .= $productListStr;

        try {
            $models = [
                'gemini-2.5-flash',
                'gemini-2.0-flash',
                'gemini-1.5-flash',
                'gemini-1.5-pro'
            ];

            $response = null;
            $lastErrorMessage = '';

            foreach ($models as $model) {
                try {
                    $payload = [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt]
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
                                        'description' => 'Daftar produk yang cocok hasil analisis perintah suara.',
                                        'items' => [
                                            'type' => 'OBJECT',
                                            'properties' => [
                                                'matched_id' => [
                                                    'type' => 'INTEGER',
                                                    'description' => 'ID produk yang cocok.'
                                                ],
                                                'qty' => [
                                                    'type' => 'INTEGER',
                                                    'description' => 'Jumlah kuantitas produk.'
                                                ],
                                                'confidence' => [
                                                    'type' => 'NUMBER',
                                                    'description' => 'Tingkat keyakinan kecocokan (0.0 s/d 1.0).'
                                                ],
                                                'reason' => [
                                                    'type' => 'STRING',
                                                    'description' => 'Alasan pencocokan.'
                                                ]
                                            ],
                                            'required' => ['matched_id', 'qty', 'confidence', 'reason']
                                        ]
                                    ]
                                ],
                                'required' => ['matches']
                            ]
                        ]
                    ];

                    $response = \Illuminate\Support\Facades\Http::withHeaders([
                        'Content-Type' => 'application/json',
                    ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}", $payload);

                    if ($response->successful()) {
                        break;
                    } else {
                        $errData = $response->json();
                        $lastErrorMessage = $errData['error']['message'] ?? $response->body();
                        Log::warning("Gemini text model {$model} failed: " . $lastErrorMessage);

                        // Retry without responseSchema
                        if (
                            str_contains(strtolower($lastErrorMessage), 'schema') || 
                            str_contains(strtolower($lastErrorMessage), 'not supported') || 
                            str_contains(strtolower($lastErrorMessage), 'not found')
                        ) {
                            Log::info("Retrying Gemini text model {$model} without responseSchema...");
                            unset($payload['generationConfig']['responseSchema']);
                            
                            $response = \Illuminate\Support\Facades\Http::withHeaders([
                                'Content-Type' => 'application/json',
                            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}", $payload);

                            if ($response->successful()) {
                                break;
                            } else {
                                $errData = $response->json();
                                $lastErrorMessage = $errData['error']['message'] ?? $response->body();
                                Log::warning("Gemini text model {$model} retry failed: " . $lastErrorMessage);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $lastErrorMessage = $e->getMessage();
                    Log::warning("Gemini text request for model {$model} failed: " . $lastErrorMessage);
                }
            }

            $deepseekError = null;
            if (!$response || !$response->successful()) {
                // Gemini failed. Try DeepSeek text-only!
                $deepseekApiKey = env('DEEPSEEK_API_KEY') ?? 'sk-44e4fa1b746e4b26ab9473dbab855873';
                if (!empty($deepseekApiKey)) {
                    Log::info('Gemini text failed. Attempting fallback to DeepSeek API (text-only)...');
                    
                    $deepseekPrompt = $prompt . "\n\nCRITICAL: Anda HARUS membalas HANYA dengan format JSON yang valid, tanpa markdown wrapper (seperti ```json), tanpa penjelasan teks tambahan di luar JSON. Format JSON harus berupa objek dengan key 'matches' berisi array, di mana setiap item memiliki key: 'matched_id' (integer), 'qty' (integer), 'confidence' (float), dan 'reason' (string).";
                    
                    try {
                        $dsResponse = \Illuminate\Support\Facades\Http::withHeaders([
                            'Authorization' => "Bearer {$deepseekApiKey}",
                            'Content-Type' => 'application/json',
                        ])->post('https://api.deepseek.com/v1/chat/completions', [
                            'model' => 'deepseek-chat',
                            'messages' => [
                                [
                                    'role' => 'user',
                                    'content' => $deepseekPrompt
                                ]
                            ],
                            'response_format' => [
                                'type' => 'json_object'
                            ]
                        ]);

                        if ($dsResponse->successful()) {
                            $dsResult = $dsResponse->json();
                            $textResponse = $dsResult['choices'][0]['message']['content'] ?? null;
                            if (!empty($textResponse)) {
                                $textResponse = preg_replace('/^```json\s*/i', '', $textResponse);
                                $textResponse = preg_replace('/```$/', '', $textResponse);
                                $textResponse = trim($textResponse);

                                $parsedData = json_decode($textResponse, true);
                                if (json_last_error() === JSON_ERROR_NONE && isset($parsedData['matches'])) {
                                    Log::info('Successfully processed text command with DeepSeek API.');
                                    return $parsedData;
                                }
                            }
                        } else {
                            $deepseekError = "Status " . $dsResponse->status() . ": " . $dsResponse->body();
                            Log::error('DeepSeek text API failed: ' . $deepseekError);
                        }
                    } catch (\Exception $dsEx) {
                        $deepseekError = $dsEx->getMessage();
                        Log::error('DeepSeek text API request exception: ' . $deepseekError);
                    }
                }
                
                $errMsg = 'Gagal memproses suara/teks dengan Gemini. Error: ' . $lastErrorMessage;
                if ($deepseekError) {
                    $errMsg .= ' | DeepSeek Error: ' . $deepseekError;
                }
                throw new \Exception($errMsg);
            }

            $result = $response->json();
            $textResponse = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (empty($textResponse)) {
                return null;
            }

            $parsedData = json_decode($textResponse, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            return $parsedData;

        } catch (\Exception $e) {
            Log::error('GeminiService analyzeTextCommand Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
