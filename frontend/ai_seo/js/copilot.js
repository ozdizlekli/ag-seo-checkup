/**
 * AdresGezgini — AI SEO Hizmetleri
 * URL gir → AI site türü tespit → 7 hizmet + özel hizmet ekle → accordion sonuçlar
 *
 * NOT — ASIL SORUN (kök neden) VE ÇÖZÜMÜ:
 * index.php şu script sırasına sahip:
 *   1) <script src="ai_seo/js/copilot.js">   (satır ~23, <head> içinde)
 *   2) <script src="js/app.js">              (satır ~640, body sonunda)
 * Modül olmayan (non-module) <script> etiketleri TEK BİR global scope'u
 * paylaşır. İki ayrı dosya da üst seviyede (top-level) AYNI isimlerle
 * tanım yapıyordu:
 *
 *   a) `const state = {...}`  → hem copilot.js hem de app.js'te var.
 *      Bir global scope'ta aynı isimle ikinci kez `const` (veya `let`)
 *      tanımlamak JS'te bir SyntaxError'dır ve bu hata o SCRIPT'İN
 *      TAMAMINI çalıştırmaz (o satırdan önceki kodlar bile çalışmaz).
 *      Yani copilot.js önce yüklenip `const state` tanımladığı için,
 *      SONRA yüklenen app.js kendi `const state` satırına gelince patlar
 *      ve app.js'in TÜMÜ (tab geçişleri dahil pek çok özellik) sessizce
 *      çalışmayı durdurabiliyordu (konsolda "Identifier 'state' has
 *      already been declared" hatası).
 *
 *   b) `async function callGemini(...)` → hem copilot.js hem de app.js'te
 *      var (farklı imzalarla). Fonksiyon tanımları const/let gibi hata
 *      vermez, sadece SONRA yüklenen SESSİZCE öncekini EZER. app.js'teki
 *      callGemini, var OLMAYAN bir endpoint'e (api_handler.php) istek
 *      atıyor ve 2. parametre olarak bir "mockResponseFactory" fonksiyonu
 *      bekliyor; copilot.js ise 2. parametre olarak bir sayı (temperature)
 *      gönderiyordu. Bu karışıklık yüzünden AI SEO istekleri (site türü
 *      tespiti ve her hizmet butonu) hatayla/sessizce başarısız oluyor,
 *      accordion hiç dolmuyordu.
 *
 * ÇÖZÜM: Bu dosyanın TÜM içeriği bir IIFE (Immediately Invoked Function
 * Expression) içine alınmıştır. Böylece `state`, `callGemini` ve diğer
 * tüm üst seviye tanımlar bu closure'a HAPSOLUR; window/global scope'a
 * hiç sızmaz. Artık app.js ile (veya ileride eklenecek başka bir script
 * ile) isim çakışması imkansız hale gelir. Dışarıdan çağrılması gereken
 * fonksiyonlar (runSingleService, toggleAccordion, runAllServices ve eski
 * uyumluluk stub'ları) zaten kod içinde açıkça `window.xxx = ...` şeklinde
 * atanıyor; IIFE'ye almak bunların dışarıdan erişilebilir olmasını
 * etkilemez, sadece iç değişkenleri izole eder.
 */
