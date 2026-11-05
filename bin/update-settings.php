<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$allowedKeys = [
    'coach_name', 'coach_title', 'phone', 'whatsapp', 'email',
    'location', 'working_model', 'instagram', 'coach_bio',
    'coach_education', 'coach_approach',
];

if ($argc < 2) {
    echo "Kullanım: php bin/update-settings.php [anahtar=değer ...]\n";
    echo "Örnek: php bin/update-settings.php coach_name=\"Turan Yılmaz\" phone=\"0555 123 45 67\"\n\n";
    echo "Mevcut Ayarlar:\n";
    foreach (get_settings() as $k => $v) {
        echo "  - {$k}: {$v}\n";
    }
    exit(0);
}

$updates = [];
for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    if (str_contains($arg, '=')) {
        [$key, $value] = explode('=', $arg, 2);
        $key = trim($key);
        if (in_array($key, $allowedKeys, true)) {
            $updates[$key] = trim($value);
        } else {
            echo "Geçersiz ayar anahtarı: {$key}\n";
        }
    }
}

if ($updates) {
    update_settings($updates);
    echo "Ayarlar başarıyla güncellendi:\n";
    foreach ($updates as $k => $v) {
        echo "  ✓ {$k} => {$v}\n";
    }
} else {
    echo "Güncellenecek geçerli bir ayar girilmedi.\n";
}

