<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin','account_manager');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/commissions.php'); exit; }

$commissionId = intval($_POST['commission_id'] ?? 0);
$dealId = intval($_POST['deal_id'] ?? 0);
$action = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? '/admin/commissions.php';

$validActions = ['approve','payable','reject','paid'];
if (!in_array($action, $validActions)) { flash('error', 'إجراء غير صالح'); header('Location: ' . $redirect); exit; }

try {
    $db = getDB();

    if ($dealId && !$commissionId) {
        // Action on deal
        if ($action === 'approve') {
            $db->prepare("UPDATE deals SET status='approved', verified_by=?, verified_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'], $dealId]);
            // Also approve associated commission
            $comm = $db->prepare("SELECT id FROM commissions WHERE deal_id=?");
            $comm->execute([$dealId]);
            $commRow = $comm->fetch();
            if ($commRow) {
                $db->prepare("UPDATE commissions SET status='approved' WHERE id=?")->execute([$commRow['id']]);
            }
        } elseif ($action === 'reject') {
            $db->prepare("UPDATE deals SET status='rejected', verified_by=?, verified_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'], $dealId]);
            $comm = $db->prepare("SELECT id FROM commissions WHERE deal_id=?");
            $comm->execute([$dealId]);
            $commRow = $comm->fetch();
            if ($commRow) {
                $db->prepare("UPDATE commissions SET status='rejected' WHERE id=?")->execute([$commRow['id']]);
            }
        }
        auditLog('Deal', $dealId, 'status_' . $action);
    } else {
        $statusMap = ['approve' => 'approved', 'payable' => 'payable', 'reject' => 'rejected', 'paid' => 'paid'];
        $newStatus = $statusMap[$action];
        $db->prepare("UPDATE commissions SET status=? WHERE id=?")->execute([$newStatus, $commissionId]);
        auditLog('Commission', $commissionId, 'status_' . $action);
    }

    flash('success', 'تم تحديث الحالة');
} catch (Exception $e) {
    flash('error', 'حدث خطأ');
}

header('Location: ' . $redirect);
exit;
