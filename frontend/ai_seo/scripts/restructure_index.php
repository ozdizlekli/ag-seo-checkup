<?php
$index = file_get_contents("frontend/index.php");

// 1. SPLIT VIEW ARCHITECTURE IN TAB 3
// Find the start of tab-3
$index = str_replace(
    '<section class="tab-panel" id="tab-3" style="display:none;">',
    '<section class="tab-panel" id="tab-3" style="display:none;">
          <!-- 1. GÖRÜNÜM: KONTROL PANELİ -->
          <div id="ai-seo-dashboard-view"></div>
          
          <!-- 2. GÖRÜNÜM: SOHBET EKRANI -->
          <div id="copilot-action-view" style="display:none;">',
    $index
);

// Close the new copilot-action-view div at the end of tab-3
$index = str_replace(
    '</section>

        <!-- YENİ 4. SEKME: YAPILACAKLAR / EKSİKLİKLER -->',
    '</div>
        </section>

        <!-- YENİ 4. SEKME: YAPILACAKLAR / EKSİKLİKLER -->',
    $index
);

// 2. ADD "GERİ DÖN" BUTTON TO COPILOT HEADER
// Find copilot-progress and insert the return button right before it
$index = preg_replace(
    '/(<div class="copilot-progress" id="copilot-progress".*?>)/s',
    '<button id="btn-return-dashboard" class="btn btn--ghost btn--sm" style="margin-right: 16px; display:inline-flex; align-items:center; color: var(--muted); font-weight:600; padding:4px 8px; border-radius:6px; font-size:13px;">
       <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
       Kontrol Paneline Dön
    </button>
    $1',
    $index
);

// 3. FIX TOOLTIPS FOR THE STEPS
// We will replace `data-tooltip="..."` on steps with HTML tooltip-text
$steps = [
    1 => ["İş Bağlamı", "Sitenizin sektörü, hitap ettiği kitle ve anahtar kelime varlıkları yapay zeka gözüyle analiz edilir."],
    2 => ["Etkililik", "Kullanıcıların bu sayfa bağlamında sorabileceği en kritik sorular ve sitenizin bunlara verdiği cevapların kalitesi ölçülür."],
    3 => ["Rakip Analizi", "Sektördeki en güçlü rakiplere kıyasla sitenizin içerik açısından hangi noktalarda eksik kaldığı tespit edilir."],
    4 => ["AI Güveni", "E-E-A-T (Deneyim, Uzmanlık, Otoriterlik, Güvenilirlik) kurallarına göre sitenizin yapay zekaya ne kadar güven verdiği puanlanır."],
    5 => ["Optimizasyon", "SGE (Search Generative Experience) ile uyumlu, okunabilirliği yüksek yepyeni bir içerik taslağı sunulur."],
    6 => ["Entegrasyon", "Yapay zekanın sitenizi sadece kelime kelime değil, anlamsal bir bütün (Semantik ve Şema) olarak nasıl algıladığı özetlenir."]
];

for ($i = 1; $i <= 6; $i++) {
    // regex to match the step div
    $pattern = '/<div class="copilot-step has-tooltip" data-step="' . $i . '" id="cp-step-' . $i . '" data-tooltip=".*?" style="cursor: pointer;">\s*<div class="copilot-step-circle">.*?<\/div>\s*<div class="step-label-wrapper"><span>.*?<\/span><\/div>.*?<\/div>/s';
    
    $desc = $steps[$i][1];
    $name = $steps[$i][0];
    
    $new_step = '
<div class="copilot-step info-tooltip" data-step="' . $i . '" id="cp-step-' . $i . '" style="cursor: pointer;">
  <div class="copilot-step-circle"><span class="num">' . $i . '</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
  <div class="step-label-wrapper">
      <span>' . $name . '</span>
  </div>
  <div class="tooltip-text">
      ' . $desc . '
      <br><br>
      <span style="font-size: 10px; font-style: italic; color: #cbd5e1;">Sohbette bu adıma gitmek için tıklayın</span>
  </div>
  <button class="btn-fix-issue" data-step="' . $i . '" id="btn-fix-' . $i . '" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); margin-top: 6px; display: none; background: transparent; color: #2563eb; border: none; padding: 2px; box-shadow: none; font-size: 16px; cursor: pointer; z-index: 10;" title="' . $i . '. Adımı Çöz">🔧</button>
</div>';
    
    $index = preg_replace($pattern, $new_step, $index);
}

// Write it back
file_put_contents("frontend/index.php", $index);
echo "index.php restructured!\n";
?>
