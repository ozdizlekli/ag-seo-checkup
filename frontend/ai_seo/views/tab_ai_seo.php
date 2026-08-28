<?php // AI SEO Hizmetler Paneli ?>
<section class="tab-panel" id="tab-3">

<!-- ACTION VIEW -->
<div id="copilot-action-view" style="display:flex; flex-direction:column; gap:20px; min-height:80vh;">

  <!-- TOP NAV -->
  <div style="display:flex; justify-content:space-between; align-items:center; width:100%; margin-bottom:4px;">
    <div>
      <span id="aiseo-url-badge" style="display:none; background:#F1F0F5; color:#312F4D; border:1px solid #DCDAE6; border-radius:6px; padding:4px 14px; font-size:12px; font-weight:600;"></span>
      <span id="aiseo-type-badge" style="display:none; background:#FFF6DF; color:#8A6400; border:1px solid #F5DFA0; border-radius:6px; padding:4px 14px; font-size:12px; font-weight:600; margin-left:6px;"></span>
    </div>
    <div style="display:flex; gap:6px; align-items:center;">
      <button id="btn-download-pdf"
        title="Tüm analiz adımları tamamlandıktan sonra indirilebilir."
        style="display:none; align-items:center; background:#64748b; color:white; border:none; opacity:0.5; cursor:not-allowed; padding:6px 12px; font-size:12px; border-radius:6px;">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        Rapor
      </button>
            <button id="btn-customize-services"
        title="Hizmet alt başlıklarını özelleştirin"
        style="display:none; align-items:center; gap:4px; padding:6px 12px; font-size:12px; font-weight:600; color:#312F4D; border:1px solid #DCDAE6; background:#fff; cursor:pointer; border-radius:6px; margin-right:4px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        Hizmetleri Özelleştir
      </button>
      <button id="btn-new-analysis" 
        title="Yeni URL analizi başlatır."
        style="display:none; align-items:center; gap:4px; padding:6px 12px; font-size:12px; font-weight:600; color:var(--muted); border:none; background:none; cursor:pointer; border-radius:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        Yeni Analiz
      </button>
      <button id="btn-toggle-history-sidebar"
        title="Geçmiş Analizleri Göster"
        style="display:inline-flex; align-items:center; gap:4px; padding:6px 12px; font-size:12px; font-weight:600; color:var(--muted); border:none; background:none; cursor:pointer; border-radius:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        Geçmiş
      </button>
    </div>
  </div>


  <!-- ===================== URL GİRİŞ KARTI ===================== -->
  <div id="aiseo-url-card" class="card" style="border-top:3px solid #312F4D; border-radius:10px; padding:32px; box-shadow:0 1px 4px rgba(31,29,48,0.06);">
    <div style="max-width:680px; margin:0 auto; text-align:center;">
      <div style="font-size:28px; margin-bottom:8px;">🤖</div>
      <h2 style="font-size:20px; font-weight:700; color:#1F1D30; margin:0 0 8px 0;">Yapay Zeka SEO Hizmetleri</h2>
      <p style="color:#6B6E82; font-size:14px; margin:0 0 28px 0;">Müşterinizin web sitesini analiz etmek için URL'yi girin. Yapay zeka site türünü otomatik tespit eder.</p>

      <div style="display:flex; align-items:center; background:#F7F7F9; border:1px solid #E4E3EC; border-radius:8px; padding:6px 8px 6px 22px;">
  <input type="text" id="aiseo-url-input"
    placeholder="Örn: https://www.musteri-sitesi.com"
    style="flex:1; border:none; outline:none; background:transparent; font-size:15px; color:#1F1D30; padding:10px 0; box-shadow:none; min-width:0;">
  <button id="aiseo-url-submit"
    style="display:inline-flex; align-items:center; justify-content:center; gap:6px; background:#312F4D; color:#fff; border:none; border-radius:6px; padding:10px 24px; font-size:14px; font-weight:600; cursor:pointer; white-space:nowrap; transition:background 0.2s; flex-shrink:0;">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
      <line x1="12" y1="19" x2="12" y2="5"></line>
      <polyline points="5 12 12 5 19 12"></polyline>
    </svg>
    Analiz Et
  </button>
