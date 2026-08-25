<?php
$c = file_get_contents("frontend/js/copilot.js");
$c = str_replace(
    "Site A'nın rakibine göre içerik derinliği, SEO kalitesi ve E-E-A-T sinyalleri açısından eksiklerini ve rakibin neden daha iyi olduğunu JSON olarak analiz et.",
    "Site A'nın rakibine göre içerik derinliği, SEO kalitesi ve E-E-A-T sinyalleri açısından eksiklerini JSON olarak analiz et. Ayrıca JSON içine \"site_a_skorlari\": {\"icerik\": 60, \"seo\": 65, \"eeat\": 50} ve \"site_b_skorlari\": {\"icerik\": 85, \"seo\": 90, \"eeat\": 88} şeklinde iki sitenin 100 üzerinden tahmini skorlarını da ekle.",
    $c
);
file_put_contents("frontend/js/copilot.js", $c);
echo "Updated battle prompt\n";
?>
