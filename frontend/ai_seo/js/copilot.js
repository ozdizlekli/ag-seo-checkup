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
  if (modal) {
      modal.remove(); // Force completely fresh modal on every open
  }
  
  modal = document.createElement('div');
     modal.id = 'customization-modal';
     modal.style.cssText = 'display:none; position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:99999; align-items:center; justify-content:center; padding:20px;';
     modal.innerHTML = `
        <div style="background:#fff; width:100%; max-width:640px; max-height:85vh; border-radius:12px; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 20px 25px -5px rgba(0, 0, 0, 0.1);">
           <div style="padding:16px 20px; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; background:#F8FAFC;">
               <h3 style="margin:0; font-size:16px; font-weight:700; color:#1F1D30;">Hizmetleri Özelleştir</h3>
               <button onclick="document.getElementById('customization-modal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer; color:#64748B;">&times;</button>
           </div>
           <div style="background:#F8FAFC; border-bottom:1px solid #E2E8F0; padding:16px 20px;">
               <div id="customize-plus-btn-area"></div>
           </div>
           <div style="padding:20px; overflow-y:auto; flex:1; background:#F1F5F9;">
               <div id="customize-services-list" style="display:flex; flex-direction:column; gap:12px;"></div>
           </div>
           <div style="padding:16px 20px; border-top:1px solid #E2E8F0; background:#F8FAFC; display:flex; justify-content:flex-end; gap:12px; align-items:center;">
               <button id="btn-reset-customization" style="background:none; border:none; color:#EF4444; font-size:13px; font-weight:600; cursor:pointer; padding:8px 12px; border-radius:6px; transition:background .15s;">Sıfırla (Varsayılan)</button>
               <button id="btn-save-customization" style="background:#10B981; color:#fff; border:none; font-size:14px; font-weight:600; cursor:pointer; padding:10px 20px; border-radius:8px; box-shadow:0 2px 4px rgba(16,185,129,0.2); transition:all .15s;">Değişiklikleri Kaydet</button>
           </div>
        </div>
     `;
     document.body.appendChild(modal);
     
     document.getElementById('btn-reset-customization').onclick = () => {
         if (confirm('Tüm özelleştirmeleri silip orijinal varsayılan ayarlara dönmek istediğinize emin misiniz?')) {
             localStorage.removeItem('ag_custom_seo_services_v2'); // CUSTOM_SERVICES_KEY
             customServicesRegistry.length = 0; // Clear all custom ones
             // Clear all overrides in built-in ones
             getAllServices().forEach(svc => {
                if(svc.subtasks) {
                   svc.subtasks.forEach(st => st.selected = true);
                }
             });
             renderAllServiceButtons();
             renderIdleAccordions(true);
             getAllServices().forEach(svc => {
                 const key = String(svc.id);
                 if (state.completedServices.has(svc.id) && state.serviceResults[key]) {
                     renderServiceAccordion(svc, 'done', state.serviceResults[key].html || state.serviceResults[key].raw || '');
                 }
             });
         }
     };
     
     document.getElementById('btn-save-customization').onclick = () => {
         document.getElementById('customization-modal').style.display = 'none';
         renderIdleAccordions(true); // BU DEĞİŞİKLİKLERİ UYGULA
         // Tamamlanmış olanları geri yükle
         getAllServices().forEach(svc => {
             const key = String(svc.id);
             if (state.completedServices.has(svc.id) && state.serviceResults[key]) {
                 renderServiceAccordion(svc, 'done', state.serviceResults[key].html || state.serviceResults[key].raw || '');
             }
         });
     };

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
          
          const dragGhost = item.cloneNode(true);
          dragGhost.style.opacity = '1';
          dragGhost.style.background = '#fff';
          dragGhost.style.padding = '8px 12px';
          dragGhost.style.border = '1px solid #10B981';
          dragGhost.style.borderRadius = '8px';
          dragGhost.style.boxShadow = '0 8px 16px rgba(0,0,0,0.1)';
          dragGhost.style.width = item.offsetWidth + 'px';
          dragGhost.style.position = 'absolute';
          dragGhost.style.top = '-1000px';
          document.body.appendChild(dragGhost);
          
          e.dataTransfer.setDragImage(dragGhost, 15, 15);
          setTimeout(() => { if (dragGhost.parentNode) dragGhost.remove(); }, 100);
          
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
  
  appendModalAddButton();
}

