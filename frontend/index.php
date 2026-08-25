<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AG SEO Check Up - Admin Panel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<script src="https://accounts.google.com/gsi/client" async defer></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="js/keyword-engine.js"></script>
<script src="js/copilot.js?v=<?= time() ?>"></script>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/copilot.css?v=<?= time() ?>">
<link rel="stylesheet" href="css/welcome.css">

<!-- Text SEO Module Dependencies -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
  // Tailwind rengi ve çakışma önleyici yapılandırma
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          primary: '#2563eb',
          secondary: '#475569',
          success: '#10b981',
          warning: '#f59e0b',
          danger: '#ef4444'
        }
      }
    }
  }
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/@phosphor-icons/web"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsdiff/7.0.0/diff.min.js"></script>
<link rel="stylesheet" href="css/text-seo.css">
<link rel="stylesheet" href="css/text-seo-pdf.css">
</head>
<body>

<!-- KARŞILAMA EKRANI (Welcome Overlay) -->
<div id="welcome-overlay">
  <div class="welcome-container">
    <div class="welcome-header">
      <h1>AG_seo_check_up'a Hoş Geldiniz</h1>
      <p>Yapay zeka destekli SEO ve İçerik Yönetim Platformu. Bugün neye odaklanmak istersiniz?</p>
    </div>
    
    <div class="welcome-grid">
      <!-- KART 1: Hızlı AI Bot -->
      <div class="wc-card" style="border-color: rgba(59,130,246,0.3);">
        <div class="wc-card-icon icon-ai">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <h3>Hızlı AI SEO (GEO Bot)</h3>
        <p>URL'nizi girin, yapay zeka sitenizi tarayıp dönüşüm, SGE ve içerik boşluklarını hemen analiz etsin.</p>
        <div class="wc-url-box">
          <input type="text" id="wc-url-input" placeholder="https://www.site.com/hizmet">
          <button id="wc-bot-start">Başla</button>
        </div>
      </div>

      <!-- KART 2: İçerik İyileştirme -->
      <div class="wc-card" id="wc-card-content">
        <div class="wc-card-icon icon-content">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </div>
        <h3>Metin ve İçerik Optimizasyonu</h3>
        <p>Elinizdeki bir metni yapay zeka ile SEO uyumlu hale getirin, başlık ve meta açıklamalarınızı düzenleyin.</p>
      </div>

      <!-- KART 3: Teknik Analiz -->
      <div class="wc-card" id="wc-card-tech">
        <div class="wc-card-icon icon-tech">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        </div>
        <h3>Teknik SEO & Schema</h3>
        <p>Web sitenizin teknik performansını ölçün, JSON-LD schema üretin veya var olan yapısal verileri onarın.</p>
      </div>

      <!-- KART 4: Gelişmiş Çalışma Yüzeyi -->
      <div class="wc-card" id="wc-card-dashboard">
        <div class="wc-card-icon icon-dash">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
        </div>
        <h3>Gelişmiş Panele Git</h3>
        <p>Beni doğrudan tüm sekmelerin ve ayarların bulunduğu ana çalışma paneline (dashboard) yönlendir.</p>
      </div>
    </div>
  </div>
