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
            historySection.classList.add('txtseo-hidden');
            return;
        }

        historySection.classList.remove('txtseo-hidden');
        historyList.innerHTML = '';

        history.forEach(item => {
            let badgeClass = 'txtseo-text-success';
            if (item.healthScore < 80) badgeClass = 'txtseo-text-warning';
            if (item.healthScore < 50) badgeClass = 'txtseo-text-danger';

            const card = document.createElement('div');
            card.className = 'txtseo-bg-white txtseo-p-4 txtseo-rounded-xl txtseo-shadow-sm txtseo-border txtseo-border-gray-100 txtseo-cursor-pointer txtseo-hover-shadow-md txtseo-transition txtseo-flex txtseo-justify-between txtseo-items-center txtseo-group';
            card.innerHTML = `
                <div class="txtseo-flex txtseo-flex-col txtseo-gap-1 txtseo-w-full">
                    <div class="txtseo-flex txtseo-justify-between txtseo-items-start">
                        <div>
                            <span class="txtseo-text-xs txtseo-font-semibold txtseo-text-gray-500 txtseo-block txtseo-mb-1">${item.date}</span>
                            <span class="txtseo-font-bold txtseo-text-gray-800">${item.targetKeyword}</span>
                        </div>
                        <div class="txtseo-flex txtseo-flex-col txtseo-items-end txtseo-gap-2">
                            <span class="txtseo-text-xs txtseo-font-bold txtseo-px-2 txtseo-py-1 txtseo-rounded txtseo-bg-gray-50 txtseo-border txtseo-border-gray-200 ${badgeClass}">
                                Skor: ${item.healthScore}
                            </span>
                        </div>
                    </div>
                    <div class="txtseo-flex txtseo-justify-between txtseo-items-center txtseo-mt-2">
                        <span class="txtseo-text-xs txtseo-text-gray-500"><i class="ph ph-text-aa"></i> ${item.wordCount} Kelime</span>
                        <button class="txtseo-delete-item-btn txtseo-text-gray-400 txtseo-hover-text-danger txtseo-transition txtseo-p-1" data-id="${item.id}" title="Sil">
                            <i class="ph ph-trash txtseo-text-lg"></i>
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
                inputSection.classList.add('txtseo-hidden');
                historySection.classList.add('txtseo-hidden');
                resultsSection.classList.remove('txtseo-hidden');
                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
            
            historyList.appendChild(card);
        });

        // Attach delete events
        document.querySelectorAll('.txtseo-delete-item-btn').forEach(btn => {
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

    if (clearAllHistoryBtn) {
        clearAllHistoryBtn.addEventListener('click', () => {
            if (confirm('Tüm geçmişi silmek istediğinize emin misiniz?')) {
                localStorage.removeItem(HISTORY_KEY);
                renderHistory();
            }
        });
    }

    if (backToInputBtn) {
        backToInputBtn.addEventListener('click', () => {
            resultsSection.classList.add('txtseo-hidden');
            inputSection.classList.remove('txtseo-hidden');
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
                copyInputBtn.classList.replace('txtseo-text-gray-700', 'txtseo-text-success');
                setTimeout(() => {
                    copyInputBtn.innerHTML = originalHTML;
                    copyInputBtn.classList.replace('txtseo-text-success', 'txtseo-text-gray-700');
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
        optionsPanel.classList.toggle('txtseo-hidden');
        optionsIcon.classList.toggle('txtseo-rotate-180');
    });

    // --- Tabs ---
    const tabBtns = document.querySelectorAll('.txtseo-tab-btn');
    const tabPanes = document.querySelectorAll('.txtseo-tab-pane');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Remove txtseo-active from all
            tabBtns.forEach(b => {
                b.classList.remove('txtseo-active', 'txtseo-border-primary', 'txtseo-text-primary');
                b.classList.add('txtseo-border-transparent', 'txtseo-text-gray-500');
            });
            tabPanes.forEach(p => p.classList.remove('txtseo-active', 'txtseo-hidden'));
            
            // Add txtseo-active to clicked
            btn.classList.add('txtseo-active', 'txtseo-border-primary', 'txtseo-text-primary');
            btn.classList.remove('txtseo-border-transparent', 'txtseo-text-gray-500');
            
            // Show target
            tabPanes.forEach(p => {
                if(p.id === btn.dataset.target) {
                    p.classList.add('txtseo-active');
                } else {
                    p.classList.add('txtseo-hidden');
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
            updateLoadingStep(1, "İçerik yapısı inceleniyor...");
            
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
            
            updateLoadingStep(3, "SEO stratejisi oluşturuluyor...");
            setTimeout(() => {
                updateLoadingStep(4, "Metin iyileştiriliyor...");
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
        loadingOverlay.classList.remove('txtseo-hidden');
        loadingOverlay.classList.add('txtseo-flex');
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
                icon.className = 'ph-fill ph-check-circle txtseo-text-success';
                li.classList.add('txtseo-text-gray-900');
                li.classList.remove('txtseo-text-primary');
            } else if (idx === stepIndex) {
                icon.className = 'ph-fill ph-spinner-gap txtseo-animate-spin txtseo-text-primary';
                li.classList.add('txtseo-text-primary');
                li.classList.remove('txtseo-text-gray-900');
            } else {
                icon.className = 'ph ph-circle';
                li.classList.remove('txtseo-text-gray-900', 'txtseo-text-primary');
            }
        });
    }

    function finishLoading() {
        loadingProgress.style.width = '100%';
        loadingPercent.textContent = '100%';
        setTimeout(() => {
            loadingOverlay.classList.add('txtseo-hidden');
            loadingOverlay.classList.remove('txtseo-flex');
            inputSection.classList.add('txtseo-hidden');
            historySection.classList.add('txtseo-hidden');
            resultsSection.classList.remove('txtseo-hidden');
        }, 300);
    }

    function stopLoading() {
        loadingOverlay.classList.add('txtseo-hidden');
        loadingOverlay.classList.remove('txtseo-flex');
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
        const healthScore = parseFloat(ai.analiz.saglik_skoru || 0) || 0;
        document.getElementById('healthScoreText').textContent = healthScore;
        const circle = document.getElementById('healthScoreCircle');
        if (circle) {
            circle.setAttribute('stroke-dasharray', `${healthScore}, 100`);
            circle.classList.remove('txtseo-text-success', 'txtseo-text-warning', 'txtseo-text-danger');
            if (healthScore >= 80) circle.classList.add('txtseo-text-success');
            else if (healthScore >= 50) circle.classList.add('txtseo-text-warning');
            else circle.classList.add('txtseo-text-danger');
        }

        // Modal Top Summary
        const modalTotalScoreText = document.getElementById('modalTotalScoreText');
        const modalScoreStatusBadge = document.getElementById('modalScoreStatusBadge');
        const modalScoreSummary = document.getElementById('modalScoreSummary');
        
        if (modalTotalScoreText && modalScoreStatusBadge && modalScoreSummary) {
            modalTotalScoreText.textContent = `Toplam Skor: ${healthScore} / 100`;
            modalScoreSummary.classList.remove('txtseo-hidden');
            
            modalScoreStatusBadge.className = 'txtseo-text-xs txtseo-font-bold txtseo-px-3 txtseo-py-1_5 txtseo-rounded-lg';
            if (healthScore >= 80) {
                modalScoreStatusBadge.textContent = 'Harika';
                modalScoreStatusBadge.classList.add('txtseo-bg-green-100', 'txtseo-text-green-800');
            } else if (healthScore >= 50) {
                modalScoreStatusBadge.textContent = 'Geliştirilebilir';
                modalScoreStatusBadge.classList.add('txtseo-bg-yellow-100', 'txtseo-text-yellow-800');
            } else {
                modalScoreStatusBadge.textContent = 'Zayıf';
                modalScoreStatusBadge.classList.add('txtseo-bg-red-100', 'txtseo-text-red-800');
            }
        }

        // Score Breakdown
        const breakdown = ai.analiz.skor_dagilimi || ai.skor_dagilimi || {};
        
        const updateScoreCat = (id, score, maxScore) => {
            const el = document.getElementById(id);
            if (!el) return;
            const badge = el.querySelector('.txtseo-badge-score');
            const missingSpan = el.querySelector('.txtseo-missing-score');
            const progressContainer = el.querySelector('.txtseo-progress-bar-container');
            const progressBar = el.querySelector('.txtseo-progress-bar');
            
            let numScore = parseFloat(score);
            if (!isNaN(numScore)) {
                badge.textContent = `${numScore} / ${maxScore} Puan`;
                progressContainer.classList.remove('txtseo-hidden');
                
                const percent = Math.max(0, Math.min(100, (numScore / maxScore) * 100));
                progressBar.style.width = `${percent}%`;
                
                progressBar.className = 'txtseo-h-1.5 txtseo-rounded-full txtseo-progress-bar';
                badge.className = 'txtseo-text-xs txtseo-font-bold txtseo-px-2 txtseo-py-1 txtseo-rounded-lg txtseo-badge-score';
                
                if (percent >= 80) {
                    progressBar.classList.add('txtseo-bg-success');
                    badge.classList.add('txtseo-bg-green-100', 'txtseo-text-green-800');
                } else if (percent >= 50) {
                    progressBar.classList.add('txtseo-bg-warning');
                    badge.classList.add('txtseo-bg-yellow-100', 'txtseo-text-yellow-800');
                } else {
                    progressBar.classList.add('txtseo-bg-danger');
                    badge.classList.add('txtseo-bg-red-100', 'txtseo-text-red-800');
                }
                
                if (missingSpan) {
                    let missing = maxScore - numScore;
                    if (missing > 0) {
                        missingSpan.textContent = `(-${missing} Puan)`;
                        missingSpan.classList.remove('txtseo-hidden');
                    } else {
                        missingSpan.classList.add('txtseo-hidden');
                    }
                }
            } else {
                badge.textContent = `Maks. ${maxScore} Puan`;
                progressContainer.classList.add('txtseo-hidden');
                badge.className = 'txtseo-text-xs txtseo-font-bold txtseo-px-2 txtseo-py-1 txtseo-rounded-lg txtseo-badge-score txtseo-bg-gray-100 txtseo-text-gray-500';
                if (missingSpan) {
                    missingSpan.classList.add('txtseo-hidden');
                }
            }
        };

        updateScoreCat('scoreCat1', breakdown.anahtar_kelime_uyumu, 25);
        updateScoreCat('scoreCat2', breakdown.okunabilirlik, 25);
        updateScoreCat('scoreCat3', breakdown.icerik_yapisi, 20);
        updateScoreCat('scoreCat4', breakdown.bilgi_yogunlugu, 15);
        updateScoreCat('scoreCat5', breakdown.ikna_edicilik, 15);

        console.log("[UI LOG] 4.1. Sağlık skoru ve başlık verileri yazıldı.");

        // Tab 1: Anatomy & Readability
        const anatomy = telemetry.anatomy || { headings: {} };
        const readability = telemetry.readability || {};
        
        renderEnhancedAnatomy(telemetry);

        const getBadgeClass = (status) => {
            if (status === 'success') return 'txtseo-bg-green-100 txtseo-text-green-700 txtseo-border txtseo-border-green-200';
            if (status === 'warning') return 'txtseo-bg-yellow-100 txtseo-text-yellow-700 txtseo-border txtseo-border-yellow-200';
            return 'txtseo-bg-red-100 txtseo-text-red-700 txtseo-border txtseo-border-red-200';
        };
        
        const getBadgeLabel = (status) => {
            if (status === 'success') return 'İyi';
            if (status === 'warning') return 'Orta';
            return 'Zayıf';
        };

        const atesmanFb = readability.atesman_feedback || {};
        const complexFb = readability.complex_words_feedback || {};
        const transitionFb = readability.transition_words?.feedback || {};
        const passiveFb = readability.passive_voice?.feedback || {};

        const readabilityStatsEl = document.getElementById('readabilityStats');
        // Kapsayıcı sınıflarını (grid yapısını) bozmamak için className ezilmez.
        readabilityStatsEl.innerHTML = `
            <div class="txtseo-bg-gray-50 txtseo-p-3 txtseo-rounded-lg txtseo-border txtseo-border-gray-100">
                <div class="txtseo-flex txtseo-justify-between txtseo-items-start">
                    <h4 class="txtseo-text-sm txtseo-font-bold txtseo-text-slate-700">Okunabilirlik Puanı: <span class="txtseo-text-primary">${readability.atesman_index || '-'}</span></h4>
                    <span class="txtseo-text-[10px] txtseo-font-bold txtseo-px-2 txtseo-py-0.5 txtseo-rounded-full txtseo-uppercase txtseo-tracking-wider ${getBadgeClass(atesmanFb.status)}">${atesmanFb.label || getBadgeLabel(atesmanFb.status)}</span>
                </div>
                <p class="txtseo-text-xs txtseo-leading-relaxed txtseo-text-gray-600 txtseo-mt-2 txtseo-bg-gray-50 txtseo-p-2.5 txtseo-rounded-lg txtseo-border txtseo-border-gray-100">${atesmanFb.advice || '-'}</p>
            </div>
            
            <div class="txtseo-bg-gray-50 txtseo-p-3 txtseo-rounded-lg txtseo-border txtseo-border-gray-100">
                <div class="txtseo-flex txtseo-justify-between txtseo-items-start">
                    <h4 class="txtseo-text-sm txtseo-font-bold txtseo-text-slate-700">Karmaşık Kelime: <span class="txtseo-text-primary">%${readability.complex_words_percentage || '-'}</span> <span class="txtseo-text-[10px] txtseo-font-normal txtseo-text-slate-500 txtseo-block txtseo-sm-inline txtseo-mt-1 txtseo-sm-mt-0">(Toplam ${readability.complex_polysyllabic_words_count || 0} adet 3+ heceli kelime)</span></h4>
                    <span class="txtseo-text-[10px] txtseo-font-bold txtseo-px-2 txtseo-py-0.5 txtseo-rounded-full txtseo-uppercase txtseo-tracking-wider ${getBadgeClass(complexFb.status)}">${getBadgeLabel(complexFb.status)}</span>
                </div>
                <p class="txtseo-text-xs txtseo-leading-relaxed txtseo-text-gray-600 txtseo-mt-2 txtseo-bg-gray-50 txtseo-p-2.5 txtseo-rounded-lg txtseo-border txtseo-border-gray-100">${complexFb.advice || '-'}</p>
            </div>
            
            <div class="txtseo-bg-gray-50 txtseo-p-3 txtseo-rounded-lg txtseo-border txtseo-border-gray-100">
                <div class="txtseo-flex txtseo-justify-between txtseo-items-start">
                    <h4 class="txtseo-text-sm txtseo-font-bold txtseo-text-slate-700">Geçiş Kelimeleri: <span class="txtseo-text-primary">%${readability.transition_words?.transition_sentence_ratio_percentage || '-'}</span> <span class="txtseo-text-[10px] txtseo-font-normal txtseo-text-slate-500 txtseo-block txtseo-sm-inline txtseo-mt-1 txtseo-sm-mt-0">(Toplam ${readability.transition_words?.matched_count || 0} adet bağlaç kullanılmış)</span></h4>
                    <span class="txtseo-text-[10px] txtseo-font-bold txtseo-px-2 txtseo-py-0.5 txtseo-rounded-full txtseo-uppercase txtseo-tracking-wider ${getBadgeClass(transitionFb.status)}">${getBadgeLabel(transitionFb.status)}</span>
                </div>
                <p class="txtseo-text-xs txtseo-leading-relaxed txtseo-text-gray-600 txtseo-mt-2 txtseo-bg-gray-50 txtseo-p-2.5 txtseo-rounded-lg txtseo-border txtseo-border-gray-100">${transitionFb.advice || '-'}</p>
            </div>
            
            <div class="txtseo-bg-gray-50 txtseo-p-3 txtseo-rounded-lg txtseo-border txtseo-border-gray-100">
                <div class="txtseo-flex txtseo-justify-between txtseo-items-start">
                    <h4 class="txtseo-text-sm txtseo-font-bold txtseo-text-slate-700">Pasif Cümle: <span class="txtseo-text-primary">%${readability.passive_voice?.passive_voice_percentage || '-'}</span> <span class="txtseo-text-[10px] txtseo-font-normal txtseo-text-slate-500 txtseo-block txtseo-sm-inline txtseo-mt-1 txtseo-sm-mt-0">(Toplam ${readability.passive_voice?.passive_sentences_count || 0} adet edilgen cümle)</span></h4>
                    <span class="txtseo-text-[10px] txtseo-font-bold txtseo-px-2 txtseo-py-0.5 txtseo-rounded-full txtseo-uppercase txtseo-tracking-wider ${getBadgeClass(passiveFb.status)}">${getBadgeLabel(passiveFb.status)}</span>
                </div>
                <p class="txtseo-text-xs txtseo-leading-relaxed txtseo-text-gray-600 txtseo-mt-2 txtseo-bg-gray-50 txtseo-p-2.5 txtseo-rounded-lg txtseo-border txtseo-border-gray-100">${passiveFb.advice || '-'}</p>
            </div>
        `;

        // Render Heading Tree
        const headingTreeContainer = document.getElementById('headingTreeContainer');
        if (headingTreeContainer) {
            const structureTree = telemetry.anatomy?.headings?.structure_tree || [];
            if (structureTree.length === 0) {
                headingTreeContainer.innerHTML = '<span class="txtseo-text-xs txtseo-text-slate-500 txtseo-italic">Başlık hiyerarşisi bulunamadı.</span>';
            } else {
                let treeHtml = '';
                structureTree.forEach(heading => {
                    const level = heading.level;
                    const text = heading.text;
                    const wordsBeforeNext = heading.word_count_before_next || 0;
                    
                    // Determine indent based on level
                    const indentClass = level === 1 ? 'txtseo-ml-0' : (level === 2 ? 'txtseo-ml-6' : (level === 3 ? 'txtseo-ml-12' : 'txtseo-ml-16'));
                    const iconColor = level === 1 ? 'txtseo-text-indigo-600' : (level === 2 ? 'txtseo-text-blue-500' : 'txtseo-text-slate-400');
                    const badgeClass = level === 1 ? 'txtseo-bg-indigo-100 txtseo-text-indigo-700' : (level === 2 ? 'txtseo-bg-blue-100 txtseo-text-blue-700' : 'txtseo-bg-slate-200 txtseo-text-slate-700');
                    const lineClass = level > 1 ? 'txtseo-border-l-2 txtseo-border-slate-200 txtseo-pl-4 txtseo-relative before:content-[\'\'] before:txtseo-absolute before:txtseo-left-[-2px] before:txtseo-top-4 before:txtseo-w-4 before:txtseo-h-[2px] before:txtseo-bg-slate-200' : '';

                    treeHtml += `
                        <div class="txtseo-flex txtseo-items-start txtseo-gap-2 txtseo-py-1.5 ${indentClass} ${lineClass}">
                            <span class="txtseo-text-[9px] txtseo-font-bold txtseo-px-1.5 txtseo-py-0.5 txtseo-rounded ${badgeClass} txtseo-mt-0.5 txtseo-shrink-0">H${level}</span>
                            <div class="txtseo-flex txtseo-flex-col">
                                <span class="txtseo-font-semibold txtseo-text-slate-800 txtseo-text-sm txtseo-leading-tight">${text}</span>
                                <span class="txtseo-text-[10px] txtseo-text-slate-500 txtseo-font-medium txtseo-flex txtseo-items-center txtseo-gap-1 txtseo-mt-0.5">
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
                        <td class="txtseo-px-4 txtseo-py-3 txtseo-font-medium txtseo-text-gray-900">${kw}</td>
                        <td class="txtseo-px-4 txtseo-py-3 txtseo-text-center">
                            <span class="txtseo-bg-blue-100 txtseo-text-blue-800 txtseo-text-xs txtseo-font-semibold txtseo-px-2.5 txtseo-py-0.5 txtseo-rounded-full">+${count}</span>
                        </td>
                    </tr>
                `;
            }
        }

        // Tab 2: Detected Contextual Keywords
        const detectedKeywordsList = document.getElementById('detectedKeywordsList');
        if (detectedKeywordsList) {
            detectedKeywordsList.innerHTML = '';
            const allDetected = [];
            unigrams.slice(0, 3).forEach(u => allDetected.push(u.term));
            bigrams.slice(0, 3).forEach(b => allDetected.push(b.term));
            if (allDetected.length === 0) {
                detectedKeywordsList.innerHTML = '<span class="txtseo-text-xs txtseo-text-gray-500">Anahtar kelime tespit edilemedi.</span>';
            } else {
                allDetected.forEach(kw => {
                    detectedKeywordsList.innerHTML += `<span class="txtseo-bg-green-100 txtseo-text-green-800 txtseo-text-xs txtseo-font-medium txtseo-px-2.5 txtseo-py-1 txtseo-rounded txtseo-border txtseo-border-green-200">${kw}</span>`;
                });
            }
        }

        // Tab 2: Semantic Gaps
        const gapsContainer = document.getElementById('semanticGapsList');
        gapsContainer.innerHTML = '';
        const gaps = ai.strateji?.semantik_bosluklar || [];
        gaps.forEach(gap => {
            gapsContainer.innerHTML += `<span class="txtseo-bg-yellow-100 txtseo-text-yellow-800 txtseo-text-xs txtseo-font-medium txtseo-px-2.5 txtseo-py-1 txtseo-rounded txtseo-border txtseo-border-yellow-200">${gap}</span>`;
        });

        // Tab 2: PAA
        const paaList = document.getElementById('paaList');
        paaList.innerHTML = '';
        const paas = ai.strateji?.paa_hedefleri || [];
        paas.forEach(paa => {
            paaList.innerHTML += `
                <li class="txtseo-flex txtseo-gap-3 txtseo-bg-gray-50 txtseo-p-3 txtseo-rounded-lg txtseo-border txtseo-border-gray-100">
                    <i class="ph-fill ph-check-circle txtseo-text-purple-500 txtseo-mt-0.5"></i>
                    <span class="txtseo-text-sm txtseo-font-medium">${paa}</span>
                </li>`;
        });
        
        console.log("[UI LOG] 4.4. Tab 2 (Strateji & Kotalar) render edildi.");

        // Tab 3: Roadmap
        const roadmapList = document.getElementById('roadmapList');
        roadmapList.innerHTML = '';
        const steps = ai.entegrasyon?.adim_adim_rehber || [];
        steps.forEach((step, idx) => {
            const cleanStep = step.replace(/^Adım\s*\d+[\s\:\-\.]*/i, '');
            roadmapList.innerHTML += `
                <div class="txtseo-relative">
                    <span class="txtseo-absolute txtseo--left-[35px] txtseo-top-1 txtseo-h-5 txtseo-w-5 txtseo-rounded-full txtseo-bg-primary txtseo-flex txtseo-items-center txtseo-justify-center txtseo-text-white txtseo-text-[10px] txtseo-font-bold txtseo-ring-4 txtseo-ring-white txtseo-pulse-circle">
                        ${idx + 1}
                    </span>
                    <p class="txtseo-text-sm txtseo-text-gray-700 txtseo-leading-relaxed txtseo-font-medium txtseo-bg-gray-50 txtseo-p-3 txtseo-rounded-lg txtseo-border txtseo-border-gray-100">${cleanStep}</p>
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

        // Ensure history section is txtseo-hidden when results are populated
        historySection.classList.add('txtseo-hidden');
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
        const textAreas = document.querySelector('#tab4 .txtseo-grid');
        const diffCont = document.getElementById('diffContainer');
        
        if (diffMode) {
            textAreas.classList.add('txtseo-hidden');
            diffCont.classList.remove('txtseo-hidden');
            toggleDiffBtn.textContent = 'Yan Yana Görünüm';
            
        } else {
            textAreas.classList.remove('txtseo-hidden');
            diffCont.classList.add('txtseo-hidden');
            toggleDiffBtn.textContent = 'Değişiklikleri Karşılaştır';
            
        }
    });

    // --- Copy Button ---
    const copyBtn = document.getElementById('copyBtn');
    copyBtn.addEventListener('click', () => {
        const text = document.getElementById('optimizedTextArea').value;
        navigator.clipboard.writeText(text).then(() => {
            const originalHTML = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="ph-fill ph-check"></i> Kopyalandı';
            copyBtn.classList.replace('txtseo-bg-primary', 'bg-success');
            setTimeout(() => {
                copyBtn.innerHTML = originalHTML;
                copyBtn.classList.replace('bg-success', 'txtseo-bg-primary');
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
            pdfModalIcon.className = 'w-10 h-10 txtseo-rounded-full txtseo-flex txtseo-items-center txtseo-justify-center txtseo-text-xl txtseo-bg-yellow-100 txtseo-text-warning';
            pdfModalMessage.innerHTML = '⚠️ Bu analiz raporunu daha önce indirdiniz. Yeniden indirmek istiyor musunuz?';
            confirmBtnText.textContent = 'Tekrar İndir';
        } else {
            pdfModalIcon.className = 'w-10 h-10 txtseo-rounded-full txtseo-flex txtseo-items-center txtseo-justify-center txtseo-text-xl txtseo-bg-blue-100 txtseo-text-primary';
            pdfModalMessage.innerHTML = 'Tüm analiz sonuçlarını, kelime stratejisini ve önerilen yeni metni içeren detaylı PDF raporunu indirmek üzeresiniz.';
            confirmBtnText.textContent = 'İndir';
        }
        
        pdfModal.classList.remove('txtseo-hidden');
    });

    closePdfModalBtn.addEventListener('click', () => {
        pdfModal.classList.add('txtseo-hidden');
    });

    confirmPdfDownloadBtn.addEventListener('click', () => {
        pdfModal.classList.add('txtseo-hidden');
        if (typeof generatePDF === 'function') {
            hasDownloadedPdf = true;
            const chartCanvas = document.getElementById('ngramChart');
            generatePDF(apiData, chartCanvas);
        } else {
            alert('PDF modülü yüklenemedi.');
        }
    });

    // --- Score Info Modal ---
    const healthScoreCard = document.getElementById('healthScoreCard');
    const scoreInfoModal = document.getElementById('scoreInfoModal');
    const closeScoreInfoModalBtn = document.getElementById('closeScoreInfoModalBtn');

    if (scoreInfoModal && closeScoreInfoModalBtn) {
        if (healthScoreCard) {
            healthScoreCard.addEventListener('click', () => {
                scoreInfoModal.classList.remove('txtseo-hidden');
            });
        }

        closeScoreInfoModalBtn.addEventListener('click', () => {
            scoreInfoModal.classList.add('txtseo-hidden');
        });

        // Close when clicking outside modal content
        scoreInfoModal.addEventListener('click', (e) => {
            if (e.target === scoreInfoModal) {
                scoreInfoModal.classList.add('txtseo-hidden');
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
            const longSentences = sentenceMetrics.sentences_over_25_words || 0;
            const infoDensity = lexical.stopwords_and_density?.information_density_percentage || 0;
            const ttr = lexical.lexical_diversity?.type_token_ratio || 0;
            const powerWordsCount = intent.power_words?.matched_count || 0;

            heroCardsContainer.innerHTML = `
                <!-- Kelime Hacmi -->
                <div class="txtseo-bg-gray-50 txtseo-p-3.5 txtseo-rounded-xl txtseo-border txtseo-border-slate-100 txtseo-flex txtseo-flex-col txtseo-justify-between txtseo-items-center txtseo-text-center txtseo-hover-border-blue-200 txtseo-transition">
                    <span class="txtseo-text-[10px] txtseo-font-bold txtseo-text-gray-400 txtseo-uppercase txtseo-tracking-wider txtseo-flex txtseo-items-center txtseo-justify-center txtseo-gap-1.5">
                        Kelime Hacmi
                        <i class="ph ph-text-aa txtseo-text-slate-400"></i>
                    </span>
                    <span class="txtseo-text-2xl txtseo-font-extrabold txtseo-text-slate-800 txtseo-mt-1">${wordCount.toLocaleString()}</span>
                    <span class="txtseo-text-[11px] txtseo-text-slate-500 txtseo-mt-0.5 txtseo-flex txtseo-items-center txtseo-gap-1 txtseo-font-medium">
                        <i class="ph ph-clock"></i> ~${readingTimeMin} dk okuma
                    </span>
                </div>

                <!-- Cümle & Ritim -->
                <div class="txtseo-bg-gray-50 txtseo-p-3.5 txtseo-rounded-xl txtseo-border txtseo-border-slate-100 txtseo-flex txtseo-flex-col txtseo-justify-between txtseo-items-center txtseo-text-center txtseo-hover-border-blue-200 txtseo-transition">
                    <span class="txtseo-text-[10px] txtseo-font-bold txtseo-text-gray-400 txtseo-uppercase txtseo-tracking-wider txtseo-flex txtseo-items-center txtseo-justify-center txtseo-gap-1.5">
                        Cümle & Akış
                        <i class="ph ph-rows txtseo-text-slate-400"></i>
                    </span>
                    <span class="txtseo-text-2xl txtseo-font-extrabold txtseo-text-slate-800 txtseo-mt-1">${anatomy.sentence_count || 0}</span>
                    <span class="txtseo-text-[11px] txtseo-text-slate-500 txtseo-mt-0.5 txtseo-font-medium">
                        Ort. ${sentenceMetrics.avg_sentence_length_words || 0} kelime/cümle
                    </span>
                </div>

                <!-- Başlık İskeleti -->
                <div class="txtseo-bg-gray-50 txtseo-p-3.5 txtseo-rounded-xl txtseo-border txtseo-border-slate-100 txtseo-flex txtseo-flex-col txtseo-justify-between txtseo-items-center txtseo-text-center txtseo-hover-border-blue-200 txtseo-transition">
                    <span class="txtseo-text-[10px] txtseo-font-bold txtseo-text-gray-400 txtseo-uppercase txtseo-tracking-wider txtseo-flex txtseo-items-center txtseo-justify-center txtseo-gap-1.5">
                        Başlık İskeleti
                        <i class="ph ph-tree-structure txtseo-text-slate-400"></i>
                    </span>
                    <span class="txtseo-text-2xl txtseo-font-extrabold txtseo-mt-1 ${h1Count === 1 ? 'txtseo-text-emerald-600' : 'txtseo-text-rose-600'}">
                        ${h1Count === 1 ? '1x H1' : `${h1Count}x H1`}
                    </span>
                    <span class="txtseo-text-[11px] txtseo-text-slate-500 txtseo-mt-0.5 txtseo-font-medium">
                        ${h2Count} H2 • ${h3Count} H3 başlık
                    </span>
                </div>

                <!-- Paragraf & Mobil Blok -->
                <div class="txtseo-bg-gray-50 txtseo-p-3.5 txtseo-rounded-xl txtseo-border txtseo-border-slate-100 txtseo-flex txtseo-flex-col txtseo-justify-between txtseo-items-center txtseo-text-center txtseo-hover-border-blue-200 txtseo-transition">
                    <span class="txtseo-text-[10px] txtseo-font-bold txtseo-text-gray-400 txtseo-uppercase txtseo-tracking-wider txtseo-flex txtseo-items-center txtseo-justify-center txtseo-gap-1.5">
                        Uzun Paragraflar
                        <i class="ph ph-paragraphs txtseo-text-slate-400"></i>
                    </span>
                    <span class="txtseo-text-2xl txtseo-font-extrabold txtseo-text-slate-800 txtseo-mt-1">${anatomy.paragraph_count || 0}</span>
                    <span class="txtseo-text-[11px] txtseo-font-semibold txtseo-mt-0.5 txtseo-flex txtseo-items-center txtseo-gap-1 ${monolithicCount === 0 ? 'txtseo-text-emerald-600' : 'txtseo-text-rose-600'}">
                        <i class="ph-fill ${monolithicCount === 0 ? 'ph-check-circle' : 'ph-warning-circle'}"></i>
                        ${monolithicCount === 0 ? 'İyi Paragraf Düzeni' : `${monolithicCount} Uzun Paragraf`}
                    </span>
                </div>
                
                <!-- Nefes Kesen Uzun Cümle -->
                <div class="txtseo-bg-gray-50 txtseo-p-3.5 txtseo-rounded-xl txtseo-border txtseo-border-slate-100 txtseo-flex txtseo-flex-col txtseo-justify-between txtseo-items-center txtseo-text-center txtseo-hover-border-blue-200 txtseo-transition">
                    <span class="txtseo-text-[10px] txtseo-font-bold txtseo-text-gray-400 txtseo-uppercase txtseo-tracking-wider txtseo-flex txtseo-items-center txtseo-justify-center txtseo-gap-1.5">
                        Uzun Cümleler
                        <i class="ph ph-text-b txtseo-text-slate-400"></i>
                    </span>
                    <span class="txtseo-text-2xl txtseo-font-extrabold txtseo-mt-1 ${longSentences > 0 ? 'txtseo-text-amber-600' : 'txtseo-text-emerald-600'}">${longSentences}</span>
                    <span class="txtseo-text-[11px] txtseo-text-slate-500 txtseo-mt-0.5 txtseo-font-medium">
                        25+ Kelimelik Cümleler
                    </span>
                </div>
                
                <!-- Saf Bilgi Yoğunluğu -->
                <div class="txtseo-bg-gray-50 txtseo-p-3.5 txtseo-rounded-xl txtseo-border txtseo-border-slate-100 txtseo-flex txtseo-flex-col txtseo-justify-between txtseo-items-center txtseo-text-center txtseo-hover-border-blue-200 txtseo-transition">
                    <span class="txtseo-text-[10px] txtseo-font-bold txtseo-text-gray-400 txtseo-uppercase txtseo-tracking-wider txtseo-flex txtseo-items-center txtseo-justify-center txtseo-gap-1.5">
                        Bilgi Yoğunluğu
                        <i class="ph ph-brain txtseo-text-slate-400"></i>
                    </span>
                    <span class="txtseo-text-2xl txtseo-font-extrabold txtseo-mt-1 ${infoDensity >= 55 ? 'txtseo-text-purple-600' : 'txtseo-text-amber-600'}">%${infoDensity}</span>
                    <span class="txtseo-text-[11px] txtseo-text-slate-500 txtseo-mt-0.5 txtseo-font-medium">
                        Faydalı Bilgi Oranı
                    </span>
                </div>
                
                <!-- İkna Gücü -->
                <div class="txtseo-bg-gray-50 txtseo-p-3.5 txtseo-rounded-xl txtseo-border txtseo-border-slate-100 txtseo-flex txtseo-flex-col txtseo-justify-between txtseo-items-center txtseo-text-center txtseo-hover-border-blue-200 txtseo-transition">
                    <span class="txtseo-text-[10px] txtseo-font-bold txtseo-text-gray-400 txtseo-uppercase txtseo-tracking-wider txtseo-flex txtseo-items-center txtseo-justify-center txtseo-gap-1.5">
                        İkna Edici Kelimeler
                        <i class="ph ph-lightning txtseo-text-slate-400"></i>
                    </span>
                    <span class="txtseo-text-2xl txtseo-font-extrabold txtseo-mt-1 ${powerWordsCount > 0 ? 'txtseo-text-emerald-600' : 'txtseo-text-slate-800'}">${powerWordsCount}</span>
                    <span class="txtseo-text-[11px] txtseo-text-slate-500 txtseo-mt-0.5 txtseo-font-medium">
                        Eyleme Çağrı (CTA)
                    </span>
                </div>
                
                <!-- Kelime Zenginliği -->
                <div class="txtseo-bg-gray-50 txtseo-p-3.5 txtseo-rounded-xl txtseo-border txtseo-border-slate-100 txtseo-flex txtseo-flex-col txtseo-justify-between txtseo-items-center txtseo-text-center txtseo-hover-border-blue-200 txtseo-transition">
                    <span class="txtseo-text-[10px] txtseo-font-bold txtseo-text-gray-400 txtseo-uppercase txtseo-tracking-wider txtseo-flex txtseo-items-center txtseo-justify-center txtseo-gap-1.5">
                        Kelime Zenginliği
                        <i class="ph ph-books txtseo-text-slate-400"></i>
                    </span>
                    <span class="txtseo-text-2xl txtseo-font-extrabold txtseo-mt-1 ${ttr >= 0.4 ? 'txtseo-text-emerald-600' : 'txtseo-text-amber-600'}">${ttr}</span>
                    <span class="txtseo-text-[11px] txtseo-text-slate-500 txtseo-mt-0.5 txtseo-font-medium">
                        ${ttr >= 0.4 ? 'Geniş Kelime Dağarcığı' : 'Tekrarlayan Kelimeler'}
                    </span>
                </div>
            `;
        }
    }

});

})();
