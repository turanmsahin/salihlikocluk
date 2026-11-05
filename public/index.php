<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$pages = [
    'home' => ['title' => 'Salihli YKS Öğrenci Koçu', 'description' => 'Salihli’de TYT, AYT ve mezun öğrencilere yönelik kişiye özel YKS koçluğu. Koçluk sürecini ve ön görüşme seçeneklerini inceleyin.'],
    'yks' => ['title' => 'Salihli YKS Koçluğu', 'description' => 'Hedeflerinize ve mevcut çalışma düzeninize göre planlanan Salihli YKS öğrenci koçluğu sürecini keşfedin.'],
    'tyt' => ['title' => 'Salihli TYT Koçluğu', 'description' => 'TYT hazırlığında çalışma düzeni, konu takibi ve deneme değerlendirmesi için kişiye özel öğrenci koçluğu.'],
    'ayt' => ['title' => 'Salihli AYT Koçluğu', 'description' => 'AYT hedeflerinize göre şekillenen, sürdürülebilir ve düzenli takip edilen çalışma yaklaşımı.'],
    'lgs' => ['title' => 'Salihli LGS Öğrenci Koçluğu | 8. Sınıf Sınav Koçluğu', 'description' => 'Salihli’de 8. sınıf öğrencilerine yönelik birebir LGS koçluğu, haftalık ders çalışma programı, deneme analizi ve nitelikli lise hazırlık takibi.'],
    'mezun' => ['title' => 'Salihli Mezun Öğrenci Koçluğu', 'description' => 'Mezun öğrenciler için hedef, çalışma düzeni ve gelişim takibini içeren YKS koçluğu.'],
    'yazilim' => ['title' => 'Veriye Dayalı Öğrenci Koçluğu | Testroom Yetenek Analizi', 'description' => 'Testroom yapay zekâ destekli yetenek analizi, rehberlik testleri ve veriye dayalı Salihli öğrenci koçluğu yaklaşımı.'],
    'veli' => ['title' => 'YKS Sürecinde Veli Danışmanlığı', 'description' => 'Sınav hazırlığı sürecinde aile iletişimi ve öğrenciye destek yaklaşımı hakkında bilgi alın.'],
    'surec' => ['title' => 'Koçluk Süreci ve Ücretler', 'description' => 'Salihli YKS koçluğu görüşme, takip ve ücretlendirme süreci hakkında bilgi alın.'],
    'hakkimda' => ['title' => 'Hakkımda | ' . setting('coach_name', 'Salihli YKS Koçu'), 'description' => 'Salihli’de sunulan öğrenci koçluğu yaklaşımını, koçun eğitimini ve çalışma sürecini tanıyın.'],
    'gizlilik' => ['title' => 'Gizlilik ve Kişisel Veriler', 'description' => 'Ön görüşme formunda paylaşılan kişisel verilerin kullanımı hakkında bilgi alın.'],
    'iletisim' => ['title' => 'İletişim ve Ön Görüşme', 'description' => 'Salihli YKS koçluğu için ön görüşme talebi iletin.'],
];

// Clean / Pretty URL Routing
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = trim(parse_url($requestUri, PHP_URL_PATH) ?? '', '/');

$page = $_GET['page'] ?? '';
if ($page === '') {
    if ($requestPath === '' || $requestPath === 'index.php') {
        $page = 'home';
    } elseif ($requestPath === 'blog') {
        $page = 'blog';
    } elseif (str_starts_with($requestPath, 'blog/')) {
        $page = 'yazi';
        $_GET['slug'] = substr($requestPath, 5);
    } elseif (str_starts_with($requestPath, 'yazi/')) {
        $page = 'yazi';
        $_GET['slug'] = substr($requestPath, 5);
    } elseif (isset($pages[$requestPath])) {
        $page = $requestPath;
    } else {
        $page = '404';
    }
}

$post = null;
$posts = [];
$meta = $pages[$page] ?? ($page === '404' ? ['title' => 'Sayfa Bulunamadı', 'description' => 'Aradığınız sayfa bulunamadı.'] : $pages['home']);
$notice = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);

$categories = get_categories(true);
$currentCategorySlug = trim((string) ($_GET['kategori'] ?? ''));

if ($page === 'blog') {
    $activeCategory = null;
    if ($currentCategorySlug !== '') {
        foreach ($categories as $cat) {
            if ($cat['slug'] === $currentCategorySlug) {
                $activeCategory = $cat;
                break;
            }
        }
    }
    
    if ($activeCategory) {
        $meta = [
            'title' => $activeCategory['name'] . ' Rehberi | Salihli Koçluk',
            'description' => 'Salihli ' . $activeCategory['name'] . ' hazırlık süreci, verimli çalışma taktikleri ve uzman koçluk rehberleri.'
        ];
    } else {
        $meta = [
            'title' => 'Sınav Hazırlık & Rehberlik Blogu',
            'description' => 'Salihli YKS, LGS, TYT, AYT, deneme analizi, çalışma alışkanlıkları ve öğrenci koçluğu üzerine uzman rehber yazıları.'
        ];
    }
    $posts = get_posts(0, true, $currentCategorySlug);
} elseif ($page === 'yazi') {
    $slug = (string) ($_GET['slug'] ?? '');
    $query = "SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE p.slug = :slug";
    if (!is_admin()) {
        $query .= " AND p.status = 'published'";
    }
    $statement = $db->prepare($query);
    $statement->execute(['slug' => $slug]);
    $post = $statement->fetch() ?: null;
    if (!$post) {
        http_response_code(404);
        $page = '404';
        $meta = ['title' => 'Yazı bulunamadı', 'description' => 'Aradığınız içerik bulunamadı.'];
    } else {
        $meta = ['title' => $post['title'], 'description' => $post['excerpt']];
    }
}