</div><div class="app">
  <!-- ============================================================
       SIDEBAR
  ============================================================ -->
  <aside class="sidebar">
    <div class="sidebar__brand">
      <div class="brand-mark">
        <svg viewBox="0 0 36 36" width="36" height="36">
          <circle class="pulse" cx="18" cy="18" r="15"/>
          <circle class="pulse pulse2" cx="18" cy="18" r="10"/>
          <circle class="dot" cx="18" cy="18" r="3"/>
        </svg>
      </div>
      <div class="brand-text">
        <div class="name">AG_seo_check_up</div>
        <div class="sub">Admin Paneli</div>
      </div>
    </div>

    <div class="client-box">
      <label for="client-select">Aktif Müşteri</label>
      <select class="client-select-dark" id="client-select">
        <option value="">— Müşteri seçin —</option>
      </select>
      <div id="sidebar-client-domain" style="font-size: 11px; color: var(--muted-2); margin-bottom: 8px; display: none; cursor: pointer;" title="Ana domaine git / kopyala"></div>
      
      <div style="display:flex; gap:4px;">
        <button class="btn btn--sidebar btn--sm" id="client-add-btn" style="flex:1;">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Ekle
        </button>
        <button class="btn btn--sidebar btn--sm" id="client-edit-btn" style="flex:1;" title="Müşteriyi Düzenle">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
        </button>
        <button class="btn btn--sidebar btn--sm" id="client-delete-btn" style="flex:1;" title="Müşteriyi Sil">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        </button>
      </div>
    </div>

    <div id="sidebar-site-explorer" style="display:none; margin: 0 20px 16px 20px; padding: 10px; background: rgba(0,0,0,0.15); border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
      <div style="font-size: 10.5px; font-weight: bold; color: var(--muted-2); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em; display: flex; justify-content: space-between; align-items: center;">
        Sitemap Explorer
        <button id="refresh-sitemap-btn" style="background:none; border:none; color:var(--accent); cursor:pointer; font-size:10px;">Yenile</button>
      </div>
      <div id="site-explorer-tree" style="max-height: 180px; overflow-y: auto; font-size: 11px; line-height: 1.6; color: #E4E7EE;">
         <span class="spinner" style="width:12px;height:12px;border-width:2px;display:inline-block;vertical-align:middle;margin-right:5px;"></span> Yükleniyor...
      </div>
      <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 8px;">
        <button class="btn btn--primary" id="auto-pilot-btn" style="width: 100%; font-size:11px; padding:6px; display:none;" disabled>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
          Auto-Pilot Başlat
        </button>
        <button class="btn btn--primary" id="schedule-night-btn" style="width: 100%; font-size:11px; padding:6px; display:none;" disabled title="Gece 03:00'te analizi başlatır ve raporu Google Drive'a gönderir">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Gece 3'te Çalıştır
        </button>
      </div>
      <!-- Zamanlayıcı Bilgi Alanı -->
      <div id="schedule-timer-info" style="display:none; font-size: 10px; color: var(--muted-2); margin-top: 8px; text-align: center; background: rgba(59, 130, 246, 0.1); padding: 4px; border-radius: 4px; border: 1px solid rgba(59, 130, 246, 0.2);">
      </div>
    </div>

    <div style="padding: 0 20px 16px 20px;">
      <button class="btn btn--sidebar" id="btn-reopen-welcome" style="width: 100%; font-size:12px; justify-content:center;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        Hızlı Asistan'ı Aç
      </button>
    </div>

    <ul class="nav" id="nav-list">
      <li><button class="nav__item active" data-tab="1"><span class="num">01</span>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h12l4 4v12H4z"/><path d="M16 4v4h4"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="14" y2="16"/></svg>
        Metin Bazlı SEO</button></li>
      <li><button class="nav__item" data-tab="2"><span class="num">02</span>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a10 10 0 1 1 7.07-2.93"/><path d="M12 12l4-3"/><circle cx="12" cy="12" r="1.2" fill="currentColor" stroke="none"/></svg>
        Teknik SEO</button></li>
      <li><button class="nav__item" data-tab="3"><span class="num">03</span>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        AI SEO Analizi</button></li>
      <li><button class="nav__item" data-tab="4"><span class="num">04</span>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        Yapılacaklar</button></li>
    </ul>

    <div class="sidebar__footer">
      
      <a href="logout.php" onclick="sessionStorage.removeItem('ag_welcome_seen');" class="btn btn--sidebar btn--sm logout-btn" style="text-decoration:none; display:flex; align-items:center; justify-content:center; gap:8px; padding:12px 16px; border-radius:8px; transition:all 0.2s;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        <span style="font-weight: 500;">Çıkış Yap</span>
    </a>
<!-- 
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20Z"/><line x1="2" y1="12" x2="22" y2="12"/></svg>
        -->
      <div class="google-status" id="google-auth-status"><span class="dot"></span> Bağlı değil</div>
      <span class="env mt-8" style="display:flex;"><span class="led"></span> Prototip Ortamı</span>
      <span>v1.0 — Agency OS</span>
    </div>
  </aside>

  <!-- ============================================================
       MAIN
  ============================================================ -->
  <main class="main">
    <header class="topbar" id="topbar">
      <div class="topbar__eyebrow" id="topbar-eyebrow">01 · İÇERİK</div>
      <h1 id="topbar-title">İçerik &amp; İç Linkleme</h1>
      <p class="topbar__desc" id="topbar-desc">Metni hedef kelimeye göre optimize edin, mevcut blog yazılarınıza otomatik iç linkler önerin.</p>
    </header>

    <div class="content">
<section class="tab-panel active" id="tab-1">
  <?php include __DIR__ . '/src/TextSeo/views/tab1_view.php'; ?>
</section>

      <!-- ==========================================================
           TAB 2 — ANAHTAR KELİME STRATEJİSİ
      =========================================================== -->
      
