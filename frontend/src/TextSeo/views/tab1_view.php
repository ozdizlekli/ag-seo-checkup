<!-- Main Content -->
<main class="txtseo-main txtseo-flex-grow txtseo-container txtseo-w-full">
    
    <!-- Input Section -->
    <section id="inputSection" class="txtseo-max-w-4xl txtseo-mx-auto txtseo-bg-white txtseo-rounded-xl txtseo-shadow-lg txtseo-border txtseo-border-gray-100 txtseo-overflow-hidden txtseo-transition txtseo-duration-300">
        <div class="txtseo-p-6">
            <div class="txtseo-flex txtseo-justify-between txtseo-items-center txtseo-mb-2">
                <h2 class="txtseo-text-2xl txtseo-font-bold">Metin Analizi</h2>
                <div class="txtseo-flex txtseo-gap-2">
                    <button type="button" id="copyInputBtn" class="txtseo-btn txtseo-btn-sm txtseo-btn-light txtseo-flex txtseo-items-center txtseo-gap-1">
                        <i class="ph ph-copy"></i> Kopyala
                    </button>
                    <button type="button" id="clearInputBtn" class="txtseo-btn txtseo-btn-sm txtseo-btn-danger-light txtseo-flex txtseo-items-center txtseo-gap-1">
                        <i class="ph ph-trash"></i> Temizle
                    </button>
                </div>
            </div>
            <p class="txtseo-text-gray-500 txtseo-text-sm txtseo-mb-6">SEO performansınızı artırmak ve arama motorlarında üst sıralara çıkmak için içeriğinizi analiz edin.</p>
            
            <div class="txtseo-relative txtseo-mb-4">
                <textarea id="rawText" rows="10" class="txtseo-textarea txtseo-focus-ring" placeholder="Analiz edilecek metni buraya yapıştırın..."></textarea>
                <div class="txtseo-absolute txtseo-bottom-3 txtseo-right-3 txtseo-text-xs txtseo-text-gray-400 txtseo-bg-white txtseo-px-2 txtseo-py-1 txtseo-rounded">
                    <span id="charCount">0</span> Karakter | <span id="wordCount">0</span> Kelime
                </div>
            </div>

            <!-- Accordion for Options -->
            <div class="txtseo-border txtseo-border-gray-200 txtseo-rounded-lg txtseo-mb-6 txtseo-overflow-hidden">
                <button id="optionsToggle" class="txtseo-accordion-header">
                    <div class="txtseo-flex txtseo-items-center txtseo-gap-2">
                        <i class="ph ph-sliders"></i> Gelişmiş Ayarlar (Opsiyonel)
                    </div>
                    <i class="ph ph-caret-down txtseo-transition txtseo-duration-200" id="optionsIcon"></i>
                </button>
                <div id="optionsPanel" class="txtseo-hidden txtseo-p-4 txtseo-bg-white txtseo-border-t txtseo-border-gray-200">
                    <div class="txtseo-w-full">
                        <label class="txtseo-block txtseo-text-xs txtseo-font-semibold txtseo-text-gray-600 txtseo-mb-1">Hedef Anahtar Kelime</label>
                        <input type="text" id="targetKeyword" class="txtseo-input txtseo-focus-ring" placeholder="Örn: on page seo">
                    </div>
                </div>
            </div>

            <div class="txtseo-flex txtseo-justify-end">
                <button id="analyzeBtn" class="txtseo-btn txtseo-btn-lg txtseo-btn-primary txtseo-group">
                    <i class="ph ph-rocket-launch txtseo-text-xl txtseo-group-hover--translate-y-1 txtseo-group-hover-translate-x-1 txtseo-transition"></i> Hemen Analiz Et
                </button>
            </div>
        </div>
        
        <!-- Loading Stepper -->
        <div id="loadingOverlay" class="txtseo-hidden txtseo-absolute txtseo-inset-0 txtseo-bg-white-90 txtseo-flex-col txtseo-items-center txtseo-justify-center txtseo-z-10">
            <div class="txtseo-w-full txtseo-max-w-md txtseo-px-8">
                <div class="txtseo-flex txtseo-justify-between txtseo-mb-2">
                    <span class="txtseo-text-xs txtseo-font-semibold txtseo-text-primary" id="loadingStatus">Sunucuya bağlanılıyor...</span>
                    <span class="txtseo-text-xs txtseo-font-semibold txtseo-text-gray-500" id="loadingPercent">0%</span>
                </div>
                <div class="txtseo-w-full txtseo-bg-gray-200 txtseo-rounded-full txtseo-h-2 txtseo-mb-6">
                    <div id="loadingProgress" class="txtseo-bg-primary txtseo-h-2 txtseo-rounded-full txtseo-transition txtseo-duration-300" style="width: 0%"></div>
                </div>
                <ul class="txtseo-text-sm txtseo-text-gray-500 txtseo-space-y-2" id="loadingSteps">
                    <li class="txtseo-flex txtseo-items-center txtseo-gap-2"><i class="ph ph-circle"></i> İçerik yapısı ve okunabilirlik ölçülüyor</li>
                    <li class="txtseo-flex txtseo-items-center txtseo-gap-2"><i class="ph ph-circle"></i> Anahtar kelime yoğunluğu ve dağılımı analiz ediliyor</li>
                    <li class="txtseo-flex txtseo-items-center txtseo-gap-2"><i class="ph ph-circle"></i> Yapay zeka ile SEO stratejisi oluşturuluyor</li>
                    <li class="txtseo-flex txtseo-items-center txtseo-gap-2"><i class="ph ph-circle"></i> İçeriğiniz Google dostu hale getiriliyor</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- History Section -->
    <section id="historySection" class="txtseo-max-w-4xl txtseo-mx-auto txtseo-mt-8 txtseo-hidden">
        <div class="txtseo-flex txtseo-justify-between txtseo-items-center txtseo-mb-4">
            <h3 class="txtseo-text-lg txtseo-font-bold txtseo-flex txtseo-items-center txtseo-gap-2">
                <i class="ph ph-clock-counter-clockwise txtseo-text-primary txtseo-text-xl"></i> Son Analiz Geçmişi
            </h3>
            <button id="clearAllHistoryBtn" class="txtseo-text-xs txtseo-font-semibold txtseo-text-danger txtseo-hover-underline txtseo-flex txtseo-items-center txtseo-gap-1">
                <i class="ph ph-trash"></i> Tümünü Temizle
            </button>
        </div>
        <div id="historyList" class="txtseo-grid txtseo-grid-cols-1 txtseo-md-grid-cols-2 txtseo-gap-4">
            <!-- JS ile doldurulacak -->
        </div>
    </section>

    <!-- Results Section -->
    <section id="resultsSection" class="txtseo-hidden txtseo-mt-8">
        <div class="txtseo-mb-4">
            <button id="backToInputBtn" class="txtseo-flex txtseo-items-center txtseo-gap-2 txtseo-text-sm txtseo-font-semibold txtseo-text-gray-600 txtseo-hover-text-primary txtseo-transition txtseo-bg-white txtseo-px-4 txtseo-py-2 txtseo-rounded-lg txtseo-border txtseo-border-gray-200 txtseo-shadow-sm">
                <i class="ph ph-arrow-left txtseo-text-lg"></i> Geri Dön / Yeni Analiz
            </button>
        </div>
        <div class="txtseo-flex txtseo-flex-col txtseo-md-flex-row txtseo-justify-between txtseo-items-start txtseo-md-items-end txtseo-mb-6 txtseo-gap-4">
            <div>
                <h2 class="txtseo-text-3xl txtseo-font-bold txtseo-flex txtseo-items-center txtseo-gap-3">
                    Analiz Raporu
                    <span class="txtseo-text-sm txtseo-px-3 txtseo-py-1 txtseo-bg-green-100 txtseo-text-green-700 txtseo-rounded-full txtseo-font-medium txtseo-border txtseo-border-green-200">Başarılı</span>
                </h2>
                <p class="txtseo-text-gray-500 txtseo-text-sm txtseo-mt-1">İçeriğinizin detaylı SEO ve kelime analiz raporu başarıyla oluşturuldu.</p>
            </div>
            <div class="txtseo-flex txtseo-items-center txtseo-gap-4">
                <!-- Gauge -->
                <div class="txtseo-flex txtseo-items-center txtseo-gap-3 txtseo-bg-white txtseo-px-4 txtseo-py-2 txtseo-rounded-lg txtseo-shadow-sm txtseo-border txtseo-border-gray-100">
                    <div class="txtseo-relative txtseo-w-12 txtseo-h-12">
                        <svg class="txtseo-w-full txtseo-h-full" viewBox="0 0 36 36">
                            <path class="txtseo-text-gray-200" stroke-width="3" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            <path id="healthScoreCircle" class="txtseo-text-success" stroke-width="3" stroke-dasharray="75, 100" stroke-linecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        </svg>
                        <div class="txtseo-absolute txtseo-inset-0 txtseo-flex txtseo-items-center txtseo-justify-center">
                            <span id="healthScoreText" class="txtseo-font-bold txtseo-text-sm">75</span>
                        </div>
                    </div>
                    <div class="txtseo-flex txtseo-items-center txtseo-gap-1">
                        <div class="txtseo-text-sm txtseo-font-semibold">Sağlık Skoru</div>
                        <button type="button" id="scoreInfoBtn" class="txtseo-text-gray-400 txtseo-hover-text-primary txtseo-transition txtseo-p-2 txtseo-rounded-full txtseo-hover-bg-gray-100" title="Skor Nasıl Hesaplanır?">
                            <i class="ph ph-info txtseo-text-base"></i>
                        </button>
                    </div>
                </div>
                <button id="downloadPdfBtn" class="txtseo-bg-white txtseo-hover-bg-gray-50 txtseo-text-gray-700 txtseo-border txtseo-border-gray-200 txtseo-px-4 txtseo-py-2_5 txtseo-rounded-lg txtseo-shadow-sm txtseo-transition txtseo-flex txtseo-items-center txtseo-gap-2 txtseo-font-medium txtseo-text-sm">
                    <i class="ph ph-file-pdf txtseo-text-lg txtseo-text-red-500"></i> PDF İndir
                </button>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="txtseo-tab-container txtseo-hide-scrollbar">
            <button class="txtseo-tab-btn txtseo-active" data-target="tab1">
                <i class="ph ph-chart-bar txtseo-text-lg"></i> İçerik Analizi & Karne
            </button>
            <button class="txtseo-tab-btn" data-target="tab2">
                <i class="ph ph-target txtseo-text-lg"></i> Strateji & Anahtar Kelimeler
            </button>
            <button class="txtseo-tab-btn" data-target="tab3">
                <i class="ph ph-map-pin-line txtseo-text-lg"></i> Adım Adım Uygulama Planı
            </button>
            <button class="txtseo-tab-btn" data-target="tab4">
                <i class="ph ph-magic-wand txtseo-text-lg"></i> Yapay Zeka ile SEO Optimizasyonu
            </button>
        </div>

        <!-- Tabs Content -->
        <div class="txtseo-relative txtseo-min-h-500">
            
            <!-- Tab 1: Detaylı Analiz & Karne -->
            <div id="tab1" class="txtseo-tab-pane txtseo-active">
                <div class="txtseo-flex txtseo-flex-col txtseo-space-y-6">
                    <!-- İçerik Yapısı ve Detaylı Analiz -->
                    <div class="txtseo-bg-white txtseo-p-6 txtseo-rounded-2xl txtseo-border txtseo-border-gray-100 txtseo-shadow-sm txtseo-transition txtseo-duration-300">
                        <div class="txtseo-flex txtseo-items-center txtseo-justify-between txtseo-mb-5">
                            <h3 class="txtseo-text-sm txtseo-font-bold txtseo-text-gray-500 txtseo-uppercase txtseo-tracking-wider txtseo-flex txtseo-items-center txtseo-gap-2">
                                 <i class="ph ph-stethoscope txtseo-text-primary txtseo-text-lg"></i> İçerik Yapısı ve Detaylı Analiz
                             </h3>
                             <span class="txtseo-text-[11px] txtseo-font-semibold txtseo-text-primary txtseo-bg-blue-50 txtseo-px-3 txtseo-py-1 txtseo-rounded-full txtseo-border txtseo-border-blue-100 txtseo-flex txtseo-items-center txtseo-gap-1">
                                 <i class="ph ph-cpu"></i> Gelişmiş İçerik ve Dil Analizi
                             </span>
                        </div>
                        <!-- 1. KATMAN: Hero 4'lü Özet Izgarası -->
                        <div class="txtseo-grid txtseo-grid-cols-2 txtseo-sm-grid-cols-4 txtseo-gap-4" id="anatomyHeroCards">
                            <!-- JS ile dinamik doldurulacak -->
                        </div>
                        <!-- 2. KATMAN: Genişletilebilir Röntgen Çekmecesi -->
                        <div class="txtseo-mt-6 txtseo-pt-4 txtseo-border-t txtseo-border-gray-100">
                            <button type="button" id="toggleXrayBtn" class="txtseo-w-full txtseo-py-4 txtseo-px-6 txtseo-bg-gradient txtseo-text-gray-800 txtseo-rounded-xl txtseo-text-sm txtseo-font-bold txtseo-transition txtseo-shadow-sm txtseo-flex txtseo-items-center txtseo-justify-between txtseo-group txtseo-border txtseo-border-blue-100-50">
                                <span class="txtseo-flex txtseo-items-center txtseo-gap-3">
                                    <div class="txtseo-w-8 txtseo-h-8 txtseo-rounded-full txtseo-bg-white txtseo-shadow-sm txtseo-flex txtseo-items-center txtseo-justify-center txtseo-border txtseo-border-blue-100">
                                        <i class="ph-fill ph-sparkle txtseo-text-primary txtseo-text-lg txtseo-group-hover-rotate-12 txtseo-transition"></i>
                                    </div>
                                    <span>Gelişmiş İçerik Analizi ve SEO Kriterleri</span>
                                    <span class="txtseo-bg-primary txtseo-text-white txtseo-text-[10px] txtseo-px-2_5 txtseo-py-0_5 txtseo-rounded-md txtseo-font-semibold txtseo-tracking-wide">24+ Metrik</span>
                                </span>
                                <i class="ph ph-caret-down txtseo-text-gray-400 txtseo-group-hover-text-primary txtseo-transition txtseo-duration-200 txtseo-text-lg" id="xrayChevron"></i>
                            </button>
                            <!-- Açılır X-Ray Paneli -->
                            <div id="xrayDetailPanel" class="txtseo-hidden txtseo-mt-5 txtseo-space-y-4 txtseo-transition txtseo-duration-300">
                                <div class="txtseo-grid txtseo-grid-cols-1 txtseo-md-grid-cols-2 txtseo-lg-grid-cols-4 txtseo-gap-4" id="xrayGrid">
                                    <!-- JS ile sütunlar doldurulacak -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Analiz Özeti ve Kritik Sorunlar -->
                    <div class="txtseo-bg-white txtseo-p-6 txtseo-rounded-2xl txtseo-border txtseo-border-gray-100 txtseo-shadow-sm">
                        <h3 class="txtseo-text-sm txtseo-font-bold txtseo-text-gray-500 txtseo-uppercase txtseo-tracking-wider txtseo-mb-4 txtseo-flex txtseo-items-center txtseo-gap-2">
                            <i class="ph ph-sparkle txtseo-text-amber-500 txtseo-text-lg"></i> AI Analiz Özeti
                        </h3>
                        <p id="aiSummary" class="txtseo-text-sm txtseo-leading-relaxed txtseo-mb-5 txtseo-text-gray-700 txtseo-bg-amber-50-60 txtseo-p-4 txtseo-rounded-xl txtseo-border txtseo-border-amber-200-50 txtseo-italic"></p>
                        <h4 class="txtseo-text-sm txtseo-font-bold txtseo-text-danger txtseo-mb-3 txtseo-flex txtseo-items-center txtseo-gap-2"><i class="ph-fill ph-warning-circle"></i> Kritik İyileştirme Alanları:</h4>
                        <ul id="aiIssues" class="txtseo-text-sm txtseo-space-y-2 txtseo-list-disc txtseo-list-inside txtseo-text-gray-600 txtseo-bg-red-50-30 txtseo-p-4 txtseo-rounded-xl txtseo-border txtseo-border-red-100-50"></ul>
                    </div>

                    <!-- Okunabilirlik (Readability) Rehberi -->
                    <div class="txtseo-bg-white txtseo-p-6 txtseo-rounded-2xl txtseo-border txtseo-border-gray-100 txtseo-shadow-sm">
                        <h3 class="txtseo-text-sm txtseo-font-bold txtseo-text-gray-500 txtseo-uppercase txtseo-tracking-wider txtseo-mb-5 txtseo-flex txtseo-items-center txtseo-gap-2">
                            <i class="ph ph-book-open-text txtseo-text-primary txtseo-text-lg"></i> Okunabilirlik & Anlaşılırlık Rehberi
                        </h3>
                        <div class="txtseo-grid txtseo-grid-cols-1 txtseo-md-grid-cols-2 txtseo-lg-grid-cols-3 txtseo-gap-4" id="readabilityStats">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                    
                    <!-- Başlık Ağacı Haritası -->
                    <div class="txtseo-bg-white txtseo-p-6 txtseo-rounded-2xl txtseo-border txtseo-border-gray-100 txtseo-shadow-sm">
                        <h3 class="txtseo-text-sm txtseo-font-bold txtseo-text-gray-500 txtseo-uppercase txtseo-tracking-wider txtseo-mb-5 txtseo-flex txtseo-items-center txtseo-gap-2">
                            <i class="ph ph-tree-structure txtseo-text-indigo-500 txtseo-text-lg"></i> İçerik Başlık Yapısı
                        </h3>
                        <div id="headingTreeContainer" class="txtseo-bg-gray-50 txtseo-p-5 txtseo-rounded-xl txtseo-border txtseo-border-slate-200-60 txtseo-space-y-3 txtseo-overflow-auto">
                            <!-- JS will populate -->
                        </div>
                    </div>
                </div>

                <!-- N-Gram Chart -->
                <div class="txtseo-bg-white txtseo-p-5 txtseo-rounded-xl txtseo-border txtseo-border-gray-100 txtseo-shadow-sm txtseo-mt-6">
                    <h3 class="txtseo-text-sm txtseo-font-bold txtseo-text-gray-500 txtseo-uppercase txtseo-tracking-wider txtseo-mb-4">En Sık Kullanılan Anahtar Kelimeler ve Kelime Grupları</h3>
                    <div class="txtseo-h-64 txtseo-w-full">
                        <canvas id="ngramChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Strateji -->
            <div id="tab2" class="txtseo-tab-pane txtseo-hidden">
                <div class="txtseo-grid txtseo-grid-cols-1 txtseo-md-grid-cols-2 txtseo-gap-6">
                    <!-- Quotas -->
                    <div class="txtseo-bg-white txtseo-p-5 txtseo-rounded-xl txtseo-border txtseo-border-gray-100 txtseo-shadow-sm">
                        <h3 class="txtseo-text-sm txtseo-font-bold txtseo-text-gray-500 txtseo-uppercase txtseo-tracking-wider txtseo-mb-4 txtseo-flex txtseo-items-center txtseo-gap-2">
                            <i class="ph ph-plus-circle txtseo-text-primary"></i> İçeriğe Eklenmesi Önerilen Anahtar Kelimeler
                        </h3>
                        <div class="txtseo-table-container">
                            <table class="txtseo-table">
                                <thead>
                                    <tr>
                                        <th>Anahtar Kelime</th>
                                        <th class="txtseo-text-center">Eklenecek Adet</th>
                                    </tr>
                                </thead>
                                <tbody id="quotasTable" class="txtseo-divide-y">
                                    <!-- Populated by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Semantic Gaps -->
                    <div class="txtseo-bg-white txtseo-p-5 txtseo-rounded-xl txtseo-border txtseo-border-gray-100 txtseo-shadow-sm">
                        <h3 class="txtseo-text-sm txtseo-font-bold txtseo-text-gray-500 txtseo-uppercase txtseo-tracking-wider txtseo-mb-4 txtseo-flex txtseo-items-center txtseo-gap-2">
                            <i class="ph ph-puzzle-piece txtseo-text-warning"></i> İçerikte Eksik Kalan Önemli Konular
                        </h3>
                        <div id="semanticGapsList" class="txtseo-flex txtseo-flex-wrap txtseo-gap-2">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>

                <!-- PAA -->
                <div class="txtseo-bg-white txtseo-p-5 txtseo-rounded-xl txtseo-border txtseo-border-gray-100 txtseo-shadow-sm txtseo-mt-6">
                    <h3 class="txtseo-text-sm txtseo-font-bold txtseo-text-gray-500 txtseo-uppercase txtseo-tracking-wider txtseo-mb-4 txtseo-flex txtseo-items-center txtseo-gap-2">
                        <i class="ph ph-question txtseo-text-purple-500"></i> Google'da Kullanıcıların En Çok Sorduğu Sorular
                    </h3>
                    <p class="txtseo-text-xs txtseo-text-gray-500 txtseo-mb-4">Bu soruları içeriğe ekleyerek Google zengin sonuçlarında çıkma ihtimalinizi artırabilirsiniz.</p>
                    <ul id="paaList" class="txtseo-space-y-3">
                        <!-- Populated by JS -->
                    </ul>
                </div>
            </div>

            <!-- Tab 3: Konumsal Plan -->
            <div id="tab3" class="txtseo-tab-pane txtseo-hidden">
                <div class="txtseo-bg-white txtseo-p-6 txtseo-rounded-xl txtseo-border txtseo-border-gray-100 txtseo-shadow-sm">
                    <h3 class="txtseo-text-sm txtseo-font-bold txtseo-text-gray-500 txtseo-uppercase txtseo-tracking-wider txtseo-mb-6 txtseo-flex txtseo-items-center txtseo-gap-2">
                        <i class="ph ph-path txtseo-text-primary"></i> İçerik Geliştirme Yol Haritası
                    </h3>
                    
                    <div class="txtseo-relative txtseo-border-l-2 txtseo-border-primary txtseo-ml-4 txtseo-pl-6 txtseo-space-y-8" id="roadmapList">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>

            <!-- Tab 4: AI Düzeltme -->
            <div id="tab4" class="txtseo-tab-pane txtseo-hidden">
                <div class="txtseo-flex txtseo-justify-between txtseo-items-center txtseo-mb-4">
                    <h3 class="txtseo-text-sm txtseo-font-bold txtseo-text-gray-500 txtseo-uppercase txtseo-tracking-wider">Google Dostu SEO'lu Yeni Metin</h3>
                    <div class="txtseo-flex txtseo-gap-2">
                        <button id="toggleDiffBtn" class="txtseo-btn txtseo-btn-sm txtseo-btn-light">Değişiklikleri Karşılaştır</button>
                        <button id="copyBtn" class="txtseo-btn txtseo-btn-sm txtseo-btn-primary txtseo-flex txtseo-items-center txtseo-gap-1">
                            <i class="ph ph-copy"></i> Kopyala
                        </button>
                    </div>
                </div>
                
                <div class="txtseo-grid txtseo-w-full txtseo-grid-cols-1 txtseo-md-grid-cols-2 txtseo-gap-4 txtseo-flex-grow txtseo-mb-4 txtseo-h-64" style="height: 400px;">
                    <div class="txtseo-flex txtseo-flex-col txtseo-w-full txtseo-h-full">
                        <div class="txtseo-bg-gray-100 txtseo-text-xs txtseo-font-bold txtseo-px-3 txtseo-py-2 txtseo-rounded-t-lg txtseo-border txtseo-border-gray-200 txtseo-border-b-0 txtseo-text-gray-500">Eski Metin (Sizin Yazdığınız)</div>
                        <textarea id="originalTextArea" class="txtseo-textarea txtseo-flex-grow txtseo-rounded-b-lg txtseo-rounded-t-none txtseo-border-gray-200 txtseo-bg-gray-50" readonly></textarea>
                    </div>
                    <div class="txtseo-flex txtseo-flex-col txtseo-w-full txtseo-h-full">
                        <div class="txtseo-bg-green-50 txtseo-text-xs txtseo-font-bold txtseo-px-3 txtseo-py-2 txtseo-rounded-t-lg txtseo-border txtseo-border-green-200 txtseo-border-b-0 txtseo-text-success txtseo-flex txtseo-justify-between">
                            <span>Yeni Metin (Google Uyumlu)</span>
                            <span id="wordDiffStat" class="txtseo-font-normal txtseo-text-gray-500"></span>
                        </div>
                        <textarea id="optimizedTextArea" class="txtseo-textarea txtseo-flex-grow txtseo-rounded-b-lg txtseo-rounded-t-none txtseo-border-green-200 txtseo-bg-white" readonly></textarea>
                    </div>
                </div>
                <div id="diffContainer" class="txtseo-hidden txtseo-w-full txtseo-overflow-auto txtseo-p-4 txtseo-border txtseo-border-gray-200 txtseo-rounded-lg txtseo-bg-white txtseo-font-mono txtseo-text-sm txtseo-whitespace-pre-wrap txtseo-leading-relaxed" style="height: 400px;">
                    <!-- Populated by JS -->
                </div>
            </div>

        </div>
    </section>
