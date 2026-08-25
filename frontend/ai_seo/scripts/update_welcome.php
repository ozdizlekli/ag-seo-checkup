<?php
$index = file_get_contents("frontend/index.php");

if (strpos($index, 'dashboard_data.php') === false) {
    $index = str_replace("<?php\nsession_start();", "<?php\nsession_start();\nrequire_once __DIR__ . '/dashboard_data.php';", $index);
}

$new_welcome = <<<HTML
<div id="welcome-overlay">
  <div class="welcome-container" style="max-width: 900px; padding: 40px;">
    <div class="welcome-header">
      <h1>Agency OS Kontrol Paneli</h1>
      <p>Yapay zeka destekli SEO ve İçerik Yönetim Platformu. Başlamak için verilerinizi inceleyin veya yeni analiz başlatın.</p>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px;">
      <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
         <div style="font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Toplam Analiz</div>
         <div style="font-size: 32px; font-weight: 700; color: #2563eb;"><?= \$dashboardData['totalAnalyses'] ?></div>
      </div>
      <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
         <div style="font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Rakip Analizi (Battle)</div>
         <div style="font-size: 32px; font-weight: 700; color: #dc2626;"><?= \$dashboardData['battleCount'] ?></div>
      </div>
      <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
         <div style="font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Ortalama E-E-A-T</div>
         <div style="font-size: 32px; font-weight: 700; color: #16a34a;"><?= \$dashboardData['avgEEAT'] > 0 ? \$dashboardData['avgEEAT'] . '/100' : '-' ?></div>
      </div>
    </div>

    <div style="background: #fff; border-radius: 12px; border: 1px solid var(--border); overflow: hidden; margin-bottom: 32px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
      <div style="padding: 16px; background: #f8fafc; border-bottom: 1px solid var(--border); font-weight: 600; font-size: 14px; color: var(--text);">Geçmişte Taranan Son 5 Site</div>
      <?php if(empty(\$dashboardData['recent'])): ?>
         <div style="padding: 16px; color: var(--muted); font-size: 13px;">Henüz analiz bulunmuyor.</div>
      <?php else: ?>
         <?php foreach(\$dashboardData['recent'] as \$r): ?>
         <div style="display:flex; justify-content:space-between; align-items:center; padding: 12px 16px; border-bottom: 1px solid var(--border);">
            <div style="font-weight: 500; font-size: 13px; color: var(--text);"><?= htmlspecialchars(\$r['url']) ?> <span style="font-size:11px; color:var(--muted); margin-left:8px;"><?= htmlspecialchars(\$r['date']) ?></span></div>
            <div style="font-size: 12px; font-weight: 600; color: <?= \$r['color'] ?>; background: <?= \$r['color'] ?>20; padding: 4px 8px; border-radius: 6px;"><?= \$r['health'] ?></div>
         </div>
         <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div style="text-align: center;">
      <button class="btn btn--primary" id="btn-dashboard-start" style="padding: 14px 28px; font-size: 16px; font-weight: 600; border-radius: 12px;">
         <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px; vertical-align:middle;"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
         Yeni Analiz Başlat
      </button>
    </div>
  </div>
</div>
HTML;

$index = preg_replace('/<div id="welcome-overlay">.*?<\/div>\s*<\/div>\s*<\/div>/s', $new_welcome, $index);

file_put_contents("frontend/index.php", $index);
echo "Welcome Overlay Replaced!\n";
?>
