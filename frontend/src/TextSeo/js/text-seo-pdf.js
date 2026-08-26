window.generatePDF = function(apiData, chartCanvas) {
    if (!apiData || !apiData.telemetry_summary || !apiData.ai_dimensions) {
        alert("Geçersiz analiz verisi. Lütfen tekrar analiz edin.");
        return;
    }

    // 1. Data Extraction
    const rawTelemetry = apiData.telemetry_summary || {};
    const telemetry = rawTelemetry.telemetry_data || rawTelemetry;
    const readability = telemetry.readability || {};
    const anatomy = telemetry.anatomy || { headings: {} };
    const sentenceMetrics = anatomy.sentence_metrics || {};
    const paragraphMetrics = anatomy.paragraph_metrics || {};
    const lexical = telemetry.lexical_and_semantics || {};
    const intent = telemetry.intent_and_action || {};

    const ai = apiData.ai_dimensions || {};
    const formData = apiData.form_data || apiData.inputs || {};
    const targetKeyword = formData.keyword || formData.focus_keyword || 'Belirtilmedi';

    // Score & Breakdown
    const healthScore = parseFloat(ai.analiz?.saglik_skoru || ai.skor_dagilimi?.saglik_skoru || 0) || 0;
    let healthClass = 'danger';
    if (healthScore >= 80) healthClass = 'success';
    else if (healthScore >= 50) healthClass = 'warning';

    const breakdown = ai.analiz?.skor_dagilimi || ai.skor_dagilimi || {};
    const s1 = parseFloat(breakdown.anahtar_kelime_uyumu) || 0;
    const s2 = parseFloat(breakdown.okunabilirlik) || 0;
    const s3 = parseFloat(breakdown.icerik_yapisi) || 0;
    const s4 = parseFloat(breakdown.bilgi_yogunlugu) || 0;
    const s5 = parseFloat(breakdown.ikna_edicilik) || 0;

    const breakdownHTML = `
        <div class="pdf-breakdown-grid">
            <div class="pdf-breakdown-col"><span class="val">${s1}/25</span><span class="lbl">Anahtar K.</span></div>
            <div class="pdf-breakdown-col"><span class="val">${s2}/25</span><span class="lbl">Okunabilirlik</span></div>
            <div class="pdf-breakdown-col"><span class="val">${s3}/20</span><span class="lbl">İçerik Yap.</span></div>
            <div class="pdf-breakdown-col"><span class="val">${s4}/15</span><span class="lbl">Bilgi Yoğ.</span></div>
            <div class="pdf-breakdown-col"><span class="val">${s5}/15</span><span class="lbl">İkna Edicilik</span></div>
        </div>
    `;

    // Executive Summary
    const aiSummary = ai.analiz?.ozet || 'Özet bulunamadı.';

    // Anatomy & 8-Grid
    const wordCount = anatomy.word_count || 0;
    const sentenceCount = anatomy.sentence_count || 0;
    const h1Count = anatomy.headings?.h1_count || 0;
    const h2Count = anatomy.headings?.h2_count || 0;
    const h3Count = anatomy.headings?.h3_count || 0;
    const paragraphCount = anatomy.paragraph_count || 0;
    const longParagraphs = paragraphMetrics.monolithic_paragraphs_count || 0;
    const longSentences = sentenceMetrics.sentences_over_25_words || 0;
    const powerWords = intent.power_words?.matched_count || 0;
    const ttr = lexical.lexical_diversity?.type_token_ratio || 0;
    const readingTime = Math.ceil(wordCount / 200);

    const anatomyGridHTML = `
        <div class="pdf-grid-4">
            <div class="pdf-stat-card"><div class="val">${wordCount}</div><div class="lbl">Kelime Hacmi</div></div>
            <div class="pdf-stat-card"><div class="val">${sentenceCount}</div><div class="lbl">Cümle Sayısı</div></div>
            <div class="pdf-stat-card"><div class="val">${h1Count+h2Count+h3Count}</div><div class="lbl">Toplam Başlık</div></div>
            <div class="pdf-stat-card"><div class="val">${readingTime} dk</div><div class="lbl">Okuma Süresi</div></div>
            
            <div class="pdf-stat-card ${longParagraphs > 0 ? 'alert' : ''}"><div class="val">${paragraphCount}</div><div class="lbl">Paragraf Sayısı</div></div>
            <div class="pdf-stat-card ${longSentences > 0 ? 'alert' : ''}"><div class="val">${longSentences}</div><div class="lbl">Uzun Cümle</div></div>
            <div class="pdf-stat-card"><div class="val">${powerWords}</div><div class="lbl">İkna Edici Kelime</div></div>
            <div class="pdf-stat-card"><div class="val">${ttr}</div><div class="lbl">Kelime Zenginliği</div></div>
        </div>
    `;

    // Heading Tree
    let headingTreeHTML = '<ul class="pdf-heading-tree">';
    const hTree = anatomy.headings?.structure_tree || [];
    if (hTree.length > 0) {
        hTree.forEach(h => {
            const indent = (h.level - 1) * 15;
            headingTreeHTML += `<li style="margin-left: ${indent}px;"><span class="tag h${h.level}">H${h.level}</span> ${h.text}</li>`;
        });
    } else {
        headingTreeHTML += '<li><span style="color:#94a3b8; font-style:italic;">Başlık hiyerarşisi bulunamadı.</span></li>';
    }
    headingTreeHTML += '</ul>';

    // Chart
    let chartImageURI = '';
    try {
        if (chartCanvas && chartCanvas instanceof HTMLCanvasElement && chartCanvas.width > 0) {
            chartImageURI = chartCanvas.toDataURL('image/png', 1.0);
        }
    } catch (e) {
        console.warn("Grafik render edilemedi", e);
    }

    // Keyword Quotas
    let quotasTableHTML = '';
    const adetler = ai.strateji?.eklenecek_kelime_adetleri || {};
    for (const [kw, count] of Object.entries(adetler)) {
        quotasTableHTML += `<tr><td>${kw}</td><td style="text-align: center;"><span class="pdf-badge-green">+${count}</span></td></tr>`;
    }

    // Detected & Semantic Gaps
    let detectedHTML = '';
    const kwFreq = telemetry.keywords_and_frequency || {};
    const unigrams = kwFreq.top_ngrams?.unigrams || [];
    const bigrams = kwFreq.top_ngrams?.bigrams || [];
    const allDetected = [];
    unigrams.slice(0, 3).forEach(u => allDetected.push(u.term));
    bigrams.slice(0, 3).forEach(b => allDetected.push(b.term));
    
    if (allDetected.length > 0) {
        allDetected.forEach(kw => { detectedHTML += `<span class="pdf-badge">${kw}</span>`; });
    } else {
        detectedHTML = '<span class="pdf-badge" style="color:#94a3b8;">Bulunamadı</span>';
    }

    let gapsHTML = '';
    const gaps = ai.strateji?.semantik_bosluklar || [];
    if (gaps.length > 0) {
        gaps.forEach(gap => { gapsHTML += `<span class="pdf-badge-red">${gap}</span>`; });
    } else {
        gapsHTML = '<span class="pdf-badge" style="color:#94a3b8;">Eksik bulunamadı</span>';
    }

    // PAA
    let paaHTML = '';
    const paas = ai.strateji?.paa_hedefleri || [];
    paas.forEach(paa => { paaHTML += `<div class="pdf-paa-item">${paa}</div>`; });

    // Roadmap
    let roadmapHTML = '';
    const steps = ai.entegrasyon?.adim_adim_rehber || [];
    steps.forEach((step, idx) => {
        const cleanStep = step.replace(/^Adım\s*\d+[\s\:\-\.]*/i, '');
        roadmapHTML += `<div class="pdf-roadmap-item"><div class="step-num">${idx+1}</div><div class="step-txt">${cleanStep}</div></div>`;
    });

    // Optimized Text
    const optimizedText = ai.otomatik_duzeltme?.yeniden_yazilmis_metin || '';
    const paragraphs = optimizedText.split(/\n\s*\n/).filter(p => p.trim().length > 0);
    let formattedTextHTML = '';
    if (paragraphs.length === 0) {
        formattedTextHTML = 'Optimizasyon metni bulunamadı.';
    } else {
        paragraphs.forEach(p => {
            const cleanP = p.replace(/</g, "&lt;").replace(/>/g, "&gt;");
            if (cleanP.startsWith('#')) {
                const hLevel = (cleanP.match(/^#+/) || [''])[0].length;
                const hText = cleanP.replace(/^#+\s*/, '');
                formattedTextHTML += `<h${Math.min(hLevel+1, 6)} class="pdf-opt-heading">${hText}</h${Math.min(hLevel+1, 6)}>`;
            } else {
                formattedTextHTML += `<p class="pdf-opt-p">${cleanP}</p>`;
            }
        });
    }
    
    // Word count diff
    const optimizedWordCount = ai.otomatik_duzeltme?.yeni_kelime_sayisi || (optimizedText.trim().split(/\s+/).filter(w => w.length>0).length);
    const diff = optimizedWordCount - wordCount;
    const diffText = diff >= 0 ? `+${diff} Kelime` : `${diff} Kelime`;

    // Extract Readability Safe Values
    const rAtesmanScore = readability.atesman_index || '-';
    const rAtesmanAdvice = readability.atesman_feedback?.advice || '-';
    const rComplexScore = readability.complex_words_percentage || '-';
    const rComplexAdvice = readability.complex_words_feedback?.advice || '-';
    const rTransitionScore = readability.transition_words?.transition_sentence_ratio_percentage || '-';
    const rTransitionAdvice = readability.transition_words?.feedback?.advice || '-';
    const rPassiveScore = readability.passive_voice?.passive_voice_percentage || '-';
    const rPassiveAdvice = readability.passive_voice?.feedback?.advice || '-';

    // 2. Build DOM
    const wrapper = document.createElement('div');
    wrapper.className = 'pdf-export-wrapper';
    const container = document.createElement('div');
    container.className = 'pdf-export-container';
    container.id = 'pdfExportContainer';

    const now = new Date();
    const dateStr = now.toLocaleDateString('tr-TR') + ' ' + now.toLocaleTimeString('tr-TR', {hour: '2-digit', minute:'2-digit'});

    container.innerHTML = `
        <div class="pdf-header">
            <div class="pdf-header-content">
                <h1>SEO Analiz & Optimizasyon Raporu</h1>
                <div class="pdf-header-meta">
                    <span><strong>Odak Kelime:</strong> ${targetKeyword}</span>
                    <span><strong>Tarih:</strong> ${dateStr}</span>
                </div>
            </div>
            <div class="pdf-header-score">
                <div class="pdf-health-score ${healthClass}">${healthScore} <span>SEO Skoru</span></div>
            </div>
        </div>
        
        <div class="pdf-section pdf-avoid-break">
            ${breakdownHTML}
        </div>

        <div class="pdf-section">
            <h2 class="pdf-section-title">Genel Değerlendirme Özeti</h2>
            <div class="pdf-card">
                <p style="font-size: 13px; line-height: 1.6; margin-top: 0;">${aiSummary}</p>
            </div>
        </div>

        <div class="pdf-section pdf-avoid-break">
            <h2 class="pdf-section-title">Bölüm 1: İçerik Kalitesi ve SEO Metrikleri</h2>
            ${anatomyGridHTML}
        </div>

        <div class="pdf-section">
            <div class="pdf-card pdf-avoid-break">
                <h3 class="pdf-card-title">Okunabilirlik Detayları</h3>
                <div class="pdf-readability-item"><div class="pdf-readability-header"><span class="pdf-readability-title">Ateşman Puanı</span><span class="pdf-readability-score">${rAtesmanScore}</span></div><p class="pdf-readability-advice">${rAtesmanAdvice}</p></div>
                <div class="pdf-readability-item"><div class="pdf-readability-header"><span class="pdf-readability-title">Karmaşık Kelimeler</span><span class="pdf-readability-score">%${rComplexScore}</span></div><p class="pdf-readability-advice">${rComplexAdvice}</p></div>
                <div class="pdf-readability-item"><div class="pdf-readability-header"><span class="pdf-readability-title">Geçiş Kelimeleri</span><span class="pdf-readability-score">%${rTransitionScore}</span></div><p class="pdf-readability-advice">${rTransitionAdvice}</p></div>
                <div class="pdf-readability-item"><div class="pdf-readability-header"><span class="pdf-readability-title">Edilgen (Pasif) Çatı</span><span class="pdf-readability-score">%${rPassiveScore}</span></div><p class="pdf-readability-advice">${rPassiveAdvice}</p></div>
            </div>
            
            <div class="pdf-card pdf-avoid-break">
                <h3 class="pdf-card-title">Başlık Hiyerarşisi (Ağaç)</h3>
                ${headingTreeHTML}
            </div>
        </div>
        
        ${chartImageURI ? `
        <div class="pdf-section pdf-avoid-break">
            <div class="pdf-card">
                <h3 class="pdf-card-title">Sık Kullanılan Kelimeler (N-Gram Frekansı)</h3>
                <div class="pdf-chart-container"><img src="${chartImageURI}" /></div>
            </div>
        </div>` : ''}

        <div class="page-break"></div>

        <div class="pdf-section">
            <h2 class="pdf-section-title">Bölüm 2: Anahtar Kelime Stratejisi</h2>
            <div class="pdf-grid-2">
                <div class="pdf-card pdf-avoid-break">
                    <h3 class="pdf-card-title">Önerilen Kelime Kotaları</h3>
                    <table class="pdf-table">
                        <thead><tr><th>Kelime Öbeği</th><th style="text-align:center;">Hedef</th></tr></thead>
                        <tbody>${quotasTableHTML || '<tr><td colspan="2">Öneri bulunamadı.</td></tr>'}</tbody>
                    </table>
                </div>
                <div class="pdf-card pdf-avoid-break">
                    <h3 class="pdf-card-title">Semantik Boşluklar (Eksik Konular)</h3>
                    <div class="pdf-tags-container">${gapsHTML || '<span class="pdf-badge">Eksik bulunamadı</span>'}</div>
                    
                    <h3 class="pdf-card-title" style="margin-top: 15px;">Mevcut Anahtar Kelimeler</h3>
                    <div class="pdf-tags-container">${detectedHTML || '<span class="pdf-badge">Bulunamadı</span>'}</div>
                </div>
            </div>
            <div class="pdf-card pdf-avoid-break">
                <h3 class="pdf-card-title">Sık Sorulan Sorular (Google PAA - Niyet Analizi)</h3>
                <div class="pdf-paa-container">${paaHTML || 'İlgili soru bulunamadı.'}</div>
            </div>
        </div>

        <div class="page-break"></div>

        <div class="pdf-section">
            <h2 class="pdf-section-title">Bölüm 3: Adım Adım İyileştirme Planı</h2>
            <div class="pdf-card pdf-avoid-break" style="background: #f8fafc; border-left: 4px solid #3b82f6;">
                ${roadmapHTML || '<p>Rehber verisi yok.</p>'}
            </div>
        </div>

        <div class="pdf-section">
            <h2 class="pdf-section-title">Bölüm 4: Optimize Edilmiş Metin <span class="pdf-diff-badge">${diffText}</span></h2>
            <div class="pdf-text-content">${formattedTextHTML}</div>
        </div>
    `;

    wrapper.appendChild(container);
    document.body.appendChild(wrapper);

    // 3. Generate PDF Configuration
    const opt = {
        margin: [8, 0, 15, 0],
        filename: 'SEO-Analiz-Raporu.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false, scrollX: 0, scrollY: 0 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: ['css', 'legacy'] }
    };

    const downloadBtn = document.getElementById('downloadPdfBtn');
    const originalBtnHTML = downloadBtn.innerHTML;
    downloadBtn.innerHTML = '<i class="ph ph-spinner animate-spin text-lg text-red-500"></i> Hazırlanıyor...';
    downloadBtn.disabled = true;

    html2pdf().set(opt).from(container).toPdf().get('pdf').then(function (pdf) {
        const totalPages = pdf.internal.getNumberOfPages();
        for (let i = 1; i <= totalPages; i++) {
            pdf.setPage(i);
            pdf.setFontSize(9);
            pdf.setTextColor(150);
            
            // Footer right (page number)
            pdf.text('Sayfa ' + i + ' / ' + totalPages, 180, 287);
            
            // Footer left (brand)
            pdf.text('SEOMaster İçerik - PDF SEO Raporu', 15, 287);
        }
    }).save().then(() => {
        document.body.removeChild(wrapper);
        downloadBtn.innerHTML = originalBtnHTML;
        downloadBtn.disabled = false;
    }).catch(err => {
        console.error("PDF Oluşturma Hatası:", err);
        alert("PDF oluşturulurken bir hata meydana geldi.");
        if (document.body.contains(wrapper)) {
            document.body.removeChild(wrapper);
        }
        downloadBtn.innerHTML = originalBtnHTML;
        downloadBtn.disabled = false;
    });
};
