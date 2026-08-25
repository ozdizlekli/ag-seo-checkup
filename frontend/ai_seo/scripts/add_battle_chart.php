<?php
$c = file_get_contents("frontend/js/copilot.js");

$battle_chart = <<<JS
const analysis = parsedData.battle_mode_analysis || parsedData;

                    if (typeof Chart !== 'undefined') {
                        document.getElementById('battle-chart-container').style.display = 'block';
                        const ctxB = document.getElementById('chart-battle');
                        if (ctxB) {
                            if (battleChart) battleChart.destroy();
                            battleChart = new Chart(ctxB, {
                                type: 'bar',
                                data: {
                                    labels: ['İçerik Derinliği', 'SEO Kalitesi', 'E-E-A-T'],
                                    datasets: [
                                        { label: 'Senin Siten', data: [analysis.site_a_skorlari?.icerik || 60, analysis.site_a_skorlari?.seo || 65, analysis.site_a_skorlari?.eeat || 50], backgroundColor: '#3b82f6' },
                                        { label: 'Rakip', data: [analysis.site_b_skorlari?.icerik || 85, analysis.site_b_skorlari?.seo || 90, analysis.site_b_skorlari?.eeat || 88], backgroundColor: '#ef4444' }
                                    ]
                                }
                            });
                        }
                    }
JS;

$c = str_replace("const analysis = parsedData.battle_mode_analysis || parsedData;", $battle_chart, $c);
file_put_contents("frontend/js/copilot.js", $c);
echo "Added Battle Chart logic\n";
?>
