<?php
$file = __DIR__ . '/js/app.js';
$content = file_get_contents($file);

// Remove fetchBacklinks calls
$content = preg_replace("/,\s*fetchBacklinks\(\)/i", "", $content);
$content = preg_replace("/await fetchBacklinks\(\);/i", "", $content);
$content = preg_replace("/fetchBacklinks\(\);/i", "", $content);

// Remove the function itself and related event listeners by matching large blocks
// Since this is risky with regex, I'll match starting from `/* --- Backlink Profilini` to the end of the delete block
// Or just remove everything from `function backlinkQualityBadgeHtml` to the end of delete event listener.

$startString = '/* Kalite rozetini üretir (mevcut scoreStatusLabel renk sınıflarını yeniden kullanır) */';
$endString = '/* --- 6. SİTE DIŞI & YEREL SEO (Checklist) --- */';
$posStart = strpos($content, $startString);
$posEnd = strpos($content, $endString);

if ($posStart !== false && $posEnd !== false) {
    $content = substr_replace($content, '', $posStart, $posEnd - $posStart);
}

// Remove the scoring part from overall score calculation
// It was:
// const backlinkCount = document.querySelectorAll('#t5-backlink-body tr').length;
// const backlinkScore = backlinkCount * 10; // Her backlink 10 puan
// let calculatedOffsiteScore = Math.min(100, checklistScore + backlinkScore);
// We'll replace it with:
// let calculatedOffsiteScore = Math.min(100, checklistScore);
$content = preg_replace("/const backlinkCount = document\.querySelectorAll\('\#t5-backlink-body tr'\)\.length;\s*const backlinkScore = backlinkCount \* 10;\s*\/\/\s*Her backlink 10 puan\s*let calculatedOffsiteScore = Math\.min\(100, checklistScore \+ backlinkScore\);/i", "let calculatedOffsiteScore = Math.min(100, checklistScore);", $content);

// Remove backlink PDF report section
$pdfStartString = '// ---- YENİ: 3.5. SİTE DIŞI & BACKLİNK PROFİLİ (Tab 05) ----';
$pdfEndString = '// ---- YENİ: 3.6. YAPAY ZEKA GÜVEN SKORU ----';
$posPdfStart = strpos($content, $pdfStartString);
$posPdfEnd = strpos($content, $pdfEndString);

if ($posPdfStart !== false && $posPdfEnd !== false) {
    $content = substr_replace($content, '', $posPdfStart, $posPdfEnd - $posPdfStart);
}

file_put_contents($file, $content);
echo "Backlinks removed from app.js\n";
?>