function appendModalAddButton() {
  let container = document.getElementById('customize-plus-btn-area');
  if (!container) {
      // Failsafe: if the area is somehow missing, prepend to list
      container = document.getElementById('customize-services-list');
  }
  if (!container) return;

  const old = document.getElementById('btn-modal-add-custom-service');
  if (old) old.remove();

  const btn = document.createElement('button');
  btn.id    = 'btn-modal-add-custom-service';
  btn.title = 'Özel hizmet ekle (Alt sekmeleri buraya sürükleyip bırakabilirsiniz)';
  // Kullanıcının mutlaka görmesi gereken devasa belirgin yeni özel sekme (artı) kutusu
  btn.style.cssText = 'display:flex; align-items:center; justify-content:center; width:100%; padding:16px; margin:0; background:#fff; border:2px dashed #94A3B8; border-radius:8px; cursor:pointer; color:#475569; transition:all .15s; font-family:\'Source Sans Pro\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; gap:12px;';
  btn.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;width:32px;height:32px;background:#F1F5F9;border-radius:50%;color:#334155;font-size:22px;line-height:1;">+</div><div style="text-align:left;"><div style="font-weight:600;font-size:14px;color:#1F2937;">Yeni Ana Hizmet / Sekme Ekle</div><div style="font-size:12px;color:#64748B;margin-top:2px;">Sıfırdan oluşturmak için tıklayın veya bir alt başlığı buraya sürükleyin.</div></div>';
  btn.addEventListener('mouseenter', () => { btn.style.borderColor='#FBBA00'; btn.style.borderStyle='solid'; btn.style.color='#312F4D'; btn.style.background='#FFF6DF'; });
  btn.addEventListener('mouseleave', () => { btn.style.borderColor='#9A9DAE'; btn.style.borderStyle='dashed'; btn.style.color='#6B6E82'; btn.style.background='#fff'; });
  
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


  async function callGemini(prompt, temperature = 0.3) {
    const res = await fetch('form_submit.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        contents: [{ parts: [{ text: prompt }] }],
        generationConfig: { temperature }
      })
    });
    if (!res.ok) throw new Error('Sunucu hatası: ' + res.status);
    const result = await res.json();
    if (result.error) throw new Error(result.error.message || JSON.stringify(result.error));
    const text = result?.candidates?.[0]?.content?.parts?.[0]?.text;
    if (!text) throw new Error('AI yanıt vermedi.');
    return text;
  }
  
  async function fetchSiteData(url) {
    const res = await fetch('fetch_url.php?url=' + encodeURIComponent(url));
    if (!res.ok) throw new Error('Site verileri alınamadı: ' + res.status);
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    return {
      title:       data.title       || '',
      description: data.description || '',
      text:        data.text        || '',
      schemas:     Array.isArray(data.schemas) ? data.schemas : []
    };
  }
  
  function mdToHtml(md) {
    if (typeof marked !== 'undefined') {
      try { return marked.parse(md); } catch(e) {}
    }
    // Basit fallback
    return md
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/^### (.+)$/gm, '<h3>$1</h3>')
      .replace(/^## (.+)$/gm,  '<h2>$1</h2>')
      .replace(/^# (.+)$/gm,   '<h1>$1</h1>')
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/`([^`]+)`/g, '<code>$1</code>')
      .replace(/^[-*] (.+)$/gm, '<li>$1</li>')
      .replace(/(<li>[\s\S]*?<\/li>)/g, '<ul>$1</ul>')
      .replace(/\n\n+/g, '</p><p>')
      .replace(/^([^<\n].+)$/gm, '<p>$1</p>');
  }
  
  function updateServiceButtonState(serviceId, status) {
    const btn  = document.querySelector(`.aiseo-svc-btn[data-service="${serviceId}"]`);
    const span = document.querySelector(`.svc-status[data-svc="${serviceId}"]`);
    if (!btn) return;
    const styles = {
      loading: { border: '#f59e0b', bg: '#fffbeb', color: '#92400e' },
      done:    { border: '#10b981', bg: '#f0fdf4', color: '#065f46' },
      error:   { border: '#ef4444', bg: '#fef2f2', color: '#991b1b' },
      idle:    { border: '#e2e8f0', bg: '#fff',    color: '#334155' }
    };
    const s = styles[status] || styles.idle;
    btn.style.borderColor = s.border;
    btn.style.background  = s.bg;
    btn.style.color       = s.color;
    if (span) {
      if (status === 'loading') {
        span.innerHTML = '<span style="display:inline-block;width:10px;height:10px;border:2px solid #f59e0b;border-top-color:transparent;border-radius:50%;animation:ag-spin .8s linear infinite;vertical-align:middle;margin-left:4px;"></span>';
      } else if (status === 'done') {
        span.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="3" style="vertical-align:middle;margin-left:4px;"><polyline points="20 6 9 17 4 12"/></svg>';
      } else {
        span.innerHTML = status === 'error' ? ' ⚠️' : '';
      }
    }
  }
  
  function updateBadges() {
    const ub = document.getElementById('aiseo-url-badge');
    const tb = document.getElementById('aiseo-type-badge');
    if (ub) { ub.textContent = state.targetUrl ? '🌐 ' + state.targetUrl : ''; ub.style.display = state.targetUrl ? 'inline-flex' : 'none'; }
    if (tb) { tb.textContent = state.siteType  ? '📌 ' + state.siteType  : ''; tb.style.display = state.siteType  ? 'inline-flex' : 'none'; }
  }
  
  function updateRunAllBtn() {
    const btn = document.getElementById('btn-run-all-services');
    if (!btn) return;
    const all  = getAllServices();
    const left = all.filter(s => !state.completedServices.has(s.id)).length;
    if (left === 0 && all.length > 0) {
      btn.textContent = '✅ Tüm Hizmetler Tamamlandı';
      btn.disabled = true; btn.style.background = '#64748b'; btn.style.cursor = 'default';
    } else {
      btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;flex-shrink:0;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>Tüm Hizmetleri Yap${left < all.length ? ' (' + left + ' kalan)' : ''}`;
      btn.disabled = false; btn.style.background = '#10b981'; btn.style.cursor = 'pointer';
    }
  }
  
  function updateDownloadBtn() {
    const btn = document.getElementById('btn-download-pdf');
    if (!btn) return;
    const all    = getAllServices();
    const allDone = all.length > 0 && all.every(s => state.completedServices.has(s.id));
    btn.disabled  = !allDone;
    btn.style.opacity = allDone ? '1' : '0.5';
    btn.style.cursor  = allDone ? 'pointer' : 'not-allowed';
    btn.title = allDone ? 'PDF Rapor İndir' : 'Tüm hizmetler tamamlandığında aktif olur';
    btn.onclick = allDone ? downloadPDF : null;
  }
  
  // ============================================================
  // ACCORDION
  // ============================================================
  
  function renderIdleAccordions(force = false) {
  const area = document.getElementById('aiseo-results-area');
  if (!area) return;
  if (force) area.innerHTML = '';
  
  const all = getAllServices();
  all.forEach(svc => {
    const domId = 'aiseo-accordion-' + svc.id;
    let wrap = document.getElementById(domId);
    if (!wrap) {
       wrap = document.createElement('div');
       wrap.id = domId;
       wrap.className = 'aiseo-accordion';
       wrap.style.cssText = 'margin-bottom:12px; border:1px solid #E2E8F0; border-radius:8px; background:#fff; overflow:hidden;';
       
       wrap.innerHTML = `
         <div class="acc-header" onclick="window.handleAccClick(event, '${svc.id}')" style="padding:16px 20px; display:flex; align-items:center; justify-content:space-between; cursor:pointer; background:#fff; transition:background 0.2s;">
            <div>
               <h3 style="margin:0; font-size:15px; font-weight:700; color:#1F1D30; display:flex; align-items:center; gap:8px;">
                  <span style="font-size:18px;">${svc.icon}</span> ${svc.title}
               </h3>
               <p style="margin:4px 0 0 26px; font-size:12px; color:#64748B;">${svc.description}</p>
            </div>
            <div class="acc-status-indicator">
               <span style="display:inline-block; padding:4px 8px; font-size:12px; font-weight:600; color:#64748B; background:#F1F5F9; border-radius:4px;">▶</span>
            </div>
         </div>
         <div class="acc-body" style="display:none; padding:0 20px 20px 20px; border-top:1px solid #E2E8F0;">
         </div>
       `;
       area.appendChild(wrap);
    }
  });
}

