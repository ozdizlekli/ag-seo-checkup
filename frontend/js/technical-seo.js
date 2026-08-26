/* ============================================================
   TAB "TEKNİK SEO" (nav data-tab="2") — js/technical-seo.js
   ============================================================
   Bu dosya eskiden app.js içinde duran "TAB 3 — TEKNİK SEO" bloğunun
   yerini alır. İki temel fark:

   1) GÜVENLİK: PageSpeed API anahtarı artık burada YOK. Eskiden
      `const PAGESPEED_API_KEY = 'AIza...'` şeklinde tarayıcıda açıkça
      duruyordu (herkes "Görünüm Kaynağı"ndan kopyalayabilirdi). Şimdi
      tarayıcı sadece kendi sunucumuzdaki api/technical_seo_audit.php
      uç noktasına URL gönderiyor; PSI'a giden istek sunucu tarafında
      yapılıyor, anahtar .env dosyasında kalıyor.

   2) MİMARİ: Skor artık "4 Lighthouse kategorisinin düz ortalaması"
      değil. Sunucu tarafında robots.txt/sitemap/SSL/canonical/kırık
      link/mobil-uyum/schema kontrolleri de yapılıp hepsi tek bir
      ağırlıklı + kritik-kapılı (weighted + gated) skorda birleştiriliyor
      (bkz. src/TechnicalSeo/ScoringEngine.php).

   Not: app.js'in tamamı bir IIFE — `(function(){ ... })();` — içinde
   sarılı olduğu için oradaki scoreColor()/showToast() bu dosyaya
   (ayrı bir <script> etiketi/ayrı global scope) sızmıyor. Bu yüzden
   aşağıda AYNI görünüm/davranışla kendi yerel kopyalarımızı
   tanımlıyoruz - app.js'e dokunmuyoruz (kapsam dışı).
============================================================ */

function showToast(message, type){
  const container = document.getElementById('toast-container');
  if (!container) { console.warn('[TeknikSEO] toast-container bulunamadı:', message); return; }
  const toast = document.createElement('div');
  toast.className = 'toast ' + (type === 'error' ? 'error' : 'success');
  toast.innerHTML = '<span class="dot-toast" style="width:6px;height:6px;border-radius:50%;background:#fff;display:inline-block;"></span><span></span>';
  toast.querySelector('span:last-child').textContent = message;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity .25s';
    setTimeout(() => toast.remove(), 260);
  }, 2600);
}

function scoreColor(v){
  if (v >= 80) return getComputedStyle(document.documentElement).getPropertyValue('--success').trim();
  if (v >= 55) return getComputedStyle(document.documentElement).getPropertyValue('--warn').trim();
  return getComputedStyle(document.documentElement).getPropertyValue('--danger').trim();
}

document.getElementById('t3-audit-btn').addEventListener('click', runTechnicalSeoAudit);
document.getElementById('t3-fullcrawl-btn').addEventListener('click', runFullSiteCrawl);

// "Genel Teknik SEO Skoru" kartındaki her kategori satırının yanındaki "i"
// butonuna basılınca / üstüne gelinince açılan kısa açıklama metinleri.
// Anahtarlar ScoringEngine.php'nin category_scores[].key alanıyla birebir eşleşir.
const T3_CATEGORY_INFO = {
  crawlability_indexability: 'Arama motorlarının siteni tarayıp indeksleyebilmesini etkileyen etkenlere bakar: noindex etiketi, robots.txt engelleri, sitemap.xml varlığı, canonical etiketlerin doğruluğu ve hiçbir yerden link almayan "yetim" sayfa oranı. Noindex ya da robots.txt tüm siteyi engelliyorsa skor doğrudan çok düşük bir tavana sabitlenir (kritik kapı).',
  performance: 'Google PageSpeed Insights\'tan alınan mobil ve masaüstü Lighthouse performans skorlarının ağırlıklı ortalamasıdır (mobil %70, masaüstü %30) — sayfa yükleme hızı ve Core Web Vitals gibi metriklere dayanır.',
  site_structure_links: 'İç link yapısını değerlendirir: taranan sayfalar arasındaki kırık link oranı ve hiçbir sayfaya çıkış linki vermeyen "çıkmaz sokak" sayfaların oranı skoru düşürür.',
  security_https: 'Sitenin HTTPS ile erişilebilir olup olmadığına, SSL sertifikasının geçerli olup olmadığına ve son kullanma tarihine ne kadar yakın olduğuna göre hesaplanır.',
  schema_structured_data: 'Sayfalardaki yapılandırılmış veri (schema.org) hatalarına göre hesaplanır — önem derecesi yüksek hatalar skoru büyük oranda, orta ve düşük olanlar daha az düşürür.',
  mobile_first: 'Mobil ve masaüstü sürümlerin içerik tutarlılığını karşılaştırır — mobilde belirgin kelime kaybı veya yapılandırılmış verinin mobilde kayboluyor olması skoru düşürür.'
};

function showT3InfoPopup(btn) {
  const popup = document.getElementById('t3-info-popup');
  const body = document.getElementById('t3-info-popup-body');
  if (!popup || !body) return;
  const text = T3_CATEGORY_INFO[btn.dataset.infoKey];
  if (!text) return;
  body.textContent = text;
  popup.classList.remove('hidden');
  document.querySelectorAll('.t3-info-btn.active').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  // Butonun altına, viewport dışına taşmayacak şekilde konumlandır.
  const btnRect = btn.getBoundingClientRect();
  const popupRect = popup.getBoundingClientRect();
  let left = btnRect.right - popupRect.width;
  left = Math.max(12, Math.min(left, window.innerWidth - popupRect.width - 12));
  let top = btnRect.bottom + 8;
  if (top + popupRect.height > window.innerHeight - 12) {
    top = btnRect.top - popupRect.height - 8;
  }
  popup.style.left = left + 'px';
  popup.style.top = Math.max(12, top) + 'px';
}

function hideT3InfoPopup() {
  const popup = document.getElementById('t3-info-popup');
  if (!popup) return;
  popup.classList.add('hidden');
  document.querySelectorAll('.t3-info-btn.active').forEach(b => b.classList.remove('active'));
}

// Hover (masaüstü) ve click (dokunmatik + masaüstü) - delege edilmiş event'ler,
// çünkü breakdownEl.innerHTML her tarama sonucunda yeniden oluşturuluyor.
document.addEventListener('mouseover', (e) => {
  const btn = e.target.closest('.t3-info-btn');
  if (btn) showT3InfoPopup(btn);
});
document.addEventListener('mouseout', (e) => {
  const btn = e.target.closest('.t3-info-btn');
  if (btn && !btn.contains(e.relatedTarget) && !document.getElementById('t3-info-popup').matches(':hover')) {
    hideT3InfoPopup();
  }
});
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.t3-info-btn');
  if (btn) {
    e.preventDefault();
    e.stopPropagation();
    const popup = document.getElementById('t3-info-popup');
    if (btn.classList.contains('active') && !popup.classList.contains('hidden')) {
      hideT3InfoPopup();
    } else {
      showT3InfoPopup(btn);
    }
    return;
  }
  if (!e.target.closest('#t3-info-popup')) hideT3InfoPopup();
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') hideT3InfoPopup();
});
window.addEventListener('scroll', hideT3InfoPopup, true);
window.addEventListener('resize', hideT3InfoPopup);

// "Tüm siteyi tara" onayı bekleyen taramanın durumu - standart tarama
// limit nedeniyle kesildiyse (site_structure.truncated) burada saklanır,
// kullanıcı onaylarsa AYNEN geri gönderilir (bkz. runFullSiteCrawl).
let t3PendingResume = null;

// api/technical_seo_audit.php'nin gönderdiği adımlarla BİREBİR aynı
// 'step' anahtarları - checklist'in başlangıç (henüz hiç olay gelmeden
// önceki) görünümünü oluşturmak için kullanılır. Etiket metni gerçek
// olay geldiğinde sunucudaki metinle güncellenir (bkz. updateProgressChecklist).
const T3_STEPS = [
  { step: 'homepage', label: 'Ana sayfa çekiliyor' },
  { step: 'ssl', label: 'SSL / HTTPS denetimi yapılıyor' },
  { step: 'robots', label: 'robots.txt kontrol ediliyor' },
  { step: 'sitemap', label: 'sitemap.xml kontrol ediliyor' },
  { step: 'indexability', label: 'Noindex ve canonical etiketi kontrol ediliyor' },
  { step: 'crawl', label: 'Site içi bağlantılar taranıyor' },
  { step: 'orphan', label: 'Sitemap ile çapraz kontrol ediliyor' },
  { step: 'links', label: 'Kırık linkler taranıyor' },
  { step: 'mobile', label: 'Mobil-öncelikli uyum karşılaştırılıyor' },
  { step: 'schema', label: 'Schema.org / JSON-LD doğrulanıyor' },
  { step: 'psi', label: 'PageSpeed Insights (Lighthouse) ölçülüyor' },
  { step: 'scoring', label: 'Nihai skor hesaplanıyor' },
];

function resetProgressChecklist() {
  const body = document.getElementById('t3-progress-body');
  body.innerHTML = T3_STEPS.map(s => `
    <div class="t3-progress-item" id="t3-progress-${s.step}" data-status="pending">
      <span class="t3-progress-icon" id="t3-progress-${s.step}-icon">○</span>
      <span id="t3-progress-${s.step}-label">${escapeHtml(s.label)}</span>
    </div>
  `).join('');
  document.getElementById('t3-progress-card').classList.remove('hidden');
}

function updateProgressChecklist(event) {
  const icon = document.getElementById('t3-progress-' + event.step + '-icon');
  const label = document.getElementById('t3-progress-' + event.step + '-label');
  const item = document.getElementById('t3-progress-' + event.step);
  if (!icon || !item) return;

  item.dataset.status = event.status;
  if (label && event.label) label.textContent = event.label;

  if (event.status === 'start') {
    icon.innerHTML = '<span class="spinner--dark"></span>';
  } else if (event.status === 'done') {
    icon.textContent = '✓';
  } else if (event.status === 'skipped') {
    icon.textContent = '—';
  } else {
    icon.textContent = '○';
  }
}

function hideProgressChecklist() {
  document.getElementById('t3-progress-card').classList.add('hidden');
}

/**
 * api/technical_seo_audit.php gerçek zamanlı ilerleme bildirmek için TEK bir
 * JSON yerine satır-satır JSON akışı döner: her satır {"type":"progress",...}
 * (checklist güncellemesi) ya da EN SONDA {"type":"result",...} (eskiden tek
 * parça dönen tüm yanıt). Bu fonksiyon fetch() + ReadableStream ile bu akışı
 * okuyup progress satırlarını onProgress'e iletir, 'result' satırını döner.
 * Tarayıcı ReadableStream desteklemiyorsa normal response.json()'a düşer.
 */
async function fetchStreamed(url, body, onProgress) {
  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });

  if (!response.body || typeof response.body.getReader !== 'function') {
    return response.json();
  }

  const reader = response.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';
  let result = null;

  const processLine = (line) => {
    const trimmed = line.trim();
    if (!trimmed) return;
    let ev;
    try { ev = JSON.parse(trimmed); } catch (e) { return; }
    if (ev.type === 'progress' && typeof onProgress === 'function') {
      onProgress(ev);
    } else if (ev.type === 'result') {
      result = ev;
    }
  };

  while (true) {
    const { value, done } = await reader.read();
    if (done) break;
    buffer += decoder.decode(value, { stream: true });
    let newlineIndex;
    while ((newlineIndex = buffer.indexOf('\n')) >= 0) {
      processLine(buffer.slice(0, newlineIndex));
      buffer = buffer.slice(newlineIndex + 1);
    }
  }
  processLine(buffer);

  if (!result) {
    throw new Error('Sunucudan geçerli bir sonuç alınamadı.');
  }
  return result;
}

async function runTechnicalSeoAudit() {
  const urlInput = document.getElementById('t3-url');
  const url = urlInput.value.trim();
  if (!url) { showToast('Lütfen bir web sitesi URLsi girin.', 'error'); return; }

  const btn = document.getElementById('t3-audit-btn');
  const label = document.getElementById('t3-audit-label');
  btn.disabled = true;
  label.innerHTML = '<span class="spinner"></span>';
  document.getElementById('t3-fullcrawl-card').classList.add('hidden');
  t3PendingResume = null;
  resetProgressChecklist();

  try {
    const data = await fetchStreamed('api/technical_seo_audit.php', { url }, updateProgressChecklist);

    if (!data.success) {
      throw new Error(data.error || 'Bilinmeyen bir hata oluştu.');
    }

    hideProgressChecklist();
    renderTechnicalSeoResult(url, data);
    showToast('Teknik denetim tamamlandı.', 'success');
  } catch (err) {
    console.error('[TeknikSEO]', err);
    hideProgressChecklist();
    showToast('Denetim başarısız: ' + err.message, 'error');
  } finally {
    // AI SEO Analizi sekmesindeki "Otomatik Pilot" akışı bu event'i
    // bekliyor (app.js içinde waitForEvent('t3-audit-btn-done')) - bu
    // yüzden başarılı/başarısız fark etmeksizin her zaman dispatch edilmeli.
    window.dispatchEvent(new Event('t3-audit-btn-done'));
    btn.disabled = false;
    label.textContent = 'Denetimi Başlat';
  }
}

/**
 * Standart tarama sayfa/derinlik/süre sınırına takılıp kesildiyse
 * (data.site_structure.truncated) kullanıcıya "tüm siteyi tara" seçeneğini
 * sunar. Bu, "tüm site" taramasının KENDİSİ de (2000 sayfa/90 sn) yine
 * kesilirse tekrar gösterilir - resume_state her seferinde kaldığı yeri
 * taşıdığı için akış aynı kalır.
 */
