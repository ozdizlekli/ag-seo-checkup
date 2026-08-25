<?php
$index = file_get_contents("frontend/index.php");

$toggle_html = '
<div style="display: flex; align-items: center; gap: 8px; margin-right: auto; margin-left: 16px;">
    <label class="switch has-tooltip" data-tooltip="Müşteri sunumu için karmaşık panelleri gizler, sadece sonuçlara odaklanır.">
        <input type="checkbox" id="client-view-toggle">
        <span class="slider round"></span>
    </label>
    <span style="font-size: 13px; font-weight: 500; color: var(--muted); cursor: pointer;" onclick="document.getElementById(\'client-view-toggle\').click()">Sunum Modu</span>
</div>
';

// Add CSS for switch inside index.php if not exists, but better add to copilot.css
$css_switch = '
.switch { position: relative; display: inline-block; width: 34px; height: 20px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 20px; }
.slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 2px; bottom: 2px; background-color: white; transition: .4s; border-radius: 50%; }
input:checked + .slider { background-color: #3b82f6; }
input:checked + .slider:before { transform: translateX(14px); }
';

file_put_contents("frontend/css/copilot.css", "\n" . $css_switch, FILE_APPEND);

// Insert toggle right before the download button in header
$index = preg_replace('/(<button class="btn btn--ghost has-tooltip".*?id="copilot-reset-btn")/', $toggle_html . "$1", $index);

file_put_contents("frontend/index.php", $index);
echo "Injected Presentation Toggle.\n";
?>
