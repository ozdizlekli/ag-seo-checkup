<?php
$copilot = file_get_contents("frontend/js/copilot.js");

$dynamic_pool_code = <<<JAVASCRIPT
  const seoQuestionPool = [
    "Bana içerik eksiklerimi söyle",
    "Rakiplerimden neden gerideyim?",
    "Bu sayfa için SSS (FAQ) hazırla",
    "İçeriği daha ikna edici nasıl yaparım?",
    "Hangi LSI kelimelerini kullanmalıyım?",
    "Kullanıcı niyetine ne kadar uygun?",
    "Okunabilirlik skorumu nasıl artırırım?",
    "Sayfa başlığı (Title) yeterince iyi mi?",
    "E-E-A-T sinyallerim eksik mi?",
    "Hangi alt başlıkları (H2/H3) eklemeliyim?",
    "Featured Snippet (Sıfırıncı sıra) için ne yapmalıyım?"
  ];

  function renderDynamicQuickActions() {
    const quickActionsContainer = document.getElementById('copilot-quick-actions');
    if (!quickActionsContainer) return;

    // Sadece sohbet boşken gösteriliyor (display: none yapılıyor sonra)
    // 4 rastgele soru seç
    const shuffled = [...seoQuestionPool].sort(() => 0.5 - Math.random());
    const selected = shuffled.slice(0, 4);

    const infoIconBlue = '<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: #3b82f6; opacity:0.8;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';

    let html = '';
    selected.forEach(q => {
      html += `<button class="quick-action-btn has-tooltip" data-tooltip="Hızlı aksiyon ile AI'a anında talimat gönderin." onclick="document.getElementById('copilot-text-input').value=this.textContent; document.getElementById('copilot-send-btn').click();">` + q + infoIconBlue + `</button>`;
    });
    
    quickActionsContainer.innerHTML = html;
  }
JAVASCRIPT;

// Inject it near the top of DOMContentLoaded
$copilot = preg_replace('/(document\.addEventListener\("DOMContentLoaded", function\(\) \{)/', "$1\n$dynamic_pool_code", $copilot);

// Call it on page load
$copilot = preg_replace('/(updateProgressUI\(currentStep\);)/', "$1\n    renderDynamicQuickActions();", $copilot);

// Add call in resetChat when starting a fresh chat
$copilot = preg_replace('/(document\.getElementById\(\'copilot-messages\'\)\.innerHTML = \`.*?\`;)/s', "$1\n      renderDynamicQuickActions();", $copilot);

file_put_contents("frontend/js/copilot.js", $copilot);
echo "Dynamic pool injected.\n";
?>
