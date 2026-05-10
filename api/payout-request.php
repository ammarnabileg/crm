<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/', 'writer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /writer/wallet.php'); exit; }

$amount = floatval($_POST['amount'] ?? 0);
$bankDetails = trim($_POST['bank_details'] ?? '');

if (!$amount || !$bankDetails) { flash('payout_error', 'يرجى تعبئة جميع الحقول'); header('Location: /writer/wallet.php'); exit; }

$user = currentUser();
try {
    $db = getDB();
    // Get a payable commission for this writer
    $comm = $db->prepare("SELECT id, amount FROM commissions WHERE writer_id=? AND status='payable' AND is_deleted=0 ORDER BY created_at LIMIT 1");
    $comm->execute([$user['id']]);
    $commRow = $comm->fetch();
    if (!$commRow) { flash('payout_error', 'لا توجد عمولات جاهزة للسحب'); header('Location: /writer/wallet.php'); exit; }

    // Check no pending payout for this commission
    $existingPayout = $db->prepare("SELECT id FROM payouts WHERE commission_id=? AND status NOT IN ('rejected')");
    $existingPayout->execute([$commRow['id']]);
    if ($existingPayout->fetch()) { flash('payout_error', 'يوجد طلب سحب معلق بالفعل'); header('Location: /writer/wallet.php'); exit; }

    $db->prepare("INSERT INTO payouts (writer_id, commission_id, amount, bank_details) VALUES (?,?,?,?)")->execute([$user['id'], $commRow['id'], $amount, $bankDetails]);
    auditLog('Payout', $db->lastInsertId(), 'requested');
    flash('payout_success', 'تم إرسال طلب السحب بنجاح');
} catch (Exception $e) {
    flash('payout_error', 'حدث خطأ');
}

header('Location: /writer/wallet.php');
exit;