<section class="tab-panel" id="tab-2">
        <div class="card">
          <div class="card__title">Canlı Denetim</div>
          <div class="flex gap-12 mt-16" style="align-items:flex-end;">
            <div class="field" style="flex:1; margin-bottom:0;">
              <label for="t3-url">Web Sitesi URL'si</label>
              <input class="input" id="t3-url" type="text" placeholder="https://www.musterisitesi.com">
            </div>
            <button class="btn btn--primary" id="t3-audit-btn">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
              <span id="t3-audit-label">Canlı Denetim Yap</span>
            </button>
          </div>
        </div>

        <div class="card mt-20 hidden" id="t3-fullcrawl-card">
          <div class="card__head">
            <div class="card__title">Tarama Kısmi Kaldı</div>
            <span class="small muted" id="t3-fullcrawl-note">Standart mod sınırına ulaşıldı.</span>
          </div>
          <div class="mt-16 flex gap-12" style="align-items:center; flex-wrap:wrap;">
            <span class="small">Sitenin tamamını taramak ister misiniz? Bu biraz zaman alabilir.</span>
            <button class="btn btn--dark btn--sm" id="t3-fullcrawl-btn">
              <span id="t3-fullcrawl-label">Evet, Tüm Siteyi Tara</span>
            </button>
          </div>
        </div>

        <div class="card mt-20 hidden" id="t3-progress-card">
          <div class="card__head">
            <div class="card__title">Analiz Sürüyor…</div>
            <span class="small muted">Şu an hangi kontrolün yapıldığını aşağıda canlı olarak görebilirsiniz</span>
          </div>
          <div class="mt-16" id="t3-progress-body"></div>
        </div>

        <div class="card mt-20" id="t3-output-card">
          <div class="card__head">
            <div class="card__title">Lighthouse & PageSpeed Denetim Sonuçları</div>
            <span class="small muted" id="t3-audit-url">Henüz bir tarama yapılmadı...</span>
          </div>

          <div class="mt-12">
            <div class="toggle-group" id="t3-psi-strategy-toggle">
              <button type="button" class="toggle-btn active" data-strategy="mobile">Mobil</button>
              <button type="button" class="toggle-btn" data-strategy="desktop">Masaüstü</button>
            </div>
          </div>

          <div class="mt-16 hidden" id="t3-psi-warning"></div>

          <!-- 4 Ana Kategori Skoru -->
          <div class="score-grid">
            <div class="score-card">
              <div class="svg-wrap">
                <svg viewBox="0 0 36 36"><circle class="bg" cx="18" cy="18" r="15.9155"/><circle class="fill" id="t3-perf-circle" cx="18" cy="18" r="15.9155" stroke-dasharray="100 100" stroke-dashoffset="100"/></svg>
                <div class="val" id="t3-perf-val">—</div>
              </div>
              <div class="lbl">Performans</div>
            </div>
            <div class="score-card">
              <div class="svg-wrap">
                <svg viewBox="0 0 36 36"><circle class="bg" cx="18" cy="18" r="15.9155"/><circle class="fill" id="t3-seo-circle" cx="18" cy="18" r="15.9155" stroke-dasharray="100 100" stroke-dashoffset="100"/></svg>
                <div class="val" id="t3-seo-val">—</div>
              </div>
              <div class="lbl">SEO</div>
            </div>
            <div class="score-card">
              <div class="svg-wrap">
                <svg viewBox="0 0 36 36"><circle class="bg" cx="18" cy="18" r="15.9155"/><circle class="fill" id="t3-a11y-circle" cx="18" cy="18" r="15.9155" stroke-dasharray="100 100" stroke-dashoffset="100"/></svg>
                <div class="val" id="t3-a11y-val">—</div>
              </div>
              <div class="lbl">Erişilebilirlik</div>
            </div>
            <div class="score-card">
              <div class="svg-wrap">
                <svg viewBox="0 0 36 36"><circle class="bg" cx="18" cy="18" r="15.9155"/><circle class="fill" id="t3-bp-circle" cx="18" cy="18" r="15.9155" stroke-dasharray="100 100" stroke-dashoffset="100"/></svg>
                <div class="val" id="t3-bp-val">—</div>
              </div>
              <div class="lbl">En İyi Uygulamalar</div>
            </div>
          </div>

          <!-- Web Vitals (Hız Metrikleri) -->
          <div class="mt-16">
            <div class="meter-row">
              <div class="meter-row__head">
                <span class="label">LCP — Largest Contentful Paint</span>
                <span class="value" id="t3-lcp-value">—</span>
              </div>
              <div class="meter-track"><div class="meter-fill" id="t3-lcp-fill" style="width:0%;"></div></div>
            </div>
            <div class="meter-row">
              <div class="meter-row__head">
                <span class="label">FCP — First Contentful Paint</span>
                <span class="value" id="t3-fcp-value">—</span>
              </div>
              <div class="meter-track"><div class="meter-fill" id="t3-fcp-fill" style="width:0%;"></div></div>
            </div>
            <div class="meter-row">
              <div class="meter-row__head">
                <span class="label">CLS — Cumulative Layout Shift</span>
                <span class="value" id="t3-cls-value">—</span>
              </div>
              <div class="meter-track"><div class="meter-fill" id="t3-cls-fill" style="width:0%;"></div></div>
            </div>
            <div class="meter-row">
              <div class="meter-row__head">
                <span class="label">TTFB — Sunucu Yanıt Süresi</span>
                <span class="value" id="t3-ttfb-value">—</span>
              </div>
              <div class="meter-track"><div class="meter-fill" id="t3-ttfb-fill" style="width:0%;"></div></div>
            </div>
            <!-- INP -->
            <div class="meter">
              <div class="meter-head">
                <span class="label">Etkileşim (INP)</span>
                <span class="value" id="t3-inp-value">—</span>
              </div>
              <div class="meter-track"><div class="meter-fill" id="t3-inp-fill" style="width:0%;"></div></div>
            </div>
          </div>
        </div>

        <div class="card mt-20 hidden" id="t3-quick-audit-card">
          <div class="card__head">
            <div class="card__title">Hızlı Teknik Denetim (Sunucu Taraflı)</div>
            <span class="small muted">robots.txt, sitemap.xml, SSL, canonical</span>
          </div>
          <div class="table-wrap mt-16">
            <table class="table" style="width: 100%; text-align: left; border-collapse: collapse;">
              <tbody>
                <tr style="border-bottom: 1px solid var(--border-soft);">
                  <td style="padding:12px; font-weight:600; width:220px;">SSL (HTTPS)</td>
                  <td style="padding:12px;" id="t3-qa-ssl"><span class="tag">Bekleniyor</span></td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-soft);">
                  <td style="padding:12px; font-weight:600;">Robots.txt</td>
                  <td style="padding:12px;" id="t3-qa-robots"><span class="tag">Bekleniyor</span></td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-soft);">
                  <td style="padding:12px; font-weight:600;">Sitemap.xml</td>
                  <td style="padding:12px;" id="t3-qa-sitemap"><span class="tag">Bekleniyor</span></td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-soft);">
                  <td style="padding:12px; font-weight:600;">Noindex</td>
                  <td style="padding:12px;" id="t3-qa-noindex"><span class="tag">Bekleniyor</span></td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-soft);">
                  <td style="padding:12px; font-weight:600;">Canonical</td>
                  <td style="padding:12px;" id="t3-qa-canonical"><span class="tag">Bekleniyor</span></td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-soft);">
                  <td style="padding:12px; font-weight:600;">Mobil-Öncelikli Uyum</td>
                  <td style="padding:12px;" id="t3-qa-mobile"><span class="tag">Bekleniyor</span></td>
                </tr>
                <tr>
                  <td style="padding:12px; font-weight:600;">Kırık Linkler</td>
                  <td style="padding:12px;" id="t3-qa-links"><span class="tag">Bekleniyor</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card mt-20 hidden" id="t3-composite-score-card">
          <div class="card__head">
            <div class="card__title">Genel Teknik SEO Skoru</div>
            <span class="small muted">Ağırlıklı kategori ortalaması + kritik kapı kontrolleri — Lighthouse un düz ortalaması DEĞİLDİR</span>
          </div>
          <div class="flex gap-24 mt-16" style="align-items:center; flex-wrap:wrap;">
            <div class="svg-wrap" style="width:110px; height:110px; flex-shrink:0;">
              <svg viewBox="0 0 36 36"><circle class="bg" cx="18" cy="18" r="15.9155"/><circle class="fill" id="t3-final-score-circle" cx="18" cy="18" r="15.9155" stroke-dasharray="100 100" stroke-dashoffset="100"/></svg>
              <div class="val" id="t3-final-score-val" style="font-size:22px;">—</div>
            </div>
            <div style="flex:1; min-width:260px;" id="t3-gates-warning"></div>
          </div>
          <div class="mt-16" id="t3-category-breakdown"></div>
        </div>

        <div class="card mt-20 hidden" id="t3-findings-card">
          <div class="card__head">
            <div class="card__title">Önceliklendirilmiş Teknik SEO Bulguları</div>
            <span class="small muted">önce önem derecesi (yüksek → orta → düşük), sonra aynı derece içinde etkilenen sayfa oranı × güven seviyesine göre sıralanmıştır</span>
          </div>
          <div class="mt-16" id="t3-findings-body"></div>
        </div>

        <!-- Bu kart kullanicidan gizlendi: localhost:3000/api/... adresine
             istek atiyor, bu proje pure-PHP oldugu icin boyle bir servis hic
             yok ve buton hicbir sonuc uretmiyor. DOM'dan tamamen silmedik
             cunku app.js'deki 8 adimli Auto-Pilot akisi bu id'lere .click()
             ile programatik olarak erisiyor (bkz. runEnterpriseAutoPilot,
             Adim 6/8) - silinirse tum try/catch bloğu patlar ve Auto-Pilot'un
             kalan adimlari (7 ve 8) hic calismaz. -->
        <div class="card mt-20 hidden" id="t3-schema-card">
          <div class="card__head">
            <div class="card__title">Toplu Şema (Schema) Denetleyici & Onarıcı</div>
            <button class="btn btn--dark btn--sm" id="t3-schema-audit-btn">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              <span id="t3-schema-audit-label">Denetle ve Onar</span>
            </button>
          </div>
          <p class="card__hint">Sayfadaki tüm yapılandırılmış verileri (JSON-LD) tarar, Google'ın zorunlu alanlarına göre eksikleri tespit edip düzeltilmiş kod üretir.</p>
          <div class="field mt-16">
            <label for="t3-schema-url">Sayfa URL'si</label>
            <input class="input" id="t3-schema-url" type="text" placeholder="https://www.musterisitesi.com/urun-sayfasi">
          </div>
        </div>

        <!-- SCHEMA ÜRETİCİ (Tab 4\'ten Taşındı) -->
        <!-- Bu kart da yukarisiyla ayni sebeple gizlendi (kullaniciya Teknik
             SEO'da gorunmesin istendi) - kendisi calisiyor (saf JS, backend'e
             ihtiyaci yok) ama Auto-Pilot'un t4-* id'lerine bagimli oldugu icin
             DOM'dan silinmedi, sadece hidden class'i ile gizlendi. -->
        <div class="card mt-20 hidden">
          <div class="card__head">
            <div class="card__title">Schema Üretici & JSON-LD</div>
            <button class="btn btn--dark btn--sm" id="t4-ai-extract-btn" title="İçerikten otomatik çıkarır">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
              <span id="t4-ai-extract-label">AI ile Otomatik Doldur</span>
            </button>
          </div>
          <p class="card__hint">Ürün veya işletme bilgilerinizi kullanarak yapılandırılmış JSON-LD üretin.</p>
          <div class="field mt-16">
            <label for="t4-schema-type">Schema Türü</label>
            <select class="input" id="t4-schema-type">
              <option value="product">Ürün (E-Ticaret)</option>
              <option value="local">Yerel İşletme (Kurumsal)</option>
              <option value="service">Hizmet (Service)</option>
              <option value="faq">Sıkça Sorulan Sorular (FAQPage)</option>
              <option value="llmstxt">Yapay Zeka Özeti (llms.txt)</option>
            </select>
          </div>
          <div class="field">
            <label for="t4-name" id="t4-name-label">Başlık / İsim</label>
            <input class="input" id="t4-name" type="text" placeholder="örn. Deri Sırt Çantası">
          </div>
          <button class="btn btn--primary mt-8" id="t4-generate-btn" style="width:100%; justify-content:center;">JSON-LD Üret</button>
          
          <div class="mt-16" id="t4-output-wrap">
            <div class="code-block hidden" id="t4-output-card"><code id="t4-output-code"></code></div>
          </div>
        </div>
</section>

      <!-- ==========================================================
           TAB 4 — SCHEMA ÜRETİCİ
      =========================================================== -->
      
<section class="tab-panel" id="tab-3">
        <!-- GEO Content Copilot UI -->
        <div class="card" id="copilot-card" style="border-top: 4px solid var(--accent); padding:0;">
          <div style="padding: 24px 24px 0 24px;">
            <div class="card__title has-tooltip" style="display:inline-flex; align-items:center;" data-tooltip="Yapay zeka asistanı ile URL bazlı SEO stratejinizi oluşturun.">GEO AI Bot (URL Tabanlı)<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: #64748b; opacity:1;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg></div>
            <div class="card__hint">Web sitenizin SEO ve Yapay Zeka (SGE) görünürlüğünü sohbet asistanı ile adım adım analiz edin.</div>
          </div>
          
          <div class="copilot-container" style="border:none; margin-top:0; border-radius:0;">
            <div class="copilot-header" style="position:relative; z-index:50;">
              <button id="btn-return-dashboard" class="btn btn--ghost btn--sm" style="margin-right: 16px; display:inline-flex; align-items:center; color: var(--muted); font-weight:600; padding:4px 8px; border-radius:6px; font-size:13px;">
       <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
       Kontrol Paneline Dön
    </button>
    <div class="copilot-progress" id="copilot-progress" style="display:none;">
                
<div class="copilot-step info-tooltip" data-step="1" id="cp-step-1" style="cursor: pointer;">
  <div class="copilot-step-circle"><span class="num">1</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
  <div class="step-label-wrapper">
      <span>İş Bağlamı</span>
  </div>
  <div class="tooltip-text">
      Sitenizin sektörü, hitap ettiği kitle ve anahtar kelime varlıkları yapay zeka gözüyle analiz edilir.
      <br><br>
      <span style="font-size: 10px; font-style: italic; color: #cbd5e1;">Sohbette bu adıma gitmek için tıklayın</span>
  </div>
  <button class="btn-fix-issue" data-step="1" id="btn-fix-1" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="1. Adımı Çöz">🔧</button>
</div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                
<div class="copilot-step info-tooltip" data-step="2" id="cp-step-2" style="cursor: pointer;">
  <div class="copilot-step-circle"><span class="num">2</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
  <div class="step-label-wrapper">
      <span>Etkililik</span>
  </div>
  <div class="tooltip-text">
      Kullanıcıların bu sayfa bağlamında sorabileceği en kritik sorular ve sitenizin bunlara verdiği cevapların kalitesi ölçülür.
      <br><br>
      <span style="font-size: 10px; font-style: italic; color: #cbd5e1;">Sohbette bu adıma gitmek için tıklayın</span>
  </div>
  <button class="btn-fix-issue" data-step="2" id="btn-fix-2" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="2. Adımı Çöz">🔧</button>
</div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                
<div class="copilot-step info-tooltip" data-step="3" id="cp-step-3" style="cursor: pointer;">
  <div class="copilot-step-circle"><span class="num">3</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
  <div class="step-label-wrapper">
      <span>Rakip Analizi</span>
  </div>
  <div class="tooltip-text">
      Sektördeki en güçlü rakiplere kıyasla sitenizin içerik açısından hangi noktalarda eksik kaldığı tespit edilir.
      <br><br>
      <span style="font-size: 10px; font-style: italic; color: #cbd5e1;">Sohbette bu adıma gitmek için tıklayın</span>
  </div>
  <button class="btn-fix-issue" data-step="3" id="btn-fix-3" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="3. Adımı Çöz">🔧</button>
</div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                
<div class="copilot-step info-tooltip" data-step="4" id="cp-step-4" style="cursor: pointer;">
  <div class="copilot-step-circle"><span class="num">4</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
  <div class="step-label-wrapper">
      <span>AI Güveni</span>
  </div>
  <div class="tooltip-text">
      E-E-A-T (Deneyim, Uzmanlık, Otoriterlik, Güvenilirlik) kurallarına göre sitenizin yapay zekaya ne kadar güven verdiği puanlanır.
      <br><br>
      <span style="font-size: 10px; font-style: italic; color: #cbd5e1;">Sohbette bu adıma gitmek için tıklayın</span>
  </div>
  <button class="btn-fix-issue" data-step="4" id="btn-fix-4" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="4. Adımı Çöz">🔧</button>
</div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                
<div class="copilot-step info-tooltip" data-step="5" id="cp-step-5" style="cursor: pointer;">
  <div class="copilot-step-circle"><span class="num">5</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
  <div class="step-label-wrapper">
      <span>Optimizasyon</span>
  </div>
  <div class="tooltip-text">
      SGE (Search Generative Experience) ile uyumlu, okunabilirliği yüksek yepyeni bir içerik taslağı sunulur.
      <br><br>
      <span style="font-size: 10px; font-style: italic; color: #cbd5e1;">Sohbette bu adıma gitmek için tıklayın</span>
  </div>
  <button class="btn-fix-issue" data-step="5" id="btn-fix-5" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="5. Adımı Çöz">🔧</button>
</div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--border)"><path d="M9 18l6-6-6-6"/></svg>
                
