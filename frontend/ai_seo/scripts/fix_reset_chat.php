<?php
$js = file_get_contents("frontend/ai_seo/js/copilot.js");

// 1. We already needed to disable save button for historical chats (from previous req).
$old_history_save = <<<JS
    if (copilotSaveBtn) {
      copilotSaveBtn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Kaydet`;
    }
JS;

$new_history_save = <<<JS
    if (copilotSaveBtn) {
      copilotSaveBtn.innerHTML = `✓ Kayıtlı`;
      copilotSaveBtn.style.opacity = '0.5';
      copilotSaveBtn.style.pointerEvents = 'none';
    }
JS;
$js = str_replace($old_history_save, $new_history_save, $js);

$old_reset_save = <<<JS
  if (copilotSaveBtn) {
    copilotSaveBtn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Kaydet`;
  }
JS;

$new_reset_save = <<<JS
  if (copilotSaveBtn) {
    copilotSaveBtn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Kaydet`;
    copilotSaveBtn.style.opacity = '1';
    copilotSaveBtn.style.pointerEvents = 'auto';
  }
JS;
$js = str_replace($old_reset_save, $new_reset_save, $js);


// 2. Fix the resetChat view logic so it doesn't hide the chat area when clicking New Chat!
$old_view_logic = <<<JS
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

// We just ensure actionView is flex and dbView is none, ALWAYS, if they called resetChat, unless they are literally clicking a "return to dashboard" button, but that's handled elsewhere.
// Wait, if resetChat(null) is called from "Yeni Analize Başla", we WANT actionView to be flex.
// If it's called from "Yeni Sohbet" inside actionView, we ALSO WANT actionView to be flex!
$new_view_logic = <<<JS
  const dbView = document.getElementById('ai-seo-dashboard-view');
  const actionView = document.getElementById('copilot-action-view');
  if (dbView && actionView) {
      dbView.style.display = 'none'; 
      actionView.style.display = 'flex';
  }
JS;
$js = str_replace($old_view_logic, $new_view_logic, $js);

// 3. Fix the display style of copilotInputArea!
// It was `copilotInputArea.style.display = "flex";` which ruined the column layout.
$js = str_replace('copilotInputArea.style.display = "flex";', 'copilotInputArea.style.display = "block";', $js);


file_put_contents("frontend/ai_seo/js/copilot.js", $js);
echo "Fixes applied to JS\n";
?>