if ($page === 'iletisim' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $notice = 'Form doğrulanamadı. Sayfayı yenileyip tekrar deneyin.';
    } elseif (!empty($_POST['website'] ?? '')) {
        $_SESSION['notice'] = 'Talebiniz alındı. En kısa sürede dönüş yapılacaktır.';
        header('Location: ?page=iletisim');
        exit;
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $level = trim((string) ($_POST['student_level'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        $allowedLevels = ['', 'YKS / 12. sınıf', 'YKS / Mezun', 'YKS / 11. sınıf', 'LGS / 8. sınıf', 'Ara Sınıf / Diğer', '11. sınıf', '12. sınıf', 'Mezun', 'Diğer'];

        if ($name === '' || $phone === '' || mb_strlen($name) > 120 || mb_strlen($phone) > 40
            || ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190))
            || mb_strlen($message) > 3000 || !in_array($level, $allowedLevels, true)
            || ($_POST['privacy_acknowledged'] ?? '') !== '1') {
            http_response_code(422);
            $notice = 'Lütfen ad, telefon ve gizlilik bilgilendirmesi alanlarını kontrol edin.';
        } else {
            $statement = $db->prepare('INSERT INTO leads (name, phone, email, student_level, message, privacy_acknowledged) VALUES (:name, :phone, :email, :level, :message, 1)');
            $statement->execute([
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'level' => $level,
                'message' => $message,
            ]);

            // Form Bildirim Sistemi (E-posta, Telegram, Webhook)
            send_lead_notifications([
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'student_level' => $level,
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $_SESSION['notice'] = 'Ön görüşme talebiniz alındı. Koçumuz en kısa sürede sizinle iletişime geçecektir.';
            header('Location: /iletisim');
            exit;
        }
    }
}

$defaultContents = [
    'yks' => [
        'eyebrow' => 'YKS ÖĞRENCİ KOÇLUĞU',
        'heading' => 'Hedefine giden yolu<br><span>birlikte planlayalım.</span>',
        'intro' => 'Salihli YKS koçluğu; öğrencinin hedeflerini, mevcut durumunu ve ihtiyaçlarını anlamakla başlar. Süreç boyunca çalışma planı ve gelişim düzenli olarak gözden geçirilir.',
        'sections' => [
            ['YKS koçluğu kimler için?', 'TYT ve AYT hazırlığını daha planlı yürütmek isteyen, çalışma düzenini takip etmek ve hedeflerini somut adımlara dönüştürmek isteyen öğrenciler için.'],
            ['Süreç nasıl ilerler?', 'İlk görüşmede öğrenci ve veli beklentileri konuşulur. Uygunluk değerlendirmesinden sonra hedefler, iletişim düzeni ve çalışma adımları belirlenir.'],
            ['TYT ve AYT birlikte', 'Öğrencinin sınav takvimi ve ihtiyaçlarına göre TYT ve AYT çalışmaları bütüncül biçimde ele alınır.'],
        ],
        'cta' => 'Ön görüşme talebinde bulunarak çalışma planınızı başlatabilirsiniz.'
    ],
    'tyt' => [
        'eyebrow' => 'TYT KOÇLUĞU',
        'heading' => 'TYT hazırlığında<br><span>düzenli ilerleme.</span>',
        'intro' => 'Salihli TYT koçluğu sürecinde öğrencinin çalışma düzeni ve hedefleri birlikte değerlendirilir; uygulanabilir adımlar ve takip ritmi belirlenir.',
        'sections' => [
            ['Konu ve çalışma takibi', 'Öğrencinin planına göre çalışma adımları belirlenir ve süreçte hangi konuların önceliklendirileceği gözden geçirilir.'],
            ['Deneme değerlendirmesi', 'Deneme sonuçları tek başına başarı ölçütü olarak değil, öğrencinin gelişim alanlarını konuşmak ve planı gözden geçirmek için kullanılır.'],
            ['Sürdürülebilir tempo', 'Programın öğrencinin günlük yaşamına uyum sağlaması ve devam ettirilebilir olması önemsenir.'],
        ],
        'cta' => ''
    ],
    'ayt' => [
        'eyebrow' => 'AYT KOÇLUĞU',
        'heading' => 'AYT hedeflerine<br><span>odaklı çalışma.</span>',
        'intro' => 'Salihli AYT koçluğu, öğrencinin alanı ve hedefleri doğrultusunda çalışma önceliklerini belirlemeye yardımcı olur.',
        'sections' => [
            ['Hedefe göre önceliklendirme', 'Öğrencinin hedefi, okul ve sınav takvimi değerlendirilerek çalışma başlıkları birlikte belirlenir.'],
            ['TYT ile dengeli hazırlık', 'AYT çalışmaları, öğrencinin TYT ihtiyaçları ve genel sınav hazırlığıyla uyumlu biçimde planlanır.'],
            ['Düzenli gözden geçirme', 'İlerleme ve ihtiyaçlar görüşmelerde ele alınır; plan gerekli oldukça güncellenir.'],
        ],
        'cta' => ''
    ],
    'lgs' => [
        'eyebrow' => 'LGS & 8. SINIF KOÇLUĞU',
        'heading' => 'Nitelikli lise hedefine<br><span>disiplinli adımlarla ilerleyin.</span>',
        'intro' => 'Salihli merkez ofisimizde 8. sınıf LGS öğrencilerine yönelik birebir sınav koçluğu, haftalık ders çalışma takvimi, yeni nesil soru çözme disiplini ve sınav kaygısı yönetimi.',
        'sections' => [
            ['Haftalık LGS Çalışma Programı Hazırlama', 'Öğrencinin okul saatlerine ve deneme eksiklerine göre gün gün somut soru hedefleri çıkarılır; dershane veya özel ders temposuyla dengelenir.'],
            ['Yeni Nesil Beceri Temelli Soru Takibi', 'LGS formatındaki mantık ve muhakeme sorularına yönelik stratejik analizler yapılır, net artışı hafta hafta ölçülür.'],
            ['Sınav Kaygısı ve Motivasyon Yönetimi', '8. sınıf öğrencilerinin sınav stresini doğru yönetmesi ve masanın başında odaklanma süresini artırması için pedagojik koçluk desteği sağlanır.'],
            ['Periyodik Veli Danışmanlığı', 'Öğrencinin haftalık gelişim karnesi ve deneme analizleri düzenli olarak aile ile paylaşılır; ev içi huzurlu çalışma ortamı desteklenir.'],
        ],
        'cta' => 'Salihli ofisimizde LGS hedeflerinizi konuşmak ve süreci planlamak için ücretsiz ön görüşme randevusu alabilirsiniz.'
    ],
    'mezun' => [
        'eyebrow' => 'MEZUN ÖĞRENCİ KOÇLUĞU',
        'heading' => 'Yeni döneme<br><span>sağlam bir düzenle başla.</span>',
        'intro' => 'Salihli mezun öğrenci koçluğu, önceki hazırlık sürecinden öğrenilenleri değerlendirip yeni dönem için gerçekçi bir çalışma düzeni kurmaya odaklanır.',
        'sections' => [
            ['Geçmiş deneyimi değerlendirme', 'Önceki sınav süreci, çalışma alışkanlıkları ve yeni dönem beklentileri birlikte konuşulur.'],
            ['Kişiye uygun plan', 'Öğrencinin günlük koşulları ve hedefleri gözetilerek takip edilebilir adımlar belirlenir.'],
            ['Süreç boyunca iletişim', 'Çalışma düzeni ve karşılaşılan güçlükler görüşmelerde ele alınarak planın uygulanabilirliği korunur.'],
        ],
        'cta' => ''
    ],
    'yazilim' => [
        'eyebrow' => 'YAZILIM VE YAPAY ZEKÂ DESTEKLİ KOÇLUK',
        'heading' => 'Teknoloji destek olur;<br><span>rehberlik insanda kalır.</span>',
        'intro' => 'Koçluk yaklaşımının merkezinde yazılım ve yapay zekâ desteği bulunur. Bu destek, koçun harici olarak kullandığı dijital eğitim ürünü üzerinden sağlanır; bu tanıtım sitesi o üründen bağımsızdır.',
        'sections' => [
            ['Dijital araçların rolü', 'Yapay zekâ özellikleri harici dijital ürün içinde koçluk sürecine destek amacıyla kullanılır. Ürünün güncel özellikleri ve kullanım biçimi, ön görüşmede açıkça anlatılır.'],
            ['Koçluk ilişkisinin merkezi', 'Öğrencinin ihtiyaçlarını anlama, iletişim kurma ve yönlendirme koçluk sürecinin temelidir. Yazılım ve yapay zekâ koçun kişisel rehberliğinin yerine geçmez.'],
            ['Bağımsız tanıtım sitesi', 'Bu site harici yazılıma entegre değildir; site üzerinden öğrenci takibi, çalışma programı veya yapay zekâ analizi sunulmaz.'],
        ],
        'cta' => ''
    ],
    'veli' => [
        'eyebrow' => 'VELİ DANIŞMANLIĞI',
        'heading' => 'Sınav sürecinde<br><span>aynı hedefe birlikte.</span>',
        'intro' => 'YKS hazırlığı öğrencinin yanında ailesini de etkiler. Veli iletişiminde öğrencinin sorumluluğunu destekleyen, açık ve gerçekçi bir yaklaşım önemlidir.',
        'sections' => [
            ['Beklentileri konuşmak', 'Öğrencinin hedefleri ve aile beklentileri yapıcı bir iletişimle ele alınır.'],
            ['Destekleyici ortam', 'Ders çalışma düzeni ve motivasyon konusunda öğrencinin yaşına ve ihtiyacına uygun destek yolları değerlendirilir.'],
            ['Koç-öğrenci-veli iletişimi', 'Görüşme ve bilgilendirme biçimi, öğrencinin mahremiyeti ve sürecin ihtiyaçları dikkate alınarak belirlenir.'],
        ],
        'cta' => ''
    ],
    'surec' => [
        'eyebrow' => 'KOÇLUK SÜRECİ',
        'heading' => 'Salihli ofisimizde tanışalım,<br><span>birlikte planlayalım.</span>',
        'intro' => 'Koçluk süreci Salihli’deki fiziksel ofisimizde yüz yüze tanışma görüşmesiyle başlar. Öğrencinin hedefleri ve durumu değerlendirildikten sonra çalışma çerçevesi netleştirilir.',
        'sections' => [
            ['1. Ofiste Yüz Yüze Ön Görüşme', 'Öğrenci ve velimizle Salihli ofisimizde bir araya gelerek mevcut ders durumunu, eksikleri ve hedefleri konuşuruz.'],
            ['2. Bireysel Yol Haritası ve Planlama', 'Öğrencinin alanına göre haftalık çalışma programı, kaynak takibi ve deneme takvimi oluşturulur.'],
            ['3. Yüz Yüze Takip & Hibrit İletişim', 'Haftalık düzenli ofis görüşmeleriyle ilerleme denetlenir; WhatsApp üzerinden hafta içi soru işaretleri ve günlük takip sürdürülür.'],
        ],
        'cta' => 'Salihli ofisimizde yüz yüze ön görüşme randevusu almak için WhatsApp veya form üzerinden iletişime geçebilirsiniz.'
    ],
    'hakkimda' => [
        'eyebrow' => 'HAKKIMDA',
        'heading' => e(setting('coach_name', 'Salihli YKS Koçu')) . '<br><span>' . e(setting('coach_title', 'Öğrenci Koçu')) . '</span>',
        'intro' => e(setting('coach_bio', 'Salihli’de TYT, AYT ve mezun öğrencilere yönelik kişiye özel YKS koçluğu ile hedeflere uygun, planlı ve sürdürülebilir bir çalışma disiplini sunuyorum.')),
        'sections' => [
            ['Eğitim ve Mesleki Deneyim', setting('coach_education', 'Resmi onaylı profesyonel öğrenci koçluğu sertifikası ve eğitim danışmanlığı arka planı.')],
            ['Koçluk Yaklaşımı ve İlkeler', setting('coach_approach', 'Her öğrencinin çalışma temposu ve hedefleri kendine özgüdür. Ofisimizde yüz yüze periyodik görüşmeler, düzenli hedef ve deneme takibi ile disiplinli ve sürdürülebilir bir hazırlık süreci yürütüyoruz.')],
            ['Çalışma Modeli ve Lokasyon', setting('working_model', 'Salihli’de Yüz Yüze (Fiziksel Ofis) & Hibrit') . (setting('location') ? ' — ' . setting('location') : '')],
        ],
        'cta' => 'Ön görüşme talebinizi ileterek koçluk sürecine ilk adımı atabilirsiniz.',
    ],
    'gizlilik' => [
        'eyebrow' => 'GİZLİLİK',
        'heading' => 'Kişisel bilgileriniz<br><span>özenle korunur.</span>',
        'intro' => 'Salihli Koçluk bünyesinde paylaştığınız kişisel verilerin korunması ve gizliliğiniz bizim için esastır.',
        'sections' => [
            ['Hangi bilgiler alınır?', 'Ön görüşme formu; ad soyad, telefon numarası, isteğe bağlı e-posta, öğrencinin sınıf/durum bilgisi ve talebinizi içeren mesajı alır. Mesaj alanına özel nitelikli kişisel veri (sağlık vb.) yazılmamalıdır.'],
            ['Kullanım amacı', 'Toplanan bilgiler, yalnızca ön görüşme talebinize dönüş yapmak ve koçluk süreciyle ilgili iletişimi sağlamak amacıyla site yönetiminde saklanır. Üçüncü taraflara veya harici yazılımlara aktarılmaz.'],
            ['Saklama ve silme', 'Ön görüşme talepleri, süreç tamamlandığında veya talep sahibi silinmesini istediğinde yönetim panelinden güvenli bir biçimde silinir.'],
            ['Veri sorumlusu ve iletişim', 'Veri Sorumlusu: ' . setting('coach_name', 'Salihli Koçluk') . '. Kişisel verilerinizle ilgili bilgi alma veya silinmesini talep etme hakkınızı ' . (setting('email') ?: 'iletisim@salihlikocluk.com') . ' e-posta adresi üzerinden kullanabilirsiniz.'],
        ],
        'cta' => ''
    ],
];

// Check if page has custom overrides in site_pages database
$pageContent = null;
if (isset($defaultContents[$page])) {
    $pageContent = get_page_content($page, $defaultContents[$page]);
    if (!empty($pageContent['title'])) {
        $meta['title'] = $pageContent['title'];
    }
    if (!empty($pageContent['description'])) {
        $meta['description'] = $pageContent['description'];
    }
}

$koclukPrograms = [
    [
        'title' => 'YKS Koçluğu',
        'desc' => '12. Sınıf TYT & AYT Tam Hazırlık',
        'href' => '/yks',
        'icon' => '🎓',
        'color' => '#2563eb'
    ],
    [
        'title' => 'TYT Koçluğu',
        'desc' => 'Temel Yeterlilik & Hız ve Zaman Yönetimi',
        'href' => '/tyt',
        'icon' => '📘',
        'color' => '#1d4ed8'
    ],
    [
        'title' => 'AYT Koçluğu',
        'desc' => 'Alan Yeterlilik & Branş Net Artırma',
        'href' => '/ayt',
        'icon' => '🎯',
        'color' => '#7c3aed'
    ],
    [
        'title' => 'LGS Koçluğu',
        'desc' => '8. Sınıf Liselere Geçiş & Nitelikli Liseler',
        'href' => '/lgs',
        'icon' => '🎒',
        'color' => '#059669'
    ],
    [
        'title' => 'Mezun Öğrenci Koçluğu',
        'desc' => 'Yeniden Hazırlananlar İçin Derece Stratejisi',
        'href' => '/mezun',
        'icon' => '⏳',
        'color' => '#ea580c'
    ],
];

$rehberlikLinks = [
    [
        'title' => 'Veriye Dayalı Koçluk',
        'desc' => 'Testroom & Yapay Zekâ Destekli Analizler',
        'href' => '/yazilim',
        'icon' => '⚡',
        'badge' => 'AI',
        'color' => '#ea580c'
    ],
    [
        'title' => 'Veli Danışmanlığı',
        'desc' => 'Aile İletişimi & Aylık Gelişim Takibi',
        'href' => '/veli',
        'icon' => '👨‍👩‍👧',
        'color' => '#db2777'
    ],
    [
        'title' => 'Koçluk Süreci & Takvim',
        'desc' => 'Haftalık Seanslar, Takip & Çalışma Modeli',
        'href' => '/surec',
        'icon' => '📋',
        'color' => '#0d9488'
    ],
];

$canonicalPath = $page === 'home' ? '/' : ($page === 'yazi' && $post ? '/blog/' . rawurlencode($post['slug']) : '/' . $page);
$canonicalUrl = 'https://salihlikocluk.com' . $canonicalPath;
$slides = ($page === 'home') ? get_slides(true) : [];

// Ziyaretçi ve sayfa kalma süresi analizi (Hafif & Asenkron)
$visitorLogId = record_page_visit($canonicalPath, $meta['title'] ?? 'Salihli Koçluk');

// Local SEO & Schema.org Setup
$serviceAreas = array_filter(array_map('trim', explode(',', setting('service_areas', 'Salihli, Ahmetli, Alaşehir, Kula, Sart, Köprübaşı'))));
$areaServedList = [];
foreach ($serviceAreas as $areaName) {
    $areaServedList[] = [
        '@type' => 'AdministrativeArea',
        'name' => $areaName,
    ];
}

$localBusinessSchema = [
    '@type' => ['EducationalOrganization', 'LocalBusiness'],
    '@id' => 'https://salihlikocluk.com/#localbusiness',
    'name' => 'Salihli YKS & LGS Öğrenci Koçluğu',
    'alternateName' => ['Salihli Koçluk', 'Salihli Sınav Koçluğu', 'Salihli YKS Koçu'],
    'url' => 'https://salihlikocluk.com/',
    'logo' => 'https://salihlikocluk.com/assets/img/logo.png',
    'image' => 'https://salihlikocluk.com/assets/img/hero.jpg',
    'description' => setting('seo_default_description', 'Salihli’de TYT, AYT ve mezun öğrencilere yönelik fiziksel ofisimizde yüz yüze ve hibrit YKS koçluğu.'),
    'telephone' => setting('phone', '+90 544 344 24 49'),
    'email' => setting('email', 'iletisim@salihlikocluk.com'),
    'priceRange' => '₺₺',
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => setting('office_address', 'Salihli Merkez, Manisa'),
        'addressLocality' => 'Salihli',
        'addressRegion' => 'Manisa',
        'postalCode' => '45300',
        'addressCountry' => 'TR',
    ],
    'geo' => [
        '@type' => 'GeoCoordinates',
        'latitude' => (float) setting('geo_latitude', '38.4819'),
        'longitude' => (float) setting('geo_longitude', '28.1345'),
    ],
    'hasMap' => setting('google_maps_url', 'https://maps.google.com/?q=Salihli,Manisa'),
    'openingHoursSpecification' => [
        [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            'opens' => '09:00',
            'closes' => '19:30',
        ]
    ],
    'areaServed' => $areaServedList,
    'founder' => [
        '@type' => 'Person',
        'name' => setting('coach_name', 'Salihli YKS Koçu'),
        'jobTitle' => setting('coach_title', 'YKS & Öğrenci Koçu'),
    ],
];

$breadcrumbsItems = [
    [
        '@type' => 'ListItem',
        'position' => 1,
        'name' => 'Ana Sayfa',
        'item' => 'https://salihlikocluk.com/'
    ]
];

if ($page === 'yazi' && $post) {
    $breadcrumbsItems[] = [
        '@type' => 'ListItem',
        'position' => 2,
        'name' => 'Rehber Blog',
        'item' => 'https://salihlikocluk.com/blog'
    ];
    $breadcrumbsItems[] = [
        '@type' => 'ListItem',
        'position' => 3,
        'name' => $post['title'],
        'item' => $canonicalUrl
    ];
} elseif ($page !== 'home') {
    $cleanTitle = explode('|', $meta['title'])[0];
    $breadcrumbsItems[] = [
        '@type' => 'ListItem',
        'position' => 2,
        'name' => trim($cleanTitle),
        'item' => $canonicalUrl
    ];
}

$schemaGraph = [$localBusinessSchema];

if ($page === 'yazi' && $post) {
    $schemaGraph[] = [
        '@type' => 'Article',
        'headline' => $post['title'],
        'description' => $post['excerpt'],
        'datePublished' => date('c', strtotime($post['created_at'])),
        'dateModified' => date('c', strtotime($post['updated_at'] ?? $post['created_at'])),
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $canonicalUrl
        ],
        'author' => [
            '@type' => 'Person',
            'name' => setting('coach_name', 'Salihli YKS Koçu'),
            'jobTitle' => setting('coach_title', 'YKS & Öğrenci Koçu')
        ],
        'publisher' => [
            '@type' => 'EducationalOrganization',
            'name' => 'Salihli Koçluk',
            'url' => 'https://salihlikocluk.com',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => 'https://salihlikocluk.com/assets/favicon.svg'
            ]
        ],
        'image' => !empty($post['image_url']) ? 'https://salihlikocluk.com' . $post['image_url'] : 'https://salihlikocluk.com/assets/favicon.svg'
    ];

    $articleFaqs = extract_article_faqs($post['content'] ?? '');
    if (!empty($articleFaqs)) {
        $faqEntities = [];
        foreach ($articleFaqs as $f) {
            $faqEntities[] = [
                '@type' => 'Question',
                'name' => $f['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $f['answer']
                ]
            ];
        }
        $schemaGraph[] = [
            '@type' => 'FAQPage',
            'mainEntity' => $faqEntities
        ];
    }
}

