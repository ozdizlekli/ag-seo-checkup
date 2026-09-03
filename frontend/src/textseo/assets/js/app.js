document.addEventListener('DOMContentLoaded', function() {

    function getAuthHeaders() {
        const metaToken = document.querySelector('meta[name="api-token"]');
        const headers = { 'Content-Type': 'application/json' };
        if (metaToken && metaToken.content) {
            headers['X-Auth-Token'] = metaToken.content;
        }
        return headers;
    }    // ===== DOM REFERANSLARI =====
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
    fetchHistory();

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
                headers: getAuthHeaders(),
                body: JSON.stringify({ url: url })
            });

            const data = await response.json();
            stopProgressSteps();

            if (data.status === 'error') {
                showError(data.error || 'Bilinmeyen bir hata oluştu.');
                return;
            }

            if (data.warnings && data.warnings.length > 0) {
                console.warn("API Uyarıları:", data.warnings);
                let warningSection = document.getElementById('warningSection');
                if (!warningSection) {
                    warningSection = document.createElement('section');
                    warningSection.id = 'warningSection';
                    warningSection.className = 'error-section';
                    warningSection.style.backgroundColor = '#fff3cd';
                    warningSection.style.borderColor = '#ffeeba';
                    warningSection.style.color = '#856404';
                    warningSection.style.marginTop = '20px';
                    warningSection.innerHTML = `
                        <div class="error-box" style="background:transparent; border:none; box-shadow:none; padding:10px;">
                            <span class="error-icon" style="color:#856404;">⚠️</span>
                            <p class="error-message" id="warningMessage" style="color:#856404; font-weight:bold;"></p>
                            <button type="button" onclick="document.getElementById('warningSection').style.display='none'" class="dismiss-btn" style="color:#856404; border-color:#856404; background:transparent;">Kapat</button>
                        </div>
                    `;
                    document.getElementById('errorSection').insertAdjacentElement('afterend', warningSection);
                }
                document.getElementById('warningMessage').innerHTML = data.warnings.join('<br>');
                warningSection.style.display = 'block';
            } else {
                const warningSection = document.getElementById('warningSection');
                if (warningSection) warningSection.style.display = 'none';
            }

            // API'dan listeyi yeniden çek
            fetchHistory();

            // Analiz başarılı, sonuçları göster
            analyzedUrlText.textContent = url;
            document.getElementById('analyzedUrlTooltip').textContent = url;
            
            // Analiz Rozeti Gösterimi
            const count = data.analysis_number || data.analyze_count || 1;
            const analyzeBadge = document.getElementById('analyzeBadge');
            if (analyzeBadge) {
                analyzeBadge.style.display = 'inline-flex';
                if(count == 1) {
                    analyzeBadge.className = 'analyze-badge first';
                    analyzeBadge.innerHTML = '✨ 1. Kapsamlı Analiz';
                } else {
                    analyzeBadge.className = 'analyze-badge repeat';
                    analyzeBadge.innerHTML = `🔄 ${count}. Analiz (Farklı Anahtar Kelimeler)`;
                }
            }

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
    async function fetchHistory() {
        if (!historyList) return;
        
        try {
            const response = await fetch('src/textseo/api/history.php', { headers: getAuthHeaders() });
            if (!response.ok) return;
            const data = await response.json();
            
            const history = Array.isArray(data) ? data : (data.data || []);
            renderHistoryList(history);
        } catch(err) {
            console.error('History fetch error:', err);
        }
    }
    
    function renderHistoryList(history) {
        historyList.innerHTML = '';
        
        if (history.length === 0) {
            clearHistoryBtn.style.display = 'none';
            historyList.innerHTML = '<div class="history-empty">Henüz kaydedilmiş bir analiz geçmişi bulunmuyor.</div>';
            return;
        }

        clearHistoryBtn.style.display = 'inline-block';

        history.forEach(item => {
            let titleText = (item.title && item.title.trim() !== '') ? item.title : item.url;
            
            const count = item.analysis_number || item.analyze_count || 1;
            let badgeHtml = '';
            if (count == 1) {
                badgeHtml = `<span class="analyze-badge first">✨ 1. Kapsamlı Analiz</span>`;
            } else {
                badgeHtml = `<span class="analyze-badge repeat">🔄 ${count}. Analiz (Farklı Anahtar Kelimeler)</span>`;
            }
            
            const dateStr = item.dateStr || item.created_at || '';

            const el = document.createElement('div');
            el.className = 'history-item';
            el.innerHTML = `
                <div class="history-main-info">
                    <span class="history-keyword" title="${escapeHTML(titleText)}">${escapeHTML(titleText)}</span>
                    <div class="history-badge-container">
                        ${badgeHtml}
                    </div>
                    <span class="history-meta">${escapeHTML(dateStr)} · ${escapeHTML(item.url)}</span>
                </div>
                <button type="button" class="delete-item-btn" title="Sil">🗑️</button>
            `;

            el.addEventListener('click', () => loadHistoryItem(item.id || item.url));
            
            const delBtn = el.querySelector('.delete-item-btn');
            delBtn.addEventListener('click', (e) => deleteHistoryItem(item.id || item.url, e));

            historyList.appendChild(el);
        });
    }

    async function loadHistoryItem(id) {
        showLoading();
        try {
            const response = await fetch(`src/textseo/api/history.php?id=${id}`, { headers: getAuthHeaders() });
            const data = await response.json();
            
            if (data.status === 'error') {
                showError('Kayıt bulunamadı.');
            } else {
                const resultData = data.data || data; 
                
                analyzedUrlText.textContent = resultData.url || resultData.data?.url || '';
                document.getElementById('analyzedUrlTooltip').textContent = resultData.url || '';
                
                const count = resultData.analysis_number || resultData.analyze_count || 1;
                const analyzeBadge = document.getElementById('analyzeBadge');
                if (analyzeBadge) {
                    analyzeBadge.style.display = 'inline-flex';
                    if(count == 1) {
                        analyzeBadge.className = 'analyze-badge first';
                        analyzeBadge.innerHTML = '✨ 1. Kapsamlı Analiz';
                    } else {
                        analyzeBadge.className = 'analyze-badge repeat';
                        analyzeBadge.innerHTML = `🔄 ${count}. Analiz (Farklı Anahtar Kelimeler)`;
                    }
                }

                // API'den dönen özellikleri garantiye al (boş obje fallback)
                const renderData = {
                    original: resultData.original || resultData.data?.original || {},
                    optimized: resultData.optimized || resultData.data?.optimized || {},
                    keywords: resultData.keywords || resultData.data?.keywords || {},
                    analysis: resultData.analysis || resultData.data?.analysis || {}
                };
                
                const warningSection = document.getElementById('warningSection');
                if (warningSection) warningSection.style.display = 'none';

                renderResults(renderData);
                switchToResultsView();
            }
        } catch(err) {
            console.error('loadHistoryItem error:', err);
            showError('Kayıt yüklenirken hata oluştu.');
        } finally {
            hideLoading();
        }
    }

    async function deleteHistoryItem(id, e) {
        e.stopPropagation();
        try {
            await fetch('src/textseo/api/history.php', {
                method: 'POST',
                headers: getAuthHeaders(),
                body: JSON.stringify({ action: 'delete', id: id })
            });
            fetchHistory();
        } catch(err) {
            console.error('Delete error', err);
        }
    }

    clearHistoryBtn.addEventListener('click', async function() {
        if (confirm("Tüm analiz geçmişini silmek istediğinize emin misiniz?")) {
            try {
                await fetch('src/textseo/api/history.php', {
                    method: 'POST',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({ action: 'clear' })
                });
                fetchHistory();
            } catch(err) {
                console.error('Clear error', err);
            }
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
                        <div class="metric-value">${analysis.title?.length ?? 0} / ${analysis.description?.length ?? 0}</div>
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
                        
                        if (trimmedToken.startsWith('### ')) { hLevel = 3; textToDisplay = textToDisplay.replace(/^[\s]*###\s*/, ''); }
                        else if (trimmedToken.startsWith('## ')) { hLevel = 2; textToDisplay = textToDisplay.replace(/^[\s]*##\s*/, ''); }
                        else if (trimmedToken.startsWith('# ')) { hLevel = 1; textToDisplay = textToDisplay.replace(/^[\s]*#\s*/, ''); }
                        else if (trimmedToken.match(/^[\s]*H3:\s*/i)) { hLevel = 3; textToDisplay = textToDisplay.replace(/^[\s]*H3:\s*/i, ''); }
                        else if (trimmedToken.match(/^[\s]*H2:\s*/i)) { hLevel = 2; textToDisplay = textToDisplay.replace(/^[\s]*H2:\s*/i, ''); }
                        else if (trimmedToken.match(/^[\s]*H1:\s*/i)) { hLevel = 1; textToDisplay = textToDisplay.replace(/^[\s]*H1:\s*/i, ''); }
                        
                        if (hLevel > 0) {
                            const newElement = document.createElement('h' + hLevel);
                            newElement.className = 'diff-h' + hLevel;
                            
                            // Replace current <p> with this new heading tag
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
        loadingSection.style.display = 'block';
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
