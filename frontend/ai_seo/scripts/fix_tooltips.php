<?php
$index = file_get_contents("frontend/index.php");

// Info Icon SVG
$info_icon = '<svg class="info-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 4px; color: #94a3b8;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';

// 1. Tooltips with Info Icon
// İş Bağlamı
$old_1 = '<span class="has-tooltip" data-tooltip="Sitenizin sektörü, hitap ettiği kitle ve anahtar kelime varlıkları yapay zeka gözüyle analiz edilir.">İş Bağlamı</span>';
$new_1 = '<div class="step-label-wrapper has-tooltip" data-tooltip="Sitenizin sektörü, hitap ettiği kitle ve anahtar kelime varlıkları yapay zeka gözüyle analiz edilir.&#10;&#10;Sohbette bu adıma gitmek için tıklayın"><span>İş Bağlamı</span>' . $info_icon . '</div>';
$index = str_replace($old_1, $new_1, $index);

// Etkililik
$old_2 = '<span class="has-tooltip" data-tooltip="Kullanıcıların bu sayfa bağlamında sorabileceği en kritik sorular ve sitenizin bunlara verdiği cevapların kalitesi ölçülür.">Etkililik</span>';
$new_2 = '<div class="step-label-wrapper has-tooltip" data-tooltip="Kullanıcıların bu sayfa bağlamında sorabileceği en kritik sorular ve sitenizin bunlara verdiği cevapların kalitesi ölçülür.&#10;&#10;Sohbette bu adıma gitmek için tıklayın"><span>Etkililik</span>' . $info_icon . '</div>';
$index = str_replace($old_2, $new_2, $index);

// Rakip Analizi
$old_3 = '<span class="has-tooltip" data-tooltip="Sektördeki en güçlü rakiplere kıyasla sitenizin içerik açısından hangi noktalarda eksik kaldığı tespit edilir.">Rakip Analizi</span>';
$new_3 = '<div class="step-label-wrapper has-tooltip" data-tooltip="Sektördeki en güçlü rakiplere kıyasla sitenizin içerik açısından hangi noktalarda eksik kaldığı tespit edilir.&#10;&#10;Sohbette bu adıma gitmek için tıklayın"><span>Rakip Analizi</span>' . $info_icon . '</div>';
$index = str_replace($old_3, $new_3, $index);

// AI Güveni
$old_4 = '<span class="has-tooltip" data-tooltip="E-E-A-T (Deneyim, Uzmanlık, Otoriterlik, Güvenilirlik) kurallarına göre sitenizin yapay zekaya ne kadar güven verdiği puanlanır.">AI Güveni</span>';
$new_4 = '<div class="step-label-wrapper has-tooltip" data-tooltip="E-E-A-T (Deneyim, Uzmanlık, Otoriterlik, Güvenilirlik) kurallarına göre sitenizin yapay zekaya ne kadar güven verdiği puanlanır.&#10;&#10;Sohbette bu adıma gitmek için tıklayın"><span>AI Güveni</span>' . $info_icon . '</div>';
$index = str_replace($old_4, $new_4, $index);

// Optimizasyon
$old_5 = '<span class="has-tooltip" data-tooltip="SGE (Search Generative Experience) ile uyumlu, okunabilirliği yüksek yepyeni bir içerik taslağı sunulur.">Optimizasyon</span>';
$new_5 = '<div class="step-label-wrapper has-tooltip" data-tooltip="SGE (Search Generative Experience) ile uyumlu, okunabilirliği yüksek yepyeni bir içerik taslağı sunulur.&#10;&#10;Sohbette bu adıma gitmek için tıklayın"><span>Optimizasyon</span>' . $info_icon . '</div>';
$index = str_replace($old_5, $new_5, $index);

// Entegrasyon
$old_6 = '<span class="has-tooltip" data-tooltip="Yapay zekanın sitenizi sadece kelime kelime değil, anlamsal bir bütün (Semantik ve Şema) olarak nasıl algıladığı özetlenir.">Entegrasyon</span>';
$new_6 = '<div class="step-label-wrapper has-tooltip" data-tooltip="Yapay zekanın sitenizi sadece kelime kelime değil, anlamsal bir bütün (Semantik ve Şema) olarak nasıl algıladığı özetlenir.&#10;&#10;Sohbette bu adıma gitmek için tıklayın"><span>Entegrasyon</span>' . $info_icon . '</div>';
$index = str_replace($old_6, $new_6, $index);

file_put_contents("frontend/index.php", $index);
echo "Updated index.php with step-label-wrapper and info icon\n";

// Update CSS
$css = file_get_contents("frontend/css/copilot.css");

$old_tooltip_css = <<<'EOF'
/* 1. Tooltip (Microlearning) */
.has-tooltip {
  position: relative;
  cursor: help;
  border-bottom: 1px dashed #cbd5e1;
}
.has-tooltip:hover::after {
  content: attr(data-tooltip);
  position: absolute;
  bottom: 120%;
  left: 50%;
  transform: translateX(-50%);
  background: #1e293b;
  color: #fff;
  padding: 6px 10px;
  border-radius: 6px;
  font-size: 12px;
  width: max-content;
  max-width: 200px;
  white-space: pre-wrap;
  z-index: 100;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
  text-align: center;
  pointer-events: none;
}
.has-tooltip:hover::before {
  content: "";
  position: absolute;
  bottom: 110%;
  left: 50%;
  transform: translateX(-50%);
  border: 5px solid transparent;
  border-top-color: #1e293b;
  z-index: 100;
}
EOF;

$new_tooltip_css = <<<'EOF'
/* 1. Tooltip & Interactive Steps */
.step-label-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  cursor: pointer; /* Change to pointer to show it's clickable */
  transition: transform 0.2s ease, opacity 0.2s ease;
}

/* Hover effects for the step container */
.copilot-step {
  transition: transform 0.2s ease, background-color 0.2s ease;
  border-radius: 8px;
  padding: 4px;
}
.copilot-step:hover {
  transform: translateY(-2px);
  background-color: rgba(59, 130, 246, 0.05); /* Slight blue highlight */
}

/* Tooltip implementation */
.has-tooltip:hover::after {
  content: attr(data-tooltip);
  position: absolute;
  bottom: 120%;
  left: 50%;
  transform: translateX(-50%);
  background: #1e293b;
  color: #fff;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 12px;
  width: max-content;
  max-width: 220px;
  white-space: pre-wrap;
  z-index: 100;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
  text-align: center;
  pointer-events: none;
  line-height: 1.4;
}

/* First line vs last line styling within the tooltip isn't purely possible with just attr() in standard CSS without JS injection, 
   but white-space: pre-wrap and the \n\n in HTML attribute handles the line break. */

.has-tooltip:hover::before {
  content: "";
  position: absolute;
  bottom: 110%;
  left: 50%;
  transform: translateX(-50%);
  border: 5px solid transparent;
  border-top-color: #1e293b;
  z-index: 100;
}

.info-icon {
  opacity: 0.6;
  transition: opacity 0.2s, color 0.2s;
}
.step-label-wrapper:hover .info-icon {
  opacity: 1;
  color: #3b82f6 !important;
}
EOF;

$css = str_replace($old_tooltip_css, $new_tooltip_css, $css);
file_put_contents("frontend/css/copilot.css", $css);
echo "Updated CSS for Tooltips & Hover Effects\n";

?>
