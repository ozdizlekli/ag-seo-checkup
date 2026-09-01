<?php
define('AGSEO_INTERNAL', true);
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/db.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AG SEO Check Up - Admin Panel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;700;900&display=swap" rel="stylesheet">

<script src="https://accounts.google.com/gsi/client" async defer></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="js/keyword-engine.js"></script>
<script src="ai_seo/js/copilot.js?v=<?php echo time(); ?>"></script>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="ai_seo/css/copilot.css?v=<?php echo time(); ?>">
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
<link rel="stylesheet" href="src/TextSeo/css/text-seo.css">
<link rel="stylesheet" href="src/TextSeo/css/text-seo-pdf.css">

<style>
  /* Uygulama düzenini dikey (yan yana) yerine yatay (alt alta) yapıyoruz */
  .app {
    display: flex !important;
    flex-direction: column !important;
    min-height: 100vh;
  }
  .main {
    width: 100% !important;
    margin-left: 0 !important;
  }

  /* ==========================================================
     KURUMSAL TOP HEADER
     Marka renkleri korunarak daha sade, teslim edilebilir bir
     "enterprise dashboard" görünümüne kavuşturuldu.
  ========================================================== */
  :root {
    --ag-navy: #1F1D30;
    --ag-navy-2: #312F4D;
    --ag-gold: #FBBA00;
    --ag-slate: #475569;
    --ag-slate-light: #94A3B8;
    --ag-border: #E2E8F0;
    --ag-bg-soft: #F8FAFC;
  }

  .ag-top-header {
    position: relative;
    display: flex;
    flex-direction: column;
    background-color: #ffffff;
    border-bottom: 1px solid var(--ag-border);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    z-index: 1000;
  }


  /* Hamburger and GitHub-like Sidebar */
  .ag-hamburger {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1px solid var(--ag-border);
    background: transparent;
    cursor: pointer;
    color: var(--ag-slate);
    transition: all 0.2s;
  }
  .ag-hamburger:hover {
    background: var(--ag-bg-soft);
    color: var(--ag-navy);
  }
  
  .github-sidebar {
    position: fixed;
    top: 0;
    left: -320px;
    width: 320px;
    height: 100vh;
    background-color: #2F3146; /* Screenshot 2 background */
    color: #fff;
    z-index: 999999;
    box-shadow: 4px 0 15px rgba(0,0,0,0.2);
    transition: left 0.3s ease;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
  }
  .github-sidebar.open {
    left: 0;
  }
  
  .github-sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.6);
    z-index: 999998;
    display: none;
    opacity: 0;
    transition: opacity 0.3s;
  }
  .github-sidebar-overlay.show {
    display: block;
    opacity: 1;
  }
  
  .gh-sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
  }
  
  .gh-sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .gh-sidebar-brand img {
    width: 48px;
    border-radius: 8px;
  }
  .gh-sidebar-brand .title {
    font-size: 15px;
    font-weight: 700;
    color: #fff;
    line-height: 1.2;
  }
  .gh-sidebar-brand .badge {
    display: inline-block;
    background: #FBBA00;
    color: #1F1D30;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 12px;
    margin-top: 4px;
  }

  .gh-sidebar-close {
    background: none;
    border: none;
    color: #94A3B8;
    cursor: pointer;
    font-size: 24px;
  }
  
  .gh-sidebar-section {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
  }
  .gh-sidebar-section-title {
    font-size: 11px;
    font-weight: 700;
    color: #94A3B8;
    letter-spacing: 1px;
    margin-bottom: 12px;
    text-transform: uppercase;
  }
  
  .gh-client-select {
    width: 100%;
    background: rgba(0,0,0,0.2);
    border: 1px solid rgba(255,255,255,0.1);
    color: #fff;
    padding: 10px 12px;
    border-radius: 6px;
    font-size: 13px;
    margin-bottom: 12px;
    outline: none;
  }
  .gh-client-select option {
    background: #2F3146;
    color: #fff;
  }
  
  .gh-btn-group {
    display: flex;
    gap: 6px;
  }
  .gh-btn {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    color: #E2E8F0;
    padding: 8px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    gap: 6px;
  }
  .gh-btn:hover {
    background: rgba(255,255,255,0.1);
  }
  
  .gh-sitemap-box {
    background: rgba(0,0,0,0.15);
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.05);
    padding: 12px;
    margin: 20px;
  }
  

  /* İnce marka çizgisi: kurumsal kimliği vurgulayan üst şerit */
  .ag-top-header::before {
    content: "";
    display: block;
    height: 3px;
    background: linear-gradient(90deg, var(--ag-navy) 0%, var(--ag-navy-2) 45%, var(--ag-gold) 100%);
  }

  .ag-header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 72px;
    padding: 0 40px;
    gap: 24px;
  }

  .ag-header-left {
    display: flex;
    align-items: center;
    text-decoration: none;
    gap: 14px;
    flex-shrink: 0;
  }

  .ag-logo-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: var(--ag-bg-soft);
    border: 1px solid var(--ag-border);
    overflow: hidden;
    flex-shrink: 0;
  }

  .ag-logo-badge img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scale(1.55);
  }

  .ag-header-left .brand-text .name {
    font-family: 'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    font-size: 16px;
    font-weight: 700;
    color: var(--ag-navy);
    line-height: 1.2;
    letter-spacing: -0.01em;
  }

  .ag-header-left .brand-text .sub {
    font-family: 'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    font-size: 10.5px;
    font-weight: 600;
    color: var(--ag-slate-light);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-top: 2px;
  }

  /* Orta Menü (Sekmeler) */
  .ag-header-center {
    flex: 1;
    display: flex;
    justify-content: center;
    min-width: 0;
  }

  .ag-header-center ul {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: 6px;
  }

  .ag-header-center .nav__item {
    position: relative;
    background: none;
    border: none;
    font-family: 'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    font-size: 13px;
    font-weight: 600;
    color: var(--ag-slate);
    cursor: pointer;
    padding: 10px 16px;
    border-radius: 8px;
    transition: all 0.15s ease;
    white-space: nowrap;
  }

  .ag-header-center .nav__item:hover {
    color: var(--ag-navy);
    background: var(--ag-bg-soft);
  }

  .ag-header-center .nav__item.active {
    color: var(--ag-navy);
    background: #FFF6DC;
  }

  .ag-header-center .nav__item.active::after {
    content: "";
    position: absolute;
    left: 16px;
    right: 16px;
    bottom: 3px;
    height: 2px;
    border-radius: 2px;
    background: var(--ag-gold);
  }

  /* Eski tasarımdaki numara ve ikonları gizleyip temiz bir görünüm veriyoruz */
  .ag-header-center .nav__item svg,
  .ag-header-center .nav__item .num {
    display: none;
  }

  /* Sağ Taraf (Müşteri Seçimi ve Çıkış) */
  .ag-header-right {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-shrink: 0;
  }

  .ag-header-divider {
    width: 1px;
    height: 28px;
    background: var(--ag-border);
  }

  .ag-client-compact {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--ag-bg-soft);
    padding: 6px 12px;
    border-radius: 8px;
    border: 1px solid var(--ag-border);
  }

  .ag-client-label {
    font-family: 'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    font-size: 10px;
    font-weight: 700;
    color: var(--ag-slate-light);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    white-space: nowrap;
  }

  .ag-client-compact select {
    border: 1px solid var(--ag-border);
    background: #fff;
    border-radius: 6px;
    padding: 5px 8px;
    font-family: 'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    font-size: 12.5px;
    color: var(--ag-navy);
    outline: none;
    max-width: 170px;
  }

  .ag-client-compact .client-actions {
    display: flex;
    gap: 4px;
  }

  .ag-client-compact button {
    width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border: 1px solid var(--ag-border);
    border-radius: 6px;
    padding: 0;
    cursor: pointer;
    color: var(--ag-slate);
    font-size: 12px;
    transition: all 0.15s ease;
  }

  .ag-client-compact button:hover {
    background: var(--ag-navy);
    border-color: var(--ag-navy);
    color: #fff;
  }

  .btn-panel {
    background: var(--ag-navy);
    color: #fff;
    border-radius: 8px;
    padding: 9px 18px;
    font-family: 'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    font-size: 12.5px;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.15s ease;
  }

  .btn-panel:hover {
    background: var(--ag-navy-2);
  }

  .btn-outline {
    background: #fff;
    border: 1px solid var(--ag-border);
    color: var(--ag-navy);
    border-radius: 8px;
    padding: 9px 16px;
    font-family: 'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
  }

  .btn-outline:hover {
    border-color: var(--ag-navy);
    background: var(--ag-bg-soft);
  }

  @media (max-width: 1180px) {
    .ag-header-inner { padding: 0 20px; }
    .ag-header-center ul { gap: 2px; }
    .ag-header-left .brand-text .sub { display: none; }
    .ag-client-label { display: none; }
  }
