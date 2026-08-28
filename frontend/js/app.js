
(function(){
"use strict";


/* ============================================================
   GOOGLE OAUTH (Search Console / GA4 / Drive)
    Google Cloud Console'dan aldığım OAuth Client ID

============================================================ */
const GOOGLE_CLIENT_ID = '274638974684-pfl8vepd2bkl5vqgg71chdc2212so5e4.apps.googleusercontent.com';
const GOOGLE_SCOPES = [
  'https://www.googleapis.com/auth/webmasters.readonly',
  'https://www.googleapis.com/auth/analytics.readonly',
  'https://www.googleapis.com/auth/drive',
].join(' ');

let googleAccessToken = null;
let gisTokenClient = null;

function initGoogleAuth(){
  if(!window.google || !window.google.accounts || !window.google.accounts.oauth2){
    // GIS script henüz yüklenmemiş olabilir, biraz sonra tekrar dene
    setTimeout(initGoogleAuth, 500);
    return;
  }
  if(GOOGLE_CLIENT_ID.includes('YOUR_CLIENT_ID')){
    return; // Kurulum yapılmamış, sessizce çık
  }
  gisTokenClient = google.accounts.oauth2.initTokenClient({
    client_id: GOOGLE_CLIENT_ID,
    scope: GOOGLE_SCOPES,
    callback: (resp) => {
      if(resp.error){
        showToast('Google bağlantısı başarısız: ' + resp.error, 'error');
        return;
      }
      googleAccessToken = resp.access_token;
      const statusEl = document.getElementById('google-auth-status');
      statusEl.classList.add('connected');
      statusEl.innerHTML = '<span class="dot"></span> Bağlı ✓';
      showToast('Google hesabı bağlandı.', 'success');
    },
  });
}
window.addEventListener('load', initGoogleAuth);

document.getElementById('google-connect-btn')?.addEventListener('click', () => {
  if(GOOGLE_CLIENT_ID.includes('YOUR_CLIENT_ID')){
    showToast('Önce GOOGLE_CLIENT_ID ayarlanmalı (bkz. GOOGLE-OAUTH-KURULUM.md).', 'error');
    return;
  }
  if(!gisTokenClient){
    showToast('Google kimlik servisi henüz yüklenmedi, birkaç saniye sonra tekrar deneyin.', 'error');
    return;
  }
  gisTokenClient.requestAccessToken();
});

function requireGoogleAuth(){
  if(!googleAccessToken){
    showToast('Önce sol menüden Google hesabınızı bağlayın.', 'error');
    return false;
  }
  return true;
}

/* ============================================================
   GLOBAL STATE
============================================================ */
const state = {
  contentArchive: [],
  t7Images: [],   // {id, name, dataUrl}
  technicalScore: null,
  schemaGenerated: false,
  contentScore: null,
  keywordScore: null,
  clients: [],          // Supabase clients tablosundan gelen liste
  currentClientId: null,
  currentClient: null,  // seçili müşterinin tam kaydı (gsc/ga4/drive alanları dahil)
  lastWordReport: null, // { blob, filename } — "Word Olarak İndir" tıklanınca doldurulur
  lastScores: null,     // computeAndRenderScore() tarafından doldurulur
  lastGSC: null,         // Search Console'dan çekilen son veri (rapor için)
  lastGA4: null,         // GA4'ten çekilen son veri (rapor için)
};

/* ============================================================
   TOAST
============================================================ */
function showToast(message, type){
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  toast.className = 'toast ' + (type === 'error' ? 'error' : 'success');
  toast.innerHTML = '<span class="dot-toast" style="width:6px;height:6px;border-radius:50%;background:#fff;display:inline-block;"></span><span>' + message + '</span>';
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity .25s';
    setTimeout(() => toast.remove(), 260);
  }, 2600);
}

/* ============================================================
   MÜŞTERİ YÖNETİMİ (Çok Müşterili Yapı)
============================================================ */
async function fetchClients(){
  try{
    const res = await fetch('api/clients.php'); const { data, error } = await res.json();
    if(error) throw error;
    state.clients = data || [];
    renderClientSelect();
  }catch(err){
    console.error('[Supabase] clients select hatası:', err);
    showToast('Müşteri listesi yüklenemedi. supabase-migration.sql çalıştırıldı mı?', 'error');
  }
}

// Sidebar "Aktif Musteri" secici artik native <select> degil, yazarak
// aranabilen bir search-select (bkz. wireSidebarClientSearchSelect asagida).
// Gorunen metin kutusunun id'si 'client-select-input', ama gercek secili
// musteri id'sini tasiyan gizli input HALA 'client-select' id'sinde - boylece
// bu dosyadaki (ve technical-seo.js'teki) client-select'in .value'sunu okuyan/
// yazan ve 'change' olayini dinleyen TUM mevcut kod DEGISMEDEN calismaya
// devam ediyor; sadece gorunen metni ayrica senkronlamamiz gerekiyor.
function renderClientSelect(){
  const hidden = document.getElementById('client-select');
  const visible = document.getElementById('client-select-input');
  const stillExists = hidden.value && state.clients.some(c => String(c.id) === String(hidden.value));
  if (!stillExists) hidden.value = '';
  const selected = hidden.value ? state.clients.find(c => String(c.id) === String(hidden.value)) : null;
  if (visible) visible.value = selected ? selected.name : '';
}

// technical-seo.js'teki wireClientSearchSelect ile ayni gorsel/etkilesim
// davranisi (odaklaninca tam liste, yazinca filtrele, tiklayinca sec, disari
// tiklayinca kapat) - ama listeyi ORADAKI ayri/cache'lenen musteri listesi
// yerine buradaki state.clients'tan besliyoruz ki musteri ekleme/duzenleme/
// silme sonrasi liste (ayri bir cache'i manuel gecersiz kilmaya gerek kalmadan)
// HER ZAMAN güncel kalsin.
function wireSidebarClientSearchSelect() {
  const input = document.getElementById('client-select-input');
  const hidden = document.getElementById('client-select');
  const list = document.getElementById('client-select-list');
  const wrap = document.getElementById('client-searchselect');
  if (!input || !hidden || !list) return;

  const renderList = (query) => {
    const q = (query || '').trim().toLowerCase();
    const sorted = state.clients.slice().sort((a, b) => (a.name || '').localeCompare(b.name || '', 'tr'));
    const filtered = q
      ? sorted.filter(c => (c.name || '').toLowerCase().includes(q) || (c.domain_url || '').toLowerCase().includes(q))
      : sorted;
    list.innerHTML = filtered.length
      ? filtered.map(c => `<div class="search-select__item" data-id="${c.id}" data-name="${escapeHtml(c.name)}">${escapeHtml(c.name)}${c.domain_url ? `<span class="small muted"> — ${escapeHtml(c.domain_url)}</span>` : ''}</div>`).join('')
      : '<div class="search-select__item search-select__item--empty">Eşleşen müşteri yok</div>';
  };

  // wrap'a 'is-open' eklenip cikarilmasi SADECE gorsel - acilir listenin
  // ok ikonunu (search-select__chevron, bkz. css) dondurmek icin; liste
  // acma/kapama mantiginin kendisini etkilemiyor.
  input.addEventListener('focus', () => { renderList(''); list.classList.remove('hidden'); wrap?.classList.add('is-open'); });
  input.addEventListener('input', () => { hidden.value = ''; renderList(input.value); list.classList.remove('hidden'); wrap?.classList.add('is-open'); });
  list.addEventListener('mousedown', (e) => {
    const item = e.target.closest('.search-select__item[data-id]');
    if (!item) return;
    e.preventDefault();
    input.value = item.dataset.name;
    hidden.value = item.dataset.id;
    list.classList.add('hidden');
    wrap?.classList.remove('is-open');
    hidden.dispatchEvent(new Event('change'));
  });
  document.addEventListener('click', (e) => {
    if (e.target === input || list.contains(e.target)) return;
    list.classList.add('hidden');
    wrap?.classList.remove('is-open');
  });
}
wireSidebarClientSearchSelect();

document.getElementById('client-add-btn')?.addEventListener('click', async () => {
  const name = prompt('Yeni müşteri adı:');
  if(!name || !name.trim()) return;
  
  let domainUrl = prompt('Müşterinin Kök URL\'si (Örn: https://adresgezgini.com):');
  if(domainUrl) {
    if(!domainUrl.startsWith('http')) domainUrl = 'https://' + domainUrl;
  }
  
  try{
    const payload = { name: name.trim() };
    if (domainUrl && domainUrl.trim()) payload.domain_url = domainUrl.trim();
    
    const res = await fetch('api/clients.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)}); const { data, error } = await res.json();
    if(error) throw error;
    await fetchClients();
    if(data && data[0]){
      document.getElementById('client-select').value = data[0].id;
      document.getElementById('client-select').dispatchEvent(new Event('change'));
    }
    showToast('Müşteri eklendi: ' + name, 'success');
  }catch(err){
    console.error('[Supabase] clients insert hatası:', err);
    if(err.message && err.message.includes('domain_url')) {
      showToast('Lütfen Supabase SQL Editor üzerinden "domain_url" sütununu ekleyin: ALTER TABLE clients ADD COLUMN domain_url TEXT;', 'error');
    } else {
      showToast('Müşteri eklenemedi: ' + (err.message || 'bilinmeyen hata'), 'error');
    }
  }
});

document.getElementById('client-edit-btn')?.addEventListener('click', async () => {
  if(!state.currentClientId) { showToast('Düzenlemek için önce bir müşteri seçin.', 'error'); return; }
  const newName = prompt('Müşteri adı:', state.currentClient.name);
  if(newName === null) return;
  const newDomain = prompt('Müşterinin Kök URL\'si (Örn: https://adresgezgini.com):', state.currentClient.domain_url || '');
  if(newDomain === null) return;
  
  let processedDomain = newDomain.trim();
  if(processedDomain && !processedDomain.startsWith('http')) processedDomain = 'https://' + processedDomain;
  
  try {
    const payload = { name: newName.trim() };
    if(processedDomain) payload.domain_url = processedDomain;
    else payload.domain_url = null;
    
    const res = await fetch('api/clients.php?id='+state.currentClientId, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)}); const json = await res.json(); const error = json.error;
    if(error) throw error;
    await fetchClients();
    document.getElementById('client-select').dispatchEvent(new Event('change'));
    showToast('Müşteri güncellendi.', 'success');
  } catch(err) {
    showToast('Güncelleme başarısız: ' + err.message, 'error');
  }
});

function resetAllForms() {
  document.querySelectorAll('input[type="text"], input[type="number"], textarea').forEach(el => el.value = '');
  document.querySelectorAll('input[type="checkbox"]').forEach(el => el.checked = false);
  document.querySelectorAll('select.input').forEach(el => el.selectedIndex = 0);
  document.querySelectorAll('.code-block code').forEach(el => el.textContent = '');
  document.querySelectorAll('.cluster-col').forEach(el => el.innerHTML = '');
  document.querySelectorAll('.num').forEach(el => el.innerHTML = '—');
  document.getElementById('t4-output-wrap')?.classList.add('hidden');
  document.getElementById('t3-schema-results')?.classList.add('hidden');
  document.getElementById('t4-master-llmstxt-wrap')?.classList.add('hidden');
}

document.getElementById('client-delete-btn')?.addEventListener('click', async () => {
  if(!state.currentClientId) { showToast('Silmek için önce bir müşteri seçin.', 'error'); return; }
  const confirmDel = confirm(`"${state.currentClient.name}" müşterisini silmek istediğinize emin misiniz? (Bu işlem geri alınamaz)`);
  if(!confirmDel) return;
  
  try {
    const res = await fetch('api/clients.php?id='+state.currentClientId, {method:'DELETE'}); const json = await res.json(); const error = json.error;
    if(error) throw error;
    await fetchClients();
    document.getElementById('client-select').value = '';
    document.getElementById('client-select').dispatchEvent(new Event('change'));
    showToast('Müşteri silindi.', 'success');
  } catch(err) {
    showToast('Silme işlemi başarısız: ' + err.message, 'error');
  }
});

document.getElementById('client-select')?.addEventListener('change', async (e) => {
  const id = e.target.value;
  // Ekle/Düzenle/Sil butonları hidden input'un .value'sunu programatik
  // olarak set edip 'change' dispatch ediyor (liste tıklamasından farklı
  // olarak) - görünen metin kutusu da burada senkronlanmazsa ör. yeni
  // müşteri eklendiğinde kutuda hiçbir isim görünmez.
  const visibleClientInput = document.getElementById('client-select-input');
  if (visibleClientInput) {
    const selectedForDisplay = id ? state.clients.find(c => String(c.id) === id) : null;
    visibleClientInput.value = selectedForDisplay ? selectedForDisplay.name : '';
  }
  state.currentClientId = id || null;
  // ÖNEMLİ DÜZELTME: c.id veritabanından PHP int olarak gelir (json'da sayı),
  // ama <select> elemanının e.target.value'su HER ZAMAN string döner. Eskiden
  // burada "===" ile karşılaştırılıyordu (ör. 5 === "5" -> false), bu yüzden
  // state.currentClient HER SEÇİMDE null kalıyordu - "Düzenle"/"Sil"
  // butonlarının çalışmamasının, sidebar domain/sitemap explorer'ın hiç
  // görünmemesinin ve URL otomatik doldurmanın çalışmamasının kök sebebi buydu.
  state.currentClient = id ? state.clients.find(c => String(c.id) === id) || null : null;
  resetAllForms();
  
  const domainEl = document.getElementById('sidebar-client-domain');
  const explorerEl = document.getElementById('sidebar-site-explorer');
  
  const headerName = document.getElementById('top-header-client-name');
  if (state.currentClient) {
    if (headerName) headerName.textContent = state.currentClient.name;
  } else {
    if (headerName) headerName.textContent = 'Bekleniyor...';
  }

  if(state.currentClient && state.currentClient.domain_url) {
    domainEl.style.display = 'block';
    domainEl.textContent = 'Ana Domain: ' + state.currentClient.domain_url;
    // Sitemap Explorer artık kendi pure-PHP api/sitemap.php endpoint'imizi
    // kullanıyor (eskiden var olmayan localhost:3000 Node servisine
    // bağımlıydı, bu yüzden her zaman "Failed to fetch" veriyordu).
    explorerEl.hidden = false;
    loadClientSitemap(state.currentClient.domain_url);
    
    // Auto-fill AI SEO URL input
    const aiSeoInput = document.getElementById('aiseo-url-input');
    if (aiSeoInput) aiSeoInput.value = state.currentClient.domain_url;
    const wcSeoInput = document.getElementById('wc-url-input');
    if (wcSeoInput) wcSeoInput.value = state.currentClient.domain_url;
    
  } else {
    sitemapLoadSequence++;
    domainEl.style.display = 'none';
    domainEl.textContent = '';
    explorerEl.hidden = true;
    const sitemapBadge = document.getElementById('sitemap-url-count');
    if (sitemapBadge) sitemapBadge.hidden = true;
    const sitemapTree = document.getElementById('site-explorer-tree');
    if (sitemapTree) sitemapTree.innerHTML = '<div class="t3-sitemap__empty">Müşteri seçiniz...</div>';
  }

  // YENİ: müşteri seçildiğinde (kök URL'si tanımlıysa) ilgili sekmelerdeki
  // URL alanlarını otomatik doldur - hangi sekmede olursak olalım, çünkü
  // kullanıcı seçim anında dolmasını istiyor, sadece o an açık olan sekmede
  // değil (aksi halde başka bir sekmeye geçilince alan boş kalırdı).
  autofillClientUrlFields(state.currentClient?.domain_url || null);

  // Tab 6/7'deki müşteri bağlantı alanları - eski (7 sekmelik) yapıdan kalma,
  // mevcut arayüzde bu elementler artık yok. Element bulunamazsa sessizce
  // atlanır - aksi halde tüm handler burada çökerdi ve altındaki "bu
  // müşterinin geçmişini yeniden yükle" adımı hiç çalışmazdı.
  const t6GscUrlEl = document.getElementById('t6-gsc-url');
  if (t6GscUrlEl) t6GscUrlEl.value = state.currentClient?.gsc_site_url || '';
  const t6Ga4IdEl = document.getElementById('t6-ga4-id');
  if (t6Ga4IdEl) t6Ga4IdEl.value = state.currentClient?.ga4_property_id || '';
  const t6DriveIdEl = document.getElementById('t6-drive-id');
  if (t6DriveIdEl) t6DriveIdEl.value = state.currentClient?.drive_folder_id || '';
  const t6RealDataWrapEl = document.getElementById('t6-real-data-wrap');
  if (t6RealDataWrapEl) t6RealDataWrapEl.style.display = 'none';

  // Tab 7'deki müşteri adını da otomatik doldur (Drive klasör/dosya isimlendirmesi için)
  const t7CustomerEl = document.getElementById('t7-customer');
  if (t7CustomerEl) t7CustomerEl.value = state.currentClient?.name || '';
  const t6GscResultEl = document.getElementById('t6-gsc-result');
  if (t6GscResultEl) t6GscResultEl.innerHTML = '';
  const t6Ga4ResultEl = document.getElementById('t6-ga4-result');
  if (t6Ga4ResultEl) t6Ga4ResultEl.innerHTML = '';
  state.lastGSC = null;
  state.lastGA4 = null;

  // Bu müşteriye ait arşiv, backlink ve skor geçmişini yeniden yükle
  await Promise.all([fetchContentHistory(), fetchScoreHistory()]);

  if(!id){
    showToast('Müşteri seçimi temizlendi.', 'success');
  }
});

let sitemapLoadSequence = 0;

async function loadClientSitemap(domain) {
  const loadSequence = ++sitemapLoadSequence;
  const treeEl = document.getElementById('site-explorer-tree');
  const badgeEl = document.getElementById('sitemap-url-count');
  const refreshBtn = document.getElementById('refresh-sitemap-btn');
  treeEl.innerHTML = '<div class="t3-sitemap__empty"><span class="spinner spinner--dark" aria-hidden="true"></span> Sitemap yükleniyor…</div>';
  refreshBtn?.classList.add('is-loading');
  refreshBtn?.setAttribute('aria-busy', 'true');
  if (badgeEl) badgeEl.hidden = true;
  
  try {
    // ESKİDEN: http://localhost:3000/api/sitemap - var olmayan bir Node servisi.
    // Artık kendi pure-PHP api/sitemap.php endpoint'imizi kullanıyoruz.
    const targetUrl = `api/sitemap.php?url=${encodeURIComponent(domain)}`;
    const res = await fetch(targetUrl);
    const payload = await res.json();
    if (loadSequence !== sitemapLoadSequence) return;
    if (!res.ok || !Array.isArray(payload)) throw new Error(payload?.error || 'Sitemap alınamadı');
    const urls = payload;
    if (urls.length === 0) {
      treeEl.innerHTML = '<div class="t3-sitemap__empty">Sitemap içinde URL bulunamadı.</div>';
      return;
    }
    
    // Klasör yapısına çevir
    const tree = {};
    urls.forEach(u => {
      try{
        const pu = new URL(u);
        const parts = pu.pathname.split('/').filter(Boolean);
        let curr = tree;
        parts.forEach((p, i) => {
          if(!curr[p]) curr[p] = { _url: (i === parts.length - 1) ? u : null, _children: {} };
          curr = curr[p]._children;
        });
        if(parts.length === 0) tree['_root'] = { _url: u, _children: {} };
      }catch(e){}
    });
    
    function buildTreeHtml(node) {
      let html = '';
      for (const key in node) {
        if(key === '_root') {
          html += `<a href="#" class="t3-sitemap__home sitemap-link" data-url="${escapeHtml(node[key]._url)}" title="${escapeHtml(node[key]._url)}"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg><span>Ana Sayfa</span></a>`;
          continue;
        }
        
        const hasChildren = Object.keys(node[key]._children).length > 0;
        const u = node[key]._url;
        
        html += '<div class="t3-sitemap__row">';
        if (hasChildren) {
          html += `<button type="button" class="t3-sitemap__folder" aria-expanded="false" title="/${escapeHtml(key)}"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg><span>/${escapeHtml(key)}</span></button>`;
          html += '<div class="t3-sitemap__children" hidden>';
          if (u) html += `<a href="#" class="t3-sitemap__link sitemap-link" data-url="${escapeHtml(u)}" title="${escapeHtml(u)}"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg><span>(dizin)</span></a>`;
          html += buildTreeHtml(node[key]._children);
          html += `</div>`;
        } else {
          html += `<a href="#" class="t3-sitemap__link sitemap-link" data-url="${escapeHtml(u)}" title="${escapeHtml(u)}"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg><span>${escapeHtml(key)}</span></a>`;
        }
        html += `</div>`;
      }
      return html;
    }
    
    treeEl.innerHTML = buildTreeHtml(tree);
    if (badgeEl) { badgeEl.textContent = String(urls.length); badgeEl.hidden = false; }
    
  } catch(err) {
    if (loadSequence !== sitemapLoadSequence) return;
    treeEl.innerHTML = `<div class="t3-sitemap__error">Sitemap yüklenemedi: ${escapeHtml(err.message)}</div>`;
  } finally {
    if (loadSequence === sitemapLoadSequence) {
      refreshBtn?.classList.remove('is-loading');
      refreshBtn?.removeAttribute('aria-busy');
    }
  }
}

document.getElementById('site-explorer-tree')?.addEventListener('click', (event) => {
  const link = event.target.closest('.sitemap-link');
  if (link) {
    event.preventDefault();
    const treeEl = event.currentTarget;
    treeEl.querySelectorAll('.sitemap-link').forEach(item => item.classList.remove('is-active'));
    link.classList.add('is-active');
    routeUrlToActiveTab(link.dataset.url);
    return;
  }
  const folder = event.target.closest('.t3-sitemap__folder');
  if (!folder) return;
  const children = folder.nextElementSibling;
  const expanded = folder.getAttribute('aria-expanded') === 'true';
  folder.setAttribute('aria-expanded', String(!expanded));
  if (children) children.hidden = expanded;
});

document.getElementById('refresh-sitemap-btn')?.addEventListener('click', () => {
  if (state.currentClient && state.currentClient.domain_url) {
    loadClientSitemap(state.currentClient.domain_url);
  }
});

