<?php
$index = file_get_contents("frontend/index.php");

// 1. Tooltips on aiSeoSteps in index.php
$old_steps = <<<'EOF'
                <div class="copilot-step" data-step="1" id="cp-step-1">
                  <div class="copilot-step-circle"><span class="num">1</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span>İş Bağlamı</span>
                  <button class="btn-fix-issue" data-step="1" id="btn-fix-1" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="1. Adımı Çöz">🔧</button>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                <div class="copilot-step" data-step="2" id="cp-step-2">
                  <div class="copilot-step-circle"><span class="num">2</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span>Etkililik & Sorular</span>
                  <button class="btn-fix-issue" data-step="2" id="btn-fix-2" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="2. Adımı Çöz">🔧</button>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                <div class="copilot-step" data-step="3" id="cp-step-3">
                  <div class="copilot-step-circle"><span class="num">3</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span>Rakip Öngörüleri</span>
                  <button class="btn-fix-issue" data-step="3" id="btn-fix-3" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="3. Adımı Çöz">🔧</button>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                <div class="copilot-step" data-step="4" id="cp-step-4">
                  <div class="copilot-step-circle"><span class="num">4</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span>AI Güven Puanı</span>
                  <button class="btn-fix-issue" data-step="4" id="btn-fix-4" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="4. Adımı Çöz">🔧</button>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                <div class="copilot-step" data-step="5" id="cp-step-5">
                  <div class="copilot-step-circle"><span class="num">5</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span>Okunabilirlik & SGE Uyumu</span>
                  <button class="btn-fix-issue" data-step="5" id="btn-fix-5" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="5. Adımı Çöz">🔧</button>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                <div class="copilot-step" data-step="6" id="cp-step-6">
                  <div class="copilot-step-circle"><span class="num">6</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span>Entegrasyon (Schema vb.)</span>
                  <button class="btn-fix-issue" data-step="6" id="btn-fix-6" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="6. Adımı Çöz">🔧</button>
                </div>
EOF;

$new_steps = <<<'EOF'
                <div class="copilot-step" data-step="1" id="cp-step-1">
                  <div class="copilot-step-circle"><span class="num">1</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span class="has-tooltip" data-tooltip="Sitenizin sektörü, hitap ettiği kitle ve anahtar kelime varlıkları yapay zeka gözüyle analiz edilir.">İş Bağlamı</span>
                  <button class="btn-fix-issue" data-step="1" id="btn-fix-1" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="1. Adımı Çöz">🔧</button>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                <div class="copilot-step" data-step="2" id="cp-step-2">
                  <div class="copilot-step-circle"><span class="num">2</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span class="has-tooltip" data-tooltip="Kullanıcıların bu sayfa bağlamında sorabileceği en kritik sorular ve sitenizin bunlara verdiği cevapların kalitesi ölçülür.">Etkililik & Sorular</span>
                  <button class="btn-fix-issue" data-step="2" id="btn-fix-2" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="2. Adımı Çöz">🔧</button>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                <div class="copilot-step" data-step="3" id="cp-step-3">
                  <div class="copilot-step-circle"><span class="num">3</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span class="has-tooltip" data-tooltip="Sektördeki en güçlü rakiplere kıyasla sitenizin içerik açısından hangi noktalarda eksik kaldığı tespit edilir.">Rakip Öngörüleri</span>
                  <button class="btn-fix-issue" data-step="3" id="btn-fix-3" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="3. Adımı Çöz">🔧</button>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                <div class="copilot-step" data-step="4" id="cp-step-4">
                  <div class="copilot-step-circle"><span class="num">4</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span class="has-tooltip" data-tooltip="E-E-A-T (Deneyim, Uzmanlık, Otoriterlik, Güvenilirlik) kurallarına göre sitenizin yapay zekaya ne kadar güven verdiği puanlanır.">AI Güven Puanı</span>
                  <button class="btn-fix-issue" data-step="4" id="btn-fix-4" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="4. Adımı Çöz">🔧</button>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                <div class="copilot-step" data-step="5" id="cp-step-5">
                  <div class="copilot-step-circle"><span class="num">5</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span class="has-tooltip" data-tooltip="SGE (Search Generative Experience) ile uyumlu, okunabilirliği yüksek yepyeni bir içerik taslağı sunulur.">Okunabilirlik & SGE Uyumu</span>
                  <button class="btn-fix-issue" data-step="5" id="btn-fix-5" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="5. Adımı Çöz">🔧</button>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                <div class="copilot-step" data-step="6" id="cp-step-6">
                  <div class="copilot-step-circle"><span class="num">6</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span class="has-tooltip" data-tooltip="Yapay zekanın sitenizi sadece kelime kelime değil, anlamsal bir bütün (Semantik ve Şema) olarak nasıl algıladığı özetlenir.">Entegrasyon (Schema vb.)</span>
                  <button class="btn-fix-issue" data-step="6" id="btn-fix-6" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="6. Adımı Çöz">🔧</button>
                </div>
