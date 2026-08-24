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
            // SatÄ±r satÄ±r gÃ¼venli okuma ve Ã¶zel karakter hatalarÄ±nÄ± Ã¶nleme
            $env = parse_ini_file($envPath, false, INI_SCANNER_RAW);
            if ($env && isset($env['GEMINI_API_KEY'])) {
                $this->apiKey = trim($env['GEMINI_API_KEY'], "\"' ");
            }
        }
        
        if (getenv('GEMINI_API_KEY')) {
            $this->apiKey = getenv('GEMINI_API_KEY');
        }

        if (empty($this->apiKey) || $this->apiKey === 'YOUR_API_KEY_HERE') {
            throw new \Exception("Sunucu yapÄ±landÄ±rma hatasÄ±: .env dosyasÄ± iÃ§inde GEMINI_API_KEY tanÄ±mlÄ± deÄŸil.");
        }
    }



    private function makeRequest(string $prompt, bool $jsonMode = true): array {
        if (empty($this->apiKey)) {
            throw new \Exception("GEMINI_API_KEY eksik. LÃ¼tfen .env dosyasÄ±na ekleyin veya istekte gÃ¶nderin.");
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

        error_log("[CMD GEMINI] " . $this->model . " modeline istek atÄ±lÄ±yor...");
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        error_log("[CMD GEMINI] YanÄ±t alÄ±ndÄ±. HTTP Kodu: " . $httpCode . ", YanÄ±t Boyutu: " . strlen((string)$response) . " bayt.");

        if ($error) {
            throw new \Exception("cURL HatasÄ±: " . $error);
        }

        if ($httpCode >= 400) {
            $decodedError = json_decode($response, true);
            $msg = $decodedError['error']['message'] ?? $response;
            throw new \Exception("Gemini API HatasÄ± (HTTP $httpCode): " . $msg);
        }

        $decoded = json_decode($response, true);
        
        if (!isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception("Gemini API'den geÃ§ersiz yanÄ±t alÄ±ndÄ±: " . $response);
        }

        $text = $decoded['candidates'][0]['content']['parts'][0]['text'];
        
        if ($jsonMode) {
            $json = json_decode($text, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Markdown formatÄ±nda dÃ¶nmÃ¼ÅŸse ayÄ±klamaya Ã§alÄ±ÅŸ (Ã–rn: ```json ... ```)
                if (preg_match('/```json\s*(.*?)\s*```/s', $text, $matches)) {
                    $json = json_decode($matches[1], true);
                }
                
                if (!$json) {
                    throw new \Exception("Gemini API geÃ§erli bir JSON dÃ¶ndÃ¼rmedi: " . $text);
                }
            }
            return $json;
        }

        return ['text' => $text];
    }

    /**
     * AÅŸama A: Semantik KeÅŸif (Semantic Discovery)
     */
    public function discoverSemantics(string $text): array {
        $prompt = "AÅŸaÄŸÄ±daki metni analiz et ve SEO (Arama Motoru Optimizasyonu) aÃ§Ä±sÄ±ndan odak anahtar kelimesini (target keyword), arama niyetini (search intent) ve yan anahtar kelimeleri (secondary keywords / LSI) Ã§Ä±kar.\n\n"
                . "YanÄ±tÄ± SADECE aÅŸaÄŸÄ±daki JSON formatÄ±nda ver:\n"
                . "{\n"
                . "  \"target_keyword\": \"ornek odak kelime\",\n"
                . "  \"search_intent\": \"bilgi edinme / satin alma vb.\",\n"
                . "  \"secondary_keywords\": [\"yan kelime 1\", \"yan kelime 2\"]\n"
                . "}\n\n"
                . "Metin:\n" . $text;

        return $this->makeRequest($prompt, true);
    }

    /**
     * AÅŸama B: AI BoyutlarÄ±nÄ±n Ãœretilmesi
     */
    public function generateExpertInsights(array $telemetryData, string $rawText): array {
        $telemetryJson = json_encode($telemetryData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        $prompt = "Sen KÄ±demli bir AI Entegrasyon, SEO Stratejisti ve Profesyonel Ä°Ã§erik EditÃ¶rÃ¼sÃ¼n.\n"
                . "AÅŸaÄŸÄ±da PHP motoru tarafÄ±ndan hesaplanmÄ±ÅŸ %100 deterministik SEO metriklerini iÃ§eren Telemetri verisi ve yazarÄ±n orijinal ham metni bulunmaktadÄ±r.\n"
                . "Bu verilere dayanarak, aÅŸaÄŸÄ±daki 4 boyutu (Analiz, Strateji, Entegrasyon, Otomatik DÃ¼zeltme) oluÅŸtururken ÅŸu kurallara KESÄ°NLÄ°KLE uy:\n\n"
                . "KURALLAR:\n"
                . "1. ANALÄ°Z BOYUTU (DÃ¼z Metin Felsefesi): KullanÄ±cÄ±ya asla 'HTML baÅŸlÄ±k etiketi eksik', 'Markdown etiketi yok' gibi teknik kodlama eleÅŸtirileri yapma. Metni profesyonel bir iÃ§erik editÃ¶rÃ¼ gÃ¶zÃ¼yle; metin akÄ±ÅŸÄ±, anahtar kelime yerleÅŸimi, baÅŸlÄ±klarÄ±n dikkat Ã§ekiciliÄŸi ve okunabilirlik Ã¼zerinden deÄŸerlendir.\n"
                . "2. OTOMATÄ°K DÃœZELTME BOYUTU (Minimalist ve Cerrahi Optimizasyon):\n"
                . "   - YAZARIN ORÄ°JÄ°NAL METNÄ°NÄ° KORU: SÄ±fÄ±rdan yeni bir makale veya farklÄ± paragraflar YAZMA. YazarÄ±n orijinal cÃ¼mlelerini, dÃ¼ÅŸÃ¼ncelerini, Ã¶rneklerini ve Ã¼slubunu en az %85-90 oranÄ±nda aynen muhafaza et.\n"
                . "   - NOKTA ATIÅI MÃœDAHALE YAP: Sadece ve sadece raporda tespit edilen eksiklikleri gider. Eksik olan hedef anahtar kelimeleri ve yan kavramlarÄ±, cÃ¼mlenin doÄŸal akÄ±ÅŸÄ±nÄ± bozmadan orijinal paragraflarÄ±n uygun yerlerine yerleÅŸtir. EÄŸer Ã§ok uzun (monolitik) bir paragraf varsa, onu anlam bÃ¼tÃ¼nlÃ¼ÄŸÃ¼nÃ¼ bozmadan sadece ikiye bÃ¶l. AnlatÄ±mÄ± gereksiz yere uzatma veya kÄ±saltma; yazarÄ±n metniyle birebir aynÄ± yapÄ±da ilerle.\n"
                . "   - TEMÄ°Z DÃœZ METÄ°N Ã‡IKTISI (Plain Text): 'yeniden_yazilmis_metin' Ã§Ä±ktÄ±sÄ±nda KESÄ°NLÄ°KLE markdown sembolleri (#, ##, ###, **, *, _, -) KULLANMA. BaÅŸlÄ±klarÄ± sadece bir satÄ±r boÅŸluk bÄ±rakÄ±p bÃ¼yÃ¼k harfle veya doÄŸal baÅŸlÄ±k formatÄ±nda yaz. Metin, doÄŸrudan Word veya CMS editÃ¶rÃ¼ne yapÄ±ÅŸtÄ±rÄ±lacak %100 saf, pÃ¼rÃ¼zsÃ¼z ve temiz dÃ¼z metin olmalÄ±dÄ±r.\n\n"
                . "Telemetri Verisi:\n" . $telemetryJson . "\n\n"
                . "Ham Metin:\n" . $rawText . "\n\n"
                . "YanÄ±tÄ± SADECE aÅŸaÄŸÄ±daki JSON formatÄ±nda ver. JSON haricinde hiÃ§bir markdown (```) veya aÃ§Ä±klama ekleme:\n"
                . "{\n"
                . "  \"analiz\": {\n"
                . "    \"ozet\": \"MÃ¼ÅŸterinin anlayacaÄŸÄ± yÃ¶netici Ã¶zeti.\",\n"
                . "    \"sorunlar\": [\"Tespit edilen sorun 1\", \"Tespit edilen sorun 2\"],\n"
                . "    \"saglik_skoru\": 75\n"
                . "  },\n"
                . "  \"strateji\": {\n"
                . "    \"hedef_yogunluklar\": \"Anahtar kelime yoÄŸunluk hedefleri.\",\n"
                . "    \"semantik_bosluklar\": [\"Eksik kavram 1\", \"Eksik kavram 2\"],\n"
                . "    \"eklenecek_kelime_adetleri\": {\"ornek_kelime_1\": 3, \"ornek_kelime_2\": 1},\n"
                . "    \"paa_hedefleri\": [\"Ä°lgili Soru 1\", \"Ä°lgili Soru 2\"]\n"
                . "  },\n"
                . "  \"entegrasyon\": {\n"
                . "    \"adim_adim_rehber\": [\n"
                . "      \"AdÄ±m 1: X baÅŸlÄ±ÄŸÄ±na Y kelimesini ekle.\",\n"
                . "      \"AdÄ±m 2: 3. paragrafÄ± ikiye bÃ¶l ve Z kelimesini Ã¶ne yÃ¼kle.\"\n"
                . "    ]\n"
                . "  },\n"
                . "  \"otomatik_duzeltme\": {\n"
                . "    \"yeniden_yazilmis_metin\": \"Metnin %85-90 orijinal hali korunmuÅŸ, sadece eksiklerin eklendiÄŸi, markdown iÃ§ermeyen saf dÃ¼z metin hali.\"\n"
                . "  }\n"
                . "}";

        return $this->makeRequest($prompt, true);
    }
}