</style>
</head>

<body>

<!-- GITHUB STYLE OFFCANVAS SIDEBAR -->
<div class="github-sidebar-overlay" id="gh-sidebar-overlay"></div>
<aside class="github-sidebar" id="gh-sidebar">
  <div class="gh-sidebar-header">
    <div class="gh-sidebar-brand">
      <img src="image.png" alt="Logo">
      <div>
        <div class="title " style="font-weight:500 ">AG SEO Check Up</div>
       
      </div>
    </div>
    <button class="gh-sidebar-close" id="gh-sidebar-close">&times;</button>
  </div>
  
  <div class="gh-sidebar-section">
    <div class="gh-sidebar-section-title">AKTİF MÜŞTERİ</div>
    <select class="gh-client-select" id="client-select">
      <option value="">— Müşteri seçin —</option>
    </select>
    <div id="sidebar-client-domain" style="font-size: 11px; color: #94A3B8; margin-bottom: 12px; display: none;"></div>
    
    <div class="gh-btn-group">
      <button class="gh-btn" id="client-add-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Ekle
      </button>
      <button class="gh-btn" id="client-edit-btn" title="Düzenle">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
      </button>
      <button class="gh-btn" id="client-delete-btn" title="Sil">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
      </button>
    </div>
  </div>
  
  <div class="gh-sitemap-box" id="sidebar-site-explorer" style="display:none;">
    <div style="font-size: 10.5px; font-weight: 700; color: #94A3B8; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.05em; display: flex; justify-content: space-between; align-items: center;">
      SİTEMAP EXPLORER
      <button id="refresh-sitemap-btn" style="background:none; border:none; color:#FBBA00; cursor:pointer; font-size:12px;" title="Yenile">&#x21bb;</button>
    </div>
    <div id="site-explorer-tree" style="max-height: 400px; overflow-y: auto; font-size: 12px; line-height: 2; color: #E4E7EE;">
       <span style="font-size:11px;">Müşteri seçin...</span>
    </div>
  </div>
