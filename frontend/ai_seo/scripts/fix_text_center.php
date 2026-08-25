<?php
$c = file_get_contents("frontend/js/copilot.js");

$plugin_str = "options: { cutout: '80%' }, plugins: [{ id: 'textCenter', beforeDraw: function(chart) { var width = chart.width, height = chart.height, ctx = chart.ctx; ctx.restore(); var fontSize = (height / 100).toFixed(2); ctx.font = 'bold ' + (fontSize * 1.5) + 'em sans-serif'; ctx.textBaseline = 'middle'; ctx.fillStyle = '#0f172a'; var text = trustScore + '%', textX = Math.round((width - ctx.measureText(text).width) / 2), textY = height / 2; ctx.fillText(text, textX, textY); ctx.save(); } }]";

$c = str_replace("options: { cutout: '80%' }", $plugin_str, $c);

file_put_contents("frontend/js/copilot.js", $c);
echo "Added textCenter plugin back\n";
?>