if (count($breadcrumbsItems) > 1) {
    $schemaGraph[] = [
        '@type' => 'BreadcrumbList',
        'itemListElement' => $breadcrumbsItems,
    ];
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($meta['title'] . (str_contains($meta['title'], '|') ? '' : setting('seo_title_suffix', ' | Salihli Koçluk'))) ?></title>
    <meta name="description" content="<?= e(!empty($meta['description']) ? $meta['description'] : setting('seo_default_description')) ?>">
    <?php if ($kw = setting('seo_keywords')): ?><meta name="keywords" content="<?= e($kw) ?>"><?php endif; ?>
    <meta name="theme-color" content="#dc2626">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta property="og:title" content="<?= e($meta['title']) ?>">
    <meta property="og:description" content="<?= e(!empty($meta['description']) ? $meta['description'] : setting('seo_default_description')) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:site_name" content="Salihli Koçluk">
    <meta property="og:locale" content="tr_TR">

    <!-- Local SEO Geo Meta Tags -->
    <meta name="geo.region" content="TR-45">
    <meta name="geo.placename" content="Salihli, Manisa">
    <meta name="geo.position" content="<?= e(setting('geo_latitude', '38.4819')) ?>;<?= e(setting('geo_longitude', '28.1345')) ?>">
    <meta name="ICBM" content="<?= e(setting('geo_latitude', '38.4819')) ?>, <?= e(setting('geo_longitude', '28.1345')) ?>">

    <!-- Schema.org Structured Data (JSON-LD) -->
    <script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@graph' => $schemaGraph
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>

    <?php if ($gVer = setting('seo_google_verification')): ?><meta name="google-site-verification" content="<?= e($gVer) ?>"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/site.css">
    <?php if ($gTag = setting('seo_analytics_id')): ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($gTag) ?>"></script>
        <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($gTag) ?>');</script>
    <?php endif; ?>
</head>
<body>
<!-- KATMAN 1: Üst Bilgi & Hızlı Erişim Barı (Top Utility Bar) -->
<div class="top-bar">
    <div class="top-bar-inner">
        <div class="top-bar-left">
            <a href="/yazilim" class="top-pill top-pill-ai" title="Yapay Zekâ ve Testroom Destekli Koçluk">
                <span class="pill-code">&lt;&gt;</span>
                <span>Yapay Zekâ Testroom</span>
                <span class="pill-badge-tag">AI</span>
            </a>
            <a href="/iletisim" class="top-pill top-pill-geo" title="Salihli Merkez Ofisimizde Yüz Yüze Görüşme">
                <span>📍 Salihli Ofis (Yüz Yüze)</span>
            </a>
            <span class="top-bar-divider" aria-hidden="true">|</span>
            <div class="top-bar-links">
                <a href="/blog" class="<?= $page === 'blog' ? 'is-active' : '' ?>">📰 Rehber Blog</a>
                <?php if ($instaUrl = get_instagram_url()): ?>
                    <a href="<?= e($instaUrl) ?>" target="_blank" rel="noopener" class="top-link-instagram" title="Salihli Koçluk Instagram">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                        <span>Instagram</span>
                    </a>
                <?php endif; ?>
                <a href="/iletisim" class="<?= $page === 'iletisim' ? 'is-active' : '' ?>">💬 İletişim</a>
            </div>
        </div>
        <div class="top-bar-right">
            <?php if ($email = setting('email')): ?>
                <a href="mailto:<?= e($email) ?>" class="top-contact-link" title="E-Posta Gönder">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                    <span><?= e($email) ?></span>
                </a>
            <?php endif; ?>
            <?php if ($phone = setting('phone')): ?>
                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>" class="top-contact-link" title="Telefonla Ara">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                    <span><?= e($phone) ?></span>
                </a>
            <?php endif; ?>
            <?php if ($wa = (setting('whatsapp') ?: setting('phone'))): ?>
                <?php $waNum = preg_replace('/[^0-9]/', '', $wa); ?>
                <?php if ($waNum): ?>
                    <a href="https://wa.me/<?= e($waNum) ?>" target="_blank" rel="noopener" class="top-contact-link top-contact-wa" title="WhatsApp Mesaj Gönder">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm.02 18.06c-1.49 0-2.95-.4-4.22-1.16l-.3-.18-3.13.82.84-3.05-.2-.31a7.9 7.9 0 0 1-1.22-4.27c0-4.41 3.59-8 8.02-8a7.95 7.95 0 0 1 5.67 2.35 7.94 7.94 0 0 1 2.35 5.66c0 4.42-3.59 8.01-8.03 8.01zm4.39-6c-.24-.12-1.42-.7-1.64-.78-.22-.08-.38-.12-.55.12-.16.24-.63.78-.77.94-.14.16-.29.18-.53.06-.24-.12-1.02-.38-1.95-1.2-.72-.64-1.2-1.44-1.34-1.68-.14-.24-.02-.37.1-.49.11-.11.24-.29.36-.43.12-.14.16-.24.24-.4.08-.16.04-.31-.02-.43s-.55-1.33-.76-1.82c-.2-.48-.41-.41-.56-.42l-.48-.01c-.16 0-.43.06-.66.31-.22.24-.87.85-.87 2.08s.89 2.41 1.01 2.58c.12.16 1.76 2.68 4.25 3.76.59.26 1.06.41 1.42.53.6.19 1.14.16 1.57.1.48-.07 1.48-.6 1.69-1.19.21-.58.21-1.08.15-1.19-.06-.11-.22-.18-.46-.3z"/></svg>
                        <span>WhatsApp</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- KATMAN 2: Ana Menü Katmanı (Main Navbar) -->
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="/" aria-label="Salihli Koçluk ana sayfa">
            <span class="brand-mark">S</span>
            <div class="brand-text-wrap">
                <span class="brand-title">Salihli<span class="brand-light">Koçluk</span></span>
                <span class="brand-subtitle">YKS &amp; LGS Danışmanlığı</span>
            </div>
        </a>

        <!-- Desktop Dropdown Navigation (Karmaşadan Uzak & 3D Renkli Estetik) -->
        <nav class="main-nav desktop-nav" aria-label="Ana menü">
            <!-- Dropdown 1: Sınav Koçluğu -->
            <div class="nav-item has-dropdown">
                <?php 
                    $isKoclukActive = in_array($page, ['yks', 'tyt', 'ayt', 'lgs', 'mezun'], true);
                ?>
                <button type="button" class="nav-link nav-3d-btn nav-pill-kocluk dropdown-toggle <?= $isKoclukActive ? 'is-active' : '' ?>" aria-expanded="false">
                    <span class="nav-icon-3d">🎯</span>
                    <span>Sınav Koçluğu</span>
                    <span class="dropdown-chevron">▾</span>
                </button>
                <div class="dropdown-menu">
                    <?php foreach ($koclukPrograms as $item): ?>
                        <a href="<?= e($item['href']) ?>" class="dropdown-item <?= ($canonicalPath === $item['href']) ? 'is-active-item' : '' ?>">
                            <span class="dropdown-icon" style="background: <?= e($item['color']) ?>15; color: <?= e($item['color']) ?>;">
                                <?= $item['icon'] ?>
                            </span>
                            <div class="dropdown-item-copy">
                                <strong><?= e($item['title']) ?></strong>
                                <small><?= e($item['desc']) ?></small>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Dropdown 2: Yöntem & Rehberlik -->
            <div class="nav-item has-dropdown">
                <?php 
                    $isRehberlikActive = in_array($page, ['yazilim', 'veli', 'surec'], true);
                ?>
                <button type="button" class="nav-link nav-3d-btn nav-pill-yontem dropdown-toggle <?= $isRehberlikActive ? 'is-active' : '' ?>" aria-expanded="false">
                    <span class="nav-icon-3d">⚡</span>
                    <span>Yöntem &amp; Rehberlik</span>
                    <span class="dropdown-chevron">▾</span>
                </button>
                <div class="dropdown-menu">
                    <?php foreach ($rehberlikLinks as $item): ?>
                        <a href="<?= e($item['href']) ?>" class="dropdown-item <?= ($canonicalPath === $item['href']) ? 'is-active-item' : '' ?>">
                            <span class="dropdown-icon" style="background: <?= e($item['color']) ?>15; color: <?= e($item['color']) ?>;">
                                <?= $item['icon'] ?>
                            </span>
                            <div class="dropdown-item-copy">
                                <strong>
                                    <?= e($item['title']) ?>
                                    <?php if (!empty($item['badge'])): ?>
                                        <span class="item-badge-pill"><?= e($item['badge']) ?></span>
                                    <?php endif; ?>
                                </strong>
                                <small><?= e($item['desc']) ?></small>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <a class="nav-link nav-3d-btn nav-pill-blog <?= $page === 'blog' || $page === 'yazi' ? 'is-active' : '' ?>" href="/blog">
                <span class="nav-icon-3d">📚</span>
                <span>Rehber Blog</span>
            </a>
            <a class="nav-link nav-3d-btn nav-pill-hakkimda <?= $page === 'hakkimda' ? 'is-active' : '' ?>" href="/hakkimda">
                <span class="nav-icon-3d">👤</span>
                <span>Hakkımda</span>
            </a>
            <a class="nav-link nav-3d-btn nav-pill-iletisim <?= $page === 'iletisim' ? 'is-active' : '' ?>" href="/iletisim">
                <span class="nav-icon-3d">📍</span>
                <span>İletişim</span>
            </a>
        </nav>

        <div class="header-right-actions">
            <a class="nav-cta-btn" href="/iletisim">
                <span>Ön Görüşme Randevusu</span>
                <span class="btn-arrow">→</span>
            </a>

            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-nav" aria-label="Menüyü Aç/Kapat">
                <span class="menu-icon-bars" aria-hidden="true">
                    <span></span><span></span><span></span>
                </span>
                <span class="menu-text">Menü</span>
            </button>
        </div>
    </div>
</header>

<!-- Mobile Navigation Drawer -->
<div id="mobile-nav-backdrop" class="mobile-nav-backdrop"></div>
<nav id="mobile-nav" class="mobile-nav-drawer" aria-label="Mobil ana menü">
    <div class="mobile-nav-header">
        <span class="mobile-nav-title">Salihli Öğrenci Koçluğu</span>
        <button class="mobile-nav-close" type="button" aria-label="Menüyü Kapat">✕</button>
    </div>

    <div class="mobile-nav-scroll">
        <!-- Grup 1: Sınav Koçluğu -->
        <div class="mobile-nav-group">
            <span class="mobile-group-title">🎯 Sınav Koçluğu Programları</span>
            <div class="mobile-group-list">
                <?php foreach ($koclukPrograms as $item): ?>
                    <a href="<?= e($item['href']) ?>" class="mobile-nav-item">
                        <span class="mobile-icon" style="background: <?= e($item['color']) ?>15; color: <?= e($item['color']) ?>;"><?= $item['icon'] ?></span>
                        <div class="mobile-item-copy">
                            <strong><?= e($item['title']) ?></strong>
                            <small><?= e($item['desc']) ?></small>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Grup 2: Yöntem & Rehberlik -->
        <div class="mobile-nav-group">
            <span class="mobile-group-title">⚡ Yöntem &amp; Rehberlik</span>
            <div class="mobile-group-list">
                <?php foreach ($rehberlikLinks as $item): ?>
                    <a href="<?= e($item['href']) ?>" class="mobile-nav-item">
                        <span class="mobile-icon" style="background: <?= e($item['color']) ?>15; color: <?= e($item['color']) ?>;"><?= $item['icon'] ?></span>
                        <div class="mobile-item-copy">
                            <strong>
                                <?= e($item['title']) ?>
                                <?php if (!empty($item['badge'])): ?>
                                    <span class="item-badge-pill"><?= e($item['badge']) ?></span>
                                <?php endif; ?>
                            </strong>
                            <small><?= e($item['desc']) ?></small>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Grup 3: Sayfalar -->
        <div class="mobile-nav-group">
            <span class="mobile-group-title">📌 Hızlı Erişim</span>
            <div class="mobile-flat-links">
                <a href="/blog" class="mobile-flat-link mobile-pill-blog">📚 Rehber Blog</a>
                <a href="/hakkimda" class="mobile-flat-link mobile-pill-about">👤 Hakkımda</a>
                <a href="/iletisim" class="mobile-flat-link mobile-pill-contact">📍 İletişim &amp; Ofis</a>
            </div>
        </div>

        <!-- Mobil Butonlar -->
        <div class="mobile-nav-cta-wrap">
            <a class="button button-primary" style="width: 100%; text-align: center; justify-content: center;" href="/iletisim">Ön Görüşme Randevusu Al →</a>
            <?php if (!empty($waNum)): ?>
                <a class="button button-whatsapp" style="width: 100%; text-align: center; justify-content: center; margin-top: 8px;" href="https://wa.me/<?= e($waNum) ?>" target="_blank" rel="noopener">WhatsApp ile Mesaj Gönder</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<main>
<?php if ($notice !== ''): ?><div class="notice" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if ($page === 'home'): ?>
    <?php if (!empty($slides)): ?>
        <section class="slider-section" aria-label="Öne çıkanlar">
            <div class="slider-wrapper">
                <?php foreach ($slides as $idx => $s): ?>
                    <div class="slide-item <?= $idx === 0 ? 'is-active' : '' ?>" data-slide="<?= $idx ?>">
                        <div class="slide-copy">
                            <?php if (!empty($s['badge'])): ?><p class="eyebrow eyebrow-accent"><?= e($s['badge']) ?></p><?php endif; ?>
                            <h1><?= e($s['title']) ?></h1>
                            <span class="accent-bar"></span>
                            <?php if (!empty($s['subtitle'])): ?><p class="lead"><?= e($s['subtitle']) ?></p><?php endif; ?>
                            <div class="actions">
                                <?php if (!empty($s['button_text'])): ?>
                                    <a class="button button-primary" href="<?= e($s['button_link']) ?>"><?= e($s['button_text']) ?></a>
                                <?php endif; ?>
                                <a class="button button-whatsapp" href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', setting('whatsapp', '905443442449'))) ?>" target="_blank" rel="noopener">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm.02 18.06c-1.49 0-2.95-.4-4.22-1.16l-.3-.18-3.13.82.84-3.05-.2-.31a7.9 7.9 0 0 1-1.22-4.27c0-4.41 3.59-8 8.02-8a7.95 7.95 0 0 1 5.67 2.35 7.94 7.94 0 0 1 2.35 5.66c0 4.42-3.59 8.01-8.03 8.01zm4.39-6c-.24-.12-1.42-.7-1.64-.78-.22-.08-.38-.12-.55.12-.16.24-.63.78-.77.94-.14.16-.29.18-.53.06-.24-.12-1.02-.38-1.95-1.2-.72-.64-1.2-1.44-1.34-1.68-.14-.24-.02-.37.1-.49.11-.11.24-.29.36-.43.12-.14.16-.24.24-.4.08-.16.04-.31-.02-.43s-.55-1.33-.76-1.82c-.2-.48-.41-.41-.56-.42l-.48-.01c-.16 0-.43.06-.66.31-.22.24-.87.85-.87 2.08s.89 2.41 1.01 2.58c.12.16 1.76 2.68 4.25 3.76.59.26 1.06.41 1.42.53.6.19 1.14.16 1.57.1.48-.07 1.48-.6 1.69-1.19.21-.58.21-1.08.15-1.19-.06-.11-.22-.18-.46-.3z"/></svg>
                                    WhatsApp ile yazın
                                </a>
                            </div>
                            <div class="hero-note" style="margin-top: 24px;"><span class="note-dot"></span> Salihli'de ofis ortamında birebir yüz yüze takip &amp; rehberlik.</div>
                        </div>
                        <?php if (!empty($s['image_url'])): ?>
                            <div class="slider-card slider-media-card">
                                <div class="slider-media-frame">
                                    <img src="<?= e($s['image_url']) ?>" alt="<?= e($s['title']) ?>" class="slider-media-img" loading="lazy">
                                    <div class="slider-media-overlay">
                                        <span class="slider-media-tag">
                                            <span class="tag-live-dot"></span>
                                            <?= e(!empty($s['badge']) ? $s['badge'] : 'Salihli Yüz Yüze Koçluk') ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="slider-card hero-card">
                                <?php if ($idx === 1): ?>
                                    <div class="card-top"><span class="tiny-label">DENEME &amp; TAKİP</span><span class="status-pill status-pill-red">Kişiye Özel</span></div>
                                    <div class="orbit"><div class="orbit-center">ANALİZ</div><span class="orbit-tag tag-one">Net Takibi</span><span class="orbit-tag tag-two">Zaman</span><span class="orbit-tag tag-three">Strateji</span></div>
                                    <p class="card-caption">Deneme netlerini analiz edip eksik noktaları haftalık çalışma planıyla hızla kapatın.</p>
                                <?php elseif ($idx === 2): ?>
                                    <div class="card-top"><span class="tiny-label">DİSİPLİN MODELİ</span><span class="status-pill status-pill-red">Sürdürülebilir</span></div>
                                    <div class="orbit"><div class="orbit-center">DİSİPLİN</div><span class="orbit-tag tag-one">Strateji</span><span class="orbit-tag tag-two">Eksik Kapatma</span><span class="orbit-tag tag-three">Derece</span></div>
                                    <p class="card-caption">Mezun senesinde yüksek moral, istikrar ve doğru stratejiyle hedefe kesin odaklanın.</p>
                                <?php else: ?>
                                    <div class="card-top"><span class="tiny-label">ÇALIŞMA MODELİ</span><span class="status-pill status-pill-red"><?= e(setting('working_model', 'Yüz Yüze & Hibrit')) ?></span></div>
                                    <div class="orbit"><div class="orbit-center">OFİS</div><span class="orbit-tag tag-one">Hedef</span><span class="orbit-tag tag-two">Takip</span><span class="orbit-tag tag-three">Gelişim</span></div>
                                    <p class="card-caption">Salihli'de yüz yüze periyodik görüşmeler, düzenli hedef ve deneme takibi.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($slides) > 1): ?>
                <div class="slider-controls">
                    <button type="button" class="slider-arrow slider-prev" aria-label="Önceki slayt">❮</button>
                    <div class="slider-dots">
                        <?php foreach ($slides as $idx => $s): ?>
                            <button type="button" class="slider-dot <?= $idx === 0 ? 'is-active' : '' ?>" data-index="<?= $idx ?>" aria-label="Slayt <?= $idx + 1 ?>"></button>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="slider-arrow slider-next" aria-label="Sonraki slayt">❯</button>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="trust-strip">
        <span>Salihli Ofis / Yüz Yüze</span><i></i>
        <span>Resmi Sertifikalı Koçluk</span><i></i>
        <span>TYT &amp; AYT</span><i></i>
        <span>Mezun Öğrenci</span><i></i>
        <span>Hibrit Destek</span>
    </section>

    <!-- Bölüm 1: Çalışma Alanları (Referans Görsel Kreatifi) -->
    <section class="section service-section">
        <div class="section-heading section-heading-modern">
            <div class="heading-left">
                <p class="eyebrow eyebrow-accent">BÖLÜM 1 / HİZMETLER</p>
                <h2>Hedefine Uygun Koçluk Çözümleri</h2>
                <span class="accent-bar"></span>
            </div>
            <p class="section-copy">YKS hazırlığında fark yaratmanız için; Salihli fiziksel ofisimizde yüz yüze, resmi sertifikalı koçluk ve yapay zekâ destekli analizler.</p>
        </div>
        <div class="service-grid modern-grid">
            <a class="modern-card" href="/tyt">
                <div class="card-visual visual-neutral">
                    <span class="visual-text">TYT Temel</span>
                </div>
                <div class="card-content">
                    <h3>TYT Öğrenci Koçluğu &amp; Takip</h3>
                    <p>Temel Yeterlilik Testi hazırlığında kişiye özel çalışma programı, haftalık soru ve konu hedefleri, düzenli deneme analizi.</p>
                </div>
            </a>
            <a class="modern-card" href="/ayt">
                <div class="card-visual visual-red">
                    <span class="visual-badge">TREND / ÖNERİLEN</span>
                    <span class="visual-text">YKS &amp; AYT</span>
                </div>
                <div class="card-content">
                    <h3>AYT &amp; Alan Odaklı Koçluk</h3>
                    <p>Sayısal, Eşit Ağırlık ve Sözel alan hedeflerinize göre şekillenen, Salihli ofisimizde birebir yüz yüze takip edilen çalışma disiplini.</p>
                </div>
            </a>
            <a class="modern-card" href="/mezun">
                <div class="card-visual visual-blue">
                    <span class="visual-text">Mezun &amp; Hibrit</span>
                </div>
                <div class="card-content">
                    <h3>Mezun Öğrenci &amp; Dijital Takip</h3>
                    <p>Önceki sınav sürecinin eksiklerini analiz eden, yeni döneme sağlam motivasyon ve kesintisiz hibrit takip ile başlayan kapsamlı koçluk.</p>
                </div>
            </a>
        </div>
    </section>

    <!-- BÖLÜM: Veli & Öğrenci Çözüm Rehberi (Kritik Sorunlar ve Çözümler) -->
    <section class="section problem-solution-section">
        <div class="section-heading section-heading-modern">
            <div class="heading-left">
                <p class="eyebrow eyebrow-accent">BÖLÜM 2 / SORUNLAR &amp; ÇÖZÜMLER</p>
                <h2>Sınav yolculuğunda yalnız değilsiniz.</h2>
                <span class="accent-bar"></span>
            </div>
            <p class="section-copy">Velilerin evde yaşadığı ders gerginliği ve öğrencilerin net tıkanıklığı için Salihli fiziksel ofisimizde uyguladığımız kanıtlanmış çözümler.</p>
        </div>

        <div class="problem-solution-grid">
            <!-- Veli Kartı -->
            <div class="ps-card ps-card-parent">
                <div class="ps-card-header">
                    <span class="ps-badge">👨‍👩‍👧 VELİLERİMİZ İÇİN ÇÖZÜMLER</span>
                    <h3>Evdeki Sınav Baskısını Bitirip Huzuru Sağlayın</h3>
                </div>
                <div class="ps-items-list">
                    <div class="ps-item">
                        <div class="ps-problem">
                            <span class="ps-icon">❌</span>
                            <div class="ps-text">
                                <strong>"Çocuğum masanın başına oturmuyor, evde sürekli telefon ve ders tartışması yaşıyoruz."</strong>
                            </div>
                        </div>
                        <div class="ps-solution">
                            <span class="ps-icon">✅</span>
                            <div class="ps-text">
                                <span class="ps-label">Salihli Koçluk Çözümü:</span>
                                <p>Evde denetçi rolünden çıkın. Birebir koçluk otoritesi ve haftalık net hedeflerle sorumluluk tamamen öğrenciye geçer; evdeki gerginlik yerini huzura bırakır.</p>
                            </div>
                        </div>
                    </div>

                    <div class="ps-item">
                        <div class="ps-problem">
                            <span class="ps-icon">❌</span>
                            <div class="ps-text">
                                <strong>"Dershaneye gönderdik ama kalabalık sınıflarda kayboldu, netleri yerinde sayıyor."</strong>
                            </div>
                        </div>
                        <div class="ps-solution">
                            <span class="ps-icon">✅</span>
                            <div class="ps-text">
                                <span class="ps-label">Salihli Koçluk Çözümü:</span>
                                <p>Standart sınıf müfredatı yerine, öğrencinin doğrudan kendi eksiklerine özel hazırlanan birebir çalışma programı ile bütçe ve zaman verimliliği sağlanır.</p>
                            </div>
                        </div>
                    </div>

                    <div class="ps-item">
                        <div class="ps-problem">
                            <span class="ps-icon">❌</span>
                            <div class="ps-text">
                                <strong>"Çocuğumun gelişimini ve sınavdaki gerçek durumunu nasıl bileceğim?"</strong>
                            </div>
                        </div>
                        <div class="ps-solution">
                            <span class="ps-icon">✅</span>
                            <div class="ps-text">
                                <span class="ps-label">Salihli Koçluk Çözümü:</span>
                                <p>Her hafta veliye özel WhatsApp bilgilendirmesi ve ayda bir kapsamlı yüz yüze gelişim raporu ile sürecin her adımından şeffaf biçimde haberdar olursunuz.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="ps-card-footer">
                    <a href="/veli" class="ps-cta-link">Veli Danışmanlığı Yaklaşımımızı İnceleyin →</a>
                </div>
            </div>

            <!-- Öğrenci Kartı -->
            <div class="ps-card ps-card-student">
                <div class="ps-card-header">
                    <span class="ps-badge">🎯 ÖĞRENCİLERİMİZ İÇİN ÇÖZÜMLER</span>
                    <h3>Netleri Tıkayan Engelleri Kaldırın</h3>
                </div>
                <div class="ps-items-list">
                    <div class="ps-item">
                        <div class="ps-problem">
                            <span class="ps-icon">❌</span>
                            <div class="ps-text">
                                <strong>"Konuları anlıyorum, saatlerce soru çözüyorum ama deneme netlerim bir türlü artmıyor."</strong>
                            </div>
                        </div>
                        <div class="ps-solution">
                            <span class="ps-icon">✅</span>
                            <div class="ps-text">
                                <span class="ps-label">Salihli Koçluk Çözümü:</span>
                                <p>Körlemesine soru çözmeyi bırakın. Hata analizi defteri ve nokta atışı eksik kazanım kapatma taktiğiyle takıldığınız net eşiğini hızla aşın.</p>
                            </div>
                        </div>
                    </div>

                    <div class="ps-item">
                        <div class="ps-problem">
                            <span class="ps-icon">❌</span>
                            <div class="ps-text">
                                <strong>"Hangi derse ne kadar çalışacağımı, hangi kaynaktan başlayacağımı bilemiyorum."</strong>
                            </div>
                        </div>
                        <div class="ps-solution">
                            <span class="ps-icon">✅</span>
                            <div class="ps-text">
                                <span class="ps-label">Salihli Koçluk Çözümü:</span>
                                <p>Saat saat gün gün planlanan, senin anlama hızına ve okul/kurs düzenine tam uyum sağlayan uygulanabilir haftalık kişisel çalışma takvimi.</p>
                            </div>
                        </div>
                    </div>

                    <div class="ps-item">
                        <div class="ps-problem">
                            <span class="ps-icon">❌</span>
                            <div class="ps-text">
                                <strong>"Denemelerde süreyi yetiştiremiyorum, heyecanlanıyorum ve motivasyonum çabuk düşüyor."</strong>
                            </div>
                        </div>
                        <div class="ps-solution">
                            <span class="ps-icon">✅</span>
                            <div class="ps-text">
                                <span class="ps-label">Salihli Koçluk Çözümü:</span>
                                <p>Turlama taktiği, süre yönetimi provaları ve Testroom veri analitiği ile sınav kaygısını ve stresini kontrol altına alma seansları.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="ps-card-footer">
                    <a href="/yks" class="ps-cta-link">YKS Koçluğu Programlarımızı İnceleyin →</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Bölüm 3: Koçluk Sistemi -->
    <section class="section section-intro">
        <div class="section-heading section-heading-modern">
            <div class="heading-left">
                <p class="eyebrow eyebrow-accent">BÖLÜM 2 / SİSTEM VE YÖNTEM</p>
                <h2>Yalnızca program değil, işleyen bir çalışma düzeni.</h2>
                <span class="accent-bar"></span>
            </div>
            <p class="section-copy">Her öğrencinin hedefi, ihtiyaçları ve çalışma alışkanlıkları farklıdır. Süreç; öğrenciyi tanımak, gerçekçi hedefler belirlemek ve ilerlemeyi düzenli değerlendirmek üzerine kurulur.</p>
        </div>
        <div class="feature-grid">
            <article class="feature-card">
                <span class="feature-number">01</span>
                <h3>Öğrenciyi Tanı &amp; Analiz Et</h3>
                <p>Hedefleri, mevcut durumu ve ihtiyaçları Salihli ofisimizde birebir değerlendirin.</p>
            </article>
            <article class="feature-card">
                <span class="feature-number">02</span>
                <h3>Planı Kişiselleştir</h3>
                <p>Öğrencinin koşullarına tam uyum sağlayan, uygulanabilir ve gerçekçi bir yol haritası oluşturun.</p>
            </article>
            <article class="feature-card">
                <span class="feature-number">03</span>
                <h3>Gelişimi Düzenli Takip Et</h3>
                <p>Haftalık görüşmeler ve deneme sonuçlarıyla süreci gözden geçirip yeni hedefler belirleyin.</p>
            </article>
        </div>
    </section>

    <section class="software-banner">
        <div>
            <p class="eyebrow eyebrow-accent" style="color: #ffffff;">TEKNOLOJİ DESTEKLİ YAKLAŞIM</p>
            <h2>Dijital araçlar süreci destekler.<br><span>Rehberlik ve bağ insanda kalır.</span></h2>
            <span class="accent-bar accent-bar-white"></span>
        </div>
        <a class="button button-white" href="/yazilim">Dijital yaklaşımı incele →</a>
    </section>

    <!-- BÖLÜM 4: Gerçek Başarı Hikayeleri & Vaka Analizleri -->
    <section class="section case-studies-section" style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
        <div class="section-heading section-heading-modern">
            <div class="heading-left">
                <p class="eyebrow eyebrow-accent">BÖLÜM 4 / VAKA ANALİZLERİ &amp; BAŞARILAR</p>
                <h2>Salihli'de Somut Başarı Öyküleri</h2>
                <span class="accent-bar"></span>
            </div>
            <p class="section-copy">İsimleri gizli tutulmuş, Salihli ofisimizde koçluk süreciyle netlerini katlayan ve hedeflerine ulaşan gerçek öğrencilerimizin yolculukları.</p>
        </div>

        <div class="case-studies-grid">
            <!-- Vaka 1 -->
            <article class="case-card">
                <div class="case-header">
                    <span class="case-badge">MEZUN SAYISAL</span>
                    <span class="case-result-badge">TYT 55 Net → 94 Net</span>
                </div>
                <h3>Tıp Fakültesi Hedefinde 55 Netten 18.000 Sıralamaya</h3>
                <div class="case-timeline">
                    <div class="timeline-step">
                        <strong>Başlangıç Durumu:</strong>
                        <p>Konu eksiği yoktu ancak denemelerde süre yetiştiremiyor, geometriden soru kaçırıyor ve panik yapıyordu.</p>
                    </div>
                    <div class="timeline-step">
                        <strong>Koçluk Müdahalesi:</strong>
                        <p>Günlük 15 problem &amp; 10 geometri rutini oturtuldu. Haftada 2 branş denemesi analiziyle hata defteri tutuldu.</p>
                    </div>
                    <div class="timeline-step result-step">
                        <strong>Elde Edilen Sonuç:</strong>
                        <p>Sayısal alanda Türkiye geneli 18.000 başarı sıralaması ile Devlet Üniversitesi Tıp Fakültesi yerleşimi.</p>
                    </div>
                </div>
            </article>

            <!-- Vaka 2 -->
            <article class="case-card">
                <div class="case-header">
                    <span class="case-badge" style="background:#fef3c7; color:#92400e;">8. SINIF LGS</span>
                    <span class="case-result-badge" style="background:#ecfdf5; color:#047857;">%14 Dilim → %2.1 Dilim</span>
                </div>
                <h3>LGS Matematik Korkusunu Yenerek Salihli Fen Lisesi'ne</h3>
                <div class="case-timeline">
                    <div class="timeline-step">
                        <strong>Başlangıç Durumu:</strong>
                        <p>Yeni nesil matematik sorularında soruyu yarıda bırakıyor, sınav kaygısı nedeniyle evde gerginlik yaşıyordu.</p>
                    </div>
                    <div class="timeline-step">
                        <strong>Koçluk Müdahalesi:</strong>
                        <p>Günlük paragraf okuma tekniğiyle anlama hızı artırıldı. MEB kazanım odaklı yeni nesil soru çözme stratejisi uygulandı.</p>
                    </div>
                    <div class="timeline-step result-step">
                        <strong>Elde Edilen Sonuç:</strong>
                        <p>LGS'de %2.1 yüzdelik dilim elde ederek Salihli Sekban Fen Lisesi'ne asil listeden yerleşti.</p>
                    </div>
                </div>
            </article>

            <!-- Vaka 3 -->
            <article class="case-card">
                <div class="case-header">
                    <span class="case-badge" style="background:#f3e8ff; color:#6b21a8;">12. SINIF EŞİT AĞIRLIK</span>
                    <span class="case-result-badge" style="background:#eff6ff; color:#1d4ed8;">İlk 12.000 Sıralama</span>
                </div>
                <h3>Dershane Yorgunluğundan Kurtulup Birebir Koçlukla Hukuk</h3>
                <div class="case-timeline">
                    <div class="timeline-step">
                        <strong>Başlangıç Durumu:</strong>
                        <p>Dershane temposunda günde 8 saat derse girip eve yorgun dönüyor, kendi başına ders çalışmaya vakit bulamıyordu.</p>
                    </div>
                    <div class="timeline-step">
                        <strong>Koçluk Müdahalesi:</strong>
                        <p>Dershane yerine hedef odaklı bireysel çalışma düzeni kuruldu. Edebiyat ezberleri ve AYT Matematik dengesi sağlandı.</p>
                    </div>
                    <div class="timeline-step result-step">
                        <strong>Elde Edilen Sonuç:</strong>
                        <p>Eşit Ağırlıkta ilk 12.000 sıralama elde ederek Devlet Üniversitesi Hukuk Fakültesi'ne yerleşti.</p>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <!-- Bölüm 5: Sıkça Sorulan Sorular (Akordeon) -->
    <?php $siteFaqs = get_faqs(true); ?>
    <?php if (!empty($siteFaqs)): ?>
        <section class="section faq-section" id="sss">
            <div class="section-heading section-heading-modern">
                <div class="heading-left">
                    <p class="eyebrow eyebrow-accent">BÖLÜM 5 / MERAK EDİLENLER</p>
                    <h2>Sıkça Sorulan Sorular</h2>
                    <span class="accent-bar"></span>
                </div>
                <p class="section-copy">YKS koçluğu süreci, Salihli ofis görüşmeleri, deneme analizleri ve çalışma takvimi hakkında aklınıza takılabilecek soruların yanıtları.</p>
            </div>
            <div class="faq-accordion">
                <?php foreach ($siteFaqs as $idx => $faq): ?>
                    <details class="faq-item" <?= $idx === 0 ? 'open' : '' ?>>
                        <summary class="faq-question">
                            <span><?= e($faq['question']) ?></span>
                            <span class="faq-icon" aria-hidden="true">+</span>
                        </summary>
                        <div class="faq-answer">
                            <p><?= nl2br(e($faq['answer'])) ?></p>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Google Rich Snippet Structured Data (FAQPage) -->
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "FAQPage",
            "mainEntity": [
                <?php
                $faqJsonItems = [];
                foreach ($siteFaqs as $f) {
                    $faqJsonItems[] = json_encode([
                        "@type" => "Question",
                        "name" => $f['question'],
                        "acceptedAnswer" => [
                            "@type" => "Answer",
                            "text" => strip_tags($f['answer']),
                        ],
                    ], JSON_UNESCAPED_UNICODE);
                }
                echo implode(",\n                ", $faqJsonItems);
                ?>
            ]
        }
        </script>
    <?php endif; ?>

    <!-- Bölüm 6: YKS Hazırlık Rehberi & Blog -->
    <?php $recentPosts = get_posts(3, true); ?>
    <?php if (!empty($recentPosts)): ?>
        <section class="section blog-section" id="blog">
            <div class="section-heading section-heading-modern">
                <div class="heading-left">
                    <p class="eyebrow eyebrow-accent">BÖLÜM 6 / REHBER &amp; İÇERİK</p>
                    <h2>YKS Hazırlık ve Koçluk Rehberi</h2>
                    <span class="accent-bar"></span>
                </div>
                <p class="section-copy">Salihli'de üniversite hazırlığı sürecinde çalışma disiplini, deneme analizleri ve mezun maratonuna dair uzman rehber yazıları.</p>
            </div>
            <div class="blog-list" style="padding-left: 0; padding-right: 0;">
                <?php foreach ($recentPosts as $item): ?>
                    <article class="blog-card">
                        <div class="blog-card-meta">
                            <span class="blog-badge">YKS REHBERİ</span>
                            <span class="blog-date"><?= e(date('d.m.Y', strtotime($item['created_at']))) ?></span>
                        </div>
                        <h3><a href="/blog/<?= rawurlencode($item['slug']) ?>"><?= e($item['title']) ?></a></h3>
                        <p><?= e($item['excerpt']) ?></p>
                        <a class="blog-read-more" href="/blog/<?= rawurlencode($item['slug']) ?>">
                            Rehberi Oku <span>→</span>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
            <div style="text-align: center; margin-top: 36px;">
                <a class="button button-outline" href="/blog">Tüm Rehber Yazılarını İncele (<?= count(get_posts(0, true)) ?>) →</a>
            </div>
        </section>
    <?php endif; ?>

    <section class="bottom-cta">
        <p class="eyebrow eyebrow-accent">İLK ADIMI BİRLİKTE ATALIM</p>
        <h2>Çocuğunuzun çalışma düzenini birlikte konuşalım.</h2>
        <span class="accent-bar" style="margin: 12px auto 20px;"></span>
        <p style="max-width: 650px; margin: 0 auto 24px; color: #475569; font-size: 15px; line-height: 1.6;">Salihli ofisimizde kahvemizi için; mevcut ders durumunu, deneme netlerini ve hedefleri ücretsiz 30 dakikalık durum analiziyle birlikte planlayalım.</p>
        <div class="actions" style="justify-content: center; margin-top: 24px; flex-wrap: wrap; gap: 12px;">
            <a class="button button-primary" href="/iletisim" style="padding: 12px 24px; font-weight: 800;">🎯 Ücretsiz 30 Dk. Durum Analizi Randevusu Alın</a>
            <a class="button button-whatsapp" href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', setting('whatsapp', '905443442449'))) ?>" target="_blank" rel="noopener">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm.02 18.06c-1.49 0-2.95-.4-4.22-1.16l-.3-.18-3.13.82.84-3.05-.2-.31a7.9 7.9 0 0 1-1.22-4.27c0-4.41 3.59-8 8.02-8a7.95 7.95 0 0 1 5.67 2.35 7.94 7.94 0 0 1 2.35 5.66c0 4.42-3.59 8.01-8.03 8.01zm4.39-6c-.24-.12-1.42-.7-1.64-.78-.22-.08-.38-.12-.55.12-.16.24-.63.78-.77.94-.14.16-.29.18-.53.06-.24-.12-1.02-.38-1.95-1.2-.72-.64-1.2-1.44-1.34-1.68-.14-.24-.02-.37.1-.49.11-.11.24-.29.36-.43.12-.14.16-.24.24-.4.08-.16.04-.31-.02-.43s-.55-1.33-.76-1.82c-.2-.48-.41-.41-.56-.42l-.48-.01c-.16 0-.43.06-.66.31-.22.24-.87.85-.87 2.08s.89 2.41 1.01 2.58c.12.16 1.76 2.68 4.25 3.76.59.26 1.06.41 1.42.53.6.19 1.14.16 1.57.1.48-.07 1.48-.6 1.69-1.19.21-.58.21-1.08.15-1.19-.06-.11-.22-.18-.46-.3z"/></svg>
                WhatsApp ile Durum Danışın
            </a>
        </div>
    </section>