function showFullCrawlPrompt(url, data) {
  const structure = data.site_structure || {};
  const linkCheck = data.link_check || {};
  const card = document.getElementById('t3-fullcrawl-card');

  // ONEMLI: resume_state artik SADECE site yapisi kesildiginde degil, link
  // kontrolu hala bekleyen yeni link varken de doluyor. Eskiden burada
  // sadece structure.truncated kontrol edilirdi - bu, link kontrolu henuz
  // bitmemisken bile "devam et" butonunun sessizce kaybolmasina (ve o
  // linklerin bir daha hic test edilmemesine) yol acardi. Artik tek dogru
  // sinyal resume_state'in kendisinin var olup olmadigi.
  if (!structure.resume_state) {
    card.classList.add('hidden');
    t3PendingResume = null;
    return;
  }

  t3PendingResume = { url, resumeState: structure.resume_state, cachedPsi: data.psi };

  let reasonText;
  if (structure.truncated) {
    reasonText = structure.truncated_reason === 'max_time'
      ? 'süre sınırına ulaşıldı'
      : 'sayfa sayısı sınırına ulaşıldı';
  } else {
    reasonText = `link kontrolü tamamlanmadı (${linkCheck.checked_count ?? 0}/${linkCheck.total_links_found ?? '?'} link test edildi)`;
  }

  document.getElementById('t3-fullcrawl-note').textContent = structure.crawl_mode === 'full'
    ? `Tüm site taramasında da ${reasonText} (şu ana kadar ${structure.crawled_page_count} sayfa tarandı).`
    : `Standart modda ${reasonText} (${structure.crawled_page_count} sayfa tarandı).`;

  card.classList.remove('hidden');
}

// "Tüm siteyi tara" tek istekte sunucunun full_max_pages (2000 sayfa)
// sınırına takılıp yine 'truncated' dönebilir (her istek kendi içinde
// ~90 sn'lik bir sunucu-tarafı zaman dilimiyle çalışır - bu sadece TEK bir
// isteğin süresi, toplam taramanın değil). Kullanıcının aynı butona
// defalarca elle basmasını istemek yerine, bu durumda OTOMATİK olarak
// (resume_state'i kendi kendine güncelleyip) kaldığı yerden yeni bir istek
// daha atarız - bunu, kullanıcının isteği üzerine artık ZAMAN SINIRI
// KOYMADAN, tüm site (en fazla full_max_pages = 2000 sayfa) taranana kadar
// tekrarlarız. T3_FULLCRAWL_MAX_ITERATIONS sadece gercek bir sinsiz-donguye
// karsi bir guvenlik supabı - normal kullanimda hicbir zaman bu sayiya
// ulasilmaz (2000 sayfa, parca basina onlarca sayfa ile bile bu sayinin
// cok altinda biter).
const T3_FULLCRAWL_MAX_ITERATIONS = 200;

async function runFullSiteCrawl() {
  if (!t3PendingResume) return;

  const btn = document.getElementById('t3-fullcrawl-btn');
  const label = document.getElementById('t3-fullcrawl-label');
  const note = document.getElementById('t3-fullcrawl-note');
  btn.disabled = true;
  label.innerHTML = '<span class="spinner"></span>';

  let current = t3PendingResume;
  let data = null;
  let iterations = 0;

  try {
    while (true) {
      resetProgressChecklist();

      data = await fetchStreamed(
        'api/technical_seo_audit.php',
        { url: current.url, resume_state: current.resumeState, cached_psi: current.cachedPsi },
        updateProgressChecklist
      );

      if (!data.success) {
        throw new Error(data.error || 'Bilinmeyen bir hata oluştu.');
      }

      iterations++;

      const structure = data.site_structure || {};
      const linkCheck = data.link_check || {};
      // resume_state artik SADECE site yapisi kesildiginde degil, link
      // kontrolu hala bekleyen yeni link varken de doluyor (bkz. backend
      // 'link_check.truncated') - o yuzden devam etme kararini dogrudan
      // resume_state'in varligina dayandiriyoruz, sadece structure.truncated'a degil.
      const linkCheckPending = !!linkCheck.truncated;
      const canAutoContinue = structure.crawl_mode === 'full' && !!structure.resume_state && (structure.truncated || linkCheckPending);
      const withinIterationSafety = iterations < T3_FULLCRAWL_MAX_ITERATIONS;

      if (canAutoContinue && withinIterationSafety) {
        current = { url: current.url, resumeState: structure.resume_state, cachedPsi: data.psi };
        if (note) {
          note.textContent = structure.truncated
            ? `Taramaya otomatik devam ediliyor... şu ana kadar ${structure.crawled_page_count} sayfa tarandı.`
            : `Sayfa taraması tamamlandı, kalan linkler kontrol ediliyor... (${linkCheck.checked_count ?? 0}/${linkCheck.total_links_found ?? '?'})`;
        }
        continue;
      }

      break;
    }

    hideProgressChecklist();
    renderTechnicalSeoResult(current.url, data);

    const structure = data.site_structure || {};
    const linkCheck = data.link_check || {};
    if (structure.truncated || linkCheck.truncated) {
      showToast(`Otomatik devam bir süre sonra yine sınıra takıldı (${structure.crawled_page_count} sayfa, ${linkCheck.checked_count ?? 0}/${linkCheck.total_links_found ?? '?'} link) - dilersen "Evet, Tüm Siteyi Tara"ya tekrar basarak devam edebilirsin.`, 'success');
    } else {
      showToast(`Tüm site taraması tamamlandı (${structure.crawled_page_count} sayfa, ${linkCheck.checked_count ?? 0} link kontrol edildi).`, 'success');
    }
  } catch (err) {
    console.error('[TeknikSEO]', err);
    hideProgressChecklist();
    showToast('Tüm site taraması başarısız: ' + err.message, 'error');
  } finally {
    btn.disabled = false;
    label.textContent = 'Evet, Tüm Siteyi Tara';
  }
}

// PSI, mobil ve masaustu sonuclarini PARALEL cekip ikisini de donduruyor
// (PageSpeedClient::analyzeBoth) ama eskiden arayuz sadece mobili gosterip
// masaustu veriyi cekilmesine ragmen hic kullanmadan atiyordu. Ozellikle
// mobilde CrUX (gercek kullanici) alan verisi olmayan sitelerde (INP gibi
// metrikler icin sik rastlanan bir durum) kullanici hicbir sey goremiyordu,
// oysa masaustunde gercek veri olabiliyordu. Simdi ikisini de sakliyoruz ve
// kucuk bir Mobil/Masaustu anahtari ile ayni karti iki veriden biriyle
// yeniden ciziyoruz (yeniden tarama yapmadan, anlik).
let t3LastPsiData = null;
let t3LastPsiStrategy = 'mobile';

function renderPsiStrategyCard(strategy) {
  const psi = t3LastPsiData || {};
  const result = psi[strategy] || null;
  const categoryScores = (result && result.category_scores) || {};

  setSmallGauge('t3-perf', categoryScores.performance ?? 0);
  setSmallGauge('t3-seo', categoryScores.seo ?? 0);
  setSmallGauge('t3-a11y', categoryScores.accessibility ?? 0);
  setSmallGauge('t3-bp', categoryScores['best-practices'] ?? 0);

  const vitals = (result && result.web_vitals) || {};
  renderVital('t3-lcp', vitals.lcp, 'lcp');
  renderVital('t3-fcp', vitals.fcp, 'fcp');
  renderVital('t3-cls', vitals.cls, 'cls');
  renderVital('t3-ttfb', vitals.ttfb, 'ttfb');
  renderVital('t3-inp', vitals.inp, 'inp');
}

function initPsiStrategyToggle() {
  const wrap = document.getElementById('t3-psi-strategy-toggle');
  if (!wrap || wrap.dataset.wired) return;
  wrap.dataset.wired = '1';
  wrap.addEventListener('click', (e) => {
    const btn = e.target.closest('.toggle-btn');
    if (!btn || !wrap.contains(btn)) return;
    const strategy = btn.dataset.strategy;
    if (!strategy || strategy === t3LastPsiStrategy) return;
    t3LastPsiStrategy = strategy;
    wrap.querySelectorAll('.toggle-btn').forEach(b => {
      b.classList.toggle('active', b.dataset.strategy === strategy);
    });
    renderPsiStrategyCard(strategy);
  });
}

function renderTechnicalSeoResult(inputUrl, data) {
  t3LastPsiData = data.psi || null;
  t3LastPsiStrategy = 'mobile';
  initPsiStrategyToggle();
  const toggleWrap = document.getElementById('t3-psi-strategy-toggle');
  if (toggleWrap) {
    toggleWrap.querySelectorAll('.toggle-btn').forEach(b => {
      b.classList.toggle('active', b.dataset.strategy === 'mobile');
    });
  }
  renderPsiStrategyCard('mobile');

  document.getElementById('t3-audit-url').textContent = data.url || inputUrl;
  document.getElementById('t3-output-card').classList.remove('hidden');

  // state.technicalScore artık düz ortalama değil, ScoringEngine'in
  // ağırlıklı + kritik-kapılı nihai skoru - app.js'deki genel rapor
  // özetinde (tab-1) bu değeri okuyan kod değişmeden çalışmaya devam eder.
  if (typeof state !== 'undefined') {
    state.technicalScore = data.score ? data.score.final_score : 0;
  }

  renderQuickAudit(data);
  renderCompositeScore(data.score);
  renderFindings(data.score ? data.score.findings : [], (data.site_structure && data.site_structure.crawled_page_count) || null);
  showFullCrawlPrompt(data.url || inputUrl, data);

  document.getElementById('t3-quick-audit-card').classList.remove('hidden');
  document.getElementById('t3-composite-score-card').classList.remove('hidden');
  document.getElementById('t3-findings-card').classList.remove('hidden');

  renderPsiErrors(data.psi);

  // Kismi (tamamlanmamis) taramalari skor gecmisinde ayirt edebilmek icin -
  // showFullCrawlPrompt'un ("Tum Siteyi Tara" istemini tetikleyen fonksiyon)
  // kullandigi TAM OLARAK AYNI sinyal kullanilmali, yoksa iki yerde farkli
  // sonuc cikar (bu bir bug'du - asagida duzeltildi):
  //  - site yapisi (sayfa/derinlik/sure) sinirina takilip kesildiyse HER
  //    modda "kismi" sayilir.
  //  - link kontrolu ise SADECE Tum Siteyi Tara (full crawl) modunda
  //    "kismi" sayilir - standart moddaki link kontrolu zaten TASARIM
  //    GEREGI en fazla ilk birkac onlarca linki kontrol eder (bkz.
  //    api/technical_seo_audit.php linkChecker->check() cagrisi ve
  //    LinkChecker::$maxLinks), bu YARIDA KALMA degil, standart modun hiz
  //    icin kabul edilmis bir sinirlamasi. Eskiden buradaki kontrol modu
  //    ayirt etmiyordu - standart moddaki HER denetim (40'tan fazla ic
  //    linki olan hemen her site icin) yanlislikla "kismi" (mor) isaretlenip
  //    "Tum Siteyi Tara" istemi hic cikmamis olsa bile grafikte oyle
  //    gorunuyordu.
  const historyStructure = data.site_structure || {};
  const historyLinkCheck = data.link_check || {};
  const isFullCrawlAudit = historyStructure.crawl_mode === 'full';
  const isPartialAudit = !!(historyStructure.truncated || (isFullCrawlAudit && historyLinkCheck.truncated));
  updateScoreHistoryUI(data.url || inputUrl, data.score, isPartialAudit, isFullCrawlAudit);
}

// PageSpeedClient.php PSI çağrısı başarısız olduğunda (kota/429, zaman aşımı,
// ağ hatası, PSI'ın kendisinin döndürdüğü hata) sunucu tarafında ayrıntılı bir
// hata mesajı üretiyor ama bu mesaj eskiden hiçbir yerde gösterilmiyordu -
// kullanıcı sadece "her şey 0/—" görüp NEDEN olduğunu anlayamıyordu. Artık
// varsa bu mesajları kartın üstünde açıkça gösteriyoruz.
const T3_PSI_STRATEGY_LABEL = { mobile: 'Mobil', desktop: 'Masaüstü' };

function renderPsiErrors(psi) {
  const el = document.getElementById('t3-psi-warning');
  if (!el) return;

  const errors = (psi && psi.errors) || {};
  const entries = Object.keys(errors).filter(k => errors[k]);

  if (entries.length === 0) {
    el.classList.add('hidden');
    el.innerHTML = '';
    return;
  }

  el.innerHTML = entries.map(strategy => `
    <div class="tag tag--danger" style="display:block; font-weight:500; margin-bottom:6px; padding:10px 12px; white-space:normal;">
      ⚠ PageSpeed Insights (${escapeHtml(T3_PSI_STRATEGY_LABEL[strategy] || strategy)}) verisi alınamadı: ${escapeHtml(errors[strategy])}
    </div>
  `).join('') + '<div class="small muted">Bu genelde Google\'ın PSI servisinde geçici bir kota/zaman aşımı sorunudur - skorlar ve metrikler bu yüzden 0/— görünüyor. Birkaç dakika sonra taramayı tekrarlamayı deneyin.</div>';
  el.classList.remove('hidden');
}

function renderQuickAudit(data) {
  const qa = data.quick_audit || {};

  const ssl = qa.ssl || {};
  document.getElementById('t3-qa-ssl').innerHTML = ssl.https_reachable && ssl.valid
    ? `<span class="tag tag--success" style="font-weight:500;">Geçerli${ssl.days_remaining !== null && ssl.days_remaining !== undefined ? ' (' + ssl.days_remaining + ' gün kaldı)' : ''}</span>`
    : ssl.https_reachable
      ? `<span class="tag tag--warn" style="font-weight:500;">Erişilebilir ama geçersiz (${ssl.error || 'süresi dolmuş/host uyuşmuyor'})</span>`
      : `<span class="tag tag--danger" style="font-weight:500;">HTTPS erişilemiyor</span>`;

  const robots = qa.robots_txt || {};
  document.getElementById('t3-qa-robots').innerHTML = !robots.found
    ? '<span class="tag tag--warn" style="font-weight:500;">Bulunamadı</span>'
    : robots.blocks_entire_site
      ? '<span class="tag tag--danger" style="font-weight:500;">TÜM SİTEYİ ENGELLİYOR</span>'
      : '<span class="tag tag--success" style="font-weight:500;">Bulundu, site engellenmiyor</span>';

  const sitemap = qa.sitemap || {};
  document.getElementById('t3-qa-sitemap').innerHTML = sitemap.found
    ? `<span class="tag tag--success" style="font-weight:500;">Bulundu (${sitemap.url_count} URL)</span>`
    : '<span class="tag tag--danger" style="font-weight:500;">Bulunamadı</span>';

  const noindex = qa.noindex || {};
  document.getElementById('t3-qa-noindex').innerHTML = noindex.noindex
    ? '<span class="tag tag--danger" style="font-weight:500;">NOINDEX VAR (' + (noindex.source || '') + ')</span>'
    : '<span class="tag tag--success" style="font-weight:500;">Yok, indekslenebilir</span>';

  const canonical = qa.canonical || {};
  document.getElementById('t3-qa-canonical').innerHTML = !canonical.present
    ? '<span class="tag tag--warn" style="font-weight:500;">Etiket yok</span>'
    : canonical.is_self_referencing
      ? '<span class="tag tag--success" style="font-weight:500;">Kendine referans veriyor</span>'
      : '<span class="tag tag--warn" style="font-weight:500;">Başka bir URL\'e işaret ediyor</span>'
        + (canonical.canonical_url ? `<div class="small muted" style="margin-top:4px;">→ ${escapeHtml(canonical.canonical_url)}</div>` : '');

  const mobileParity = data.mobile_parity || {};
  document.getElementById('t3-qa-mobile').innerHTML = !mobileParity.comparable
    ? '<span class="tag" style="font-weight:500;">Karşılaştırılamadı</span>'
    : mobileParity.significant_content_loss
      ? `<span class="tag tag--danger" style="font-weight:500;">Kayıp var (%${mobileParity.word_count_drop_percent} kelime azalması)</span>`
      : '<span class="tag tag--success" style="font-weight:500;">İçerik uyumlu</span>';

  const linkCheck = data.link_check || {};
  const brokenCount = (linkCheck.broken || []).length;
  document.getElementById('t3-qa-links').innerHTML = brokenCount > 0
    ? `<span class="tag tag--danger" style="font-weight:500;">${brokenCount} kırık link (${linkCheck.checked_count} test edildi)</span>`
    : `<span class="tag tag--success" style="font-weight:500;">Kırık link yok (${linkCheck.checked_count} test edildi)</span>`;
}

