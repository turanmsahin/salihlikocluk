<?php
declare(strict_types=1);

/**
 * Salihli Koçluk - Ziyaretçi Analizi & Canlı Log Motoru
 * Hafif, KVKK Uyumlu, Sayfa Süresi ve Şehir Tespiti
 */

function get_client_ip(): string
{
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
}

function is_private_ip(string $ip): bool
{
    return $ip === '127.0.0.1' 
        || $ip === '::1' 
        || str_starts_with($ip, '192.168.') 
        || str_starts_with($ip, '10.') 
        || str_starts_with($ip, '172.16.')
        || str_starts_with($ip, 'fe80:');
}

function resolve_ip_city(string $ip): array
{
    global $db;

    // Cloudflare Header varsa anında al
    if (!empty($_SERVER['HTTP_CF_IPCITY'])) {
        $cfCity = trim((string) $_SERVER['HTTP_CF_IPCITY']);
        $cfCountry = trim((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'Türkiye'));
        return ['city' => $cfCity, 'country' => $cfCountry];
    }

    if (is_private_ip($ip)) {
        return ['city' => 'Manisa (Salihli / Yerel)', 'country' => 'Türkiye'];
    }

    // SQLite önbellekten kontrol et (hızlı sorgu, dış API'ye tekrar gitmez)
    try {
        $stmt = $db->prepare('SELECT city, country FROM ip_cache WHERE ip = :ip');
        $stmt->execute(['ip' => $ip]);
        $cached = $stmt->fetch();
        if ($cached && !empty($cached['city'])) {
            return ['city' => $cached['city'], 'country' => $cached['country']];
        }
    } catch (Throwable $e) {
        // Önbellek hatasında devam et
    }

    // IP-API üzerinden şehir tespiti (1 saniye zaman aşımı - siteyi asla yavaşlatmaz)
    $city = 'Bilinmiyor';
    $country = 'Türkiye';

    if (function_exists('curl_init')) {
        $ch = curl_init("http://ip-api.com/json/{$ip}?fields=status,city,regionName,country");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        $res = curl_exec($ch);
        curl_close($ch);

        if ($res) {
            $data = json_decode($res, true);
            if (is_array($data) && ($data['status'] ?? '') === 'success') {
                $city = !empty($data['city']) ? $data['city'] : ($data['regionName'] ?? 'Bilinmiyor');
                $country = $data['country'] ?? 'Türkiye';
            }
        }
    }

    // Önbelleğe kaydet
    try {
        $ins = $db->prepare('INSERT OR REPLACE INTO ip_cache (ip, city, country) VALUES (:ip, :city, :country)');
        $ins->execute(['ip' => $ip, 'city' => $city, 'country' => $country]);
    } catch (Throwable $e) {
    }

    return ['city' => $city, 'country' => $country];
}

function detect_device_type(string $ua): string
{
    if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $ua)) {
        return 'tablet';
    }
    if (preg_match('/(mobile|iphone|ipod|blackberry|opera mini|iemobile|wpdesktop)/i', $ua)) {
        return 'mobile';
    }
    return 'desktop';
}

function format_referrer(string $ref): string
{
    $ref = trim($ref);
    if ($ref === '') {
        return '🔗 Doğrudan (Direkt)';
    }

    $host = parse_url($ref, PHP_URL_HOST) ?: '';
    if (str_contains($host, 'google.')) {
        return '🔍 Google Arama';
    }
    if (str_contains($host, 'instagram.')) {
        return '📷 Instagram';
    }
    if (str_contains($host, 't.co') || str_contains($host, 'twitter.') || str_contains($host, 'x.com')) {
        return '𝕏 Twitter / X';
    }
    if (str_contains($host, 'facebook.')) {
        return '📘 Facebook';
    }
    if (str_contains($host, 'whatsapp.')) {
        return '💬 WhatsApp';
    }
    if (str_contains($host, 'youtube.')) {
        return '▶️ YouTube';
    }
    if (str_contains($host, 'salihlikocluk.com.tr') || str_contains($host, 'salihlikocluk.com')) {
        return 'Site İçi Dolaşım';
    }

    return $host !== '' ? $host : 'Diğer';
}

function format_duration(int $seconds): string
{
    if ($seconds <= 0) {
        return 'Az önce girdi';
    }
    if ($seconds < 60) {
        return $seconds . ' sn';
    }
    $mins = (int) floor($seconds / 60);
    $rem = $seconds % 60;
    if ($rem === 0) {
        return $mins . ' dk';
    }
    return $mins . ' dk ' . $rem . ' sn';
}

