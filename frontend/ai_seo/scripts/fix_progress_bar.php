<?php
$index = file_get_contents("frontend/index.php");

$bad_steps = <<<HTML
              <div class="copilot-steps" style="display:flex; flex-direction:row; justify-content:center; align-items:center; gap:16px; width:100%; overflow-x:auto;">
                <div class="step active has-tooltip" data-step="1" data-tooltip="İş Bağlamı (Bağlamsal Alaka) analizi." style="cursor:pointer;"><div class="step-num">1</div><span class="step-label">İş Bağlamı</span></div>
                <div class="step has-tooltip" data-step="2" data-tooltip="Kullanıcı niyeti ve etkililik analizi." style="cursor:pointer;"><div class="step-num">2</div><span class="step-label">Etkililik</span></div>
                <div class="step has-tooltip" data-step="3" data-tooltip="Rakiplere kıyasla eksik içerik/değer boşlukları analizi." style="cursor:pointer;"><div class="step-num">3</div><span class="step-label">Rakip Analizi</span></div>
                <div class="step has-tooltip" data-step="4" data-tooltip="Yapay zeka sistemleri nezdinde marka güveni ve otorite (E-E-A-T) analizi." style="cursor:pointer;"><div class="step-num">4</div><span class="step-label">AI Güveni</span></div>
                <div class="step has-tooltip" data-step="5" data-tooltip="Okunabilirlik, Şema yapıları ve kullanıcı deneyimi (UX/UI) sorunları." style="cursor:pointer;"><div class="step-num">5</div><span class="step-label">Optimizasyon</span></div>
                <div class="step has-tooltip" data-step="6" data-tooltip="Sentez ve çözümleme aşaması." style="cursor:pointer;"><div class="step-num">6</div><span class="step-label">Entegrasyon</span></div>
              </div>
HTML;

$good_steps = <<<HTML
              <div class="copilot-progress" id="copilot-progress" style="display:flex; justify-content:center; align-items:center; gap:12px; width:100%; overflow-x:auto; padding-bottom:4px;">
                <div class="copilot-step active has-tooltip" data-step="1" id="cp-step-1" data-tooltip="İş Bağlamı (Bağlamsal Alaka) analizi.">
                  <div class="copilot-step-circle"><span class="num">1</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span>İş Bağlamı</span>
                </div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                <div class="copilot-step has-tooltip" data-step="2" id="cp-step-2" data-tooltip="Kullanıcı niyeti ve etkililik analizi.">
                  <div class="copilot-step-circle"><span class="num">2</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span>Etkililik</span>
                </div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                <div class="copilot-step has-tooltip" data-step="3" id="cp-step-3" data-tooltip="Rakiplere kıyasla eksik içerik/değer boşlukları analizi.">
                  <div class="copilot-step-circle"><span class="num">3</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span>Rakip Analizi</span>
                </div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                <div class="copilot-step has-tooltip" data-step="4" id="cp-step-4" data-tooltip="Yapay zeka sistemleri nezdinde marka güveni ve otorite (E-E-A-T) analizi.">
                  <div class="copilot-step-circle"><span class="num">4</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span>AI Güveni</span>
                </div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                <div class="copilot-step has-tooltip" data-step="5" id="cp-step-5" data-tooltip="Okunabilirlik, Şema yapıları ve kullanıcı deneyimi (UX/UI) sorunları.">
                  <div class="copilot-step-circle"><span class="num">5</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span>Optimizasyon</span>
                </div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                <div class="copilot-step has-tooltip" data-step="6" id="cp-step-6" data-tooltip="Sentez ve çözümleme aşaması.">
                  <div class="copilot-step-circle"><span class="num">6</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                  <span>Entegrasyon</span>
                </div>
              </div>
HTML;

$index = str_replace($bad_steps, $good_steps, $index);

file_put_contents("frontend/index.php", $index);
echo "Restored original progress bar\n";
?>
