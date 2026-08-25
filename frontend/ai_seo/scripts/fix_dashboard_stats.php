<?php
$c = file_get_contents("frontend/js/copilot.js");

$c = str_replace(
    "const data = await res.json();\n      historyList.innerHTML = '';",
    "const data = await res.json();\n      window.agChatHistory = data.history || [];\n      if(typeof window.renderDashboard === 'function') window.renderDashboard();\n      historyList.innerHTML = '';",
    $c
);

file_put_contents("frontend/js/copilot.js", $c);
echo "Added agChatHistory to loadHistory\n";
?>