</aside>
<!-- /GITHUB STYLE OFFCANVAS SIDEBAR -->


<!-- KARŞILAMA EKRANI (Welcome Overlay) -->
<div id="welcome-overlay">
  <div class="welcome-container">
    <div class="welcome-header">
      <h1>AG Seo Checkup'a Hoş Geldiniz</h1>
      <p>Yapay zeka destekli SEO ve İçerik Yönetim Platformu. Bugün neye odaklanmak istersiniz?</p>
      
      <div style="margin-top: 24px; display: flex; justify-content: center; gap: 12px; max-width: 700px; margin-left: auto; margin-right: auto; padding: 20px; background: #F8FAFC; border-radius: 12px; border: 1px solid #E2E8F0;">
         <input type="url" id="welcome-main-url-input" placeholder="URL girin (örn: https://...)" style="flex:1; border-radius:8px; border:1px solid #CBD5E1; background:#fff; color:#1F1D30; padding:12px 16px; font-size:14px; outline:none;">
         <button id="welcome-send-url-btn" style="background:#10b981; border:none; color:#fff; border-radius:8px; padding:0 20px; font-size:14px; font-weight:600; cursor:pointer; white-space:nowrap; transition: background 0.2s;">URL'yi Gönder</button>
         <button id="welcome-client-select-btn" style="background:#3b82f6; border:none; color:#fff; border-radius:8px; padding:0 20px; font-size:14px; font-weight:600; cursor:pointer; white-space:nowrap; transition: background 0.2s;" onclick="document.getElementById('gh-hamburger-btn').click(); document.getElementById('welcome-overlay').style.opacity='0'; setTimeout(()=>document.getElementById('welcome-overlay').style.display='none',400);">Müşteri Seç</button>
      </div>
    </div>
    
    <div class="welcome-grid">
      <!-- KART 1: Metin Bazlı SEO -->
      <div class="wc-card" id="wc-card-content">
        <div class="wc-card-icon icon-content">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </div>
        <h3>Metin Bazlı SEO</h3>
        <p>Elinizdeki bir metni yapay zeka ile SEO uyumlu hale getirin, başlık ve meta açıklamalarınızı düzenleyin.</p>
      </div>

      <!-- KART 2: Teknik SEO -->
      <div class="wc-card" id="wc-card-tech">
        <div class="wc-card-icon icon-tech">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        </div>
        <h3>Teknik SEO</h3>
        <p>Web sitenizin teknik performansını ölçün, JSON-LD schema üretin veya var olan yapısal verileri onarın.</p>
      </div>

      <!-- KART 3: AI SEO -->
      <div class="wc-card" id="wc-card-ai" style="border-color: rgba(59,130,246,0.3);">
        <div class="wc-card-icon icon-ai">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <h3>AI SEO</h3>
        <p>Yapay zeka sitenizi tarayıp dönüşüm, SGE ve içerik boşluklarını hemen analiz etsin.</p>
      </div>
    </div>
  </div>
