const seoQuestionPool = [
  "Bana içerik eksiklerimi söyle",
  "Rakiplerimden neden gerideyim?",
  "Bu sayfa için SSS (FAQ) hazırla",
  "İçeriği daha ikna edici nasıl yaparım?",
  "Hangi LSI kelimelerini kullanmalıyım?",
  "Kullanıcı niyetine ne kadar uygun?",
  "Okunabilirlik skorumu nasıl artırırım?",
  "Sayfa başlığı (Title) yeterince iyi mi?",
  "E-E-A-T sinyallerim eksik mi?",
  "Hangi alt başlıkları (H2/H3) eklemeliyim?",
  "Featured Snippet (Sıfırıncı sıra) için ne yapmalıyım?"
];

window.renderDynamicQuickActions = function() {
  const quickActionsContainer = document.getElementById("copilot-quick-actions");
  if (!quickActionsContainer) return;

  const shuffled = [...seoQuestionPool].sort(() => 0.5 - Math.random());
  const selected = shuffled.slice(0, 3);

  const infoIconBlue = '';

  let html = "";
  selected.forEach(q => {
    // FIX the button so it sets value to just the question string, without the SVG!
    // 'this.textContent' will include the SVG text if not careful, but textContent ignores SVG tags, so it should be fine.
    html += `<button class="quick-action-btn has-tooltip" data-tooltip="Hızlı aksiyon ile AI'a anında talimat gönderin." onclick="document.getElementById('copilot-text-input').value=this.textContent; document.getElementById('btn-send-message').click();">` + q + infoIconBlue + `</button>`;
  });
  
  quickActionsContainer.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', () => {
const copilotChat = document.getElementById('copilot-chat-messages-container');
const copilotResetBtn = document.getElementById('btn-clear-chat');
const copilotSaveBtn = document.getElementById('copilot-manual-save-btn');
const copilotProgress = document.getElementById('copilot-progress');
const copilotActions = document.getElementById('copilot-actions');
const copilotInputArea = document.getElementById('copilot-input-area-container');
const copilotTextInput = document.getElementById('copilot-text-input');
const copilotSendBtn = document.getElementById('btn-send-message');
const historyList = document.getElementById('copilot-history-list');
const btnClearHistory = document.getElementById('btn-clear-history');

if (!copilotChat) return;

const aiSeoSteps = ["", "Site Yapısı ve Taranabilirlik", "Kullanıcı Odaklı İçerik ve SSS", "Rakip Zafiyetleri ve İçerik Derinliği", "İçerik İçi Bağlantılar ve Kayıtlar", "Yapılandırılmış Veri Derinliği", "AI Bilgilendirme ve Entegrasyon"];

let currentState = 'WAITING_FOR_URL';
let currentStep = 1;
let completedSteps = new Set();
let fixedIssues = new Set();
let targetUrl = '';
let targetType = '';
let fetchedData = null;
window.targetCompetitorUrl = '';
window.fetchedCompetitorData = null;
let chatMessages = [];
let reportData = [];
let currentChatId = Date.now().toString();

function addMessage(text, sender, isHtml = false, doPush = true) {
  const emptyState = document.getElementById("copilot-empty-state");
  if(emptyState) emptyState.style.display = "none";
  const div = document.createElement('div');
  div.className = `chat-msg ${sender}`;
  const msgId = 'msg-' + document.querySelectorAll('.chat-msg').length;
  div.id = msgId;
  if (isHtml) { div.innerHTML = text; } else { div.textContent = text; }
  const msgContainer = document.getElementById('copilot-chat-messages-container');
  if (msgContainer) msgContainer.appendChild(div);
  copilotChat.scrollTop = copilotChat.scrollHeight;

  if (doPush) {
    chatMessages.push({ text, sender, isHtml });
    // We no longer auto-save here, the user must click Save manually
  }
}

function addTypingIndicator() {
  const div = document.createElement('div');
  div.className = `chat-msg ai typing-indicator-wrap`;
  div.id = 'typing-indicator';
  div.innerHTML = `<div class="typing-indicator"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>`;
  const msgContainer = document.getElementById('copilot-chat-messages-container');
  if (msgContainer) msgContainer.appendChild(div);
  copilotChat.scrollTop = copilotChat.scrollHeight;
}

function removeTypingIndicator() {
  const indicator = document.getElementById('typing-indicator');
  if (indicator) {
      try { indicator.remove(); } catch(e) { console.warn(e); }
  }
}

function updateProgressUI(step) {
  if(step === 0) {
    copilotProgress.style.display = 'none';
    if (copilotSaveBtn) copilotSaveBtn.style.display = 'none';
    return;
  }
  copilotProgress.style.display = 'flex';
  if (copilotSaveBtn) copilotSaveBtn.style.display = 'inline-flex';

  for (let i = 1; i <= 6; i++) {
    const stepEl = document.getElementById(`cp-step-${i}`);
    if (!stepEl) continue;
    if (completedSteps.has(i)) {
      stepEl.classList.add('completed');
      stepEl.classList.remove('active');
    } else if (i === step) {
      stepEl.classList.add('active');
      stepEl.classList.remove('completed');
    } else {
      stepEl.classList.remove('active', 'completed');
    }
  }
  renderIssuesUI();
}

function renderIssuesUI() {
  for (let i = 1; i <= 6; i++) {
    const btn = document.getElementById(`btn-fix-${i}`);
    if (!btn) continue;
    
    if (completedSteps.has(i)) {
      btn.style.display = 'block';
      const isFixed = fixedIssues.has(i);
      btn.style.background = 'transparent';
      btn.style.color = isFixed ? '#10b981' : '#2563eb';
      btn.style.borderColor = 'transparent';
      
      btn.innerHTML = isFixed ? '✓' : '🔧';
      btn.title = isFixed ? `${i}. Adım Çözüldü` : `${i}. Adımı Çöz (Manuel)`;
      
      btn.style.cursor = (window.isAutoAnalyzing || window.isAutoFixing) ? 'default' : 'pointer';
      btn.style.pointerEvents = (window.isAutoAnalyzing || window.isAutoFixing) ? 'none' : 'auto';
      btn.style.textDecoration = 'none';
      
      // Remove existing listeners to avoid duplicates, then add
      const newBtn = btn.cloneNode(true);
      btn.parentNode.replaceChild(newBtn, btn);
      
      newBtn.addEventListener('click', (e) => {
        const s = parseInt(e.currentTarget.getAttribute('data-step'));
        if (!fixedIssues.has(s)) {
          fixAiSeoIssue(s);
        } else {
           const msgs = Array.from(document.querySelectorAll('.chat-msg.user'));
           const targetMsg = msgs.find(m => m.innerText.includes(`${s}. Adım Çözümü`));
           if (targetMsg) {
              const scrollOffset = targetMsg.getBoundingClientRect().top - copilotChat.getBoundingClientRect().top + copilotChat.scrollTop;
              copilotChat.scrollTo({ top: scrollOffset - 10, behavior: 'smooth' });
           }
        }
      });
    } else {
      btn.style.display = 'none';
    }
  }
}

async function fixAiSeoIssue(step) {
  const kural = "ÖNEMLİ KURAL: Önerdiğin İSTİSNASIZ HER eylemi modül mantığıyla sun. Eğer eylem sadece yazılım/teknik ekibini ilgilendiriyorsa (Şema, kod, hız) başına tam olarak '🚨 [TEKNİK - Modül: Modül Adı]', sadece içerik ekibini ilgilendiriyorsa (metin yazımı) '✍️ [METİN - Modül: Modül Adı]', her ikisini de içeren bütünleşik bir eylemse (veya fotoğraf, strateji, çoklu lokasyon gibi genel bir kurguysa) '📌 [GENEL - Modül: Modül Adı]' yaz. (Örn: 📌 [GENEL - Modül: SSS] Bu soruları sayfaya ekle ve FAQ şemasını da yayımla). Bütün eksikleri atlamadan bu formata sok!";
  const fixPrompts = [
    "",
    "Az önceki 1. Adım analizinde bulduğun eksikleri gidermek için siteme doğrudan ekleyebileceğim E-E-A-T sinyallerini artıran metinler yaz ve Site Hiyerarşisini (Bilgi Mimarisini) düzeltmek için menü/kategori URL yapısı önerileri sun. " + kural,
    "Az önceki 2. Adım analizine dayanarak, kullanıcıların en çok aradığı sorulara doğrudan yanıt veren 5 adet 'Kullanıcı Odaklı SSS (FAQ)' metni ve bu soruların hatasız JSON-LD Schema kodunu hazırla. " + kural,
    "Az önceki 3. Adım analizinde bulduğun içerik açıklarını kapatmak için; hizmet kapsamını detaylandıran, teknik terimleri basitleştiren ve rakiplerden ayrışan ikna edici bir Değer Teklifi (Value Proposition) metni yaz. " + kural,
    "Az önceki 4. Adım analizine göre; bu sayfanın otoritesini besleyecek 'Site İçi İçerik Bağlantıları (Internal Linking / Topic Clusters)' stratejisi oluştur. Hangi blog başlıkları yazılmalı ve bu sayfaya hangi Anchor Text (bağlantı metni) ile linklenmeli detaylıca yaz. " + kural,
    "Az önceki 5. Adım analizine göre sayfadaki Yapılandırılmış Veri (Schema) derinliğini artır. Eğer sayfa ürünse Product, hizmetse Service (veya uygun olan) şemasını fiyat, yorum, açıklama gibi tüm detaylarıyla baştan yaz. Şema koduna yapay zeka (LLM) dostu 'knowsAbout' veya 'mentions' bağlamsal etiketlerini ekle. " + kural,
    "" // 6. adımın fix butonu yok.
  ];

  setTimeout(() => { copilotChat.scrollTop = copilotChat.scrollHeight; }, 100);
  let prompt = fixPrompts[step];
  if (step === 6) {
    prompt = "Sen bir AI SEO Entegrasyon Şefisin. Aşağıda, bu web sitesi için önceki 5 adımda ürettiğimiz TÜM spesifik içerik ve teknik düzeltme çıktılarını sana veriyorum.\nGörevin genel geçer SEO kuralları anlatmak DEĞİL. Görevin; doğrudan bizim ürettiğimiz bu spesifik metinlerin ve kodların birbirleriyle olan görünmez bağlarını (zincirlerini) ekibe göstermek.\nÖrneğin: Ürettiğimiz spesifik değer teklifi metinlerinin, şemadaki 'knowsAbout' veya 'mentions' etiketleriyle nasıl eşleştiğini; veya ürettiğimiz SSS metinlerinin teknik taraftaki HTML/Schema kodlarıyla aynı anda yayına alınmazsa yapay zekanın neden kafasının karışacağını anlat. \nEkibe, ürettiğimiz bu 5 adımlık paketin parçalanmadan 'bütünsel' olarak siteye entegre edilmesi gerektiğini, doğrudan ürettiğimiz spesifik veriler üzerinden ispatla.\n\n--- ÖNCEKİ 5 ADIMDA ÜRETTİĞİMİZ ÇIKTILAR ---\n";
    chatMessages.forEach(msg => {
      if(msg.sender === 'ai' && !msg.text.includes('Site başarıyla tarandı') && !msg.text.includes('Arka planda')) {
        prompt += "\n" + msg.text + "\n";
      }
    });
    prompt += "\n--- ÇIKTILARIN SONU ---\n\nYukarıdaki verilere dayanarak AdresGezgini ekibi için entegrasyon zincirlerini oluştur. Uzun metinleri ve kod bloklarını birebir kopyalamana GEREK YOK, ancak tavsiyelerini verirken KESİNLİKLE yukarıdaki spesifik verilerden örnekler kullan! (Örn: '5. adımda ürettiğimiz şemadaki Folkart Towers adresi ile 2. adımdaki SSS metni birbiriyle eşleşmelidir' gibi). Genel SEO cümleleri kurma, sadece bu projeye ve yukarıdaki verilere özel konuş. " + kural;
  } else if (fetchedData) {
    prompt += `\n\n--- SİTE BAĞLAMI (BUNLARI KULLAN) ---\nURL: ${fetchedData.url}\nBaşlık: ${fetchedData.title}\nAçıklama: ${fetchedData.description}\nKategori: ${reportData.siteCategory || 'Bilinmiyor'}\nSchema: ${JSON.stringify(fetchedData.schemas)}\n---------------------\n\nÖNEMLİ KURAL: Bana şablon (Örn: [Şirket Adı], [Sektör]) verme! Yukarıdaki site bağlamı verilerini kullanarak metni doğrudan bu şirket için kişiselleştir.`;
  }
  
  // Instead of calling handleSend which might conflict with state, we process directly:
  addMessage(`${step}. Adımdaki Eksiklikler Gideriliyor (Otomatik Çözüm)...`, 'user');
  addTypingIndicator();
  copilotActions.style.display = 'none';

          p += `\n\nÖNEMLİ: Yanıtının SONUNA, analizine dayanan şu verileri içeren, aşağıdaki YAPIDA KESİN bir JSON bloğu ekle (\`\`\`json ... \`\`\` içinde olsun):
{
"overview_html": "<p>Sitenin genel özeti...</p>",
"charts_data": {
  "trust_score": 0-100 arası genel sağlık skoru,
  "eeat_radar": {
    "experience": 0-100,
    "expertise": 0-100,
    "authoritativeness": 0-100,
    "trustworthiness": 0-100
  }
},
"action_plan_table": [
  { "issue": "Aksiyon açıklaması", "category": "Kategori", "priority": "high/medium/low", "color": "red/orange/green" }
]
}`;
  try {
    const res = await fetch('form_submit.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        contents: [{ parts: [{ text: prompt }] }],
        generationConfig: { temperature: 0.2 }
      })
    });
    const result = await res.json();
    removeTypingIndicator();
    if (result.error) {
      addMessage(`Hata: ${result.error.message || result.error}`, 'ai');
      copilotActions.style.display = 'flex';
      return;
    }

          let aiText = result.candidates[0].content.parts[0].text;
    
    // JSON Extraction
    let cleanText = aiText;
    let chartData = null;
    let rawJsonStr = '';
    
    const jsonMatch = aiText.match(/```(?:json)?\s*(\{[\s\S]*?\})\s*```/i);
    if (jsonMatch) {
        cleanText = aiText.replace(jsonMatch[0], '').trim();
        rawJsonStr = jsonMatch[0];
        try {
            chartData = JSON.parse(jsonMatch[1]);
        } catch(e) { console.warn("JSON Parse Error:", e); }
    } else {
        const fallbackMatch = aiText.match(/\{[\s\S]*"trust_score"[\s\S]*\}$/);
        if (fallbackMatch) {
            cleanText = aiText.replace(fallbackMatch[0], '').trim();
            rawJsonStr = fallbackMatch[0];
            try { chartData = JSON.parse(fallbackMatch[0]); } catch(e){}
        }
    }
    
          if (chartData) {
        initOrUpdateCharts(chartData);
        if (chartData.overview_html) {
            cleanText = chartData.overview_html + "\n\n" + cleanText;
        }
    }

    let htmlText = (typeof marked !== 'undefined' ? marked.parse(cleanText) : cleanText) + (rawJsonStr ? '<div class="ai-raw-json" style="display:none;">' + rawJsonStr + '</div>' : '');
    addMessage(htmlText, 'ai', true);

    fixedIssues.add(step);
    renderIssuesUI();
    renderAiSeoActions();
    if (window.isAutoFixing) {
      copilotActions.style.display = 'none';
    } else {
      copilotActions.style.display = 'flex';
    }
    setTimeout(() => { copilotChat.scrollTop = copilotChat.scrollHeight; }, 100);
  } catch (err) {
    removeTypingIndicator();
    addMessage(`Bağlantı hatası: ${err.message}`, 'ai');
    copilotActions.style.display = 'flex';
  }
}

      window.renderDashboard = function() {
    const history = window.agChatHistory || [];
    const totalAnalyses = history.length;
    
    let battleCount = 0;
    let totalEEAT = 0;
    let eeatCount = 0;
    
    history.forEach(h => {
       if (h.messages) {
          const msgs = JSON.stringify(h.messages).toLowerCase();
          if (msgs.includes('rakip') || msgs.includes('battle') || msgs.includes('competitor_comparison')) battleCount++;
          
          // Try to extract real EEAT score from JSON blocks in history
          h.messages.forEach(msg => {
              if (msg.sender === 'ai' && msg.text) {
                  try {
                      const jsonMatch = msg.text.match(/<div class="ai-raw-json" style="display:none;">```json\s*(\{[\s\S]*?\})\s*```<\/div>/);
                      if (jsonMatch) {
                          const data = JSON.parse(jsonMatch[1]);
                          let charts = data.charts_data || data;
                          if (charts.eeat_radar) {
                              let exp = charts.eeat_radar.experience || 0;
                              let expt = charts.eeat_radar.expertise || 0;
                              let auth = charts.eeat_radar.authoritativeness || 0;
                              let trust = charts.eeat_radar.trustworthiness || 0;
                              let avg = (exp + expt + auth + trust) / 4;
                              if (avg > 0) {
                                  totalEEAT += avg;
                                  eeatCount++;
                              }
                          }
                      }
                  } catch(e) {}
              }
          });
       }
    });

    const avgEEAT = eeatCount > 0 ? Math.round(totalEEAT / eeatCount) : 0;

    let recentHtml = '';
    history.slice(0, 5).forEach(h => {
       const comp = h.completedSteps ? h.completedSteps.length : 0;
       const fixes = h.fixedIssues ? h.fixedIssues.length : 0;
       let health = "Orta";
       let color = "#eab308";
       if (comp >= 5 && fixes >= 3) { health = "Mükemmel"; color = "#22c55e"; }
       else if (comp >= 3 && fixes < 2) { health = "Kritik"; color = "#ef4444"; }
       
       const dateStr = new Date(h.date).toLocaleDateString('tr-TR');
       recentHtml += `
          <div style="display:flex; justify-content:space-between; align-items:center; padding: 12px; border-bottom: 1px solid var(--border);">
             <div style="font-weight: 500; font-size: 13px; color: var(--text);">${h.url} <span style="font-size:11px; color:var(--muted); margin-left:8px;">${dateStr}</span></div>
             <div style="font-size: 12px; font-weight: 600; color: ${color}; background: ${color}20; padding: 4px 8px; border-radius: 6px;">${health}</div>
          </div>
       `;
    });
    if (recentHtml === '') recentHtml = '<div style="padding: 12px; color: var(--muted); font-size: 13px;">Henüz analiz bulunmuyor.</div>';

    const dbView = document.getElementById('ai-seo-dashboard-view');
    const actionView = document.getElementById('copilot-action-view');
    if(dbView) {
        dbView.innerHTML = `
      <div id="welcome-dashboard-flag" style="padding: 24px;">
         <h2 style="margin-bottom: 24px; font-size: 20px; font-weight: 600; color: var(--text);">Agency OS Kontrol Paneli</h2>
         
         <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px;">
            <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
               <div style="font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Toplam Analiz</div>
               <div style="font-size: 32px; font-weight: 700; color: #2563eb;">${totalAnalyses}</div>
            </div>
            <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
               <div style="font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Rakip Analizi (Battle)</div>
               <div style="font-size: 32px; font-weight: 700; color: #dc2626;">${battleCount}</div>
            </div>
            <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
               <div style="font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Ortalama E-E-A-T</div>
               <div style="font-size: 32px; font-weight: 700; color: #16a34a;">${avgEEAT > 0 ? avgEEAT + '/100' : '-'}</div>
            </div>
         </div>

         <div style="background: #fff; border-radius: 12px; border: 1px solid var(--border); overflow: hidden; margin-bottom: 32px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <div style="padding: 16px; background: #f8fafc; border-bottom: 1px solid var(--border); font-weight: 600; font-size: 14px; color: var(--text);">Son Taranan 5 Site</div>
            ${recentHtml}
         </div>

         <div style="text-align: center;">
            <button id="btn-dashboard-start-fresh" class="btn btn--primary" style="padding: 12px 24px; font-size: 14px; font-weight: 600; cursor: pointer; position: relative; z-index: 9999;">
               <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px; vertical-align:middle;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
               Yeni Analiz Başlat
            </button>
         </div>
      </div>
    `;
    const btnFresh = document.getElementById('btn-dashboard-start-fresh');
    if (btnFresh) {
        btnFresh.addEventListener('click', () => {
            if (typeof window.startFreshAnalysis === 'function') {
                window.startFreshAnalysis();
            }
        });
    }
} // close if(dbView)
}; // close window.renderDashboard = function()

