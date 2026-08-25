<?php
$copilot = file_get_contents("frontend/js/copilot.js");

$info_icon_html = '<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: currentColor; opacity:0.8;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';

$copilot = str_replace(
    '<button class="btn btn--primary" id="btn-analyze-all" style="flex: 1; font-size: 11.5px; padding: 8px; font-weight: 600;">${analyzeText}</button>',
    '<button class="btn btn--primary has-tooltip" id="btn-analyze-all" style="flex: 1; font-size: 11.5px; padding: 8px; font-weight: 600;" data-tooltip="Tüm adımları tek tıklamayla sırayla analiz etmeye başlar.">${analyzeText}' . $info_icon_html . '</button>',
    $copilot
);

$copilot = str_replace(
    '<button class="btn btn--secondary" id="btn-fix-all" style="flex: 1; font-size: 11.5px; padding: 8px; font-weight: 600;">${fixText}</button>',
    '<button class="btn btn--secondary has-tooltip" id="btn-fix-all" style="flex: 1; font-size: 11.5px; padding: 8px; font-weight: 600;" data-tooltip="Analiz edilen sorunları otomatik olarak çözmeye başlar.">${fixText}' . $info_icon_html . '</button>',
    $copilot
);

$copilot = str_replace(
    '<button class="btn btn--primary" id="btn-fix-all" style="flex: 1; font-size: 11.5px; padding: 8px; font-weight: 600; background: #2563eb; color: white; border: none;">${fixText}</button>',
    '<button class="btn btn--primary has-tooltip" id="btn-fix-all" style="flex: 1; font-size: 11.5px; padding: 8px; font-weight: 600; background: #2563eb; color: white; border: none;" data-tooltip="Analiz edilen sorunları otomatik olarak çözmeye başlar.">${fixText}' . $info_icon_html . '</button>',
    $copilot
);


file_put_contents("frontend/js/copilot.js", $copilot);
echo "Updated dynamic buttons in copilot.js\n";
?>
