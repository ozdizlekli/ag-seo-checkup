<?php
$c = file_get_contents("frontend/js/copilot.js");

// Replace the addMessage in resetChat with renderDashboard call
$old_msg = "addMessage(`👋 Merhaba! Ben <strong>GEO SEO Asistanı</strong>.<br><br>Web siteni tarayıp yapay zeka (LLM) arama motorları için optimize edelim. Lütfen analiz etmemi istediğin sayfanın <strong>URL'sini</strong> aşağıya yaz.`, 'ai', true, false);";

$new_msg = "if (typeof renderDashboard === 'function') { renderDashboard(); }";

$c = str_replace($old_msg, $new_msg, $c);

// Also modify renderDashboard function to target #copilot-chat-messages-container
$c = preg_replace("/document\.getElementById\('copilot-messages'\)\.innerHTML = `/", "const container = document.getElementById('copilot-chat-messages-container'); if(container) container.innerHTML = `", $c);

// Add logic to "Yeni Analiz Başlat" button inside the dashboard to clear the dashboard and show the greeting
$c = str_replace(
    'onclick="document.getElementById(\'copilot-text-input\').focus();"',
    'onclick="document.getElementById(\'copilot-chat-messages-container\').innerHTML = \'\'; addMessage(\'👋 Merhaba! Ben <strong>GEO SEO Asistanı</strong>.<br><br>Web siteni tarayıp yapay zeka (LLM) arama motorları için optimize edelim. Lütfen analiz etmemi istediğin sayfanın <strong>URL\\\'sini</strong> aşağıya yaz.\', \'ai\', true, false); document.getElementById(\'copilot-text-input\').focus();"',
    $c
);

file_put_contents("frontend/js/copilot.js", $c);
echo "Updated copilot.js logic for Dashboard.\n";
?>
