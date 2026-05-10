<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'الرئيسية - ابحث عن عقارك المميز';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config = { theme: { extend: { fontFamily: { cairo: ['Cairo','sans-serif'] }, colors: { brand: '#F5C518' } } } }</script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-gray-50 text-gray-900">

<!-- NAV -->
<nav class="bg-white shadow-sm sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
    <a href="/" class="text-2xl font-black text-[#F5C518]">مربح <span class="text-gray-900 text-lg font-semibold">MORBEH</span></a>
    <div class="hidden md:flex items-center gap-6 text-sm font-medium">
      <a href="/" class="text-gray-700 hover:text-[#F5C518]">الرئيسية</a>
      <a href="/index.php?section=projects" class="text-gray-700 hover:text-[#F5C518]">المشاريع العقارية</a>
      <a href="/index.php?section=articles" class="text-gray-700 hover:text-[#F5C518]">مقالات تهمك</a>
      <?php if (isLoggedIn()): ?>
        <?php $u = currentUser(); ?>
        <?php if (hasRole('admin','super_admin','account_manager')): ?>
          <a href="/admin/" class="bg-gray-900 text-white px-4 py-2 rounded-lg hover:bg-gray-700">لوحة الإدارة</a>
        <?php elseif (hasRole('writer')): ?>
          <a href="/writer/" class="bg-gray-900 text-white px-4 py-2 rounded-lg hover:bg-gray-700">لوحتي</a>
        <?php elseif (hasRole('broker')): ?>
          <a href="/broker/" class="bg-gray-900 text-white px-4 py-2 rounded-lg hover:bg-gray-700">لوحتي</a>
        <?php endif; ?>
      <?php else: ?>
        <a href="/register.php" class="bg-[#F5C518] text-black px-4 py-2 rounded-lg font-bold hover:bg-yellow-400">انضم لفريقنا</a>
        <a href="/login.php" class="border border-gray-300 px-4 py-2 rounded-lg hover:border-[#F5C518]">دخول</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="bg-[#F5C518] py-20 px-4">
  <div class="max-w-4xl mx-auto text-center">
    <h1 class="text-4xl md:text-6xl font-black text-gray-900 mb-4">ابحث عن عقارك المميز</h1>
    <p class="text-gray-800 text-lg mb-8 font-medium">آلاف الوحدات السكنية والتجارية في أفضل المواقع بمصر</p>
    <form method="GET" action="/index.php" class="bg-white rounded-2xl shadow-xl p-4 flex flex-col md:flex-row gap-3 max-w-2xl mx-auto">
      <input type="text" name="q" value="<?= h($_GET['q'] ?? '') ?>" placeholder="اسم المشروع أو الحي..." class="flex-1 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#F5C518]">
      <select name="type" class="border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#F5C518]">
        <option value="">نوع الوحدة</option>
        <option value="apartment">شقة</option>
        <option value="villa">فيلا</option>
        <option value="duplex">دوبلكس</option>
        <option value="office">مكتب</option>
        <option value="shop">محل تجاري</option>
      </select>
      <button type="submit" class="bg-[#F5C518] text-black font-bold px-6 py-3 rounded-xl hover:bg-yellow-400">بحث</button>
    </form>
  </div>
</section>

<!-- CITIES GRID -->
<section class="py-16 px-4 max-w-7xl mx-auto">
  <h2 class="text-2xl font-bold text-gray-900 mb-2">اختر منطقتك</h2>
  <p class="text-gray-500 mb-8">تصفح المشاريع حسب المدينة</p>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <?php
    try {
      $db = getDB();
      $cities = $db->query("SELECT * FROM cities WHERE is_active=1 AND is_deleted=0 ORDER BY name_ar")->fetchAll();
      foreach ($cities as $city):
        $img = $city['image'] ?: 'https://images.unsplash.com/photo-1486325212027-8081e485255e?w=400&h=200&fit=crop';
    ?>
      <a href="/index.php?city_id=<?= $city['id'] ?>" class="relative rounded-2xl overflow-hidden h-40 group cursor-pointer">
        <img src="<?= h($img) ?>" alt="<?= h($city['name_ar']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
        <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent"></div>
        <div class="absolute bottom-3 right-3 text-white font-bold text-sm"><?= h($city['name_ar']) ?></div>
      </a>
    <?php endforeach;
    } catch (Exception $e) { echo '<p class="text-red-500 col-span-4">تعذر تحميل المدن</p>'; } ?>
  </div>
</section>

