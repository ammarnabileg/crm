<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/settings.php'); exit; }

$action = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? '/admin/settings.php';

try {
    $db = getDB();

    if ($action === 'settings') {
        $settings = [
            'site_name' => trim($_POST['site_name'] ?? ''),
            'sales_phone' => trim($_POST['sales_phone'] ?? ''),
            'whatsapp_number' => trim($_POST['whatsapp_number'] ?? ''),
            'commission_rate' => trim($_POST['commission_rate'] ?? '0.20'),
        ];
        foreach ($settings as $key => $value) {
            $db->prepare("INSERT INTO settings (`key`, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?")->execute([$key, $value, $value]);
        }
        flash('success', 'تم حفظ الإعدادات');

    } elseif ($action === 'save') {
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        $faqId = intval($_POST['id'] ?? 0);

        if (!$question || !$answer) { flash('error', 'السؤال والإجابة مطلوبان'); header('Location: ' . $redirect); exit; }

        if ($faqId) {
            $db->prepare("UPDATE global_faqs SET question=?, answer=?, sort_order=? WHERE id=?")->execute([$question, $answer, $sortOrder, $faqId]);
        } else {
            $db->prepare("INSERT INTO global_faqs (question, answer, sort_order) VALUES (?,?,?)")->execute([$question, $answer, $sortOrder]);
        }
        flash('success', 'تم حفظ السؤال');

    } elseif ($action === 'delete') {
        $faqId = intval($_POST['id'] ?? 0);
        if ($faqId) {
            $db->prepare("UPDATE global_faqs SET is_active=0 WHERE id=?")->execute([$faqId]);
            flash('success', 'تم حذف السؤال');
        }
    }
} catch (Exception $e) {
    flash('error', 'حدث خطأ: ' . $e->getMessage());
}

header('Location: ' . $redirect);
exit;