</div><div class="app">
  <!-- ============================================================
       SIDEBAR
  ============================================================ -->
  <header class="ag-top-header">
    <div class="ag-header-inner">
      <!-- SOL: Hamburger, Logo ve Marka -->
      <div class="ag-header-left">
        <button class="ag-hamburger" id="gh-hamburger-btn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </button>
        <a href="#" style="display:flex; align-items:center; text-decoration:none; gap:12px; margin-left:12px;">
          <img src="image.png" alt="Logo" style="width:40px; height:40px; border-radius:6px; border: 1px solid var(--ag-border);">
          <div style="display:flex; flex-direction:column; justify-content:center; gap:4px;">
            <div style="font-family: 'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-weight:700; font-size:16px; color:#1F1D30; line-height:1; letter-spacing:-0.3px;">AG SEO Check Up</div>
            <div style="font-family: 'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size:12px; color:#6B6E82; display:flex; align-items:center; gap:6px; line-height:1;">
              <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'ozdizlekli'); ?></span>
              <span style="color:#94A3B8;">/</span>
              <span style="font-weight:700; color:#1F1D30;" id="top-header-client-name">Bekleniyor...</span>
            </div>
          </div>
        </a>
      </div>

      <!-- ORTA: Navigasyon Menüsü -->
      <nav class="ag-header-center">
        <ul id="nav-list">
          <li><button class="nav__item active" data-tab="1">Metin Bazlı SEO</button></li>
          <li><button class="nav__item" data-tab="2">Teknik SEO</button></li>
          <li><button class="nav__item" data-tab="3">AI SEO Hizmetleri</button></li>
          <!-- <li><button class="nav__item" data-tab="4">Yapılacaklar</button></li> -->
        </ul>
      </nav>

      <!-- SAĞ: Müşteri Seçimi ve İşlem Butonları -->
      <div class="ag-header-right">
        <!-- Müşteri yönetimi artık soldaki Hamburger menüye taşındı -->

        <button id="btn-reopen-welcome" class="btn-outline">Hızlı Asistan</button>
        <a href="logout.php" onclick="sessionStorage.removeItem('ag_welcome_seen');" class="btn-panel">Çıkış Yap</a>
      </div>
    </div>

    <!-- JavaScript'in hata vermemesi için gizli tutulan eski sidebar elemanları -->
    <div style="display:none;">
      <div id="google-auth-status"></div>
    </div>
  </header>

  <!-- ============================================================
       MAIN
  ============================================================ -->
  <main class="main">
    <header class="topbar" id="topbar" style="display:none;">
      <div class="topbar__eyebrow" id="topbar-eyebrow">01 · İÇERİK</div>
      <h1 id="topbar-title">İçerik &amp; İç Linkleme</h1>
      <p class="topbar__desc" id="topbar-desc">Metni hedef kelimeye göre optimize edin, mevcut blog yazılarınıza otomatik iç linkler önerin.</p>
    </header>

    <div class="content">
<section class="tab-panel active" id="tab-1" style="position: relative;">
  <?php include __DIR__ . '/src/textseo/index.php'; ?>
</section>

      <!-- ==========================================================
           TAB 2 — ANAHTAR KELİME STRATEJİSİ
      =========================================================== -->
      
