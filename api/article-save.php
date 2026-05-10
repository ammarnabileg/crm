<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/', 'writer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /writer/article-new.php'); exit; }

$user = currentUser();
$id = intval($_POST['id'] ?? 0);
$action = $_POST['action'] ?? 'submit';
$status = $action === 'draft' ? 'draft' : 'pending';

$title = trim($_POST['title'] ?? '');
$slug = trim($_POST['slug'] ?? '');
$content = trim($_POST['content'] ?? '');
$excerpt = trim($_POST['excerpt'] ?? '');
$coverImage = trim($_POST['cover_image'] ?? '');
$cityId = intval($_POST['city_id'] ?? 0) ?: null;
$projectId = intval($_POST['project_id'] ?? 0) ?: null;
$unitRef = trim($_POST['unit_ref'] ?? '');
$seoTitle = trim($_POST['seo_title'] ?? '');
$seoDesc = trim($_POST['seo_description'] ?? '');
$seoKeys = trim($_POST['seo_keywords'] ?? '');

if (!$title || !$content) {
    flash('error', 'العنوان والمحتوى مطلوبان');
    header('Location: /writer/article-new.php' . ($id ? "?id=$id" : ''));
    exit;
}

try {
    $db = getDB();

    if (!$slug) $slug = slugify($title);

    // Ensure unique slug
    $slugCheck = $db->prepare("SELECT id FROM articles WHERE slug=? AND id!=?");
    $slugCheck->execute([$slug, $id ?: 0]);
    if ($slugCheck->fetch()) $slug = $slug . '-' . time();

    if ($id) {
        // Check ownership
        $own = $db->prepare("SELECT id, status FROM articles WHERE id=? AND author_id=? AND is_deleted=0");
        $own->execute([$id, $user['id']]);
        $existing = $own->fetch();
        if (!$existing) { flash('error', 'غير مخول'); header('Location: /writer/articles.php'); exit; }

        $db->prepare("UPDATE articles SET title=?, slug=?, content=?, excerpt=?, cover_image=?, city_id=?, project_id=?, unit_ref=?, seo_title=?, seo_description=?, seo_keywords=?, status=? WHERE id=?")->execute([$title, $slug, $content, $excerpt, $coverImage, $cityId, $projectId, $unitRef, $seoTitle, $seoDesc, $seoKeys, $status, $id]);
        auditLog('Article', $id, 'updated');
    } else {
        $stmt = $db->prepare("INSERT INTO articles (title, slug, content, excerpt, cover_image, author_id, city_id, project_id, unit_ref, seo_title, seo_description, seo_keywords, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$title, $slug, $content, $excerpt, $coverImage, $user['id'], $cityId, $projectId, $unitRef, $seoTitle, $seoDesc, $seoKeys, $status]);
        $id = $db->lastInsertId();
        auditLog('Article', $id, 'created');
    }

    $msg = $status === 'draft' ? 'تم حفظ المقال كمسودة' : 'تم إرسال المقال للمراجعة بنجاح';
    flash('success', $msg);
} catch (Exception $e) {
    flash('error', 'حدث خطأ: ' . $e->getMessage());
}

header('Location: /writer/articles.php');
exit;
