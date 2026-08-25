window.generatePDF = function(apiData, chartCanvas) {
    if (!apiData || !apiData.telemetry_summary || !apiData.ai_dimensions) {
        alert("Geçersiz analiz verisi. Lütfen tekrar analiz edin.");
        return;
    }

    // 1. Telemetri ve Okunabilirlik Verilerini Eksiksiz Çek:
    const rawTelemetry = apiData.telemetry_summary || {};
    const telemetry = rawTelemetry.telemetry_data || rawTelemetry;
    const readability = telemetry.readability || {};
    const anatomy = telemetry.anatomy || { headings: {} };
    const ai = apiData.ai_dimensions || {};

    // Prepare Health Score
    const healthScore = ai.analiz?.saglik_skoru || 0;
    let healthClass = 'danger';
    if (healthScore >= 80) healthClass = 'success';
    else if (healthScore >= 50) healthClass = 'warning';

    // Prepare Executive Summary
    const aiSummary = ai.analiz?.ozet || 'Özet bulunamadı.';
    let issuesListHTML = '';
    if (ai.analiz?.sorunlar && Array.isArray(ai.analiz.sorunlar)) {
        ai.analiz.sorunlar.forEach(issue => {
            issuesListHTML += `<li>${issue}</li>`;
        });
    }

    // Prepare Chart Image
    let chartImageURI = '';
    try {
        if (chartCanvas && chartCanvas instanceof HTMLCanvasElement && chartCanvas.width > 0) {
            chartImageURI = chartCanvas.toDataURL('image/png', 1.0);
        }
    } catch (e) {
        console.warn("Grafik render edilemedi veya henüz hazır değil:", e);
    }

    // Prepare Quotas Table
    let quotasTableHTML = '';
    const adetler = ai.strateji?.eklenecek_kelime_adetleri || {};
    for (const [kw, count] of Object.entries(adetler)) {
        quotasTableHTML += `<tr><td>${kw}</td><td style="text-align: center;">+${count}</td></tr>`;
    }

    // Prepare Semantic Gaps
    let gapsHTML = '';
    const gaps = ai.strateji?.semantik_bosluklar || [];
    gaps.forEach(gap => {
        gapsHTML += `<span class="pdf-badge">${gap}</span>`;
    });

    // Prepare PAA
    let paaHTML = '';
    const paas = ai.strateji?.paa_hedefleri || [];
    paas.forEach(paa => {
        paaHTML += `<li>${paa}</li>`;
    });

    // Prepare Roadmap
    let roadmapHTML = '';
    const steps = ai.entegrasyon?.adim_adim_rehber || [];
    steps.forEach((step) => {
        roadmapHTML += `<div class="pdf-roadmap-item">${step}</div>`;
    });

    // Prepare Optimized Text
    const optimizedText = ai.otomatik_duzeltme?.yeniden_yazilmis_metin || '';
    const paragraphs = optimizedText.split(/\n\s*\n/).filter(p => p.trim().length > 0);
    let formattedTextHTML = '';
    if (paragraphs.length === 0) {
        formattedTextHTML = 'Veri yok';
    } else {
        paragraphs.forEach(p => {
            const cleanP = p.replace(/</g, "&lt;").replace(/>/g, "&gt;");
            if (cleanP.startsWith('#')) {
                formattedTextHTML += `<div class="pdf-p-heading" style="font-weight:bold; font-size:13px; margin: 12px 0 6px 0; color:#0f172a;">${cleanP}</div>`;
            } else {
                formattedTextHTML += `<div class="pdf-p-text" style="font-size:11.5px; line-height:1.6; margin-bottom:8px; color:#334155;">${cleanP}</div>`;
            }
        });
    }

    // Extract Readability Safe Values
    const rAtesmanScore = readability.atesman?.score || readability.atesman_index || '-';
    const rAtesmanAdvice = readability.atesman?.advice || '';
    
    const rComplexScore = (readability.complex_words?.percentage !== undefined ? readability.complex_words.percentage : readability.complex_words_percentage) || '-';
    const rComplexAdvice = readability.complex_words?.advice || '';

    const rTransitionScore = (readability.transition_words?.percentage !== undefined ? readability.transition_words.percentage : readability.transition_words?.transition_sentence_ratio_percentage) || '-';
    const rTransitionAdvice = readability.transition_words?.advice || '';

    const rPassiveScore = (readability.passive_voice?.percentage !== undefined ? readability.passive_voice.percentage : readability.passive_voice?.passive_voice_percentage) || '-';
    const rPassiveAdvice = readability.passive_voice?.advice || '';

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
                <h1>SEO & İçerik Analiz Raporu</h1>
                <p>Proje: SEOMaster İçerik | Analiz Tarihi: ${dateStr}</p>
            </div>
            <div class="pdf-health-score ${healthClass}">
                ${healthScore}
                <span>Genel SEO Skoru</span>
            </div>
        </div>

        <div class="pdf-section">
            <h2 class="pdf-section-title">Analiz Özeti ve Öneriler</h2>
            <div class="pdf-card">
                <p style="font-size: 13px; line-height: 1.5; margin-top: 0;">${aiSummary}</p>
                <h4 style="font-size: 13px; color: #ef4444; margin-top: 15px; margin-bottom: 5px;">Kritik Sorunlar:</h4>
                <ul class="pdf-list">
                    ${issuesListHTML || '<li>Kritik sorun bulunamadı.</li>'}
                </ul>
            </div>
        </div>

        <div class="pdf-section pdf-avoid-break">
            <h2 class="pdf-section-title">Bölüm 1: İçerik Kalitesi ve SEO Metrikleri</h2>
            <div class="pdf-grid-2">
                <div class="pdf-card">
                    <h3 style="font-size: 14px; margin-top: 0; margin-bottom: 10px;">İçerik Yapısı</h3>
                    <div class="pdf-stat-item"><span class="pdf-stat-label">Kelime Sayısı</span><span class="pdf-stat-value">${anatomy.word_count || 0}</span></div>
                    <div class="pdf-stat-item"><span class="pdf-stat-label">Cümle Sayısı</span><span class="pdf-stat-value">${anatomy.sentence_count || 0}</span></div>
                    <div class="pdf-stat-item"><span class="pdf-stat-label">H1 Başlık</span><span class="pdf-stat-value">${anatomy.headings?.h1_count || 0}</span></div>
                    <div class="pdf-stat-item"><span class="pdf-stat-label">Paragraf Sayısı</span><span class="pdf-stat-value">${anatomy.paragraph_count || 0}</span></div>
                </div>
                
                <div class="pdf-card">
                    <h3 style="font-size: 14px; margin-top: 0; margin-bottom: 10px;">Okunabilirlik ve Anlaşılırlık</h3>
                    
                    <div class="pdf-readability-item">
                        <div class="pdf-readability-header">
                            <span class="pdf-readability-title">Okunabilirlik Puanı</span>
                            <span class="pdf-readability-score">${rAtesmanScore}</span>
                        </div>
                        <p class="pdf-readability-advice">${rAtesmanAdvice}</p>
                    </div>

                    <div class="pdf-readability-item">
                        <div class="pdf-readability-header">
                            <span class="pdf-readability-title">Anlaşılması Zor Kelimeler</span>
                            <span class="pdf-readability-score">%${rComplexScore}</span>
                        </div>
                        <p class="pdf-readability-advice">${rComplexAdvice}</p>
                    </div>

                    <div class="pdf-readability-item">
                        <div class="pdf-readability-header">
                            <span class="pdf-readability-title">Akıcılık Sağlayan Bağlaçlar</span>
                            <span class="pdf-readability-score">%${rTransitionScore}</span>
                        </div>
                        <p class="pdf-readability-advice">${rTransitionAdvice}</p>
                    </div>

                    <div class="pdf-readability-item">
                        <div class="pdf-readability-header">
                            <span class="pdf-readability-title">Edilgen (Pasif) Anlatım</span>
                            <span class="pdf-readability-score">%${rPassiveScore}</span>
                        </div>
                        <p class="pdf-readability-advice">${rPassiveAdvice}</p>
                    </div>
                </div>
            </div>
            
            <div class="pdf-card">
                <h3 style="font-size: 14px; margin-top: 0; margin-bottom: 10px;">Sık Kullanılan Kelimeler</h3>
                <div class="pdf-chart-container">
                    ${chartImageURI ? `<img src="${chartImageURI}" />` : '<p style="font-size:12px;color:#94a3b8;">Grafik bulunamadı</p>'}
                </div>
            </div>
        </div>

        <div class="page-break"></div>

        <div class="pdf-section">
            <h2 class="pdf-section-title">Bölüm 2: Anahtar Kelime Stratejisi</h2>
            <div class="pdf-grid-2">
                <div class="pdf-card pdf-avoid-break">
                    <h3 style="font-size: 14px; margin-top: 0; margin-bottom: 10px;">Önerilen Anahtar Kelimeler</h3>
                    <table class="pdf-table">
                        <thead><tr><th>Kelime</th><th style="text-align:center;">Önerilen Sayı</th></tr></thead>
                        <tbody>
                            ${quotasTableHTML || '<tr><td colspan="2">Veri yok</td></tr>'}
                        </tbody>
                    </table>
                </div>
                <div class="pdf-card pdf-avoid-break">
                    <h3 style="font-size: 14px; margin-top: 0; margin-bottom: 10px;">Eksik Konular</h3>
                    <div style="margin-bottom: 15px;">
                        ${gapsHTML || '<span style="font-size:12px;color:#94a3b8;">Veri yok</span>'}
                    </div>
                    
                    <h3 style="font-size: 14px; margin-top: 0; margin-bottom: 10px;">Sık Sorulan Sorular (Google PAA)</h3>
                    <ul class="pdf-list">
                        ${paaHTML || '<li>Veri yok</li>'}
                    </ul>
                </div>
            </div>
        </div>

        <div class="pdf-section pdf-avoid-break">
            <h2 class="pdf-section-title">Bölüm 3: Adım Adım İyileştirme Planı</h2>
            <div class="pdf-card">
                ${roadmapHTML || '<p style="font-size:12px;color:#94a3b8;">Veri yok</p>'}
            </div>
        </div>

        <div class="page-break"></div>

        <div class="pdf-section">
            <h2 class="pdf-section-title">Bölüm 4: Optimize Edilmiş Metin</h2>
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
