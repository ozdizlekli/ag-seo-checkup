<?php
$index = file_get_contents("frontend/index.php");

$info_icon = '<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: #64748b; opacity:1;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';

// 1. GEO AI Bot Title
$index = str_replace(
    '<div class="card__title">GEO AI Bot (URL Tabanlı)</div>',
    '<div class="card__title has-tooltip" style="display:inline-flex; align-items:center;" data-tooltip="Yapay zeka asistanı ile URL bazlı SEO stratejinizi oluşturun.">GEO AI Bot (URL Tabanlı)' . $info_icon . '</div>',
    $index
);

// 2. Rakip Karşılaştırma button
$index = str_replace(
    '<button class="btn btn--danger btn--sm" id="btn-open-battle-mode"',
    '<button class="btn btn--danger btn--sm has-tooltip" data-tooltip="Sitenizi en dişli rakiplerinizle yapay zeka gözünden kıyaslayın." id="btn-open-battle-mode"',
    $index
);
$index = str_replace(
    'Rakip Karşılaştırma</button>',
    'Rakip Karşılaştırma' . str_replace('color: #64748b;', 'color: #fff;', $info_icon) . '</button>',
    $index
);

// 3. Rapor İndir button
$index = str_replace(
    '<button class="btn btn--danger btn--sm" id="btn-download-pdf"',
    '<button class="btn btn--danger btn--sm has-tooltip" data-tooltip="Tüm sohbet analizini şık bir PDF raporu olarak bilgisayarınıza indirin." id="btn-download-pdf"',
    $index
);

// 4. Gönder (Top) button
$index = str_replace(
    '<button class="btn btn--primary btn--sm" id="btn-send-to-todos"',
    '<button class="btn btn--primary btn--sm has-tooltip" data-tooltip="Analizde tespit edilen eksikleri \'Yapılacaklar\' listesine aktarır." id="btn-send-to-todos"',
    $index
);

// 5. Kaydet button
$index = str_replace(
    '<button class="btn btn--primary btn--sm" id="copilot-manual-save-btn"',
    '<button class="btn btn--primary btn--sm has-tooltip" data-tooltip="Mevcut sohbeti geçmişe kaydeder." id="copilot-manual-save-btn"',
    $index
);

// 6. Reset button
$index = str_replace(
    '<button class="btn btn--ghost" id="copilot-reset-btn"',
    '<button class="btn btn--ghost has-tooltip" data-tooltip="Sohbeti temizler ve yeni bir analize başlar." id="copilot-reset-btn"',
    $index
);

// 7. Geçmiş Sohbetler Title
$index = str_replace(
    '<div class="card__title">Geçmiş Sohbetler</div>',
    '<div class="card__title has-tooltip" style="display:inline-flex; align-items:center;" data-tooltip="Daha önce yaptığınız analizleri buradan geri yükleyebilirsiniz.">Geçmiş Sohbetler' . $info_icon . '</div>',
    $index
);

// 8. Temizle button (History)
$index = str_replace(
    '<button class="btn btn--ghost btn--sm" id="btn-clear-history">Temizle</button>',
    '<button class="btn btn--ghost btn--sm has-tooltip" data-tooltip="Tüm sohbet geçmişini kalıcı olarak siler." id="btn-clear-history">Temizle' . $info_icon . '</button>',
    $index
);

// 9. Yapılacaklar & Eksiklikler Title
$index = str_replace(
    '<div class="card__title">Yapılacaklar & Eksiklikler</div>',
    '<div class="card__title has-tooltip" style="display:inline-flex; align-items:center;" data-tooltip="AI tarafından tespit edilen tüm eksiklikleri tek bir listede takip edin.">Yapılacaklar & Eksiklikler' . $info_icon . '</div>',
    $index
);

// 10. Listeyi Temizle button
$index = str_replace(
    '<button class="btn btn--ghost btn--sm" id="btn-clear-todos">Listeyi Temizle</button>',
    '<button class="btn btn--ghost btn--sm has-tooltip" data-tooltip="Yapılacaklar listesini kalıcı olarak siler." id="btn-clear-todos">Listeyi Temizle' . $info_icon . '</button>',
    $index
);


file_put_contents("frontend/index.php", $index);
echo "Added i icons to other elements\n";
?>
