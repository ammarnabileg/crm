<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/', 'broker');
$user = currentUser();

// Find broker's company
try {
    $db = getDB();
    $companyStmt = $db->prepare("SELECT bc.* FROM broker_companies bc JOIN broker_company_staff bcs ON bcs.company_id=bc.id WHERE bcs.user_id=? LIMIT 1");
    $companyStmt->execute([$user['id']]);
    $company = $companyStmt->fetch();
    $companyId = $company['id'] ?? null;

    if ($companyId) {
        $assigned = $db->prepare("SELECT COUNT(*) FROM leads WHERE broker_company_id=? AND is_deleted=0"); $assigned->execute([$companyId]); $assigned = $assigned->fetchColumn();
        $inProgress = $db->prepare("SELECT COUNT(*) FROM leads WHERE broker_company_id=? AND status='in_progress' AND is_deleted=0"); $inProgress->execute([$companyId]); $inProgress = $inProgress->fetchColumn();
        $closedWon = $db->prepare("SELECT COUNT(*) FROM leads WHERE broker_company_id=? AND status='closed_won' AND is_deleted=0"); $closedWon->execute([$companyId]); $closedWon = $closedWon->fetchColumn();
        $recentLeads = $db->prepare("SELECT l.* FROM leads l WHERE l.broker_company_id=? AND l.is_deleted=0 ORDER BY l.created_at DESC LIMIT 8"); $recentLeads->execute([$companyId]); $recentLeads = $recentLeads->fetchAll();
    } else {
        $assigned = $inProgress = $closedWon = 0;
        $recentLeads = [];
    }
} catch (Exception $e) { $company=null; $assigned=$inProgress=$closedWon=0; $recentLeads=[]; }
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>لوحة الوسيط - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-broker.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">أهلاً، <?= h($user['name']) ?>!</h1>
    <?php if ($company): ?><p class="text-gray-500 text-sm mt-1"><?= h($company['name']) ?></p><?php endif; ?>
  </div>

  <?php if (!$company): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-6 text-center">
      <p class="text-yellow-800 font-medium">لم يتم تعيينك لشركة وسيطة بعد. تواصل مع الإدارة.</p>
    </div>
  <?php else: ?>

  <!-- Stats -->
  <div class="grid grid-cols-3 gap-4 mb-8">
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 text-center">
      <div class="text-3xl font-black text-gray-900"><?= $assigned ?></div>
      <div class="text-sm text-gray-500 mt-1">العملاء المحالون</div>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 text-center">
      <div class="text-3xl font-black text-blue-500"><?= $inProgress ?></div>
      <div class="text-sm text-gray-500 mt-1">جارٍ المتابعة</div>
    </div>
    <div class="bg-[#F5C518] rounded-2xl p-5 text-center">
      <div class="text-3xl font-black text-gray-900"><?= $closedWon ?></div>
      <div class="text-sm text-gray-800 mt-1">صفقات مغلقة</div>
    </div>
  </div>

  <!-- Recent Leads -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
    <div class="flex items-center justify-between p-4 border-b border-gray-50">
      <h2 class="font-bold text-gray-900">آخر العملاء</h2>
      <a href="/broker/leads.php" class="text-xs text-[#F5C518] font-medium">عرض الكل ←</a>
    </div>
    <div class="divide-y divide-gray-50">
      <?php if (empty($recentLeads)): ?>
        <p class="text-center text-gray-400 py-8 text-sm">لا توجد عملاء محالون بعد</p>
      <?php else: foreach ($recentLeads as $lead): ?>
        <div class="p-4 flex items-center justify-between gap-2">
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900"><?= h($lead['name']) ?> <span class="text-gray-400 font-mono text-xs" dir="ltr"><?= h($lead['phone']) ?></span></p>
            <p class="text-xs text-gray-400 mt-0.5"><?= timeAgo($lead['created_at']) ?></p>
          </div>
          <div class="flex items-center gap-2">
            <?= statusBadge($lead['pipeline_stage']) ?>
            <a href="/broker/lead-detail.php?id=<?= $lead['id'] ?>" class="text-xs bg-gray-100 text-gray-600 px-3 py-1 rounded-lg hover:bg-gray-200">تفاصيل</a>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
</div>
</body></html>
