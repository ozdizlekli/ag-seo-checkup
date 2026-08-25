<?php
$index = file_get_contents("frontend/index.php");

// 1. Update Logout Button
$index = preg_replace(
    '/<a href="logout.php" onclick="sessionStorage.removeItem\(\'ag_welcome_seen\'\);" class="btn btn--sidebar btn--sm" style="text-decoration:none; text-align:center; display:block;">(.*?)Çıkış Yap\s*<\/a>/s',
    '<a href="logout.php" onclick="sessionStorage.removeItem(\'ag_welcome_seen\');" class="btn btn--sidebar btn--sm logout-btn" style="text-decoration:none; display:flex; align-items:center; justify-content:center; gap:8px; padding:12px 16px; border-radius:8px; transition:all 0.2s;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        <span style="font-weight: 500;">Çıkış Yap</span>
    </a>',
    $index
);

// 2. Update PDF Button (Rapor İndir)
$info_icon_pdf = '<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: #fff; opacity:0.9;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';

$index = preg_replace(
    '/<button class="btn btn--secondary btn--sm" id="btn-download-pdf" title="Önce tüm analiz ve çözümleri bitirin" style="(.*?)">(.*?)Rapor İndir\s*<\/button>/s',
    '<button class="btn btn--secondary btn--sm has-tooltip" data-tooltip="Tüm analiz adımları (6 adım) tamamlandıktan sonra, müşteriye sunulmaya hazır detaylı PDF raporunu indirebilirsiniz." id="btn-download-pdf" style="$1">
        $2
        Rapor İndir' . $info_icon_pdf . '
    </button>',
    $index
);

// 3. Update Rakip Karşılaştırma Button
$index = preg_replace(
    '/<button class="btn btn--danger btn--sm has-tooltip" data-tooltip="Sitenizi en dişli rakiplerinizle yapay zeka gözünden kıyaslayın." id="btn-open-battle-mode" style="(.*?)">\s*Rakip Karşılaştırma\s*<\/button>/s',
    '<button class="btn btn--danger btn--sm has-tooltip" data-tooltip="Sitenizi en dişli rakiplerinizle yapay zeka gözünden kıyaslayın." id="btn-open-battle-mode" style="$1">
        Rakip Karşılaştırma' . $info_icon_pdf . '
    </button>',
    $index
);

// 4. Update Gönder Butonu?
// User said "diğer butonlarında da gözüken gibi bilgisini yazmalısın ancak diğerlerinde bilgi var i ikonu yok "i" ikonunundan bütün butonlara eklemelisin"
// Is there a Gönder button? Let's check "Gönder" button.
$index = preg_replace(
    '/<button class="btn btn--primary" id="copilot-send-btn">(.*?)Gönder(.*?)<\/button>/s',
    '<button class="btn btn--primary has-tooltip" data-tooltip="Yapay zeka asistanına analiz talimatını gönderin." id="copilot-send-btn" style="display:inline-flex; align-items:center;">$1Gönder' . $info_icon_pdf . '</button>',
    $index
);

file_put_contents("frontend/index.php", $index);
echo "Updated buttons.\n";
?>