<section class="tab-panel" id="tab-2">
        <div class="toggle-group t3-launch-tabs" id="t3-subview-toggle">
          <button type="button" class="toggle-btn active" data-subview="live">Canlı Denetim</button>
          <button type="button" class="toggle-btn" data-subview="history">Skor Geçmişi</button>
        </div>

        <div id="t3-live-view">
        <div class="card t3-launch-card">
          <div class="t3-launch-hero t3-hero-only" id="t3-launch-hero">
            <div class="t3-launch-hero__icon" aria-hidden="true">🔍</div>
            <h2 class="t3-launch-hero__title">Yeni Teknik Denetim</h2>
            <p class="t3-launch-hero__desc">Web sitesi adresini girin, teknik SEO denetimini başlatın.</p>
          </div>

          <div class="card__head hidden t3-post-audit">
            <div class="card__title">Yeni Teknik Denetim</div>
            <span class="small muted" id="t3-last-analysis-date">Henüz analiz yapılmadı</span>
          </div>

          <div class="t3-audit-row">
            <div class="field" style="margin-bottom:0;">
              <input class="input t3-url-input" id="t3-url" type="text" placeholder="https://www.musterisitesi.com" aria-label="Web Sitesi URL'si">
            </div>
            <button class="btn btn--primary" id="t3-audit-btn">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
              <span id="t3-audit-label">Denetimi Başlat</span>
            </button>
          </div>
          <p class="t3-launch-hero-caption t3-hero-only">Kayıtlı müşteriyle otomatik eşleşir • Google PageSpeed API ile çalışır</p>

          <!-- Kompakt musteri otomatik-eslesme durumu (sol) + otomatik-kaydet
               (sag) tek satirda - JS (renderClientMatchStatus) sol tarafi doldurur. -->
          <div class="t3-audit-meta hidden t3-post-audit">
            <div class="t3-client-match" id="t3-client-match-status"></div>
            <div class="t3-autosave-row">
              <button class="btn btn--ghost btn--sm" id="t3-history-save-btn" disabled title="Kaydetmek için önce bir denetim tamamlanmalı">Şimdi Kaydet</button>
            </div>
          </div>

          <!-- Manuel musteri secici - varsayilan gizli, "değiştir"/"Müşteri seç"
               linkine tiklaninca aciliyor (bkz. js/technical-seo.js). Ayni
               search-select bileseni Skor Gecmisi sekmesinde de kullaniliyor. -->
          <div class="search-select hidden mt-8" id="t3-history-client-searchselect" style="position:relative; max-width:320px;">
            <input type="text" class="input" id="t3-history-client-select-input" placeholder="Müşteri ara..." autocomplete="off">
            <input type="hidden" id="t3-history-client-select" value="">
            <div class="search-select__list hidden" id="t3-history-client-select-list"></div>
          </div>
        </div>

        <div class="t3-crawl-banner hidden mt-8" id="t3-fullcrawl-card">
          <div class="t3-crawl-banner__text">
            <strong>Daha kapsamlı analiz ister misiniz?</strong>
            <span class="small" id="t3-fullcrawl-note">Standart tarama sınırına ulaşıldı.</span>
          </div>
          <button class="btn btn--dark btn--sm" id="t3-fullcrawl-btn">
            <span id="t3-fullcrawl-label">Tüm Siteyi Tara</span>
          </button>
        </div>

        <div class="t3-scan-overlay hidden" id="t3-progress-card" role="dialog" aria-modal="true" aria-labelledby="t3-scan-title" aria-describedby="t3-scan-current" aria-live="polite" aria-busy="false" aria-hidden="true">
          <div class="t3-scan-overlay__panel" tabindex="-1">
            <div id="t3-scan-running">
              <div class="t3-scan-overlay__scan-dot" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" stroke-dasharray="18 8"/></svg></div>
              <div class="t3-scan-overlay__title" id="t3-scan-title">Teknik analiz sürüyor</div>
              <div class="t3-scan-overlay__current" id="t3-scan-current">Analiz hazırlanıyor…</div>
              <div class="t3-scan-overlay__bar" role="progressbar" aria-label="Analiz ilerlemesi" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="t3-scan-overlay__bar-fill" id="t3-scan-progress-fill"></div></div>
              <div class="t3-scan-overlay__steps" id="t3-progress-body"></div>
            </div>
            <div class="t3-scan-overlay__done hidden" id="t3-scan-done" role="status"><div class="t3-scan-overlay__done-icon" aria-hidden="true">✓</div><div class="t3-scan-overlay__done-text">Analiz tamamlandı. Sonuçlar hazırlanıyor…</div></div>
            <div class="t3-scan-overlay__error hidden" id="t3-scan-error" role="alert"><div class="t3-scan-overlay__error-icon" aria-hidden="true">!</div><div class="t3-scan-overlay__error-text" id="t3-scan-error-text"></div><div class="t3-scan-overlay__error-actions"><button class="btn btn--primary btn--sm" id="t3-scan-retry" type="button">Tekrar Dene</button><button class="btn btn--ghost btn--sm" id="t3-scan-close" type="button">Kapat</button></div></div>
          </div>
        </div>

        <div class="card mt-8 hidden t3-overview" id="t3-overview-card">
          <div class="card__head">
            <div><div class="card__title">Genel Bakış</div><div class="small muted" id="t3-overview-meta"></div></div>
            <button class="btn btn--dark btn--sm" id="t3-report-btn" type="button" disabled title="Rapor oluşturmak için önce bir denetim tamamlanmalı">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
              <span>Rapor Oluştur (PDF)</span>
            </button>
          </div>
          <div class="t3-overview__stats hidden" id="t3-overview-stats"></div>

          <div class="t3-overview-top">
            <div class="t3-score-hero">
              <div class="t3-score-hero__circle">
                <svg viewBox="0 0 36 36"><circle class="bg" cx="18" cy="18" r="15.9155"/><circle class="fill" id="t3-final-score-circle" cx="18" cy="18" r="15.9155" stroke-dasharray="100 100" stroke-dashoffset="100"/></svg>
                <div class="val" id="t3-final-score-val">—</div>
              </div>
              <div class="t3-score-hero__meta">
                <div class="t3-score-hero__title">SEO Sağlık Skoru
                  <span class="t3-info-icon" tabindex="0" title="Ağırlıklı kategori ortalaması + kritik eşik kontrolleri — Lighthouse'un düz ortalaması DEĞİLDİR">i</span>
                </div>
                <div class="t3-score-hero__health" id="t3-score-health">—</div>
                <div id="t3-gates-warning"></div>
              </div>
            </div>

            <div class="t3-psi-summary hidden" id="t3-psi-summary-card">
              <div class="t3-psi-summary__head">
                <span class="t3-psi-summary__title">PageSpeed Özeti</span>
                <button type="button" class="btn btn--ghost btn--sm" data-target="t3-output-card">Ayrıntılar</button>
              </div>
              <div class="t3-psi-summary-row">
                <div class="t3-psi-summary-col">
                  <small>Mobil</small>
                  <strong id="t3-psi-summary-mobile-val">—</strong>
                </div>
                <div class="t3-psi-summary-col">
                  <small>Masaüstü</small>
                  <strong id="t3-psi-summary-desktop-val">—</strong>
                </div>
              </div>
            </div>
          </div>

          <div class="t3-stat-row t3-stat-row--3" id="t3-result-stat-row">
            <div class="t3-summary-card t3-stat-card t3-stat-card--critical">
              <small>Kritik</small>
              <strong id="t3-stat-critical-val">—</strong>
            </div>
            <div class="t3-summary-card t3-stat-card t3-stat-card--warning">
              <small>Uyarı</small>
              <strong id="t3-stat-warning-val">—</strong>
            </div>
            <div class="t3-summary-card t3-stat-card t3-stat-card--success">
              <small>Başarılı</small>
              <strong id="t3-stat-success-val">—</strong>
            </div>
          </div>

          <div class="mt-12" id="t3-category-breakdown"></div>
        </div>

        <div class="t3-qa-accordion mt-12 hidden" id="t3-quick-audit-card">
          <button type="button" class="t3-qa-accordion__toggle" id="t3-qa-toggle" aria-expanded="false" aria-controls="t3-qa-panel">
            <span class="t3-qa-accordion__title">Hızlı Teknik Kontroller</span>
            <span class="t3-qa-accordion__summary" id="t3-qa-summary">—</span>
            <span class="t3-qa-accordion__cta">Ayrıntıları Gör
              <svg class="t3-qa-accordion__arrow" aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </span>
          </button>
          <div class="t3-qa-accordion__panel" id="t3-qa-panel" role="region" aria-labelledby="t3-qa-toggle" aria-hidden="true">
            <div class="t3-qa-accordion__panel-inner">
              <div class="t3-qa-grid">
                <div class="t3-qa-row"><span class="t3-qa-row__icon" id="t3-qa-ssl-icon"></span><span class="t3-qa-row__label">SSL</span><span class="t3-qa-row__value" id="t3-qa-ssl"><span class="tag">Bekleniyor</span></span></div>
                <div class="t3-qa-row"><span class="t3-qa-row__icon" id="t3-qa-robots-icon"></span><span class="t3-qa-row__label">Robots.txt</span><span class="t3-qa-row__value" id="t3-qa-robots"><span class="tag">Bekleniyor</span></span></div>
                <div class="t3-qa-row"><span class="t3-qa-row__icon" id="t3-qa-sitemap-icon"></span><span class="t3-qa-row__label">Sitemap</span><span class="t3-qa-row__value" id="t3-qa-sitemap"><span class="tag">Bekleniyor</span></span></div>
                <div class="t3-qa-row"><span class="t3-qa-row__icon" id="t3-qa-noindex-icon"></span><span class="t3-qa-row__label">Noindex</span><span class="t3-qa-row__value" id="t3-qa-noindex"><span class="tag">Bekleniyor</span></span></div>
                <div class="t3-qa-row"><span class="t3-qa-row__icon" id="t3-qa-canonical-icon"></span><span class="t3-qa-row__label">Canonical</span><span class="t3-qa-row__value" id="t3-qa-canonical"><span class="tag">Bekleniyor</span></span></div>
                <div class="t3-qa-row"><span class="t3-qa-row__icon" id="t3-qa-mobile-icon"></span><span class="t3-qa-row__label">Mobil Uyum</span><span class="t3-qa-row__value" id="t3-qa-mobile"><span class="tag">Bekleniyor</span></span></div>
                <div class="t3-qa-row"><span class="t3-qa-row__icon" id="t3-qa-links-icon"></span><span class="t3-qa-row__label">Kırık Link</span><span class="t3-qa-row__value" id="t3-qa-links"><span class="tag">Bekleniyor</span></span></div>
              </div>
            </div>
          </div>
        </div>

        <div class="t3-results-tabs toggle-group mt-12 hidden t3-post-audit" id="t3-results-tab-toggle" role="tablist" aria-label="Teknik SEO sonuç sekmeleri">
          <button type="button" class="toggle-btn active" data-result-tab="findings" role="tab" aria-selected="true">Bulgular</button>
          <button type="button" class="toggle-btn" data-result-tab="solutions" role="tab" aria-selected="false">Düzeltilmiş Çıktılar</button>
          <button type="button" class="toggle-btn" data-result-tab="pagespeed" role="tab" aria-selected="false">PageSpeed</button>
        </div>

        <div class="t3-results-panel" id="t3-results-panel-findings" data-panel="findings">
        <div class="card hidden" id="t3-findings-card">
          <div class="card__head">
            <div class="card__title">Önceliklendirilmiş Teknik SEO Bulguları</div>
            <span class="small muted" id="t3-findings-subtitle">önce önem derecesi (yüksek → orta → düşük), sonra aynı derece içinde etkilenen sayfa oranı × güven seviyesine göre sıralanmıştır</span>
          </div>
          <div class="mt-16" id="t3-findings-body"></div>
        </div>
        </div>

        <div class="t3-results-panel hidden" id="t3-results-panel-solutions" data-panel="solutions">
        <div class="card mt-20 hidden" id="t3-solutions-card">
          <div class="card__head"><div><div class="card__title">Hazır Çıktılar</div><div class="small muted">Önizleyin, kopyalayın veya dosya olarak indirin. Hiçbir değişiklik siteye otomatik uygulanmaz.</div></div></div>
          <div class="mt-16" id="t3-solutions-body"></div>
        </div>

        </div>

        <div class="t3-results-panel hidden" id="t3-results-panel-pagespeed" data-panel="pagespeed">
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
          <div class="t3-psi-details mt-20" id="t3-psi-details"></div>
        </div>

        </div>

        </div><!-- /#t3-live-view -->

        <div id="t3-history-view" class="hidden">
          <div class="card">
            <div class="card__head">
              <div class="card__title">Skor Geçmişi ve Karşılaştırma</div>
              <span class="small muted">Kaydedilmiş denetimleri URL'ye ya da müşteriye göre görüntüle</span>
            </div>
            <div class="toggle-group" id="t3-history-mode-toggle">
              <button type="button" class="toggle-btn active" data-mode="url">URL ile Ara</button>
              <button type="button" class="toggle-btn" data-mode="client">Müşteriye Göre</button>
            </div>
            <div class="flex gap-8 mt-16" id="t3-history-url-lookup" style="align-items:center; flex-wrap:wrap;">
              <input class="input" id="t3-history-lookup-url" type="text" placeholder="https://www.musterisitesi.com" style="flex:1; min-width:240px;">
              <button class="btn btn--primary btn--sm" id="t3-history-lookup-url-btn">Göster</button>
            </div>
            <div class="flex gap-8 mt-16 hidden" id="t3-history-client-lookup" style="align-items:center; flex-wrap:wrap;">
              <div class="search-select" id="t3-history-lookup-client-searchselect" style="position:relative; flex:1; min-width:240px;">
                <input type="text" class="input" id="t3-history-lookup-client-select-input" placeholder="Müşteri ara..." autocomplete="off" style="width:100%;">
                <input type="hidden" id="t3-history-lookup-client-select" value="">
                <div class="search-select__list hidden" id="t3-history-lookup-client-select-list"></div>
              </div>
              <button class="btn btn--primary btn--sm" id="t3-history-lookup-client-btn">Göster</button>
            </div>
          </div>

          <div class="card mt-20 hidden" id="t3-history-card">
            <div class="card__head">
              <div class="card__title" id="t3-history-result-title">Skor Geçmişi</div>
              <button class="btn btn--danger btn--sm hidden" id="t3-history-delete-btn">Geçmişi Temizle</button>
            </div>
            <div class="mt-16" id="t3-history-chart-wrap"></div>
            <div class="mt-16" id="t3-history-category-tables"></div>
          </div>

          <!-- "Tum Denetim Gecmisi" - varsayilan kapali acilir/kapanir
               bolum. Kayitli TUM denetimleri musteri/domain'e gore
               gruplayip listeler (bkz. js/technical-seo.js
               initAuditLogSection). Satira tiklaninca yukaridaki mevcut
               #t3-history-card grafigi o musteri/domain icin yeniden
               doldurulur - ayri bir grafik/detay alani EKLEMIYORUZ. -->
          <div class="card mt-20 t3-audit-log" id="t3-audit-log-card">
            <button type="button" class="t3-audit-log__header" id="t3-audit-log-toggle" aria-expanded="false">
              <span class="t3-audit-log__title">Tüm Denetim Geçmişi <span class="t3-audit-log__count" id="t3-audit-log-count">(0)</span></span>
              <svg class="t3-audit-log__chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="t3-audit-log__body hidden" id="t3-audit-log-body">
              <div class="field mt-16" style="margin-bottom:0;">
                <input class="input" id="t3-audit-log-search" type="text" placeholder="Müşteri adı veya alan adına göre ara...">
              </div>
              <div class="mt-16" id="t3-audit-log-list">
                <p class="small muted t3-audit-log__empty">Yükleniyor…</p>
              </div>
              <div class="mt-16 t3-audit-log__more-wrap">
                <button type="button" class="finding-card__toggle-btn hidden" id="t3-audit-log-more">Daha fazla göster</button>
              </div>
            </div>
          </div>
        </div><!-- /#t3-history-view -->

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
      
