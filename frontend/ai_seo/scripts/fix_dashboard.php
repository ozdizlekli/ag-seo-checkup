<?php
$c = file_get_contents("frontend/js/copilot.js");

// We want loadHistory to call renderDashboard if the dashboard is currently visible.
// When resetChat(null) is called initially, renderDashboard is called, which creates the dashboard HTML.
// It contains "Agency OS Kontrol Paneli".
$c = preg_replace("/if \(document\.getElementById\('welcome-dashboard-flag'\)\) \{.*?renderDashboard\(\);\s*\}/s", "if (document.getElementById('copilot-chat-messages-container') && document.getElementById('copilot-chat-messages-container').innerHTML.includes('Agency OS Kontrol Paneli')) { renderDashboard(); }", $c);

// Also make sure renderDashboard is a global function or accessible
$c = str_replace("function renderDashboard()", "window.renderDashboard = function()", $c);
$c = str_replace("renderDashboard();", "if(typeof window.renderDashboard === 'function') window.renderDashboard();", $c);

file_put_contents("frontend/js/copilot.js", $c);
?>
