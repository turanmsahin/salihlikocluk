<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;
    
    $logId = (int) ($data['log_id'] ?? 0);
    $seconds = (int) ($data['seconds'] ?? 0);

    if ($logId > 0 && $seconds > 0) {
        update_visit_duration($logId, $seconds);
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false]);

