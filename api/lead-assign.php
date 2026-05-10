<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin','account_manager');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/leads.php'); exit; }

$leadId = intval($_POST['lead_id'] ?? 0);
$brokerCompanyId = intval($_POST['broker_company_id'] ?? 0);
$redirect = $_POST['redirect'] ?? '/admin/leads.php';

if (!$leadId || !$brokerCompanyId) { flash('error', 'بيانات ناقصة'); header('Location: ' . $redirect); exit; }

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT name FROM broker_companies WHERE id=? AND is_deleted=0");
    $stmt->execute([$brokerCompanyId]);
    $company = $stmt->fetch();
    if (!$company) { flash('error', 'الشركة غير موجودة'); header('Location: ' . $redirect); exit; }

    $db->prepare("UPDATE leads SET broker_company_id=?, status='assigned', pipeline_stage='new_lead' WHERE id=?")->execute([$brokerCompanyId, $leadId]);

    $db->prepare("INSERT INTO lead_interactions (lead_id, type, content, created_by) VALUES (?, 'assignment', ?, ?)")->execute([$leadId, 'تم الإحالة لشركة ' . $company['name'], $_SESSION['user_id']]);

    auditLog('Lead', $leadId, 'assigned', ['broker_company_id' => $brokerCompanyId]);
    flash('success', 'تم إحالة العميل بنجاح');
} catch (Exception $e) {
    flash('error', 'حدث خطأ أثناء الإحالة');
}

header('Location: ' . $redirect);
exit;
