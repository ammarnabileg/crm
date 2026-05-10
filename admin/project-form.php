<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin','account_manager');

$id = intval($_GET['id'] ?? 0);
$project = null;
try {
    $db = getDB();
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM projects WHERE id=? AND is_deleted=0");
        $stmt->execute([$id]);
        $project = $stmt->fetch();
    }
    $cities = $db->query("SELECT * FROM cities WHERE is_active=1 AND is_deleted=0 ORDER BY name_ar")->fetchAll();
    $developers = $db->query("SELECT * FROM developer_companies WHERE is_active=1 AND is_deleted=0 ORDER BY name")->fetchAll();
    $defaultPhone = getSetting('sales_phone', '0123456789');
    if ($project) {
        $images = $db->prepare("SELECT image_url FROM project_images WHERE project_id=? ORDER BY sort_order"); $images->execute([$id]); $images = implode(',', array_column($images->fetchAll(), 'image_url'));
    } else { $images = ''; }
} catch (Exception $e) { $cities = []; $developers = []; $images = ''; $defaultPhone = '0123456789'; }

$error = flash('error');
$success = flash('success');
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $project ? 'تعديل مشروع' : 'مشروع جديد' ?> - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>
<div class="flex-1 p-6 overflow-auto" x-data="projectForm()">
  <div class="flex items-center gap-4 mb-6">
    <a href="/admin/projects.php" class="text-gray-400 hover:text-gray-600">← رجوع</a>
    <h1 class="text-2xl font-bold text-gray-900"><?= $project ? 'تعديل المشروع' : 'مشروع جديد' ?></h1>
  </div>
  <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4 text-sm"><?= h($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4 text-sm"><?= h($success) ?></div><?php endif; ?>

  <form method="POST" action="/api/project-save.php" class="space-y-6">
    <?php if ($project): ?><input type="hidden" name="id" value="<?= $project['id'] ?>"><?php endif; ?>
    <input type="hidden" name="redirect" value="/admin/projects.php">

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
      <h2 class="font-bold text-gray-900">المعلومات الأساسية</h2>
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">اسم المشروع (إنجليزي) *</label>
          <input type="text" name="name" x-model="name" @input="autoSlug()" value="<?= h($project['name'] ?? '') ?>" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">اسم المشروع (عربي)</label>
          <input type="text" name="name_ar" value="<?= h($project['name_ar'] ?? '') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">الرابط (Slug) *</label>
          <input type="text" name="slug" x-model="slug" value="<?= h($project['slug'] ?? '') ?>" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518] font-mono" dir="ltr">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">شركة المطور</label>
          <select name="developer_id" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
            <option value="">بدون مطور</option>
            <?php foreach ($developers as $dev): ?>
              <option value="<?= $dev['id'] ?>" <?= ($project['developer_id'] ?? '') == $dev['id'] ? 'selected' : '' ?>><?= h($dev['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">المدينة</label>
          <select name="city_id" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
            <option value="">اختر المدينة</option>
            <?php foreach ($cities as $city): ?>
              <option value="<?= $city['id'] ?>" <?= ($project['city_id'] ?? '') == $city['id'] ? 'selected' : '' ?>><?= h($city['name_ar']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">رقم المبيعات</label>
          <input type="text" name="sales_phone" value="<?= h($project['sales_phone'] ?? $defaultPhone) ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]" dir="ltr">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">الموقع / العنوان</label>
          <input type="text" name="location" value="<?= h($project['location'] ?? '') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
      <h2 class="font-bold text-gray-900">التفاصيل المالية</h2>
      <div class="grid md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">أقل سعر</label>
          <input type="number" name="min_price" value="<?= h($project['min_price'] ?? '') ?>" step="1000" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">أقل مقدم</label>
          <input type="number" name="min_down_payment" value="<?= h($project['min_down_payment'] ?? '') ?>" step="1000" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">أقل قسط شهري</label>
          <input type="number" name="min_installment" value="<?= h($project['min_installment'] ?? '') ?>" step="100" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">أقل مساحة (م²)</label>
          <input type="number" name="min_area" value="<?= h($project['min_area'] ?? '') ?>" step="1" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
      <h2 class="font-bold text-gray-900">الوصف والمحتوى</h2>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">وصف المشروع</label>
        <textarea name="description" rows="6" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]"><?= h($project['description'] ?? '') ?></textarea>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">روابط الصور (مفصولة بفاصلة)</label>
        <textarea name="images" rows="3" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518] font-mono" dir="ltr" placeholder="https://example.com/1.jpg, https://example.com/2.jpg"><?= h($images) ?></textarea>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
      <h2 class="font-bold text-gray-900">بيانات SEO</h2>
      <div class="space-y-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">عنوان SEO</label><input type="text" name="seo_title" value="<?= h($project['seo_title'] ?? '') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">وصف SEO</label><textarea name="seo_description" rows="2" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]"><?= h($project['seo_description'] ?? '') ?></textarea></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">كلمات مفتاحية</label><input type="text" name="seo_keywords" value="<?= h($project['seo_keywords'] ?? '') ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]"></div>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
      <label class="flex items-center gap-3 cursor-pointer">
        <input type="checkbox" name="is_published" value="1" <?= ($project['is_published'] ?? 0) ? 'checked' : '' ?> class="w-4 h-4 accent-yellow-400">
        <span class="font-medium text-gray-700">نشر المشروع (يظهر للزوار)</span>
      </label>
    </div>

    <div class="flex gap-3">
      <button type="submit" class="flex-1 bg-[#F5C518] text-black font-bold py-3 rounded-xl hover:bg-yellow-400">حفظ المشروع</button>
      <a href="/admin/projects.php" class="px-6 bg-gray-100 text-gray-700 font-medium py-3 rounded-xl hover:bg-gray-200 flex items-center">إلغاء</a>
    </div>
  </form>
</div>
</div>
<script>
function projectForm() {
  return {
    name: '<?= addslashes($project['name'] ?? '') ?>',
    slug: '<?= addslashes($project['slug'] ?? '') ?>',
    autoSlug() {
      this.slug = this.slugify(this.name);
    },
    slugify(text) {
      text = text.toLowerCase().replace(/[\s\-]+/g, '-').replace(/[^a-z0-9\-]/g, '');
      return text.replace(/^-+|-+$/g, '') || Math.random().toString(36).substr(2, 8);
    }
  }
}
</script>
</body></html>
