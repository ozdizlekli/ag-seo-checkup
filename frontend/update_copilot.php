<?php
$copilot = file_get_contents("frontend/js/copilot.js");

$old_battle = <<<'EOF'
            // Build Prompt
            const prompt = `GERÇEK ZAMANLI RAKİP SAVAŞ MODU (BATTLE MODE) AKTİF!
HEDEF SİTE (Müşteri): ${dataT.title}
RAKİP SİTE (Geçilecek Site): ${dataC.title}

Rakibin Çekilen HTML/İçerik Verisi (Sadece Özet):
- Rakip H1/H2 Başlıkları: ${JSON.stringify((dataC.headings || []).slice(0,5))}
- Rakip Şema (Schema) Kullanımı: ${dataC.schemas ? dataC.schemas.length : 0} adet
- Rakip Meta Açıklaması: ${dataC.description}
- Rakip Metni (İlk 500 Karakter): ${(dataC.text || '').substring(0, 500)}

Görev: Müşterinin sitesiyle, bu canlı rakip sitesini "Generative Engine Optimization (GEO)" perspektifinden acımasızca kıyasla! Sadece genel SEO değil, Yapay Zeka botlarının (ChatGPT, SGE, Perplexity) okuma ve alıntı yapma biçimlerine odaklanarak şu formatta kapsamlı bir rapor çıkar:

* 🧠 SEMANTİK KAPSAM VE VARLIK (ENTITY) ANALİZİ
* 🏗 FORMAT VE YZ OKUNABİLİRLİĞİ (LLM UYUMU)
* 🛡 E-E-A-T VE BİLGİ YOĞUNLUĞU
* ⚔️ KESİN ZAFER STRATEJİSİ: ChatGPT ve Google SGE aramalarında bu rakibi tahtından etmek için acilen yapmamız gereken 5 nokta atışı taktik.`;
EOF;

$new_battle = <<<'EOF'
            // Build Prompt - Rakip Savaş Modu 2. PDF Uyumu
            const prompt = `GERÇEK ZAMANLI RAKİP SAVAŞ MODU (BATTLE MODE) AKTİF!

Site A:
${(dataT.text || '').substring(0, 10000)}

Site B (Rakip):
${(dataC.text || '').substring(0, 10000)}

Site A'nın rakibine göre içerik derinliği, SEO kalitesi ve E-E-A-T sinyalleri açısından eksiklerini ve rakibin neden daha iyi olduğunu JSON olarak analiz et.`;
EOF;

$copilot = str_replace($old_battle, $new_battle, $copilot);

// Wait! In the old logic, the result was parsed and converted from markdown.
// If we request JSON, we must parse JSON! But `copilot.js` expects markdown from battle mode:
// let aiText = aiResult.candidates[0].content.parts[0].text;
// let htmlText = typeof marked !== 'undefined' ? marked.parse(aiText) : aiText;
// battleResults.innerHTML = '<div style="animation: fade-in 0.5s;">' + htmlText + '</div>';

$old_battle_result = <<<'EOF'
            let aiText = aiResult.candidates[0].content.parts[0].text;
            let htmlText = typeof marked !== 'undefined' ? marked.parse(aiText) : aiText;
            battleResults.innerHTML = '<div style="animation: fade-in 0.5s;">' + htmlText + '</div>';
        } catch (err) {
EOF;

$new_battle_result = <<<'EOF'
            let aiText = aiResult.candidates[0].content.parts[0].text;
            
            // JSON parse etmeye çalışalım
            let parsedHtml = '';
            try {
                // Temizle
                let cleanJson = aiText.replace(/```json/gi, '').replace(/```/g, '').trim();
                let jsonObj = JSON.parse(cleanJson);
                
                parsedHtml = '<h3 style="color:#ef4444; border-bottom:1px solid #fee2e2; padding-bottom:8px; margin-bottom:16px;">⚔️ Battle Mode: AI Kıyaslama Raporu</h3>';
                
                // Dinamik olarak JSON içeriğini HTML'e dök
                for (let key in jsonObj) {
                    if(jsonObj.hasOwnProperty(key)) {
                        let val = jsonObj[key];
                        let title = key.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase()); // camelCase to Title Case
                        parsedHtml += `<div style="margin-bottom:16px;">`;
                        parsedHtml += `<strong style="color:#334155; font-size:16px;">${title}:</strong>`;
                        if (typeof val === 'string') {
                            parsedHtml += `<p style="color:#475569; margin-top:4px;">${val}</p>`;
                        } else if (Array.isArray(val)) {
                            parsedHtml += `<ul style="color:#475569; margin-top:4px; padding-left:20px;">`;
                            val.forEach(item => {
                                parsedHtml += `<li style="margin-bottom:4px;">${typeof item === 'object' ? JSON.stringify(item) : item}</li>`;
                            });
                            parsedHtml += `</ul>`;
                        } else if (typeof val === 'object') {
                            parsedHtml += `<pre style="background:#f1f5f9; padding:8px; border-radius:4px; font-size:13px; color:#334155;">${JSON.stringify(val, null, 2)}</pre>`;
                        }
                        parsedHtml += `</div>`;
                    }
                }
            } catch(e) {
                // JSON değilse Markdown olarak göster
                parsedHtml = typeof marked !== 'undefined' ? marked.parse(aiText) : aiText;
            }

            battleResults.innerHTML = '<div style="animation: fade-in 0.5s;">' + parsedHtml + '</div>';
        } catch (err) {
EOF;

$copilot = str_replace($old_battle_result, $new_battle_result, $copilot);

file_put_contents("frontend/js/copilot.js", $copilot);
echo "Updated copilot.js\n";
?>
