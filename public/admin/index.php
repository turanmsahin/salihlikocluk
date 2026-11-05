<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

// AJAX Category Reordering
if (is_admin() && ($_GET['action'] ?? '') === 'reorder_categories' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $order = $input['order'] ?? [];
    if (is_array($order) && !empty($order)) {
        $stmt = $db->prepare('UPDATE categories SET sort_order = :sort_order WHERE id = :id');
        foreach ($order as $index => $id) {
            $stmt->execute(['sort_order' => $index + 1, 'id' => (int) $id]);
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
}

// AJAX AI Blog Article Generator
if (is_admin() && ($_GET['action'] ?? '') === 'ai_generate_blog' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    $categoryId = (int) ($input['category_id'] ?? 1);
    $categoryName = 'YKS Koçluğu';
    $catStmt = $db->prepare('SELECT name FROM categories WHERE id = :id');
    $catStmt->execute(['id' => $categoryId]);
    if ($catRow = $catStmt->fetch()) {
        $categoryName = $catRow['name'];
    }

    $result = generate_ai_blog_article([
        'topic' => $input['topic'] ?? '',
        'category_id' => $categoryId,
        'category_name' => $categoryName,
        'audience' => $input['audience'] ?? '',
        'tone' => $input['tone'] ?? '',
        'include_local' => !empty($input['include_local']),
        'include_faq' => !empty($input['include_faq']),
        'include_tldr' => !empty($input['include_tldr']),
    ]);

    echo json_encode(['success' => true, 'data' => $result]);
    exit;
}

// AJAX Canlı Yapay Zekâ API Bağlantı Testi
if (is_admin() && ($_GET['action'] ?? '') === 'test_ai_api' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $provider = trim((string)($input['provider'] ?? 'gemini'));
    $apiKey = trim((string)($input['api_key'] ?? ''));
    $model = trim((string)($input['model'] ?? ''));
    $res = test_ai_connection($provider, $apiKey, $model);
    echo json_encode($res);
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $error = 'Form doğrulanamadı. Sayfayı yenileyip tekrar deneyin.';
    } elseif (($_POST['action'] ?? '') === 'login') {
        $statement = $db->prepare('SELECT id, username, password_hash FROM admins WHERE username = :username');
        $statement->execute(['username' => trim((string) ($_POST['username'] ?? ''))]);
        $admin = $statement->fetch();
        if ($admin && password_verify((string) ($_POST['password'] ?? ''), $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            header('Location: /admin/');
            exit;
        }
        $error = 'Kullanıcı adı veya parola hatalı.';
    } elseif (!is_admin()) {
        http_response_code(403);
        $error = 'Bu işlem için yönetici girişi gereklidir.';
    } elseif (($_POST['action'] ?? '') === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: /admin/');
        exit;
    } elseif (($_POST['action'] ?? '') === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        $stmt = $db->prepare('SELECT password_hash FROM admins WHERE id = :id');
        $stmt->execute(['id' => (int) $_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify($current, $admin['password_hash'])) {
            $error = 'Mevcut parolanız hatalı.';
        } elseif (mb_strlen($new) < 8) {
            $error = 'Yeni parola en az 8 karakter olmalıdır.';
        } elseif ($new !== $confirm) {
            $error = 'Yeni parolalar birbiriyle eşleşmiyor.';
        } else {
            $updateStmt = $db->prepare('UPDATE admins SET password_hash = :hash WHERE id = :id');
            $updateStmt->execute(['hash' => password_hash($new, PASSWORD_DEFAULT), 'id' => (int) $_SESSION['admin_id']]);
            $_SESSION['admin_notice'] = 'Parolanız başarıyla güncellendi.';
            header('Location: /admin/?tab=settings');
            exit;
        }
    } elseif (($_POST['action'] ?? '') === 'save_settings') {
        $allowedKeys = [
            'coach_name', 'coach_title', 'phone', 'whatsapp', 'email',
            'location', 'working_model', 'instagram', 'coach_bio',
            'coach_education', 'coach_approach',
        ];
        $toUpdate = [];
        foreach ($allowedKeys as $key) {
            if (isset($_POST[$key])) {
                $toUpdate[$key] = trim((string) $_POST[$key]);
            }
        }
        update_settings($toUpdate);
        $_SESSION['admin_notice'] = 'Koç profili ve iletişim bilgileri başarıyla güncellendi.';
        header('Location: /admin/?tab=settings');
        exit;
    } elseif (($_POST['action'] ?? '') === 'save_seo') {
        $allowedKeys = [
            'seo_title_suffix', 'seo_default_description', 'seo_keywords',
            'seo_google_verification', 'seo_analytics_id', 'gemini_api_key',
            'office_address', 'geo_latitude', 'geo_longitude',
            'google_maps_url', 'opening_hours', 'service_areas',
        ];
        $toUpdate = [];
        foreach ($allowedKeys as $key) {
            if (isset($_POST[$key])) {
                $toUpdate[$key] = trim((string) $_POST[$key]);
            }
        }
        update_settings($toUpdate);
        $_SESSION['admin_notice'] = 'SEO ayarları başarıyla güncellendi.';
        header('Location: /admin/?tab=seo');
        exit;
    } elseif (($_POST['action'] ?? '') === 'save_notifications') {
        $allowedKeys = [
            'notify_email', 'notify_telegram_bot_token',
            'notify_telegram_chat_id', 'notify_webhook_url',
        ];
        $toUpdate = [];
        foreach ($allowedKeys as $key) {
            if (isset($_POST[$key])) {
                $toUpdate[$key] = trim((string) $_POST[$key]);
            }
        }
        $toUpdate['notify_email_enabled'] = isset($_POST['notify_email_enabled']) ? '1' : '0';
        update_settings($toUpdate);
        $_SESSION['admin_notice'] = 'Bildirim ayarları başarıyla güncellendi.';
        header('Location: /admin/?tab=notifications');
        exit;
    } elseif (($_POST['action'] ?? '') === 'send_test_notification') {
        $testLead = [
            'name' => 'Örnek Öğrenci / Veli (TEST)',
            'phone' => setting('phone', '+90 544 344 24 49'),
            'email' => setting('email', 'iletisim@salihlikocluk.com'),
            'student_level' => 'YKS / 12. sınıf',
            'message' => 'Bu bir test bildirimidir. Salihli Koçluk bildirim sisteminin (E-posta, Telegram, Webhook) çalıştığını doğrulamak amacıyla gönderilmiştir.',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $results = send_lead_notifications($testLead, true);
        $_SESSION['admin_notice'] = 'Test bildirimi tamamlandı: ' . implode(' | ', $results);
        header('Location: /admin/?tab=notifications');
        exit;
    } elseif (($_POST['action'] ?? '') === 'save_slide') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $badge = trim((string) ($_POST['badge'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
        $button_text = trim((string) ($_POST['button_text'] ?? ''));
        $button_link = trim((string) ($_POST['button_link'] ?? ''));
        $image_url = trim((string) ($_POST['image_url'] ?? ''));
        $sort_order = (int) ($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (!empty($_POST['remove_slide_image'])) {
            $image_url = '';
        } elseif (isset($_FILES['slide_image_file']) && ($_FILES['slide_image_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $uploaded = handle_file_upload($_FILES['slide_image_file']);
            if ($uploaded) {
                $image_url = $uploaded;
            }
        }

        if ($title === '') {
            $error = 'Slayt başlığı zorunludur.';
        } else {
            if ($id) {
                $stmt = $db->prepare('UPDATE slides SET badge = :badge, title = :title, subtitle = :subtitle, button_text = :button_text, button_link = :button_link, image_url = :image_url, sort_order = :sort_order, is_active = :is_active WHERE id = :id');
                $stmt->execute(compact('badge', 'title', 'subtitle', 'button_text', 'button_link', 'image_url', 'sort_order', 'is_active', 'id'));
            } else {
                $stmt = $db->prepare('INSERT INTO slides (badge, title, subtitle, button_text, button_link, image_url, sort_order, is_active) VALUES (:badge, :title, :subtitle, :button_text, :button_link, :image_url, :sort_order, :is_active)');
                $stmt->execute(compact('badge', 'title', 'subtitle', 'button_text', 'button_link', 'image_url', 'sort_order', 'is_active'));
            }
            $_SESSION['admin_notice'] = 'Slayt başarıyla kaydedildi.';
            header('Location: /admin/?tab=slider');
            exit;
        }
    } elseif (($_POST['action'] ?? '') === 'delete_slide') {
        $stmt = $db->prepare('DELETE FROM slides WHERE id = :id');
        $stmt->execute(['id' => (int) ($_POST['id'] ?? 0)]);
        $_SESSION['admin_notice'] = 'Slayt silindi.';
        header('Location: /admin/?tab=slider');
        exit;
    } elseif (($_POST['action'] ?? '') === 'save_faq') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $question = trim((string) ($_POST['question'] ?? ''));
        $answer = trim((string) ($_POST['answer'] ?? ''));
        $sort_order = (int) ($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($question === '' || $answer === '') {
            $error = 'Soru ve cevap alanları zorunludur.';
        } else {
            if ($id) {
                $stmt = $db->prepare('UPDATE faqs SET question = :question, answer = :answer, sort_order = :sort_order, is_active = :is_active WHERE id = :id');
                $stmt->execute(compact('question', 'answer', 'sort_order', 'is_active', 'id'));
            } else {
                $stmt = $db->prepare('INSERT INTO faqs (question, answer, sort_order, is_active) VALUES (:question, :answer, :sort_order, :is_active)');
                $stmt->execute(compact('question', 'answer', 'sort_order', 'is_active'));
            }
            $_SESSION['admin_notice'] = 'Soru ve cevap başarıyla kaydedildi.';
            header('Location: /admin/?tab=faqs');
            exit;
        }
    } elseif (($_POST['action'] ?? '') === 'delete_faq') {
        $stmt = $db->prepare('DELETE FROM faqs WHERE id = :id');
        $stmt->execute(['id' => (int) ($_POST['id'] ?? 0)]);
        $_SESSION['admin_notice'] = 'Soru silindi.';
        header('Location: /admin/?tab=faqs');
        exit;
    } elseif (($_POST['action'] ?? '') === 'save_page') {
        $slug = trim((string) ($_POST['slug'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $meta_description = trim((string) ($_POST['meta_description'] ?? ''));
        $eyebrow = trim((string) ($_POST['eyebrow'] ?? ''));
        $heading = trim((string) ($_POST['heading'] ?? ''));
        $intro = trim((string) ($_POST['intro'] ?? ''));
        $sections_raw = trim((string) ($_POST['sections_raw'] ?? ''));
        $cta_text = trim((string) ($_POST['cta_text'] ?? ''));
        $image_url = trim((string) ($_POST['image_url'] ?? ''));

        if (!empty($_POST['remove_page_image'])) {
            $image_url = '';
        } elseif (isset($_FILES['page_image_file']) && ($_FILES['page_image_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $uploaded = handle_file_upload($_FILES['page_image_file']);
            if ($uploaded) {
                $image_url = $uploaded;
            }
        }

        $sections = [];
        if ($sections_raw !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $sections_raw);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                if (str_contains($line, '|')) {
                    [$h, $b] = explode('|', $line, 2);
                    $sections[] = [trim($h), trim($b)];
                } else {
                    $sections[] = [$line, ''];
                }
            }
        }
        $sections_json = json_encode($sections, JSON_UNESCAPED_UNICODE);

        $stmt = $db->prepare('INSERT INTO site_pages (slug, title, meta_description, eyebrow, heading, intro, image_url, sections_json, cta_text, updated_at) VALUES (:slug, :title, :meta_description, :eyebrow, :heading, :intro, :image_url, :sections_json, :cta_text, CURRENT_TIMESTAMP) ON CONFLICT(slug) DO UPDATE SET title = excluded.title, meta_description = excluded.meta_description, eyebrow = excluded.eyebrow, heading = excluded.heading, intro = excluded.intro, image_url = excluded.image_url, sections_json = excluded.sections_json, cta_text = excluded.cta_text, updated_at = CURRENT_TIMESTAMP');
        $stmt->execute(compact('slug', 'title', 'meta_description', 'eyebrow', 'heading', 'intro', 'image_url', 'sections_json', 'cta_text'));

        $_SESSION['admin_notice'] = 'Sayfa içeriği ve görseli başarıyla kaydedildi.';
        header('Location: /admin/?tab=pages&page=' . rawurlencode($slug));
        exit;
    } elseif (($_POST['action'] ?? '') === 'save_category') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = slugify((string) (($_POST['slug'] ?? '') ?: $name));
        $sort_order = (int) ($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '' || $slug === '') {
            $error = 'Kategori adı ve bağlantı adı boş bırakılamaz.';
        } else {
            try {
                if ($id) {
                    $stmt = $db->prepare('UPDATE categories SET name = :name, slug = :slug, sort_order = :sort_order, is_active = :is_active WHERE id = :id');
                    $stmt->execute(compact('name', 'slug', 'sort_order', 'is_active', 'id'));
                } else {
                    if ($sort_order === 0) {
                        $maxOrder = (int) $db->query('SELECT MAX(sort_order) FROM categories')->fetchColumn();
                        $sort_order = $maxOrder + 1;
                    }
                    $stmt = $db->prepare('INSERT INTO categories (name, slug, sort_order, is_active) VALUES (:name, :slug, :sort_order, :is_active)');
                    $stmt->execute(compact('name', 'slug', 'sort_order', 'is_active'));
                }
                $_SESSION['admin_notice'] = 'Kategori başarıyla kaydedildi.';
                header('Location: /admin/?tab=blog#categories-section');
                exit;
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'UNIQUE')) {
                    $error = 'Bu bağlantı adına (slug) sahip bir kategori zaten var.';
                } else {
                    throw $e;
                }
            }
        }
    } elseif (($_POST['action'] ?? '') === 'delete_category') {
        $id = (int) ($_POST['id'] ?? 0);
        $minCatId = (int) $db->query('SELECT id FROM categories WHERE id != ' . $id . ' ORDER BY sort_order ASC, id ASC LIMIT 1')->fetchColumn();
        if ($minCatId) {
            $db->prepare('UPDATE posts SET category_id = :min_id WHERE category_id = :id')->execute(['min_id' => $minCatId, 'id' => $id]);
        }
        $db->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => $id]);
        $_SESSION['admin_notice'] = 'Kategori silindi.';
        header('Location: /admin/?tab=blog#categories-section');
        exit;
    } elseif (($_POST['action'] ?? '') === 'save_post') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $title = trim((string) ($_POST['title'] ?? ''));
        $slug = slugify((string) (($_POST['slug'] ?? '') ?: $title));
        $category_id = (int) ($_POST['category_id'] ?? 1);
        $image_url = trim((string) ($_POST['image_url'] ?? ''));

        if (!empty($_POST['image_url_custom'])) {
            $image_url = trim((string) $_POST['image_url_custom']);
        }

        if (!empty($_POST['remove_image'])) {
            $image_url = '';
        } elseif (isset($_FILES['image_file']) && ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $uploaded = handle_file_upload($_FILES['image_file']);
            if ($uploaded) {
                $image_url = $uploaded;
            }
        }

        $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        $status = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';

        if ($title === '' || $slug === '' || $excerpt === '' || $content === ''
            || mb_strlen($title) > 200 || mb_strlen($slug) > 200 || mb_strlen($excerpt) > 500
            || mb_strlen($content) > 50000) {
            $error = 'Başlık, bağlantı adı, özet ve içerik zorunludur. Özet 500, içerik 50000 karakteri aşamaz.';
        } else {
            try {
                if ($id) {
                    $statement = $db->prepare('UPDATE posts SET title = :title, slug = :slug, category_id = :category_id, image_url = :image_url, excerpt = :excerpt, content = :content, status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                    $statement->execute(compact('title', 'slug', 'category_id', 'image_url', 'excerpt', 'content', 'status', 'id'));
                } else {
                    $statement = $db->prepare('INSERT INTO posts (title, slug, category_id, image_url, excerpt, content, status) VALUES (:title, :slug, :category_id, :image_url, :excerpt, :content, :status)');
                    $statement->execute(compact('title', 'slug', 'category_id', 'image_url', 'excerpt', 'content', 'status'));
                }
                $_SESSION['admin_notice'] = 'Yazı kaydedildi.';
                header('Location: /admin/?tab=blog');
                exit;
            } catch (PDOException $exception) {
                if (str_contains($exception->getMessage(), 'UNIQUE')) {
                    $error = 'Bu bağlantı adı başka bir yazıda kullanılıyor.';
                } else {
                    throw $exception;
                }
            }
        }
    } elseif (($_POST['action'] ?? '') === 'delete_post') {
        $statement = $db->prepare('DELETE FROM posts WHERE id = :id');
        $statement->execute(['id' => (int) ($_POST['id'] ?? 0)]);
        $_SESSION['admin_notice'] = 'Yazı silindi.';
        header('Location: /admin/?tab=blog');
        exit;
    } elseif (($_POST['action'] ?? '') === 'update_lead') {
        $status = in_array($_POST['status'] ?? '', ['new', 'contacted', 'closed'], true) ? $_POST['status'] : 'new';
        $statement = $db->prepare('UPDATE leads SET status = :status WHERE id = :id');
        $statement->execute(['status' => $status, 'id' => (int) ($_POST['id'] ?? 0)]);
        $_SESSION['admin_notice'] = 'Talep durumu güncellendi.';
        header('Location: /admin/?tab=leads');
        exit;
    } elseif (($_POST['action'] ?? '') === 'delete_lead') {
        $statement = $db->prepare('DELETE FROM leads WHERE id = :id');
        $statement->execute(['id' => (int) ($_POST['id'] ?? 0)]);
        $_SESSION['admin_notice'] = 'Başvuru kaydı silindi.';
        header('Location: /admin/?tab=leads');
        exit;
    } elseif (($_POST['action'] ?? '') === 'clear_analytics_logs') {
        $days = (int) ($_POST['days'] ?? 30);
        if ($days > 0) {
            $stmt = $db->prepare("DELETE FROM visitor_logs WHERE created_at < datetime('now', 'localtime', :days_ago)");
            $stmt->execute(['days_ago' => '-' . $days . ' days']);
            $_SESSION['admin_notice'] = "Son {$days} günden eski ziyaretçi logları temizlendi.";
        }
        header('Location: /admin/?tab=analytics');
        exit;
    } elseif (($_POST['action'] ?? '') === 'save_ai_settings') {
        $keys = [
            'ai_provider', 'gemini_api_key', 'gemini_model', 
            'openai_api_key', 'openai_model',
            'ai_default_tone', 'ai_default_audience', 
            'ai_default_local', 'ai_default_faq', 'ai_default_tldr'
        ];
        $updates = [];
        foreach ($keys as $k) {
            $updates[$k] = trim((string) ($_POST[$k] ?? ''));
        }
        save_settings($updates);
        $_SESSION['admin_notice'] = 'Yapay zekâ API ve blog üretim ayarları kaydedildi.';
        header('Location: /admin/?tab=ai');
        exit;
    }
}

$notice = $_SESSION['admin_notice'] ?? '';
unset($_SESSION['admin_notice']);
$tab = $_GET['tab'] ?? (isset($_GET['edit']) ? 'blog' : 'settings');
$editingPost = null;
if (is_admin() && isset($_GET['edit'])) {
    $statement = $db->prepare('SELECT * FROM posts WHERE id = :id');
    $statement->execute(['id' => (int) $_GET['edit']]);
    $editingPost = $statement->fetch() ?: null;
}

$editingSlide = null;
if (is_admin() && isset($_GET['edit_slide'])) {
    $stmt = $db->prepare('SELECT * FROM slides WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['edit_slide']]);
    $editingSlide = $stmt->fetch() ?: null;
}

$editingFaq = null;
if (is_admin() && isset($_GET['edit_faq'])) {
    $stmt = $db->prepare('SELECT * FROM faqs WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['edit_faq']]);
    $editingFaq = $stmt->fetch() ?: null;
}

$editingCategory = null;
if (is_admin() && isset($_GET['edit_category'])) {
    $cstmt = $db->prepare('SELECT * FROM categories WHERE id = :id');
    $cstmt->execute(['id' => (int) $_GET['edit_category']]);
    $editingCategory = $cstmt->fetch() ?: null;
}

$categories = is_admin() ? get_categories(false) : [];
$posts = is_admin() ? $db->query('SELECT p.id, p.title, p.slug, p.status, p.category_id, p.updated_at, c.name AS category_name FROM posts p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.updated_at DESC')->fetchAll() : [];
$leads = is_admin() ? $db->query('SELECT id, name, phone, email, student_level, message, status, created_at FROM leads ORDER BY created_at DESC LIMIT 100')->fetchAll() : [];
$newLeadsCount = is_admin() ? (int) $db->query("SELECT COUNT(*) FROM leads WHERE status = 'new'")->fetchColumn() : 0;
$slides = is_admin() ? get_slides(false) : [];
$faqs = is_admin() ? get_faqs(false) : [];
$currentSettings = is_admin() ? get_settings() : [];

$analyticsCity = trim((string) ($_GET['city'] ?? ''));
$analyticsRange = (string) ($_GET['range'] ?? 'today');
if (!in_array($analyticsRange, ['today', '7days', '30days', 'all'], true)) {
    $analyticsRange = 'today';
}
$analyticsData = ($tab === 'analytics' && is_admin()) ? get_analytics_metrics($analyticsCity, $analyticsRange) : [
    'total_views' => 0,
    'unique_visitors' => 0,
    'avg_duration_seconds' => 0,
    'top_cities' => [],
    'top_pages' => [],
    'devices' => [],
    'recent_logs' => [],
];
$todayVisitsCount = is_admin() ? (int) $db->query("SELECT COUNT(*) FROM visitor_logs WHERE created_at >= date('now', 'localtime', 'start of day')")->fetchColumn() : 0;

$editablePages = [
    'yks' => 'YKS Öğrenci Koçluğu',
    'tyt' => 'TYT Koçluğu',
    'ayt' => 'AYT Koçluğu',
    'mezun' => 'Mezun Öğrenci Koçluğu',
    'yazilim' => 'Yazılım ve Yapay Zekâ Destekli Koçluk',
    'veli' => 'Veli Danışmanlığı',
    'surec' => 'Koçluk Süreci ve Ücretler',
    'hakkimda' => 'Hakkımda Sayfası',
    'gizlilik' => 'Gizlilik ve KVKK Metni',
];

$selectedPageSlug = $_GET['page'] ?? 'yks';
if (!isset($editablePages[$selectedPageSlug])) {
    $selectedPageSlug = 'yks';
}

$selectedPageData = is_admin() ? get_page_content($selectedPageSlug, []) : [];
$selectedPageSectionsRaw = '';
if (!empty($selectedPageData['sections']) && is_array($selectedPageData['sections'])) {
    $lines = [];
    foreach ($selectedPageData['sections'] as $sec) {
        $lines[] = ($sec[0] ?? '') . ' | ' . ($sec[1] ?? '');
    }
    $selectedPageSectionsRaw = implode("\n", $lines);
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Yönetim Paneli | Salihli Koçluk</title>
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="admin-body">
<header class="admin-header">
    <a class="brand" href="/"><span class="brand-mark">S</span><span>Salihli<span class="brand-light">Koçluk</span></span></a>
    <a class="text-link" href="/" target="_blank">Siteyi görüntüle ↗</a>
</header>
<main class="admin-main">
<?php if (!is_admin()): ?>
    <form class="admin-login" method="post">
        <p class="eyebrow">YÖNETİM PANELİ</p>
        <h1>Giriş yap</h1>
        <?php if ($error): ?><p class="form-error"><?= e($error) ?></p><?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="login">
        <label>Kullanıcı adı
            <input name="username" autocomplete="username" required>
        </label>
        <label>Parola
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button class="button button-primary" type="submit">Giriş yap</button>
    </form>
<?php else: ?>
    <div class="admin-title">
        <div>
            <p class="eyebrow">YÖNETİM PANELİ</p>
            <h1>Merhaba, <?= e($_SESSION['admin_username'] ?? 'Yönetici') ?></h1>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="logout">
            <button class="button button-outline" type="submit">Çıkış Yap</button>
        </form>
    </div>
    
    <nav class="admin-nav" aria-label="Yönetim sekmeleri">
        <a class="admin-tab <?= $tab === 'settings' ? 'is-active' : '' ?>" href="/admin/?tab=settings">👤 Koç Profili</a>
        <a class="admin-tab <?= $tab === 'seo' ? 'is-active' : '' ?>" href="/admin/?tab=seo">🎯 SEO Paneli</a>
        <a class="admin-tab <?= $tab === 'slider' ? 'is-active' : '' ?>" href="/admin/?tab=slider">🖼️ Slider / Vitrin (<?= count($slides) ?>)</a>
        <a class="admin-tab <?= $tab === 'pages' ? 'is-active' : '' ?>" href="/admin/?tab=pages">📄 Sayfa Yönetimi</a>
        <a class="admin-tab <?= $tab === 'faqs' ? 'is-active' : '' ?>" href="/admin/?tab=faqs">❓ SSS / Sorular (<?= count($faqs) ?>)</a>
        <a class="admin-tab <?= $tab === 'blog' ? 'is-active' : '' ?>" href="/admin/?tab=blog">📝 Blog Yazıları (<?= count($posts) ?>)</a>
        <a class="admin-tab <?= $tab === 'ai' ? 'is-active' : '' ?>" href="/admin/?tab=ai">🤖 Yapay Zekâ &amp; API</a>
        <a class="admin-tab <?= $tab === 'notifications' ? 'is-active' : '' ?>" href="/admin/?tab=notifications">🔔 Bildirimler</a>
        <a class="admin-tab <?= $tab === 'leads' ? 'is-active' : '' ?>" href="/admin/?tab=leads">📬 Talepler <?php if ($newLeadsCount > 0): ?><span class="badge"><?= $newLeadsCount ?> yeni</span><?php endif; ?></a>
        <a class="admin-tab <?= $tab === 'analytics' ? 'is-active' : '' ?>" href="/admin/?tab=analytics">📊 Canlı Ziyaretçi &amp; Log <?php if ($todayVisitsCount > 0): ?><span class="badge" style="background:#dc2626; color:#fff;"><?= $todayVisitsCount ?></span><?php endif; ?></a>
    </nav>

    <?php if ($error || $notice): ?><p class="<?= $error ? 'form-error' : 'notice' ?>"><?= e($error ?: $notice) ?></p><?php endif; ?>

    <?php if ($tab === 'settings'): ?>
        <section class="admin-section" id="settings">
            <h2>Koç Profili ve Temel İletişim Bilgileri</h2>
            <p class="admin-help-text" style="margin-bottom: 20px;">Buraya girdiğiniz bilgiler ana sayfa, Hakkımda, İletişim, Gizlilik ve sitenin alt bilgi (footer) alanlarına otomatik yansır.</p>
            <form class="admin-editor" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_settings">

                <label>Koç Adı Soyadı
                    <input name="coach_name" maxlength="120" value="<?= e($currentSettings['coach_name'] ?? '') ?>" placeholder="Örn: Turan ...">
                </label>
                <label>Unvan / Rol
                    <input name="coach_title" maxlength="150" value="<?= e($currentSettings['coach_title'] ?? '') ?>" placeholder="Örn: YKS & Öğrenci Koçu">
                </label>

                <label>Telefon Numarası
                    <input name="phone" type="tel" maxlength="40" value="<?= e($currentSettings['phone'] ?? '') ?>" placeholder="Örn: +90 544 344 24 49">
                </label>
                <label>WhatsApp Numarası (Sadece Rakamlar / Uluslararası)
                    <input name="whatsapp" type="tel" maxlength="40" value="<?= e($currentSettings['whatsapp'] ?? '') ?>" placeholder="Örn: 905443442449">
                </label>

                <label>E-posta Adresi
                    <input name="email" type="email" maxlength="120" value="<?= e($currentSettings['email'] ?? '') ?>" placeholder="Örn: iletisim@salihlikocluk.com">
                </label>
                <label>Şehir / Konum (Ofis)
                    <input name="location" maxlength="120" value="<?= e($currentSettings['location'] ?? '') ?>" placeholder="Örn: Salihli, Manisa (Fiziksel Ofis)">
                </label>

                <label>Çalışma Modeli
                    <input name="working_model" maxlength="150" value="<?= e($currentSettings['working_model'] ?? '') ?>" placeholder="Örn: Salihli'de Yüz Yüze (Fiziksel Ofis) & Hibrit">
                </label>
                <label style="grid-column: 1 / -1; background: #fff5f8; border: 1px solid #fbcfe8; padding: 14px 16px; border-radius: var(--radius-sm); margin: 6px 0;">
                    <strong style="color: #db2777; display: flex; align-items: center; gap: 6px; margin-bottom: 6px; font-size: 13px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                        Instagram Hesabı / Profil Bağlantısı
                    </strong>
                    <input name="instagram" maxlength="255" value="<?= e($currentSettings['instagram'] ?? '') ?>" placeholder="Örn: salihlikocluk veya https://instagram.com/salihlikocluk" style="background: #ffffff; border-color: #f472b6;">
                    <span class="admin-help-text" style="color: #9d174d; margin-top: 6px; display: block; font-size: 12px; line-height: 1.5;">
                        📌 <strong>Manuel Düzenleme:</strong> İster sadece kullanıcı adınızı (örn: <code>salihlikocluk</code> veya <code>@salihlikocluk</code>), ister doğrudan tam profil URL'nizi (örn: <code>https://instagram.com/salihlikocluk</code>) yazabilirsiniz. Üst bar ve sitedeki tüm Instagram butonları otomatik olarak bu hedefe yönlenir.
                        <?php if (!empty($currentSettings['instagram'])): ?>
                            <span style="display: block; margin-top: 4px; color: #475569;">
                                Mevcut hedef: <a href="<?= e(get_instagram_url($currentSettings['instagram'])) ?>" target="_blank" rel="noopener" style="color: #db2777; font-weight: 600; text-decoration: underline;"><?= e(get_instagram_url($currentSettings['instagram'])) ?></a>
                            </span>
                        <?php endif; ?>
                    </span>
                </label>

                <label style="grid-column: 1 / -1;">Kısa Biyografi (Hakkımda Giriş Metni)
                    <textarea name="coach_bio" rows="3" maxlength="1500" placeholder="Öğrencilere kendinizi ve koçluk misyonunuzu kısaca tanıtın."><?= e($currentSettings['coach_bio'] ?? '') ?></textarea>
                </label>

                <label style="grid-column: 1 / -1;">Eğitim, Sertifikalar ve Deneyim
                    <textarea name="coach_education" rows="3" maxlength="1500" placeholder="Mezun olunan okul, koçluk sertifikası veya resmi yetkinlikler."><?= e($currentSettings['coach_education'] ?? '') ?></textarea>
                </label>

                <label style="grid-column: 1 / -1;">Koçluk Yaklaşımı ve İlkeler
                    <textarea name="coach_approach" rows="3" maxlength="1500" placeholder="Öğrenciyle çalışırken izlediğiniz temel ilkeler ve çalışma prensipleri."><?= e($currentSettings['coach_approach'] ?? '') ?></textarea>
                </label>

                <button class="button button-primary" type="submit" style="grid-column: 1 / -1;">Profil Bilgilerini Kaydet</button>
            </form>
        </section>

        <section class="admin-section">
            <h2>Yönetici Parolasını Değiştir</h2>
            <form class="admin-editor" method="post" style="max-width: 600px;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="change_password">
                <label style="grid-column: 1 / -1;">Mevcut Parolanız
                    <input type="password" name="current_password" required>
                </label>
                <label>Yeni Parola (En az 8 karakter)
                    <input type="password" name="new_password" minlength="8" required>
                </label>
                <label>Yeni Parola Tekrar
                    <input type="password" name="confirm_password" minlength="8" required>
                </label>
                <button class="button button-outline" type="submit" style="grid-column: 1 / -1;">Parolayı Güncelle</button>
            </form>
        </section>

    <?php elseif ($tab === 'seo'): ?>
        <section class="admin-section" id="seo">
            <h2>SEO &amp; Arama Motoru Optimizasyonu</h2>
            <p class="admin-help-text" style="margin-bottom: 20px;">Google ve diğer arama motorlarında sitenizin nasıl görüneceğini ve doğrulama kodlarınızı buradan yönetin.</p>
            <form class="admin-editor" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_seo">

                <label style="grid-column: 1 / -1;">Sayfa Başlığı Eki (Title Suffix)
                    <input name="seo_title_suffix" maxlength="100" value="<?= e($currentSettings['seo_title_suffix'] ?? ' | Salihli Koçluk') ?>" placeholder="Örn:  | Salihli Koçluk">
                    <span class="admin-help-text">Tüm sayfaların başlıklarının sonuna otomatik eklenir (örn: TYT Koçluğu | Salihli Koçluk).</span>
                </label>

                <label style="grid-column: 1 / -1;">Varsayılan Meta Açıklaması (Meta Description)
                    <textarea name="seo_default_description" rows="3" maxlength="300" placeholder="Arama motoru sonuçlarında sitenizin altında görünen açıklama."><?= e($currentSettings['seo_default_description'] ?? '') ?></textarea>
                </label>

                <label style="grid-column: 1 / -1;">Anahtar Kelimeler (Meta Keywords)
                    <input name="seo_keywords" maxlength="300" value="<?= e($currentSettings['seo_keywords'] ?? '') ?>" placeholder="Örn: salihli yks koçluğu, salihli öğrenci koçu, tyt ayt rehberlik">
                </label>

                <label>Google Search Console Doğrulama Kodu
                    <input name="seo_google_verification" maxlength="120" value="<?= e($currentSettings['seo_google_verification'] ?? '') ?>" placeholder="Örn: AbCdEf123456789...">
                    <span class="admin-help-text">Google Search Console'dan aldığınız meta etiketi `content` değeri.</span>
                </label>

                <label>Google Analytics / Tag Manager Kimliği
                    <input name="seo_analytics_id" maxlength="60" value="<?= e($currentSettings['seo_analytics_id'] ?? '') ?>" placeholder="Örn: G-XXXXXXXXXX">
                    <span class="admin-help-text">Ziyaretçi trafiğinizi izlemek için Google Analytics 4 ölçüm kimliği.</span>
                </label>

                <div style="grid-column: 1 / -1; padding: 20px 22px; background: #fff1f2; border: 1px solid #fecdd3; border-radius: var(--radius-md);">
                    <h3 style="margin: 0 0 8px; font-size: 16px; color: #be123c; display: flex; align-items: center; gap: 8px;">
                        <span>✨</span> Google Gemini API (Yapay Zekâ Blog Motoru)
                    </h3>
                    <p style="font-size: 13px; color: #881337; margin-bottom: 12px; line-height: 1.5;">
                        Blog sekmesindeki <strong>Yapay Zekâ İçerik Stüdyosu</strong>'nun doğrudan Google Gemini 2.5 Flash modelini kullanmasını isterseniz API anahtarınızı buraya girebilirsiniz.
                        <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener" style="color: #e11d48; font-weight: 700; text-decoration: underline;">Google AI Studio'dan ücretsiz API anahtarı alabilirsiniz →</a>
                    </p>
                    <label style="margin: 0;">
                        Gemini API Anahtarı (API Key)
                        <input name="gemini_api_key" type="password" value="<?= e($currentSettings['gemini_api_key'] ?? '') ?>" placeholder="AIzaSy..." style="background: #ffffff;">
                        <span class="admin-help-text" style="color: #9f1239;">Boş bırakırsanız sistemin yerleşik akıllı koçluk makale motoru otomatik olarak devreye girer.</span>
                    </label>
                </div>

                <div style="grid-column: 1 / -1; margin-top: 15px; padding: 20px 22px; background: #f8fafc; border: 1px solid var(--line); border-radius: var(--radius-md);">
                    <h3 style="margin: 0 0 10px; font-size: 16px;">📍 Yerel SEO, Coğrafi Konum &amp; Google Haritalar (Local Pack)</h3>
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 16px;">Google botlarının sitenizi Manisa Salihli'deki fiziksel bir eğitim kurumu olarak tanıması ve Haritalar'da öne çıkarması için koordinatlar ve adres bilgileri.</p>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <label style="grid-column: 1 / -1;">Ofis Açık Adresi (Salihli)
                            <input name="office_address" value="<?= e($currentSettings['office_address'] ?? 'Salihli Merkez, Manisa') ?>" placeholder="Örn: Zafer Mah. Belediye Cad. No:.. Salihli / Manisa">
                        </label>
                        <label>Harita Enlemi (Latitude)
                            <input name="geo_latitude" value="<?= e($currentSettings['geo_latitude'] ?? '38.4819') ?>" placeholder="38.4819">
                        </label>
                        <label>Harita Boylamı (Longitude)
                            <input name="geo_longitude" value="<?= e($currentSettings['geo_longitude'] ?? '28.1345') ?>" placeholder="28.1345">
                        </label>
                        <label style="grid-column: 1 / -1;">Google Haritalar / İşletme Bağlantısı (Maps URL)
                            <input name="google_maps_url" value="<?= e($currentSettings['google_maps_url'] ?? 'https://maps.google.com/?q=Salihli,Manisa') ?>" placeholder="https://maps.app.goo.gl/... veya Google Harita linki">
                        </label>
                        <label>Çalışma Gün ve Saatleri
                            <input name="opening_hours" value="<?= e($currentSettings['opening_hours'] ?? 'Pazartesi - Cumartesi: 09:00 - 19:30') ?>" placeholder="Örn: Pazartesi - Cumartesi: 09:00 - 19:30">
                        </label>
                        <label>Hizmet Verilen Çevre Bölgeler
                            <input name="service_areas" value="<?= e($currentSettings['service_areas'] ?? 'Salihli, Ahmetli, Alaşehir, Kula, Sart, Köprübaşı') ?>" placeholder="Salihli, Ahmetli, Alaşehir, Kula, Sart">
                        </label>
                    </div>
                </div>

                <button class="button button-primary" type="submit" style="grid-column: 1 / -1;">SEO Ayarlarını Kaydet</button>
            </form>
        </section>

    <?php elseif ($tab === 'slider'): ?>
        <section class="admin-section" id="slider-form">
            <h2><?= $editingSlide ? 'Slaytı Düzenle' : 'Yeni Slayt Ekle' ?></h2>
            <form class="admin-editor" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_slide">
                <input type="hidden" name="id" value="<?= e((string) ($editingSlide['id'] ?? '')) ?>">

                <label>Üst Rozet / Etiket (Badge)
                    <input name="badge" maxlength="60" value="<?= e($editingSlide['badge'] ?? '') ?>" placeholder="Örn: SALİHLİ OFİS & YÜZ YÜZE">
                </label>
                <label>Sıra Numarası
                    <input name="sort_order" type="number" min="0" max="99" value="<?= e((string) ($editingSlide['sort_order'] ?? 1)) ?>">
                </label>

                <label style="grid-column: 1 / -1;">Ana Başlık
                    <input name="title" maxlength="160" value="<?= e($editingSlide['title'] ?? '') ?>" placeholder="Örn: YKS yolculuğunda planlı ve kararlı ilerle." required>
                </label>

                <label style="grid-column: 1 / -1;">Alt Açıklama Metni
                    <textarea name="subtitle" rows="2" maxlength="400" placeholder="Slaytın altında yer alacak açıklama cümlesi."><?= e($editingSlide['subtitle'] ?? '') ?></textarea>
                </label>

                <label>Buton Yazısı
                    <input name="button_text" maxlength="60" value="<?= e($editingSlide['button_text'] ?? '') ?>" placeholder="Örn: Ön görüşme talep et">
                </label>
                <label>Buton Bağlantısı
                    <input name="button_link" maxlength="180" value="<?= e($editingSlide['button_link'] ?? '') ?>" placeholder="Örn: ?page=iletisim">
                </label>

                <div style="grid-column: 1 / -1; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 18px;">
                    <label style="font-weight: 700; color: #1e293b; margin-bottom: 6px; display: block; font-size: 13px;">
                        🖼️ Slayt Görseli (Sağ Blok / Opsiyonel)
                    </label>
                    <p style="font-size: 12px; color: #64748b; margin-bottom: 12px; line-height: 1.5;">
                        Slaytın sağ tarafındaki blokta gösterilecek görseli yükleyin. Boş bırakırsanız otomatik olarak şık interaktif koçluk şeması gösterilir.
                    </p>

                    <?php if (!empty($editingSlide['image_url'])): ?>
                        <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 14px; padding: 12px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px;">
                            <img src="<?= e($editingSlide['image_url']) ?>" alt="Mevcut Slayt Görseli" style="width: 90px; height: 65px; object-fit: cover; border-radius: 6px; border: 1px solid #cbd5e1;">
                            <div>
                                <small style="display: block; color: #475569; font-weight: 600;">Mevcut Görsel:</small>
                                <a href="<?= e($editingSlide['image_url']) ?>" target="_blank" rel="noopener" style="font-size: 11px; color: #dc2626; word-break: break-all;"><?= e($editingSlide['image_url']) ?></a>
                                <label style="display: flex; align-items: center; gap: 6px; margin-top: 6px; font-size: 12px; color: #dc2626; cursor: pointer;">
                                    <input type="checkbox" name="remove_slide_image" value="1" style="width: auto; min-height: auto;">
                                    Bu görseli kaldır (varsayılan şemayı göster)
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div>
                            <span style="font-size: 12px; font-weight: 600; color: #334155; display: block; margin-bottom: 4px;">Dosyadan Yükle (JPG, PNG, WebP):</span>
                            <input type="file" name="slide_image_file" accept="image/png,image/jpeg,image/webp,image/gif" style="font-size: 12px;">
                        </div>
                        <div>
                            <span style="font-size: 12px; font-weight: 600; color: #334155; display: block; margin-bottom: 4px;">Veya Doğrudan Görsel URL'si:</span>
                            <input name="image_url" value="<?= e($editingSlide['image_url'] ?? '') ?>" placeholder="https://... veya /assets/uploads/...">
                        </div>
                    </div>
                </div>

                <label style="grid-column: 1 / -1; display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="is_active" value="1" style="width: auto; min-height: auto;" <?= ($editingSlide['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <span>Bu slayt ana sayfada aktif olsun</span>
                </label>

                <div style="grid-column: 1 / -1; display: flex; gap: 10px;">
                    <button class="button button-primary" type="submit"><?= $editingSlide ? 'Slaytı Güncelle' : 'Slayt Ekle' ?></button>
                    <?php if ($editingSlide): ?>
                        <a class="button button-outline" href="/admin/?tab=slider">İptal</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="admin-section">
            <h2>Mevcut Slaytlar (Vitrin)</h2>
            <div class="admin-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Sıra</th>
                            <th>Görsel</th>
                            <th>Rozet / Başlık</th>
                            <th>Açıklama / Buton</th>
                            <th>Durum</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slides as $item): ?>
                            <tr>
                                <td>#<?= (int) $item['sort_order'] ?></td>
                                <td>
                                    <?php if (!empty($item['image_url'])): ?>
                                        <img src="<?= e($item['image_url']) ?>" alt="" style="width: 50px; height: 38px; object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0; display: block;">
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 11px; font-style: italic;">Şema</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['badge']): ?><small style="color: #6a8c54; font-weight:700;"><?= e($item['badge']) ?></small><br><?php endif; ?>
                                    <strong><?= e($item['title']) ?></strong>
                                </td>
                                <td>
                                    <?= e($item['subtitle']) ?><br>
                                    <?php if ($item['button_text']): ?>
                                        <small>Buton: <code><?= e($item['button_text']) ?></code> &rarr; <?= e($item['button_link']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight:700; color: <?= $item['is_active'] ? '#2d7a3a' : '#999' ?>;">
                                        <?= $item['is_active'] ? '● Yayında' : '○ Pasif' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="/admin/?tab=slider&amp;edit_slide=<?= (int) $item['id'] ?>">Düzenle</a>
                                    <form class="inline-form" method="post" onsubmit="return confirm('Bu slayt silinsin mi?')">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_slide">
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <button class="link-button" type="submit">Sil</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$slides): ?><tr><td colspan="6">Henüz kayıtlı slayt yok.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php elseif ($tab === 'pages'): ?>
        <section class="admin-section" id="pages">
            <h2>Sayfa İçerik &amp; SEO Yönetimi</h2>
            <p class="admin-help-text" style="margin-bottom: 16px;">Düzenlemek istediğiniz sayfayı seçin; başlık, meta açıklaması, ana metin ve alt bölümlerini özelleştirin.</p>
            
            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px;">
                <?php foreach ($editablePages as $slugKey => $name): ?>
                    <a class="admin-tab <?= $selectedPageSlug === $slugKey ? 'is-active' : '' ?>" href="/admin/?tab=pages&amp;page=<?= e($slugKey) ?>">
                        <?= e($name) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form class="admin-editor" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_page">
                <input type="hidden" name="slug" value="<?= e($selectedPageSlug) ?>">

                <div style="grid-column: 1 / -1; padding: 12px 16px; background: #eef3ec; border-radius: 6px; font-weight:600;">
                    Düzenlenen Sayfa: <span style="color:#204030;"><?= e($editablePages[$selectedPageSlug]) ?></span>
                    <a href="/?page=<?= e($selectedPageSlug) ?>" target="_blank" style="margin-left: 12px; font-size:11px; text-decoration:underline;">Sayfayı Aç ↗</a>
                </div>

                <label>Sayfa SEO Başlığı (Meta Title)
                    <input name="title" maxlength="150" value="<?= e($selectedPageData['title'] ?? '') ?>" placeholder="Boş bırakılırsa varsayılan başlık kullanılır">
                </label>
                <label>Sayfa Meta Açıklaması (Meta Description)
                    <input name="meta_description" maxlength="300" value="<?= e($selectedPageData['description'] ?? '') ?>" placeholder="Arama motoru sonuçlarında görünen sayfa açıklaması">
                </label>

                <label>Üst Başlık (Eyebrow / Kategori)
                    <input name="eyebrow" maxlength="100" value="<?= e($selectedPageData['eyebrow'] ?? '') ?>" placeholder="Örn: YKS ÖĞRENCİ KOÇLUĞU">
                </label>
                <label>Sayfa Ana Başlığı (H1)
                    <input name="heading" maxlength="160" value="<?= e($selectedPageData['heading'] ?? '') ?>" placeholder="Sayfanın en üstünde büyük harflerle görünen başlık">
                </label>

                <div style="grid-column: 1 / -1; background: #f8fafc; border: 1px solid var(--line); border-radius: 8px; padding: 16px;">
                    <h3 style="margin: 0 0 6px; font-size: 14px; color: var(--ink);">🖼️ Sayfa Tanıtım Görseli (Opsiyonel)</h3>
                    <p class="admin-help-text" style="margin-bottom: 12px;">Sayfaya kapak veya tanıtım görseli eklemek isterseniz dosya seçin. Boş bırakırsanız sayfa standart tipografik düzende kalır.</p>
                    
                    <?php if (!empty($selectedPageData['image_url'])): ?>
                        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 12px; background: #fff; padding: 10px; border: 1px solid var(--line); border-radius: 6px;">
                            <img src="<?= e($selectedPageData['image_url']) ?>" alt="Sayfa görseli" style="width: 140px; height: 90px; object-fit: cover; border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.06);">
                            <div>
                                <small style="color: #64748b; display: block; margin-bottom: 6px;">Mevcut Görsel: <code><?= e($selectedPageData['image_url']) ?></code></small>
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #dc2626; cursor: pointer; margin: 0;">
                                    <input type="checkbox" name="remove_page_image" value="1">
                                    <strong>Bu görseli kaldır</strong>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="image_url" value="<?= e($selectedPageData['image_url'] ?? '') ?>">
                    <label style="margin: 0;">Yeni Görsel Yükle (Bilgisayardan / Telefondan Seç)
                        <input type="file" name="page_image_file" accept="image/*">
                    </label>
                </div>

                <label style="grid-column: 1 / -1;">Giriş Paragrafı (Lead / Açıklama)
                    <textarea name="intro" rows="3" maxlength="1200" placeholder="Sayfanın girişinde öne çıkan açıklama metni."><?= e($selectedPageData['intro'] ?? '') ?></textarea>
                </label>

                <label style="grid-column: 1 / -1;">Sayfa Bölümleri &amp; Maddeler (Her satıra bir madde: <code>Başlık | Açıklama</code>)
                    <textarea name="sections_raw" rows="6" maxlength="5000" placeholder="1. Başlık | Açıklama metni&#10;2. Başlık | Açıklama metni"><?= e($selectedPageSectionsRaw) ?></textarea>
                    <span class="admin-help-text">Her satıra dikey çizgi (|) ile ayrılmış olarak başlık ve açıklama yazabilirsiniz.</span>
                </label>

                <label style="grid-column: 1 / -1;">Sayfa Sonu Çağrı Metni (İsteğe Bağlı)
                    <input name="cta_text" maxlength="250" value="<?= e($selectedPageData['cta'] ?? '') ?>" placeholder="Örn: Salihli ofisimizde yüz yüze tanışmak için randevu alabilirsiniz.">
                </label>

                <button class="button button-primary" type="submit" style="grid-column: 1 / -1;">Bu Sayfayı Kaydet</button>
            </form>
        </section>

    <?php elseif ($tab === 'faqs'): ?>
        <section class="admin-section" id="faq-form">
            <h2><?= $editingFaq ? 'Soruyu Düzenle' : 'Yeni Soru &amp; Cevap Ekle' ?></h2>
            <form class="admin-editor" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_faq">
                <input type="hidden" name="id" value="<?= e((string) ($editingFaq['id'] ?? '')) ?>">

                <label style="grid-column: 1 / -1;">Soru Başlığı
                    <input name="question" maxlength="250" value="<?= e($editingFaq['question'] ?? '') ?>" placeholder="Örn: Görüşmeler nerede ve nasıl yapılıyor?" required>
                </label>

                <label style="grid-column: 1 / -1;">Cevap Metni
                    <textarea name="answer" rows="4" maxlength="2000" placeholder="Sorunun ayrıntılı ve net yanıtı..." required><?= e($editingFaq['answer'] ?? '') ?></textarea>
                </label>

                <label>Sıra Numarası
                    <input name="sort_order" type="number" min="0" max="99" value="<?= e((string) ($editingFaq['sort_order'] ?? 1)) ?>">
                </label>

                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="is_active" value="1" style="width: auto; min-height: auto;" <?= ($editingFaq['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <span>Bu soru sitede yayında olsun</span>
                </label>

                <div style="grid-column: 1 / -1; display: flex; gap: 10px;">
                    <button class="button button-primary" type="submit"><?= $editingFaq ? 'Soruyu Güncelle' : 'Soruyu Kaydet' ?></button>
                    <?php if ($editingFaq): ?>
                        <a class="button button-outline" href="/admin/?tab=faqs">İptal</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="admin-section">
            <h2>Mevcut Sorular ve Cevaplar (<?= count($faqs) ?>)</h2>
            <div class="admin-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Sıra</th>
                            <th>Soru</th>
                            <th>Cevap</th>
                            <th>Durum</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($faqs as $item): ?>
                            <tr>
                                <td>#<?= (int) $item['sort_order'] ?></td>
                                <td style="font-weight: 700; max-width: 250px;"><?= e($item['question']) ?></td>
                                <td style="max-width: 400px;"><?= nl2br(e($item['answer'])) ?></td>
                                <td>
                                    <span style="font-weight: 700; color: <?= $item['is_active'] ? '#2e6b36' : '#888' ?>;">
                                        <?= $item['is_active'] ? '● Yayında' : '○ Pasif' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="/admin/?tab=faqs&amp;edit_faq=<?= (int) $item['id'] ?>">Düzenle</a>
                                    <form class="inline-form" method="post" onsubmit="return confirm('Bu soru silinsin mi?')">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_faq">
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <button class="link-button" type="submit" style="color: #ba3224;">Sil</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$faqs): ?><tr><td colspan="5">Henüz soru eklenmemiş.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php elseif ($tab === 'blog'): ?>
        <!-- KATEGORİ YÖNETİMİ & SÜRÜKLE - BIRAK -->
        <section class="admin-section" id="categories-section">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 14px;">
                <div>
                    <h2>🏷️ Blog Kategorileri (Sürükle - Bırak Sıralama)</h2>
                    <p class="admin-help-text" style="margin: 0;">Kategorileri <strong>⠿</strong> simgesinden tutarak yukarı/aşağı sürükleyin. Sıralama anında kaydedilir ve sitedeki butonlara yansır.</p>
                </div>
                <div id="dragNotice" class="ajax-save-toast" style="display:none; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 6px 14px; border-radius: 6px; font-weight: 700; font-size: 13px;">
                    ✓ Kategori sıralaması güncellendi!
                </div>
            </div>

            <!-- Kategori Ekle / Düzenle Mini Formu -->
            <form class="admin-editor" method="post" style="background: #f8fafc; border: 1px solid var(--line); border-radius: 8px; padding: 18px; margin-bottom: 24px;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_category">
                <input type="hidden" name="id" value="<?= e((string) ($editingCategory['id'] ?? '')) ?>">

                <h3 style="margin: 0 0 10px; font-size: 15px; color: var(--ink);">
                    <?= $editingCategory ? '✏️ Kategoriyi Düzenle' : '➕ Yeni Kategori Ekle' ?>
                </h3>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; align-items: flex-end;">
                    <label>Kategori Adı
                        <input name="name" maxlength="100" value="<?= e($editingCategory['name'] ?? '') ?>" placeholder="Örn: Deneme Analizi" required>
                    </label>
                    <label>Bağlantı Adı (URL Slug)
                        <input name="slug" maxlength="100" value="<?= e($editingCategory['slug'] ?? '') ?>" placeholder="Boş bırakırsanız otomatik üretilir">
                    </label>
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
                        <label style="display: flex; align-items: center; gap: 8px; margin: 0; cursor: pointer;">
                            <input type="checkbox" name="is_active" value="1" <?= (!isset($editingCategory) || !empty($editingCategory['is_active'])) ? 'checked' : '' ?>>
                            <strong>Aktif</strong>
                        </label>
                        <button class="button button-primary" type="submit" style="padding: 10px 18px;"><?= $editingCategory ? 'Güncelle' : 'Kategori Ekle' ?></button>
                        <?php if ($editingCategory): ?>
                            <a class="button button-outline" href="/admin/?tab=blog#categories-section">İptal</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- Sürükle Bırak Kategori Listesi -->
            <div class="drag-category-list" id="categorySortableList">
                <?php foreach ($categories as $cat): ?>
                    <div class="drag-category-item" draggable="true" data-id="<?= (int) $cat['id'] ?>">
                        <span class="drag-handle" title="Sıralamak için sürükleyin">⠿</span>
                        <div class="cat-details">
                            <strong><?= e($cat['name']) ?></strong>
                            <code class="cat-slug"><?= e($cat['slug']) ?></code>
                            <span class="cat-badge-status <?= $cat['is_active'] ? 'is-active' : 'is-passive' ?>">
                                <?= $cat['is_active'] ? '● Aktif' : '○ Pasif' ?>
                            </span>
                        </div>
                        <div class="cat-actions">
                            <a href="/admin/?tab=blog&amp;edit_category=<?= (int) $cat['id'] ?>#categories-section" class="button-sm">Düzenle</a>
                            <form class="inline-form" method="post" onsubmit="return confirm('Bu kategori silinsin mi? Bu kategorideki yazılar otomatik olarak diğer kategoriye aktarılacaktır.')">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                <button class="link-button" type="submit" style="color: #dc2626;">Sil</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                    <p style="color: #64748b; font-size: 14px;">Henüz kategori eklenmemiş.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- YAPAY ZEKÂ İÇERİK STÜDYOSU (SEO & GEO MOTORU) -->
        <section class="admin-section ai-studio-section" id="ai-studio" style="margin-top: 36px;">
            <div class="ai-studio-header">
                <div class="ai-studio-title-wrap">
                    <span class="ai-sparkle-badge">✨ AI</span>
                    <div>
                        <h2 style="margin: 0; font-size: 19px; color: var(--ink);">Yapay Zekâ İçerik Stüdyosu (SEO &amp; GEO Blog Motoru)</h2>
                        <p class="admin-help-text" style="margin: 4px 0 0;">Google AI Overviews (SGE) ve yerel aramalarda zirveye oynayan, E-E-A-T uyumlu derinlikli blog taslağını saniyeler içinde üretin ve tek tıkla düzenleyin.</p>
                    </div>
                </div>
                <span class="ai-engine-status-pill">
                    <?= !empty($currentSettings['gemini_api_key']) ? '⚡ Gemini 2.5 Flash Aktif' : '🧠 Akıllı Yerleşik Motor Aktif' ?>
                </span>
            </div>

            <div class="ai-studio-body">
                <div class="ai-studio-grid">
                    <label style="grid-column: 1 / -1;">
                        <span class="ai-field-label">🎯 Odak Konu / Başlık Fikri</span>
                        <input type="text" id="aiTopic" placeholder="Örn: TYT Matematik Netlerini 20'den 30'a Çıkarma Yolları veya LGS Sınav Kaygısı" style="font-size: 14px; padding: 12px 14px;">
                    </label>

                    <label>
                        <span class="ai-field-label">🏷️ Kategori</span>
                        <select id="aiCategory">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span class="ai-field-label">👥 Hedef Kitle</span>
                        <select id="aiAudience">
                            <option value="YKS Öğrencileri (12. Sınıf &amp; Mezun)">YKS Öğrencileri (12. Sınıf &amp; Mezun)</option>
                            <option value="LGS Öğrencileri (8. Sınıf)">LGS Öğrencileri (8. Sınıf)</option>
                            <option value="Veliler &amp; Anne-Babalar">Veliler &amp; Anne-Babalar</option>
                            <option value="Sınav Kaygısı ve Motivasyon Arayanlar">Sınav Kaygısı &amp; Motivasyon</option>
                        </select>
                    </label>

                    <label>
                        <span class="ai-field-label">🎙️ Anlatım Tonu</span>
                        <select id="aiTone">
                            <option value="Samimi, Profesyonel &amp; Sonuç Odaklı">Samimi, Profesyonel &amp; Sonuç Odaklı</option>
                            <option value="Taktiksel, Hap Bilgiler &amp; Hızlı İlerleme">Taktiksel &amp; Hap Bilgiler</option>
                            <option value="Veriye Dayalı, Analitik &amp; Disiplinli">Veriye Dayalı &amp; Analitik</option>
                        </select>
                    </label>
                </div>

                <div class="ai-studio-switches">
                    <label class="ai-switch-item">
                        <input type="checkbox" id="aiIncludeLocal" checked>
                        <span>📍 <strong>Salihli &amp; Çevre İlçe Entegrasyonu</strong> (Fiziksel ofis, yüz yüze seans, Alaşehir, Ahmetli, Kula)</span>
                    </label>
                    <label class="ai-switch-item">
                        <input type="checkbox" id="aiIncludeTldr" checked>
                        <span>⚡ <strong>Doğrudan Cevap &amp; AI Özeti (TL;DR)</strong> (Google AI Overviews için 45-60 kelimelik hap kutu)</span>
                    </label>
                    <label class="ai-switch-item">
                        <input type="checkbox" id="aiIncludeFaq" checked>
                        <span>❓ <strong>Google SSS (FAQPage Schema) Bloğu</strong> (Arama sonuçlarında zengin akordeon kutuları)</span>
                    </label>
                </div>

                <div class="ai-studio-footer">
                    <button type="button" class="button button-ai-generate" id="btnGenerateAi">
                        <span id="aiBtnSpinner" style="display:none;" class="ai-spin-icon">⏳</span>
                        <span id="aiBtnText">✨ Yapay Zekâ ile Makaleyi Hazırla</span>
                    </button>
                    <div id="aiToast" style="display:none;" class="ai-inline-toast"></div>
                </div>
            </div>
        </section>

        <!-- BLOG YAZISI EKLE / DÜZENLE -->
        <section class="admin-section" id="blog-form" style="margin-top: 36px;">
            <h2><?= $editingPost ? '✏️ Blog Yazısını Düzenle' : '➕ Yeni Blog Yazısı Ekle' ?></h2>
            <form class="admin-editor" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_post">
                <input type="hidden" name="id" value="<?= e((string) ($editingPost['id'] ?? '')) ?>">
                
                <label>Kategori
                    <select name="category_id" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= ((int) ($editingPost['category_id'] ?? 1) === (int) $cat['id']) ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>Yazı Başlığı
                    <input name="title" maxlength="200" value="<?= e($editingPost['title'] ?? '') ?>" placeholder="Örn: TYT ve AYT Netleri Neden Yükselmez?" required>
                </label>

                <label>Bağlantı Adı (URL Slug)
                    <input name="slug" maxlength="200" value="<?= e($editingPost['slug'] ?? '') ?>" placeholder="Boş bırakırsanız başlıktan üretilir">
                </label>

                <div style="grid-column: 1 / -1; background: #f8fafc; border: 1px solid var(--line); border-radius: 8px; padding: 16px;">
                    <h3 style="margin: 0 0 6px; font-size: 14px; color: var(--ink);">🖼️ Kapak Görseli (Opsiyonel)</h3>
                    <p class="admin-help-text" style="margin-bottom: 12px;">Yazıya kapak görseli eklemek isterseniz dosya seçin. Boş bırakırsanız kategoriye özel renkli kart kullanılır.</p>
                    
                    <?php if (!empty($editingPost['image_url'])): ?>
                        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 12px; background: #fff; padding: 10px; border: 1px solid var(--line); border-radius: 6px;">
                            <img src="<?= e($editingPost['image_url']) ?>" alt="Kapak görseli" style="width: 140px; height: 90px; object-fit: cover; border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.06);">
                            <div>
                                <small style="color: #64748b; display: block; margin-bottom: 6px;">Mevcut Görsel: <code><?= e($editingPost['image_url']) ?></code></small>
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #dc2626; cursor: pointer; margin: 0;">
                                    <input type="checkbox" name="remove_image" value="1">
                                    <strong>Bu görseli kaldır</strong>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="image_url" value="<?= e($editingPost['image_url'] ?? '') ?>">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px;">
                        <label style="margin: 0;">Görsel Yükle (Bilgisayardan / Telefondan Seç)
                            <input type="file" name="image_file" accept="image/*">
                        </label>
                        <label style="margin: 0;">Veya Doğrudan Görsel URL'si
                            <input name="image_url_custom" maxlength="500" placeholder="https://... (isteğe bağlı)">
                        </label>
                    </div>
                </div>
                
                <label style="grid-column: 1 / -1;">Kısa Özet (Blog listesinde ve arama motorlarında görünür)
                    <textarea name="excerpt" rows="2" maxlength="500" required><?= e($editingPost['excerpt'] ?? '') ?></textarea>
                </label>

                <label style="grid-column: 1 / -1;">Makale İçeriği (Markdown formatında. Metin içine resim eklemek için: <code>![Görsel Başlığı](/assets/uploads/...)</code>)
                    <textarea name="content" rows="12" required><?= e($editingPost['content'] ?? '') ?></textarea>
                </label>
                
                <label style="max-width: 260px;">Yayın Durumu
                    <select name="status">
                        <option value="published" <?= ($editingPost['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>● Yayında (Sitede Canlı)</option>
                        <option value="draft" <?= ($editingPost['status'] ?? '') === 'draft' ? 'selected' : '' ?>>○ Taslak (Yalnızca Yönetici Görür)</option>
                    </select>
                </label>

                <div style="grid-column: 1 / -1; display: flex; gap: 10px;">
                    <button class="button button-primary" type="submit"><?= $editingPost ? 'Yazıyı Güncelle' : 'Yazıyı Kaydet' ?></button>
                    <?php if ($editingPost): ?>
                        <a class="button button-outline" href="/admin/?tab=blog">İptal</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- BLOG YAZILARI LİSTESİ -->
        <section class="admin-section">
            <h2>Blog Yazıları (<?= count($posts) ?>)</h2>
            <div class="admin-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Başlık &amp; Görüntüle</th>
                            <th>Kategori</th>
                            <th>Durum</th>
                            <th>Son Güncelleme</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= e($item['title']) ?></strong><br>
                                    <small><a href="/blog/<?= rawurlencode($item['slug']) ?>" target="_blank">Görüntüle ↗</a></small>
                                </td>
                                <td>
                                    <span style="display: inline-block; background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 4px; font-size: 11.5px; font-weight: 700;">
                                        <?= e($item['category_name'] ?? 'Genel') ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight: 700; color: <?= $item['status'] === 'published' ? '#2e6b36' : '#888' ?>;">
                                        <?= $item['status'] === 'published' ? '● Yayında' : '○ Taslak' ?>
                                    </span>
                                </td>
                                <td><?= e(date('d.m.Y H:i', strtotime($item['updated_at']))) ?></td>
                                <td>
                                    <a href="/admin/?tab=blog&amp;edit=<?= (int) $item['id'] ?>#blog-form">Düzenle</a> 
                                    <form class="inline-form" method="post" onsubmit="return confirm('Bu yazı silinsin mi?')">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_post">
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <button class="link-button" type="submit" style="color: #dc2626;">Sil</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$posts): ?><tr><td colspan="5">Henüz blog yazısı eklenmemiş.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php elseif ($tab === 'notifications'): ?>
        <section class="admin-section" id="notifications">
            <h2>🔔 Form Bildirim Sistemi (E-posta, Telegram, Webhook)</h2>
            <p class="admin-help-text" style="margin-bottom: 24px;">Web sitenizdeki ön görüşme formu (veli veya öğrenci) doldurulduğunda bildirimlerin anında nereye düşeceğini buradan yönetebilirsiniz.</p>

            <form class="admin-editor" method="post" style="margin-bottom: 30px;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_notifications">

                <div style="grid-column: 1 / -1; background: #f8fafc; border: 1px solid var(--line); border-radius: var(--radius-md); padding: 20px 22px;">
                    <h3 style="margin: 0 0 12px; font-size: 16px;">📧 1. E-posta Bildirimleri</h3>
                    <label style="display: flex; align-items: center; gap: 10px; font-weight: 700; cursor: pointer; margin-bottom: 14px;">
                        <input type="checkbox" name="notify_email_enabled" value="1" <?= setting('notify_email_enabled', '1') === '1' ? 'checked' : '' ?> style="width: auto;">
                        Yeni bir ön görüşme talebi geldiğinde e-posta bildirimi gönder
                    </label>
                    <label>Bildirim Gönderilecek E-posta Adresi
                        <input name="notify_email" type="email" value="<?= e(setting('notify_email', setting('email', 'iletisim@salihlikocluk.com'))) ?>" placeholder="Örn: iletisim@salihlikocluk.com">
                    </label>
                    <small style="color: #64748b; display: block; margin-top: 6px;">Başvuran kişinin adı, telefonu, WhatsApp doğrudan yaz linki, öğrenci seviyesi ve mesajı bu e-posta adresine HTML şablonuyla iletilir.</small>
                </div>

                <div style="grid-column: 1 / -1; background: #f8fafc; border: 1px solid var(--line); border-radius: var(--radius-md); padding: 20px 22px;">
                    <h3 style="margin: 0 0 8px; font-size: 16px;">💬 2. Telegram Anlık Bildirim (Önerilen)</h3>
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 14px;">Telefonunuza anında sesli mesaj düşmesi için Telegram Bot entegrasyonunu kullanabilirsiniz.</p>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <label>Telegram Bot Token
                            <input name="notify_telegram_bot_token" value="<?= e(setting('notify_telegram_bot_token')) ?>" placeholder="Örn: 123456789:ABCdefGhIjkLmNoPqRsTuVwXyZ">
                        </label>
                        <label>Telegram Chat ID
                            <input name="notify_telegram_chat_id" value="<?= e(setting('notify_telegram_chat_id')) ?>" placeholder="Örn: 987654321">
                        </label>
                    </div>
                    <small style="color: #64748b; display: block; margin-top: 8px;">
                        <strong>Nasıl Kurulur?</strong> Telegram uygulamasında <code>@BotFather</code> ile 1 dakikada ücretsiz bot açıp Token alabilirsiniz. Kendi Chat ID numaranızı öğrenmek için ise <code>@userinfobot</code> botuna mesaj atmanız yeterlidir.
                    </small>
                </div>

                <div style="grid-column: 1 / -1; background: #f8fafc; border: 1px solid var(--line); border-radius: var(--radius-md); padding: 20px 22px;">
                    <h3 style="margin: 0 0 8px; font-size: 16px;">🔗 3. Webhook Entegrasyonu (Zapier / Make / Discord)</h3>
                    <label>Webhook URL (İsteğe Bağlı)
                        <input name="notify_webhook_url" type="url" value="<?= e(setting('notify_webhook_url')) ?>" placeholder="https://hook.eu1.make.com/... veya https://discord.com/api/webhooks/...">
                    </label>
                    <small style="color: #64748b; display: block; margin-top: 6px;">Yeni başvuru bilgileri JSON formatında bu webhook adresine anında POST edilir.</small>
                </div>

                <div style="grid-column: 1 / -1; margin-top: 10px;">
                    <button class="button button-primary" type="submit">Bildirim Ayarlarını Kaydet</button>
                </div>
            </form>

            <div style="background: #ffffff; border: 1px solid var(--line); border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-sm);">
                <h3 style="margin: 0 0 8px; font-size: 16px;">🧪 Test Bildirimi Çalıştır</h3>
                <p style="font-size: 13.5px; color: #64748b; margin-bottom: 16px;">Yapılandırdığınız e-posta ve Telegram kanallarına örnek bir test başvurusu göndererek sistemin sorunsuz çalıştığını doğrulayın.</p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="send_test_notification">
                    <button class="button button-outline" type="submit">🔔 Test Bildirimi Gönder</button>
                </form>
            </div>
        </section>

    <?php elseif ($tab === 'leads'): ?>
        <section class="admin-section" id="requests">
            <h2>Ön Görüşme Talepleri (<?= count($leads) ?>)</h2>
            <div class="admin-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tarih / Öğrenci</th>
                            <th>İletişim</th>
                            <th>Durum</th>
                            <th>Mesaj</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): ?>
                            <tr>
                                <td>
                                    <small><?= e($lead['created_at']) ?></small><br>
                                    <strong><?= e($lead['name']) ?></strong><br>
                                    <span class="badge" style="background:#506a58; color:#fff; font-size:10px;"><?= e($lead['student_level']) ?></span>
                                </td>
                                <td>
                                    <a href="tel:<?= e($lead['phone']) ?>" style="font-weight:700;">📞 <?= e($lead['phone']) ?></a><br>
                                    <a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $lead['phone'])) ?>" target="_blank" rel="noopener" style="color:#207238;">💬 WhatsApp</a>
                                    <?php if ($lead['email']): ?><br><a href="mailto:<?= e($lead['email']) ?>">✉️ <?= e($lead['email']) ?></a><?php endif; ?>
                                </td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="update_lead">
                                        <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                                        <select name="status" aria-label="Talep durumu" onchange="this.form.submit()">
                                            <option value="new" <?= $lead['status'] === 'new' ? 'selected' : '' ?>>🔴 Yeni</option>
                                            <option value="contacted" <?= $lead['status'] === 'contacted' ? 'selected' : '' ?>>🟡 Görüşüldü</option>
                                            <option value="closed" <?= $lead['status'] === 'closed' ? 'selected' : '' ?>>🟢 Tamamlandı</option>
                                        </select>
                                    </form>
                                </td>
                                <td><?= nl2br(e($lead['message'])) ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Bu başvuru kaydı silinsin mi?')">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_lead">
                                        <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                                        <button class="link-button" type="submit" style="color:#ba3224;">Sil</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$leads): ?><tr><td colspan="5">Henüz ön görüşme talebi gelmedi.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php elseif ($tab === 'analytics'): ?>
        <section class="admin-section" id="analytics">
            <div class="analytics-header">
                <div>
                    <h2>📊 Canlı Ziyaretçi &amp; Trafik Analizi</h2>
                    <p class="admin-help-text">Sitenize hangi ilden, hangi IP ile girildiğini, hangi sayfada kaç dakika kaldığını ve ziyaret kaynağını canlı takip edin.</p>
                </div>
                <div class="analytics-actions">
                    <form method="post" onsubmit="return confirm('30 günden eski ziyaretçi kayıtları silinsin mi?')" style="display:inline-block;">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="clear_analytics_logs">
                        <input type="hidden" name="days" value="30">
                        <button class="button button-outline" type="submit" style="font-size:12px; padding:6px 12px; color:#ba3224; border-color:#e0b4b4;">🗑️ Eski Logları Temizle (&gt;30 Gün)</button>
                    </form>
                </div>
            </div>

            <!-- Zaman ve Şehir Filtresi -->
            <div class="analytics-filter-bar">
                <div class="analytics-range-pills">
                    <a href="/admin/?tab=analytics&range=today<?= $analyticsCity !== '' ? '&city=' . urlencode($analyticsCity) : '' ?>" class="pill-btn <?= $analyticsRange === 'today' ? 'is-active' : '' ?>">Bugün</a>
                    <a href="/admin/?tab=analytics&range=7days<?= $analyticsCity !== '' ? '&city=' . urlencode($analyticsCity) : '' ?>" class="pill-btn <?= $analyticsRange === '7days' ? 'is-active' : '' ?>">Son 7 Gün</a>
                    <a href="/admin/?tab=analytics&range=30days<?= $analyticsCity !== '' ? '&city=' . urlencode($analyticsCity) : '' ?>" class="pill-btn <?= $analyticsRange === '30days' ? 'is-active' : '' ?>">Son 30 Gün</a>
                    <a href="/admin/?tab=analytics&range=all<?= $analyticsCity !== '' ? '&city=' . urlencode($analyticsCity) : '' ?>" class="pill-btn <?= $analyticsRange === 'all' ? 'is-active' : '' ?>">Tüm Zamanlar</a>
                </div>

                <form method="get" class="analytics-city-filter">
                    <input type="hidden" name="tab" value="analytics">
                    <input type="hidden" name="range" value="<?= e($analyticsRange) ?>">
                    <input type="text" name="city" value="<?= e($analyticsCity) ?>" placeholder="Şehir ara (Örn: Manisa, İzmir)" aria-label="Şehir ara">
                    <button type="submit" class="button button-dark" style="padding: 7px 14px; font-size:13px;">Filtrele</button>
                    <?php if ($analyticsCity !== ''): ?>
                        <a href="/admin/?tab=analytics&range=<?= e($analyticsRange) ?>" class="link-button" style="font-size:12px; color:#ba3224;">Filtreyi Temizle</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- KPI İstatistik Kartları -->
            <div class="analytics-kpi-grid">
                <div class="kpi-card">
                    <span class="kpi-icon">👁️</span>
                    <div class="kpi-meta">
                        <span class="kpi-value"><?= number_format($analyticsData['total_views']) ?></span>
                        <span class="kpi-label">Sayfa Görüntüleme</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <span class="kpi-icon">👤</span>
                    <div class="kpi-meta">
                        <span class="kpi-value"><?= number_format($analyticsData['unique_visitors']) ?></span>
                        <span class="kpi-label">Tekil Ziyaretçi</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <span class="kpi-icon">⏱️</span>
                    <div class="kpi-meta">
                        <span class="kpi-value"><?= format_duration($analyticsData['avg_duration_seconds']) ?></span>
                        <span class="kpi-label">Ortalama Kalma Süresi</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <span class="kpi-icon">📱</span>
                    <div class="kpi-meta">
                        <span class="kpi-value">
                            <?= (int) ($analyticsData['devices']['mobile'] ?? 0) ?> Mob / <?= (int) ($analyticsData['devices']['desktop'] ?? 0) ?> Mas
                        </span>
                        <span class="kpi-label">Cihaz Dağılımı</span>
                    </div>
                </div>
            </div>

            <!-- İki Kolonlu Dağılım Analizi -->
            <div class="analytics-columns-grid">
                <!-- Şehir Dağılımı -->
                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h3>🏙️ Hangi İlden Gelindi? (Şehir Dağılımı)</h3>
                    </div>
                    <?php if (!empty($analyticsData['top_cities'])): ?>
                        <?php 
                            $maxHits = max(array_column($analyticsData['top_cities'], 'hit_count')) ?: 1;
                        ?>
                        <div class="city-distribution-list">
                            <?php foreach ($analyticsData['top_cities'] as $cItem): ?>
                                <?php 
                                    $cityPercent = round(($cItem['hit_count'] / $maxHits) * 100); 
                                ?>
                                <div class="city-row">
                                    <div class="city-info">
                                        <a href="/admin/?tab=analytics&range=<?= e($analyticsRange) ?>&city=<?= urlencode(str_replace(' (Yönetici)', '', $cItem['city'])) ?>" class="city-name">
                                            📍 <?= e($cItem['city']) ?>
                                        </a>
                                        <span class="city-count"><?= (int) $cItem['hit_count'] ?> ziyaret</span>
                                    </div>
                                    <div class="city-bar-wrap">
                                        <div class="city-bar-fill" style="width: <?= $cityPercent ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="admin-help-text" style="padding:20px 0;">Seçilen filtrede henüz şehir verisi bulunmuyor.</p>
                    <?php endif; ?>
                </div>

                <!-- En Çok Okunan Sayfalar & Kalma Süresi -->
                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h3>📄 Hangi Sayfadaydı &amp; Kalma Süreleri</h3>
                    </div>
                    <?php if (!empty($analyticsData['top_pages'])): ?>
                        <div class="top-pages-list">
                            <?php foreach ($analyticsData['top_pages'] as $pItem): ?>
                                <div class="page-row">
                                    <div class="page-title-box">
                                        <strong><?= e($pItem['page_title'] ?: $pItem['page_url']) ?></strong>
                                        <a href="<?= e($pItem['page_url']) ?>" target="_blank" class="page-link" rel="noopener"><?= e($pItem['page_url']) ?> ↗</a>
                                    </div>
                                    <div class="page-stats-box">
                                        <span class="page-hits"><?= (int) $pItem['hit_count'] ?> tık</span>
                                        <span class="page-duration-pill">⏱️ <?= format_duration((int) round((float) $pItem['avg_duration'])) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="admin-help-text" style="padding:20px 0;">Seçilen filtrede henüz sayfa verisi bulunmuyor.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Canlı Ziyaretçi & IP Log Listesi -->
            <div class="analytics-card" style="margin-top:24px;">
                <div class="analytics-card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div>
                        <h3 style="margin:0 0 4px 0;">⏱️ Canlı Ziyaretçi Günlüğü (Son <?= count($analyticsData['recent_logs']) ?> Giriş)</h3>
                        <p class="admin-help-text" style="margin:0;">Ziyaretçinin hangi ilden ve IP'den girdiği, hangi sayfada kaç dakika kaldığı ve geliş kaynağı.</p>
                    </div>
                    <a href="/admin/?tab=analytics&range=<?= e($analyticsRange) ?><?= $analyticsCity !== '' ? '&city=' . urlencode($analyticsCity) : '' ?>" class="button button-outline" style="font-size:12px; padding:6px 12px;">🔄 Listeyi Yenile</a>
                </div>

                <div class="admin-table-wrap">
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Tarih / Saat</th>
                                <th>İl / Şehir</th>
                                <th>Kim / IP &amp; Cihaz</th>
                                <th>Hangi Sayfada?</th>
                                <th>Kaç Dk Kaldı?</th>
                                <th>Kaynak (Referrer)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analyticsData['recent_logs'] as $log): ?>
                                <tr>
                                    <td style="white-space:nowrap;">
                                        <strong><?= date('d.m.Y', strtotime($log['created_at'])) ?></strong><br>
                                        <span style="color:#666; font-size:12px;"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
                                    </td>
                                    <td>
                                        <div class="city-badge">
                                            <span class="badge" style="background:#eef2ff; color:#3730a3; font-weight:700;">
                                                📍 <?= e($log['city']) ?>
                                            </span>
                                            <?php if (!empty($log['country']) && $log['country'] !== 'Türkiye'): ?>
                                                <small style="color:#777; display:block;"><?= e($log['country']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <code style="font-size:12px; background:#f3f4f6; padding:2px 6px; border-radius:4px; font-weight:600;"><?= e($log['ip_address']) ?></code>
                                        <div style="margin-top:4px; font-size:11px; color:#555;">
                                            <?php if ($log['device_type'] === 'mobile'): ?>
                                                📱 Mobil
                                            <?php elseif ($log['device_type'] === 'tablet'): ?>
                                                📟 Tablet
                                            <?php else: ?>
                                                💻 Masaüstü
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight:600; font-size:13px; color:#111;">
                                            <?= e($log['page_title'] ?: $log['page_url']) ?>
                                        </div>
                                        <a href="<?= e($log['page_url']) ?>" target="_blank" rel="noopener" style="font-size:11px; color:#2563eb; text-decoration:none;">
                                            <?= e($log['page_url']) ?> ↗
                                        </a>
                                    </td>
                                    <td>
                                        <?php 
                                            $dur = (int) $log['duration_seconds'];
                                            if ($dur >= 60) {
                                                $pillStyle = 'background:#dcfce7; color:#166534; font-weight:700;';
                                            } elseif ($dur > 0) {
                                                $pillStyle = 'background:#fef9c3; color:#854d0e; font-weight:600;';
                                            } else {
                                                $pillStyle = 'background:#f3f4f6; color:#6b7280; font-size:11px;';
                                            }
                                        ?>
                                        <span class="duration-pill" style="<?= $pillStyle ?>">
                                            ⏱️ <?= format_duration($dur) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-size:12px; font-weight:500; color:#374151;">
                                            <?= e($log['referrer']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($analyticsData['recent_logs'])): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:30px; color:#666;">
                                        Seçilen filtre ve zaman diliminde henüz ziyaretçi kaydı bulunmuyor.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    <?php elseif ($tab === 'ai'): ?>
        <section class="admin-section" id="ai-settings">
            <div class="analytics-header">
                <div>
                    <h2>🤖 Yapay Zekâ (AI) API Yönetimi &amp; Blog Motoru</h2>
                    <p class="admin-help-text">Blog yazılarını ve sınav rehberlerini tek tıkla üretmek için kullanacağınız yapay zekâ sağlayıcısını, API anahtarlarını ve içerik kurallarını buradan yönetin.</p>
                </div>
                <div>
                    <?php 
                        $curProv = setting('ai_provider', 'gemini');
                        $hasGemini = !empty($currentSettings['gemini_api_key']);
                        $hasOpenAi = !empty($currentSettings['openai_api_key']);
                    ?>
                    <?php if ($curProv === 'gemini' && $hasGemini): ?>
                        <span class="badge" style="background:#dcfce7; color:#15803d; font-size:13px; padding:6px 14px;">🟢 Gemini API Aktif (<?= e(setting('gemini_model', 'gemini-2.5-flash')) ?>)</span>
                    <?php elseif ($curProv === 'openai' && $hasOpenAi): ?>
                        <span class="badge" style="background:#eff6ff; color:#1d4ed8; font-size:13px; padding:6px 14px;">🟢 OpenAI API Aktif (<?= e(setting('openai_model', 'gpt-4o-mini')) ?>)</span>
                    <?php else: ?>
                        <span class="badge" style="background:#f3f4f6; color:#4b5563; font-size:13px; padding:6px 14px;">🧠 Dahili Yerleşik Motor Devrede</span>
                    <?php endif; ?>
                </div>
            </div>

            <form class="admin-editor" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_ai_settings">

                <!-- 1. Sağlayıcı Seçimi -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:24px;">
                    <h3 style="margin-top:0; margin-bottom:12px; font-size:16px;">1. Tercih Edilen Yapay Zekâ Motoru</h3>
                    <p class="admin-help-text" style="margin-bottom:16px;">Yeni bir blog yazısı oluşturulurken öncelikli olarak hangi yapay zekâ altyapısının çalıştırılacağını seçin:</p>
                    
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:16px;">
                        <label style="display:flex; align-items:flex-start; gap:10px; background:#fff; padding:16px; border:1px solid #d1d5db; border-radius:10px; cursor:pointer;">
                            <input type="radio" name="ai_provider" value="gemini" <?= setting('ai_provider', 'gemini') === 'gemini' ? 'checked' : '' ?> style="margin-top:4px;">
                            <div>
                                <strong style="display:block; color:#0f172a;">⚡ Google Gemini (Önerilen)</strong>
                                <small style="color:#64748b; display:block; margin-top:4px;">Gemini 2.5 Flash ile saniyeler içinde zengin, Türkçe SEO uyumlu metin üretir. Ücretsiz kullanım limiti çok geniştir.</small>
                            </div>
                        </label>

                        <label style="display:flex; align-items:flex-start; gap:10px; background:#fff; padding:16px; border:1px solid #d1d5db; border-radius:10px; cursor:pointer;">
                            <input type="radio" name="ai_provider" value="openai" <?= setting('ai_provider') === 'openai' ? 'checked' : '' ?> style="margin-top:4px;">
                            <div>
                                <strong style="display:block; color:#0f172a;">🧠 OpenAI (ChatGPT)</strong>
                                <small style="color:#64748b; display:block; margin-top:4px;">GPT-4o Mini veya GPT-4o modelleri ile yüksek kalitede, derinlikli blog içerikleri üretir.</small>
                            </div>
                        </label>

                        <label style="display:flex; align-items:flex-start; gap:10px; background:#fff; padding:16px; border:1px solid #d1d5db; border-radius:10px; cursor:pointer;">
                            <input type="radio" name="ai_provider" value="builtin" <?= setting('ai_provider') === 'builtin' ? 'checked' : '' ?> style="margin-top:4px;">
                            <div>
                                <strong style="display:block; color:#0f172a;">🛠️ Dahili Şablon Motoru</strong>
                                <small style="color:#64748b; display:block; margin-top:4px;">Hiçbir harici API anahtarına gerek duymadan, sistemin kendi yerleşik şablonlarıyla anında tam makale oluşturur.</small>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- 2. Google Gemini API Ayarları -->
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:24px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
                        <h3 style="margin:0; font-size:16px; color:#1e293b;">⚡ Google Gemini API Yapılandırması</h3>
                        <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener" style="font-size:12px; color:#2563eb; text-decoration:none; font-weight:700;">Google AI Studio'dan Ücretsiz API Anahtarı Al ↗</a>
                    </div>

                    <div style="display:grid; grid-template-columns:2fr 1fr; gap:16px; margin-bottom:16px;">
                        <label style="margin:0;">Gemini API Anahtarı (API Key)
                            <div style="display:flex; gap:8px; margin-top:6px;">
                                <input type="password" id="gemini_api_key_input" name="gemini_api_key" value="<?= e($currentSettings['gemini_api_key'] ?? '') ?>" placeholder="AIzaSy..." style="flex:1;">
                                <button type="button" class="button button-outline" onclick="togglePasswordVisibility('gemini_api_key_input', this)" style="padding:6px 12px; font-size:12px;">Göster</button>
                            </div>
                        </label>

                        <label style="margin:0;">Model
                            <select name="gemini_model" style="margin-top:6px;">
                                <option value="gemini-2.5-flash" <?= setting('gemini_model', 'gemini-2.5-flash') === 'gemini-2.5-flash' ? 'selected' : '' ?>>gemini-2.5-flash (Önerilen / Çok Hızlı)</option>
                                <option value="gemini-1.5-flash" <?= setting('gemini_model') === 'gemini-1.5-flash' ? 'selected' : '' ?>>gemini-1.5-flash</option>
                                <option value="gemini-1.5-pro" <?= setting('gemini_model') === 'gemini-1.5-pro' ? 'selected' : '' ?>>gemini-1.5-pro (Kapsamlı)</option>
                            </select>
                        </label>
                    </div>

                    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                        <button type="button" class="button button-outline" onclick="testAiApi('gemini')" style="font-size:12px; padding:7px 16px; font-weight:700;">
                            🔌 Gemini Bağlantısını Test Et
                        </button>
                        <span id="gemini_test_feedback" style="font-size:13px; font-weight:600; display:none;"></span>
                    </div>
                </div>

                <!-- 3. OpenAI API Ayarları -->
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:24px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
                        <h3 style="margin:0; font-size:16px; color:#1e293b;">🧠 OpenAI (ChatGPT) API Yapılandırması</h3>
                        <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener" style="font-size:12px; color:#2563eb; text-decoration:none; font-weight:700;">OpenAI Platformundan API Anahtarı Al ↗</a>
                    </div>

                    <div style="display:grid; grid-template-columns:2fr 1fr; gap:16px; margin-bottom:16px;">
                        <label style="margin:0;">OpenAI API Anahtarı (API Key)
                            <div style="display:flex; gap:8px; margin-top:6px;">
                                <input type="password" id="openai_api_key_input" name="openai_api_key" value="<?= e($currentSettings['openai_api_key'] ?? '') ?>" placeholder="sk-proj-..." style="flex:1;">
                                <button type="button" class="button button-outline" onclick="togglePasswordVisibility('openai_api_key_input', this)" style="padding:6px 12px; font-size:12px;">Göster</button>
                            </div>
                        </label>

                        <label style="margin:0;">Model
                            <select name="openai_model" style="margin-top:6px;">
                                <option value="gpt-4o-mini" <?= setting('openai_model', 'gpt-4o-mini') === 'gpt-4o-mini' ? 'selected' : '' ?>>gpt-4o-mini (Ekonomik &amp; Hızlı)</option>
                                <option value="gpt-4o" <?= setting('openai_model') === 'gpt-4o' ? 'selected' : '' ?>>gpt-4o (Amiral Gemisi)</option>
                            </select>
                        </label>
                    </div>

                    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                        <button type="button" class="button button-outline" onclick="testAiApi('openai')" style="font-size:12px; padding:7px 16px; font-weight:700;">
                            🔌 OpenAI Bağlantısını Test Et
                        </button>
                        <span id="openai_test_feedback" style="font-size:13px; font-weight:600; display:none;"></span>
                    </div>
                </div>

                <!-- 4. Blog Üretim Varsayılan Parametreleri -->
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:24px;">
                    <h3 style="margin-top:0; margin-bottom:16px; font-size:16px; color:#1e293b;">⚙️ Blog Yazısı Üretim Şablon ve SEO Kuralları</h3>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <label style="margin:0;">Varsayılan Anlatım Dili / Üslup (Tone)
                            <input name="ai_default_tone" value="<?= e(setting('ai_default_tone', 'Samimi, Profesyonel & Sonuç Odaklı')) ?>" placeholder="Örn: Samimi, Profesyonel & Sonuç Odaklı">
                        </label>
                        <label style="margin:0;">Varsayılan Hedef Kitle
                            <input name="ai_default_audience" value="<?= e(setting('ai_default_audience', 'YKS & LGS Öğrencileri ve Veliler')) ?>" placeholder="Örn: YKS & LGS Öğrencileri ve Veliler">
                        </label>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:12px; margin-top:14px; background:#f8fafc; padding:16px; border-radius:10px;">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:600; font-size:13.5px; margin:0;">
                            <input type="checkbox" name="ai_default_local" value="1" <?= setting('ai_default_local', '1') === '1' ? 'checked' : '' ?>>
                            Salihli, Alaşehir, Kula yerel anahtar kelimelerini doğal olarak metne yedir (Lokal SEO)
                        </label>
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:600; font-size:13.5px; margin:0;">
                            <input type="checkbox" name="ai_default_faq" value="1" <?= setting('ai_default_faq', '1') === '1' ? 'checked' : '' ?>>
                            Makale sonuna otomatik Sıkça Sorulan Sorular (Google FAQ Schema) ekle
                        </label>
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:600; font-size:13.5px; margin:0;">
                            <input type="checkbox" name="ai_default_tldr" value="1" <?= setting('ai_default_tldr', '1') === '1' ? 'checked' : '' ?>>
                            Google AI Overviews (SGE) için makale başına hap özet (TL;DR) kutusu ekle
                        </label>
                    </div>
                </div>

                <button class="button button-primary" type="submit" style="padding:12px 28px; font-weight:800; font-size:14px;">
                    💾 Yapay Zekâ ve API Ayarlarını Kaydet
                </button>
            </form>
        </section>
    <?php endif; ?>
<?php endif; ?>
</main>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const list = document.getElementById('categorySortableList');
    if (!list) return;

    let draggedItem = null;

    list.querySelectorAll('.drag-category-item').forEach(item => {
        item.addEventListener('dragstart', function(e) {
            draggedItem = this;
            setTimeout(() => this.classList.add('is-dragging'), 0);
            e.dataTransfer.effectAllowed = 'move';
        });

        item.addEventListener('dragend', function() {
            this.classList.remove('is-dragging');
            draggedItem = null;
            saveNewCategoryOrder();
        });

        item.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (this === draggedItem) return;

            const rect = this.getBoundingClientRect();
            const next = (e.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
            list.insertBefore(draggedItem, next ? this.nextSibling : this);
        });
    });

    function saveNewCategoryOrder() {
        const ids = Array.from(list.querySelectorAll('.drag-category-item')).map(el => parseInt(el.getAttribute('data-id'), 10));
        fetch('/admin/?action=reorder_categories', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({order: ids})
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.success) {
                const toast = document.getElementById('dragNotice');
                if (toast) {
                    toast.style.display = 'inline-block';
                    setTimeout(() => { toast.style.display = 'none'; }, 2500);
                }
            }
        })
        .catch(err => console.error('Reorder error:', err));
    }

    // AI Blog Generator Handler
    const btnAi = document.getElementById('btnGenerateAi');
    if (btnAi) {
        btnAi.addEventListener('click', function() {
            const topic = (document.getElementById('aiTopic').value || '').trim();
            if (!topic) {
                alert('Lütfen bir odak konu veya başlık fikri yazın (Örn: TYT Matematik Netlerini Artırma Yolları).');
                document.getElementById('aiTopic').focus();
                return;
            }

            const categoryId = document.getElementById('aiCategory').value;
            const audience = document.getElementById('aiAudience').value;
            const tone = document.getElementById('aiTone').value;
            const includeLocal = document.getElementById('aiIncludeLocal').checked ? 1 : 0;
            const includeTldr = document.getElementById('aiIncludeTldr').checked ? 1 : 0;
            const includeFaq = document.getElementById('aiIncludeFaq').checked ? 1 : 0;

            const spinner = document.getElementById('aiBtnSpinner');
            const btnText = document.getElementById('aiBtnText');
            const toast = document.getElementById('aiToast');

            btnAi.disabled = true;
            spinner.style.display = 'inline-block';
            btnText.textContent = 'Yapay zekâ SEO & GEO uyumlu makaleyi hazırlıyor...';
            toast.style.display = 'none';

            fetch('/admin/?action=ai_generate_blog', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    topic: topic,
                    category_id: categoryId,
                    audience: audience,
                    tone: tone,
                    include_local: includeLocal,
                    include_tldr: includeTldr,
                    include_faq: includeFaq
                })
            })
            .then(res => res.json())
            .then(response => {
                btnAi.disabled = false;
                spinner.style.display = 'none';
                btnText.textContent = '✨ Yapay Zekâ ile Makaleyi Hazırla';

                if (response && response.success && response.data) {
                    const d = response.data;
                    const form = document.querySelector('#blog-form form');
                    if (form) {
                        const titleEl = form.querySelector('input[name="title"]');
                        const slugEl = form.querySelector('input[name="slug"]');
                        const excerptEl = form.querySelector('textarea[name="excerpt"]');
                        const contentEl = form.querySelector('textarea[name="content"]');
                        const catEl = form.querySelector('select[name="category_id"]');

                        if (titleEl) titleEl.value = d.title;
                        if (slugEl) slugEl.value = d.slug;
                        if (excerptEl) excerptEl.value = d.excerpt;
                        if (contentEl) contentEl.value = d.content;
                        if (catEl && d.category_id) catEl.value = d.category_id;
                        const statusEl = form.querySelector('select[name="status"]');
                        if (statusEl) statusEl.value = 'published';

                        const sourceNote = (d.source === 'gemini') ? ' (Gemini 2.5 Flash ile üretildi)' : '';
                        toast.textContent = '✓ Makale hazırlandı ve form alanlarına dolduruldu!' + sourceNote;
                        toast.style.display = 'inline-block';
                        toast.className = 'ai-inline-toast ai-toast-success';

                        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        form.classList.add('ai-form-highlight');
                        setTimeout(() => form.classList.remove('ai-form-highlight'), 2500);
                    }
                } else {
                    toast.textContent = 'Bir hata oluştu, lütfen tekrar deneyin.';
                    toast.style.display = 'inline-block';
                    toast.className = 'ai-inline-toast ai-toast-error';
                }
            })
            .catch(err => {
                console.error('AI Generation error:', err);
                btnAi.disabled = false;
                spinner.style.display = 'none';
                btnText.textContent = '✨ Yapay Zekâ ile Makaleyi Hazırla';
                toast.textContent = 'Bağlantı hatası oluştu.';
                toast.style.display = 'inline-block';
                toast.className = 'ai-inline-toast ai-toast-error';
            });
        });
    }
});

