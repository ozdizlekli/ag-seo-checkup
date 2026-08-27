/**
 * AdresGezgini — AI SEO Hizmetleri
 * URL gir → AI site türü tespit → 7 hizmet + özel hizmet ekle → accordion sonuçlar
 *
 * NOT — ASIL SORUN (kök neden) VE ÇÖZÜMÜ:
 * index.php şu script sırasına sahip:
 *   1) <script src="ai_seo/js/copilot.js">   (satır ~23, <head> içinde)
 *   2) <script src="js/app.js">              (satır ~640, body sonunda)
 * Modül olmayan (non-module) <script> etiketleri TEK BİR global scope'u
 * paylaşır. İki ayrı dosya da üst seviyede (top-level) AYNI isimlerle
 * tanım yapıyordu:
 *
 *   a) `const state = {...}`  → hem copilot.js hem de app.js'te var.
 *      Bir global scope'ta aynı isimle ikinci kez `const` (veya `let`)
 *      tanımlamak JS'te bir SyntaxError'dır ve bu hata o SCRIPT'İN
 *      TAMAMINI çalıştırmaz (o satırdan önceki kodlar bile çalışmaz).
 *      Yani copilot.js önce yüklenip `const state` tanımladığı için,
 *      SONRA yüklenen app.js kendi `const state` satırına gelince patlar
 *      ve app.js'in TÜMÜ (tab geçişleri dahil pek çok özellik) sessizce
 *      çalışmayı durdurabiliyordu (konsolda "Identifier 'state' has
 *      already been declared" hatası).
 *
 *   b) `async function callGemini(...)` → hem copilot.js hem de app.js'te
 *      var (farklı imzalarla). Fonksiyon tanımları const/let gibi hata
 *      vermez, sadece SONRA yüklenen SESSİZCE öncekini EZER. app.js'teki
 *      callGemini, var OLMAYAN bir endpoint'e (api_handler.php) istek
 *      atıyor ve 2. parametre olarak bir "mockResponseFactory" fonksiyonu
 *      bekliyor; copilot.js ise 2. parametre olarak bir sayı (temperature)
 *      gönderiyordu. Bu karışıklık yüzünden AI SEO istekleri (site türü
 *      tespiti ve her hizmet butonu) hatayla/sessizce başarısız oluyor,
 *      accordion hiç dolmuyordu.
 *
 * ÇÖZÜM: Bu dosyanın TÜM içeriği bir IIFE (Immediately Invoked Function
 * Expression) içine alınmıştır. Böylece `state`, `callGemini` ve diğer
 * tüm üst seviye tanımlar bu closure'a HAPSOLUR; window/global scope'a
 * hiç sızmaz. Artık app.js ile (veya ileride eklenecek başka bir script
 * ile) isim çakışması imkansız hale gelir. Dışarıdan çağrılması gereken
 * fonksiyonlar (runSingleService, toggleAccordion, runAllServices ve eski
 * uyumluluk stub'ları) zaten kod içinde açıkça `window.xxx = ...` şeklinde
 * atanıyor; IIFE'ye almak bunların dışarıdan erişilebilir olmasını
 * etkilemez, sadece iç değişkenleri izole eder.
 */
