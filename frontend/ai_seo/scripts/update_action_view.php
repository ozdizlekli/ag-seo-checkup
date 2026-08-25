<?php
$index = file_get_contents("frontend/index.php");

// Change copilot-action-view from:
// <div id="copilot-action-view" style="display:none;">
//   <div class="copilot-container" style="border:none; margin-top:0; border-radius:0;">
// to:
// <div id="copilot-action-view" style="display:none; flex-direction: row; gap: 0;">
//   <div class="copilot-container" style="border:none; margin-top:0; border-radius:0; flex: 2; border-right: 1px solid var(--border);">
$index = preg_replace(
    '/<div id="copilot-action-view" style="display:none;">\s*<div class="copilot-container" style="border:none; margin-top:0; border-radius:0;">/s',
    '<div id="copilot-action-view" style="display:none; flex-direction: row; gap: 0; min-height: 80vh;">
          <div class="copilot-container" style="border:none; margin-top:0; border-radius:0; flex: 2; border-right: 1px solid var(--border); padding-right: 16px;">',
    $index
);

// We need to close the `copilot-container` and add the `live-report-panel` inside `copilot-action-view`.
// The `copilot-container` ends at the end of tab-3 (before `</div> </section>`).
// Let's find the end of `copilot-action-view`.
$live_report_html = <<<HTML
          </div> <!-- copilot-container bitiş -->
          
          <!-- CANLI RAPOR PANELİ -->
          <div class="live-report-panel" id="live-report-panel" style="flex: 1; padding: 24px; background: #f8fafc; overflow-y: auto; display: flex; flex-direction: column; gap: 32px;">
             <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                 <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#2563eb;"><path d="M3 3v18h18"/><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"/></svg>
                 Canlı Rapor Paneli
             </h3>
             
             <!-- Genel Sağlık Grafiği -->
             <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <h4 style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 12px; text-align: center;">Genel AI Güven Skoru</h4>
                <div style="height: 180px; position: relative; display: flex; justify-content: center; align-items: center;">
                    <canvas id="chart-overall-health"></canvas>
                </div>
             </div>
             
             <!-- E-E-A-T Radar Grafiği -->
             <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <h4 style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 12px; text-align: center;">E-E-A-T Sinyalleri</h4>
                <div style="height: 220px; position: relative;">
                    <canvas id="chart-eeat"></canvas>
                </div>
             </div>

             <!-- Rakip Kıyaslama Grafiği (Gizli) -->
             <div id="battle-chart-container" style="display: none; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <h4 style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 12px; text-align: center;">Savaş Modu Kıyaslaması</h4>
                <div style="height: 200px; position: relative;">
                    <canvas id="chart-battle"></canvas>
                </div>
             </div>
             
             <!-- Aksiyon Planı Tablosu -->
             <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <h4 style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 12px;">Acil Aksiyon Planı</h4>
                <div id="action-plan-table-container" style="font-size: 13px;">
                    <p style="color: #94a3b8; font-size: 12px; text-align: center; margin: 20px 0;">Henüz veri yok.</p>
                </div>
             </div>
          </div>
HTML;

$index = preg_replace(
    '/<\/div>\s*<\/div>\s*<\/section>\s*<!-- YENİ 4\. SEKME: YAPILACAKLAR \/ EKSİKLİKLER -->/s',
    $live_report_html . "\n        </div>\n        </section>\n\n        <!-- YENİ 4. SEKME: YAPILACAKLAR / EKSİKLİKLER -->",
    $index
);

file_put_contents("frontend/index.php", $index);
echo "Restructured copilot-action-view to include Live Report Panel.\n";
?>
