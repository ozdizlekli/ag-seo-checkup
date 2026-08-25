<?php
$index = file_get_contents("frontend/index.php");

// 1. Fix Chat Scroll
$index = str_replace(
    '<div class="card" id="copilot-card" style="flex: 3; border-top: 4px solid var(--accent); padding:0; display:flex; flex-direction:column;">',
    '<div class="card" id="copilot-card" style="flex: 3; border-top: 4px solid var(--accent); padding:0; display:flex; flex-direction:column; max-height: calc(100vh - 120px);">',
    $index
);

$index = str_replace(
    '<div class="copilot-chat" id="copilot-chat-messages-container" style="padding:24px; min-height: 350px;">',
    '<div class="copilot-chat" id="copilot-chat-messages-container" style="padding:24px; min-height: 350px; flex: 1; overflow-y: auto;">',
    $index
);


// 2. Fix History Accordion
$old_history = <<<HTML
    <!-- HISTORY LIST (Full width at bottom) -->
    <div class="card mt-24" id="copilot-history-card" style="width: 100%;">
      <div class="card__head">
        <div class="card__title" style="display:inline-flex; align-items:center;">Geçmiş Sohbetler</div>
        <button class="btn btn--ghost btn--sm" id="btn-clear-history">Temizle</button>
      </div>
      <div class="card__hint">Önceki URL analizleriniz burada listelenir. Tıklayarak sohbeti geri yükleyebilirsiniz.</div>
      <div id="copilot-history-list" class="mt-16" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:12px;">
HTML;

$new_history = <<<HTML
    <!-- HISTORY LIST (Full width at bottom) -->
    <div class="card mt-24" id="copilot-history-card" style="width: 100%;">
      <div class="card__head" style="cursor: pointer; user-select: none;" onclick="const hl = document.getElementById('copilot-history-list'); hl.style.display = (hl.style.display === 'none' ? 'flex' : 'none'); const icon = document.getElementById('history-toggle-icon'); icon.style.transform = (hl.style.display === 'none' ? 'rotate(0deg)' : 'rotate(180deg)');">
        <div class="card__title" style="display:inline-flex; align-items:center;">
            Geçmiş Sohbetler
            <svg id="history-toggle-icon" style="margin-left:8px; transition: transform 0.2s;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </div>
        <button class="btn btn--ghost btn--sm" id="btn-clear-history" onclick="event.stopPropagation();">Temizle</button>
      </div>
      <div class="card__hint">Önceki URL analizleriniz burada listelenir. Tıklayarak sohbeti geri yükleyebilirsiniz.</div>
      <div id="copilot-history-list" class="mt-16" style="display:none; flex-direction:column; gap:8px;">
HTML;

$index = str_replace($old_history, $new_history, $index);

file_put_contents("frontend/index.php", $index);
echo "Fixed chat scroll and history accordion\n";
?>
