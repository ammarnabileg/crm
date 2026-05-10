<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/', 'broker');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /broker/leads.php'); exit; }

$leadId = intval($_POST['lead_id'] ?? 0);
$saleAmount = floatval($_POST['sale_amount'] ?? 0);
$netProfit = floatval($_POST['net_profit'] ?? 0);
$proofFiles = trim($_POST['proof_files'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$redirect = $_POST['redirect'] ?? '/broker/leads.php';

if (!$leadId || !$saleAmount || !$netProfit) { flash('error', 'جميع الحقول المطلوبة يجب تعبئتها'); header('Location: ' . $redirect); exit; }

try {
    $db = getDB();
    // Get lead info
    $stmt = $db->prepare("SELECT l.*, bc.id as bc_id FROM leads l LEFT JOIN broker_company_staff bcs ON bcs.company_id=l.broker_company_id WHERE l.id=? AND l.is_deleted=0 AND bcs.user_id=?");
    $stmt->execute([$leadId, $_SESSION['user_id']]);
    $lead = $stmt->fetch();
    if (!$lead) { flash('error', 'غير مخول للوصول لهذا العميل'); header('Location: ' . $redirect); exit; }

    // Check no existing deal
    $existing = $db->prepare("SELECT id FROM deals WHERE lead_id=? AND is_deleted=0");
    $existing->execute([$leadId]);
    if ($existing->fetch()) { flash('error', 'تم تسجيل صفقة لهذا العميل مسبقاً'); header('Location: ' . $redirect); exit; }

    // Create deal
    $dealStmt = $db->prepare("INSERT INTO deals (lead_id, broker_company_id, sale_amount, net_profit, proof_files, admin_notes) VALUES (?,?,?,?,?,?)");
    $dealStmt->execute([$leadId, $lead['broker_company_id'], $saleAmount, $netProfit, $proofFiles, $notes]);
    $dealId = $db->lastInsertId();

    // Create commission if writer attributed
    if ($lead['writer_id']) {
        $commRate = (float)getSetting('commission_rate', '0.20');
        $commAmount = $netProfit * $commRate;
        $db->prepare("INSERT INTO commissions (lead_id, deal_id, writer_id, amount, rate) VALUES (?,?,?,?,?)")->execute([$leadId, $dealId, $lead['writer_id'], $commAmount, $commRate]);
    }

    // Update lead status
    $db->prepare("UPDATE leads SET status='closed_won', pipeline_stage='closed_won' WHERE id=?")->execute([$leadId]);

    // Add interaction
    $db->prepare("INSERT INTO lead_interactions (lead_id, type, content, created_by) VALUES (?, 'deal', ?, ?)")->execute([$leadId, 'تم تسجيل صفقة بقيمة ' . number_format($saleAmount) . ' ج.م', $_SESSION['user_id']]);

    auditLog('Deal', $dealId, 'created', ['sale_amount' => $saleAmount]);
    flash('success', 'تم تسجيل الصفقة بنجاح وسيتم مراجعتها');
} catch (Exception $e) {
    flash('error', 'حدث خطأ: ' . $e->getMessage());
}

header('Location: ' . $redirect);
exit;
