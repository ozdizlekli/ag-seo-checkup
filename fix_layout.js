const fs = require('fs');

let html = fs.readFileSync('frontend/index.php', 'utf8');

// Find sections
let surumStart = html.indexOf('<div class="card mt-20">\n          <div class="card__title">Sürüm Geçmişi</div>');
let tab1End = html.indexOf('</section>', surumStart);

let tab2End = html.indexOf('</section>', html.indexOf('id="tab-2"'));

let metinYapayStart = html.indexOf('<div class="card mt-20">\n          <div class="card__title">Metin Yapay Zeka Analizi</div>');
let schema1Start = html.indexOf('<div class="card mt-20" id="t3-schema-card">');
let historyStart = html.indexOf('<div class="card mt-20" id="copilot-history-card">');
let schema2Start = html.indexOf('<!-- SCHEMA ÜRETİCİ');
let tab3End = html.indexOf('</section>', schema2Start);

let metinYapayBlock = html.substring(metinYapayStart, schema1Start);
let schemaBlock = html.substring(schema1Start, historyStart) + html.substring(schema2Start, tab3End);
let historyBlock = html.substring(historyStart, schema2Start);

// Remove the misplaced blocks from Tab 3
let newHtml = html.substring(0, metinYapayStart) + historyBlock + html.substring(tab3End);

// Insert Schema blocks into Tab 2 (before closing section)
let t2 = newHtml.indexOf('</section>', newHtml.indexOf('id="tab-2"'));
newHtml = newHtml.substring(0, t2) + schemaBlock + newHtml.substring(t2);

// Insert Metin Yapay Zeka into Tab 1 (before closing section)
let t1 = newHtml.indexOf('</section>', newHtml.indexOf('id="tab-1"'));
newHtml = newHtml.substring(0, t1) + metinYapayBlock + newHtml.substring(t1);

fs.writeFileSync('frontend/index.php', newHtml);
console.log("Fixed HTML Layout!");