function waitForEvent(eventName) {
  return new Promise(resolve => {
    window.addEventListener(eventName, resolve, { once: true });
  });
}

async function runEnterpriseAutoPilot() {
  if(!state.selectedSitemapUrl) return;
  const url = state.selectedSitemapUrl;
  const apBtn = document.getElementById('auto-pilot-btn');
  const rootDomain = (state.currentClient && state.currentClient.domain_url) ? state.currentClient.domain_url : url;
  
  apBtn.disabled = true;
  apBtn.innerHTML = '<span class="spinner" style="width:12px;height:12px;border-width:2px;margin-right:4px;"></span> Auto-Pilot Devrede...';
  
  // Ekranda tıklamayı engelleyecek tam ekran overlay
  const overlay = document.createElement('div');
  overlay.style.position = 'fixed';
  overlay.style.top = '0'; overlay.style.left = '0'; overlay.style.width = '100vw'; overlay.style.height = '100vh';
  overlay.style.backgroundColor = 'rgba(0,0,0,0.6)';
  overlay.style.zIndex = '99999';
  overlay.style.display = 'flex'; overlay.style.flexDirection = 'column'; overlay.style.alignItems = 'center'; overlay.style.justifyContent = 'center';
  overlay.innerHTML = '<div class="spinner" style="width:40px;height:40px;border-width:4px;"></div><div style="color:white; margin-top:20px; font-weight:600; font-size:18px;" id="ap-status">Auto-Pilot Başlatılıyor...</div>';
  document.body.appendChild(overlay);

  try {
    resetAllForms();
    
    // TAB 1
    overlay.querySelector('#ap-status').textContent = 'Adım 1/8: Temel Veriler Çekiliyor...';
    document.querySelector('.nav__item[data-tab="1"]').click();
    document.getElementById('t1-fetch-url').value = url;
    document.getElementById('t1-fetch-btn').click();
    await waitForEvent('t1-fetch-btn-done');
    
    overlay.querySelector('#ap-status').textContent = 'Adım 2/8: AI İçerik İyileştirme...';
    document.getElementById('t1-improve-btn').click();
    await waitForEvent('t1-improve-btn-done');
    
    overlay.querySelector('#ap-status').textContent = 'Adım 3/8: Dönüşüm Skoru...';
    document.getElementById('t1-conversion-btn').click();
    await waitForEvent('t1-conversion-btn-done');
    
    overlay.querySelector('#ap-status').textContent = 'Adım 4/8: AI Overviews Uyumluluğu...';
    document.getElementById('t1-sge-btn').click();
    await waitForEvent('t1-sge-btn-done');
    
    // TAB 2 - Kelimeleri Topla
    overlay.querySelector('#ap-status').textContent = 'Adım 5/8: Kelime Analizi...';
    const keyword = document.getElementById('t1-keyword').value;
    if(keyword) {
      // removed tab 2 auto pilot
      document.getElementById('t2-seed').value = keyword;
      // removed
      await waitForEvent('t2-generate-btn-done');
    }
    
    // TAB 3 - Canlı Denetim & Şema Denetim
    overlay.querySelector('#ap-status').textContent = 'Adım 6/8: Hız & Şema Denetimi...';
    document.querySelector('.nav__item[data-tab="2"]').click();
    document.getElementById('t3-url').value = url;
    document.getElementById('t3-audit-btn').click();
    await waitForEvent('t3-audit-btn-done');
    
    document.getElementById('t3-schema-url').value = url;
    document.getElementById('t3-schema-audit-btn').click();
    await waitForEvent('t3-schema-audit-btn-done');
    
    // TAB 4 - Yapılandırılmış Veri & Master LLMs
    overlay.querySelector('#ap-status').textContent = 'Adım 7/8: Şema & LLM Üretimi...';
    document.querySelector('.nav__item[data-tab="3"]').click();
document.getElementById('t8-analyze-btn').click();
    document.getElementById('t4-ai-extract-btn').click();
    await waitForEvent('t4-ai-extract-btn-done');
    
    document.getElementById('t4-generate-btn').click();
    await waitForEvent('t4-generate-btn-done');
    
    document.getElementById('t4-llmstxt-btn').click();
    await waitForEvent('t4-llmstxt-btn-done');
    
    // Master LLMs (Eğer site kökse veya url kök domainse çalıştıralım, şimdilik hep çalıştıralım)
    const sitemapUrl = rootDomain + (rootDomain.endsWith('/') ? 'sitemap.xml' : '/sitemap.xml');
    document.getElementById('t4-sitemap-url').value = sitemapUrl;
    document.getElementById('t4-master-llmstxt-btn').click();
    await waitForEvent('t4-master-llmstxt-btn-done');
    
    // TAB 6 - Satış & Rapor (Tab 6) 
    overlay.querySelector('#ap-status').textContent = 'Adım 8/8: Genel Skor Hesaplanıyor...';
    // removed
    if(state.currentClient) {
       document.getElementById('t6-brand-name').value = state.currentClient.name;
    }
    // removed
    await waitForEvent('t6-refresh-btn-done');
    
    showToast('Tüm Auto-Pilot süreci başarıyla tamamlandı!', 'success');
  } catch(err) {
    showToast('Auto-Pilot iptal oldu veya hata verdi: ' + err.message, 'error');
  } finally {
    document.body.removeChild(overlay);
    apBtn.disabled = false;
    apBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg> Auto-Pilot Başlat';
  }
}

document.getElementById('auto-pilot-btn')?.addEventListener('click', runEnterpriseAutoPilot);

let nightJobTimer = null;
document.getElementById('schedule-night-btn')?.addEventListener('click', () => {
  if(!state.selectedSitemapUrl) return;
  
  const schedBtn = document.getElementById('schedule-night-btn');
  const timerInfo = document.getElementById('schedule-timer-info');
  
  if (nightJobTimer) {
     clearTimeout(nightJobTimer);
     nightJobTimer = null;
     timerInfo.style.display = 'none';
     showToast('Zamanlanmış analiz iptal edildi.', 'info');
     schedBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Gece 3\'te Çalıştır';
     return;
  }
  
  const now = new Date();
  let target = new Date();
  target.setHours(3, 0, 0, 0);
  
  if (now.getTime() > target.getTime()) {
     // Saat 3'ü geçmişse yarına kur
     target.setDate(target.getDate() + 1);
  }
  
  const msUntil3AM = target.getTime() - now.getTime();
  
  timerInfo.style.display = 'block';
  timerInfo.textContent = `Analiz şu saatte başlayacak: ${target.toLocaleString('tr-TR')}. Lütfen cihazı ve sekmeyi açık bırakın!`;
  
  schedBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Zamanlamayı İptal Et';
  showToast('Analiz gece 3 için zamanlandı!', 'success');
  
  nightJobTimer = setTimeout(async () => {
     nightJobTimer = null;
     timerInfo.style.display = 'none';
     schedBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Gece 3\'te Çalıştır';
     
     showToast('Zamanlanmış gece analizi başlıyor...', 'info');
     
     // 1. AutoPilot çalıştır
     await runEnterpriseAutoPilot();
     
     // 2. Drive'a yükle (export)
     const driveBtn = document.getElementById('t7-export-drive');
     if (driveBtn) {
       driveBtn.click();
     }
  }, msUntil3AM);
});

function autofillClientUrlFields(domainUrl) {
  // YENİ: bir müşteri seçildiğinde (kök domain_url'i varsa) o URL'yi ilgili
  // sekmelerdeki URL giriş alanlarına otomatik doldurur. domainUrl null/boşsa
  // hiçbir şeye dokunmuyoruz (kullanıcının elle girdiği bir değeri yanlışlıkla
  // silmemek için). Not: Metin Bazlı SEO (tab 1) artık URL değil, doğrudan
  // yapıştırılan metinle çalışıyor (bkz. src/TextSeo/views/tab1_view.php
  // #rawText) - o yüzden burada bir t1 alanı doldurmuyoruz, öyle bir alan yok.
  if (!domainUrl) return;

  const t3Url = document.getElementById('t3-url');
  if (t3Url) t3Url.value = domainUrl;
  const t3SchemaUrl = document.getElementById('t3-schema-url');
  if (t3SchemaUrl) t3SchemaUrl.value = domainUrl;

  // Skor Geçmişi > "URL ile Ara" alanı - daha önce unutulmuştu, otomatik
  // doldurmaya dahil edilmemişti.
  const t3HistoryLookupUrl = document.getElementById('t3-history-lookup-url');
  if (t3HistoryLookupUrl) t3HistoryLookupUrl.value = domainUrl;

  const copilotUrl = document.getElementById('copilot-text-input');
  if (copilotUrl) copilotUrl.value = domainUrl;
}

function routeUrlToActiveTab(url) {
  state.selectedSitemapUrl = url;
  const apBtn = document.getElementById('auto-pilot-btn');
  const schedBtn = document.getElementById('schedule-night-btn');
  if(apBtn) {
    apBtn.style.display = 'flex';
    apBtn.disabled = false;
  }
  if(schedBtn) {
    schedBtn.style.display = 'flex';
    schedBtn.disabled = false;
  }
  
  const activeTabBtn = document.querySelector('.nav__item.active');
  const tabId = activeTabBtn ? activeTabBtn.getAttribute('data-tab') : null;
  const rootDomain = (state.currentClient && state.currentClient.domain_url) ? state.currentClient.domain_url : url;
  
  if (tabId === '1') {
    // NOT: t1-fetch-url artık mevcut arayüzde yok (Metin Bazlı SEO metin
    // yapıştırma tabanlı çalışıyor) - bu dal muhtemelen eski bir sürümden
    // kalma, kapsamımız dışında bıraktık (element yoksa güvenle no-op olur).
    const t1FetchUrl = document.getElementById('t1-fetch-url');
    if (t1FetchUrl) t1FetchUrl.value = url;
    showToast('URL aktarıldı, işlemi manuel başlatabilirsiniz.', 'info');
  } else if (tabId === '2') {
    // DÜZELTME: burası eskiden yanlışlıkla '3' kontrol ediyordu - nav sekmeleri
    // yeniden numaralandırıldığında (Teknik SEO artık data-tab="2") bu dal
    // güncellenmemişti, yani Teknik SEO sekmesindeyken hiçbir alan dolmuyordu.
    document.getElementById('t3-url').value = url;
    document.getElementById('t3-schema-url').value = url;
    // Skor Geçmişi > "URL ile Ara" alanı - Sitemap Explorer'dan bir sayfa
    // seçildiğinde bu da doldurulmalı, daha önce unutulmuştu.
    const t3HistoryLookupUrlEl = document.getElementById('t3-history-lookup-url');
    if (t3HistoryLookupUrlEl) t3HistoryLookupUrlEl.value = url;
    showToast('URL aktarıldı, işlemi manuel başlatabilirsiniz.', 'info');
  } else if (tabId === '3') {
    // AI SEO Analizi sekmesi (data-tab="3") - eskiden bu dal yanlışlıkla
    // Teknik SEO'nun alanlarını dolduruyordu (bkz. yukarıdaki not).
    const copilotUrl = document.getElementById('copilot-text-input');
    if (copilotUrl) copilotUrl.value = url;
    showToast('URL aktarıldı, işlemi manuel başlatabilirsiniz.', 'info');
  } else if (tabId === '4') {
    // Tab 4 için sitemap kutusunu ana domain üzerinden doldur
    const sitemapUrl = rootDomain + (rootDomain.endsWith('/') ? 'sitemap.xml' : '/sitemap.xml');
    document.getElementById('t4-sitemap-url').value = sitemapUrl;
    showToast(`Master llms.txt için hedef URL (${rootDomain}) olarak ayarlandı.`, 'info');
  } else if (tabId === '5') {
    // Tab 5 (Backlink) için doğrudan URL kopyala
    showToast(`Seçilen URL kopyalandı: ${url}`, 'info');
  } else {
    showToast(`Seçilen URL kopyalandı veya hafızaya alındı: ${url}`, 'info');
  }
}

document.getElementById('sidebar-client-domain')?.addEventListener('click', () => {
  if (state.currentClient && state.currentClient.domain_url) {
    routeUrlToActiveTab(state.currentClient.domain_url);
  }
});

document.getElementById('t6-save-links-btn')?.addEventListener('click', async () => {
  if(!state.currentClientId){ showToast('Önce sol menüden bir müşteri seçin.', 'error'); return; }
  const payload = {
    gsc_site_url: document.getElementById('t6-gsc-url').value.trim() || null,
    ga4_property_id: document.getElementById('t6-ga4-id').value.trim() || null,
    drive_folder_id: document.getElementById('t6-drive-id').value.trim() || null,
  };
  try{
    const res = await fetch('api/clients.php?id='+state.currentClientId, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)}); const json = await res.json(); const error = json.error;
    if(error) throw error;
    Object.assign(state.currentClient, payload);
    showToast('Müşteri bağlantıları kaydedildi.', 'success');
  }catch(err){
    console.error('[Supabase] clients update hatası:', err);
    showToast('Kaydedilemedi: ' + (err.message || 'bilinmeyen hata'), 'error');
  }
});

/* ============================================================
   TAB NAVIGATION
============================================================ */
const navItems = document.querySelectorAll('.nav__item');
const panels = document.querySelectorAll('.tab-panel');
const topbarMeta = {
  1:{ eyebrow:'01 · İÇERİK', title:'Metin Bazlı SEO', desc:'Metni hedef kelimeye göre optimize edin, mevcut blog yazılarınıza otomatik iç linkler önerin.' },
  2:{ eyebrow:'02 · TEKNİK', title:'Teknik SEO', desc:'Site performansını PageSpeed Insights ve teknik SEO kontrolleri üzerinden analiz edin.' },
  3:{ eyebrow:'03 · AI SEO', title:'AI SEO Analizi', desc:'Hedef sitenin içerik kalitesini, otoritesini ve dönüşüm yeteneğini yapay zeka ile denetleyin.' },
  4:{ eyebrow:'04 · AKSİYON', title:'Yapılacaklar / Eksiklikler', desc:'Yapay zeka analizleri sonucunda siteniz için önerilen teknik ve metin bazlı iyileştirme tavsiyeleri.' },
};

navItems.forEach(btn => {
  btn.addEventListener('click', () => {
    const tab = btn.getAttribute('data-tab');
    navItems.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    panels.forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    const meta = topbarMeta[tab];
    document.getElementById('topbar-eyebrow').textContent = meta.eyebrow;
    document.getElementById('topbar-title').textContent = meta.title;
    document.getElementById('topbar-desc').textContent = meta.desc;
    window.scrollTo({top:0, behavior:'instant' in window ? 'instant' : 'auto'});
  });
});

/* ============================================================
   AI CALL — callGemini()
   Gerçek bir Gemini API isteği dener; API key geçersizse veya
   istek başarısız olursa simüle edilmiş (mock) bir yanıt döndürülür.
============================================================ */
/* ============================================================
 *  YAPAY ZEKA (GOOGLE GEMINI) İSTEK FONKSİYONU
 * Burası uygulamanın beynidir. İçerik iyileştirme, kelime analizi veya
 * şema üretimi gibi AI gerektiren tüm işlemler bu fonksiyondan geçer.
 * API'ye istek atarız, hata alırsak (örn. kota dolarsa) sistemin çökmemesi
 * için 'mockResponseFactory' (sahte veri) ile yola devam ederiz.
 * ============================================================ */
async function callGemini(userPrompt, mockResponseFactory){
  // API anahtarımız güvenlik için .env dosyasında duruyor.
  // İstekleri kendi sunucumuzdaki proxy dosyasına atıyoruz.
  const endpoint = 'api_handler.php';

  const requestBody = {
    contents: [{
      parts: [{ text: userPrompt }]
    }],
    generationConfig: {
      temperature: 0.1, // Tutarlı ve analitik yanıtlar için yaratıcılığı kıstık
      topP: 0.9,
      topK: 40
    }
  };

  try{
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(requestBody)
    });

    if(!response.ok){
      throw new Error('API yanıt kodu: ' + response.status);
    }

    const data = await response.json();
    
    // Gemini'ın JSON yapısından metni çıkartıyoruz
    if(data.candidates && data.candidates[0] && data.candidates[0].content.parts[0].text){
      return data.candidates[0].content.parts[0].text;
    }
    throw new Error('Beklenen içerik bloğu bulunamadı.');

  }catch(err){
    // Hata olursa veya API Key girilmezse uygulama çökmesin diye Prototip Modu devreye girer
    console.warn('[callGemini] Prototip modunda çalışıyor →', err.message);
    await new Promise(resolve => setTimeout(resolve, 850 + Math.random()*450));
    return mockResponseFactory();
  }
}

/* ============================================================
   TAB 1 — İÇERİK & İÇ LİNKLEME
============================================================ */
const t1ImproveBtn = document.getElementById('t1-improve-btn');
const t1ImproveLabel = document.getElementById('t1-improve-label');
let t1LastResult = null;
let t1LastOriginal = '';

