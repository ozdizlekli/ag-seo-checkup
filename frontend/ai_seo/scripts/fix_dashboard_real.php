<?php
$c = file_get_contents("frontend/js/copilot.js");

// 1. Update the fallback regex in JSON extraction for 'trust_score'
$c = str_replace(
    'const fallbackMatch = aiText.match(/\{[\s\S]*"genel_skor"[\s\S]*\}$/);',
    'const fallbackMatch = aiText.match(/\{[\s\S]*"trust_score"[\s\S]*\}$/);',
    $c
);

// 2. Update renderDashboard to parse JSON from history for REAL scores!
$dashboard_logic = <<<JS
    window.renderDashboard = function() {
      const history = window.agChatHistory || [];
      const totalAnalyses = history.length;
      
      let battleCount = 0;
      let totalEEAT = 0;
      let eeatCount = 0;
      
      history.forEach(h => {
         if (h.messages) {
            const msgs = JSON.stringify(h.messages).toLowerCase();
            if (msgs.includes('rakip') || msgs.includes('battle') || msgs.includes('competitor_comparison')) battleCount++;
            
            // Try to extract real EEAT score from JSON blocks in history
            h.messages.forEach(msg => {
                if (msg.sender === 'ai' && msg.text) {
                    try {
                        const jsonMatch = msg.text.match(/<div class="ai-raw-json" style="display:none;">```json\s*(\{[\s\S]*?\})\s*```<\/div>/);
                        if (jsonMatch) {
                            const data = JSON.parse(jsonMatch[1]);
                            let charts = data.charts_data || data;
                            if (charts.eeat_radar) {
                                let exp = charts.eeat_radar.experience || 0;
                                let expt = charts.eeat_radar.expertise || 0;
                                let auth = charts.eeat_radar.authoritativeness || 0;
                                let trust = charts.eeat_radar.trustworthiness || 0;
                                let avg = (exp + expt + auth + trust) / 4;
                                if (avg > 0) {
                                    totalEEAT += avg;
                                    eeatCount++;
                                }
                            }
                        }
                    } catch(e) {}
                }
            });
         }
      });

      const avgEEAT = eeatCount > 0 ? Math.round(totalEEAT / eeatCount) : 0;
JS;

$c = preg_replace('/window\.renderDashboard = function\(\) \{.*?const avgEEAT = eeatCount > 0 \? Math\.round\(totalEEAT \/ eeatCount\) : 0;/s', $dashboard_logic, $c);


file_put_contents("frontend/js/copilot.js", $c);
echo "Updated Dashboard logic to use REAL data from DB/history\n";
?>
