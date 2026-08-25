<?php
$index = file_get_contents("frontend/index.php");

// 1. Remove all info-icons (Task 2)
$index = preg_replace('/<svg class="info-icon".*?<\/svg>/s', '', $index);

// 2. Rewrite Header (Task 1)
// We need to extract the progress steps and buttons and rewrite the header.
// Let's find the progress bar content.
preg_match('/<div class="copilot-progress" id="copilot-progress".*?>(.*?)<\/div>\s*<button class="btn btn--danger/s', $index, $progress_match);
$progress_content = $progress_match[1] ?? '';

// If the regex failed, let's extract carefully
if (!$progress_content) {
    preg_match('/<div class="copilot-progress" id="copilot-progress".*?>(.*?)<\/div>\s*<\/div>\s*<button/s', $index, $progress_match);
    $progress_content = $progress_match[1] ?? '';
}

// Rebuild the header
$new_header = <<<HTML
<div class="copilot-header" style="position:relative; z-index:50; display: flex; flex-direction: column; gap: 16px;">
  <!-- Üst Satır: Geri Dönüş ve Sağ Aksiyonlar -->
  <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
    <!-- Sol Üst -->
    <button id="btn-return-dashboard" class="btn btn--ghost btn--sm" style="display:inline-flex; align-items:center; color: var(--muted); font-weight:600; padding:6px 12px; border-radius:8px; font-size:13px; background: #f1f5f9;">
       <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
       Kontrol Paneline Dön
    </button>
    
    <!-- Sağ Üst (Küçük İkonlu Butonlar) -->
    <div style="display: flex; gap: 8px; align-items: center;">
      <button class="btn btn--danger btn--sm has-tooltip" data-tooltip="Sitenizi en dişli rakiplerinizle kıyaslayın." id="btn-open-battle-mode" style="display:inline-flex; align-items:center; background:#dc2626; color:white; border:none; box-shadow:0 2px 8px rgba(220,38,38,0.4); border-radius:20px; padding: 6px 12px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
          Savaş Modu
      </button>
      <button class="btn btn--secondary btn--sm has-tooltip" data-tooltip="Tüm analiz adımları tamamlandıktan sonra indirilebilir." id="btn-download-pdf" style="display:inline-flex; align-items:center; background:#ef4444; color:white; border:none; opacity:0.5; cursor:not-allowed; padding: 6px 12px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
          Rapor
      </button>
      <button class="btn btn--primary btn--sm has-tooltip" data-tooltip="Sohbeti geçmişe kaydeder." id="copilot-manual-save-btn" style="display:inline-flex; align-items:center; padding: 6px 12px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
          Kaydet
      </button>
      
      <div style="display: flex; align-items: center; gap: 6px; margin-left: 8px; border-left: 1px solid var(--border); padding-left: 12px;">
          <label class="switch has-tooltip" data-tooltip="Müşteri sunumu için sade görünüm">
              <input type="checkbox" id="client-view-toggle">
              <span class="slider round"></span>
          </label>
          <span style="font-size: 12px; font-weight: 500; color: var(--muted); cursor: pointer;" onclick="document.getElementById('client-view-toggle').click()">Sunum</span>
      </div>
      
      <button class="btn btn--ghost btn--sm has-tooltip" data-tooltip="Sohbeti temizle" id="copilot-reset-btn" style="padding: 6px; margin-left: 8px;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
      </button>
    </div>
  </div>
  
  <!-- Alt Satır: Progress Bar -->
  <div class="copilot-progress" id="copilot-progress" style="display:none; justify-content: center; width: 100%;">
HTML;

$new_header .= $progress_content . "</div>\n</div>";

// Replace old header
$index = preg_replace('/<div class="copilot-header".*?<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<div class="copilot-chat"/s', $new_header . "\n<div class=\"copilot-chat\"", $index);
// Wait, the regex might be tricky. Let's just use string replace for safety if regex is too complex.
// The old header ended with `<button id="copilot-reset-btn"...</button></div>` inside `copilot-header`?
// Let's replace the whole copilot-header div.
$index = preg_replace('/<div class="copilot-header" style="position:relative; z-index:50;">.*?<div class="copilot-chat" id="copilot-chat"/s', $new_header . "\n            <div class=\"copilot-chat\" id=\"copilot-chat\"", $index);

// 3. Battle Mode Modal VS Badge (Task 5)
$vs_badge = '<div style="background: #dc2626; color: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 14px; box-shadow: 0 4px 10px rgba(220,38,38,0.3); flex-shrink: 0; margin-top: 2px;">VS</div>';

$index = preg_replace('/<input type="url" id="battle-comp-url"/', $vs_badge . "\n      <input type=\"url\" id=\"battle-comp-url\"", $index);

file_put_contents("frontend/index.php", $index);
echo "Header and Modal updated.\n";
?>
