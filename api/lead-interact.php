<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /'); exit; }

$leadId = intval($_POST['lead_id'] ?? 0);
$type = trim($_POST['type'] ?? 'note');
$content = trim($_POST['content'] ?? '');
$redirect = $_POST['redirect'] ?? '/';

if (!$leadId || !$content) { flash('error', 'البيانات مطلوبة'); header('Location: ' . $redirect); exit; }

try {
    $db = getDB();
    $db->prepare("INSERT INTO lead_interactions (lead_id, type, content, created_by) VALUES (?,?,?,?)")->execute([$leadId, $type, $content, $_SESSION['user_id']]);
    auditLog('LeadInteraction', $leadId, 'note_added');
} catch (Exception $e) {
    flash('error', 'حدث خطأ');
}

header('Location: ' . $redirect);
exit;
