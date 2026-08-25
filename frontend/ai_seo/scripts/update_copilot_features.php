<?php
$c = file_get_contents("frontend/js/copilot.js");

// Task 2: Remove <svg class="info-icon"...> completely from copilot.js just in case
$c = preg_replace('/<svg class="info-icon".*?<\/svg>/s', '', $c);

// Task 3: Bottom Action Buttons Flexbox Fix
$old_actions = 'actionsHtml += `<div style="display: flex; width: 100%; gap: 8px; margin-bottom: 10px;">
              <button class="btn btn--primary" id="btn-analyze-all" style="flex:1;">${analyzeText}</button>
              <button class="btn btn--success" id="btn-fix-all" style="flex:1;">${fixText}</button>
            </div>`;';

// If it's already somewhat like that, let's just make it perfectly aligned
$c = preg_replace(
    '/actionsHtml \+= `<div style="display: flex; width: 100%; gap: 8px; margin-bottom: 10px;">.*?<\/div>`;/s',
    'actionsHtml += `<div style="display: flex; flex-direction: row; width: 100%; gap: 16px; margin-bottom: 16px;">
              <button class="btn btn--primary" id="btn-analyze-all" style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 12px; font-weight: 600; font-size: 14px;">⚡ ${analyzeText}</button>
              <button class="btn btn--success" id="btn-fix-all" style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 12px; font-weight: 600; font-size: 14px; background-color: #10b981; border: none;">🔧 ${fixText}</button>
            </div>`;',
    $c
);

// Wait, the original code might not have exactly that regex match. Let's do a more robust replace.
$c = preg_replace(
    '/actionsHtml \+= `<div style="display: flex; width: 100%; gap: 8px; margin-bottom: 10px;">\s*<button class="btn btn--primary" id="btn-analyze-all".*?>\$\{analyzeText\}<\/button>\s*<button class="btn btn--success" id="btn-fix-all".*?>\$\{fixText\}<\/button>\s*<\/div>`;/s',
    'actionsHtml += `<div style="display: flex; flex-direction: row; width: 100%; gap: 16px; margin-bottom: 16px;">
              <button class="btn btn--primary" id="btn-analyze-all" style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 12px; font-weight: 600; font-size: 14px; border-radius: 8px;">⚡ ${analyzeText}</button>
              <button class="btn btn--success" id="btn-fix-all" style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 12px; font-weight: 600; font-size: 14px; background-color: #10b981; color: white; border: none; border-radius: 8px;">🔧 ${fixText}</button>
            </div>`;',
    $c
);

// Actually, in `renderAiSeoActions()`, the analyzeText and fixText ALREADY include the emoji!
// `const analyzeText = completedSteps.size === 0 ? "⚡ Tüm Siteyi Analiz Et" : "⚡ Kalan Adımları Analiz Et";`
// So I shouldn't duplicate emojis. I'll just use `${analyzeText}`.
$c = preg_replace(
    '/actionsHtml \+= `<div style="display: flex; width: 100%; gap: 8px; margin-bottom: 10px;">.*?<\/div>`;/s',
    'actionsHtml += `<div style="display: flex; flex-direction: row; width: 100%; gap: 16px; margin-bottom: 16px;">
              <button class="btn btn--primary" id="btn-analyze-all" style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 12px; font-weight: 600; font-size: 14px; border-radius: 8px;">${analyzeText}</button>
              <button class="btn btn--success" id="btn-fix-all" style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 12px; font-weight: 600; font-size: 14px; background-color: #10b981; color: white; border: none; border-radius: 8px;">${fixText}</button>
            </div>`;',
    $c
);

// Task 4: Empty State Design
$empty_state_html = <<<HTML
<div class="copilot-empty-state" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; min-height: 350px; padding: 40px 20px; text-align: center;">
    <svg style="width: 100px; height: 100px; opacity: 0.05; margin-bottom: 24px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
    <h3 style="font-size: 18px; font-weight: 600; color: var(--text); margin-bottom: 8px;">Analiz etmek istediğiniz sayfanın URL\'sini aşağıdaki kutuya yapıştırın</h3>
    <p style="color: var(--muted); font-size: 14px;">Yapay zeka (LLM) arama motorları için derinlemesine optimizasyon başlasın.</p>
</div>
HTML;

// In resetChat, replace `addMessage('👋 Merhaba...` with this empty state!
$c = preg_replace(
    "/document\.getElementById\('copilot-chat-messages-container'\)\.innerHTML = ''; addMessage\('👋 Merhaba.*?true, false\);/s",
    "document.getElementById('copilot-chat-messages-container').innerHTML = `$empty_state_html`;",
    $c
);

// Also in the HTML string inside onclick of 'Yeni Analiz Başlat' (in renderDashboard)
$c = preg_replace(
    "/onclick=\"document\.getElementById\('ai-seo-dashboard-view'\)\.style\.display='none'; document\.getElementById\('copilot-action-view'\)\.style\.display='block'; document\.getElementById\('copilot-chat-messages-container'\)\.innerHTML = ''; addMessage\('.*?'\); document\.getElementById\('copilot-text-input'\)\.focus\(\);\"/s",
    "onclick=\"document.getElementById('ai-seo-dashboard-view').style.display='none'; document.getElementById('copilot-action-view').style.display='block'; document.getElementById('copilot-chat-messages-container').innerHTML = `$empty_state_html`; document.getElementById('copilot-text-input').focus();\"",
    $c
);

// Task 5: Battle Mode Aggressive UI
// Change background colors of 3 columns
$c = str_replace(
    '<div style="background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">',
    '<div style="background: #eff6ff; padding: 16px; border-radius: 8px; border: 1px solid #bfdbfe;">',
    $c
);

// I don't know the exact string, let's just use regex for the battle grid
$c = preg_replace(
    "/html \+= `<div style=\"display: grid; grid-template-columns: repeat\(3, 1fr\); gap: 16px; margin-top: 16px;\">.*?<\/div>`;/s",
    'html += `<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 16px;">
        <div style="background: #eff6ff; padding: 16px; border-radius: 8px; border: 1px solid #bfdbfe;">
           <h4 style="font-weight: 600; font-size: 14px; margin-bottom: 8px; color: #1e3a8a;"><svg width="16" height="16" style="vertical-align:middle; margin-right:6px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>İçerik Kalitesi</h4>
           <div style="font-size: 13px; color: #334155;">${res.icerik_kalitesi || "-"}</div>
        </div>
        <div style="background: #f0fdf4; padding: 16px; border-radius: 8px; border: 1px solid #bbf7d0;">
           <h4 style="font-weight: 600; font-size: 14px; margin-bottom: 8px; color: #14532d;"><svg width="16" height="16" style="vertical-align:middle; margin-right:6px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>SEO Stratejisi</h4>
           <div style="font-size: 13px; color: #334155;">${res.seo_stratejisi || "-"}</div>
        </div>
        <div style="background: #fef2f2; padding: 16px; border-radius: 8px; border: 1px solid #fecaca;">
           <h4 style="font-weight: 600; font-size: 14px; margin-bottom: 8px; color: #7f1d1d;"><svg width="16" height="16" style="vertical-align:middle; margin-right:6px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4l3 3"></path></svg>E-E-A-T Sinyalleri</h4>
           <div style="font-size: 13px; color: #334155;">${res.eeat_sinyalleri || "-"}</div>
        </div>
      </div>
      
      <div style="margin-top: 24px; background: #fffbeb; border: 2px solid #fcd34d; border-radius: 8px; padding: 20px;">
          <h3 style="font-size: 16px; font-weight: 700; color: #b45309; margin-bottom: 12px; display: flex; align-items: center;">
              <svg width="20" height="20" style="margin-right:8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
              🚀 Yapılması Gereken 3 Hamle
          </h3>
          <ul style="list-style-type: none; padding-left: 0; font-size: 14px; color: #451a03; line-height: 1.6;" id="battle-action-plan">
              <li style="margin-bottom: 8px; display: flex; align-items: flex-start;"><span style="color: #d97706; margin-right: 8px;">1.</span> <span class="action-item">Yapay zeka analizini bekliyor...</span></li>
              <li style="margin-bottom: 8px; display: flex; align-items: flex-start;"><span style="color: #d97706; margin-right: 8px;">2.</span> <span class="action-item">Yapay zeka analizini bekliyor...</span></li>
              <li style="display: flex; align-items: flex-start;"><span style="color: #d97706; margin-right: 8px;">3.</span> <span class="action-item">Yapay zeka analizini bekliyor...</span></li>
          </ul>
      </div>`;',
    $c
);


file_put_contents("frontend/js/copilot.js", $c);
echo "copilot.js updated.\n";
?>
