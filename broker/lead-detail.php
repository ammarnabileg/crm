<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/', 'broker');
$user = currentUser();

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: /broker/leads.php'); exit; }

try {
    $db = getDB();
    // Verify broker has access
    $companyStmt = $db->prepare("SELECT company_id FROM broker_company_staff WHERE user_id=? LIMIT 1");
    $companyStmt->execute([$user['id']]);
    $companyId = $companyStmt->fetchColumn();
    if (!$companyId) { header('Location: /broker/leads.php'); exit; }

    $stmt = $db->prepare("SELECT l.*, c.name_ar as city_name, p.name as project_name FROM leads l LEFT JOIN cities c ON c.id=l.city_id LEFT JOIN projects p ON p.id=l.project_id WHERE l.id=? AND l.broker_company_id=? AND l.is_deleted=0");
    $stmt->execute([$id, $companyId]);
    $lead = $stmt->fetch();
    if (!$lead) { header('Location: /broker/leads.php'); exit; }

    $interactions = $db->prepare("SELECT li.*, u.name as user_name FROM lead_interactions li LEFT JOIN users u ON u.id=li.created_by WHERE li.lead_id=? ORDER BY li.created_at ASC");
    $interactions->execute([$id]);
    $interactions = $interactions->fetchAll();

    $deal = $db->prepare("SELECT * FROM deals WHERE lead_id=? AND is_deleted=0");
    $deal->execute([$id]);
    $deal = $deal->fetch();
} catch (Exception $e) { header('Location: /broker/leads.php'); exit; }

