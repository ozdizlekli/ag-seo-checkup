<?php // AI SEO Hizmetler Paneli ?>
<section class="tab-panel" id="tab-3">

<!-- ACTION VIEW -->
<div id="copilot-action-view" style="display:flex; flex-direction:column; gap:20px; min-height:80vh;">

  <!-- TOP NAV -->
  <div style="display:flex; justify-content:space-between; align-items:center; width:100%; margin-bottom:4px;">
    <div>
      <span id="aiseo-url-badge" style="display:none; background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; border-radius:20px; padding:4px 14px; font-size:12px; font-weight:600;"></span>
      <span id="aiseo-type-badge" style="display:none; background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; border-radius:20px; padding:4px 14px; font-size:12px; font-weight:600; margin-left:6px;"></span>
    </div>
    <div style="display:flex; gap:6px; align-items:center;">
      <button id="btn-download-pdf"
        title="Tüm analiz adımları tamamlandıktan sonra indirilebilir."
        style="display:inline-flex; align-items:center; background:#64748b; color:white; border:none; opacity:0.5; cursor:not-allowed; padding:6px 12px; font-size:12px; border-radius:12px;">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        Rapor
      </button>
      <button id="btn-new-analysis"
        title="Yeni URL analizi başlatır."
        style="display:inline-flex; align-items:center; gap:4px; padding:6px 12px; font-size:12px; font-weight:600; color:var(--muted); border:none; background:none; cursor:pointer; border-radius:12px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        Yeni Analiz
      </button>
      <button id="btn-toggle-history-sidebar"
        title="Geçmiş Analizleri Göster"
        style="display:inline-flex; align-items:center; gap:4px; padding:6px 12px; font-size:12px; font-weight:600; color:var(--muted); border:none; background:none; cursor:pointer; border-radius:12px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        Geçmiş
      </button>
    </div>
  </div>


  <!-- ===================== URL GİRİŞ KARTI ===================== -->
  <div id="aiseo-url-card" class="card" style="border-top:4px solid var(--accent); border-radius:20px; padding:32px; box-shadow:0 4px 20px rgba(0,0,0,0.06);">
    <div style="max-width:680px; margin:0 auto; text-align:center;">
      <div style="font-size:28px; margin-bottom:8px;">🤖</div>
      <h2 style="font-size:20px; font-weight:700; color:#0f172a; margin:0 0 8px 0;">Yapay Zeka SEO Hizmetleri</h2>
      <p style="color:#64748b; font-size:14px; margin:0 0 28px 0;">Müşterinizin web sitesini analiz etmek için URL'yi girin. Yapay zeka site türünü otomatik tespit eder.</p>

      <div style="display:flex; align-items:center; background:#f0f4f9; border-radius:30px; padding:6px 8px 6px 22px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
        <input type="text" id="aiseo-url-input"
          placeholder="Örn: https://www.musteri-sitesi.com"
          style="flex:1; border:none; outline:none; background:transparent; font-size:15px; color:#1e293b; padding:10px 0; box-shadow:none; min-width:0;">
        <button id="aiseo-url-submit"
          style="background:#2563eb; color:#fff; border:none; border-radius:22px; padding:10px 22px; font-size:14px; font-weight:600; cursor:pointer; white-space:nowrap; transition:all 0.2s; flex-shrink:0;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle; margin-right:4px;"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>
          Analiz Et
        </button>
      </div>
      <p style="font-size:11px; color:#94a3b8; margin-top:10px;">Yapay zeka site türünü otomatik algılar • Gemini API ile güçlendirilmiştir</p>

      <!-- Tarama göstergesi -->
      <div id="aiseo-scan-indicator" style="display:none; margin-top:24px; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;">
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
    <div class="card" style="border-radius:20px; padding:20px 24px; box-shadow:0 2px 12px rgba(0,0,0,0.05);">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
        <div>
          <h3 style="font-size:15px; font-weight:700; color:#0f172a; margin:0 0 2px 0;">AI SEO Hizmetleri</h3>
          <p style="font-size:12px; color:#64748b; margin:0;">Hizmete tıklayarak AI analizi başlatın</p>
        </div>
        <button id="btn-run-all-services"
          style="display:inline-flex; align-items:center; gap:6px; background:#10b981; color:#fff; border:none; border-radius:20px; padding:10px 20px; font-size:13px; font-weight:600; cursor:pointer; box-shadow:0 3px 8px rgba(16,185,129,0.25); transition:all 0.2s;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
          Tüm Hizmetleri Yap
        </button>
      </div>

      <div id="aiseo-service-buttons" style="display:flex; flex-wrap:wrap; gap:8px;">

        <!-- Hizmet 1 -->
        <button class="aiseo-svc-btn has-tooltip" data-service="1"
          data-tooltip="Web sitenizdeki sayfa, hizmet ve işletme bilgilerinin yapay zeka destekli arama sistemleri tarafından daha anlaşılır hale gelmesi hedeflenir. Sitenin bilgi yapısı, hizmet başlıkları ve önemli sayfaları daha net bir düzene kavuşturulur."
          style="display:inline-flex; align-items:center; gap:6px; background:#fff; border:1.5px solid #e2e8f0; border-radius:20px; padding:8px 16px; font-size:13px; font-weight:500; color:#334155; cursor:pointer; transition:all 0.2s;">
          <span class="svc-icon">🏗️</span> Yapay Zeka Uyumlu Site Yapısı
          <span class="svc-status" data-svc="1"></span>
        </button>

        <!-- Hizmet 2 -->
        <button class="aiseo-svc-btn has-tooltip" data-service="2"
          data-tooltip="Yapay zeka destekli arama sistemlerinin web sitesini daha kolay anlamasına yardımcı olacak tanıtım dosyaları hazırlanır. llms.txt gibi dosyalar ve önemli sayfa bilgileri bu kapsamda değerlendirilir."
          style="display:inline-flex; align-items:center; gap:6px; background:#fff; border:1.5px solid #e2e8f0; border-radius:20px; padding:8px 16px; font-size:13px; font-weight:500; color:#334155; cursor:pointer; transition:all 0.2s;">
          <span class="svc-icon">🤖</span> Yapay Zeka Tanıtım Dosyaları
          <span class="svc-status" data-svc="2"></span>
        </button>

        <!-- Hizmet 3 -->
        <button class="aiseo-svc-btn has-tooltip" data-service="3"
          data-tooltip="Web sitenizin Google, Yandex ve Bing gibi arama motorlarına tanıtılması için gerekli kayıt ve doğrulama işlemleri rehber şeklinde sunulur. Search Console ve webmaster araçları bu kapsamda değerlendirilir."
          style="display:inline-flex; align-items:center; gap:6px; background:#fff; border:1.5px solid #e2e8f0; border-radius:20px; padding:8px 16px; font-size:13px; font-weight:500; color:#334155; cursor:pointer; transition:all 0.2s;">
          <span class="svc-icon">🔍</span> Arama Motoru Kayıtları
          <span class="svc-status" data-svc="3"></span>
        </button>

        <!-- Hizmet 4 -->
        <button class="aiseo-svc-btn has-tooltip" data-service="4"
          data-tooltip="Web sitenizin içeriğini arama motorlarına ve yapay zeka destekli sistemlere daha açık şekilde anlatmak için Schema.org uyumlu işaretleme yapıları kontrol edilir veya düzenlenir."
          style="display:inline-flex; align-items:center; gap:6px; background:#fff; border:1.5px solid #e2e8f0; border-radius:20px; padding:8px 16px; font-size:13px; font-weight:500; color:#334155; cursor:pointer; transition:all 0.2s;">
          <span class="svc-icon">📊</span> Yapılandırılmış Veri Düzenlemeleri
          <span class="svc-status" data-svc="4"></span>
        </button>

        <!-- Hizmet 5 -->
        <button class="aiseo-svc-btn has-tooltip" data-service="5"
          data-tooltip="Müşteriden alınan mevcut metinler web sitesine, kullanıcı deneyimine ve SEO yapısına uygun olacak şekilde düzenlenir. Hizmet açıklamaları, kurumsal metinler, başlıklar ve açıklama alanları geliştirilebilir."
          style="display:inline-flex; align-items:center; gap:6px; background:#fff; border:1.5px solid #e2e8f0; border-radius:20px; padding:8px 16px; font-size:13px; font-weight:500; color:#334155; cursor:pointer; transition:all 0.2s;">
          <span class="svc-icon">✍️</span> İçerik Düzenleme ve Geliştirme
          <span class="svc-status" data-svc="5"></span>
        </button>

        <!-- Hizmet 6 -->
        <button class="aiseo-svc-btn has-tooltip" data-service="6"
          data-tooltip="İçerikler yalnızca arama motorları için değil, ziyaretçilerin daha kolay anlayabileceği şekilde düzenlenir. Açık anlatım, doğru başlık yapısı ve yönlendirici içerik kurgusu oluşturulur."
          style="display:inline-flex; align-items:center; gap:6px; background:#fff; border:1.5px solid #e2e8f0; border-radius:20px; padding:8px 16px; font-size:13px; font-weight:500; color:#334155; cursor:pointer; transition:all 0.2s;">
          <span class="svc-icon">👥</span> Kullanıcı Odaklı İçerik
          <span class="svc-status" data-svc="6"></span>
        </button>

        <!-- Hizmet 7 -->
        <button class="aiseo-svc-btn has-tooltip" data-service="7"
          data-tooltip="Web sitesindeki ilgili sayfaların birbirini desteklemesi için site içi bağlantı yapısı düzenlenir. Kullanıcıların ilgili hizmetlere daha kolay ulaşması ve arama motorlarının site yapısını daha iyi anlaması hedeflenir."
          style="display:inline-flex; align-items:center; gap:6px; background:#fff; border:1.5px solid #e2e8f0; border-radius:20px; padding:8px 16px; font-size:13px; font-weight:500; color:#334155; cursor:pointer; transition:all 0.2s;">
          <span class="svc-icon">🔗</span> Site İçi İçerik Bağlantıları
          <span class="svc-status" data-svc="7"></span>
        </button>

      </div>
    </div><!-- /hizmet butonları -->


    <!-- ===================== SONUÇ ACCORDION ALANI ===================== -->
    <div id="aiseo-results-area" style="display:flex; flex-direction:column; gap:12px;">
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