<!-- LATEST ARTICLES -->
<section class="py-16 px-4 bg-white" id="articles">
  <div class="max-w-7xl mx-auto">
    <h2 class="text-2xl font-bold text-gray-900 mb-2">آخر التطورات العقارية</h2>
    <p class="text-gray-500 mb-8">مقالات وتحليلات متخصصة في السوق العقاري</p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <?php
      try {
        $db = getDB();
        $arts = $db->query("SELECT a.*, u.name as author_name, c.name_ar as city_name FROM articles a LEFT JOIN users u ON u.id=a.author_id LEFT JOIN cities c ON c.id=a.city_id WHERE a.status='approved' AND a.is_deleted=0 ORDER BY a.published_at DESC LIMIT 6")->fetchAll();
        foreach ($arts as $art):
          $cover = $art['cover_image'] ?: 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=600&h=300&fit=crop';
      ?>
        <article class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
          <a href="/article.php?slug=<?= h($art['slug']) ?>">
            <img src="<?= h($cover) ?>" alt="<?= h($art['title']) ?>" class="w-full h-48 object-cover">
          </a>
          <div class="p-4">
            <?php if ($art['city_name']): ?>
              <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full"><?= h($art['city_name']) ?></span>
            <?php endif; ?>
            <h3 class="font-bold text-gray-900 mt-2 mb-1 line-clamp-2">
              <a href="/article.php?slug=<?= h($art['slug']) ?>" class="hover:text-[#F5C518]"><?= h($art['title']) ?></a>
            </h3>
            <?php if ($art['excerpt']): ?>
              <p class="text-sm text-gray-500 line-clamp-2"><?= h($art['excerpt']) ?></p>
            <?php endif; ?>
            <div class="flex items-center justify-between mt-3 text-xs text-gray-400">
              <span><?= h($art['author_name']) ?></span>
              <span><?= $art['published_at'] ? date('d/m/Y', strtotime($art['published_at'])) : '' ?></span>
            </div>
          </div>
        </article>
      <?php endforeach;
      if (empty($arts)): ?>
        <p class="col-span-3 text-center text-gray-400 py-8">لا توجد مقالات منشورة حتى الآن</p>
      <?php endif;
      } catch (Exception $e) { echo '<p class="col-span-3 text-red-500">تعذر تحميل المقالات</p>'; } ?>
    </div>
  </div>
</section>

<!-- CONSULTATION CTA -->
<section class="py-16 px-4 bg-gray-900" x-data="leadForm()">
  <div class="max-w-5xl mx-auto grid md:grid-cols-2 gap-10 items-center">
    <div class="text-white">
      <h2 class="text-3xl font-black mb-3">احصل على استشارة مجانية</h2>
      <p class="text-gray-400 mb-6">فريقنا المتخصص يساعدك في اختيار الوحدة المثالية بأفضل الأسعار وأسهل التسهيلات.</p>
      <?php $phone = getSetting('sales_phone','0123456789'); $wa = getSetting('whatsapp_number','0123456789'); ?>
      <div class="flex flex-col gap-3">
        <a href="tel:<?= h($phone) ?>" class="flex items-center gap-3 bg-white/10 hover:bg-white/20 rounded-xl px-4 py-3 transition-colors">
          <svg class="w-5 h-5 text-[#F5C518]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
          <span class="font-medium"><?= h($phone) ?></span>
        </a>
        <a href="https://wa.me/<?= h($wa) ?>" target="_blank" class="flex items-center gap-3 bg-green-600/80 hover:bg-green-600 rounded-xl px-4 py-3 transition-colors">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          <span class="font-medium">تواصل واتساب</span>
        </a>
      </div>
    </div>
    <div class="bg-white rounded-2xl p-6 shadow-xl">
      <h3 class="font-bold text-gray-900 mb-4 text-lg">طلب استشارة مجانية</h3>
      <template x-if="success">
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-green-800 text-center font-medium">
          تم إرسال طلبك بنجاح! سيتواصل معك فريقنا قريباً.
        </div>
      </template>
      <form x-show="!success" @submit.prevent="submit" class="space-y-3">
        <input type="text" x-model="name" placeholder="الاسم الكامل *" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#F5C518]" required>
        <input type="tel" x-model="phone" placeholder="رقم الهاتف *" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#F5C518]" required>
        <select x-model="area" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#F5C518]">
          <option value="">المنطقة المفضلة</option>
          <?php
          try {
            $db = getDB();
            $cities2 = $db->query("SELECT * FROM cities WHERE is_active=1 AND is_deleted=0 ORDER BY name_ar")->fetchAll();
            foreach ($cities2 as $c): ?>
            <option value="<?= h($c['name_ar']) ?>"><?= h($c['name_ar']) ?></option>
          <?php endforeach;
          } catch (Exception $e) {} ?>
        </select>
        <button type="submit" :disabled="loading" class="w-full bg-[#F5C518] text-black font-bold py-3 rounded-xl hover:bg-yellow-400 disabled:opacity-50">
          <span x-show="!loading">طلب استشارة مجانية</span>
          <span x-show="loading">جاري الإرسال...</span>
        </button>
        <p x-show="error" x-text="error" class="text-red-600 text-sm text-center"></p>
      </form>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
function leadForm() {
  return {
    name: '', phone: '', area: '',
    loading: false, success: false, error: '',
    async submit() {
      this.loading = true; this.error = '';
      const fd = new FormData();
      fd.append('name', this.name);
      fd.append('phone', this.phone);
      fd.append('interested_area', this.area);
      fd.append('source', 'homepage');
      const r = await fetch('/api/lead-submit.php', { method: 'POST', body: fd });
      const d = await r.json();
      this.loading = false;
      if (d.success) this.success = true;
      else this.error = d.message || 'حدث خطأ، حاول مرة أخرى';
    }
  }
}
</script>
</body>
</html>