function togglePasswordVisibility(id, btn) {
    const el = document.getElementById(id);
    if (!el) return;
    if (el.type === 'password') {
        el.type = 'text';
        btn.textContent = 'Gizle';
    } else {
        el.type = 'password';
        btn.textContent = 'Göster';
    }
}

function testAiApi(provider) {
    const feedback = document.getElementById(provider + '_test_feedback');
    const keyInput = document.getElementById(provider + '_api_key_input');
    const modelSelect = document.querySelector('select[name="' + provider + '_model"]');
    
    if (!feedback || !keyInput) return;
    const apiKey = keyInput.value.trim();
    if (!apiKey) {
        feedback.style.display = 'inline-block';
        feedback.style.color = '#dc2626';
        feedback.textContent = 'Lütfen test etmek için önce API anahtarını yazın.';
        return;
    }

    feedback.style.display = 'inline-block';
    feedback.style.color = '#64748b';
    feedback.textContent = 'Bağlantı test ediliyor, lütfen bekleyin...';

    fetch('/admin/?action=test_ai_api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            provider: provider,
            api_key: apiKey,
            model: modelSelect ? modelSelect.value : ''
        })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            feedback.style.color = '#15803d';
            feedback.textContent = d.message;
        } else {
            feedback.style.color = '#dc2626';
            feedback.textContent = d.message;
        }
    })
    .catch(err => {
        feedback.style.color = '#dc2626';
        feedback.textContent = 'Bağlantı isteği başarısız oldu.';
    });
}
</script>
</body>
</html>