<div class="copilot-step info-tooltip" data-step="6" id="cp-step-6" style="cursor: pointer;">
  <div class="copilot-step-circle"><span class="num">6</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
  <div class="step-label-wrapper">
      <span>Entegrasyon</span>
  </div>
  <div class="tooltip-text">
      Yapay zekanın sitenizi sadece kelime kelime değil, anlamsal bir bütün (Semantik ve Şema) olarak nasıl algıladığı özetlenir.
      <br><br>
      <span style="font-size: 10px; font-style: italic; color: #cbd5e1;">Sohbette bu adıma gitmek için tıklayın</span>
  </div>
  <button class="btn-fix-issue" data-step="6" id="btn-fix-6" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="6. Adımı Çöz">🔧</button>
</div>
              </div>
              <button class="btn btn--danger btn--sm has-tooltip" data-tooltip="Sitenizi en dişli rakiplerinizle yapay zeka gözünden kıyaslayın." id="btn-open-battle-mode" style="margin-left:auto; display:inline-flex; align-items:center; background:#dc2626; color:white; border:none; box-shadow:0 2px 8px rgba(220,38,38,0.4); border-radius:20px;">
        Rakip Karşılaştırma<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: #fff; opacity:0.9;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
    </button>
              <button class="btn btn--secondary btn--sm has-tooltip" data-tooltip="Tüm analiz adımları (6 adım) tamamlandıktan sonra, müşteriye sunulmaya hazır detaylı PDF raporunu indirebilirsiniz." id="btn-download-pdf" style="margin-left:4px; display:inline-flex; align-items:center; background:#ef4444; color:white; border:none; opacity:0.5; cursor:not-allowed;">
        
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                
        Rapor İndir<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: #fff; opacity:0.9;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
    </button>
              <button class="btn btn--primary btn--sm has-tooltip" data-tooltip="Analizde tespit edilen eksikleri 'Yapılacaklar' listesine aktarır." id="btn-send-to-todos" title="Önce tüm analiz ve çözümleri bitirin" style="margin-left:4px; display:inline-flex; align-items:center; opacity:0.5; cursor:not-allowed;">
                📋 Gönder
              </button>
              <button class="btn btn--primary btn--sm has-tooltip" data-tooltip="Mevcut sohbeti geçmişe kaydeder." id="copilot-manual-save-btn" title="Sohbeti Kaydet" style="margin-left:4px; display:inline-flex; align-items:center;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                Kaydet
              </button>
              
