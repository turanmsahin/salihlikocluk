<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');
$pages = ['/', '/yks', '/tyt', '/ayt', '/lgs', '/mezun', '/yazilim', '/veli', '/surec', '/hakkimda', '/blog', '/gizlilik', '/iletisim'];
$customPages = $db->query("SELECT slug FROM site_pages WHERE slug NOT IN ('yks', 'tyt', 'ayt', 'lgs', 'mezun', 'yazilim', 'veli', 'surec', 'hakkimda', 'gizlilik', 'iletisim')")->fetchAll(PDO::FETCH_COLUMN);
foreach ($customPages as $cpSlug) {
    if (!empty($cpSlug)) {
        $pages[] = '/' . ltrim($cpSlug, '/');
    }
}
$posts = $db->query("SELECT slug, updated_at FROM posts WHERE status = 'published' ORDER BY updated_at DESC")->fetchAll();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($pages as $page): ?><url><loc><?= e('https://www.salihlikocluk.com.tr' . $page) ?></loc></url><?php endforeach; ?>
<?php foreach ($posts as $post): ?><url><loc><?= e('https://www.salihlikocluk.com.tr/blog/' . rawurlencode($post['slug'])) ?></loc><lastmod><?= e(date('Y-m-d', strtotime($post['updated_at']))) ?></lastmod></url><?php endforeach; ?>
</urlset>
