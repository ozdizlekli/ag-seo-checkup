const fs = require('fs');

// --- 1. Fix tab_ai_seo.php ---
let html = fs.readFileSync('frontend/ai_seo/views/tab_ai_seo.php', 'utf8');

// A. Mavi Noktalar (Kartın Köşelerini Yuvarlatma)
html = html.replace(/(id="copilot-card" style="[^"]+)(")/, "$1; border-radius: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);$2");

// B. Kırmızı Çizgiler
html = html.replace(/(class="copilot-header" style="[^"]+)(")/, "$1; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; background: #fff; flex-shrink: 0;$2");

// Replace copilot-input-area-container block
const inputAreaRegex = /<div class="copilot-input-area" id="copilot-input-area-container"[\s\S]*?(?=<\/div>\s*<\/div>\s*<!-- \/copilot-action-view -->)/;
const newInputArea = `<div class="copilot-input-area" id="copilot-input-area-container" style="padding: 16px 24px; background: transparent; border-top: none; position: relative; flex-shrink: 0;">
            
            <!-- GREEN BUTTONS (Tüm Siteyi Analiz Et / Eksikleri Gider) -->
            <div id="copilot-llms-container" style="display:none; flex-direction:row; gap:16px; margin-bottom: 24px; justify-content: center; max-width: 800px; margin-left: auto; margin-right: auto;">
               <button class="btn btn--primary" id="btn-auto-analyze" style="flex:1; padding: 12px; font-weight:600; background:#10b981; border:none; border-radius: 12px; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);">
                   <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; color:#fff;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                   Tüm Siteyi Analiz Et
               </button>
               <button class="btn btn--primary" id="btn-auto-fix" style="flex:1; padding: 12px; font-weight:600; background:#10b981; border:none; border-radius: 12px; display:none; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);">
                   <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; color:#fff;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                   Tüm Eksikleri Gider
               </button>
            </div>
            
            <!-- HIZLI AKSİYON BUTONLARI -->
            <div class="quick-actions" id="copilot-quick-actions" style="margin-bottom: 16px; padding-top: 4px; max-width: 800px; width: 100%; margin-left: auto; margin-right: auto; display: none; justify-content: center; gap: 8px; background: transparent; border: none; overflow-x: auto; white-space: nowrap;">
              <button class="quick-action-btn has-tooltip" data-tooltip="İçeriği daha inandırıcı hale getirmek için ipuçları iste." onclick="document.getElementById('copilot-text-input').value='İçeriği daha ikna edici nasıl yaparım?'; document.getElementById('btn-send-message').click();">İçeriği daha ikna edici nasıl yaparım?</button>
              <button class="quick-action-btn has-tooltip" data-tooltip="Eksik olan E-E-A-T veya semantik unsurları listele." onclick="document.getElementById('copilot-text-input').value='Bana içerik eksiklerimi söyle'; document.getElementById('btn-send-message').click();">Bana içerik eksiklerimi söyle</button>
              <button class="quick-action-btn has-tooltip" data-tooltip="Makalenin anlamsal derinliğini artıracak anahtar kelimeler öner." onclick="document.getElementById('copilot-text-input').value='Hangi LSI kelimelerini kullanmalıyım?'; document.getElementById('btn-send-message').click();">Hangi LSI kelimelerini kullanmalıyım?</button>
              <button class="quick-action-btn has-tooltip" data-tooltip="Rakiplerin senden daha iyi olduğu spesifik noktaları öğren." onclick="document.getElementById('copilot-text-input').value='Rakiplerimden neden gerideyim?'; document.getElementById('btn-send-message').click();">Rakiplerimden neden gerideyim?</button>
            </div>
            
            <!-- YENİ GEMINI STİLİ GİRİŞ ALANI -->
            <div class="input-wrapper" style="display: flex; flex-direction: column; align-items: center; width: 100%;">
                <div style="display: flex; align-items: center; width: 100%; max-width: 800px; background: #f0f4f9; border-radius: 30px; padding: 6px 10px 6px 24px; margin-bottom: 8px;">
                  <input type="text" id="copilot-text-input" placeholder="Örn: https://www.site.com/hizmet" style="flex: 1; border: none; outline: none; background: transparent; font-size: 15px; color: #1e293b; padding: 10px 0; box-shadow: none;">
                  <input type="text" id="copilot-secondary-input" style="display:none;">
                  <button id="btn-send-message" style="background: #a8c7fa; color: #041e49; border: none; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; transition: all 0.2s; margin-left: 12px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>
                  </button>
                </div>
                <div style="font-size: 12px; color: #64748b; text-align: center; font-weight: 400;">
                    AI SEO Bot bir yapay zeka modeli olduğu için hata yapabilir.
                </div>
            </div>
          </div>\n`;

html = html.replace(inputAreaRegex, newInputArea);
fs.writeFileSync('frontend/ai_seo/views/tab_ai_seo.php', html);


// --- 2. Fix copilot.js ---
let js = fs.readFileSync('frontend/ai_seo/js/copilot.js', 'utf8');

// A. Geçmiş Sohbet Yüklendiğinde Oluşan Sıkışma
const aOld = `const msgContainer = document.getElementById('copilot-chat-messages-container');
    if (msgContainer) msgContainer.style.display = 'block';`;
const aNew = `const msgContainer = document.getElementById('copilot-chat-messages-container');
if (msgContainer) { 
    msgContainer.style.display = 'flex'; 
    msgContainer.style.flexDirection = 'column'; 
    msgContainer.style.flex = '1'; 
    msgContainer.style.overflowY = 'auto'; 
    msgContainer.style.paddingTop = '16px'; 
}`;
js = js.replace(aOld, aNew);

// B. Geçmiş Sohbet Yüklendiğinde Örnek Soruların Çıkması
const bOld = `copilotInputArea.style.display = "none"; const cqa = document.getElementById("copilot-quick-actions"); if(cqa) cqa.style.display = "none";`;
const bNew = `copilotInputArea.style.display = "none"; const cqa = document.getElementById("copilot-quick-actions"); if(cqa) cqa.style.display = "flex";`;
js = js.replace(bOld, bNew);

// C. Yeni Sohbet Boşluk Hatasını Silme
// Since I already changed this previously, let's just make sure it's updated exactly as requested.
// Try to replace both variations just in case.
const cOld1 = `const msgContainer = document.getElementById('copilot-chat-messages-container');
  if (msgContainer) { 
      msgContainer.style.display = 'flex'; 
      msgContainer.style.flexDirection = 'column';
      msgContainer.style.flex = '1'; 
      msgContainer.style.overflowY = 'auto';
      msgContainer.style.paddingTop = '16px';
  }`;
const cOld2 = `const msgContainer = document.getElementById('copilot-chat-messages-container');
  if (msgContainer) { msgContainer.style.display = 'block'; msgContainer.style.flex = '0'; msgContainer.style.minHeight = '100px'; }`;

const cNew = `const msgContainer = document.getElementById('copilot-chat-messages-container');
if (msgContainer) { 
    msgContainer.style.display = 'flex'; 
    msgContainer.style.flexDirection = 'column'; 
    msgContainer.style.flex = '1'; 
    msgContainer.style.overflowY = 'auto'; 
    msgContainer.style.paddingTop = '16px'; 
    msgContainer.style.minHeight = '0'; 
}`;
if (js.includes(cOld1)) js = js.replace(cOld1, cNew);
if (js.includes(cOld2)) js = js.replace(cOld2, cNew);

// D. Yeni URL Taraması Bitince Örnek Soruların Çıkması
const dOld = `addMessage(\`Şimdi \${targetType} sitenize özel 5 adımlı analiz sürecini başlatabiliriz.\`, 'ai', true);
    const cqa = document.getElementById('copilot-quick-actions'); 
    if(cqa) cqa.style.display = 'flex';
    renderAiSeoActions();`;
// (We already had some of this, but we'll re-apply exactly to be safe)
const dNew = `addMessage(\`Şimdi \${targetType} sitenize özel 5 adımlı analiz sürecini başlatabiliriz.\`, 'ai', true);
const cqa = document.getElementById('copilot-quick-actions'); 
if(cqa) cqa.style.display = 'flex';
renderAiSeoActions();`;
js = js.replace(dOld, dNew);

fs.writeFileSync('frontend/ai_seo/js/copilot.js', js);
console.log("Applied final UI patches");
