<?php
$c = file_get_contents("frontend/js/copilot.js");
$c = str_replace("actionView.style.display = 'block'", "actionView.style.display = 'flex'", $c);
file_put_contents("frontend/js/copilot.js", $c);
echo "Replaced block with flex\n";
?>
