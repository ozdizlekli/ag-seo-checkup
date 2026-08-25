(function() {
document.addEventListener('DOMContentLoaded', () => {
    // --- Elements ---
    const rawText = document.getElementById('rawText');
    const charCount = document.getElementById('charCount');
    const wordCount = document.getElementById('wordCount');
    const analyzeBtn = document.getElementById('analyzeBtn');
    const optionsToggle = document.getElementById('optionsToggle');
    const optionsPanel = document.getElementById('optionsPanel');
    const optionsIcon = document.getElementById('optionsIcon');
    const inputSection = document.getElementById('inputSection');
    const resultsSection = document.getElementById('resultsSection');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const targetKeywordInput = document.getElementById('targetKeyword');
    const historySection = document.getElementById('historySection');
    const historyList = document.getElementById('historyList');
    const clearAllHistoryBtn = document.getElementById('clearAllHistoryBtn');
    const backToInputBtn = document.getElementById('backToInputBtn');
    
    let chartInstance = null;
    let apiData = null;
    let hasDownloadedPdf = false;

    // --- History & Navigation ---
    const HISTORY_KEY = 'seo_nlp_history';

    function getHistory() {
        return JSON.parse(localStorage.getItem(HISTORY_KEY)) || [];
    }

    function saveToHistory(apiData, text) {
        let history = getHistory();
        const healthScore = apiData.ai_dimensions?.analiz?.saglik_skoru || 0;
        const words = text.trim().split(/\s+/).filter(w => w.length > 0).length;
        const targetKeyword = apiData.orchestration?.target_keyword || 'Belirtilmedi';

        const newItem = {
            id: Date.now(),
            date: new Date().toLocaleString('tr-TR'),
            targetKeyword,
            healthScore,
            wordCount: words,
            rawText: text,
            apiData
        };

        history.unshift(newItem);
        if (history.length > 15) history.pop();
        localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
    }

    function renderHistory() {
        const history = getHistory();
        if (history.length === 0) {
            historySection.classList.add('hidden');
            return;
        }

        historySection.classList.remove('hidden');
        historyList.innerHTML = '';

        history.forEach(item => {
            let badgeClass = 'text-success';
            if (item.healthScore < 80) badgeClass = 'text-warning';
            if (item.healthScore < 50) badgeClass = 'text-danger';

            const card = document.createElement('div');
            card.className = 'bg-white p-4 rounded-xl shadow-sm border border-gray-100 cursor-pointer hover:shadow-md transition flex justify-between items-center group';
            card.innerHTML = `
                <div class="flex flex-col gap-1 w-full">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="text-xs font-semibold text-gray-500 block mb-1">${item.date}</span>
                            <span class="font-bold text-gray-800">${item.targetKeyword}</span>
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            <span class="text-xs font-bold px-2 py-1 rounded bg-gray-50 border border-gray-200 ${badgeClass}">
                                Skor: ${item.healthScore}
                            </span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <span class="text-xs text-gray-500"><i class="ph ph-text-aa"></i> ${item.wordCount} Kelime</span>
                        <button class="delete-item-btn text-gray-400 hover:text-danger transition p-1" data-id="${item.id}" title="Sil">
                            <i class="ph ph-trash text-lg"></i>
                        </button>
                    </div>
                </div>
            `;
            
            card.addEventListener('click', () => {
                rawText.value = item.rawText;
                // Trigger input event to update counters
                rawText.dispatchEvent(new Event('input'));
                apiData = item.apiData;
                populateResults(apiData);
                inputSection.classList.add('hidden');
                historySection.classList.add('hidden');
                resultsSection.classList.remove('hidden');
                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
            
            historyList.appendChild(card);
        });

        // Attach delete events
        document.querySelectorAll('.delete-item-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                deleteHistoryItem(Number(btn.dataset.id));
            });
        });
    }

    function deleteHistoryItem(id) {
        let history = getHistory();
        history = history.filter(item => item.id !== id);
        localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
        renderHistory();
    }

    clearAllHistoryBtn.addEventListener('click', () => {
        if (confirm('Tüm geçmişi silmek istediğinize emin misiniz?')) {
            localStorage.removeItem(HISTORY_KEY);
            renderHistory();
        }
    });

    if (backToInputBtn) {
        backToInputBtn.addEventListener('click', () => {
            resultsSection.classList.add('hidden');
            inputSection.classList.remove('hidden');
            renderHistory();
        });
    }

    // --- Init ---
    renderHistory();

    // --- Input Counters & Actions ---
    rawText.addEventListener('input', () => {
        const text = rawText.value;
        charCount.textContent = text.length;
        const words = text.trim().split(/\s+/).filter(w => w.length > 0);
        wordCount.textContent = words.length;
    });

    const copyInputBtn = document.getElementById('copyInputBtn');
    const clearInputBtn = document.getElementById('clearInputBtn');

    if (copyInputBtn) {
        copyInputBtn.addEventListener('click', () => {
            const text = rawText.value;
            if (!text) return;
            navigator.clipboard.writeText(text).then(() => {
                const originalHTML = copyInputBtn.innerHTML;
                copyInputBtn.innerHTML = '<i class="ph-fill ph-check"></i> Kopyalandı';
                copyInputBtn.classList.replace('text-gray-700', 'text-success');
                setTimeout(() => {
                    copyInputBtn.innerHTML = originalHTML;
                    copyInputBtn.classList.replace('text-success', 'text-gray-700');
                }, 2000);
            });
        });
    }

    if (clearInputBtn) {
        clearInputBtn.addEventListener('click', () => {
            rawText.value = '';
            rawText.dispatchEvent(new Event('input'));
            rawText.focus();
        });
    }

    // --- Accordion ---
    optionsToggle.addEventListener('click', () => {
        optionsPanel.classList.toggle('hidden');
        optionsIcon.classList.toggle('rotate-180');
    });

    // --- Tabs ---
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Remove active from all
            tabBtns.forEach(b => {
                b.classList.remove('active', 'border-primary', 'text-primary');
                b.classList.add('border-transparent', 'text-gray-500');
            });
            tabPanes.forEach(p => p.classList.remove('active', 'hidden'));
            
            // Add active to clicked
            btn.classList.add('active', 'border-primary', 'text-primary');
            btn.classList.remove('border-transparent', 'text-gray-500');
            
            // Show target
            tabPanes.forEach(p => {
                if(p.id === btn.dataset.target) {
                    p.classList.add('active');
                } else {
                    p.classList.add('hidden');
                }
            });
        });
    });

    // --- Analyze Button ---
    analyzeBtn.addEventListener('click', async () => {
        hasDownloadedPdf = false;
        const text = rawText.value.trim();
        if (!text) {
            alert('Lütfen analiz edilecek metni girin.');
            return;
        }

        console.log("[UI LOG] 1. Analiz butonu tıklandı. Metin:", text.substring(0, 50) + "...");

        const targetKeyword = targetKeywordInput.value.trim();

        startLoading();

        try {
            const payload = { text };
            if (targetKeyword) payload.target_keyword = targetKeyword;

            // Simulate step progress slightly faster than real API just for UX
            updateLoadingStep(1, "Metin anatomisi ölçülüyor...");
            
            console.log("[UI LOG] 2. api/text_seo_analyze.php adresine POST isteği gönderiliyor...");
            const response = await fetch('src/TextSeo/api/text_seo_analyze.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Bilinmeyen bir hata oluştu.');
            }

            console.log("[UI LOG] 3. Sunucudan yanıt alındı:", data);
            
            saveToHistory(data, text);
            renderHistory();

            apiData = data;
            
            updateLoadingStep(3, "Yapay zeka ile strateji belirleniyor...");
            setTimeout(() => {
                updateLoadingStep(4, "Otomatik düzeltme uygulanıyor...");
                setTimeout(() => {
                    try {
                        console.log("[UI LOG] 4. populateResults() başlatılıyor...");
                        populateResults(data);
                    } catch (e) {
                        console.error("[UI HATA]:", e);
                        alert('Sonuçlar işlenirken bir hata oluştu: ' + e.message);
                    } finally {
                        console.log("[UI LOG] 5. finishLoading() çağrıldı. Yükleme perdesi kapatılıyor.");
                        finishLoading();
                    }
                }, 500);
            }, 500);

        } catch (error) {
            stopLoading();
            alert('Hata: ' + error.message);
        }
    });

    // --- Loading Stepper ---
    const loadingProgress = document.getElementById('loadingProgress');
    const loadingPercent = document.getElementById('loadingPercent');
    const loadingStatus = document.getElementById('loadingStatus');
    const loadingStepsItems = document.querySelectorAll('#loadingSteps li');

    function startLoading() {
        loadingOverlay.classList.remove('hidden');
        loadingOverlay.classList.add('flex');
        updateLoadingStep(0, "Bağlantı kuruluyor...");
    }

    function updateLoadingStep(stepIndex, msg) {
        loadingStatus.textContent = msg;
        const pct = [10, 30, 60, 90, 100][stepIndex] || 0;
        loadingProgress.style.width = pct + '%';
        loadingPercent.textContent = pct + '%';

        loadingStepsItems.forEach((li, idx) => {
            const icon = li.querySelector('i');
            if (idx < stepIndex) {
                icon.className = 'ph-fill ph-check-circle text-success';
                li.classList.add('text-gray-900', '');
            } else if (idx === stepIndex) {
                icon.className = 'ph-fill ph-spinner-gap animate-spin text-primary';
                li.classList.add('text-primary');
            } else {
                icon.className = 'ph ph-circle';
                li.classList.remove('text-gray-900', '', 'text-primary');
            }
        });
    }

    function finishLoading() {
        loadingProgress.style.width = '100%';
        loadingPercent.textContent = '100%';
        setTimeout(() => {
            loadingOverlay.classList.add('hidden');
            loadingOverlay.classList.remove('flex');
            inputSection.classList.add('hidden');
            historySection.classList.add('hidden');
            resultsSection.classList.remove('hidden');
        }, 300);
    }

    function stopLoading() {
        loadingOverlay.classList.add('hidden');
        loadingOverlay.classList.remove('flex');
    }

    // --- Populate Results ---
    function populateResults(data) {
        const rawTelemetry = data.telemetry_summary || {};
        const telemetry = rawTelemetry.telemetry_data || rawTelemetry;
        const ai = data.ai_dimensions || {};

        ai.analiz = ai.analiz || {};
        ai.strateji = ai.strateji || {};
        ai.entegrasyon = ai.entegrasyon || {};
        ai.otomatik_duzeltme = ai.otomatik_duzeltme || {};

        // Header
        const healthScore = ai.analiz.saglik_skoru || 0;
        document.getElementById('healthScoreText').textContent = healthScore;
        const circle = document.getElementById('healthScoreCircle');
        circle.setAttribute('stroke-dasharray', `${healthScore}, 100`);
        circle.classList.remove('text-success', 'text-warning', 'text-danger');
        if (healthScore >= 80) circle.classList.add('text-success');
        else if (healthScore >= 50) circle.classList.add('text-warning');
        else circle.classList.add('text-danger');

        console.log("[UI LOG] 4.1. Sağlık skoru ve başlık verileri yazıldı.");

        // Tab 1: Anatomy & Readability
        const anatomy = telemetry.anatomy || { headings: {} };
        const readability = telemetry.readability || {};
        
        renderEnhancedAnatomy(telemetry);

        const getBadgeClass = (status) => {
            if (status === 'success') return 'bg-green-100 text-green-700 border border-green-200';
            if (status === 'warning') return 'bg-yellow-100 text-yellow-700 border border-yellow-200';
            return 'bg-red-100 text-red-700 border border-red-200';
        };
        
        const getBadgeLabel = (status) => {
            if (status === 'success') return 'İyi';
            if (status === 'warning') return 'Orta';
            return 'Kritik';
        };

        const atesmanFb = readability.atesman_feedback || {};
        const complexFb = readability.complex_words_feedback || {};
        const transitionFb = readability.transition_words?.feedback || {};
        const passiveFb = readability.passive_voice?.feedback || {};

        const readabilityStatsEl = document.getElementById('readabilityStats');
        // Kapsayıcı sınıflarını (grid yapısını) bozmamak için className ezilmez.
        readabilityStatsEl.innerHTML = `
            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                <div class="flex justify-between items-start">
                    <h4 class="text-sm font-bold text-slate-700">Okunabilirlik Puanı: <span class="text-primary">${readability.atesman_index || '-'}</span></h4>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider ${getBadgeClass(atesmanFb.status)}">${atesmanFb.label || getBadgeLabel(atesmanFb.status)}</span>
                </div>
                <p class="text-xs leading-relaxed text-gray-600 mt-2 bg-gray-50 p-2.5 rounded-lg border border-gray-100">${atesmanFb.advice || '-'}</p>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                <div class="flex justify-between items-start">
                    <h4 class="text-sm font-bold text-slate-700">Karmaşık Kelime: <span class="text-primary">%${readability.complex_words_percentage || '-'}</span> <span class="text-[10px] font-normal text-slate-500 block sm:inline mt-1 sm:mt-0">(Toplam ${readability.complex_polysyllabic_words_count || 0} adet 3+ heceli kelime)</span></h4>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider ${getBadgeClass(complexFb.status)}">${getBadgeLabel(complexFb.status)}</span>
                </div>
                <p class="text-xs leading-relaxed text-gray-600 mt-2 bg-gray-50 p-2.5 rounded-lg border border-gray-100">${complexFb.advice || '-'}</p>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                <div class="flex justify-between items-start">
                    <h4 class="text-sm font-bold text-slate-700">Geçiş Kelimeleri: <span class="text-primary">%${readability.transition_words?.transition_sentence_ratio_percentage || '-'}</span> <span class="text-[10px] font-normal text-slate-500 block sm:inline mt-1 sm:mt-0">(Toplam ${readability.transition_words?.matched_count || 0} adet bağlaç kullanılmış)</span></h4>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider ${getBadgeClass(transitionFb.status)}">${getBadgeLabel(transitionFb.status)}</span>
                </div>
                <p class="text-xs leading-relaxed text-gray-600 mt-2 bg-gray-50 p-2.5 rounded-lg border border-gray-100">${transitionFb.advice || '-'}</p>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                <div class="flex justify-between items-start">
                    <h4 class="text-sm font-bold text-slate-700">Pasif Cümle: <span class="text-primary">%${readability.passive_voice?.passive_voice_percentage || '-'}</span> <span class="text-[10px] font-normal text-slate-500 block sm:inline mt-1 sm:mt-0">(Toplam ${readability.passive_voice?.passive_sentences_count || 0} adet edilgen cümle)</span></h4>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider ${getBadgeClass(passiveFb.status)}">${getBadgeLabel(passiveFb.status)}</span>
                </div>
                <p class="text-xs leading-relaxed text-gray-600 mt-2 bg-gray-50 p-2.5 rounded-lg border border-gray-100">${passiveFb.advice || '-'}</p>
            </div>
        `;

        // Render Heading Tree
        const headingTreeContainer = document.getElementById('headingTreeContainer');
        if (headingTreeContainer) {
            const structureTree = telemetry.anatomy?.headings?.structure_tree || [];
            if (structureTree.length === 0) {
                headingTreeContainer.innerHTML = '<span class="text-xs text-slate-500 italic">Başlık hiyerarşisi bulunamadı.</span>';
            } else {
                let treeHtml = '';
                structureTree.forEach(heading => {
                    const level = heading.level;
                    const text = heading.text;
                    const wordsBeforeNext = heading.word_count_before_next || 0;
                    
                    // Determine indent based on level
                    const indentClass = level === 1 ? 'ml-0' : (level === 2 ? 'ml-6' : (level === 3 ? 'ml-12' : 'ml-16'));
                    const iconColor = level === 1 ? 'text-indigo-600' : (level === 2 ? 'text-blue-500' : 'text-slate-400');
                    const badgeClass = level === 1 ? 'bg-indigo-100 text-indigo-700' : (level === 2 ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-700');
                    const lineClass = level > 1 ? 'border-l-2 border-slate-200 pl-4 relative before:content-[\'\'] before:absolute before:left-[-2px] before:top-4 before:w-4 before:h-[2px] before:bg-slate-200' : '';

                    treeHtml += `
                        <div class="flex items-start gap-2 py-1.5 ${indentClass} ${lineClass}">
                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded ${badgeClass} mt-0.5 shrink-0">H${level}</span>
                            <div class="flex flex-col">
                                <span class="font-semibold text-slate-800 text-sm leading-tight">${text}</span>
                                <span class="text-[10px] text-slate-500 font-medium flex items-center gap-1 mt-0.5">
                                    <i class="ph ph-text-align-left"></i> ${wordsBeforeNext} kelime içerik
                                </span>
                            </div>
                        </div>
                    `;
                });
                headingTreeContainer.innerHTML = treeHtml;
            }
        }

        // AI Summary & Issues
        document.getElementById('aiSummary').textContent = ai.analiz?.ozet || 'Özet bulunamadı.';
        const issuesList = document.getElementById('aiIssues');
        issuesList.innerHTML = '';
        if (ai.analiz?.sorunlar && Array.isArray(ai.analiz.sorunlar)) {
            ai.analiz.sorunlar.forEach(issue => {
                const li = document.createElement('li');
                li.textContent = issue;
                issuesList.appendChild(li);
            });
        }
        
        console.log("[UI LOG] 4.2. Tab 1 (Metin Anatomisi & Okunabilirlik) render edildi.");

        // Chart.js (N-Grams)
        const keywords = telemetry.keywords_and_frequency || {};
        const unigrams = keywords.top_ngrams?.unigrams || [];
        const bigrams = keywords.top_ngrams?.bigrams || [];
        
        const labels = [];
        const dataValues = [];
        // Mix top 3 unigrams and top 3 bigrams
        unigrams.slice(0, 4).forEach(u => { labels.push(u.term); dataValues.push(u.count); });
        bigrams.slice(0, 3).forEach(b => { labels.push(b.term); dataValues.push(b.count); });

        renderChart(labels, dataValues);
        console.log("[UI LOG] 4.3. Tab 1 Chart.js grafiği çizildi.");

        // Tab 2: Quotas
        const quotasTable = document.getElementById('quotasTable');
        quotasTable.innerHTML = '';
        const adetler = ai.strateji.eklenecek_kelime_adetleri || {};
        if (typeof adetler === 'object' && adetler !== null) {
            for (const [kw, count] of Object.entries(adetler)) {
                quotasTable.innerHTML += `
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">${kw}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">+${count}</span>
                        </td>
                    </tr>
                `;
            }
        }

        // Tab 2: Semantic Gaps
        const gapsContainer = document.getElementById('semanticGapsList');
        gapsContainer.innerHTML = '';
        const gaps = ai.strateji?.semantik_bosluklar || [];
        gaps.forEach(gap => {
            gapsContainer.innerHTML += `<span class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2.5 py-1 rounded border border-yellow-200">${gap}</span>`;
        });

        // Tab 2: PAA
        const paaList = document.getElementById('paaList');
        paaList.innerHTML = '';
        const paas = ai.strateji?.paa_hedefleri || [];
        paas.forEach(paa => {
            paaList.innerHTML += `
                <li class="flex gap-3 bg-gray-50 p-3 rounded-lg border border-gray-100">
                    <i class="ph-fill ph-check-circle text-purple-500 mt-0.5"></i>
                    <span class="text-sm font-medium">${paa}</span>
                </li>`;
        });
        
        console.log("[UI LOG] 4.4. Tab 2 (Strateji & Kotalar) render edildi.");

        // Tab 3: Roadmap
        const roadmapList = document.getElementById('roadmapList');
        roadmapList.innerHTML = '';
        const steps = ai.entegrasyon?.adim_adim_rehber || [];
        steps.forEach((step, idx) => {
            roadmapList.innerHTML += `
                <div class="relative">
                    <span class="absolute -left-[35px] top-1 h-5 w-5 rounded-full bg-primary flex items-center justify-center text-white text-[10px] font-bold ring-4 ring-white pulse-circle">
                        ${idx + 1}
                    </span>
                    <p class="text-sm text-gray-700 leading-relaxed font-medium bg-gray-50 p-3 rounded-lg border border-gray-100">${step}</p>
                </div>
            `;
        });
        
        console.log("[UI LOG] 4.5. Tab 3 (Konumsal Plan) render edildi.");

        // Tab 4: AI Diff
        const origText = rawText.value;
        const optText = ai.otomatik_duzeltme?.yeniden_yazilmis_metin || '';
        
        document.getElementById('originalTextArea').value = origText;
        document.getElementById('optimizedTextArea').value = optText;

        const origWords = origText.trim().split(/\s+/).filter(w => w.length > 0).length;
        const optWords = optText.trim().split(/\s+/).filter(w => w.length > 0).length;
        const diffWords = optWords - origWords;
        const diffText = diffWords >= 0 ? `+${diffWords} Kelime` : `${diffWords} Kelime`;
        document.getElementById('wordDiffStat').textContent = `Fark: ${diffText}`;

        generateDiff(origText, optText);
        console.log("[UI LOG] 4.6. Tab 4 (AI Düzeltme & Diff) render edildi.");

        // Ensure history section is hidden when results are populated
        historySection.classList.add('hidden');
    }

    // --- Chart.js ---
    function renderChart(labels, dataValues) {
        const canvas = document.getElementById('ngramChart');
        if (!canvas || !labels || !dataValues || labels.length === 0 || dataValues.length === 0) return;
        const ctx = canvas.getContext('2d');
        if (chartInstance) chartInstance.destroy();

        const textColor = '#475569';
        const gridColor = '#e2e8f0';

        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Frekans',
                    data: dataValues,
                    backgroundColor: 'rgba(37, 99, 235, 0.7)',
                    borderColor: 'rgb(37, 99, 235)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: textColor, precision: 0 },
                        grid: { color: gridColor }
                    },
                    x: {
                        ticks: { color: textColor },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // --- Diff Generator ---
    function generateDiff(oldStr, newStr) {
        const diffContainer = document.getElementById('diffContainer');
        const diffEngine = window.Diff || window.JsDiff || (typeof Diff !== 'undefined' ? Diff : null);
        
        if (!diffEngine) {
            // Fallback
            diffContainer.textContent = "Eski Metin (Sizin Yazdığınız):\n" + oldStr + "\n\nYeni Metin (Google Uyumlu):\n" + newStr;
            return;
        }

        const diff = diffEngine.diffWords(oldStr, newStr);
        const fragment = document.createDocumentFragment();

        diff.forEach((part) => {
            const span = document.createElement('span');
            span.textContent = part.value;
            if (part.added) {
                span.className = 'diff-add';
            } else if (part.removed) {
                span.className = 'diff-remove';
            }
            fragment.appendChild(span);
        });

        diffContainer.innerHTML = '';
        diffContainer.appendChild(fragment);
    }

    // --- Toggle Diff ---
    const toggleDiffBtn = document.getElementById('toggleDiffBtn');
    let diffMode = false;
    toggleDiffBtn.addEventListener('click', () => {
        diffMode = !diffMode;
        const textAreas = document.querySelector('#tab4 .grid');
        const diffCont = document.getElementById('diffContainer');
        
        if (diffMode) {
            textAreas.classList.add('hidden');
            diffCont.classList.remove('hidden');
            toggleDiffBtn.textContent = 'Yan Yana Görünüm';
            toggleDiffBtn.classList.add('bg-primary', 'text-white');
        } else {
            textAreas.classList.remove('hidden');
            diffCont.classList.add('hidden');
            toggleDiffBtn.textContent = 'Değişiklikleri Karşılaştır';
            toggleDiffBtn.classList.remove('bg-primary', 'text-white');
        }
    });

    // --- Copy Button ---
    const copyBtn = document.getElementById('copyBtn');
    copyBtn.addEventListener('click', () => {
        const text = document.getElementById('optimizedTextArea').value;
        navigator.clipboard.writeText(text).then(() => {
            const originalHTML = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="ph-fill ph-check"></i> Kopyalandı';
            copyBtn.classList.replace('bg-primary', 'bg-success');
            setTimeout(() => {
                copyBtn.innerHTML = originalHTML;
                copyBtn.classList.replace('bg-success', 'bg-primary');
            }, 2000);
        });
    });

    // --- PDF Download Modal ---
    const downloadPdfBtn = document.getElementById('downloadPdfBtn');
    const pdfModal = document.getElementById('pdfModal');
    const pdfModalIcon = document.getElementById('pdfModalIcon');
    const pdfModalMessage = document.getElementById('pdfModalMessage');
    const confirmBtnText = document.getElementById('confirmBtnText');
    const closePdfModalBtn = document.getElementById('closePdfModalBtn');
    const confirmPdfDownloadBtn = document.getElementById('confirmPdfDownloadBtn');

    downloadPdfBtn.addEventListener('click', () => {
        if (!apiData) {
            alert('Lütfen önce bir analiz gerçekleştirin.');
            return;
        }

        if (hasDownloadedPdf) {
            pdfModalIcon.className = 'w-10 h-10 rounded-full flex items-center justify-center text-xl bg-yellow-100 text-warning';
            pdfModalMessage.innerHTML = '⚠️ Bu analizin PDF raporunu daha önce indirdiniz. Güncel raporu yeniden indirmek istiyor musunuz?';
            confirmBtnText.textContent = 'Tekrar İndir';
        } else {
            pdfModalIcon.className = 'w-10 h-10 rounded-full flex items-center justify-center text-xl bg-blue-100 text-primary';
            pdfModalMessage.innerHTML = 'Tüm analizler, grafikler, strateji tablosu ve optimize edilmiş metin PDF olarak hazırlanacaktır. İndirmeyi onaylıyor musunuz?';
            confirmBtnText.textContent = 'PDF Raporunu İndir';
        }
        
        pdfModal.classList.remove('hidden');
    });

    closePdfModalBtn.addEventListener('click', () => {
        pdfModal.classList.add('hidden');
    });

    confirmPdfDownloadBtn.addEventListener('click', () => {
        pdfModal.classList.add('hidden');
        if (typeof generatePDF === 'function') {
            hasDownloadedPdf = true;
            const chartCanvas = document.getElementById('ngramChart');
            generatePDF(apiData, chartCanvas);
        } else {
            alert('PDF modülü yüklenemedi.');
        }
    });

    // --- Score Info Modal ---
    const scoreInfoBtn = document.getElementById('scoreInfoBtn');
    const scoreInfoModal = document.getElementById('scoreInfoModal');
    const closeScoreInfoModalBtn = document.getElementById('closeScoreInfoModalBtn');

    if (scoreInfoBtn && scoreInfoModal && closeScoreInfoModalBtn) {
        scoreInfoBtn.addEventListener('click', () => {
            scoreInfoModal.classList.remove('hidden');
        });

        closeScoreInfoModalBtn.addEventListener('click', () => {
            scoreInfoModal.classList.add('hidden');
        });

        // Close when clicking outside modal content
        scoreInfoModal.addEventListener('click', (e) => {
            if (e.target === scoreInfoModal) {
                scoreInfoModal.classList.add('hidden');
            }
        });
    }
    function renderEnhancedAnatomy(telemetry) {
        const anatomy = telemetry.anatomy || {};
        const sentenceMetrics = anatomy.sentence_metrics || {};
        const paragraphMetrics = anatomy.paragraph_metrics || {};
        const headings = anatomy.headings || {};
        const formatting = anatomy.formatting || {};
        const lexical = telemetry.lexical_and_semantics || {};
        const intent = telemetry.intent_and_action || {};
        
        const wordCount = anatomy.word_count || 0;
        const readingTimeMin = Math.ceil(wordCount / 200);
        const monolithicCount = paragraphMetrics.monolithic_paragraphs_count || 0;
        
        const h1Count = headings.h1_count || 0;
        const h2Count = headings.h2_count || 0;
        const h3Count = headings.h3_count || 0;

        // 1. KATMAN: HERO 4'LÜ KARTLARI DOLDUR
        const heroCardsContainer = document.getElementById('anatomyHeroCards');
        if (heroCardsContainer) {
            heroCardsContainer.innerHTML = `
                <!-- Kelime Hacmi -->
                <div class="bg-slate-50/80 p-3.5 rounded-xl border border-slate-100 flex flex-col justify-between hover:border-blue-200 transition">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider flex items-center justify-between">
                        Kelime Hacmi
                        <i class="ph ph-text-aa text-slate-400"></i>
                    </span>
                    <span class="text-2xl font-extrabold text-slate-800 mt-1">${wordCount.toLocaleString()}</span>
                    <span class="text-[11px] text-slate-500 mt-0.5 flex items-center gap-1 font-medium">
                        <i class="ph ph-clock"></i> ~${readingTimeMin} dk okuma
                    </span>
                </div>

                <!-- Cümle & Ritim -->
                <div class="bg-slate-50/80 p-3.5 rounded-xl border border-slate-100 flex flex-col justify-between hover:border-blue-200 transition">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider flex items-center justify-between">
                        Cümle & Akış
                        <i class="ph ph-rows text-slate-400"></i>
                    </span>
                    <span class="text-2xl font-extrabold text-slate-800 mt-1">${anatomy.sentence_count || 0}</span>
                    <span class="text-[11px] text-slate-500 mt-0.5 font-medium">
                        Ort. ${sentenceMetrics.avg_sentence_length_words || 0} kelime/cümle
                    </span>
                </div>

                <!-- Başlık İskeleti -->
                <div class="bg-slate-50/80 p-3.5 rounded-xl border border-slate-100 flex flex-col justify-between hover:border-blue-200 transition">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider flex items-center justify-between">
                        Başlık İskeleti
                        <i class="ph ph-tree-structure text-slate-400"></i>
                    </span>
                    <span class="text-2xl font-extrabold mt-1 ${h1Count === 1 ? 'text-emerald-600' : 'text-rose-600'}">
                        ${h1Count === 1 ? '1x H1' : `${h1Count}x H1`}
                    </span>
                    <span class="text-[11px] text-slate-500 mt-0.5 font-medium">
                        ${h2Count} H2 • ${h3Count} H3 başlık
                    </span>
                </div>

                <!-- Paragraf & Mobil Blok -->
                <div class="bg-slate-50/80 p-3.5 rounded-xl border border-slate-100 flex flex-col justify-between hover:border-blue-200 transition">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider flex items-center justify-between">
                        Okumayı Zorlaştıran Uzun Paragraflar
                        <i class="ph ph-paragraphs text-slate-400"></i>
                    </span>
                    <span class="text-2xl font-extrabold text-slate-800 mt-1">${anatomy.paragraph_count || 0}</span>
                    <span class="text-[11px] font-semibold mt-0.5 flex items-center gap-1 ${monolithicCount === 0 ? 'text-emerald-600' : 'text-rose-600'}">
                        <i class="ph-fill ${monolithicCount === 0 ? 'ph-check-circle' : 'ph-warning-circle'}"></i>
                        ${monolithicCount === 0 ? 'Kusursuz Paragraf Düzeni' : `${monolithicCount} Uzun ve Yorucu Paragraf!`}
                    </span>
                </div>
            `;
        }

        // 2. KATMAN: DETAYLI X-RAY PANELİNİ DOLDUR (4 SÜTUN)
        const xrayGrid = document.getElementById('xrayGrid');
        if (xrayGrid) {
            const burstiness = sentenceMetrics.burstiness_score || 0;
            const stdDev = sentenceMetrics.standard_deviation || 0;
            const isMonotonous = sentenceMetrics.monotonous_flow_detected || false;
            const longSentences = sentenceMetrics.sentences_over_25_words || 0;
            
            const isHierarchyValid = headings.hierarchy_valid !== false;
            const boldRatio = formatting.bold_words_ratio_percentage || 0;
            const listCount = formatting.list_items_total || 0;
            
            const infoDensity = lexical.stopwords_and_density?.information_density_percentage || 0;
            const stopwordRatio = lexical.stopwords_and_density?.stopword_ratio_percentage || 0;
            const ttr = lexical.lexical_diversity?.type_token_ratio || 0;
            
            const charCountWithSpaces = anatomy.character_count?.with_spaces || 0;
            const syllableCount = anatomy.syllable_count || 0;
            
            const tone = intent.modality_and_tone?.tone_classification || 'NEUTRAL';
            const hasClosingCta = intent.cta_metrics?.has_closing_cta || false;
            const questionRatio = lexical.questions_and_snippets?.question_sentences_ratio_percentage || 0;
            const powerWordsCount = intent.power_words?.matched_count || 0;

            xrayGrid.innerHTML = `
                <!-- SÜTUN 1: RİTİM & AKIŞ DİNAMİĞİ -->
                <div class="bg-gradient-to-br from-slate-50 to-white p-4 rounded-xl border border-slate-200/80 shadow-xs space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                            <i class="ph ph-wave-sine text-indigo-500 text-sm"></i> Cümle Akıcılık ve Dalgalanma Ritmi
                        </span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${!isMonotonous ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'bg-amber-100 text-amber-700 border border-amber-200'}">
                            ${!isMonotonous ? 'Doğal İnsan Ritmi' : 'Monoton Akış'}
                        </span>
                    </div>
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between items-center text-slate-600">
                            <span>Cümle Akıcılık Ritmi (Varyans):</span>
                            <span class="font-bold text-slate-800">${burstiness} <span class="text-[10px] text-slate-400">(Değişkenlik: ${stdDev})</span></span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600">
                            <span>25+ Kelimelik Ağır Cümleler:</span>
                            <span class="font-bold ${longSentences > 0 ? 'text-amber-600' : 'text-emerald-600'}">${longSentences} adet</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600">
                            <span>Okuması Zor Uzun Paragraf (>100 kelime):</span>
                            <span class="font-bold ${monolithicCount > 0 ? 'text-rose-600' : 'text-emerald-600'}">${monolithicCount} blok</span>
                        </div>
                    </div>
                </div>

                <!-- SÜTUN 2: İSKELET & TARANABİLİRLİK -->
                <div class="bg-gradient-to-br from-slate-50 to-white p-4 rounded-xl border border-slate-200/80 shadow-xs space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                            <i class="ph ph-list-dashes text-blue-500 text-sm"></i> İçerik Düzeni ve Okunabilirlik
                        </span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${isHierarchyValid ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'bg-rose-100 text-rose-700 border border-rose-200'}">
                            ${isHierarchyValid ? 'Başlıklar Düzenli' : 'Başlık Sıralaması Hatalı'}
                        </span>
                    </div>
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between items-center text-slate-600">
                            <span>Kullanılan Başlık Türleri:</span>
                            <span class="font-bold text-slate-800">${h1Count} H1 • ${h2Count} H2 • ${h3Count} H3</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600">
                            <span>Madde İmleri & Tablolar:</span>
                            <span class="font-bold ${listCount > 0 ? 'text-emerald-600' : 'text-slate-500'}">${listCount} madde ${formatting.tables_count > 0 ? '+ Tablo' : ''}</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600">
                            <span>Kalın (Bold) Yazılan Kelimeler:</span>
                            <span class="font-bold ${boldRatio <= 3.5 ? 'text-emerald-600' : 'text-amber-600'}">%${boldRatio} <span class="text-[10px] text-slate-400">(İdeal: %1-%3)</span></span>
                        </div>
                    </div>
                </div>

                <!-- SÜTUN 3: BİLGİ YOĞUNLUĞU & SÖZCÜK -->
                <div class="bg-gradient-to-br from-slate-50 to-white p-4 rounded-xl border border-slate-200/80 shadow-xs space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                            <i class="ph ph-brain text-purple-500 text-sm"></i> Net ve Faydalı Bilgi Yoğunluğu
                        </span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${infoDensity >= 55 ? 'bg-purple-100 text-purple-700 border border-purple-200' : 'bg-amber-100 text-amber-700 border border-amber-200'}">
                            %${infoDensity} Faydalı Bilgi
                        </span>
                    </div>
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between items-center text-slate-600">
                            <span>Dolgu Kelime Kullanımı:</span>
                            <span class="font-bold text-slate-800">%${stopwordRatio}</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600">
                            <span>Kelime Zenginliği ve Çeşitliliği:</span>
                            <span class="font-bold ${ttr >= 0.4 ? 'text-emerald-600' : 'text-amber-600'}">${ttr} <span class="text-[10px] text-slate-400">(${ttr >= 0.4 ? 'Zengin' : 'Tekrarlı'})</span></span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600">
                            <span>Karakter / Hece Hacmi:</span>
                            <span class="font-bold text-slate-800">${charCountWithSpaces.toLocaleString()} kr / ${syllableCount.toLocaleString()} hece</span>
                        </div>
                    </div>
                </div>

                <!-- SÜTUN 4: NİYET, TON & EYLEME ÇAĞRI -->
                <div class="bg-gradient-to-br from-slate-50 to-white p-4 rounded-xl border border-slate-200/80 shadow-xs space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                            <i class="ph ph-target text-amber-500 text-sm"></i> Uzman ve Güven Veren Anlatım Dili
                        </span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${tone === 'AUTHORITATIVE' ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : (tone === 'HESITANT' ? 'bg-amber-100 text-amber-700 border border-amber-200' : 'bg-blue-100 text-blue-700 border border-blue-200')}">
                            ${tone === 'AUTHORITATIVE' ? 'Uzman ve Güvenilir' : (tone === 'HESITANT' ? 'Güvensiz İfadeler' : 'Dengeli ve Profesyonel')}
                        </span>
                    </div>
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between items-center text-slate-600">
                            <span>Google'da En Çok Sorulan Sorular:</span>
                            <span class="font-bold ${questionRatio > 0 ? 'text-emerald-600' : 'text-slate-500'}">%${questionRatio} Soru Cümlesi</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600">
                            <span>Müşteriyi Harekete Geçiren Mesaj:</span>
                            <span class="font-bold ${hasClosingCta ? 'text-emerald-600' : 'text-amber-600'}">${hasClosingCta ? 'Var (Son Bölümde)' : 'Bulunamadı'}</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600">
                            <span>İkna Edici ve Etkili Kelimeler:</span>
                            <span class="font-bold text-slate-800">${powerWordsCount} adet tespit edildi</span>
                        </div>
                    </div>
                </div>
            `;
        }

        // 3. ACCORDION EVENT LISTENER BAĞLANTISI
        const toggleBtn = document.getElementById('toggleXrayBtn');
        const xrayPanel = document.getElementById('xrayDetailPanel');
        const xrayChevron = document.getElementById('xrayChevron');
        
        if (toggleBtn && xrayPanel && xrayChevron) {
            const newToggleBtn = toggleBtn.cloneNode(true);
            toggleBtn.parentNode.replaceChild(newToggleBtn, toggleBtn);
            newToggleBtn.addEventListener('click', () => {
                const isHidden = xrayPanel.classList.toggle('hidden');
                document.getElementById('xrayChevron').classList.toggle('rotate-180', !isHidden);
            });
        }
    }

});

})();
