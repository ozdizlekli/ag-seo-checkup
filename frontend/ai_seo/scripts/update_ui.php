<?php
$index = file_get_contents("frontend/index.php");

// 1. Remove info-icons from steps and move tooltips to copilot-step
for ($i = 1; $i <= 6; $i++) {
    // Find the copilot-step div
    preg_match('/<div class="copilot-step" data-step="' . $i . '" id="cp-step-' . $i . '">(.*?)<div class="step-label-wrapper has-tooltip" data-tooltip="([^"]+)"><span>(.*?)<\/span><svg class="info-icon".*?<\/svg><\/div>/s', $index, $matches);
    
    if ($matches) {
        $full_match = $matches[0];
        $inner_before = $matches[1];
        $tooltip_text = str_replace('&#10;&#10;Sohbette bu adıma gitmek için tıklayın', '', $matches[2]); // clean it up a bit if needed, or keep it. Let's keep it.
        $tooltip_text = $matches[2];
        $step_name = $matches[3];
        
        $new_html = '<div class="copilot-step has-tooltip" data-step="' . $i . '" id="cp-step-' . $i . '" data-tooltip="' . htmlspecialchars($tooltip_text, ENT_QUOTES) . '" style="cursor: pointer;">' .
                    $inner_before .
                    '<div class="step-label-wrapper"><span>' . $step_name . '</span></div>';
        
        $index = str_replace($full_match, $new_html, $index);
    }
}

// 2. Fix Quick Action Buttons Flexbox
$index = str_replace(
    '<div class="quick-actions" id="copilot-quick-actions" style="display:none;">',
    '<div class="quick-actions" id="copilot-quick-actions" style="display:none; flex-wrap:nowrap;">',
    $index
);

// We need to add CSS for quick-action-btn to not wrap text. We'll do it in css.

// 3. Fix Sidebar Logout Button
$index = preg_replace(
    '/<a href="logout.php" .*?class="btn btn--sidebar btn--sm logout-btn" style="([^"]+)".*?>.*?<svg.*?<\/svg>.*?Çıkış Yap.*?<\/a>/s',
    '<a href="logout.php" onclick="sessionStorage.removeItem(\'ag_welcome_seen\');" class="btn btn--sidebar btn--sm logout-btn" style="text-decoration:none; display:flex; align-items:center; justify-content:center; gap:8px; padding:12px 16px; border-radius:8px; transition:all 0.2s;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        <span style="font-weight: 500;">Çıkış Yap</span>
    </a>',
    $index
);

// 4. Update PDF button to have actual info icon
$info_icon_pdf = '<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: #fff; opacity:0.9;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';

$index = preg_replace(
    '/<button class="btn btn--danger btn--sm has-tooltip" data-tooltip="Tüm sohbet analizini şık bir PDF raporu olarak bilgisayarınıza indirin." id="btn-download-pdf".*?>.*?<svg.*?<\/svg>.*?Rapor İndir.*?<\/button>/s',
    '<button class="btn btn--danger btn--sm has-tooltip" data-tooltip="Tüm analiz adımları (6 adım) tamamlandıktan sonra, müşteriye sunulmaya hazır detaylı PDF raporunu indirebilirsiniz." id="btn-download-pdf" style="display:inline-flex; align-items:center; margin-left:4px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        &nbsp;Rapor İndir' . $info_icon_pdf . '
    </button>',
    $index
);

file_put_contents("frontend/index.php", $index);
echo "index.php updated successfully.\n";
?>
