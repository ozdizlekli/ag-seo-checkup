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
    label.textContent = 'Canlı Denetim Yap';
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

// "Tüm siteyi tara" tek istekte sunucunun full_max_time_seconds (90 sn) /
// full_max_pages (2000 sayfa) sınırına takılıp yine 'truncated' dönebilir.
// Kullanıcının aynı butona defalarca elle basmasını istemek yerine, bu
// durumda OTOMATİK olarak (resume_state'i kendi kendine güncelleyip)
// kaldığı yerden yeni bir istek daha atarız - sekmenin süresiz dönmesini
// önlemek için istemci tarafında toplam bir üst süre koyuyoruz; bu süre
// dolduğunda (nadir - çok büyük siteler) "Tarama Kısmi Kaldı" kartı
// tekrar çıkar, kullanıcı isterse manuel olarak devam ettirebilir.
const T3_FULLCRAWL_AUTO_MAX_MS = 2.5 * 60 * 1000; // 2,5 dakika

async function runFullSiteCrawl() {
  if (!t3PendingResume) return;

  const btn = document.getElementById('t3-fullcrawl-btn');
  const label = document.getElementById('t3-fullcrawl-label');
  const note = document.getElementById('t3-fullcrawl-note');
  btn.disabled = true;
  label.innerHTML = '<span class="spinner"></span>';

  const autoStartedAt = Date.now();
  let current = t3PendingResume;
  let data = null;

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

      const structure = data.site_structure || {};
      const linkCheck = data.link_check || {};
      // resume_state artik SADECE site yapisi kesildiginde degil, link
      // kontrolu hala bekleyen yeni link varken de doluyor (bkz. backend
      // 'link_check.truncated') - o yuzden devam etme kararini dogrudan
      // resume_state'in varligina dayandiriyoruz, sadece structure.truncated'a degil.
      const linkCheckPending = !!linkCheck.truncated;
      const canAutoContinue = structure.crawl_mode === 'full' && !!structure.resume_state && (structure.truncated || linkCheckPending);
      const withinAutoBudget = (Date.now() - autoStartedAt) < T3_FULLCRAWL_AUTO_MAX_MS;

      if (canAutoContinue && withinAutoBudget) {
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
  renderFindings(data.score ? data.score.findings : []);
  showFullCrawlPrompt(data.url || inputUrl, data);

  document.getElementById('t3-quick-audit-card').classList.remove('hidden');
  document.getElementById('t3-composite-score-card').classList.remove('hidden');
  document.getElementById('t3-findings-card').classList.remove('hidden');

  renderPsiErrors(data.psi);
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

function renderFindings(findings) {
  const el = document.getElementById('t3-findings-body');

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

    const toggleHtml = hasItems
      ? `<button type="button" class="finding-card__toggle-btn" aria-expanded="false">${items.length} link — göster <span class="finding-card__chevron">▾</span></button>`
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
  const displayValue = (vital && vital.display_value) || '—';
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