<?php elseif ($page === 'blog'): ?>
    <section class="page-hero page-hero-blog">
        <p class="eyebrow eyebrow-accent">GÜNCEL YAZILAR &amp; REHBERLER</p>
        <h1>Sınav yolculuğuna<br><span>eşlik eden uzman rehberler.</span></h1>
        <span class="accent-bar"></span>
        <p class="lead">Salihli'de TYT, AYT, LGS hazırlığı, deneme analizleri, net artırma stratejileri ve veriye dayalı koçluk üzerine rehber içerikler.</p>
    </section>

    <!-- KATEGORİK FİLTRE BUTONLARI -->
    <nav class="blog-categories-nav" aria-label="Blog kategorileri">
        <div class="blog-categories-wrapper">
            <a class="cat-pill cat-pill-all <?= ($currentCategorySlug === '' || $currentCategorySlug === 'tumu') ? 'is-active' : '' ?>" href="/blog">
                <span class="cat-pill-icon">🌟</span>
                <span>Tümü</span>
            </a>
            <?php foreach ($categories as $idx => $cat): ?>
                <?php 
                    $isActive = ($currentCategorySlug === $cat['slug']);
                    $catMeta = get_category_color_meta($cat['slug'], $idx);
                ?>
                <a class="cat-pill cat-pill-<?= e($cat['slug']) ?> <?= $isActive ? 'is-active' : '' ?>" href="/blog?kategori=<?= rawurlencode($cat['slug']) ?>">
                    <span class="cat-pill-icon"><?= $catMeta['icon'] ?></span>
                    <span><?= e($cat['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <section class="section blog-modern-list">
        <?php if (!$posts): ?>
            <div class="empty-blog-state">
                <p>Bu kategoride henüz yayınlanmış yazı bulunmamaktadır.</p>
                <a class="button button-outline" href="/blog">← Tüm Yazıları Görüntüle</a>
            </div>
        <?php endif; ?>

        <?php foreach ($posts as $idx => $item): ?>
            <?php 
                $readTime = estimate_reading_time($item['content'] ?? '');
                $itemMeta = get_category_color_meta($item['category_slug'] ?? '', $idx);
            ?>
            <article class="blog-row-card">
                <a class="blog-card-thumb-wrap" href="/blog/<?= rawurlencode($item['slug']) ?>" aria-label="<?= e($item['title']) ?>">
                    <?php if (!empty($item['image_url'])): ?>
                        <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['title']) ?>" class="blog-card-thumb" loading="lazy">
                    <?php else: ?>
                        <div class="blog-card-thumb-fallback" style="background: <?= e($itemMeta['gradient']) ?>;">
                            <span class="thumb-category"><?= e($item['category_name'] ?? 'YKS REHBERİ') ?></span>
                            <span class="thumb-icon"><?= $itemMeta['icon'] ?></span>
                        </div>
                    <?php endif; ?>
                </a>

                <div class="blog-row-content">
                    <span class="blog-category-tag" style="color: <?= e($itemMeta['tag_color']) ?>;">
                        <?= $itemMeta['icon'] ?> <?= e($item['category_name'] ?? 'REHBER') ?>
                    </span>
                    <h2 class="blog-row-title">
                        <a href="/blog/<?= rawurlencode($item['slug']) ?>"><?= e($item['title']) ?></a>
                    </h2>
                    <p class="blog-row-excerpt"><?= e($item['excerpt']) ?></p>
                    <div class="blog-row-meta">
                        <span class="meta-date"><?= e(date('d.m.Y', strtotime($item['created_at']))) ?></span>
                        <span class="meta-dot">·</span>
                        <span class="meta-readtime"><?= $readTime ?> dk okuma</span>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php elseif ($page === 'yazi' && $post): ?>
    <?php $readTime = estimate_reading_time($post['content'] ?? ''); ?>
    <article class="article">
        <?php if (($post['status'] ?? '') === 'draft'): ?>
            <div class="draft-preview-notice">
                <div class="draft-notice-content">
                    <span class="draft-notice-icon">⚠️</span>
                    <div>
                        <strong>Taslak Önizleme Modu:</strong> Bu yazı henüz yayına alınmamıştır ve yalnızca yönetici olarak size görünmektedir.
                    </div>
                </div>
                <a href="/admin/?tab=blog&amp;edit=<?= (int) $post['id'] ?>#blog-form" class="draft-notice-btn">Yazıyı Düzenle &amp; Yayına Al →</a>
            </div>
        <?php endif; ?>
        <div class="article-header">
            <a class="article-back" href="/blog">← Tüm Rehber Yazılarına Dön</a>
            <div class="article-meta-row">
                <span class="blog-badge"><?= e($post['category_name'] ?? 'YKS REHBERİ') ?></span>
                <time class="article-date"><?= e(date('d.m.Y', strtotime($post['created_at']))) ?></time>
                <span class="article-reading-time">⏱️ ~<?= $readTime ?> dk okuma</span>
            </div>
            <h1><?= e($post['title']) ?></h1>
            <p class="article-lead"><?= e($post['excerpt']) ?></p>
            <div class="article-author-strip">
                <div class="author-avatar"><?= mb_substr(setting('coach_name', 'S'), 0, 1) ?></div>
                <div class="author-info">
                    <strong><?= e(setting('coach_name', 'Salihli YKS Koçu')) ?></strong>
                    <span><?= e(setting('credentials', 'Resmi Sertifikalı Öğrenci Koçu')) ?> • Salihli Ofis</span>
                </div>
            </div>
        </div>

        <?php if (!empty($post['image_url'])): ?>
            <div class="article-cover-wrap">
                <img src="<?= e($post['image_url']) ?>" alt="<?= e($post['title']) ?>" class="article-cover-img" loading="lazy">
            </div>
        <?php endif; ?>

        <!-- Otomatik İçindekiler Tablosu (TOC / Hızlı Gezinme) -->
        <?php $toc = extract_table_of_contents($post['content'] ?? ''); ?>
        <?php if (count($toc) >= 2): ?>
            <div class="article-toc-box">
                <div class="toc-header">
                    <span class="toc-icon">📑</span>
                    <strong>Bu Rehberde Neler Var? (İçindekiler)</strong>
                </div>
                <nav class="toc-list" aria-label="İçindekiler">
                    <?php foreach ($toc as $idx => $item): ?>
                        <a href="#<?= e($item['id']) ?>" class="toc-link <?= $item['level'] === 3 ? 'toc-sub' : '' ?>">
                            <span class="toc-num"><?= $idx + 1 ?>.</span>
                            <span><?= e($item['title']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        <?php endif; ?>

        <div class="article-content">
            <?= render_markdown_content($post['content']) ?>
        </div>

        <!-- Sosyal Medya Paylaşım Butonları -->
        <div class="article-share-bar">
            <div class="share-title">
                <span>📢 Bu Rehberi Paylaşın:</span>
            </div>
            <div class="share-buttons">
                <?php 
                    $shareUrl = $canonicalUrl;
                    $shareText = rawurlencode($post['title'] . ' | Salihli Koçluk');
                    $shareUrlEncoded = rawurlencode($shareUrl);
                    $instaShareUrl = get_instagram_url() ?: 'https://instagram.com/salihlikocluk';
                ?>
                <a class="share-btn share-ig" href="<?= e($instaShareUrl) ?>" target="_blank" rel="noopener" aria-label="Instagram'da Takip Edin ve Paylaşın">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    Instagram
                </a>
                <a class="share-btn share-wa" href="https://api.whatsapp.com/send?text=<?= $shareText ?>%20<?= $shareUrlEncoded ?>" target="_blank" rel="noopener" aria-label="WhatsApp'ta Paylaş">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm.02 18.06c-1.49 0-2.95-.4-4.22-1.16l-.3-.18-3.13.82.84-3.05-.2-.31a7.9 7.9 0 0 1-1.22-4.27c0-4.41 3.59-8 8.02-8a7.95 7.95 0 0 1 5.67 2.35 7.94 7.94 0 0 1 2.35 5.66c0 4.42-3.59 8.01-8.03 8.01zm4.39-6c-.24-.12-1.42-.7-1.64-.78-.22-.08-.38-.12-.55.12-.16.24-.63.78-.77.94-.14.16-.29.18-.53.06-.24-.12-1.02-.38-1.95-1.2-.72-.64-1.2-1.44-1.34-1.68-.14-.24-.02-.37.1-.49.11-.11.24-.29.36-.43.12-.14.16-.24.24-.4.08-.16.04-.31-.02-.43s-.55-1.33-.76-1.82c-.2-.48-.41-.41-.56-.42l-.48-.01c-.16 0-.43.06-.66.31-.22.24-.87.85-.87 2.08s.89 2.41 1.01 2.58c.12.16 1.76 2.68 4.25 3.76.59.26 1.06.41 1.42.53.6.19 1.14.16 1.57.1.48-.07 1.48-.6 1.69-1.19.21-.58.21-1.08.15-1.19-.06-.11-.22-.18-.46-.3z"/></svg>
                    WhatsApp
                </a>
                <a class="share-btn share-x" href="https://twitter.com/intent/tweet?url=<?= $shareUrlEncoded ?>&text=<?= $shareText ?>" target="_blank" rel="noopener" aria-label="X (Twitter)'da Paylaş">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    X (Twitter)
                </a>
                <a class="share-btn share-fb" href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrlEncoded ?>" target="_blank" rel="noopener" aria-label="Facebook'ta Paylaş">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    Facebook
                </a>
                <a class="share-btn share-li" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareUrlEncoded ?>" target="_blank" rel="noopener" aria-label="LinkedIn'de Paylaş">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                    LinkedIn
                </a>
                <button class="share-btn share-copy" type="button" onclick="copyArticleLink(this, '<?= e($shareUrl) ?>')">
                    🔗 Bağlantıyı Kopyala
                </button>
            </div>
            <div class="copy-success-tooltip" id="copyTooltip" style="display:none;">
                ✓ Bağlantı panoya kopyalandı!
            </div>
        </div>

        <div class="article-cta-box">
            <div class="article-cta-text">
                <h3>Hedeflediğiniz Sıralama İçin Doğru Stratejiyle Çalışın</h3>
                <p>Salihli merkez ofisimizde yüz yüze tanışarak öğrencinizin seviyesine ve hedeflerine özel haftalık çalışma planını birlikte oluşturalım.</p>
            </div>
            <div class="article-cta-actions">
                <a class="button button-primary" href="/iletisim">Ücretsiz Ön Görüşme Al</a>
                <a class="button button-whatsapp" href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', setting('whatsapp', '905443442449'))) ?>?text=<?= rawurlencode('Merhaba, "' . $post['title'] . '" yazınızı inceledim. YKS koçluğu hakkında bilgi almak istiyorum.') ?>" target="_blank" rel="noopener">WhatsApp ile Sorun</a>
            </div>
        </div>

        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Article",
            "headline": <?= json_encode($post['title'], JSON_UNESCAPED_UNICODE) ?>,
            "description": <?= json_encode($post['excerpt'], JSON_UNESCAPED_UNICODE) ?>,
            "datePublished": "<?= date('c', strtotime($post['created_at'])) ?>",
            "dateModified": "<?= date('c', strtotime($post['created_at'])) ?>",
            "author": {
                "@type": "Person",
                "name": <?= json_encode(setting('coach_name', 'Salihli YKS Koçu'), JSON_UNESCAPED_UNICODE) ?>
            },
            "publisher": {
                "@type": "Organization",
                "name": "Salihli YKS Koçluk"
            }
        }
        </script>
    </article>
<?php elseif ($page === 'iletisim'): ?>
    <section class="page-hero"><p class="eyebrow eyebrow-accent">İLETİŞİM &amp; RANDEVU</p><h1>Ön görüşme için<br><span>ilk adımı atın.</span></h1><span class="accent-bar"></span><p class="lead">Öğrencinin hedefini ve ihtiyaçlarını kısaca paylaşın. Talebiniz koç tarafından değerlendirilip size dönüş yapılır.</p></section>
    <section class="contact-section">
        <div class="contact-aside">
            <h2>Görüşmede neleri konuşacağız?</h2>
            <p>Öğrencinin hedefi, mevcut çalışma düzeni ve koçluk beklentisi. Görüşme sonrasında sürecin uygunluğu ve sonraki adımlar netleştirilir.</p>
            <div class="contact-note">Bu form bir ön görüşme talebidir; koçluk hizmeti veya sonuç garantisi anlamına gelmez.</div>
            
            <div class="direct-contact-card">
                <h3>📍 Salihli Ofis &amp; İletişim</h3>
                <?php if ($addr = setting('office_address')): ?>
                    <p><strong>Ofis Adresi:</strong> <?= e($addr) ?></p>
                <?php endif; ?>
                <?php if ($hours = setting('opening_hours')): ?>
                    <p><strong>Çalışma Saatleri:</strong> <?= e($hours) ?></p>
                <?php endif; ?>
                <?php if ($phone = setting('phone')): ?>
                    <p><strong>Telefon &amp; Randevu:</strong> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a></p>
                <?php endif; ?>
                <?php if ($wa = (setting('whatsapp') ?: setting('phone'))): ?>
                    <?php $waNum = preg_replace('/[^0-9]/', '', $wa); ?>
                    <?php if ($waNum): ?>
                        <p><strong>WhatsApp:</strong> <a href="https://wa.me/<?= e($waNum) ?>" target="_blank" rel="noopener">Mesaj Gönder (WhatsApp) →</a></p>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($email = setting('email')): ?>
                    <p><strong>E-posta:</strong> <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p>
                <?php endif; ?>
                <?php if ($wm = setting('working_model')): ?>
                    <p><strong>Görüşme Şekli:</strong> <?= e($wm) ?></p>
                <?php endif; ?>
                <?php if ($mapUrl = setting('google_maps_url')): ?>
                    <div style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--line);">
                        <a href="<?= e($mapUrl) ?>" target="_blank" rel="noopener" class="button button-outline" style="display: flex; align-items: center; gap: 8px; width: 100%; justify-content: center; font-size: 13.5px; padding: 11px 16px; font-weight: 700;">
                            🗺️ Google Haritalar'da Yol Tarifi Al →
                        </a>
                    </div>
                <?php endif; ?>
                <?php if ($areas = setting('service_areas')): ?>
                    <div style="margin-top: 16px; padding-top: 12px; border-top: 1px dashed var(--line); font-size: 12px; color: #64748b;">
                        <strong style="color: #334155; display: block; margin-bottom: 6px;">Hizmet Verilen Çevre İlçeler:</strong>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                            <?php foreach (array_map('trim', explode(',', $areas)) as $area): ?>
                                <?php if ($area !== ''): ?>
                                    <span style="background: #f1f5f9; color: #334155; padding: 3px 8px; border-radius: 4px; font-weight: 600; font-size: 11.5px;">📍 <?= e($area) ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <form class="contact-form" method="post" action="/iletisim">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Ad soyad <input name="name" autocomplete="name" maxlength="120" required></label>
        <label>Telefon <input name="phone" type="tel" autocomplete="tel" maxlength="40" required></label>
        <label>E-posta <span class="optional">(isteğe bağlı)</span><input name="email" type="email" autocomplete="email" maxlength="190"></label>
        <label>Öğrenci sınıfı / durumu <select name="student_level">
            <option value="">Seçiniz</option>
            <option>YKS / 12. sınıf</option>
            <option>YKS / Mezun</option>
            <option>YKS / 11. sınıf</option>
            <option>LGS / 8. sınıf</option>
            <option>Ara Sınıf / Diğer</option>
        </select></label>
        <label>Mesaj <textarea name="message" rows="4" maxlength="3000" placeholder="Kısaca ihtiyacınızı paylaşabilirsiniz."></textarea></label>
        <label class="honeypot" aria-hidden="true">Web sitesi<input name="website" tabindex="-1" autocomplete="off"></label>
        <label class="privacy-check"><input type="checkbox" name="privacy_acknowledged" value="1" required><span><a href="/gizlilik">Gizlilik bilgilendirmesini</a> okudum; talebime dönüş yapılması için bilgilerimin kullanılmasını kabul ediyorum.</span></label>
        <button class="button button-primary" type="submit">Talep gönder</button><small>Bilgileriniz yalnızca talebinize dönüş yapmak amacıyla kullanılır.</small>
    </form></section>
