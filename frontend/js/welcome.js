document.addEventListener('DOMContentLoaded', () => {
    const welcomeOverlay = document.getElementById('welcome-overlay');
    if(!welcomeOverlay) return;
    
    // Yalnızca oturum boyunca 1 kez göster
    if(sessionStorage.getItem('ag_welcome_seen') === 'true') {
        welcomeOverlay.style.display = 'none';
        // DO NOT return here, otherwise btnReopen event listener won't be bound!
    }
    
    function dismissWelcome(tabNum) {
        welcomeOverlay.style.opacity = '0';
        setTimeout(() => {
            welcomeOverlay.style.display = 'none';
            sessionStorage.setItem('ag_welcome_seen', 'true');
            if(tabNum) {
                // Tab butonunu bul ve tıkla
                const tabBtn = document.querySelector(`[data-tab="${tabNum}"]`);
                if(tabBtn) tabBtn.click();
            }
        }, 400); // CSS animasyon süresi
    }
    
    // Kart Tıklamaları
    const clearOtherInputs = (excludeId) => {
        const urlInputs = ['aiseo-url-input', 'urlInput', 't3-url'];
        urlInputs.forEach(id => {
            if (id !== excludeId) {
                const input = document.getElementById(id);
                if (input) input.value = '';
            }
        });
    };

    const handleCardClick = (tabNum, targetId) => {
        const inputEl = document.getElementById('welcome-main-url-input'); if (inputEl && !inputEl.checkValidity()) { inputEl.reportValidity(); return; } const val = inputEl?.value.trim() || '';
        const input = document.getElementById(targetId);
        if (input) input.value = val;
        
        // Eğer bu URL daha önce "URL'yi Gönder" ile tüm sayfalara gönderilmediyse diğer sayfaları temizle
        if (window.agLastSentUrl !== val || val === '') {
            clearOtherInputs(targetId);
        }
        
        dismissWelcome(tabNum);
    };

    const btnContent = document.getElementById('wc-card-content');
    if(btnContent) btnContent.addEventListener('click', () => handleCardClick(1, 'urlInput'));
    
    const btnTech = document.getElementById('wc-card-tech');
    if(btnTech) btnTech.addEventListener('click', () => handleCardClick(2, 't3-url'));
    
    const btnAi = document.getElementById('wc-card-ai');
    if(btnAi) btnAi.addEventListener('click', () => handleCardClick(3, 'aiseo-url-input'));
    
    // GEO AI Bot Hızlı Başlangıç
    const btnBotStart = document.getElementById('wc-bot-start');
    const inputUrl = document.getElementById('wc-url-input');
    
    // Input alanına tıklanınca kartın tıklanmasını engelle
    if(inputUrl) {
        inputUrl.addEventListener('click', (e) => e.stopPropagation());
        inputUrl.addEventListener('keypress', (e) => {
            if(e.key === 'Enter' && btnBotStart) {
                btnBotStart.click();
            }
        });
    }

    if(btnBotStart) {
        btnBotStart.addEventListener('click', (e) => {
            e.stopPropagation();
            const url = inputUrl.value.trim();
            if(!url) {
                alert("Lütfen analiz edilecek URL'yi girin.");
                return;
            }
            if(!url.startsWith('http')) {
                alert("Geçerli bir URL girin (http:// veya https:// ile başlamalı).");
                return;
            }
            
            // 3. Sekmeye geç
            dismissWelcome(3);
            
            // Copilot JS'in hazır olması için biraz bekle ve veriyi aktar
            setTimeout(() => {
                const cpInput = document.getElementById('copilot-text-input');
                const cpSend = document.getElementById('copilot-send-btn');
                
                if(cpInput && cpSend) {
                    cpInput.value = url;
                    cpSend.click();
                }
            }, 500);
        });
    }

    // Yeniden açma butonu mantığı
    const btnReopen = document.getElementById('btn-reopen-welcome');
    if (btnReopen) {
        btnReopen.addEventListener('click', () => {
            welcomeOverlay.style.display = 'flex';
            setTimeout(() => {
                welcomeOverlay.style.opacity = '1';
            }, 10);
        });
    }
});




