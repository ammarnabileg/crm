<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) { header('Location: /'); exit; }

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$name || !$email || !$password) {
        $error = 'جميع الحقول المطلوبة يجب تعبئتها';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'البريد الإلكتروني غير صحيح';
    } elseif (strlen($password) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } elseif ($password !== $confirm) {
        $error = 'كلمات المرور غير متطابقة';
    } else {
        try {
            $db = getDB();
            $check = $db->prepare("SELECT id FROM users WHERE email=?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = 'هذا البريد الإلكتروني مسجل بالفعل';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $code = generateAffiliateCode();
                $stmt = $db->prepare("INSERT INTO users (name, email, phone, password, role, affiliate_code) VALUES (?,?,?,?,'writer',?)");
                $stmt->execute([$name, $email, $phone, $hash, $code]);
                $userId = $db->lastInsertId();
                auditLog('User', $userId, 'register');
                $success = 'تم التسجيل بنجاح! يمكنك الآن تسجيل الدخول.';
            }
        } catch (Exception $e) {
            $error = 'حدث خطأ، حاول مرة أخرى';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>انضم كاتب - مربح</title>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-md">
    <div class="text-center mb-8">
      <a href="/" class="text-3xl font-black text-[#F5C518]">مربح</a>
      <p class="text-gray-500 mt-1 text-sm">انضم كاتب محتوى واكسب عمولات</p>
    </div>
    <div class="bg-white rounded-2xl shadow-lg p-8">
      <h1 class="text-xl font-bold text-gray-900 mb-2">إنشاء حساب كاتب</h1>
      <p class="text-sm text-gray-500 mb-6">اكسب 20% عمولة على كل صفقة تُغلق من خلال مقالاتك</p>
      <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-4 text-sm"><?= h($success) ?></div>
        <a href="/login.php" class="block text-center bg-[#F5C518] text-black font-bold py-3 rounded-xl hover:bg-yellow-400">تسجيل الدخول الآن</a>
      <?php else: ?>
        <?php if ($error): ?>
          <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 text-sm"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل *</label>
            <input type="text" name="name" value="<?= h($_POST['name'] ?? '') ?>" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#F5C518]">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني *</label>
            <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#F5C518]">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
            <input type="tel" name="phone" value="<?= h($_POST['phone'] ?? '') ?>" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#F5C518]">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور *</label>
            <input type="password" name="password" required minlength="6" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#F5C518]">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">تأكيد كلمة المرور *</label>
            <input type="password" name="confirm_password" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#F5C518]">
          </div>
          <button type="submit" class="w-full bg-[#F5C518] text-black font-bold py-3 rounded-xl hover:bg-yellow-400">إنشاء الحساب</button>
        </form>
      <?php endif; ?>
      <div class="mt-4 text-center text-sm text-gray-500">
        لديك حساب؟ <a href="/login.php" class="text-[#F5C518] font-semibold hover:underline">تسجيل الدخول</a>
      </div>
    </div>
  </div>
</body>
</html>