EOF;

$index = str_replace($old_steps, $new_steps, $index);

// 4. Empty State for Copilot Chat (id="copilot-chat-messages-container")
$old_chat_area = <<<'EOF'
            <div class="copilot-chat-area" id="copilot-chat-area" style="display:none;">
              <div class="copilot-chat-messages" id="copilot-chat-messages-container" style="display: flex; flex-direction: column; gap: 16px; flex: 1;">
              </div>
            </div>
EOF;

$new_chat_area = <<<'EOF'
            <div class="copilot-chat-area" id="copilot-chat-area" style="display:none;">
              <div class="copilot-chat-messages" id="copilot-chat-messages-container" style="display: flex; flex-direction: column; gap: 16px; flex: 1;">
                <div class="empty-state" id="copilot-empty-state">
                  <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                  <p>Burada henüz bir analiz yok. Hemen yukarıya bir URL yapıştırarak sitenizin Google SGE (Yapay Zeka Aramaları) uyumluluğunu test etmeye başlayın!</p>
                </div>
              </div>
            </div>
EOF;

$index = str_replace($old_chat_area, $new_chat_area, $index);

// 5. Quick Actions for Copilot
$old_input_area = <<<'EOF'
            <div class="copilot-input-area" id="copilot-input-area" style="display:none;">
              <input type="text" id="copilot-text-input" placeholder="İçerik türünü seçin veya URL yapıştırın..." />
              <button id="copilot-send-btn" class="btn btn--primary" style="padding: 0 16px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              </button>
            </div>
EOF;

$new_input_area = <<<'EOF'
            <div class="quick-actions" id="copilot-quick-actions" style="display:none;">
              <button class="quick-action-btn" onclick="document.getElementById('copilot-text-input').value=this.textContent; document.getElementById('copilot-send-btn').click();">Bana içerik eksiklerimi söyle</button>
              <button class="quick-action-btn" onclick="document.getElementById('copilot-text-input').value=this.textContent; document.getElementById('copilot-send-btn').click();">Rakiplerimden neden gerideyim?</button>
              <button class="quick-action-btn" onclick="document.getElementById('copilot-text-input').value=this.textContent; document.getElementById('copilot-send-btn').click();">Bu sayfa için SSS (FAQ) hazırla</button>
            </div>
            <div class="copilot-input-area" id="copilot-input-area" style="display:none;">
              <input type="text" id="copilot-text-input" placeholder="İçerik türünü seçin veya URL yapıştırın..." />
              <button id="copilot-send-btn" class="btn btn--primary" style="padding: 0 16px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              </button>
            </div>
EOF;

$index = str_replace($old_input_area, $new_input_area, $index);

file_put_contents("frontend/index.php", $index);
echo "Updated index.php for Tooltips, Empty States, and Quick Actions\n";
?>