<div style="display: flex; align-items: center; gap: 8px; margin-right: auto; margin-left: 16px;">
    <label class="switch has-tooltip" data-tooltip="Müşteri sunumu için karmaşık panelleri gizler, sadece sonuçlara odaklanır.">
        <input type="checkbox" id="client-view-toggle">
        <span class="slider round"></span>
    </label>
    <span style="font-size: 13px; font-weight: 500; color: var(--muted); cursor: pointer;" onclick="document.getElementById('client-view-toggle').click()">Sunum Modu</span>
</div>
<button class="btn btn--ghost has-tooltip" data-tooltip="Sohbeti temizler ve yeni bir analize başlar." id="copilot-reset-btn" title="Baştan Başla" style="margin-left:4px; padding: 0.5rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
              </button>
            </div>
            
            

            <div class="copilot-chat" id="copilot-chat" style="height: 40vh; min-height: 300px; max-height: 500px; overflow-y: auto;">
              <div id="copilot-chat-messages-container" style="display: flex; flex-direction: column; gap: 16px;"><div class="empty-state" id="copilot-empty-state"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg><p>Burada henüz bir analiz yok. Hemen yukarıya bir URL yapıştırarak sitenizin Google SGE (Yapay Zeka Aramaları) uyumluluğunu test etmeye başlayın!</p></div></div>
            </div>
            
            <div class="copilot-actions" id="copilot-actions">
            </div>

            <div class="quick-actions" id="copilot-quick-actions" style="display:none; flex-wrap:nowrap;"><button class="quick-action-btn has-tooltip" data-tooltip="Hızlı aksiyon ile AI'a anında talimat gönderin." onclick="document.getElementById('copilot-text-input').value=this.textContent; document.getElementById('copilot-send-btn').click();">Bana içerik eksiklerimi söyle<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: #3b82f6; opacity:0.8;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg></button><button class="quick-action-btn has-tooltip" data-tooltip="Hızlı aksiyon ile AI'a anında talimat gönderin." onclick="document.getElementById('copilot-text-input').value=this.textContent; document.getElementById('copilot-send-btn').click();">Rakiplerimden neden gerideyim?<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: #3b82f6; opacity:0.8;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg></button><button class="quick-action-btn has-tooltip" data-tooltip="Hızlı aksiyon ile AI'a anında talimat gönderin." onclick="document.getElementById('copilot-text-input').value=this.textContent; document.getElementById('copilot-send-btn').click();">Bu sayfa için SSS (FAQ) hazırla<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: #3b82f6; opacity:0.8;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg></button></div><div class="copilot-input-area" id="copilot-input-area" style="padding: 16px; border-top: 1px solid var(--border); display: flex; gap: 8px; background: #fff;">
              <input type="text" id="copilot-text-input" class="input" style="flex:1; margin-bottom:0;" placeholder="Hedef URL (Örn: https://adresgezgini.com)">
              <button class="btn btn--primary has-tooltip" data-tooltip="Yapay zeka asistanına analiz talimatını gönderin." id="copilot-send-btn" style="display:inline-flex; align-items:center;">Gönder<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: #fff; opacity:0.9;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg></button>
            </div>
          </div>
        </div>
        <div class="card mt-20" id="copilot-history-card">
          <div class="card__head">
            <div class="card__title has-tooltip" style="display:inline-flex; align-items:center;" data-tooltip="Daha önce yaptığınız analizleri buradan geri yükleyebilirsiniz.">Geçmiş Sohbetler<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: #64748b; opacity:1;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg></div>
            <button class="btn btn--ghost btn--sm has-tooltip" data-tooltip="Tüm sohbet geçmişini kalıcı olarak siler." id="btn-clear-history">Temizle<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: #64748b; opacity:1;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg></button>
          </div>
          <div class="card__hint">Önceki URL analizleriniz burada listelenir. Tıklayarak sohbeti geri yükleyebilirsiniz.</div>
          <div id="copilot-history-list" class="mt-16" style="display:flex; flex-direction:column; gap:8px;">
            <p class="empty-note">Henüz geçmiş sohbet yok.</p>
          </div>
        </div>
        </div>
        </section>

        <!-- YENİ 4. SEKME: YAPILACAKLAR / EKSİKLİKLER -->
        <section class="tab-panel" id="tab-4">
          <div class="card" style="margin-bottom: 20px;">
            <div class="card__head">
              <div class="card__title has-tooltip" style="display:inline-flex; align-items:center;" data-tooltip="AI tarafından tespit edilen tüm eksiklikleri tek bir listede takip edin.">Yapılacaklar & Eksiklikler<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: #64748b; opacity:1;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg></div>
              <button class="btn btn--ghost btn--sm has-tooltip" data-tooltip="Yapılacaklar listesini kalıcı olarak siler." id="btn-clear-todos">Listeyi Temizle<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: #64748b; opacity:1;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg></button>
            </div>
            <div class="card__hint">AI SEO Analizi (3. Sekme) sırasında sistemin tespit ettiği "Metin Bazlı SEO Gereksinimleri" ve "Teknik SEO Gereksinimleri" burada toplanır. Bir maddeye tıklayarak analiz edildiği noktaya (3. Sekmeye) geri dönebilirsiniz.</div>
            
            <div class="grid mt-20" style="gap:20px; grid-template-columns: repeat(3, 1fr);">
              <!-- Teknik Eksiklikler Sütunu -->
              <div class="todo-column" style="background:#f8fafc; border:1px solid var(--border); border-radius:8px; padding:16px;">
                <h3 style="font-size:14px; font-weight:600; color:#0f172a; margin-bottom:12px; display:flex; align-items:center; gap:6px;">
                  🚨 Teknik SEO Gereksinimleri
                </h3>
                <div id="todo-list-tech" style="display:flex; flex-direction:column; gap:8px;">
                  <p class="empty-note" style="font-size:12px; color:var(--muted-2);">Henüz teknik eksiklik bulunamadı.</p>
                </div>
              </div>

              <!-- Metin Bazlı Eksiklikler Sütunu -->
              <div class="todo-column" style="background:#f8fafc; border:1px solid var(--border); border-radius:8px; padding:16px;">
                <h3 style="font-size:14px; font-weight:600; color:#0f172a; margin-bottom:12px; display:flex; align-items:center; gap:6px;">
                  ✍️ Metin Bazlı SEO Gereksinimleri
                </h3>
                <div id="todo-list-text" style="display:flex; flex-direction:column; gap:8px;">
                  <p class="empty-note" style="font-size:12px; color:var(--muted-2);">Henüz metin eksikliği bulunamadı.</p>
                </div>
              </div>

              <!-- Genel / Bütünleşik Görevler Sütunu -->
              <div class="todo-column" style="background:#f8fafc; border:1px solid var(--border); border-radius:8px; padding:16px;">
                <h3 style="font-size:14px; font-weight:600; color:#0f172a; margin-bottom:12px; display:flex; align-items:center; gap:6px;">
                  📌 Genel & Bütünleşik Görevler
                </h3>
                <div id="todo-list-general" style="display:flex; flex-direction:column; gap:8px;">
                  <p class="empty-note" style="font-size:12px; color:var(--muted-2);">Henüz genel görev bulunamadı.</p>
                </div>
              </div>
            </div>
          </div>
        </section>
    </div>
  </main>