<?php include __DIR__ . '/ai_seo/views/tab_ai_seo.php'; ?>
<!-- YENİ 4. SEKME: YAPILACAKLAR / EKSİKLİKLER -->
        <section class="tab-panel" id="tab-4">
          <div class="card" style="margin-bottom: 20px;">
            <div class="card__head">
              <div class="card__title has-tooltip" style="display:inline-flex; align-items:center;" data-tooltip="AI tarafından tespit edilen tüm eksiklikleri tek bir listede takip edin.">Yapılacaklar & Eksiklikler</div>
              <button class="btn btn--ghost btn--sm has-tooltip" data-tooltip="Yapılacaklar listesini kalıcı olarak siler." id="btn-clear-todos">Listeyi Temizle</button>
            </div>
            <div class="card__hint">AI SEO Hizmetleri (3. Sekme) kapsamında tespit edilen görev ve öneriler burada toplanır.</div>
            
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
      <h2 style="margin:0; font-size:22px; display:flex; align-items:center; gap:8px;">Rakip AI GEO Kıyaslaması</h2>
      <button id="battle-close" style="background:none; border:none; font-size:28px; cursor:pointer; color:#64748b;">&times;</button>
    </div>
    <div style="display:flex; gap:12px; margin-bottom:16px;">
      <input type="url" id="battle-target-url" class="input" placeholder="Sizin URL'niz (Örn: https://adresgezgini.com/)" style="flex:1; border:2px solid #e2e8f0; padding:12px; border-radius:8px;">
      <div style="background: #dc2626; color: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 14px; box-shadow: 0 4px 10px rgba(220,38,38,0.3); flex-shrink: 0; margin-top: 2px;">VS</div>
      <input type="url" id="battle-comp-url" class="input" placeholder="Rakip URL (Örn: https://reklamvermek.com/)" style="flex:1; border:2px solid #e2e8f0; padding:12px; border-radius:8px;">
      <button id="battle-start-btn" class="btn btn--primary" style="background:#dc2626; color:white; border:none; padding:0 24px; font-weight:bold; border-radius:8px; font-size:15px;">Karşılaştır</button>
    </div>
    <div id="battle-results" class="chat-content" style="flex:1; overflow-y:auto; background:#f8fafc; border-radius:8px; padding:24px; border:1px solid #e2e8f0;">
       <div style="color:#64748b; text-align:center; margin-top:80px; font-size:16px;">
         <div style="font-size:48px; margin-bottom:16px;"></div>
         Hedef ve rakip URL'yi girip <strong>Karşılaştır</strong>'a tıklayın.<br>Yapay zeka SGE ve GEO standartlarına göre iki siteyi kıyaslayıp acil strateji çıkaracaktır.
       </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="js/technical-seo.js?v=<?php echo time(); ?>.0"></script>