<?php elseif ($page === 'yazilim'): ?>
    <!-- Veriye Dayalı Öğrenci Koçluğu & Testroom Analizleri -->
    <section class="page-hero">
        <p class="eyebrow eyebrow-accent">ÖĞRENCİYİ TANIYARAK BAŞLAYAN YAKLAŞIM</p>
        <h1>Öğrenciyi Tanıyarak Başlayan,<br><span>Veriye Dayalı Öğrenci Koçluğu.</span></h1>
        <span class="accent-bar"></span>
        <p class="lead">Her öğrencinin güçlü yönleri, hedefleri ve desteğe ihtiyaç duyduğu alanlar farklıdır. Koçluk sürecimde Testroom’un yapay zekâ destekli yetenek analizi ve rehberlik testlerinden yararlanarak öğrencimi daha yakından tanıyor, çalışmalarımızı kişisel ihtiyaçlarına göre planlıyorum.</p>
        <div class="actions" style="margin-top: 28px;">
            <a class="button button-primary" href="/iletisim">Ön Görüşme Talep Edin</a>
            <a class="button button-whatsapp" href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', setting('whatsapp', '905443442449'))) ?>?text=<?= rawurlencode('Merhaba, Testroom veriye dayalı koçluk ve örnek analiz raporu hakkında bilgi almak istiyorum.') ?>" target="_blank" rel="noopener">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm.02 18.06c-1.49 0-2.95-.4-4.22-1.16l-.3-.18-3.13.82.84-3.05-.2-.31a7.9 7.9 0 0 1-1.22-4.27c0-4.41 3.59-8 8.02-8a7.95 7.95 0 0 1 5.67 2.35 7.94 7.94 0 0 1 2.35 5.66c0 4.42-3.59 8.01-8.03 8.01zm4.39-6c-.24-.12-1.42-.7-1.64-.78-.22-.08-.38-.12-.55.12-.16.24-.63.78-.77.94-.14.16-.29.18-.53.06-.24-.12-1.02-.38-1.95-1.2-.72-.64-1.2-1.44-1.34-1.68-.14-.24-.02-.37.1-.49.11-.11.24-.29.36-.43.12-.14.16-.24.24-.4.08-.16.04-.31-.02-.43s-.55-1.33-.76-1.82c-.2-.48-.41-.41-.56-.42l-.48-.01c-.16 0-.43.06-.66.31-.22.24-.87.85-.87 2.08s.89 2.41 1.01 2.58c.12.16 1.76 2.68 4.25 3.76.59.26 1.06.41 1.42.53.6.19 1.14.16 1.57.1.48-.07 1.48-.6 1.69-1.19.21-.58.21-1.08.15-1.19-.06-.11-.22-.18-.46-.3z"/></svg>
                Örnek Raporu İnceleyin
            </a>
        </div>
    </section>

    <!-- 1. Neleri Birlikte Keşfediyoruz -->
    <section class="section">
        <div class="section-heading section-heading-modern">
            <div class="heading-left">
                <p class="eyebrow eyebrow-accent">BİRLİKTE KEŞFEDİYORUZ</p>
                <h2>Neleri Birlikte Keşfediyoruz?</h2>
                <span class="accent-bar"></span>
            </div>
            <p class="section-copy">Yapay zekâ destekli Testroom değerlendirmeleri ile öğrencinin akademik ve kişisel potansiyelini 5 temel boyutta ortaya çıkarıyoruz.</p>
        </div>

        <div class="testroom-grid">
            <div class="testroom-card">
                <div class="testroom-icon">🎯</div>
                <h3>İlgi ve Yetenekler</h3>
                <p>Öğrencinin güçlü yönlerini ve ilgi alanlarını değerlendiriyor, eğitim ve kariyer hedeflerini konuşmak için bir başlangıç oluşturuyoruz.</p>
            </div>
            <div class="testroom-card">
                <div class="testroom-icon">📚</div>
                <h3>Çalışma Alışkanlıkları ve Öğrenme Tercihleri</h3>
                <p>Ders çalışma düzenini ve tercihlerini inceliyor, farklı çalışma yöntemlerini deneyerek kendisine uygun bir plan geliştiriyoruz.</p>
            </div>
            <div class="testroom-card">
                <div class="testroom-icon">🧠</div>
                <h3>Dikkat ve Odaklanma</h3>
                <p>Dikkat değerlendirmelerini öğrencinin günlük çalışma deneyimleriyle birlikte ele alıyor; çalışma süresi, mola düzeni ve ortamına yönelik adımlar belirliyoruz.</p>
            </div>
            <div class="testroom-card">
                <div class="testroom-icon">💬</div>
                <h3>Sınav Kaygısı ve Akademik Motivasyon</h3>
                <p>Öğrencinin sınav sürecindeki deneyimlerini ve motivasyonunu değerlendiriyor, koçluk kapsamında destekleyebileceğimiz alanları belirliyoruz.</p>
            </div>
            <div class="testroom-card">
                <div class="testroom-icon">🧭</div>
                <h3>Mesleki İlgi ve Yönelim</h3>
                <p>Öğrencinin ilgi duyduğu alanları keşfetmesine, eğitim ve meslek seçeneklerini daha bilinçli araştırmasına yardımcı oluyoruz.</p>
            </div>
        </div>
    </section>

    <!-- 2. Veriye Dayalı Rehberliği Nasıl Uyguluyorum -->
    <section class="section" style="background: var(--bg-alt); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line);">
        <div class="section-heading section-heading-modern">
            <div class="heading-left">
                <p class="eyebrow eyebrow-accent">UYGULAMA SÜRECİ</p>
                <h2>Veriye Dayalı Rehberliği Nasıl Uyguluyorum?</h2>
                <span class="accent-bar"></span>
            </div>
            <p class="section-copy">Tek bir test sonucuyla öğrenciyi etiketlemeden, pedagojik rehberlik ve yüz yüze koçlukla veriyi aksiyona dönüştüren 4 adımlı süreç.</p>
        </div>

        <div class="workflow-grid">
            <div class="workflow-card">
                <span class="workflow-step">ADIM 01</span>
                <h3>Tanıyoruz</h3>
                <p>İlk görüşmede öğrencinin hedeflerini ve ihtiyaçlarını dinliyor, uygun Testroom değerlendirmelerini belirliyorum.</p>
            </div>
            <div class="workflow-card">
                <span class="workflow-step">ADIM 02</span>
                <h3>Birlikte Yorumluyoruz</h3>
                <p>Analiz raporlarını; öğrencinin görüşleri, ders sonuçları ve gözlemlerimle birlikte değerlendiriyorum. Tek bir test sonucuyla öğrenciyi etiketlemiyorum.</p>
            </div>
            <div class="workflow-card">
                <span class="workflow-step">ADIM 03</span>
                <h3>Kişiye Özel Planlıyoruz</h3>
                <p>Bulguları somut adımlara dönüştürüyoruz: ulaşılabilir hedefler, haftalık çalışma planı ve gelişim öncelikleri belirliyoruz.</p>
            </div>
            <div class="workflow-card">
                <span class="workflow-step">ADIM 04</span>
                <h3>Takip Ediyoruz</h3>
                <p>Düzenli görüşmelerde planın uygulanmasını, ders ve deneme sonuçlarını, öğrencinin geri bildirimlerini inceliyor; ihtiyaç oldukça planımızı güncelliyoruz.</p>
            </div>
        </div>
    </section>

    <!-- 3. Veliler İçin Daha Anlaşılır Bir Süreç -->
    <section class="section">
        <div class="parent-benefit-box">
            <div class="parent-benefit-badge">AİLE VE VELİ İLETİŞİMİ</div>
            <h2>Veliler İçin Daha Anlaşılır Bir Süreç</h2>
            <p>Öğrencinin mahremiyetini gözeterek, veli görüşmelerinde güçlü yönleri, gelişim ihtiyaçlarını ve evde sunulabilecek desteği somut bilgilerle ele alıyorum.</p>
            <p class="parent-benefit-quote">"Amacım yalnızca bir rapor sunmak değil; analiz sonuçlarını öğrencinin kendini tanımasına ve harekete geçmesine yardımcı olan bir rehberlik sürecine dönüştürmek."</p>
        </div>
    </section>

    <!-- 4. CTA & Bilgilendirme Notu -->
    <section class="bottom-cta" style="padding-bottom: 75px;">
        <p class="eyebrow eyebrow-accent">BİRLİKTE BAŞLAYALIM</p>
        <h2>Öğrencinize Özel Bir Başlangıç Yapalım!</h2>
        <span class="accent-bar" style="margin: 12px auto 20px;"></span>
        <p style="max-width: 620px; margin: 0 auto 24px; color: #475569; font-size: 16px;">Koçluk sürecini öğrenmek ve örnek analiz raporunu incelemek için benimle iletişime geçin.</p>
        <div class="actions" style="justify-content: center; gap: 14px; margin-bottom: 30px;">
            <a class="button button-primary" href="/iletisim">Ön Görüşme Talep Edin</a>
            <a class="button button-whatsapp" href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', setting('whatsapp', '905443442449'))) ?>?text=<?= rawurlencode('Merhaba, örnek Testroom analiz raporunu incelemek istiyorum.') ?>" target="_blank" rel="noopener">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm.02 18.06c-1.49 0-2.95-.4-4.22-1.16l-.3-.18-3.13.82.84-3.05-.2-.31a7.9 7.9 0 0 1-1.22-4.27c0-4.41 3.59-8 8.02-8a7.95 7.95 0 0 1 5.67 2.35 7.94 7.94 0 0 1 2.35 5.66c0 4.42-3.59 8.01-8.03 8.01zm4.39-6c-.24-.12-1.42-.7-1.64-.78-.22-.08-.38-.12-.55.12-.16.24-.63.78-.77.94-.14.16-.29.18-.53.06-.24-.12-1.02-.38-1.95-1.2-.72-.64-1.2-1.44-1.34-1.68-.14-.24-.02-.37.1-.49.11-.11.24-.29.36-.43.12-.14.16-.24.24-.4.08-.16.04-.31-.02-.43s-.55-1.33-.76-1.82c-.2-.48-.41-.41-.56-.42l-.48-.01c-.16 0-.43.06-.66.31-.22.24-.87.85-.87 2.08s.89 2.41 1.01 2.58c.12.16 1.76 2.68 4.25 3.76.59.26 1.06.41 1.42.53.6.19 1.14.16 1.57.1.48-.07 1.48-.6 1.69-1.19.21-.58.21-1.08.15-1.19-.06-.11-.22-.18-.46-.3z"/></svg>
                Örnek Raporu İnceleyin
            </a>
        </div>
        <div class="disclaimer-note">
            ⚠️ <strong>Bilgilendirme:</strong> Test ve analizler rehberliği destekleyen araçlardır; klinik tanı koymaz ve öğrencinin geleceğini kesin olarak belirlemez. Kullanılacak değerlendirmeler öğrencinin yaşına, ihtiyacına ve hizmet kapsamına göre seçilir.
        </div>
    </section>
