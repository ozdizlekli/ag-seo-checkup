<section class="tab-panel" id="tab-4">
          <div class="card" style="margin-bottom: 20px;">
            <div class="card__head">
              <div class="card__title has-tooltip" style="display:inline-flex; align-items:center;" data-tooltip="AI tarafından tespit edilen tüm eksiklikleri tek bir listede takip edin.">Yapılacaklar & Eksiklikler</div>
              <button class="btn btn--ghost btn--sm has-tooltip" data-tooltip="Yapılacaklar listesini kalıcı olarak siler." id="btn-clear-todos">Listeyi Temizle</button>
            </div>
            <div class="card__hint">AI SEO Analizi (3. Sekme) sırasında sistemin tespit ettiği "Metin Bazlı SEO Gereksinimleri" ve "Teknik SEO Gereksinimleri" burada toplanır. Bir maddeye tıklayarak analiz edildiği noktaya (3. Sekmeye) geri dönebilirsiniz.</div>
            
            <div class="grid mt-20" style="gap:20px; grid-template-columns: repeat(3, 1fr);">
              <!-- Teknik Eksiklikler Sütunu -->
              <div class="todo-column" style="background:#f8fafc; border:1px solid var(--border); border-radius:8px; padding:16px;">
                <h3 style="font-size:14px; font-weight:600; color:#0f172a; margin-bottom:12px; display:flex; align-items:center; gap:6px;">
                  🚨 Teknik SEO Gereksinimleri
                </h3>
                <div id="todo-list-tech" style="display:flex; flex-direction:column; gap:8px;">
                  <p class="empty-note" style="font-size:12px; color:var(--muted-2);">Henüz teknik eksiklik bulunamadı.</p>
                </div>
              </div>

              <!-- Metin Bazlı Eksiklikler Sütunu -->
              <div class="todo-column" style="background:#f8fafc; border:1px solid var(--border); border-radius:8px; padding:16px;">
                <h3 style="font-size:14px; font-weight:600; color:#0f172a; margin-bottom:12px; display:flex; align-items:center; gap:6px;">
                  ✍️ Metin Bazlı SEO Gereksinimleri
                </h3>
                <div id="todo-list-text" style="display:flex; flex-direction:column; gap:8px;">
                  <p class="empty-note" style="font-size:12px; color:var(--muted-2);">Henüz metin eksikliği bulunamadı.</p>
                </div>
              </div>

              <!-- Genel / Bütünleşik Görevler Sütunu -->
              <div class="todo-column" style="background:#f8fafc; border:1px solid var(--border); border-radius:8px; padding:16px;">
                <h3 style="font-size:14px; font-weight:600; color:#0f172a; margin-bottom:12px; display:flex; align-items:center; gap:6px;">
                  📌 Genel & Bütünleşik Görevler
                </h3>
                <div id="todo-list-general" style="display:flex; flex-direction:column; gap:8px;">
                  <p class="empty-note" style="font-size:12px; color:var(--muted-2);">Henüz genel görev bulunamadı.</p>
                </div>
              </div>
            </div>
          </div>
        </section>