function get_visitor_session_id(): string
{
    if (!empty($_COOKIE['s_vid']) && preg_match('/^[a-f0-9]{16,32}$/i', $_COOKIE['s_vid'])) {
        return $_COOKIE['s_vid'];
    }
    $newId = bin2hex(random_bytes(10));
    if (!headers_sent()) {
        setcookie('s_vid', $newId, [
            'expires' => time() + 86400 * 30,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    return $newId;
}

function record_page_visit(string $pageUrl, string $pageTitle): ?int
{
    global $db;

    // Statik dosya veya API isteklerini kaydetme
    if (str_contains($pageUrl, '.') && !str_ends_with($pageUrl, '.php')) {
        return null;
    }
    if (str_starts_with($pageUrl, '/admin') || str_starts_with($pageUrl, '/api/')) {
        return null;
    }

    // Bot filtreleme (Googlebot, Bingbot vb.)
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (preg_match('/(bot|crawler|spider|crawling|slurp|duckduckbot)/i', $ua)) {
        return null;
    }

    $ip = get_client_ip();
    $geo = resolve_ip_city($ip);
    $sessionId = get_visitor_session_id();
    $device = detect_device_type($ua);
    $ref = format_referrer((string) ($_SERVER['HTTP_REFERER'] ?? ''));

    // Yönetici kontrolü
    if (is_admin()) {
        $geo['city'] = $geo['city'] . ' (Yönetici)';
    }

    try {
        $stmt = $db->prepare('INSERT INTO visitor_logs (session_id, ip_address, city, country, page_url, page_title, referrer, user_agent, device_type, created_at, last_ping_at) VALUES (:session_id, :ip_address, :city, :country, :page_url, :page_title, :referrer, :user_agent, :device_type, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
        $stmt->execute([
            'session_id' => $sessionId,
            'ip_address' => $ip,
            'city' => $geo['city'],
            'country' => $geo['country'],
            'page_url' => $pageUrl,
            'page_title' => $pageTitle,
            'referrer' => $ref,
            'user_agent' => mb_substr($ua, 0, 250),
            'device_type' => $device,
        ]);
        return (int) $db->lastInsertId();
    } catch (Throwable $e) {
        return null;
    }
}

function update_visit_duration(int $logId, int $seconds): bool
{
    global $db;
    if ($logId <= 0 || $seconds < 0) {
        return false;
    }
    // Maksimum makul kalma süresi (örn: 3 saat / 10800 saniye)
    $seconds = min($seconds, 10800);

    try {
        $stmt = $db->prepare('UPDATE visitor_logs SET duration_seconds = :seconds, last_ping_at = CURRENT_TIMESTAMP WHERE id = :id');
        return $stmt->execute(['seconds' => $seconds, 'id' => $logId]);
    } catch (Throwable $e) {
        return false;
    }
}

function get_analytics_metrics(string $filterCity = '', string $dateRange = 'today'): array
{
    global $db;

    $dateSql = "created_at >= date('now', 'localtime', 'start of day')";
    if ($dateRange === '7days') {
        $dateSql = "created_at >= datetime('now', 'localtime', '-7 days')";
    } elseif ($dateRange === '30days') {
        $dateSql = "created_at >= datetime('now', 'localtime', '-30 days')";
    } elseif ($dateRange === 'all') {
        $dateSql = "1=1";
    }

    $whereCity = '';
    $params = [];
    if ($filterCity !== '') {
        $whereCity = " AND city LIKE :city";
        $params['city'] = '%' . $filterCity . '%';
    }

    $baseWhere = "WHERE {$dateSql} {$whereCity}";

    // Toplam sayfa görüntüleme
    $stmt = $db->prepare("SELECT COUNT(*) FROM visitor_logs {$baseWhere}");
    $stmt->execute($params);
    $totalViews = (int) $stmt->fetchColumn();

    // Tekil ziyaretçi (farklı IP veya session sayısı)
    $stmt = $db->prepare("SELECT COUNT(DISTINCT session_id) FROM visitor_logs {$baseWhere}");
    $stmt->execute($params);
    $uniqueVisitors = (int) $stmt->fetchColumn();

    // Ortalama kalma süresi
    $stmt = $db->prepare("SELECT AVG(duration_seconds) FROM visitor_logs {$baseWhere} AND duration_seconds > 0");
    $stmt->execute($params);
    $avgSeconds = (int) round((float) $stmt->fetchColumn());

    // Şehir dağılımı
    $stmt = $db->prepare("SELECT city, COUNT(*) as hit_count FROM visitor_logs {$baseWhere} GROUP BY city ORDER BY hit_count DESC LIMIT 8");
    $stmt->execute($params);
    $topCities = $stmt->fetchAll();

    // En çok okunan sayfalar
    $stmt = $db->prepare("SELECT page_url, page_title, COUNT(*) as hit_count, AVG(duration_seconds) as avg_duration FROM visitor_logs {$baseWhere} GROUP BY page_url ORDER BY hit_count DESC LIMIT 8");
    $stmt->execute($params);
    $topPages = $stmt->fetchAll();

    // Cihaz dağılımı
    $stmt = $db->prepare("SELECT device_type, COUNT(*) as device_count FROM visitor_logs {$baseWhere} GROUP BY device_type");
    $stmt->execute($params);
    $devices = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Son 100 ziyaretçi log kaydı
    $stmt = $db->prepare("SELECT * FROM visitor_logs {$baseWhere} ORDER BY id DESC LIMIT 100");
    $stmt->execute($params);
    $recentLogs = $stmt->fetchAll();

    return [
        'total_views' => $totalViews,
        'unique_visitors' => $uniqueVisitors,
        'avg_duration_seconds' => $avgSeconds,
        'top_cities' => $topCities,
        'top_pages' => $topPages,
        'devices' => $devices,
        'recent_logs' => $recentLogs,
    ];
}
