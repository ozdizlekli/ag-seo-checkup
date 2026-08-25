<section class="tab-panel" id="tab-3">
  <!-- DASHBOARD VIEW -->
  <div id="ai-seo-dashboard-view"></div>
  
  <!-- ACTION VIEW (CHAT + CHARTS) -->
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
        <button class="btn btn--danger btn--sm has-tooltip" data-tooltip="Sitenizi en dişli rakiplerinizle kıyaslayın." id="btn-open-battle-mode" style="display:inline-flex; align-items:center; background:#dc2626; color:white; border:none; box-shadow:0 2px 8px rgba(220,38,38,0.4); border-radius:12px; padding: 6px 12px; font-size:12px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
            Savaş Modu
        </button>
        <button class="btn btn--secondary btn--sm has-tooltip" data-tooltip="Tüm analiz adımları tamamlandıktan sonra indirilebilir." id="btn-download-pdf" style="display:inline-flex; align-items:center; background:#ef4444; color:white; border:none; opacity:0.5; cursor:not-allowed; padding: 6px 12px; font-size:12px; border-radius:12px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Rapor
        </button>
        <button class="btn btn--primary btn--sm has-tooltip" data-tooltip="Eksikleri listeye aktar." id="btn-send-to-todos" style="display:inline-flex; align-items:center; opacity:0.5; cursor:not-allowed; padding: 6px 12px; background: #2563eb; color: white; border: none; font-size:12px; border-radius:12px;">
            📋 Gönder
        </button>
        <button class="btn btn--primary btn--sm has-tooltip" data-tooltip="Sohbeti geçmişe kaydeder." id="copilot-manual-save-btn" style="display:inline-flex; align-items:center; padding: 6px 12px; font-size:12px; border-radius:12px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            Kaydet
        </button>
        
        <div style="display: flex; align-items: center; gap: 6px; margin-left: 8px; border-left: 1px solid var(--border); padding-left: 8px;">
            <label class="switch has-tooltip" data-tooltip="Sunum modu">
                <input type="checkbox" id="client-view-toggle">
                <span class="slider round"></span>
            </label>
            <span style="font-size:12px; font-weight:600; color:var(--muted);">Sunum</span>
            <button class="btn btn--ghost btn--sm has-tooltip" data-tooltip="Yeni bir sohbet / yeni URL analizi başlatır." id="btn-clear-chat" style="display:inline-flex; align-items:center; gap:4px; padding:4px 10px; margin-left:4px; font-size:12px; font-weight:600; color:var(--muted);">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
              Yeni Sohbet
            </button>
        </div>
      </div>
    </div>
    
    <!-- MAIN TWO-COLUMN AREA -->
    <div style="display: flex; flex-direction: row; gap: 24px; align-items: stretch; width: 100%;">
      
      <!-- CHAT INTERFACE (Left Column - flex: 3) -->
      <div class="card" id="copilot-card" style="flex: 3; border-top: 4px solid var(--accent); padding:0; display:flex; flex-direction:column; height: calc(100vh - 120px); overflow: hidden;">
        <div style="padding: 24px 24px 0 24px;">
          <div class="card__title" style="display:inline-flex; align-items:center;">GEO AI Bot (URL Tabanlı)</div>
          <div class="card__hint">Web sitenizin SEO ve Yapay Zeka (SGE) görünürlüğünü sohbet asistanı ile adım adım analiz edin.</div>
        </div>
        
        <div class="copilot-container" style="border:none; margin-top:0; border-radius:0; padding-right: 0; display:flex; flex-direction:column; flex:1; min-height: 0;">
          <div class="copilot-header" style="position:relative; z-index:50; display: flex; flex-direction: column; gap: 16px;">
            
            <!-- Progress Bar -->
            <div style="display:flex; justify-content:center; width:100%; border-top: 1px solid var(--border); padding-top:16px; margin-top:8px;">
              <div class="copilot-progress" id="copilot-progress" style="display:flex; justify-content:center; align-items:center; gap:12px; width:100%; overflow-x:auto; overflow-y:hidden; padding:100px 4px 4px; margin-top:-100px;">
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
            </div>
            
          </div>
          
          <div class="copilot-chat" id="copilot-chat-messages-container" style="padding:24px; min-height: 350px; flex: 1; overflow-y: auto;">
            <!-- Chat messages go here. EMPTY STATE KULLANILMIYOR. Doğrudan 'Merhaba' eklenecek JS'den. -->
          </div>
          
          <div class="copilot-input-area" id="copilot-input-area-container" style="padding:24px; background:#f8fafc; border-top:1px solid var(--border); border-radius:0 0 12px 12px; position:relative;">
            
            <!-- Dinamik adım aksiyonları (copilot.js -> renderAiSeoActions/handleSend tarafından dolduruluyor) -->
            <div id="copilot-actions" style="display:flex; flex-direction:column; gap:8px; margin-bottom:16px;"></div>

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
            
            <div class="input-wrapper" style="display:flex; align-items:center; gap:8px;">
              <div style="flex:1; position:relative;">
                <input type="text" id="copilot-text-input" placeholder="Örn: https://www.site.com/hizmet" class="form-input" style="width:100%; border-radius:24px; padding-left:16px; padding-right:48px;">
                <input type="text" id="copilot-secondary-input" placeholder="Başka sormak istediğiniz bir şey var mı?" class="form-input" style="width:100%; border-radius:24px; padding-left:16px; padding-right:48px; display:none;">
              </div>
              <button class="btn btn--primary" id="btn-send-message" style="border-radius:24px; padding:0 24px; height:42px;">Gönder</button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- LIVE REPORT PANEL (Right Column - flex: 2) -->
      <div class="live-report-panel" id="live-report-panel" style="flex: 2; padding: 24px; background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); position: sticky; top: 24px; display: flex; flex-direction: column; gap: 24px; height: calc(100vh - 120px); overflow-y: auto;">
         <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
             <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#2563eb;"><path d="M3 3v18h18"/><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"/></svg>
             Canlı Rapor Paneli
         </h3>
         
         <!-- Genel Sağlık Grafiği -->
         <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
            <h4 style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 12px; text-align: center;">Genel AI Güven Skoru</h4>
            <div style="width: 200px; height: 200px; margin: auto; position: relative; display: flex; justify-content: center; align-items: center;">
                <canvas id="chart-overall-health"></canvas>
            </div>
         </div>
         
         <!-- E-E-A-T Radar Grafiği -->
         <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
            <h4 style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 12px; text-align: center;">E-E-A-T Sinyalleri</h4>
            <div style="height: 220px; position: relative;">
                <canvas id="chart-eeat"></canvas>
            </div>
         </div>

         <!-- Rakip Kıyaslama Grafiği (Gizli) -->
         <div id="battle-chart-container" style="display: none; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
            <h4 style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 12px; text-align: center;">Savaş Modu Kıyaslaması</h4>
            <div style="height: 200px; position: relative;">
                <canvas id="chart-battle"></canvas>
            </div>
         </div>
         
         <!-- Aksiyon Planı Tablosu -->
         <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
            <h4 style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 12px;">Acil Aksiyon Planı</h4>
            <div id="action-plan-table-container" style="font-size: 13px;">
                <p style="color: #94a3b8; font-size: 12px; text-align: center; margin: 20px 0;">Henüz veri yok.</p>
            </div>
         </div>
      </div>
    </div> <!-- /MAIN TWO-COLUMN AREA -->
    
    <!-- HISTORY LIST (Full width at bottom) -->
    <div class="card mt-24" id="copilot-history-card" style="width: 100%;">
      <div class="card__head" style="cursor: pointer; user-select: none;" onclick="const hl = document.getElementById('copilot-history-list'); hl.style.display = (hl.style.display === 'none' ? 'flex' : 'none'); const icon = document.getElementById('history-toggle-icon'); icon.style.transform = (hl.style.display === 'none' ? 'rotate(0deg)' : 'rotate(180deg)');">
        <div class="card__title" style="display:inline-flex; align-items:center;">
            Geçmiş Sohbetler
            <svg id="history-toggle-icon" style="margin-left:8px; transition: transform 0.2s;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </div>
        <button class="btn btn--ghost btn--sm" id="btn-clear-history" onclick="event.stopPropagation();">Temizle</button>
      </div>
      <div class="card__hint">Önceki URL analizleriniz burada listelenir. Tıklayarak sohbeti geri yükleyebilirsiniz.</div>
      <div id="copilot-history-list" class="mt-16" style="display:none; flex-direction:column; gap:8px;">
        <p class="empty-note">Henüz geçmiş sohbet yok.</p>
      </div>
    </div>
    
  </div> <!-- /copilot-action-view -->
</section>