<?php

namespace Services;

class GeminiService {
    private string $apiKey;
    private string $model = 'gemini-3.6-flash';
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct() {
        $this->apiKey = '';
        $envPath = __DIR__ . '/../../../.env';
        
        if (file_exists($envPath)) {
            // Satır satır güvenli okuma ve özel karakter hatalarını önleme
            $env = parse_ini_file($envPath, false, INI_SCANNER_RAW);
            if ($env && isset($env['GEMINI_API_KEY'])) {
                $this->apiKey = trim($env['GEMINI_API_KEY'], "\"' ");
            }
        }
        
        if (getenv('GEMINI_API_KEY')) {
            $this->apiKey = getenv('GEMINI_API_KEY');
        }

        if (empty($this->apiKey) || $this->apiKey === 'YOUR_API_KEY_HERE') {
            throw new \Exception("Sunucu yapılandırma hatası: .env dosyası içinde GEMINI_API_KEY tanımlı değil.");
        }
    }



    private function makeRequest(string $prompt, ?string $systemInstruction = null, ?array $responseSchema = null, bool $jsonMode = true): array {
        if (empty($this->apiKey)) {
            throw new \Exception("GEMINI_API_KEY eksik. Lütfen .env dosyasına ekleyin veya istekte gönderin.");
        }

        $url = $this->baseUrl . $this->model . ':generateContent?key=' . $this->apiKey;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 8192
            ]
        ];

        if ($systemInstruction) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ];
        }

        if ($jsonMode) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
            if ($responseSchema) {
                $payload['generationConfig']['responseSchema'] = $responseSchema;
            }
        }

        $maxRetries = 3;
        $retryDelay = 2; // Saniye
        $response = '';
        $httpCode = 0;
        $error = '';

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            error_log("[CMD GEMINI] {$this->model} modeline istek atılıyor... (Deneme: {$attempt})");
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                if ($attempt < $maxRetries) {
                    sleep($retryDelay);
                    $retryDelay *= 2;
                    continue;
                }
                throw new \Exception("cURL Hatası: " . $error);
            }

            if ($httpCode >= 400) {
                if ($httpCode === 429 || $httpCode >= 500) {
                    if ($attempt < $maxRetries) {
                        sleep($retryDelay);
                        $retryDelay *= 2;
                        continue;
                    }
                }
                $decodedError = json_decode($response, true);
                $msg = $decodedError['error']['message'] ?? $response;
                throw new \Exception("Gemini API Hatası (HTTP $httpCode): " . $msg);
            }

            error_log("[CMD GEMINI] Yanıt alındı. HTTP Kodu: {$httpCode}, Yanıt Boyutu: " . strlen((string)$response) . " bayt.");
            break; // Başarılı, döngüden çık
        }

        $decoded = json_decode($response, true);
        
        if (!isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception("Gemini API'den geçersiz yanıt alındı: " . $response);
        }

        $text = $decoded['candidates'][0]['content']['parts'][0]['text'];
        
        if ($jsonMode) {
            $json = json_decode($text, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Markdown formatında dönmüşse ayıklamaya çalış (Örn: ```json ... ```)
                if (preg_match('/```json\s*(.*?)\s*```/s', $text, $matches)) {
                    $json = json_decode($matches[1], true);
                }
                
                if (!$json) {
                    throw new \Exception("Gemini API geçerli bir JSON döndürmedi: " . $text);
                }
            }
            return $json;
        }

        return ['text' => $text];
    }

    /**
     * Aşama A: Semantik Keşif (Semantic Discovery)
     */
    public function discoverSemantics(string $text): array {
        $systemInstruction = "Aşağıdaki metni analiz et ve SEO (Arama Motoru Optimizasyonu) açısından odak anahtar kelimesini (target keyword), arama niyetini (search intent) ve yan anahtar kelimeleri (secondary keywords / LSI) çıkar.";
        
        $prompt = "Metin:\n" . $text;

        $responseSchema = [
            'type' => 'OBJECT',
            'properties' => [
                'target_keyword' => ['type' => 'STRING', 'description' => 'Odak anahtar kelime'],
                'search_intent' => ['type' => 'STRING', 'description' => 'Arama niyeti (bilgi edinme, satın alma vb.)'],
                'secondary_keywords' => [
                    'type' => 'ARRAY',
                    'items' => ['type' => 'STRING'],
                    'description' => 'Yan anahtar kelimeler listesi'
                ]
            ],
            'required' => ['target_keyword', 'search_intent', 'secondary_keywords']
        ];

        return $this->makeRequest($prompt, $systemInstruction, $responseSchema, true);
    }

    /**
     * Aşama B: AI Boyutlarının Üretilmesi
     */
    public function generateExpertInsights(array $telemetryData, string $rawText): array {
        $systemInstruction = "Sen Kıdemli bir AI Entegrasyon, SEO Stratejisti ve Profesyonel İçerik Editörüsün.\n"
                . "Aşağıda PHP motoru tarafından hesaplanmış %100 deterministik SEO metriklerini içeren Telemetri verisi ve yazarın orijinal ham metni bulunmaktadır.\n"
                . "Bu verilere dayanarak, aşağıdaki 4 boyutu (Analiz, Strateji, Entegrasyon, Otomatik Düzeltme) oluştururken şu kurallara KESİNLİKLE uy:\n\n"
                . "KURALLAR:\n"
                . "1. ANALİZ BOYUTU (Düz Metin Felsefesi): Kullanıcıya asla 'HTML başlık etiketi eksik', 'Markdown etiketi yok' gibi teknik kodlama eleştirileri yapma. Metni profesyonel bir içerik editörü gözüyle; metin akışı, anahtar kelime yerleşimi, başlıkların dikkat çekiciliği ve okunabilirlik üzerinden değerlendir.\n"
                . "2. OTOMATİK DÜZELTME BOYUTU (Minimalist ve Cerrahi Optimizasyon):\n"
                . "   - YAZARIN ORİJİNAL METNİNİ KORU: Sıfırdan yeni bir makale veya farklı paragraflar YAZMA. Yazarın orijinal cümlelerini, düşüncelerini, örneklerini ve üslubunu en az %85-90 oranında aynen muhafaza et.\n"
                . "   - NOKTA ATIŞI MÜDAHALE YAP: Sadece ve sadece raporda tespit edilen eksiklikleri gider. Eksik olan hedef anahtar kelimeleri ve yan kavramları, cümlenin doğal akışını bozmadan orijinal paragrafların uygun yerlerine yerleştir. Eğer çok uzun (monolitik) bir paragraf varsa, onu anlam bütünlüğünü bozmadan sadece ikiye böl. Anlatımı gereksiz yere uzatma veya kısaltma; yazarın metniyle birebir aynı yapıda ilerle.\n"
                . "   - TEMİZ DÜZ METİN ÇIKTISI (Plain Text): 'yeniden_yazilmis_metin' çıktısında KESİNLİKLE markdown sembolleri (#, ##, ###, **, *, _, -) KULLANMA. Başlıkları sadece bir satır boşluk bırakıp büyük harfle veya doğal başlık formatında yaz. Metin, doğrudan Word veya CMS editörüne yapıştırılacak %100 saf, pürüzsüz ve temiz düz metin olmalıdır.";

        $telemetryJson = json_encode($telemetryData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        $prompt = "Telemetri Verisi:\n" . $telemetryJson . "\n\n"
                . "Ham Metin:\n" . $rawText;

        $responseSchema = [
            'type' => 'OBJECT',
            'properties' => [
                'analiz' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'ozet' => ['type' => 'STRING', 'description' => 'Müşterinin anlayacağı yönetici özeti.'],
                        'sorunlar' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING'], 'description' => 'Tespit edilen sorunlar'],
                        'saglik_skoru' => ['type' => 'INTEGER', 'description' => '0-100 arası SEO sağlık skoru']
                    ],
                    'required' => ['ozet', 'sorunlar', 'saglik_skoru']
                ],
                'strateji' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'hedef_yogunluklar' => ['type' => 'STRING', 'description' => 'Anahtar kelime yoğunluk hedefleri.'],
                        'semantik_bosluklar' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING'], 'description' => 'Eksik kavramlar'],
                        'eklenecek_kelime_adetleri' => [
                            'type' => 'OBJECT',
                            'description' => 'Anahtar kelime ve eklenecek adet eşleştirmesi, örn {"kelime": 3}'
                        ],
                        'paa_hedefleri' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING'], 'description' => 'İlgili Sorular (People Also Ask)']
                    ],
                    'required' => ['hedef_yogunluklar', 'semantik_bosluklar', 'eklenecek_kelime_adetleri', 'paa_hedefleri']
                ],
                'entegrasyon' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'adim_adim_rehber' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING'], 'description' => 'Adım adım entegrasyon rehberi']
                    ],
                    'required' => ['adim_adim_rehber']
                ],
                'otomatik_duzeltme' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'yeniden_yazilmis_metin' => ['type' => 'STRING', 'description' => 'Metnin %85-90 orijinal hali korunmuş, sadece eksiklerin eklendiği, markdown içermeyen saf düz metin hali.']
                    ],
                    'required' => ['yeniden_yazilmis_metin']
                ]
            ],
            'required' => ['analiz', 'strateji', 'entegrasyon', 'otomatik_duzeltme']
        ];

        return $this->makeRequest($prompt, $systemInstruction, $responseSchema, true);
    }
}