<?php elseif ($pageContent !== null): ?>
    <?php if (!empty($pageContent['image_url'])): ?>
        <section class="page-hero page-hero-with-image">
            <div class="page-hero-copy">
                <p class="eyebrow eyebrow-accent"><?= e($pageContent['eyebrow']) ?></p>
                <h1><?= $pageContent['heading'] ?></h1>
                <span class="accent-bar"></span>
                <p class="lead"><?= e($pageContent['intro']) ?></p>
            </div>
            <div class="page-hero-media">
                <img src="<?= e($pageContent['image_url']) ?>" alt="<?= e(strip_tags((string) $pageContent['heading'])) ?>" class="page-hero-img" loading="lazy">
            </div>
        </section>
    <?php else: ?>
        <section class="page-hero">
            <p class="eyebrow eyebrow-accent"><?= e($pageContent['eyebrow']) ?></p>
            <h1><?= $pageContent['heading'] ?></h1>
            <span class="accent-bar"></span>
            <p class="lead"><?= e($pageContent['intro']) ?></p>
        </section>
    <?php endif; ?>
    <section class="content-sections">
        <?php foreach ($pageContent['sections'] as $index => [$heading, $body]): ?>
            <article class="content-row">
                <span class="feature-number"><?= sprintf('%02d', $index + 1) ?></span>
                <div>
                    <h2><?= e($heading) ?></h2>
                    <p><?= e($body) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
    <?php if (!empty($pageContent['cta'])): ?>
        <p class="inline-note"><?= e($pageContent['cta']) ?></p>
    <?php endif; ?>
    <div class="page-cta">
        <a class="button button-primary" href="/iletisim">Ön görüşme talep et</a>
    </div>
