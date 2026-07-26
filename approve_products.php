<?php
declare(strict_types=1);

/**
 * approve_products.php — Approves or rejects a pending product by setting
 * its approval_status column. Called by admin.html's "Pending Products"
 * panel. Key-gated (?key=khurmi2026), same pattern as this project's other
 * admin-triggered scripts (auto_source.php, sync_products.php, etc.) —
 * uses the Supabase service key so it works regardless of RLS policy.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if (($_GET['key'] ?? '') !== 'khurmi2026') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/config.php';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$id     = isset($data['id']) ? (int)$data['id'] : 0;
$action = trim((string)($data['action'] ?? ''));

if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'id and action (approve|reject) required']);
    exit;
}

$newStatus = $action === 'approve' ? 'approved' : 'rejected';

$ch = curl_init(rtrim(SUPABASE_URL, '/') . '/rest/v1/products?id=eq.' . $id);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'PATCH',
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal',
    ],
    CURLOPT_POSTFIELDS => json_encode(['approval_status' => $newStatus], JSON_UNESCAPED_UNICODE),
]);
curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err    = curl_error($ch);
curl_close($ch);

if ($status < 200 || $status >= 300) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Supabase PATCH failed', 'httpCode' => $status, 'curlErr' => $err]);
    exit;
}

echo json_encode(['success' => true, 'id' => $id, 'approval_status' => $newStatus]);
