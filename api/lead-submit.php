<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$interestedArea = trim($_POST['interested_area'] ?? '');
$articleId = intval($_POST['article_id'] ?? 0) ?: null;
$projectId = intval($_POST['project_id'] ?? 0) ?: null;
$source = $_POST['source'] ?? 'form';

$validSources = ['form','whatsapp','phone','homepage','project_page','article'];
if (!in_array($source, $validSources)) $source = 'form';

if (!$name || !$phone) {
    echo json_encode(['success' => false, 'message' => 'الاسم ورقم الهاتف مطلوبان']);
    exit;
}

try {
    $db = getDB();

    // Check duplicate
    $dup = $db->prepare("SELECT id FROM leads WHERE phone=? AND is_deleted=0 LIMIT 1");
    $dup->execute([$phone]);
    $isDuplicate = (bool)$dup->fetch();

    // Get writer from article
    $writerId = null;
    $cityId = null;
    if ($articleId) {
        $art = $db->prepare("SELECT author_id, city_id, project_id FROM articles WHERE id=?");
        $art->execute([$articleId]);
        $artRow = $art->fetch();
        if ($artRow) {
            $writerId = $artRow['author_id'];
            $cityId = $artRow['city_id'];
            if (!$projectId) $projectId = $artRow['project_id'];
        }
    }

    $utmSource = $_POST['utm_source'] ?? $_GET['utm_source'] ?? $_COOKIE['utm_source'] ?? null;
    $utmMedium = $_POST['utm_medium'] ?? $_GET['utm_medium'] ?? $_COOKIE['utm_medium'] ?? null;
    $utmCampaign = $_POST['utm_campaign'] ?? $_GET['utm_campaign'] ?? $_COOKIE['utm_campaign'] ?? null;
    $referrer = $_SERVER['HTTP_REFERER'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = $db->prepare("INSERT INTO leads (name, phone, email, interested_area, source, article_id, writer_id, city_id, project_id, utm_source, utm_medium, utm_campaign, referrer, ip_address, device_info, is_duplicate) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$name, $phone, $email, $interestedArea, $source, $articleId, $writerId, $cityId, $projectId, $utmSource, $utmMedium, $utmCampaign, $referrer, $ip, $deviceInfo, $isDuplicate ? 1 : 0]);
    $leadId = $db->lastInsertId();

    if ($articleId) {
        $db->prepare("UPDATE articles SET lead_count=lead_count+1 WHERE id=?")->execute([$articleId]);
    }

    auditLog('Lead', $leadId, 'created');
    echo json_encode(['success' => true, 'message' => 'تم إرسال بياناتك بنجاح، سيتواصل معك فريقنا قريباً', 'lead_id' => $leadId]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'حدث خطأ، حاول مرة أخرى']);
}
