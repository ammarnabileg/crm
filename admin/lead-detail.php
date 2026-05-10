<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin','account_manager');

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin/leads.php'); exit; }

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT l.*, a.title as article_title, a.slug as article_slug, u.name as writer_name, bc.name as broker_name, c.name_ar as city_name, p.name as project_name FROM leads l LEFT JOIN articles a ON a.id=l.article_id LEFT JOIN users u ON u.id=l.writer_id LEFT JOIN broker_companies bc ON bc.id=l.broker_company_id LEFT JOIN cities c ON c.id=l.city_id LEFT JOIN projects p ON p.id=l.project_id WHERE l.id=? AND l.is_deleted=0");
    $stmt->execute([$id]);
    $lead = $stmt->fetch();
    if (!$lead) { header('Location: /admin/leads.php'); exit; }

    $interactions = $db->prepare("SELECT li.*, u.name as user_name FROM lead_interactions li LEFT JOIN users u ON u.id=li.created_by WHERE li.lead_id=? ORDER BY li.created_at ASC");
    $interactions->execute([$id]);
    $interactions = $interactions->fetchAll();

    $deal = $db->prepare("SELECT d.*, u.name as verified_by_name FROM deals d LEFT JOIN users u ON u.id=d.verified_by WHERE d.lead_id=? AND d.is_deleted=0");
    $deal->execute([$id]);
    $deal = $deal->fetch();

    $commission = null;
    if ($deal) {
        $comm = $db->prepare("SELECT c.*, u.name as writer_name FROM commissions c LEFT JOIN users u ON u.id=c.writer_id WHERE c.deal_id=? AND c.is_deleted=0");
        $comm->execute([$deal['id']]);
        $commission = $comm->fetch();
    }

    $brokerCompanies = $db->query("SELECT * FROM broker_companies WHERE is_active=1 AND is_deleted=0 ORDER BY name")->fetchAll();
} catch (Exception $e) { header('Location: /admin/leads.php'); exit; }

