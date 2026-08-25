<?php
$c = file_get_contents("frontend/js/copilot.js");

// Replace emptyStateHtml usage with a direct resetChat(null)
$c = str_replace(
    "document.getElementById('copilot-chat-messages-container').innerHTML = emptyStateHtml;",
    "resetChat(null);",
    $c
);

// Add the welcome message to resetChat
$c = str_replace(
    "if (msgContainer) msgContainer.innerHTML = '';",
    "if (msgContainer) { msgContainer.innerHTML = ''; addMessage('👋 Merhaba! Ben GEO SEO Asistanı. Analiz etmek istediğiniz sayfanın URL\'sini yapıştırarak başlayabilirsiniz.', 'ai'); }",
    $c
);

file_put_contents("frontend/js/copilot.js", $c);
echo "Fixed empty state and welcome message\n";
?>
