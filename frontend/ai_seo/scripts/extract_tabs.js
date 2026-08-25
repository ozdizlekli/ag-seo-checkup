const fs = require('fs');

let content = fs.readFileSync('frontend/index.php', 'utf-8');

const tab3Match = content.match(/(<section class="tab-panel" id="tab-3">[\s\S]*?<\/section>)<!-- YENİ 4\. SEKME/);
if (tab3Match) {
    fs.writeFileSync('frontend/ai_seo/views/tab_ai_seo.php', tab3Match[1]);
    content = content.replace(tab3Match[1], "<?php include 'ai_seo/views/tab_ai_seo.php'; ?>\n");
}

const tab4Match = content.match(/(<section class="tab-panel" id="tab-4">[\s\S]*?<\/section>)\s*(?=<\/div>\s*<\/main>)/);
if (tab4Match) {
    fs.writeFileSync('frontend/ai_seo/views/tab_todos.php', tab4Match[1]);
    content = content.replace(tab4Match[1], "<?php include 'ai_seo/views/tab_todos.php'; ?>\n");
}

fs.writeFileSync('frontend/index.php', content);
console.log("Extraction complete");
