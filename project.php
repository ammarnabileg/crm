<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: /'); exit; }

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT p.*, d.name as developer_name, c.name_ar as city_name FROM projects p LEFT JOIN developer_companies d ON d.id=p.developer_id LEFT JOIN cities c ON c.id=p.city_id WHERE p.slug=? AND p.is_published=1 AND p.is_deleted=0");
    $stmt->execute([$slug]);
    $project = $stmt->fetch();
    if (!$project) { http_response_code(404); echo '<h1>المشروع غير موجود</h1>'; exit; }

    $images = $db->prepare("SELECT * FROM project_images WHERE project_id=? ORDER BY sort_order");
    $images->execute([$project['id']]);
    $projectImages = $images->fetchAll();

    $globalFaqs = $db->query("SELECT * FROM global_faqs WHERE is_active=1 ORDER BY sort_order")->fetchAll();
    $phone = $project['sales_phone'] ?: getSetting('sales_phone', '0123456789');
    $wa = getSetting('whatsapp_number', '0123456789');
} catch (Exception $e) {
    die('خطأ في تحميل المشروع');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($project['seo_title'] ?: $project['name']) ?> - مربح</title>
  <?php if ($project['seo_description']): ?><meta name="description" content="<?= h($project['seo_description']) ?>"><?php endif; ?>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-gray-50">
<nav class="bg-white shadow-sm sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
    <a href="/" class="text-2xl font-black text-[#F5C518]">مربح</a>
    <div class="flex items-center gap-4 text-sm">
      <a href="/" class="text-gray-600 hover:text-[#F5C518]">الرئيسية</a>
      <?php if (!isLoggedIn()): ?>
        <a href="/login.php" class="bg-[#F5C518] text-black px-4 py-2 rounded-lg font-bold">دخول</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="max-w-7xl mx-auto px-4 py-8">
  <div class="flex flex-col lg:flex-row gap-8">

    <!-- LEAD FORM SIDEBAR -->
    <aside class="lg:w-72 flex-shrink-0 order-1 lg:order-2">
      <div class="sticky top-24" x-data="projectLeadForm()">
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-gray-100">
          <h3 class="font-bold text-gray-900 mb-1">احجز الآن</h3>
          <p class="text-xs text-gray-400 mb-4">تواصل مع فريق المبيعات للحجز والاستفسار</p>
          <template x-if="success">
            <div class="bg-green-50 text-green-700 rounded-xl p-4 text-sm text-center font-medium">تم الإرسال! سنتواصل معك قريباً.</div>
          </template>
          <form x-show="!success" @submit.prevent="submit" class="space-y-3">
            <input type="text" x-model="name" placeholder="الاسم *" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
            <input type="tel" x-model="phone" placeholder="رقم الهاتف *" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
            <button type="submit" :disabled="loading" class="w-full bg-[#F5C518] text-black font-bold py-2.5 rounded-xl text-sm hover:bg-yellow-400 disabled:opacity-50">
              <span x-show="!loading">إرسال الاستفسار</span>
              <span x-show="loading">جاري...</span>
            </button>
          </form>
          <p x-show="error" x-text="error" class="text-red-500 text-xs mt-2 text-center"></p>
          <div class="mt-4 flex gap-2">
            <a href="https://wa.me/<?= h($wa) ?>" target="_blank" class="flex-1 bg-green-500 text-white text-xs font-medium py-2.5 rounded-xl text-center hover:bg-green-600">واتساب</a>
            <a href="tel:<?= h($phone) ?>" class="flex-1 bg-gray-100 text-gray-700 text-xs font-medium py-2.5 rounded-xl text-center hover:bg-gray-200">اتصال</a>
          </div>
        </div>

        <!-- Key Info Card -->
        <div class="mt-4 bg-[#F5C518] rounded-2xl p-5">
          <h4 class="font-bold text-gray-900 mb-3">أبرز المميزات</h4>
          <div class="space-y-2 text-sm">
            <?php if ($project['min_price']): ?>
            <div class="flex justify-between"><span class="text-gray-700">سعر يبدأ من</span><span class="font-bold"><?= formatMoney((float)$project['min_price']) ?></span></div>
            <?php endif; ?>
            <?php if ($project['min_down_payment']): ?>
            <div class="flex justify-between"><span class="text-gray-700">مقدم يبدأ من</span><span class="font-bold"><?= formatMoney((float)$project['min_down_payment']) ?></span></div>
            <?php endif; ?>
            <?php if ($project['min_installment']): ?>
            <div class="flex justify-between"><span class="text-gray-700">قسط يبدأ من</span><span class="font-bold"><?= formatMoney((float)$project['min_installment']) ?></span></div>
            <?php endif; ?>
            <?php if ($project['min_area']): ?>
            <div class="flex justify-between"><span class="text-gray-700">مساحة تبدأ من</span><span class="font-bold"><?= number_format((float)$project['min_area']) ?> م²</span></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </aside>

    <!-- PROJECT MAIN -->
    <main class="flex-1 order-2 lg:order-1 min-w-0">
      <!-- Gallery -->
      <?php if (!empty($projectImages)): ?>
      <div class="grid grid-cols-3 gap-2 mb-6 rounded-2xl overflow-hidden h-64">
        <?php foreach (array_slice($projectImages, 0, 3) as $idx => $img): ?>
          <img src="<?= h($img['image_url']) ?>" alt="<?= h($project['name']) ?>" class="w-full h-full object-cover <?= $idx === 0 ? 'col-span-2' : '' ?>">
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="bg-gradient-to-br from-yellow-100 to-yellow-50 rounded-2xl h-64 flex items-center justify-center mb-6">
        <span class="text-gray-400 text-lg">لا توجد صور</span>
      </div>
      <?php endif; ?>

      <div class="flex flex-wrap gap-2 mb-4">
        <?php if ($project['city_name']): ?>
          <span class="bg-yellow-100 text-yellow-800 text-xs px-3 py-1 rounded-full"><?= h($project['city_name']) ?></span>
        <?php endif; ?>
        <?php if ($project['developer_name']): ?>
          <span class="bg-blue-100 text-blue-800 text-xs px-3 py-1 rounded-full"><?= h($project['developer_name']) ?></span>
        <?php endif; ?>
      </div>

      <h1 class="text-2xl md:text-3xl font-black text-gray-900 mb-2"><?= h($project['name']) ?></h1>
      <?php if ($project['name_ar'] && $project['name_ar'] !== $project['name']): ?>
        <p class="text-gray-500 text-lg mb-3"><?= h($project['name_ar']) ?></p>
      <?php endif; ?>
      <?php if ($project['location']): ?>
        <p class="text-sm text-gray-500 mb-4 flex items-center gap-1">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
          <?= h($project['location']) ?>
        </p>
      <?php endif; ?>

      <?php if ($project['description']): ?>
      <div class="bg-white rounded-2xl p-6 mb-6">
        <h2 class="text-lg font-bold mb-3">عن المشروع</h2>
        <div class="text-gray-600 text-sm leading-relaxed"><?= nl2br(h($project['description'])) ?></div>
      </div>
      <?php endif; ?>

      <!-- FAQs -->
      <?php if (!empty($globalFaqs)): ?>
      <div class="mt-6 bg-yellow-50 rounded-2xl p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">الأسئلة الشائعة</h2>
        <div class="space-y-3" x-data="{ open: null }">
          <?php foreach ($globalFaqs as $i => $faq): ?>
          <div class="bg-white rounded-xl border border-yellow-100 overflow-hidden">
            <button @click="open === <?= $i ?> ? open = null : open = <?= $i ?>" class="w-full flex items-center justify-between p-4 text-right font-semibold text-sm text-gray-900">
              <span><?= h($faq['question']) ?></span>
              <svg :class="open === <?= $i ?> ? 'rotate-180' : ''" class="w-4 h-4 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === <?= $i ?>" class="px-4 pb-4 text-sm text-gray-600"><?= h($faq['answer']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </main>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
function projectLeadForm() {
  return {
    name: '', phone: '',
    loading: false, success: false, error: '',
    async submit() {
      this.loading = true; this.error = '';
      const fd = new FormData();
      fd.append('name', this.name);
      fd.append('phone', this.phone);
      fd.append('project_id', '<?= $project['id'] ?>');
      fd.append('source', 'project_page');
      const r = await fetch('/api/lead-submit.php', { method: 'POST', body: fd });
      const d = await r.json();
      this.loading = false;
      if (d.success) this.success = true;
      else this.error = d.message || 'حدث خطأ';
    }
  }
}
</script>
</body>
</html>
