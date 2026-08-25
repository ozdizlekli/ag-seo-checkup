<?php
$app = file_get_contents("frontend/js/app.js");

$old_opp = <<<'EOF'
       document.getElementById('t8-opportunities').innerHTML = aiParsed.contentOpportunities.map((o, i) => `
         <div style="margin-bottom:12px; page-break-inside: avoid;">
           <strong style="color:#333; font-size:14px;">${i+1}. ${escapeHtml(o.title || 'Fırsat')}</strong>
           <p style="margin:4px 0 0 0; font-size:14px; color:#444;">${escapeHtml(o.desc || o)}</p>
         </div>
       `).join('');
EOF;

$new_opp = <<<'EOF'
       document.getElementById('t8-opportunities').innerHTML = aiParsed.contentOpportunities.map((o, i) => `
         <details class="seo-details">
           <summary>${i+1}. ${escapeHtml(o.title || 'Fırsat')}</summary>
           <div class="details-content">${escapeHtml(o.desc || o)}</div>
         </details>
       `).join('');
EOF;

$app = str_replace($old_opp, $new_opp, $app);

$old_qs = <<<'EOF'
       document.getElementById('t8-top-questions').innerHTML = aiParsed.contentEffectiveness.topQuestions.map((q, i) => `
         <div style="margin-bottom:12px; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; page-break-inside: avoid;">
           <strong style="display:block; margin-bottom:4px; font-size:14px; color:#1e293b;">Soru ${i+1}: ${escapeHtml(q.question)}</strong>
           <p style="margin:0 0 4px 0; font-size:13px; color:#475569;">${escapeHtml(q.analysis)}</p>
           <span style="font-size:12px; font-weight:600; color:${q.score >= 8 ? '#10b981' : (q.score >= 5 ? '#f59e0b' : '#ef4444')};">Yanıt Skoru: ${q.score}/10</span>
         </div>
       `).join('');
EOF;

$new_qs = <<<'EOF'
       document.getElementById('t8-top-questions').innerHTML = aiParsed.contentEffectiveness.topQuestions.map((q, i) => `
         <details class="seo-details">
           <summary style="color:${q.score >= 8 ? '#10b981' : (q.score >= 5 ? '#f59e0b' : '#ef4444')}">Skor: ${q.score}/10 - ${escapeHtml(q.question)}</summary>
           <div class="details-content">
             <p style="margin:0 0 4px 0;">${escapeHtml(q.analysis)}</p>
           </div>
         </details>
       `).join('');
EOF;

$app = str_replace($old_qs, $new_qs, $app);
file_put_contents("frontend/js/app.js", $app);

$copilot = file_get_contents("frontend/js/copilot.js");
$old_battle = <<<'EOF'
                        if (typeof val === 'string') {
                            parsedHtml += `<p style="color:#475569; margin-top:4px;">${val}</p>`;
                        } else if (Array.isArray(val)) {
                            parsedHtml += `<ul style="color:#475569; margin-top:4px; padding-left:20px;">`;
                            val.forEach(item => {
                                parsedHtml += `<li style="margin-bottom:4px;">${typeof item === 'object' ? JSON.stringify(item) : item}</li>`;
                            });
                            parsedHtml += `</ul>`;
                        } else if (typeof val === 'object') {
                            parsedHtml += `<pre style="background:#f1f5f9; padding:8px; border-radius:4px; font-size:13px; color:#334155;">${JSON.stringify(val, null, 2)}</pre>`;
                        }
                        parsedHtml += `</div>`;
EOF;

$new_battle = <<<'EOF'
                        parsedHtml += `<details class="seo-details" style="margin-top:8px;">
                            <summary>Detayları Gör</summary>
                            <div class="details-content">`;
                        if (typeof val === 'string') {
                            parsedHtml += `<p style="color:#475569; margin-top:4px;">${val}</p>`;
                        } else if (Array.isArray(val)) {
                            parsedHtml += `<ul style="color:#475569; margin-top:4px; padding-left:20px;">`;
                            val.forEach(item => {
                                parsedHtml += `<li style="margin-bottom:4px;">${typeof item === 'object' ? JSON.stringify(item) : item}</li>`;
                            });
                            parsedHtml += `</ul>`;
                        } else if (typeof val === 'object') {
                            parsedHtml += `<pre style="background:#f1f5f9; padding:8px; border-radius:4px; font-size:13px; color:#334155;">${JSON.stringify(val, null, 2)}</pre>`;
                        }
                        parsedHtml += `</div></details></div>`;
EOF;

$copilot = str_replace($old_battle, $new_battle, $copilot);
file_put_contents("frontend/js/copilot.js", $copilot);

echo "Applied Progressive Disclosure\n";
?>
