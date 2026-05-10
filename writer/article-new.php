<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/', 'writer');
$user = currentUser();

$editId = intval($_GET['id'] ?? 0);
$article = null;
$error = flash('error');
$success = flash('success');

try {
    $db = getDB();
    if ($editId) {
        $stmt = $db->prepare("SELECT * FROM articles WHERE id=? AND author_id=? AND is_deleted=0");
        $stmt->execute([$editId, $user['id']]);
        $article = $stmt->fetch();
        if (!$article) { header('Location: /writer/articles.php'); exit; }
    }
    $cities = $db->query("SELECT * FROM cities WHERE is_active=1 AND is_deleted=0 ORDER BY name_ar")->fetchAll();
    $projects = $db->query("SELECT p.*, c.name_ar as city_name FROM projects p LEFT JOIN cities c ON c.id=p.city_id WHERE p.is_deleted=0 ORDER BY p.name")->fetchAll();
} catch (Exception $e) { $cities = []; $projects = []; }
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $article ? 'تعديل مقالة' : 'مقالة جديدة' ?> - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>body{font-family:'Cairo',sans-serif}</style>
</head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-writer.php'; ?>
<div class="flex-1 p-6 overflow-auto" x-data="articleForm()">
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900"><?= $article ? 'تعديل المقالة' : 'مقالة جديدة' ?></h1>
    <p class="text-gray-500 text-sm mt-1">أنشئ محتوى عقارياً متميزاً واكسب عمولات</p>
  </div>

  <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4 text-sm"><?= h($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4 text-sm"><?= h($success) ?></div><?php endif; ?>

  <form method="POST" action="/api/article-save.php" class="space-y-6">
    <?php if ($article): ?><input type="hidden" name="id" value="<?= $article['id'] ?>"><?php endif; ?>

    <div class="grid md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">المدينة</label>
        <select name="city_id" x-model="cityId" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
          <option value="">اختر المدينة</option>
          <?php foreach ($cities as $city): ?>
            <option value="<?= $city['id'] ?>" <?= ($article['city_id'] ?? '') == $city['id'] ? 'selected' : '' ?>><?= h($city['name_ar']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">المشروع</label>
        <select name="project_id" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
          <option value="">اختر المشروع (اختياري)</option>
          <?php foreach ($projects as $proj): ?>
            <option value="<?= $proj['id'] ?>" data-city="<?= $proj['city_id'] ?>" <?= ($article['project_id'] ?? '') == $proj['id'] ? 'selected' : '' ?>>
              <?= h($proj['name']) ?> <?= $proj['city_name'] ? '(' . h($proj['city_name']) . ')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">مرجع الوحدة (اختياري)</label>
      <input type="text" name="unit_ref" value="<?= h($article['unit_ref'] ?? '') ?>" placeholder="مثل: شقة 3 غرف نوم" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">عنوان المقالة *</label>
      <input type="text" name="title" x-model="title" @input="autoSlug()" value="<?= h($article['title'] ?? '') ?>" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]" placeholder="مثل: شقق للبيع في العاصمة الإدارية بأفضل الأسعار">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">الرابط (Slug)</label>
      <input type="text" name="slug" x-model="slug" value="<?= h($article['slug'] ?? '') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518] font-mono" dir="ltr">
      <p class="text-xs text-gray-400 mt-1">يتم توليده تلقائياً من العنوان</p>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">مقتطف المقالة</label>
      <textarea name="excerpt" rows="2" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]" placeholder="وصف مختصر للمقالة (150-200 حرف)"><?= h($article['excerpt'] ?? '') ?></textarea>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">رابط صورة الغلاف</label>
      <input type="url" name="cover_image" value="<?= h($article['cover_image'] ?? '') ?>" placeholder="https://example.com/image.jpg" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]" dir="ltr">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">محتوى المقالة *</label>
      <textarea name="content" rows="15" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]" placeholder="اكتب محتوى المقالة هنا... استخدم عناوين h2 و h3 لتنظيم المحتوى"><?= h($article['content'] ?? '') ?></textarea>
    </div>

    <div class="bg-gray-50 rounded-2xl p-5">
      <h3 class="font-bold text-gray-900 mb-4">بيانات SEO</h3>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">عنوان SEO</label>
          <input type="text" name="seo_title" value="<?= h($article['seo_title'] ?? '') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]" placeholder="العنوان كما يظهر في نتائج البحث (50-60 حرف)">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">وصف SEO</label>
          <textarea name="seo_description" rows="2" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]" placeholder="وصف الصفحة لمحركات البحث (150-160 حرف)"><?= h($article['seo_description'] ?? '') ?></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">كلمات مفتاحية</label>
          <input type="text" name="seo_keywords" value="<?= h($article['seo_keywords'] ?? '') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]" placeholder="كلمة1، كلمة2، كلمة3">
        </div>
      </div>
    </div>

    <div class="flex gap-3">
      <button type="submit" name="action" value="submit" class="flex-1 bg-[#F5C518] text-black font-bold py-3 rounded-xl hover:bg-yellow-400">
        <?= $article ? 'إرسال للمراجعة' : 'إرسال للمراجعة' ?>
      </button>
      <button type="submit" name="action" value="draft" class="px-6 bg-gray-100 text-gray-700 font-medium py-3 rounded-xl hover:bg-gray-200">حفظ كمسودة</button>
    </div>
  </form>
</div>
</div>
<script>
function articleForm() {
  return {
    title: '<?= addslashes($article['title'] ?? '') ?>',
    slug: '<?= addslashes($article['slug'] ?? '') ?>',
    cityId: '<?= $article['city_id'] ?? '' ?>',
    autoSlug() {
      if (!this.slug || this.slug === this.oldSlug) {
        this.slug = this.slugify(this.title);
        this.oldSlug = this.slug;
      }
    },
    slugify(text) {
      text = text.toLowerCase();
      text = text.replace(/[\s\-]+/g, '-');
      text = text.replace(/[^؀-ۿa-z0-9\-]/g, '');
      return text.replace(/^-+|-+$/g, '') || Math.random().toString(36).substr(2, 8);
    },
    oldSlug: '<?= addslashes($article['slug'] ?? '') ?>'
  }
}
</script>
</body></html>