<script src="js/app.js?v=<?php echo time(); ?>"></script>
<script src="js/welcome.js"></script>
<!-- Text SEO Scripts -->
<script src="src/TextSeo/js/text-seo-pdf.js"></script>
<script src="src/TextSeo/js/text-seo.js"></script>

<script>
// FORCE UI UPDATES TO BYPASS ANY APP.JS CONFLICTS
document.addEventListener('DOMContentLoaded', () => {
    
    // 1. URL Input Listener
    const sendUrlBtn = document.getElementById('welcome-send-url-btn');
    if (sendUrlBtn) {
        sendUrlBtn.addEventListener('click', () => {
            const val = document.getElementById('welcome-main-url-input').value.trim();
            const headerName = document.getElementById('top-header-client-name');
            if (typeof window.appState === 'undefined') return;

            // 1. Sync to all inputs
            const urlInputs = [
                document.getElementById('aiseo-url-input'),
                document.getElementById('urlInput'),
                document.getElementById('t3-url'),
                document.getElementById('t8-url'),
                document.getElementById('welcome-main-url-input')
            ];
            
            urlInputs.forEach(input => {
                if (input) {
                    input.value = val;
                }
            });

            // 2. Update Header
            if (!headerName) return;
            if (!val) {
                const clientSelect = document.getElementById('client-select');
                if (clientSelect && clientSelect.value && window.appState.currentClient) {
                    headerName.textContent = window.appState.currentClient.name;
                } else {
                    headerName.textContent = 'Bekleniyor...';
                }
                
                // Show toast and hide welcome screen
                if (typeof showToast !== 'undefined') showToast('URL kutuları temizlendi.', 'info');
                document.getElementById('welcome-overlay').style.opacity = '0';
                setTimeout(() => document.getElementById('welcome-overlay').style.display = 'none', 400);
                return;
            }

            let matchedClient = null;
            if (window.appState.clients) {
                 matchedClient = window.appState.clients.find(c => {
                   if (!c.domain_url) return false;
                   let domain1 = c.domain_url.replace(/^https?:\/\//, '').replace(/\/$/, '').toLowerCase();
                   let domain2 = val.replace(/^https?:\/\//, '').replace(/\/$/, '').toLowerCase();
                   return domain2.startsWith(domain1) || domain1.startsWith(domain2);
                 });
            }
            
            if (matchedClient) {
                headerName.textContent = 'Eşleşti: ' + matchedClient.name;
            } else {
                headerName.textContent = val;
            }
            
            if (typeof showToast !== 'undefined') showToast('URL tüm sekmelere başarıyla gönderildi!', 'success');
            
            // Hide welcome screen
            document.getElementById('welcome-overlay').style.opacity = '0';
            setTimeout(() => document.getElementById('welcome-overlay').style.display = 'none', 400);
        });
    }

    const clientSelect = document.getElementById('client-select');
    if (clientSelect) {
        clientSelect.addEventListener('change', (e) => {
            setTimeout(() => { 
                if (typeof window.appState === 'undefined') return;
                
                const headerName = document.getElementById('top-header-client-name');
                if (window.appState.currentClient) {
                    if (headerName) headerName.textContent = window.appState.currentClient.name;
                    
                    const urlInputs = [
                        document.getElementById('aiseo-url-input'),
                        document.getElementById('urlInput'),
                        document.getElementById('t3-url'),
                        document.getElementById('t8-url'),
                        document.getElementById('welcome-main-url-input')
                    ];
                    
                    urlInputs.forEach(input => {
                        if (input && window.appState.currentClient.domain_url) {
                            input.value = window.appState.currentClient.domain_url;
                        }
                    });
                } else {
                    if (headerName) headerName.textContent = 'Bekleniyor...';
                    const urlInputs = [
                        document.getElementById('aiseo-url-input'),
                        document.getElementById('urlInput'),
                        document.getElementById('t3-url'),
                        document.getElementById('t8-url'),
                        document.getElementById('welcome-main-url-input')
                    ];
                    urlInputs.forEach(input => {
                        if (input) input.value = '';
                    });
                }
            }, 100); 
        });
    }
});
</script>
</body>
</html>
