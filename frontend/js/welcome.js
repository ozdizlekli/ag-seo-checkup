document.addEventListener("DOMContentLoaded", () => {
    const welcomeOverlay = document.getElementById("welcome-overlay");
    if(!welcomeOverlay) return;
    
    if(sessionStorage.getItem("ag_welcome_seen") === "true") {
        welcomeOverlay.style.display = "none";
    }
    
    function dismissWelcome(tabNum) {
        welcomeOverlay.style.opacity = "0";
        setTimeout(() => {
            welcomeOverlay.style.display = "none";
            sessionStorage.setItem("ag_welcome_seen", "true");
            if(tabNum) {
                const tabBtn = document.querySelector(`[data-tab="${tabNum}"]`);
                if(tabBtn) tabBtn.click();
            }
        }, 400);
    }
    
    const btnStart = document.getElementById("btn-dashboard-start");
    if(btnStart) {
        btnStart.addEventListener("click", () => {
            dismissWelcome(3);
        });
    }

    const btnReopen = document.getElementById("btn-reopen-welcome");
    if (btnReopen) {
        btnReopen.addEventListener("click", () => {
            welcomeOverlay.style.display = "flex";
            setTimeout(() => {
                welcomeOverlay.style.opacity = "1";
            }, 10);
        });
    }
});