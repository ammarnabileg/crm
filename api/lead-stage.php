<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /'); exit; }

$leadId = intval($_POST['lead_id'] ?? 0);
$stage = $_POST['stage'] ?? '';
$score = $_POST['score'] ?? null;
$redirect = $_POST['redirect'] ?? '/';

$validStages = ['new_lead','attempted_contact','contacted','interested','viewing_scheduled','viewing_completed','negotiation','reservation','closed_won','closed_lost'];
$validScores = ['cold','warm','hot','high_intent'];

if (!$leadId || !in_array($stage, $validStages)) { flash('error', 'بيانات غير صالحة'); header('Location: ' . $redirect); exit; }

try {
    $db = getDB();
    // Check lead is accessible to the current user
    $user = currentUser();
    $stmt = $db->prepare("SELECT id FROM leads WHERE id=? AND is_deleted=0");
    $stmt->execute([$leadId]);
    if (!$stmt->fetch()) { flash('error', 'العميل غير موجود'); header('Location: ' . $redirect); exit; }

    $scoreSQL = ($score && in_array($score, $validScores)) ? ", score='$score'" : '';
    $db->prepare("UPDATE leads SET pipeline_stage=?, status=CASE WHEN ? IN ('closed_won','closed_lost') THEN ? ELSE status END$scoreSQL WHERE id=?")->execute([$stage, $stage, $stage === 'closed_won' ? 'closed_won' : ($stage === 'closed_lost' ? 'closed_lost' : 'in_progress'), $leadId]);

    $db->prepare("INSERT INTO lead_interactions (lead_id, type, content, created_by) VALUES (?, 'stage_change', ?, ?)")->execute([$leadId, 'تم تحديث المرحلة إلى: ' . $stage, $_SESSION['user_id']]);

    auditLog('Lead', $leadId, 'stage_update', ['stage' => $stage, 'score' => $score]);
    flash('success', 'تم تحديث المرحلة');
} catch (Exception $e) {
    flash('error', 'حدث خطأ');
}

header('Location: ' . $redirect);
exit;
