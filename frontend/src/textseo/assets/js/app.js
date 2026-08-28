document.addEventListener('DOMContentLoaded', function() {

    // ===== SABİTLER =====
    const STORAGE_KEY = 'seo_analysis_history';

    // ===== DOM REFERANSLARI =====
    const homeView = document.getElementById('homeView');
    const resultsView = document.getElementById('resultsView');
    
    const form = document.getElementById('analyzeForm');
    const urlInput = document.getElementById('urlInput');
    const analyzeBtn = document.getElementById('analyzeBtn');
    
    const loadingSection = document.getElementById('loadingSection');
    const loadingMessage = document.getElementById('loadingMessage');
    const errorSection = document.getElementById('errorSection');
    const errorMessage = document.getElementById('errorMessage');
    
    const newAnalysisBtn = document.getElementById('newAnalysisBtn');
    const analyzedUrlText = document.getElementById('analyzedUrlText');
    const analyzedUrlContainer = document.getElementById('analyzedUrlContainer');
    const analyzedUrlTooltip = document.getElementById('analyzedUrlTooltip');

    // Tooltip'i sadece metin sığmadığında (truncated) göster
    analyzedUrlContainer.addEventListener('mouseenter', function() {
        if (analyzedUrlText.scrollWidth > analyzedUrlText.clientWidth) {
            analyzedUrlTooltip.style.visibility = 'visible';
            analyzedUrlTooltip.style.opacity = '1';
        }
    });

    analyzedUrlContainer.addEventListener('mouseleave', function() {
        analyzedUrlTooltip.style.visibility = 'hidden';
        analyzedUrlTooltip.style.opacity = '0';
    });

    // Geçmiş Referansları
    const historyList = document.getElementById('historyList');
    const clearHistoryBtn = document.getElementById('clearHistoryBtn');

    // Debug Referansları
    const debugBtn = document.getElementById('debugBtn');
    const debugModalOverlay = document.getElementById('debugModalOverlay');
    const closeDebugBtn = document.getElementById('closeDebugBtn');
    const debugContent = document.getElementById('debugContent');
    let currentDebugTrace = [];

    debugBtn.addEventListener('click', function() {
        debugContent.innerHTML = ''; // Temizle
        if (currentDebugTrace && currentDebugTrace.length > 0) {
            currentDebugTrace.forEach((trace, index) => {
                const traceHtml = `
                    <div style="margin-bottom: 25px; border: 1px solid #ddd; padding: 10px; border-radius: 5px; background: #fff;">
                        <h3 style="margin-top:0; color:#1a73e8;">#${index + 1} - ${trace.step}</h3>
                        <strong>Sistem Talimatı (System Instruction):</strong>
                        <pre style="background:#f1f3f4; padding:8px; border-radius:4px; margin:5px 0 15px;">${escapeHTML(trace.system)}</pre>
                        <strong>Kullanıcı İsteği (Prompt):</strong>
                        <pre style="background:#f1f3f4; padding:8px; border-radius:4px; margin:5px 0 15px;">${escapeHTML(trace.prompt)}</pre>
                        <strong>Yapay Zeka Yanıtı (Response):</strong>
                        <pre style="background:#e8f0fe; padding:8px; border-radius:4px; margin:5px 0; color:#174ea6;">${escapeHTML(trace.response)}</pre>
                    </div>
                `;
                debugContent.innerHTML += traceHtml;
            });
        } else {
            debugContent.innerHTML = '<i>Hata ayıklama (debug) verisi bulunamadı.</i>';
        }
        debugModalOverlay.style.display = 'flex';
    });

    closeDebugBtn.addEventListener('click', function() {
        debugModalOverlay.style.display = 'none';
    });

    // Toggle Referansları
    const toggleMissingTopicsBtn = document.getElementById('toggleMissingTopicsBtn');
    const missingTopicsContainer = document.getElementById('missingTopicsContainer');
    const missingTopicsList = document.getElementById('missingTopicsList');
    
    const toggleTechReportBtn = document.getElementById('toggleTechReportBtn');
    const techReportContainer = document.getElementById('techReportContainer');

    toggleMissingTopicsBtn.addEventListener('click', function() {
        if (missingTopicsContainer.style.display === 'none') {
            missingTopicsContainer.style.display = 'block';
        } else {
            missingTopicsContainer.style.display = 'none';
        }
    });

    toggleTechReportBtn.addEventListener('click', function() {
        if (techReportContainer.style.display === 'none') {
            techReportContainer.style.display = 'block';
        } else {
            techReportContainer.style.display = 'none';
        }
    });

    // İlerleme adımları
    const steps = [
        { id: 'step1', time: 0,     text: 'Sayfa içeriği çekiliyor...' },
        { id: 'step2', time: 3000,  text: 'Metin analiz ediliyor...' },
        { id: 'step3', time: 6000,  text: 'Yapay zeka anahtar kelimeleri keşfediyor...' },
        { id: 'step4', time: 11000, text: 'Metin optimize ediliyor...' },
        { id: 'step4', time: 30000, text: 'Kapsamlı içerik optimize ediliyor, lütfen bekleyin...' },
        { id: 'step4', time: 45000, text: 'Büyük metinler için yapay zeka son kontrolleri yapıyor...' },
        { id: 'step4', time: 60000, text: 'İşlem devam ediyor, kelime optimizasyonu sürüyor...' },
        { id: 'step5', time: 75000, text: 'Sonuçlar hazırlanıyor...' }
    ];

    let progressTimers = [];
    let optimizedBodyText = '';

    // İlk açılışta geçmişi yükle
    renderHistory();

    // ===== FORM GÖNDERİMİ =====
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const url = urlInput.value.trim();
        if (!url) return;

        // UI güncelle
        showLoading();
        hideError();
        startProgressSteps();

        try {
            const response = await fetch('src/textseo/api/analyze.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ url: url })
            });

            const data = await response.json();
            stopProgressSteps();

            if (data.status === 'error') {
                showError(data.error || 'Bilinmeyen bir hata oluştu.');
                return;
            }

            // Analizi geçmişe kaydet
            saveToHistory(url, data);

            // Analiz başarılı, sonuçları göster
            currentDebugTrace = data.debug_trace || [];
            analyzedUrlText.textContent = url;
            document.getElementById('analyzedUrlTooltip').textContent = url;
            renderResults(data);
            switchToResultsView();

        } catch (err) {
            stopProgressSteps();
            showError('Bağlantı hatası. Sunucuya ulaşılamadı, lütfen tekrar deneyin.');
            console.error('Fetch hatası:', err);
        } finally {
            hideLoading();
        }
    });
    
    // ===== YENİ ANALİZ BUTONU =====
    newAnalysisBtn.addEventListener('click', function() {
        switchToHomeView();
        urlInput.value = '';
        urlInput.focus();
    });

    // ===== GEÇMİŞ (HISTORY) FONKSİYONLARI =====
    function getHistory() {
        const h = localStorage.getItem(STORAGE_KEY);
        return h ? JSON.parse(h) : [];
    }

    function saveToHistory(url, data) {
        let history = getHistory();
        
        const now = new Date();
        const dateStr = now.toLocaleDateString('tr-TR') + ' - ' + now.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
        
        // Benzersiz ID
        const id = Date.now().toString();

        const newItem = {
            id: id,
            url: url,
            dateStr: dateStr,
            data: data
        };

        // Başa ekle
        history.unshift(newItem);

        // Maksimum 20 kayıt
        if (history.length > 20) {
            history = history.slice(0, 20);
        }

        localStorage.setItem(STORAGE_KEY, JSON.stringify(history));
        renderHistory();
    }

    function renderHistory() {
        if (!historyList) return;
        const history = getHistory();
        historyList.innerHTML = '';

        if (history.length === 0) {
            clearHistoryBtn.style.display = 'none';
            historyList.innerHTML = '<div class="history-empty">Henüz kaydedilmiş bir analiz geçmişi bulunmuyor.</div>';
            return;
        }

        clearHistoryBtn.style.display = 'inline-block';

        history.forEach(item => {
            // Başlık belirleme (Orijinal Meta Title > URL)
            let titleText = item.url;
            if (item.data && item.data.original && item.data.original.title) {
                titleText = item.data.original.title;
            }

            const el = document.createElement('div');
            el.className = 'history-item';
            el.innerHTML = `
                <div class="history-main-info">
                    <span class="history-keyword">${escapeHTML(titleText)}</span>
                    <span class="history-meta">${escapeHTML(item.dateStr)} · ${escapeHTML(item.url)}</span>
                </div>
                <button type="button" class="delete-item-btn" title="Sil">🗑️</button>
            `;

            // Tıklama eventleri
            el.addEventListener('click', () => loadHistoryItem(item));
            
            const delBtn = el.querySelector('.delete-item-btn');
            delBtn.addEventListener('click', (e) => deleteHistoryItem(item.id, e));

            historyList.appendChild(el);
        });
    }

    function loadHistoryItem(item) {
        currentDebugTrace = item.data.debug_trace || [];
        analyzedUrlText.textContent = item.url;
        document.getElementById('analyzedUrlTooltip').textContent = item.url;
        renderResults(item.data);
        switchToResultsView();
    }

    function deleteHistoryItem(id, e) {
        e.stopPropagation(); // Parent tıklamasını engelle
        let history = getHistory();
        history = history.filter(item => item.id !== id);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(history));
        renderHistory();
    }

    clearHistoryBtn.addEventListener('click', function() {
        if (confirm("Tüm analiz geçmişini silmek istediğinize emin misiniz?")) {
            localStorage.removeItem(STORAGE_KEY);
            renderHistory();
        }
    });

    function escapeHTML(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }


    // ===== SONUÇLARI RENDER ET =====
    function renderResults(data) {
        const { original, optimized, keywords, analysis } = data;
        optimizedBodyText = optimized.body_text || '';

        // Eksik Konular Listesini Doldur
        missingTopicsList.innerHTML = '';
        if (keywords && keywords.missing_topics && keywords.missing_topics.length > 0) {
            keywords.missing_topics.forEach(topic => {
                const li = document.createElement('li');
                li.style.marginBottom = '8px';
                li.textContent = topic;
                missingTopicsList.appendChild(li);
            });
            toggleMissingTopicsBtn.style.display = 'inline-block';
        } else {
            toggleMissingTopicsBtn.style.display = 'none';
        }

        // Teknik Raporu Doldur (Güzel UI ile)
        if (analysis && analysis.content) {
            const metrics = analysis.content;
            const grid = document.getElementById('techMetricsGrid');

            grid.innerHTML = `
                <div class="metric-card">
                    <div class="metric-label">Kelime Sayısı</div>
                    <div class="metric-value">${metrics.word_count || 0}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Cümle Sayısı</div>
                    <div class="metric-value">${metrics.sentence_count || 0}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Ort. Cümle Uzunluğu</div>
                    <div class="metric-value">${metrics.avg_sentence_length || 0}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Paragraf Sayısı</div>
                    <div class="metric-value">${metrics.paragraph_count || 0}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Okunabilirlik Puanı</div>
                    <div class="metric-value">${metrics.readability_score || 0}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Ort. Kelime Başı Hece</div>
                    <div class="metric-value">${metrics.avg_syllable_per_word || 0}</div>
                </div>
            `;
            
            // Başlık (Heading) istatistikleri varsa ekle
            if (analysis.headings) {
                const h = analysis.headings;
                grid.innerHTML += `
                    <div class="metric-card">
                        <div class="metric-label">H1 / H2 / H3 Sayısı</div>
                        <div class="metric-value">${h.h1_count || 0} / ${h.h2_count || 0} / ${h.h3_count || 0}</div>
                    </div>
                `;
            }

            // Title / Desc analizleri varsa ekle
            if (analysis.title && analysis.description) {
                grid.innerHTML += `
                    <div class="metric-card">
                        <div class="metric-label">Title / Desc Uzunluğu</div>
                        <div class="metric-value">${analysis.title.length || 0} / ${analysis.description.length || 0}</div>
                    </div>
                `;
            }
            toggleTechReportBtn.style.display = 'inline-block';
        } else {
            toggleTechReportBtn.style.display = 'none';
        }

        // Kapları gizle
        missingTopicsContainer.style.display = 'none';
        techReportContainer.style.display = 'none';

        // Anahtar kelimeler
        renderKeywords(keywords);

        // Meta Title
        setText('oldTitle', original.title || '(Boş)');
        setText('newTitle', optimized.title || '(Boş)');
        renderCharCount('oldTitleCount', (original.title || '').length, 50, 60);
        renderCharCount('newTitleCount', (optimized.title || '').length, 50, 60);

        // Meta Description
        setText('oldDesc', original.description || '(Boş)');
        setText('newDesc', optimized.description || '(Boş)');
        renderCharCount('oldDescCount', (original.description || '').length, 150, 160);
        renderCharCount('newDescCount', (optimized.description || '').length, 150, 160);

        // Gövde Metni Diff
        renderDiff(original.body_text || '', optimized.body_text || '');
        
        // Kelime Sayacı
        const wordCount = countWords(optimizedBodyText);
        const oldWordCount = countWords(original.body_text || '');
        document.getElementById('bodyWordCount').textContent = '📊 Eski: ' + oldWordCount + ' | Yeni: ' + wordCount + ' Kelime';
    }

    // ===== ANAHTAR KELİMELER =====
    function renderKeywords(keywords) {
        if (!keywords || !keywords.focus) {
            document.getElementById('keywordsBar').style.display = 'none';
            return;
        }

        document.getElementById('keywordsBar').style.display = 'flex';
        document.getElementById('focusKeyword').textContent = keywords.focus;

        const secondaryContainer = document.getElementById('secondaryKeywords');
        secondaryContainer.innerHTML = '';
        if (keywords.secondary && keywords.secondary.length > 0) {
            keywords.secondary.forEach(function(kw) {
                const tag = document.createElement('span');
                tag.className = 'keyword-tag';
                tag.textContent = kw;
                secondaryContainer.appendChild(tag);
            });
        }
    }

    // ===== KARAKTER SAYACI =====
    function renderCharCount(elementId, length, min, max) {
        const el = document.getElementById(elementId);
        el.textContent = length + ' karakter';
        el.className = (length >= min && length <= max) ? 'char-count ideal' : 'char-count bad';
    }

    // ===== DİFF RENDER =====
    function renderDiff(oldText, newText) {
        const diffContainer = document.getElementById('diffContainer');
        diffContainer.innerHTML = '';

        if (!oldText && !newText) {
            diffContainer.innerHTML = '<p class="diff-p" style="color:#94a3b8;text-align:center;">Metin bulunamadı.</p>';
            return;
        }

        // jsdiff ile kelime bazlı karşılaştırma
        const diff = Diff.diffWords(oldText, newText);

        let addedWords = 0;
        let removedWords = 0;

        let currentBlock = document.createElement('div');
        currentBlock.className = 'diff-block';
        let currentElement = document.createElement('p');
        currentElement.className = 'diff-p';
        currentBlock.appendChild(currentElement);
        
        let isNewLine = true;

        diff.forEach(function(part) {
            if (part.added) addedWords += countWords(part.value);
            if (part.removed) removedWords += countWords(part.value);

            // Metni satır satır böl, satır sonlarını işle
            const tokens = part.value.split(/(\n+)/);

            tokens.forEach(function(token) {
                if (!token) return;

                if (token.match(/^\n+$/)) {
                    // Yeni satır karakterleri
                    if (currentElement.childNodes.length > 0) {
                        diffContainer.appendChild(currentBlock);
                    }
                    
                    currentBlock = document.createElement('div');
                    currentBlock.className = 'diff-block';
                    currentElement = document.createElement('p');
                    currentElement.className = 'diff-p';
                    currentBlock.appendChild(currentElement);
                    
                    isNewLine = true;
                } else {
                    // Metin parçası
                    let textToDisplay = token;
                    
                    // Satırın başındaysak başlık kontrolü yap
                    if (isNewLine && textToDisplay.trim().length > 0) {
                        const trimmedToken = textToDisplay.trimStart();
                        let hLevel = 0;
                        
                        if (trimmedToken.startsWith('# ')) { hLevel = 1; textToDisplay = textToDisplay.replace(/^[\s]*#\s*/, ''); }
                        else if (trimmedToken.startsWith('## ')) { hLevel = 2; textToDisplay = textToDisplay.replace(/^[\s]*##\s*/, ''); }
                        else if (trimmedToken.startsWith('### ')) { hLevel = 3; textToDisplay = textToDisplay.replace(/^[\s]*###\s*/, ''); }
                        else if (trimmedToken.startsWith('H1:')) { hLevel = 1; textToDisplay = textToDisplay.replace(/^[\s]*H1:\s*/i, ''); }
                        else if (trimmedToken.startsWith('H2:')) { hLevel = 2; textToDisplay = textToDisplay.replace(/^[\s]*H2:\s*/i, ''); }
                        else if (trimmedToken.startsWith('H3:')) { hLevel = 3; textToDisplay = textToDisplay.replace(/^[\s]*H3:\s*/i, ''); }
                        
                        if (hLevel > 0) {
                            const newElement = document.createElement('div');
                            newElement.className = 'diff-h' + hLevel;
                            
                            const badge = document.createElement('span');
                            badge.className = 'heading-tag-badge';
                            badge.textContent = 'H' + hLevel;
                            
                            newElement.appendChild(badge);
                            
                            // Replace current <p> with this new heading div
                            currentBlock.replaceChild(newElement, currentElement);
                            currentElement = newElement;
                        }
                        
                        // Satır başını işledik
                        isNewLine = false;
                    }

                    if (textToDisplay.length > 0) {
                        const span = document.createElement('span');
                        if (part.added) span.className = 'diff-added';
                        else if (part.removed) span.className = 'diff-removed';
                        
                        span.textContent = textToDisplay;
                        currentElement.appendChild(span);
                    }
                }
            });
        });

        // Kalan son bloğu ekle (eğer içi boş değilse)
        if (currentElement.childNodes.length > 0) {
             diffContainer.appendChild(currentBlock);
        }

        // Diff istatistikleri
        const statsEl = document.getElementById('diffStats');
        statsEl.innerHTML =
            '<span style="color:#28a745;font-weight:600;">+' + addedWords + ' kelime</span>' +
            ' · ' +
            '<span style="color:#dc3545;font-weight:600;">−' + removedWords + ' kelime</span>';
    }

    function countWords(text) {
        return text.trim().split(/\s+/).filter(function(w) { return w.length > 0; }).length;
    }

    // ===== KOPYALAMA =====
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.copy-btn');
        if (!btn) return;

        let textToCopy = '';

        if (btn.id === 'copyOptimizedText') {
            textToCopy = optimizedBodyText;
        } else {
            const targetId = btn.dataset.target;
            if (targetId) {
                textToCopy = document.getElementById(targetId).textContent;
            }
        }

        if (textToCopy && textToCopy !== '(Boş)') {
            copyToClipboard(textToCopy, btn);
        }
    });

    function copyToClipboard(text, btn) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                showCopied(btn);
            }).catch(function(err) {
                console.error('Clipboard API başarısız:', err);
                fallbackCopyTextToClipboard(text, btn);
            });
        } else {
            fallbackCopyTextToClipboard(text, btn);
        }
    }
    
    function fallbackCopyTextToClipboard(text, btn) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            showCopied(btn);
        } catch(e) {
            console.error('Fallback kopyalama başarısız:', e);
        }
        document.body.removeChild(textarea);
    }

    function showCopied(btn) {
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '✅ Kopyalandı!';
        btn.classList.add('copied');
        setTimeout(function() {
            btn.innerHTML = originalHTML;
            btn.classList.remove('copied');
        }, 2000);
    }

    // ===== İLERLEME ADIMLARI =====
    function startProgressSteps() {
        // Tüm adımları resetle
        steps.forEach(function(step) {
            const el = document.getElementById(step.id);
            el.className = 'step';
        });

        progressTimers = steps.map(function(step, index) {
            return setTimeout(function() {
                // Önceki adımları 'done' yap
                for (let i = 0; i < index; i++) {
                    document.getElementById(steps[i].id).className = 'step done';
                }
                // Bu adımı 'active' yap
                document.getElementById(step.id).className = 'step active';
                // Mesajı güncelle
                loadingMessage.textContent = step.text;
            }, step.time);
        });
    }

    function stopProgressSteps() {
        progressTimers.forEach(clearTimeout);
        progressTimers = [];
    }

    // ===== UI YARDIMCILARI =====
    function setText(id, text) {
        const el = document.getElementById(id);
        if(el) el.textContent = text;
    }

    function showLoading() {
        document.body.classList.add('is-loading');
        loadingSection.style.display = 'flex';
        analyzeBtn.disabled = true;
        analyzeBtn.querySelector('.btn-text').style.display = 'none';
        analyzeBtn.querySelector('.btn-spinner').style.display = 'inline-flex';
    }

    function hideLoading() {
        document.body.classList.remove('is-loading');
        loadingSection.style.display = 'none';
        analyzeBtn.disabled = false;
        analyzeBtn.querySelector('.btn-text').style.display = 'inline';
        analyzeBtn.querySelector('.btn-spinner').style.display = 'none';
    }

    function showError(msg) {
        errorSection.style.display = 'block';
        errorMessage.textContent = msg;
        // Hata görününce scroll
        errorSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function hideError() {
        errorSection.style.display = 'none';
    }

    // EKRAN GEÇİŞ FONKSİYONLARI (SPA MANTIĞI)
    function switchToResultsView() {
        homeView.style.display = 'none';
        resultsView.style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    function switchToHomeView() {
        resultsView.style.display = 'none';
        homeView.style.display = 'flex'; // flex kullandığımız için flex'e döndürüyoruz
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

});
