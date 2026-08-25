<?php
$c = file_get_contents("frontend/js/copilot.js");

$reset_logic = <<<JS
function resetChat(loadFromHistory = null) {
    const dbView = document.getElementById('ai-seo-dashboard-view');
    const actionView = document.getElementById('copilot-action-view');
    if (dbView && actionView) {
        if (loadFromHistory) {
            dbView.style.display = 'none'; 
            actionView.style.display = 'flex';
        } else {
            dbView.style.display = 'block'; 
            actionView.style.display = 'none';
        }
    }
JS;

$c = preg_replace(
    "/function resetChat\(loadFromHistory = null\) \{.*?if \(loadFromHistory\) \{/s",
    $reset_logic . "\n    if (loadFromHistory) {",
    $c
);

// We should also NOT focus the text input if we are going to Dashboard!
// In resetChat, it does: copilotTextInput.focus();
// We should only do this if loadFromHistory is true OR we just clicked "Yeni Analiz Başlat"
// Actually "Yeni Analiz Başlat" calls startNewAnalysisFromDashboard, which DOES NOT call resetChat! It just shows actionView and calls addMessage.
// So resetChat is only called on "Temizle" button click! 
// If Temizle is clicked, they want to go back to Dashboard!

file_put_contents("frontend/js/copilot.js", $c);
echo "Fixed resetChat dashboard logic\n";
?>