// --- CHART.JS LOGIC START ---
let overallHealthChart = null;
let eeatChart = null;
let battleChart = null;

function initOrUpdateCharts(data) {
  if (typeof Chart === 'undefined') return;

  let chartsData = data.charts_data || data; // Fallback if AI skips wrapper
  let trustScore = chartsData.trust_score || chartsData.genel_skor || 0;
  
  // 1. Genel Sağlık (Doughnut)
  const ctxOverall = document.getElementById('chart-overall-health');
  if (ctxOverall) {
      const placeholder = document.getElementById('chart-overall-health-placeholder');
      if (placeholder) placeholder.style.display = 'none';
      if (overallHealthChart) overallHealthChart.destroy();
      overallHealthChart = new Chart(ctxOverall, {
          type: 'doughnut',
          data: {
              labels: ['Güven Skoru', 'Eksik'],
              datasets: [{
                  data: [trustScore, 100 - trustScore],
                  backgroundColor: ['#475569', '#e2e8f0'],
                  borderWidth: 0
              }]
          },
          options: { cutout: '80%' }, plugins: [{ id: 'textCenter', beforeDraw: function(chart) { var width = chart.width, height = chart.height, ctx = chart.ctx; ctx.restore(); var fontSize = (height / 100).toFixed(2); ctx.font = 'bold ' + (fontSize * 1.5) + 'em sans-serif'; ctx.textBaseline = 'middle'; ctx.fillStyle = '#0f172a'; var text = trustScore + '%', textX = Math.round((width - ctx.measureText(text).width) / 2), textY = height / 2; ctx.fillText(text, textX, textY); ctx.save(); } }]
      });
      
      // Update the centered text inside doughnut (I had a plugin for this, but let's just use absolute div if they prefer)
      let textDiv = document.getElementById('chart-overall-health-text');
      if (textDiv) textDiv.innerHTML = trustScore + '%';
  }

  // 2. E-E-A-T Radar
  const ctxEeat = document.getElementById('chart-eeat');
  if (ctxEeat && chartsData.eeat_radar) {
      const eeatPlaceholder = document.getElementById('chart-eeat-placeholder');
      if (eeatPlaceholder) eeatPlaceholder.style.display = 'none';
      if (eeatChart) eeatChart.destroy();
      
      let eeat = chartsData.eeat_radar;
      // fallback to old turkish keys just in case AI messes up
      let exp = eeat.experience || eeat.deneyim || 0;
      let exp_t = eeat.expertise || eeat.uzmanlik || 0;
      let auth = eeat.authoritativeness || eeat.otorite || 0;
      let trust = eeat.trustworthiness || eeat.guvenilirlik || 0;

      eeatChart = new Chart(ctxEeat, {
          type: 'radar',
          data: {
              labels: ['Deneyim', 'Uzmanlık', 'Otorite', 'Güvenilirlik'],
              datasets: [{
                  label: 'E-E-A-T Profili',
                  data: [exp, exp_t, auth, trust],
                  backgroundColor: 'rgba(59, 130, 235, 0.2)',
                  borderColor: '#2563eb',
                  pointBackgroundColor: '#2563eb'
              }]
          },
          options: { scales: { r: { min: 0, max: 100 } } }
      });
  }

  // 3. Aksiyon Planı Tablosu
  const tableContainer = document.getElementById('action-plan-table-container');
  let actionPlan = data.action_plan_table || data.acil_aksiyon_plani || [];
  
  if (tableContainer && actionPlan.length > 0) {
      let tableHtml = '<table style="width:100%; border-collapse: collapse; text-align:left; font-size:13px;">';
      tableHtml += '<tr style="border-bottom: 2px solid #e2e8f0; color: #475569;"><th>Tespit Edilen Sorun</th><th>Kategori</th><th>Önem</th></tr>';

      actionPlan.forEach(item => {
          let priorityText = item.priority || item.onem || 'Medium';
          let color = item.color || (priorityText.toLowerCase().includes('high') || priorityText.toLowerCase().includes('yüksek') ? '#ef4444' : (priorityText.toLowerCase().includes('low') || priorityText.toLowerCase().includes('düşük') ? '#10b981' : '#f59e0b'));
          
          tableHtml += `<tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 10px;">${item.issue || item.sorun || '-'}</td>
              <td style="padding: 10px; color: #64748b;">${item.category || item.kategori || '-'}</td>
              <td style="padding: 10px;">
                  <span style="background: ${color}; color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px;">
                      ${priorityText.toUpperCase()}
                  </span>
              </td>
          </tr>`;
      });
      tableHtml += '</table>';
      tableContainer.innerHTML = tableHtml;
  }
  
  // 4. Battle Mode Bar Chart
  if (chartsData.competitor_comparison && chartsData.competitor_comparison.main_scores) {
      const comp = chartsData.competitor_comparison;
      document.getElementById('battle-chart-container').style.display = 'block';
      const ctxB = document.getElementById('chart-battle');
      const battlePlaceholder = document.getElementById('chart-battle-placeholder');
      if (battlePlaceholder) battlePlaceholder.style.display = 'none';
      if (ctxB) {
          if (battleChart) battleChart.destroy();
          battleChart = new Chart(ctxB, {
              type: 'bar',
              data: {
                  labels: ['İçerik', 'SEO', 'E-E-A-T'],
                  datasets: [
                      { label: comp.main_site_name || 'Senin Siten', data: comp.main_scores, backgroundColor: '#3b82f6' },
                      { label: comp.competitor_name || 'Rakip', data: comp.comp_scores, backgroundColor: '#ef4444' }
                  ]
              }
          });
      }
  }
}
// --- CHART.JS LOGIC END ---

