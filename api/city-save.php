<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin','account_manager');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/cities.php'); exit; }

$redirect = $_POST['redirect'] ?? '/admin/cities.php';
$id = intval($_POST['id'] ?? 0);

if (isset($_POST['delete']) && $id) {
    try {
        $db = getDB();
        $db->prepare("UPDATE cities SET is_deleted=1 WHERE id=?")->execute([$id]);
        auditLog('City', $id, 'delete');
        flash('success', 'تم حذف المدينة');
    } catch (Exception $e) { flash('error', 'حدث خطأ'); }
    header('Location: ' . $redirect); exit;
}

$name = trim($_POST['name'] ?? '');
$nameAr = trim($_POST['name_ar'] ?? '');
$countryId = intval($_POST['country_id'] ?? 1);
$image = trim($_POST['image'] ?? '');

if (!$name || !$nameAr) { flash('error', 'الاسم مطلوب'); header('Location: ' . $redirect); exit; }

try {
    $db = getDB();
    if ($id) {
        $db->prepare("UPDATE cities SET name=?, name_ar=?, country_id=?, image=? WHERE id=?")->execute([$name, $nameAr, $countryId, $image, $id]);
    } else {
        $db->prepare("INSERT INTO cities (name, name_ar, country_id, image) VALUES (?,?,?,?)")->execute([$name, $nameAr, $countryId, $image]);
        $id = $db->lastInsertId();
    }
    auditLog('City', $id, 'saved');
    flash('success', 'تم حفظ المدينة');
} catch (Exception $e) { flash('error', 'حدث خطأ'); }

header('Location: ' . $redirect);
exit;