<?php else: ?>
    <section class="page-hero"><p class="eyebrow">404</p><h1>Bu sayfa bulunamadı.</h1><p class="lead">Aradığınız içerik taşınmış veya kaldırılmış olabilir.</p><a class="button button-primary" href="/">Ana sayfaya dön</a></section>
<?php endif; ?>
</main>
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-top">
            <div class="footer-brand-col">
                <a class="brand brand-footer" href="/" aria-label="Salihli Koçluk">
                    <span class="brand-mark">S</span>
                    <span>Salihli<span class="brand-light">Koçluk</span></span>
                </a>
                <p class="footer-tagline"><?= e(setting('coach_title', 'Salihli YKS Öğrenci Koçu')) ?></p>
                <p class="footer-desc">Salihli merkez ofisimizde TYT, AYT ve mezun öğrencilerine yönelik birebir yüz yüze ve hibrit takip ile hedefe odaklı koçluk rehberliği.</p>
                <div class="footer-badges">
                    <span class="footer-badge-pill">📍 Salihli Merkez Ofis</span>
                    <span class="footer-badge-pill">🎓 Resmi Sertifikalı</span>
                </div>
            </div>

            <div class="footer-contact-card">
                <h4>İletişim &amp; Ofis</h4>
                <div class="footer-contact-list">
                    <?php if ($phone = setting('phone')): ?>
                        <div class="contact-row">
                            <span class="contact-icon">📞</span>
                            <div>
                                <small>Telefon &amp; Randevu</small>
                                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($wa = (setting('whatsapp') ?: setting('phone'))): ?>
                        <?php $waNum = preg_replace('/[^0-9]/', '', $wa); ?>
                        <?php if ($waNum): ?>
                            <div class="contact-row">
                                <span class="contact-icon contact-icon-wa">💬</span>
                                <div>
                                    <small>WhatsApp İletişim</small>
                                    <a href="https://wa.me/<?= e($waNum) ?>" target="_blank" rel="noopener">Mesaj Gönder (Aktif) →</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($email = setting('email')): ?>
                        <div class="contact-row">
                            <span class="contact-icon">✉️</span>
                            <div>
                                <small>E-Posta</small>
                                <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($instaUrl = get_instagram_url()): ?>
                        <div class="contact-row">
                            <span class="contact-icon">📷</span>
                            <div>
                                <small>Instagram</small>
                                <a href="<?= e($instaUrl) ?>" target="_blank" rel="noopener"><?= e(get_instagram_handle()) ?></a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="footer-nav-grid">
            <div class="footer-nav-col">
                <h5>Koçluk Programları</h5>
                <a href="/yks">YKS Koçluğu</a>
                <a href="/tyt">TYT Çalışma Sistemi</a>
                <a href="/ayt">AYT Stratejisi</a>
                <a href="/lgs">LGS &amp; 8. Sınıf Koçluğu</a>
                <a href="/mezun">Mezun Koçluğu</a>
            </div>
            <div class="footer-nav-col">
                <h5>Süreç &amp; Yaklaşım</h5>
                <a href="/veli">Veli Danışmanlığı</a>
                <a href="/surec">Süreç ve Ücretler</a>
                <a href="/yazilim">Dijital &amp; Yapay Zekâ</a>
                <a href="/#sss">Sıkça Sorulan Sorular</a>
            </div>
            <div class="footer-nav-col">
                <h5>Kurumsal &amp; İletişim</h5>
                <a href="/hakkimda">Koç Hakkında</a>
                <a href="/blog">YKS Rehber Blogu</a>
                <a href="/iletisim">Ön Görüşme Randevusu</a>
                <a href="/gizlilik">Gizlilik ve KVKK</a>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-bottom-copy">
                <p>© <?= date('Y') ?> <?= e(setting('coach_name', 'Salihli Koçluk')) ?>. Tüm hakları saklıdır.</p>
                <small>Koçluk süreci kişiye özel olarak planlanır ve yürütülür.</small>
            </div>
            <div class="footer-bottom-links">
                <a href="/gizlilik">Gizlilik</a>
                <a href="/sitemap.php">Sitemap</a>
                <a href="/admin/">Yönetim Paneli</a>
            </div>
        </div>
    </div>
