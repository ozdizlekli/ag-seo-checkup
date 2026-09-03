<?php

require_once __DIR__ . '/../config.php';

class GeminiClient {

    public $debugTrace = [];
    public $warnings = [];

    /**
     * Gemini API ile iletişim kurar.
     * 
     * @param string $prompt Gönderilecek prompt metni
     * @param string $systemInstruction Sistem talimatı
     * @param bool $jsonMode JSON modunda (application/json) yanıt istenip istenmediği
     * @param string $stepName Hata ayıklama için adım adı
     * @return string API yanıt metni (hata durumunda boş string)
     */
    private function callAPI(string $prompt, string $systemInstruction = '', bool $jsonMode = true, string $stepName = 'Bilinmeyen Adım'): string {
        $url = GEMINI_ENDPOINT . GEMINI_MODEL . ':generateContent';

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 8192
            ]
        ];

        if ($systemInstruction !== '') {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]]
            ];
        }

        if ($jsonMode) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        $maxAttempts = 3;
        $attempt = 0;
        $lastError = '';
        $response = '';
        $httpCode = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
            
            $headers = [
                'Content-Type: application/json',
                'x-goog-api-key: ' . GEMINI_API_KEY
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            // Timeout sabiti tanımlı değilse varsayılan 60 saniye olarak ayarlanır
            $timeout = defined('GEMINI_TIMEOUT') ? GEMINI_TIMEOUT : 60;
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // Windows IPv6 kilitlenmesini önler
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);          // İlk bağlantı kurma timeout'u 10 sn

            // cURL isteği uzun sürebileceğinden PHP'nin zaman sayacını tazele
            set_time_limit(300);

            $response = curl_exec($ch);
            
            $errNo = curl_errno($ch);
            $errMsg = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);

            // Başarılı durum
            if ($errNo === 0 && $httpCode === 200) {
                $lastError = ''; // Hata yok
                break;
            }

            // Hata tespiti
            if ($errNo) {
                $lastError = "cURL ERROR ($errNo): $errMsg";
            } else {
                $lastError = "HTTP ERROR $httpCode: $response";
            }

            // Retry gerektiren hatalar (cURL hatası veya HTTP 429 / 503)
            if ($errNo || $httpCode === 429 || $httpCode === 503) {
                if ($attempt < $maxAttempts) {
                    error_log("Gemini API geçici olarak meşgul (HTTP $httpCode). $attempt. yeniden deneme yapılıyor...");
                    if ($attempt === 1) {
                        sleep(2);
                    } elseif ($attempt === 2) {
                        sleep(4);
                    }
                    continue;
                }
            } else {
                // Diğer HTTP hatalarında (ör. 400, 401, 500 vb.) retry yapma
                break;
            }
        }

        if ($lastError !== '') {
            error_log('Gemini API Hatası: ' . $lastError);
            $this->debugTrace[] = [
                'step' => $stepName,
                'system' => $systemInstruction,
                'prompt' => $prompt,
                'response' => $lastError,
                'attempts' => $attempt
            ];
            return '';
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Gemini Response Parse Hatası: ' . json_last_error_msg());
            $this->debugTrace[] = [
                'step' => $stepName,
                'system' => $systemInstruction,
                'prompt' => $prompt,
                'response' => 'JSON PARSE ERROR: ' . json_last_error_msg(),
                'attempts' => $attempt
            ];
            return '';
        }

        $responseText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        $this->debugTrace[] = [
            'step' => $stepName,
            'system' => $systemInstruction,
            'prompt' => $prompt,
            'response' => $responseText,
            'attempts' => $attempt
        ];

        return $responseText;
    }

    /**
     * Sayfa verilerini kullanarak anahtar kelime keşfi yapar. (Aşama A)
     *
     * @param string $title
     * @param string $description
     * @param array $headings
     * @param string $bodyText
     * @return array
     */
    public function discoverKeywords(string $title, string $description, array $headings, string $bodyText): array {
        $systemInstruction = "Sen Türkçe SEO ve içerik analizi uzmanısın. Sayfanın dilini otomatik algıla ama yanıtını her zaman Türkçe ver. Sadece JSON formatında yanıt ver, başka hiçbir açıklama ekleme. Türkçede yaygın kullanılan İngilizce terimler varsa (squat, deadlift, SEO, blog, influencer, e-ticaret vb.) onları da Türkçe bağlamında değerlendir, atlamadan analiz et.";
    
        $headingsStr = "";
        foreach ($headings as $heading) {
            $tag = strtoupper($heading['tag'] ?? '');
            $text = $heading['text'] ?? '';
            if ($tag !== '' && $text !== '') {
                $headingsStr .= "{$tag}: {$text}\n";
            }
        }

        $prompt = <<<EOT
Aşağıda bir web sayfasının başlığı, meta açıklaması, başlıkları ve gövde metni var.

Bu sayfanın:
1. Ana odak anahtar kelimesini belirle (tek bir anahtar kelime veya kelime grubu, Türkçe olmalı)
2. 4-6 adet yan (ilişkili/semantik) anahtar kelime belirle (Türkçe bağlamda, metin içinde doğal geçebilecek)
3. Kullanıcının bu sayfayı hangi arama niyetiyle bulacağını belirle
4. Metinde eksik kalmış, bahsedilmemiş ama konu bütünlüğü açısından SEO için eklenmesi faydalı olacak 2-3 adet alt başlık/konu önerisi yap (örneğin "Şu konudan da bahsedilebilir...").

SAYFA VERİLERİ:
Title: {$title}
Description: {$description}
Başlıklar:
{$headingsStr}
Gövde Metni (Tamamı):
{$bodyText}

YANIT FORMATI (SADECE JSON):
{
  "focus": "odak anahtar kelime",
  "secondary": ["yan kelime 1", "yan kelime 2", "yan kelime 3"],
  "intent": "bilgi alma|satın alma|karşılaştırma|navigasyon",
  "topic_summary": "Sayfanın kısa konu özeti (1 cümle)",
  "missing_topics": ["Eksik Konu 1 (Neden eklenmeli?)", "Eksik Konu 2 (Neden eklenmeli?)"]
}
EOT;

        $response = $this->callAPI($prompt, $systemInstruction, true, 'Aşama A: Anahtar Kelime Keşfi ve İçerik Önerisi');
        
        $fallback = [
            'focus' => '', 
            'secondary' => [], 
            'intent' => 'bilgi alma', 
            'topic_summary' => '',
            'missing_topics' => []
        ];

        if (empty($response)) {
            return $fallback;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $fallback;
        }

        return $decoded;
    }

    /**
     * Daha önce kullanılmış odak kelimeleri hariç tutarak tek seferde keşif ve optimizasyon yapar.
     *
     * @param array $original
     * @param array $excludedKeywords
     * @return array
     */
    public function quickReOptimizeWithDifferentKeywords(array $original, array $excludedKeywords = []): array {
        $systemInstruction = "Sen Türkçe SEO ve içerik geliştirme uzmanısın. Yanıtını SADECE JSON formatında ver. KESİN KURAL: Gövde metnini üretirken ana başlığı '# Başlık', alt başlıkları '## Başlık', '### Başlık' şeklinde standart Markdown formatında yapılandır ve paragrafları temiz satır boşluklarıyla ayır.";
        
        $title = $original['title'] ?? '';
        $description = $original['description'] ?? '';
        $bodyText = $original['body_text'] ?? '';
        
        $excludedKeywords = is_array($excludedKeywords) ? $excludedKeywords : [];
        $excludedKeywordsStr = implode(', ', array_filter($excludedKeywords));
        
        $prompt = <<<EOT
Sana verilen gövde metni, daha önceki SEO analizinde okunabilirlik ve dil bilgisi açısından optimize edilmiş temiz bir metindir.

BİR ÖNCEKİ ANALİZDE KULLANILAN KELİMELER: [{$excludedKeywordsStr}]

GÖREVİN:
1. Bu sayfayla doğrudan alakalı, ancak yukarıdaki bir önceki analizde kullanılan kelimelerden KESİNLİKLE FARKLI yeni 1 adet odak kelime ve 3-5 adet yan anahtar kelime belirle.
2. Bu YENİ anahtar kelimeleri, verilen optimize gövde metnine organik ve akıcı bir şekilde entegre et (metnin mevcut düzenini bozma).
3. Bu yeni odak anahtar kelimeyi kullanarak; 50-60 karakter arası yeni bir Meta Title ve 150-160 karakter arası aksiyon çağrısı (CTA) içeren yeni bir Meta Description üret.

Sayfa Verileri:
Title: {$title}
Description: {$description}
Gövde Metni:
{$bodyText}

YANIT FORMATI (SADECE JSON):
{
  "focus": "yeni odak kelime",
  "secondary": ["yan1", "yan2", "yan3"],
  "intent": "bilgi alma/satın alma...",
  "topic_summary": "özet",
  "title": "Yeni Meta Title (50-60 kar.)",
  "description": "Yeni Meta Description (150-160 kar.)",
  "body_text": "Yeni kelimelerin entegre edildiği optimize gövde metni"
}
EOT;

        $response = $this->callAPI($prompt, $systemInstruction, true, 'Tek Adımlı Hızlı Re-Optimizasyon (Alternatif Anahtar Kelimeler)');
        
        $fallback = [
            'focus' => '',
            'secondary' => [],
            'intent' => 'bilgi alma',
            'topic_summary' => '',
            'title' => $title,
            'description' => $description,
            'body_text' => $bodyText
        ];

        if (empty($response)) {
            $this->warnings[] = "Re-optimizasyon API çağrısı başarısız, orijinal metin korundu";
            return $fallback;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->warnings[] = "Re-optimizasyon JSON hatası, orijinal metin korundu";
            return $fallback;
        }

        if (isset($decoded['body_text'])) {
            $decoded['body_text'] = str_replace(['**', '__'], '', $decoded['body_text']);
        }

        return $decoded;
    }

    /**
     * Orijinal metni, eksiklik raporunu ve anahtar kelimeleri kullanarak içeriği 3 aşamada optimize eder.
     *
     * @param array $original
     * @param array $analysis
     * @param array $keywords
     * @return array
     */
    public function optimizeContent(array $original, array $analysis, array $keywords): array {
        $bodyText = $original['body_text'] ?? '';
        
        // --- ADIM 1: Anahtar Kelime Entegrasyonu ---
        $step1Text = $this->step1KeywordIntegration($bodyText, $keywords);
        if (empty($step1Text)) {
            $this->warnings[] = "Anahtar kelime entegrasyonu başarısız, orijinal metin korundu";
            $step1Text = $bodyText; // Hata olursa orijinal metne dön
        }

        // --- ADIM 2: Okunabilirlik ve Akış ---
        $step2Text = $this->step2Readability($step1Text, $analysis);
        if (empty($step2Text)) {
            $this->warnings[] = "Okunabilirlik iyileştirmesi başarısız, önceki adım korundu";
            $step2Text = $step1Text;
        }

        // --- ADIM 3: Dil Bilgisi ve Meta Etiketler ---
        $finalOutput = $this->step3GrammarAndMeta($step2Text, $original, $analysis, $keywords);
        
        // Final output'ta hata varsa, en azından metni kurtar
        if (empty($finalOutput['title']) || empty($finalOutput['body_text'])) {
            $this->warnings[] = "Meta etiket üretimi başarısız, orijinal etiketler korundu";
            return [
                'title'       => $original['title'] ?? '',
                'description' => $original['description'] ?? '',
                'body_text'   => $step2Text
            ];
        }

        return $finalOutput;
    }

    private function step1KeywordIntegration(string $text, array $keywords): string {
        $systemInstruction = "Sen uzman bir SEO editörüsün. Görevin, verilen orijinal metnin içerisine istenen anahtar kelimeleri akışı bozmayacak şekilde, organik ve doğal olarak yerleştirmektir. KESİN KURAL: Orijinal metnin yazar üslubunu, cümle yapısını ve kelime sayısını olabildiğince koru. Gereksiz yere hiçbir kelimeyi eş anlamlısıyla değiştirme (örneğin 'önemli' yerine 'mühim' yazma). Sadece ve sadece anahtar kelimeleri yedir. Gövde metnini üretirken ana başlığı '# Başlık', alt başlıkları '## Başlık', '### Başlık' şeklinde standart Markdown formatında yapılandır ve paragrafları temiz satır boşluklarıyla ayır. Yanıtını SADECE JSON formatında ver.";
        
        $focus = $keywords['focus'] ?? '';
        $secondary = isset($keywords['secondary']) && is_array($keywords['secondary']) ? implode(', ', $keywords['secondary']) : '';
        
        if (empty($focus) && empty($secondary)) {
            return $text;
        }

        $prompt = <<<EOT
Sana bir metin ve bu metne eklenmesi gereken anahtar kelimeleri veriyorum.

Anahtar Kelimeler:
Odak Kelime: {$focus}
Yan Kelimeler: {$secondary}

Metin:
{$text}

GÖREVİN: Bu anahtar kelimeleri metnin uygun yerlerine ekle. Metni sıfırdan yazma, özetleme veya gereksiz detay ekleme.

YANIT FORMATI (SADECE JSON):
{
  "body_text": "Anahtar kelimelerin eklendiği yeni metin"
}
EOT;

        $response = $this->callAPI($prompt, $systemInstruction, true, 'Adım 1: Anahtar Kelime Entegrasyonu');
        $decoded = json_decode($response, true);
        $bodyText = $decoded['body_text'] ?? '';
        return str_replace(['**', '__'], '', $bodyText);
    }

    private function step2Readability(string $text, array $analysis): string {
        $systemInstruction = "Sen bir içerik geliştirme uzmanısın. Görevin, verilen eksiklik raporundaki (sadece gövde metni, başlıklar ve okunabilirlik ile ilgili olan) önerileri dikkate alarak metni iyileştirmektir. KESİN KURAL: Metnin asıl anlamını ve bilgi bütünlüğünü asla bozma. Orijinal üslubu koru. Hiçbir kelimeyi daha şık durması için eş anlamlısıyla değiştirme. Sadece rapordaki içerik/akış sorunlarını çöz. Gövde metnini üretirken ana başlığı '# Başlık', alt başlıkları '## Başlık', '### Başlık' şeklinde standart Markdown formatında yapılandır ve paragrafları temiz satır boşluklarıyla ayır. Yanıtını SADECE JSON formatında ver.";
        $deficiencyReport = $analysis['deficiency_report'] ?? 'Ciddi bir eksiklik tespit edilmedi.';

        $prompt = <<<EOT
Aşağıda PHP tarafından üretilmiş genel bir SEO ve İçerik Eksiklik Raporu bulunuyor:

{$deficiencyReport}

GÖREVİN:
1. Bu raporda yer alan **Title, Description ve Anahtar Kelime yoğunluğu** ile ilgili maddeleri tamamen GÖRMEZDEN GEL (bunlar diğer aşamalarda hallediliyor).
2. Raporda eğer **Okunabilirlik (Ateşman), Cümle Uzunluğu, Başlık (H1, H2 vb.) Hiyerarşisi veya Akış** ile ilgili hatalar/öneriler varsa, gövde metnini bu uyarılara göre düzenle.
3. Uzun cümleleri böl, kopuk yerlere bağlaç ekle, eksik başlık (Heading) belirtilmişse metnin uygun yerlerine başlıklar ekle.
4. Eğer raporda metin yapısıyla ilgili hiçbir uyarı yoksa, metne HİÇ DOKUNMADAN aynen geri ver.

Metin:
{$text}

YANIT FORMATI (SADECE JSON):
{
    "body_text": "Önerilere göre iyileştirilmiş metin"
}
EOT;

        $response = $this->callAPI($prompt, $systemInstruction, true, 'Adım 2: Okunabilirlik ve Akış');
        $decoded = json_decode($response, true);
        $bodyText = $decoded['body_text'] ?? '';
        return str_replace(['**', '__'], '', $bodyText);
    }

    private function step3GrammarAndMeta(string $text, array $original, array $analysis, array $keywords): array {
        $systemInstruction = "Sen profesyonel bir Türkçe Dil Bilgisi ve SEO Uzmanısın. Görevin, metindeki açık yazım ve noktalama hatalarını düzeltmek, ayrıca sayfa için SEO uyumlu Title ve Description etiketleri oluşturmaktır. KESİN KURAL: Metnin uzunluğunu veya kelime seçimlerini keyfi olarak DEĞİŞTİRME. Eş anlamlı kelime ataması YAPMA. Sadece imla/noktalama düzelt. Gövde metnini üretirken ana başlığı '# Başlık', alt başlıkları '## Başlık', '### Başlık' şeklinde standart Markdown formatında yapılandır ve paragrafları temiz satır boşluklarıyla ayır. Yanıtını SADECE JSON formatında ver.";
        $title = $original['title'] ?? '';
        $description = $original['description'] ?? '';
        $focus = $keywords['focus'] ?? '';
        
        $prompt = <<<EOT
GÖREV 1 (Metin Düzeltme):
Aşağıdaki metinde sadece bariz imla (yazım yanlışı) ve noktalama hatalarını düzelt. Başka hiçbir yapısal değişiklik, kelime ekleme veya çıkarma yapma.

Metin:
{$text}

GÖREV 2 (Meta Etiketleri):
Aşağıdaki mevcut etiketleri kullanarak, SEO'ya uygun yeni etiketler üret.
- Title: 50-60 karakter olmalı, "{$focus}" kelimesi başa yakın geçmeli.
- Description: 150-160 karakter olmalı, "{$focus}" kelimesini ve bir aksiyon çağrısını (ör: keşfet, incele, öğren) içermeli.

Mevcut Title: {$title}
Mevcut Description: {$description}

YANIT FORMATI (SADECE JSON):
{
  "title": "Yeni title (50-60 karakter)",
  "description": "Yeni description (150-160 karakter)",
  "body_text": "İmla ve noktalama hataları giderilmiş son metin"
}
EOT;

        $response = $this->callAPI($prompt, $systemInstruction, true, 'Adım 3: İmla ve Meta Etiketleri');
        $decoded = json_decode($response, true);
        
        if (isset($decoded['body_text'])) {
            $decoded['body_text'] = str_replace(['**', '__'], '', $decoded['body_text']);
        }
        
        return $decoded ?? [];
    }
}