</main>

<!-- PDF Modal -->
<div id="pdfModal" class="txtseo-hidden txtseo-fixed txtseo-inset-0 txtseo-bg-gray-900-60 txtseo-backdrop-blur txtseo-flex txtseo-items-center txtseo-justify-center txtseo-z-50 txtseo-transition">
    <div class="txtseo-bg-white txtseo-rounded-2xl txtseo-max-w-md txtseo-w-full txtseo-p-6 txtseo-shadow-2xl txtseo-border txtseo-border-gray-100 txtseo-mx-4 txtseo-transform txtseo-transition">
        <div class="txtseo-flex txtseo-items-center txtseo-gap-3 txtseo-mb-4">
            <div id="pdfModalIcon" class="txtseo-w-10 txtseo-h-10 txtseo-rounded-full txtseo-bg-blue-100 txtseo-flex txtseo-items-center txtseo-justify-center txtseo-text-primary txtseo-text-xl">
                <i class="ph ph-file-pdf"></i>
            </div>
            <div>
                <h3 class="txtseo-text-lg txtseo-font-bold txtseo-text-gray-900">Kurumsal SEO Raporu</h3>
                <p class="txtseo-text-xs txtseo-text-gray-500">A4 formatında ajans kalitesinde PDF çıktısı</p>
            </div>
        </div>
        
        <!-- Dinamik Mesaj Alanı -->
        <div id="pdfModalMessage" class="txtseo-bg-gray-50 txtseo-p-4 txtseo-rounded-xl txtseo-text-sm txtseo-text-gray-700 txtseo-mb-6 txtseo-border txtseo-border-gray-100">
            Tüm analizler, grafikler, strateji tablosu ve optimize edilmiş metin PDF olarak hazırlanacaktır. İndirmeyi onaylıyor musunuz?
        </div>

        <div class="txtseo-flex txtseo-justify-end txtseo-gap-3">
            <button id="closePdfModalBtn" class="txtseo-btn txtseo-btn-md txtseo-btn-transparent">İptal</button>
            <button id="confirmPdfDownloadBtn" class="txtseo-btn txtseo-btn-md txtseo-btn-primary txtseo-flex txtseo-items-center txtseo-gap-2">
                <i class="ph ph-download-simple"></i> <span id="confirmBtnText">PDF Raporunu İndir</span>
            </button>
        </div>
    </div>
