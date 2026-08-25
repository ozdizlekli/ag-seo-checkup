<?php
$c = file_get_contents("frontend/js/copilot.js");

// 1. Update the JSON prompt appending block in processAiSeoStep
$old_prompt = <<<JS
    p += `\\n\\nÖNEMLİ: Yanıtının SONUNA, analizine dayanan şu verileri içeren bir JSON bloğu ekle (\\`\\`\\`json ... \\`\\`\\` içinde olsun):
{
  "genel_skor": 0-100 arası genel sağlık skoru,
  "eeat_scores": { "deneyim": 0-100, "uzmanlik": 0-100, "otorite": 0-100, "guvenilirlik": 0-100 },
  "acil_aksiyon_plani": [ { "sorun": "Aksiyon açıklaması", "kategori": "Teknik/İçerik/UX vs", "onem": "Yüksek/Orta/Düşük" } ]
}`;
JS;

$new_prompt = <<<JS
    p += `\\n\\nÖNEMLİ: Yanıtının SONUNA, analizine dayanan şu verileri içeren, aşağıdaki YAPIDA KESİN bir JSON bloğu ekle (\\`\\`\\`json ... \\`\\`\\` içinde olsun):
{
  "overview_html": "<p>Sitenin genel özeti...</p>",
  "charts_data": {
    "trust_score": 0-100 arası genel sağlık skoru,
    "eeat_radar": {
      "experience": 0-100,
      "expertise": 0-100,
      "authoritativeness": 0-100,
      "trustworthiness": 0-100
    }
  },
  "action_plan_table": [
    { "issue": "Aksiyon açıklaması", "category": "Kategori", "priority": "high/medium/low", "color": "red/orange/green" }
  ]
}`;
JS;

$c = str_replace($old_prompt, $new_prompt, $c);

// Also need to update the prompt in runAutoAnalysis or runAutoFixes if it exists. (Lines 198, 603)
$c = preg_replace('/p \+= `\\\\n\\\\nÖNEMLİ: Yanıtının SONUNA.*?}`;/s', $new_prompt, $c);


// 2. Update initOrUpdateCharts function
$new_charts = <<<JS
function initOrUpdateCharts(data) {
    if (typeof Chart === 'undefined') return;

    let chartsData = data.charts_data || data; // Fallback if AI skips wrapper
    let trustScore = chartsData.trust_score || chartsData.genel_skor || 0;
    
    // 1. Genel Sağlık (Doughnut)
    const ctxOverall = document.getElementById('chart-overall-health');
    if (ctxOverall) {
        if (overallHealthChart) overallHealthChart.destroy();
        overallHealthChart = new Chart(ctxOverall, {
            type: 'doughnut',
            data: {
                labels: ['Güven Skoru', 'Eksik'],
                datasets: [{
                    data: [trustScore, 100 - trustScore],
                    backgroundColor: ['#10b981', '#e2e8f0'],
                    borderWidth: 0
                }]
            },
            options: { cutout: '80%' }
        });
        
        // Update the centered text inside doughnut (I had a plugin for this, but let's just use absolute div if they prefer)
        let textDiv = document.getElementById('chart-overall-health-text');
        if (textDiv) textDiv.innerHTML = trustScore + '%';
    }

    // 2. E-E-A-T Radar
    const ctxEeat = document.getElementById('chart-eeat');
    if (ctxEeat && chartsData.eeat_radar) {
        if (eeatChart) eeatChart.destroy();
        
        let eeat = chartsData.eeat_radar;
        // fallback to old turkish keys just in case AI messes up
        let exp = eeat.experience || eeat.deneyim || 0;
        let exp_t = eeat.expertise || eeat.uzmanlik || 0;
        let auth = eeat.authoritativeness || eeat.otorite || 0;
        let trust = eeat.trustworthiness || eeat.guvenilirlik || 0;

        eeatChart = new Chart(ctxEeat, {
            type: 'radar',
            data: {
                labels: ['Deneyim', 'Uzmanlık', 'Otorite', 'Güvenilirlik'],
                datasets: [{
                    label: 'E-E-A-T Profili',
                    data: [exp, exp_t, auth, trust],
                    backgroundColor: 'rgba(59, 130, 235, 0.2)',
                    borderColor: '#2563eb',
                    pointBackgroundColor: '#2563eb'
                }]
            },
            options: { scales: { r: { min: 0, max: 100 } } }
        });
    }

    // 3. Aksiyon Planı Tablosu
    const tableContainer = document.getElementById('action-plan-table-container');
    let actionPlan = data.action_plan_table || data.acil_aksiyon_plani || [];
    
    if (tableContainer && actionPlan.length > 0) {
        let tableHtml = '<table style="width:100%; border-collapse: collapse; text-align:left; font-size:13px;">';
        tableHtml += '<tr style="border-bottom: 2px solid #e2e8f0; color: #475569;"><th>Tespit Edilen Sorun</th><th>Kategori</th><th>Önem</th></tr>';

        actionPlan.forEach(item => {
            let priorityText = item.priority || item.onem || 'Medium';
            let color = item.color || (priorityText.toLowerCase().includes('high') || priorityText.toLowerCase().includes('yüksek') ? '#ef4444' : (priorityText.toLowerCase().includes('low') || priorityText.toLowerCase().includes('düşük') ? '#10b981' : '#f59e0b'));
            
            tableHtml += `<tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 10px;">\${item.issue || item.sorun || '-'}</td>
                <td style="padding: 10px; color: #64748b;">\${item.category || item.kategori || '-'}</td>
                <td style="padding: 10px;">
                    <span style="background: \${color}; color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px;">
                        \${priorityText.toUpperCase()}
                    </span>
                </td>
            </tr>`;
        });
        tableHtml += '</table>';
        tableContainer.innerHTML = tableHtml;
    }
    
    // 4. Battle Mode Bar Chart
    if (chartsData.competitor_comparison && chartsData.competitor_comparison.main_scores) {
        const comp = chartsData.competitor_comparison;
        document.getElementById('battle-chart-container').style.display = 'block';
        const ctxB = document.getElementById('chart-battle');
        if (ctxB) {
            if (battleChart) battleChart.destroy();
            battleChart = new Chart(ctxB, {
                type: 'bar',
                data: {
                    labels: ['İçerik', 'SEO', 'E-E-A-T'],
                    datasets: [
                        { label: comp.main_site_name || 'Senin Siten', data: comp.main_scores, backgroundColor: '#3b82f6' },
                        { label: comp.competitor_name || 'Rakip', data: comp.comp_scores, backgroundColor: '#ef4444' }
                    ]
                }
            });
        }
    }
}
JS;