(function () {
  'use strict';
  // ============================================================
  // STATE
  // ============================================================
  const state = {
    targetUrl: '',
    siteType: '',
    fetchedData: null,
    completedServices: new Set(),
    serviceResults: {},
    currentChatId: String(Date.now())
  };

  
  // ============================================================
  // 7 SABİT HİZMET
  // ============================================================
  const DEFAULT_SERVICES = [
  {
    id: 1,
    icon: '🏗️',
    title: 'Yapay Zekâ Uyumlu Site Yapısı',
    description: 'Site yapısının, hizmet ve işletme bilgilerinin yapay zeka sistemleri tarafından anlaşılır hale getirilmesi.',
    loadingMessages: ['Bilgi mimarisi taranıyor...', 'Hiyerarşi düzeni kontrol ediliyor...', 'Taranabilirlik testleri yapılıyor...', 'LLM uyumluluğu analiz ediliyor...'],
    subtasks: [
      { id: '1_1', title: 'Bilgi Mimarisi ve Hiyerarşi Düzeni', desc: 'Ana sayfa, hizmet sayfaları ve alt hizmet sayfaları arasındaki ilişkinin netleştirilmesi.', selected: true },
      { id: '1_2', title: 'Ayrıştırma İşlemleri', desc: 'Hizmet, ürün, sektör ve uygulama alanı sayfalarının birbirine karışmasını önleyecek yapısal düzenlemeler.', selected: true },
      { id: '1_3', title: 'Kullanıcı Niyetinin (Intent) Vurgulanması', desc: 'Her hizmet sayfasının tam olarak hangi probleme veya ihtiyaca çözüm sunduğunun başlık ve meta verilerle belirginleştirilmesi.', selected: true },
      { id: '1_4', title: 'Erişilebilirlik ve Menü Optimizasyonu', desc: 'Önemli sayfalara site menüsü ve ana içerik üzerinden erişimin kolaylaştırılması.', selected: true },
      { id: '1_5', title: 'Teknik Taranabilirlik ve Tutarlılık Kontrolleri', desc: 'robots.txt, XML sitemap, 404 sayfaları, yönlendirme zincirleri incelenmelidir. Ayrıca HTTP/HTTPS ve sayfaların mobil/masaüstü sürümlerindeki ana içerik tutarlılığı kontrol edilmelidir.', selected: true }
    ],
    basePrompt: `Sen bir AdresGezgini AI SEO uzmanısın. "{URL}" sitesi için "Yapay Zekâ Uyumlu Site Yapısı" analizi yap.
Site: {TITLE} | {DESCRIPTION}
Tür: {SITE_TYPE}
Metin: {TEXT}
Aşağıdaki başlıkları (tam olarak aynı yazımla '### Başlık' formatında) kullanarak detaylı ve uygulanabilir bir rapor hazırla:`
  },
  {
    id: 2,
    icon: '🤖',
    title: 'Yapay Zekâ Tanıtım Dosyaları',
    description: 'Yapay zekâ destekli arama motorlarının sitenin ne iş yaptığını hızlıca özetleyebilmesi için yapılandırılmış metin dosyalarının oluşturulmasıdır.',
    loadingMessages: ['Tanıtım dosyaları taranıyor...', 'llms.txt gereksinimleri belirleniyor...', 'Kurumsal kimlik doğrulanıyor...', 'Doküman yapıları inceleniyor...'],
    subtasks: [
      { id: '2_1', title: 'llms.txt Dosyasının Hazırlanması', desc: 'Sitenin ana faaliyetlerini özetleyen dosyanın kurgulanması. Not: Bu dosya yardımcı bir özettir, AI Overviews için özel bir sıralama sinyali veya zorunluluk olmadığı belirtilmelidir.', selected: true },
      { id: '2_2', title: 'Kategori ve Hizmet Listelemesi', desc: 'İşletmenin en önemli sayfalarının, yapay zekâ botlarına referans olması amacıyla dosyaya entegre edilmesi.', selected: true },
      { id: '2_3', title: 'Kurumsal Kimlik ve İletişim Tanımlamaları', desc: 'Firma iletişim ve "Hakkımızda" detaylarının dosyada doğrulanmış kaynak olarak sunulması.', selected: true },
      { id: '2_4', title: 'Doküman ve Rehber Entegrasyonu', desc: 'Varsa teknik doküman, katalog veya rehber içeriklerin canonical (geçerli) URL\'leri ile birlikte dosyaya eklenmesi.', selected: true }
    ],
    basePrompt: `Sen bir AdresGezgini AI SEO uzmanısın. "{URL}" sitesi için "Yapay Zekâ Tanıtım Dosyaları" analizi yap.
Site: {TITLE} | {DESCRIPTION}
Tür: {SITE_TYPE}
Metin: {TEXT}
Aşağıdaki başlıkları (tam olarak aynı yazımla '### Başlık' formatında) kullanarak detaylı ve uygulanabilir bir rapor hazırla:

DİKKAT ÖNEMLİ KURAL: Yazılımcının veya editörün doğrudan kopyalayıp kullanacağı hazır metinleri, llms.txt içeriklerini, meta etiketlerini ve tüm kodları MUTLAKA \`\`\` (markdown) bloğu içerisine al. Kopyalanacak içerikleri asla düz yazı olarak sunma!`
  },
  {
    id: 3,
    icon: '🔍',
    title: 'Arama Motoru Kayıtları',
    description: 'İşletmenin kendi sahipliğini doğrulayabilmesi ve metriklerini takip edebilmesi için kapsamlı bir kurulum rehberi sunulur.',
    loadingMessages: ['Kayıt rehberleri oluşturuluyor...', 'Search Console adımları yazılıyor...', 'Bing ve Yandex yönergeleri hazırlanıyor...', 'Performans takip talimatları belirleniyor...'],
    subtasks: [
      { id: '3_1', title: 'Google Search Console Kurulum Yönergesi', desc: 'Site mülkünün nasıl ekleneceği ve sahipliğin nasıl doğrulanacağı (DNS, HTML etiketi vb.) hakkında adım adım rehber.', selected: true },
      { id: '3_2', title: 'Bing ve Yandex Webmaster Adımları', desc: 'Alternatif arama motorlarına sitenin nasıl tanıtılacağına dair yönergeler.', selected: true },
      { id: '3_3', title: 'Sitemap Gönderim Kılavuzu', desc: 'XML sitemap dosyalarının arama motorlarına nasıl iletileceğinin ekran görüntüleri veya maddeler halinde anlatımı.', selected: true },
      { id: '3_4', title: 'Performans ve Hata Takibi Eğitimi', desc: 'İndeksleme sorunlarının, tarama hatalarının ve temel performans metriklerinin (tıklama, gösterim) paneller üzerinden nasıl okunup yorumlanacağı.', selected: true }
    ],
    basePrompt: `Sen bir AdresGezgini AI SEO uzmanısın. "{URL}" sitesi için "Arama Motoru Kayıtları" analizi yap.
Bu hizmet kapsamında işletme adına doğrudan kayıt işlemi yapılmaz, kurulum rehberi sunulur.
Site: {TITLE} | {DESCRIPTION}
Tür: {SITE_TYPE}
Metin: {TEXT}
Aşağıdaki başlıkları (tam olarak aynı yazımla '### Başlık' formatında) kullanarak detaylı ve uygulanabilir bir rapor hazırla:`
  },
  {
    id: 4,
    icon: '📊',
    title: 'Yapılandırılmış Veri Düzenlemeleri',
    description: 'Sitedeki içeriklerin arama motorlarının veritabanında birer "varlık (entity)" olarak algılanmasını sağlayan kodlamaların yapılmasıdır.',
    loadingMessages: ['Şemalar kontrol ediliyor...', 'Kurumsal kimlik kodlamaları analiz ediliyor...', 'Hizmet ve Ürün etiketleri doğrulanıyor...', 'Rich Results uyumluluğu test ediliyor...'],
    subtasks: [
      { id: '4_1', title: 'Kurumsal Kimlik (LocalBusiness / Organization) İşaretlemeleri', desc: 'Ana sayfaya firma adı, logo, adres, telefon ve e-posta gibi temel bilgilerin kod olarak eklenmesi.', selected: true },
      { id: '4_2', title: 'Hizmet (Service) ve Ürün (Product) Tanımlamaları', desc: 'Hizmet sayfalarına ve fiziksel ürün sayfalarına Schema.org standartlarına uygun etiketlerin yerleştirilmesi.', selected: true },
      { id: '4_3', title: 'İçerik ve Yazar (Article / FAQ) Verileri', desc: 'Blog içeriklerinde yazar ve tarih; soru-cevap alanlarında ise Sıkça Sorulan Sorular (FAQ) yapılandırılmış verilerinin kurgulanması.', selected: true },
      { id: '4_4', title: 'Sayfa Hiyerarşisi (Breadcrumb)', desc: 'Kullanıcının sitedeki konumunu arama motorlarına bildiren yol haritası verilerinin eklenmesi.', selected: true },
      { id: '4_5', title: 'Kod Doğrulama', desc: 'Eklenen tüm yapılandırılmış verilerin Zengin Sonuçlar Testi (Rich Results Test) üzerinden doğrulanması ve olası hataların temizlenmesi.', selected: true }
    ],
    basePrompt: `Sen bir AdresGezgini AI SEO uzmanısın. "{URL}" sitesi için "Yapılandırılmış Veri Düzenlemeleri" analizi yap.
Site: {TITLE} | {DESCRIPTION}
Tür: {SITE_TYPE}
Mevcut Schemalar: {SCHEMAS}
Metin: {TEXT}
Aşağıdaki başlıkları (tam olarak aynı yazımla '### Başlık' formatında) kullanarak detaylı ve uygulanabilir bir rapor hazırla:

DİKKAT ÖNEMLİ KURAL: Yazılımcının sayfa kodlarına doğrudan yapıştırabileceği güncel ve hatasız JSON-LD Schema kodlarını (Organization, Service, FAQ vb.) sadece öneri olarak bırakma, eksiksiz bir şekilde \`\`\`json (markdown) kod bloğu içinde üret.`
  },
  {
    id: 5,
    icon: '✍️',
    title: 'İçerik Düzenleme ve Geliştirme',
    description: 'Mevcut içeriklerin, SEO kurallarına ve yapay zekânın dil işleme algoritmalarına uygun bir yapıya kavuşturulmasıdır.',
    loadingMessages: ['İçerik okunabilirliği ölçülüyor...', 'Teknik terimler optimize ediliyor...', 'Metin kapsamları değerlendiriliyor...', 'Özgünleştirme ihtiyaçları belirleniyor...'],
    subtasks: [
      { id: '5_1', title: 'Detaylandırma ve Kapsam Genişletme', desc: 'Yüzeysel bırakılmış hizmet açıklamalarının, sürecin nasıl işlediğini anlatacak şekilde derinleştirilmesi.', selected: true },
      { id: '5_2', title: 'Teknik Terim Optimizasyonu', desc: 'Sektörel jargonun, hem uzmanların hem de son kullanıcıların anlayabileceği açık ifadelere dönüştürülmesi.', selected: true },
      { id: '5_3', title: 'Okunabilirlik (Readability) Düzenlemeleri', desc: 'Uzun paragraf yapılarının bölünmesi, eksik H2/H3 (Alt Başlık) etiketlerinin eklenerek metnin taranabilir hale getirilmesi.', selected: true },
      { id: '5_4', title: 'Özgünleştirme', desc: 'Birbirine çok benzeyen veya kopyalanmış hizmet sayfalarındaki metinlerin ayrıştırılarak tamamen özgün hale getirilmesi.', selected: true },
      { id: '5_5', title: 'Kullanım Alanı ve Kapsam Belirtme', desc: 'Hizmetin kimler için uygun olduğu, neleri kapsayıp neleri kapsamadığı gibi sınırların metin içinde netleştirilmesi.', selected: true },
      { id: '5_6', title: 'Eğitici Materyal ve ROI Kurgusu', desc: 'Kullanıcılara değer katmak için yatırım getirisi (ROI) iyileştirme, analitik araçların kullanımı hakkında adım adım rehberler ve indirilebilir şablon/e-kitap fırsatlarının oluşturulması.', selected: true }
    ],
    basePrompt: `Sen bir AdresGezgini AI SEO uzmanısın. "{URL}" sitesi için "İçerik Düzenleme ve Geliştirme" analizi yap.
Site: {TITLE} | {DESCRIPTION}
Tür: {SITE_TYPE}
Metin: {TEXT}
Aşağıdaki başlıkları (tam olarak aynı yazımla '### Başlık' formatında) kullanarak detaylı ve uygulanabilir bir rapor hazırla:

DİKKAT ÖNEMLİ KURAL: Sadece 'şöyle yazılmalı' şeklinde tavsiye verme. Editörün doğrudan kopyalayıp siteye yapıştırabileceği; optimize edilmiş H2/H3 başlıklarını, derinleştirilmiş hizmet açıklamalarını ve ROI odaklı metin paragraflarını bizzat yaz ve bunları \`\`\`text (markdown) kod bloğu içinde sun.`
  },
  {
    id: 6,
    icon: '👥',
    title: 'Kullanıcı Odaklı İçerik ve Soru-Cevap',
    description: 'İçeriklerin gerçek ziyaretçilerin sorularına doyurucu yanıtlar verecek şekilde kurgulanmasıdır.',
    loadingMessages: ['Kullanıcı niyetleri analiz ediliyor...', 'Soru-cevap senaryoları kurgulanıyor...', 'Doğrudan cevap stratejileri oluşturuluyor...', 'Bilgi ve satış ayırımları yapılıyor...'],
    subtasks: [
      { id: '6_1', title: 'Kullanıcı Niyeti (User Intent) Analizi', desc: 'Kullanıcıların ilgili hizmeti aratırken zihinlerinde oluşan temel soruların tespit edilmesi.', selected: true },
      { id: '6_2', title: 'Doğrudan Cevap Stratejisi', desc: 'Sorulan sorulara dolaylı değil, ilk cümlede doğrudan ve net bir şekilde cevap verilmesi (Yapay zekâ özetleri için kritik bir metottur).', selected: true },
      { id: '6_3', title: 'Sayfaya Özel SSS Kurgusu', desc: 'Ana sayfada genel kurumsal soruların; hizmet sayfalarında ise sadece o hizmete özgü detaylı soruların konumlandırılması.', selected: true },
      { id: '6_4', title: 'Bilgi ve Satış Ayrımı', desc: 'Teklif alma (ticari) amacı taşıyan sayfalar ile bilgi verme (blog/rehber) amacı taşıyan sayfaların üslup olarak birbirinden kesin sınırlarla ayrılması.', selected: true },
      { id: '6_5', title: 'Dönüşüm Odaklı Web Tasarım Stratejileri', desc: 'Kullanıcıları müşteriye dönüştüren web tasarım uygulamaları, UI/UX stratejileri ve dönüşüm optimizasyonu (CRO) üzerine içerik önerilerinin kurgulanması.', selected: true }
    ],
    basePrompt: `Sen bir AdresGezgini AI SEO uzmanısın. "{URL}" sitesi için "Kullanıcı Odaklı İçerik ve Soru-Cevap (FAQ) Kurgusu" analizi yap.
Site: {TITLE} | {DESCRIPTION}
Tür: {SITE_TYPE}
Metin: {TEXT}
Aşağıdaki başlıkları (tam olarak aynı yazımla '### Başlık' formatında) kullanarak detaylı ve uygulanabilir bir rapor hazırla:

DİKKAT ÖNEMLİ KURAL: Kullanıcı niyetine uygun SSS (Sıkça Sorulan Sorular) kurgusunu sadece soru olarak bırakma. Hem soruları hem de doğrudan yanıt içeren detaylı cevap metinlerini bizzat yaz ve kopyalanmaya hazır olması için \`\`\`text (markdown) kod bloğu içinde ver.`
  },
  {
    id: 7,
    icon: '🔗',
    title: 'Site İçi İçerik Bağlantıları',
    description: 'Sitenin farklı sayfaları arasında anlamlı köprüler kurarak, botların taramasını kolaylaştırmaktır.',
    loadingMessages: ['İç link yapıları taranıyor...', 'Çapraz bağlantı fırsatları aranıyor...', 'Anchor text kalitesi ölçülüyor...', 'Kırık bağlantı riskleri analiz ediliyor...'],
    subtasks: [
      { id: '7_1', title: 'Blog ve Hizmet Çapraz Bağlantıları', desc: 'Bilgilendirici blog yazılarından, alakalı ana hizmet veya ürün sayfalarına stratejik bağlantıların çıkılması.', selected: true },
      { id: '7_2', title: 'Hiyerarşik Bağlantı Akışı', desc: 'Kategori sayfalarından alt hizmetlere, ana sayfadan ise en öncelikli ticari sayfalara doğrudan bağlantı verilmesi.', selected: true },
      { id: '7_3', title: 'Bağlantı Metni (Anchor Text) Optimizasyonu', desc: '"Buraya tıklayın" gibi anlamsız metinler yerine, hedef sayfanın içeriğini belirten açıklayıcı bağlantı metinlerinin kullanılması.', selected: true },
      { id: '7_4', title: 'Ölçülebilir Referans ve Güven Sinyalleri (E-E-A-T)', desc: 'Tamamlanmış projelerin ölçülebilir başarı hikayeleriyle (case study) ilişkilendirilmesi. Metinlerde şirketin 60+ kişilik uzman kadrosu ve mühendis/akademisyen kurucu geçmişi gibi otorite/güven unsurlarının vurgulanması.', selected: true },
      { id: '7_5', title: 'Kırık Bağlantı Onarımı', desc: 'Site içinde tıklanamayan veya 404 sayfalarına giden eski bağlantıların temizlenmesi ve güncel canonical URL\'lere yönlendirilmesi.', selected: true }
    ],
    basePrompt: `Sen bir AdresGezgini AI SEO uzmanısın. "{URL}" sitesi için "Site İçi İçerik Bağlantıları (Internal Linking)" analizi yap.
Site: {TITLE} | {DESCRIPTION}
Tür: {SITE_TYPE}
Metin: {TEXT}
Aşağıdaki başlıkları (tam olarak aynı yazımla '### Başlık' formatında) kullanarak detaylı ve uygulanabilir bir rapor hazırla:`
  },
  {
    id: 8,
    icon: '🔀',
    title: 'Canonical Link Düzenlemeleri',
    description: 'Arama motorlarına her içeriğin tercih edilen ana adresini bildirmek amacıyla canonical etiketleri ve URL standartlarının düzenlenmesi.',
    loadingMessages: ['URL varyasyonları inceleniyor...', 'Canonical etiketleri taranıyor...', '301 yönlendirmeleri kontrol ediliyor...', 'Sitemap tutarlılığı test ediliyor...'],
    subtasks: [
      { id: '8_1', title: 'URL Standardizasyonu', desc: 'Parametreli URL\'lerin temiz ana URL\'yi göstermesi; HTTP, HTTPS, www ve slash farklılıklarının kontrol edilmesi.', selected: true },
      { id: '8_2', title: 'Yönlendirme ve Kopya Analizi', desc: 'Aynı içeriği açan alternatif URL\'lerin tespiti ve gerekli alanlarda 301 kalıcı yönlendirmelerinin uygulanması.', selected: true },
      { id: '8_3', title: 'Sitemap ve Bağlantı Tutarlılığı', desc: 'Özgün sayfaların kendisini canonical göstermesi; sitemap ve site içi bağlantıların canonical URL\'lerle tutarlı olması.', selected: true }
    ],
    basePrompt: `Sen bir AdresGezgini AI SEO uzmanısın. "{URL}" sitesi için "Canonical Link Düzenlemeleri" analizi yap.
Site: {TITLE} | {DESCRIPTION}
Tür: {SITE_TYPE}
Metin: {TEXT}
Aşağıdaki başlıkları (tam olarak aynı yazımla '### Başlık' formatında) kullanarak detaylı ve uygulanabilir bir rapor hazırla:

DİKKAT ÖNEMLİ KURAL: Yazılımcının doğrudan sunucuya (Apache/Nginx) veya <head> etiketine kopyalayıp yapıştırabileceği 301 yönlendirme kurallarını ve <link rel="canonical"> etiketlerini mutlaka \`\`\`apache, \`\`\`nginx veya \`\`\`html kod blokları içinde ver.`
  }
];


  
  // ============================================================
  // ÖZEL HİZMETLER — In-Memory dizi (persistence için localStorage)
  // ============================================================
  const CUSTOM_SERVICES_KEY = 'ag_custom_seo_services_v2';
  
  // In-memory dizi — buildPrompt fonksiyonlarını korur
  const customServicesRegistry = [];
  
  function persistCustomServices() {
    const data = customServicesRegistry.map(s => ({
      id: s.id,
      icon: s.icon,
      title: s.title,
      description: s.description,
      promptTemplate: s.promptTemplate
    }));
    try { localStorage.setItem(CUSTOM_SERVICES_KEY, JSON.stringify(data)); } catch(e) {}
  }
  
  function buildCustomServiceObj(data) {
    const tmpl = data.promptTemplate || '';
    return {
      id: data.id,
      icon: data.icon || '⭐',
      title: data.title,
      description: data.description,
      promptTemplate: tmpl,
      isCustom: true,
      buildPrompt(d) {
        return tmpl
          .replace(/\{URL\}/g,         d.url)
          .replace(/\{TITLE\}/g,       d.title)
          .replace(/\{DESCRIPTION\}/g, d.description)
          .replace(/\{SITE_TYPE\}/g,   d.siteType)
          .replace(/\{TEXT\}/g,        (d.text || '').substring(0, 4000))
          .replace(/\{SCHEMAS\}/g,     (d.schemas || []).join(', ') || 'Yok');
      }
    };
  }
  
  /** Tüm hizmetler: sabit 7 + özel */
  


async function editSubtaskPrompt(svc, st) {
  const userInput = prompt('Bu alt başlıkta AI analizi için ne gibi bir ekleme/çıkarma istiyorsunuz?\nÖrn: "Analize sosyal medya etkisini de ekle"');
  if (!userInput) return;
  
  // Show a simple loading indicator or use existing
  const originalDesc = st.desc;
  try {
    const aiText = await callGemini(
      `Sen bir prompt mühendisisin. Aşağıdaki orjinal "AI SEO analiz görev tanımını", kullanıcının isteği doğrultusunda GÜNCELLE ve SADECE YENİ GÖREV TANIMINI YAZ.\n\nORJİNAL GÖREV:\n${originalDesc}\n\nKULLANICI İSTEĞİ:\n${userInput}\n\nKurallar:\n- Sadece görev tanımını yaz, başka hiçbir açıklama yapma.\n- Türkçe yaz.`,
      0.3
    );
    st.desc = aiText.trim();
    persistCustomServices();
    alert('✅ Değişiklikler kaydedildi.\n\nYeni Görev:\n' + st.desc);
    renderAllServiceButtons();
  } catch (err) {
    alert('Hata oluştu: ' + err.message);
  }
}


// We need to implement persistCustomServices to save DEFAULT_SERVICES overrides too.
// Wait, customServicesRegistry handles custom services. We can save overrides in a separate key.
function persistCustomServices() {
  localStorage.setItem('ag_aiseo_custom_services', JSON.stringify(customServicesRegistry));
  const overrides = {};
  DEFAULT_SERVICES.forEach(s => {
    overrides[s.id] = { subtasks: s.subtasks, title: s.title, deleted: s.deleted };
  });
  localStorage.setItem('ag_aiseo_default_overrides', JSON.stringify(overrides));
}

// And load it
try {
  const ovr = JSON.parse(localStorage.getItem('ag_aiseo_default_overrides'));
  if (ovr) {
    DEFAULT_SERVICES.forEach(s => {
      if (ovr[s.id]) {
        if (ovr[s.id].subtasks) s.subtasks = ovr[s.id].subtasks;
        if (ovr[s.id].title) s.title = ovr[s.id].title;
        if (ovr[s.id].deleted) s.deleted = ovr[s.id].deleted;
      }
    });
  }
} catch(e) {}


function getAllServices() {
  const custom = customServicesRegistry.map(c => ({
    id: c.id,
    icon: c.icon,
    title: c.title,
    description: c.description,
    loadingMessages: ['Analiz ediliyor...', 'Veriler kontrol ediliyor...'],
    subtasks: c.subtasks || [],
    basePrompt: c.promptTemplate,
    deleted: c.deleted
  }));
  return [...DEFAULT_SERVICES.filter(s => !s.deleted), ...custom.filter(s => !s.deleted)];
}

function buildPromptForService(svc, data) {
  let baseP = svc.basePrompt || '';
  let strictRule = '';
  
  if (baseP.includes('DİKKAT ÖNEMLİ KURAL:')) {
      const parts = baseP.split('DİKKAT ÖNEMLİ KURAL:');
      baseP = parts[0];
      strictRule = '\n\nDİKKAT ÖNEMLİ KURAL:' + parts[1];
  }

  let prompt = baseP
    .replace('{URL}', data.url || '')
    .replace('{TITLE}', data.title || '')
    .replace('{DESCRIPTION}', data.description || '')
    .replace('{SITE_TYPE}', data.siteType || '')
    .replace('{TEXT}', (data.text || '').substring(0, 3000))
    .replace('{SCHEMAS}', data.schemas ? data.schemas.join(', ') : 'Yok');
    
  if (svc.subtasks && svc.subtasks.length > 0) {
    // If it's already in baseP we don't necessarily need it, but it's okay to repeat.
    prompt += "\n\nAşağıdaki başlıkları (tam olarak aynı yazımla '### Başlık' formatında) kullanarak detaylı ve uygulanabilir bir rapor hazırla:\n";
    const selected = svc.subtasks.filter(st => st.selected !== false);
    selected.forEach(st => {
      prompt += `### ${st.title}\n${st.desc}\n\n`;
    });
  }
  
  if (strictRule) {
      prompt += strictRule;
  }
  
  return prompt;
}

// DRAG AND DROP & RENDERING

function renderAllServiceButtons() {
  let modal = document.getElementById('customization-modal');
  if (!modal) {
     modal = document.createElement('div');
     modal.id = 'customization-modal';
     modal.style.cssText = 'display:none; position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:99999; align-items:center; justify-content:center; padding:20px;';
     modal.innerHTML = `
        <div style="background:#fff; width:100%; max-width:640px; max-height:85vh; border-radius:12px; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 20px 25px -5px rgba(0, 0, 0, 0.1);">
           <div style="padding:16px 20px; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; background:#F8FAFC;">
               <h3 style="margin:0; font-size:16px; font-weight:700; color:#1F1D30;">Hizmetleri Özelleştir</h3>
               <button onclick="document.getElementById('customization-modal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer; color:#64748B;">&times;</button>
           </div>
           <div style="padding:20px; overflow-y:auto; flex:1; background:#F1F5F9;">
               <div id="customize-plus-btn-area" style="margin-bottom:16px;"></div>
               <div id="customize-services-list" style="display:flex; flex-direction:column; gap:12px;"></div>
           </div>
        </div>
     `;
     document.body.appendChild(modal);
  }

  const listContainer = modal.querySelector('#customize-services-list');
  listContainer.innerHTML = '';
  
  const all = getAllServices();
  all.forEach(svc => {
    const card = document.createElement('div');
    card.style.cssText = 'background:#fff; border:1px solid #E2E8F0; border-radius:8px; overflow:hidden;';
    
    // Header (Click to open dropdown internally or just keep it open)
    // The user said: "tüm hizmetler alt alta gözüksün ve tıklandığında şu anki gibi her şeyi içersin"
    // Let's make the card header clickable to expand its content
    const header = document.createElement('div');
    header.style.cssText = 'padding:14px 16px; display:flex; align-items:center; justify-content:space-between; cursor:pointer; background:#fff;';
    header.innerHTML = `
       <div style="font-weight:600; font-size:14px; color:#1F1D30;">${svc.icon} ${svc.title}</div>
       <div style="color:#94A3B8; font-size:12px;">▼</div>
    `;
    
    const content = document.createElement('div');
    content.style.cssText = 'display:none; padding:16px; border-top:1px solid #E2E8F0; background:#FAFAFA;';
    
    header.onclick = () => {
       content.style.display = content.style.display === 'none' ? 'block' : 'none';
       header.querySelector('div:last-child').innerText = content.style.display === 'none' ? '▼' : '▲';
    };
    
    // Main Tab Actions
    const mainTabActions = document.createElement('div');
    mainTabActions.style.cssText = 'display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding-bottom:12px; border-bottom:1px solid #E2E8F0;';
    
    const mainTitle = document.createElement('div');
    mainTitle.style.cssText = 'font-size:13px; font-weight:700; color:#1F1D30;';
    mainTitle.textContent = 'Ana Başlık Ayarları';
    
    const mainBtns = document.createElement('div');
    mainBtns.style.display = 'flex';
    mainBtns.style.gap = '4px';
    
    const editMainBtn = document.createElement('button');
    editMainBtn.innerHTML = '✏️ Düzenle';
    editMainBtn.style.cssText = 'background:#fff; border:1px solid #E2E8F0; border-radius:4px; cursor:pointer; font-size:11px; padding:4px 8px; color:#475569;';
    editMainBtn.onclick = () => {
       const nt = prompt('Ana başlık adını değiştirin:', svc.title);
       if (nt) {
          svc.title = nt;
          if (String(svc.id).startsWith('custom_')) {
             const cIdx = customServicesRegistry.findIndex(c => c.id === svc.id);
             if (cIdx > -1) customServicesRegistry[cIdx].title = nt;
          }
          persistCustomServices();
          renderAllServiceButtons();
          renderIdleAccordions(true); // refresh main screen
       }
    };
    
    const delMainBtn = document.createElement('button');
    delMainBtn.innerHTML = '🗑️ Sil';
    delMainBtn.style.cssText = 'background:#fff; border:1px solid #FECACA; border-radius:4px; cursor:pointer; font-size:11px; padding:4px 8px; color:#EF4444;';
    delMainBtn.onclick = () => {
       if (confirm('Bu ana başlığı ve içindeki tüm alt başlıkları silmek istediğinize emin misiniz?')) {
          svc.deleted = true;
          if (String(svc.id).startsWith('custom_')) {
             const cIdx = customServicesRegistry.findIndex(c => c.id === svc.id);
             if (cIdx > -1) customServicesRegistry[cIdx].deleted = true;
          }
          persistCustomServices();
          renderAllServiceButtons();
          renderIdleAccordions(true);
       }
    };
    
    mainBtns.appendChild(editMainBtn);
    mainBtns.appendChild(delMainBtn);
    mainTabActions.appendChild(mainTitle);
    mainTabActions.appendChild(mainBtns);
    
    content.appendChild(mainTabActions);
    
    // Subtasks List
    const subList = document.createElement('div');
    
    (svc.subtasks || []).forEach(st => {
        const item = document.createElement('div');
        item.style.cssText = 'display:flex; align-items:flex-start; padding:8px 0; border-bottom:1px solid #f1f5f9;';
        
        // Drag logic
        item.draggable = true;
        item.ondragstart = (e) => {
          e.dataTransfer.setData('text/plain', JSON.stringify({ parentId: svc.id, subtaskId: st.id }));
          item.style.opacity = '0.5';
        };
        item.ondragend = () => { item.style.opacity = '1'; };

        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.checked = st.selected !== false;
        cb.style.cssText = 'margin-top:2px; margin-right:8px; cursor:pointer;';
        cb.onchange = () => {
           st.selected = cb.checked;
           persistCustomServices();
        };
        
        const textWrap = document.createElement('div');
        textWrap.style.flex = '1';
        
        const stTitle = document.createElement('div');
        stTitle.style.cssText = 'font-size:13px; font-weight:600; color:#334155; margin-bottom:2px;';
        stTitle.textContent = st.title;
        
        const stDesc = document.createElement('div');
        stDesc.style.cssText = 'font-size:11px; color:#64748b; line-height:1.4;';
        stDesc.textContent = st.desc;
        
        textWrap.appendChild(stTitle);
        textWrap.appendChild(stDesc);
        
        const editBtn = document.createElement('button');
        editBtn.innerHTML = '✏️';
        editBtn.style.cssText = 'background:none; border:none; cursor:pointer; font-size:13px; padding:4px;';
        editBtn.onclick = (e) => {
          e.stopPropagation();
          editSubtaskPrompt(svc, st);
        };
        
        const delBtn = document.createElement('button');
        delBtn.innerHTML = '🗑️';
        delBtn.style.cssText = 'background:none; border:none; cursor:pointer; font-size:13px; padding:4px; margin-left:2px;';
        delBtn.onclick = (e) => {
          e.stopPropagation();
          if(confirm('Bu alt başlığı silmek istediğinize emin misiniz?')) {
            const idx = svc.subtasks.findIndex(x => x.id === st.id);
            if (idx > -1) {
              svc.subtasks.splice(idx, 1);
              persistCustomServices();
              renderAllServiceButtons();
            }
          }
        };
        
        item.appendChild(cb);
        item.appendChild(textWrap);
        item.appendChild(editBtn);
        item.appendChild(delBtn);
        subList.appendChild(item);
    });
    
    content.appendChild(subList);
    
    // Add Subtask Button
    const addSubBtn = document.createElement('button');
    addSubBtn.innerHTML = '+ Alt Başlık Ekle';
    addSubBtn.style.cssText = 'background:none; color:#312F4D; border:1px dashed #94A3B8; padding:8px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; width:100%; margin-top:12px;';
    addSubBtn.onclick = async () => {
       const title = prompt('Yeni alt başlık adını girin (Örn: Mobil SEO):');
       if (!title) return;
       const descInput = prompt('Bu başlıkta neyin analiz edilmesini istersiniz?\\nAI otomatik görev tanımı oluşturacaktır.');
       if (!descInput) return;
       
       try {
         const aiText = await callGemini(
           `Sen bir AI SEO prompt mühendisisin. Aşağıdaki bilgileri kullanarak profesyonel bir analiz görev tanımı yaz (1-2 cümle).
Başlık: ${title}
Kullanıcı İsteği: ${descInput}

Kurallar:
- Sadece görev tanımını Türkçe olarak yaz.`,
           0.3
         );
         
         const newSt = {
           id: 'st_' + Date.now(),
           title: title.trim(),
           desc: aiText.trim(),
           selected: true
         };
         
         if (!svc.subtasks) svc.subtasks = [];
         svc.subtasks.push(newSt);
         persistCustomServices();
         alert('✅ Yeni alt başlık oluşturuldu ve eklendi.\n\nGörev:\n' + newSt.desc);
         renderAllServiceButtons();
       } catch(e) {
         alert('Hata: ' + e.message);
       }
    };
    
    content.appendChild(addSubBtn);
    
    card.appendChild(header);
    card.appendChild(content);
    listContainer.appendChild(card);
  });
  
  appendAddButton();
}

function appendAddButton() {
  const container = document.getElementById('customize-plus-btn-area');
  if (!container) return;

  const old = document.getElementById('btn-add-custom-service');
  if (old) old.remove();

  const btn = document.createElement('button');
  btn.id    = 'btn-add-custom-service';
  btn.title = 'Özel hizmet ekle (Alt sekmeleri buraya sürükleyip bırakabilirsiniz)';
  btn.style.cssText = 'display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:12px; background:#fff; border:2px dashed #94A3B8; border-radius:8px; cursor:pointer; font-size:14px; font-weight:600; color:#475569; transition:all .15s;';
  btn.innerHTML = '<span>+</span> Yeni Özel Hizmet Ekle veya Sürükle Bırak';
  
  // Drag and drop event listeners
  btn.addEventListener('dragover', (e) => {
    e.preventDefault();
    btn.style.borderColor = '#10B981';
    btn.style.background = '#F0FDF4';
  });
  btn.addEventListener('dragleave', () => {
    btn.style.borderColor = '#94A3B8';
    btn.style.background = '#fff';
  });
  btn.addEventListener('drop', (e) => {
    e.preventDefault();
    btn.style.borderColor = '#94A3B8';
    btn.style.background = '#fff';
    
    try {
      const data = JSON.parse(e.dataTransfer.getData('text/plain'));
      const svc = getAllServices().find(s => String(s.id) === String(data.parentId));
      if (svc) {
        const subIdx = svc.subtasks.findIndex(st => String(st.id) === String(data.subtaskId));
        if (subIdx > -1) {
          const st = svc.subtasks[subIdx];
          svc.subtasks.splice(subIdx, 1);
          
          // Add as custom service
          const newCustom = {
            id: 'custom_' + Date.now(),
            icon: '⭐',
            title: st.title,
            description: st.desc,
            promptTemplate: "Sen bir AdresGezgini AI SEO uzmanısın. {URL} sitesi için \" + st.title + \" analizi yap.\nSite: {TITLE} | {DESCRIPTION}\nTür: {SITE_TYPE}\nMetin: {TEXT}\n\nAşağıdaki başlıkları (tam olarak aynı yazımla '### Başlık' formatında) kullanarak detaylı ve uygulanabilir bir rapor hazırla:\n### \" + st.title + \"\n\" + st.desc + \"\n",
            subtasks: [{ id: 'custom_st_' + Date.now(), title: st.title, desc: st.desc, selected: true }],
            loadingMessages: ['Analiz ediliyor...']
          };
          customServicesRegistry.push(newCustom);
          persistCustomServices();
          renderAllServiceButtons();
          renderIdleAccordions(true);
        }
      }
    } catch(err) { console.error('Drop err:', err); }
  });

  btn.addEventListener('click', openAddCustomServiceModal);
  container.appendChild(btn);
}


function openAddCustomServiceModal() {
    document.getElementById('custom-svc-modal')?.remove();
  
    const modal = document.createElement('div');
    modal.id = 'custom-svc-modal';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:10000;display:flex;align-items:center;justify-content:center;padding:20px;';
    modal.innerHTML = `
      <div style="background:#fff;border-radius:10px;border-top:3px solid #312F4D;padding:32px;max-width:480px;width:100%;box-shadow:0 10px 30px rgba(31,29,48,.18);animation:ag-slide-down .25s ease;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
          <div>
            <h3 style="font-size:17px;font-weight:700;color:#1F1D30;margin:0 0 4px;">Özel Hizmet Ekle</h3>
            <p style="font-size:13px;color:#6B6E82;margin:0;">AI bu hizmet için analiz promptunu otomatik oluşturacak</p>
          </div>
          <button id="csm-close" style="background:none;border:none;cursor:pointer;font-size:22px;color:#6B6E82;line-height:1;padding:4px;">✕</button>
        </div>
  
        <div style="margin-bottom:14px;">
          <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Hizmet Adı *</label>
          <input id="csm-name" type="text" placeholder="Örn: Mobil SEO Denetimi"
            style="width:100%;border:1px solid #E4E3EC;border-radius:6px;padding:10px 14px;font-size:14px;box-sizing:border-box;font-family:inherit;">
        </div>
  
        <div style="margin-bottom:24px;">
          <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Hizmet Detayı *</label>
          <textarea id="csm-desc" rows="4" placeholder="Bu hizmet kapsamında ne yapılacak? AI bu bilgiyi kullanarak analiz sorularını oluşturacak..."
            style="width:100%;border:1px solid #E4E3EC;border-radius:6px;padding:10px 14px;font-size:13px;box-sizing:border-box;resize:vertical;line-height:1.6;font-family:inherit;"></textarea>
        </div>
  
        <div id="csm-loading" style="display:none;text-align:center;padding:10px;color:#6B6E82;font-size:13px;">
          <span style="display:inline-block;width:14px;height:14px;border:2px solid #312F4D;border-top-color:transparent;border-radius:50%;animation:ag-spin .8s linear infinite;vertical-align:middle;margin-right:8px;"></span>
          AI prompt oluşturuyor...
        </div>
  
        <div style="display:flex;gap:10px;">
          <button id="csm-cancel" style="flex:1;padding:11px;background:#F1F0F5;color:#475569;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;">İptal</button>
          <button id="csm-save"   style="flex:2;padding:11px;background:#312F4D;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:700;cursor:pointer;">Kaydet</button>
        </div>
      </div>`;
  
    document.body.appendChild(modal);
    setTimeout(() => document.getElementById('csm-name')?.focus(), 80);
  
    const close = () => modal.remove();
    document.getElementById('csm-close').onclick  = close;
    document.getElementById('csm-cancel').onclick = close;
    modal.addEventListener('click', e => { if (e.target === modal) close(); });
  
    document.getElementById('csm-save').addEventListener('click', async () => {
      const name = (document.getElementById('csm-name').value || '').trim();
      const desc = (document.getElementById('csm-desc').value || '').trim();
      if (!name || !desc) { alert('Hizmet adı ve detayını doldurun.'); return; }
  
      const saveBtn = document.getElementById('csm-save');
      const loading = document.getElementById('csm-loading');
      saveBtn.disabled = true; saveBtn.style.opacity = '0.6';
      if (loading) loading.style.display = 'block';
  
      try {
        const generatedPrompt = await callGemini(
          `Sen bir AI SEO uzmanısın. Aşağıdaki hizmet için bir web sitesi analiz promptu yaz.
  
  Hizmet adı: ${name}
  Hizmet açıklaması: ${desc}
  
  Kurallar:
  - Prompt Türkçe olmalı
  - Prompt içinde şu yer tutucuları kullan: {URL}, {TITLE}, {DESCRIPTION}, {SITE_TYPE}, {TEXT}, {SCHEMAS}
  - Markdown başlıkları (## ile) kullan
  - En az 5, en fazla 7 ana başlık
  - Son başlık "## Öncelikli 5 Aksiyon" olsun
  - Her başlık altında somut, uygulanabilir öneriler iste
  - Sadece promptu yaz, başka açıklama ekleme`,
          0.4
        );
  
        const newSvc = buildCustomServiceObj({
          id:             'custom_' + Date.now(),
          icon:           '⭐',
          title:          name,
          description:    desc,
          promptTemplate: generatedPrompt
        });
  
        customServicesRegistry.push(newSvc);
        persistCustomServices();
        renderCustomServiceButton(newSvc);
        updateRunAllBtn();
        close();
  
      } catch (err) {

      if (loadingInterval) clearInterval(loadingInterval);

        if (loading) loading.style.display = 'none';
        saveBtn.disabled = false; saveBtn.style.opacity = '1';
        alert('Hata: ' + err.message);
      }
    });
  }
  
  // ============================================================
  // GEÇMİŞ
  // ============================================================
  
  async function saveAnalysis() {
    if (!state.targetUrl || Object.keys(state.serviceResults).length === 0) return;
    await fetch('ai_seo/api/save_chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        chatId:            state.currentChatId,
        url:               state.targetUrl,
        type:              state.siteType,
        serviceResults:    state.serviceResults,
        completedServices: Array.from(state.completedServices).filter(id => !String(id).startsWith('loading_')),
        messages:          []
      })
    });
    loadHistory().catch(() => {});
  }
  
  async function loadHistory() {
    const list = document.getElementById('copilot-sidebar-history-list');
    try {
      const data    = await (await fetch('ai_seo/api/save_chat.php?t=' + Date.now())).json();
      const history = data.history || [];
      window.agChatHistory = history;
      if (typeof window.renderDashboard === 'function') window.renderDashboard();
      if (!list) return;
      list.innerHTML = '';
      if (!history.length) { list.innerHTML = '<p class="empty-note">Henüz geçmiş analiz yok.</p>'; return; }
      history.forEach(item => {
        const div = document.createElement('div');
        div.style.cssText = 'padding:12px;background:#f9fafb;border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;gap:8px;';
        const cnt = (item.completedServices || []).filter(id => !String(id).startsWith('loading_')).length;
        div.innerHTML = `
          <div style="flex:1;cursor:pointer;min-width:0;" class="hist-load-btn" data-idx="${history.indexOf(item)}">
            <div style="font-weight:600;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${item.url}</div>
            <div style="font-size:11px;color:#666;margin-top:2px;">${item.date || ''} • ${cnt} hizmet</div>
          </div>
          <button data-del-id="${item.chatId}" style="background:none;border:none;cursor:pointer;color:#ef4444;flex-shrink:0;padding:4px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          </button>`;
  
        div.querySelector('[data-del-id]').onclick = async e => {
          e.stopPropagation();
          if (!confirm('Bu analizi silmek istiyor musunuz?')) return;
          await fetch('ai_seo/api/save_chat.php?id=' + item.chatId, { method: 'DELETE' });
          loadHistory();
        };
        div.querySelector('.hist-load-btn').onclick = () => {
          loadFromHistory(item);
          document.getElementById('ai-history-sidebar').style.right = '-380px';
        };
        list.appendChild(div);
      });
    } catch(e) { console.warn('Geçmiş yükleme hatası:', e); }
  }
  
    function openAddCustomServiceModal() {
    document.getElementById('custom-svc-modal')?.remove();
  
    const modal = document.createElement('div');
    modal.id = 'custom-svc-modal';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:10000;display:flex;align-items:center;justify-content:center;padding:20px;';
    modal.innerHTML = `
      <div style="background:#fff;border-radius:10px;border-top:3px solid #312F4D;padding:32px;max-width:480px;width:100%;box-shadow:0 10px 30px rgba(31,29,48,.18);animation:ag-slide-down .25s ease;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
          <div>
            <h3 style="font-size:17px;font-weight:700;color:#1F1D30;margin:0 0 4px;">Özel Hizmet Ekle</h3>
            <p style="font-size:13px;color:#6B6E82;margin:0;">AI bu hizmet için analiz promptunu otomatik oluşturacak</p>
          </div>
          <button id="csm-close" style="background:none;border:none;cursor:pointer;font-size:22px;color:#6B6E82;line-height:1;padding:4px;">✕</button>
        </div>
  
        <div style="margin-bottom:14px;">
          <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Hizmet Adı *</label>
          <input id="csm-name" type="text" placeholder="Örn: Mobil SEO Denetimi"
            style="width:100%;border:1px solid #E4E3EC;border-radius:6px;padding:10px 14px;font-size:14px;box-sizing:border-box;font-family:inherit;">
        </div>
  
        <div style="margin-bottom:24px;">
          <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Hizmet Detayı *</label>
          <textarea id="csm-desc" rows="4" placeholder="Bu hizmet kapsamında ne yapılacak? AI bu bilgiyi kullanarak analiz sorularını oluşturacak..."
            style="width:100%;border:1px solid #E4E3EC;border-radius:6px;padding:10px 14px;font-size:13px;box-sizing:border-box;resize:vertical;line-height:1.6;font-family:inherit;"></textarea>
        </div>
  
        <div id="csm-loading" style="display:none;text-align:center;padding:10px;color:#6B6E82;font-size:13px;">
          <span style="display:inline-block;width:14px;height:14px;border:2px solid #312F4D;border-top-color:transparent;border-radius:50%;animation:ag-spin .8s linear infinite;vertical-align:middle;margin-right:8px;"></span>
          AI prompt oluşturuyor...
        </div>
  
        <div style="display:flex;gap:10px;">
          <button id="csm-cancel" style="flex:1;padding:11px;background:#F1F0F5;color:#475569;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;">İptal</button>
          <button id="csm-save"   style="flex:2;padding:11px;background:#312F4D;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:700;cursor:pointer;">Kaydet</button>
        </div>
      </div>`;
  
    document.body.appendChild(modal);
    setTimeout(() => document.getElementById('csm-name')?.focus(), 80);
  
    const close = () => modal.remove();
    document.getElementById('csm-close').onclick  = close;
    document.getElementById('csm-cancel').onclick = close;
    modal.addEventListener('click', e => { if (e.target === modal) close(); });
  
    document.getElementById('csm-save').addEventListener('click', async () => {
      const name = (document.getElementById('csm-name').value || '').trim();
      const desc = (document.getElementById('csm-desc').value || '').trim();
      if (!name || !desc) { alert('Hizmet adı ve detayını doldurun.'); return; }
  
      const saveBtn = document.getElementById('csm-save');
      const loading = document.getElementById('csm-loading');
      saveBtn.disabled = true; saveBtn.style.opacity = '0.6';
      if (loading) loading.style.display = 'block';
  
      try {
        const generatedPrompt = await callGemini(
          `Sen bir AI SEO uzmanısın. Aşağıdaki hizmet için bir web sitesi analiz promptu yaz.
  
  Hizmet adı: ${name}
  Hizmet açıklaması: ${desc}
  
  Kurallar:
  - Prompt Türkçe olmalı
  - Prompt içinde şu yer tutucuları kullan: {URL}, {TITLE}, {DESCRIPTION}, {SITE_TYPE}, {TEXT}, {SCHEMAS}
  - Markdown başlıkları (## ile) kullan
  - En az 5, en fazla 7 ana başlık
  - Son başlık "## Öncelikli 5 Aksiyon" olsun
  - Her başlık altında somut, uygulanabilir öneriler iste
  - Sadece promptu yaz, başka açıklama ekleme`,
          0.4
        );
  
        const newSvc = buildCustomServiceObj({
          id:             'custom_' + Date.now(),
          icon:           '⭐',
          title:          name,
          description:    desc,
          promptTemplate: generatedPrompt
        });
  
        customServicesRegistry.push(newSvc);
        persistCustomServices();
        renderCustomServiceButton(newSvc);
        updateRunAllBtn();
        close();
  
      } catch (err) {
        if (loading) loading.style.display = 'none';
        saveBtn.disabled = false; saveBtn.style.opacity = '1';
        alert('Hata: ' + err.message);
      }
    });
  }
  
  // ============================================================
  // GEÇMİŞ
  // ============================================================
  
  async function saveAnalysis() {
    if (!state.targetUrl || Object.keys(state.serviceResults).length === 0) return;
    await fetch('ai_seo/api/save_chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        chatId:            state.currentChatId,
        url:               state.targetUrl,
        type:              state.siteType,
        serviceResults:    state.serviceResults,
        completedServices: Array.from(state.completedServices).filter(id => !String(id).startsWith('loading_')),
        messages:          []
      })
    });
    loadHistory().catch(() => {});
  }
  
  async function loadHistory() {
    const list = document.getElementById('copilot-sidebar-history-list');
    try {
      const data    = await (await fetch('ai_seo/api/save_chat.php?t=' + Date.now())).json();
      const history = data.history || [];
      window.agChatHistory = history;
      if (typeof window.renderDashboard === 'function') window.renderDashboard();
      if (!list) return;
      list.innerHTML = '';
      if (!history.length) { list.innerHTML = '<p class="empty-note">Henüz geçmiş analiz yok.</p>'; return; }
      history.forEach(item => {
        const div = document.createElement('div');
        div.style.cssText = 'padding:12px;background:#f9fafb;border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;gap:8px;';
        const cnt = (item.completedServices || []).filter(id => !String(id).startsWith('loading_')).length;
        div.innerHTML = `
          <div style="flex:1;cursor:pointer;min-width:0;" class="hist-load-btn" data-idx="${history.indexOf(item)}">
            <div style="font-weight:600;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${item.url}</div>
            <div style="font-size:11px;color:#666;margin-top:2px;">${item.date || ''} • ${cnt} hizmet</div>
          </div>
          <button data-del-id="${item.chatId}" style="background:none;border:none;cursor:pointer;color:#ef4444;flex-shrink:0;padding:4px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          </button>`;
  
        div.querySelector('[data-del-id]').onclick = async e => {
          e.stopPropagation();
          if (!confirm('Bu analizi silmek istiyor musunuz?')) return;
          await fetch('ai_seo/api/save_chat.php?id=' + item.chatId, { method: 'DELETE' });
          loadHistory();
        };
        div.querySelector('.hist-load-btn').onclick = () => {
          loadFromHistory(item);
          document.getElementById('ai-history-sidebar').style.right = '-380px';
        };
        list.appendChild(div);
      });
    } catch(e) { console.warn('Geçmiş yükleme hatası:', e); }
  }
  


