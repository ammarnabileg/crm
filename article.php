<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: /'); exit; }

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT a.*, u.name as author_name, c.name_ar as city_name, p.name as project_name, p.slug as project_slug FROM articles a LEFT JOIN users u ON u.id=a.author_id LEFT JOIN cities c ON c.id=a.city_id LEFT JOIN projects p ON p.id=a.project_id WHERE a.slug=? AND a.status='approved' AND a.is_deleted=0");
    $stmt->execute([$slug]);
    $article = $stmt->fetch();
    if (!$article) { header('HTTP/1.1 404 Not Found'); include '404.php'; exit; }

    // Track view
    $db->prepare("UPDATE articles SET view_count=view_count+1 WHERE id=?")->execute([$article['id']]);

    // Load FAQs
    $faqStmt = $db->prepare("SELECT * FROM article_faqs WHERE article_id=? ORDER BY sort_order");
    $faqStmt->execute([$article['id']]);
    $articleFaqs = $faqStmt->fetchAll();

    $globalFaqs = $db->query("SELECT * FROM global_faqs WHERE is_active=1 ORDER BY sort_order")->fetchAll();
    $phone = getSetting('sales_phone', '0123456789');
    $wa = getSetting('whatsapp_number', '0123456789');
} catch (Exception $e) {
    die('خطأ في تحميل المقال');
}
$pageTitle = $article['seo_title'] ?: $article['title'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($pageTitle) ?> - مربح</title>
  <?php if ($article['seo_description']): ?>
  <meta name="description" content="<?= h($article['seo_description']) ?>">
  <?php endif; ?>
  <?php if ($article['seo_keywords']): ?>
  <meta name="keywords" content="<?= h($article['seo_keywords']) ?>">
  <?php endif; ?>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>body { font-family: 'Cairo', sans-serif; } .prose p { margin-bottom: 1rem; } .prose h2 { font-size: 1.4rem; font-weight: 700; margin: 1.5rem 0 0.5rem; } .prose h3 { font-size: 1.2rem; font-weight: 600; margin: 1.2rem 0 0.4rem; }</style>
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

    <!-- LEAD FORM SIDEBAR (sticky) -->
    <aside class="lg:w-72 flex-shrink-0 order-1 lg:order-2">
      <div class="sticky top-24" x-data="leadFormArticle()">
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-gray-100">
          <h3 class="font-bold text-gray-900 mb-1">الحجز والاستفسار</h3>
          <p class="text-xs text-gray-400 mb-4">أرسل بياناتك وسيتواصل معك مستشار عقاري</p>
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
      </div>
    </aside>

    <!-- ARTICLE MAIN -->
    <main class="flex-1 order-2 lg:order-1 min-w-0">
      <?php if ($article['cover_image']): ?>
        <img src="<?= h($article['cover_image']) ?>" alt="<?= h($article['title']) ?>" class="w-full h-64 md:h-96 object-cover rounded-2xl mb-6">
      <?php endif; ?>

      <div class="flex flex-wrap gap-2 mb-4">
        <?php if ($article['city_name']): ?>
          <span class="bg-yellow-100 text-yellow-800 text-xs px-3 py-1 rounded-full"><?= h($article['city_name']) ?></span>
        <?php endif; ?>
        <?php if ($article['project_name']): ?>
          <a href="/project.php?slug=<?= h($article['project_slug']) ?>" class="bg-blue-100 text-blue-800 text-xs px-3 py-1 rounded-full hover:bg-blue-200"><?= h($article['project_name']) ?></a>
        <?php endif; ?>
      </div>

      <h1 class="text-2xl md:text-3xl font-black text-gray-900 mb-3"><?= h($article['title']) ?></h1>

      <div class="flex items-center gap-4 text-sm text-gray-400 mb-6 pb-4 border-b border-gray-100">
        <span>بقلم: <?= h($article['author_name']) ?></span>
        <?php if ($article['published_at']): ?>
          <span><?= date('d/m/Y', strtotime($article['published_at'])) ?></span>
        <?php endif; ?>
        <span><?= number_format($article['view_count']) ?> مشاهدة</span>
      </div>

      <div class="prose text-gray-700 leading-relaxed text-sm md:text-base">
        <?= nl2br(h($article['content'])) ?>
      </div>

      <!-- FAQs -->
      <?php if (!empty($articleFaqs) || !empty($globalFaqs)): ?>
      <div class="mt-10 bg-yellow-50 rounded-2xl p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">الأسئلة الشائعة</h2>
        <div class="space-y-3" x-data="{ open: null }">
          <?php foreach (array_merge($articleFaqs, $globalFaqs) as $i => $faq): ?>
          <div class="bg-white rounded-xl border border-yellow-100 overflow-hidden">
            <button @click="open === <?= $i ?> ? open = null : open = <?= $i ?>" class="w-full flex items-center justify-between p-4 text-right font-semibold text-sm text-gray-900">
              <span><?= h($faq['question']) ?></span>
              <svg :class="open === <?= $i ?> ? 'rotate-180' : ''" class="w-4 h-4 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === <?= $i ?>" x-collapse class="px-4 pb-4 text-sm text-gray-600">
              <?= h($faq['answer']) ?>
            </div>
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
function leadFormArticle() {
  return {
    name: '', phone: '',
    loading: false, success: false, error: '',
    async submit() {
      this.loading = true; this.error = '';
      const fd = new FormData();
      fd.append('name', this.name);
      fd.append('phone', this.phone);
      fd.append('article_id', '<?= $article['id'] ?>');
      fd.append('source', 'article');
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
