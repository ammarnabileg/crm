<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin','account_manager');

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$filterStatus = $_GET['status'] ?? '';
$filterScore = $_GET['score'] ?? '';
$filterSource = $_GET['source'] ?? '';
$filterCity = $_GET['city_id'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

try {
    $db = getDB();
    $where = ['l.is_deleted=0'];
    $params = [];
    if ($filterStatus) { $where[] = 'l.status=?'; $params[] = $filterStatus; }
    if ($filterScore) { $where[] = 'l.score=?'; $params[] = $filterScore; }
    if ($filterSource) { $where[] = 'l.source=?'; $params[] = $filterSource; }
    if ($filterCity) { $where[] = 'l.city_id=?'; $params[] = $filterCity; }
    if ($filterDateFrom) { $where[] = 'DATE(l.created_at)>=?'; $params[] = $filterDateFrom; }
    if ($filterDateTo) { $where[] = 'DATE(l.created_at)<=?'; $params[] = $filterDateTo; }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);
    $total = $db->prepare("SELECT COUNT(*) FROM leads l $whereSQL");
    $total->execute($params);
    $total = $total->fetchColumn();
    $totalPages = ceil($total / $perPage);

    $stmt = $db->prepare("SELECT l.*, a.title as article_title, u.name as writer_name, bc.name as broker_name, c.name_ar as city_name FROM leads l LEFT JOIN articles a ON a.id=l.article_id LEFT JOIN users u ON u.id=l.writer_id LEFT JOIN broker_companies bc ON bc.id=l.broker_company_id LEFT JOIN cities c ON c.id=l.city_id $whereSQL ORDER BY l.created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $leads = $stmt->fetchAll();

    $cities = $db->query("SELECT id, name_ar FROM cities WHERE is_active=1 AND is_deleted=0 ORDER BY name_ar")->fetchAll();
} catch (Exception $e) { $leads = []; $total = 0; $totalPages = 1; $cities = []; }
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>إدارة العملاء CRM - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">إدارة العملاء (CRM)</h1>
    <p class="text-gray-500 text-sm mt-1">إجمالي <?= number_format($total) ?> عميل</p>
  </div>

  <!-- Filters -->
  <form method="GET" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6">
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
      <select name="status" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]">
        <option value="">كل الحالات</option>
        <option value="new" <?= $filterStatus==='new'?'selected':'' ?>>جديد</option>
        <option value="assigned" <?= $filterStatus==='assigned'?'selected':'' ?>>محال</option>
        <option value="in_progress" <?= $filterStatus==='in_progress'?'selected':'' ?>>جاري</option>
        <option value="closed_won" <?= $filterStatus==='closed_won'?'selected':'' ?>>تم البيع</option>
        <option value="closed_lost" <?= $filterStatus==='closed_lost'?'selected':'' ?>>خسارة</option>
        <option value="duplicate" <?= $filterStatus==='duplicate'?'selected':'' ?>>مكرر</option>
      </select>
      <select name="score" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]">
        <option value="">كل الدرجات</option>
        <option value="cold" <?= $filterScore==='cold'?'selected':'' ?>>بارد</option>
        <option value="warm" <?= $filterScore==='warm'?'selected':'' ?>>دافئ</option>
        <option value="hot" <?= $filterScore==='hot'?'selected':'' ?>>ساخن</option>
        <option value="high_intent" <?= $filterScore==='high_intent'?'selected':'' ?>>نية عالية</option>
      </select>
      <select name="source" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]">
        <option value="">كل المصادر</option>
        <option value="form" <?= $filterSource==='form'?'selected':'' ?>>نموذج</option>
        <option value="article" <?= $filterSource==='article'?'selected':'' ?>>مقالة</option>
        <option value="project_page" <?= $filterSource==='project_page'?'selected':'' ?>>صفحة مشروع</option>
        <option value="homepage" <?= $filterSource==='homepage'?'selected':'' ?>>الرئيسية</option>
        <option value="whatsapp" <?= $filterSource==='whatsapp'?'selected':'' ?>>واتساب</option>
      </select>
      <select name="city_id" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]">
        <option value="">كل المدن</option>
        <?php foreach ($cities as $city): ?>
          <option value="<?= $city['id'] ?>" <?= $filterCity==$city['id']?'selected':'' ?>><?= h($city['name_ar']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="date_from" value="<?= h($filterDateFrom) ?>" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]">
      <div class="flex gap-2">
        <input type="date" name="date_to" value="<?= h($filterDateTo) ?>" class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]">
        <button type="submit" class="bg-[#F5C518] text-black px-3 py-2 rounded-xl text-sm font-medium">فلتر</button>
      </div>
    </div>
  </form>

  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr>
            <th class="text-right p-4 font-medium">الاسم</th>
            <th class="text-right p-4 font-medium">الهاتف</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">المصدر</th>
            <th class="text-right p-4 font-medium">الدرجة</th>
            <th class="text-right p-4 font-medium">الحالة</th>
            <th class="text-right p-4 font-medium hidden lg:table-cell">الكاتب</th>
            <th class="text-right p-4 font-medium hidden lg:table-cell">الشركة</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">التاريخ</th>
            <th class="text-right p-4 font-medium">إجراء</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($leads)): ?>
            <tr><td colspan="9" class="text-center text-gray-400 py-8">لا توجد نتائج</td></tr>
          <?php else: foreach ($leads as $lead): ?>
            <tr class="hover:bg-gray-50">
              <td class="p-4 font-medium text-gray-900">
                <?= h($lead['name']) ?>
                <?php if ($lead['is_duplicate']): ?><span class="text-xs bg-gray-100 text-gray-500 px-1 rounded ml-1">مكرر</span><?php endif; ?>
              </td>
              <td class="p-4 font-mono text-gray-500 text-xs" dir="ltr"><?= h($lead['phone']) ?></td>
              <td class="p-4 hidden md:table-cell"><?= statusBadge($lead['source']) ?></td>
              <td class="p-4"><?= statusBadge($lead['score']) ?></td>
              <td class="p-4"><?= statusBadge($lead['status']) ?></td>
              <td class="p-4 text-gray-500 text-xs hidden lg:table-cell"><?= h($lead['writer_name'] ?? '-') ?></td>
              <td class="p-4 text-gray-500 text-xs hidden lg:table-cell"><?= h($lead['broker_name'] ?? '-') ?></td>
              <td class="p-4 text-gray-400 text-xs hidden md:table-cell"><?= date('d/m/Y', strtotime($lead['created_at'])) ?></td>
              <td class="p-4">
                <a href="/admin/lead-detail.php?id=<?= $lead['id'] ?>" class="text-xs bg-[#F5C518] text-black px-3 py-1 rounded-lg font-medium hover:bg-yellow-400">تفاصيل</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="p-4 border-t border-gray-50 flex items-center justify-between">
      <span class="text-sm text-gray-500">صفحة <?= $page ?> من <?= $totalPages ?></span>
      <div class="flex gap-2">
        <?php if ($page > 1): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>" class="px-3 py-1 border border-gray-200 rounded-lg text-sm hover:bg-gray-50">السابق</a><?php endif; ?>
        <?php if ($page < $totalPages): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>" class="px-3 py-1 border border-gray-200 rounded-lg text-sm hover:bg-gray-50">التالي</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
</div>
</body></html>
