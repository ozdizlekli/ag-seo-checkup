<?php
$tab = file_get_contents("frontend/ai_seo/views/tab_ai_seo.php");

// 1. Remove has-tooltip from top buttons and use title
$tab = str_replace('has-tooltip" data-tooltip="Sitenizi en dişli rakiplerinizle kıyaslayın."', '" title="Sitenizi en dişli rakiplerinizle kıyaslayın."', $tab);
$tab = str_replace('has-tooltip" data-tooltip="Tüm analiz adımları tamamlandıktan sonra indirilebilir."', '" title="Tüm analiz adımları tamamlandıktan sonra indirilebilir."', $tab);
$tab = str_replace('has-tooltip" data-tooltip="Eksikleri listeye aktar."', '" title="Eksikleri listeye aktar."', $tab);
$tab = str_replace('has-tooltip" data-tooltip="Sohbeti geçmişe kaydeder."', '" title="Sohbeti geçmişe kaydeder."', $tab);

// 2. Change colors of all these buttons to gray tones
$tab = str_replace('background:#dc2626;', 'background:#475569;', $tab); // Savaş Modu (red -> gray)
$tab = str_replace('box-shadow:0 2px 8px rgba(220,38,38,0.4);', 'box-shadow:0 2px 8px rgba(71,85,105,0.4);', $tab); // shadow
$tab = str_replace('background:#ef4444;', 'background:#64748b;', $tab); // Rapor (red -> gray)
$tab = str_replace('background: #2563eb;', 'background:#475569;', $tab); // Gönder (blue -> gray)
// Kaydet is standard btn--primary, let's inject gray background
$tab = str_replace('id="copilot-manual-save-btn" style="display:inline-flex; align-items:center; padding: 6px 12px; font-size:12px; border-radius:12px;"', 'id="copilot-manual-save-btn" style="display:inline-flex; align-items:center; padding: 6px 12px; font-size:12px; border-radius:12px; background:#475569; color:white; border:none;"', $tab);

// 3. Fix progress bar overflow
$tab = str_replace('overflow-x:auto; overflow-y:hidden; padding:100px 4px 4px; margin-top:-100px;', 'flex-wrap:wrap; margin-bottom:8px;', $tab);

// 4. Prompt suggestions and Gray buttons
// Search for GREEN BUTTONS and quick-actions
$old_llm_and_quick_actions = <<<HTML
            <!-- GREEN BUTTONS (Tüm Siteyi Analiz Et / Eksikleri Gider) -->
            <div id="copilot-llms-container" style="display:flex; flex-direction:row; gap:16px; margin-bottom: 16px;">
               <button class="btn btn--primary" id="btn-auto-analyze" style="flex:1; padding: 12px; font-weight:600; background:#10b981; border:none;">
                   <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; color:#fbbf24;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                   Tüm Siteyi Analiz Et
               </button>
               <button class="btn btn--primary" id="btn-auto-fix" style="flex:1; padding: 12px; font-weight:600; background:#10b981; border:none; display:none;">
                   <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                   Tüm Eksikleri Gider
               </button>
            </div>
            
            <div class="quick-actions" id="copilot-quick-actions" style="margin-bottom:12px; padding-top:4px;">
              <button class="quick-action-btn has-tooltip" data-tooltip="İçeriği daha inandırıcı hale getirmek için ipuçları iste." onclick="document.getElementById('copilot-secondary-input').value='İçeriği daha ikna edici nasıl yaparım?'; document.getElementById('btn-send-message').click();">İçeriği daha ikna edici nasıl yaparım?</button>
              <button class="quick-action-btn has-tooltip" data-tooltip="Eksik olan E-E-A-T veya semantik unsurları listele." onclick="document.getElementById('copilot-secondary-input').value='Bana içerik eksiklerimi söyle'; document.getElementById('btn-send-message').click();">Bana içerik eksiklerimi söyle</button>
              <button class="quick-action-btn has-tooltip" data-tooltip="Makalenin anlamsal derinliğini artıracak anahtar kelimeler öner." onclick="document.getElementById('copilot-secondary-input').value='Hangi LSI kelimelerini kullanmalıyım?'; document.getElementById('btn-send-message').click();">Hangi LSI kelimelerini kullanmalıyım?</button>
              <button class="quick-action-btn has-tooltip" data-tooltip="Rakiplerin senden daha iyi olduğu spesifik noktaları öğren." onclick="document.getElementById('copilot-secondary-input').value='Rakiplerimden neden gerideyim?'; document.getElementById('btn-send-message').click();">Rakiplerimden neden gerideyim?</button>
            </div>
HTML;

$new_llm_and_quick_actions = <<<HTML
            <!-- QUICK ACTIONS (Soru Önerileri - Max 3 adet, Butonların Üstünde) -->
            <div id="copilot-quick-actions" style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px; justify-content:center;">
              <button class="quick-action-btn" title="İçeriği daha inandırıcı hale getirmek için ipuçları iste." onclick="document.getElementById('copilot-secondary-input').value='İçeriği daha ikna edici nasıl yaparım?'; document.getElementById('btn-send-message').click();">İçeriği daha ikna edici nasıl yaparım?</button>
              <button class="quick-action-btn" title="Eksik olan E-E-A-T veya semantik unsurları listele." onclick="document.getElementById('copilot-secondary-input').value='Bana içerik eksiklerimi söyle'; document.getElementById('btn-send-message').click();">Bana içerik eksiklerimi söyle</button>
              <button class="quick-action-btn" title="Rakiplerin senden daha iyi olduğu spesifik noktaları öğren." onclick="document.getElementById('copilot-secondary-input').value='Rakiplerimden neden gerideyim?'; document.getElementById('btn-send-message').click();">Rakiplerimden neden gerideyim?</button>
            </div>

            <!-- ANALYZER BUTTONS (Tüm Siteyi Analiz Et / Eksikleri Gider) -->
            <div id="copilot-llms-container" style="display:flex; flex-direction:row; gap:16px; margin-bottom: 16px;">
               <button class="btn btn--primary" id="btn-auto-analyze" style="flex:1; padding: 12px; font-weight:600; background:#475569; border:none; border-radius:8px; color:white; display:flex; justify-content:center; align-items:center;">
                   <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; color:#cbd5e1;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                   Tüm Siteyi Analiz Et
               </button>
               <button class="btn btn--primary" id="btn-auto-fix" style="flex:1; padding: 12px; font-weight:600; background:#475569; border:none; display:none; border-radius:8px; color:white; justify-content:center; align-items:center;">
                   <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                   Tüm Eksikleri Gider
               </button>
            </div>
HTML;

$tab = str_replace($old_llm_and_quick_actions, $new_llm_and_quick_actions, $tab);

// 5. Change "Gönder" input button to gray
$tab = str_replace('id="btn-send-message" style="border-radius:24px; padding:0 24px; height:42px;"', 'id="btn-send-message" style="border-radius:24px; padding:0 24px; height:42px; background:#475569; color:white; border:none;"', $tab);


file_put_contents("frontend/ai_seo/views/tab_ai_seo.php", $tab);
echo "Fixes applied to tab_ai_seo.php\n";
?>
