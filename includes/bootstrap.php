<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Istanbul');

// Canlı Yayın / Prodüksiyon Güvenlik ve Hata Ayarları
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

define('ROOT_PATH', dirname(__DIR__));

$storagePath = ROOT_PATH . '/storage';
if (!is_dir($storagePath) && !mkdir($storagePath, 0750, true) && !is_dir($storagePath)) {
    throw new RuntimeException('Veri klasörü oluşturulamadı.');
}

$sessionPath = $storagePath . '/sessions';
if (!is_dir($sessionPath) && !mkdir($sessionPath, 0700, true) && !is_dir($sessionPath)) {
    $sessionPath = sys_get_temp_dir();
}

if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
} else {
    session_save_path(sys_get_temp_dir());
}

if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
    session_name('SALIHLIKOCLUK');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

$db = new PDO('sqlite:' . $storagePath . '/site.sqlite', options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec(
    'CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        excerpt TEXT NOT NULL,
        content TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT "draft" CHECK (status IN ("draft", "published")),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS leads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        phone TEXT NOT NULL,
        email TEXT NOT NULL DEFAULT "",
        student_level TEXT NOT NULL DEFAULT "",
        message TEXT NOT NULL DEFAULT "",
        privacy_acknowledged INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT "new" CHECK (status IN ("new", "contacted", "closed")),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT ""
    );
    CREATE TABLE IF NOT EXISTS slides (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        badge TEXT NOT NULL DEFAULT "",
        title TEXT NOT NULL,
        subtitle TEXT NOT NULL DEFAULT "",
        button_text TEXT NOT NULL DEFAULT "",
        button_link TEXT NOT NULL DEFAULT "",
        image_url TEXT NOT NULL DEFAULT "",
        sort_order INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS site_pages (
        slug TEXT PRIMARY KEY,
        title TEXT NOT NULL DEFAULT "",
        meta_description TEXT NOT NULL DEFAULT "",
        eyebrow TEXT NOT NULL DEFAULT "",
        heading TEXT NOT NULL DEFAULT "",
        intro TEXT NOT NULL DEFAULT "",
        sections_json TEXT NOT NULL DEFAULT "[]",
        cta_text TEXT NOT NULL DEFAULT "",
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS faqs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        question TEXT NOT NULL,
        answer TEXT NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        sort_order INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )'
);
$leadColumns = array_column($db->query('PRAGMA table_info(leads)')->fetchAll(), 'name');
if (!in_array('privacy_acknowledged', $leadColumns, true)) {
    $db->exec('ALTER TABLE leads ADD COLUMN privacy_acknowledged INTEGER NOT NULL DEFAULT 0');
}

$postColumns = array_column($db->query('PRAGMA table_info(posts)')->fetchAll(), 'name');
if (!in_array('category_id', $postColumns, true)) {
    $db->exec('ALTER TABLE posts ADD COLUMN category_id INTEGER DEFAULT 1');
}
if (!in_array('image_url', $postColumns, true)) {
    $db->exec('ALTER TABLE posts ADD COLUMN image_url TEXT DEFAULT ""');
}

$pageColumns = array_column($db->query('PRAGMA table_info(site_pages)')->fetchAll(), 'name');
if (!in_array('image_url', $pageColumns, true)) {
    $db->exec('ALTER TABLE site_pages ADD COLUMN image_url TEXT DEFAULT ""');
}

$slideColumns = array_column($db->query('PRAGMA table_info(slides)')->fetchAll(), 'name');
if (!in_array('image_url', $slideColumns, true)) {
    $db->exec('ALTER TABLE slides ADD COLUMN image_url TEXT DEFAULT ""');
}

// Seed default categories if empty
$catCount = (int) $db->query('SELECT COUNT(*) FROM categories')->fetchColumn();
if ($catCount === 0) {
    $seedCategories = [
        ['name' => 'TYT Koçluğu', 'slug' => 'tyt-koclugu', 'sort_order' => 1],
        ['name' => 'AYT Koçluğu', 'slug' => 'ayt-koclugu', 'sort_order' => 2],
        ['name' => 'LGS Koçluğu', 'slug' => 'lgs-koclugu', 'sort_order' => 3],
        ['name' => 'Sınav Kaygısı & Motivasyon', 'slug' => 'sinav-kaygisi', 'sort_order' => 4],
        ['name' => 'Deneme Analizi & Takip', 'slug' => 'deneme-analizi', 'sort_order' => 5],
        ['name' => 'Veriye Dayalı Koçluk', 'slug' => 'veriye-dayali-kocluk', 'sort_order' => 6],
        ['name' => 'Veli Rehberi', 'slug' => 'veli-rehberi', 'sort_order' => 7],
    ];
    $catStmt = $db->prepare('INSERT INTO categories (name, slug, sort_order) VALUES (:name, :slug, :sort_order)');
    foreach ($seedCategories as $cat) {
        $catStmt->execute($cat);
    }

    // Auto-map initial existing posts to their matching category
    $categoryMap = [
        'salihlide-yks-koclugu-dershane-mi-birebir-ogrenci-koclugu-mu' => 'sinav-kaygisi',
        'tyt-ve-ayt-netleri-neden-yukselmez-5-kritik-hata' => 'tyt-koclugu',
        'mezuna-kalanlar-icin-yks-rehberi-salihlide-derece-stratejisi' => 'ayt-koclugu',
        'salihlide-ogrenci-koclugu-fiyatlari-ve-dershane-karsilastirmasi' => 'tyt-koclugu',
        'salihli-lgs-koclugu-8-sinif-ogrencileri-icin-rehber' => 'lgs-koclugu',
        'cocugum-ders-calismiyor-salihli-veli-rehberi' => 'veli-rehberi',
        'salihli-yks-ve-mezun-koclugu-net-artirma-rehberi' => 'deneme-analizi',
        'salihlide-dijital-ve-yapay-zeka-destekli-ogrenci-koclugu' => 'veriye-dayali-kocluk',
        'ogrenciyi-taniyarak-baslayan-veriye-dayali-kocluk-testroom' => 'veriye-dayali-kocluk',
    ];
    foreach ($categoryMap as $postSlug => $catSlug) {
        $catId = $db->query("SELECT id FROM categories WHERE slug = '$catSlug'")->fetchColumn();
        if ($catId) {
            $db->prepare("UPDATE posts SET category_id = :cat_id WHERE slug = :slug")->execute([
                'cat_id' => $catId,
                'slug' => $postSlug,
            ]);
        }
    }
}

$defaultSettings = [
    'coach_name' => 'Salihli YKS Koçu',
    'coach_title' => 'YKS & Öğrenci Koçu',
    'coach_bio' => 'Salihli’deki fiziksel ofisimizde ağırlıklı olarak birebir yüz yüze, gerektiğinde online/hibrit destekle TYT, AYT ve mezun öğrencilere kişiye özel YKS koçluğu sunuyorum.',
    'coach_education' => 'Resmi onaylı profesyonel öğrenci koçluğu sertifikası ve eğitim danışmanlığı arka planı.',
    'coach_approach' => 'Her öğrencinin çalışma temposu ve hedefleri kendine özgüdür. Ofisimizde yüz yüze periyodik görüşmeler, düzenli hedef ve deneme takibi ile disiplinli ve sürdürülebilir bir hazırlık süreci yürütüyoruz.',
    'phone' => '+90 544 344 24 49',
    'whatsapp' => '905443442449',
    'email' => 'iletisim@salihlikocluk.com',
    'location' => 'Salihli, Manisa (Fiziksel Ofis)',
    'working_model' => 'Salihli’de Yüz Yüze (Fiziksel Ofis) & Hibrit',
    'instagram' => '',
    'seo_title_suffix' => ' | Salihli Koçluk',
    'seo_default_description' => 'Salihli’de TYT, AYT ve mezun öğrencilere yönelik fiziksel ofisimizde yüz yüze ve hibrit YKS koçluğu.',
    'seo_keywords' => 'salihli yks koçluğu, salihli öğrenci koçu, tyt koçluğu, ayt koçluğu, mezun koçluğu salihli',
    'seo_google_verification' => '',
    'seo_analytics_id' => '',
    'notify_email' => 'iletisim@salihlikocluk.com',
    'notify_email_enabled' => '1',
    'notify_telegram_bot_token' => '',
    'notify_telegram_chat_id' => '',
    'notify_webhook_url' => '',
    'office_address' => 'Salihli Merkez, Manisa',
    'geo_latitude' => '38.4819',
    'geo_longitude' => '28.1345',
    'google_maps_url' => 'https://maps.google.com/?q=Salihli,Manisa',
    'opening_hours' => 'Pazartesi - Cumartesi: 09:00 - 19:30',
    'service_areas' => 'Salihli, Ahmetli, Alaşehir, Kula, Sart, Köprübaşı',
];

$existingSettingsCount = (int) $db->query('SELECT COUNT(*) FROM settings')->fetchColumn();
if ($existingSettingsCount === 0) {
    $seedStmt = $db->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (:key, :value)');
    foreach ($defaultSettings as $k => $v) {
        $seedStmt->execute(['key' => $k, 'value' => $v]);
    }
} else {
    // Ensure any new default keys exist
    $ensureStmt = $db->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (:key, :value)');
    foreach ($defaultSettings as $k => $v) {
        $ensureStmt->execute(['key' => $k, 'value' => $v]);
    }
}

$slidesCount = (int) $db->query('SELECT COUNT(*) FROM slides')->fetchColumn();
if ($slidesCount === 0) {
    $seedSlides = [
        [
            'badge' => "SALİHLİ OFİS & YÜZ YÜZE",
            'title' => 'YKS yolculuğunda planlı ve kararlı ilerle.',
            'subtitle' => "Salihli'deki fiziksel ofisimizde ağırlıklı yüz yüze, resmi sertifikalı koçluk ve yazılım destekli takip.",
            'button_text' => 'Ön görüşme talep et',
            'button_link' => '?page=iletisim',
            'sort_order' => 1,
            'is_active' => 1,
        ],
        [
            'badge' => 'TYT & AYT HAZIRLIK',
            'title' => 'Hedefine uygun birebir çalışma temposu.',
            'subtitle' => 'Haftalık hedef takibi, deneme değerlendirmesi ve kişiselleştirilmiş analizlerle sınav temposunu yönet.',
            'button_text' => 'Koçluk sürecini keşfet',
            'button_link' => '?page=surec',
            'sort_order' => 2,
            'is_active' => 1,
        ],
        [
            'badge' => 'MEZUN ÖĞRENCİ KOÇLUĞU',
            'title' => 'Yeni bir başlangıç için sağlam çalışma disiplini.',
            'subtitle' => 'Önceki dönemin tecrübesiyle eksikleri hızla kapatıp sürdürülebilir bir yol haritasıyla hedefine ulaş.',
            'button_text' => 'Hemen iletişime geç',
            'button_link' => '?page=iletisim',
            'sort_order' => 3,
            'is_active' => 1,
        ],
    ];
    $slideStmt = $db->prepare('INSERT INTO slides (badge, title, subtitle, button_text, button_link, sort_order, is_active) VALUES (:badge, :title, :subtitle, :button_text, :button_link, :sort_order, :is_active)');
    foreach ($seedSlides as $s) {
        $slideStmt->execute($s);
    }
}

$faqsCount = (int) $db->query('SELECT COUNT(*) FROM faqs')->fetchColumn();
if ($faqsCount === 0) {
    $seedFaqs = [
        [
            'question' => 'Görüşmeler nerede ve nasıl yapılıyor?',
            'answer' => "Salihli merkezdeki fiziksel ofisimizde ağırlıklı olarak birebir ve yüz yüze görüşmeler yapıyoruz. Öğrencinin koşullarına ve ihtiyaçlarına göre hibrit (gerektiğinde online) takip imkânı da sunuyoruz.",
            'sort_order' => 1,
            'is_active' => 1,
        ],
        [
            'question' => 'Öğrenci koçluğu ile özel ders veya dershane arasındaki fark nedir?',
            'answer' => "Koçluk bir ders anlatımı değildir. Koçluk; öğrencinin hedeflerine uygun haftalık çalışma planının hazırlanması, eksiklerin analizi, deneme takibi, zaman yönetimi, sınav kaygısı ve motivasyon sürecinin resmi sertifikalı rehberlikle birebir yönetilmesidir.",
            'sort_order' => 2,
            'is_active' => 1,
        ],
        [
            'question' => 'Koçluk sürecinde veli iletişimi nasıl yürütülüyor?',
            'answer' => "YKS süreci öğrenci kadar aileyi de etkileyen bir maratondur. Belirli aralıklarla velimizle görüşerek öğrencinin gelişim grafiği, çalışma temposu ve deneme analizleri hakkında şeffaf ve yapıcı bir bilgilendirme yapıyoruz.",
            'sort_order' => 3,
            'is_active' => 1,
        ],
        [
            'question' => 'Deneme sınavı analizleri ve net takibi nasıl yapılıyor?',
            'answer' => "Öğrencinin girdiği tüm TYT ve AYT deneme sonuçları detaylı olarak incelenir. Netler tek başına bir başarı veya başarısızlık ölçütü değil; hangi ders ve konularda eksik kalındığını tespit etmek ve bir sonraki haftanın çalışma hedeflerini somutlaştırmak için analiz edilir.",
            'sort_order' => 4,
            'is_active' => 1,
        ],
        [
            'question' => 'Ön görüşme ücretli mi, nasıl randevu alabilirim?',
            'answer' => "İlk tanışma ve ön görüşmemiz tamamen ücretsizdir. Salihli ofisimizde bir araya gelerek öğrencinin hedeflerini konuşmak ve sürecin uygunluğunu değerlendirmek için web sitemizdeki formdan ya da WhatsApp üzerinden doğrudan randevu talep edebilirsiniz.",
            'sort_order' => 5,
            'is_active' => 1,
        ],
    ];
    $faqStmt = $db->prepare('INSERT INTO faqs (question, answer, sort_order, is_active) VALUES (:question, :answer, :sort_order, :is_active)');
    foreach ($seedFaqs as $f) {
        $faqStmt->execute($f);
    }
}

function get_slides(bool $activeOnly = true): array
{
    global $db;
    $sql = 'SELECT * FROM slides ' . ($activeOnly ? 'WHERE is_active = 1 ' : '') . 'ORDER BY sort_order ASC, id ASC';

    return $db->query($sql)->fetchAll();
}

function get_faqs(bool $activeOnly = true): array
{
    global $db;
    $sql = 'SELECT * FROM faqs ' . ($activeOnly ? 'WHERE is_active = 1 ' : '') . 'ORDER BY sort_order ASC, id ASC';

    return $db->query($sql)->fetchAll();
}

function get_categories(bool $activeOnly = true): array
{
    global $db;
    $sql = 'SELECT * FROM categories ' . ($activeOnly ? 'WHERE is_active = 1 ' : '') . 'ORDER BY sort_order ASC, id ASC';

    return $db->query($sql)->fetchAll();
}

function get_posts(int $limit = 0, bool $publishedOnly = true, ?string $categorySlug = null): array
{
    global $db;
    $params = [];
    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug 
            FROM posts p 
            LEFT JOIN categories c ON p.category_id = c.id ';
    
    $where = [];
    if ($publishedOnly) {
        $where[] = "p.status = 'published'";
    }
    if ($categorySlug !== null && $categorySlug !== '' && $categorySlug !== 'tumu') {
        $where[] = "c.slug = :category_slug";
        $params['category_slug'] = $categorySlug;
    }
    
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    
    $sql .= ' ORDER BY p.created_at DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function estimate_reading_time(string $text): int
{
    $wordCount = count(preg_split('/\s+/', strip_tags($text), -1, PREG_SPLIT_NO_EMPTY));
    $minutes = (int) ceil($wordCount / 180);
    return max(2, $minutes);
}

function get_category_color_meta(string $slug, int $index = 0): array
{
    $colors = [
        'tyt-koclugu' => [
            'icon' => '📘',
            'tag_color' => '#2563eb',
            'gradient' => 'linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%)',
        ],
        'ayt-koclugu' => [
            'icon' => '🎯',
            'tag_color' => '#7c3aed',
            'gradient' => 'linear-gradient(135deg, #4c1d95 0%, #7c3aed 100%)',
        ],
        'lgs-koclugu' => [
            'icon' => '🎒',
            'tag_color' => '#059669',
            'gradient' => 'linear-gradient(135deg, #064e3b 0%, #059669 100%)',
        ],
        'sinav-kaygisi' => [
            'icon' => '🧘',
            'tag_color' => '#ea580c',
            'gradient' => 'linear-gradient(135deg, #7c2d12 0%, #ea580c 100%)',
        ],
        'deneme-analizi' => [
            'icon' => '📊',
            'tag_color' => '#dc2626',
            'gradient' => 'linear-gradient(135deg, #7f1d1d 0%, #dc2626 100%)',
        ],
        'veriye-dayali-kocluk' => [
            'icon' => '⚡',
            'tag_color' => '#0d9488',
            'gradient' => 'linear-gradient(135deg, #134e4a 0%, #0d9488 100%)',
        ],
        'veli-rehberi' => [
            'icon' => '👨‍👩‍👧',
            'tag_color' => '#db2777',
            'gradient' => 'linear-gradient(135deg, #831843 0%, #db2777 100%)',
        ],
    ];

    if (isset($colors[$slug])) {
        return $colors[$slug];
    }

    $palette = [
        ['icon' => '🏷️', 'tag_color' => '#2563eb', 'gradient' => 'linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%)'],
        ['icon' => '🏷️', 'tag_color' => '#7c3aed', 'gradient' => 'linear-gradient(135deg, #4c1d95 0%, #7c3aed 100%)'],
        ['icon' => '🏷️', 'tag_color' => '#059669', 'gradient' => 'linear-gradient(135deg, #064e3b 0%, #059669 100%)'],
    ];

    return $palette[$index % count($palette)];
}

function handle_file_upload(array $file): ?string
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    if (($file['size'] ?? 0) > 8 * 1024 * 1024) {
        return null; // Max 8MB
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
    ];

    if (!isset($allowedMimes[$mime])) {
        return null;
    }

    $ext = $allowedMimes[$mime];
    $origName = pathinfo((string) ($file['name'] ?? 'gorsel'), PATHINFO_FILENAME);
    $cleanName = slugify($origName);
    if ($cleanName === '') {
        $cleanName = 'gorsel';
    }

    $fileName = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6) . '_' . substr($cleanName, 0, 30) . '.' . $ext;
    $targetDir = dirname(__DIR__) . '/public/assets/uploads';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $targetPath = $targetDir . '/' . $fileName;
    if (move_uploaded_file($tmp, $targetPath)) {
        return '/assets/uploads/' . $fileName;
    }

    return null;
}