(function () {
  'use strict';
  
  // ============================================================
  // 7 SABİT HİZMET
  // ============================================================
  const AI_SEO_SERVICES = [
    {
      id: 1,
      icon: '🏗️',
      title: 'Yapay Zeka Uyumlu Site Yapısı',
      description: 'Site yapısının, hizmet ve işletme bilgilerinin yapay zeka sistemleri tarafından anlaşılır hale getirilmesi.',
      buildPrompt(d) {
        return `Sen bir AdresGezgini AI SEO uzmanısın. "${d.url}" sitesi için YAPAY ZEKA UYUMLU SİTE YAPISI analizi yap.
  
  Site: ${d.title} | ${d.description}
  Tür: ${d.siteType}
  Metin: ${d.text.substring(0, 3000)}
  Schema'lar: ${d.schemas.join(', ') || 'Yok'}
  
  Türkçe, Markdown başlıkları kullanarak aşağıdakileri analiz et:
  
  ## 1. Genel Şema ve Site Kimliği
  Organization/WebSite/LocalBusiness şemalarının durumu. AI sistemlerin siteyi doğru tanıması için eksik bilgiler.
  
  ## 2. ${d.siteType.includes('Ürün') ? 'Ürün (Product)' : 'Hizmet (Service)'} Detay Şeması
  ${d.siteType.includes('Ürün') ? 'Product şeması: name, description, image, price, availability, brand eksiklikleri ve JSON-LD örneği.' : 'Service şeması: name, description, provider, areaServed, serviceType eksiklikleri ve JSON-LD örneği.'}
  
  ## 3. Breadcrumb ve Sayfa Hiyerarşisi
  URL yapısı, H1-H6 tutarlılığı, BreadcrumbList şeması durumu.
  
  ## 4. Sayfa Sayfa İçerik Değerlendirmesi
  ${d.siteType.includes('Ürün') ? 'Ana sayfa, kategori, ürün, hakkımızda, iletişim' : 'Ana sayfa, hizmet sayfaları, hakkımızda, iletişim, blog'} için ayrı ayrı değerlendirme (4-5 sayfa).
  
  ## 5. Taranabilirlik ve Linkleme
  robots.txt, sitemap.xml, canonical, HTTP/HTTPS tutarlılığı, iç link yapısı.
  
  ## 6. LLM Uyumluluğu
  ChatGPT/Gemini/Perplexity bu siteyi nasıl anlıyor? İşletme kimliği ve hizmetler yeterince açık mı? LLM-dostu iyileştirme önerileri.
  
  ## Öncelikli 5 Aksiyon
  Kritik maddeler öncelik sırasıyla.`;
      }
    },
    {
      id: 2,
      icon: '🤖',
      title: 'Yapay Zeka Tanıtım Dosyaları',
      description: 'llms.txt ve diğer AI tanıtım dosyalarının hazırlanması ve meta verilerin AI sistemleri için optimize edilmesi.',
      buildPrompt(d) {
        return `Sen bir AdresGezgini AI SEO uzmanısın. "${d.url}" sitesi için YAPAY ZEKA TANITIM DOSYALARI hazırla.
  
  Site: ${d.title} | ${d.description}
  Tür: ${d.siteType}
  Metin: ${d.text.substring(0, 4000)}
  Schema'lar: ${d.schemas.join(', ') || 'Yok'}
  
  ## 1. llms.txt Dosyası Analizi
  Bu site için llms.txt gerekli mi? Perplexity, ChatGPT gibi sistemlere katkısı nedir?
  
  ## 2. Hazır llms.txt İçeriği
  Aşağıdaki formatı kullan ve siteye özel doldur:
  
  \`\`\`
  # ${d.title} - AI Tanıtım Dosyası
  > [İşletme özeti - tek cümle]
  
  ## İşletme Hakkında
  [Faaliyet alanı, lokasyon, hedef kitle]
  
  ## Önemli Sayfalar
  - ${d.url} — Ana Sayfa
  - [Tespit ettiğin diğer sayfalar]
  
  ## Hizmetler / Ürünler
  [Her biri için kısa açıklama]
  
  ## İletişim
  [Varsa bilgiler]
  \`\`\`
  
  ## 3. Meta Veri Durumu
  Meta açıklamaları, Open Graph etiketleri, JSON-LD description/name alanları AI sistemleri için yeterli mi?
  
  ## 4. Öncelikli 5 Aksiyon
  En önemli yapılacaklar listesi.`;
      }
    },
    {
      id: 3,
      icon: '🔍',
      title: 'Arama Motoru Kayıtları',
      description: 'Google, Yandex ve Bing webmaster araçlarına kayıt ve doğrulama için adım adım rehber. (Sadece rehber formatında)',
      buildPrompt(d) {
        return `Sen bir AdresGezgini AI SEO uzmanısın. "${d.url}" sitesi için ARAMA MOTORU KAYITLARI rehberi hazırla.
  
  ÖNEMLİ: Biz kayıt işlemini yapmıyoruz. Müşteriye rehber sunuyoruz.
  
  Site: ${d.title} | Tür: ${d.siteType}
  
  ## 1. Google Search Console Kayıt Rehberi
  - Site ekleme adımları (arayüz güncellemelerine göre)
  - Sahiplik doğrulama yöntemleri: HTML etiket, Google Analytics, DNS kaydı
  - XML Sitemap gönderimi nasıl yapılır?
  - URL Denetleme aracı nasıl kullanılır?
  - İndeksleme hataları ve Core Web Vitals raporu nasıl okunur?
  
  ## 2. Bing Webmaster Tools Kayıt Rehberi
  - Site ekleme ve sahiplik doğrulama
  - Google Search Console'dan otomatik aktarım seçeneği
  - Sitemap gönderimi ve Bing'e özel dikkat edilecekler
  
  ## 3. Yandex Webmaster Kayıt Rehberi
  - Türk kullanıcılar için Yandex önemi
  - Site ekleme, doğrulama, sitemap gönderimi
  
  ## 4. ${d.url} İçin Özel Notlar
  Bu sitenin URL yapısına göre kayıt sürecinde dikkat edilecek özel durumlar (www/non-www tercihi, HTTPS zorunluluğu, vs.)
  
  ## 5. Webmaster Araçları Takip Takvimi
  Aylık/haftalık kontrol edilmesi gereken raporlar ve metrikler.`;
      }
    },
    {
      id: 4,
      icon: '📊',
      title: 'Yapılandırılmış Veri Düzenlemeleri',
      description: 'Schema.org işaretlemelerinin denetimi ve eksik şemaların JSON-LD koduyla hazırlanması.',
      buildPrompt(d) {
        return `Sen bir AdresGezgini AI SEO uzmanısın. "${d.url}" sitesi için YAPILANDIRILMIŞ VERİ DÜZENLEMELERİ analizi yap.
  
  Site: ${d.title} | ${d.description}
  Tür: ${d.siteType}
  Mevcut Schema'lar: ${d.schemas.join(', ') || 'HİÇ SCHEMA BULUNAMADI'}
  Metin: ${d.text.substring(0, 3000)}
  
  ## 1. Mevcut Şema Durumu
  Hangi şemalar var, hangileri eksik? Genel değerlendirme.
  
  ## 2. Organization / LocalBusiness Şeması
  Örnek JSON-LD kodu (siteye özel name, url, description, address, telephone, openingHours alanlarıyla):
  \`\`\`json
  {JSON-LD örneği buraya}
  \`\`\`
  
  ## 3. ${d.siteType.includes('Ürün') ? 'Product Şeması' : 'Service Şeması'}
  ${d.siteType.includes('Ürün') ? 'Ürün sayfaları için Product şeması örneği (name, description, image, offers, aggregateRating).' : 'Hizmet sayfaları için Service şeması örneği (name, description, provider, areaServed, serviceType).'}
  \`\`\`json
  {JSON-LD örneği buraya}
  \`\`\`
  
  ## 4. BreadcrumbList Şeması
  Bu site için örnek Breadcrumb şeması.
  \`\`\`json
  {JSON-LD örneği buraya}
  \`\`\`
  
  ## 5. FAQ Şeması
  Siteye uygun 5 soru-cevap ve JSON-LD örneği.
  \`\`\`json
  {JSON-LD örneği buraya}
  \`\`\`
  
  ## 6. Test ve Doğrulama
  Google Rich Results Test nasıl kullanılır? Yaygın şema hataları nelerdir?
  
  ## 7. Öncelikli 5 Aksiyon`;
      }
    },
    {
      id: 5,
      icon: '✍️',
      title: 'İçerik Düzenleme ve Geliştirme',
      description: 'Mevcut metin içeriklerinin SEO ve kullanıcı deneyimine uygun şekilde geliştirilmesi.',
      buildPrompt(d) {
        return `Sen bir AdresGezgini AI SEO uzmanısın. "${d.url}" sitesi için İÇERİK DÜZENLEME VE GELİŞTİRME analizi yap.
  
  Site: ${d.title} | ${d.description}
  Tür: ${d.siteType}
  Mevcut metin: ${d.text.substring(0, 5000)}
  
  ## 1. Meta Başlık Analizi
  Mevcut: "${d.title}"
  SEO için optimize edilmiş 3 alternatif başlık öner (max 60 karakter, her biri farklı açıdan).
  
  ## 2. Meta Açıklama Analizi
  Mevcut: "${d.description}"
  2 alternatif meta açıklama yaz (max 160 karakter, kullanıcıyı tıklamaya ikna eden).
  
  ## 3. Ana Sayfa İçerik İyileştirmeleri
  Mevcut metinden tespit ettiğin en zayıf 3 bölümü belirt ve alternatif metin önerileri sun.
  
  ## 4. Sayfa Sayfa Değerlendirme (4-5 Sayfa)
  ${d.siteType.includes('Ürün') ? 'Ana sayfa, kategori sayfaları, ürün sayfaları, hakkımızda, iletişim' : 'Ana sayfa, hizmet sayfaları, hakkımızda, iletişim, varsa blog'} için ayrı ayrı içerik değerlendirmesi ve iyileştirme notları.
  
  ## 5. H1-H6 Başlık Yapısı
  Başlık hiyerarşisi doğru mu? Eksik veya hatalı başlıklar için somut düzeltme önerileri.
  
  ## 6. Okunabilirlik ve Dil Kalitesi
  Anlaşılması güç ifadeler, tekrarlayan cümleler, gereksiz teknik jargon tespiti.
  
  ## 7. Öncelikli 5 Aksiyon`;
      }
    },
    {
      id: 6,
      icon: '👥',
      title: 'Kullanıcı Odaklı İçerik',
      description: 'Ziyaretçi niyetine uygun SSS, değer teklifi ve kullanıcı yönlendirme içeriklerinin geliştirilmesi.',
      buildPrompt(d) {
        return `Sen bir AdresGezgini AI SEO uzmanısın. "${d.url}" sitesi için KULLANICI ODAKLI İÇERİK analizi yap.
  
  Site: ${d.title} | ${d.description}
  Tür: ${d.siteType}
  Metin: ${d.text.substring(0, 4000)}
  
  ## 1. Kullanıcı Niyeti Analizi
  Bu siteyi ziyaret eden kullanıcıların en temel soruları ve beklentileri. Ağırlıklı kullanıcı niyeti (bilgilendirme, karşılaştırma, satın alma, iletişim)?
  
  ## 2. Sıkça Sorulan Sorular (8 Soru)
  Bu ${d.siteType.includes('Ürün') ? 'e-ticaret' : 'hizmet'} sitesi için en çok sorulan 8 soru ve doğrudan yanıtları. Her yanıt ilk cümlede cevabı vermeli.
  
  ## 3. Değer Teklifi (Value Proposition)
  Rakiplerden ayrışan, kullanıcıyı ikna eden yeni değer teklifi metni (max 100 kelime).
  
  ## 4. CTA (Harekete Geçirici Mesaj) Önerileri
  Mevcut CTA metinlerini değerlendir, 5 güçlü alternatif CTA metni öner.
  
  ## 5. İçerik Boşlukları
  Kullanıcıların karar vermeden önce ihtiyaç duyduğu ama sitede olmayan bilgiler neler?
  
  ## 6. Blog / Rehber İçerik Önerileri
  Kullanıcı sorularını karşılayacak 5 blog konusu.
  
  ## 7. Öncelikli 5 Aksiyon`;
      }
    },
    {
      id: 7,
      icon: '🔗',
      title: 'Site İçi İçerik Bağlantıları',
      description: 'İç linkleme stratejisinin oluşturulması ve topic cluster yapısının kurulması.',
      buildPrompt(d) {
        return `Sen bir AdresGezgini AI SEO uzmanısın. "${d.url}" sitesi için SİTE İÇİ İÇERİK BAĞLANTILARI analizi yap.
  
  Site: ${d.title} | ${d.description}
  Tür: ${d.siteType}
  Metin: ${d.text.substring(0, 4000)}
  Schema'lar: ${d.schemas.join(', ') || 'Yok'}
  
  ## 1. Mevcut İç Link Yapısı
  Ana sayfa önemli sayfalara yeterince link veriyor mu? Anchor text kalitesi nasıl?
  
  ## 2. Topic Cluster Haritası
  Bu site için önerilen konu kümeleri:
  - Pillar Page (Ana Sayfa): ...
  - Cluster Content: ...
  (Her küme için belirt)
  
  ## 3. ${d.siteType.includes('Ürün') ? 'Kategori ↔ Ürün Linkleme' : 'Hizmet Sayfaları Arası Linkleme'}
  ${d.siteType.includes('Ürün') ? 'Kategori → Ürün ve Ürün → İlgili Ürün bağlantı önerileri ve anchor text örnekleri.' : 'Hizmet sayfaları arası çapraz linkleme önerileri ve anchor text örnekleri.'}
  
  ## 4. Blog → Hizmet/Ürün Linkleme
  Önerilen blog konuları ve hangi sayfalara link vermesi gerektiği.
  
  ## 5. Kırık Bağlantı Riskleri
  404 riski taşıyan URL yapıları, canonical URL tutarlılığı.
  
  ## 6. Öncelikli 5 Aksiyon`;
      }
    }
  ];
  
  // ============================================================
  // ÖZEL HİZMETLER — In-Memory dizi (persistence için localStorage)
  // ============================================================
  const CUSTOM_SERVICES_KEY = 'ag_custom_seo_services_v2';
  
  // In-memory dizi — buildPrompt fonksiyonlarını korur
  const customServicesRegistry = [];
  
  function persistCustomServices() {
    const data = customServicesRegistry.map(s => ({
      id: s.id,
      icon: s.icon,
      title: s.title,
      description: s.description,
      promptTemplate: s.promptTemplate
    }));
    try { localStorage.setItem(CUSTOM_SERVICES_KEY, JSON.stringify(data)); } catch(e) {}
  }
  
  function buildCustomServiceObj(data) {
    const tmpl = data.promptTemplate || '';
    return {
      id: data.id,
      icon: data.icon || '⭐',
      title: data.title,
      description: data.description,
      promptTemplate: tmpl,
      isCustom: true,
      buildPrompt(d) {
        return tmpl
          .replace(/\{URL\}/g,         d.url)
          .replace(/\{TITLE\}/g,       d.title)
          .replace(/\{DESCRIPTION\}/g, d.description)
          .replace(/\{SITE_TYPE\}/g,   d.siteType)
          .replace(/\{TEXT\}/g,        (d.text || '').substring(0, 4000))
          .replace(/\{SCHEMAS\}/g,     (d.schemas || []).join(', ') || 'Yok');
      }
    };
  }
  
  /** Tüm hizmetler: sabit 7 + özel */
  function getAllServices() {
    return [...AI_SEO_SERVICES, ...customServicesRegistry];
  }
  
  // ============================================================
  // STATE
  // ============================================================
  const state = {
    targetUrl: '',
    siteType: '',
    fetchedData: null,
    completedServices: new Set(),
    serviceResults: {},
    currentChatId: String(Date.now())
  };
  
  // ============================================================
  // YARDIMCI FONKSİYONLAR
  // ============================================================
  
  async function callGemini(prompt, temperature = 0.3) {
    const res = await fetch('form_submit.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        contents: [{ parts: [{ text: prompt }] }],
        generationConfig: { temperature }
      })
    });
    if (!res.ok) throw new Error('Sunucu hatası: ' + res.status);
    const result = await res.json();
    if (result.error) throw new Error(result.error.message || JSON.stringify(result.error));
    const text = result?.candidates?.[0]?.content?.parts?.[0]?.text;
    if (!text) throw new Error('AI yanıt vermedi.');
    return text;
  }
  
  async function fetchSiteData(url) {
    const res = await fetch('fetch_url.php?url=' + encodeURIComponent(url));
    if (!res.ok) throw new Error('Site verileri alınamadı: ' + res.status);
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    return {
      title:       data.title       || '',
      description: data.description || '',
      text:        data.text        || '',
      schemas:     Array.isArray(data.schemas) ? data.schemas : []
    };
  }
  
  function mdToHtml(md) {
    if (typeof marked !== 'undefined') {
      try { return marked.parse(md); } catch(e) {}
    }
    // Basit fallback
    return md
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/^### (.+)$/gm, '<h3>$1</h3>')
      .replace(/^## (.+)$/gm,  '<h2>$1</h2>')
      .replace(/^# (.+)$/gm,   '<h1>$1</h1>')
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/`([^`]+)`/g, '<code>$1</code>')
      .replace(/^[-*] (.+)$/gm, '<li>$1</li>')
      .replace(/(<li>[\s\S]*?<\/li>)/g, '<ul>$1</ul>')
      .replace(/\n\n+/g, '</p><p>')
      .replace(/^([^<\n].+)$/gm, '<p>$1</p>');
  }
  
  function updateServiceButtonState(serviceId, status) {
    const btn  = document.querySelector(`.aiseo-svc-btn[data-service="${serviceId}"]`);
    const span = document.querySelector(`.svc-status[data-svc="${serviceId}"]`);
    if (!btn) return;
    const styles = {
      loading: { border: '#f59e0b', bg: '#fffbeb', color: '#92400e' },
      done:    { border: '#10b981', bg: '#f0fdf4', color: '#065f46' },
      error:   { border: '#ef4444', bg: '#fef2f2', color: '#991b1b' },
      idle:    { border: '#e2e8f0', bg: '#fff',    color: '#334155' }
    };
    const s = styles[status] || styles.idle;
    btn.style.borderColor = s.border;
    btn.style.background  = s.bg;
    btn.style.color       = s.color;
    if (span) {
      if (status === 'loading') {
        span.innerHTML = '<span style="display:inline-block;width:10px;height:10px;border:2px solid #f59e0b;border-top-color:transparent;border-radius:50%;animation:ag-spin .8s linear infinite;vertical-align:middle;margin-left:4px;"></span>';
      } else if (status === 'done') {
        span.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="3" style="vertical-align:middle;margin-left:4px;"><polyline points="20 6 9 17 4 12"/></svg>';
      } else {
        span.innerHTML = status === 'error' ? ' ⚠️' : '';
      }
    }
  }
  
  function updateBadges() {
    const ub = document.getElementById('aiseo-url-badge');
    const tb = document.getElementById('aiseo-type-badge');
    if (ub) { ub.textContent = state.targetUrl ? '🌐 ' + state.targetUrl : ''; ub.style.display = state.targetUrl ? 'inline-flex' : 'none'; }
    if (tb) { tb.textContent = state.siteType  ? '📌 ' + state.siteType  : ''; tb.style.display = state.siteType  ? 'inline-flex' : 'none'; }
  }
  
  function updateRunAllBtn() {
    const btn = document.getElementById('btn-run-all-services');
    if (!btn) return;
    const all  = getAllServices();
    const left = all.filter(s => !state.completedServices.has(s.id)).length;
    if (left === 0 && all.length > 0) {
      btn.textContent = '✅ Tüm Hizmetler Tamamlandı';
      btn.disabled = true; btn.style.background = '#64748b'; btn.style.cursor = 'default';
    } else {
      btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;flex-shrink:0;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>Tüm Hizmetleri Yap${left < all.length ? ' (' + left + ' kalan)' : ''}`;
      btn.disabled = false; btn.style.background = '#10b981'; btn.style.cursor = 'pointer';
    }
  }
  
  function updateDownloadBtn() {
    const btn = document.getElementById('btn-download-pdf');
    if (!btn) return;
    const all    = getAllServices();
    const allDone = all.length > 0 && all.every(s => state.completedServices.has(s.id));
    btn.disabled  = !allDone;
    btn.style.opacity = allDone ? '1' : '0.5';
    btn.style.cursor  = allDone ? 'pointer' : 'not-allowed';
    btn.title = allDone ? 'PDF Rapor İndir' : 'Tüm hizmetler tamamlandığında aktif olur';
    btn.onclick = allDone ? downloadPDF : null;
  }
  
  // ============================================================
  // ACCORDION
  // ============================================================
  
  function renderServiceAccordion(service, status, htmlContent, errorMsg) {
    htmlContent = htmlContent || '';
    errorMsg    = errorMsg    || '';
  
    // Panel + results area görünür olsun
    const panel = document.getElementById('aiseo-services-panel');
    if (panel) panel.style.display = 'block';
  
    const area = document.getElementById('aiseo-results-area');
    if (!area) { console.error('aiseo-results-area bulunamadı'); return; }
  
    const domId = 'aiseo-accordion-' + service.id;
    let wrap = document.getElementById(domId);
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.id        = domId;
      wrap.className = 'aiseo-accordion';
      area.appendChild(wrap);
      setTimeout(() => wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 200);
    }
  
    const colors = {
      done:    { border: '#10b981', bg: '#f0fdf4', bodyBorder: '#d1fae5' },
      loading: { border: '#f59e0b', bg: '#fffbeb', bodyBorder: '#fde68a' },
      error:   { border: '#ef4444', bg: '#fef2f2', bodyBorder: '#fecaca' }
    };
    const c = colors[status] || colors.loading;
  
    const icon = status === 'done'
      ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`
      : status === 'loading'
        ? `<span style="display:inline-block;width:14px;height:14px;border:2px solid #f59e0b;border-top-color:transparent;border-radius:50%;animation:ag-spin .8s linear infinite;"></span>`
        : `<span style="font-size:14px;">⚠️</span>`;
  
    let body = '';
    if (status === 'loading') {
      body = `<div style="padding:20px 24px;display:flex;align-items:center;gap:12px;color:#64748b;border-top:1px solid ${c.bodyBorder};">
        <div class="typing-indicator" style="padding:0;flex-shrink:0;"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>
        <span style="font-size:13px;">Yapay zeka analiz yapıyor, lütfen bekleyin...</span>
      </div>`;
    } else if (status === 'error') {
      body = `<div style="padding:20px 24px;color:#dc2626;font-size:13px;border-top:1px solid ${c.bodyBorder};">
        <strong>⚠️ Hata:</strong> ${errorMsg}<br><br>
        <button onclick="window.runSingleService(${JSON.stringify(service.id)})" style="background:#ef4444;color:#fff;border:none;border-radius:8px;padding:7px 16px;font-size:12px;font-weight:600;cursor:pointer;">🔄 Tekrar Dene</button>
      </div>`;
    } else {
      body = `<div class="aiseo-accordion-content" style="padding:20px 24px 28px;font-size:13.5px;line-height:1.75;color:#334155;border-top:1px solid ${c.bodyBorder};">${htmlContent}</div>`;
    }
  
    const bodyOpen   = status !== 'error';
    const toggleRot  = bodyOpen ? 'rotate(180deg)' : '';
  
    wrap.innerHTML = `
      <div style="border:1.5px solid ${c.border};border-radius:14px;overflow:hidden;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:${c.bg};cursor:pointer;user-select:none;gap:12px;"
             onclick="window.toggleAccordion(${JSON.stringify(service.id)})">
          <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
            <span style="font-size:20px;flex-shrink:0;">${service.icon}</span>
            <div style="min-width:0;">
              <div style="font-size:14px;font-weight:700;color:#0f172a;">${service.title}</div>
              <div style="font-size:12px;color:#64748b;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${service.description}</div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
            ${icon}
            <div id="aiseo-toggle-${service.id}" style="width:24px;height:24px;border-radius:50%;background:rgba(0,0,0,0.06);display:flex;align-items:center;justify-content:center;transition:transform .25s;transform:${toggleRot};">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
          </div>
        </div>
        <div id="aiseo-body-${service.id}" style="display:${bodyOpen ? 'block' : 'none'};">
          ${body}
        </div>
      </div>`;
  }
  
  window.toggleAccordion = function(serviceId) {
    const body   = document.getElementById('aiseo-body-' + serviceId);
    const toggle = document.getElementById('aiseo-toggle-' + serviceId);
    if (!body) return;
    const open = body.style.display !== 'none';
    body.style.display          = open ? 'none' : 'block';
    if (toggle) toggle.style.transform = open ? '' : 'rotate(180deg)';
  };
  
  // ============================================================
  // HİZMET ÇALIŞTIR
  // ============================================================
  
  window.runSingleService = async function(rawId) {
    // id hem integer hem string olabilir — tip-güvenli karşılaştırma
    const serviceId = (typeof rawId === 'string' && !rawId.startsWith('custom_'))
      ? parseInt(rawId, 10) : rawId;
  
    const service = getAllServices().find(s => String(s.id) === String(serviceId));
    if (!service) {
      console.error('Hizmet bulunamadı:', serviceId, 'Mevcut:', getAllServices().map(s => s.id));
      return;
    }
    if (!state.fetchedData) {
      alert('Lütfen önce bir URL girin ve analiz ettirin.');
      return;
    }
  
    // Hali hazırda yükleniyorsa tekrar tetikleme
    const loadKey = 'loading_' + String(serviceId);
    if (state.completedServices.has(loadKey)) return;
    state.completedServices.add(loadKey);
  
    updateServiceButtonState(serviceId, 'loading');
    renderServiceAccordion(service, 'loading');
  
    try {
      const siteData = {
        url:         state.targetUrl,
        title:       state.fetchedData.title,
        description: state.fetchedData.description,
        text:        state.fetchedData.text,
        schemas:     state.fetchedData.schemas,
        siteType:    state.siteType
      };
  
      const aiText     = await callGemini(service.buildPrompt(siteData), 0.25);
      const htmlContent = mdToHtml(aiText);
  
      state.completedServices.delete(loadKey);
      state.serviceResults[String(serviceId)] = { html: htmlContent, raw: aiText };
      state.completedServices.add(serviceId);
  
      renderServiceAccordion(service, 'done', htmlContent);
      updateServiceButtonState(serviceId, 'done');
      updateRunAllBtn();
      updateDownloadBtn();
      saveAnalysis().catch(e => console.warn('Kayıt hatası:', e));
  
    } catch (err) {
      state.completedServices.delete(loadKey);
      renderServiceAccordion(service, 'error', '', err.message);
      updateServiceButtonState(serviceId, 'error');
      console.error('Hizmet hatası [' + serviceId + ']:', err);
    }
  };
  
  window.runAllServices = async function() {
    const btn = document.getElementById('btn-run-all-services');
    if (btn) { btn.disabled = true; btn.style.opacity = '0.8'; }
  
    for (const svc of getAllServices()) {
      if (!state.completedServices.has(svc.id)) {
        await window.runSingleService(svc.id);
        await new Promise(r => setTimeout(r, 2000));
      }
    }
  
    if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
    updateRunAllBtn();
  };
  
  // ============================================================
  // PDF
  // ============================================================
  
  function downloadPDF() {
    if (typeof html2pdf === 'undefined') { alert('PDF kütüphanesi yüklenemedi.'); return; }
    const date = new Date().toLocaleDateString('tr-TR');
    let pages  = '';
    getAllServices().forEach(svc => {
      const res = state.serviceResults[String(svc.id)];
      pages += `<div style="padding:40px;min-height:1100px;background:#fff;page-break-after:always;position:relative;">
        <div style="border-bottom:3px solid #2563eb;padding-bottom:14px;margin-bottom:24px;">
          <h2 style="font-size:20px;color:#0f172a;margin:0;font-weight:800;">${svc.icon} ${svc.title}</h2>
          <p style="font-size:12px;color:#64748b;margin:5px 0 0;">${svc.description}</p>
        </div>
        <div style="font-size:13px;line-height:1.7;color:#334155;">${res ? res.html : '<p style="color:#94a3b8;">Analiz yapılmadı.</p>'}</div>
        <div style="position:absolute;bottom:18px;left:40px;right:40px;border-top:1px solid #e2e8f0;padding-top:8px;font-size:10px;color:#94a3b8;display:flex;justify-content:space-between;">
          <span>AdresGezgini — Yapay Zeka SEO Raporu</span><span>${date}</span>
        </div>
      </div>`;
    });
  
    const el = document.createElement('div');
    el.innerHTML = `<div style="font-family:Arial,sans-serif;">
      <div style="height:1100px;display:flex;flex-direction:column;justify-content:center;align-items:center;background:linear-gradient(135deg,#2563eb,#1e3a8a);color:#fff;text-align:center;padding:40px;page-break-after:always;">
        <div style="font-size:36px;font-weight:800;margin-bottom:14px;">AdresGezgini</div>
        <div style="font-size:44px;font-weight:800;line-height:1.1;margin-bottom:24px;">YAPAY ZEKA<br>SEO RAPORU</div>
        <div style="background:rgba(255,255,255,.12);padding:10px 26px;border-radius:50px;font-size:15px;font-family:monospace;margin-bottom:12px;">${state.targetUrl}</div>
        <div style="font-size:13px;opacity:.8;">Site Türü: ${state.siteType}</div>
        <div style="margin-top:auto;font-size:12px;opacity:.6;">${date}</div>
      </div>${pages}</div>`;
  
    html2pdf().set({ margin: 0, filename: 'AG_AI_SEO_Raporu.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2, useCORS: true }, jsPDF: { unit: 'px', format: [794, 1123], orientation: 'portrait' } }).from(el).save();
  }
  
  // ============================================================
  // URL FETCH + SİTE TÜRÜ TESPİTİ
  // ============================================================
  
  async function detectAndFetchSite(url) {
    const indicator = document.getElementById('aiseo-scan-indicator');
    const scanText  = document.getElementById('aiseo-scan-text');
    const submitBtn = document.getElementById('aiseo-url-submit');
  
    if (indicator) indicator.style.display = 'block';
    if (scanText)  scanText.textContent = 'Site verileri çekiliyor...';
    if (submitBtn) { submitBtn.disabled = true; submitBtn.style.opacity = '0.6'; }
  
    try {
      state.fetchedData = await fetchSiteData(url);
      if (scanText) scanText.textContent = 'Yapay zeka site türünü tespit ediyor...';
  
      const typeText = await callGemini(
        `Bu web sitesini analiz et ve sadece şu iki seçenekten birini yaz, başka HİÇBİR ŞEY EKLEME:\n- Ürün / E-Ticaret\n- Hizmet / Kurumsal\n\nURL: ${url}\nBaşlık: ${state.fetchedData.title}\nAçıklama: ${state.fetchedData.description}\nMetin (ilk 1000 karakter): ${state.fetchedData.text.substring(0, 1000)}`,
        0.1
      );
      state.siteType = typeText.trim().includes('Ürün') ? 'Ürün / E-Ticaret' : 'Hizmet / Kurumsal';
  
      updateBadges();
      if (indicator) indicator.style.display = 'none';
  
      document.getElementById('aiseo-url-card').style.display     = 'none';
      document.getElementById('aiseo-services-panel').style.display = 'block';
  
      updateRunAllBtn();
      updateDownloadBtn();
  
    } catch (err) {
      if (indicator) indicator.style.display = 'none';
      alert('Hata: ' + err.message);
    } finally {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.style.opacity = '1'; }
    }
  }
  
  // ============================================================
  // ÖZEL HİZMET EKLE (+ butonu)
  // ============================================================
  
  function renderCustomServiceButton(svc) {
    const container = document.getElementById('aiseo-service-buttons');
    if (!container) return;
  
    // Eski + butonunu çıkar
    const addBtn = document.getElementById('btn-add-custom-service');
    if (addBtn) addBtn.remove();
  
    const btn = document.createElement('button');
    btn.className = 'aiseo-svc-btn has-tooltip';
    btn.setAttribute('data-service', svc.id);
    btn.setAttribute('data-tooltip', svc.description);
    btn.style.cssText = 'display:inline-flex;align-items:center;gap:6px;background:#fff;border:1.5px solid #a78bfa;border-radius:20px;padding:8px 16px;font-size:13px;font-weight:500;color:#5b21b6;cursor:pointer;transition:all .2s;';
    btn.innerHTML = `<span>${svc.icon}</span> ${svc.title}<span class="svc-status" data-svc="${svc.id}"></span>`;
    btn.addEventListener('click', () => window.runSingleService(svc.id));
    container.appendChild(btn);
  
    appendAddButton();
  }
  
  function appendAddButton() {
    const container = document.getElementById('aiseo-service-buttons');
    if (!container) return;
  
    const old = document.getElementById('btn-add-custom-service');
    if (old) old.remove();
  
    const btn = document.createElement('button');
    btn.id    = 'btn-add-custom-service';
    btn.title = 'Özel hizmet ekle';
    btn.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;background:#fff;border:1.5px dashed #94a3b8;border-radius:50%;cursor:pointer;font-size:20px;color:#64748b;transition:all .2s;flex-shrink:0;line-height:1;';
    btn.textContent = '+';
    btn.addEventListener('mouseenter', () => { btn.style.borderColor='#2563eb'; btn.style.color='#2563eb'; btn.style.background='#eff6ff'; });
    btn.addEventListener('mouseleave', () => { btn.style.borderColor='#94a3b8'; btn.style.color='#64748b'; btn.style.background='#fff'; });
    btn.addEventListener('click', openAddCustomServiceModal);
    container.appendChild(btn);
  }
  
  function openAddCustomServiceModal() {
    document.getElementById('custom-svc-modal')?.remove();
  
    const modal = document.createElement('div');
    modal.id = 'custom-svc-modal';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:10000;display:flex;align-items:center;justify-content:center;padding:20px;';
    modal.innerHTML = `
      <div style="background:#fff;border-radius:20px;padding:32px;max-width:480px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:ag-slide-down .25s ease;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
          <div>
            <h3 style="font-size:17px;font-weight:700;color:#0f172a;margin:0 0 4px;">Özel Hizmet Ekle</h3>
            <p style="font-size:13px;color:#64748b;margin:0;">AI bu hizmet için analiz promptunu otomatik oluşturacak</p>
          </div>
          <button id="csm-close" style="background:none;border:none;cursor:pointer;font-size:22px;color:#64748b;line-height:1;padding:4px;">✕</button>
        </div>
  
        <div style="margin-bottom:14px;">
          <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Hizmet Adı *</label>
          <input id="csm-name" type="text" placeholder="Örn: Mobil SEO Denetimi"
            style="width:100%;border:1.5px solid #e2e8f0;border-radius:10px;padding:10px 14px;font-size:14px;box-sizing:border-box;font-family:inherit;">
        </div>
  
        <div style="margin-bottom:24px;">
          <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Hizmet Detayı *</label>
          <textarea id="csm-desc" rows="4" placeholder="Bu hizmet kapsamında ne yapılacak? AI bu bilgiyi kullanarak analiz sorularını oluşturacak..."
            style="width:100%;border:1.5px solid #e2e8f0;border-radius:10px;padding:10px 14px;font-size:13px;box-sizing:border-box;resize:vertical;line-height:1.6;font-family:inherit;"></textarea>
        </div>
  
        <div id="csm-loading" style="display:none;text-align:center;padding:10px;color:#64748b;font-size:13px;">
          <span style="display:inline-block;width:14px;height:14px;border:2px solid #2563eb;border-top-color:transparent;border-radius:50%;animation:ag-spin .8s linear infinite;vertical-align:middle;margin-right:8px;"></span>
          AI prompt oluşturuyor...
        </div>
  
        <div style="display:flex;gap:10px;">
          <button id="csm-cancel" style="flex:1;padding:11px;background:#f1f5f9;color:#475569;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">İptal</button>
          <button id="csm-save"   style="flex:2;padding:11px;background:#2563eb;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;">Kaydet</button>
        </div>
      </div>`;
  
    document.body.appendChild(modal);
    setTimeout(() => document.getElementById('csm-name')?.focus(), 80);
  
    const close = () => modal.remove();
    document.getElementById('csm-close').onclick  = close;
    document.getElementById('csm-cancel').onclick = close;
    modal.addEventListener('click', e => { if (e.target === modal) close(); });
  
    document.getElementById('csm-save').addEventListener('click', async () => {
      const name = (document.getElementById('csm-name').value || '').trim();
      const desc = (document.getElementById('csm-desc').value || '').trim();
      if (!name || !desc) { alert('Hizmet adı ve detayını doldurun.'); return; }
  
      const saveBtn = document.getElementById('csm-save');
      const loading = document.getElementById('csm-loading');
      saveBtn.disabled = true; saveBtn.style.opacity = '0.6';
      if (loading) loading.style.display = 'block';
  
      try {
        const generatedPrompt = await callGemini(
          `Sen bir AI SEO uzmanısın. Aşağıdaki hizmet için bir web sitesi analiz promptu yaz.
  
  Hizmet adı: ${name}
  Hizmet açıklaması: ${desc}
  
  Kurallar:
  - Prompt Türkçe olmalı
  - Prompt içinde şu yer tutucuları kullan: {URL}, {TITLE}, {DESCRIPTION}, {SITE_TYPE}, {TEXT}, {SCHEMAS}
  - Markdown başlıkları (## ile) kullan
  - En az 5, en fazla 7 ana başlık
  - Son başlık "## Öncelikli 5 Aksiyon" olsun
  - Her başlık altında somut, uygulanabilir öneriler iste
  - Sadece promptu yaz, başka açıklama ekleme`,
          0.4
        );
  
        const newSvc = buildCustomServiceObj({
          id:             'custom_' + Date.now(),
          icon:           '⭐',
          title:          name,
          description:    desc,
          promptTemplate: generatedPrompt
        });
  
        customServicesRegistry.push(newSvc);
        persistCustomServices();
        renderCustomServiceButton(newSvc);
        updateRunAllBtn();
        close();
  
      } catch (err) {
        if (loading) loading.style.display = 'none';
        saveBtn.disabled = false; saveBtn.style.opacity = '1';
        alert('Hata: ' + err.message);
      }
    });
  }
  
  // ============================================================
  // GEÇMİŞ
  // ============================================================
  
  async function saveAnalysis() {
    if (!state.targetUrl || Object.keys(state.serviceResults).length === 0) return;
    await fetch('ai_seo/api/save_chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        chatId:            state.currentChatId,
        url:               state.targetUrl,
        type:              state.siteType,
        serviceResults:    state.serviceResults,
        completedServices: Array.from(state.completedServices).filter(id => !String(id).startsWith('loading_')),
        messages:          []
      })
    });
    loadHistory().catch(() => {});
  }
  
  async function loadHistory() {
    const list = document.getElementById('copilot-sidebar-history-list');
    try {
      const data    = await (await fetch('ai_seo/api/save_chat.php?t=' + Date.now())).json();
      const history = data.history || [];
      window.agChatHistory = history;
      if (typeof window.renderDashboard === 'function') window.renderDashboard();
      if (!list) return;
      list.innerHTML = '';
      if (!history.length) { list.innerHTML = '<p class="empty-note">Henüz geçmiş analiz yok.</p>'; return; }
      history.forEach(item => {
        const div = document.createElement('div');
        div.style.cssText = 'padding:12px;background:#f9fafb;border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;gap:8px;';
        const cnt = (item.completedServices || []).filter(id => !String(id).startsWith('loading_')).length;
        div.innerHTML = `
          <div style="flex:1;cursor:pointer;min-width:0;" class="hist-load-btn" data-idx="${history.indexOf(item)}">
            <div style="font-weight:600;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${item.url}</div>
            <div style="font-size:11px;color:#666;margin-top:2px;">${item.date || ''} • ${cnt} hizmet</div>
          </div>
          <button data-del-id="${item.chatId}" style="background:none;border:none;cursor:pointer;color:#ef4444;flex-shrink:0;padding:4px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          </button>`;
  
        div.querySelector('[data-del-id]').onclick = async e => {
          e.stopPropagation();
          if (!confirm('Bu analizi silmek istiyor musunuz?')) return;
          await fetch('ai_seo/api/save_chat.php?id=' + item.chatId, { method: 'DELETE' });
          loadHistory();
        };
        div.querySelector('.hist-load-btn').onclick = () => {
          loadFromHistory(item);
          document.getElementById('ai-history-sidebar').style.right = '-380px';
        };
        list.appendChild(div);
      });
    } catch(e) { console.warn('Geçmiş yükleme hatası:', e); }
  }
  
  function loadFromHistory(item) {
    resetAnalysis(false);
    state.targetUrl      = item.url;
    state.siteType       = item.type || '';
    state.currentChatId  = item.chatId;
    state.serviceResults = item.serviceResults || {};
    state.completedServices = new Set(
      (item.completedServices || []).map(id => isNaN(id) ? id : Number(id))
    );
    updateBadges();
    document.getElementById('aiseo-url-card').style.display      = 'none';
    document.getElementById('aiseo-services-panel').style.display = 'block';
  
    getAllServices().forEach(svc => {
      const key = String(svc.id);
      if (state.completedServices.has(svc.id) && state.serviceResults[key]) {
        updateServiceButtonState(svc.id, 'done');
        renderServiceAccordion(svc, 'done', state.serviceResults[key].html || state.serviceResults[key].raw || '');
      }
    });
    updateRunAllBtn(); updateDownloadBtn();
  }
  
  function resetAnalysis(showUrlCard = true) {
    state.targetUrl         = '';
    state.siteType          = '';
    state.fetchedData       = null;
    state.completedServices = new Set();
    state.serviceResults    = {};
    state.currentChatId     = String(Date.now());
  
    updateBadges();
    const area = document.getElementById('aiseo-results-area');
    if (area) area.innerHTML = '';
    getAllServices().forEach(s => updateServiceButtonState(s.id, 'idle'));
  
    const urlCard = document.getElementById('aiseo-url-card');
    const panel   = document.getElementById('aiseo-services-panel');
    const input   = document.getElementById('aiseo-url-input');
    if (urlCard) urlCard.style.display = showUrlCard ? 'block' : 'none';
    if (panel)   panel.style.display   = showUrlCard ? 'none'  : 'block';
    if (input) { input.value = ''; if (showUrlCard) setTimeout(() => input.focus(), 100); }
    updateDownloadBtn();
  }
  
  // ============================================================
  // INIT
  // ============================================================
  
  document.addEventListener('DOMContentLoaded', () => {
  
    // localStorage'dan özel hizmetleri yükle
    try {
      const saved = JSON.parse(localStorage.getItem(CUSTOM_SERVICES_KEY) || '[]');
      saved.forEach(data => {
        if (!customServicesRegistry.find(s => s.id === data.id)) {
          customServicesRegistry.push(buildCustomServiceObj(data));
        }
      });
    } catch(e) {}
  
    // URL submit
    function handleSubmit() {
      const val = (document.getElementById('aiseo-url-input')?.value || '').trim();
      if (!val) return;
      if (!val.startsWith('http')) { alert('Geçerli bir URL girin (http/https ile başlamalı).'); return; }
      state.targetUrl = val;
      detectAndFetchSite(val);
    }
    document.getElementById('aiseo-url-submit')?.addEventListener('click', handleSubmit);
    document.getElementById('aiseo-url-input')?.addEventListener('keypress', e => { if (e.key === 'Enter') handleSubmit(); });
  
    // Sabit hizmet butonları
    document.querySelectorAll('.aiseo-svc-btn[data-service]').forEach(btn => {
      btn.addEventListener('click', () => {
        const raw = btn.getAttribute('data-service');
        window.runSingleService(isNaN(raw) ? raw : parseInt(raw, 10));
      });
    });
  
    // Tüm Hizmetleri Yap
    document.getElementById('btn-run-all-services')?.addEventListener('click', window.runAllServices);
  
    // Yeni Analiz
    document.getElementById('btn-new-analysis')?.addEventListener('click', () => resetAnalysis(true));
  
    // History sidebar
    const sidebar = document.getElementById('ai-history-sidebar');
    document.getElementById('btn-toggle-history-sidebar')?.addEventListener('click', () => { if (sidebar) sidebar.style.right = '0'; });
    document.getElementById('btn-close-history-sidebar')?.addEventListener('click', () =>  { if (sidebar) sidebar.style.right = '-380px'; });
    document.getElementById('btn-clear-history-sidebar')?.addEventListener('click', async () => {
      if (!confirm('Tüm geçmiş silinecek. Emin misiniz?')) return;
      await fetch('ai_seo/api/save_chat.php?id=all', { method: 'DELETE' });
      loadHistory();
    });
  
    // PDF butonu
    document.getElementById('btn-download-pdf')?.addEventListener('click', () => {
      if (!getAllServices().every(s => state.completedServices.has(s.id))) {
        alert('Tüm hizmetler tamamlandığında rapor indirilebilir.'); return;
      }
      downloadPDF();
    });
  
    // + butonu ve özel hizmet butonları DOM'a ekle
    customServicesRegistry.forEach(svc => renderCustomServiceButton(svc));
    appendAddButton();
  
    // İlk yükleme
    resetAnalysis(true);
    loadHistory();
  
    // Eski uyumluluk stubs
    window._forceResetChat               = () => resetAnalysis(true);
    window.startFreshAnalysis            = () => resetAnalysis(true);
    window.startNewAnalysisFromDashboard = () => resetAnalysis(true);
    window.renderTodos                   = window.renderTodos          || function() {};
    window.extractTodosAndSend           = window.extractTodosAndSend  || function() {};
    window.agChatHistory                 = window.agChatHistory        || [];
  });
  
  // CSS inject: animasyonlar
  (function injectStyles() {
    if (document.getElementById('ag-aiseo-styles')) return;
    const s = document.createElement('style');
    s.id = 'ag-aiseo-styles';
    s.textContent = `
      @keyframes ag-spin      { to { transform: rotate(360deg); } }
      @keyframes ag-slide-down { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }
      .aiseo-accordion { animation: ag-slide-down .25s ease; }
    `;
    document.head.appendChild(s);
  })();
  
  })(); // /IIFE — copilot.js scope sonu