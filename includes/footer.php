<?php
$siteName = getSetting('site_name', 'مربح');
$salesPhone = getSetting('sales_phone', '0123456789');
$whatsapp = getSetting('whatsapp_number', '0123456789');
?>
<footer class="bg-gray-900 text-gray-300 mt-16">
  <div class="max-w-7xl mx-auto px-4 py-12">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
      <div>
        <div class="text-2xl font-bold text-[#F5C518] mb-3"><?= h($siteName) ?></div>
        <p class="text-sm text-gray-400">منصة عقارية رائدة تربط بين المحتوى والعملاء والوسطاء لتحقيق أفضل الصفقات العقارية.</p>
      </div>
      <div>
        <h4 class="text-white font-semibold mb-3">روابط سريعة</h4>
        <ul class="space-y-2 text-sm">
          <li><a href="/" class="hover:text-[#F5C518] transition-colors">الرئيسية</a></li>
          <li><a href="/register.php" class="hover:text-[#F5C518] transition-colors">انضم كاتب</a></li>
          <li><a href="/login.php" class="hover:text-[#F5C518] transition-colors">تسجيل الدخول</a></li>
        </ul>
      </div>
      <div>
        <h4 class="text-white font-semibold mb-3">تواصل معنا</h4>
        <div class="space-y-2 text-sm">
          <a href="tel:<?= h($salesPhone) ?>" class="flex items-center gap-2 hover:text-[#F5C518] transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            <?= h($salesPhone) ?>
          </a>
          <a href="https://wa.me/<?= h($whatsapp) ?>" target="_blank" class="flex items-center gap-2 hover:text-[#F5C518] transition-colors">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            واتساب
          </a>
        </div>
      </div>
    </div>
    <div class="border-t border-gray-700 mt-8 pt-6 text-center text-sm text-gray-500">
      © <?= date('Y') ?> <?= h($siteName) ?> - جميع الحقوق محفوظة
    </div>
  </div>
</footer>
</body>
</html>
