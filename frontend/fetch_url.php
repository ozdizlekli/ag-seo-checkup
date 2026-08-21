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
while (($node = $xpath->query('//script|//style')->item(0))) {
    $node->parentNode->removeChild($node);
}
$bodyText = strip_tags($dom->saveHTML());
$bodyText = preg_replace('/\s+/', ' ', $bodyText);
$bodyText = trim(substr($bodyText, 0, 15000)); // Limit to prevent massive payloads

echo json_encode([
    'title' => $title,
    'description' => $desc,
    'schemas' => $schemas,
    'text' => $bodyText
]);
