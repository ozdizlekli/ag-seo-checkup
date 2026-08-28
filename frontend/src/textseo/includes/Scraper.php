<?php
require_once __DIR__ . '/../config.php';

class Scraper {
    /**
     * URL'den SEO verilerini çeker
     *
     * @param string $url Çekilecek web sayfasının URL'si
     * @return array
     */
    public function scrape(string $url): array {
        // A) cURL ile Sayfayı Çekme
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, MAX_SCRAPE_TIMEOUT);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 7);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $headers = [
            'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // D) Hata Yönetimi
        if ($html === false) {
            return ['status' => 'error', 'error' => 'Sayfa yüklenemedi: ' . $curlError];
        }
        if ($httpCode >= 400) {
            return ['status' => 'error', 'error' => 'HTTP hatası: ' . $httpCode];
        }
        if (trim($html) === '') {
            return ['status' => 'error', 'error' => 'Sayfa içeriği boş'];
        }
        
        // B) Encoding Düzeltme
        if (!mb_check_encoding($html, 'UTF-8')) {
            $detected = mb_detect_encoding($html, 'UTF-8, ISO-8859-9, Windows-1254, ISO-8859-1', true);
            if ($detected) {
                $html = mb_convert_encoding($html, 'UTF-8', $detected);
            } else {
                $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-9'); // Fallback
            }
        }
        $html = '<?xml encoding="UTF-8">' . $html;
        
        // C) DOMDocument + DOMXPath ile Parse Etme
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        
        if (!$loaded) {
            return ['status' => 'error', 'error' => 'HTML ayrıştırılamadı'];
        }
        
        $xpath = new DOMXPath($dom);
        
        // 1. Title
        $titleNode = $xpath->query('//title')->item(0);
        $title = $titleNode ? trim($titleNode->textContent) : '';
        
        // 2. Meta Description
        $descQuery = '//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="description"]/@content';
        $descNodes = $xpath->query($descQuery);
        $description = $descNodes !== false && $descNodes->length > 0 ? trim($descNodes->item(0)->nodeValue) : '';
        
        // 3. Başlıklar (H1, H2, H3)
        $headings = [];
        $headingNodes = $xpath->query('//h1 | //h2 | //h3');
        if ($headingNodes !== false) {
            foreach ($headingNodes as $node) {
                $text = preg_replace('/\s+/', ' ', trim($node->textContent));
                if ($text !== '') {
                    $headings[] = [
                        'tag'  => strtolower($node->nodeName),
                        'text' => $text
                    ];
                }
            }
        }
        
        // 4. Gövde Metni (body_text)
        $clonedDom = clone $dom;
        $clonedXpath = new DOMXPath($clonedDom);
        
        // Gürültü etiketlerini kaldır
        $tagsToRemove = ['script', 'style', 'nav', 'header', 'footer', 'aside', 'form', 'noscript', 'iframe', 'svg'];
        foreach ($tagsToRemove as $tag) {
            $nodesToRemove = $clonedXpath->query('//' . $tag);
            if ($nodesToRemove !== false) {
                foreach ($nodesToRemove as $node) {
                    if ($node->parentNode) {
                        $node->parentNode->removeChild($node);
                    }
                }
            }
        }
        
        // Kalan blok elementlerden metni çek
        $textParts = [];
        $blockNodes = $clonedXpath->query('//p | //li | //article | //h1 | //h2 | //h3 | //h4 | //h5 | //h6 | //blockquote | //div');
        if ($blockNodes !== false) {
            foreach ($blockNodes as $node) {
                // İç içe blokları önlemek için, elementin içinde başka bir blok element var mı kontrol et
                $hasBlockChild = false;
                $childBlocks = ['p', 'li', 'article', 'blockquote', 'div'];
                foreach ($childBlocks as $cTag) {
                    if ($clonedXpath->query('.//' . $cTag, $node)->length > 0) {
                        $hasBlockChild = true;
                        break;
                    }
                }
                
                if (!$hasBlockChild) {
                    $text = preg_replace('/\s+/', ' ', trim($node->textContent));
                    // 25 karakterden kısa parçaları atla (gürültü filtresi)
                    if (mb_strlen($text, 'UTF-8') >= 25) {
                        $textParts[] = $text;
                    }
                }
            }
        }
        
        // Eğer hiçbir metin bulunamadıysa fallback olarak tüm body metnini al
        if (empty($textParts)) {
            $bodyNode = $clonedXpath->query('//body')->item(0);
            if ($bodyNode) {
                $text = preg_replace('/\s+/', ' ', trim($bodyNode->textContent));
                if (mb_strlen($text, 'UTF-8') >= 25) {
                    $textParts[] = $text;
                }
            }
        }
        
        $bodyText = implode("\n\n", $textParts);
        $bodyText = trim($bodyText);
        
        // 5. Kelime Sayısı
        $wordCount = 0;
        if ($bodyText !== '') {
            $words = preg_split('/\s+/u', $bodyText, -1, PREG_SPLIT_NO_EMPTY);
            $wordCount = is_array($words) ? count($words) : 0;
        }
        
        // E) Başarılı Dönüş Formatı
        return [
            'url'         => $url,
            'title'       => $title,
            'description' => $description,
            'headings'    => $headings,
            'body_text'   => $bodyText,
            'word_count'  => $wordCount,
            'status'      => 'success',
            'error'       => null
        ];
    }
}
