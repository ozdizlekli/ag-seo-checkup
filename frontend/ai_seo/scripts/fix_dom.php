<?php
$index = file_get_contents("frontend/index.php");

// If ai-seo-dashboard-view already exists, do nothing or remove it to re-add.
if (strpos($index, 'id="ai-seo-dashboard-view"') === false) {
    // 1. Insert Dashboard View and start of Action View BEFORE copilot-card
    $index = preg_replace(
        '/<div class="card" id="copilot-card"/',
        '<div id="ai-seo-dashboard-view"></div>
        <div id="copilot-action-view" style="display: none; flex-direction: row; gap: 0; min-height: 80vh;">
        <div class="card" id="copilot-card" style="flex: 2; border-right: 1px solid var(--border); border-top: 4px solid var(--accent); padding:0;"',
        $index
    );
    
    // 2. Find the closing of live-report-panel and close copilot-action-view
    // Actually, I already injected live-report-panel into index.php. It is currently at the end of tab-3.
    // Let's just append `</div>` for copilot-action-view at the very end of tab-3.
    $index = preg_replace(
        '/(<\/div>\s*<\/section>\s*<!-- YENİ 4\. SEKME: YAPILACAKLAR \/ EKSİKLİKLER -->)/',
        "</div>\n$1", // close copilot-action-view
        $index
    );
    
    file_put_contents("frontend/index.php", $index);
    echo "Added ai-seo-dashboard-view and wrapped in copilot-action-view.\n";
} else {
    echo "Already contains dashboard view.\n";
}
?>
