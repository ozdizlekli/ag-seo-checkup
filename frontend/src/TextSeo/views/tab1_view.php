<!-- Main Content -->
<main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
    
    <!-- Input Section -->
    <section id="inputSection" class="max-w-4xl mx-auto bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden transition-all duration-300">
        <div class="p-6">
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-2xl font-bold">Metin Analizi</h2>
                <div class="flex gap-2">
                    <button type="button" id="copyInputBtn" class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition flex items-center gap-1">
                        <i class="ph ph-copy"></i> Kopyala
                    </button>
                    <button type="button" id="clearInputBtn" class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-danger transition flex items-center gap-1">
                        <i class="ph ph-trash"></i> Temizle
                    </button>
                </div>
            </div>
            <p class="text-gray-500 text-sm mb-6">SEO performansınızı artırmak ve arama motorlarında üst sıralara çıkmak için içeriğinizi analiz edin.</p>
            
            <div class="relative mb-4">
                <textarea id="rawText" rows="10" class="w-full p-4 border border-gray-300 rounded-lg bg-gray-50 focus:ring-2 focus:ring-primary focus:border-transparent outline-none resize-y font-mono text-sm" placeholder="Analiz edilecek metni buraya yapıştırın..."></textarea>
                <div class="absolute bottom-3 right-3 text-xs text-gray-400 bg-white px-2 py-1 rounded">
                    <span id="charCount">0</span> Karakter | <span id="wordCount">0</span> Kelime
                </div>
            </div>

            <!-- Accordion for Options -->
            <div class="border border-gray-200 rounded-lg mb-6 overflow-hidden">
                <button id="optionsToggle" class="w-full flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 transition text-sm font-semibold">
                    <div class="flex items-center gap-2">
                        <i class="ph ph-sliders"></i> Gelişmiş Ayarlar (Opsiyonel)
                    </div>
                    <i class="ph ph-caret-down transition-transform duration-200" id="optionsIcon"></i>
                </button>
                <div id="optionsPanel" class="hidden p-4 bg-white border-t border-gray-200 ">
                    <div class="w-full">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Hedef Anahtar Kelime</label>
                        <input type="text" id="targetKeyword" class="w-full p-2 border border-gray-300 rounded-md bg-transparent focus:ring-1 focus:ring-primary outline-none text-sm" placeholder="Örn: on page seo">
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button id="analyzeBtn" class="bg-primary hover:bg-blue-700 text-white px-8 py-3 rounded-lg font-semibold shadow-md transition-all flex items-center gap-2 group">
                    <i class="ph ph-rocket-launch text-xl group-hover:-translate-y-1 group-hover:translate-x-1 transition-transform"></i> Hemen Analiz Et
                </button>
            </div>
        </div>
        
        <!-- Loading Stepper -->
        <div id="loadingOverlay" class="hidden absolute inset-0 bg-white/90 flex-col items-center justify-center z-10">
            <div class="w-full max-w-md px-8">
                <div class="flex justify-between mb-2">
                    <span class="text-xs font-semibold text-primary" id="loadingStatus">Sunucuya bağlanılıyor...</span>
                    <span class="text-xs font-semibold text-gray-500" id="loadingPercent">0%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 mb-6">
                    <div id="loadingProgress" class="bg-primary h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
                <ul class="text-sm text-gray-500 space-y-2" id="loadingSteps">
                    <li class="flex items-center gap-2"><i class="ph ph-circle"></i> İçerik yapısı ve okunabilirlik ölçülüyor</li>
                    <li class="flex items-center gap-2"><i class="ph ph-circle"></i> Anahtar kelime yoğunluğu ve dağılımı analiz ediliyor</li>
                    <li class="flex items-center gap-2"><i class="ph ph-circle"></i> Yapay zeka ile SEO stratejisi oluşturuluyor</li>
                    <li class="flex items-center gap-2"><i class="ph ph-circle"></i> İçeriğiniz Google dostu hale getiriliyor</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- History Section -->
    <section id="historySection" class="max-w-4xl mx-auto mt-8 hidden">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold flex items-center gap-2">
                <i class="ph ph-clock-counter-clockwise text-primary text-xl"></i> Son Analiz Geçmişi
            </h3>
            <button id="clearAllHistoryBtn" class="text-xs font-semibold text-danger hover:underline flex items-center gap-1">
                <i class="ph ph-trash"></i> Tümünü Temizle
            </button>
        </div>
        <div id="historyList" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- JS ile doldurulacak -->
        </div>
    </section>

    <!-- Results Section -->
    <section id="resultsSection" class="hidden mt-8">
        <div class="mb-4">
            <button id="backToInputBtn" class="flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-primary transition bg-white px-4 py-2 rounded-lg border border-gray-200 shadow-sm">
                <i class="ph ph-arrow-left text-lg"></i> Geri Dön / Yeni Analiz
            </button>
        </div>
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-6 gap-4">
            <div>
                <h2 class="text-3xl font-bold flex items-center gap-3">
                    Analiz Raporu
                    <span class="text-sm px-3 py-1 bg-green-100 text-green-700 rounded-full font-medium border border-green-200 ">Başarılı</span>
                </h2>
                <p class="text-gray-500 text-sm mt-1">İçeriğinizin detaylı SEO ve kelime analiz raporu başarıyla oluşturuldu.</p>
            </div>
            <div class="flex items-center gap-4">
                <!-- Gauge -->
                <div class="flex items-center gap-3 bg-white px-4 py-2 rounded-lg shadow-sm border border-gray-100 ">
                    <div class="relative w-12 h-12">
                        <svg class="w-full h-full" viewBox="0 0 36 36">
                            <path class="text-gray-200 " stroke-width="3" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            <path id="healthScoreCircle" class="text-success" stroke-width="3" stroke-dasharray="75, 100" stroke-linecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span id="healthScoreText" class="font-bold text-sm">75</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="text-sm font-semibold">Sağlık Skoru</div>
                        <button type="button" id="scoreInfoBtn" class="text-gray-400 hover:text-primary transition p-0.5 rounded-full hover:bg-gray-100" title="Skor Nasıl Hesaplanır?">
                            <i class="ph ph-info text-base"></i>
                        </button>
                    </div>
                </div>
                <button id="downloadPdfBtn" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 px-4 py-2.5 rounded-lg shadow-sm transition-colors flex items-center gap-2 font-medium text-sm">
                    <i class="ph ph-file-pdf text-lg text-red-500"></i> PDF İndir
                </button>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="border-b border-gray-200 mb-6 flex overflow-x-auto pb-2">
            <button class="tab-btn active px-6 py-3 font-semibold text-sm border-b-2 border-primary text-primary flex items-center gap-2 whitespace-nowrap" data-target="tab1">
                <i class="ph ph-chart-bar text-lg"></i> İçerik Analizi & Karne
            </button>
            <button class="tab-btn px-6 py-3 font-semibold text-sm border-b-2 border-transparent text-gray-500 hover:text-gray-700 flex items-center gap-2 whitespace-nowrap" data-target="tab2">
                <i class="ph ph-target text-lg"></i> Strateji & Anahtar Kelimeler
            </button>
            <button class="tab-btn px-6 py-3 font-semibold text-sm border-b-2 border-transparent text-gray-500 hover:text-gray-700 flex items-center gap-2 whitespace-nowrap" data-target="tab3">
                <i class="ph ph-map-pin-line text-lg"></i> Adım Adım Uygulama Planı
            </button>
            <button class="tab-btn px-6 py-3 font-semibold text-sm border-b-2 border-transparent text-gray-500 hover:text-gray-700 flex items-center gap-2 whitespace-nowrap" data-target="tab4">
                <i class="ph ph-magic-wand text-lg"></i> Yapay Zeka ile SEO Optimizasyonu
            </button>
        </div>

        <!-- Tabs Content -->
        <div class="tab-content relative min-h-[500px]">
            
            <!-- Tab 1: Detaylı Analiz & Karne -->
            <div id="tab1" class="tab-pane active space-y-6">
                <div class="flex flex-col space-y-6">
                    <!-- İçerik Yapısı ve Detaylı Analiz (Eski Sol Üst) -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm transition-all duration-300">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                 <i class="ph ph-stethoscope text-primary text-lg"></i> İçerik Yapısı ve Detaylı Analiz
                             </h3>
                             <span class="text-[11px] font-semibold text-primary bg-blue-50 px-3 py-1 rounded-full border border-blue-100 flex items-center gap-1">
                                 <i class="ph ph-cpu"></i> Gelişmiş İçerik ve Dil Analizi
                             </span>
                        </div>
                        <!-- 1. KATMAN: Hero 4'lü Özet Izgarası -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4" id="anatomyHeroCards">
                            <!-- JS ile dinamik doldurulacak -->
                        </div>
                        <!-- 2. KATMAN: Genişletilebilir Röntgen Çekmecesi (Accordion Trigger) -->
                        <div class="mt-6 pt-4 border-t border-gray-100">
                            <button type="button" id="toggleXrayBtn" class="w-full py-4 px-6 bg-gradient-to-r from-blue-50 to-slate-50 hover:from-blue-100 hover:to-slate-100 text-slate-800 rounded-xl text-sm font-bold transition-all shadow-sm flex items-center justify-between group border border-blue-100/50">
                                <span class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-white shadow-sm flex items-center justify-center border border-blue-50">
                                        <i class="ph-fill ph-sparkle text-primary text-lg group-hover:rotate-12 transition-transform"></i>
                                    </div>
                                    <span>Gelişmiş İçerik Analizi ve SEO Kriterleri</span>
                                    <span class="bg-primary text-white text-[10px] px-2.5 py-0.5 rounded-md font-semibold tracking-wide">24+ Metrik</span>
                                </span>
                                <i class="ph ph-caret-down text-slate-400 group-hover:text-primary transition-transform duration-200 text-lg" id="xrayChevron"></i>
                            </button>
                            <!-- Açılır X-Ray Paneli (Varsayılan: Hidden) -->
                            <div id="xrayDetailPanel" class="hidden mt-5 space-y-4 transition-all duration-300">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4" id="xrayGrid">
                                    <!-- JS ile sütunlar doldurulacak -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Analiz Özeti ve Kritik Sorunlar (Eski Sol Alt) -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                            <i class="ph ph-sparkle text-amber-500 text-lg"></i> AI Analiz Özeti
                        </h3>
                        <p id="aiSummary" class="text-sm leading-relaxed mb-5 text-gray-700 bg-amber-50/60 p-4 rounded-xl border border-amber-200/50 italic"></p>
                        <h4 class="text-sm font-bold text-danger mb-3 flex items-center gap-2"><i class="ph-fill ph-warning-circle"></i> Kritik İyileştirme Alanları:</h4>
                        <ul id="aiIssues" class="text-sm space-y-2 list-disc list-inside text-gray-600 bg-red-50/30 p-4 rounded-xl border border-red-100/50"></ul>
                    </div>

                    <!-- Okunabilirlik (Readability) Rehberi (Eski Sağ Üst) -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-5 flex items-center gap-2">
                            <i class="ph ph-book-open-text text-primary text-lg"></i> Okunabilirlik & Anlaşılırlık Rehberi
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="readabilityStats">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                    
                    <!-- Başlık Ağacı Haritası (Eski Sağ Alt) -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-5 flex items-center gap-2">
                            <i class="ph ph-tree-structure text-indigo-500 text-lg"></i> İçerik Başlık Yapısı
                        </h3>
                        <div id="headingTreeContainer" class="bg-slate-50 p-5 rounded-xl border border-slate-200/60 space-y-3 overflow-x-auto">
                            <!-- JS will populate -->
                        </div>
                    </div>
                </div>

                <!-- N-Gram Chart -->
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4">En Sık Kullanılan Anahtar Kelimeler ve Kelime Grupları</h3>
                    <div class="h-64 w-full">
                        <canvas id="ngramChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Strateji -->
            <div id="tab2" class="tab-pane hidden space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Quotas -->
                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                            <i class="ph ph-plus-circle text-primary"></i> İçeriğe Eklenmesi Önerilen Anahtar Kelimeler
                        </h3>
                        <div class="overflow-hidden rounded-lg border border-gray-200 ">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-200 ">
                                    <tr>
                                        <th class="px-4 py-3">Anahtar Kelime</th>
                                        <th class="px-4 py-3 text-center">Eklenecek Adet</th>
                                    </tr>
                                </thead>
                                <tbody id="quotasTable" class="divide-y divide-gray-200 ">
                                    <!-- Populated by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Semantic Gaps -->
                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                            <i class="ph ph-puzzle-piece text-warning"></i> İçerikte Eksik Kalan Önemli Konular
                        </h3>
                        <div id="semanticGapsList" class="flex flex-wrap gap-2">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>

                <!-- PAA -->
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                        <i class="ph ph-question text-purple-500"></i> Google'da Kullanıcıların En Çok Sorduğu Sorular
                    </h3>
                    <p class="text-xs text-gray-500 mb-4">Bu soruları içeriğe ekleyerek Google zengin sonuçlarında çıkma ihtimalinizi artırabilirsiniz.</p>
                    <ul id="paaList" class="space-y-3">
                        <!-- Populated by JS -->
                    </ul>
                </div>
            </div>

            <!-- Tab 3: Konumsal Plan -->
            <div id="tab3" class="tab-pane hidden space-y-6">
                <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                    <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-6 flex items-center gap-2">
                        <i class="ph ph-path text-primary"></i> İçerik Geliştirme Yol Haritası
                    </h3>
                    
                    <div class="relative border-l-2 border-primary/30 ml-4 pl-6 space-y-8" id="roadmapList">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>

            <!-- Tab 4: AI Düzeltme -->
            <div id="tab4" class="tab-pane hidden w-full h-full flex flex-col">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider">Google Dostu SEO'lu Yeni Metin</h3>
                    <div class="flex gap-2">
                        <button id="toggleDiffBtn" class="text-xs font-semibold px-3 py-1.5 rounded bg-gray-100 hover:bg-gray-200 transition">Değişiklikleri Karşılaştır</button>
                        <button id="copyBtn" class="text-xs font-semibold px-3 py-1.5 rounded bg-primary text-white hover:bg-blue-600 transition flex items-center gap-1">
                            <i class="ph ph-copy"></i> Kopyala
                        </button>
                    </div>
                </div>
                
                <div class="grid w-full grid-cols-1 md:grid-cols-2 gap-4 flex-grow mb-4 h-[400px]">
                    <div class="flex flex-col w-full h-full">
                        <div class="bg-gray-100 text-xs font-bold px-3 py-2 rounded-t-lg border border-gray-200 border-b-0 text-gray-500">Eski Metin (Sizin Yazdığınız)</div>
                        <textarea id="originalTextArea" class="w-full flex-grow p-4 border border-gray-200 rounded-b-lg bg-gray-50 focus:outline-none resize-none font-mono text-sm" readonly></textarea>
                    </div>
                    <div class="flex flex-col w-full h-full">
                        <div class="bg-green-50 text-xs font-bold px-3 py-2 rounded-t-lg border border-green-200 border-b-0 text-success flex justify-between">
                            <span>Yeni Metin (Google Uyumlu)</span>
                            <span id="wordDiffStat" class="font-normal text-gray-500 "></span>
                        </div>
                        <textarea id="optimizedTextArea" class="w-full flex-grow p-4 border border-green-200 rounded-b-lg bg-white focus:outline-none resize-none font-mono text-sm" readonly></textarea>
                    </div>
                </div>
                <div id="diffContainer" class="hidden w-full h-[400px] overflow-auto p-4 border border-gray-200 rounded-lg bg-white font-mono text-sm whitespace-pre-wrap leading-relaxed">
                    <!-- Populated by JS -->
                </div>
            </div>

        </div>
    </section>
