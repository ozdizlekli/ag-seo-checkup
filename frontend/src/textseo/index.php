<?php
require_once __DIR__ . '/config.php';
$authToken = $_ENV['AUTH_TOKEN'] ?? '';
$authEnabled = filter_var($_ENV['AUTH_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($authEnabled && !empty($authToken)) {
    echo '<meta name="api-token" content="' . htmlspecialchars($authToken) . '">' . "\n";
}
?>
<link rel="stylesheet" href="src/textseo/assets/css/style.css">    <!-- 1. EKRAN: GİRİŞ VE YÜKLEME -->
    <div id="homeView" class="view-screen active">
        <div class="home-container">
            <header class="app-header">
                <h1>🔍 SEO Metin Optimizasyonu</h1>
                <p class="subtitle">URL girin, yapay zeka metninizi optimize etsin</p>
            </header>

            <!-- URL GİRİŞ FORMU -->
            <section class="url-input-section">
                <form id="analyzeForm">
                    <div class="input-group">
                        <input type="url" id="urlInput" 
                               placeholder="https://ornek.com/sayfa-adi" 
                               required 
                               autocomplete="url"
                               spellcheck="false">
                        <button type="submit" id="analyzeBtn">
                            <span class="btn-text">Analiz Et</span>
                            <span class="btn-spinner" style="display:none;">
                                <span class="spinner-icon"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </section>

            <!-- YÜKLEME GÖSTERGESİ (OVERLAY) -->
            <div class="loading-overlay" id="loadingSection" style="display:none;">
                <div class="loading-modal-card">
                    <div class="spinner-large"></div>
                    <p class="loading-message" id="loadingMessage">Sayfa içeriği çekiliyor...</p>
                    <div class="loading-steps">
                        <div class="step active" id="step1">📥 Sayfa çekiliyor</div>
                        <div class="step" id="step2">📊 Metin analiz ediliyor</div>
                        <div class="step" id="step3">🤖 Anahtar kelimeler keşfediliyor</div>
                        <div class="step" id="step4">✍️ Metin optimize ediliyor</div>
                        <div class="step" id="step5">✅ Sonuçlar hazırlanıyor</div>
                    </div>
                </div>
            </div>

            <!-- HATA MESAJI -->
            <section class="error-section" id="errorSection" style="display:none;">
                <div class="error-box">
                    <span class="error-icon">⚠️</span>
                    <p class="error-message" id="errorMessage"></p>
                    <button type="button" onclick="document.getElementById('errorSection').style.display='none'" class="dismiss-btn">Kapat</button>
                </div>
            </section>

            <!-- GEÇMİŞ ANALİZLER -->
            <section class="history-section" id="historySection">
                <div class="history-header">
                    <h2 class="history-title">🕒 Geçmiş Analizler</h2>
                    <button type="button" id="clearHistoryBtn" class="clear-history-btn" style="display:none;">
                        Tümünü Temizle
                    </button>
                </div>
                <div class="history-list" id="historyList">
                    <!-- JS ile dinamik doldurulacak -->
                </div>
            </section>
        </div>
        
        <footer class="app-footer">
            <p>SEO Metin Optimizasyonu — Metin Bazlı SEO Aracı</p>
        </footer>
    </div>

    <!-- 2. EKRAN: SONUÇLAR -->
    <div id="resultsView" class="view-screen" style="display:none;">
        
        <!-- ÜST BAR (NAVBAR) -->
        <nav class="results-navbar">
            <div class="navbar-container">
                <button type="button" id="newAnalysisBtn" class="nav-btn">← Yeni URL Analiz Et</button>
                <div class="analyzed-url" id="analyzedUrlContainer">
                    <span id="analyzedUrlText" class="url-text"></span>
                    <span id="analyzeBadge" class="analyze-badge" style="display:none;"></span>
                    <div id="analyzedUrlTooltip" class="url-tooltip"></div>
                </div>
                <button type="button" id="debugBtn" class="nav-btn" style="background:#ffc107; color:#000; font-weight:bold;">🐛 AI Sürecini Gör (Geçici)</button>
            </div>
        </nav>

        <main class="container results-container">
            <!-- GİZLİ EKSTRA VERİ PANELLERİ (TOGGLE) -->
            <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" id="toggleMissingTopicsBtn" class="nav-btn" style="background:#e8eaed; font-size:13px; border:1px solid #dadce0;">💡 Önerilen Ek İçerikler</button>
                <button type="button" id="toggleTechReportBtn" class="nav-btn" style="background:#e8eaed; font-size:13px; border:1px solid #dadce0;">⚙️ Detaylı Teknik Rapor</button>
            </div>
            
            <div id="missingTopicsContainer" class="missing-topics-card" style="display:none;">
                <h3 class="missing-topics-header"><span class="icon">💡</span> Sayfada Eksik Kalan ve Eklenmesi Önerilen Konular</h3>
                <ul id="missingTopicsList" class="missing-topics-list">
                    <!-- JS ile doldurulacak -->
                </ul>
            </div>

            <div id="techReportContainer" style="display:none; background:#fff; padding:24px; border-radius:12px; border:1px solid #e8eaed; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.06);">
                <h3 style="margin-top:0; font-size:16px; color:#1a73e8; border-bottom:1px solid #e8eaed; padding-bottom:12px; margin-bottom:20px;">⚙️ İçerik Okunabilirlik Metrikleri</h3>
                <div id="techMetricsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px;">
                    <!-- JS ile doldurulacak -->
                </div>
            </div>
            <!-- ANAHTAR KELİME BİLGİSİ -->
            <div class="keywords-bar" id="keywordsBar">
                <div class="keyword-group">
                    <span class="keywords-label">🎯 Odak:</span>
                    <span class="keyword-tag focus" id="focusKeyword"></span>
                </div>
                <div class="keyword-group">
                    <span class="keywords-label">🏷️ Yan:</span>
                    <span id="secondaryKeywords" class="secondary-tags"></span>
                </div>
            </div>

            <!-- META TITLE KARTI -->
            <div class="comparison-card" id="titleCard">
                <div class="card-header-row">
                    <h2 class="card-title">📌 Meta Title</h2>
                    <button type="button" class="copy-btn" data-target="newTitle">📋 Kopyala</button>
                </div>
                <div class="comparison-grid">
                    <div class="comparison-col old">
                        <span class="label">Mevcut</span>
                        <div class="value" id="oldTitle"></div>
                        <span class="char-count" id="oldTitleCount"></span>
                    </div>
                    <div class="comparison-col new">
                        <span class="label">Optimize</span>
                        <div class="value" id="newTitle"></div>
                        <div class="action-row">
                            <span class="char-count" id="newTitleCount"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- META DESCRIPTION KARTI -->
            <div class="comparison-card" id="descCard">
                <div class="card-header-row">
                    <h2 class="card-title">📝 Meta Description</h2>
                    <button type="button" class="copy-btn" data-target="newDesc">📋 Kopyala</button>
                </div>
                <div class="comparison-grid">
                    <div class="comparison-col old">
                        <span class="label">Mevcut</span>
                        <div class="value" id="oldDesc"></div>
                        <span class="char-count" id="oldDescCount"></span>
                    </div>
                    <div class="comparison-col new">
                        <span class="label">Optimize</span>
                        <div class="value" id="newDesc"></div>
                        <div class="action-row">
                            <span class="char-count" id="newDescCount"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GÖVDE METNİ DİFF -->
            <div class="comparison-card diff-card" id="diffCard">
                <div class="card-header-row">
                    <h2 class="card-title">📄 Metin Değişiklikleri</h2>
                    <button type="button" class="copy-btn" id="copyOptimizedText">📋 Optimize Metni Kopyala</button>
                </div>
                <div class="diff-toolbar">
                    <div class="diff-legend">
                        <span class="legend-item added">Eklenen</span>
                        <span class="legend-item removed">Çıkarılan</span>
                    </div>
                    <div class="diff-stats" id="diffStats"></div>
                </div>
                <div class="diff-container" id="diffContainer"></div>
                <div class="diff-footer-bar">
                    <span id="bodyWordCount" class="diff-word-counter"></span>
                </div>
            </div>
        </main>
    </div>

    <!-- HATA AYIKLAMA MODALI -->
    <div id="debugModalOverlay" class="loading-overlay" style="display:none; z-index:10000; align-items:flex-start; padding-top:40px; overflow-y:auto;">
        <div class="loading-modal-card" style="max-width: 900px; width:100%; text-align:left; position:relative; margin-bottom:40px;">
            <button id="closeDebugBtn" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:24px; cursor:pointer;">&times;</button>
            <h2 style="margin-top:0; border-bottom:2px solid #e8eaed; padding-bottom:10px;">🐛 Yapay Zeka Süreç Kaydı (Debug)</h2>
            <div id="debugContent" style="margin-top:20px; font-family:'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size:13px; background:#f8f9fa; padding:15px; border-radius:8px; overflow-x:auto; white-space:pre-wrap;">
                <!-- İçerik JS ile eklenecek -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/diff/dist/diff.min.js"></script>
    <script src="src/textseo/assets/js/app.js"></script>