window._forceResetChat = resetChat;
function resetChat(loadFromHistory = null, forceActionView = false) {
  const dbView = document.getElementById('ai-seo-dashboard-view');
  const actionView = document.getElementById('copilot-action-view');
  if (dbView && actionView) {
      if (loadFromHistory || forceActionView) {
          // Loading a history item OR explicitly forcing action view → show chat view
          dbView.style.display = 'none'; 
          actionView.style.display = 'flex';
      } else if (actionView.style.display === 'flex' || actionView.style.display === 'block') {
          // Already in action view (e.g. "Yeni Sohbet" clicked) → stay in action view
      } else {
          // Called at page load → show dashboard
          dbView.style.display = 'block'; 
          actionView.style.display = 'none';
      }
  }
  if (loadFromHistory) {
    window._chatLoadedFromHistory = true;
    currentChatId = loadFromHistory.chatId;
    currentState = 'WAITING_FOR_TYPE';
    currentStep = 1;
    const rawSteps = loadFromHistory.completedSteps || [];
    completedSteps = new Set(rawSteps.map(s => parseInt(s)));
    const rawIssues = loadFromHistory.fixedIssues || [];
    fixedIssues = new Set(rawIssues.map(s => parseInt(s)));
    
    targetUrl = loadFromHistory.url;
    targetType = loadFromHistory.type;
    chatMessages = loadFromHistory.messages || [];

    if (completedSteps.size === 0 && chatMessages.length > 0) {
      chatMessages.forEach(m => {
        if (m.text) {
           const match = m.text.match(/^(\d+)\.\s*Adım:/i);
           if (match) completedSteps.add(parseInt(match[1]));
        }
      });
      
      if (completedSteps.size === 0) {
         let aiCount = chatMessages.filter(m => m.sender === 'ai').length;
         for(let i = 1; i < aiCount && i <= 5; i++) {
           completedSteps.add(i);
         }
      }
    }
    
    if (completedSteps.has(5)) completedSteps.add(6); if (loadFromHistory.reportData) {
      reportData = loadFromHistory.reportData;
    } else {
      // Fallback for old history
      reportData = chatMessages.filter(m => m.sender === 'ai' && m.text.length > 200).map(m => m.text);
    }
    const llmsC = document.getElementById('copilot-llms-container');
    if (llmsC) llmsC.style.display = 'none';
    const msgContainer = document.getElementById('copilot-chat-messages-container');
    if (msgContainer) msgContainer.style.display = 'block';
    if (msgContainer) { try { msgContainer.replaceChildren(); } catch(e) { msgContainer.innerHTML = ''; } }
    chatMessages.forEach(msg => { addMessage(msg.text, msg.sender, msg.isHtml, false); if (msg.sender === 'ai') { try { const jsonMatch = msg.text.match(/<div class="ai-raw-json" style="display:none;">```json\s*(\{[\s\S]*?\})\s*```<\/div>/); if (jsonMatch) { const chartData = JSON.parse(jsonMatch[1]); initOrUpdateCharts(chartData); } } catch(e){} } });
    
    copilotInputArea.style.display = "none"; const cqa = document.getElementById("copilot-quick-actions"); if(cqa) cqa.style.display = "none";
    renderAiSeoActions();
    
    if (copilotSaveBtn) {
      copilotSaveBtn.innerHTML = `✓ Kayıtlı`;
      copilotSaveBtn.style.opacity = '0.5';
      copilotSaveBtn.style.pointerEvents = 'none';
    }
    renderAiSeoActions();
  if (typeof updateActiveHistoryItem === 'function') updateActiveHistoryItem();
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }

  currentChatId = Date.now().toString();
  window._chatLoadedFromHistory = false;
  currentState = 'WAITING_FOR_URL';
  currentStep = 1;
  completedSteps.clear();
  fixedIssues.clear();
  targetUrl = '';
  targetType = '';
  fetchedData = null;
  window.targetCompetitorUrl = '';
  window.fetchedCompetitorData = null;
  chatMessages = [];
  reportData = [];
  
  updateProgressUI(0);
  const llmsC = document.getElementById('copilot-llms-container');
  if (llmsC) llmsC.style.display = 'none';
  const msgContainer = document.getElementById('copilot-chat-messages-container');
  if (msgContainer) { msgContainer.style.display = 'block'; msgContainer.style.flex = '0'; msgContainer.style.minHeight = '100px'; }
  


  if (msgContainer) { 
      try { msgContainer.replaceChildren(); } catch(e) { msgContainer.innerHTML = ''; }
      addMessage('👋 Merhaba! Ben GEO SEO Asistanı. Analiz etmek istediğiniz sayfanın URL\'sini yapıştırarak başlayabilirsiniz.', 'ai'); 
  }
  if (typeof window.renderDashboard === 'function') { window.renderDashboard(); }
  
  if (copilotActions) {
      try { copilotActions.replaceChildren(); } catch(e) { copilotActions.innerHTML = ''; }
  }
  if (copilotInputArea) copilotInputArea.style.display = "block"; 
  
  // Explicitly ensure the input container itself is visible, just in case
  const inputContainer = document.getElementById('copilot-input-area-container');
  if (inputContainer) inputContainer.style.display = 'block';
  
  const cqa = document.getElementById("copilot-quick-actions"); 
  if(cqa) cqa.style.display = "none";
  
  // Explicitly show the text input and wrapper
  const textInput = document.getElementById('copilot-text-input');
  if (textInput) {
      const wrapper = textInput.closest('.input-wrapper');
      if (wrapper) wrapper.style.display = 'flex';
      textInput.style.display = 'block';
      textInput.value = '';
      textInput.placeholder = 'Örn: https://www.site.com/hizmet';
      setTimeout(() => textInput.focus(), 100);
  }
  
  
  if (copilotSaveBtn) {
    copilotSaveBtn.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Kaydet`;
    copilotSaveBtn.style.opacity = '1';
    copilotSaveBtn.style.cursor = 'pointer';
    copilotSaveBtn.style.pointerEvents = 'auto';
    copilotSaveBtn.onclick = null;
  }
  if (loadFromHistory) {
      renderAiSeoActions();
  }
  if (typeof updateActiveHistoryItem === 'function') updateActiveHistoryItem();
}

async function handleSend() {
  const val = copilotTextInput.value.trim();
  if (!val) return;
  
  if (currentState === 'WAITING_FOR_URL') {
    addMessage(val, 'user');
    copilotTextInput.value = '';
    if (!val.startsWith('http')) { addMessage("Lütfen geçerli bir URL girin (http veya https ile başlamalı).", 'ai'); return; }
    
    targetUrl = val;
    currentState = 'WAITING_FOR_TYPE';
    const msgContainer = document.getElementById('copilot-chat-messages-container');
    if (msgContainer) { msgContainer.style.flex = '1'; msgContainer.style.minHeight = '200px'; }
    copilotInputArea.style.display = "none"; const cqa = document.getElementById("copilot-quick-actions"); if(cqa) cqa.style.display = "none";
    addMessage("Harika! Bu sayfa hangi kategoride yer alıyor? (Hizmet mi, yoksa ürün sattığınız bir E-Ticaret sayfası mı?)", 'ai');
    
    copilotActions.innerHTML = `
      <button class="btn btn--primary" id="btn-type-service">Hizmet / Kurumsal</button>
      <button class="btn btn--primary" id="btn-type-product">Ürün / E-Ticaret</button>
    `;
    document.getElementById('btn-type-service').addEventListener('click', () => { targetType = 'Hizmet / Kurumsal'; addMessage(targetType, 'user'); startUrlFetch(); });
    document.getElementById('btn-type-product').addEventListener('click', () => { targetType = 'Ürün / E-Ticaret'; addMessage(targetType, 'user'); startUrlFetch(); });
  } else if (currentState === 'CHAT_MODE') {
    addMessage(val, 'user');
    copilotTextInput.value = '';
    addTypingIndicator();
    
    let p = `Sen bir GEO uzmanısın. Kullanıcı şu an "${targetUrl}" sitesi hakkında ekstra bir soru soruyor: "${val}"\n\nSite Başlığı: ${fetchedData ? fetchedData.title : ''}\nLütfen soruyu markdown formatında yanıtla.`;
    
            p += `\n\nÖNEMLİ: Yanıtının SONUNA, analizine dayanan şu verileri içeren, aşağıdaki YAPIDA KESİN bir JSON bloğu ekle (\`\`\`json ... \`\`\` içinde olsun):
{
"overview_html": "<p>Sitenin genel özeti...</p>",
"charts_data": {
  "trust_score": 0-100 arası genel sağlık skoru,
  "eeat_radar": {
    "experience": 0-100,
    "expertise": 0-100,
    "authoritativeness": 0-100,
    "trustworthiness": 0-100
  }
},
"action_plan_table": [
  { "issue": "Aksiyon açıklaması", "category": "Kategori", "priority": "high/medium/low", "color": "red/orange/green" }
]
}`;
  try {
    const res = await fetch('form_submit.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ contents: [{ parts: [{ text: p }] }], generationConfig: { temperature: 0.5 } }) });
      const result = await res.json();
      removeTypingIndicator();
      if (result.error) { addMessage(`Hata: ${result.error.message || result.error}`, 'ai'); return; }
      
            let aiText = result.candidates[0].content.parts[0].text;
    
    // JSON Extraction
    let cleanText = aiText;
    let chartData = null;
    let rawJsonStr = '';
    
    const jsonMatch = aiText.match(/```(?:json)?\s*(\{[\s\S]*?\})\s*```/i);
    if (jsonMatch) {
        cleanText = aiText.replace(jsonMatch[0], '').trim();
        rawJsonStr = jsonMatch[0];
        try {
            chartData = JSON.parse(jsonMatch[1]);
        } catch(e) { console.warn("JSON Parse Error:", e); }
    } else {
        const fallbackMatch = aiText.match(/\{[\s\S]*"trust_score"[\s\S]*\}$/);
        if (fallbackMatch) {
            cleanText = aiText.replace(fallbackMatch[0], '').trim();
            rawJsonStr = fallbackMatch[0];
            try { chartData = JSON.parse(fallbackMatch[0]); } catch(e){}
        }
    }
    
          if (chartData) {
        initOrUpdateCharts(chartData);
        if (chartData.overview_html) {
            cleanText = chartData.overview_html + "\n\n" + cleanText;
        }
    }

    let htmlText = (typeof marked !== 'undefined' ? marked.parse(cleanText) : cleanText) + (rawJsonStr ? '<div class="ai-raw-json" style="display:none;">' + rawJsonStr + '</div>' : '');
      addMessage(htmlText, 'ai', true);
    } catch (err) {
      removeTypingIndicator();
      addMessage(`Bağlantı hatası: ${err.message}`, 'ai');
    }
  }
}

copilotSendBtn.addEventListener('click', handleSend);
copilotTextInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') handleSend(); });

async function startUrlFetch() {
  copilotActions.innerHTML = '';
  addMessage(window.targetCompetitorUrl ? "Hedef site ve rakip site arka planda taranıyor. Lütfen bekle..." : "Arka planda siteyi tarıyorum, meta ve schema verilerini çekiyorum. Lütfen bekle...", 'ai');
  addTypingIndicator();
  try {
    const res = await fetch(`fetch_url.php?url=${encodeURIComponent(targetUrl)}`);
    const data = await res.json();
    removeTypingIndicator();
    if (data.error) { addMessage(`Hata: ${data.error}`, 'ai'); setTimeout(resetChat, 3000); return; }
    
    fetchedData = data;
    addMessage(`✅ Hedef Site başarıyla tarandı!<br><br><strong>Başlık:</strong> ${data.title}<br><strong>Bulunan Schema Sayısı:</strong> ${data.schemas ? data.schemas.length : 0}`, 'ai', true);



    addMessage(`Şimdi ${targetType} sitenize özel 5 adımlı analiz sürecini başlatabiliriz.`, 'ai', true);
    renderAiSeoActions();
  } catch (e) {
    removeTypingIndicator();
    addMessage(`Bağlantı hatası: ${e.message}`, 'ai');
    setTimeout(resetChat, 3000);
  }
}

function renderAiSeoActions() {
  updateProgressUI(currentStep);
  window.renderDynamicQuickActions();
  let nextStep = 1;
  while(completedSteps.has(nextStep) && nextStep <= 5) { nextStep++; }
  
  let actionsHtml = '';
  
  if (nextStep <= 5) {
    currentStep = nextStep;
    updateProgressUI(currentStep);
    window.renderDynamicQuickActions();
  }

  const btnAnalyze = document.getElementById('btn-auto-analyze');
  const btnFix = document.getElementById('btn-auto-fix');

  if (completedSteps.size < 6 || (completedSteps.size >= 5 && fixedIssues.size < 6)) {
    const analyzeText = completedSteps.size === 0 ? "Tüm Siteyi Analiz Et" : "Kalan Adımları Analiz Et";
    const fixText = fixedIssues.size === 0 ? "Tüm Eksikleri Gider" : "Kalan Eksikleri Gider";
    
    if (btnAnalyze) {
        btnAnalyze.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg> ${analyzeText}`;
        btnAnalyze.style.display = 'flex';
    }
    if (btnFix) {
        btnFix.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg> ${fixText}`;
        btnFix.style.display = 'flex';
    }
  } else {
    if (btnAnalyze) btnAnalyze.style.display = 'none';
    if (btnFix) btnFix.style.display = 'none';
  }

  copilotActions.innerHTML = '';

  const topPdfBtn = document.getElementById('btn-download-pdf');
  const topTodosBtn = document.getElementById('btn-send-to-todos');
  
  if (topPdfBtn) {
     topPdfBtn.style.display = 'inline-flex';
     topPdfBtn.style.alignItems = 'center';
     topPdfBtn.disabled = false;
     topPdfBtn.style.opacity = isCompleted ? '1' : '0.5';
     topPdfBtn.style.cursor = isCompleted ? 'pointer' : 'not-allowed';
     topPdfBtn.title = isCompleted ? "PDF Rapor İndir" : "Önce tüm analiz ve çözümleri bitirin";
     topPdfBtn.onclick = () => { 
        if (!isCompleted) {
           alert("Önce tüm analiz ve çözümleri bitirin.");
           return;
        }
        downloadReportPDF(); 
     };
  }
  
  if (topTodosBtn) {
     topTodosBtn.style.display = 'inline-flex';
     topTodosBtn.style.alignItems = 'center';
     topTodosBtn.disabled = false;
     
     let isSent = false;
     if (isCompleted && typeof currentChatId !== 'undefined' && currentChatId) {
         let todos = JSON.parse(localStorage.getItem('ag_seo_todos') || '{}');
         for (let domain in todos) {
             if ((todos[domain].tech && todos[domain].tech.some(t => t.chatId === currentChatId)) ||
                 (todos[domain].text && todos[domain].text.some(t => t.chatId === currentChatId)) ||
                 (todos[domain].general && todos[domain].general.some(t => t.chatId === currentChatId))) {
                 isSent = true;
                 break;
             }
         }
     }

     if (!isCompleted) {
         topTodosBtn.style.opacity = '0.5';
         topTodosBtn.style.cursor = 'not-allowed';
         topTodosBtn.title = "Önce tüm analiz ve çözümleri bitirin";
         topTodosBtn.innerHTML = '📋 Gönder';
     } else if (isSent) {
         topTodosBtn.style.opacity = '0.5';
         topTodosBtn.style.cursor = 'default';
         topTodosBtn.title = "Zaten gönderildi";
         topTodosBtn.innerHTML = '✅ Gönderildi';
     } else {
         topTodosBtn.style.opacity = '1';
         topTodosBtn.style.cursor = 'pointer';
         topTodosBtn.title = "Yapılacaklara Gönder";
         topTodosBtn.innerHTML = '📋 Gönder';
     }
     
     topTodosBtn.onclick = () => {
        if (!isCompleted) {
           alert("Önce tüm analiz ve çözümleri bitirin.");
           return;
        }
        if (isSent || topTodosBtn.innerHTML.includes('Gönderildi')) return;
        extractTodosAndSend();
        topTodosBtn.innerHTML = '✅ Gönderildi';
        topTodosBtn.style.opacity = '0.5';
        topTodosBtn.style.cursor = 'default';
        topTodosBtn.title = "Zaten gönderildi";
     };
  }

  // btn-ai-step removed
  if (document.getElementById('btn-auto-analyze')) {
    // avoid multiple bindings by replacing the node or using a flag
    const btn = document.getElementById('btn-auto-analyze');
    const newBtn = btn.cloneNode(true);
    btn.parentNode.replaceChild(newBtn, btn);
    newBtn.addEventListener('click', async () => {
      window.isAutoAnalyzing = true;
      await runAutoAnalysis();
    });
  }

  if (document.getElementById('btn-auto-fix')) {
    const btn = document.getElementById('btn-auto-fix');
    const newBtn = btn.cloneNode(true);
    btn.parentNode.replaceChild(newBtn, btn);
    newBtn.addEventListener('click', async () => {
      window.isAutoFixing = true;
      await runAutoFixes();
    });
  }
  
  // PDF listener moved to topBtn

  currentState = 'CHAT_MODE';
  copilotInputArea.style.display = "block"; const cqa = document.getElementById("copilot-quick-actions"); if(cqa) cqa.style.display = "flex";
  copilotTextInput.placeholder = "Başka sormak istediğiniz bir şey var mı?";
}

window.isAutoAnalyzing = false;
window.isAutoFixing = false;

async function runAutoAnalysis() {
  for (let i = 1; i <= 5; i++) {
    if (!window.isAutoAnalyzing) break;
    if (!completedSteps.has(i)) {
      currentStep = i;
      await processAiSeoStep();
      if (i < 5) await new Promise(r => setTimeout(r, 4000));
    }
  }
  window.isAutoAnalyzing = false;
  copilotActions.style.display = 'flex';
  renderAiSeoActions();
}

async function runAutoFixes() {
  for (let i = 1; i <= 6; i++) {
    if (!window.isAutoFixing) break;
    if (!fixedIssues.has(i) && (completedSteps.has(i) || (i === 6 && completedSteps.has(5)))) {
      await fixAiSeoIssue(i);
      if (i < 6) await new Promise(r => setTimeout(r, 4000));
    }
  }
  window.isAutoFixing = false;
  copilotActions.style.display = 'flex';
  renderAiSeoActions();
}

async function processAiSeoStep() {
  const step = currentStep;
  addMessage(`${step}. Adım: ${aiSeoSteps[step]} çalıştırılıyor...`, 'user');
  copilotActions.style.display = 'none'; 
  addTypingIndicator();

  if (!fetchedData) {
    removeTypingIndicator();
    addMessage("⚠️ Önceki sohbet geri yüklendi ancak arka plan verisi eksik. Detaylı analize devam etmek için sayfayı yenileyip URL'yi baştan taratmalısınız.", 'ai');
    copilotActions.style.display = 'flex';
    return;
  }

  let p = `Sen bir GEO uzmanısın. ÖNEMLİ: Kendini tanıtma, selamlama yapma, doğrudan istenilen formatta yanıt ver. Kullanıcının ${targetType} sektörü sitesini incele. \nSayfa Başlığı: ${fetchedData.title}\nMeta: ${fetchedData.description}\nBulunan JSON-LD Schema: ${fetchedData.schemas.join(', ')}\nSayfa Metni: ${fetchedData.text}\n\n`;
  
  if (step === 1) {
    p += `Aşağıdaki başlıkları kullanarak sitenin İş Bağlamını ve Site Yapısını Markdown formatında analiz et:
**Yapay Zekâ Uyumlu Site Yapısı (Bilgi Mimarisi)**
* BU SAYFA NE HAKKINDADIR VE KİMİN İÇİNDİR?
* HİYERARŞİ VE AYRIŞMA: Sayfadaki hizmet/ürün hiyerarşisi net mi? Karmaşıklık var mı?
* ANAHTAR VARLIKLAR (ENTITIES): Yapay zekanın Knowledge Graph (Bilgi Grafiği) için bu sitenin odaklanması gereken en önemli 3-4 kavram.
* TEKNİK SEO EKSİKLERİ: Sayfanın taranabilirliğini (Robots.txt, Sitemap, Canonical vb. standartları) göz önünde bulundurarak olası risklerini değerlendir.
Buna ek olarak E-E-A-T (Deneyim, Uzmanlık, Otoriterlik, Güvenilirlik) kurallarına göre bir değerlendirme yaz.`;
  } 
  else if (step === 2) {
    p += `Aşağıdaki başlıkları kullanarak Markdown formatında bir Kullanıcı Odaklı İçerik ve Etkililik raporu oluştur:
**Kullanıcı Odaklı İçerik ve SSS Raporu**
* KULLANICI NİYETİ: Kullanıcıların bu sayfada bulmayı beklediği asıl cevaplar neler?
* ÖNE ÇIKAN KULLANICI SORULARI (En az 5 soru listele. Sitenin bu sorulara DOĞRUDAN cevap verip veremediğini (+/-) yönleriyle ve 10 üzerinden skorla yaz.)
* İÇERİK FIRSATLARI: İçeriğin daha faydalı, güvenilir ve insan odaklı (helpful content) olması için 5 maddelik aksiyon planı.`;
  } 
  else if (step === 3) {
    p += `Aşağıdaki başlıkları kullanarak Rakip ve İçerik Geliştirme analizi yap:
**Rakip Zafiyetleri ve İçerik Düzenleme**
* İÇERİK DERİNLİĞİ: Sitedeki metinler çok mu genel? Teknik terimler açıklanmış mı? Hizmet/ürün kapsamı net mi?
* RAKİP KIYASLAMASI: Aynı sektördeki en bilinen 3 rakibe kıyasla tahmini İÇERİK GÜVEN PUANI (%).
Sitenin rakiplere kıyasla hangi açıkları kapatması ve metinleri nasıl özgünleştirmesi gerektiğini vurgula.`;
  } 
  else if (step === 4) {
    p += `Aşağıdaki başlıkları kullanarak LLM içerik güven metriklerini yüzdelik (%) olarak belirle:
Altına "İÇERİK İÇİ BAĞLANTILAR (INTERNAL LINKING) VE CANONICAL" başlığı aç.
* İÇERİK BAĞ AĞI (TOPIC CLUSTERS): Bu sayfanın otoritesini artırmak için hangi konularda bilgilendirici blog yazıları yazılmalı ve bu sayfaya nasıl iç link (internal link) verilmeli?
* ARAMA MOTORU KAYITLARI: GSC, Bing ve Yandex üzerinde takip edilmesi gereken indeks ve tarama durumları için genel stratejik tavsiyeler.`;
  } 
  else if (step === 5) {
    p += `Aşağıdaki başlıkları kullanarak Yapılandırılmış Veri ve Optimizasyon analizi yap:
**Yapılandırılmış Veri (Schema.org) Derinliği**
* Sitede tespit edilen JSON-LD şemaları yeterli mi? (Ürün sayfaları için detaylı Product, Hizmet sayfaları için Service, Breadcrumb, FAQ vs. var mı?)
* Okunabilirlik ve UX Analizi: LLM'ler metni rahat anlıyor mu?
Son olarak, LLM (SGE) dostu kusursuz hale getirilmiş YENİ BİR ÖRNEK METİN ve META AÇIKLAMASI sun.`;
  }
  else if (step === 6) {
    p += `ÖNEMLİ: Sen bir Bütünsel Entegrasyon Şefisin. Önceki 5 adımdaki Metin ve Teknik yapıları birbirine nasıl bağlamamız gerektiğini analiz et. 
* AI TANITIM DOSYALARI (llms.txt): Bu sitenin kök dizininde bulunması gereken bir llms.txt dosyasının önemini ve yapay zeka botlarına siteyi nasıl özetlemesi gerektiğini anlat.
* SENTEZ: Hangi içeriğin, hangi şemayla ve hangi linkleme (Topic Cluster) kurgusuyla BİRLİKTE canlıya alınması gerektiğini açıkla. Hiçbir kod üretme, sadece bu organik bağı analiz et.`;
  }

          p += `\n\nÖNEMLİ: Yanıtının SONUNA, analizine dayanan şu verileri içeren, aşağıdaki YAPIDA KESİN bir JSON bloğu ekle (\`\`\`json ... \`\`\` içinde olsun):
{
"overview_html": "<p>Sitenin genel özeti...</p>",
"charts_data": {
  "trust_score": 0-100 arası genel sağlık skoru,
  "eeat_radar": {
    "experience": 0-100,
    "expertise": 0-100,
    "authoritativeness": 0-100,
    "trustworthiness": 0-100
  }
},
"action_plan_table": [
  { "issue": "Aksiyon açıklaması", "category": "Kategori", "priority": "high/medium/low", "color": "red/orange/green" }
]
}`;
  try {
    const res = await fetch('form_submit.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ contents: [{ parts: [{ text: p }] }], generationConfig: { temperature: 0.2 } }) });
    const result = await res.json();
    removeTypingIndicator();
    if (result.error) { addMessage(`Hata: ${result.error.message || result.error}`, 'ai'); copilotActions.style.display = 'flex'; return; }
    
          let aiText = result.candidates[0].content.parts[0].text;
    
    // JSON Extraction
    let cleanText = aiText;
    let chartData = null;
    let rawJsonStr = '';
    
    const jsonMatch = aiText.match(/```(?:json)?\s*(\{[\s\S]*?\})\s*```/i);
    if (jsonMatch) {
        cleanText = aiText.replace(jsonMatch[0], '').trim();
        rawJsonStr = jsonMatch[0];
        try {
            chartData = JSON.parse(jsonMatch[1]);
        } catch(e) { console.warn("JSON Parse Error:", e); }
    } else {
        const fallbackMatch = aiText.match(/\{[\s\S]*"trust_score"[\s\S]*\}$/);
        if (fallbackMatch) {
            cleanText = aiText.replace(fallbackMatch[0], '').trim();
            rawJsonStr = fallbackMatch[0];
            try { chartData = JSON.parse(fallbackMatch[0]); } catch(e){}
        }
    }
    
          if (chartData) {
        initOrUpdateCharts(chartData);
        if (chartData.overview_html) {
            cleanText = chartData.overview_html + "\n\n" + cleanText;
        }
    }

    let htmlText = (typeof marked !== 'undefined' ? marked.parse(cleanText) : cleanText) + (rawJsonStr ? '<div class="ai-raw-json" style="display:none;">' + rawJsonStr + '</div>' : '');
    addMessage(htmlText, 'ai', true);
    
    reportData[step - 1] = htmlText;

    completedSteps.add(step); if(step === 5) completedSteps.add(6);
    if (completedSteps.size >= 6) {
      updateProgressUI(7);
      let llmsHtml = `🎉 Harika! Tüm AI SEO Entegrasyon zinciri dahil 6 adımın tamamını bitirdik.<br><br>Sitenizin ChatGPT ve Gemini gibi yapay zekalar tarafından %100 doğru anlaşılması için kök dizininize bir <strong>llms.txt</strong> dosyası eklemenizi öneririz.<br><br><button id="btn-generate-llms" class="btn btn--primary" style="margin-top:10px; background:#10b981; border:none; padding:8px 16px; font-weight:600; color:#fff; border-radius:6px; cursor:pointer;">🤖 Özel llms.txt Üret</button>`;
      addMessage(llmsHtml, 'ai', true);
      
      setTimeout(() => {
        let btn = document.getElementById('btn-generate-llms');
        if(btn) {
          btn.addEventListener('click', () => {
            if(typeof window.openLlmsGenerator === 'function') window.openLlmsGenerator();
          });
        }
      }, 100);
    }
    renderAiSeoActions();
    if (window.isAutoAnalyzing) {
      copilotActions.style.display = 'none';
    } else {
      copilotActions.style.display = 'flex';
    }
  } catch (err) {
    removeTypingIndicator();
    addMessage(`Bağlantı hatası: ${err.message}`, 'ai');
    copilotActions.style.display = 'flex';
  }
}

function downloadReportPDF() {
  if (!reportData || reportData.length < 5) {
    alert("Rapor oluşturulacak yeterli veri yok. Lütfen analizi tamamlayın.");
    return;
  }

  // 1. Fetch Todos
  let todos = JSON.parse(localStorage.getItem('ag_seo_todos') || '{}');
  let siteTodos = todos[targetUrl] || { tech: [], text: [], general: [] };
  
  let todoHtml = '';
  if (siteTodos.tech.length > 0 || siteTodos.text.length > 0 || siteTodos.general.length > 0) {
      todoHtml += '<div class="pdf-page" style="page-break-before: always;"><div class="pdf-header"><h2>🚀 Aksiyon Planı (Yapılacaklar)</h2></div>';
      
      if (siteTodos.tech.length > 0) {
          todoHtml += '<div class="todo-box"><h3 class="todo-title" style="color:#ef4444;">🚨 Teknik Görevler</h3><ul class="todo-list">';
          siteTodos.tech.forEach(t => todoHtml += `<li>${t.text}</li>`);
          todoHtml += '</ul></div>';
      }
      if (siteTodos.text.length > 0) {
          todoHtml += '<div class="todo-box"><h3 class="todo-title" style="color:#3b82f6;">✍️ Metin Görevleri</h3><ul class="todo-list">';
          siteTodos.text.forEach(t => todoHtml += `<li>${t.text}</li>`);
          todoHtml += '</ul></div>';
      }
      if (siteTodos.general.length > 0) {
          todoHtml += '<div class="todo-box"><h3 class="todo-title" style="color:#10b981;">📌 Genel Görevler</h3><ul class="todo-list">';
          siteTodos.general.forEach(t => todoHtml += `<li>${t.text}</li>`);
          todoHtml += '</ul></div>';
      }
      todoHtml += '</div>';
  }

  const dateStr = new Date().toLocaleDateString('tr-TR');

  const reportDiv = document.createElement('div');
  reportDiv.innerHTML = `
    <style>
      * { box-sizing: border-box; }
      .pdf-wrapper { font-family: 'Inter', Arial, sans-serif; background-color: #f8fafc; color: #1e293b; }
      
      .pdf-cover { height: 1120px; display: flex; flex-direction: column; justify-content: center; align-items: center; background: linear-gradient(135deg, #ef4444 0%, #7f1d1d 100%); color: white; text-align: center; padding: 40px; page-break-after: always; }
      .pdf-cover-logo { font-size: 42px; font-weight: 800; letter-spacing: -1px; margin-bottom: 20px; text-shadow: 0 4px 10px rgba(0,0,0,0.3); }
      .pdf-cover-title { font-size: 56px; font-weight: 800; margin-bottom: 30px; line-height: 1.1; }
      .pdf-cover-subtitle { font-size: 24px; font-weight: 500; opacity: 0.9; margin-bottom: 50px; }
      .pdf-cover-url { background: rgba(255,255,255,0.1); padding: 15px 30px; border-radius: 50px; font-size: 20px; font-weight: 600; font-family: monospace; border: 1px solid rgba(255,255,255,0.2); }
      .pdf-cover-date { margin-top: auto; font-size: 16px; opacity: 0.7; }
      
      .pdf-page { padding: 40px; min-height: 1120px; position: relative; background: white; }
      .pdf-page:not(:last-child) { page-break-after: always; }
      
      .pdf-header { border-bottom: 3px solid #ef4444; padding-bottom: 15px; margin-bottom: 30px; }
      .pdf-header h2 { font-size: 28px; color: #0f172a; margin: 0; font-weight: 800; letter-spacing: -0.5px; }
      
      .pdf-section { background: #ffffff; border-radius: 12px; padding: 25px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; }
      .pdf-section h3, .pdf-section strong { color: #ef4444; font-size: 18px; margin-bottom: 12px; display: block; page-break-after: avoid; font-weight: 700; }
      .pdf-section h4 { color: #334155; font-size: 15px; margin-top: 15px; page-break-after: avoid; }
      .pdf-section p { font-size: 14px; line-height: 1.6; color: #475569; margin-bottom: 12px; text-align: justify; }
      .pdf-section ul, .pdf-section ol { padding-left: 20px; margin-bottom: 15px; }
      .pdf-section li { font-size: 14px; margin-bottom: 8px; color: #475569; line-height: 1.5; text-align: justify; }
      
      .todo-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
      .todo-title { margin-top: 0; font-size: 16px; font-weight: 700; margin-bottom: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; }
      .todo-list { list-style-type: none; padding-left: 0; margin: 0; }
      .todo-list li { position: relative; padding-left: 25px; margin-bottom: 12px; font-size: 14px; color: #334155; line-height: 1.5; }
      .todo-list li::before { content: "✓"; position: absolute; left: 0; top: 0; color: #ef4444; font-size: 16px; font-weight: bold; }
      
      .footer { position: absolute; bottom: 20px; left: 40px; right: 40px; border-top: 1px solid #e2e8f0; padding-top: 10px; font-size: 10px; color: #94a3b8; display: flex; justify-content: space-between; }
    </style>
    
    <div class="pdf-wrapper">
        <!-- COVER PAGE -->
        <div class="pdf-cover">
          <div class="pdf-cover-logo">AG SEO CHECK UP</div>
          <div class="pdf-cover-title">KAPSAMLI<br>YAPAY ZEKA SEO<br>ANALİZ RAPORU</div>
          <div class="pdf-cover-subtitle">Modern Arama Motorları ve SGE İçin Özel İnceleme</div>
          <div class="pdf-cover-url">${targetUrl}</div>
          <div class="pdf-cover-date">Oluşturulma Tarihi: ${dateStr}</div>
        </div>
        
        <!-- PAGE 1: İş Bağlamı -->
        <div class="pdf-page">
          <div class="pdf-header"><h2>1. İş Bağlamı ve E-E-A-T Analizi</h2></div>
          <div class="pdf-section">
            ${(reportData[0] || '').replace(/\(Domain Business Context\)/gi, '').replace(/\(WHAT IS THIS DOMAIN ABOUT\)/gi, '').replace(/\(TARGET AUDIENCE\)/gi, '').replace(/\(INDUSTRY NICHE\)/gi, '')}
          </div>
          <div class="footer"><span>AG SEO Check Up - Gizli Rapor</span></div>
        </div>
        
        <!-- PAGE 2: Etkililik -->
        <div class="pdf-page">
          <div class="pdf-header"><h2>2. Kullanıcı Soruları ve Etkililik</h2></div>
          <div class="pdf-section">
            ${(reportData[1] || '').replace(/\(OVERVIEW\)/gi, '').replace(/\(Score\)/gi, '(Skor)')}
          </div>
          <div class="footer"><span>AG SEO Check Up - Gizli Rapor</span></div>
        </div>
      
        <!-- PAGE 3: Rakip Analizi & Güven Puanı -->
        <div class="pdf-page">
          <div class="pdf-header"><h2>3. Rakip & Güven Analizi</h2></div>
          <div class="pdf-section">
            ${(reportData[2] || '').replace(/\(Competitor Content Insights\)/gi, '')}
          </div>
          <div class="pdf-section">
            ${(reportData[3] || '').replace(/\(AI Content Trust\)/gi, '').replace(/\(CONTENT TRUST SCORE\)/gi, '').replace(/\(Topical Relevance\)/gi, '').replace(/\(Subject Expertise\)/gi, '').replace(/\(Credibility\)/gi, '').replace(/\(CONTENT TRUST IMPROVEMENTS\)/gi, '')}
          </div>
          <div class="footer"><span>AG SEO Check Up - Gizli Rapor</span></div>
        </div>
      
        <!-- PAGE 4: Optimizasyon & Entegrasyon -->
        <div class="pdf-page">
          <div class="pdf-header"><h2>4. Optimizasyon Önerileri</h2></div>
          <div class="pdf-section">
            ${reportData[4] || ''}
          </div>
          <div class="footer"><span>AG SEO Check Up - Gizli Rapor</span></div>
        </div>
        
        <!-- PAGE 5: YAPILACAKLAR -->
        ${todoHtml}
    </div>
  `;

  if (typeof html2pdf !== 'undefined') {
    const opt = {
      margin:       0,
      filename:     'AG_SEO_Raporu.pdf',
      image:        { type: 'jpeg', quality: 0.98 },
      html2canvas:  { scale: 2, useCORS: true, letterRendering: true },
      jsPDF:        { unit: 'px', format: [794, 1123], orientation: 'portrait' }
    };
    
    html2pdf().set(opt).from(reportDiv).save();
  } else {
    alert("PDF kütüphanesi yüklenemedi. Lütfen sayfayı yenileyin.");
  }
}

async function saveChat() {
  if (!targetUrl || chatMessages.length === 0) return;
  try {
    if (copilotSaveBtn) copilotSaveBtn.innerHTML = 'Kaydediliyor...';
    await fetch('ai_seo/api/save_chat.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ chatId: currentChatId, url: targetUrl, type: targetType, messages: chatMessages, completedSteps: Array.from(completedSteps), reportData: reportData, fixedIssues: Array.from(fixedIssues) }) });
    loadHistory();
    if (copilotSaveBtn) copilotSaveBtn.innerHTML = '✅ Kaydedildi';
    setTimeout(() => {
      if (copilotSaveBtn) copilotSaveBtn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Kaydet`;
    }, 2000);
  } catch(e) {}
}

async function loadHistory() {
  if (!historyList) return;
  try {
    const res = await fetch('ai_seo/api/save_chat.php?t=' + Date.now());
    const data = await res.json();
    window.agChatHistory = data.history || [];
    if(typeof window.renderDashboard === 'function') window.renderDashboard();
    historyList.innerHTML = '';
    if (!data.history || data.history.length === 0) {
      historyList.innerHTML = '<p class="empty-note">Henüz geçmiş sohbet yok.</p>';
      return;
    }
    data.history.forEach(item => {
      const div = document.createElement('div');
      div.className = 'history-item';
      div.setAttribute('data-chat-id', item.chatId || item.id);
      div.style.padding = '12px';
      div.style.background = '#f9fafb';
      div.style.border = '1px solid var(--border)';
      div.style.borderRadius = '6px';
      div.style.display = 'flex';
      div.style.justifyContent = 'space-between';
      div.style.alignItems = 'center';
      
      const infoDiv = document.createElement('div');
      infoDiv.style.cursor = 'pointer';
      infoDiv.style.flex = '1';
      infoDiv.innerHTML = `<div style="font-weight:600; font-size:13px; color:#111;">${item.url}</div><div style="font-size:12px; color:#666; margin-top:4px;">${item.date} • Adım: ${item.completedSteps ? item.completedSteps.length : 0}/5</div>`;
      infoDiv.addEventListener('click', () => resetChat(item));
      
      const deleteBtn = document.createElement('button');
      deleteBtn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="#ef4444"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>`;
      deleteBtn.style.background = 'none';
      deleteBtn.style.border = 'none';
      deleteBtn.style.cursor = 'pointer';
      deleteBtn.style.padding = '8px';
      deleteBtn.addEventListener('click', async (e) => {
        e.stopPropagation();
        await fetch(`save_chat.php?id=${item.chatId}`, { method: 'DELETE' });
        loadHistory();
      });

      div.appendChild(infoDiv);
      div.appendChild(deleteBtn);
      historyList.appendChild(div);
    });
    renderAiSeoActions();
  if (typeof updateActiveHistoryItem === 'function') updateActiveHistoryItem();
  } catch(e) {}
}

if (copilotSaveBtn) {
  copilotSaveBtn.addEventListener('click', saveChat);
}

if (btnClearHistory) {
  btnClearHistory.addEventListener('click', async () => { await fetch('save_chat.php?id=all', { method: 'DELETE' }); loadHistory(); resetChat(null); });
}

if (copilotResetBtn) {
  copilotResetBtn.addEventListener('click', () => {
    try {
        const hasUserMessages = chatMessages.some(m => m.sender === 'user');
        const hasUnsavedMessages = hasUserMessages && currentState !== 'WAITING_FOR_URL' && !window._chatLoadedFromHistory;
        if (hasUnsavedMessages && !confirm('Mevcut sohbet kaydedilmedi. Yeni bir sohbet/URL başlatmak istediğinize emin misiniz?')) return;
        resetChat(null, true);
    } catch(err) {
        alert("Reset error: " + err.message);
    }
  });
}

for (let i = 1; i <= 6; i++) {
  const stepEl = document.getElementById(`cp-step-${i}`);
  if (stepEl) {
    stepEl.addEventListener('click', async () => {
      if (completedSteps.has(i)) {
        const msgs = Array.from(document.querySelectorAll('.chat-msg.user'));
        const targetMsg = msgs.find(m => m.innerText.includes(`${i}. Adım:`));
        if (targetMsg) {
          const scrollOffset = targetMsg.getBoundingClientRect().top - copilotChat.getBoundingClientRect().top + copilotChat.scrollTop;
          copilotChat.scrollTo({ top: scrollOffset - 10, behavior: 'smooth' });

          const observer = new IntersectionObserver((entries, obs) => {
            if (entries[0].isIntersecting) {
              obs.disconnect();
              const origBg = targetMsg.style.background;
              targetMsg.style.transition = 'background 0.4s ease';
              targetMsg.style.background = '#10b981';
              setTimeout(() => { targetMsg.style.background = origBg; }, 1000);
            }
          }, { threshold: 0.5 });

          observer.observe(targetMsg);
        }
      } else {
        if (!window.isAutoAnalyzing && !window.isAutoFixing && i <= 6) {
           currentStep = i;
           updateProgressUI(currentStep);
  window.renderDynamicQuickActions();
           await processAiSeoStep();
        }
      }
    });
    // We will set cursor pointer in CSS or dynamically below
  }
}

resetChat(null);
loadHistory();

function saveTodo(domain, type, text, msgId, chatId) {
   let todos = JSON.parse(localStorage.getItem('ag_seo_todos') || '{}');
   if (!todos[domain]) todos[domain] = { tech: [], text: [], general: [] };
   if (!todos[domain].general) todos[domain].general = [];
   
   // duplicate check
   let isDuplicate = todos[domain][type].some(t => t.text === text);
   if (!isDuplicate) {
       todos[domain][type].push({ id: Date.now() + Math.random().toString(), text, msgId, chatId, done: false });
       localStorage.setItem('ag_seo_todos', JSON.stringify(todos));
   }
}

window.removeTodo = function(domain, type, id) {
   let todos = JSON.parse(localStorage.getItem('ag_seo_todos') || '{}');
   if (todos[domain] && todos[domain][type]) {
       todos[domain][type] = todos[domain][type].filter(t => t.id !== id);
       localStorage.setItem('ag_seo_todos', JSON.stringify(todos));
       renderTodos();
   }
};

window.renderTodos = function() {
   const techContainer = document.getElementById('todo-list-tech');
   const textContainer = document.getElementById('todo-list-text');
   const generalContainer = document.getElementById('todo-list-general');
   if (!techContainer || !textContainer || !generalContainer) return;
   
   techContainer.innerHTML = '';
   textContainer.innerHTML = '';
   generalContainer.innerHTML = '';
   
   let todos = JSON.parse(localStorage.getItem('ag_seo_todos') || '{}');
   let hasTech = false;
   let hasText = false;
   let hasGeneral = false;

   for (const [domain, domainTodos] of Object.entries(todos)) {
       if (domainTodos.tech && domainTodos.tech.length > 0) {
           hasTech = true;
           const header = document.createElement('div');
           header.style.fontWeight = '600';
           header.style.fontSize = '12px';
           header.style.marginTop = '12px';
           header.style.marginBottom = '8px';
           header.style.color = '#475569';
           header.innerText = '🌐 ' + domain;
           techContainer.appendChild(header);

           domainTodos.tech.forEach(item => {
               const div = document.createElement('div');
               div.className = 'todo-item';
               div.style.padding = '10px';
               div.style.background = '#fff';
               div.style.border = '1px solid var(--border)';
               div.style.borderRadius = '6px';
               div.style.display = 'flex';
               div.style.alignItems = 'flex-start';
               div.style.gap = '8px';
               div.style.marginBottom = '6px';
               div.innerHTML = `
                   <input type="checkbox" style="margin-top:3px; cursor:pointer;" onclick="removeTodo('${domain}', 'tech', '${item.id}')">
                   <div style="flex:1;">
                      <span style="display:block; font-size:13px; line-height:1.5; color:#1e293b;">${item.text}</span>
                      <a href="#" data-jump="${item.msgId}" data-chat="${item.chatId || ''}" style="display:inline-block; font-size:11px; margin-top:4px; color:#2563eb; font-weight:600; text-decoration:underline;">Sohbette Gör ➔</a>
                   </div>
               `;
               techContainer.appendChild(div);
           });
       }
       
       if (domainTodos.text && domainTodos.text.length > 0) {
           hasText = true;
           const header = document.createElement('div');
           header.style.fontWeight = '600';
           header.style.fontSize = '12px';
           header.style.marginTop = '12px';
           header.style.marginBottom = '8px';
           header.style.color = '#475569';
           header.innerText = '🌐 ' + domain;
           textContainer.appendChild(header);

           domainTodos.text.forEach(item => {
               const div = document.createElement('div');
               div.className = 'todo-item';
               div.style.padding = '10px';
               div.style.background = '#fff';
               div.style.border = '1px solid var(--border)';
               div.style.borderRadius = '6px';
               div.style.display = 'flex';
               div.style.alignItems = 'flex-start';
               div.style.gap = '8px';
               div.style.marginBottom = '6px';
               div.innerHTML = `
                   <input type="checkbox" style="margin-top:3px; cursor:pointer;" onclick="removeTodo('${domain}', 'text', '${item.id}')">
                   <div style="flex:1;">
                      <span style="display:block; font-size:13px; line-height:1.5; color:#1e293b;">${item.text}</span>
                      <a href="#" data-jump="${item.msgId}" data-chat="${item.chatId || ''}" style="display:inline-block; font-size:11px; margin-top:4px; color:#2563eb; font-weight:600; text-decoration:underline;">Sohbette Gör ➔</a>
                   </div>
               `;
               textContainer.appendChild(div);
           });
       }
       
       if (domainTodos.general && domainTodos.general.length > 0) {
           hasGeneral = true;
           const header = document.createElement('div');
           header.style.fontWeight = '600';
           header.style.fontSize = '12px';
           header.style.marginTop = '12px';
           header.style.marginBottom = '8px';
           header.style.color = '#475569';
           header.innerText = '🌐 ' + domain;
           generalContainer.appendChild(header);

           domainTodos.general.forEach(item => {
               const div = document.createElement('div');
               div.className = 'todo-item';
               div.style.padding = '10px';
               div.style.background = '#fff';
               div.style.border = '1px solid var(--border)';
               div.style.borderRadius = '6px';
               div.style.display = 'flex';
               div.style.alignItems = 'flex-start';
               div.style.gap = '8px';
               div.style.marginBottom = '6px';
               div.innerHTML = `
                   <input type="checkbox" style="margin-top:3px; cursor:pointer;" onclick="removeTodo('${domain}', 'general', '${item.id}')">
                   <div style="flex:1;">
                      <span style="display:block; font-size:13px; line-height:1.5; color:#1e293b;">${item.text}</span>
                      <a href="#" data-jump="${item.msgId}" data-chat="${item.chatId || ''}" style="display:inline-block; font-size:11px; margin-top:4px; color:#2563eb; font-weight:600; text-decoration:underline;">Sohbette Gör ➔</a>
                   </div>
               `;
               generalContainer.appendChild(div);
           });
       }
   }

   if (!hasTech) techContainer.innerHTML = '<p class="empty-note" style="font-size:12px; color:var(--muted-2);">Henüz teknik eksiklik bulunamadı.</p>';
   if (!hasText) textContainer.innerHTML = '<p class="empty-note" style="font-size:12px; color:var(--muted-2);">Henüz metin eksikliği bulunamadı.</p>';
   if (!hasGeneral) generalContainer.innerHTML = '<p class="empty-note" style="font-size:12px; color:var(--muted-2);">Henüz genel görev bulunamadı.</p>';

   // Bind jump links
   document.querySelectorAll('[data-jump]').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const targetId = e.currentTarget.getAttribute('data-jump');
        const targetChat = e.currentTarget.getAttribute('data-chat');
        
        if (!targetChat) {
           alert('Bu görev eski bir kayda ait olduğu için orijinal sohbeti bulunamıyor. Lütfen listeden temizleyin veya silin.');
           return;
        }

        if (typeof currentChatId !== 'undefined' && targetChat !== currentChatId) {
           const historyDiv = document.querySelector(`.history-item[data-chat-id="${targetChat}"]`);
           if (historyDiv) {
               historyDiv.firstChild.click();
           } else {
               alert('Bu analiz geçmiş sohbetlerde bulunamadı. Sohbet geçmişten silinmiş veya henüz kaydedilmemiş olabilir. Lütfen 3. sekmedeki Kaydet butonuna basın.');
               return;
           }
        }

        document.querySelector('.nav__item[data-tab="3"]').click();
        setTimeout(() => {
          const targetNode = document.getElementById(targetId);
          if (targetNode) {
             const chatContainer = document.getElementById('copilot-chat-messages-container');
             if (chatContainer) {
                 const scrollOffset = targetNode.getBoundingClientRect().top - chatContainer.getBoundingClientRect().top + chatContainer.scrollTop;
                 chatContainer.scrollTo({ top: scrollOffset - 10, behavior: 'smooth' });
             }
             targetNode.style.transition = 'background 0.3s ease';
             targetNode.style.background = '#fef08a';
             setTimeout(() => { targetNode.style.background = ''; }, 2000);
          }
        }, 300); // 300ms wait for chat rendering if it was just loaded
      });
   });
};