function renderServiceAccordion(service, status, htmlContent, errorMsg) {
    htmlContent = htmlContent || '';
    errorMsg    = errorMsg    || '';

    try {
      // Panel + results area görünür olsun
      const panel = document.getElementById('aiseo-services-panel');
      if (panel) panel.style.display = 'block';

      const area = document.getElementById('aiseo-results-area');
      if (!area) { console.error('[AI SEO] aiseo-results-area DOM\'da bulunamadı — accordion çizilemedi.'); return; }

      const domId = 'aiseo-accordion-' + service.id;
      let wrap = document.getElementById(domId);
      if (!wrap) {
        wrap = document.createElement('div');
        wrap.id        = domId;
        wrap.className = 'aiseo-accordion';
      }
      if (wrap.parentElement !== area) {
        area.appendChild(wrap);
        setTimeout(() => wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 200);
      }

      const colors = {
        done:    { border: '#10b981', bg: '#f0fdf4', bodyBorder: '#d1fae5' },
        loading: { border: '#f59e0b', bg: '#fffbeb', bodyBorder: '#fde68a' },
        error:   { border: '#ef4444', bg: '#fef2f2', bodyBorder: '#fecaca' }
      };
      const c = colors[status] || colors.loading;

      const icon = status === 'done'
        ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`
        : status === 'loading'
          ? `<span style="display:inline-block;width:14px;height:14px;border:2px solid #f59e0b;border-top-color:transparent;border-radius:50%;animation:ag-spin .8s linear infinite;"></span>`
          : `<span style="font-size:14px;">⚠️</span>`;

      let body = '';
      if (status === 'loading') {
        body = `<div style="padding:20px 24px;display:flex;align-items:center;gap:12px;color:#64748b;border-top:1px solid ${c.bodyBorder};">
          <div class="typing-indicator" style="padding:0;flex-shrink:0;"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>
          <span style="font-size:13px;">Yapay zeka analiz yapıyor, lütfen bekleyin...</span>
        </div>`;
      } else if (status === 'error') {
        body = `<div style="padding:20px 24px;color:#dc2626;font-size:13px;border-top:1px solid ${c.bodyBorder};">
          <strong>⚠️ Hata:</strong> ${errorMsg || 'Bilinmeyen bir hata oluştu.'}<br><br>
          <button onclick="window.runSingleService(${JSON.stringify(service.id)})" style="background:#ef4444;color:#fff;border:none;border-radius:8px;padding:7px 16px;font-size:12px;font-weight:600;cursor:pointer;">🔄 Tekrar Dene</button>
        </div>`;
      } else if (!htmlContent || !htmlContent.trim()) {
        // AI'dan boş cevap geldi — sessizce hiçbir şey göstermek yerine kullanıcıyı bilgilendir
        console.warn('[AI SEO] Servis ' + service.id + ' için AI cevabı boş geldi.');
        body = `<div style="padding:20px 24px;color:#92400e;font-size:13px;border-top:1px solid ${c.bodyBorder};background:#fffbeb;">
          <strong>⚠️ Boş cevap:</strong> Yapay zeka bu hizmet için içerik döndürmedi. Lütfen tekrar deneyin.<br><br>
          <button onclick="window.runSingleService(${JSON.stringify(service.id)})" style="background:#f59e0b;color:#fff;border:none;border-radius:8px;padding:7px 16px;font-size:12px;font-weight:600;cursor:pointer;">🔄 Tekrar Dene</button>
        </div>`;
      } else {
        // Parse the single HTML content into subtask blocks!
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = htmlContent || '';
        
        // Find all H3 elements and group content
        const chunks = {};
        let currentHeader = null;
        let currentContent = [];
        
        Array.from(tempDiv.children).forEach(child => {
            if (child.tagName.match(/^H[2-4]$/)) {
                if (currentHeader) {
                    chunks[currentHeader] = currentContent.map(el => el.outerHTML).join('');
                }
                currentHeader = child.innerText.trim().toLowerCase().replace(/[^a-z0-9ğüşıöç]/gi, '');
                currentContent = [];
            } else {
                if (currentHeader) {
                    currentContent.push(child);
                }
            }
        });
        if (currentHeader) {
            chunks[currentHeader] = currentContent.map(el => el.outerHTML).join('');
        }
        
        // Now build the accordion body using service.subtasks
        let subtasksHtml = '';
        if (Object.keys(chunks).length === 0) {
            // No H2/H3/H4 headers found. This might be an old history item or unformatted output.
            subtasksHtml = htmlContent;
        } else if (service.subtasks && service.subtasks.length > 0) {
            const activeSubtasks = service.subtasks.filter(st => st.selected !== false);
            activeSubtasks.forEach(st => {
                const title = st.title.trim();
                const normTitle = title.toLowerCase().replace(/[^a-z0-9ğüşıöç]/gi, '');
                const content = chunks[normTitle] || '<p style="color:#64748B;font-style:italic;">Bu başlık için içerik üretilmedi.</p>';
                subtasksHtml += `
                    <details style="border-bottom:1px solid #E2E8F0;">
                        <summary style="padding:16px 24px; display:flex; justify-content:space-between; align-items:center; background:#FAFAFA; cursor:pointer; list-style:none;">
                            <span style="font-weight:600; font-size:14px; color:#1F2937;">${title}</span>
                            <span style="color:#10B981; font-size:13px; font-weight:700;"> Tamamlandı</span>
                        </summary>
                        <div style="padding:20px 24px; font-size:14px; color:#374151; background:#fff; line-height:1.7;">
                            ${content}
                        </div>
                    </details>
                `;
            });
        } else {
            subtasksHtml = `<div style="padding:20px 24px 28px;font-size:13.5px;line-height:1.75;color:#334155;">${htmlContent}</div>`;
        }
        
        body = `<div class="aiseo-accordion-content" style="border-top:1px solid ${c.bodyBorder};">${subtasksHtml}</div>`;
      }

      const bodyOpen   = status !== 'error';
      const toggleRot  = bodyOpen ? 'rotate(180deg)' : '';

      wrap.innerHTML = `
        <div style="border:1px solid ${c.border};border-radius:8px;overflow:hidden;background:#fff;box-shadow:0 1px 4px rgba(31,29,48,0.05);">
          <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:${c.bg};cursor:pointer;user-select:none;gap:12px;"
               onclick="window.handleAccClick(event, ${JSON.stringify(service.id)})">
            <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
              <span style="font-size:20px;flex-shrink:0;">${service.icon}</span>
              <div style="min-width:0;">
                <div style="font-size:14px;font-weight:700;color:#1F1D30;">${service.title}</div>
                <div style="font-size:12px;color:#6B6E82;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${service.description}</div>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
              ${icon}
              <div id="aiseo-toggle-${service.id}" style="width:24px;height:24px;border-radius:50%;background:rgba(0,0,0,0.06);display:flex;align-items:center;justify-content:center;transition:transform .25s;transform:${toggleRot};">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
            </div>
          </div>
          <div id="aiseo-body-${service.id}" style="display:${bodyOpen ? 'block' : 'none'};">
            ${body}
          </div>
        </div>`;

      console.log('[AI SEO] Accordion çizildi → servis:', service.id, 'durum:', status, 'içerik uzunluğu:', htmlContent.length);

    } catch (renderErr) {
      // Accordion oluştururken beklenmedik bir hata olursa sessizce yutmak yerine konsola yaz
      console.error('[AI SEO] renderServiceAccordion HATASI:', renderErr, { serviceId: service && service.id, status });
    }
  }
  
  window.toggleAccordion = function(serviceId) {
    const body   = document.getElementById('aiseo-body-' + serviceId);
    const toggle = document.getElementById('aiseo-toggle-' + serviceId);
    if (!body) return;
    const open = body.style.display !== 'none';
    body.style.display          = open ? 'none' : 'block';
    if (toggle) toggle.style.transform = open ? '' : 'rotate(180deg)';
  };
  
  // ============================================================
  // HİZMET ÇALIŞTIR
  // ============================================================
  
  let accClickTimers = {};
  window.handleAccClick = function(e, id) {
     if (!state.completedServices.has(id) && !state.completedServices.has('loading_' + id)) {
         window.runSingleService(id);
         return;
     }
     const wrap = document.getElementById('aiseo-accordion-' + id);
     if (!wrap) return;
     const body = wrap.querySelector('.acc-body') || document.getElementById('aiseo-body-' + id);
     if (!body) return;
     
     if (e.detail === 1) {
         accClickTimers[id] = setTimeout(() => {
             const toggle = document.getElementById('aiseo-toggle-' + id);
             if (body.style.display === 'none' || body.style.display === '') {
                 body.style.display = 'block';
                 if (toggle) toggle.style.transform = 'rotate(180deg)';
             } else {
                 let anyOpen = false;
                 wrap.querySelectorAll('details').forEach(d => { if(d.open) anyOpen = true; });
                 if (anyOpen) { 
                     wrap.querySelectorAll('details').forEach(d => d.open = false); 
                 } else { 
                     body.style.display = 'none'; 
                     if (toggle) toggle.style.transform = '';
                 }
             }
         }, 250);
     } else if (e.detail === 2) {
         clearTimeout(accClickTimers[id]);
         const toggle = document.getElementById('aiseo-toggle-' + id);
         body.style.display = 'block';
         if (toggle) toggle.style.transform = 'rotate(180deg)';
         wrap.querySelectorAll('details').forEach(d => d.open = true);
     }
  };

  window.runSingleService = async function(rawId) {
    // id hem integer hem string olabilir — tip-güvenli karşılaştırma
    const serviceId = (typeof rawId === 'string' && !rawId.startsWith('custom_'))
      ? parseInt(rawId, 10) : rawId;
  
    const service = getAllServices().find(s => String(s.id) === String(serviceId));
    if (!service) {
      console.error('Hizmet bulunamadı:', serviceId, 'Mevcut:', getAllServices().map(s => s.id));
      return;
    }
    if (!state.fetchedData) {
      alert('Lütfen önce bir URL girin ve analiz ettirin.');
      return;
    }
  
    // Hali hazırda yükleniyorsa tekrar tetikleme
    const loadKey = 'loading_' + String(serviceId);
    if (state.completedServices.has(loadKey)) return;
    state.completedServices.add(loadKey);
  
    updateServiceButtonState(serviceId, 'loading');
    renderServiceAccordion(service, 'loading');
  
    try {
      const siteData = {
        url:         state.targetUrl,
        title:       state.fetchedData.title,
        description: state.fetchedData.description,
        text:        state.fetchedData.text,
        schemas:     state.fetchedData.schemas,
        siteType:    state.siteType
      };
  
      console.log('[AI SEO] Servis çalıştırılıyor:', serviceId, service.title);
      const aiText = await callGemini(buildPromptForService(service, siteData), 0.25);
      console.log('[AI SEO] AI cevabı alındı, uzunluk:', (aiText || '').length);
      let htmlContent = mdToHtml(aiText);
      
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = htmlContent;
      tempDiv.querySelectorAll('pre').forEach(pre => {
          const wrapper = document.createElement('div');
          wrapper.className = 'ai-code-block';
          pre.parentNode.insertBefore(wrapper, pre);
          wrapper.appendChild(pre);
          
          const copyBtn = document.createElement('button');
          copyBtn.className = 'ai-copy-btn';
          const iconCopy = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px; vertical-align:middle;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>`;
          const iconDone = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px; vertical-align:middle;"><polyline points="20 6 9 17 4 12"></polyline></svg>`;
          copyBtn.innerHTML = iconCopy + 'Kopyala';
          copyBtn.onclick = (e) => {
              e.stopPropagation();
              const codeEl = pre.querySelector('code');
              let codeText = '';
              if (codeEl) {
                  codeText = codeEl.innerText || codeEl.textContent;
              } else {
                  // clone pre and remove the button so we don't copy the button text
                  const clone = pre.cloneNode(true);
                  const btnInClone = clone.querySelector('button');
                  if(btnInClone) btnInClone.remove();
                  codeText = clone.innerText || clone.textContent;
              }
              
              // Fallback for clipboard API if not in secure context
              if (navigator.clipboard && window.isSecureContext) {
                  navigator.clipboard.writeText(codeText).then(() => {
                      copyBtn.innerHTML = iconDone + 'Kopyalandı';
                      copyBtn.style.backgroundColor = '#10b981'; copyBtn.style.color = '#fff';
                      setTimeout(() => { copyBtn.innerHTML = iconCopy + 'Kopyala'; copyBtn.style.backgroundColor = ''; copyBtn.style.color = ''; }, 2000);
                  }).catch(err => { console.error('Kopyalama başarısız:', err); });
              } else {
                  const textArea = document.createElement('textarea');
                  textArea.value = codeText;
                  textArea.style.position = 'fixed';
                  textArea.style.left = '-9999px';
                  document.body.appendChild(textArea);
                  textArea.focus();
                  textArea.select();
                  try {
                      document.execCommand('copy');
                      copyBtn.innerHTML = iconDone + 'Kopyalandı';
                      copyBtn.style.backgroundColor = '#10b981'; copyBtn.style.color = '#fff';
                      setTimeout(() => { copyBtn.innerHTML = iconCopy + 'Kopyala'; copyBtn.style.backgroundColor = ''; copyBtn.style.color = ''; }, 2000);
                  } catch (err) {
                      console.error('Kopyalama fallback başarısız:', err);
                  }
                  document.body.removeChild(textArea);
              }
          };
          pre.appendChild(copyBtn);
      });
      htmlContent = tempDiv.innerHTML;
      
      console.log('[AI SEO] Markdown → HTML dönüşümü tamam, uzunluk:', (htmlContent || '').length);
  
      state.completedServices.delete(loadKey);
      state.serviceResults[String(serviceId)] = { html: htmlContent, raw: aiText };
      state.completedServices.add(serviceId);
  
      renderServiceAccordion(service, 'done', htmlContent);
      updateServiceButtonState(serviceId, 'done');
      updateRunAllBtn();
      updateDownloadBtn();
      saveAnalysis().catch(e => console.warn('Kayıt hatası:', e));
  
    } catch (err) {
      state.completedServices.delete(loadKey);
      renderServiceAccordion(service, 'error', '', err.message);
      updateServiceButtonState(serviceId, 'error');
      console.error('[AI SEO] Hizmet hatası [' + serviceId + ']:', err);
    }
  };
  
  window.runAllServices = async function() {
    const btn = document.getElementById('btn-run-all-services');
    if (btn) { btn.disabled = true; btn.style.opacity = '0.8'; }
  
    for (const svc of getAllServices()) {
      if (!state.completedServices.has(svc.id)) {
        await window.runSingleService(svc.id);
        await new Promise(r => setTimeout(r, 4500)); // Dakikadaki 15 istek sınırına (429) takılmamak için bekleme süresi artırıldı
      }
    }
  
    if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
    updateRunAllBtn();
  };
  
  // ============================================================
  // PDF
  // ============================================================
  
  function downloadPDF() {
    if (typeof html2pdf === 'undefined') { alert('PDF kütüphanesi yüklenemedi.'); return; }
    const date = new Date().toLocaleDateString('tr-TR');
    let pages  = '';
    getAllServices().forEach(svc => {
      const res = state.serviceResults[String(svc.id)];
      pages += `<div style="padding:40px;min-height:1100px;background:#fff;page-break-after:always;position:relative;">
        <div style="border-bottom:3px solid #FBBA00;padding-bottom:14px;margin-bottom:24px;">
          <h2 style="font-size:20px;color:#1F1D30;margin:0;font-weight:800;">${svc.icon} ${svc.title}</h2>
          <p style="font-size:12px;color:#6B6E82;margin:5px 0 0;">${svc.description}</p>
        </div>
        <div style="font-size:13px;line-height:1.7;color:#334155;">${res ? res.html : '<p style="color:#94a3b8;">Analiz yapılmadı.</p>'}</div>
        <div style="position:absolute;bottom:18px;left:40px;right:40px;border-top:1px solid #e2e8f0;padding-top:8px;font-size:10px;color:#94a3b8;display:flex;justify-content:space-between;">
          <span>AdresGezgini — Yapay Zeka SEO Raporu</span><span>${date}</span>
        </div>
      </div>`;
    });
  
    const el = document.createElement('div');
    el.innerHTML = `<div style="font-family:\'Source Sans Pro\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;">
      <div style="height:1100px;display:flex;flex-direction:column;justify-content:center;align-items:center;background:linear-gradient(135deg,#312F4D,#1F1D30);color:#fff;text-align:center;padding:40px;page-break-after:always;">
        <div style="font-size:36px;font-weight:800;margin-bottom:14px;color:#FBBA00;">AdresGezgini</div>
        <div style="font-size:44px;font-weight:800;line-height:1.1;margin-bottom:24px;">YAPAY ZEKA<br>SEO RAPORU</div>
        <div style="background:rgba(255,255,255,.12);padding:10px 26px;border-radius:6px;font-size:15px;font-family:\'Source Sans Pro\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;margin-bottom:12px;">${state.targetUrl}</div>
        <div style="font-size:13px;opacity:.8;">Site Türü: ${state.siteType}</div>
        <div style="margin-top:auto;font-size:12px;opacity:.6;">${date}</div>
      </div>${pages}</div>`;
  
    html2pdf().set({ margin: 0, filename: 'AG_AI_SEO_Raporu.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2, useCORS: true }, jsPDF: { unit: 'px', format: [794, 1123], orientation: 'portrait' } }).from(el).save();
  }
  
  // ============================================================
  // URL FETCH + SİTE TÜRÜ TESPİTİ
  // ============================================================
  
  async function detectAndFetchSite(url) {
    const indicator = document.getElementById('aiseo-scan-indicator');
    const scanText  = document.getElementById('aiseo-scan-text');
    const submitBtn = document.getElementById('aiseo-url-submit');
  
    if (indicator) indicator.style.display = 'block';
    if (scanText)  scanText.textContent = 'Site verileri çekiliyor...';
    if (submitBtn) { submitBtn.disabled = true; submitBtn.style.opacity = '0.6'; }
  
    try {
      state.fetchedData = await fetchSiteData(url);
      if (scanText) scanText.textContent = 'Yapay zeka site türünü tespit ediyor...';
  
      const typeText = await callGemini(
        `Bu web sitesini analiz et ve sadece şu iki seçenekten birini yaz, başka HİÇBİR ŞEY EKLEME:\n- Ürün / E-Ticaret\n- Hizmet / Kurumsal\n\nURL: ${url}\nBaşlık: ${state.fetchedData.title}\nAçıklama: ${state.fetchedData.description}\nMetin (ilk 1000 karakter): ${state.fetchedData.text.substring(0, 1000)}`,
        0.1
      );
      state.siteType = typeText.trim().includes('Ürün') ? 'Ürün / E-Ticaret' : 'Hizmet / Kurumsal';
  
      updateBadges();
      if (indicator) indicator.style.display = 'none';
  
      document.getElementById('aiseo-url-card').style.display     = 'none';
      document.getElementById('aiseo-services-panel').style.display = 'block';
      
      const btnC = document.getElementById('btn-customize-services');
      if (btnC) btnC.style.display = 'inline-flex';
      const btnN = document.getElementById('btn-new-analysis');
      if (btnN) btnN.style.display = 'inline-flex';
      const btnP = document.getElementById('btn-download-pdf');
      if (btnP) btnP.style.display = 'inline-flex';
      
      renderIdleAccordions(true);
  
      updateRunAllBtn();
      updateDownloadBtn();
  
    } catch (err) {
      if (indicator) indicator.style.display = 'none';
      alert('Hata: ' + err.message);
    } finally {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.style.opacity = '1'; }
    }
  }
  
  // ============================================================
  // ÖZEL HİZMET EKLE (+ butonu)
  // ============================================================
  
  function renderCustomServiceButton(svc) {
    const container = document.getElementById('aiseo-service-buttons');
    if (!container) return;
  
    // Eski + butonunu çıkar
    const addBtn = document.getElementById('btn-add-custom-service');
    if (addBtn) addBtn.remove();
  
    const btn = document.createElement('button');
    btn.className = 'aiseo-svc-btn has-tooltip';
    btn.setAttribute('data-service', svc.id);
    btn.setAttribute('data-tooltip', svc.description);
    btn.style.cssText = 'display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid #FBBA00;border-radius:6px;padding:8px 16px;font-size:13px;font-weight:500;color:#312F4D;cursor:pointer;transition:all .15s;';
    btn.innerHTML = `<span>${svc.icon}</span> ${svc.title}<span class="svc-status" data-svc="${svc.id}"></span>`;
    btn.addEventListener('click', () => window.runSingleService(svc.id));
    container.appendChild(btn);
  
    appendMainAddButton();
  }
  
  function appendMainAddButton() {
    const container = document.getElementById('aiseo-service-buttons');
    if (!container) return;
  
    const old = document.getElementById('btn-add-custom-service');
    if (old) old.remove();
  
    const btn = document.createElement('button');
    btn.id    = 'btn-add-custom-service';
    btn.title = 'Özel hizmet ekle';
    btn.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;background:#fff;border:1.5px dashed #9A9DAE;border-radius:6px;cursor:pointer;font-size:20px;color:#6B6E82;transition:all .15s;flex-shrink:0;line-height:1;';
    btn.textContent = '+';
    btn.addEventListener('mouseenter', () => { btn.style.borderColor='#FBBA00'; btn.style.borderStyle='solid'; btn.style.color='#312F4D'; btn.style.background='#FFF6DF'; });
    btn.addEventListener('mouseleave', () => { btn.style.borderColor='#9A9DAE'; btn.style.borderStyle='dashed'; btn.style.color='#6B6E82'; btn.style.background='#fff'; });
    btn.addEventListener('click', openAddCustomServiceModal);
    container.appendChild(btn);
  }
  


function openAddCustomServiceModal() {
    document.getElementById('custom-svc-modal')?.remove();
  
    const modal = document.createElement('div');
    modal.id = 'custom-svc-modal';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:100000;display:flex;align-items:center;justify-content:center;padding:20px;';
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
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:100000;display:flex;align-items:center;justify-content:center;padding:20px;';
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
    
    // Üst butonları görünür yap (Geçmişten gelince de çıksınlar)
    const btnC = document.getElementById('btn-customize-services');
    if (btnC) btnC.style.display = 'inline-flex';
    const btnN = document.getElementById('btn-new-analysis');
    if (btnN) btnN.style.display = 'inline-flex';
    const btnP = document.getElementById('btn-download-pdf');
    if (btnP) btnP.style.display = 'inline-flex';
      
    // Setup Customization Toggle
      

      
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
    
    // URL ekraniysa dugmeleri gizle
    if (showUrlCard) {
       document.getElementById('btn-customize-services').style.display = 'none';
       document.getElementById('btn-new-analysis').style.display = 'none';
       document.getElementById('btn-download-pdf').style.display = 'none';
    }
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
  
    const custBtn = document.getElementById('btn-customize-services');
    if (custBtn) {
        custBtn.onclick = () => {
            const modal = document.getElementById('customization-modal');
            if(modal) {
               modal.style.display = 'flex';
            }
        };
    }
  
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

