<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$username = getenv('ADMIN_USERNAME') ?: '';
$password = getenv('ADMIN_PASSWORD') ?: '';

if ($username === '' || mb_strlen($username) > 80 || mb_strlen($password) < 12) {
    fwrite(STDERR, "ADMIN_USERNAME ve en az 12 karakterli ADMIN_PASSWORD ortam değişkenlerini tanımlayın.\n");
    exit(1);
}

$statement = $db->prepare('INSERT INTO admins (username, password_hash) VALUES (:username, :password)');
try {
    $statement->execute([
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
    ]);
} catch (PDOException $exception) {
    if (str_contains($exception->getMessage(), 'UNIQUE')) {
        fwrite(STDERR, "Bu kullanıcı adı zaten kayıtlı.\n");
        exit(1);
    }
    throw $exception;
}

fwrite(STDOUT, "Yönetici hesabı oluşturuldu: {$username}\n");
