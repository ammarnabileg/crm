<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('/login.php', 'admin','super_admin');

try {
    $db = getDB();
    $users = $db->query("SELECT * FROM users WHERE is_deleted=0 ORDER BY created_at DESC")->fetchAll();
} catch (Exception $e) { $users = []; }

$success = flash('success');

// Handle role change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = intval($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $currentUser = currentUser();
    if ($uid && $uid !== $currentUser['id']) {
        try {
            $db = getDB();
            if ($action === 'activate') $db->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$uid]);
            elseif ($action === 'deactivate') $db->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$uid]);
            elseif ($action === 'role' && isset($_POST['role'])) {
                $role = $_POST['role'];
                $validRoles = ['writer','broker','account_manager','admin'];
                if (in_array($role, $validRoles)) $db->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role, $uid]);
            }
            auditLog('User', $uid, 'admin_update');
            flash('success', 'تم التحديث بنجاح');
        } catch (Exception $e) {}
    }
    header('Location: /admin/users.php'); exit;
}
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>المستخدمون - مربح</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Cairo',sans-serif}</style></head>
<body class="bg-gray-50">
<div class="flex">
<?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>
<div class="flex-1 p-6 overflow-auto">
  <h1 class="text-2xl font-bold text-gray-900 mb-6">إدارة المستخدمين</h1>
  <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4 text-sm"><?= h($success) ?></div><?php endif; ?>

  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr>
            <th class="text-right p-4 font-medium">الاسم</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">البريد</th>
            <th class="text-right p-4 font-medium">الدور</th>
            <th class="text-right p-4 font-medium hidden md:table-cell">الثقة</th>
            <th class="text-right p-4 font-medium">الحالة</th>
            <th class="text-right p-4 font-medium">إجراء</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($users)): ?>
            <tr><td colspan="6" class="text-center text-gray-400 py-8">لا توجد مستخدمون</td></tr>
          <?php else: foreach ($users as $u):
            $roleLabels = ['super_admin'=>'مدير عام','admin'=>'مدير','account_manager'=>'مدير حسابات','writer'=>'كاتب','broker'=>'وسيط'];
            $roleColors = ['super_admin'=>'bg-red-100 text-red-800','admin'=>'bg-purple-100 text-purple-800','account_manager'=>'bg-blue-100 text-blue-800','writer'=>'bg-green-100 text-green-800','broker'=>'bg-orange-100 text-orange-800'];
          ?>
            <tr class="hover:bg-gray-50">
              <td class="p-4">
                <div class="font-medium text-gray-900"><?= h($u['name']) ?></div>
                <?php if ($u['affiliate_code']): ?><div class="text-xs text-gray-400">كود: <?= h($u['affiliate_code']) ?></div><?php endif; ?>
              </td>
              <td class="p-4 text-gray-500 hidden md:table-cell text-xs"><?= h($u['email']) ?></td>
              <td class="p-4">
                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?= $roleColors[$u['role']] ?? 'bg-gray-100 text-gray-700' ?>"><?= $roleLabels[$u['role']] ?? h($u['role']) ?></span>
              </td>
              <td class="p-4 hidden md:table-cell">
                <div class="flex items-center gap-2">
                  <div class="w-16 bg-gray-200 rounded-full h-1.5">
                    <div class="bg-[#F5C518] h-1.5 rounded-full" style="width:<?= $u['trust_score'] ?>%"></div>
                  </div>
                  <span class="text-xs text-gray-500"><?= $u['trust_score'] ?>%</span>
                </div>
              </td>
              <td class="p-4"><?= $u['is_active'] ? '<span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">نشط</span>' : '<span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full">معطل</span>' ?></td>
              <td class="p-4">
                <?php if ($u['id'] !== currentUser()['id']): ?>
                <div class="flex items-center gap-2">
                  <form method="POST" class="flex gap-2 items-center">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="action" value="role">
                    <select name="role" onchange="this.form.submit()" class="text-xs border border-gray-200 rounded-lg px-2 py-1 focus:outline-none">
                      <?php foreach ($roleLabels as $rk => $rl): ?><option value="<?= $rk ?>" <?= $u['role']===$rk?'selected':'' ?>><?= $rl ?></option><?php endforeach; ?>
                    </select>
                  </form>
                  <form method="POST">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="action" value="<?= $u['is_active'] ? 'deactivate' : 'activate' ?>">
                    <button type="submit" class="text-xs <?= $u['is_active'] ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-green-50 text-green-600 hover:bg-green-100' ?> px-3 py-1 rounded-lg"><?= $u['is_active'] ? 'تعطيل' : 'تفعيل' ?></button>
                  </form>
                </div>
                <?php else: ?><span class="text-xs text-gray-400">أنت</span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>
</body></html>