require_once __DIR__ . '/ai_generator.php';
require_once __DIR__ . '/analytics.php';

function render_markdown_content(string $text): string
{
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
    $html = '';
    $inList = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            continue;
        }

        // Markdown blockquote / TL;DR Callout Box
        if (str_starts_with($trimmed, '> ')) {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            $quoteBody = trim(substr($trimmed, 2));
            if (str_starts_with($quoteBody, '[!NOTE]') || str_starts_with($quoteBody, '[!TIP]') || stripos($quoteBody, 'Doğrudan Cevap') !== false || stripos($quoteBody, 'Özet') !== false) {
                $cleanBody = preg_replace('/^\[!(NOTE|TIP)\]\s*/i', '', $quoteBody);
                $cleanBody = htmlspecialchars($cleanBody, ENT_QUOTES, 'UTF-8');
                $cleanBody = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $cleanBody);
                $html .= "<div class=\"article-direct-answer-box\"><div class=\"direct-answer-badge\">⚡ Doğrudan Cevap &amp; AI Özeti (TL;DR)</div><p>{$cleanBody}</p></div>\n";
            } else {
                $quoteText = htmlspecialchars($quoteBody, ENT_QUOTES, 'UTF-8');
                $quoteText = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $quoteText);
                $html .= "<blockquote class=\"article-blockquote\"><p>{$quoteText}</p></blockquote>\n";
            }
            continue;
        }

        // Markdown image: ![Alt Text](url)
        if (preg_match('/^!\[(.*?)\]\((.*?)\)$/', $trimmed, $imgMatches)) {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            $alt = htmlspecialchars($imgMatches[1], ENT_QUOTES, 'UTF-8');
            $src = htmlspecialchars($imgMatches[2], ENT_QUOTES, 'UTF-8');
            $html .= "<figure class=\"article-figure\"><img src=\"{$src}\" alt=\"{$alt}\" loading=\"lazy\" class=\"article-inline-img\">" . ($alt !== '' ? "<figcaption class=\"article-figcaption\">{$alt}</figcaption>" : '') . "</figure>\n";
            continue;
        }

        if (str_starts_with($trimmed, '### ')) {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            $rawText = trim(substr($trimmed, 4));
            $anchor = slugify($rawText);
            $headingText = htmlspecialchars($rawText, ENT_QUOTES, 'UTF-8');
            $html .= "<h3 id=\"{$anchor}\" class=\"article-subtitle\">{$headingText}</h3>\n";
        } elseif (str_starts_with($trimmed, '## ')) {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            $rawText = trim(substr($trimmed, 3));
            $anchor = slugify($rawText);
            $headingText = htmlspecialchars($rawText, ENT_QUOTES, 'UTF-8');
            $html .= "<h2 id=\"{$anchor}\" class=\"article-subtitle\">{$headingText}</h2>\n";
        } elseif (str_starts_with($trimmed, '- ') || str_starts_with($trimmed, '* ')) {
            if (!$inList) {
                $html .= "<ul class=\"article-list\">\n";
                $inList = true;
            }
            $itemText = htmlspecialchars(trim(substr($trimmed, 2)), ENT_QUOTES, 'UTF-8');
            $itemText = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $itemText);
            $html .= "<li>{$itemText}</li>\n";
        } else {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            $pText = htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8');
            $pText = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $pText);
            $html .= "<p>{$pText}</p>\n";
        }
    }

    if ($inList) {
        $html .= "</ul>\n";
    }

    return $html;
}

