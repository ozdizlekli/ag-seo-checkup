<?php
$c = file_get_contents("frontend/js/copilot.js");

// We need to define `renderDynamicQuickActions` at the top level or inside the DOMContentLoaded if `renderAiSeoActions` and `resetChat` are also inside it.
// Let's check where `resetChat` is defined.
preg_match('/function resetChat/', $c, $matches, PREG_OFFSET_CAPTURE);
// It is inside DOMContentLoaded!
// Wait, then the function should be globally defined so it can be called anywhere, or inside the DOMContentLoaded block.
// I will just put it at the very top of the file to be safe.

$func = <<<JS

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

  window.renderDynamicQuickActions = function() {
    const quickActionsContainer = document.getElementById("copilot-quick-actions");
    if (!quickActionsContainer) return;

    const shuffled = [...seoQuestionPool].sort(() => 0.5 - Math.random());
    const selected = shuffled.slice(0, 4);

    const infoIconBlue = '<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; color: #3b82f6; opacity:0.8;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';

    let html = "";
    selected.forEach(q => {
      // FIX the button so it sets value to just the question string, without the SVG!
      // 'this.textContent' will include the SVG text if not careful, but textContent ignores SVG tags, so it should be fine.
      html += `<button class="quick-action-btn has-tooltip" data-tooltip="Hızlı aksiyon ile AI'a anında talimat gönderin." onclick="document.getElementById('copilot-text-input').value=this.textContent; document.getElementById('copilot-send-btn').click();">` + q + infoIconBlue + `</button>`;
    });
    
    quickActionsContainer.innerHTML = html;
  }

JS;

$c = $func . "\n" . $c;
// Replace local calls with window object call to be safe, or just leave them as they will resolve via window.
$c = str_replace('renderDynamicQuickActions();', 'window.renderDynamicQuickActions();', $c);

file_put_contents("frontend/js/copilot.js", $c);
echo "Fixed JS.\n";
?>
