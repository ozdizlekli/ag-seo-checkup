<?php
$index = file_get_contents("frontend/index.php");

// 1. Tooltips
$index = str_replace('<span>İş Bağlamı</span>', '<span class="has-tooltip" data-tooltip="Sitenizin sektörü, hitap ettiği kitle ve anahtar kelime varlıkları yapay zeka gözüyle analiz edilir.">İş Bağlamı</span>', $index);
$index = str_replace('<span>Etkililik</span>', '<span class="has-tooltip" data-tooltip="Kullanıcıların bu sayfa bağlamında sorabileceği en kritik sorular ve sitenizin bunlara verdiği cevapların kalitesi ölçülür.">Etkililik</span>', $index);
$index = str_replace('<span>Rakip Analizi</span>', '<span class="has-tooltip" data-tooltip="Sektördeki en güçlü rakiplere kıyasla sitenizin içerik açısından hangi noktalarda eksik kaldığı tespit edilir.">Rakip Analizi</span>', $index);
$index = str_replace('<span>AI Güveni</span>', '<span class="has-tooltip" data-tooltip="E-E-A-T (Deneyim, Uzmanlık, Otoriterlik, Güvenilirlik) kurallarına göre sitenizin yapay zekaya ne kadar güven verdiği puanlanır.">AI Güveni</span>', $index);
$index = str_replace('<span>Optimizasyon</span>', '<span class="has-tooltip" data-tooltip="SGE (Search Generative Experience) ile uyumlu, okunabilirliği yüksek yepyeni bir içerik taslağı sunulur.">Optimizasyon</span>', $index);
$index = str_replace('<span>Entegrasyon</span>', '<span class="has-tooltip" data-tooltip="Yapay zekanın sitenizi sadece kelime kelime değil, anlamsal bir bütün (Semantik ve Şema) olarak nasıl algıladığı özetlenir.">Entegrasyon</span>', $index);

// 2. Empty state in copilot-chat-messages-container
// Find this exact line:
// <div class="copilot-chat-messages" id="copilot-chat-messages-container" style="display: flex; flex-direction: column; gap: 16px; flex: 1;">
$old_chat = '<div class="copilot-chat-messages" id="copilot-chat-messages-container" style="display: flex; flex-direction: column; gap: 16px; flex: 1;">';
$new_chat = $old_chat . '
                <div class="empty-state" id="copilot-empty-state">
                  <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                  <p>Burada henüz bir analiz yok. Hemen yukarıya bir URL yapıştırarak sitenizin Google SGE (Yapay Zeka Aramaları) uyumluluğunu test etmeye başlayın!</p>
                </div>';
$index = str_replace($old_chat, $new_chat, $index);

// 3. Quick Actions
// Find this exact line:
// <div class="copilot-input-area" id="copilot-input-area" style="display:none;">
$old_input = '<div class="copilot-input-area" id="copilot-input-area" style="display:none;">';
$new_input = '            <div class="quick-actions" id="copilot-quick-actions" style="display:none;">
              <button class="quick-action-btn" onclick="document.getElementById(\'copilot-text-input\').value=this.textContent; document.getElementById(\'copilot-send-btn\').click();">Bana içerik eksiklerimi söyle</button>
              <button class="quick-action-btn" onclick="document.getElementById(\'copilot-text-input\').value=this.textContent; document.getElementById(\'copilot-send-btn\').click();">Rakiplerimden neden gerideyim?</button>
              <button class="quick-action-btn" onclick="document.getElementById(\'copilot-text-input\').value=this.textContent; document.getElementById(\'copilot-send-btn\').click();">Bu sayfa için SSS (FAQ) hazırla</button>
            </div>
            ' . $old_input;
$index = str_replace($old_input, $new_input, $index);

file_put_contents("frontend/index.php", $index);
echo "Successfully updated index.php with precise str_replace\n";
?>
