<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin','account_manager');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/projects.php'); exit; }

$redirect = $_POST['redirect'] ?? '/admin/projects.php';
$id = intval($_POST['id'] ?? 0);

// Soft delete
if (isset($_POST['delete']) && $id) {
    try {
        $db = getDB();
        $db->prepare("UPDATE projects SET is_deleted=1 WHERE id=?")->execute([$id]);
        auditLog('Project', $id, 'delete');
        flash('success', 'تم حذف المشروع');
    } catch (Exception $e) { flash('error', 'حدث خطأ'); }
    header('Location: ' . $redirect); exit;
}

// Toggle publish
if (isset($_POST['toggle_publish']) && $id) {
    try {
        $db = getDB();
        $db->prepare("UPDATE projects SET is_published = 1 - is_published WHERE id=?")->execute([$id]);
        auditLog('Project', $id, 'toggle_publish');
        flash('success', 'تم تحديث حالة النشر');
    } catch (Exception $e) { flash('error', 'حدث خطأ'); }
    header('Location: ' . $redirect); exit;
}

$name = trim($_POST['name'] ?? '');
$nameAr = trim($_POST['name_ar'] ?? '');
$slug = trim($_POST['slug'] ?? '');
$developerId = intval($_POST['developer_id'] ?? 0) ?: null;
$cityId = intval($_POST['city_id'] ?? 0) ?: null;
$location = trim($_POST['location'] ?? '');
$salesPhone = trim($_POST['sales_phone'] ?? '');
$minPrice = floatval($_POST['min_price'] ?? 0) ?: null;
$minDown = floatval($_POST['min_down_payment'] ?? 0) ?: null;
$minInstall = floatval($_POST['min_installment'] ?? 0) ?: null;
$minArea = floatval($_POST['min_area'] ?? 0) ?: null;
$description = trim($_POST['description'] ?? '');
$seoTitle = trim($_POST['seo_title'] ?? '');
$seoDesc = trim($_POST['seo_description'] ?? '');
$seoKeys = trim($_POST['seo_keywords'] ?? '');
$isPublished = isset($_POST['is_published']) ? 1 : 0;
$imagesRaw = trim($_POST['images'] ?? '');

if (!$name) { flash('error', 'اسم المشروع مطلوب'); header('Location: ' . $redirect); exit; }

try {
    $db = getDB();

    if (!$slug) $slug = slugify($name);

    // Ensure unique slug
    $slugCheck = $db->prepare("SELECT id FROM projects WHERE slug=? AND id!=?");
    $slugCheck->execute([$slug, $id ?: 0]);
    if ($slugCheck->fetch()) { $slug = $slug . '-' . time(); }

    if ($id) {
        $stmt = $db->prepare("UPDATE projects SET name=?, name_ar=?, slug=?, developer_id=?, city_id=?, location=?, sales_phone=?, min_price=?, min_down_payment=?, min_installment=?, min_area=?, description=?, seo_title=?, seo_description=?, seo_keywords=?, is_published=? WHERE id=?");
        $stmt->execute([$name, $nameAr, $slug, $developerId, $cityId, $location, $salesPhone, $minPrice, $minDown, $minInstall, $minArea, $description, $seoTitle, $seoDesc, $seoKeys, $isPublished, $id]);
        // Clear and re-insert images
        $db->prepare("DELETE FROM project_images WHERE project_id=?")->execute([$id]);
    } else {
        $stmt = $db->prepare("INSERT INTO projects (name, name_ar, slug, developer_id, city_id, location, sales_phone, min_price, min_down_payment, min_installment, min_area, description, seo_title, seo_description, seo_keywords, is_published) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$name, $nameAr, $slug, $developerId, $cityId, $location, $salesPhone, $minPrice, $minDown, $minInstall, $minArea, $description, $seoTitle, $seoDesc, $seoKeys, $isPublished]);
        $id = $db->lastInsertId();
    }

    // Handle images
    if ($imagesRaw) {
        $images = array_map('trim', explode(',', $imagesRaw));
        $imgStmt = $db->prepare("INSERT INTO project_images (project_id, image_url, sort_order) VALUES (?,?,?)");
        foreach ($images as $i => $url) {
            if ($url) $imgStmt->execute([$id, $url, $i]);
        }
    }

    auditLog('Project', $id, 'saved');
    flash('success', 'تم حفظ المشروع بنجاح');
} catch (Exception $e) {
    flash('error', 'حدث خطأ: ' . $e->getMessage());
}

header('Location: ' . $redirect);
exit;