</div>
      <p style="font-size:11px; color:#9A9DAE; margin-top:10px;">Yapay zeka site türünü otomatik algılar • Gemini API ile güçlendirilmiştir</p>

      <!-- Tarama göstergesi -->
      <div id="aiseo-scan-indicator" style="display:none; margin-top:24px; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
        <div style="display:flex; align-items:center; justify-content:center; gap:10px; color:#475569; font-size:14px;">
          <div class="typing-indicator" style="padding:0;"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>
          <span id="aiseo-scan-text">Site taranıyor ve site türü tespit ediliyor...</span>
        </div>
      </div>
    </div>
  </div>


  <!-- ===================== HİZMET PANELİ (URL girildikten sonra) ===================== -->
  <div id="aiseo-services-panel" style="display:none;">

    <!-- Hizmet Butonları -->
    <div class="card" style="border-radius:10px; padding:20px 24px; box-shadow:0 1px 4px rgba(31,29,48,0.05);">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
        <div>
          <h3 style="font-size:15px; font-weight:700; color:#1F1D30; margin:0 0 2px 0;">AI SEO Hizmetleri</h3>
          <p style="font-size:12px; color:#6B6E82; margin:0;">Hizmete tıklayarak AI analizi başlatın</p>
        </div>
        <button id="btn-run-all-services"
  style="display:inline-flex; align-items:center; gap:6px; background:#4b5563; color:#fff; border:none; border-radius:6px; padding:10px 20px; font-size:13px; font-weight:600; cursor:pointer; transition:background 0.2s;">
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
  </svg>
  Tüm Hizmetleri Yap
</button>
      </div>

      <div id="aiseo-service-buttons" style="display:none; flex-wrap:wrap; gap:8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #E2E8F0;">
        <!-- Dinamik hizmet butonları (Dropdown'lı) JS tarafından buraya eklenecek -->
      </div>
    </div><!-- /hizmet butonları -->


    <!-- ===================== SONUÇ ACCORDION ALANI ===================== -->
    <!-- ===================== SONUÇ ACCORDION ALANI ===================== -->
    <div id="aiseo-results-area" style="display:flex; flex-direction:column; gap:12px; margin-top:12px;">
      <!-- Accordion'lar JS tarafından buraya eklenir -->
    </div>

  </div><!-- /aiseo-services-panel -->

</div><!-- /copilot-action-view -->


<!-- HISTORY SIDEBAR -->
<div id="ai-history-sidebar" style="position:fixed; top:0; right:-380px; width:380px; height:100vh; background:#fff; box-shadow:-4px 0 15px rgba(0,0,0,0.1); z-index:9999; transition:right 0.3s ease; display:flex; flex-direction:column; border-left:1px solid #e2e8f0;">
  <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
    <h3 style="margin:0; font-size:16px; font-weight:600; color:#0f172a;">Geçmiş Analizler</h3>
    <button id="btn-close-history-sidebar" style="background:none; border:none; cursor:pointer; padding:4px; color:#64748b;">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>
  </div>
  <div style="padding:12px 20px; font-size:13px; color:#64748b; background:#f1f5f9; display:flex; justify-content:space-between; align-items:center;">
    Tıklayarak analizi geri yükle
    <button class="btn btn--ghost btn--sm" id="btn-clear-history-sidebar" style="font-size:12px; padding:2px 6px; color:#ef4444;">Tümünü Sil</button>
  </div>
  <div id="copilot-sidebar-history-list" style="flex:1; overflow-y:auto; padding:12px 20px; display:flex; flex-direction:column; gap:8px; scroll-behavior:smooth;">
    <p class="empty-note">Henüz geçmiş analiz yok.</p>
  </div>
</div>
<!-- /HISTORY SIDEBAR -->

</section>