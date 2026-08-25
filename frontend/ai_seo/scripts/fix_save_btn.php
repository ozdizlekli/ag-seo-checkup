<?php
$js = file_get_contents("frontend/ai_seo/js/copilot.js");

// Replace the active save button in the history loading section
$old_history_save = <<<JS
    if (copilotSaveBtn) {
      copilotSaveBtn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Kaydet`;
    }
JS;

$new_history_save = <<<JS
    if (copilotSaveBtn) {
      copilotSaveBtn.innerHTML = `✓ Kayıtlı`;
      copilotSaveBtn.style.opacity = '0.5';
      copilotSaveBtn.style.cursor = 'not-allowed';
      copilotSaveBtn.onclick = function(e){ e.preventDefault(); return false; };
    }
JS;

$js = str_replace($old_history_save, $new_history_save, $js);

// Restore the active save button in the resetChat section
$old_reset_save = <<<JS
  if (copilotSaveBtn) {
    copilotSaveBtn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Kaydet`;
  }
JS;

$new_reset_save = <<<JS
  if (copilotSaveBtn) {
    copilotSaveBtn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Kaydet`;
    copilotSaveBtn.style.opacity = '1';
    copilotSaveBtn.style.cursor = 'pointer';
    copilotSaveBtn.onclick = window.saveChatHistory; // Restore normal click if we overwrote it. Wait, the original just uses addEventListener.
    // If it uses addEventListener, overriding onclick in the other block might not prevent addEventListener from firing.
    // Let's just use pointer-events: none;
    copilotSaveBtn.style.pointerEvents = 'auto';
  }
JS;

// Actually, in the history_save let's use pointerEvents
$new_history_save = <<<JS
    if (copilotSaveBtn) {
      copilotSaveBtn.innerHTML = `✓ Kayıtlı`;
      copilotSaveBtn.style.opacity = '0.5';
      copilotSaveBtn.style.pointerEvents = 'none';
    }
JS;

$js = str_replace($old_history_save, $new_history_save, $js);
$js = str_replace($old_reset_save, $new_reset_save, $js);

file_put_contents("frontend/ai_seo/js/copilot.js", $js);
echo "Fixes applied to JS\n";
?>
