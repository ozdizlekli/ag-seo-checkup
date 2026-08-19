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
<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script src="js/keyword-engine.js"></script>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="app">
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
    </ul>

    <div class="sidebar__footer">
      
      <a href="logout.php" class="btn btn--sidebar btn--sm" style="text-decoration:none; text-align:center; display:block;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        Çıkış Yap
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

        <!-- ANAHTAR KELİME ARAŞTIRMASI (Tab 2'den Taşındı) -->
        <div class="card" style="margin-bottom: 20px;">
          <div class="card__title">Anahtar Kelime Araştırması (Gerçek Google Verisi)</div>
          <div class="card__hint">Canlı Google Autocomplete önerilerinden yararlanarak %100 matematiksel Fırsat Skoru hesaplar. İçerik oluşturmadan önce kelimenizi analiz edin.</div>
          <div class="flex gap-12 mt-16" style="align-items:flex-end;">
            <div class="field" style="flex:1; margin-bottom:0;">
              <label for="t2-seed">Tohum (Kök) Kelime</label>
              <input class="input" id="t2-seed" type="text" placeholder="örn. seo ajansı, diş hekimi">
            </div>
            <button class="btn btn--primary" id="t2-cluster-btn">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <span id="t2-cluster-label">Kelimeleri Topla</span>
            </button>
          </div>
        </div>

        <div class="card mt-20 hidden" id="t2-output-card" style="margin-bottom: 20px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div>
              <div class="card__title">Keşfedilen Fırsatlar</div>
              <div class="card__hint">Gerçekleşen sorgulardan oluşturulmuş ve skorlanmış sonuçlar.</div>
            </div>
            <button class="btn btn--ghost btn--sm" id="t2-save-keywords-btn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              Seçilenleri Kaydet
            </button>
          </div>

          <div class="tabs mt-16" style="display:flex; gap:8px; border-bottom:1px solid var(--border); padding-bottom:10px;">
            <button class="btn btn--ghost btn--sm active" id="btn-tab-questions" style="border-radius:20px;">Sorular (<span id="count-questions">0</span>)</button>
            <button class="btn btn--ghost btn--sm" id="btn-tab-similar" style="border-radius:20px;">Benzerler (<span id="count-similar">0</span>)</button>
            <button class="btn btn--ghost btn--sm" id="btn-tab-related" style="border-radius:20px;">İlgili (<span id="count-related">0</span>)</button>
            <button class="btn btn--ghost btn--sm" id="btn-tab-low_volume" style="border-radius:20px;">Uzun (<span id="count-low_volume">0</span>)</button>
          </div>

          <div class="table-wrap mt-16" style="max-height: 400px; overflow-y: auto;">
            <table class="table" style="width: 100%; text-align: left; border-collapse: collapse;">
              <thead>
                <tr style="border-bottom: 1px solid var(--border); font-size:11px; color:var(--muted-2); text-transform:uppercase;">
                  <th style="padding:10px 8px; width:30px;"><input type="checkbox" id="t2-select-all"></th>
                  <th style="padding:10px 8px;">Kelime</th>
                  <th style="padding:10px 8px;">Fırsat</th>
                  <th style="padding:10px 8px;">Hacim</th>
                  <th style="padding:10px 8px;">Zorluk</th>
                  <th style="padding:10px 8px;">TBM</th>
                  <th style="padding:10px 8px;">Niyet</th>
                  <th style="padding:10px 8px; width:44px;"></th>
                </tr>
              </thead>
              <tbody id="t2-results-tbody"></tbody>
            </table>
          </div>
        </div>

        
        <!-- İçerik Türü Seçici -->
        <div class="card">
          <div class="card__head">
            <div class="card__title">İçerik Türü</div>
          </div>
          <div class="pill-group">
            <input type="radio" name="contentType" id="ct-blog" value="blog" checked>
            <label for="ct-blog">Blog / Makale</label>
            <input type="radio" name="contentType" id="ct-ecommerce" value="ecommerce">
            <label for="ct-ecommerce">E-Ticaret / Ürün</label>
            <input type="radio" name="contentType" id="ct-service" value="service">
            <label for="ct-service">Hizmet (Kurumsal)</label>
            <input type="radio" name="contentType" id="ct-portfolio" value="portfolio">
            <label for="ct-portfolio">Portfolyo / Proje</label>
          </div>
        </div>

        <!-- URL'den Veri Çekme Alanı -->
        <div class="card mt-20">
          <div class="card__title">İçerik Kaynağı (Canlı Sayfa Optimizasyonu)</div>
          <div class="flex gap-12 mt-16" style="align-items:flex-end;">
            <div class="field" style="flex:1; margin-bottom:0;">
              <label for="t1-fetch-url">Sayfa Yayındaysa URL'sini Girin</label>
              <input class="input" id="t1-fetch-url" type="text" placeholder="https://www.musterisitesi.com/hizmetlerimiz">
            </div>
            <button class="btn btn--dark" id="t1-fetch-btn">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              <span id="t1-fetch-label">Mevcut Verileri Çek</span>
            </button>
          </div>
          <p class="field-note mt-8">Sistem, girdiğiniz sayfanın başlığını, meta açıklamasını ve ana metnini otomatik okuyarak aşağıdaki formlara doldurur. Sonrasında istediğiniz gibi düzenleyip AI ile iyileştirebilirsiniz.</p>
        </div>

        <div class="grid grid-2 mt-20">
          <div class="card">
            <div class="card__title">Temel SEO Bilgileri</div>
            <div class="field mt-16">
              <label for="t1-keyword">Hedef Kelime</label>
              <input class="input" id="t1-keyword" type="text" placeholder="örn. kurumsal seo danışmanlığı">
            </div>
            <div class="field">
              <label for="t1-title">Title (Sayfa Başlığı)</label>
              <input class="input" id="t1-title" type="text" placeholder="örn. Kurumsal SEO Danışmanlığı | Ajans Adı">
            </div>
            <div class="field">
              <label for="t1-meta">Meta Açıklama</label>
              <textarea class="input" id="t1-meta" placeholder="150-160 karakter aralığında meta açıklama yazın..."></textarea>
            </div>
            
            <!-- Çapraz Linkleme Alanı (Güncellendi) -->
            <div class="field">
              <label for="t1-related-urls">İlişkili İçerik / Hedef URL'ler</label>
              <p class="field-note mb-8">Metin içinden yönlendirme yapmak istediğiniz ürün, hizmet, blog veya portfolyo sayfalarının linkleri.</p>
              <textarea class="input" id="t1-related-urls" placeholder="https://site.com/urun/kredi-karti-pos&#10;https://site.com/blog/sanal-pos-nedir"></textarea>
            </div>

            <!-- Dinamik Alanlar (Seçime Göre Değişir) -->
            <div id="dynamic-fields-wrapper" style="margin-top:16px; padding-top:16px; border-top:1px dashed var(--border);">
              
              <!-- Blog -->
              <div id="df-blog">
                <div class="grid grid-2" style="gap:12px;">
                  <div class="field"><label>Okuma Süresi</label><input type="text" class="input" id="df-blog-time" placeholder="Örn: 5 dk"></div>
                  <div class="field"><label>Kaynaklar / Atıflar</label><input type="text" class="input" id="df-blog-refs" placeholder="Referans linkleri"></div>
                </div>
              </div>
              
              <!-- E-Ticaret -->
              <div id="df-ecommerce" class="hidden">
                <div class="grid grid-2" style="gap:12px;">
                  <div class="field"><label>Stok Kodu (SKU)</label><input type="text" class="input" id="df-eco-sku" placeholder="Örn: PRD-1029"></div>
                  <div class="field"><label>Teknik Döküman (PDF)</label><input type="text" class="input" id="df-eco-doc" placeholder="Kılavuz URL'si"></div>
                </div>
              </div>
              
              <!-- Hizmet -->
              <div id="df-service" class="hidden">
                <div class="field"><label>Hedef CTA Bağlantısı</label><input type="text" class="input" id="df-srv-cta" placeholder="Örn: /teklif-al"></div>
                <div class="field"><label>Hizmete Özel SSS</label><textarea class="input" id="df-srv-faq" placeholder="Soru: ... Cevap: ..."></textarea></div>
              </div>
              
              <!-- Portfolyo -->
              <div id="df-portfolio" class="hidden">
                <div class="grid grid-2" style="gap:12px;">
                  <div class="field"><label>Tech Stack</label><input type="text" class="input" id="df-prt-tech" placeholder="Örn: React, Node.js"></div>
                  <div class="field"><label>Kazanım / Metrik</label><input type="text" class="input" id="df-prt-metrics" placeholder="Örn: %40 hız artışı"></div>
                </div>
                <div class="field"><label>Canlı Demo / Repo URL</label><input type="text" class="input" id="df-prt-demo" placeholder="Proje bağlantısı"></div>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card__title">İçerik Metni</div>
            <div class="field mt-16">
              <label for="t1-content">Mevcut / Taslak İçerik</label>
              <textarea class="input tall" id="t1-content" placeholder="Optimize edilecek içerik metnini buraya yapıştırın..."></textarea>
            </div>
            <div class="flex gap-12 mt-8" style="flex-wrap:wrap;">
              <button class="btn btn--primary" id="t1-improve-btn" style="flex:1; justify-content:center; min-width:200px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3m0 12v3m9-9h-3M6 12H3m14.5-6.5-2 2m-9 9-2 2m13-2-2-2m-9-9-2-2"/></svg>
                <span id="t1-improve-label">AI ile İyileştir</span>
              </button>
              
              
            </div>
          </div>
        </div>





        <!-- AI Çıktısı -->
        <div class="card mt-20 hidden" id="t1-output-card">
          <div class="card__head">
            <div class="card__title">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 6 9 17l-5-5"/></svg>
              AI Çıktısı
            </div>
            <div class="toggle-group">
              <button class="toggle-btn active" data-view="text" id="t1-toggle-text">Görsel Metin</button>
              <button class="toggle-btn" data-view="html" id="t1-toggle-html">Kaynak Kod (HTML)</button>
            </div>
          </div>
          <div id="t1-output-text" style="font-size:13.5px; line-height:1.7; white-space:pre-wrap; background:var(--paper); border-radius:8px; padding:16px;"></div>
          <div id="t1-output-html" class="code-block hidden"><code id="t1-output-html-code"></code></div>

          <div class="flex-between mt-16">
            <p class="small muted">Sonuçlardan memnun kaldıysanız arşive kaydedip müşteri geçmişinde saklayabilirsiniz.</p>
            <button class="btn btn--dark btn--sm" id="t1-save-archive">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              Arşive Kaydet
            </button>
          </div>
        </div>

        <div class="card mt-20">
          <div class="card__title">Sürüm Geçmişi</div>
          <div class="card__hint">Kaydedilen her iyileştirme burada eski/yeni metin karşılaştırmasıyla listelenir. (Supabase — <code>content_history</code> tablosu)</div>
          <div class="mt-16" id="t1-archive-list">
            <p class="empty-note">Henüz arşivlenmiş bir kayıt yok.</p>
          </div>
        </div>
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

        <div class="card mt-20" id="t3-output-card">
          <div class="card__head">
            <div class="card__title">Lighthouse & PageSpeed Denetim Sonuçları</div>
            <span class="small muted" id="t3-audit-url">Henüz bir tarama yapılmadı...</span>
          </div>
          
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
            <div class="card__title">Hızlı Teknik Denetim (İstemci Taraflı)</div>
            <span class="small muted">robots.txt, sitemap.xml ve SSL durumu</span>
          </div>
          <div class="table-wrap mt-16">
            <table class="table" style="width: 100%; text-align: left; border-collapse: collapse;">
              <tbody>
                <tr style="border-bottom: 1px solid var(--border-soft);">
                  <td style="padding:12px; font-weight:600; width:150px;">SSL (HTTPS)</td>
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
              </tbody>
            </table>
          </div>
        </div>

        </section>

      <!-- ==========================================================
           TAB 4 — SCHEMA ÜRETİCİ
      =========================================================== -->
      
<section class="tab-panel" id="tab-3">
        <!-- MASTER AI SEO ANALİZİ (TEK TUŞ) -->
        <div class="card" style="position:relative; border-top: 4px solid var(--accent);">
          <div class="card__title">Kapsamlı AI SEO Analizi (Domain Seviyesi)</div>
          <p class="card__hint">Hedef sitenin yapay zeka botları (ChatGPT, Claude, Gemini vb.) tarafından nasıl algılandığını, <strong>llms.txt</strong> dosyasının varlığını, içerik güven skorunu ve rakip öngörülerini tek tıkla analiz edin.</p>
          
          <div class="grid grid-2 mt-16" style="gap:16px;">
            <div class="field" style="margin-bottom:0;">
              <label for="ai-domain">Web Sitesi Domaini</label>
              <input class="input" id="ai-domain" type="text" placeholder="https://www.adresgezgini.com">
            </div>
            <div class="field" style="margin-bottom:0;">
              <label>Sektör / Site Türü</label>
              <div class="pill-group" style="margin-top:8px;">
                <input type="radio" name="aiSiteType" id="ai-type-service" value="service" checked>
                <label for="ai-type-service">Hizmet / Kurumsal</label>
                <input type="radio" name="aiSiteType" id="ai-type-product" value="product">
                <label for="ai-type-product">Ürün / E-Ticaret</label>
              </div>
            </div>
          </div>

          <button class="btn btn--primary mt-16" id="ai-master-btn" style="width: 100%; justify-content:center; padding:12px; font-size:15px; font-weight: 600;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            Tek Tuşla Kapsamlı AI SEO Raporu Çıkar
          </button>
        </div>

        <!-- ÖRNEK RAPOR ALANI -->
        <div class="card mt-20 hidden" id="ai-report-card" style="padding: 40px; background: #fff;">
          
          <div style="border-bottom: 2px solid #eaeaea; padding-bottom: 16px; margin-bottom: 30px; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h2 style="font-size: 24px; color: #333; margin: 0; font-weight: 600;">Yapay Zeka İçerik Analizi <span style="font-size:12px; background:#eef2ff; color:#4f46e5; padding:4px 8px; border-radius:4px; margin-left:8px;">AI Content Analysis</span></h2>
                <p style="color: #888; font-size: 14px; margin-top: 4px;">Büyük Dil Modellerinin (LLM'lerin) alan adınızın içeriği hakkında bildikleri</p>
            </div>
            <div id="llms-txt-status" style="text-align:right;">
                <div style="font-size:12px; color:#999; text-transform:uppercase; font-weight:600;">llms.txt Kontrolü</div>
                <div style="color:#10b981; font-weight:bold; display:flex; align-items:center; gap:4px; justify-content:flex-end;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    Bulundu (Geçerli GEO)
                </div>
            </div>
          </div>

          <!-- Domain İş Bağlamı -->
          <div style="border: 1px solid #eaeaea; border-radius: 8px; margin-bottom: 30px;">
             <div style="background: #fdfdfd; padding: 16px 20px; border-bottom: 1px solid #eaeaea; border-radius: 8px 8px 0 0;">
                <h3 style="font-size: 16px; color: #333; margin: 0; font-weight: 600;">Alan Adı İş Bağlamı (Domain Business Context)</h3>
                <p style="color: #888; font-size: 13px; margin: 4px 0 0 0;">LLM'lerin alan adınızın profili, hedef kitlesi ve sektör nişi hakkında bildikleri</p>
             </div>
             <div style="padding: 20px;">
               <div style="margin-bottom: 24px;">
                   <h4 style="font-size: 13px; color: #999; text-transform: uppercase; margin: 0 0 8px 0; font-weight: 600;">BU ALAN ADI NE HAKKINDADIR (WHAT IS THIS DOMAIN ABOUT)</h4>
                   <div style="font-size: 14px; line-height: 1.6; color: #444;" id="rep-about">AdresGezgini; Google Ads, Facebook Reklamları, Instagram Reklamları ve web tasarımı da dahil olmak üzere çevrim içi reklamcılık hizmetlerinde uzmanlaşmış bir dijital pazarlama ajansıdır. Google Ads Premier Partneri olan şirket; arama ağı, mobil, video, görüntülü reklamlar, alışveriş reklamları, Google Analytics IQ, mobil siteler ve dijital satış konularında uzmanlık sunmaktadır.</div>
               </div>
               <div style="margin-bottom: 24px;">
                   <h4 style="font-size: 13px; color: #999; text-transform: uppercase; margin: 0 0 8px 0; font-weight: 600;">HEDEF KİTLE (TARGET AUDIENCE)</h4>
                   <div style="font-size: 14px; line-height: 1.6; color: #444;" id="rep-target">İçerik; hedeflenmiş reklamcılık ve web geliştirme hizmetleri aracılığıyla çevrim içi varlıklarını güçlendirmek isteyen işletmeler için hazırlanmıştır. Bu işletmeler, daha geniş bir kitleye ulaşmak, marka bilinirliğini artırmak ve satışları yükseltmek için dijital platformlardan yararlanmayı hedeflemektedir.</div>
               </div>
               <div>
                   <h4 style="font-size: 13px; color: #999; text-transform: uppercase; margin: 0 0 8px 0; font-weight: 600;">SEKTÖR NİŞİ (INDUSTRY NICHE)</h4>
                   <div style="font-size: 14px; line-height: 1.6; color: #444;" id="rep-niche">Web sitesi, işletmelere ürün ve hizmetlerini çevrim içi olarak tanıtmaları için stratejiler ve araçlar sağlamaya odaklanan Dijital Pazarlama ve Çevrim İçi Reklamcılık sektör nişinde faaliyet göstermektedir.</div>
               </div>
             </div>
          </div>

          <!-- Genel Bakış -->
          <div style="margin-bottom: 40px;">
             <h4 style="font-size: 13px; color: #999; text-transform: uppercase; margin: 0 0 8px 0; font-weight: 600;">GENEL BAKIŞ (OVERVIEW)</h4>
             <p style="font-size: 14px; line-height: 1.6; color: #444; margin-bottom: 20px;">AdresGezgini, yerelleştirilmiş hizmet teklifleri ve pratik örneklerdeki belirgin güçlü yönleriyle, Türkiye'deki işletmeler için Google Ads ve sosyal medya reklamcılığı konusunda güçlü bir uzmanlık sergilemektedir. Bununla birlikte site; özellikle analitik ve dönüşüm optimizasyonu konularında daha derinlemesine eğitici içeriklerden fayda sağlayabilir.</p>
             
             <div style="background: #f4f9fc; border: 1px solid #bce0f9; border-radius: 8px; padding: 16px; margin-bottom: 30px; display: flex; gap: 12px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#3b82f6" stroke="#fff" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                <p style="margin:0; font-size: 13.5px; line-height: 1.5; color: #333;"><strong>İçerik Etkililik Analizi</strong>, web sitesi içeriğinin kendi nişi dahilindeki hedef kitlesinin temel sorularını ve ihtiyaçlarını ne kadar iyi karşıladığını ölçer.</p>
             </div>

             <h4 style="font-size: 13px; color: #999; text-transform: uppercase; margin: 0 0 16px 0; font-weight: 600;">ÖNE ÇIKAN KULLANICI SORULARI (TOP USER QUESTIONS)</h4>
             <div style="display: flex; flex-direction: column; gap: 24px;" id="rep-questions">
                <!-- S1 -->
                <div>
                   <div style="font-weight:600; font-size:14px; color:#222;">S1: Türkiye pazarına özel, etkili bir Google Ads kampanyasını nasıl oluşturabilirim?</div>
                   <ul style="font-size:13.5px; color:#555; margin:8px 0; padding-left:20px;">
                      <li>Hizmet sayfaları ve vaka analizleri ile vurgulanan kapsamlı uzmanlık.</li>
                      <li>Pratik uygulamayı gösteren yerelleştirilmiş içerik.</li>
                   </ul>
                   <div style="font-weight:bold; color:#10b981; font-size:13px;">Puan: 9/10</div>
                </div>
                <!-- S2 -->
                <div>
                   <div style="font-weight:600; font-size:14px; color:#222;">S2: Instagram ve Facebook'ta marka bilinirliğini artırmak için en iyi stratejiler nelerdir?</div>
                   <ul style="font-size:13.5px; color:#555; margin:8px 0; padding-left:20px;">
                      <li>Sosyal medya stratejilerinin kapsamlı bir şekilde ele alınması.</li>
                      <li>İleri düzey geliştirme konusunda sınırlı sayıda kaynak.</li>
                   </ul>
                   <div style="font-weight:bold; color:#10b981; font-size:13px;">Puan: 8/10</div>
                </div>
                <!-- S3 -->
                <div>
                   <div style="font-weight:600; font-size:14px; color:#222;">S3: Dijital reklam kampanyalarımın yatırım getirisini (ROI) nasıl ölçebilirim?</div>
                   <ul style="font-size:13.5px; color:#555; margin:8px 0; padding-left:20px;">
                      <li>Google Analytics IQ ve performansa yapılan vurgu.</li>
                      <li>Adım adım eğitimlerin eksikliği.</li>
                   </ul>
                   <div style="font-weight:bold; color:#f59e0b; font-size:13px;">Puan: 7/10</div>
                </div>
             </div>
          </div>

          <!-- Fırsatlar & Rakipler -->
          <div class="grid grid-2" style="gap:20px; margin-bottom: 40px;">
              <div style="border: 1px solid #eaeaea; border-radius: 8px; padding: 20px;">
                 <h4 style="font-size: 13px; color: #999; text-transform: uppercase; margin: 0 0 16px 0; font-weight: 600;">İÇERİK FIRSATLARI</h4>
                 <ul style="font-size:13.5px; line-height:1.6; color:#444; padding-left:20px; margin:0;" id="rep-opps">
                    <li><strong>Derinlemesine Kılavuzlar:</strong> ROI iyileştirme üzerine kapsamlı kılavuzlar oluşturun.</li>
                    <li><strong>Vaka Analizleri:</strong> Ölçülebilir sonuçları olan başarı hikayelerini genişletin.</li>
                    <li><strong>İndirilebilir Kaynaklar:</strong> Kurumsal müşteriler için e-kitaplar sağlayın.</li>
                    <li><strong>Dönüşüm Optimizasyonu:</strong> Web tasarımı ve dönüşüm stratejilerine odaklanan bloglar.</li>
                 </ul>
              </div>
              <div style="border: 1px solid #eaeaea; border-radius: 8px; padding: 20px; background:#fcfcfc;">
                 <h4 style="font-size: 13px; color: #999; text-transform: uppercase; margin: 0 0 16px 0; font-weight: 600;">RAKİP İÇERİK ÖNGÖRÜLERİ</h4>
                 <p style="font-size:13.5px; line-height:1.6; color:#444; margin:0;">AdresGezgini, yerelleştirilmiş hizmetlerde rakiplerine kıyasla güçlü. Ancak rakiplerin bazıları analitik ve dönüşüm optimizasyonu konularında daha derinlemesine eğitici video ve PDF'ler sunarak Trust (Güven) skorunu artırıyor.</p>
              </div>
          </div>

          <!-- AI Content Trust -->
          <div style="background: #111827; border-radius: 8px; padding: 30px; color: #fff;">
             <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid #374151; padding-bottom: 20px; margin-bottom: 20px;">
                 <div>
                     <h3 style="font-size: 18px; margin: 0; font-weight: 600;">Yapay Zeka İçerik Güveni (AI Content Trust)</h3>
                     <p style="color: #9ca3af; font-size: 13px; margin: 4px 0 0 0;">LLM'ler tarafından içeriğinizin güvenilirlik algısı</p>
                 </div>
                 <div style="text-align:right;">
                     <div style="font-size:32px; font-weight:bold; color:#10b981;">%88</div>
                     <div style="font-size:11px; color:#9ca3af; text-transform:uppercase;">Genel Güven Puanı</div>
                 </div>
             </div>
             <div class="grid grid-3" style="gap:20px; margin-bottom: 20px; text-align:center;">
                 <div style="background:rgba(255,255,255,0.05); padding:16px; border-radius:6px;">
                     <div style="font-size:20px; font-weight:bold; color:#fff;">%88</div>
                     <div style="font-size:12px; color:#9ca3af;">Konusal Uygunluk</div>
                 </div>
                 <div style="background:rgba(255,255,255,0.05); padding:16px; border-radius:6px;">
                     <div style="font-size:20px; font-weight:bold; color:#fff;">%85</div>
                     <div style="font-size:12px; color:#9ca3af;">Konu Uzmanlığı</div>
                 </div>
                 <div style="background:rgba(255,255,255,0.05); padding:16px; border-radius:6px;">
                     <div style="font-size:20px; font-weight:bold; color:#10b981;">%90</div>
                     <div style="font-size:12px; color:#9ca3af;">Güvenilirlik</div>
                 </div>
             </div>
             <p style="font-size:13px; line-height:1.6; color:#d1d5db; margin:0;">Genel güven puanı %88 olup; güçlü konu odağını, kanıtlanmış uzmanlığını ve yüksek güvenilirliğini yansıtmaktadır. Eğitici içeriğin geliştirilmesi güvenilirliğini daha da pekiştirecektir.</p>
          </div>
        </div>
        
        <script>
            document.getElementById("ai-master-btn").addEventListener("click", function() {
                var btn = this;
                var type = document.querySelector('input[name="aiSiteType"]:checked').value;
                btn.innerHTML = '<span class="spinner" style="width:14px;height:14px;margin-right:8px;display:inline-block;border:2px solid #fff;border-right-color:transparent;border-radius:50%;animation:spin 1s linear infinite;"></span> Analiz Ediliyor...';
                setTimeout(function() {
                    document.getElementById("ai-report-card").classList.remove("hidden");
                    btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Analiz Tamamlandı. Yeniden Çalıştır';
                    
                    if(type === "product") {
                        document.getElementById("rep-about").innerText = "E-Ticaret odaklı bir alan adı. Online perakende, ürün satışı ve dijital mağazacılık işlemleri yürütmektedir.";
                        document.getElementById("rep-target").innerText = "Doğrudan tüketiciler (B2C) ve belirli ürün kategorilerinde alışveriş yapmak isteyen internet kullanıcıları.";
                        document.getElementById("rep-niche").innerText = "E-Ticaret ve Perakende sektör nişinde faaliyet göstermektedir.";
                        document.getElementById("rep-questions").innerHTML = "<div><div style='font-weight:600; font-size:14px; color:#222;'>S1: Ürünlerin teslimat süreleri ve iade politikaları ne kadar şeffaf?</div><div style='font-weight:bold; color:#10b981; font-size:13px;'>Puan: 8/10</div></div>";
                        document.getElementById("rep-opps").innerHTML = "<li><strong>Ürün Açıklamaları:</strong> AI destekli SEO uyumlu ürün açıklamaları girin.</li><li><strong>Kategori SEO:</strong> Alt kategoriler için rehber niteliğinde yazılar hazırlayın.</li>";
                    } else {
                        // Reset to default service text (Adresgezgini example)
                        document.getElementById("rep-about").innerText = "AdresGezgini; Google Ads, Facebook Reklamları, Instagram Reklamları ve web tasarımı da dahil olmak üzere çevrim içi reklamcılık hizmetlerinde uzmanlaşmış bir dijital pazarlama ajansıdır.";
                        document.getElementById("rep-target").innerText = "İçerik; hedeflenmiş reklamcılık ve web geliştirme hizmetleri aracılığıyla çevrim içi varlıklarını güçlendirmek isteyen işletmeler için hazırlanmıştır.";
                        document.getElementById("rep-niche").innerText = "Web sitesi, işletmelere ürün ve hizmetlerini çevrim içi olarak tanıtmaları için stratejiler ve araçlar sağlamaya odaklanan Dijital Pazarlama ve Çevrim İçi Reklamcılık sektör nişinde faaliyet göstermektedir.";
                    }
                }, 1500);
            });
        </script>
        
        <br>
        <hr style="border:0; border-top:1px dashed var(--border); margin: 30px 0;">
        <br>
        <div class="card mt-20">
          <div class="card__title">Metin Yapay Zeka Analizi</div>
          <p class="card__hint">1. Sekmeye (Metin Bazlı SEO) yapıştırdığınız içerik metnini aşağıdaki AI modelleriyle değerlendirin.</p>
          <div class="flex gap-12 mt-16" style="flex-wrap:wrap;">
            <button class="btn btn--dark" id="t1-conversion-btn" style="flex:1; justify-content:center; min-width:200px;" title="Metni CTA gücü, güven sinyalleri ve satın alma niyetine göre puanlar">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span id="t1-conversion-label">Dönüşüm Skoru</span>
              </button>
            <button class="btn btn--dark" id="t1-sge-btn" style="flex:1; justify-content:center; min-width:200px;" title="İçeriğin Google AI Overviews (SGE) üzerinde kaynak gösterilme ihtimalini ölçer">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <span id="t1-sge-label">AI Overviews Uyum Analizi</span>
              </button>
          </div>
        </div>
                <!-- Dönüşüm / Satın Alma Niyeti Skoru -->
        <div class="card mt-20 hidden" id="t1-conversion-card">
          <div class="card__title">Satın Alma / Dönüşüm Niyeti Analizi</div>
          <div class="flex gap-12 mt-16" style="align-items:center;">
            <div style="font-family:Georgia,'Times New Roman',serif; font-size:34pt; font-weight:700;" id="t1-conversion-score">—</div>
            <div class="small muted" id="t1-conversion-status">/ 100</div>
          </div>
          <div class="sub-scores mt-12" id="t1-conversion-breakdown"></div>
          <div class="mt-12" id="t1-conversion-notes" style="font-size:13px; line-height:1.6; color:var(--ink-2);"></div>
        </div>
                <!-- AI Overviews (SGE) Uyumluluk Analizi -->
        <div class="card mt-20 hidden" id="t1-sge-card">
          <div class="card__title">Google AI Overviews (SGE) Uyum Analizi</div>
          <div class="flex gap-12 mt-16" style="align-items:center;">
            <div style="font-family:Georgia,'Times New Roman',serif; font-size:34pt; font-weight:700;" id="t1-sge-score">—</div>
            <div class="small muted" id="t1-sge-status">/ 100</div>
          </div>
          <div class="sub-scores mt-12" id="t1-sge-breakdown"></div>
          <div class="mt-12" id="t1-sge-notes" style="font-size:13px; line-height:1.6; color:var(--ink-2);"></div>
        </div>

                <div class="card mt-20" id="t3-schema-card">
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
          <div id="t3-schema-results" class="mt-16 hidden"></div>
        </div>

        <!-- SCHEMA ÜRETİCİ (Tab 4\'ten Taşındı) -->
        <div class="card mt-20">
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
    </div>
  </main>
</div>

<div id="toast-container"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="js/app.js?v=1.4"></script>
</body>
</html>
    </div>
</main>
</div>

<div id="toast-container"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="js/app.js?v=1.4"></script>
</body>
</html>