window.extractTodosAndSend = function() {
  let currentDomain = 'Bilinmeyen Site';
  if (typeof targetUrl !== 'undefined' && targetUrl) {
     try { currentDomain = new URL(targetUrl).hostname; } catch(e) { currentDomain = targetUrl; }
  } else {
     const storedDomain = document.getElementById('sidebar-client-domain')?.innerText;
     if (storedDomain) currentDomain = storedDomain;
  }
  
  let activeChatId = (typeof currentChatId !== 'undefined') ? currentChatId : '';

  document.querySelectorAll('.chat-msg.ai').forEach(msgNode => {
    let textContent = msgNode.innerHTML;
    
    let parts = textContent.split(/(?=🚨|✍️|📌)/);
    
    parts.forEach(part => {
        let type = null;
        if (part.includes('🚨')) type = 'tech';
        else if (part.includes('✍️')) type = 'text';
        else if (part.includes('📌')) type = 'general';

        if (type) {
            let safeText = part.replace(/<([a-z0-9]+)[^>]*>(.*?)<\/\1>/gi, '&lt;$1&gt;$2&lt;/$1&gt;');
            safeText = safeText.replace(/<([a-z0-9]+)[^>]*>/gi, '&lt;$1&gt;');
            safeText = safeText.replace(/&lt;p&gt;|&lt;br&gt;|&lt;\/p&gt;|&lt;ul&gt;|&lt;\/ul&gt;|&lt;ol&gt;|&lt;\/ol&gt;|&lt;li&gt;|&lt;\/li&gt;|&lt;strong&gt;|&lt;\/strong&gt;|&lt;em&gt;|&lt;\/em&gt;/gi, ' ');
            
            // Kural değişikliği: 🚨 [TEKNİK - Modül: SSS] -> [SSS] şekline dönüştür
            safeText = safeText.replace(/^(🚨|✍️|📌)\s*\[(?:TEKNİK|METİN|GENEL)\s*-\s*Modül:\s*([^\]]+)\]\s*[:-]?\s*/i, '[$2] ');
            // Fallback for old formatting or if AI missed the module format
            safeText = safeText.replace(/(🚨|✍️|📌)?\s*\[[^\]]*SEO[^\]]*\]\s*[:-]?\s*/gi, '');
            safeText = safeText.replace(/^(🚨|✍️|📌)\s*/g, '');
            
            safeText = safeText.replace(/\s+/g, ' ').trim();
            if (safeText.length > 180) {
                safeText = safeText.substring(0, 180).replace(/\s+\S*$/, '') + '...';
            }
            if (safeText.length > 15) saveTodo(currentDomain, type, safeText, msgNode.id, activeChatId);
        }
    });
  });

  renderTodos();
  document.querySelector('.nav__item[data-tab="4"]').click();
};

