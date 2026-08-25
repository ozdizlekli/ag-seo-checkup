<?php
$index = file_get_contents("frontend/index.php");

// 1. Fix the steps stacking vertically
$index = str_replace(
    '<div class="copilot-steps" style="justify-content:center; gap:32px;">',
    '<div class="copilot-steps" style="display:flex; flex-direction:row; justify-content:center; align-items:center; gap:16px; width:100%; overflow-x:auto;">',
    $index
);

// 2. Fix the scroll overflowing
$index = str_replace(
    '<div class="card" id="copilot-card" style="flex: 3; border-top: 4px solid var(--accent); padding:0; display:flex; flex-direction:column; height: calc(100vh - 120px);">',
    '<div class="card" id="copilot-card" style="flex: 3; border-top: 4px solid var(--accent); padding:0; display:flex; flex-direction:column; height: calc(100vh - 120px); overflow: hidden;">',
    $index
);

$index = str_replace(
    '<div class="copilot-container" style="border:none; margin-top:0; border-radius:0; padding-right: 0; display:flex; flex-direction:column; flex:1;">',
    '<div class="copilot-container" style="border:none; margin-top:0; border-radius:0; padding-right: 0; display:flex; flex-direction:column; flex:1; min-height: 0;">',
    $index
);

file_put_contents("frontend/index.php", $index);
echo "Fixed steps flex and chat scroll container\n";
?>
