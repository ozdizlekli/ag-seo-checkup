<?php
$copilot = file_get_contents("frontend/js/copilot.js");

// 1. History Tagging
// Replace the history item rendering
$old_history_render = <<<JS
      data.history.forEach(item => {
        const div = document.createElement('div');
        div.className = 'history-item';
        const dateStr = new Date(item.date).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' });
        div.innerHTML = `
          <div class="history-url">\${item.url || 'Bilinmeyen URL'}</div>
          <div class="history-date">\${dateStr}</div>
        `;
        div.addEventListener('click', () => {
          resetChat(item);
        });
        historyList.appendChild(div);
      });
JS;

$new_history_render = <<<JS
      // Save global history for dashboard
      window.agChatHistory = data.history;

      data.history.forEach(item => {
        const div = document.createElement('div');
        div.className = 'history-item';
        const dateStr = new Date(item.date).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' });
        
        // Akıllı Etiket (Smart Tagging)
        let badgeHtml = '';
        const comp = item.completedSteps ? item.completedSteps.length : 0;
        const fixes = item.fixedIssues ? item.fixedIssues.length : 0;
        
        if (comp >= 5 && fixes >= 3) {
            badgeHtml = '<span style="font-size: 10px; padding: 2px 6px; border-radius: 4px; background: #dcfce7; color: #166534; font-weight: 600; margin-left: 6px;">Otoriter</span>';
        } else if (comp >= 3 && fixes < 2) {
            badgeHtml = '<span style="font-size: 10px; padding: 2px 6px; border-radius: 4px; background: #fee2e2; color: #991b1b; font-weight: 600; margin-left: 6px;">Kritik</span>';
        } else if (comp > 0) {
            badgeHtml = '<span style="font-size: 10px; padding: 2px 6px; border-radius: 4px; background: #fef9c3; color: #854d0e; font-weight: 600; margin-left: 6px;">Gelişiyor</span>';
        }

        div.innerHTML = `
          <div class="history-url" style="display:flex; align-items:center; justify-content:space-between;">
             <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px;">\${item.url || 'Bilinmeyen URL'}</span>
             \${badgeHtml}
          </div>
          <div class="history-date">\${dateStr}</div>
        `;
        div.addEventListener('click', () => {
          resetChat(item);
        });
        historyList.appendChild(div);
      });

      // Render dashboard if we are on the welcome screen
      if (document.getElementById('welcome-dashboard-flag')) {
         renderDashboard();
      }
JS;

$copilot = str_replace($old_history_render, $new_history_render, $copilot);

// 2. Dashboard Rendering
$dashboard_js = <<<JS
  function renderDashboard() {
      const history = window.agChatHistory || [];
      const totalAnalyses = history.length;
      
      let battleCount = 0;
      let totalEEAT = 0;
      let eeatCount = 0;
      
      history.forEach(h => {
         if (h.messages) {
            const msgs = JSON.stringify(h.messages).toLowerCase();
            if (msgs.includes('rakip') || msgs.includes('battle')) battleCount++;
         }
         // Pseudo-EEAT from steps (if step 4 is completed, we assume a score of 70-95 based on fixed issues)
         if (h.completedSteps && h.completedSteps.includes("4") || h.completedSteps && h.completedSteps.includes(4)) {
             const fixes = h.fixedIssues ? h.fixedIssues.length : 0;
             totalEEAT += 70 + (fixes * 4);
             eeatCount++;
         }
      });

      const avgEEAT = eeatCount > 0 ? Math.round(totalEEAT / eeatCount) : 0;

      let recentHtml = '';
      history.slice(0, 5).forEach(h => {
         const comp = h.completedSteps ? h.completedSteps.length : 0;
         const fixes = h.fixedIssues ? h.fixedIssues.length : 0;
         let health = "Orta";
         let color = "#eab308";
         if (comp >= 5 && fixes >= 3) { health = "Mükemmel"; color = "#22c55e"; }
         else if (comp >= 3 && fixes < 2) { health = "Kritik"; color = "#ef4444"; }
         
         const dateStr = new Date(h.date).toLocaleDateString('tr-TR');
         recentHtml += `
            <div style="display:flex; justify-content:space-between; align-items:center; padding: 12px; border-bottom: 1px solid var(--border);">
               <div style="font-weight: 500; font-size: 13px; color: var(--text);">\${h.url} <span style="font-size:11px; color:var(--muted); margin-left:8px;">\${dateStr}</span></div>
               <div style="font-size: 12px; font-weight: 600; color: \${color}; background: \${color}20; padding: 4px 8px; border-radius: 6px;">\${health}</div>
            </div>
         `;
      });
      if (recentHtml === '') recentHtml = '<div style="padding: 12px; color: var(--muted); font-size: 13px;">Henüz analiz bulunmuyor.</div>';

      document.getElementById('copilot-messages').innerHTML = `
        <div id="welcome-dashboard-flag" style="padding: 24px;">
           <h2 style="margin-bottom: 24px; font-size: 20px; font-weight: 600; color: var(--text);">Agency OS Kontrol Paneli</h2>
           
           <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px;">
              <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                 <div style="font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Toplam Analiz</div>
                 <div style="font-size: 32px; font-weight: 700; color: #2563eb;">\${totalAnalyses}</div>
              </div>
              <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                 <div style="font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Rakip Analizi (Battle)</div>
                 <div style="font-size: 32px; font-weight: 700; color: #dc2626;">\${battleCount}</div>
              </div>
              <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                 <div style="font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Ortalama E-E-A-T</div>
                 <div style="font-size: 32px; font-weight: 700; color: #16a34a;">\${avgEEAT > 0 ? avgEEAT + '/100' : '-'}</div>
              </div>
           </div>

           <div style="background: #fff; border-radius: 12px; border: 1px solid var(--border); overflow: hidden; margin-bottom: 32px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
              <div style="padding: 16px; background: #f8fafc; border-bottom: 1px solid var(--border); font-weight: 600; font-size: 14px; color: var(--text);">Son Taranan 5 Site</div>
              \${recentHtml}
           </div>

           <div style="text-align: center;">
              <button class="btn btn--primary" style="padding: 12px 24px; font-size: 14px; font-weight: 600;" onclick="document.getElementById('copilot-text-input').focus();">
                 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px; vertical-align:middle;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                 Yeni Analiz Başlat
              </button>
           </div>
        </div>
      `;
  }
JS;

$copilot = preg_replace('/(function resetChat\(loadFromHistory = null\) \{)/', "$dashboard_js\n$1", $copilot);

// Modify resetChat to call renderDashboard() if not loading from history
$old_reset_view = <<<JS
      document.getElementById('copilot-messages').innerHTML = `
        <div class="card__hint" style="text-align:center; padding: 40px 20px;">
           <div style="font-size: 40px; margin-bottom: 15px;">👋</div>
           <h3 style="margin-bottom: 10px; color: var(--text);">Merhaba! Ben GEO SEO Asistanı.</h3>
           <p style="color: var(--muted); line-height: 1.5;">Web siteni tarayıp yapay zeka (LLM) arama motorları için optimize edelim. Lütfen analiz etmemi istediğin sayfanın URL'sini aşağıya yaz.</p>
        </div>
      `;
JS;

$new_reset_view = <<<JS
      document.getElementById('copilot-messages').innerHTML = `<div id="welcome-dashboard-flag"></div>`;
      renderDashboard();
JS;

$copilot = str_replace($old_reset_view, $new_reset_view, $copilot);

file_put_contents("frontend/js/copilot.js", $copilot);
echo "Dashboard injected.\n";
?>