function renderCompositeScore(score) {
  if (!score) return;

  const circle = document.getElementById('t3-final-score-circle');
  const valEl = document.getElementById('t3-final-score-val');
  valEl.textContent = score.final_score;
  valEl.style.color = scoreColor(score.final_score);
  circle.style.stroke = scoreColor(score.final_score);
  circle.style.strokeDashoffset = 100 - score.final_score;

  const gatesEl = document.getElementById('t3-gates-warning');
  if (score.gates_triggered && score.gates_triggered.length > 0) {
    gatesEl.innerHTML = score.gates_triggered.map(g =>
      `<div class="tag tag--danger" style="display:block; font-weight:600; margin-bottom:6px; padding:10px 12px; white-space:normal;">
        ⚠ Kritik kapı tetiklendi — skor en fazla ${g.cap}: ${escapeHtml(g.message)}
      </div>`
    ).join('');
  } else {
    gatesEl.innerHTML = `<span class="small muted">Ağırlıklı ortalama: ${score.weighted_average_before_gates} — hiçbir kritik kapı tetiklenmedi, nihai skor ağırlıklı ortalamayla aynı.</span>`;
  }

  const breakdownEl = document.getElementById('t3-category-breakdown');
  breakdownEl.innerHTML = (score.category_scores || []).map(cat => `
    <div class="meter-row">
      <div class="meter-row__head">
        <span class="label">${escapeHtml(cat.label)} <span class="small muted">(ağırlık: %${cat.weight_percent}, güven: ${cat.confidence})</span></span>
        <span class="value-wrap">
          <span class="value">${cat.score}</span>
          ${T3_CATEGORY_INFO[cat.key] ? `<button type="button" class="t3-info-btn" data-info-key="${escapeHtml(cat.key)}" aria-label="${escapeHtml(cat.label)} nasıl hesaplanıyor?">i</button>` : ''}
        </span>
      </div>
      <div class="meter-track"><div class="meter-fill fill-${scoreStatus(cat.score)}" style="width:${cat.score}%;"></div></div>
    </div>
  `).join('');
}

// Bulgu kartlarında kullanılan önem derecesi meta verisi: rozet rengi,
// Türkçe etiket ve sıralama ağırlığı (ScoringEngine.php'deki SEVERITY_WEIGHTS
// ile aynı önem sırası - critical > major > minor).
const T3_SEVERITY_META = {
  critical: { tagClass: 'tag--danger', label: 'Yüksek', dotColor: 'var(--danger)' },
  major: { tagClass: 'tag--warn', label: 'Orta', dotColor: 'var(--warn)' },
  minor: { tagClass: 'tag', label: 'Düşük', dotColor: 'var(--muted-2)' }
};

function renderFindings(findings, totalPages) {
  const el = document.getElementById('t3-findings-body');
  const subtitleEl = document.getElementById('t3-findings-subtitle');
  if (subtitleEl && totalPages) {
    // "Etkilenen sayfa" / "grup" sayılarının neye göre ORANLANDIĞINI belli
    // etmek için taranan toplam sayfa sayısını burada gösteriyoruz - aksi
    // halde ör. "246 sayfa" bulgusunun büyük mü küçük mü bir oran olduğu
    // taranan toplam sayfa sayısı bilinmeden anlaşılamıyordu.
    subtitleEl.textContent = `Taranan toplam sayfa: ${totalPages} — önce önem derecesi (yüksek → orta → düşük), sonra aynı derece içinde etkilenen sayfa oranı × güven seviyesine göre sıralanmıştır`;
  }

  if (!findings || findings.length === 0) {
    el.innerHTML = '<span class="small muted">Herhangi bir sorun tespit edilmedi 🎉</span>';
    return;
  }

  // Bulgular backend'de zaten önem derecesine göre (yüksek → orta → düşük,
  // aynı derece içinde öncelik puanına göre) sıralı geliyor (bkz.
  // ScoringEngine::compute). Burada sadece üstte hızlı bir özet gösteriyoruz.
  const counts = { critical: 0, major: 0, minor: 0 };
  findings.forEach(f => { if (counts[f.severity] !== undefined) counts[f.severity]++; });

  const summaryHtml = Object.keys(T3_SEVERITY_META)
    .filter(sev => counts[sev] > 0)
    .map(sev => {
      const meta = T3_SEVERITY_META[sev];
      return `<span class="finding-summary__item"><span class="finding-summary__dot" style="background:${meta.dotColor};"></span>${counts[sev]} ${meta.label.toLowerCase()}</span>`;
    }).join('');

  // Bazı bulgular (şu an: kırık linkler) tek tek örnek/URL listesi taşır
  // (bkz. ScoringEngine::buildFindings 'items' alanı) - bu durumda kart
  // tıklanabilir hale gelir, tıklanınca aşağı doğru açılıp listeyi gösterir.
  const cardsHtml = findings.map(f => {
    const meta = T3_SEVERITY_META[f.severity] || T3_SEVERITY_META.minor;
    const items = Array.isArray(f.items) ? f.items : [];
    const hasItems = items.length > 0;

    const itemsListHtml = hasItems
      ? items.map(it => `
          <div class="finding-card__item">
            <a href="${escapeHtml(it.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(it.url)}</a>
            <span class="small muted">${escapeHtml(it.status)}</span>
          </div>
        `).join('')
      : '';

    // Bulgunun 'items' listesi her zaman "link" degil - cogu (H1 eksik,
    // yinelenen title, sitemap disi sayfa, yetim sayfa...) aslinda SAYFA
    // listesi. Backend artik hangisi oldugunu 'item_noun' ile bildiriyor,
    // biz de burada onu kullaniyoruz (varsayilan: 'sayfa').
    const itemNoun = escapeHtml(f.item_noun || 'sayfa');
    const toggleHtml = hasItems
      ? `<button type="button" class="finding-card__toggle-btn" aria-expanded="false">${items.length} ${itemNoun} — göster <span class="finding-card__chevron">▾</span></button>`
      : '';

    return `
    <div class="card finding-card finding-card--${escapeHtml(f.severity)}${hasItems ? ' finding-card--expandable' : ''}" style="margin-bottom:10px;">
      <div class="finding-card__meta">
        <span class="tag ${meta.tagClass}" style="font-weight:600;">${meta.label}</span>
        <span class="small muted">güven: ${escapeHtml(f.confidence)} · öncelik puanı: ${f.priority_score} · etkilenen sayfa: ${f.affected_pages}</span>
      </div>
      <div class="finding-card__title">${escapeHtml(f.title)}</div>
      <div class="small muted finding-card__detail">${escapeHtml(f.detail)}</div>
      <div class="small finding-card__fix"><strong>Nasıl düzeltilir:</strong> ${escapeHtml(f.how_to_fix)}</div>
      ${toggleHtml}
      ${hasItems ? `<div class="finding-card__items hidden">${itemsListHtml}</div>` : ''}
    </div>
  `;
  }).join('');

  el.innerHTML = `<div class="finding-summary">${summaryHtml}</div>${cardsHtml}`;
}

// Bulgu kartına (veya "X link — göster" butonuna) tıklanınca, varsa altındaki
// link listesini açar/kapatır. Listedeki linklere tıklamak (yeni sekmede
// açılsın diye) toggle'ı TETİKLEMEMELİ - bu yüzden tıklamanın bir <a>
// içinden gelip gelmediğini ayrıca kontrol ediyoruz.
document.addEventListener('click', (e) => {
  if (e.target.closest('a')) return;

  const card = e.target.closest('.finding-card--expandable');
  if (!card) return;

  const itemsEl = card.querySelector('.finding-card__items');
  const toggleBtn = card.querySelector('.finding-card__toggle-btn');
  if (!itemsEl || !toggleBtn) return;

  const willExpand = itemsEl.classList.contains('hidden');
  itemsEl.classList.toggle('hidden', !willExpand);
  toggleBtn.setAttribute('aria-expanded', willExpand ? 'true' : 'false');
  toggleBtn.classList.toggle('finding-card__toggle-btn--open', willExpand);
});

function setSmallGauge(id, score) {
  const circle = document.getElementById(id + '-circle');
  const valEl = document.getElementById(id + '-val');
  valEl.textContent = score;
  valEl.style.color = scoreColor(score);
  circle.style.stroke = scoreColor(score);
  circle.style.strokeDashoffset = 100 - score;
}

function renderVital(prefix, vital, kind) {
  const displayValue = (vital && vital.display_value) || 'Veri bulunamadı';
  const numericValue = vital && vital.numeric_value !== null && vital.numeric_value !== undefined ? vital.numeric_value : null;

  // Veri yoksa (PSI çağrısı başarısız olduysa) çubuk boş/nötr görünmeli -
  // eskiden burada varsayılan "orta" (turuncu, %50 dolu) gösteriliyordu,
  // bu da veri YOKKEN sanki "ortalama bir ölçüm var" gibi yanıltıcı bir
  // izlenim veriyordu.
  let status = 'none';
  let percent = 0;

  if (numericValue !== null) {
    if (kind === 'lcp' || kind === 'fcp' || kind === 'ttfb') {
      const sec = numericValue / 1000;
      status = lcpStatus(sec);
      percent = lcpPercent(sec);
    } else if (kind === 'cls') {
      status = clsStatus(numericValue);
      percent = clsPercent(numericValue);
    } else if (kind === 'inp') {
      status = numericValue <= 200 ? 'good' : numericValue <= 500 ? 'mid' : 'bad';
      percent = Math.max(0, 100 - (numericValue / 10));
    }
  }

  renderMeter(prefix, displayValue, status, percent);
}

function lcpStatus(v) { return v <= 2.5 ? 'good' : v <= 4.0 ? 'mid' : 'bad'; }
function lcpPercent(v) { return Math.max(6, Math.min(100, 100 - (v / 6) * 100)); }
function clsStatus(v) { return v <= 0.1 ? 'good' : v <= 0.25 ? 'mid' : 'bad'; }
function clsPercent(v) { return Math.max(6, Math.min(100, 100 - (v / 0.4) * 100)); }
function scoreStatus(v) { return v >= 90 ? 'good' : v >= 50 ? 'mid' : 'bad'; }

function renderMeter(prefix, valueText, status, percent) {
  const value = document.getElementById(prefix + '-value');
  const fill = document.getElementById(prefix + '-fill');
  if (!value || !fill) return;
  value.textContent = valueText;
  fill.style.width = percent + '%';
  fill.className = 'meter-fill fill-' + status;
}

function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/* ============================================================
   SKOR GEÇMİŞİ VE KARŞILAŞTIRMA (Teknik SEO)
   ============================================================
   Bu denetim akışı bir "müşteri" seçimine değil, doğrudan girilen
   bir URL'ye dayanır (bkz. runTechnicalSeoAudit - client_id yok,
   sadece #t3-url'e yazılan adres). Bu yüzden geçmiş kayıtlar URL'ye
   göre saklanır (bkz. api/technical_score_history.php). Müşteriye
   bağlama isteğe bağlıdır (client_id NULL olabilir) - zorunlu tutmak
   bu sekmeye "önce müşteri seç" gibi ekstra bir adım ekler ki mevcut
   akışta böyle bir seçim hiç yok.
============================================================ */
const T3_HISTORY_CATEGORIES = [
  { key: 'crawlability_indexability', column: 'crawlability_score', label: 'Taranabilirlik & İndekslenebilirlik' },
  { key: 'performance', column: 'performance_score', label: 'Performans' },
  { key: 'site_structure_links', column: 'site_structure_score', label: 'Site Yapısı & Linkler' },
  { key: 'security_https', column: 'security_score', label: 'Güvenlik (HTTPS)' },
  { key: 'schema_structured_data', column: 'schema_score', label: 'Yapılandırılmış Veri (Schema)' },
  { key: 'mobile_first', column: 'mobile_score', label: 'Mobil Öncelik' },
];

let t3HistoryCurrentUrl = null;
let t3HistoryCurrentScore = null; // { final_score, category_scores }
let t3HistoryCurrentIsPartial = false;
let t3HistorySavedForCurrent = false;
let t3HistoryCurrentIsFullCrawl = false;
// "Skor Gecmisi" ayri sayfasinda o an EKRANDA GOSTERILEN url/musteri -
// t3HistoryCurrentUrl'den farkli: o hep en son CANLI DENETIMI takip eder,
// bu ise kullanicinin gecmis sayfasinda arayip actigi kaydi takip eder.
// "Su an" hayalet cubugu SADECE ikisi eslestiginde gosterilir.
let t3HistoryDisplayedUrl = null;
let t3HistoryClientsCache = null; // api/clients.php sonucu - hem kaydetme hem arama select'i paylasir