$success = flash('success');
$error = flash('error');
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تفاصيل العميل - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-broker.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <div class="flex items-center gap-4 mb-6">
    <a href="/broker/leads.php" class="text-gray-400 hover:text-gray-600">← رجوع</a>
    <h1 class="text-2xl font-bold text-gray-900">تفاصيل: <?= h($lead['name']) ?></h1>
  </div>

  <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4 text-sm"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4 text-sm"><?= h($error) ?></div><?php endif; ?>

  <div class="grid lg:grid-cols-3 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-2 space-y-6">
      <!-- Lead Info -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-start justify-between mb-4">
          <h2 class="font-bold text-gray-900">معلومات العميل</h2>
          <div class="flex gap-2"><?= statusBadge($lead['status']) ?> <?= statusBadge($lead['score']) ?></div>
        </div>
        <div class="grid md:grid-cols-2 gap-4 text-sm">
          <div><span class="text-gray-500">الاسم:</span> <span class="font-medium"><?= h($lead['name']) ?></span></div>
          <div><span class="text-gray-500">الهاتف:</span> <a href="tel:<?= h($lead['phone']) ?>" class="font-medium text-blue-600" dir="ltr"><?= h($lead['phone']) ?></a></div>
          <?php if ($lead['email']): ?><div><span class="text-gray-500">البريد:</span> <span><?= h($lead['email']) ?></span></div><?php endif; ?>
          <?php if ($lead['interested_area']): ?><div><span class="text-gray-500">المنطقة:</span> <span><?= h($lead['interested_area']) ?></span></div><?php endif; ?>
          <?php if ($lead['city_name']): ?><div><span class="text-gray-500">المدينة:</span> <span><?= h($lead['city_name']) ?></span></div><?php endif; ?>
          <?php if ($lead['project_name']): ?><div><span class="text-gray-500">المشروع:</span> <span><?= h($lead['project_name']) ?></span></div><?php endif; ?>
          <div><span class="text-gray-500">مرحلة الـ Pipeline:</span> <?= statusBadge($lead['pipeline_stage']) ?></div>
        </div>
      </div>

      <!-- Update Pipeline Stage -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-bold text-gray-900 mb-4">تحديث مرحلة المتابعة</h2>
        <form method="POST" action="/api/lead-stage.php" class="flex flex-wrap gap-3">
          <input type="hidden" name="lead_id" value="<?= $id ?>">
          <input type="hidden" name="redirect" value="/broker/lead-detail.php?id=<?= $id ?>">
          <select name="stage" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]">
            <?php
            $stages = ['new_lead','attempted_contact','contacted','interested','viewing_scheduled','viewing_completed','negotiation','reservation','closed_won','closed_lost'];
            $stageLabels = ['new_lead'=>'عميل جديد','attempted_contact'=>'محاولة تواصل','contacted'=>'تم التواصل','interested'=>'مهتم','viewing_scheduled'=>'معاينة مجدولة','viewing_completed'=>'اكتملت المعاينة','negotiation'=>'تفاوض','reservation'=>'حجز','closed_won'=>'صفقة ناجحة','closed_lost'=>'لم تتم الصفقة'];
            foreach ($stages as $s):
            ?><option value="<?= $s ?>" <?= $lead['pipeline_stage']===$s?'selected':'' ?>><?= $stageLabels[$s] ?? $s ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="bg-[#F5C518] text-black font-medium px-4 py-2 rounded-xl text-sm hover:bg-yellow-400">تحديث</button>
        </form>
      </div>

      <!-- Interactions -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-bold text-gray-900 mb-4">سجل التفاعلات</h2>
        <div class="space-y-3 mb-4">
          <?php if (empty($interactions)): ?>
            <p class="text-gray-400 text-sm">لا توجد تفاعلات بعد</p>
          <?php else: foreach ($interactions as $inter): ?>
            <div class="flex gap-3">
              <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-medium"><?= mb_substr($inter['user_name'] ?? '?', 0, 1) ?></div>
              <div class="flex-1">
                <div class="bg-gray-50 rounded-xl p-3 text-sm"><?= h($inter['content']) ?></div>
                <div class="text-xs text-gray-400 mt-1"><?= h($inter['user_name'] ?? 'نظام') ?> • <?= date('d/m/Y H:i', strtotime($inter['created_at'])) ?></div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
        <form method="POST" action="/api/lead-interact.php" class="flex gap-3">
          <input type="hidden" name="lead_id" value="<?= $id ?>">
          <input type="hidden" name="redirect" value="/broker/lead-detail.php?id=<?= $id ?>">
          <input type="hidden" name="type" value="broker_note">
          <input type="text" name="content" required placeholder="أضف ملاحظة..." class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]">
          <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded-xl text-sm hover:bg-gray-700">إضافة</button>
        </form>
      </div>
    </div>

    <!-- Side Panel -->
    <div class="space-y-4">
      <!-- Quick Actions -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-bold text-gray-900 mb-3">تواصل سريع</h3>
        <div class="space-y-2">
          <a href="tel:<?= h($lead['phone']) ?>" class="flex items-center gap-2 bg-gray-100 text-gray-700 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-200">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            اتصال: <?= h($lead['phone']) ?>
          </a>
          <a href="https://wa.me/<?= h(preg_replace('/[^0-9]/', '', $lead['phone'])) ?>" target="_blank" class="flex items-center gap-2 bg-green-500 text-white px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-green-600">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967c-.273-.099-.471-.148-.67.15c-.197.297-.767.966-.94 1.164c-.173.199-.347.223-.644.075c-.297-.15-1.255-.463-2.39-1.475c-.883-.788-1.48-1.761-1.653-2.059c-.173-.297-.018-.458.13-.606c.134-.133.298-.347.446-.52c.149-.174.198-.298.298-.497c.099-.198.05-.371-.025-.52c-.075-.149-.669-1.612-.916-2.207c-.242-.579-.487-.5-.669-.51c-.173-.008-.371-.01-.57-.01c-.198 0-.52.074-.792.372c-.272.297-1.04 1.016-1.04 2.479c0 1.462 1.065 2.875 1.213 3.074c.149.198 2.096 3.2 5.077 4.487c.709.306 1.262.489 1.694.625c.712.227 1.36.195 1.871.118c.571-.085 1.758-.719 2.006-1.413c.248-.694.248-1.289.173-1.413c-.074-.124-.272-.198-.57-.347z"/></svg>
            واتساب
          </a>
        </div>
      </div>

      <!-- Deal Submission -->
      <?php if (!$deal && $lead['pipeline_stage'] === 'closed_won'): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-bold text-gray-900 mb-3">تسجيل الصفقة</h3>
        <form method="POST" action="/api/deal-submit.php" class="space-y-3">
          <input type="hidden" name="lead_id" value="<?= $id ?>">
          <input type="hidden" name="redirect" value="/broker/lead-detail.php?id=<?= $id ?>">
          <div><label class="block text-xs font-medium text-gray-700 mb-1">قيمة البيع (ج.م) *</label><input type="number" name="sale_amount" step="1000" required class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]"></div>
          <div><label class="block text-xs font-medium text-gray-700 mb-1">صافي الربح *</label><input type="number" name="net_profit" step="1000" required class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]"></div>
          <div><label class="block text-xs font-medium text-gray-700 mb-1">رابط الإثباتات</label><input type="url" name="proof_files" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]" dir="ltr"></div>
          <div><label class="block text-xs font-medium text-gray-700 mb-1">ملاحظات</label><textarea name="notes" rows="2" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]"></textarea></div>
          <button type="submit" class="w-full bg-[#F5C518] text-black font-bold py-2.5 rounded-xl hover:bg-yellow-400 text-sm">تسجيل الصفقة</button>
        </form>
      </div>
      <?php elseif ($deal): ?>
      <div class="bg-[#F5C518] rounded-2xl p-5">
        <h3 class="font-bold text-gray-900 mb-3">الصفقة المسجلة</h3>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between"><span>قيمة البيع</span><span class="font-bold"><?= formatMoney((float)$deal['sale_amount']) ?></span></div>
          <div class="flex justify-between"><span>الحالة</span><?= statusBadge($deal['status']) ?></div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>
</body></html>