</main>

<!-- PDF Modal -->
<div id="pdfModal" class="hidden fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center z-50 transition-opacity">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 mx-4 transform transition-all">
        <div class="flex items-center gap-3 mb-4">
            <div id="pdfModalIcon" class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-primary text-xl">
                <i class="ph ph-file-pdf"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900">Kurumsal SEO Raporu</h3>
                <p class="text-xs text-gray-500">A4 formatında ajans kalitesinde PDF çıktısı</p>
            </div>
        </div>
        
        <!-- Dinamik Mesaj Alanı -->
        <div id="pdfModalMessage" class="bg-gray-50 p-4 rounded-xl text-sm text-gray-700 mb-6 border border-gray-100">
            Tüm analizler, grafikler, strateji tablosu ve optimize edilmiş metin PDF olarak hazırlanacaktır. İndirmeyi onaylıyor musunuz?
        </div>

        <div class="flex justify-end gap-3">
            <button id="closePdfModalBtn" class="px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 rounded-lg transition">İptal</button>
            <button id="confirmPdfDownloadBtn" class="px-5 py-2 text-sm font-semibold text-white bg-primary hover:bg-blue-700 rounded-lg shadow-md transition flex items-center gap-2">
                <i class="ph ph-download-simple"></i> <span id="confirmBtnText">PDF Raporunu İndir</span>
            </button>
        </div>
    </div>