</div>

<div id="toast-container"></div>


<div class="t3-info-popup hidden" id="t3-info-popup">
  <div class="t3-info-popup__body" id="t3-info-popup-body"></div>
</div>

<!-- BATTLE MODE MODAL -->
<div class="modal-overlay" id="battle-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
  <div class="modal-content" style="background:#fff; width:90%; max-width:900px; height:85vh; border-radius:12px; padding:24px; display:flex; flex-direction:column; box-shadow:0 10px 40px rgba(0,0,0,0.3);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h2 style="margin:0; font-size:22px; display:flex; align-items:center; gap:8px;"> Rakip Savaş Modu (AI GEO Kıyaslaması)</h2>
      <button id="battle-close" style="background:none; border:none; font-size:28px; cursor:pointer; color:#64748b;">&times;</button>
    </div>
    <div style="display:flex; gap:12px; margin-bottom:16px;">
      <input type="url" id="battle-target-url" class="input" placeholder="Sizin URL'niz (Örn: https://adresgezgini.com/)" style="flex:1; border:2px solid #e2e8f0; padding:12px; border-radius:8px;">
      <input type="url" id="battle-comp-url" class="input" placeholder="Rakip URL (Örn: https://reklamvermek.com/)" style="flex:1; border:2px solid #e2e8f0; padding:12px; border-radius:8px;">
      <button id="battle-start-btn" class="btn btn--primary" style="background:#dc2626; color:white; border:none; padding:0 24px; font-weight:bold; border-radius:8px; font-size:15px;">Savaşı Başlat</button>
    </div>
    <div id="battle-results" class="chat-content" style="flex:1; overflow-y:auto; background:#f8fafc; border-radius:8px; padding:24px; border:1px solid #e2e8f0;">
       <div style="color:#64748b; text-align:center; margin-top:80px; font-size:16px;">
         <div style="font-size:48px; margin-bottom:16px;"></div>
         Hedef ve rakip URL'yi girip <strong>Savaşı Başlat</strong>'a tıklayın.<br>Yapay zeka SGE ve GEO standartlarına göre iki siteyi kıyaslayıp acil strateji çıkaracaktır.
       </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="js/technical-seo.js?v=<?= time() ?>.0"></script>

<script src="js/app.js?v=<?= time() ?>"></script>
<script src="js/welcome.js"></script>
<!-- Text SEO Scripts -->
<script src="js/text-seo-pdf.js"></script>
<script src="js/text-seo.js"></script>
</body>
</html>
