<?php
$index = file_get_contents("frontend/index.php");

// 1. Extract Tab 3
$start3 = '<section class="tab-panel" id="tab-3">';
$end3 = '<!-- YENİ 4. SEKME: YAPILACAKLAR / EKSİKLİKLER -->';

$pos_start3 = strpos($index, $start3);
$pos_end3 = strpos($index, $end3);

if ($pos_start3 !== false && $pos_end3 !== false) {
    $tab3_content = substr($index, $pos_start3, $pos_end3 - $pos_start3);
    file_put_contents("frontend/ai_seo/views/tab_ai_seo.php", $tab3_content);
}

// 2. Extract Tab 4
$start4 = '<section class="tab-panel" id="tab-4">';
$end4 = '      </main>';

$pos_start4 = strpos($index, $start4);
$pos_end4 = strpos($index, $end4);

if ($pos_start4 !== false && $pos_end4 !== false) {
    $tab4_content = substr($index, $pos_start4, $pos_end4 - $pos_start4);
    file_put_contents("frontend/ai_seo/views/tab_todos.php", $tab4_content);
}

// 3. Rewrite index.php
$index_new = substr($index, 0, $pos_start3) . 
             "<?php include 'ai_seo/views/tab_ai_seo.php'; ?>\n" . 
             "<?php include 'ai_seo/views/tab_todos.php'; ?>\n" . 
             substr($index, $pos_end4);

file_put_contents("frontend/index.php", $index_new);
echo "Extracted Tab 3 and Tab 4 into ai_seo/views/ and updated index.php\n";
?>