function extract_table_of_contents(string $text): array
{
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
    $toc = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '## ')) {
            $raw = trim(substr($trimmed, 3));
            $toc[] = [
                'level' => 2,
                'title' => $raw,
                'id' => slugify($raw)
            ];
        } elseif (str_starts_with($trimmed, '### ')) {
            $raw = trim(substr($trimmed, 4));
            $toc[] = [
                'level' => 3,
                'title' => $raw,
                'id' => slugify($raw)
            ];
        }
    }
    return $toc;
}

function extract_article_faqs(string $text): array
{
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
    $faqs = [];
    $inFaqSection = false;
    $currentQ = '';
    $currentA = '';

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (stripos($trimmed, 'Sıkça Sorulan Sorular') !== false || stripos($trimmed, 'SSS') !== false) {
            $inFaqSection = true;
            continue;
        }

        if ($inFaqSection) {
            if (str_starts_with($trimmed, '### ') || str_starts_with($trimmed, '## ')) {
                if ($currentQ !== '' && $currentA !== '') {
                    $faqs[] = ['question' => $currentQ, 'answer' => trim($currentA)];
                    $currentA = '';
                }
                $currentQ = trim(preg_replace('/^#+\s*/', '', $trimmed));
            } elseif ($currentQ !== '' && $trimmed !== '') {
                $currentA .= ($currentA !== '' ? ' ' : '') . $trimmed;
            }
        }
    }
    if ($currentQ !== '' && $currentA !== '') {
        $faqs[] = ['question' => $currentQ, 'answer' => trim($currentA)];
    }
    return $faqs;
}

