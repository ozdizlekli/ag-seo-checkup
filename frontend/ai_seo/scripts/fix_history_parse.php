<?php
$c = file_get_contents("frontend/js/copilot.js");
$c = str_replace(
    "chatMessages.forEach(msg => { addMessage(msg.text, msg.sender, msg.isHtml, false); });",
    "chatMessages.forEach(msg => { addMessage(msg.text, msg.sender, msg.isHtml, false); if (msg.sender === 'ai') { try { const jsonMatch = msg.text.match(/<div class=\"ai-raw-json\" style=\"display:none;\">```json\s*(\{[\s\S]*?\})\s*```<\/div>/); if (jsonMatch) { const chartData = JSON.parse(jsonMatch[1]); initOrUpdateCharts(chartData); } } catch(e){} } });",
    $c
);
file_put_contents("frontend/js/copilot.js", $c);
echo "Fixed history parse\n";
?>