function escapeHtml(str){
  return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function buildMockImprovedContent(originalText, keyword, blogUrlsRaw){
  const kw = (keyword || 'hedef kelime').trim();
  const blogUrls = (blogUrlsRaw || '').split('\n').map(s => s.trim()).filter(Boolean).slice(0,3);
  const base = (originalText || '').trim() || 'Bu alan için henüz bir içerik metni girilmedi.';

  let plainText = base;
  plainText += '\n\nAyrıca ' + kw + ' konusunda daha detaylı bilgi almak isteyen kullanıcılar, konuyla ilgili diğer kaynaklarımızı da inceleyebilir.';
  if(blogUrls.length){
    plainText += ' Bu kapsamda aşağıdaki içeriklerimize göz atabilirsiniz: ';
    plainText += blogUrls.map(u => u.replace(/^https?:\/\//,'')).join(', ') + '.';
  }

  let htmlBody = '<p>' + escapeHtml(base).replace(new RegExp('(' + kw.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')','gi'), '<strong>$1</strong>') + '</p>';
  htmlBody += '\n<p>Ayrıca <strong>' + escapeHtml(kw) + '</strong> konusunda daha detaylı bilgi almak isteyen kullanıcılar, konuyla ilgili diğer kaynaklarımızı da inceleyebilir.</p>';
  if(blogUrls.length){
    htmlBody += '\n<ul>\n' + blogUrls.map(u => '  <li><a href="' + escapeHtml(u) + '">' + escapeHtml(u.replace(/^https?:\/\//,'')) + '</a></li>').join('\n') + '\n</ul>';
  }

  return JSON.stringify({ plainText, html: htmlBody });
}
/* --- Sekme 1: Canlı URL'den Veri Çekme (Özel Sunucu ve Akıllı Çıkarım İle) --- */
/* --- Sekme 1: Canlı URL'den Veri Çekme (Özel Sunucu ve YAPAY ZEKA Analizi İle) --- */
document.getElementById('t1-fetch-btn')?.addEventListener('click', async () => {
  const urlInput = document.getElementById('t1-fetch-url').value.trim();
  if(!urlInput) { showToast('Lütfen geçerli bir URL girin.', 'error'); return; }
  if(!/^https?:\/\//i.test(urlInput)){
    showToast('URL "http://" veya "https://" ile başlamalı.', 'error');
    return;
  }

  // Checkbox'ı kontrol et
  const forceJsCheckbox = document.getElementById('t1-force-js');
  const forceJs = forceJsCheckbox ? forceJsCheckbox.checked : false;

  const btn = document.getElementById('t1-fetch-btn');
  const label = document.getElementById('t1-fetch-label');
  btn.disabled = true;
  label.innerHTML = '<span class="spinner"></span> Analiz ediliyor...';

  try {
    const targetUrl = `http://localhost:3000/api/scrape?url=${encodeURIComponent(urlInput)}`;

    // 60 saniyelik zaman aşımı koruması
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 60000); 

    const response = await fetch(targetUrl, { signal: controller.signal });
    clearTimeout(timeoutId);

    if(!response.ok) {
        const errData = await response.json().catch(() => ({}));
        throw new Error(errData.error || 'Ağ hatası: ' + response.status);
    }
    
    // Backend'den tertemiz JSON gelir
    const data = await response.json();
    const rawText = data.content || '';
    const internalLinks = data.internalLinks || [];
    const externalLinks = data.externalLinks || [];
    const scrapedTitle = data.title || '';
    const scrapedDesc = data.description || '';

    if(!rawText) throw new Error('Sitenin içeriği boş geldi.');

    const textForAnalysis = rawText.substring(0, 4000);

    if(document.getElementById('t1-content')) { if(document.getElementById('t1-content')) { document.getElementById('t1-content').value = rawText; } }
    document.getElementById('t1-related-urls').value = Array.from(internalLinks).slice(0, 5).join('\n'); 
    
    // Temel verileri (Title ve Meta) hemen doldur
    if (scrapedTitle) document.getElementById('t1-title').value = scrapedTitle;
    if (scrapedDesc) document.getElementById('t1-meta').value = scrapedDesc;

    const contentType = document.querySelector('input[name="contentType"]:checked').value;
    if(contentType === 'blog') {
        const readTime = Math.ceil(rawText.split(/\s+/).length / 200) + ' dk';
        document.getElementById('df-blog-time').value = readTime;
        document.getElementById('df-blog-refs').value = Array.from(externalLinks).slice(0, 3).join(', '); 
    }

    // 5. ZOR İŞ (BAŞLIK, META, HEDEF KELİME) YAPAY ZEKA
    const aiPrompt = `
      Aşağıda bir web sitesinin ham metin dökümü var. Bu metni inceleyerek bir SEO uzmanı gibi şu bilgileri çıkar:
      1. Bu sayfa için mükemmel ve eksiksiz bir Title (Maksimum 60 karakter)
      2. Bu sayfa için mükemmel bir Meta Description (Maksimum 160 karakter)
      3. Bu sayfanın odaklanması gereken 1 (bir) adet Hedef Kelime
      
      Yanıtı SADECE ve SADECE JSON formatında ver: {"title": "...", "meta": "...", "keyword": "..."}
      Metin: 
      ${textForAnalysis}
    `;

    // AI'ı çağırıyoruz
    const rawAiResponse = await callGemini(aiPrompt, () => '{}');
    let aiParsed = { title: '', meta: '', keyword: '' };
    
    try {
        aiParsed = JSON.parse(cleanJsonResponse(rawAiResponse));
    } catch (e) {
        console.warn("AI JSON okunamadı, varsayılan değerler boş kalacak.");
    }

    // 6. AI'DAN GELEN VERİLERLE KALAN KUTULARI DOLDUR (Eğer scrape ile gelmediyse title/meta ezilmesin)
    if (aiParsed.title && !scrapedTitle) document.getElementById('t1-title').value = aiParsed.title;
    if (aiParsed.meta && !scrapedDesc) document.getElementById('t1-meta').value = aiParsed.meta;
    if (aiParsed.keyword) document.getElementById('t1-keyword').value = aiParsed.keyword;

    showToast('Sayfa çekildi ve yapay zeka tarafından analiz edildi!', 'success');

  } catch(err) {
    console.error('[Fetch URL Error]', err);
    showToast('Sayfa okunamadı: ' + err.message, 'error');
  } finally {
    window.dispatchEvent(new Event('t1-fetch-btn-done'));
    btn.disabled = false;
    label.textContent = 'Mevcut Verileri Çek';
  }
});

/* --- Sekme 1: İçerik Türü Değişimi --- */
document.querySelectorAll('input[name="contentType"]').forEach(radio => {
  radio.addEventListener('change', (e) => {
    document.getElementById('df-blog').classList.add('hidden');
    document.getElementById('df-ecommerce').classList.add('hidden');
    document.getElementById('df-service').classList.add('hidden');
    document.getElementById('df-portfolio').classList.add('hidden');
    document.getElementById('df-' + e.target.value).classList.remove('hidden');
  });
});

/* --- Sekme 1: İyileştir Butonu (Yeni Prompt) --- */
t1ImproveBtn?.addEventListener('click', async () => {
  const content = (document.getElementById('t1-content')?.value || '');
  if(!content.trim()){ showToast('Lütfen önce içerik metni girin.', 'error'); return; }
  
  const keyword = document.getElementById('t1-keyword').value;
  const title = document.getElementById('t1-title').value;
  const meta = document.getElementById('t1-meta').value;
  const relatedUrls = document.getElementById('t1-related-urls').value;
  const contentType = document.querySelector('input[name="contentType"]:checked').value;

  // Hangi tür seçildiyse onun dinamik verilerini topla
  let extraInfo = '';
  if(contentType === 'blog') {
    extraInfo += `Okuma Süresi: ${document.getElementById('df-blog-time').value}\n`;
    extraInfo += `Kaynaklar: ${document.getElementById('df-blog-refs').value}\n`;
  } else if(contentType === 'ecommerce') {
    extraInfo += `Stok Kodu (SKU): ${document.getElementById('df-eco-sku').value}\n`;
    extraInfo += `Teknik Döküman: ${document.getElementById('df-eco-doc').value}\n`;
  } else if(contentType === 'service') {
    extraInfo += `Hedef CTA Bağlantısı: ${document.getElementById('df-srv-cta').value}\n`;
    extraInfo += `SSS (Sıkça Sorulan Sorular): ${document.getElementById('df-srv-faq').value}\n`;
  } else if(contentType === 'portfolio') {
    extraInfo += `Kullanılan Teknolojiler: ${document.getElementById('df-prt-tech').value}\n`;
    extraInfo += `Proje Metrikleri: ${document.getElementById('df-prt-metrics').value}\n`;
    extraInfo += `Demo/Repo URL: ${document.getElementById('df-prt-demo').value}\n`;
  }

  t1LastOriginal = content;
  t1ImproveBtn.disabled = true;
  t1ImproveLabel.innerHTML = '<span class="spinner"></span>';

  // AI TALİMATI (PROMPT)
  const prompt = `Sayfa Türü: ${contentType.toUpperCase()}
Hedef Kelime: ${keyword}
Sayfa Başlığı: ${title}
Meta Açıklama: ${meta}
İlişkili URL'ler: \n${relatedUrls}\n
Ekstra Sayfa Bilgileri: \n${extraInfo}\n

Görevler:
1. Aşağıdaki içeriği SEO açısından iyileştir ve hedef kelimeyi doğal biçimde kullan.
2. Sana verilen URL'ler sitenin farklı türdeki (blog, ürün, hizmet vb.) sayfalarına ait olabilir. URL'lerin yapılarına bakarak konularını tahmin et ve metnin içindeki anlamsal olarak en uygun, en doğal noktalara bu linkleri birer iç link (anchor text) olarak yerleştir.
3. Varsa sana verilen 'Ekstra Sayfa Bilgileri'ni (SSS, SKU, Okuma Süresi, Kullanılan Teknolojiler vb.) HTML şablonuna mantıklı ve göze hoş gelen bir tasarımla (liste, tablo veya vurgulu metin olarak) dahil et.
4. YALNIZCA ve SADECE JSON formatında {"plainText": "...", "html": "..."} döndür. Başka metin, açıklama veya kod bloğu ekleme.

İçerik:
${content}`;

  let parsed = null;
  try{
    const raw = await callGemini(prompt, () => buildMockImprovedContent(content, keyword, relatedUrls));
    try{
      parsed = JSON.parse(cleanJsonResponse(raw));
    }catch(parseErr){
      showToast('AI yanıtı işlenemedi (geçersiz JSON).', 'error'); return;
    }

    t1LastResult = parsed;
    document.getElementById('t1-output-text').textContent = parsed.plainText || '';
    document.getElementById('t1-output-html-code').textContent = parsed.html || '';
    document.getElementById('t1-output-card').classList.remove('hidden');
    state.contentScore = 74 + Math.round(Math.random()*20);
    renderT7VersionSelect();
    showToast('İçerik başarıyla iyileştirildi.', 'success');
  }catch(err){
    console.error(err); showToast('Beklenmeyen bir hata oluştu.', 'error');
  }finally{
    window.dispatchEvent(new Event('t1-improve-btn-done'));
    t1ImproveBtn.disabled = false; t1ImproveLabel.textContent = 'AI ile İyileştir';
  }
});

document.getElementById('t1-toggle-text')?.addEventListener('click', function(){
  this.classList.add('active');
  document.getElementById('t1-toggle-html').classList.remove('active');
  document.getElementById('t1-output-text').classList.remove('hidden');
  document.getElementById('t1-output-html').classList.add('hidden');
});
document.getElementById('t1-toggle-html')?.addEventListener('click', function(){
  this.classList.add('active');
  document.getElementById('t1-toggle-text').classList.remove('active');
  document.getElementById('t1-output-html').classList.remove('hidden');
  document.getElementById('t1-output-text').classList.add('hidden');
});

/* --- Sekme 1: Dönüşüm / Satın Alma Niyeti Skoru  --- */
document.getElementById('t1-conversion-btn')?.addEventListener('click', async () => {
  const content = (document.getElementById('t1-content')?.value || '');
  if (!content.trim()) { 
    showToast('Lütfen önce içerik metni girin veya URL\'den çekin.', 'error'); 
    return; 
  }

  const contentType = document.querySelector('input[name="contentType"]:checked').value;

  const btn = document.getElementById('t1-conversion-btn');
  const label = document.getElementById('t1-conversion-label');
  btn.disabled = true;
  label.innerHTML = '<span class="spinner"></span> Hesaplanıyor...';

  /* İçerik türüne göre analiz çerçevesi: her tür için dönüşümün ne anlama
     geldiği farklı olduğundan (blog'da satış değil etkileşim/otorite,
     portfolyoda satış değil güven/kanıt vb.) her biri kendi kriter setiyle
     ayrı bir prompt ve mock veri kullanır. */
  const conversionFrameworks = {
    ecommerce: {
      siteType: 'e-ticaret',
      instruction: "Metnin ziyaretçiyi ikna etme gücünü, Call-to-Action (CTA - Harekete Geçirici Mesaj) ifadelerinin netliğini, güven sinyallerini (garanti, iade koşulları, yorum/referans vb.) ve satın alma niyetini (purchase intent) ölç.",
      labels: ["CTA (Harekete Geçirici Mesaj)", "Güven Sinyalleri (Garanti/İade/Yorum)", "Satın Alma Niyeti"],
      exampleNote: "Metin genel olarak ikna edici ancak güven sinyalleri (örn. iade koşulları veya yorumlar) eksik. Satın al butonlarına yönlendiren ifadeler daha belirgin hale getirilmeli.",
      mock: { score: 72, values: [65, 80, 70], note: "Prototip Modu: Metinde 'Satın Al' veya 'Sepete Ekle' gibi net yönlendirmeler zayıf kalmış. Müşteriye güven veren ibareler (iade/garanti) yeterli seviyede." }
    },
    service: {
      siteType: 'hizmet/kurumsal',
      instruction: "Metnin ziyaretçiyi iletişime geçmeye veya teklif istemeye ikna etme gücünü, Call-to-Action (CTA - 'Teklif İste', 'Bize Ulaşın' vb.) ifadelerinin netliğini, güven sinyallerini (referans, sertifika, deneyim yılı, vaka örneği vb.) ve iletişim/teklif talep etme niyetini ölç. Bu bir hizmet sayfası olduğu için 'satın alma' değil 'iletişime geçme/teklif alma' dönüşümü esas alınmalı.",
      labels: ["CTA (Teklif İste / İletişime Geç)", "Güven Sinyalleri (Referans/Sertifika/Deneyim)", "İletişim/Teklif Niyeti"],
      exampleNote: "Metin hizmetin faydalarını anlatıyor ancak net bir 'Teklif İste' veya 'Bize Ulaşın' çağrısı zayıf. Referans/vaka örnekleri eklenerek güven artırılabilir.",
      mock: { score: 68, values: [60, 75, 68], note: "Prototip Modu: Sayfada hizmetin anlatımı güçlü ancak iletişime geçmeyi teşvik eden net bir çağrı eksik. Referans veya vaka çalışması eklenmesi güveni artırabilir." }
    },
    portfolio: {
      siteType: 'portfolyo',
      instruction: "Bu bir portfolyo/vaka çalışması sayfası. Metnin çalışmaları/işleri ne kadar ikna edici sunduğunu, kanıt ve güven sinyallerini (öne çıkan projeler, müşteri yorumları, ölçülebilir sonuçlar/rakamlar vb.) ve ziyaretçiyi iletişime geçmeye yönlendiren çağrının (CTA) varlığını ölç. Burada 'satın alma niyeti' değil, işi beğenip iletişime geçme isteği esas alınmalı.",
      labels: ["Sunum & Görsel/Anlatım Gücü", "Kanıt & Güven Sinyalleri (Sonuç/Referans)", "İletişime Geçme Çağrısı (CTA)"],
      exampleNote: "Projeler iyi anlatılmış ancak somut sonuç/rakam (ör. '%40 trafik artışı') içeren kanıtlar az. Sayfa sonunda net bir 'Projeniz için konuşalım' çağrısı eklenebilir.",
      mock: { score: 70, values: [78, 58, 72], note: "Prototip Modu: Çalışmaların sunumu güçlü ancak ölçülebilir sonuç/rakam içeren kanıtlar sınırlı. İletişime geçme çağrısı orta seviyede." }
    },
    blog: {
      siteType: 'blog/makale',
      instruction: "Bu bir blog/makale içeriği. Burada klasik satış dönüşümü değil, okuyucuyu elde tutma ve bir sonraki adıma (bültene abone olma, ilgili yazıyı okuma, ürün/hizmet sayfasına yumuşak geçiş vb.) yönlendirme başarısı ölçülmeli. Ayrıca içeriğin okunabilirliğini/akıcılığını ve yazarın konudaki uzmanlık/güven (otorite) sinyallerini (kaynak gösterme, deneyim paylaşımı, veri kullanımı vb.) değerlendir. İade koşulları, garanti veya doğrudan satın alma gibi e-ticaret kriterlerini KULLANMA.",
      labels: ["Okunabilirlik & Akıcılık", "İçerik Otoritesi/Güven Sinyalleri", "Sonraki Adım Çağrısı (Abone Ol/İlgili İçerik)"],
      exampleNote: "İçerik akıcı ve bilgilendirici ancak yazının sonunda okuyucuyu bir sonraki adıma (bültene abone olma veya ilgili yazıyı okuma) yönlendiren net bir çağrı yok. Kaynak/veri kullanımı güveni artırabilir.",
      mock: { score: 66, values: [80, 62, 55], note: "Prototip Modu: Yazı akıcı ve okunabilir ancak sonunda okuyucuyu bültene abone olmaya veya ilgili bir yazıya yönlendiren yumuşak bir CTA eksik." }
    }
  };

  const fw = conversionFrameworks[contentType] || conversionFrameworks.service;

  // Yapay Zekaya Giden İkna ve Pazarlama Promptu (içerik türüne göre özelleştirilmiş)
  const prompt = `Aşağıdaki ${fw.siteType} web sitesi metnini pazarlama ve dönüşüm (conversion) uzmanı gözüyle analiz et.
  ${fw.instruction}

  Yanıtı SADECE ve SADECE geçerli bir JSON formatında ver. Format şu şekilde olmalı:
  {
    "score": 85,
    "breakdown": [
      {"label": "${fw.labels[0]}", "value": 80},
      {"label": "${fw.labels[1]}", "value": 60},
      {"label": "${fw.labels[2]}", "value": 90}
    ],
    "notes": "${fw.exampleNote}"
  }

  Metin:
  ${content.substring(0, 3500)}`;

  // API Çökerse Devreye Girecek Prototip Verisi (içerik türüne göre)
  const mockData = () => JSON.stringify({
    score: fw.mock.score,
    breakdown: [
      {label: fw.labels[0], value: fw.mock.values[0]},
      {label: fw.labels[1], value: fw.mock.values[1]},
      {label: fw.labels[2], value: fw.mock.values[2]}
    ],
    notes: fw.mock.note
  });

  try {
    const raw = await callGemini(prompt, mockData);
    const parsed = JSON.parse(cleanJsonResponse(raw));

    // Arayüzü (DOM) Güncelleme
    const scoreEl = document.getElementById('t1-conversion-score');
    const breakdownEl = document.getElementById('t1-conversion-breakdown');
    const notesEl = document.getElementById('t1-conversion-notes');
    const card = document.getElementById('t1-conversion-card');

    scoreEl.textContent = parsed.score || 0;
    scoreEl.style.color = scoreColor(parsed.score || 0);

    let bdHtml = '';
    (parsed.breakdown || []).forEach(item => {
      const st = scoreStatusLabel(item.value);
      bdHtml += `
        <div class="meter-row">
          <div class="meter-row__head">
            <span class="label">${escapeHtml(item.label)}</span>
            <span class="flex gap-8">
              <span class="value">${item.value} / 100</span>
              <span class="status-chip ${st.cls}">${st.txt}</span>
            </span>
          </div>
          <div class="meter-track">
            <div class="meter-fill fill-${item.value >= 80 ? 'good' : item.value >= 55 ? 'mid' : 'bad'}" style="width:${item.value}%;"></div>
          </div>
        </div>`;
    });
    
    breakdownEl.innerHTML = bdHtml;
    notesEl.innerHTML = '<strong>Yapay Zeka Notu:</strong> ' + escapeHtml(parsed.notes || '');

    // Kartı Görünür Yap
    card.classList.remove('hidden');
    showToast('Dönüşüm analizi tamamlandı.', 'success');

  } catch(err) {
    console.error('[Conversion Analysis]', err);
    showToast('Analiz yapılamadı: Yapay Zeka yanıtı okunamadı.', 'error');
  } finally {
    window.dispatchEvent(new Event('t1-conversion-btn-done'));
    btn.disabled = false;
    label.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> <span id="t1-conversion-label">Dönüşüm Skoru</span>';
  }
});

const t1SgeBtn = document.getElementById('t1-sge-btn');
// Google AI Overviews (SGE) Uyumluluk Analizi Butonu
t1SgeBtn?.addEventListener('click', () => {
  const content = (document.getElementById('t1-content')?.value || '');
  if (!content.trim()) {
    showToast('Lütfen önce analiz edilecek bir içerik metni girin.', 'error');
    return;
  }

  // 1. Özgün Bakış Açısı (First-person pronouns & experience verbs)
  const perspectiveRegex = /\b(bence|bizce|deneyimledik|deneyimledim|gördük|gördüm|test ettik|test ettim|analiz ettik|analiz ettim|tecrübem|tecrübemiz|kendi deneyim|kendi tecrübe)\b/gi;
  const perspectiveMatches = content.match(perspectiveRegex) || [];
  const perspectiveScore = Math.min(30, perspectiveMatches.length * 10); // Max 30 points

  // 2. Anlamsal Yapı / Okunabilirlik (Check for paragraphs and potential heading elements/lines)
  const paragraphCount = content.split(/\n\s*\n/).filter(p => p.trim().length > 30).length;
  const headingCount = (content.match(/^(#{1,6}\s+.+|h[1-6]>)/gm) || []).length;
  let structureScore = 0;
  if (paragraphCount >= 3) structureScore += 15;
  else if (paragraphCount >= 1) structureScore += 5;
  if (headingCount >= 2) structureScore += 15;
  else if (headingCount >= 1) structureScore += 5;

  // 3. RAG / Soru-Cevap Yapısı (Check for question sentences or definitions)
  const questionCount = (content.match(/(\?|nedir|nasıl|neden|sebebi)/gi) || []).length;
  const ragScore = Math.min(25, questionCount * 8); // Max 25 points

  // 4. Görsel / Video Referansları (Checking for image tags, media mentions)
  const mediaRegex = /(resim|video|görsel|grafik|fotoğraf|svg|img|iframe)/gi;
  const mediaCount = (content.match(mediaRegex) || []).length;
  const mediaScore = Math.min(15, mediaCount * 5); // Max 15 points

  const totalScore = perspectiveScore + structureScore + ragScore + mediaScore;
  state.sgeScore = totalScore;
  
  // Update UI
  const scoreEl = document.getElementById('t1-sge-score');
  scoreEl.textContent = totalScore;
  
  let scoreColor = 'var(--danger)';
  if (totalScore >= 75) scoreColor = 'var(--success)';
  else if (totalScore >= 45) scoreColor = 'var(--warn)';
  scoreEl.style.color = scoreColor;

  const breakdownEl = document.getElementById('t1-sge-breakdown');
  breakdownEl.innerHTML = `
    <div class="sub-score-row" style="display:flex; justify-content:space-between; padding:4px 0; border-bottom:1px solid var(--border-soft); font-size:13.5px;"><span>Özgün Bakış Açısı & Kişisel Deneyim:</span> <strong>${perspectiveScore}/30</strong></div>
    <div class="sub-score-row" style="display:flex; justify-content:space-between; padding:4px 0; border-bottom:1px solid var(--border-soft); font-size:13.5px;"><span>Anlamsal Yapı & Hiyerarşi (Başlıklar):</span> <strong>${structureScore}/30</strong></div>
    <div class="sub-score-row" style="display:flex; justify-content:space-between; padding:4px 0; border-bottom:1px solid var(--border-soft); font-size:13.5px;"><span>RAG / Soru-Cevap Odaklılık:</span> <strong>${ragScore}/25</strong></div>
    <div class="sub-score-row" style="display:flex; justify-content:space-between; padding:4px 0; font-size:13.5px;"><span>Görsel & Medya Zenginliği:</span> <strong>${mediaScore}/15</strong></div>
  `;

  const notesEl = document.getElementById('t1-sge-notes');
  let notesHtml = '<strong>Analiz Sonuçları ve Öneriler:</strong><br><ul style="padding-left: 20px; margin-top: 8px; list-style-type: disc;">';
  
  if (perspectiveScore < 20) {
    notesHtml += '<li><strong>Özgünlüğü Artırın:</strong> Google, yapay zeka aramalarında (AI Overviews) ilk elden tecrübelere değer verir. Metne "deneyimledik", "bize göre" gibi kişisel/ajans tecrübelerinizi belirten ifadeler ekleyin.</li>';
  } else {
    notesHtml += '<li>✔️ <strong>Özgün Bakış Açısı:</strong> Kişisel tecrübe ve birinci tekil/çoğul anlatım tonu oldukça güçlü. AI için değerli bir sinyal.</li>';
  }

  if (structureScore < 20) {
    notesHtml += '<li><strong>Hiyerarşik Yapıyı Güçlendirin:</strong> İçeriği anlamsal (Semantic HTML) olarak başlıklar (H2, H3) ve okunabilir paragraflarla daha net parçalara bölün.</li>';
  } else {
    notesHtml += '<li>✔️ <strong>Anlamsal Yapı:</strong> Paragraf ve başlık dağılımı, LLM modelinin içeriği kolayca taraması için uygun.</li>';
  }

  if (ragScore < 15) {
    notesHtml += '<li><strong>RAG Blokları Ekleyin:</strong> Kullanıcıların sorabileceği soruları (örn: ...nedir?, ...nasıl yapılır?) doğrudan soru cümlesi yapıp altına 1-2 cümlelik net cevaplar ekleyerek "temellendirme" şansınızı artırın.</li>';
  } else {
    notesHtml += '<li>✔️ <strong>Soru-Cevap Odağı:</strong> RAG temelli yapay zeka (SGE) yanıtları için doğrudan cevap üreten yapılar mevcut.</li>';
  }

  if (mediaScore < 10) {
    notesHtml += '<li><strong>Görsel/Video Destek Belirtin:</strong> İçerikte resim veya video bulundurmak, AI Overviews panellerinde görsel sonuç olarak (kart şeklinde) listelenme şansınızı artırır.</li>';
  } else {
    notesHtml += '<li>✔️ <strong>Medya Referansı:</strong> İçerikte görsellere veya zengin medyaya yeterince atıfta bulunulmuş.</li>';
  }

  notesHtml += '</ul>';
  notesEl.innerHTML = notesHtml;

  document.getElementById('t1-sge-card').classList.remove('hidden');
  document.getElementById('t1-sge-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
  showToast('Google AI Overviews (SGE) uyumluluk analizi tamamlandı.', 'success');
});




/* --- Supabase: Arşive kaydet (content_history tablosuna insert) --- */
document.getElementById('t1-save-archive')?.addEventListener('click', async () => {
  if(!t1LastResult){
    showToast('Önce AI ile iyileştirme yapın.', 'error');
    return;
  }

  const saveBtn = document.getElementById('t1-save-archive');
  const payload = {
    keyword: document.getElementById('t1-keyword').value || '(kelime belirtilmedi)',
    old_text: t1LastOriginal,
    new_text: t1LastResult.plainText,
    project_type: document.querySelector('input[name="contentType"]:checked').value,
    client_id: state.currentClientId || null,
  };

  saveBtn.disabled = true;
  try{
    const res = await fetch('api/content_history.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)}); const json = await res.json(); const error = json.error;
    if(error) throw error;
    showToast('Kayıt arşive eklendi.', 'success');
    await fetchContentHistory();
  }catch(err){
    console.error('[Supabase] content_history insert hatası:', err);
    showToast('Arşive kaydedilirken hata oluştu: ' + (err.message || 'bilinmeyen hata'), 'error');
  }finally{
    saveBtn.disabled = false;
  }
});

function renderArchive(){
  const wrap = document.getElementById('t1-archive-list');
  if(!state.contentArchive.length){
    wrap.innerHTML = '<p class="empty-note">Henüz arşivlenmiş bir kayıt yok.</p>';
    renderT7VersionSelect();
    return;
  }
  wrap.innerHTML = '';
  state.contentArchive.forEach(entry => {
    const item = document.createElement('div');
    item.className = 'accordion__item';
    item.innerHTML =
      '<button class="accordion__header" data-id="' + entry.id + '">' +
        '<span class="meta"><span class="kw">' + escapeHtml(entry.keyword) + '</span><span class="date">' + entry.date + '</span></span>' +
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>' +
      '</button>' +
      '<div class="accordion__body">' +
        '<div class="compare-cols">' +
          '<div><div class="col-label">Eski Metin</div><div class="col-box">' + escapeHtml(entry.oldText || '—') + '</div></div>' +
          '<div><div class="col-label">Yeni Metin</div><div class="col-box">' + escapeHtml(entry.newText || '—') + '</div></div>' +
        '</div>' +
      '</div>';
    wrap.appendChild(item);
  });

  wrap.querySelectorAll('.accordion__header').forEach(header => {
    header.addEventListener('click', () => {
      header.closest('.accordion__item').classList.toggle('open');
    });
  });

  renderT7VersionSelect();
}

/* --- Supabase: Sayfa yüklendiğinde content_history'i çek (seçili müşteriye göre) --- */
async function fetchContentHistory(){
  if(!state.currentClientId){
    state.contentArchive = [];
    renderArchive();
    return;
  }
  try{
    const res = await fetch('api/content_history.php?client_id='+state.currentClientId); const { data, error } = await res.json();
    if(error) throw error;

    state.contentArchive = (data || []).map(row => ({
      id: row.id,
      date: row.created_at ? new Date(row.created_at).toLocaleString('tr-TR') : '',
      rawDate: row.created_at ? new Date(row.created_at) : new Date(),
      keyword: row.keyword || '(kelime belirtilmedi)',
      oldText: row.old_text,
      newText: row.new_text
    }));
    renderArchive();
  }catch(err){
    console.error('[Supabase] content_history select hatası:', err);
    showToast('Arşiv verileri Supabase\'den yüklenemedi.', 'error');
  }
}

/* --- Tab 7: "Drive'a Gönderilecek İçerik Sürümü" seçim kutusunu doldurur ---
   (t1-improve sonucu geldiğinde, arşiv güncellendiğinde ve müşteri değiştiğinde çağrılır) */
function renderT7VersionSelect(){
  const sel = document.getElementById('t7-content-version');
  if(!sel) return;
  const prevValue = sel.value;

  let optionsHtml = '<option value="">— İçerik gönderilmeyecek (sadece varsa raporu gönder) —</option>';

  // Henüz arşive kaydedilmemiş, taze bir AI sonucu varsa listeye ekle
  if(t1LastResult){
    const kwInput = document.getElementById('t1-keyword');
    const kw = kwInput ? kwInput.value.trim() : '';
    optionsHtml += '<option value="fresh">🆕 Yeni oluşturuldu' + (kw ? ' — ' + escapeHtml(kw) : '') + '</option>';
  }

  // Daha önce arşivlenmiş eski/yeni çiftleri
  state.contentArchive.forEach(entry => {
    optionsHtml += '<option value="' + entry.id + '">' + escapeHtml(entry.keyword) + ' — ' + escapeHtml(entry.date) + '</option>';
  });

  sel.innerHTML = optionsHtml;

  // Mümkünse önceki seçimi koru
  if(prevValue && Array.from(sel.options).some(o => o.value === prevValue)){
    sel.value = prevValue;
  }
}

/* --- Tab 7: seçili içerik sürümünün eski/yeni metnini döndürür (yoksa null) --- */
function getSelectedContentPair(){
  const sel = document.getElementById('t7-content-version');
  if(!sel) return null;
  const val = sel.value;
  if(!val) return null;

  if(val === 'fresh'){
    if(!t1LastResult) return null;
    const kwInput = document.getElementById('t1-keyword');
    return {
      oldText: t1LastOriginal || '',
      newText: t1LastResult.plainText || '',
      label: kwInput ? kwInput.value.trim() : ''
    };
  }

  const entry = state.contentArchive.find(e => String(e.id) === val);
  if(!entry) return null;
  return { oldText: entry.oldText || '', newText: entry.newText || '', label: entry.keyword };
}

/* ============================================================
   TAB 2 — ANAHTAR KELİME STRATEJİSİ
============================================================ */
const t2Btn = document.getElementById('t2-cluster-btn');
const t2Label = document.getElementById('t2-cluster-label');
const t2ResultsTbody = document.getElementById('t2-results-tbody');
const t2SaveBtn = document.getElementById('t2-save-keywords-btn');
const t2SelectAll = document.getElementById('t2-select-all');

let currentKeywordBuckets = { questions: [], similar: [], related: [], low_volume: [] };
let activeBucket = 'questions';

function renderKeywordTable(bucketName) {
  const words = currentKeywordBuckets[bucketName] || [];
  t2ResultsTbody.innerHTML = '';
  
  if (words.length === 0) {
    t2ResultsTbody.innerHTML = '<tr><td colspan="7" style="padding:20px;text-align:center;color:var(--muted);font-size:13px;">Bu kategoride kelime bulunamadı.</td></tr>';
    return;
  }

  words.forEach((kw) => {
    let intentBadge = '';
    if(kw.intent === 'T') intentBadge = '<span class="tag tag--transaction" style="font-size:10px;padding:2px 6px;">İşlem (T)</span>';
    else if(kw.intent === 'C') intentBadge = '<span class="tag tag--compare" style="font-size:10px;padding:2px 6px;">Ticari (C)</span>';
    else if(kw.intent === 'I') intentBadge = '<span class="tag tag--info" style="font-size:10px;padding:2px 6px;">Bilgi (I)</span>';
    else intentBadge = '<span class="tag" style="background:var(--border);color:var(--muted);font-size:10px;padding:2px 6px;">Yerel (L)</span>';

    const oppColor = kw.opportunityScore >= 70 ? 'var(--success)' : kw.opportunityScore >= 40 ? 'var(--warn)' : 'var(--danger)';
    
    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--border-soft)';
    tr.innerHTML = `
      <td style="padding:10px 8px;"><input type="checkbox" class="t2-kw-checkbox" value="${escapeHtml(kw.keyword)}"></td>
      <td style="padding:10px 8px; font-weight:600; font-size:13px; color:var(--ink);">${escapeHtml(kw.keyword)}</td>
      <td style="padding:10px 8px; font-family:var(--font-mono); font-size:13px; color:${oppColor}; font-weight:600;">${kw.opportunityScore}</td>
      <td style="padding:10px 8px; font-family:var(--font-mono); font-size:12px;">${kw.searchVolume.toLocaleString('tr-TR')}</td>
      <td style="padding:10px 8px; font-family:var(--font-mono); font-size:12px;">${kw.difficulty}</td>
      <td style="padding:10px 8px; font-family:var(--font-mono); font-size:12px;">$${kw.cpc.toFixed(2)}</td>
      <td style="padding:10px 8px;">${intentBadge}</td>
      <td style="padding:10px 8px; text-align:right;">
        <button class="btn btn--ghost btn--sm t2-import-btn" data-kw="${escapeHtml(kw.keyword)}" title="İçerik Optimizatörüne (Tab 1) Gönder">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
        </button>
      </td>
    `;
    t2ResultsTbody.appendChild(tr);
  });
}

function updateBucketCounts() {
  document.getElementById('count-questions').textContent = currentKeywordBuckets.questions.length;
  document.getElementById('count-similar').textContent = currentKeywordBuckets.similar.length;
  document.getElementById('count-related').textContent = currentKeywordBuckets.related.length;
  document.getElementById('count-low_volume').textContent = currentKeywordBuckets.low_volume.length;
}

['questions', 'similar', 'related', 'low_volume'].forEach(tab => {
  const btn = document.getElementById('btn-tab-' + tab);
  btn?.addEventListener('click', () => {
    ['questions', 'similar', 'related', 'low_volume'].forEach(t => document.getElementById('btn-tab-' + t)?.classList.remove('active'));
    btn.classList.add('active');
    activeBucket = tab;
    renderKeywordTable(tab);
    if(t2SelectAll) t2SelectAll.checked = false;
  });
});

t2SelectAll?.addEventListener('change', (e) => {
  const checkboxes = document.querySelectorAll('.t2-kw-checkbox');
  checkboxes.forEach(cb => cb.checked = e.target.checked);
});

t2ResultsTbody?.addEventListener('click', (e) => {
  const btn = e.target.closest('.t2-import-btn');
  if(btn) {
    const kw = btn.getAttribute('data-kw');
    const kwInput = document.getElementById('t1-keyword');
    if(kwInput) kwInput.value = kw;
    // Switch to Tab 1
    document.querySelector('.nav__item[data-tab="tab-1"]')?.click();
    showToast(`"${kw}" Tab 1 hedefine kopyalandı.`, 'success');
  }
});

t2Btn?.addEventListener('click', async () => {
  const seed = document.getElementById('t2-seed')?.value?.trim();
  if(!seed){
    showToast('Lütfen tohum kelime girin.', 'error');
    return;
  }
  if(typeof window.KeywordEngine === 'undefined'){
    showToast('KeywordEngine yüklenemedi.', 'error');
    return;
  }

  t2Btn.disabled = true;
  if(t2Label) t2Label.innerHTML = '<span class="spinner" style="width:12px;height:12px;border-width:2px;display:inline-block;vertical-align:middle;margin-right:5px;border-top-color:#fff;"></span> Toplanıyor...';

  try {
    const buckets = await window.KeywordEngine.analyzeKeywords(seed);
    currentKeywordBuckets = buckets;
    updateBucketCounts();
    
    // Switch to first non-empty bucket if active is empty
    if(currentKeywordBuckets[activeBucket].length === 0) {
      ['questions', 'similar', 'related', 'low_volume'].forEach(t => document.getElementById('btn-tab-' + t)?.classList.remove('active'));
      const nextActive = ['questions', 'similar', 'related', 'low_volume'].find(t => currentKeywordBuckets[t].length > 0) || 'questions';
      activeBucket = nextActive;
      document.getElementById('btn-tab-' + activeBucket)?.classList.add('active');
    }

    renderKeywordTable(activeBucket);
    document.getElementById('t2-output-card')?.classList.remove('hidden');
    
    state.keywordScore = 80 + Math.round(Math.random()*15); 
    showToast('Google önerileri başarıyla toplandı ve skorlandı.', 'success');
  } catch(err) {
    console.error(err);
    showToast('Kelime analizi sırasında hata oluştu.', 'error');
  } finally {
    window.dispatchEvent(new Event('t2-generate-btn-done'));
    t2Btn.disabled = false; if(t2Label) t2Label.textContent = 'Kelimeleri Topla';
  }
});

t2SaveBtn?.addEventListener('click', async () => {
  if(!state.currentClientId){
    showToast('Önce sol menüden bir müşteri seçin.', 'error');
    return;
  }
  
  const checkboxes = document.querySelectorAll('.t2-kw-checkbox:checked');
  if(checkboxes.length === 0){
    showToast('Lütfen kaydedilecek en az bir kelime seçin.', 'error');
    return;
  }

  // Butonu yükleniyor moduna al
  t2SaveBtn.disabled = true;
  const originalText = t2SaveBtn.innerHTML;
  t2SaveBtn.innerHTML = '<span class="spinner" style="width:12px;height:12px;border-width:2px;display:inline-block;vertical-align:middle;margin-right:5px;border-top-color:var(--accent);"></span> Kaydediliyor...';

  // Seçilen kelimelerin tüm metriklerini tablodan toplayıp bir paket (payload) hazırlıyoruz
  const payload = [];
  checkboxes.forEach(cb => {
    const tr = cb.closest('tr'); // Kelimenin bulunduğu satırı bul
    const cells = tr.querySelectorAll('td'); // O satırdaki tüm hücreleri (sütunları) al
    
    // Tablodaki sütun sırası: [0: Checkbox, 1: Kelime, 2: Skor, 3: Hacim, 4: Zorluk, 5: TBM, 6: Niyet]
    payload.push({
      client_id: state.currentClientId,
      keyword: cb.value,
      opportunity_score: parseInt(cells[2].textContent) || 0,
      volume: parseInt(cells[3].textContent.replace(/\D/g, '')) || 0, // Sadece rakamları al
      difficulty: parseInt(cells[4].textContent) || 0,
      cpc: parseFloat(cells[5].textContent.replace('$', '')) || 0,
      intent: cells[6].textContent.trim()
    });
  });

  try {
    // Topladığımız veri paketini Supabase'deki yeni tablomuza tek seferde yazıyoruz
    const res = await fetch('api/client_keywords.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)}); const json = await res.json(); const error = json.error;
    
    if (error) throw error;
    
    showToast(payload.length + ' kelime müşteri hedeflerine başarıyla kaydedildi!', 'success');
    
    // Kullanıcı deneyimi: İşlem bitince kutucukların işaretlerini otomatik temizle
    checkboxes.forEach(cb => cb.checked = false);
    const selectAllCb = document.getElementById('t2-select-all');
    if(selectAllCb) selectAllCb.checked = false;

  } catch (err) {
    console.error('[Supabase] Keyword kayıt hatası:', err);
    if (err.message && err.message.includes('row-level security')) {
      showToast('Hata: Tabloda RLS açık. Lütfen Supabase SQL Editörde şunu çalıştırın: ALTER TABLE client_keywords DISABLE ROW LEVEL SECURITY;', 'error');
    } else {
      showToast('Kaydedilirken bir hata oluştu: ' + (err.message || 'Bilinmeyen hata'), 'error');
    }
  } finally {
    // Butonu eski haline döndür
    t2SaveBtn.disabled = false;
    t2SaveBtn.innerHTML = originalText;
  }
});

/* ============================================================
   TAB 3 — TEKNİK SEO
   Bu blok artık burada değil. Güvenlik nedeniyle (PageSpeed API anahtarı
   eskiden burada, tarayıcıda açık şekilde duruyordu) ve mimari nedenlerle
   (tüm teknik SEO mantığı sunucu tarafında, api/technical_seo_audit.php
   içinde topluca çalışıyor) Teknik SEO sekmesinin JS kodu ayrı bir dosyaya
   taşındı: js/technical-seo.js (bkz. index.php <script> etiketleri).
============================================================ */
/* ============================================================
   TAB 4 — SCHEMA ÜRETİCİ
============================================================ */
document.getElementById('t4-schema-type')?.addEventListener('change', function(){
  const val = this.value;
  document.getElementById('t4-product-fields').classList.toggle('hidden', val !== 'product');
  document.getElementById('t4-local-fields').classList.toggle('hidden', val !== 'local');
  document.getElementById('t4-breadcrumb-fields').classList.toggle('hidden', val !== 'breadcrumb');
  document.getElementById('t4-category-fields').classList.toggle('hidden', val !== 'category');
  
  if (val === 'local') {
    document.getElementById('t4-name-label').textContent = 'İşletme Adı';
    document.getElementById('t4-name').placeholder = 'örn. Ajans Adı Dijital Pazarlama';
    document.getElementById('t4-name').parentElement.classList.remove('hidden');
  } else if (val === 'product') {
    document.getElementById('t4-name-label').textContent = 'Ürün Adı';
    document.getElementById('t4-name').placeholder = 'örn. Deri Sırt Çantası';
    document.getElementById('t4-name').parentElement.classList.remove('hidden');
  } else if (val === 'category') {
    document.getElementById('t4-name-label').textContent = 'Kategori Adı';
    document.getElementById('t4-name').placeholder = 'örn. Kadın Çantaları';
    document.getElementById('t4-name').parentElement.classList.remove('hidden');
  } else if (val === 'breadcrumb') {
    document.getElementById('t4-name').parentElement.classList.add('hidden');
  }
});

document.getElementById('t4-generate-btn')?.addEventListener('click', () => {
  const schemaType = document.getElementById('t4-schema-type').value;
  const name = document.getElementById('t4-name').value.trim();
  let schema;

  if(schemaType === 'local'){
    const phone = document.getElementById('t4-phone').value.trim();
    const address = document.getElementById('t4-address').value.trim();

    if(!name || !phone){
      showToast('Lütfen işletme adı ve telefon numarası girin.', 'error');
      return;
    }

    schema = {
      "@context": "https://schema.org/",
      "@type": "LocalBusiness",
      "name": name,
      "telephone": phone,
      "address": {
        "@type": "PostalAddress",
        "streetAddress": address || undefined
      }
    };
  } else if (schemaType === 'product') {
    const price = document.getElementById('t4-price').value.trim();
    const currency = document.getElementById('t4-currency').value;
    const availability = document.getElementById('t4-stock').value;
    const shipCost = document.getElementById('t4-shipping-cost').value.trim();
    const shipDays = document.getElementById('t4-shipping-days').value.trim();
    const returnDays = document.getElementById('t4-return-days').value.trim();

    if(!name || !price){
      showToast('Lütfen ürün adı ve fiyat girin.', 'error');
      return;
    }

    schema = {
      "@context": "https://schema.org/",
      "@type": "Product",
      "name": name,
      "offers": {
        "@type": "Offer",
        "priceCurrency": currency,
        "price": price,
        "availability": availability,
        "url": "https://www.ornekmagaza.com/urun/" + name.toLowerCase().replace(/[^a-z0-9ığüşöç]+/gi,'-')
      }
    };

    if (shipCost || shipDays) {
      schema.offers.shippingDetails = {
        "@type": "OfferShippingDetails",
        "shippingRate": {
          "@type": "MonetaryAmount",
          "value": shipCost || "0",
          "currency": currency
        },
        "deliveryTime": {
          "@type": "ShippingDeliveryTime",
          "transitTime": {
            "@type": "QuantitativeValue",
            "maxValue": shipDays || "3",
            "unitCode": "d"
          }
        }
      };
    }

    if (returnDays) {
      schema.offers.hasMerchantReturnPolicy = {
        "@type": "MerchantReturnPolicy",
        "returnPolicyCategory": "https://schema.org/MerchantReturnFiniteReturnWindow",
        "merchantReturnDays": returnDays,
        "returnMethod": "https://schema.org/ReturnByMail",
        "returnFees": "https://schema.org/FreeReturn"
      };
    }
  } else if (schemaType === 'breadcrumb') {
    const path = document.getElementById('t4-breadcrumb-path').value.trim();
    if(!path) { showToast('Lütfen > ile ayrılmış kategori yolunu girin.', 'error'); return; }
    const parts = path.split('>').map(s => s.trim()).filter(s => s);
    schema = {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": parts.map((p, i) => ({
        "@type": "ListItem",
        "position": i + 1,
        "name": p,
        "item": "https://www.orneksite.com/" + p.toLowerCase().replace(/[^a-z0-9ığüşöç]+/gi,'-')
      }))
    };
  } else if (schemaType === 'category') {
    const desc = document.getElementById('t4-category-desc').value.trim();
    if(!name) { showToast('Lütfen kategori adı girin.', 'error'); return; }
    schema = {
      "@context": "https://schema.org",
      "@type": "CollectionPage",
      "name": name,
      "description": desc,
      "url": "https://www.orneksite.com/kategori/" + name.toLowerCase().replace(/[^a-z0-9ığüşöç]+/gi,'-')
    };
  }

  document.getElementById('t4-output-code').textContent = JSON.stringify(schema, null, 2);
  document.getElementById('t4-empty-note').classList.add('hidden');
  document.getElementById('t4-output-card').classList.remove('hidden');
  document.getElementById('t4-copy-btn').classList.remove('hidden');
  state.schemaGenerated = true;
  showToast('JSON-LD şeması üretildi.', 'success');
});

document.getElementById('t4-copy-btn')?.addEventListener('click', async () => {
  const text = document.getElementById('t4-output-code').textContent;
  try{
    await navigator.clipboard.writeText(text);
    showToast('Kopyalandı.', 'success');
  }catch(e){
    showToast('Kopyalama başarısız oldu.', 'error');
  }
});

/* ============================================================
   TAB 4 — YAPAY ZEKA DESTEKLİ SCHEMA (NER) VE LLMS.TXT ÜRETİCİ
============================================================ */

// 1. NER Modeli ile Sekme 1'deki Metinden Şema Bilgilerini Çıkarma
document.getElementById('t4-ai-extract-btn')?.addEventListener('click', async () => {
  const content = (document.getElementById('t1-content')?.value || '');
  if (!content.trim()) {
    showToast('Önce Sekme 1\'de bir içerik metni hazırlayın veya URL\'den veri çekin.', 'error');
    return;
  }

  const btn = document.getElementById('t4-ai-extract-btn');
  const label = document.getElementById('t4-ai-extract-label');
  btn.disabled = true;
  label.innerHTML = '<span class="spinner"></span> Çıkarılıyor...';

  const schemaType = document.getElementById('t4-schema-type').value;
  const isLocal = (schemaType === 'local');

  const prompt = `Aşağıdaki web sitesi metninden Varlık İsmi Tanıma (NER) mantığıyla şu alanları çıkar ve SADECE JSON formatında ver:
  ${isLocal ? 
    '{"name": "İşletme Adı", "phone": "Telefon Numarası", "address": "Açık Adres"}' : 
    '{"name": "Ürün Adı", "price": 1299.90, "currency": "TRY", "stock": "https://schema.org/InStock"}'
  }
  Metin:
  ${content.substring(0, 3500)}`;

  try {
    if (isLocal) {
      // Yapay Zeka yerine Hızlı Regex Çıkarımı
      const phoneMatch = content.match(/(?:\+90|0)?\s?[1-9]\d{2}\s?\d{3}\s?\d{2}\s?\d{2}/);
      const addressMatch = content.match(/([A-ZÇĞİÖŞÜ][a-zçğıöşü]+\s+(?:Mah\.|Mahallesi|Cad\.|Caddesi|Sok\.|Sokak)[^\n,.]+)/i);
      
      document.getElementById('t4-phone').value = phoneMatch ? phoneMatch[0].trim() : '';
      document.getElementById('t4-address').value = addressMatch ? addressMatch[0].trim() : '';
      document.getElementById('t4-name').value = document.getElementById('t7-customer').value || '';
    } else {
      // Ürün için Regex Çıkarımı
      const priceMatch = content.match(/(?:₺|TL|TRY)\s*(\d+(?:[.,]\d{2})?)|(\d+(?:[.,]\d{2})?)\s*(?:₺|TL|TRY)/i);
      let extractedPrice = '';
      if(priceMatch) {
         extractedPrice = (priceMatch[1] || priceMatch[2]).replace(',', '.');
      }
      document.getElementById('t4-price').value = extractedPrice;
      document.getElementById('t4-currency').value = 'TRY';
      document.getElementById('t4-stock').value = 'https://schema.org/InStock';
      document.getElementById('t4-name').value = 'Ürün Adı (' + (document.getElementById('t7-customer').value || '') + ')';
    }

    showToast('Şema bilgileri Regex ile metinden anında çıkarıldı!', 'success');
  } catch (err) {
    console.error('[Regex Extract]', err);
    showToast('Bilgiler çıkarılamadı.', 'error');
  } finally {
    window.dispatchEvent(new Event('t4-generate-btn-done'));
    window.dispatchEvent(new Event('t4-ai-extract-btn-done'));
    btn.disabled = false;
    label.innerHTML = '<span id="t4-ai-extract-label">Otomatik Doldur</span>';
  }
});

// 2. Yapay Zeka Tanıtım Dosyası (llms.txt) Üretici
document.getElementById('t4-llmstxt-btn')?.addEventListener('click', async () => {
  const content = (document.getElementById('t1-content')?.value || '');
  const clientName = document.getElementById('t7-customer').value || 'Müşteri Web Sitesi';

  const btn = document.getElementById('t4-llmstxt-btn');
  const label = document.getElementById('t4-llmstxt-label');
  btn.disabled = true;
  label.innerHTML = '<span class="spinner"></span> Üretiliyor...';

  const prompt = `Aşağıdaki site metnini ve bilgilerini kullanarak ChatGPT, Claude ve Perplexity gibi yapay zeka arama motorlarının sitenin kimliğini, hizmetlerini ve ürünlerini kusursuz anlaması için standart Markdown formatında bir "llms.txt" dosyası oluştur.
  Dosya şunları içermeli: # Proje/Firma Adı, > Kısa Özet, ## Hizmetler/Ürünler, ve ## İletişim/Detaylar başlıkları. Yalnızca metni ver.

  Firma/Proje: ${clientName}
  Metin:
  ${content.substring(0, 3000)}`;

  const mockData = () => `# ${clientName}\n\n> Bu dosya yapay zeka motorları için optimize edilmiş resmi tanıtım belgesidir.\n\n## Hakkımızda\n${clientName}, sektöründe lider dijital pazarlama ve SEO çözümleri sunar.\n\n## Temel Hizmetler\n- Yapay Zeka Destekli SEO\n- Teknik SEO ve Optimizasyon\n- E-Ticaret ve Kurumsal Danışmanlık`;

  try {
    const raw = await callGemini(prompt, mockData);
    const cleaned = cleanJsonResponse(raw).replace(/^```markdown\s*/i, '').replace(/^```\s*/, '').replace(/```\s*$/, '');
    
    document.getElementById('t4-llmstxt-output').value = cleaned;
    showToast('llms.txt dosyası başarıyla üretildi.', 'success');
  } catch (err) {
    console.error('[LLMs.txt]', err);
    showToast('Dosya üretilemedi.', 'error');
  } finally {
    window.dispatchEvent(new Event('t4-llmstxt-btn-done'));
    btn.disabled = false;
    label.innerHTML = '<span id="t4-llmstxt-label">AI ile Oluştur</span>';
  }
});

// Kopyalama Butonu
document.getElementById('t4-llmstxt-copy-btn')?.addEventListener('click', async () => {
  const text = document.getElementById('t4-llmstxt-output').value;
  if (!text) { showToast('Önce llms.txt dosyası üretin.', 'error'); return; }
  try {
    await navigator.clipboard.writeText(text);
    showToast('llms.txt panoya kopyalandı.', 'success');
  } catch (e) {
    showToast('Kopyalama başarısız.', 'error');
  }
});

// Dosya Olarak İndirme Butonu
document.getElementById('t4-llmstxt-download-btn')?.addEventListener('click', () => {
  const text = document.getElementById('t4-llmstxt-output').value;
  if (!text) { showToast('Önce llms.txt dosyası üretin.', 'error'); return; }
  const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'llms.txt';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
  showToast('llms.txt indiriliyor...', 'success');
});

// ============================================================
//   MASTER LLMS.TXT (SITEMAP TABANLI)
// ============================================================
document.getElementById('t4-master-llmstxt-btn')?.addEventListener('click', async () => {
  const sitemapUrl = document.getElementById('t4-sitemap-url').value.trim();
  if(!sitemapUrl) { showToast('Lütfen sitemap.xml adresi girin.', 'error'); return; }
  
  const btn = document.getElementById('t4-master-llmstxt-btn');
  const label = document.getElementById('t4-master-llmstxt-label');
  const originalLabel = label.textContent;
  
  btn.disabled = true;
  label.textContent = 'Taranıyor...';
  
  document.getElementById('t4-master-llmstxt-wrap').classList.remove('hidden');
  document.getElementById('t4-master-llmstxt-output').value = 'Sitemap taranıyor ve GEO yapısı çıkarılıyor... Lütfen bekleyin.';
  
  try {
    // 1. Fetch Sitemap using backend API
    const targetUrl = `http://localhost:3000/api/sitemap?url=${encodeURIComponent(sitemapUrl)}`;
    const res = await fetch(targetUrl);
    if(!res.ok) {
       const errData = await res.json().catch(() => ({}));
       throw new Error(errData.error || 'Sitemap erişilemedi. Lütfen adresi kontrol edin.');
    }
    const urls = await res.json();
    
    // Fallback if not an XML sitemap or empty
    if(!Array.isArray(urls) || urls.length === 0) {
      throw new Error("Geçerli bir sitemap XML formatı bulunamadı.");
    }
    
    const selectedUrls = urls;
    
    let masterContent = `# SİTE GENELİ BİLGİ DOSYASI (llms.txt)\n\n`;
    masterContent += `Bu dosya, büyük dil modellerinin (LLM) site hiyerarşisini ve bağlamını kavraması için Sitemap üzerinden otomatik olarak ${new Date().toISOString().split('T')[0]} tarihinde derlenmiştir.\n\n`;
    masterContent += `## Site Hiyerarşisi\nToplanan Sayfa Sayısı: ${selectedUrls.length}\n\n`;
    
    selectedUrls.forEach((url, i) => {
      masterContent += `- [Sayfa ${i+1}](${url})\n`;
    });
    
    masterContent += `\n## Anahtar Sayfalar ve İçerik Özetleri\n\n`;
    
    // Akıllı Kuyruk (Smart Queue) ile gerçek içerikleri asenkron çekme
    const queue = [...selectedUrls];
    let completed = 0;
    const concurrency = 5; // Aynı anda en fazla 5 istek
    const results = new Array(selectedUrls.length);
    
    async function worker() {
      while (queue.length > 0) {
        const url = queue.shift();
        const index = selectedUrls.indexOf(url);
        try {
           label.textContent = `%${Math.round((completed / selectedUrls.length)*100)} Taranıyor (${completed}/${selectedUrls.length})`;
           const scrapeRes = await fetch('http://localhost:3000/api/scrape?url=' + encodeURIComponent(url));
           const data = await scrapeRes.json();
           const title = data.title || url.split('/').pop().replace(/-/g, ' ') || "Sayfa";
           const textSample = data.text ? data.text.substring(0, 300).replace(/\n/g, ' ') : "İçerik analiz edilemedi.";
           
           results[index] = `### ${title.toUpperCase()}\n- URL: ${url}\n- Özet İçerik: ${textSample}...\n\n`;
        } catch(e) {
           results[index] = `### ${url.split('/').pop() || 'Sayfa'}\n- URL: ${url}\n- Durum: Tarama Başarısız\n\n`;
        }
        completed++;
        label.textContent = `%${Math.round((completed / selectedUrls.length)*100)} Taranıyor (${completed}/${selectedUrls.length})`;
      }
    }
    
    const workers = [];
    for(let i = 0; i < Math.min(concurrency, selectedUrls.length); i++) {
      workers.push(worker());
    }
    
    await Promise.all(workers);
    
    results.forEach(r => masterContent += r);
    masterContent += `## Sistem Notu\nBu dosya SEO Copilot tarafından Master Modülü kullanılarak üretilmiştir.`;
    
    document.getElementById('t4-master-llmstxt-output').value = masterContent;
    showToast('Master llms.txt üretildi!', 'success');
  } catch (err) {
    document.getElementById('t4-master-llmstxt-output').value = 'Hata: ' + err.message + '\n\nManuel bir URL deneyin veya proxy izinlerini kontrol edin.';
    showToast('Sitemap tarama hatası.', 'error');
  } finally {
    window.dispatchEvent(new Event('t4-master-llmstxt-btn-done'));
    btn.disabled = false;
    label.textContent = originalLabel;
  }
});

document.getElementById('t4-master-llmstxt-copy-btn')?.addEventListener('click', async () => {
  const text = document.getElementById('t4-master-llmstxt-output').value;
  if(!text) return;
  try {
    await navigator.clipboard.writeText(text);
    showToast('Kopyalandı.', 'success');
  } catch(e) { showToast('Kopyalama başarısız.', 'error'); }
});

document.getElementById('t4-master-llmstxt-download-btn')?.addEventListener('click', () => {
  const text = document.getElementById('t4-master-llmstxt-output').value;
  if(!text) return;
  const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'master-llms.txt';
  a.click();
  URL.revokeObjectURL(url);
  showToast('İndiriliyor...', 'success');
});

// ============================================================
//   ŞEMA DENETLEYİCİ VE ONARICI (TAB 03)
// ============================================================
document.getElementById('t3-schema-audit-btn')?.addEventListener('click', async () => {
  let targetUrl = document.getElementById('t3-schema-url').value.trim();
  // Fallback to Quick Audit URL if available
  if(!targetUrl) {
    targetUrl = document.getElementById('t3-qa-url') ? document.getElementById('t3-qa-url').value.trim() : '';
  }
  if(!targetUrl) {
    showToast('Lütfen taranacak bir sayfa URL\'si girin.', 'error');
    return;
  }
  
  const btn = document.getElementById('t3-schema-audit-btn');
  const label = document.getElementById('t3-schema-audit-label');
  const resultsDiv = document.getElementById('t3-schema-results');
  const originalLabel = label.textContent;
  
  btn.disabled = true;
  label.textContent = 'Denetleniyor...';
  resultsDiv.classList.remove('hidden');
  resultsDiv.innerHTML = '<span class="small muted"><span class="spinner" style="border-top-color:var(--accent);"></span> URL taranıyor, şemalar aranıyor...</span>';
  
  try {
    let urlsToScan = [targetUrl];
    // Kök URL girilmişse veya sitemap varsa tüm siteyi tarayalım
    try {
      const sitemapRes = await fetch(`http://localhost:3000/api/sitemap?url=${encodeURIComponent(targetUrl)}`);
      if(sitemapRes.ok) {
        const sUrls = await sitemapRes.json();
        if(Array.isArray(sUrls) && sUrls.length > 0) urlsToScan = sUrls;
      }
    } catch(e) {}
    
    let parsedSchemas = [];
    let errorCount = 0;
    
    // Akıllı Kuyruk (Smart Queue)
    const queue = [...urlsToScan];
    let completed = 0;
    const concurrency = 5;
    
    const updateProgress = () => {
      label.textContent = `%${Math.round((completed / urlsToScan.length)*100)} Taranıyor`;
      resultsDiv.innerHTML = `<span class="small muted"><span class="spinner" style="border-top-color:var(--accent);"></span> ${completed}/${urlsToScan.length} Sayfa tarandı... Toplam ${parsedSchemas.length} Şema bulundu.</span>`;
    };
    
    updateProgress();
    
    async function worker() {
      while (queue.length > 0) {
        const url = queue.shift();
        try {
          const res = await fetch(`http://localhost:3000/api/schema?url=${encodeURIComponent(url)}`);
          if(res.ok) {
             const schemasArray = await res.json();
             if(Array.isArray(schemasArray)) {
                schemasArray.forEach(scriptContent => {
                   try {
                     let json = JSON.parse(scriptContent);
                     let errors = [];
                     if(!json['@context']) errors.push('@context eksik.');
                     if(!json['@type']) errors.push('@type eksik.');
                     if(json['@type'] === 'Product') {
                        if(!json.offers) errors.push('Product için offers nesnesi eksik.');
                        else {
                          if(!json.offers.price) errors.push('Fiyat eksik.');
                          if(!json.offers.priceCurrency) errors.push('Para birimi eksik.');
                        }
                     }
                     parsedSchemas.push({ url: url, data: json, errors: errors });
                     if(errors.length > 0) errorCount++;
                   } catch(e) {
                     parsedSchemas.push({ url: url, data: null, errors: ['Geçersiz JSON formatı (Syntax Error).'] });
                     errorCount++;
                   }
                });
             }
          }
        } catch(e) { }
        completed++;
        updateProgress();
      }
    }
    
    const workers = [];
    for(let i = 0; i < Math.min(concurrency, urlsToScan.length); i++) {
      workers.push(worker());
    }
    
    await Promise.all(workers);
    
    let htmlOut = '';
    
    if(parsedSchemas.length === 0) {
      htmlOut += `<div style="background:var(--bg-card); padding:16px; border:1px solid var(--border-soft); border-radius:6px; max-height:400px; overflow-y:auto;">`;
      htmlOut += `<p class="small" style="color:var(--danger);">${urlsToScan.length} sayfa tarandı, hiçbir Yapılandırılmış Veri (JSON-LD) bulunamadı!</p>`;
      htmlOut += `<br><button class="btn btn--primary btn--sm mt-12" id="t3-schema-fix-btn">Tüm Sayfalar İçin Şema Üret</button>`;
      htmlOut += `</div>`;
      window.tempSchemasToFix = urlsToScan.map(url => ({ url: url, data: null }));
    } else {
      htmlOut += `<div style="background:var(--bg-card); padding:16px; border:1px solid var(--border-soft); border-radius:6px; max-height:400px; overflow-y:auto;">`;
      htmlOut += `<strong>Tarama Sonucu:</strong> ${urlsToScan.length} sayfada toplam ${parsedSchemas.length} şema bulundu. <span style="color:${errorCount > 0 ? 'var(--danger)' : 'var(--success)'}; font-weight:600;">${errorCount} Hatalı</span>.`;
      
      if(errorCount > 0) {
        htmlOut += `<br><button class="btn btn--primary btn--sm mt-12" id="t3-schema-fix-btn">Hataları Otomatik Onar</button>`;
        window.tempSchemasToFix = parsedSchemas;
      }
      
      htmlOut += `<div class="mt-12" style="font-size:12.5px;">`;
      // Sadece hatalıları veya ilk 100 tanesini gösterelim (UI kasmasın)
      const displaySchemas = parsedSchemas.slice(0, 100);
      displaySchemas.forEach((s, idx) => {
         const shortUrl = s.url.replace(/^https?:\/\/[^\/]+/, '');
         if(s.errors.length > 0) {
           htmlOut += `<div style="margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid var(--border-soft);">`;
           htmlOut += `<span style="color:var(--danger);">[Hata]</span> <a href="${s.url}" target="_blank" style="color:var(--accent);">${shortUrl || '/'}</a> <br> ➔ ${s.errors.join(', ')}`;
           htmlOut += `</div>`;
         } else {
           htmlOut += `<div style="margin-bottom:8px; color:var(--success);">[Kusursuz] <a href="${s.url}" target="_blank" style="color:inherit;">${shortUrl || '/'}</a> (${s.data['@type']})</div>`;
         }
      });
      if(parsedSchemas.length > 100) htmlOut += `<div style="color:var(--muted-2); margin-top:8px;">... ve ${parsedSchemas.length - 100} şema daha.</div>`;
      htmlOut += `</div></div>`;
    }
    
    htmlOut += `<div id="t3-schema-fixed-wrap" class="mt-12 hidden">
                  <strong>Onarılan / Üretilen JSON-LD Kodları:</strong>
                  <textarea class="input mt-8" id="t3-schema-fixed-output" rows="10" style="font-family:\'Source Sans Pro\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; font-size:12px;"></textarea>
                  <button class="btn btn--ghost btn--sm mt-8" id="t3-schema-fixed-copy">Kodu Kopyala</button>
                </div>`;
                
    resultsDiv.innerHTML = htmlOut;
    
    // Attach listener to Fix button if exists
    const fixBtn = document.getElementById('t3-schema-fix-btn');
    if(fixBtn) {
       fixBtn.addEventListener('click', () => {
          let fixedSchemas = window.tempSchemasToFix.map(s => {
             let clone = s.data ? JSON.parse(JSON.stringify(s.data)) : { "@context": "https://schema.org" };
             if(!clone['@context']) clone['@context'] = 'https://schema.org';
             
             // Akıllı Type Seçimi (Eğer yeni üretiliyorsa)
             if(!clone['@type']) {
                const urlLower = s.url.toLowerCase();
                if (urlLower.endsWith('.com') || urlLower.endsWith('.com/') || urlLower.endsWith('.tr') || urlLower.endsWith('.tr/')) {
                    clone['@type'] = ['WebSite', 'Organization'];
                    clone.name = urlLower.replace(/^https?:\/\//, '').split('/')[0];
                    clone.url = s.url;
                } else if (urlLower.includes('/urun/') || urlLower.includes('/product/')) {
                    clone['@type'] = 'Product';
                } else if (urlLower.includes('/blog/') || urlLower.includes('/haber/')) {
                    clone['@type'] = 'Article';
                } else {
                    clone['@type'] = 'WebPage';
                }
             }

             if(clone['@type'] === 'Product' || (Array.isArray(clone['@type']) && clone['@type'].includes('Product'))) {
               if(!clone.offers) clone.offers = { "@type": "Offer" };
               if(!clone.offers.price) clone.offers.price = "0.00";
               if(!clone.offers.priceCurrency) clone.offers.priceCurrency = "TRY";
               if(!clone.offers.availability) clone.offers.availability = "https://schema.org/InStock";
             }
             return { url: s.url, data: clone };
          });
          
          let outputText = '';
          // 1500+ sayfa varsa browser çökmemesi için ilk 100 tanesini kopyalayalım
          const limit = Math.min(fixedSchemas.length, 100);
          for(let i=0; i<limit; i++){
             outputText += `<!-- URL: ${fixedSchemas[i].url} -->\n<script type="application/ld+json">\n${JSON.stringify(fixedSchemas[i].data, null, 2)}\n</script>\n\n`;
          }
          if (fixedSchemas.length > 100) {
              outputText += `\n<!-- Not: Performans için sadece ilk 100 sayfa kodu gösterilmektedir. (Toplam: ${fixedSchemas.length}) -->`;
          }
          
          document.getElementById('t3-schema-fixed-wrap').classList.remove('hidden');
          document.getElementById('t3-schema-fixed-output').value = outputText;
          showToast('Bozuk şemalar onarıldı.', 'success');
       });
    }
    
    document.getElementById('t3-schema-fixed-copy')?.addEventListener('click', async () => {
       try {
          await navigator.clipboard.writeText(document.getElementById('t3-schema-fixed-output').value);
          showToast('Onarılmış kod kopyalandı.', 'success');
       }catch(err){ showToast('Kopyalama başarısız.', 'error'); }
    });

  } catch (err) {
    resultsDiv.innerHTML = '<p class="small" style="color:var(--danger);">Tarama başarısız: ' + escapeHtml(err.message) + '</p>';
  } finally {
    window.dispatchEvent(new Event('t3-schema-audit-btn-done'));
    btn.disabled = false;
    label.textContent = originalLabel;
  }
});


/* ============================================================
   TAB 6 — GENEL SKOR & SATIŞ
============================================================ */
const GAUGE_CIRC = 2 * Math.PI * 80; // ≈ 502.4

function scoreColor(v){
  if(v >= 80) return getComputedStyle(document.documentElement).getPropertyValue('--success').trim();
  if(v >= 55) return getComputedStyle(document.documentElement).getPropertyValue('--warn').trim();
  return getComputedStyle(document.documentElement).getPropertyValue('--danger').trim();
}
function scoreStatusLabel(v){
  if(v >= 80) return { cls:'status-good', txt:'İyi' };
  if(v >= 55) return { cls:'status-mid', txt:'Orta' };
  return { cls:'status-bad', txt:'Zayıf' };
}

async function computeAndRenderScore(){
  // 1. İÇERİK SKORU (Kelime Sayısına göre)
  const contentText = (document.getElementById('t1-content')?.value || '').trim();
  const wordCount = contentText ? contentText.split(/\s+/).length : 0;
  // Kelime başına 0.2 puan (Max 100). Örn: 400 kelime = 80 puan
  let calculatedContentScore = Math.min(100, Math.floor(wordCount * 0.2));
  // AI kullanıldıysa +20 bonus
  if(t1LastResult) calculatedContentScore = Math.min(100, calculatedContentScore + 20);

  // 2. ANAHTAR KELİME STRATEJİSİ
  let calculatedKeywordScore = 0;
  if(state.currentClientId){
     try {
       const res = await fetch('api/client_keywords.php?client_id='+state.currentClientId); const { data: kwData, error } = await res.json();
       if(kwData && kwData.length > 0) {
          calculatedKeywordScore = 100;
       }
     } catch(err){}
  } else {
     const kwRows = Array.from(document.querySelectorAll('#t2-results-tbody tr'));
     if(kwRows.length > 0 && !kwRows[0].textContent.includes('bulunamadı')) {
       calculatedKeywordScore = 100;
     }
  }

  // 3. TEKNİK SEO (Gerçek PageSpeed Skoru)
  let calculatedTechnicalScore = state.technicalScore !== null ? state.technicalScore : 0;

  // 4. SCHEMA ÜRETİCİ (Kod oluşturuldu mu?)
  let calculatedSchemaScore = state.schemaGenerated ? 100 : 0;

  // 5. SİTE DIŞI & YEREL SEO (Checklist + Backlink Sayısı)
  const checkedItems = document.querySelectorAll('#t5-checklist input[type="checkbox"]:checked').length;
  const checklistScore = checkedItems * 10; // 7 madde = Max 70 Puan
  
  let calculatedOffsiteScore = Math.min(100, checklistScore);

  // GENEL ORTALAMA HESABI
  const subs = [
    { label:'İçerik & İç Linkleme', value: calculatedContentScore },
    { label:'Anahtar Kelime Stratejisi', value: calculatedKeywordScore },
    { label:'Teknik SEO', value: calculatedTechnicalScore },
    { label:'Yapılandırılmış Veri (Schema)', value: calculatedSchemaScore },
    { label:'Site Dışı & Yerel SEO', value: calculatedOffsiteScore },
  ];
  const overall = Math.round(subs.reduce((a,s) => a + s.value, 0) / subs.length);

  // DOM GÜNCELLEMESİ
  const numEl = document.getElementById('t6-score-num');
  const circle = document.getElementById('t6-gauge-circle');
  if (numEl) {
      numEl.textContent = overall;
      numEl.style.color = scoreColor(overall);
  }
  if (circle) {
      circle.style.stroke = scoreColor(overall);
      circle.style.strokeDasharray = GAUGE_CIRC;
      circle.style.strokeDashoffset = GAUGE_CIRC * (1 - overall/100);
  }

  const wrap = document.getElementById('t6-sub-scores');
  if (wrap) {
    wrap.innerHTML = '';
    subs.forEach(s => {
    const st = scoreStatusLabel(s.value);
    const row = document.createElement('div');
    row.className = 'meter-row';
    row.innerHTML =
      '<div class="meter-row__head"><span class="label">' + s.label + '</span>' +
      '<span class="flex gap-8"><span class="value">' + s.value + '</span><span class="status-chip ' + st.cls + '">' + st.txt + '</span></span></div>' +
      '<div class="meter-track"><div class="meter-fill fill-' + (s.value>=80?'good':s.value>=55?'mid':'bad') + '" style="width:' + s.value + '%;"></div></div>';
    wrap.appendChild(row);
  });
  } // end if wrap

  // UPSELL (SATIŞ) BANNER GÖSTERİMİ
  const banner = document.getElementById('t6-upsell-banner');
  const titleEl = document.getElementById('t6-upsell-title');
  const textEl = document.getElementById('t6-upsell-text');

  // Sekme 1'deki seçimi kontrol et (E-Ticaret mi Kurumsal mı?)
  const contentTypeEl = document.querySelector('input[name="contentType"]:checked');
  const isEcommerce = contentTypeEl && contentTypeEl.value === 'ecommerce';

  let packageTitle = "";
  let packagePrice = "";
  let packageDesc = "";

  // AdresGezgini Karar Ağacı
  if (calculatedTechnicalScore > 0 && calculatedTechnicalScore < 65 && calculatedContentScore >= 65) {
    packageTitle = "Teknik Altyapı Büyümeyi Engelliyor!";
    packagePrice = isEcommerce ? "21.950 ₺" : "18.350 ₺";
    packageDesc = `Sitenin içerik altyapısı güçlü ancak teknik SEO metrikleri (Hız, Core Web Vitals) sınırın altında. Müşteriye <strong>${isEcommerce ? 'E-Ticaret' : 'Kurumsal'} Teknik SEO</strong> paketi satışı önerilir.`;
  
  } else if (calculatedContentScore > 0 && calculatedContentScore < 65 && calculatedTechnicalScore >= 65) {
    packageTitle = "İçerik ve Şema Eksikliği!";
    packagePrice = isEcommerce ? "13.700 ₺" : "11.500 ₺";
    packageDesc = `Sitenin teknik altyapısı sağlam ancak içerik, yapılandırılmış veri ve satın alma odaklı metinler zayıf. Müşteriye <strong>${isEcommerce ? 'E-Ticaret' : 'Kurumsal'} AI SEO</strong> paketi satışı önerilir.`;
  
  } else if (overall > 0 && overall < 70) {
    packageTitle = "Kapsamlı İyileştirme Gerekiyor!";
    packagePrice = isEcommerce ? "29.900 ₺" : "25.900 ₺";
    packageDesc = `Sitenin hem teknik hem de içerik tarafında ciddi eksikleri var. Hedeflenen organik büyüme için müşteriye <strong>${isEcommerce ? 'E-Ticaret' : 'Kurumsal'} En Kapsamlı (AI SEO + Teknik SEO)</strong> paketi satışı önerilir.`;
  }

  // Ekrana Basma
  if (packageTitle !== "") {
    titleEl.textContent = packageTitle;
    textEl.innerHTML = `${packageDesc} <br>
      <span class="price" style="font-size: 14.5px; display:inline-block; margin-top:8px; font-weight:700; color:var(--accent);">
        Önerilen Paket Fiyatı: ${packagePrice} + KDV
      </span>`;
    banner.classList.remove('hidden');
  } else {
    banner.classList.add('hidden');
  }

  // Anlık görüntü kaydı için son hesaplanan skorları sakla
  state.lastScores = {
    content: calculatedContentScore,
    keyword: calculatedKeywordScore,
    technical: calculatedTechnicalScore,
    schema: calculatedSchemaScore,
    offsite: calculatedOffsiteScore,
    overall: overall,
  };
}

const t6Btn = document.getElementById('t6-refresh-btn'); if(t6Btn) t6Btn.addEventListener('click', computeAndRenderScore);
computeAndRenderScore();

/* ============================================================
   TAB 6 — SEARCH CONSOLE & GA4 (gerçek veri)
============================================================ */
document.getElementById('t6-fetch-gsc-btn')?.addEventListener('click', async () => {
  if(!requireGoogleAuth()) return;
  const siteUrl = document.getElementById('t6-gsc-url').value.trim();
  if(!siteUrl){ showToast('Önce Search Console Site URL alanını doldurup kaydedin.', 'error'); return; }

  const resultEl = document.getElementById('t6-gsc-result');
  document.getElementById('t6-real-data-wrap').style.display = 'grid';
  resultEl.innerHTML = '<span class="small muted"><span class="spinner" style="border-top-color:var(--accent);"></span> Search Console verileri çekiliyor...</span>';

  const endDate = new Date();
  const startDate = new Date();
  startDate.setDate(startDate.getDate() - 28);
  const fmt = (d) => d.toISOString().slice(0,10);

  try{
    const res = await fetch(
      'https://www.googleapis.com/webmasters/v3/sites/' + encodeURIComponent(siteUrl) + '/searchAnalytics/query',
      {
        method: 'POST',
        headers: {
          'Authorization': 'Bearer ' + googleAccessToken,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ startDate: fmt(startDate), endDate: fmt(endDate), dimensions: [] }),
      }
    );
    if(!res.ok){
      const errBody = await res.json().catch(() => null);
      throw new Error((errBody && errBody.error && errBody.error.message) || ('HTTP ' + res.status));
    }
    
    // Also fetch keyword data for Striking Distance
    const resKeywords = await fetch(
      'https://www.googleapis.com/webmasters/v3/sites/' + encodeURIComponent(siteUrl) + '/searchAnalytics/query',
      {
        method: 'POST',
        headers: {
          'Authorization': 'Bearer ' + googleAccessToken,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ startDate: fmt(startDate), endDate: fmt(endDate), dimensions: ['query'], rowLimit: 500 }),
      }
    ).catch(()=>null);

    const data = await res.json();
    const row = (data.rows && data.rows[0]) || { clicks: 0, impressions: 0, ctr: 0, position: 0 };

    let strikingKeywordsHtml = '<span class="small muted">Uygun kelime bulunamadı.</span>';
    const brandName = document.getElementById('t6-brand-name').value.trim().toLowerCase();
    let nonBrandHtml = '';
    
    if(resKeywords && resKeywords.ok) {
       const kwData = await resKeywords.json();
       
       // Striking Distance (11-20)
       const striking = (kwData.rows || []).filter(r => r.position >= 11 && r.position <= 20).sort((a,b) => b.impressions - a.impressions).slice(0, 15);
       if(striking.length > 0) {
         strikingKeywordsHtml = striking.map(r => `<span class="tag tag--warn" style="font-size:11px;">${r.keys[0]} (${r.position.toFixed(1)})</span>`).join('');
       }
       
       // Non-Brand Filter
       if (brandName) {
         let nbClicks = 0, nbImp = 0;
         const nbRows = (kwData.rows || []).filter(r => !r.keys[0].toLowerCase().includes(brandName));
         nbRows.forEach(r => { nbClicks += r.clicks; nbImp += r.impressions; });
         nonBrandHtml = 
           '<div style="margin-top:12px; padding-top:12px; border-top:1px dashed var(--border-soft);">' +
             '<div class="meter-row__head"><span class="label" style="font-weight:600;color:var(--ink-2);">Markasız Organik Büyüme</span></div>' +
             '<div class="meter-row"><div class="meter-row__head"><span class="label">Markasız Tıklama</span><span class="value" style="color:var(--success); font-weight:700;">' + nbClicks + '</span></div></div>' +
             '<div class="meter-row"><div class="meter-row__head"><span class="label">Markasız Gösterim</span><span class="value" style="color:var(--success); font-weight:700;">' + nbImp + '</span></div></div>' +
           '</div>';
       }
    }

    // Raporda kullanılmak üzere ham veriyi de sakla
    state.lastGSC = {
      clicks: row.clicks,
      impressions: row.impressions,
      ctr: row.ctr,
      position: row.position,
      rangeLabel: 'Son 28 Gün',
    };

    resultEl.innerHTML =
      '<h4 style="color:var(--info);"><span class="dot-sm" style="background:var(--info);"></span>Search Console (Son 28 Gün)</h4>' +
      '<div class="meter-row"><div class="meter-row__head"><span class="label">Tıklama</span><span class="value">' + row.clicks + '</span></div></div>' +
      '<div class="meter-row"><div class="meter-row__head"><span class="label">Gösterim</span><span class="value">' + row.impressions + '</span></div></div>' +
      '<div class="meter-row"><div class="meter-row__head"><span class="label">TO (CTR)</span><span class="value">' + (row.ctr*100).toFixed(2) + '%</span></div></div>' +
      '<div class="meter-row"><div class="meter-row__head"><span class="label">Ort. Pozisyon</span><span class="value">' + row.position.toFixed(1) + '</span></div></div>' +
      nonBrandHtml;
    
    document.getElementById('t6-striking-keywords').innerHTML = strikingKeywordsHtml;
    document.getElementById('t6-striking-distance-card').classList.remove('hidden');

    showToast('Search Console verileri çekildi.', 'success');
  }catch(err){
    console.error('[Search Console]', err);
    resultEl.innerHTML = '<p class="small" style="color:var(--danger);">Hata: ' + escapeHtml(err.message) + '</p>';
  }
});

document.getElementById('t6-fetch-ga4-btn')?.addEventListener('click', async () => {
  if(!requireGoogleAuth()) return;
  const propertyId = document.getElementById('t6-ga4-id').value.trim();
  if(!propertyId){ showToast('Önce GA4 Property ID alanını doldurup kaydedin.', 'error'); return; }

  const resultEl = document.getElementById('t6-ga4-result');
  document.getElementById('t6-real-data-wrap').style.display = 'grid';
  resultEl.innerHTML = '<span class="small muted"><span class="spinner" style="border-top-color:var(--accent);"></span> GA4 verileri çekiliyor...</span>';

  try{
    const res = await fetch(
      'https://analyticsdata.googleapis.com/v1beta/properties/' + encodeURIComponent(propertyId) + ':runReport',
      {
        method: 'POST',
        headers: {
          'Authorization': 'Bearer ' + googleAccessToken,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          dateRanges: [{ startDate: '28daysAgo', endDate: 'today' }],
          metrics: [{ name: 'sessions' }, { name: 'activeUsers' }, { name: 'conversions' }, { name: 'engagementRate' }],
        }),
      }
    );
    if(!res.ok){
      const errBody = await res.json().catch(() => null);
      throw new Error((errBody && errBody.error && errBody.error.message) || ('HTTP ' + res.status));
    }
    const data = await res.json();
    const vals = (data.rows && data.rows[0] && data.rows[0].metricValues) || [];
    const get = (i) => vals[i] ? vals[i].value : '0';

    // Raporda kullanılmak üzere ham veriyi de sakla
    state.lastGA4 = {
      sessions: get(0),
      activeUsers: get(1),
      conversions: get(2),
      engagementRate: parseFloat(get(3)) || 0,
      rangeLabel: 'Son 28 Gün',
    };

    resultEl.innerHTML =
      '<h4 style="color:var(--violet);"><span class="dot-sm" style="background:var(--violet);"></span>GA4 (Son 28 Gün)</h4>' +
      '<div class="meter-row"><div class="meter-row__head"><span class="label">Oturum</span><span class="value">' + get(0) + '</span></div></div>' +
      '<div class="meter-row"><div class="meter-row__head"><span class="label">Aktif Kullanıcı</span><span class="value">' + get(1) + '</span></div></div>' +
      '<div class="meter-row"><div class="meter-row__head"><span class="label">Dönüşüm</span><span class="value">' + get(2) + '</span></div></div>' +
      '<div class="meter-row"><div class="meter-row__head"><span class="label">Etkileşim Oranı</span><span class="value">' + (parseFloat(get(3))*100).toFixed(1) + '%</span></div></div>';
    showToast('GA4 verileri çekildi.', 'success');
  }catch(err){
    console.error('[GA4]', err);
    resultEl.innerHTML = '<p class="small" style="color:var(--danger);">Hata: ' + escapeHtml(err.message) + '</p>';
  }
});

/* ============================================================
   TAB 6 — SKOR GEÇMİŞİ & TREND GRAFİĞİ
============================================================ */
document.getElementById('t6-snapshot-btn')?.addEventListener('click', async () => {
  if(!state.currentClientId){ showToast('Önce sol menüden bir müşteri seçin.', 'error'); return; }
  if(!state.lastScores){ showToast('Önce "Skoru Hesapla / Yenile" ile bir skor üretin.', 'error'); return; }

  const payload = {
    client_id: state.currentClientId,
    content_score: state.lastScores.content,
    keyword_score: state.lastScores.keyword,
    technical_score: state.lastScores.technical,
    schema_score: state.lastScores.schema,
    offsite_score: state.lastScores.offsite,
    overall_score: state.lastScores.overall,
  };
  try{
    const res = await fetch('api/score_history.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify([payload])}); const json = await res.json(); const error = json.error;
    if(error) throw error;
    showToast('Anlık görüntü kaydedildi.', 'success');
    await fetchScoreHistory();
  }catch(err){
    console.error('[Supabase] score_history insert hatası:', err);
    showToast('Kaydedilemedi: ' + (err.message || 'bilinmeyen hata'), 'error');
  }
});

async function fetchScoreHistory(){
  const wrap = document.getElementById('t6-trend-chart-wrap');
  if(!state.currentClientId){
    wrap.innerHTML = '<p class="empty-note">Önce sol menüden bir müşteri seçin.</p>';
    return;
  }
  try{
    const res = await fetch('api/score_history.php?client_id='+state.currentClientId); const { data, error } = await res.json();
    if(error) throw error;
    renderTrendChart(data || []);
  }catch(err){
    console.error('[Supabase] score_history select hatası:', err);
    wrap.innerHTML = '<p class="empty-note">Skor geçmişi yüklenemedi.</p>';
  }
}

function renderTrendChart(rows){
  const wrap = document.getElementById('t6-trend-chart-wrap');
  if(!rows.length){
    wrap.innerHTML = '<p class="empty-note">Henüz kaydedilmiş bir anlık görüntü yok.</p>';
    return;
  }
  const W = 680, H = 180, PAD = 24;
  const points = rows.map(r => r.overall_score);
  const maxV = 100, minV = 0;
  const stepX = rows.length > 1 ? (W - PAD*2) / (rows.length - 1) : 0;
  const toY = (v) => H - PAD - ((v - minV) / (maxV - minV)) * (H - PAD*2);
  const coords = points.map((v,i) => [PAD + i*stepX, toY(v)]);
  const pathD = coords.map((c,i) => (i===0?'M':'L') + c[0].toFixed(1) + ',' + c[1].toFixed(1)).join(' ');
  const areaD = pathD + ' L' + coords[coords.length-1][0].toFixed(1) + ',' + (H-PAD) + ' L' + coords[0][0].toFixed(1) + ',' + (H-PAD) + ' Z';

  const dots = coords.map((c,i) => {
    const dateLabel = rows[i].created_at ? new Date(rows[i].created_at).toLocaleDateString('tr-TR') : '';
    return '<circle cx="' + c[0].toFixed(1) + '" cy="' + c[1].toFixed(1) + '" r="4" fill="var(--accent)" stroke="#fff" stroke-width="2"><title>' + dateLabel + ' — ' + points[i] + '/100</title></circle>';
  }).join('');

  wrap.innerHTML =
    '<svg viewBox="0 0 ' + W + ' ' + H + '" style="width:100%; height:auto; max-height:220px;">' +
      '<line x1="' + PAD + '" y1="' + (H-PAD) + '" x2="' + (W-PAD) + '" y2="' + (H-PAD) + '" stroke="#EDEFF3" stroke-width="1"/>' +
      '<path d="' + areaD + '" fill="var(--accent-soft)" opacity="0.6"/>' +
      '<path d="' + pathD + '" fill="none" stroke="var(--accent)" stroke-width="2.5"/>' +
      dots +
    '</svg>' +
    '<div class="small muted mt-8">Son ' + rows.length + ' anlık görüntü — en yeni: ' + points[points.length-1] + '/100</div>';
}
/* ============================================================
   TAB 7 — AYLIK MÜŞTERİ RAPORU
============================================================ */
const todoByPackage = {
  kurumsal_ai: [
    'Yapay Zeka Uyumlu Site Yapısı Oluşturuldu',
    'Yapay Zeka Tanıtım Dosyaları (llms.txt) Eklendi',
    'Arama Motoru Kayıtları Tamamlandı',
    'Yapılandırılmış Veri (Schema) Düzenlemeleri Yapıldı',
    'Harita ve İşletme Görünürlüğü Optimizasyonu',
    'İçerik Düzenleme ve Geliştirme',
    'Kullanıcı Odaklı İçerik Güncellemeleri',
    'Site İçi İçerik Bağlantıları (İç Linkleme) Kurgulandı'
  ],
  kurumsal_teknik: [
    'Arama Motoru Kayıtları Tamamlandı',
    'Yapılandırılmış Veri (Schema) Düzenlemeleri Yapıldı',
    'Hız ve Core Web Vitals İyileştirmesi',
    'Mobil ve Görsel Optimizasyonu',
    'Site Haritası, Robots.txt ve Tarama Kontrolleri',
    'Kırık Link, SSL ve Teknik Hata Kontrolleri',
    'Google Search Console ve Kanonik URL Kontrolleri'
  ],
  kurumsal_kapsamli: [
    'Yapay Zeka Uyumlu Site Yapısı & Tanıtım Dosyaları',
    'Arama Motoru Kayıtları',
    'Yapılandırılmış Veri Düzenlemeleri',
    'Harita ve İşletme Görünürlüğü',
    'İçerik Düzenleme & Kullanıcı Odaklı İçerik',
    'Site İçi İçerik Bağlantıları',
    'Hız ve Core Web Vitals İyileştirmesi',
    'Mobil ve Görsel Optimizasyon',
    'Site Haritası, Robots.txt ve Tarama Kontrolleri',
    'Kırık Link, SSL ve Teknik Hata Kontrolleri',
    'Google Search Console ve Kanonik URL Kontrolleri'
  ],
  eticaret_ai: [
    'Yapay Zeka Uyumlu E-Ticaret Site Yapısı',
    'Yapay Zeka Tanıtım Dosyaları (llms.txt)',
    'Arama Motoru Kayıtları',
    'Ürün, Kategori ve Breadcrumb Schema Düzenlemeleri',
    'Kategori ve Ürün İçerik Planlama',
    'Kategori Açıklamaları ve Ürün İçerik Yapısı',
    'Satın Alma Odaklı İçerik',
    'Site İçi Kategori ve Ürün Bağlantıları'
  ],
  eticaret_teknik: [
    'Arama Motoru Kayıtları',
    'Ürün, Kategori ve Breadcrumb Schema Düzenlemeleri',
    'Hız ve Core Web Vitals İyileştirmesi',
    'Mobil ve Ürün Görsel Optimizasyon',
    'Site Haritası, Robots.txt ve Tarama Kontrolleri',
    'Filtre, Parametre URL ve Teknik Hata Kontrolleri',
    'Search Console, Kanonik URL ve Stok Kontrolleri'
  ],
  eticaret_kapsamli: [
    'Yapay Zeka Uyumlu E-Ticaret Yapısı & llms.txt',
    'Arama Motoru Kayıtları',
    'Ürün, Kategori ve Breadcrumb Schema Düzenlemeleri',
    'Kategori ve Ürün İçerik Planlama',
    'Satın Alma Odaklı İçerik & Site İçi Bağlantılar',
    'Hız ve Core Web Vitals İyileştirmesi',
    'Mobil ve Ürün Görsel Optimizasyon',
    'Site Haritası, Robots.txt ve Tarama Kontrolleri',
    'Filtre, Parametre URL ve Teknik Hata Kontrolleri',
    'Search Console, Kanonik URL ve Stok Kontrolleri'
  ]
};

document.getElementById('t7-package')?.addEventListener('change', function(){
  const pkg = this.value;
  const wrap = document.getElementById('t7-todo-wrap');
  const list = document.getElementById('t7-todo-list');
  if(!pkg){ wrap.classList.add('hidden'); list.innerHTML=''; return; }
  list.innerHTML = '';
  todoByPackage[pkg].forEach((task, i) => {
    const id = 't7-todo-' + i;
    const item = document.createElement('div');
    item.className = 'check-item';
    item.innerHTML = '<input type="checkbox" id="' + id + '"><label for="' + id + '">' + task + '</label>';
    list.appendChild(item);
  });
  wrap.classList.remove('hidden');
});
document.getElementById('t7-todo-list')?.addEventListener('change', (e) => {
  if(e.target.type === 'checkbox'){
    e.target.closest('.check-item').classList.toggle('checked', e.target.checked);
  }
});

/* --- Drag & Drop image upload --- */
const dz = document.getElementById('t7-dropzone');
const fileInput = document.getElementById('t7-file-input');

if (dz && fileInput) {
  dz.addEventListener('click', () => fileInput.click());
  ['dragenter','dragover'].forEach(evt => dz.addEventListener(evt, (e) => {
    e.preventDefault(); e.stopPropagation(); dz.classList.add('dragover');
  }));
  ['dragleave','drop'].forEach(evt => dz.addEventListener(evt, (e) => {
    e.preventDefault(); e.stopPropagation(); dz.classList.remove('dragover');
  }));
  dz.addEventListener('drop', (e) => {
    const files = e.dataTransfer.files;
    handleFiles(files);
  });
  fileInput.addEventListener('change', (e) => {
    handleFiles(e.target.files);
    fileInput.value = '';
  });
}

function handleFiles(fileList){
  Array.from(fileList).forEach(file => {
    if(!file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = function(e){
      const id = Date.now() + '-' + Math.random().toString(36).slice(2);
      state.t7Images.push({ id, name: file.name, dataUrl: e.target.result });
      renderImageGrid();
    };
    reader.onerror = function(){
      showToast('Görsel okunamadı: ' + file.name, 'error');
    };
    reader.readAsDataURL(file);
  });
}
function cleanJsonResponse(raw){
  let text = (raw || '').trim();
  // ```json ... ``` veya ``` ... ``` code-fence'leri temizle
  text = text.replace(/^```json\s*/i, '').replace(/^```\s*/,'').replace(/```\s*$/,'');
  // ilk { ile son } arasını al (etraftaki açıklama metnini at)
  const start = text.indexOf('{');
  const end = text.lastIndexOf('}');
  if(start !== -1 && end !== -1 && end > start){
    text = text.substring(start, end + 1);
  }
  return text.trim();
}

function renderImageGrid(){
  const grid = document.getElementById('t7-image-grid');
  grid.innerHTML = '';
  state.t7Images.forEach(img => {
    const div = document.createElement('div');
    div.className = 'image-thumb';
    div.innerHTML = '<img src="' + img.dataUrl + '" alt="' + escapeHtml(img.name) + '">' +
      '<button class="rm" data-id="' + img.id + '" title="Kaldır">&times;</button>';
    grid.appendChild(div);
  });
}
document.getElementById('t7-image-grid')?.addEventListener('click', (e) => {
  const btn = e.target.closest('.rm');
  if(btn){
    state.t7Images = state.t7Images.filter(i => i.id !== btn.getAttribute('data-id'));
    renderImageGrid();
  }
});

/* --- Word Export --- */
function elementToWordSafe(rootClone){
  // input / textarea -> span
  rootClone.querySelectorAll('input, textarea, select').forEach(el => {
    if(el.closest('[data-no-export]')){ el.closest('[data-no-export]').remove(); return; }
    let displayValue = '';
    if(el.tagName === 'SELECT'){
      displayValue = el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : '';
    }else if(el.type === 'checkbox'){
      const span = document.createElement('span');
      span.textContent = (el.checked ? '☑ ' : '☐ ');
      el.replaceWith(span);
      return;
    }else if(el.tagName === 'TEXTAREA'){
      displayValue = el.value;
    }else{
      displayValue = el.value;
    }
    const span = document.createElement('span');
    span.style.fontFamily = 'Calibri, Arial, sans-serif';
    span.innerHTML = escapeHtml(displayValue || '—').replace(/\n/g, '<br>');
    el.replaceWith(span);
  });
  // remove leftover file input / dropzone remnants
  rootClone.querySelectorAll('[data-no-export]').forEach(n => n.remove());
  return rootClone;
}

/* Sayı formatlama (binlik ayraç, Türkçe) */
function frTR(n){
  const num = Number(n);
  if(isNaN(num)) return String(n);
  return num.toLocaleString('tr-TR');
}

/* Skor çubuğu — Word'de CSS grid/flex desteklenmediği için tablo tabanlı çizilir */
function scoreBarRowHtml(label, value){
  const color = scoreColor(value);
  const st = scoreStatusLabel(value);
  return (
    '<tr>' +
      '<td style="border:none;padding:5px 0;width:34%;font-size:11.5pt;color:#12151F;">' + escapeHtml(label) + '</td>' +
      '<td style="border:none;padding:5px 0;width:46%;">' +
        '<table cellpadding="0" cellspacing="0" style="width:100%;height:10px;background:#EDEFF3;border-radius:0;"><tr>' +
          '<td style="border:none;background:' + color + ';width:' + Math.max(2,value) + '%;height:10px;font-size:1px;line-height:1px;">&nbsp;</td>' +
          '<td style="border:none;height:10px;font-size:1px;line-height:1px;">&nbsp;</td>' +
        '</tr></table>' +
      '</td>' +
      '<td style="border:none;padding:5px 0 5px 10px;width:10%;font-weight:700;color:' + color + ';font-size:11.5pt;">' + value + '</td>' +
      '<td style="border:none;padding:5px 0 5px 8px;width:10%;font-size:10pt;color:#6B7280;">' + st.txt + '</td>' +
    '</tr>'
  );
}

/* Basit bir "veri kartı" (istatistik) hücresi — GSC/GA4 tabloları için */
function statCellHtml(label, value){
  return (
    '<td style="border:1px solid #E3E6EC;padding:14px 12px;width:25%;vertical-align:top;">' +
      '<div style="font-size:9.5pt;letter-spacing:.03em;text-transform:uppercase;color:#8A8F9C;margin-bottom:4px;">' + escapeHtml(label) + '</div>' +
      '<div style="font-size:19pt;font-weight:700;color:#12151F;font-family:\'Source Sans Pro\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;">' + escapeHtml(String(value)) + '</div>' +
    '</td>'
  );
}

function sectionTitleHtml(num, title){
  return (
    '<table cellpadding="0" cellspacing="0" style="width:100%;margin:26px 0 12px 0;border:none;"><tr>' +
      '<td style="border:none;width:34px;font-family:\'Source Sans Pro\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;font-size:15pt;font-weight:700;color:#B8C2FF;vertical-align:top;">' + num + '</td>' +
      '<td style="border:none;font-family:\'Source Sans Pro\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;font-size:15pt;font-weight:700;color:#12151F;border-bottom:1.5px solid #12151F;padding-bottom:6px;">' + escapeHtml(title) + '</td>' +
    '</tr></table>'
  );
}

/* Raporun saf HTML gövdesini üretir (resimler data URI olarak gömülü).
   Bu HTML iki yerde kullanılır:
   1) buildReportWordBlob() -> yerel "Word Olarak İndir" için MHTML'e paketlenir
   2) Drive'a gönderirken -> Google'ın kendi HTML->Google Doküman dönüştürücüsüne
      doğrudan gönderilir (bkz. t7-export-drive click handler) */
function buildReportHtml(){
  const customer = document.getElementById('t7-customer').value.trim() || 'Müşteri';
  const reportDateRaw = (document.getElementById('t7-date') ? document.getElementById('t7-date').value : '') || new Date().toISOString().slice(0,10);
  const reportDateDisplay = formatDateForFileName(reportDateRaw);

  const packageSel = document.getElementById('t7-package');
  const packageLabel = packageSel.options[packageSel.selectedIndex] ? packageSel.options[packageSel.selectedIndex].text : '— Paket Seçilmedi —';

  const notes = document.getElementById('t7-notes').value.trim();

  // Bu ay yapılan işler (checklist'ten canlı okunur)
  const todoItems = Array.from(document.querySelectorAll('#t7-todo-list .check-item')).map(item => {
    const cb = item.querySelector('input[type="checkbox"]');
    const lbl = item.querySelector('label');
    return { done: cb ? cb.checked : false, text: lbl ? lbl.textContent : '' };
  });

  let sectionNum = 1;
  let bodyHtml = '';

  // ---- KAPAK BLOĞU ----
  bodyHtml +=
    '<table cellpadding="0" cellspacing="0" style="width:100%;border:none;margin-bottom:6px;"><tr>' +
      '<td style="border:none;">' +
        '<div style="font-size:9.5pt;letter-spacing:.12em;text-transform:uppercase;color:#8A8F9C;margin-bottom:6px;">SEO Danışmanlığı &middot; Aylık Performans Raporu</div>' +
        '<div style="font-family:\'Source Sans Pro\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;font-size:26pt;font-weight:700;color:#12151F;line-height:1.15;">' + escapeHtml(customer) + '</div>' +
      '</td>' +
      '<td style="border:none;text-align:right;vertical-align:bottom;width:170px;">' +
        '<div style="font-size:10pt;color:#8A8F9C;">Rapor Tarihi</div>' +
        '<div style="font-size:13pt;font-weight:700;color:#12151F;">' + escapeHtml(reportDateDisplay) + '</div>' +
      '</td>' +
    '</tr></table>' +
    '<div style="border-top:2.5px solid #12151F;margin:10px 0 18px 0;"></div>';

  // ---- 1. GENEL SEO SKORU ----
  if(state.lastScores){
    const s = state.lastScores;
    bodyHtml += sectionTitleHtml(sectionNum++, 'Genel SEO Skoru');
    bodyHtml +=
      '<table cellpadding="0" cellspacing="0" style="width:100%;border:none;margin-bottom:8px;"><tr>' +
        '<td style="border:none;width:120px;vertical-align:middle;">' +
          '<div style="font-family:\'Source Sans Pro\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;font-size:38pt;font-weight:700;color:' + scoreColor(s.overall) + ';">' + s.overall + '</div>' +
          '<div style="font-size:9.5pt;color:#8A8F9C;margin-top:-6px;">/ 100 &middot; genel ortalama</div>' +
        '</td>' +
        '<td style="border:none;vertical-align:middle;">' +
          '<table cellpadding="0" cellspacing="0" style="width:100%;border:none;">' +
            scoreBarRowHtml('İçerik & İç Linkleme', s.content) +
            scoreBarRowHtml('Anahtar Kelime Stratejisi', s.keyword) +
            scoreBarRowHtml('Teknik SEO', s.technical) +
            scoreBarRowHtml('Yapılandırılmış Veri (Schema)', s.schema) +
            scoreBarRowHtml('Site Dışı & Yerel SEO', s.offsite) +
          '</table>' +
        '</td>' +
      '</tr></table>';

    if (state.sgeScore) {
      bodyHtml += 
        '<div style="margin-top:12px; padding:12px; background:#F8FAFC; border-left:4px solid #175ae2; font-size:10.5pt; color:#12151F;">' +
          '<strong>Google AI Overviews (SGE) Uyumluluğu:</strong> Müşteri metniniz yapay zeka (AEO/GEO) aramaları için <strong>' + state.sgeScore + ' / 100</strong> skor ile optimize edilmiştir.' +
        '</div>';
    }
  }

  // ---- 2. SEARCH CONSOLE ----
  if(state.lastGSC){
    const g = state.lastGSC;
    bodyHtml += sectionTitleHtml(sectionNum++, 'Google Search Console (' + (g.rangeLabel || 'Son 28 Gün') + ')');
    bodyHtml +=
      '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin-bottom:8px;"><tr>' +
        statCellHtml('Tıklama', frTR(g.clicks)) +
        statCellHtml('Gösterim', frTR(g.impressions)) +
        statCellHtml('TO (CTR)', (g.ctr*100).toFixed(2) + '%') +
        statCellHtml('Ort. Pozisyon', g.position.toFixed(1)) +
      '</tr></table>';
  }

  // ---- 3. GOOGLE ANALYTICS ----
  if(state.lastGA4){
    const a = state.lastGA4;
    bodyHtml += sectionTitleHtml(sectionNum++, 'Google Analytics 4 (' + (a.rangeLabel || 'Son 28 Gün') + ')');
    bodyHtml +=
      '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin-bottom:8px;"><tr>' +
        statCellHtml('Oturum', frTR(a.sessions)) +
        statCellHtml('Aktif Kullanıcı', frTR(a.activeUsers)) +
        statCellHtml('Dönüşüm', frTR(a.conversions)) +
        statCellHtml('Etkileşim Oranı', (a.engagementRate*100).toFixed(1) + '%') +
      '</tr></table>';
  }

  if(!state.lastScores && !state.lastGSC && !state.lastGA4){
    bodyHtml += sectionTitleHtml(sectionNum++, 'Performans Verisi');
    bodyHtml += '<p style="font-size:10.5pt;color:#8A8F9C;">Bu rapor için henüz hesaplanmış bir SEO skoru ya da çekilmiş Search Console / GA4 verisi bulunmuyor. Sekme 6 üzerinden "Skoru Hesapla / Yenile" ve "Search Console / GA4 verilerini çek" adımlarını tamamlayıp raporu tekrar oluşturun.</p>';
  }

  // ---- YENİ: 3.1. HEDEF ANAHTAR KELİMELER (Tab 02) ----
  const keywordRows = Array.from(document.querySelectorAll('#t2-tbody tr'));
  if (keywordRows.length > 0) {
    bodyHtml += sectionTitleHtml(sectionNum++, 'Hedef Anahtar Kelimeler');
    bodyHtml += '<table cellpadding="4" cellspacing="0" style="width:100%;border-collapse:collapse;margin-bottom:8px;font-size:10.5pt;">';
    bodyHtml += '<tr style="background:#F8FAFC;border-bottom:1px solid #E3E6EC;"><th style="text-align:left;">Anahtar Kelime</th><th style="text-align:right;">Hacim</th><th style="text-align:right;">Zorluk</th><th style="text-align:right;">Niyet</th></tr>';
    keywordRows.forEach(tr => {
      const cells = tr.querySelectorAll('td');
      if(cells.length >= 7) {
        bodyHtml += `<tr style="border-bottom:1px solid #E3E6EC;">
          <td style="text-align:left;">${escapeHtml(cells[1].textContent)}</td>
          <td style="text-align:right;">${escapeHtml(cells[3].textContent)}</td>
          <td style="text-align:right;">${escapeHtml(cells[4].textContent)}</td>
          <td style="text-align:right;">${escapeHtml(cells[6].textContent)}</td>
        </tr>`;
      }
    });
    bodyHtml += '</table>';
  }

  // ---- YENİ: 3.2. TEKNİK HIZ & CORE WEB VITALS (Tab 03) ----
  const lcpText = document.getElementById('t3-lcp-value').textContent;
  const inpText = document.getElementById('t3-inp-value').textContent;
  const perfScoreText = document.getElementById('t3-perf-val').textContent;
  if (lcpText || perfScoreText) {
    bodyHtml += sectionTitleHtml(sectionNum++, 'Teknik Hız & Core Web Vitals');
    bodyHtml +=
      '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin-bottom:8px;"><tr>' +
        statCellHtml('Lighthouse Hız Skoru', perfScoreText || '—') +
        statCellHtml('LCP (Yükleme Hızı)', lcpText || '—') +
        statCellHtml('INP (Etkileşim)', inpText || '—') +
      '</tr></table>';
  }

  // ---- YENİ: 3.3. ŞEMA & YAPILANDIRILMIŞ VERİ (Tab 03) ----
  const schemaOut = document.getElementById('t3-schema-results').innerHTML;
  if (schemaOut && schemaOut.includes('şema bulundu')) {
    bodyHtml += sectionTitleHtml(sectionNum++, 'Şema (JSON-LD) Denetimi');
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = schemaOut;
    const summary = tempDiv.querySelector('strong')?.parentNode?.textContent || 'Şema taraması tamamlandı.';
    bodyHtml += '<div style="margin-top:4px; padding:12px; background:#F8FAFC; border-left:4px solid #1BA672; font-size:10.5pt; color:#12151F;">' + escapeHtml(summary) + '</div>';
  }

  // ---- YENİ: 3.4. MASTER LLMS.TXT (Tab 04) ----
  const llmsOutput = document.getElementById('t4-master-llmstxt-output').value;
  if (llmsOutput && llmsOutput.length > 50) {
    bodyHtml += sectionTitleHtml(sectionNum++, 'Google AI (SGE) & LLM Uyumluluğu');
    bodyHtml += '<div style="margin-top:4px; padding:12px; background:#F8FAFC; border-left:4px solid #175ae2; font-size:10.5pt; color:#12151F;">Müşteri web sitesinin tüm hiyerarşisi taranmış ve <strong>Master llms.txt (GEO dosyası)</strong> başarıyla oluşturularak arama motorlarının yapay zeka botlarına sunulmuştur.</div>';
  }

  // ---- 4. PAKET & YAPILAN İŞLER ----
  bodyHtml += sectionTitleHtml(sectionNum++, 'Paket & Bu Ay Yapılan İşler');
  bodyHtml += '<p style="font-size:10.5pt;margin:0 0 8px 0;"><strong>Satın Alınan Paket:</strong> ' + escapeHtml(packageLabel) + '</p>';
  if(todoItems.length){
    bodyHtml += '<table cellpadding="0" cellspacing="0" style="width:100%;border:none;">';
    todoItems.forEach(t => {
      bodyHtml +=
        '<tr><td style="border:none;padding:3px 0;font-size:10.5pt;width:22px;color:' + (t.done ? '#1BA672' : '#C6CBD6') + ';">' + (t.done ? '&#10003;' : '&#9675;') + '</td>' +
        '<td style="border:none;padding:3px 0;font-size:10.5pt;color:#12151F;">' + escapeHtml(t.text) + '</td></tr>';
    });
    bodyHtml += '</table>';
  }else{
    bodyHtml += '<p style="font-size:10.5pt;color:#8A8F9C;">Bu ay için işaretlenmiş bir görev listesi yok.</p>';
  }

  // ---- 5. NOTLAR ----
  bodyHtml += sectionTitleHtml(sectionNum++, 'Değerlendirme & Öneriler');
  bodyHtml += '<p style="font-size:10.5pt;white-space:pre-wrap;color:#12151F;">' + (notes ? escapeHtml(notes).replace(/\n/g,'<br>') : '<span style="color:#8A8F9C;">Bu ay için not girilmedi.</span>') + '</p>';

  // ---- 6. GÖRSELLER (Word-uyumlu, taşmayan 2 sütunlu tablo) ----
  if(state.t7Images.length){
    bodyHtml += sectionTitleHtml(sectionNum++, 'Ekran Görüntüleri');
    bodyHtml += '<table cellpadding="0" cellspacing="0" style="width:100%;border:none;">';
    for(let i = 0; i < state.t7Images.length; i += 2){
      bodyHtml += '<tr>';
      for(let j = i; j < i + 2; j++){
        if(j >= state.t7Images.length){
          bodyHtml += '<td style="border:none;width:50%;">&nbsp;</td>';
          continue;
        }
        const img = state.t7Images[j];
        bodyHtml +=
          '<td style="border:none;width:50%;padding:6px;vertical-align:top;">' +
            '<table cellpadding="0" cellspacing="0" style="width:100%;border:1px solid #E3E6EC;"><tr><td style="border:none;padding:6px;text-align:center;">' +
              '<img src="' + img.dataUrl + '" alt="' + escapeHtml(img.name) + '" style="width:100%;max-width:280px;height:auto;display:block;margin:0 auto;">' +
            '</td></tr></table>' +
          '</td>';
      }
      bodyHtml += '</tr>';
    }
    bodyHtml += '</table>';
  }

  const htmlContent =
    "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>" +
    "<head><meta charset='utf-8'><title>Aylık SEO Raporu — " + escapeHtml(customer) + "</title>" +
    "<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]-->" +
    "<style>" +
      "@page{size:21cm 29.7cm;margin:2.2cm 2cm;}" +
      "body{font-family:\'Source Sans Pro\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;font-size:10.5pt;color:#12151F;}" +
      "table{border-collapse:collapse;}" +
    "</style>" +
    "</head><body>" +
    bodyHtml +
    '<div style="border-top:1px solid #E3E6EC;margin-top:28px;padding-top:8px;font-size:8.5pt;color:#8A8F9C;">Bu rapor SEO CoPilot tarafından ' + escapeHtml(customer) + ' için otomatik olarak oluşturulmuştur.</div>' +
    "</body></html>";

  const filename = reportDateRaw + '-' + customer.replace(/\s+/g,'-') + '-SEO-Raporu.doc';
  return { html: htmlContent, filename, customer };
}

/* "Word Olarak İndir" için: buildReportHtml() çıktısındaki data-URI resimleri
   cid: referanslarına çevirip gerçek bir MHTML (multipart) belgesi üretir.
   (Bilgisayara indirilip doğrudan Microsoft Word ile açılacak dosya budur;
   Drive'a gönderilen belge ise Google'ın kendi HTML dönüştürücüsünü kullanır,
   ayrı bir yoldur — bkz. t7-export-drive click handler.) */
function buildReportWordBlob(){
  const { html, filename } = buildReportHtml();
  const embeddedImages = [];
  let imgIndex = 0;

  const htmlWithCids = html.replace(/<img src="(data:([^;]+);base64,[^"]*)"/g, (full, dataUrl, mimeType) => {
    const m = /^data:[^;]+;base64,([\s\S]*)$/.exec(dataUrl);
    if(!m) return full;
    const cid = 'img' + (imgIndex++) + '@seocapilot';
    embeddedImages.push({ cid, mimeType, base64: m[1] });
    return '<img src="cid:' + cid + '"';
  });

  const blob = embeddedImages.length
    ? buildMhtmlBlob(htmlWithCids, embeddedImages)
    : new Blob(['\ufeff', html], { type: 'application/msword' });

  return { blob, filename };
}

/* Base64 veriyi MIME standardına uygun şekilde 76 karakterlik satırlara böler */
function wrapBase64(base64){
  return base64.replace(/[\r\n]/g, '').match(/.{1,76}/g).join('\r\n');
}

/* HTML gövdeyi + gömülü resimleri tek bir MHTML (multipart/related) belgesine paketler.
   Word, .doc uzantılı olsa bile içeriği MIME yapısından tanıyıp resimleri düzgün gösterir
   — data: URI'lerin aksine, bu yöntem Word'de güvenilir şekilde çalışır. */
function buildMhtmlBlob(htmlContent, images){
  const boundary = '----=_NextPart_SEOCoPilot_' + Date.now();
  let mhtml =
    'MIME-Version: 1.0\r\n' +
    'Content-Type: multipart/related; boundary="' + boundary + '"; type="text/html"\r\n\r\n' +
    'Bu, MIME formatında çok parçalı bir mesajdır.\r\n\r\n' +
    '--' + boundary + '\r\n' +
    'Content-Type: text/html; charset="utf-8"\r\n' +
    'Content-Transfer-Encoding: 8bit\r\n\r\n' +
    htmlContent + '\r\n\r\n';

  images.forEach(img => {
    mhtml +=
      '--' + boundary + '\r\n' +
      'Content-Type: ' + img.mimeType + '\r\n' +
      'Content-Transfer-Encoding: base64\r\n' +
      'Content-ID: <' + img.cid + '>\r\n' +
      'Content-Location: ' + img.cid + '\r\n\r\n' +
      wrapBase64(img.base64) + '\r\n\r\n';
  });

  mhtml += '--' + boundary + '--\r\n';

  return new Blob([mhtml], { type: 'application/msword' });
}

/* ============================================================
   TAB 7 — GOOGLE DRIVE ENTEGRASYONU
   Akış: kendi Drive'ınızdaki "SEO" klasörünü bulur/oluşturur ->
   içinde seçili müşterinin alt klasörünü bulur/oluşturur (bulunca
   client kaydına drive_folder_id olarak yazar, bir daha aramaz) ->
   seçilen içerik sürümünün eski/yeni metnini "<Tarih> Eski" / "<Tarih> Yeni"
   adıyla Google Doc olarak, işaretliyse profesyonel biçimlendirilmiş
   raporu da .doc olarak o klasöre yükler. Aynı isimde dosya varsa
   üzerine yazar (yinelenen dosya oluşturmaz).
============================================================ */
const TR_MONTHS = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
function getTurkishMonthName(dateStr){
  const d = dateStr ? new Date(dateStr) : new Date();
  if(isNaN(d.getTime())) return TR_MONTHS[new Date().getMonth()];
  return TR_MONTHS[d.getMonth()];
}

/* "2026-08-11" gibi bir ISO tarihi "11.08.2026" formatına çevirir (dosya adlandırma için) */
function formatDateForFileName(dateStr){
  const d = dateStr ? new Date(dateStr) : new Date();
  if(isNaN(d.getTime())) return formatDateForFileName(new Date().toISOString().slice(0,10));
  const dd = String(d.getDate()).padStart(2,'0');
  const mm = String(d.getMonth()+1).padStart(2,'0');
  const yyyy = d.getFullYear();
  return dd + '.' + mm + '.' + yyyy;
}

function escapeDriveQueryValue(str){
  return String(str || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

async function driveFetch(url, options){
  options = options || {};
  const headers = Object.assign({}, options.headers || {}, {
    'Authorization': 'Bearer ' + googleAccessToken,
  });
  const res = await fetch(url, Object.assign({}, options, { headers }));
  if(!res.ok){
    let msg = 'Drive API hatası (' + res.status + ')';
    try{
      const errJson = await res.json();
      msg = (errJson.error && errJson.error.message) || msg;
    }catch(e){ /* yoksay */ }
    if(res.status === 401){
      googleAccessToken = null;
      msg = 'Google oturumu sona ermiş, lütfen Google hesabınızı yeniden bağlayın.';
    }
    throw new Error(msg);
  }
  if(res.status === 204) return null;
  return res.json();
}

/* Verilen isimde klasör var mı ara (opsiyonel parent içinde). Yoksa null döner. */
async function findDriveFolder(name, parentId){
  let q = "mimeType = 'application/vnd.google-apps.folder' and trashed = false and name = '" + escapeDriveQueryValue(name) + "'";
  if(parentId) q += " and '" + parentId + "' in parents";
  const url = 'https://www.googleapis.com/drive/v3/files?q=' + encodeURIComponent(q) + '&fields=files(id,name)&spaces=drive';
  const data = await driveFetch(url);
  return (data.files && data.files[0]) ? data.files[0].id : null;
}

async function createDriveFolder(name, parentId){
  const body = { name: name, mimeType: 'application/vnd.google-apps.folder' };
  if(parentId) body.parents = [parentId];
  const data = await driveFetch('https://www.googleapis.com/drive/v3/files?fields=id', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return data.id;
}

async function findOrCreateDriveFolder(name, parentId){
  const existing = await findDriveFolder(name, parentId);
  if(existing) return existing;
  return createDriveFolder(name, parentId);
}

/* Bir klasör içinde verilen isimde dosya ara (herhangi bir mimeType) */
async function findDriveFile(name, parentId){
  const q = "trashed = false and name = '" + escapeDriveQueryValue(name) + "' and '" + parentId + "' in parents";
  const url = 'https://www.googleapis.com/drive/v3/files?q=' + encodeURIComponent(q) + '&fields=files(id,name)&spaces=drive';
  const data = await driveFetch(url);
  return (data.files && data.files[0]) ? data.files[0].id : null;
}

/* Aynı isimde dosya varsa içeriğini günceller (üzerine yazar), yoksa yeni oluşturur.
   blobOrText: string ya da Blob olabilir. targetMimeType Drive'da saklanacak tip
   (ör. Google Doc'a çevirmek için 'application/vnd.google-apps.document'),
   sourceMimeType yüklenen ham içeriğin tipidir (ör. 'text/plain'). */
async function uploadOrUpdateDriveFile(name, parentId, targetMimeType, blobOrText, sourceMimeType){
  const existingId = await findDriveFile(name, parentId);
  const metadata = { name: name, mimeType: targetMimeType };
  if(!existingId) metadata.parents = [parentId];

  const contentText = (typeof blobOrText === 'string') ? blobOrText : await blobOrText.text();

  const boundary = 'seocapilot-' + Date.now() + '-' + Math.random().toString(36).slice(2);
  const delimiter = '\r\n--' + boundary + '\r\n';
  const closeDelim = '\r\n--' + boundary + '--';

  const multipartBody =
    delimiter +
    'Content-Type: application/json; charset=UTF-8\r\n\r\n' +
    JSON.stringify(metadata) +
    delimiter +
    'Content-Type: ' + (sourceMimeType || targetMimeType) + '; charset=UTF-8\r\n\r\n' +
    contentText +
    closeDelim;

  const url = existingId
    ? 'https://www.googleapis.com/upload/drive/v3/files/' + existingId + '?uploadType=multipart&fields=id'
    : 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id';

  const data = await driveFetch(url, {
    method: existingId ? 'PATCH' : 'POST',
    headers: { 'Content-Type': 'multipart/related; boundary=' + boundary },
    body: multipartBody,
  });
  return { id: data.id || existingId, updated: !!existingId };
}

/* Seçili müşterinin Drive alt klasörünü bulur/oluşturur.
   - client kaydında drive_folder_id kayıtlıysa ve hâlâ geçerliyse onu kullanır (arama yapmaz).
   - yoksa/silinmişse: kök dizinde "SEO" klasörünü bulur/oluşturur, içinde müşteri
     adıyla alt klasörü bulur/oluşturur, sonucu client kaydına yazar (bir sonraki
     gönderimde tekrar aramaya gerek kalmasın diye). */
async function resolveClientDriveFolderId(){
  if(!state.currentClientId || !state.currentClient){
    throw new Error('Önce sol menüden bir müşteri seçin.');
  }

  const clientName = (document.getElementById('t7-customer').value || state.currentClient.name || 'Musteri').trim();

  // Kayıtlı bir klasör ID'si varsa körü körüne güvenmeden doğrula:
  // klasör hâlâ duruyor mu VE gerçekten bu müşterinin adını taşıyor mu?
  // (SEO ana klasörünün ID'si yanlışlıkla buraya kaydedilmiş olabilir —
  // bu durumda dosyalar müşteri alt klasörü yerine doğrudan SEO'nun
  // köküne düşer. Böyle bir uyuşmazlık bulunursa eski kayıt yok sayılır
  // ve doğru alt klasör yeniden bulunup/oluşturulup kayıt düzeltilir.)
  if(state.currentClient.drive_folder_id){
    try{
      const info = await driveFetch('https://www.googleapis.com/drive/v3/files/' + state.currentClient.drive_folder_id + '?fields=id,name,trashed');
      if(info && !info.trashed && info.name === clientName){
        return state.currentClient.drive_folder_id;
      }
      // İsim uyuşmuyor (ör. yanlışlıkla SEO ana klasörünün ID'si kaydedilmiş) -> yok say, yeniden oluştur
    }catch(e){
      // Kayıtlı klasör artık yok/silinmiş/erişilemiyor; aşağıda yeniden bulunup oluşturulacak
    }
  }

  const seoFolderId = await findOrCreateDriveFolder('SEO', null);
  const clientFolderId = await findOrCreateDriveFolder(clientName, seoFolderId);

  try{
    const res = await fetch('api/clients.php?id='+state.currentClientId, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({drive_folder_id: clientFolderId})}); const json = await res.json(); const error = json.error;
    if(error) throw error;
    state.currentClient.drive_folder_id = clientFolderId;
    document.getElementById('t6-drive-id').value = clientFolderId;
  }catch(e){
    console.error('[Supabase] drive_folder_id güncellenemedi:', e);
  }

  return clientFolderId;
}

document.getElementById('t7-export-drive')?.addEventListener('click', async () => {
  if(!requireGoogleAuth()) return;
  if(!state.currentClientId){
    showToast('Önce sol menüden bir müşteri seçin.', 'error');
    return;
  }

  const contentPair = getSelectedContentPair();
  const sendReport = document.getElementById('t7-send-report').checked;

  if(!contentPair && !sendReport){
    showToast('Gönderilecek bir içerik sürümü seçin ya da "Aylık Raporu da oluşturup gönder" kutusunu işaretleyin.', 'error');
    return;
  }

  const btn = document.getElementById('t7-export-drive');
  const label = document.getElementById('t7-export-drive-label');
  const originalLabel = label.textContent;
  btn.disabled = true;
  label.textContent = 'Gönderiliyor...';

  try{
    const clientFolderId = await resolveClientDriveFolderId();
    const fileDate = formatDateForFileName(document.getElementById('t7-date') ? document.getElementById('t7-date').value : '');
    const sentItems = [];

    if(contentPair){
      await uploadOrUpdateDriveFile(
        fileDate + ' Eski',
        clientFolderId,
        'application/vnd.google-apps.document',
        contentPair.oldText || '(içerik yok)',
        'text/plain'
      );
      await uploadOrUpdateDriveFile(
        fileDate + ' Yeni',
        clientFolderId,
        'application/vnd.google-apps.document',
        contentPair.newText || '(içerik yok)',
        'text/plain'
      );
      sentItems.push(fileDate + ' Eski / ' + fileDate + ' Yeni');
    }

    if(sendReport){
      const { html, filename } = buildReportHtml();
      // Drive dosya adı uzantısız olmalı (Google Doküman'lar dosya uzantısı kullanmaz)
      const driveDocName = filename.replace(/\.doc$/i, '');
      await uploadOrUpdateDriveFile(driveDocName, clientFolderId, 'application/vnd.google-apps.document', html, 'text/html');
      sentItems.push('Aylık Rapor (' + driveDocName + ')');
    }

    showToast('Drive\'a gönderildi: ' + sentItems.join(', '), 'success');
  }catch(err){
    console.error('[Drive] gönderim hatası:', err);
    showToast('Drive\'a gönderilirken hata oluştu: ' + (err.message || 'bilinmeyen hata'), 'error');
  }finally{
    window.dispatchEvent(new Event('t6-refresh-btn-done'));
    btn.disabled = false;
    label.textContent = originalLabel;
  }
});

document.getElementById('t7-export-word')?.addEventListener('click', () => {
  const { blob, filename } = buildReportWordBlob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
  showToast('Word raporu indiriliyor...', 'success');
});

/* Set default report date to today */
if(document.getElementById('t7-date')) { if(document.getElementById('t7-date')) { document.getElementById('t7-date').value = new Date().toISOString().slice(0,10); } }

/* ============================================================
   İLK YÜKLEME — Supabase'den verileri çek
============================================================ */
fetchClients();
// content_history / backlinks / score_history yüklemeleri, bir müşteri
// seçildiğinde client-select'in 'change' olayı üzerinden tetiklenir.

/* ============================================================
   TAB 8 — AI SEO ANALİZİ
============================================================ */
document.getElementById('t8-analyze-btn')?.addEventListener('click', async () => {
  const url = document.getElementById('t8-url').value.trim();
  if(!url) { showToast('Lütfen geçerli bir domain veya URL girin.', 'error'); return; }

  const btn = document.getElementById('t8-analyze-btn');
  const label = document.getElementById('t8-analyze-label');
  btn.disabled = true;
  label.innerHTML = '<span class="spinner"></span> Analiz ediliyor...';
  document.getElementById('t8-output-card').classList.add('hidden');

  try {
    let rawText = '';
    let hasLlms = false;
    try {
      const response = await fetch(`api/fetch_url.php?url=${encodeURIComponent(url)}`);
      if(response.ok) {
         const data = await response.json();
         rawText = data.text || '';
         hasLlms = data.has_llms_txt || false;
      }
    } catch(e) {
      console.warn('Scraping başarısız, sadece URL ile AI\'a sorulacak.', e);
    }

    const textForAnalysis = rawText; // fetch_url.php already trims to 20k

    const aiPrompt = `Sen kıdemli bir AI SEO, Generative Engine Optimization (GEO) uzmanısın.
Domain: ${url}
Sitenin Markdown Formatındaki İçeriği: ${textForAnalysis}
llms.txt durumu: ${hasLlms ? 'Var' : 'Yok'}

Aşağıdaki bilgileri EKSİKSİZ, DETAYLI ve SADECE geçerli bir JSON olarak döndür:
{
  "domainBusinessContext": {
    "about": "Bu domain/şirket hakkında genel bilgi...",
    "targetAudience": "Hedef kitlenin kim olduğu ve ihtiyaçları...",
    "industryNiche": "Sektörel niş ve odaklanılan alanlar..."
  },
  "contentEffectiveness": {
    "overview": "Sitenin içerik etkinliğine genel bakış (örneğin hizmetler açıklanmış mı, eksikler neler)...",
    "topQuestions": [
      {
        "question": "Soru 1 (Örn: Türkiye pazarı için etkili Google Ads kampanyası nasıl oluşturulur?)",
        "analysis": "Bu sorunun sitede ne kadar iyi yanıtlandığına dair kısa analiz...",
        "score": 9
      }
    ]
  },
  "contentOpportunities": [
    { "title": "llms.txt Dosyası", "desc": "Yapay zeka için özet dosya eksik/var..." },
    { "title": "İç Linkleme (Anchor Text)", "desc": "Link metinleri SEO'ya uygun mu?..." },
    { "title": "Başlık Hiyerarşisi", "desc": "H1, H2 yapısı semantik mi?..." }
  ],
  "competitorInsights": "Rakip analizi bağlamında sitenin güçlü ve zayıf yönleri...",
  "aiContentTrust": {
    "overallScore": 88,
    "topicalRelevance": 88,
    "subjectExpertise": 85,
    "credibility": 90,
    "improvements": [
      "Geliştirme önerisi 1..."
    ]
  }
}
Yalnızca geçerli bir JSON döndür. Başka metin ekleme.`;

    const rawAiResponse = await callGemini(aiPrompt, () => '{}');
    const aiParsed = JSON.parse(cleanJsonResponse(rawAiResponse));

    if(aiParsed.domainBusinessContext) {
       document.getElementById('t8-domain-about').textContent = aiParsed.domainBusinessContext.about || '—';
       document.getElementById('t8-target-audience').textContent = aiParsed.domainBusinessContext.targetAudience || '—';
       document.getElementById('t8-industry-niche').textContent = aiParsed.domainBusinessContext.industryNiche || '—';
    }

    if(aiParsed.contentEffectiveness) {
       document.getElementById('t8-effectiveness-overview').textContent = aiParsed.contentEffectiveness.overview || '—';
       const questionsHtml = (aiParsed.contentEffectiveness.topQuestions || []).map(q => `
         <div style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid #eaeaea; page-break-inside: avoid;">
           <h4 style="font-size: 14px; color: #333; font-weight: 600; margin: 0 0 12px 0;">Soru: ${escapeHtml(q.question)}</h4>
           <div style="display: flex; gap: 8px; align-items: flex-start; margin-bottom: 12px;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="margin-top:2px; flex-shrink:0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
              <p style="font-size: 14px; color: #444; line-height: 1.5; margin: 0;">${escapeHtml(q.analysis)}</p>
           </div>
           <div style="font-weight: 700; color: #333; font-size: 14px;">Skor: ${q.score}/10</div>
         </div>
       `).join('');
       document.getElementById('t8-top-questions').innerHTML = questionsHtml;
    }

    if(aiParsed.contentOpportunities) {
       document.getElementById('t8-opportunities').innerHTML = aiParsed.contentOpportunities.map((o, i) => `
         <details class="seo-details">
           <summary>${i+1}. ${escapeHtml(o.title || 'Fırsat')}</summary>
           <div class="details-content">${escapeHtml(o.desc || o)}</div>
         </details>
       `).join('');
    }

    document.getElementById('t8-competitor-insights').textContent = aiParsed.competitorInsights || '—';

    if(aiParsed.aiContentTrust) {
       document.getElementById('t8-trust-score').textContent = aiParsed.aiContentTrust.overallScore + '%';
       document.getElementById('t8-tr-val').textContent = aiParsed.aiContentTrust.topicalRelevance + '%';
       document.getElementById('t8-se-val').textContent = aiParsed.aiContentTrust.subjectExpertise + '%';
       document.getElementById('t8-cr-val').textContent = aiParsed.aiContentTrust.credibility + '%';

       if(aiParsed.aiContentTrust.improvements) {
          document.getElementById('t8-trust-improvements').innerHTML = aiParsed.aiContentTrust.improvements.map(i => `
           <div style="display: flex; gap: 8px; align-items: flex-start;">
             <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="margin-top:2px; flex-shrink:0;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 16 16 12 12 8"></polyline><line x1="8" y1="12" x2="16" y2="12"></line></svg>
             <span>${escapeHtml(i)}</span>
           </div>
          `).join('');
       }
    }

    document.getElementById('t8-output-card').classList.remove('hidden');
    document.getElementById('t8-pdf-btn').classList.remove('hidden');
    showToast('AI SEO Raporu başarıyla oluşturuldu!', 'success');

  } catch (err) {
    console.error(err);
    showToast('Rapor oluşturulurken hata oluştu: ' + err.message, 'error');
  } finally {
    btn.disabled = false;
    label.textContent = 'AI Raporu Oluştur';
  }
});

// PDF Export Event Listener
document.getElementById('t8-pdf-btn')?.addEventListener('click', () => {
  const element = document.getElementById('t8-output-card');
  const opt = {
    margin:       10,
    filename:     'AI_SEO_Analiz_Raporu.pdf',
    image:        { type: 'jpeg', quality: 0.98 },
    html2canvas:  { scale: 2, useCORS: true },
    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
  };
  
  if (typeof html2pdf !== 'undefined') {
    showToast('PDF hazırlanıyor, lütfen bekleyin...', 'success');
    html2pdf().set(opt).from(element).save();
  } else {
    showToast('PDF kütüphanesi yüklenemedi. Sayfayı yenileyin.', 'error');
  }
});

})();

// UPDATE HEADER CLIENT/URL DYNAMICALLY
document.addEventListener('input', (e) => {
  // Sadece URL inputlarını dinle
  if (e.target && e.target.tagName === 'INPUT' && (e.target.type === 'url' || e.target.id.toLowerCase().includes('url'))) {
    const val = e.target.value.trim();
    const headerName = document.getElementById('top-header-client-name');
    if (headerName) {
      // Eğer bir müşteri seçiliyse (dropdown üzerinden) ismi kalsın
      if (state.currentClient && state.currentClient.name) {
        headerName.textContent = state.currentClient.name;
        return;
      }
      
      if (!val) {
        headerName.textContent = 'Bekleniyor...';
        return;
      }

      // Girilen URL, kayıtlı müşterilerden birinin URL'siyle eşleşiyor mu?
      // (Birebir eşleşme veya http/https farklılıklarını bir nebze tolere edelim)
      let matchedClient = null;
      if (state.clients && state.clients.length > 0) {
         matchedClient = state.clients.find(c => {
           if (!c.domain_url) return false;
           // Basit domain eşleştirmesi (protokolü görmezden gelerek)
           let domain1 = c.domain_url.replace(/^https?:\/\//, '').replace(/\/$/, '');
           let domain2 = val.replace(/^https?:\/\//, '').replace(/\/$/, '');
           return domain2.startsWith(domain1);
         });
      }

      if (matchedClient) {
        headerName.textContent = matchedClient.name;
      } else {
        // Eşleşme yoksa direkt URL'yi yaz
        headerName.textContent = val;
      }
    }
  }
});

// GITHUB STYLE HAMBURGER MENU LOGIC
document.addEventListener('DOMContentLoaded', () => {
  const hamburger = document.getElementById('gh-hamburger-btn');
  const sidebar = document.getElementById('gh-sidebar');
  const overlay = document.getElementById('gh-sidebar-overlay');
  const closeBtn = document.getElementById('gh-sidebar-close');
  
  function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('show');
  }
  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
  }
  
  if (hamburger) hamburger.addEventListener('click', openSidebar);
  if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
  if (overlay) overlay.addEventListener('click', closeSidebar);
});

