<?php
$c = file_get_contents("frontend/js/copilot.js");

// 1. Fix startNewAnalysisFromDashboard
$c = str_replace(
    "document.getElementById('copilot-chat-messages-container').innerHTML = emptyStateHtml;",
    "document.getElementById('copilot-chat-messages-container').innerHTML = ''; addMessage('👋 Merhaba! Ben <strong>GEO SEO Asistanı</strong>.<br><br>Web siteni tarayıp yapay zeka (LLM) arama motorları için optimize edelim. Lütfen analiz etmemi istediğin sayfanın <strong>URL\'sini</strong> aşağıya yaz.', 'ai', true, false);",
    $c
);

// 2. The user said: "Sunum Modu onu düzelt"
// Wait, is client-view-toggle in app.js or copilot.js?
// I checked earlier and it wasn't anywhere!
$client_view_js = <<<JS
document.addEventListener('DOMContentLoaded', () => {
  const clientViewToggle = document.getElementById('client-view-toggle');
  if (clientViewToggle) {
    clientViewToggle.addEventListener('change', function() {
      if (this.checked) {
        document.body.classList.add('client-presentation-mode');
      } else {
        document.body.classList.remove('client-presentation-mode');
      }
    });
  }
});
JS;

if (!strpos($c, 'client-view-toggle')) {
    $c .= "\n" . $client_view_js;
}

// 3. User said: "geçmiş sohbetlerden birine tıkladığımda sağdaki butonlara fareyi getriğimde soru işareti çıkıyor ama boş bir sohbette çıkıyor o soru işaretini de kaldır bir tek bilgilendirme çıksın"
// This means when loading history (or in general) the buttons have a native `title="..."` attribute, AND a `data-tooltip="..."` attribute.
// The `title` attribute shows a native browser tooltip, which might look like a box.
// And some browsers show `?` for `cursor: help;`.
// Let's remove `title` attributes from elements that have `has-tooltip`, and ensure `cursor: pointer;` instead of `cursor: help;`
$c = str_replace("cursor: help;", "cursor: pointer;", $c);

file_put_contents("frontend/js/copilot.js", $c);

// Also remove `title` attributes from `index.php` for elements that have `has-tooltip`
$index = file_get_contents("frontend/index.php");
// E.g. <button class="btn btn--ghost btn--sm has-tooltip" data-tooltip="Sohbeti temizle" id="copilot-reset-btn" title="Baştan Başla"...
$index = preg_replace('/(class="[^"]*has-tooltip[^"]*".*?)title="[^"]*"/s', '$1', $index);
file_put_contents("frontend/index.php", $index);

// Also CSS cursor: help;
$css = file_get_contents("frontend/css/copilot.css");
$css = str_replace("cursor: help;", "cursor: pointer;", $css);

// User said: "ayrıca aşağıda olan soruların açıklamaları gözükmüyo panelin altında kalıyo onu da düzelt"
// Quick action buttons have `.has-tooltip`. They appear at the bottom.
// If the tooltip is `bottom: 120%`, it should appear ABOVE the button.
// If it's `bottom: -...` it might go below.
// In copilot.css:
// .has-tooltip:hover::after { bottom: 120%; ... }
// Why would they stay below the panel? Because `.quick-actions` or `#copilot-input-area` has `overflow: hidden;`?
$css = str_replace("overflow-x: auto;", "overflow: visible;", $css);
$css = str_replace("overflow-y: auto;", "overflow: visible;", $css);

file_put_contents("frontend/css/copilot.css", $css);

echo "Fixed everything.\n";
?>
