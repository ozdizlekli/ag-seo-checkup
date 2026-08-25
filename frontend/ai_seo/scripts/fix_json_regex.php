<?php
$c = file_get_contents("frontend/js/copilot.js");

$old_json = <<<JS
      // JSON Extraction
      let cleanText = aiText;
      let chartData = null;
      try {
          const jsonMatch = aiText.match(/```json\s*(\{[\s\S]*?\})\s*```/);
          if (jsonMatch) {
              chartData = JSON.parse(jsonMatch[1]);
              cleanText = aiText.replace(jsonMatch[0], '').trim(); // Remove JSON from chat
          } else {
              // Try finding JSON block at the very end if no backticks
              const fallbackMatch = aiText.match(/\{[\s\S]*"trust_score"[\s\S]*\}$/);
              if (fallbackMatch) {
                  chartData = JSON.parse(fallbackMatch[0]);
                  cleanText = aiText.replace(fallbackMatch[0], '').trim();
              }
          }
      } catch (e) {
          console.warn("JSON Parse Error:", e);
      }
JS;

$new_json = <<<JS
      // JSON Extraction
      let cleanText = aiText;
      let chartData = null;
      let rawJsonStr = '';
      
      const jsonMatch = aiText.match(/```(?:json)?\s*(\{[\s\S]*?\})\s*```/i);
      if (jsonMatch) {
          cleanText = aiText.replace(jsonMatch[0], '').trim();
          rawJsonStr = jsonMatch[0];
          try {
              chartData = JSON.parse(jsonMatch[1]);
          } catch(e) { console.warn("JSON Parse Error:", e); }
      } else {
          const fallbackMatch = aiText.match(/\{[\s\S]*"trust_score"[\s\S]*\}$/);
          if (fallbackMatch) {
              cleanText = aiText.replace(fallbackMatch[0], '').trim();
              rawJsonStr = fallbackMatch[0];
              try { chartData = JSON.parse(fallbackMatch[0]); } catch(e){}
          }
      }
JS;

$c = str_replace($old_json, $new_json, $c);

// Also we need to fix the htmlText assignment
// Currently: let htmlText = ... + (typeof jsonMatch !== 'undefined' && jsonMatch ? '<div class="ai-raw-json" style="display:none;">' + jsonMatch[0] + '</div>' : '');
// We should change it to use rawJsonStr
$c = preg_replace('/let htmlText = \(typeof marked.*?;/', 'let htmlText = (typeof marked !== \'undefined\' ? marked.parse(cleanText) : cleanText) + (rawJsonStr ? \'<div class="ai-raw-json" style="display:none;">\' + rawJsonStr + \'</div>\' : \'\');', $c);


file_put_contents("frontend/js/copilot.js", $c);
echo "Fixed JSON parsing logic\n";
?>
