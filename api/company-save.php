<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin','account_manager');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/companies.php'); exit; }

$redirect = $_POST['redirect'] ?? '/admin/companies.php';
$type = $_POST['type'] ?? 'broker';
$id = intval($_POST['id'] ?? 0);

if (isset($_POST['delete']) && $id) {
    try {
        $db = getDB();
        $table = $type === 'developer' ? 'developer_companies' : 'broker_companies';
        $db->prepare("UPDATE $table SET is_deleted=1 WHERE id=?")->execute([$id]);
        auditLog(ucfirst($type) . 'Company', $id, 'delete');
        flash('success', 'تم الحذف');
    } catch (Exception $e) { flash('error', 'حدث خطأ'); }
    header('Location: ' . $redirect); exit;
}

$name = trim($_POST['name'] ?? '');
if (!$name) { flash('error', 'الاسم مطلوب'); header('Location: ' . $redirect); exit; }

try {
    $db = getDB();
    if ($type === 'developer') {
        $nameAr = trim($_POST['name_ar'] ?? '');
        $logo = trim($_POST['logo'] ?? '');
        if ($id) {
            $db->prepare("UPDATE developer_companies SET name=?, name_ar=?, logo=? WHERE id=?")->execute([$name, $nameAr, $logo, $id]);
        } else {
            $db->prepare("INSERT INTO developer_companies (name, name_ar, logo) VALUES (?,?,?)")->execute([$name, $nameAr, $logo]);
            $id = $db->lastInsertId();
        }
    } else {
        $nameAr = trim($_POST['name_ar'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $commRate = floatval($_POST['commission_rate'] ?? 0);
        $managerId = intval($_POST['account_manager_id'] ?? 0) ?: null;

        if (!$phone) { flash('error', 'رقم الهاتف مطلوب'); header('Location: ' . $redirect); exit; }

        if ($id) {
            $db->prepare("UPDATE broker_companies SET name=?, name_ar=?, phone=?, email=?, commission_rate=?, account_manager_id=? WHERE id=?")->execute([$name, $nameAr, $phone, $email, $commRate, $managerId, $id]);
        } else {
            $db->prepare("INSERT INTO broker_companies (name, name_ar, phone, email, commission_rate, account_manager_id) VALUES (?,?,?,?,?,?)")->execute([$name, $nameAr, $phone, $email, $commRate, $managerId]);
            $id = $db->lastInsertId();
        }
    }
    auditLog(ucfirst($type) . 'Company', $id, 'saved');
    flash('success', 'تم الحفظ');
} catch (Exception $e) { flash('error', 'حدث خطأ: ' . $e->getMessage()); }

header('Location: ' . $redirect);
exit;
