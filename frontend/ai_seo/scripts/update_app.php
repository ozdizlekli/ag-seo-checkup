<?php
$app = file_get_contents("frontend/js/app.js");

// Replace the fetching logic in Tab 8
$old_fetch = <<<'EOF'
    const targetUrl = `http://localhost:3000/api/scrape?url=${encodeURIComponent(url)}`;
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 30000); 

    let rawText = '';
    try {
      const response = await fetch(targetUrl, { signal: controller.signal });
      if(response.ok) {
         const data = await response.json();
         rawText = data.content || '';
      }
    } catch(e) {
      console.warn('Scraping başarısız, sadece URL ile AI\'a sorulacak.', e);
    }
    clearTimeout(timeoutId);

    const textForAnalysis = rawText.substring(0, 5000);

    const aiPrompt = `Lütfen aşağıdaki web sitesi içeriğini veya domaini SEO ve pazarlama perspektifinden analiz et. Analizini TÜRKÇE yap ve kesinlikle JSON formatında döndür.

Hedef Domain/URL: ${url}
Siteden çekilebilen içerik (varsa): ${textForAnalysis}

Lütfen şu formatta bir JSON döndür:
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
    "Fırsat 1...",
    "Fırsat 2...",
    "Fırsat 3..."
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
Yalnızca geçerli bir JSON döndür. Başka bir metin ekleme.`;
EOF;

$new_fetch = <<<'EOF'
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
EOF;

$app = str_replace($old_fetch, $new_fetch, $app);

// Update renderer
$old_render = <<<'EOF'
       document.getElementById('t8-opportunities').innerHTML = aiParsed.contentOpportunities.map((o, i) => `
         <div style="margin-bottom:12px; page-break-inside: avoid;">
           <strong style="color:#333; font-size:14px;">${i+1}. Fırsat</strong>
           <p style="margin:4px 0 0 0; font-size:14px; color:#444;">${escapeHtml(o)}</p>
         </div>
       `).join('');
EOF;

$new_render = <<<'EOF'
       document.getElementById('t8-opportunities').innerHTML = aiParsed.contentOpportunities.map((o, i) => `
         <div style="margin-bottom:12px; page-break-inside: avoid;">
           <strong style="color:#333; font-size:14px;">${i+1}. ${escapeHtml(o.title || 'Fırsat')}</strong>
           <p style="margin:4px 0 0 0; font-size:14px; color:#444;">${escapeHtml(o.desc || o)}</p>
         </div>
       `).join('');
EOF;

$app = str_replace($old_render, $new_render, $app);

file_put_contents("frontend/js/app.js", $app);
echo "Updated app.js\n";
?>
