<?php
$c = file_get_contents("frontend/js/copilot.js");

$c = str_replace(
    "let htmlText = typeof marked !== 'undefined' ? marked.parse(cleanText) : cleanText;",
    "let htmlText = (typeof marked !== 'undefined' ? marked.parse(cleanText) : cleanText) + (typeof jsonMatch !== 'undefined' && jsonMatch ? '<div class=\"ai-raw-json\" style=\"display:none;\">' + jsonMatch[0] + '</div>' : '');",
    $c
);

// Also need to make sure `loadHistory` correctly parses this hidden div.
// Currently loadHistory does: `msg.text.match(/```json\s*(\{[\s\S]*?\})\s*```/)`
// If it's wrapped in `<div ...>`, the regex will still match the ` ```json ... ``` ` inside it! So it's perfect.
file_put_contents("frontend/js/copilot.js", $c);
echo "Added hidden JSON div logic\n";
?>