function loadFromHistory(item) {
    resetAnalysis(false);
    state.targetUrl      = item.url;
    state.siteType       = item.type || '';
    state.currentChatId  = item.chatId;
    state.serviceResults = item.serviceResults || {};
    state.completedServices = new Set(
      (item.completedServices || []).map(id => isNaN(id) ? id : Number(id))
    );
    updateBadges();
    document.getElementById('aiseo-url-card').style.display      = 'none';
          document.getElementById('aiseo-services-panel').style.display = 'block';
      
      // Setup Customization Toggle
      
      const custBtn = document.getElementById('btn-customize-services');
      if (custBtn) {
         custBtn.onclick = () => {
            let modal = document.getElementById('customization-modal');
            if(modal) {
               modal.style.display = 'flex';
            }
         };
      }
      
      // Render the idle skeleton accordions
      renderIdleAccordions();

  
    getAllServices().forEach(svc => {
      const key = String(svc.id);
      if (state.completedServices.has(svc.id) && state.serviceResults[key]) {
        updateServiceButtonState(svc.id, 'done');
        renderServiceAccordion(svc, 'done', state.serviceResults[key].html || state.serviceResults[key].raw || '');
      }
    });
    updateRunAllBtn(); updateDownloadBtn();
  }
  
  function resetAnalysis(showUrlCard = true) {
    state.targetUrl         = '';
    state.siteType          = '';
    state.fetchedData       = null;
    state.completedServices = new Set();
    state.serviceResults    = {};
    state.currentChatId     = String(Date.now());
  
    updateBadges();
    const area = document.getElementById('aiseo-results-area');
    if (area) area.innerHTML = '';
    getAllServices().forEach(s => updateServiceButtonState(s.id, 'idle'));
  
    const urlCard = document.getElementById('aiseo-url-card');
    const panel   = document.getElementById('aiseo-services-panel');
    const input   = document.getElementById('aiseo-url-input');
    if (urlCard) urlCard.style.display = showUrlCard ? 'block' : 'none';
    if (panel)   panel.style.display   = showUrlCard ? 'none'  : 'block';
    if (input) { input.value = ''; if (showUrlCard) setTimeout(() => input.focus(), 100); }
    updateDownloadBtn();
  }
  
  // ============================================================
  // INIT
  // ============================================================
  
  function initAiseoCopilot() {
  
    // localStorage'dan özel hizmetleri yükle
    try {
      const saved = JSON.parse(localStorage.getItem(CUSTOM_SERVICES_KEY) || '[]');
      saved.forEach(data => {
        if (!customServicesRegistry.find(s => s.id === data.id)) {
          customServicesRegistry.push(buildCustomServiceObj(data));
        }
      });
    } catch(e) {}
  
    // URL submit
    function handleSubmit() {
      const val = (document.getElementById('aiseo-url-input')?.value || '').trim();
      if (!val) return;
      if (!val.startsWith('http')) { alert('Geçerli bir URL girin (http/https ile başlamalı).'); return; }
      state.targetUrl = val;
      detectAndFetchSite(val);
    }
    document.getElementById('aiseo-url-submit')?.addEventListener('click', handleSubmit);
    document.getElementById('aiseo-url-input')?.addEventListener('keypress', e => { if (e.key === 'Enter') handleSubmit(); });
  
    // Sabit hizmet butonları
    document.querySelectorAll('.aiseo-svc-btn[data-service]').forEach(btn => {
      btn.addEventListener('click', () => {
        const raw = btn.getAttribute('data-service');
        window.runSingleService(isNaN(raw) ? raw : parseInt(raw, 10));
      });
    });
  
    // Tüm Hizmetleri Yap
    document.getElementById('btn-run-all-services')?.addEventListener('click', window.runAllServices);
  
    // Yeni Analiz
    document.getElementById('btn-new-analysis')?.addEventListener('click', () => resetAnalysis(true));
  
    // History sidebar
    const sidebar = document.getElementById('ai-history-sidebar');
    document.getElementById('btn-toggle-history-sidebar')?.addEventListener('click', () => { if (sidebar) sidebar.style.right = '0'; });
    document.getElementById('btn-close-history-sidebar')?.addEventListener('click', () =>  { if (sidebar) sidebar.style.right = '-380px'; });
    document.getElementById('btn-clear-history-sidebar')?.addEventListener('click', async () => {
      if (!confirm('Tüm geçmiş silinecek. Emin misiniz?')) return;
      await fetch('ai_seo/api/save_chat.php?id=all', { method: 'DELETE' });
      loadHistory();
    });
  
    // PDF butonu
    document.getElementById('btn-download-pdf')?.addEventListener('click', () => {
      if (!getAllServices().every(s => state.completedServices.has(s.id))) {
        alert('Tüm hizmetler tamamlandığında rapor indirilebilir.'); return;
      }
      downloadPDF();
    });
  
    // + butonu ve özel hizmet butonları DOM'a ekle
    renderAllServiceButtons();
  
    // İlk yükleme
    resetAnalysis(true);
    loadHistory();
  
    // Eski uyumluluk stubs
    window._forceResetChat               = () => resetAnalysis(true);
    window.startFreshAnalysis            = () => resetAnalysis(true);
    window.startNewAnalysisFromDashboard = () => resetAnalysis(true);
    window.renderTodos                   = window.renderTodos          || function() {};
    window.extractTodosAndSend           = window.extractTodosAndSend  || function() {};
    window.agChatHistory                 = window.agChatHistory        || [];
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAiseoCopilot);
  } else {
    initAiseoCopilot();
  }
  
  // CSS inject: animasyonlar
  (function injectStyles() {
    if (document.getElementById('ag-aiseo-styles')) return;
    const s = document.createElement('style');
    s.id = 'ag-aiseo-styles';
    s.textContent = `
      @keyframes ag-spin      { to { transform: rotate(360deg); } }
      @keyframes ag-slide-down { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }
      .aiseo-accordion { animation: ag-slide-down .25s ease; }
    `;
    document.head.appendChild(s);
  })();
  
  })(); // /IIFE — copilot.js scope sonu