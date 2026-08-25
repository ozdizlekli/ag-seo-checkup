<?php
$index = file_get_contents("frontend/index.php");

// 1. Update #copilot-card
$index = str_replace(
    '<div class="card" id="copilot-card" style="border-top: 4px solid var(--accent); padding:0;">',
    '<div class="card" id="copilot-card" style="border-top: 4px solid var(--accent); padding:0; display:flex; flex-direction:column; max-height: 85vh;">',
    $index
);

// 2. Update .copilot-container
$index = str_replace(
    '<div class="copilot-container" style="border:none; margin-top:0; border-radius:0;">',
    '<div class="copilot-container" style="border:none; margin-top:0; border-radius:0; flex:1; display:flex; flex-direction:column; min-height:0;">',
    $index
);

// 3. Update #copilot-chat
$index = str_replace(
    '<div class="copilot-chat" id="copilot-chat" style="height: 40vh; min-height: 300px; max-height: 500px; overflow-y: auto;">',
    '<div class="copilot-chat" id="copilot-chat" style="flex:1; overflow-y:auto; min-height:0;">',
    $index
);

// 4. Update copilot-actions
$index = str_replace(
    '<div class="copilot-actions" id="copilot-actions">',
    '<div class="copilot-actions" id="copilot-actions" style="flex-shrink:0;">',
    $index
);

// 5. Update copilot-quick-actions
// Currently: <div class="quick-actions" id="copilot-quick-actions" style="display:none;">
$index = preg_replace(
    '/<div class="quick-actions" id="copilot-quick-actions" style="display:none;">/',
    '<div class="quick-actions" id="copilot-quick-actions" style="display:none; flex-shrink:0;">',
    $index
);

// 6. Update copilot-input-area
// Currently: <div class="copilot-input-area" id="copilot-input-area" style="padding: 16px; border-top: 1px solid var(--border); display: flex; gap: 8px; background: #fff;">
$index = preg_replace(
    '/<div class="copilot-input-area" id="copilot-input-area" style="([^"]*)">/',
    '<div class="copilot-input-area" id="copilot-input-area" style="$1 flex-shrink:0;">',
    $index
);

file_put_contents("frontend/index.php", $index);
echo "Updated flexbox layout in index.php\n";
?>
