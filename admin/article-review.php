<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin','account_manager');

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin/articles.php'); exit; }

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT a.*, u.name as author_name, u.email as author_email, c.name_ar as city_name, p.name as project_name FROM articles a LEFT JOIN users u ON u.id=a.author_id LEFT JOIN cities c ON c.id=a.city_id LEFT JOIN projects p ON p.id=a.project_id WHERE a.id=? AND a.is_deleted=0");
    $stmt->execute([$id]);
    $article = $stmt->fetch();
    if (!$article) { header('Location: /admin/articles.php'); exit; }
    $faqs = $db->prepare("SELECT * FROM article_faqs WHERE article_id=? ORDER BY sort_order");
    $faqs->execute([$id]);
    $faqs = $faqs->fetchAll();
} catch (Exception $e) { header('Location: /admin/articles.php'); exit; }

$success = flash('success');
$error = flash('error');
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>مراجعة مقالة - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <div class="flex items-center gap-4 mb-6">
    <a href="/admin/articles.php" class="text-gray-400 hover:text-gray-600">← رجوع</a>
    <h1 class="text-2xl font-bold text-gray-900">مراجعة مقالة</h1>
  </div>

  <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4 text-sm"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4 text-sm"><?= h($error) ?></div><?php endif; ?>

  <div class="grid lg:grid-cols-3 gap-6">
    <!-- Article Content -->
    <div class="lg:col-span-2 space-y-6">
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <?php if ($article['cover_image']): ?>
          <img src="<?= h($article['cover_image']) ?>" alt="" class="w-full h-48 object-cover rounded-xl mb-4">
        <?php endif; ?>
        <div class="flex items-center gap-2 mb-3">
          <?= statusBadge($article['status']) ?>
          <?php if ($article['city_name']): ?>
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full"><?= h($article['city_name']) ?></span>
          <?php endif; ?>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2"><?= h($article['title']) ?></h2>
        <div class="text-sm text-gray-400 mb-4">
          الكاتب: <?= h($article['author_name']) ?> (<?= h($article['author_email']) ?>) •
          <?= date('d/m/Y H:i', strtotime($article['created_at'])) ?>
        </div>
        <?php if ($article['excerpt']): ?>
          <div class="bg-gray-50 rounded-xl p-3 text-sm text-gray-600 mb-4 italic"><?= h($article['excerpt']) ?></div>
        <?php endif; ?>
        <div class="prose text-gray-700 text-sm leading-relaxed whitespace-pre-wrap"><?= h($article['content']) ?></div>
      </div>

      <!-- SEO Info -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-bold text-gray-900 mb-3">بيانات SEO</h3>
        <div class="space-y-2 text-sm">
          <div><span class="text-gray-500">عنوان SEO:</span> <?= h($article['seo_title'] ?: '-') ?></div>
          <div><span class="text-gray-500">وصف SEO:</span> <?= h($article['seo_description'] ?: '-') ?></div>
          <div><span class="text-gray-500">الكلمات المفتاحية:</span> <?= h($article['seo_keywords'] ?: '-') ?></div>
        </div>
      </div>
    </div>

    <!-- Action Panel -->
    <div class="space-y-4" x-data="{ action: '' }">
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-bold text-gray-900 mb-4">إجراء المراجعة</h3>
        <div class="space-y-2">
          <button @click="action='approve'" :class="action==='approve' ? 'bg-green-500 text-white' : 'bg-green-50 text-green-700'" class="w-full py-2.5 rounded-xl text-sm font-medium hover:bg-green-500 hover:text-white transition-colors">قبول المقالة ✓</button>
          <button @click="action='needs_edit'" :class="action==='needs_edit' ? 'bg-orange-500 text-white' : 'bg-orange-50 text-orange-700'" class="w-full py-2.5 rounded-xl text-sm font-medium hover:bg-orange-500 hover:text-white transition-colors">يحتاج تعديل ✎</button>
          <button @click="action='reject'" :class="action==='reject' ? 'bg-red-500 text-white' : 'bg-red-50 text-red-700'" class="w-full py-2.5 rounded-xl text-sm font-medium hover:bg-red-500 hover:text-white transition-colors">رفض ✗</button>
        </div>

        <form x-show="action" method="POST" action="/api/article-status.php" class="mt-4 space-y-3">
          <input type="hidden" name="article_id" value="<?= $article['id'] ?>">
          <input type="hidden" name="action" :value="action">
          <div x-show="action === 'reject' || action === 'needs_edit'">
            <label class="block text-sm font-medium text-gray-700 mb-1">سبب القرار</label>
            <textarea name="reason" rows="3" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]" placeholder="اشرح سبب القرار..."></textarea>
          </div>
          <button type="submit" class="w-full bg-[#F5C518] text-black font-bold py-2.5 rounded-xl hover:bg-yellow-400">تأكيد الإجراء</button>
        </form>
      </div>

      <!-- Stats -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-bold text-gray-900 mb-3">إحصائيات</h3>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between"><span class="text-gray-500">المشاهدات</span><span class="font-medium"><?= number_format($article['view_count']) ?></span></div>
          <div class="flex justify-between"><span class="text-gray-500">العملاء</span><span class="font-medium"><?= $article['lead_count'] ?></span></div>
          <?php if ($article['rejection_reason']): ?>
            <div class="bg-red-50 rounded-lg p-2 text-xs text-red-700">سبب رفض سابق: <?= h($article['rejection_reason']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
</body></html>
