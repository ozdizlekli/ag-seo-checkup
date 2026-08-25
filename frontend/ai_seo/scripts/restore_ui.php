<?php
// 1. RESTORE btn-send-to-todos in index.php
$index = file_get_contents("frontend/index.php");

$todo_btn = '
      <button class="btn btn--primary btn--sm has-tooltip" data-tooltip="Eksikleri listeye aktar." id="btn-send-to-todos" style="display:inline-flex; align-items:center; opacity:0.5; cursor:not-allowed; padding: 6px 12px; background: #2563eb; color: white; border: none;">
          📋 Gönder
      </button>';

// insert after btn-download-pdf
$index = preg_replace('/(<button class="btn btn--secondary[^>]+id="btn-download-pdf".*?<\/button>)/s', '$1' . $todo_btn, $index);

file_put_contents("frontend/index.php", $index);
echo "Restored btn-send-to-todos.\n";

// 2. REVERT EMPTY STATE TO ORIGINAL GREETING in copilot.js
$c = file_get_contents("frontend/js/copilot.js");

$greeting_call = "addMessage('👋 Merhaba! Ben <strong>GEO SEO Asistanı</strong>.<br><br>Web siteni tarayıp yapay zeka (LLM) arama motorları için optimize edelim. Lütfen analiz etmemi istediğin sayfanın <strong>URL\\\'sini</strong> aşağıya yaz.', 'ai', true, false);";

// Replace empty state injection in startNewAnalysisFromDashboard
$c = preg_replace(
    "/const emptyStateHtml = `<div class=\"copilot-empty-state\".*?<\/div>`;\n\s*document\.getElementById\('copilot-chat-messages-container'\)\.innerHTML = emptyStateHtml;/s",
    "document.getElementById('copilot-chat-messages-container').innerHTML = '';\n    $greeting_call",
    $c
);

// Replace empty state injection in resetChat (for Temizle button)
$c = preg_replace(
    "/document\.getElementById\('copilot-chat-messages-container'\)\.innerHTML = `<div class=\"copilot-empty-state\".*?<\/div>`;/s",
    "document.getElementById('copilot-chat-messages-container').innerHTML = '';\n    $greeting_call",
    $c
);

file_put_contents("frontend/js/copilot.js", $c);
echo "Restored AI greeting.\n";
?>
