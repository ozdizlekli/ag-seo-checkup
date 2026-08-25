<?php
$c = file_get_contents("frontend/js/copilot.js");

// Replace renderDashboard logic
$c = preg_replace(
    "/const container = document.getElementById\('copilot-chat-messages-container'\); if\(container\) container\.innerHTML = `(.*?)`;/s",
    "const dbView = document.getElementById('ai-seo-dashboard-view');
      const actionView = document.getElementById('copilot-action-view');
      if(dbView) {
          dbView.innerHTML = `$1`;
          dbView.style.display = 'block';
      }
      if(actionView) actionView.style.display = 'none';",
    $c
);

// We must also hook the "Yeni Analiz Başlat" button in the dashboard to switch views!
// The previous code had:
// onclick="document.getElementById('copilot-chat-messages-container').innerHTML = ''; addMessage('...', 'ai', true, false); document.getElementById('copilot-text-input').focus();"
// Now we need it to toggle views.
$c = preg_replace(
    "/onclick=\"document\.getElementById\('copilot-chat-messages-container'\)\.innerHTML = ''; addMessage\((.*?)\); document\.getElementById\('copilot-text-input'\)\.focus\(\);\"/s",
    "onclick=\"document.getElementById('ai-seo-dashboard-view').style.display='none'; document.getElementById('copilot-action-view').style.display='block'; document.getElementById('copilot-chat-messages-container').innerHTML = ''; addMessage($1); document.getElementById('copilot-text-input').focus();\"",
    $c
);

// Add event listener for btn-return-dashboard
$btn_listener = <<<JS
  document.addEventListener('DOMContentLoaded', () => {
      const btnReturn = document.getElementById('btn-return-dashboard');
      if (btnReturn) {
          btnReturn.addEventListener('click', () => {
              const dbView = document.getElementById('ai-seo-dashboard-view');
              const actionView = document.getElementById('copilot-action-view');
              if(dbView && actionView) {
                  actionView.style.display = 'none';
                  dbView.style.display = 'block';
              }
          });
      }
  });
JS;

$c .= "\n" . $btn_listener;

// Modify resetChat(loadFromHistory)
// When loadFromHistory is true, we should SHOW the action view!
$c = preg_replace(
    "/(function resetChat\(loadFromHistory = null\) \{)/",
    "$1\n    const dbView = document.getElementById('ai-seo-dashboard-view');\n    const actionView = document.getElementById('copilot-action-view');\n    if (loadFromHistory && dbView && actionView) { dbView.style.display = 'none'; actionView.style.display = 'block'; }",
    $c
);

// When resetChat(null) is called (from Temizle button), it shows Dashboard
$c = preg_replace(
    "/if \(typeof window\.renderDashboard === 'function'\) window\.renderDashboard\(\);/",
    "if (typeof window.renderDashboard === 'function') { window.renderDashboard(); }\n    if (dbView && actionView) { dbView.style.display = 'block'; actionView.style.display = 'none'; }",
    $c
);

file_put_contents("frontend/js/copilot.js", $c);
echo "copilot.js updated for sibling views.\n";
?>