</div>

<!-- Sağlık Skoru Puanlama Modalı -->
<div id="scoreInfoModal" class="hidden fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 transition-opacity">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-gray-100 transform transition-all">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-primary">
                    <i class="ph ph-chart-donut text-lg"></i>
                </div>
                <h3 class="text-base font-bold text-gray-900">SEO Sağlık Skoru Nasıl Hesaplanır?</h3>
            </div>
            <button id="closeScoreInfoModalBtn" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 text-lg">
                <i class="ph ph-x"></i>
            </button>
        </div>
        
        <p class="text-xs text-gray-500 mb-4">Skor, metninizin arama motoru başarısını belirleyen 5 temel sütunun toplamından (100 Puan) oluşur:</p>

        <!-- 5 Puanlama Sütunu -->
        <div class="space-y-2.5 mb-5">
            <div class="p-2.5 bg-gray-50 rounded-xl border border-gray-100 flex justify-between items-center">
                <div>
                    <div class="text-xs font-bold text-gray-800">1. Anahtar Kelime & Arama Uyumu</div>
                    <div class="text-[11px] text-gray-500">İdeal yoğunluk (%1-%2), ilk 100 kelime ve başlık yerleşimi</div>
                </div>
                <span class="text-xs font-bold text-primary bg-blue-50 px-2 py-1 rounded-lg">25 Puan</span>
            </div>

            <div class="p-2.5 bg-gray-50 rounded-xl border border-gray-100 flex justify-between items-center">
                <div>
                    <div class="text-xs font-bold text-gray-800">2. Okunabilirlik & Akıcılık</div>
                    <div class="text-[11px] text-gray-500">Okunabilirlik Puanı, karmaşık kelime ve geçiş bağlaçları dengesi</div>
                </div>
                <span class="text-xs font-bold text-primary bg-blue-50 px-2 py-1 rounded-lg">25 Puan</span>
            </div>

            <div class="p-2.5 bg-gray-50 rounded-xl border border-gray-100 flex justify-between items-center">
                <div>
                    <div class="text-xs font-bold text-gray-800">3. İçerik Yapısı ve Başlıklar</div>
                    <div class="text-[11px] text-gray-500">H1/H2 başlık düzeni, paragraf düzeni ve cümle akıcılığı</div>
                </div>
                <span class="text-xs font-bold text-primary bg-blue-50 px-2 py-1 rounded-lg">20 Puan</span>
            </div>

            <div class="p-2.5 bg-gray-50 rounded-xl border border-gray-100 flex justify-between items-center">
                <div>
                    <div class="text-xs font-bold text-gray-800">4. Faydalı Bilgi Yoğunluğu</div>
                    <div class="text-[11px] text-gray-500">Net bilgi oranı, kelime zenginliği ve soru cümleleri</div>
                </div>
                <span class="text-xs font-bold text-primary bg-blue-50 px-2 py-1 rounded-lg">15 Puan</span>
            </div>

            <div class="p-2.5 bg-gray-50 rounded-xl border border-gray-100 flex justify-between items-center">
                <div>
                    <div class="text-xs font-bold text-gray-800">5. İkna Edicilik ve Uzmanlık</div>
                    <div class="text-[11px] text-gray-500">Kapanışta iletişime yönlendirme (CTA) ve güven veren uzman anlatımı</div>
                </div>
                <span class="text-xs font-bold text-primary bg-blue-50 px-2 py-1 rounded-lg">15 Puan</span>
            </div>
        </div>

        <!-- Renk Anlamları Rozetleri -->
        <div class="grid grid-cols-3 gap-2 text-center text-[11px] font-medium pt-3 border-t border-gray-100">
            <div class="bg-green-50 text-green-700 p-2 rounded-lg border border-green-200">
                <span class="font-bold block text-xs">80 - 100</span> Mükemmel
            </div>
            <div class="bg-yellow-50 text-yellow-700 p-2 rounded-lg border border-yellow-200">
                <span class="font-bold block text-xs">50 - 79</span> Geliştirilmeli
            </div>
            <div class="bg-red-50 text-red-700 p-2 rounded-lg border border-red-200">
                <span class="font-bold block text-xs">0 - 49</span> Kritik Seviye
            </div>
        </div>
    </div>
</div>