function get_page_content(string $slug, array $fallback = []): array
{
    global $db;
    $stmt = $db->prepare('SELECT * FROM site_pages WHERE slug = :slug');
    $stmt->execute(['slug' => $slug]);
    $row = $stmt->fetch();
    if (!$row) {
        return $fallback;
    }
    $sections = json_decode((string) $row['sections_json'], true);

    return [
        'title' => !empty($row['title']) ? $row['title'] : ($fallback['title'] ?? ''),
        'description' => !empty($row['meta_description']) ? $row['meta_description'] : ($fallback['description'] ?? ''),
        'eyebrow' => !empty($row['eyebrow']) ? $row['eyebrow'] : ($fallback['eyebrow'] ?? ''),
        'heading' => !empty($row['heading']) ? $row['heading'] : ($fallback['title'] ?? ($fallback['heading'] ?? '')),
        'intro' => !empty($row['intro']) ? $row['intro'] : ($fallback['intro'] ?? ''),
        'image_url' => (string) ($row['image_url'] ?? ''),
        'sections' => is_array($sections) && $sections ? $sections : ($fallback['sections'] ?? []),
        'cta' => !empty($row['cta_text']) ? $row['cta_text'] : ($fallback['cta'] ?? ''),
    ];
}

function get_settings(): array
{
    global $db;
    static $settings = null;
    if ($settings === null) {
        $rows = $db->query('SELECT key, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $settings = is_array($rows) ? $rows : [];
    }

    return $settings;
}

function setting(string $key, string $default = ''): string
{
    $settings = get_settings();
    return !empty($settings[$key]) ? (string) $settings[$key] : $default;
}

function get_instagram_url(?string $val = null): string
{
    $val = trim((string)($val ?? setting('instagram', '')));
    if ($val === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $val)) {
        return $val;
    }
    return 'https://instagram.com/' . ltrim($val, '@/');
}

