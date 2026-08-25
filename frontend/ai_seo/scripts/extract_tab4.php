<?php
$index = file_get_contents("frontend/index.php");

$start4 = '<section class="tab-panel" id="tab-4">';
$end4 = '  </main>';

$pos_start4 = strpos($index, $start4);
$pos_end4 = strpos($index, $end4);

if ($pos_start4 !== false && $pos_end4 !== false) {
    // Extract tab 4, but leave the closing tag of the tab panel (</section>) which might be right before </main>
    $tab4_content = substr($index, $pos_start4, $pos_end4 - $pos_start4);
    // actually, let's just search for </section> after start4. Wait, no.
    
    // find the </section> before </main>
    $end_section = strrpos(substr($index, 0, $pos_end4), '</section>');
    if ($end_section !== false && $end_section > $pos_start4) {
        $tab4_content = substr($index, $pos_start4, $end_section + 10 - $pos_start4);
        file_put_contents("frontend/ai_seo/views/tab_todos.php", $tab4_content);
        
        $index_new = substr($index, 0, $pos_start4) . 
                     "<?php include 'ai_seo/views/tab_todos.php'; ?>\n" . 
                     substr($index, $end_section + 10);
        file_put_contents("frontend/index.php", $index_new);
        echo "Extracted Tab 4 successfully\n";
    }
} else {
    echo "Could not find start4 or end4\n";
}
?>
