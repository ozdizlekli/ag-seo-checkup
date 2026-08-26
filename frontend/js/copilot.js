document.addEventListener('DOMContentLoaded', () => {
  const copilotChat = document.getElementById('copilot-chat');
  const copilotResetBtn = document.getElementById('copilot-reset-btn');
  const copilotSaveBtn = document.getElementById('copilot-manual-save-btn');
  const copilotProgress = document.getElementById('copilot-progress');
  const copilotActions = document.getElementById('copilot-actions');
  const copilotInputArea = document.getElementById('copilot-input-area');
  const copilotTextInput = document.getElementById('copilot-text-input');
  const copilotSendBtn = document.getElementById('copilot-send-btn');
  const historyList = document.getElementById('copilot-history-list');
  const btnClearHistory = document.getElementById('btn-clear-history');
  
  if (!copilotChat) return;

  const aiSeoSteps = ["", "İş Bağlamı ve E-E-A-T", "Kullanıcı Soruları ve Etkililik", "Rakip Öngörüleri", "Yapay Zeka Güven Puanı", "Okunabilirlik ve Optimize İçerik", "Bütünsel Entegrasyon Zinciri"];

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
    if (indicator) indicator.remove();
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
                const chatContainer = document.getElementById('copilot-chat');
                if (chatContainer) {
                    const scrollOffset = targetMsg.getBoundingClientRect().top - chatContainer.getBoundingClientRect().top + chatContainer.scrollTop;
                    chatContainer.scrollTo({ top: scrollOffset - 10, behavior: 'smooth' });
                }
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
      "Az önceki 1. Adım analizinde bulduğun eksikleri gidermek için siteme doğrudan ekleyebileceğim zenginleştirilmiş 'Hakkımızda / Kurumsal' metni ve E-E-A-T sinyallerini artıracak öneriler üret. " + kural,
      "Az önceki 2. Adım analizine dayanarak, kullanıcıların ve yapay zekanın en çok aradığı soruları kapsayan, siteme kopyalayıp yapıştırabileceğim 5 adet 'Doğrudan Yanıt Odaklı SSS (FAQ)' metni ve bu soruların JSON-LD Schema kodunu hazırla. " + kural,
      "Az önceki 3. Adım analizinde rakiplerimde olup bende olmayan içerik açıklarını kapatmak için, sitemde kullanabileceğim iddialı bir değer teklifi (Value Proposition) metni ve hizmetleri öne çıkaran 2 paragraflık ikna edici bir içerik yaz. " + kural,
      "Az önceki 4. Adım analizindeki güven eksikliklerini gidermek için, siteme ekleyebileceğim güven veren istatistiksel ifadeler, sertifika/ödül bildirimleri ve yapay zekanın 'Otorite' puanımı artırmasını sağlayacak metin blokları oluştur. " + kural,
      "Az önceki 5. Adım analizindeki teknik hataları ve Schema eksikliklerini tamamen gider. Sadece Schema değil, Hız, Karakter Kodlaması, Semantik HTML gibi AI'ın okumasını zorlaştıran ne varsa düzeltme kodu veya talimatı ver. Ayrıca Schema koduna 'knowsAbout' veya 'mentions' gibi bağlamsal etiketler ekle. " + kural,
      "ÖNEMLİ: Sen bir Entegrasyon Şefisin. Önceki 5 adımda üretilen metinleri veya kodları SAKIN tekrar etme! Senin tek görevin 'Bağlantı Noktalarını (Zincirleri)' göstermek. Hangi metinle hangi teknik SEO hamlesinin (Hız, Şema, Semantik HTML vb.) aynı anda yapılması gerektiğini söyle. " + kural
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
      let htmlText = typeof marked !== 'undefined' ? marked.parse(aiText) : aiText;
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

  function resetChat(loadFromHistory = null) {
    if (loadFromHistory) {
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
      if (msgContainer) msgContainer.innerHTML = '';
      chatMessages.forEach(msg => { addMessage(msg.text, msg.sender, msg.isHtml, false); });
      
      copilotInputArea.style.display = "none"; const cqa = document.getElementById("copilot-quick-actions"); if(cqa) cqa.style.display = "none";
      renderAiSeoActions();
      
      if (copilotSaveBtn) {
        copilotSaveBtn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Kaydet`;
      }
      renderAiSeoActions();
    if (typeof updateActiveHistoryItem === 'function') updateActiveHistoryItem();
      return;
    }

    currentChatId = Date.now().toString();
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
    if (msgContainer) msgContainer.style.display = 'block';
    


    if (msgContainer) msgContainer.innerHTML = '';
    addMessage(`👋 Merhaba! Ben <strong>GEO SEO Asistanı</strong>.<br><br>Web siteni tarayıp yapay zeka (LLM) arama motorları için optimize edelim. Lütfen analiz etmemi istediğin sayfanın <strong>URL'sini</strong> aşağıya yaz.`, 'ai', true, false);
    
    copilotActions.innerHTML = '';
    copilotInputArea.style.display = "flex"; const cqa = document.getElementById("copilot-quick-actions"); if(cqa) cqa.style.display = "flex";
    copilotTextInput.value = '';
    copilotTextInput.placeholder = 'Örn: https://www.site.com/hizmet';
    copilotTextInput.focus();
    
    if (copilotSaveBtn) {
      copilotSaveBtn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Kaydet`;
    }
    renderAiSeoActions();
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
      
      try {
        const res = await fetch('form_submit.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ contents: [{ parts: [{ text: p }] }], generationConfig: { temperature: 0.5 } }) });
        const result = await res.json();
        removeTypingIndicator();
        if (result.error) { addMessage(`Hata: ${result.error.message || result.error}`, 'ai'); return; }
        
        let aiText = result.candidates[0].content.parts[0].text;
        let htmlText = typeof marked !== 'undefined' ? marked.parse(aiText) : aiText;
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
    let nextStep = 1;
    while(completedSteps.has(nextStep) && nextStep <= 5) { nextStep++; }
    
    let actionsHtml = '';
    
    if (nextStep <= 5) {
      currentStep = nextStep;
      updateProgressUI(currentStep);
      
      const analyzeText = completedSteps.size === 0 ? "⚡ Tüm Siteyi Analiz Et" : "⚡ Kalan Adımları Analiz Et";
      const fixText = fixedIssues.size === 0 ? "🔧 Tüm Eksikleri Gider" : "🔧 Kalan Eksikleri Gider";

      actionsHtml += `<div style="display: flex; width: 100%; gap: 8px; margin-bottom: 10px;">
         <button class="btn btn--primary" id="btn-analyze-all" style="flex: 1; font-size: 11.5px; padding: 8px; font-weight: 600;">${analyzeText}</button>
         <button class="btn btn--secondary" id="btn-fix-all" style="flex: 1; font-size: 11.5px; padding: 8px; font-weight: 600;">${fixText}</button>
      </div>`;

    } else if (completedSteps.size >= 5 && fixedIssues.size < 6) {
      const analyzeText = "⚡ Kalan Adımları Analiz Et";
      const fixText = fixedIssues.size === 0 ? "🔧 Tüm Eksikleri Gider" : "🔧 Kalan Eksikleri Gider";

      actionsHtml += `<div style="display: flex; width: 100%; gap: 8px; margin-bottom: 10px;">
         <button class="btn btn--secondary" style="flex: 1; font-size: 11.5px; padding: 8px; font-weight: 600; opacity: 0.5; cursor: not-allowed;" disabled>${analyzeText}</button>
         <button class="btn btn--primary" id="btn-fix-all" style="flex: 1; font-size: 11.5px; padding: 8px; font-weight: 600; background: #2563eb; color: white; border: none;">${fixText}</button>
      </div>`;
    }

    const isCompleted = completedSteps.size === 6 && fixedIssues.size === 6;

    copilotActions.innerHTML = actionsHtml;

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
    if (document.getElementById('btn-analyze-all')) {
      document.getElementById('btn-analyze-all').addEventListener('click', async () => {
        window.isAutoAnalyzing = true;
        await runAutoAnalysis();
      });
    }

    if (document.getElementById('btn-fix-all')) {
      document.getElementById('btn-fix-all').addEventListener('click', async () => {
        window.isAutoFixing = true;
        await runAutoFixes();
      });
    }
    
    // PDF listener moved to topBtn

    currentState = 'CHAT_MODE';
    copilotInputArea.style.display = "flex"; const cqa = document.getElementById("copilot-quick-actions"); if(cqa) cqa.style.display = "flex";
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
      p += `Aşağıdaki başlıkları kullanarak sitenin İş Bağlamını Markdown formatında analiz et:
**Alan Adı İş Bağlamı**
* BU ALAN ADI NE HAKKINDADIR?
* HEDEF KİTLE
* SEKTÖR NİŞİ
* ANAHTAR VARLIKLAR (ENTITIES): (Yapay zekanın Bilgi Grafiği / Knowledge Graph için bu sitenin temsil ettiği ve odaklanması gereken en önemli 3-4 kavram/varlık nedir?)
Buna ek olarak E-E-A-T (Deneyim, Uzmanlık, Otoriterlik, Güvenilirlik) kurallarına göre bir değerlendirme yaz.`;
    } 
    else if (step === 2) {
      p += `Aşağıdaki başlıkları kullanarak Markdown formatında bir etkililik raporu oluştur:
**Yapay Zeka İçerik Etkililik Raporu**
* GENEL BAKIŞ
* ÖNE ÇIKAN KULLANICI SORULARI (En az 5 soru listele. Her bir soru için sitenin ilgili soruyu yanıtlama gücünü (+/- yönleriyle) ve 10 üzerinden puanını (Skor) yaz.)
* İÇERİK FIRSATLARI (Uygulanabilir aksiyon tavsiyelerini 5 maddelik liste yap.)`;
    } 
    else if (step === 3) {
      p += `Aşağıdaki başlıkları kullanarak rakipleri analiz et:
**Rakip İçerik Öngörüleri**
* GENEL BAKIŞ
* Aynı sektördeki en iyi bilinen en az 3 rakibi (örn. Magna Dijital, Webtures, Zeo Agency vb. veya global alternatifler) ele al. 
* Her rakip için tahmini İÇERİK GÜVEN PUANI (%), güçlü (✔) ve zayıf (▲) yönlerini listele.
Sitenin bu rakiplere kıyasla hangi açıkları kapatması gerektiğini vurgula.`;
    } 
    else if (step === 4) {
      p += `Aşağıdaki başlıkları kullanarak LLM içerik güven metriklerini yüzdelik (%) olarak belirle:
Altına bu yüzdelikleri açıklayan bir Genel Değerlendirme Özeti ve "İÇERİK GÜVENİ İYİLEŞTİRMELERİ" yaz.`;
    } 
    else if (step === 5) {
      p += `Okunabilirlik ve UX Analizi yap.
Sitede pasif cümleler çok mu? LLM'ler rahat anlıyor mu? 
Son olarak, önceki 4 adımda çıkardığın tüm analizleri (İş bağlamı, kullanıcı soruları, güven puanları, eksiklikler) harmanlayarak, LLM (SGE) dostu kusursuz hale getirilmiş YENİ BİR ÖRNEK METİN ve META AÇIKLAMASI sun. (Markdown formatında)`;
    }
    else if (step === 6) {
      p += `ÖNEMLİ: Sen bir Bütünsel Entegrasyon Şefisin. Önceki 5 adımdaki Metin ve Teknik yapıları birbirine nasıl bağlamamız gerektiğini analiz et. Hangi içeriğin, hangi şemayla veya hangi linkleme kurgusuyla birlikte canlıya alınması gerektiğini açıkla. Hiçbir içerik veya kod üretme, sadece bu organik bağı analiz et.`;
    }

    try {
      const res = await fetch('form_submit.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ contents: [{ parts: [{ text: p }] }], generationConfig: { temperature: 0.2 } }) });
      const result = await res.json();
      removeTypingIndicator();
      if (result.error) { addMessage(`Hata: ${result.error.message || result.error}`, 'ai'); copilotActions.style.display = 'flex'; return; }
      
      let aiText = result.candidates[0].content.parts[0].text;
      let htmlText = typeof marked !== 'undefined' ? marked.parse(aiText) : aiText;
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
      await fetch('save_chat.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ chatId: currentChatId, url: targetUrl, type: targetType, messages: chatMessages, completedSteps: Array.from(completedSteps), reportData: reportData, fixedIssues: Array.from(fixedIssues) }) });
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
      const res = await fetch('save_chat.php?t=' + Date.now());
      const data = await res.json();
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

  if (copilotResetBtn) { copilotResetBtn.addEventListener('click', () => resetChat(null)); }

  for (let i = 1; i <= 5; i++) {
    const stepEl = document.getElementById(`cp-step-${i}`);
    if (stepEl) {
      stepEl.addEventListener('click', async () => {
        if (completedSteps.has(i)) {
          const msgs = Array.from(document.querySelectorAll('.chat-msg.user'));
          const targetMsg = msgs.find(m => m.innerText.includes(`${i}. Adım:`));
          if (targetMsg) {
            const chatContainer = document.getElementById('copilot-chat');
            if (chatContainer) {
                const scrollOffset = targetMsg.getBoundingClientRect().top - chatContainer.getBoundingClientRect().top + chatContainer.scrollTop;
                chatContainer.scrollTo({ top: scrollOffset - 10, behavior: 'smooth' });
            }
            
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
          if (!window.isAutoAnalyzing && !window.isAutoFixing && i <= 5) {
             currentStep = i;
             updateProgressUI(currentStep);
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
               const chatContainer = document.getElementById('copilot-chat');
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
             activeEl.style.background = '#eef2ff';
             activeEl.style.borderColor = '#6366f1';
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

Site A'nın rakibine göre içerik derinliği, SEO kalitesi ve E-E-A-T sinyalleri açısından eksiklerini ve rakibin neden daha iyi olduğunu JSON olarak analiz et.`;

            const aiRes = await fetch('form_submit.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/json' }, 
                body: JSON.stringify({ contents: [{ parts: [{ text: prompt }] }], generationConfig: { temperature: 0.2 } }) 
            });

            const aiResult = await aiRes.json();
            if (aiResult.error) throw new Error(aiResult.error.message || aiResult.error);

            let aiText = aiResult.candidates[0].content.parts[0].text;
            let htmlText = typeof marked !== 'undefined' ? marked.parse(aiText) : aiText;

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