function get_instagram_handle(?string $val = null): string
{
    $val = trim((string)($val ?? setting('instagram', '')));
    if ($val === '') {
        return '';
    }
    if (preg_match('~instagram\.com/([^/?#]+)~i', $val, $m)) {
        return '@' . ltrim($m[1], '@');
    }
    return '@' . ltrim($val, '@');
}

function update_settings(array $data): void
{
    global $db;
    $statement = $db->prepare('INSERT INTO settings (key, value) VALUES (:key, :value) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    foreach ($data as $key => $value) {
        $statement->execute(['key' => (string) $key, 'value' => trim((string) $value)]);
    }
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function is_admin(): bool
{
    return isset($_SESSION['admin_id']);
}

function slugify(string $text): string
{
    $text = strtr(mb_strtolower(trim($text), 'UTF-8'), [
        'ı' => 'i', 'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ö' => 'o', 'ç' => 'c',
    ]);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $text);

    return trim($slug ?? '', '-');
}

function send_lead_notifications(array $lead, bool $isTest = false): array
{
    $results = [];
    $cleanPhone = preg_replace('/[^0-9]/', '', (string) ($lead['phone'] ?? ''));
    $waLink = $cleanPhone ? 'https://wa.me/' . $cleanPhone : '';
    $dateStr = date('d.m.Y H:i', strtotime($lead['created_at'] ?? 'now'));
    $testPrefix = $isTest ? '[TEST BİLDİRİMİ] ' : '';

    // 1. E-POSTA BİLDİRİMİ
    $emailEnabled = setting('notify_email_enabled', '1') === '1';
    $toEmail = trim(setting('notify_email', setting('email', 'iletisim@salihlikocluk.com')));

    if ($emailEnabled && !empty($toEmail)) {
        $subject = "=?UTF-8?B?" . base64_encode("{$testPrefix}🔔 Yeni Ön Görüşme Talebi: " . ($lead['name'] ?? 'İsimsiz') . " (" . ($lead['student_level'] ?? 'Genel') . ")") . "?=";
        
        $html = '<!DOCTYPE html>
<html lang="tr">
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #f8fafc; padding: 24px; color: #0f172a; margin: 0;">
    <div style="max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.05);">
        <div style="background: #dc2626; padding: 20px 24px; color: #ffffff;">
            <h2 style="margin: 0; font-size: 20px; font-weight: 800;">Salihli Koçluk — ' . ($isTest ? 'Test Bildirimi' : 'Yeni Ön Görüşme Talebi') . '</h2>
            <p style="margin: 4px 0 0; font-size: 13px; opacity: 0.9;">Web sitenizden yeni bir randevu/ön görüşme formu iletildi.</p>
        </div>
        <div style="padding: 24px;">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <tr>
                    <td style="padding: 8px 0; color: #64748b; width: 140px; font-weight: 600;">Adı Soyadı:</td>
                    <td style="padding: 8px 0; color: #0f172a; font-weight: 700; font-size: 15px;">' . htmlspecialchars((string)($lead['name'] ?? '')) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Telefon:</td>
                    <td style="padding: 8px 0; font-weight: 700;">
                        <a href="tel:' . htmlspecialchars((string)($lead['phone'] ?? '')) . '" style="color: #dc2626; text-decoration: none;">' . htmlspecialchars((string)($lead['phone'] ?? '')) . '</a>
                        ' . ($waLink ? ' &nbsp;•&nbsp; <a href="' . htmlspecialchars($waLink) . '" style="color: #25d366; text-decoration: none; font-weight: bold;">WhatsApp ile Yaz →</a>' : '') . '
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Öğrenci Durumu:</td>
                    <td style="padding: 8px 0; color: #0f172a;">' . htmlspecialchars((string)($lead['student_level'] ?: 'Belirtilmedi')) . '</td>
                </tr>
                ' . (!empty($lead['email']) ? '<tr>
                    <td style="padding: 8px 0; color: #64748b; font-weight: 600;">E-posta:</td>
                    <td style="padding: 8px 0;"><a href="mailto:' . htmlspecialchars((string)$lead['email']) . '" style="color: #1877f2;">' . htmlspecialchars((string)$lead['email']) . '</a></td>
                </tr>' : '') . '
                <tr>
                    <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Tarih & Saat:</td>
                    <td style="padding: 8px 0; color: #0f172a;">' . $dateStr . '</td>
                </tr>
            </table>

            <div style="margin-top: 18px; padding: 16px; background: #f1f5f9; border-radius: 8px; border-left: 4px solid #dc2626;">
                <strong style="display: block; font-size: 12px; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Öğrenci / Veli Mesajı:</strong>
                <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #334155; white-space: pre-wrap;">' . htmlspecialchars((string)($lead['message'] ?: 'Mesaj yazılmadı.')) . '</p>
            </div>

            <div style="margin-top: 24px; text-align: center;">
                <a href="https://salihlikocluk.com/admin/?tab=leads" style="display: inline-block; padding: 12px 24px; background: #0f172a; color: #ffffff; text-decoration: none; font-weight: 700; border-radius: 8px; font-size: 14px;">Yönetim Paneline Git →</a>
            </div>
        </div>
        <div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 24px; font-size: 12px; color: #94a3b8; text-align: center;">
            Salihli YKS & LGS Öğrenci Koçluğu Bildirim Sistemi
        </div>
    </div>
</body>
</html>';

        $serverHost = $_SERVER['SERVER_NAME'] ?? 'salihlikocluk.com';
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: Salihli Kocluk <no-reply@' . $serverHost . '>',
        ];
        if (!empty($lead['email'])) {
            $headers[] = 'Reply-To: ' . $lead['email'];
        }

        try {
            $mailSent = @mail($toEmail, $subject, $html, implode("\r\n", $headers));
            $results[] = $mailSent ? 'E-posta bildirimi gönderildi (' . $toEmail . ')' : 'E-posta kuyruğa alındı (' . $toEmail . ')';
        } catch (\Throwable $e) {
            $results[] = 'E-posta hatası: ' . $e->getMessage();
        }
    } else {
        $results[] = 'E-posta bildirimi pasif veya adres boş';
    }

    // 2. TELEGRAM BOT BİLDİRİMİ (Opsiyonel)
    $tgToken = trim(setting('notify_telegram_bot_token'));
    $tgChatId = trim(setting('notify_telegram_chat_id'));

    if (!empty($tgToken) && !empty($tgChatId)) {
        $tgText = "🔔 <b>" . ($isTest ? "[TEST] " : "") . "YENİ ÖN GÖRÜŞME TALEBİ!</b>\n\n"
            . "👤 <b>Ad Soyad:</b> " . htmlspecialchars((string)($lead['name'] ?? '')) . "\n"
            . "📞 <b>Telefon:</b> " . htmlspecialchars((string)($lead['phone'] ?? '')) . "\n"
            . ($waLink ? "💬 <b>WhatsApp:</b> " . $waLink . "\n" : "")
            . "🎓 <b>Durum:</b> " . htmlspecialchars((string)($lead['student_level'] ?: 'Belirtilmedi')) . "\n"
            . (!empty($lead['email']) ? "✉️ <b>E-posta:</b> " . htmlspecialchars((string)$lead['email']) . "\n" : "")
            . "📝 <b>Mesaj:</b>\n" . htmlspecialchars((string)($lead['message'] ?: 'Mesaj yazılmadı.')) . "\n\n"
            . "📅 <b>Tarih:</b> " . $dateStr;

        $tgUrl = "https://api.telegram.org/bot{$tgToken}/sendMessage";
        $tgData = json_encode([
            'chat_id' => $tgChatId,
            'text' => $tgText,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $tgData,
                'timeout' => 5,
                'ignore_errors' => true,
            ]
        ];

        try {
            $ctx = stream_context_create($opts);
            $resp = @file_get_contents($tgUrl, false, $ctx);
            if ($resp && ($json = json_decode($resp, true)) && !empty($json['ok'])) {
                $results[] = 'Telegram mesajı iletildi';
            } else {
                $results[] = 'Telegram API uyarısı (Token/Chat ID kontrol edilmeli)';
            }
        } catch (\Throwable $e) {
            $results[] = 'Telegram bağlantı hatası: ' . $e->getMessage();
        }
    }

    // 3. WEBHOOK BİLDİRİMİ (Opsiyonel: Zapier, Make, Discord, Slack)
    $webhookUrl = trim(setting('notify_webhook_url'));
    if (!empty($webhookUrl)) {
        $webhookData = json_encode([
            'event' => $isTest ? 'test_lead' : 'new_lead',
            'name' => $lead['name'] ?? '',
            'phone' => $lead['phone'] ?? '',
            'whatsapp_link' => $waLink,
            'email' => $lead['email'] ?? '',
            'student_level' => $lead['student_level'] ?? '',
            'message' => $lead['message'] ?? '',
            'created_at' => $lead['created_at'] ?? date('Y-m-d H:i:s'),
        ]);

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $webhookData,
                'timeout' => 5,
                'ignore_errors' => true,
            ]
        ];

        try {
            $ctx = stream_context_create($opts);
            $resp = @file_get_contents($webhookUrl, false, $ctx);
            $results[] = 'Webhook tetiklendi (' . parse_url($webhookUrl, PHP_URL_HOST) . ')';
        } catch (\Throwable $e) {
            $results[] = 'Webhook hatası: ' . $e->getMessage();
        }
    }

    return $results;
}
