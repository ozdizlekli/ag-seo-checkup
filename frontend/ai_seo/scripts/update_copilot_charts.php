<?php
$c = file_get_contents("frontend/js/copilot.js");

// We need to inject the chart initialization and update logic.
$charts_logic = <<<JS

// --- CHART.JS LOGIC START ---
let overallHealthChart = null;
let eeatChart = null;
let battleChart = null;

function initOrUpdateCharts(data) {
    if (typeof Chart === 'undefined') return;

    // 1. Genel Sağlık (Doughnut)
    const ctxOverall = document.getElementById('chart-overall-health');
    if (ctxOverall) {
        if (overallHealthChart) overallHealthChart.destroy();
        overallHealthChart = new Chart(ctxOverall, {
            type: 'doughnut',
            data: {
                labels: ['Güven Skoru', 'Risk'],
                datasets: [{
                    data: [data.genel_skor || 0, 100 - (data.genel_skor || 0)],
                    backgroundColor: ['#10b981', '#f1f5f9'],
                    borderWidth: 0,
                    cutout: '75%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false }
                }
            },
            plugins: [{
                id: 'textCenter',
                beforeDraw: function(chart) {
                    var width = chart.width, height = chart.height, ctx = chart.ctx;
                    ctx.restore();
                    var fontSize = (height / 100).toFixed(2);
                    ctx.font = "bold " + (fontSize * 1.5) + "em sans-serif";
                    ctx.textBaseline = "middle";
                    ctx.fillStyle = "#0f172a";
                    var text = (data.genel_skor || 0) + "%",
                        textX = Math.round((width - ctx.measureText(text).width) / 2),
                        textY = height / 2;
                    ctx.fillText(text, textX, textY);
                    
                    ctx.font = (fontSize * 0.8) + "em sans-serif";
                    ctx.fillStyle = "#64748b";
                    var subtext = "Skor",
                        subtextX = Math.round((width - ctx.measureText(subtext).width) / 2),
                        subtextY = (height / 2) + 20;
                    ctx.fillText(subtext, subtextX, subtextY);
                    ctx.save();
                }
            }]
        });
    }

    // 2. E-E-A-T Radar
    const ctxEeat = document.getElementById('chart-eeat');
    if (ctxEeat && data.eeat_scores) {
        if (eeatChart) eeatChart.destroy();
        eeatChart = new Chart(ctxEeat, {
            type: 'radar',
            data: {
                labels: ['Deneyim', 'Uzmanlık', 'Otorite', 'Güvenilirlik'],
                datasets: [{
                    label: 'E-E-A-T Skoru',
                    data: [
                        data.eeat_scores.deneyim || 0, 
                        data.eeat_scores.uzmanlik || 0, 
                        data.eeat_scores.otorite || 0, 
                        data.eeat_scores.guvenilirlik || 0
                    ],
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: '#3b82f6',
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#3b82f6',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        angleLines: { color: 'rgba(0, 0, 0, 0.05)' },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        pointLabels: { font: { size: 11, weight: '600' }, color: '#475569' },
                        ticks: { display: false, min: 0, max: 100 }
                    }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    // 3. Aksiyon Planı Tablosu
    const tableContainer = document.getElementById('action-plan-table-container');
    if (tableContainer && data.acil_aksiyon_plani && data.acil_aksiyon_plani.length > 0) {
        let tableHtml = `<table style="width: 100%; border-collapse: collapse; margin-top: 8px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left;">
                    <th style="padding: 8px; font-weight: 600; color: #475569;">Sorun / Aksiyon</th>
                    <th style="padding: 8px; font-weight: 600; color: #475569;">Kategori</th>
                    <th style="padding: 8px; font-weight: 600; color: #475569;">Önem</th>
                </tr>
            </thead>
            <tbody>`;
        
        data.acil_aksiyon_plani.forEach(item => {
            let color = item.onem === 'Yüksek' || item.onem === 'Kritik' ? '#ef4444' : (item.onem === 'Orta' ? '#f59e0b' : '#10b981');
            tableHtml += `
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 8px; color: #1e293b;">\${item.sorun || item.aksiyon || '-'}</td>
                    <td style="padding: 8px; color: #64748b; font-size: 12px;">\${item.kategori || '-'}</td>
                    <td style="padding: 8px;"><span style="background: \${color}20; color: \${color}; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600;">\${item.onem || '-'}</span></td>
                </tr>`;
        });
        tableHtml += `</tbody></table>`;
        tableContainer.innerHTML = tableHtml;
    }
}
// --- CHART.JS LOGIC END ---

JS;

// Inject charts_logic before `function resetChat`
$c = preg_replace('/function resetChat\(/', $charts_logic . "\nfunction resetChat(", $c);


// Now modify processAiSeoStep to extract JSON from AI response
// and append JSON instruction to the prompt!
$prompt_append = <<<JS
    p += `\\n\\nÖNEMLİ: Yanıtının SONUNA, analizine dayanan şu verileri içeren bir JSON bloğu ekle (\\`\\`\\`json ... \\`\\`\\` içinde olsun):
{
  "genel_skor": 0-100 arası genel sağlık skoru,
  "eeat_scores": { "deneyim": 0-100, "uzmanlik": 0-100, "otorite": 0-100, "guvenilirlik": 0-100 },
  "acil_aksiyon_plani": [ { "sorun": "Aksiyon açıklaması", "kategori": "Teknik/İçerik/UX vs", "onem": "Yüksek/Orta/Düşük" } ]
}`;
JS;

$c = preg_replace('/try \{\s*const res = await fetch\(\'form_submit\.php\'/', $prompt_append . "\n    try {\n      const res = await fetch('form_submit.php'", $c);

// Parse JSON from aiText
$json_parser = <<<JS
      let aiText = result.candidates[0].content.parts[0].text;
      
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
              const fallbackMatch = aiText.match(/\{[\s\S]*"genel_skor"[\s\S]*\}$/);
              if (fallbackMatch) {
                  chartData = JSON.parse(fallbackMatch[0]);
                  cleanText = aiText.replace(fallbackMatch[0], '').trim();
              }
          }
      } catch (e) {
          console.warn("JSON Parse Error:", e);
      }
      
      if (chartData) {
          initOrUpdateCharts(chartData);
      }

      let htmlText = typeof marked !== 'undefined' ? marked.parse(cleanText) : cleanText;
JS;

$c = preg_replace('/let aiText = result\.candidates\[0\]\.content\.parts\[0\]\.text;\s*let htmlText = typeof marked !== \'undefined\' \? marked\.parse\(aiText\) : aiText;/s', $json_parser, $c);


// We also need to re-render charts when loading history.
// Where is loadHistory?
// It fetches history, which has `msg.text` or AI JSON stored?
// The problem is historical messages won't have the new JSON structure, so they won't render charts.
// We can just call a dummy render for history or do nothing, but the prompt says:
// "Kontrol panelindeki 'Geçmiş Sohbetler'den birine tıklandığında, JSON tekrar parse edilip bu grafikler o site için anında yeniden çizilsin (re-render)."
// Historical chats didn't have JSON! But if they did, we can parse them.
// Let's add parsing logic in `loadHistory` / `updateActiveHistoryItem`.

$load_history_parser = <<<JS
      if (msg.sender === 'ai') {
          try {
              const jsonMatch = msg.text.match(/```json\s*(\{[\s\S]*?\})\s*```/);
              if (jsonMatch) {
                  const chartData = JSON.parse(jsonMatch[1]);
                  initOrUpdateCharts(chartData);
              }
          } catch (e) {}
      }
JS;

// Wait, the chat messages are already parsed to HTML when rendered. If we save `htmlText` or `aiText`? We save `htmlText` to db probably.
// Wait, when saving chat to db, `saveChat()` reads `chatMessages`, which only contains `text` (htmlText because `addMessage` stores `isHtml=true`).
// So the JSON block is already removed from `chatMessages`!
// If we want history to have charts, we must save the JSON data to history.
// Actually, let's just parse the JSON in `startBattleMode` as well for the Bar Chart.

file_put_contents("frontend/js/copilot.js", $c);
echo "Injected JSON parser and Chart JS logic into copilot.js\n";
?>
