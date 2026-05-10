<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    $u = currentUser();
    if (hasRole('admin','super_admin','account_manager')) header('Location: /admin/');
    elseif (hasRole('writer')) header('Location: /writer/');
    elseif (hasRole('broker')) header('Location: /broker/');
    else header('Location: /');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email && $password) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email=? AND is_active=1 AND is_deleted=0");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password'])) {
                login($user);
                auditLog('User', $user['id'], 'login');
                if (in_array($user['role'], ['admin','super_admin','account_manager'])) header('Location: /admin/');
                elseif ($user['role'] === 'writer') header('Location: /writer/');
                elseif ($user['role'] === 'broker') header('Location: /broker/');
                else header('Location: /');
                exit;
            } else {
                $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
            }
        } catch (Exception $e) {
            $error = 'حدث خطأ في الاتصال بقاعدة البيانات';
        }
    } else {
        $error = 'يرجى إدخال البريد الإلكتروني وكلمة المرور';
    }
}
$pageTitle = 'تسجيل الدخول';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($pageTitle) ?> - مربح</title>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-md">
    <div class="text-center mb-8">
      <a href="/" class="text-3xl font-black text-[#F5C518]">مربح</a>
      <p class="text-gray-500 mt-1 text-sm">منصة عقارية رائدة</p>
    </div>
    <div class="bg-white rounded-2xl shadow-lg p-8">
      <h1 class="text-xl font-bold text-gray-900 mb-6">تسجيل الدخول</h1>
      <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 text-sm"><?= h($error) ?></div>
      <?php endif; ?>
      <form method="POST" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
          <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#F5C518] focus:ring-1 focus:ring-[#F5C518]" placeholder="admin@morbeh.com">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور</label>
          <input type="password" name="password" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#F5C518] focus:ring-1 focus:ring-[#F5C518]" placeholder="••••••••">
        </div>
        <button type="submit" class="w-full bg-[#F5C518] text-black font-bold py-3 rounded-xl hover:bg-yellow-400 transition-colors">دخول</button>
      </form>
      <div class="mt-4 text-center text-sm text-gray-500">
        ليس لديك حساب؟ <a href="/register.php" class="text-[#F5C518] font-semibold hover:underline">انضم الآن</a>
      </div>
    </div>
    <p class="text-center text-xs text-gray-400 mt-4">
      للتجربة: admin@morbeh.com / admin123
    </p>
  </div>
</body>
</html>
