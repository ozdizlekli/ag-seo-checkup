<?php
$c = file_get_contents("frontend/js/copilot.js");

// Replace the onclick completely with a clean approach
$bad_onclick = 'onclick="document.getElementById(\'ai-seo-dashboard-view\').style.display=\'none\'; document.getElementById(\'copilot-action-view\').style.display=\'block\'; document.getElementById(\'copilot-chat-messages-container\').innerHTML = `<div class=\"copilot-empty-state\" style=\"display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; min-height: 350px; padding: 40px 20px; text-align: center;\">\n    <svg style=\"width: 100px; height: 100px; opacity: 0.05; margin-bottom: 24px;\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6\"></path></svg>\n    <h3 style=\"font-size: 18px; font-weight: 600; color: var(--text); margin-bottom: 8px;\">Analiz etmek istediğiniz sayfanın URL\\\'sini aşağıdaki kutuya yapıştırın</h3>\n    <p style=\"color: var(--muted); font-size: 14px;\">Yapay zeka (LLM) arama motorları için derinlemesine optimizasyon başlasın.</p>\n</div>`; document.getElementById(\'copilot-text-input\').focus();"';

// Actually, I can just find the <button> inside renderDashboard and replace it.
$c = preg_replace(
    '/<button class="btn btn--primary" style="padding: 12px 24px; font-size: 14px; font-weight: 600;" onclick=".*?">/s',
    '<button class="btn btn--primary" style="padding: 12px 24px; font-size: 14px; font-weight: 600;" onclick="startNewAnalysisFromDashboard()">',
    $c
);

// Add the startNewAnalysisFromDashboard function to global scope
$func = <<<JS

window.startNewAnalysisFromDashboard = function() {
    document.getElementById('ai-seo-dashboard-view').style.display = 'none';
    document.getElementById('copilot-action-view').style.display = 'block';
    const emptyStateHtml = `<div class="copilot-empty-state" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; min-height: 350px; padding: 40px 20px; text-align: center;">
        <svg style="width: 100px; height: 100px; opacity: 0.05; margin-bottom: 24px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
        <h3 style="font-size: 18px; font-weight: 600; color: var(--text); margin-bottom: 8px;">Analiz etmek istediğiniz sayfanın URL'sini aşağıdaki kutuya yapıştırın</h3>
        <p style="color: var(--muted); font-size: 14px;">Yapay zeka (LLM) arama motorları için derinlemesine optimizasyon başlasın.</p>
    </div>`;
    document.getElementById('copilot-chat-messages-container').innerHTML = emptyStateHtml;
    document.getElementById('copilot-text-input').focus();
};
JS;

$c .= $func;

file_put_contents("frontend/js/copilot.js", $c);
echo "Cleaned up onclick handler.\n";
?>
