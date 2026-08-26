<section class="tab-panel" id="tab-3">
  <!-- DASHBOARD VIEW -->
  <div id="ai-seo-dashboard-view"></div>
  
  <!-- ACTION VIEW (CHAT + REPORT stacked vertically) -->
  <div id="copilot-action-view" style="display: none; flex-direction: column; gap: 16px; min-height: 80vh;">
    
    <!-- TOP NAV: Return Button & Actions -->
    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 8px;">
      <!-- Sol Üst -->
      <button id="btn-return-dashboard" class="btn btn--ghost btn--sm" style="display:inline-flex; align-items:center; color: var(--muted); font-weight:600; padding:8px 16px; border-radius:8px; font-size:13px; background: #fff; border: 1px solid var(--border); box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
         <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
         Kontrol Paneline Dön
      </button>
      
      <!-- Sağ Üst (Kompakt İkonlu Butonlar) -->
      <div style="display: flex; gap: 6px; align-items: center;">
        <button class="btn btn--sm" id="btn-open-battle-mode" title="Sitenizi en dişli rakiplerinizle kıyaslayın." style="display:inline-flex; align-items:center; background:#475569; color:white; border:none; border-radius:12px; padding: 6px 12px; font-size:12px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
            Savaş Modu
        </button>
        <button class="btn btn--sm" id="btn-download-pdf" title="Tüm analiz adımları tamamlandıktan sonra indirilebilir." style="display:inline-flex; align-items:center; background:#64748b; color:white; border:none; opacity:0.5; cursor:not-allowed; padding: 6px 12px; font-size:12px; border-radius:12px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Rapor
        </button>
        <button class="btn btn--sm" id="btn-send-to-todos" title="Eksikleri yapılacaklar listesine aktar." style="display:inline-flex; align-items:center; opacity:0.5; cursor:not-allowed; padding: 6px 12px; background:#64748b; color:white; border:none; font-size:12px; border-radius:12px;">
            📋 Gönder
        </button>
        <button class="btn btn--sm" id="copilot-manual-save-btn" title="Sohbeti geçmişe kaydeder." style="display:inline-flex; align-items:center; padding: 6px 12px; font-size:12px; border-radius:12px; background:#64748b; color:white; border:none;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            Kaydet
        </button>
        
        <div style="display: flex; align-items: center; gap: 6px; margin-left: 8px; border-left: 1px solid var(--border); padding-left: 8px;">
            <label class="switch" title="Sunum modu">
                <input type="checkbox" id="client-view-toggle">
                <span class="slider round"></span>
            </label>
            <span style="font-size:12px; font-weight:600; color:var(--muted);">Sunum</span>
            <button class="btn btn--ghost btn--sm" title="Yeni bir sohbet / yeni URL analizi başlatır." id="btn-clear-chat" style="display:inline-flex; align-items:center; gap:4px; padding:4px 10px; margin-left:4px; font-size:12px; font-weight:600; color:var(--muted);">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
              Yeni Sohbet
            </button>
        </div>
      </div>
    </div>
    
    <!-- CHAT INTERFACE (Full width, top) -->
    <div class="card" id="copilot-card" style="width:100%; border-top: 4px solid var(--accent); padding:0; display:flex; flex-direction:column; height: calc(100vh - 200px); overflow: hidden; border-radius: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);; border-radius: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
      <div style="padding: 24px 24px 0 24px;">
        <div class="card__title" style="display:inline-flex; align-items:center;">GEO AI Bot (URL Tabanlı)</div>
        <div class="card__hint">Web sitenizin SEO ve Yapay Zeka (SGE) görünürlüğünü sohbet asistanı ile adım adım analiz edin.</div>
      </div>
      
      <div class="copilot-container" style="border:none; margin-top:0; border-radius:0; padding-right: 0; display:flex; flex-direction:column; flex:1; min-height: 0;">
        <div class="copilot-header" style="position:relative; z-index:2; display: flex; flex-direction: column; gap: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; background: #fff; flex-shrink: 0;; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; background: #fff; flex-shrink: 0;">
          
          <!-- Progress Bar (NO overflow-x) -->
          <div style="display:flex; justify-content:center; width:100%; border-top: 1px solid var(--border); padding-top:16px; margin-top:8px;">
            <div class="copilot-progress" id="copilot-progress" style="display:flex; justify-content:center; align-items:center; gap:8px; width:100%; flex-wrap:wrap; padding:4px;">
              <div class="copilot-step active has-tooltip" data-step="1" id="cp-step-1" data-tooltip="İş Bağlamı (Bağlamsal Alaka) analizi.">
                <div class="copilot-step-circle"><span class="num">1</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                <button class="btn-fix-issue" data-step="1" id="btn-fix-1" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="1. Adımı Çöz">🔧</button>
                <span>İş Bağlamı</span>
              </div>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
              <div class="copilot-step has-tooltip" data-step="2" id="cp-step-2" data-tooltip="Kullanıcı niyeti ve etkililik analizi.">
                <div class="copilot-step-circle"><span class="num">2</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                <button class="btn-fix-issue" data-step="2" id="btn-fix-2" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="2. Adımı Çöz">🔧</button>
                <span>Etkililik</span>
              </div>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
              <div class="copilot-step has-tooltip" data-step="3" id="cp-step-3" data-tooltip="Rakiplere kıyasla eksik içerik/değer boşlukları analizi.">
                <div class="copilot-step-circle"><span class="num">3</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                <button class="btn-fix-issue" data-step="3" id="btn-fix-3" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="3. Adımı Çöz">🔧</button>
                <span>Rakip Analizi</span>
              </div>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
              <div class="copilot-step has-tooltip" data-step="4" id="cp-step-4" data-tooltip="Yapay zeka sistemleri nezdinde marka güveni ve otorite (E-E-A-T) analizi.">
                <div class="copilot-step-circle"><span class="num">4</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                <button class="btn-fix-issue" data-step="4" id="btn-fix-4" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="4. Adımı Çöz">🔧</button>
                <span>AI Güveni</span>
              </div>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
              <div class="copilot-step has-tooltip" data-step="5" id="cp-step-5" data-tooltip="Okunabilirlik, Şema yapıları ve kullanıcı deneyimi (UX/UI) sorunları.">
                <div class="copilot-step-circle"><span class="num">5</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                <button class="btn-fix-issue" data-step="5" id="btn-fix-5" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="5. Adımı Çöz">🔧</button>
                <span>Optimizasyon</span>
              </div>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
              <div class="copilot-step has-tooltip" data-step="6" id="cp-step-6" data-tooltip="Sentez ve çözümleme aşaması.">
                <div class="copilot-step-circle"><span class="num">6</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                <span>Entegrasyon</span>
              </div>
            </div>
          </div>
          
        </div>
        
        <div class="copilot-chat" id="copilot-chat-messages-container" style="padding:24px; min-height: 200px; flex: 1; overflow-y: auto;">
          <!-- Chat messages go here. Doğrudan 'Merhaba' eklenecek JS'den. -->
        </div>

        <div class="copilot-input-area" id="copilot-input-area-container" style="padding: 16px 24px; background: transparent; border-top: none; position: relative; flex-shrink: 0;">
            
            <!-- GREEN BUTTONS (Tüm Siteyi Analiz Et / Eksikleri Gider) -->
            <div id="copilot-llms-container" style="display:none; flex-direction:row; gap:16px; margin-bottom: 24px; justify-content: center; max-width: 800px; margin-left: auto; margin-right: auto;">
               <button class="btn btn--primary" id="btn-auto-analyze" style="flex:1; padding: 12px; font-weight:600; background:#10b981; border:none; border-radius: 12px; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);">
                   <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; color:#fff;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                   Tüm Siteyi Analiz Et
               </button>
               <button class="btn btn--primary" id="btn-auto-fix" style="flex:1; padding: 12px; font-weight:600; background:#10b981; border:none; border-radius: 12px; display:none; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);">
                   <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; color:#fff;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                   Tüm Eksikleri Gider
               </button>
            </div>
            
            <!-- HIZLI AKSİYON BUTONLARI -->
            <div class="quick-actions" id="copilot-quick-actions" style="margin-bottom: 16px; padding-top: 4px; max-width: 800px; width: 100%; margin-left: auto; margin-right: auto; display: none; justify-content: center; gap: 8px; background: transparent; border: none; overflow-x: auto; white-space: nowrap;">
              <button class="quick-action-btn has-tooltip" data-tooltip="İçeriği daha inandırıcı hale getirmek için ipuçları iste." onclick="document.getElementById('copilot-text-input').value='İçeriği daha ikna edici nasıl yaparım?'; document.getElementById('btn-send-message').click();">İçeriği daha ikna edici nasıl yaparım?</button>
              <button class="quick-action-btn has-tooltip" data-tooltip="Eksik olan E-E-A-T veya semantik unsurları listele." onclick="document.getElementById('copilot-text-input').value='Bana içerik eksiklerimi söyle'; document.getElementById('btn-send-message').click();">Bana içerik eksiklerimi söyle</button>
              <button class="quick-action-btn has-tooltip" data-tooltip="Makalenin anlamsal derinliğini artıracak anahtar kelimeler öner." onclick="document.getElementById('copilot-text-input').value='Hangi LSI kelimelerini kullanmalıyım?'; document.getElementById('btn-send-message').click();">Hangi LSI kelimelerini kullanmalıyım?</button>
              <button class="quick-action-btn has-tooltip" data-tooltip="Rakiplerin senden daha iyi olduğu spesifik noktaları öğren." onclick="document.getElementById('copilot-text-input').value='Rakiplerimden neden gerideyim?'; document.getElementById('btn-send-message').click();">Rakiplerimden neden gerideyim?</button>
            </div>
            
            <!-- YENİ GEMINI STİLİ GİRİŞ ALANI -->
            <div class="input-wrapper" style="display: flex; flex-direction: column; align-items: center; width: 100%;">
                <div style="display: flex; align-items: center; width: 100%; max-width: 800px; background: #f0f4f9; border-radius: 30px; padding: 6px 10px 6px 24px; margin-bottom: 8px;">
                  <input type="text" id="copilot-text-input" placeholder="Örn: https://www.site.com/hizmet" style="flex: 1; border: none; outline: none; background: transparent; font-size: 15px; color: #1e293b; padding: 10px 0; box-shadow: none;">
                  <input type="text" id="copilot-secondary-input" style="display:none;">
                  <button id="btn-send-message" style="background: #a8c7fa; color: #041e49; border: none; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; transition: all 0.2s; margin-left: 12px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>
                  </button>
                </div>
                <div style="font-size: 12px; color: #64748b; text-align: center; font-weight: 400;">
                    AI SEO Bot bir yapay zeka modeli olduğu için hata yapabilir.
                </div>
            </div>
          </div>
</div>
    
  </div> <!-- /copilot-action-view -->
</section>