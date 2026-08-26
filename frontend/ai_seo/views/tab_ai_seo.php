<section class="tab-panel" id="tab-3">

  <!-- DASHBOARD VIEW -->
  <div id="ai-seo-dashboard-view"></div>

  <!-- ACTION VIEW -->
  <div
    id="copilot-action-view"
    style="display:none; flex-direction:column; gap:16px; min-height:80vh;"
  >

    <!-- TOP NAV -->
    <div
      style="display:flex; justify-content:space-between; align-items:center; width:100%; margin-bottom:8px;"
    >

      <!-- Sol Üst -->
      <button
        id="btn-return-dashboard"
        class="btn btn--ghost btn--sm"
        style="display:inline-flex; align-items:center; color:var(--muted); font-weight:600; padding:8px 16px; border-radius:8px; font-size:13px; background:#fff; border:1px solid var(--border); box-shadow:0 1px 2px rgba(0,0,0,0.05);"
      >
        <svg
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          style="margin-right:6px;"
        >
          <line x1="19" y1="12" x2="5" y2="12"></line>
          <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        Kontrol Paneline Dön
      </button>

      <!-- Sağ Üst -->
      <div style="display:flex; gap:6px; align-items:center;">

        <button
          class="btn btn--sm"
          id="btn-open-battle-mode"
          title="Sitenizi en dişli rakiplerinizle kıyaslayın."
          style="display:inline-flex; align-items:center; background:#475569; color:white; border:none; border-radius:12px; padding:6px 12px; font-size:12px;"
        >
          <svg
            width="12"
            height="12"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            style="margin-right:4px;"
          >
            <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
            <path d="M2 17l10 5 10-5"></path>
            <path d="M2 12l10 5 10-5"></path>
          </svg>
          Savaş Modu
        </button>

        <button
          class="btn btn--sm"
          id="btn-download-pdf"
          title="Tüm analiz adımları tamamlandıktan sonra indirilebilir."
          style="display:inline-flex; align-items:center; background:#64748b; color:white; border:none; opacity:0.5; cursor:not-allowed; padding:6px 12px; font-size:12px; border-radius:12px;"
        >
          <svg
            width="12"
            height="12"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            style="margin-right:4px;"
          >
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
          </svg>
          Rapor
        </button>

        <button
          class="btn btn--sm"
          id="btn-send-to-todos"
          title="Eksikleri yapılacaklar listesine aktar."
          style="display:inline-flex; align-items:center; opacity:0.5; cursor:not-allowed; padding:6px 12px; background:#64748b; color:white; border:none; font-size:12px; border-radius:12px;"
        >
          📋 Gönder
        </button>

        <button
          class="btn btn--sm"
          id="copilot-manual-save-btn"
          title="Sohbeti geçmişe kaydeder."
          style="display:inline-flex; align-items:center; padding:6px 12px; font-size:12px; border-radius:12px; background:#64748b; color:white; border:none;"
        >
          <svg
            width="12"
            height="12"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            style="margin-right:4px;"
          >
            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
            <polyline points="17 21 17 13 7 13 7 21"></polyline>
            <polyline points="7 3 7 8 15 8"></polyline>
          </svg>
          Kaydet
        </button>

        <div
          style="display:flex; align-items:center; gap:6px; margin-left:8px; border-left:1px solid var(--border); padding-left:8px;"
        >
          <label class="switch" title="Sunum modu">
            <input type="checkbox" id="client-view-toggle">
            <span class="slider round"></span>
          </label>

          <span style="font-size:12px; font-weight:600; color:var(--muted);">
            Sunum
          </span>

          <button
            class="btn btn--ghost btn--sm"
            title="Yeni bir sohbet / yeni URL analizi başlatır."
            id="btn-clear-chat"
            style="display:inline-flex; align-items:center; gap:4px; padding:4px 10px; margin-left:4px; font-size:12px; font-weight:600; color:var(--muted);"
          >
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Yeni Sohbet
          </button>
        </div>

      </div>
    </div>


    <!-- CHAT INTERFACE -->
    <div
      class="card"
      id="copilot-card"
      style="width:100%; border-top:4px solid var(--accent); padding:0; display:flex; flex-direction:column; height:calc(100vh - 200px); overflow:hidden; border-radius:28px; box-shadow:0 4px 20px rgba(0,0,0,0.05);"
    >

      <!-- Card Header -->
      <div style="padding:24px 24px 0 24px;">
        <div
          class="card__title"
          style="display:inline-flex; align-items:center;"
        >
          GEO AI Bot (URL Tabanlı)
        </div>

        <div class="card__hint">
          Web sitenizin SEO ve Yapay Zeka (SGE) görünürlüğünü sohbet asistanı ile adım adım analiz edin.
        </div>
      </div>


      <!-- COPILOT CONTAINER -->
      <div
        class="copilot-container"
        style="border:none; margin-top:0; border-radius:0; padding-right:0; display:flex; flex-direction:column; flex:1; min-height:0;"
      >

        <!-- COPILOT HEADER -->
        <div
          class="copilot-header"
          style="position:relative; z-index:2; display:flex; flex-direction:column; gap:16px; border-bottom:1px solid #e2e8f0; padding-bottom:16px; background:#fff; flex-shrink:0;"
        >

          <!-- Progress Bar -->
          <div
            style="display:flex; justify-content:center; width:calc(100% - 40px); margin:8px auto 0 auto; border-top:1px solid var(--border); padding-top:16px;"
          >

            <div
              class="copilot-progress"
              id="copilot-progress"
              style="display:flex; justify-content:center; align-items:center; gap:8px; width:100%; flex-wrap:wrap; padding:4px;"
            >

              <!-- STEP 1 -->
              <div
                class="copilot-step active has-tooltip"
                data-step="1"
                id="cp-step-1"
                data-tooltip="İş Bağlamı (Bağlamsal Alaka) analizi."
              >
                <div class="copilot-step-circle">
                  <span class="num">1</span>
                  <svg
                    width="12"
                    height="12"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="3"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                </div>

                <button
                  class="btn-fix-issue"
                  data-step="1"
                  id="btn-fix-1"
                  style="position:absolute; top:-4px; right:-4px; display:none; width:16px; height:16px; border-radius:50%; background:#fff; color:#2563eb; border:1px solid #e2e8f0; padding:0; box-shadow:0 1px 3px rgba(0,0,0,0.12); font-size:10px; line-height:14px; text-align:center; cursor:pointer; z-index:10;"
                  title="1. Adımı Çöz"
                >
                  🔧
                </button>

                <span>İş Bağlamı</span>
              </div>


              <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                color="var(--border)"
              >
                <path d="M9 18l6-6-6-6"></path>
              </svg>


              <!-- STEP 2 -->
              <div
                class="copilot-step has-tooltip"
                data-step="2"
                id="cp-step-2"
                data-tooltip="Kullanıcı niyeti ve etkililik analizi."
              >
                <div class="copilot-step-circle">
                  <span class="num">2</span>
                  <svg
                    width="12"
                    height="12"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="3"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                </div>

                <button
                  class="btn-fix-issue"
                  data-step="2"
                  id="btn-fix-2"
                  style="position:absolute; top:-4px; right:-4px; display:none; width:16px; height:16px; border-radius:50%; background:#fff; color:#2563eb; border:1px solid #e2e8f0; padding:0; box-shadow:0 1px 3px rgba(0,0,0,0.12); font-size:10px; line-height:14px; text-align:center; cursor:pointer; z-index:10;"
                  title="2. Adımı Çöz"
                >
                  🔧
                </button>

                <span>Etkililik</span>
              </div>


              <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                color="var(--border)"
              >
                <path d="M9 18l6-6-6-6"></path>
              </svg>


              <!-- STEP 3 -->
              <div
                class="copilot-step has-tooltip"
                data-step="3"
                id="cp-step-3"
                data-tooltip="Rakiplere kıyasla eksik içerik/değer boşlukları analizi."
              >
                <div class="copilot-step-circle">
                  <span class="num">3</span>
                  <svg
                    width="12"
                    height="12"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="3"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                </div>

                <button
                  class="btn-fix-issue"
                  data-step="3"
                  id="btn-fix-3"
                  style="position:absolute; top:-4px; right:-4px; display:none; width:16px; height:16px; border-radius:50%; background:#fff; color:#2563eb; border:1px solid #e2e8f0; padding:0; box-shadow:0 1px 3px rgba(0,0,0,0.12); font-size:10px; line-height:14px; text-align:center; cursor:pointer; z-index:10;"
                  title="3. Adımı Çöz"
                >
                  🔧
                </button>

                <span>Rakip Analizi</span>
              </div>


              <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                color="var(--border)"
              >
                <path d="M9 18l6-6-6-6"></path>
              </svg>


              <!-- STEP 4 -->
              <div
                class="copilot-step has-tooltip"
                data-step="4"
                id="cp-step-4"
                data-tooltip="Yapay zeka sistemleri nezdinde marka güveni ve otorite (E-E-A-T) analizi."
              >
                <div class="copilot-step-circle">
                  <span class="num">4</span>
                  <svg
                    width="12"
                    height="12"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="3"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                </div>

                <button
                  class="btn-fix-issue"
                  data-step="4"
                  id="btn-fix-4"
                  style="position:absolute; top:-4px; right:-4px; display:none; width:16px; height:16px; border-radius:50%; background:#fff; color:#2563eb; border:1px solid #e2e8f0; padding:0; box-shadow:0 1px 3px rgba(0,0,0,0.12); font-size:10px; line-height:14px; text-align:center; cursor:pointer; z-index:10;"
                  title="4. Adımı Çöz"
                >
                  🔧
                </button>

                <span>AI Güveni</span>
              </div>


              <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                color="var(--border)"
              >
                <path d="M9 18l6-6-6-6"></path>
              </svg>


              <!-- STEP 5 -->
              <div
                class="copilot-step has-tooltip"
                data-step="5"
                id="cp-step-5"
                data-tooltip="Okunabilirlik, Şema yapıları ve kullanıcı deneyimi (UX/UI) sorunları."
              >
                <div class="copilot-step-circle">
                  <span class="num">5</span>
                  <svg
                    width="12"
                    height="12"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="3"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                </div>

                <button
                  class="btn-fix-issue"
                  data-step="5"
                  id="btn-fix-5"
                  style="position:absolute; top:-4px; right:-4px; display:none; width:16px; height:16px; border-radius:50%; background:#fff; color:#2563eb; border:1px solid #e2e8f0; padding:0; box-shadow:0 1px 3px rgba(0,0,0,0.12); font-size:10px; line-height:14px; text-align:center; cursor:pointer; z-index:10;"
                  title="5. Adımı Çöz"
                >
                  🔧
                </button>

                <span>Optimizasyon</span>
              </div>


              <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                color="var(--border)"
              >
                <path d="M9 18l6-6-6-6"></path>
              </svg>


              <!-- STEP 6 -->
              <div
                class="copilot-step has-tooltip"
                data-step="6"
                id="cp-step-6"
                data-tooltip="Sentez ve çözümleme aşaması."
              >
                <div class="copilot-step-circle">
                  <span class="num">6</span>
                  <svg
                    width="12"
                    height="12"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="3"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                </div>

                <span>Entegrasyon</span>
              </div>

            </div>
          </div>

        </div>
        <!-- /COPILOT HEADER -->


        <!-- CHAT MESSAGES -->
        <div
          class="copilot-chat"
          id="copilot-chat-messages-container"
          style="padding:24px; min-height:200px; flex:1; overflow-y:auto;"
        >
          <!-- Chat messages go here -->
        </div>


        <!-- INPUT AREA -->
        <div
          class="copilot-input-area"
          id="copilot-input-area-container"
          style="padding:16px 24px; background:transparent; border-top:none; position:relative; flex-shrink:0;"
        >

          <!-- QUICK ACTIONS WRAPPER -->
          <div style="position:relative;">

            <div
              style="position:absolute; top:-16px; left:0; right:0; height:16px; background:linear-gradient(to bottom, rgba(255,255,255,0), #fff 85%); pointer-events:none; z-index:2;"
            ></div>

            <div
              class="quick-actions"
              id="copilot-quick-actions"
              style="margin-bottom:8px; padding:4px 8px 4px 8px; max-width:800px; width:100%; margin-left:auto; margin-right:auto; display:none; justify-content:center; gap:8px; background:#fff; border:none; overflow:visible; white-space:nowrap; position:relative; z-index:3;"
            >

              <button
                class="quick-action-btn has-tooltip"
                data-tooltip="İçeriği daha inandırıcı hale getirmek için ipuçları iste."
                onclick="document.getElementById('copilot-text-input').value='İçeriği daha ikna edici nasıl yaparım?'; document.getElementById('btn-send-message').click();"
              >
                İçeriği daha ikna edici nasıl yaparım?
              </button>

              <button
                class="quick-action-btn has-tooltip"
                data-tooltip="Eksik olan E-E-A-T veya semantik unsurları listele."
                onclick="document.getElementById('copilot-text-input').value='Bana içerik eksiklerimi söyle'; document.getElementById('btn-send-message').click();"
              >
                Bana içerik eksiklerimi söyle
              </button>

              <button
                class="quick-action-btn has-tooltip"
                data-tooltip="Makalenin anlamsal derinliğini artıracak anahtar kelimeler öner."
                onclick="document.getElementById('copilot-text-input').value='Hangi LSI kelimelerini kullanmalıyım?'; document.getElementById('btn-send-message').click();"
              >
                Hangi LSI kelimelerini kullanmalıyım?
              </button>

              <button
                class="quick-action-btn has-tooltip"
                data-tooltip="Rakiplerin senden daha iyi olduğu spesifik noktaları öğren."
                onclick="document.getElementById('copilot-text-input').value='Rakiplerimden neden gerideyim?'; document.getElementById('btn-send-message').click();"
              >
                Rakiplerimden neden gerideyim?
              </button>

            </div>
          </div>


          <!-- INPUT WRAPPER -->
          <div
            class="input-wrapper"
            style="display:flex; flex-direction:column; align-items:center; width:100%;"
          >
            <div
              id="copilot-llms-container"
              style="display:flex; align-items:center; width:100%; max-width:1000px; gap:8px; margin-bottom:8px; justify-content:center;"
            >
                <button
                  class="btn btn--primary"
                  id="btn-auto-analyze"
                  style="display:none; padding:10px 16px; font-weight:600; background:#10b981; border:none; border-radius:30px; box-shadow:0 4px 6px rgba(16,185,129,0.2); white-space:nowrap; font-size:13px;"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px; color:#fff; vertical-align:middle;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                  Tüm Siteyi Analiz Et
                </button>

                <div
                  style="display:flex; align-items:center; flex:1; max-width:800px; background:#f0f4f9; border-radius:30px; padding:6px 10px 6px 24px;"
                >
                  <input
                    type="text"
                    id="copilot-text-input"
                    placeholder="Örn: https://www.site.com/hizmet"
                    style="flex:1; border:none; outline:none; background:transparent; font-size:15px; color:#1e293b; padding:10px 0; box-shadow:none; min-width:0;"
                  >
                  <input
                    type="text"
                    id="copilot-secondary-input"
                    style="display:none;"
                  >
                  <button
                    id="btn-send-message"
                    style="background:#a8c7fa; color:#041e49; border:none; border-radius:50%; width:40px; height:40px; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; transition:all 0.2s; margin-left:12px;"
                  >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>
                  </button>
                </div>

                <button
                  class="btn btn--primary"
                  id="btn-auto-fix"
                  style="display:none; padding:10px 16px; font-weight:600; background:#10b981; border:none; border-radius:30px; box-shadow:0 4px 6px rgba(16,185,129,0.2); white-space:nowrap; font-size:13px;"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px; color:#fff; vertical-align:middle;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                  Tüm Eksikleri Gider
                </button>
            </div>

            <div
              style="font-size:12px; color:#64748b; text-align:center; font-weight:400;"
            >
              AI SEO Bot bir yapay zeka modeli olduğu için hata yapabilir.
            </div>

          </div>
          <!-- /INPUT WRAPPER -->

        </div>
        <!-- /COPILOT INPUT AREA -->

      </div>
      <!-- /COPILOT CONTAINER -->

    </div>
    <!-- /COPILOT CARD -->


    <!-- LIVE REPORT PANEL -->
    <div
      class="live-report-panel"
      id="live-report-panel"
      style="width:100%; background:#fff; border:1px solid var(--border); border-radius:12px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); padding:24px;"
    >

      <h3
        style="font-size:16px; font-weight:700; color:#0f172a; margin:0 0 20px 0; display:flex; align-items:center; gap:8px;"
      >
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          style="color:#475569;"
        >
          <path d="M3 3v18h18"></path>
          <path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"></path>
        </svg>
        Canlı Rapor Paneli
      </h3>


      <!-- 3 GRAFİK -->
      <div
        style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; margin-bottom:16px;"
      >

        <!-- Genel Sağlık -->
        <div
          style="background:#f8fafc; border:2px dashed #cbd5e1; border-radius:12px; padding:16px;"
        >

          <h4
            style="font-size:13px; font-weight:600; color:#475569; margin-bottom:12px; text-align:center;"
          >
            Genel AI Güven Skoru
          </h4>

          <div
            style="height:200px; position:relative; display:flex; justify-content:center; align-items:center;"
          >
            <canvas id="chart-overall-health"></canvas>

            <div
              id="chart-overall-health-placeholder"
              style="position:absolute; display:flex; flex-direction:column; align-items:center; color:#94a3b8;"
            >
              <svg
                width="48"
                height="48"
                viewBox="0 0 24 24"
                fill="none"
                stroke="#cbd5e1"
                stroke-width="1.5"
              >
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M12 8v4l3 3"></path>
              </svg>

              <span style="font-size:12px; margin-top:8px;">
                Analiz bekleniyor...
              </span>
            </div>
          </div>

        </div>


        <!-- E-E-A-T -->
        <div
          style="background:#f8fafc; border:2px dashed #cbd5e1; border-radius:12px; padding:16px;"
        >

          <h4
            style="font-size:13px; font-weight:600; color:#475569; margin-bottom:12px; text-align:center;"
          >
            E-E-A-T Sinyalleri
          </h4>

          <div
            style="height:200px; position:relative; display:flex; justify-content:center; align-items:center;"
          >
            <canvas id="chart-eeat"></canvas>

            <div
              id="chart-eeat-placeholder"
              style="position:absolute; display:flex; flex-direction:column; align-items:center; color:#94a3b8;"
            >
              <svg
                width="48"
                height="48"
                viewBox="0 0 24 24"
                fill="none"
                stroke="#cbd5e1"
                stroke-width="1.5"
              >
                <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                <polyline points="2 17 12 22 22 17"></polyline>
                <polyline points="2 12 12 17 22 12"></polyline>
              </svg>

              <span style="font-size:12px; margin-top:8px;">
                Analiz bekleniyor...
              </span>
            </div>
          </div>

        </div>


        <!-- RAKİP KIYASLAMA -->
        <div
          id="battle-chart-container"
          style="background:#f8fafc; border:2px dashed #cbd5e1; border-radius:12px; padding:16px;"
        >

          <h4
            style="font-size:13px; font-weight:600; color:#475569; margin-bottom:12px; text-align:center;"
          >
            Rakip Kıyaslaması
          </h4>

          <div
            style="height:200px; position:relative; display:flex; justify-content:center; align-items:center;"
          >
            <canvas id="chart-battle"></canvas>

            <div
              id="chart-battle-placeholder"
              style="position:absolute; display:flex; flex-direction:column; align-items:center; color:#94a3b8;"
            >
              <svg
                width="48"
                height="48"
                viewBox="0 0 24 24"
                fill="none"
                stroke="#cbd5e1"
                stroke-width="1.5"
              >
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>

              <span style="font-size:12px; margin-top:8px;">
                Savaş modu aktif değil
              </span>
            </div>
          </div>

        </div>

      </div>


      <!-- AKSİYON PLANI -->
      <div
        style="background:#f8fafc; border:2px dashed #cbd5e1; border-radius:12px; padding:16px;"
      >

        <h4
          style="font-size:13px; font-weight:600; color:#475569; margin-bottom:12px;"
        >
          Acil Aksiyon Planı
        </h4>

        <div
          id="action-plan-table-container"
          style="font-size:13px;"
        >
          <p
            style="color:#94a3b8; font-size:12px; text-align:center; margin:20px 0;"
          >
            Analiz tamamlandığında aksiyon maddeleri burada görünecek.
          </p>
        </div>

      </div>

    </div>
    <!-- /LIVE REPORT PANEL -->



  </div>
  <!-- /COPILOT ACTION VIEW -->

</section>