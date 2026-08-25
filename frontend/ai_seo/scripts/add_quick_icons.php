<?php
$index = file_get_contents("frontend/index.php");

$info_icon_blue = '<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: #3b82f6; opacity:0.8;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';

$index = str_replace(
    'Bana içerik eksiklerimi söyle</button>',
    'Bana içerik eksiklerimi söyle' . $info_icon_blue . '</button>',
    $index
);
$index = str_replace(
    'Rakiplerimden neden gerideyim?</button>',
    'Rakiplerimden neden gerideyim?' . $info_icon_blue . '</button>',
    $index
);
$index = str_replace(
    'Bu sayfa için SSS (FAQ) hazırla</button>',
    'Bu sayfa için SSS (FAQ) hazırla' . $info_icon_blue . '</button>',
    $index
);

// Modify class to have tooltip
$index = str_replace(
    '<button class="quick-action-btn"',
    '<button class="quick-action-btn has-tooltip" data-tooltip="Hızlı aksiyon ile AI\'a anında talimat gönderin."',
    $index
);

file_put_contents("frontend/index.php", $index);
echo "Added quick action tooltips\n";
?>
