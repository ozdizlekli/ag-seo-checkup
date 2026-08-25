<?php
header('Content-Type: application/json');
$url = $_GET['url'] ?? '';

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['error' => 'Geçersiz URL']);
    exit;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$html = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(['error' => 'URL çekilemedi: ' . curl_error($ch)]);
    exit;
}
curl_close($ch);

// Basit temizlik
$dom = new DOMDocument();
@$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
$xpath = new DOMXPath($dom);

$title = '';
$nodes = $xpath->query('//title');
if ($nodes->length > 0) $title = $nodes->item(0)->nodeValue;

$desc = '';
$nodes = $xpath->query('//meta[@name="description"]/@content');
if ($nodes->length > 0) $desc = $nodes->item(0)->nodeValue;

$schemas = [];
$nodes = $xpath->query('//script[@type="application/ld+json"]');
foreach ($nodes as $node) {
    $schemas[] = trim($node->nodeValue);
}

// Görünür metni al (script ve style hariç)
$bodyText = "";
// Gereksiz etiketleri kaldır
while (($node = $xpath->query('//script|//style|//nav|//footer|//svg')->item(0))) {
    $node->parentNode->removeChild($node);
}

// Sadece SEO için önemli etiketleri çek (Başlıklar, paragraflar, linkler ve listeler)
$nodes = $xpath->query('//h1 | //h2 | //h3 | //p | //li | //a');
foreach ($nodes as $node) {
    $tagName = strtolower($node->nodeName);
    $text = trim($node->nodeValue);
    
    if (empty($text)) continue;

    if ($tagName === 'h1') $bodyText .= "\n# " . $text . "\n";
    elseif ($tagName === 'h2') $bodyText .= "\n## " . $text . "\n";
    elseif ($tagName === 'h3') $bodyText .= "\n### " . $text . "\n";
    elseif ($tagName === 'p') $bodyText .= $text . "\n";
    elseif ($tagName === 'li') $bodyText .= "- " . $text . "\n";
    elseif ($tagName === 'a') {
        $href = $node->getAttribute('href');
        // Gemini'nin anchor text ve linki görmesi için markdown formatı
        $bodyText .= "[LINK: " . $text . "](URL: " . $href . ") "; 
    }
}

$bodyText = preg_replace('/\n+/', "\n", $bodyText); // Fazla boşlukları temizle
$bodyText = substr($bodyText, 0, 20000); // Flash-lite context window'u geniştir, biraz artırabilirsin.

// llms.txt kontrolü ekle (1. PDF gereksinimi)
$llmsUrl = rtrim($url, '/') . '/llms.txt';
// fetch header using curl to be faster and support https properly
$ch_llms = curl_init($llmsUrl);
curl_setopt($ch_llms, CURLOPT_NOBODY, true);
curl_setopt($ch_llms, CURLOPT_TIMEOUT, 3);
curl_setopt($ch_llms, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch_llms, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_exec($ch_llms);
$llms_code = curl_getinfo($ch_llms, CURLINFO_HTTP_CODE);
curl_close($ch_llms);

$hasLlms = ($llms_code == 200) ? true : false;

echo json_encode([
    'title' => $title,
    'description' => $desc,
    'schemas' => $schemas,
    'has_llms_txt' => $hasLlms,
    'text' => trim($bodyText)
]);