</div>

<!-- Sağlık Skoru Puanlama Modalı -->
<div id="scoreInfoModal" class="txtseo-hidden txtseo-fixed txtseo-inset-0 txtseo-bg-gray-900-60 txtseo-backdrop-blur txtseo-flex txtseo-items-center txtseo-justify-center txtseo-z-50 txtseo-p-4 txtseo-transition">
    <div class="txtseo-bg-white txtseo-rounded-2xl txtseo-max-w-lg txtseo-w-full txtseo-p-6 txtseo-shadow-2xl txtseo-border txtseo-border-gray-100 txtseo-transform txtseo-transition">
        <div class="txtseo-flex txtseo-justify-between txtseo-items-center txtseo-mb-4 txtseo-pb-3 txtseo-border-b txtseo-border-gray-100">
            <div class="txtseo-flex txtseo-items-center txtseo-gap-2">
                <div class="txtseo-w-8 txtseo-h-8 txtseo-rounded-full txtseo-bg-blue-100 txtseo-flex txtseo-items-center txtseo-justify-center txtseo-text-primary">
                    <i class="ph ph-chart-donut txtseo-text-lg"></i>
                </div>
                <h3 class="txtseo-text-base txtseo-font-bold txtseo-text-gray-900">SEO Sağlık Skoru Nasıl Hesaplanır?</h3>
            </div>
            <button id="closeScoreInfoModalBtn" class="txtseo-text-gray-400 txtseo-hover-text-gray-600 txtseo-p-2 txtseo-rounded-lg txtseo-hover-bg-gray-100 txtseo-text-lg">
                <i class="ph ph-x"></i>
            </button>
        </div>
        
        <p class="txtseo-text-xs txtseo-text-gray-500 txtseo-mb-4">Skor, metninizin arama motoru başarısını belirleyen 5 temel sütunun toplamından (100 Puan) oluşur:</p>

        <!-- 5 Puanlama Sütunu -->
        <div class="txtseo-space-y-2_5 txtseo-mb-5">
            <div class="txtseo-p-2_5 txtseo-bg-gray-50 txtseo-rounded-xl txtseo-border txtseo-border-gray-100 txtseo-flex txtseo-justify-between txtseo-items-center">
                <div>
                    <div class="txtseo-text-xs txtseo-font-bold txtseo-text-gray-800">1. Anahtar Kelime & Arama Uyumu</div>
                    <div class="txtseo-text-[11px] txtseo-text-gray-500">İdeal yoğunluk (%1-%2), ilk 100 kelime ve başlık yerleşimi</div>
                </div>
                <span class="txtseo-text-xs txtseo-font-bold txtseo-text-primary txtseo-bg-blue-50 txtseo-px-2 txtseo-py-1 txtseo-rounded-lg">25 Puan</span>
            </div>

            <div class="txtseo-p-2_5 txtseo-bg-gray-50 txtseo-rounded-xl txtseo-border txtseo-border-gray-100 txtseo-flex txtseo-justify-between txtseo-items-center">
                <div>
                    <div class="txtseo-text-xs txtseo-font-bold txtseo-text-gray-800">2. Okunabilirlik & Akıcılık</div>
                    <div class="txtseo-text-[11px] txtseo-text-gray-500">Okunabilirlik Puanı, karmaşık kelime ve geçiş bağlaçları dengesi</div>
                </div>
                <span class="txtseo-text-xs txtseo-font-bold txtseo-text-primary txtseo-bg-blue-50 txtseo-px-2 txtseo-py-1 txtseo-rounded-lg">25 Puan</span>
            </div>

            <div class="txtseo-p-2_5 txtseo-bg-gray-50 txtseo-rounded-xl txtseo-border txtseo-border-gray-100 txtseo-flex txtseo-justify-between txtseo-items-center">
                <div>
                    <div class="txtseo-text-xs txtseo-font-bold txtseo-text-gray-800">3. İçerik Yapısı ve Başlıklar</div>
                    <div class="txtseo-text-[11px] txtseo-text-gray-500">H1/H2 başlık düzeni, paragraf düzeni ve cümle akıcılığı</div>
                </div>
                <span class="txtseo-text-xs txtseo-font-bold txtseo-text-primary txtseo-bg-blue-50 txtseo-px-2 txtseo-py-1 txtseo-rounded-lg">20 Puan</span>
            </div>

            <div class="txtseo-p-2_5 txtseo-bg-gray-50 txtseo-rounded-xl txtseo-border txtseo-border-gray-100 txtseo-flex txtseo-justify-between txtseo-items-center">
                <div>
                    <div class="txtseo-text-xs txtseo-font-bold txtseo-text-gray-800">4. Faydalı Bilgi Yoğunluğu</div>
                    <div class="txtseo-text-[11px] txtseo-text-gray-500">Net bilgi oranı, kelime zenginliği ve soru cümleleri</div>
                </div>
                <span class="txtseo-text-xs txtseo-font-bold txtseo-text-primary txtseo-bg-blue-50 txtseo-px-2 txtseo-py-1 txtseo-rounded-lg">15 Puan</span>
            </div>

            <div class="txtseo-p-2_5 txtseo-bg-gray-50 txtseo-rounded-xl txtseo-border txtseo-border-gray-100 txtseo-flex txtseo-justify-between txtseo-items-center">
                <div>
                    <div class="txtseo-text-xs txtseo-font-bold txtseo-text-gray-800">5. İkna Edicilik ve Uzmanlık</div>
                    <div class="txtseo-text-[11px] txtseo-text-gray-500">Kapanışta iletişime yönlendirme (CTA) ve güven veren uzman anlatımı</div>
                </div>
                <span class="txtseo-text-xs txtseo-font-bold txtseo-text-primary txtseo-bg-blue-50 txtseo-px-2 txtseo-py-1 txtseo-rounded-lg">15 Puan</span>
            </div>
        </div>

        <!-- Renk Anlamları Rozetleri -->
        <div class="txtseo-grid txtseo-grid-cols-3 txtseo-gap-2 txtseo-text-center txtseo-text-[11px] txtseo-font-medium txtseo-pt-3 txtseo-border-t txtseo-border-gray-100">
            <div class="txtseo-bg-green-50 txtseo-text-green-700 txtseo-p-2 txtseo-rounded-lg txtseo-border txtseo-border-green-200">
                <span class="txtseo-font-bold txtseo-block txtseo-text-xs">80 - 100</span> Mükemmel
            </div>
            <div class="txtseo-bg-yellow-50 txtseo-text-yellow-700 txtseo-p-2 txtseo-rounded-lg txtseo-border txtseo-border-yellow-200">
                <span class="txtseo-font-bold txtseo-block txtseo-text-xs">50 - 79</span> Geliştirilmeli
            </div>
            <div class="txtseo-bg-red-50 txtseo-text-red-700 txtseo-p-2 txtseo-rounded-lg txtseo-border txtseo-border-red-200">
                <span class="txtseo-font-bold txtseo-block txtseo-text-xs">0 - 49</span> Kritik Seviye
            </div>
        </div>
    </div>
</div>