function normalizeUrlLoose(url) {
  if (!url) return '';
  return String(url).trim().toLowerCase().replace(/\/+$/, '').replace(/^https?:\/\//, '');
}

// Bir URL'nin, musteri eslestirmesinde kullanilacak "cikri" hostname'ini
// cikarir - protokol, "www." on eki, yol, sorgu string'i ve sondaki slash
// yok sayilir. Tarayicinin kendi URL sinifini kullaniyoruz (naif bir regex
// yerine gercek bir parser) - boylece ornegin "adresgezgini.example.com"
// ile "adresgezgini.com" YANLISLIKLA eslesmez (backend'deki normalizeDomain
// ile ayni mantik - bkz. api/technical_score_history.php).
function normalizeDomain(url) {
  if (!url) return '';
  let s = String(url).trim();
  if (!s) return '';
  if (!/^https?:\/\//i.test(s)) s = 'https://' + s;
  try {
    let host = new URL(s).hostname.toLowerCase();
    if (host.startsWith('www.')) host = host.slice(4);
    return host;
  } catch (e) {
    return '';
  }
}

// Verilen url'nin hostname'i, cache'lenmis musteri listesindeki bir
// domain_url ile eslesiyor mu diye bakar. Musteri listesi henuz cache'de
// yoksa once yukler (await ile).
async function findClientByUrl(url) {
  const domain = normalizeDomain(url);
  if (!domain) return null;
  const clients = await fetchClientsCached();
  return clients.find(c => c.domain_url && normalizeDomain(c.domain_url) === domain) || null;
}

async function fetchClientsCached() {
  if (t3HistoryClientsCache) return t3HistoryClientsCache;
  try {
    const res = await fetch('api/clients.php');
    const { data } = await res.json();
    // Kayıtlı müşteriler her zaman alfabetik (tr) sıralı gösterilsin - hem
    // kaydetme widget'ında hem arama listesinde, çok sayıda müşteri
    // olduğunda bulmayı kolaylaştırır.
    t3HistoryClientsCache = (data || []).slice().sort((a, b) => (a.name || '').localeCompare(b.name || '', 'tr'));
  } catch (err) {
    console.error('[TeknikSEO] Müşteri listesi yüklenemedi:', err);
    t3HistoryClientsCache = [];
  }
  return t3HistoryClientsCache;
}

function renderClientSearchList(listEl, clients, query) {
  const q = (query || '').trim().toLowerCase();
  const filtered = q
    ? clients.filter(c => (c.name || '').toLowerCase().includes(q) || (c.domain_url || '').toLowerCase().includes(q))
    : clients;
  if (!filtered.length) {
    listEl.innerHTML = '<div class="search-select__item search-select__item--empty">Eşleşen müşteri yok</div>';
    return;
  }
  listEl.innerHTML = filtered.map(c => `
    <div class="search-select__item" data-id="${c.id}" data-name="${escapeHtml(c.name)}">
      ${escapeHtml(c.name)}${c.domain_url ? `<span class="small muted"> — ${escapeHtml(c.domain_url)}</span>` : ''}
    </div>
  `).join('');
}

// Musteri sayisi arttikca native <select> icinde arama yapamamak isi
// zorlastiriyor - framework kullanmadan, input + filtrelenen bir liste
// ile yazarak aranabilen hafif bir bilesen. hidden input secili musteri
// id'sini tasir, mevcut kod (ornegin clientSelect.value okuyan save
// handler'i) degismeden calismaya devam eder.
function wireClientSearchSelect(inputId, hiddenId, listId) {
  const input = document.getElementById(inputId);
  const hidden = document.getElementById(hiddenId);
  const list = document.getElementById(listId);
  if (!input || !hidden || !list || input.dataset.wired) return;
  input.dataset.wired = '1';

  const openFiltered = async () => {
    const clients = await fetchClientsCached();
    renderClientSearchList(list, clients, input.value);
    list.classList.remove('hidden');
  };

  // Alana TIKLANDIGINDA (focus) - alanda onceden secilmis bir musteri adi
  // yazili olsa bile, o metne gore FILTRELEMEDEN butun musteri listesini
  // (alfabetik, kaydirilabilir) gosteriyoruz. Aksi halde "Reklam.im" secili
  // haldeyken tekrar tiklayinca liste kendi kendine "Reklam.im" ile
  // filtrelenip sanki tek musteri varmis gibi goruniyordu - baska bir
  // musteriye gecmek icin once metni silmek gerekiyordu.
  const openAll = async () => {
    const clients = await fetchClientsCached();
    renderClientSearchList(list, clients, '');
    list.classList.remove('hidden');
  };

  input.addEventListener('focus', openAll);
  input.addEventListener('input', () => {
    // Kullanici yeniden yazmaya basladiginda onceki secim gecersiz olur -
    // yeniden bir listeden secim yapilana kadar hidden id bosaltilir. Burada
    // (focus'tan farkli olarak) YAZILAN metne gore filtrelemeye devam
    // ediyoruz - arama kutusunun asil amaci bu.
    hidden.value = '';
    openFiltered();
  });
  list.addEventListener('mousedown', (e) => {
    // click yerine mousedown - input'un blur ile listeyi gizlemesinden ONCE
    // secim yakalanmis olsun diye.
    const item = e.target.closest('.search-select__item[data-id]');
    if (!item) return;
    e.preventDefault();
    input.value = item.dataset.name;
    hidden.value = item.dataset.id;
    list.classList.add('hidden');
    hidden.dispatchEvent(new Event('change'));
  });
  document.addEventListener('click', (e) => {
    if (e.target === input || list.contains(e.target)) return;
    list.classList.add('hidden');
  });
}

// ------------------------------------------------------------------
// "Yeni Teknik Denetim" karti - kompakt musteri otomatik-eslesme durumu.
// URL yazildikca (ve bir denetim tamamlandiginda gercek/yonlendirilmis
// URL ile) domain kontrolu yapilip bu kucuk durum satiri guncellenir -
// eskiden ayri/buyuk bir "Musteriye bagla" alani vardi, artik URL'nin
// altinda tek satirlik bir ozet + gerekirse manuel secim linki var.
// ------------------------------------------------------------------

function renderClientMatchStatus() {
  const hidden = document.getElementById('t3-history-client-select');
  const input = document.getElementById('t3-history-client-select-input');
  const statusEl = document.getElementById('t3-client-match-status');
  if (!hidden || !input || !statusEl) return;

  if (hidden.value) {
    statusEl.classList.add('t3-client-match--ok');
    statusEl.innerHTML =
      '<span class="t3-client-match__dot"></span>' +
      '<span>' + escapeHtml(input.value) + ' müşterisiyle eşleşti</span>' +
      '<a href="#" class="t3-client-match__link" data-action="change">değiştir</a>';
  } else {
    statusEl.classList.remove('t3-client-match--ok');
    statusEl.innerHTML =
      '<span class="t3-client-match__dot"></span>' +
      '<span>Kayıtlı müşteri bulunamadı</span>' +
      '<a href="#" class="t3-client-match__link" data-action="pick">Müşteri seç</a>';
  }
}

// URL'nin hostname'i kayitli bir musteriyle eslesip eslesmedigini kontrol
// edip hem hidden/input degerlerini hem durum satirini gunceller - hem canli
// URL yazarken hem de bir denetim TAMAMLANDIGINDA (gercek/yonlendirilmis
// url ile) ayni yerden cagrilir, boylece tek bir dogru kaynak olur.
async function applyClientMatchForUrl(url) {
  const hidden = document.getElementById('t3-history-client-select');
  const input = document.getElementById('t3-history-client-select-input');
  if (!hidden || !input) return;
  const client = await findClientByUrl(url);
  if (client) {
    hidden.value = client.id;
    input.value = client.name;
  } else {
    hidden.value = '';
    input.value = '';
  }
  renderClientMatchStatus();
}

document.getElementById('t3-url')?.addEventListener('input', (e) => {
  applyClientMatchForUrl(e.target.value);
});

// "değiştir" / "Müşteri seç" linkine tiklaninca, varsayilan gizli olan
// arama kutusunu ac/kapa (kompakt kalsin diye varsayilan gizli) ve
// aciliyorsa hemen odakla ki tam listeyi gorsün (bkz. wireClientSearchSelect
// -> input focus -> openAll).
document.getElementById('t3-client-match-status')?.addEventListener('click', (e) => {
  const link = e.target.closest('[data-action]');
  if (!link) return;
  e.preventDefault();
  const picker = document.getElementById('t3-history-client-searchselect');
  if (!picker) return;
  picker.classList.toggle('hidden');
  if (!picker.classList.contains('hidden')) {
    document.getElementById('t3-history-client-select-input')?.focus();
  }
});

// Manuel secim yapildiginda (wireClientSearchSelect'in dispatch ettigi
// 'change') seciciyi tekrar gizleyip durum satirini yeni secime gore
// yeniliyoruz.
document.getElementById('t3-history-client-select')?.addEventListener('change', () => {
  document.getElementById('t3-history-client-searchselect')?.classList.add('hidden');
  renderClientMatchStatus();
});

renderClientMatchStatus();

async function updateScoreHistoryUI(url, score, isPartial, isFullCrawl) {
  if (!url || !score) return;
  t3HistoryCurrentUrl = url;
  t3HistoryCurrentScore = score;
  t3HistoryCurrentIsPartial = !!isPartial;
  t3HistoryCurrentIsFullCrawl = !!isFullCrawl;
  // Her yeni denetim sonucu (ilk "Canlı Denetim Yap" ya da "Tüm Siteyi Tara"
  // ile devam eden bir tarama tamamlandığında) BİR kez daha kaydedilebilir
  // olmasi icin kaydetme kilidini ve buton durumunu sifirliyoruz - bkz.
  // t3-history-save-btn click handler'daki "zaten kaydedildi" kontrolu.
  t3HistorySavedForCurrent = false;
  const saveBtnReset = document.getElementById('t3-history-save-btn');
  if (saveBtnReset) {
    saveBtnReset.disabled = false;
    saveBtnReset.textContent = 'Şimdi Kaydet';
  }
  // "Rapor Oluştur (PDF)" butonu ilk denetim TAMAMLANANA kadar disabled
  // duruyordu (bkz. index.php) - artik elimizde gercek bir sonuc oldugu
  // icin tiklanabilir hale getiriyoruz.
  const reportBtn = document.getElementById('t3-report-btn');
  if (reportBtn) {
    reportBtn.disabled = false;
    reportBtn.title = 'Denetim sonucunu ve skor geçmişini yeni bir sekmede rapor olarak aç';
  }
  // Bu URL'nin (gercek/yonlendirilmis - denetimden donen url) domain'i
  // kayitli bir musteriyle eslesiyor mu diye bakip kompakt durum satirini
  // gunceller - kullanici URL'yi elle yazarken zaten ayni kontrol calisiyor
  // (bkz. #t3-url input listener), burada denetim TAMAMLANDIKTAN sonraki
  // kesin url ile tekrar dogruluyoruz (ornegin redirect sonrasi degismis
  // olabilir).
  applyClientMatchForUrl(url).then(() => {
    // Checkbox isaretliyse, "Simdi Kaydet" butonuna GERCEKTEN tiklamis gibi
    // davranip AYNI kaydetme fonksiyonunu (asagidaki click handler)
    // tetikliyoruz - kayit mantigini burada TEKRARLAMIYORUZ, sadece
    // tetikleyiciyi degistiriyoruz (manuel tik yerine otomatik tik).
    const autosave = document.getElementById('t3-history-autosave-checkbox');
    if (autosave && autosave.checked && saveBtnReset && !saveBtnReset.disabled) {
      saveBtnReset.click();
    }
  });
  // NOT: "Skor Geçmişi" artık ayrı bir alt-sekme - burada otomatik açılıp
  // geçmiş yüklenmiyor. Kullanıcı "Skor Geçmişi" sekmesine geçtiğinde (bkz.
  // initT3SubviewToggle) o an ekranda gösterilen url bu url ile eşleşiyorsa
  // "Şu an" karşılaştırma çubuğu otomatik belirir.
}

function computeCurrentForDisplay() {
  // "Şu an" hayalet çubuğu sadece geçmiş sayfasında GÖRÜNTÜLENEN url, en son
  // CANLI DENETİMİN url'siyle (gevşek/normalize) eşleştiğinde gösterilir -
  // başka bir url/müşteri geçmişine bakarken alakasız bir "şu an" çubuğu
  // çıkmasın diye.
  if (!t3HistoryDisplayedUrl || !t3HistoryCurrentUrl || !t3HistoryCurrentScore) return null;
  if (normalizeUrlLoose(t3HistoryDisplayedUrl) !== normalizeUrlLoose(t3HistoryCurrentUrl)) return null;
  // Bu denetim zaten kaydedildiyse (t3HistorySavedForCurrent), az sonra
  // yüklenecek geçmiş listesinde SON dolu çubuk olarak zaten görünecek -
  // aynı skoru bir de kesikli "şu an" çubuğu olarak tekrar göstermek
  // gereksiz bir tekrar/kafa karışıklığı yaratıyordu.
  if (t3HistorySavedForCurrent) return null;
  return {
    score: t3HistoryCurrentScore,
    isPartial: t3HistoryCurrentIsPartial,
    isFullCrawl: t3HistoryCurrentIsFullCrawl,
  };
}

async function loadAndRenderScoreHistory(url) {
  t3HistoryDisplayedUrl = url;
  const chartWrap = document.getElementById('t3-history-chart-wrap');
  const tablesWrap = document.getElementById('t3-history-category-tables');
  if (!chartWrap || !tablesWrap) return;
  try {
    const res = await fetch('api/technical_score_history.php?url=' + encodeURIComponent(url));
    const { data, error } = await res.json();
    if (error) throw new Error(error);
    renderScoreHistory(data || []);
  } catch (err) {
    console.error('[TeknikSEO] Skor geçmişi yüklenemedi:', err);
    chartWrap.innerHTML = '<p class="small muted">Skor geçmişi yüklenemedi.</p>';
    tablesWrap.innerHTML = '';
  }
}

async function loadAndRenderScoreHistoryForClient(clientId, domain) {
  t3HistoryDisplayedUrl = null;
  const chartWrap = document.getElementById('t3-history-chart-wrap');
  const tablesWrap = document.getElementById('t3-history-category-tables');
  if (!chartWrap || !tablesWrap) return;
  try {
    // domain verilmisse backend (client_id = ? OR domain = ?) ile, bu
    // musterinin ID'siyle etiketlenmemis ama AYNI SITEYE ait eski kayitlari
    // da tek listede birlestirir - artik URL ve musteri gecmisi ayrilmiyor.
    let qs = 'client_id=' + encodeURIComponent(clientId);
    if (domain) qs += '&domain=' + encodeURIComponent(domain);
    const res = await fetch('api/technical_score_history.php?' + qs);
    const { data, error } = await res.json();
    if (error) throw new Error(error);
    renderScoreHistory(data || []);
  } catch (err) {
    console.error('[TeknikSEO] Müşteri geçmişi yüklenemedi:', err);
    chartWrap.innerHTML = '<p class="small muted">Geçmiş yüklenemedi.</p>';
    tablesWrap.innerHTML = '';
  }
}

async function lookupHistoryByUrl(url) {
  const clean = (url || '').trim();
  if (!clean) {
    showToast('Bir URL gir.', 'error');
    return;
  }
  const title = document.getElementById('t3-history-result-title');
  const deleteBtn = document.getElementById('t3-history-delete-btn');

  // Girilen URL'nin hostname'i kayitli bir musteriyle eslesiyor mu? Eslesirse
  // bu, o musterinin domain_url'i ile etiketlenmis/eslesen TUM gecmisi
  // (client_id ile kaydedilmis olsun ya da sadece ayni domain'den olsun)
  // TEK listede gosteririz - artik "URL ile Ara" ve "Musteriye Gore" ayni
  // site icin iki ayri, birlesmeyen sonuc dondurmuyor.
  const client = await findClientByUrl(clean);
  if (client) {
    if (title) title.textContent = 'Skor Geçmişi — ' + client.name + ' (' + normalizeDomain(clean) + ')';
    // Tek bir URL'ye ozel "sil" burada anlamli degil - sonuc birden fazla
    // URL'yi (musterinin tum domain'ini) kapsiyor, musteri modundaki gibi
    // gizliyoruz.
    if (deleteBtn) deleteBtn.classList.add('hidden');
    document.getElementById('t3-history-card')?.classList.remove('hidden');
    loadAndRenderScoreHistoryForClient(client.id, normalizeDomain(clean));
    return;
  }

  // Eslesen bir musteri yoksa eskisi gibi TAM URL'ye gore ara.
  if (title) title.textContent = 'Skor Geçmişi — ' + clean;
  if (deleteBtn) deleteBtn.classList.remove('hidden');
  document.getElementById('t3-history-card')?.classList.remove('hidden');
  loadAndRenderScoreHistory(clean);
}

function lookupHistoryByClient(clientId, clientLabel) {
  if (!clientId) {
    showToast('Bir müşteri seç.', 'error');
    return;
  }
  const title = document.getElementById('t3-history-result-title');
  if (title) title.textContent = 'Skor Geçmişi — ' + (clientLabel || 'Müşteri');
  const deleteBtn = document.getElementById('t3-history-delete-btn');
  if (deleteBtn) deleteBtn.classList.add('hidden');
  document.getElementById('t3-history-card')?.classList.remove('hidden');
  // Musterinin kendi domain_url'i de biliniyorsa (cache'den) backend'e
  // birlikte gonderiyoruz ki client_id ile etiketlenmemis ama ayni domain'e
  // ait eski kayitlar da bu listede cikabilsin.
  const cachedClient = (t3HistoryClientsCache || []).find(c => String(c.id) === String(clientId));
  const domain = cachedClient && cachedClient.domain_url ? normalizeDomain(cachedClient.domain_url) : '';
  loadAndRenderScoreHistoryForClient(clientId, domain);
}

// ------------------------------------------------------------------
// "Tum Denetim Gecmisi" - Skor Gecmisi sekmesinin altinda, varsayilan
// KAPALI acilir/kapanir bir bolum. Kayitli TUM denetimleri musteri/domain'e
// gore gruplayip (backend zaten gruplu dondurur - bkz. api/technical_score_
// history.php?overview=1) listeler: son skor, tarih, kismi/tam tarama,
// onceki skora gore degisim. Satira tiklaninca AYRI bir grafik/detay alani
// ACMIYORUZ - yukaridaki MEVCUT #t3-history-card grafigini (loadAndRender
// ScoreHistoryForClient / lookupHistoryByUrl uzerinden, "yalnizca secilen
// satirin detaylarini yukle" kuralina uyarak) o musteri/domain icin
// yeniden dolduruyoruz.
// ------------------------------------------------------------------
let t3AuditLogOffset = 0;
let t3AuditLogLoaded = false;
let t3AuditLogSearchTimer = null;
const T3_AUDIT_LOG_PAGE_SIZE = 10;

function t3AuditLogFormatDate(str) {
  if (!str) return '—';
  const dt = new Date(String(str).replace(' ', 'T'));
  if (isNaN(dt.getTime())) return '—';
  return dt.toLocaleDateString('tr-TR');
}

function t3AuditLogCrawlTag(item) {
  // Ayni mavi/mor renk dili grafikteki (renderHistoryTrendChart) mor =
  // kismi, mavi = tam site taramasi mantigiyla birebir ayni.
  if (item.is_partial) return '<span class="tag tag--compare">Kısmi Tarama</span>';
  if (item.is_full_crawl) return '<span class="tag tag--info">Tam Tarama</span>';
  return '<span class="tag" style="background:var(--border-soft); color:var(--muted);">Standart</span>';
}

function t3AuditLogDeltaHtml(item) {
  if (item.previous_score === null || item.previous_score === undefined) {
    return '<span class="t3-audit-log__delta--flat">—</span>';
  }
  const delta = Number(item.delta) || 0;
  if (delta > 0) return '<span class="t3-audit-log__delta--up">▲ ' + delta + '</span>';
  if (delta < 0) return '<span class="t3-audit-log__delta--down">▼ ' + Math.abs(delta) + '</span>';
  return '<span class="t3-audit-log__delta--flat">— 0</span>';
}

function renderAuditLogRows(items, append) {
  const list = document.getElementById('t3-audit-log-list');
  if (!list) return;
  if (!items.length) {
    if (!append) {
      list.innerHTML = '<p class="small muted t3-audit-log__empty">Henüz kaydedilmiş bir denetim yok.</p>';
    }
    return;
  }
  const html = items.map(item => {
    const displayName = item.client_name || item.domain || item.representative_url || 'Bilinmeyen';
    const subline = item.client_name && item.domain
      ? '<span class="t3-audit-log__domain">' + escapeHtml(item.domain) + '</span>'
      : '';
    return (
      '<div class="t3-audit-log__row" data-client-id="' + (item.client_id || '') + '" ' +
      'data-client-name="' + escapeHtml(item.client_name || '') + '" ' +
      'data-domain="' + escapeHtml(item.domain || '') + '" ' +
      'data-url="' + escapeHtml(item.representative_url || '') + '">' +
        '<div class="t3-audit-log__cell t3-audit-log__cell--name" data-label="Müşteri / Alan Adı">' +
          escapeHtml(displayName) + subline +
        '</div>' +
        '<div class="t3-audit-log__cell t3-audit-log__cell--score" data-label="Son Skor" style="color:' + scoreColor(item.latest_score) + '">' + item.latest_score + '</div>' +
        '<div class="t3-audit-log__cell t3-audit-log__cell--date" data-label="Tarih">' + t3AuditLogFormatDate(item.latest_date) + '</div>' +
        '<div class="t3-audit-log__cell t3-audit-log__cell--crawl" data-label="Tarama Türü">' + t3AuditLogCrawlTag(item) + '</div>' +
        '<div class="t3-audit-log__cell t3-audit-log__cell--delta" data-label="Önceki Skora Göre">' + t3AuditLogDeltaHtml(item) + '</div>' +
      '</div>'
    );
  }).join('');
  if (append) list.insertAdjacentHTML('beforeend', html);
  else list.innerHTML = html;
}

async function loadAuditLogPage(reset) {
  const list = document.getElementById('t3-audit-log-list');
  const moreBtn = document.getElementById('t3-audit-log-more');
  const searchInput = document.getElementById('t3-audit-log-search');
  if (!list) return;
  if (reset) {
    t3AuditLogOffset = 0;
    list.innerHTML = '<p class="small muted t3-audit-log__empty">Yükleniyor…</p>';
  }
  const search = searchInput ? searchInput.value.trim() : '';
  try {
    let qs = 'overview=1&offset=' + t3AuditLogOffset + '&limit=' + T3_AUDIT_LOG_PAGE_SIZE;
    if (search) qs += '&search=' + encodeURIComponent(search);
    const res = await fetch('api/technical_score_history.php?' + qs);
    const { data, total, total_records, error } = await res.json();
    if (error) throw new Error(error);
    const items = data || [];
    renderAuditLogRows(items, !reset);
    t3AuditLogOffset += items.length;
    // Basliktaki sayi, listede gorunen SATIR (grup) sayisiyla degil, toplam
    // KAYITLI DENETIM sayisiyla (technical_score_history'deki ham satir
    // sayisi) eslesiyor - ayni siteye ait birden fazla kayit listede TEK
    // satirda (son skor + degisim) birlestigi icin bu iki sayi farkli olabilir;
    // kullanicinin "daha fazla denetim yaptim ama gozukmuyor" seklinde
    // yanlis anlamasini onlemek icin ham toplami gosteriyoruz. Arama
    // filtresinden bagimsiz, her zaman gercek toplami yansitir.
    const countEl = document.getElementById('t3-audit-log-count');
    if (countEl) countEl.textContent = '(' + (total_records || 0) + ')';
    if (moreBtn) moreBtn.classList.toggle('hidden', t3AuditLogOffset >= (total || 0));
    if (!items.length && reset) {
      list.innerHTML = '<p class="small muted t3-audit-log__empty">' +
        (search ? 'Eşleşen kayıt bulunamadı.' : 'Henüz kaydedilmiş bir denetim yok.') +
        '</p>';
    }
  } catch (err) {
    console.error('[TeknikSEO] Denetim geçmişi listesi yüklenemedi:', err);
    if (reset) list.innerHTML = '<p class="small muted t3-audit-log__empty">Liste yüklenemedi.</p>';
  }
}

// Yeni bir anlik goruntu kaydedildiginde (asagidaki t3-history-save-btn
// click handler'i - hem manuel tiklama hem checkbox'un otomatik tetikledigi
// tiklama) "Tum Denetim Gecmisi" listesi ESKI kalip guncellenmiyordu, cunku
// bolum sadece IlK acilista bir kez yukleniyor (t3AuditLogLoaded). Kullanici
// yeni bir denetim kaydettiginde: bolum ACIKSA hemen ilk sayfayi yeniden
// cekip taze goster; KAPALIYSA sadece "yuklendi" bayragini sifirla ki bir
// dahaki acilista (cache'lenmis eski veri yerine) taze veri gelsin.
function refreshAuditLogAfterSave() {
  const body = document.getElementById('t3-audit-log-body');
  if (body && !body.classList.contains('hidden')) {
    loadAuditLogPage(true);
  } else {
    t3AuditLogLoaded = false;
  }
}

function initAuditLogSection() {
  const toggle = document.getElementById('t3-audit-log-toggle');
  const body = document.getElementById('t3-audit-log-body');
  const list = document.getElementById('t3-audit-log-list');
  const moreBtn = document.getElementById('t3-audit-log-more');
  const searchInput = document.getElementById('t3-audit-log-search');
  if (!toggle || !body || toggle.dataset.wired) return;
  toggle.dataset.wired = '1';

  toggle.addEventListener('click', () => {
    const willOpen = body.classList.contains('hidden');
    body.classList.toggle('hidden', !willOpen);
    toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    // Bolum kapaliyken listeyi hic cekmiyoruz - kullanici GERCEKTEN acinca
    // ilk 10 kayit yukleniyor, boylece "Skor Gecmisi" sekmesine her
    // girildiginde gereksiz bir istek atilmiyor.
    if (willOpen && !t3AuditLogLoaded) {
      t3AuditLogLoaded = true;
      loadAuditLogPage(true);
    }
  });

  moreBtn?.addEventListener('click', () => loadAuditLogPage(false));

  searchInput?.addEventListener('input', () => {
    clearTimeout(t3AuditLogSearchTimer);
    t3AuditLogSearchTimer = setTimeout(() => loadAuditLogPage(true), 300);
  });

  // Bir satira tiklaninca AYRI bir grafik yuklemiyoruz - yukaridaki mevcut
  // arama moduna (URL/Musteri) gecip #t3-history-card'i o musteri/domain
  // icin dolduruyoruz, boylece grafik/kategori render mantigi TEK yerde
  // kalir.
  list?.addEventListener('click', (e) => {
    const row = e.target.closest('.t3-audit-log__row');
    if (!row) return;
    const clientId = row.dataset.clientId || '';
    const clientName = row.dataset.clientName || '';
    const domain = row.dataset.domain || '';
    const url = row.dataset.url || '';
    if (!clientId && !url) return;

    const mode = clientId ? 'client' : 'url';
    document.getElementById('t3-history-mode-toggle')?.querySelectorAll('.toggle-btn').forEach(b => {
      b.classList.toggle('active', b.dataset.mode === mode);
    });
    document.getElementById('t3-history-url-lookup')?.classList.toggle('hidden', mode !== 'url');
    document.getElementById('t3-history-client-lookup')?.classList.toggle('hidden', mode !== 'client');

    document.getElementById('t3-history-card')?.classList.remove('hidden');

    if (clientId) {
      const clientInput = document.getElementById('t3-history-lookup-client-select-input');
      const clientHidden = document.getElementById('t3-history-lookup-client-select');
      if (clientInput) clientInput.value = clientName;
      if (clientHidden) clientHidden.value = clientId;
      const title = document.getElementById('t3-history-result-title');
      if (title) title.textContent = 'Skor Geçmişi — ' + (clientName || 'Müşteri');
      document.getElementById('t3-history-delete-btn')?.classList.add('hidden');
      loadAndRenderScoreHistoryForClient(clientId, domain);
    } else {
      const urlInput = document.getElementById('t3-history-lookup-url');
      if (urlInput) urlInput.value = url;
      lookupHistoryByUrl(url);
    }

    document.getElementById('t3-history-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
}

function initT3SubviewToggle() {
  const wrap = document.getElementById('t3-subview-toggle');
  if (!wrap || wrap.dataset.wired) return;
  wrap.dataset.wired = '1';
  wrap.addEventListener('click', (e) => {
    const btn = e.target.closest('.toggle-btn');
    if (!btn || !wrap.contains(btn)) return;
    const subview = btn.dataset.subview;
    if (!subview) return;
    wrap.querySelectorAll('.toggle-btn').forEach(b => b.classList.toggle('active', b === btn));
    const liveView = document.getElementById('t3-live-view');
    const historyView = document.getElementById('t3-history-view');
    if (liveView) liveView.classList.toggle('hidden', subview !== 'live');
    if (historyView) historyView.classList.toggle('hidden', subview !== 'history');
    if (subview === 'history') {
      fetchClientsCached();
      const urlInput = document.getElementById('t3-history-lookup-url');
      // Az önce canlı denetim yapıldıysa, geçmiş sekmesine geçince o url'yi
      // otomatik önceden doldurup gösteriyoruz - kullanıcı tekrar yazmasın.
      if (urlInput && !urlInput.value && t3HistoryCurrentUrl) {
        urlInput.value = t3HistoryCurrentUrl;
        lookupHistoryByUrl(t3HistoryCurrentUrl);
      }
      // "Tum Denetim Gecmisi" basligindaki sayi (toplam kayit sayisi)
      // bolum HENUZ acilmamis olsa bile DOGRU gorunmeli - Skor Gecmisi
      // sekmesine gecer gecmez (acilir/kapanir govde gizli KALSA da)
      // listeyi arka planda cekip cache'liyoruz. Boylece kullanici "0"
      // gibi yaniltici bir sayi gormuyor, ve bolumu actiginda da tekrar
      // istek atmadan (t3AuditLogLoaded=true) hazir veriyi gosteriyor.
      if (!t3AuditLogLoaded) {
        t3AuditLogLoaded = true;
        loadAuditLogPage(true);
      }
    }
  });
}

function initT3HistoryModeToggle() {
  const wrap = document.getElementById('t3-history-mode-toggle');
  if (!wrap || wrap.dataset.wired) return;
  wrap.dataset.wired = '1';
  wrap.addEventListener('click', (e) => {
    const btn = e.target.closest('.toggle-btn');
    if (!btn || !wrap.contains(btn)) return;
    const mode = btn.dataset.mode;
    if (!mode) return;
    wrap.querySelectorAll('.toggle-btn').forEach(b => b.classList.toggle('active', b === btn));
    document.getElementById('t3-history-url-lookup')?.classList.toggle('hidden', mode !== 'url');
    document.getElementById('t3-history-client-lookup')?.classList.toggle('hidden', mode !== 'client');
    if (mode === 'client') fetchClientsCached();
  });
}

initT3SubviewToggle();
initT3HistoryModeToggle();
initAuditLogSection();
wireClientSearchSelect('t3-history-client-select-input', 't3-history-client-select', 't3-history-client-select-list');
wireClientSearchSelect('t3-history-lookup-client-select-input', 't3-history-lookup-client-select', 't3-history-lookup-client-select-list');

document.getElementById('t3-history-lookup-url-btn')?.addEventListener('click', () => {
  const input = document.getElementById('t3-history-lookup-url');
  lookupHistoryByUrl(input ? input.value : '');
});
document.getElementById('t3-history-lookup-url')?.addEventListener('keydown', (e) => {
  if (e.key === 'Enter') document.getElementById('t3-history-lookup-url-btn')?.click();
});
document.getElementById('t3-history-lookup-client-btn')?.addEventListener('click', () => {
  const hidden = document.getElementById('t3-history-lookup-client-select');
  const input = document.getElementById('t3-history-lookup-client-select-input');
  if (!hidden || !hidden.value) {
    showToast('Listeden bir müşteri seç.', 'error');
    return;
  }
  lookupHistoryByClient(hidden.value, input ? input.value : '');
});

document.getElementById('t3-history-save-btn')?.addEventListener('click', async () => {
  if (!t3HistoryCurrentUrl || !t3HistoryCurrentScore) {
    showToast('Önce bir denetim tamamlanmalı.', 'error');
    return;
  }
  // Ayni denetim sonucu icin birden fazla kayit olusmasin - bir kez
  // kaydedildikten sonra buton kilitlenir, ancak yeni bir denetim ("Canlı
  // Denetim Yap" ya da "Tüm Siteyi Tara" ile devam) tamamlandiginda
  // updateScoreHistoryUI kilidi tekrar acar.
  if (t3HistorySavedForCurrent) {
    showToast('Bu denetim zaten kaydedildi. Yeni bir kayıt için tekrar denetim çalıştır (ör. "Tüm Siteyi Tara").', 'error');
    return;
  }
  const btn = document.getElementById('t3-history-save-btn');
  const clientSelect = document.getElementById('t3-history-client-select');
  const clientId = clientSelect && clientSelect.value ? parseInt(clientSelect.value, 10) : null;
  btn.disabled = true;
  btn.textContent = 'Kaydediliyor...';
  try {
    const payload = {
      url: t3HistoryCurrentUrl,
      client_id: clientId,
      final_score: t3HistoryCurrentScore.final_score,
      category_scores: t3HistoryCurrentScore.category_scores || [],
      is_partial: t3HistoryCurrentIsPartial ? 1 : 0,
      is_full_crawl: t3HistoryCurrentIsFullCrawl ? 1 : 0,
    };
    const res = await fetch('api/technical_score_history.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const json = await res.json();
    if (json.error) throw new Error(json.error);
    showToast('Anlık görüntü kaydedildi.', 'success');
    t3HistorySavedForCurrent = true;
    btn.textContent = 'Kaydedildi ✓';
    btn.disabled = true;
    refreshAuditLogAfterSave();
    // Geçmiş sekmesi şu an tam bu url'yi gösteriyorsa listeyi tazeleyelim;
    // başka bir url/müşteri geçmişine bakılıyorsa dokunmuyoruz.
    if (t3HistoryDisplayedUrl && normalizeUrlLoose(t3HistoryDisplayedUrl) === normalizeUrlLoose(t3HistoryCurrentUrl)) {
      await loadAndRenderScoreHistory(t3HistoryCurrentUrl);
    }
  } catch (err) {
    console.error('[TeknikSEO] Anlık görüntü kaydedilemedi:', err);
    showToast('Kaydedilemedi: ' + err.message, 'error');
    btn.disabled = false;
    btn.textContent = 'Şimdi Kaydet';
  }
});

document.getElementById('t3-history-delete-btn')?.addEventListener('click', async () => {
  if (!t3HistoryDisplayedUrl) {
    showToast('Önce bir url için geçmiş görüntülemelisin.', 'error');
    return;
  }
  const confirmed = confirm('"' + t3HistoryDisplayedUrl + '" için kaydedilmiş TÜM skor geçmişini silmek istediğine emin misin? Bu işlem geri alınamaz.');
  if (!confirmed) return;

  const btn = document.getElementById('t3-history-delete-btn');
  btn.disabled = true;
  try {
    const res = await fetch('api/technical_score_history.php?url=' + encodeURIComponent(t3HistoryDisplayedUrl), { method: 'DELETE' });
    const json = await res.json();
    if (json.error) throw new Error(json.error);
    showToast('Skor geçmişi temizlendi.', 'success');
    await loadAndRenderScoreHistory(t3HistoryDisplayedUrl);
  } catch (err) {
    console.error('[TeknikSEO] Geçmiş silinemedi:', err);
    showToast('Silinemedi: ' + err.message, 'error');
  } finally {
    btn.disabled = false;
  }
});

function renderScoreHistory(rows) {
  // API en yeniden en eskiye (DESC) döner - grafik için eskiden yeniye çeviriyoruz.
  const chronological = rows.slice().reverse();
  const current = computeCurrentForDisplay();
  renderHistoryTrendChart(chronological, current);
  renderHistoryCategoryTables(chronological, current);
}

function renderHistoryTrendChart(rows, current) {
  const wrap = document.getElementById('t3-history-chart-wrap');
  if (!wrap) return;

  // Cubuklar: once kaydedilmis gecmis anlik goruntuler (dolu, tarihli), en
  // sonda da "su an" icin ayri, seffaf/kesikli cerceveli bir hayalet cubuk -
  // boylece gecmis ile su anki denetim TEK grafikte yan yana karsilastirilir.
  const bars = rows.map(r => {
    const dt = r.created_at ? new Date(r.created_at.replace(' ', 'T')) : null;
    return {
      score: Number(r.final_score),
      // Cok cubuk oldugunda yil dahil tam tarih siga sigmiyor ve
      // birbirine giriyordu - burada sadece gun.ay gosterip tam tarihi
      // (yil dahil) <title> tooltip'inde birakiyoruz.
      label: dt ? dt.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit' }) : '—',
      fullLabel: dt ? dt.toLocaleDateString('tr-TR') : '—',
      timeLabel: dt ? dt.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' }) : '',
      kind: 'history',
      isPartial: !!Number(r.is_partial),
      isFullCrawl: !!Number(r.is_full_crawl),
    };
  });
  if (current) {
    bars.push({
      score: Number(current.score.final_score),
      label: 'Şu an',
      fullLabel: 'Şu an',
      timeLabel: '',
      kind: 'current',
      isPartial: !!current.isPartial,
      isFullCrawl: !!current.isFullCrawl,
    });
  }

  if (!bars.length) {
    wrap.innerHTML = '<p class="small muted">Henüz kaydedilmiş bir anlık görüntü yok. "Şimdi Kaydet" ile geçmiş oluşturmaya başlayabilirsin.</p>';
    return;
  }

  const H = 256, PAD_TOP = 28, PAD_BOTTOM = 56, PAD_SIDES = 32;
  const n = bars.length;
  const gap = 14;
  // Cok fazla cubuk oldugunda genisligi sikistirip etiketleri ustuste
  // bindirmek yerine, HER cubuga sabit/rahat bir genislik veriyoruz ve
  // grafik genisliyor - disaridaki kapsayici (overflow-x:auto) gerektiginde
  // yatay kaydirma sunuyor.
  const minBarWidth = n <= 8 ? 48 : 36;
  const maxBarWidth = 96;
  const cardWidth = wrap.clientWidth || 680;
  const availableForBars = cardWidth - PAD_SIDES * 2 - gap * Math.max(0, n - 1);
  // Az sayida cubuk oldugunda (ör. 2-3 kayit) sabit dar genislik kartin
  // cogu bos kalmasina yol aciyordu - cubuklar sigdigi surece kart
  // genisligini doldurana kadar genisletiliyor, sigmadigi durumda (cok
  // sayida kayit) eski sabit/kaydirmali davranisa geri donuluyor.
  const barWidth = n > 0 && availableForBars / n > minBarWidth
    ? Math.min(maxBarWidth, availableForBars / n)
    : minBarWidth;
  const usedWidth = barWidth * n + gap * (n - 1);
  const W = Math.max(usedWidth + PAD_SIDES * 2, cardWidth);
  const startX = PAD_SIDES;
  const baseY = H - PAD_BOTTOM;
  const plotH = H - PAD_TOP - PAD_BOTTOM;

  // Yukseklik farklarini daha net gorebilmek icin arka planda 0/25/50/75/100
  // seviyelerinde yatay referans cizgileri + sol tarafta seviye rakamlari.
  const GRID_LEVELS = [0, 25, 50, 75, 100];
  const gridHtml = GRID_LEVELS.map(level => {
    const gy = baseY - (level / 100) * plotH;
    const isBase = level === 0;
    return `
      <line x1="${PAD_SIDES}" y1="${gy.toFixed(1)}" x2="${W - PAD_SIDES}" y2="${gy.toFixed(1)}" stroke="var(--border-soft)" stroke-width="1" ${isBase ? '' : 'stroke-dasharray="3 4"'}/>
      <text x="${(PAD_SIDES - 8).toFixed(1)}" y="${(gy + 3.5).toFixed(1)}" text-anchor="end" font-size="9.5" style="fill:var(--muted-2);">${level}</text>
    `;
  }).join('');

  const barsHtml = bars.map((b, i) => {
    const x = startX + i * (barWidth + gap);
    const barH = Math.max(2, (b.score / 100) * plotH);
    const y = baseY - barH;
    // Renk onceligi: kismi (sinira takilip yarida kalmis) tarama HER ZAMAN
    // mor - "bu skora guvenme, tarama bitmedi" en onemli sinyal. Tam site
    // taramasi (Tum Siteyi Tara ile, YARIDA KALMAMIS) mavi - normal hizli
    // denetimden ayirt edilsin diye. Ikisi de degilse skor rengine gore
    // (yesil/sari/kirmizi) boyanir.
    const color = b.isPartial ? 'var(--violet)' : b.isFullCrawl ? 'var(--info)' : scoreColor(b.score);
    const isCurrent = b.kind === 'current';
    const rectAttrs = isCurrent
      ? `fill="${color}" fill-opacity="0.15" stroke="${color}" stroke-width="2" stroke-dasharray="4 3"`
      : `fill="${color}"`;
    const labelText = escapeHtml(b.label);

    return `
      <g>
        <rect x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${barWidth.toFixed(1)}" height="${barH.toFixed(1)}" rx="4" ${rectAttrs}>
          <title>${escapeHtml(b.fullLabel)}${b.timeLabel ? ' ' + escapeHtml(b.timeLabel) : ''} — ${b.score}/100${b.isPartial ? ' (kısmi tarama)' : b.isFullCrawl ? ' (tam site taraması)' : ''}</title>
        </rect>
        <text x="${(x + barWidth / 2).toFixed(1)}" y="${(y - 8).toFixed(1)}" text-anchor="middle" font-size="12" font-weight="700" style="fill:${color};">${b.score}</text>
        <text x="${(x + barWidth / 2).toFixed(1)}" y="${(baseY + 18).toFixed(1)}" text-anchor="middle" font-size="10.5" style="fill:var(--muted);">${labelText}</text>
        ${b.timeLabel ? `<text x="${(x + barWidth / 2).toFixed(1)}" y="${(baseY + 32).toFixed(1)}" text-anchor="middle" font-size="9.5" style="fill:var(--muted-2);">${escapeHtml(b.timeLabel)}</text>` : ''}
      </g>
    `;
  }).join('');

  const trendPoints = bars.map((b, i) => {
    const x = startX + i * (barWidth + gap) + barWidth / 2;
    const y = baseY - Math.max(2, (b.score / 100) * plotH);
    return { x, y };
  });
  const trendPolylineHtml = trendPoints.length > 1
    ? `<polyline points="${trendPoints.map(p => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ')}" fill="none" stroke="var(--ink-soft)" stroke-width="1.75" stroke-linejoin="round" stroke-linecap="round" opacity="0.45"/>` +
      trendPoints.map(p => `<circle cx="${p.x.toFixed(1)}" cy="${p.y.toFixed(1)}" r="2.5" fill="var(--ink-soft)" opacity="0.55"/>`).join('')
    : '';

  const legendLines = [];
  if (current) {
    legendLines.push('Dolu çubuklar = kaydedilmiş geçmiş denetimler · kesikli çerçeveli çubuk = şu anki (henüz kaydedilmemiş olabilir) denetim');
  }
  if (bars.some(b => b.isPartial)) {
    legendLines.push('Mor çubuklar = kısmi (sayfa/süre sınırına takılıp yarıda kalmış) tarama — skoru tüm siteyi yansıtmayabilir');
  }
  if (bars.some(b => b.isFullCrawl && !b.isPartial)) {
    legendLines.push('Mavi çubuklar = "Tüm Siteyi Tara" ile tamamlanmış, tüm siteyi kapsayan denetim');
  }
  // Mor/mavi disindaki cubuklar (standart moddaki, sinira takilmamis
  // denetimler) skor rengine gore boyaniyor (bkz. scoreColor) - bu renklerin
  // ne anlama geldigi eskiden hic aciklanmiyordu, sadece mor/mavi kosullu
  // olarak eklenince "yesil ne demek" belirsiz kaliyordu.
  if (bars.some(b => !b.isPartial && !b.isFullCrawl)) {
    legendLines.push('Yeşil/sarı/kırmızı çubuklar = standart "Canlı Denetim Yap" ile, sınıra takılmadan tamamlanmış denetim — renk skora göre değişir (yeşil: iyi, sarı: orta, kırmızı: düşük)');
  }
  const legend = legendLines.length
    ? `<div class="small muted mt-8">${legendLines.map(escapeHtml).join('<br>')}</div>`
    : '';

  wrap.innerHTML =
    `<div style="overflow-x:auto; padding-bottom:4px;">` +
      `<svg viewBox="0 0 ${W} ${H}" width="${W}" height="${H}" style="display:block; max-height:280px;">` +
        gridHtml +
        barsHtml +
        trendPolylineHtml +
      '</svg>' +
    '</div>' +
    (rows.length ? `<div class="small muted mt-8">Son ${rows.length} anlık görüntü — en yeni kayıt: ${rows[rows.length - 1].final_score}/100</div>` : '') +
    legend;
}

function renderHistoryCategoryTables(rows, current) {
  const wrap = document.getElementById('t3-history-category-tables');
  if (!wrap) return;

  const currentByKey = {};
  if (current && Array.isArray(current.score.category_scores)) {
    current.score.category_scores.forEach(cat => { currentByKey[cat.key] = cat.score; });
  }

  wrap.innerHTML = T3_HISTORY_CATEGORIES.map(catDef => {
    const historyRows = rows.map(r => {
      const dt = r.created_at ? new Date(r.created_at.replace(' ', 'T')) : null;
      const dateText = dt ? dt.toLocaleDateString('tr-TR') + ' ' + dt.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' }) : '—';
      return {
        date: dateText,
        score: Number(r[catDef.column] ?? 0),
      };
    });
    const currentScore = currentByKey[catDef.key];

    const rowsHtml = historyRows.map(hr => `
      <div class="finding-card__item">
        <span class="small muted">${escapeHtml(hr.date)}</span>
        <span class="small" style="font-weight:600; color:${scoreColor(hr.score)};">${hr.score}/100</span>
      </div>
    `).join('');

    const currentRowHtml = currentScore !== undefined
      ? `<div class="finding-card__item" style="border-top:1px dashed var(--border-soft); padding-top:8px; margin-top:4px;">
          <span class="small" style="font-weight:700;">Şu an</span>
          <span class="small" style="font-weight:700; color:${scoreColor(currentScore)};">${currentScore}/100</span>
        </div>`
      : '';

    const hasItems = historyRows.length > 0 || !!currentRowHtml;

    return `
      <div class="card finding-card finding-card--info${hasItems ? ' finding-card--expandable' : ''}" style="margin:0;">
        <div class="finding-card__title" style="margin-top:0;">${escapeHtml(catDef.label)}</div>
        ${hasItems ? `<button type="button" class="finding-card__toggle-btn" aria-expanded="false">${historyRows.length} kayıt — göster <span class="finding-card__chevron">▾</span></button>` : '<div class="small muted">Henüz veri yok.</div>'}
        ${hasItems ? `<div class="finding-card__items hidden">${rowsHtml}${currentRowHtml}</div>` : ''}
      </div>
    `;
  }).join('');

  // 6 kategori kartini 2 satir x 3 sutun grid'e yerlestiriyoruz (bkz.
  // css/style.css .grid/.grid-3 - 960px altinda otomatik tek sutuna duser).
  wrap.innerHTML = `<div class="grid grid-3">${wrap.innerHTML}</div>`;
}


/* ============================================================
   TEKNIK SEO RAPORU (PDF) - Sadece Pure JS/HTML/CSS.
   ------------------------------------------------------------
   KURAL: html2pdf.js, html2canvas, jsPDF, Dompdf, TCPDF, mPDF, FPDF
   vb. HICBIR harici PDF kutuphanesi kullanilmiyor - proje baska bir
   modul icin html2pdf.js yuklemis olsa bile (bkz. index.php altindaki
   cdnjs script) burada ona DOKUNMUYORUZ/CAGIRMIYORUZ. Rapor, Pure
   JavaScript ile ayri bir HTML onizleme SEKMESI olarak olusturuluyor
   (window.open + document.write, hicbir kutuphane yok). A4 yazdirma
   duzeni Pure CSS (@page + @media print) ile hazirlaniyor.
   "PDF Olarak Kaydet" butonu SADECE window.print() cagiriyor - gercek
   PDF uretimini tamamen tarayicinin "Yazdirici olarak PDF'e kaydet"
   ozelligine birakiyoruz. Onizleme sekmesindeki arac cubugu (.no-print)
   @media print ile GIZLENIYOR, ana uygulamanin navigasyon/sidebar'i
   zaten bu AYRI pencerede/sekmede hic yok - yani ciktida sadece rapor
   icerigi kaliyor.
   ============================================================ */

const T3_REPORT_CSS = `
:root{ color-scheme: light; }
*{ box-sizing:border-box; }
html,body{ margin:0; padding:0; }
body{
  background:#EEF0F4; color:#12151F;
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  -webkit-font-smoothing:antialiased;
}
.rpt-toolbar{
  position:sticky; top:0; z-index:10; display:flex; align-items:center; justify-content:space-between;
  padding:12px 20px; background:#12151F; color:#fff; box-shadow:0 2px 10px rgba(0,0,0,.15);
}
.rpt-toolbar__title{ font-size:14px; font-weight:700; }
.rpt-toolbar__actions{ display:flex; gap:10px; }
.rpt-toolbar__actions button{
  border:none; border-radius:6px; padding:9px 16px; font-size:13px; font-weight:600; cursor:pointer;
  font-family:inherit;
}
#rpt-print-btn{ background:#0E8F82; color:#fff; }
#rpt-print-btn:hover{ background:#0c7c71; }
#rpt-close-btn{ background:rgba(255,255,255,.12); color:#fff; }
#rpt-close-btn:hover{ background:rgba(255,255,255,.2); }

.rpt-page{
  max-width:210mm; margin:24px auto; background:#fff; padding:18mm 16mm;
  box-shadow:0 4px 24px rgba(18,21,31,.12); border-radius:4px;
}
.rpt-header{ display:flex; justify-content:space-between; align-items:flex-start; gap:16px; border-bottom:2px solid #12151F; padding-bottom:14px; margin-bottom:20px; flex-wrap:wrap; }
.rpt-header__title{ font-size:20px; font-weight:800; }
.rpt-meta{ font-size:12px; color:#4B5160; line-height:1.8; text-align:right; }
.rpt-meta__row strong{ color:#12151F; }

.rpt-summary{ background:#F3FBF9; border:1px solid #CFE8E3; border-left:4px solid #0E8F82; border-radius:8px; padding:16px 18px; margin-bottom:22px; }
.rpt-summary .rpt-section__title{ border-bottom:none; padding-bottom:0; margin-bottom:8px; }
.rpt-summary__text{ font-size:13px; line-height:1.7; margin:0 0 8px; color:#12151F; }
.rpt-summary__text:last-child{ margin-bottom:0; }
.rpt-summary__list{ margin:6px 0 0; padding-left:0; list-style:none; font-size:12.5px; }
.rpt-summary__list li{ display:flex; align-items:center; gap:8px; margin-bottom:6px; }
.rpt-summary__list li:last-child{ margin-bottom:0; }

.rpt-score-hero{ display:flex; align-items:center; gap:20px; margin-bottom:22px; }
.rpt-score-hero__circle{
  width:84px; height:84px; border-radius:50%; border:6px solid; display:flex; align-items:center; justify-content:center;
  font-size:24px; font-weight:800; flex:none;
}
.rpt-score-hero__body{ flex:1; min-width:0; }

.rpt-section{ margin-bottom:22px; }
.rpt-section__title{ font-size:14px; font-weight:700; margin-bottom:10px; padding-bottom:6px; border-bottom:1px solid #E4E6EC; }

.rpt-table{ width:100%; border-collapse:collapse; font-size:12px; }
.rpt-table th{ text-align:left; font-weight:700; color:#4B5160; padding:7px 8px; border-bottom:1.5px solid #12151F; font-size:10.5px; text-transform:uppercase; letter-spacing:.03em; }
.rpt-table td{ padding:7px 8px; border-bottom:1px solid #E4E6EC; }
.rpt-table.mt-8{ margin-top:10px; }

.rpt-badge{ display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; white-space:nowrap; }
.rpt-badge--violet{ background:#EFEAFA; color:#7A5CD0; }
.rpt-badge--info{ background:#E8EEFC; color:#3E6FD9; }
.rpt-badge--muted{ background:#EEF0F4; color:#6B7280; }

.rpt-gate{ background:#FBEAEA; color:#D6484A; padding:8px 10px; border-radius:6px; font-size:12px; font-weight:600; margin-bottom:6px; }
.rpt-note{ font-size:12px; color:#6B7280; }
.rpt-muted{ color:#9AA1B0; }

.rpt-finding{ border-left:4px solid #9AA1B0; background:#FAFBFC; border-radius:0 6px 6px 0; padding:10px 12px; margin-bottom:10px; }
.rpt-finding__head{ display:flex; align-items:center; gap:10px; margin-bottom:6px; font-size:11.5px; flex-wrap:wrap; }
.rpt-finding__title{ font-weight:700; font-size:13px; margin-bottom:3px; }
.rpt-finding__detail{ font-size:12px; color:#4B5160; margin-bottom:5px; }
.rpt-finding__fix{ font-size:12px; }
.rpt-items{ margin:8px 0 0; padding-left:18px; font-size:11.5px; color:#4B5160; }
.rpt-items li{ margin-bottom:2px; word-break:break-all; }

.rpt-footer{ margin-top:28px; padding-top:12px; border-top:1px solid #E4E6EC; font-size:10.5px; color:#9AA1B0; text-align:center; }

@page{ size: A4; margin: 14mm; }
@media print{
  .no-print{ display:none !important; }
  body{ background:#fff; }
  .rpt-page{ box-shadow:none; margin:0; padding:0; max-width:none; border-radius:0; }
  .rpt-finding, .rpt-section, .rpt-score-hero, .rpt-summary{ break-inside:avoid; }
  .rpt-table tr{ break-inside:avoid; }
}
`;

function t3ReportSeverityColor(sev) {
  if (sev === 'critical') return '#D6484A';
  if (sev === 'major') return '#C98A1D';
  return '#9AA1B0';
}

function t3ReportCrawlBadge(isPartial, isFullCrawl) {
  if (isPartial) return '<span class="rpt-badge rpt-badge--violet">Kısmi Tarama</span>';
  if (isFullCrawl) return '<span class="rpt-badge rpt-badge--info">Tam Site Taraması</span>';
  return '<span class="rpt-badge rpt-badge--muted">Standart Denetim</span>';
}

function t3ReportVitalRow(label, mobileVital, desktopVital) {
  const fmt = (v) => (v && v.display_value) ? escapeHtml(v.display_value) : '—';
  return `<tr><td>${escapeHtml(label)}</td><td>${fmt(mobileVital)}</td><td>${fmt(desktopVital)}</td></tr>`;
}

// Denetim sonucu + skor gecmisini TEK bir standalone HTML dokumanina
// (kendi <style>'i, kendi kucuk <script>'i ile) yazan fonksiyon - bu
// string dogrudan yeni pencerenin document'ina yazilir (bkz.
// generateT3Report), disaridan HICBIR css/js dosyasi yuklemez.
// Skoru duz dille nitelendirir - renderMeter/scoreStatus'taki ayni
// esikleri (>=90 iyi, >=50 orta, altı zayif) kullanir, sadece Turkce
// bir sifat donduruyor (rapor ozetinde kullanmak icin).
function t3ReportScoreLabel(v) {
  if (v >= 90) return 'iyi';
  if (v >= 50) return 'orta';
  return 'zayıf';
}

// "Yonetici Ozeti" bolumunu olusturur - ham veriyi (skor, onceki denetime
// gore degisim, kritik kapilar, en oncelikli bulgular) tek bir okunakli
// paragraf + kisa bir listeye cevirir. Amac: raporu acan kisinin (teknik
// olmayan bir musteri de olabilir) once bu kutuyu okuyup 10 saniyede genel
// tabloyu anlamasi - detaylar zaten asagidaki bolumlerde var.
function t3ReportBuildSummaryHtml(score, previousScore, findings) {
  const label = t3ReportScoreLabel(score.final_score);
  const color = scoreColor(score.final_score);

  let deltaSentence = '';
  if (previousScore !== null && previousScore !== undefined) {
    const delta = score.final_score - previousScore;
    if (delta > 0) deltaSentence = `, önceki kaydedilen denetime göre <strong style="color:#1F9D63;">+${delta} puan artış</strong> gösterdi`;
    else if (delta < 0) deltaSentence = `, önceki kaydedilen denetime göre <strong style="color:#D6484A;">${delta} puan düşüş</strong> gösterdi`;
    else deltaSentence = ', önceki kaydedilen denetimle aynı seviyede kaldı';
  }

  let gateSentence = '';
  if (score.gates_triggered && score.gates_triggered.length) {
    gateSentence = ` Ayrıca ${score.gates_triggered.length} kritik kapı kısıtlaması tetiklendi (aşağıda "Genel Teknik SEO Skoru" bölümünde detaylandırılmıştır).`;
  }

  const scoreLineHtml = `<p class="rpt-summary__text"><strong>${escapeHtml(score.final_score)}/100</strong> (<span style="color:${color}; font-weight:700;">${label}</span>) genel teknik SEO skoru ölçüldü${deltaSentence}.${gateSentence}</p>`;

  const topFindings = (findings || []).slice(0, 3);
  const findingsBlockHtml = topFindings.length
    ? `<p class="rpt-summary__text" style="margin-bottom:4px;">En öncelikli ${topFindings.length} konu:</p>
       <ul class="rpt-summary__list">${topFindings.map(f => {
         const c = t3ReportSeverityColor(f.severity);
         return `<li><span class="rpt-badge" style="background:${c}1a; color:${c}; flex:none;">${escapeHtml((T3_SEVERITY_META[f.severity] || T3_SEVERITY_META.minor).label)}</span> ${escapeHtml(f.title)}</li>`;
       }).join('')}</ul>`
    : `<p class="rpt-summary__text" style="margin-bottom:0;">Bu denetimde önceliklendirilmiş bir sorun tespit edilmedi 🎉</p>`;

  return `
    <div class="rpt-summary">
      <div class="rpt-section__title">Yönetici Özeti</div>
      ${scoreLineHtml}
      ${findingsBlockHtml}
    </div>
  `;
}

function buildT3ReportHtml(payload) {
  const { url, score, isPartial, isFullCrawl, psi, client, historyRows, generatedAt, previousScore } = payload;
  const summaryHtml = t3ReportBuildSummaryHtml(score, previousScore, score.findings || []);
  const mobileVitals = (psi && psi.mobile && psi.mobile.web_vitals) || {};
  const desktopVitals = (psi && psi.desktop && psi.desktop.web_vitals) || {};
  const mobileCats = (psi && psi.mobile && psi.mobile.category_scores) || {};
  const desktopCats = (psi && psi.desktop && psi.desktop.category_scores) || {};

  const catRowsHtml = (score.category_scores || []).map(cat => `
    <tr>
      <td>${escapeHtml(cat.label)}</td>
      <td style="text-align:center;">%${escapeHtml(cat.weight_percent)}</td>
      <td style="text-align:center;">${escapeHtml(cat.confidence)}</td>
      <td style="text-align:right; font-weight:700; color:${scoreColor(cat.score)};">${cat.score}/100</td>
    </tr>
  `).join('');

  const gatesHtml = (score.gates_triggered && score.gates_triggered.length)
    ? score.gates_triggered.map(g => `<div class="rpt-gate">⚠ Kritik kapı tetiklendi — skor en fazla ${escapeHtml(g.cap)}: ${escapeHtml(g.message)}</div>`).join('')
    : `<div class="rpt-note">Ağırlıklı ortalama: ${escapeHtml(score.weighted_average_before_gates)} — hiçbir kritik kapı tetiklenmedi, nihai skor ağırlıklı ortalamayla aynı.</div>`;

  const findings = score.findings || [];
  const findingsHtml = findings.length
    ? findings.map(f => {
        const items = Array.isArray(f.items) ? f.items : [];
        const shown = items.slice(0, 5);
        const itemsHtml = shown.length
          ? `<ul class="rpt-items">${shown.map(it => `<li>${escapeHtml(it.url)} <span class="rpt-muted">(${escapeHtml(it.status)})</span></li>`).join('')}${items.length > shown.length ? `<li class="rpt-muted">+ ${items.length - shown.length} tane daha…</li>` : ''}</ul>`
          : '';
        return `
          <div class="rpt-finding" style="border-left-color:${t3ReportSeverityColor(f.severity)};">
            <div class="rpt-finding__head">
              <span class="rpt-badge" style="background:${t3ReportSeverityColor(f.severity)}1a; color:${t3ReportSeverityColor(f.severity)};">${escapeHtml((T3_SEVERITY_META[f.severity] || T3_SEVERITY_META.minor).label)}</span>
              <span class="rpt-muted">güven: ${escapeHtml(f.confidence)} · etkilenen sayfa: ${escapeHtml(f.affected_pages)}</span>
            </div>
            <div class="rpt-finding__title">${escapeHtml(f.title)}</div>
            <div class="rpt-finding__detail">${escapeHtml(f.detail)}</div>
            <div class="rpt-finding__fix"><strong>Nasıl düzeltilir:</strong> ${escapeHtml(f.how_to_fix)}</div>
            ${itemsHtml}
          </div>
        `;
      }).join('')
    : '<div class="rpt-note">Herhangi bir sorun tespit edilmedi.</div>';

  // Skor gecmisi API'den en yeniden en eskiye (DESC) geliyor - raporda
  // kronolojik (eskiden yeniye) okunsun ve "onceki skora gore degisim"
  // doğru hesaplansin diye ters ceviriyoruz (renderScoreHistory ile ayni
  // mantik).
  const chronological = (historyRows || []).slice().reverse();
  let prevScore = null;
  const historyRowsHtml = chronological.map(r => {
    const dt = r.created_at ? new Date(String(r.created_at).replace(' ', 'T')) : null;
    const dateText = dt ? (dt.toLocaleDateString('tr-TR') + ' ' + dt.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' })) : '—';
    const s = Number(r.final_score);
    let deltaHtml = '<span class="rpt-muted">—</span>';
    if (prevScore !== null) {
      const d = s - prevScore;
      if (d > 0) deltaHtml = `<span style="color:#1F9D63; font-weight:700;">▲ ${d}</span>`;
      else if (d < 0) deltaHtml = `<span style="color:#D6484A; font-weight:700;">▼ ${Math.abs(d)}</span>`;
      else deltaHtml = '<span class="rpt-muted">— 0</span>';
    }
    prevScore = s;
    const crawlLabel = Number(r.is_partial) ? 'Kısmi' : (Number(r.is_full_crawl) ? 'Tam Site' : 'Standart');
    return `
      <tr>
        <td>${dateText}</td>
        <td style="text-align:right; font-weight:700; color:${scoreColor(s)};">${s}/100</td>
        <td>${escapeHtml(crawlLabel)}</td>
        <td style="text-align:right;">${deltaHtml}</td>
      </tr>
    `;
  }).join('');

  const historySectionHtml = chronological.length
    ? `<table class="rpt-table"><thead><tr><th>Tarih</th><th style="text-align:right;">Skor</th><th>Tarama Türü</th><th style="text-align:right;">Değişim</th></tr></thead><tbody>${historyRowsHtml}</tbody></table>`
    : '<div class="rpt-note">Bu site/müşteri için henüz kaydedilmiş geçmiş bir denetim yok.</div>';

  const clientLineHtml = client
    ? `<div class="rpt-meta__row"><strong>Müşteri:</strong> ${escapeHtml(client.name)}</div>`
    : '';

  return `<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Teknik SEO Raporu — ${escapeHtml(url)}</title>
<style>${T3_REPORT_CSS}</style>
</head>
<body>
  <div class="no-print rpt-toolbar">
    <span class="rpt-toolbar__title">Teknik SEO Raporu Önizleme</span>
    <div class="rpt-toolbar__actions">
      <button type="button" id="rpt-print-btn">PDF Olarak Kaydet</button>
      <button type="button" id="rpt-close-btn">Kapat</button>
    </div>
  </div>

  <div class="rpt-page">
    <div class="rpt-header">
      <div class="rpt-header__title">Teknik SEO Denetim Raporu</div>
      <div class="rpt-meta">
        <div class="rpt-meta__row"><strong>Site:</strong> ${escapeHtml(url)}</div>
        ${clientLineHtml}
        <div class="rpt-meta__row"><strong>Oluşturulma:</strong> ${escapeHtml(generatedAt)}</div>
        <div class="rpt-meta__row">${t3ReportCrawlBadge(isPartial, isFullCrawl)}</div>
      </div>
    </div>

    ${summaryHtml}

    <div class="rpt-score-hero">
      <div class="rpt-score-hero__circle" style="border-color:${scoreColor(score.final_score)};">
        <span style="color:${scoreColor(score.final_score)};">${score.final_score}</span>
      </div>
      <div class="rpt-score-hero__body">
        <div class="rpt-section__title" style="margin:0 0 6px; border-bottom:none; padding-bottom:0;">Genel Teknik SEO Skoru</div>
        ${gatesHtml}
      </div>
    </div>

    <div class="rpt-section">
      <div class="rpt-section__title">Kategori Kırılımı</div>
      <table class="rpt-table">
        <thead><tr><th>Kategori</th><th style="text-align:center;">Ağırlık</th><th style="text-align:center;">Güven</th><th style="text-align:right;">Skor</th></tr></thead>
        <tbody>${catRowsHtml}</tbody>
      </table>
    </div>

    <div class="rpt-section">
      <div class="rpt-section__title">Web Vitals (Hız Metrikleri)</div>
      <table class="rpt-table">
        <thead><tr><th>Metrik</th><th>Mobil</th><th>Masaüstü</th></tr></thead>
        <tbody>
          ${t3ReportVitalRow('LCP — Largest Contentful Paint', mobileVitals.lcp, desktopVitals.lcp)}
          ${t3ReportVitalRow('FCP — First Contentful Paint', mobileVitals.fcp, desktopVitals.fcp)}
          ${t3ReportVitalRow('CLS — Cumulative Layout Shift', mobileVitals.cls, desktopVitals.cls)}
          ${t3ReportVitalRow('TTFB — Sunucu Yanıt Süresi', mobileVitals.ttfb, desktopVitals.ttfb)}
          ${t3ReportVitalRow('INP — Etkileşim', mobileVitals.inp, desktopVitals.inp)}
        </tbody>
      </table>
      <table class="rpt-table mt-8">
        <thead><tr><th>Lighthouse Kategorisi</th><th style="text-align:right;">Mobil</th><th style="text-align:right;">Masaüstü</th></tr></thead>
        <tbody>
          <tr><td>Performans</td><td style="text-align:right;">${mobileCats.performance ?? '—'}</td><td style="text-align:right;">${desktopCats.performance ?? '—'}</td></tr>
          <tr><td>SEO</td><td style="text-align:right;">${mobileCats.seo ?? '—'}</td><td style="text-align:right;">${desktopCats.seo ?? '—'}</td></tr>
          <tr><td>Erişilebilirlik</td><td style="text-align:right;">${mobileCats.accessibility ?? '—'}</td><td style="text-align:right;">${desktopCats.accessibility ?? '—'}</td></tr>
          <tr><td>En İyi Uygulamalar</td><td style="text-align:right;">${mobileCats['best-practices'] ?? '—'}</td><td style="text-align:right;">${desktopCats['best-practices'] ?? '—'}</td></tr>
        </tbody>
      </table>
    </div>

    <div class="rpt-section">
      <div class="rpt-section__title">Önceliklendirilmiş Bulgular (${findings.length})</div>
      ${findingsHtml}
    </div>

    <div class="rpt-section">
      <div class="rpt-section__title">Skor Geçmişi</div>
      ${historySectionHtml}
    </div>

    <div class="rpt-footer">Teknik SEO Raporu · ${escapeHtml(generatedAt)}</div>
  </div>

  <script>
    // Bilerek SADECE window.print() - PDF uretimi tamamen tarayicinin
    // kendi "Yazdirici olarak PDF'e kaydet" secenegine birakiliyor,
    // hicbir PDF kutuphanesi yok.
    document.getElementById('rpt-print-btn').addEventListener('click', function () { window.print(); });
    document.getElementById('rpt-close-btn').addEventListener('click', function () { window.close(); });
  </script>
</body>
</html>`;
}

// "Rapor Oluştur (PDF)" butonuna basilinca cagrilir. Skor gecmisini de
// (varsa bu site/musterinin domain'i uzerinden) canli cekip TEK raporda
// birlestiriyor - kullanici hangi sekmede olursa olsun (Canli Denetim ya
// da Skor Gecmisi) rapor her zaman guncel + eksiksiz olsun diye.
async function generateT3Report() {
  if (!t3HistoryCurrentUrl || !t3HistoryCurrentScore) {
    showToast('Önce bir denetim tamamlanmalı.', 'error');
    return;
  }

  // Pencere, tiklama olayi islenirken HEMEN (senkron) aciliyor - tarayici
  // pop-up engelleyicileri butona tiklamadan HEMEN sonra acilan pencereleri
  // engellemez, ama asagidaki "await fetch(...)"tan SONRA acilsaydi
  // engellenebilirdi. Icerik hazir olunca AYNI pencereye yaziliyor.
  const reportWindow = window.open('', '_blank');
  if (!reportWindow) {
    showToast('Rapor penceresi açılamadı — tarayıcının pop-up engelleyicisini kontrol et.', 'error');
    return;
  }
  reportWindow.document.write('<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Rapor hazırlanıyor…</title></head><body style="font-family:sans-serif; padding:40px; color:#6B7280;">Rapor hazırlanıyor…</body></html>');
  reportWindow.document.close();

  let client = null;
  let historyRows = [];
  try {
    client = await findClientByUrl(t3HistoryCurrentUrl);
    const qs = client
      ? 'client_id=' + encodeURIComponent(client.id) + '&domain=' + encodeURIComponent(normalizeDomain(t3HistoryCurrentUrl))
      : 'url=' + encodeURIComponent(t3HistoryCurrentUrl);
    const res = await fetch('api/technical_score_history.php?' + qs);
    const json = await res.json();
    historyRows = json.data || [];
  } catch (err) {
    console.error('[TeknikSEO] Rapor için skor geçmişi alınamadı:', err);
  }

  // Yonetici ozetindeki "onceki denetime gore degisim" cumlesi icin -
  // historyRows en yeniden en eskiye (DESC) geliyor. Eger su anki sonuc
  // ZATEN kaydedildiyse (t3HistorySavedForCurrent), en yeni kayit kendisi
  // olur - o durumda "onceki" olarak ondan BIR ONCEKI kaydi kullanmamiz
  // gerekir, yoksa degisim hep "0" cikar (kendisiyle kiyaslanmis olur).
  const prevIdx = t3HistorySavedForCurrent ? 1 : 0;
  const previousScore = historyRows[prevIdx] ? Number(historyRows[prevIdx].final_score) : null;

  const html = buildT3ReportHtml({
    url: t3HistoryCurrentUrl,
    score: t3HistoryCurrentScore,
    isPartial: t3HistoryCurrentIsPartial,
    isFullCrawl: t3HistoryCurrentIsFullCrawl,
    psi: t3LastPsiData,
    client,
    historyRows,
    previousScore,
    generatedAt: new Date().toLocaleString('tr-TR'),
  });

  reportWindow.document.open();
  reportWindow.document.write(html);
  reportWindow.document.close();
}

document.getElementById('t3-report-btn')?.addEventListener('click', generateT3Report);