// Bind clear all
const btnClearTodos = document.getElementById('btn-clear-todos');
if (btnClearTodos) {
  btnClearTodos.addEventListener('click', () => {
     if (confirm('Tüm yapılacaklar listesi silinecek. Emin misiniz?')) {
         localStorage.removeItem('ag_seo_todos');
         renderTodos();
     }
  });
}

// Call initial render on load
renderTodos();

window.updateActiveHistoryItem = function() {
   document.querySelectorAll('.history-item').forEach(el => {
       el.style.background = '#f9fafb';
       el.style.borderColor = 'var(--border)';
   });
   if (typeof currentChatId !== 'undefined' && currentChatId) {
       const activeEl = document.querySelector(`.history-item[data-chat-id="${currentChatId}"]`);
       if (activeEl) {
           activeEl.style.background = '#e2e8f0';
           activeEl.style.borderColor = '#475569';
       }
   }
};

// initial call
updateActiveHistoryItem();
});


// ==============================================
// BATTLE MODE (MODAL LOGIC)
// ==============================================
// ==============================================
// BATTLE MODE (MODAL LOGIC) - EVENT DELEGATION
// ==============================================
document.addEventListener('click', async (e) => {
  // 1. Open Modal
  if (e.target && (e.target.id === 'btn-open-battle-mode' || e.target.closest('#btn-open-battle-mode'))) {
      const battleModal = document.getElementById('battle-modal');
      if (battleModal) {
          battleModal.style.display = 'flex';
          const battleTarget = document.getElementById('battle-target-url');
          if (window.fetchedData && window.fetchedData.url) {
              battleTarget.value = window.fetchedData.url;
          } else if (document.getElementById('copilot-text-input')) {
              const tv = document.getElementById('copilot-text-input').value;
              if(tv && tv.startsWith('http')) battleTarget.value = tv;
          }
      }
  }
  
  // 2. Close Modal
  if (e.target && (e.target.id === 'battle-close' || e.target.closest('#battle-close'))) {
      const battleModal = document.getElementById('battle-modal');
      if (battleModal) battleModal.style.display = 'none';
  }
  
  // 3. Start Battle
  if (e.target && (e.target.id === 'battle-start-btn' || e.target.closest('#battle-start-btn'))) {
      const battleStart = document.getElementById('battle-start-btn');
      const battleTarget = document.getElementById('battle-target-url');
      const battleComp = document.getElementById('battle-comp-url');
      const battleResults = document.getElementById('battle-results');

      const tUrl = battleTarget.value.trim();
      const cUrl = battleComp.value.trim();

      if (!tUrl || !cUrl) {
          alert('Lütfen hem hedef hem rakip URL\'yi girin!');
          return;
      }

      battleStart.disabled = true;
      battleResults.innerHTML = '<div style="text-align:center; margin-top:40px; color:#64748b;">Analiz ediliyor, lütfen bekleyin... (Veriler çekilip AI\'a gönderiliyor)</div>';

      try {
          // Fetch both URLs
          const [resT, resC] = await Promise.all([
              fetch(`fetch_url.php?url=${encodeURIComponent(tUrl)}`),
              fetch(`fetch_url.php?url=${encodeURIComponent(cUrl)}`)
          ]);

          const dataT = await resT.json();
          const dataC = await resC.json();

          if (dataT.error) throw new Error("Hedef URL Hatası: " + dataT.error);
          if (dataC.error) throw new Error("Rakip URL Hatası: " + dataC.error);

          // Build Prompt - Rakip Savaş Modu 2. PDF Uyumu
          const prompt = `GERÇEK ZAMANLI RAKİP SAVAŞ MODU (BATTLE MODE) AKTİF!

Site A:
${(dataT.text || '').substring(0, 10000)}

Site B (Rakip):
${(dataC.text || '').substring(0, 10000)}

Site A'nın rakibine göre içerik derinliği, SEO kalitesi ve E-E-A-T sinyalleri açısından eksiklerini JSON olarak analiz et. Ayrıca JSON içine "site_a_skorlari": {"icerik": 60, "seo": 65, "eeat": 50} ve "site_b_skorlari": {"icerik": 85, "seo": 90, "eeat": 88} şeklinde iki sitenin 100 üzerinden tahmini skorlarını da ekle.`;

          const aiRes = await fetch('form_submit.php', { 
              method: 'POST', 
              headers: { 'Content-Type': 'application/json' }, 
              body: JSON.stringify({ contents: [{ parts: [{ text: prompt }] }], generationConfig: { temperature: 0.2 } }) 
          });

          const aiResult = await aiRes.json();
          if (aiResult.error) throw new Error(aiResult.error.message || aiResult.error);

          let aiText = aiResult.candidates[0].content.parts[0].text;
          let htmlText = '';
          try {
              // Try to extract JSON if AI wrapped it in markdown code blocks
              let cleanJsonStr = aiText.replace(/```json/g, '').replace(/```/g, '').trim();
              // Sometimes AI returns markdown before/after the JSON. Just extract the first { ... } block.
              const jsonMatch = cleanJsonStr.match(/\{[\s\S]*\}/);
              if (jsonMatch) {
                  const parsedData = JSON.parse(jsonMatch[0]);
                  const analysis = parsedData.battle_mode_analysis || parsedData;

                  if (typeof Chart !== 'undefined') {
                      document.getElementById('battle-chart-container').style.display = 'block';
                      const ctxB = document.getElementById('chart-battle');
                      if (ctxB) {
                          if (battleChart) battleChart.destroy();
                          battleChart = new Chart(ctxB, {
                              type: 'bar',
                              data: {
                                  labels: ['İçerik Derinliği', 'SEO Kalitesi', 'E-E-A-T'],
                                  datasets: [
                                      { label: 'Senin Siten', data: [analysis.site_a_skorlari?.icerik || 60, analysis.site_a_skorlari?.seo || 65, analysis.site_a_skorlari?.eeat || 50], backgroundColor: '#3b82f6' },
                                      { label: 'Rakip', data: [analysis.site_b_skorlari?.icerik || 85, analysis.site_b_skorlari?.seo || 90, analysis.site_b_skorlari?.eeat || 88], backgroundColor: '#ef4444' }
                                  ]
                              }
                          });
                      }
                  }
                  
                  const formatArray = (arr) => arr && arr.length ? `<ul>${arr.map(item => `<li>${item}</li>`).join('')}</ul>` : 'Veri yok.';
                  
                  let eksiklerHtml = '';
                  if (analysis.site_a_eksikleri) {
                      eksiklerHtml = `
                          <div class="compare-cols" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; margin-top:20px;">
                              <div class="col-box" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px;">
                                  <h4 style="color:#2563eb; font-size:14px; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">📚 İçerik Derinliği</h4>
                                  <div style="font-size:13px; color:#475569; line-height:1.6;">${typeof marked !== 'undefined' ? marked.parse(analysis.site_a_eksikleri.icerik_derinligi.join('\n\n')) : formatArray(analysis.site_a_eksikleri.icerik_derinligi)}</div>
                              </div>
                              <div class="col-box" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px;">
                                  <h4 style="color:#10b981; font-size:14px; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">🎯 SEO Kalitesi</h4>
                                  <div style="font-size:13px; color:#475569; line-height:1.6;">${typeof marked !== 'undefined' ? marked.parse(analysis.site_a_eksikleri.seo_kalitesi.join('\n\n')) : formatArray(analysis.site_a_eksikleri.seo_kalitesi)}</div>
                              </div>
                              <div class="col-box" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px;">
                                  <h4 style="color:#f59e0b; font-size:14px; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">🛡️ E-E-A-T Sinyalleri</h4>
                                  <div style="font-size:13px; color:#475569; line-height:1.6;">${typeof marked !== 'undefined' ? marked.parse(analysis.site_a_eksikleri.eeat_sinyalleri.join('\n\n')) : formatArray(analysis.site_a_eksikleri.eeat_sinyalleri)}</div>
                              </div>
                          </div>
                      `;
                  }
                  
                  htmlText = `
                      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px;">
                          <h3 style="font-size:16px; color:#1e293b; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                              <span style="background:#dc2626; color:white; padding:4px 8px; border-radius:6px; font-size:12px; font-weight:bold;">RAKİP ÜSTÜNLÜĞÜ</span> Neden Rakip Daha İyi?
                          </h3>
                          <div style="font-size:14px; color:#334155; line-height:1.7;">
                              ${typeof marked !== 'undefined' ? marked.parse(analysis.rakip_ustunluk_nedenleri || '') : analysis.rakip_ustunluk_nedenleri}
                          </div>
                          
                          ${eksiklerHtml}
                      </div>
                  `;
              } else {
                  throw new Error("JSON formatı bulunamadı.");
              }
          } catch(e) {
              // Fallback if not JSON or parsing failed
              console.warn("Battle mode JSON parse failed, falling back to markdown", e);
              htmlText = typeof marked !== 'undefined' ? marked.parse(aiText) : aiText;
          }

          battleResults.innerHTML = htmlText;

      } catch (err) {
          battleResults.innerHTML = '<div style="color:#ef4444; font-weight:bold;">Hata: ' + err.message + '</div>';
      } finally {
          battleStart.disabled = false;
      }
  }
});
window.openLlmsGenerator = function() {
  if (typeof switchTab === 'function') {
      switchTab('tab4');
      const t1 = document.getElementById('t1-content');
      if (t1 && window.targetUrl) t1.value = window.targetUrl;
      const btn = document.getElementById('t4-llmstxt-btn');
      if (btn) btn.click();
  } else {
      alert('Master llms.txt oluşturmak için paneldeki ilgili sekmeyi kullanın.');
  }
};

document.addEventListener('DOMContentLoaded', () => {
    const btnReturn = document.getElementById('btn-return-dashboard');
    if (btnReturn) {
        btnReturn.addEventListener('click', () => {
            const dbView = document.getElementById('ai-seo-dashboard-view');
            const actionView = document.getElementById('copilot-action-view');
            if(dbView && actionView) {
                actionView.style.display = 'none';
                dbView.style.display = 'block';
            }
        });
    }
});
window.startFreshAnalysis = function() {
  try {
      const dbView = document.getElementById('ai-seo-dashboard-view');
      const actionView = document.getElementById('copilot-action-view');
      if (dbView) dbView.style.display = 'none';
      if (actionView) actionView.style.display = 'flex';
      
      if (typeof window._forceResetChat === 'function') {
          window._forceResetChat(null, true);
      } else {
          alert('Error: _forceResetChat is not a function');
      }
  } catch(e) {
      alert("startFreshAnalysis error: " + e.message);
      console.error("startFreshAnalysis error:", e);
  }
};
window.startNewAnalysisFromDashboard = window.startFreshAnalysis;