</footer>

<?php if ($wa = (setting('whatsapp') ?: setting('phone'))): ?>
    <?php $waClean = preg_replace('/[^0-9]/', '', $wa); ?>
    <?php if ($waClean): ?>
        <a href="https://wa.me/<?= e($waClean) ?>" class="floating-whatsapp" target="_blank" rel="noopener" aria-label="WhatsApp ile iletişime geçin">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm.02 18.06c-1.49 0-2.95-.4-4.22-1.16l-.3-.18-3.13.82.84-3.05-.2-.31a7.9 7.9 0 0 1-1.22-4.27c0-4.41 3.59-8 8.02-8a7.95 7.95 0 0 1 5.67 2.35 7.94 7.94 0 0 1 2.35 5.66c0 4.42-3.59 8.01-8.03 8.01zm4.39-6c-.24-.12-1.42-.7-1.64-.78-.22-.08-.38-.12-.55.12-.16.24-.63.78-.77.94-.14.16-.29.18-.53.06-.24-.12-1.02-.38-1.95-1.2-.72-.64-1.2-1.44-1.34-1.68-.14-.24-.02-.37.1-.49.11-.11.24-.29.36-.43.12-.14.16-.24.24-.4.08-.16.04-.31-.02-.43s-.55-1.33-.76-1.82c-.2-.48-.41-.41-.56-.42l-.48-.01c-.16 0-.43.06-.66.31-.22.24-.87.85-.87 2.08s.89 2.41 1.01 2.58c.12.16 1.76 2.68 4.25 3.76.59.26 1.06.41 1.42.53.6.19 1.14.16 1.57.1.48-.07 1.48-.6 1.69-1.19.21-.58.21-1.08.15-1.19-.06-.11-.22-.18-.46-.3z"/>
            </svg>
            <span>WhatsApp</span>
        </a>
    <?php endif; ?>
<?php endif; ?>
<?php if (!empty($visitorLogId)): ?>
<script>window.VISITOR_LOG_ID = <?= (int) $visitorLogId ?>;</script>
<?php endif; ?>
<script src="/assets/js/site.js" defer></script>
</body>
</html>
