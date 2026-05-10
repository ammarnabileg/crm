<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/', 'writer');
$user = currentUser();

try {
    $db = getDB();
    $complaints = $db->prepare("SELECT * FROM complaints WHERE user_id=? AND is_deleted=0 ORDER BY created_at DESC");
    $complaints->execute([$user['id']]);
    $complaints = $complaints->fetchAll();
} catch (Exception $e) { $complaints = []; }

$success = flash('success');
$error = flash('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? 'other';
    $description = trim($_POST['description'] ?? '');
    if ($title && $description) {
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO complaints (title, description, type, user_id) VALUES (?,?,?,?)");
            $stmt->execute([$title, $description, $type, $user['id']]);
            flash('success', 'تم إرسال شكواك بنجاح');
            header('Location: /writer/complaints.php'); exit;
        } catch (Exception $e) { $error = 'حدث خطأ في الإرسال'; }
    } else {
        $error = 'يرجى ملء جميع الحقول المطلوبة';
    }
}
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>الشكاوي - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-writer.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">الشكاوي والاستفسارات</h1>
    <p class="text-gray-500 text-sm mt-1">تقديم وتتبع شكاواك</p>
  </div>

  <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4 text-sm"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4 text-sm"><?= h($error) ?></div><?php endif; ?>

  <div class="grid md:grid-cols-2 gap-6">
    <!-- Submit Form -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h2 class="font-bold text-gray-900 mb-4">تقديم شكوى جديدة</h2>
      <form method="POST" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">عنوان الشكوى *</label>
          <input type="text" name="title" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">نوع الشكوى</label>
          <select name="type" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
            <option value="commission_dispute">نزاع على عمولة</option>
            <option value="lead_ownership">ملكية عميل</option>
            <option value="broker_behavior">سلوك وسيط</option>
            <option value="content_issue">مشكلة محتوى</option>
            <option value="payment_issue">مشكلة دفع</option>
            <option value="other">أخرى</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">تفاصيل الشكوى *</label>
          <textarea name="description" rows="5" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]" placeholder="اشرح مشكلتك بالتفصيل..."></textarea>
        </div>
        <button type="submit" class="w-full bg-[#F5C518] text-black font-bold py-2.5 rounded-xl hover:bg-yellow-400">إرسال الشكوى</button>
      </form>
    </div>

    <!-- Complaints List -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="p-4 border-b border-gray-50">
        <h2 class="font-bold text-gray-900">شكاواي السابقة</h2>
      </div>
      <div class="divide-y divide-gray-50">
        <?php if (empty($complaints)): ?>
          <p class="text-center text-gray-400 py-8 text-sm">لا توجد شكاوي</p>
        <?php else: foreach ($complaints as $c): ?>
          <div class="p-4">
            <div class="flex items-start justify-between gap-2">
              <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-900 text-sm"><?= h($c['title']) ?></p>
                <p class="text-xs text-gray-400 mt-0.5"><?= date('d/m/Y', strtotime($c['created_at'])) ?></p>
                <?php if ($c['resolution']): ?>
                  <p class="text-xs text-green-600 mt-1 bg-green-50 rounded-lg p-2"><?= h($c['resolution']) ?></p>
                <?php endif; ?>
              </div>
              <div class="flex flex-col items-end gap-1">
                <?= statusBadge($c['status']) ?>
                <?= statusBadge($c['priority']) ?>
              </div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>
</div>
</body></html>
