<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin','account_manager');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/articles.php'); exit; }

$articleId = intval($_POST['article_id'] ?? 0);
$action = $_POST['action'] ?? '';
$reason = trim($_POST['reason'] ?? '');
$redirect = $_POST['redirect'] ?? '/admin/articles.php';

if (!$articleId) { flash('error', 'معرف المقال مطلوب'); header('Location: ' . $redirect); exit; }

$validActions = ['approve','reject','needs_edit','delete'];
if (!in_array($action, $validActions)) { flash('error', 'إجراء غير صالح'); header('Location: ' . $redirect); exit; }

try {
    $db = getDB();
    if ($action === 'delete') {
        $db->prepare("UPDATE articles SET is_deleted=1 WHERE id=?")->execute([$articleId]);
        auditLog('Article', $articleId, 'delete');
        flash('success', 'تم حذف المقال');
    } else {
        $statusMap = ['approve' => 'approved', 'reject' => 'rejected', 'needs_edit' => 'needs_edit'];
        $newStatus = $statusMap[$action];
        $publishedAt = $newStatus === 'approved' ? date('Y-m-d H:i:s') : null;

        $stmt = $db->prepare("UPDATE articles SET status=?, rejection_reason=?, published_at=? WHERE id=?");
        $stmt->execute([$newStatus, $reason ?: null, $publishedAt, $articleId]);
        auditLog('Article', $articleId, 'status_change', ['status' => $newStatus, 'reason' => $reason]);
        flash('success', 'تم تحديث حالة المقال');
    }
} catch (Exception $e) {
    flash('error', 'حدث خطأ أثناء التحديث');
}

header('Location: ' . $redirect);
exit;
