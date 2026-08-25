<?php
$copilot = file_get_contents("frontend/js/copilot.js");

$old_parsing = <<<'EOF'
            let aiText = aiResult.candidates[0].content.parts[0].text;
            let htmlText = typeof marked !== 'undefined' ? marked.parse(aiText) : aiText;

            battleResults.innerHTML = htmlText;
EOF;

$new_parsing = <<<'EOF'
            let aiText = aiResult.candidates[0].content.parts[0].text;
            let htmlText = '';
            try {
                // Try to extract JSON if AI wrapped it in markdown code blocks
                let cleanJsonStr = aiText.replace(/```json/g, '').replace(/```/g, '').trim();
                // Sometimes AI returns markdown before/after the JSON. Just extract the first { ... } block.
                const jsonMatch = cleanJsonStr.match(/\{[\s\S]*\}/);
                if (jsonMatch) {
                    const parsedData = JSON.parse(jsonMatch[0]);
                    const analysis = parsedData.battle_mode_analysis || parsedData;
                    
                    const formatArray = (arr) => arr && arr.length ? `<ul>${arr.map(item => `<li>${item}</li>`).join('')}</ul>` : 'Veri yok.';
                    
                    let eksiklerHtml = '';
                    if (analysis.site_a_eksikleri) {
                        eksiklerHtml = `
                            <div class="compare-cols" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; margin-top:20px;">
                                <div class="col-box" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px;">
                                    <h4 style="color:#2563eb; font-size:14px; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">📚 İçerik Derinliği</h4>
                                    <div style="font-size:13px; color:#475569; line-height:1.6;">${typeof marked !== 'undefined' ? marked.parse(analysis.site_a_eksikleri.icerik_derinligi.join('\n\n')) : formatArray(analysis.site_a_eksikleri.icerik_derinligi)}</div>
                                </div>
                                <div class="col-box" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px;">
                                    <h4 style="color:#10b981; font-size:14px; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">🎯 SEO Kalitesi</h4>
                                    <div style="font-size:13px; color:#475569; line-height:1.6;">${typeof marked !== 'undefined' ? marked.parse(analysis.site_a_eksikleri.seo_kalitesi.join('\n\n')) : formatArray(analysis.site_a_eksikleri.seo_kalitesi)}</div>
                                </div>
                                <div class="col-box" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px;">
                                    <h4 style="color:#f59e0b; font-size:14px; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">🛡️ E-E-A-T Sinyalleri</h4>
                                    <div style="font-size:13px; color:#475569; line-height:1.6;">${typeof marked !== 'undefined' ? marked.parse(analysis.site_a_eksikleri.eeat_sinyalleri.join('\n\n')) : formatArray(analysis.site_a_eksikleri.eeat_sinyalleri)}</div>
                                </div>
                            </div>
                        `;
                    }
                    
                    htmlText = `
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px;">
                            <h3 style="font-size:16px; color:#1e293b; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                                <span style="background:#dc2626; color:white; padding:4px 8px; border-radius:6px; font-size:12px; font-weight:bold;">RAKİP ÜSTÜNLÜĞÜ</span> Neden Rakip Daha İyi?
                            </h3>
                            <div style="font-size:14px; color:#334155; line-height:1.7;">
                                ${typeof marked !== 'undefined' ? marked.parse(analysis.rakip_ustunluk_nedenleri || '') : analysis.rakip_ustunluk_nedenleri}
                            </div>
                            
                            ${eksiklerHtml}
                        </div>
                    `;
                } else {
                    throw new Error("JSON formatı bulunamadı.");
                }
            } catch(e) {
                // Fallback if not JSON or parsing failed
                console.warn("Battle mode JSON parse failed, falling back to markdown", e);
                htmlText = typeof marked !== 'undefined' ? marked.parse(aiText) : aiText;
            }

            battleResults.innerHTML = htmlText;
EOF;

$copilot = str_replace($old_parsing, $new_parsing, $copilot);

file_put_contents("frontend/js/copilot.js", $copilot);
echo "Updated parsing logic in copilot.js\n";
?>