$success = flash('success');
$error = flash('error');
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تفاصيل العميل - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <div class="flex items-center gap-4 mb-6">
    <a href="/admin/leads.php" class="text-gray-400 hover:text-gray-600">← رجوع</a>
    <h1 class="text-2xl font-bold text-gray-900">تفاصيل العميل: <?= h($lead['name']) ?></h1>
  </div>

  <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4 text-sm"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4 text-sm"><?= h($error) ?></div><?php endif; ?>

  <div class="grid lg:grid-cols-3 gap-6">
    <!-- Lead Info -->
    <div class="lg:col-span-2 space-y-6">
      <!-- Basic Info Card -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-start justify-between mb-4">
          <h2 class="font-bold text-gray-900">معلومات العميل</h2>
          <div class="flex gap-2"><?= statusBadge($lead['status']) ?> <?= statusBadge($lead['score']) ?></div>
        </div>
        <div class="grid md:grid-cols-2 gap-4 text-sm">
          <div><span class="text-gray-500">الاسم:</span> <span class="font-medium"><?= h($lead['name']) ?></span></div>
          <div><span class="text-gray-500">الهاتف:</span> <a href="tel:<?= h($lead['phone']) ?>" class="font-medium text-blue-600" dir="ltr"><?= h($lead['phone']) ?></a></div>
          <?php if ($lead['email']): ?><div><span class="text-gray-500">البريد:</span> <span class="font-medium"><?= h($lead['email']) ?></span></div><?php endif; ?>
          <?php if ($lead['interested_area']): ?><div><span class="text-gray-500">المنطقة:</span> <span class="font-medium"><?= h($lead['interested_area']) ?></span></div><?php endif; ?>
          <div><span class="text-gray-500">المصدر:</span> <?= statusBadge($lead['source']) ?></div>
          <div><span class="text-gray-500">المرحلة:</span> <?= statusBadge($lead['pipeline_stage']) ?></div>
          <?php if ($lead['city_name']): ?><div><span class="text-gray-500">المدينة:</span> <span class="font-medium"><?= h($lead['city_name']) ?></span></div><?php endif; ?>
          <?php if ($lead['project_name']): ?><div><span class="text-gray-500">المشروع:</span> <span class="font-medium"><?= h($lead['project_name']) ?></span></div><?php endif; ?>
          <?php if ($lead['article_title']): ?><div class="md:col-span-2"><span class="text-gray-500">المقالة:</span> <a href="/article.php?slug=<?= h($lead['article_slug']) ?>" class="text-blue-600 hover:underline"><?= h($lead['article_title']) ?></a></div><?php endif; ?>
          <?php if ($lead['writer_name']): ?><div><span class="text-gray-500">الكاتب:</span> <span class="font-medium"><?= h($lead['writer_name']) ?></span></div><?php endif; ?>
          <?php if ($lead['broker_name']): ?><div><span class="text-gray-500">الشركة الوسيطة:</span> <span class="font-medium"><?= h($lead['broker_name']) ?></span></div><?php endif; ?>
        </div>
        <!-- UTM & Tech -->
        <?php if ($lead['utm_source'] || $lead['ip_address']): ?>
        <div class="mt-4 pt-4 border-t border-gray-50 text-xs text-gray-400 space-y-1">
          <?php if ($lead['utm_source']): ?><div>UTM: <?= h($lead['utm_source']) ?> / <?= h($lead['utm_medium']) ?> / <?= h($lead['utm_campaign']) ?></div><?php endif; ?>
          <?php if ($lead['ip_address']): ?><div>IP: <?= h($lead['ip_address']) ?> | <?= h(mb_substr($lead['device_info'] ?? '', 0, 80)) ?></div><?php endif; ?>
          <?php if ($lead['referrer']): ?><div>Referrer: <?= h(mb_substr($lead['referrer'], 0, 100)) ?></div><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Pipeline Stage -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-bold text-gray-900 mb-4">تحديث المرحلة والدرجة</h2>
        <form method="POST" action="/api/lead-stage.php" class="flex flex-wrap gap-3">
          <input type="hidden" name="lead_id" value="<?= $id ?>">
          <input type="hidden" name="redirect" value="/admin/lead-detail.php?id=<?= $id ?>">
          <select name="stage" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]">
            <?php
            $stages = ['new_lead','attempted_contact','contacted','interested','viewing_scheduled','viewing_completed','negotiation','reservation','closed_won','closed_lost'];
            foreach ($stages as $s):
            ?><option value="<?= $s ?>" <?= $lead['pipeline_stage']===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <select name="score" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]">
            <option value="cold" <?= $lead['score']==='cold'?'selected':'' ?>>بارد</option>
            <option value="warm" <?= $lead['score']==='warm'?'selected':'' ?>>دافئ</option>
            <option value="hot" <?= $lead['score']==='hot'?'selected':'' ?>>ساخن</option>
            <option value="high_intent" <?= $lead['score']==='high_intent'?'selected':'' ?>>نية عالية</option>
          </select>
          <button type="submit" class="bg-[#F5C518] text-black font-medium px-4 py-2 rounded-xl text-sm hover:bg-yellow-400">تحديث</button>
        </form>
      </div>

      <!-- Interactions Timeline -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-bold text-gray-900 mb-4">سجل التفاعلات</h2>
        <div class="space-y-3 mb-6">
          <?php if (empty($interactions)): ?>
            <p class="text-gray-400 text-sm">لا توجد تفاعلات بعد</p>
          <?php else: foreach ($interactions as $inter): ?>
            <div class="flex gap-3">
              <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-medium text-gray-600"><?= mb_substr($inter['user_name'] ?? '?', 0, 1) ?></div>
              <div class="flex-1">
                <div class="bg-gray-50 rounded-xl p-3 text-sm"><?= h($inter['content']) ?></div>
                <div class="text-xs text-gray-400 mt-1"><?= h($inter['user_name'] ?? 'نظام') ?> • <?= date('d/m/Y H:i', strtotime($inter['created_at'])) ?> • <?= h($inter['type']) ?></div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
        <form method="POST" action="/api/lead-interact.php" class="flex gap-3">
          <input type="hidden" name="lead_id" value="<?= $id ?>">
          <input type="hidden" name="redirect" value="/admin/lead-detail.php?id=<?= $id ?>">
          <input type="hidden" name="type" value="note">
          <input type="text" name="content" required placeholder="أضف ملاحظة..." class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-[#F5C518]">
          <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-gray-700">إضافة</button>
        </form>
      </div>
    </div>

    <!-- Side Actions -->
    <div class="space-y-4">
      <!-- Assign to Broker -->
      <?php if ($lead['status'] !== 'closed_won'): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-bold text-gray-900 mb-3">إحالة إلى شركة وسيطة</h3>
        <form method="POST" action="/api/lead-assign.php" class="space-y-3">
          <input type="hidden" name="lead_id" value="<?= $id ?>">
          <input type="hidden" name="redirect" value="/admin/lead-detail.php?id=<?= $id ?>">
          <select name="broker_company_id" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#F5C518]">
            <option value="">اختر شركة وسيطة</option>
            <?php foreach ($brokerCompanies as $bc): ?>
              <option value="<?= $bc['id'] ?>" <?= $lead['broker_company_id']==$bc['id']?'selected':'' ?>><?= h($bc['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="w-full bg-[#F5C518] text-black font-bold py-2 rounded-xl text-sm hover:bg-yellow-400">إحالة</button>
        </form>
      </div>
      <?php endif; ?>

      <!-- Deal Info -->
      <?php if ($deal): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-bold text-gray-900 mb-3">معلومات الصفقة</h3>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between"><span class="text-gray-500">قيمة البيع</span><span class="font-bold"><?= formatMoney((float)$deal['sale_amount']) ?></span></div>
          <div class="flex justify-between"><span class="text-gray-500">صافي الربح</span><span class="font-medium"><?= formatMoney((float)$deal['net_profit']) ?></span></div>
          <div class="flex justify-between"><span class="text-gray-500">الحالة</span><?= statusBadge($deal['status']) ?></div>
        </div>
        <?php if ($deal['status'] === 'pending_review'): ?>
        <div class="mt-3 flex gap-2">
          <form method="POST" action="/api/commission-action.php">
            <input type="hidden" name="deal_id" value="<?= $deal['id'] ?>">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="redirect" value="/admin/lead-detail.php?id=<?= $id ?>">
            <button type="submit" class="bg-green-500 text-white text-xs px-3 py-1.5 rounded-lg hover:bg-green-600">قبول</button>
          </form>
          <form method="POST" action="/api/commission-action.php">
            <input type="hidden" name="deal_id" value="<?= $deal['id'] ?>">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="redirect" value="/admin/lead-detail.php?id=<?= $id ?>">
            <button type="submit" class="bg-red-500 text-white text-xs px-3 py-1.5 rounded-lg hover:bg-red-600">رفض</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Commission Info -->
      <?php if ($commission): ?>
      <div class="bg-[#F5C518] rounded-2xl p-5">
        <h3 class="font-bold text-gray-900 mb-3">عمولة الكاتب</h3>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between"><span class="text-gray-700">الكاتب</span><span class="font-medium"><?= h($commission['writer_name']) ?></span></div>
          <div class="flex justify-between"><span class="text-gray-700">المبلغ</span><span class="font-bold text-lg"><?= formatMoney((float)$commission['amount']) ?></span></div>
          <div class="flex justify-between"><span class="text-gray-700">الحالة</span><?= statusBadge($commission['status']) ?></div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>
</body></html>