$c = preg_replace('/function initOrUpdateCharts\(data\) \{.*?\}\s*\n\s*\/\/\s*---\s*CHART\.JS LOGIC END\s*---/s', $new_charts . "\n// --- CHART.JS LOGIC END ---", $c);


// And if AI returns data.overview_html, we can use it in the chat instead of markdown text!
// Wait, the JSON parser logic already extracts the JSON block and removes it from the markdown (`cleanText`).
// If data.overview_html exists, we should probably append it or replace cleanText?
// The user says "JSON { overview_html: ... }" so the AI might ONLY return the JSON block!
// In that case, `cleanText` will be empty. We should use `chartData.overview_html` if it exists.
$json_parser_update = <<<JS
      if (chartData) {
          initOrUpdateCharts(chartData);
          if (chartData.overview_html) {
              cleanText = chartData.overview_html + "\\n\\n" + cleanText;
          }
      }
JS;
$c = preg_replace('/if \(chartData\) \{\s*initOrUpdateCharts\(chartData\);\s*\}/s', $json_parser_update, $c);

// Update startBattleMode to use the new JSON format as well
$battle_prompt_new = <<<JS
            // Build Prompt - Rakip Savaş Modu
            const prompt = `GERÇEK ZAMANLI RAKİP SAVAŞ MODU (BATTLE MODE) AKTİF!

Site A:
\${(dataT.text || '').substring(0, 10000)}

Site B (Rakip):
\${(dataC.text || '').substring(0, 10000)}

Site A'nın rakibine göre içerik derinliği, SEO kalitesi ve E-E-A-T sinyalleri açısından eksiklerini JSON olarak analiz et.
ÖNEMLİ: Kesinlikle aşağıdaki JSON yapısını (```json ... ``` içinde) döndür:
{
  "overview_html": "<p>Sitenin genel özeti...</p>",
  "charts_data": {
    "trust_score": 0-100,
    "eeat_radar": { "experience": 0-100, "expertise": 0-100, "authoritativeness": 0-100, "trustworthiness": 0-100 },
    "competitor_comparison": {
      "main_site_name": "Site A",
      "competitor_name": "Site B",
      "main_scores": [85, 90, 75], 
      "comp_scores": [92, 88, 95]
    }
  },
  "action_plan_table": [
    { "issue": "Aksiyon açıklaması", "category": "SEO", "priority": "high", "color": "red" }
  ]
}`;
JS;

$c = preg_replace('/\/\/\s*Build Prompt - Rakip Savaş Modu.*?\}";/s', $battle_prompt_new, $c);


file_put_contents("frontend/js/copilot.js", $c);
echo "Updated JSON formats and Chart.js parsing.\n";
?>
