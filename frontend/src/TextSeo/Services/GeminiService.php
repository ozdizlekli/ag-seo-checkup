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



    private function makeRequest(string $prompt, bool $jsonMode = true): array {
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
                'temperature' => 0.2
            ]
        ];

        if ($jsonMode) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        error_log("[CMD GEMINI] " . $this->model . " modeline istek atılıyor...");
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        error_log("[CMD GEMINI] Yanıt alındı. HTTP Kodu: " . $httpCode . ", Yanıt Boyutu: " . strlen((string)$response) . " bayt.");

        if ($error) {
            throw new \Exception("cURL Hatası: " . $error);
        }

        if ($httpCode >= 400) {
            $decodedError = json_decode($response, true);
            $msg = $decodedError['error']['message'] ?? $response;
            throw new \Exception("Gemini API Hatası (HTTP $httpCode): " . $msg);
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
        $prompt = "Aşağıdaki metni analiz et ve SEO (Arama Motoru Optimizasyonu) açısından odak anahtar kelimesini (target keyword), arama niyetini (search intent) ve yan anahtar kelimeleri (secondary keywords / LSI) çıkar.\n\n"
                . "Yanıtı SADECE aşağıdaki JSON formatında ver:\n"
                . "{\n"
                . "  \"target_keyword\": \"ornek odak kelime\",\n"
                . "  \"search_intent\": \"bilgi edinme / satin alma vb.\",\n"
                . "  \"secondary_keywords\": [\"yan kelime 1\", \"yan kelime 2\"]\n"
                . "}\n\n"
                . "Metin:\n" . $text;

        return $this->makeRequest($prompt, true);
    }

    /**
     * Aşama B: AI Boyutlarının Üretilmesi
     */
    public function generateExpertInsights(array $telemetryData, string $rawText): array {
        $telemetryJson = json_encode($telemetryData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        $prompt = "Sen Kıdemli bir AI Entegrasyon, SEO Stratejisti ve Profesyonel İçerik Editörüsün.\n"
                . "Aşağıda PHP motoru tarafından hesaplanmış %100 deterministik SEO metriklerini içeren Telemetri verisi ve yazarın orijinal ham metni bulunmaktadır.\n"
                . "Bu verilere dayanarak, aşağıdaki 4 boyutu (Analiz, Strateji, Entegrasyon, Otomatik Düzeltme) oluştururken şu kurallara KESİNLİKLE uy:\n\n"
                . "KURALLAR:\n"
                . "1. ANALİZ BOYUTU (Düz Metin Felsefesi): Kullanıcıya asla 'HTML başlık etiketi eksik', 'Markdown etiketi yok' gibi teknik kodlama eleştirileri yapma. Metni profesyonel bir içerik editörü gözüyle; metin akışı, anahtar kelime yerleşimi, başlıkların dikkat çekiciliği ve okunabilirlik üzerinden değerlendir.\n"
                . "2. OTOMATİK DÜZELTME BOYUTU (Minimalist ve Cerrahi Optimizasyon):\n"
                . "   - YAZARIN ORİJİNAL METNİNİ KORU: Sıfırdan yeni bir makale veya farklı paragraflar YAZMA. Yazarın orijinal cümlelerini, düşüncelerini, örneklerini ve üslubunu en az %85-90 oranında aynen muhafaza et.\n"
                . "   - NOKTA ATIŞI MÜDAHALE YAP: Sadece ve sadece raporda tespit edilen eksiklikleri gider. Eksik olan hedef anahtar kelimeleri ve yan kavramları, cümlenin doğal akışını bozmadan orijinal paragrafların uygun yerlerine yerleştir. Eğer çok uzun (monolitik) bir paragraf varsa, onu anlam bütünlüğünü bozmadan sadece ikiye böl. Anlatımı gereksiz yere uzatma veya kısaltma; yazarın metniyle birebir aynı yapıda ilerle.\n"
                . "   - TEMİZ DÜZ METİN ÇIKTISI (Plain Text): 'yeniden_yazilmis_metin' çıktısında KESİNLİKLE markdown sembolleri (#, ##, ###, **, *, _, -) KULLANMA. Başlıkları sadece bir satır boşluk bırakıp büyük harfle veya doğal başlık formatında yaz. Metin, doğrudan Word veya CMS editörüne yapıştırılacak %100 saf, pürüzsüz ve temiz düz metin olmalıdır.\n\n"
                . "Telemetri Verisi:\n" . $telemetryJson . "\n\n"
                . "Ham Metin:\n" . $rawText . "\n\n"
                . "Yanıtı SADECE aşağıdaki JSON formatında ver. JSON haricinde hiçbir markdown (```) veya açıklama ekleme:\n"
                . "{\n"
                . "  \"analiz\": {\n"
                . "    \"ozet\": \"Müşterinin anlayacağı yönetici özeti.\",\n"
                . "    \"sorunlar\": [\"Tespit edilen sorun 1\", \"Tespit edilen sorun 2\"],\n"
                . "    \"saglik_skoru\": 75\n"
                . "  },\n"
                . "  \"strateji\": {\n"
                . "    \"hedef_yogunluklar\": \"Anahtar kelime yoğunluk hedefleri.\",\n"
                . "    \"semantik_bosluklar\": [\"Eksik kavram 1\", \"Eksik kavram 2\"],\n"
                . "    \"eklenecek_kelime_adetleri\": {\"ornek_kelime_1\": 3, \"ornek_kelime_2\": 1},\n"
                . "    \"paa_hedefleri\": [\"İlgili Soru 1\", \"İlgili Soru 2\"]\n"
                . "  },\n"
                . "  \"entegrasyon\": {\n"
                . "    \"adim_adim_rehber\": [\n"
                . "      \"Adım 1: X başlığına Y kelimesini ekle.\",\n"
                . "      \"Adım 2: 3. paragrafı ikiye böl ve Z kelimesini öne yükle.\"\n"
                . "    ]\n"
                . "  },\n"
                . "  \"otomatik_duzeltme\": {\n"
                . "    \"yeniden_yazilmis_metin\": \"Metnin %85-90 orijinal hali korunmuş, sadece eksiklerin eklendiği, markdown içermeyen saf düz metin hali.\"\n"
                . "  }\n"
                . "}";

        return $this->makeRequest($prompt, true);
    }
}


