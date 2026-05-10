<?php
require_once __DIR__ . '/../config/database.php';

function slugify(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[\s\-]+/', '-', $text);
    $text = preg_replace('/[^\p{L}\p{N}\-]/u', '', $text);
    return trim($text, '-') ?: uniqid();
}

function generateAffiliateCode(): string {
    return strtoupper(substr(md5(uniqid()), 0, 8));
}

function auditLog(string $entity, int $entityId, string $action, ?array $changes = null): void {
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $db->prepare("INSERT INTO audit_logs (entity, entity_id, action, changes, user_id, ip_address) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$entity, $entityId, $action, $changes ? json_encode($changes) : null, $userId, $ip]);
    } catch (Exception $e) {}
}

function getSetting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            $cache[$key] = $row ? $row['value'] : $default;
        } catch (Exception $e) {
            $cache[$key] = $default;
        }
    }
    return $cache[$key];
}

function formatMoney(float $amount): string {
    return number_format($amount, 0, '.', ',') . ' ج.م';
}

function timeAgo(string $datetime): string {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'الآن';
    if ($time < 3600) return floor($time/60) . ' دقيقة';
    if ($time < 86400) return floor($time/3600) . ' ساعة';
    return floor($time/86400) . ' يوم';
}

function statusBadge(string $status): string {
    $map = [
        'pending'              => ['bg-yellow-100 text-yellow-800', 'قيد المراجعة'],
        'approved'             => ['bg-green-100 text-green-800', 'مقبول'],
        'rejected'             => ['bg-red-100 text-red-800', 'مرفوض'],
        'needs_edit'           => ['bg-orange-100 text-orange-800', 'يحتاج تعديل'],
        'draft'                => ['bg-gray-100 text-gray-800', 'مسودة'],
        'new'                  => ['bg-blue-100 text-blue-800', 'جديد'],
        'assigned'             => ['bg-purple-100 text-purple-800', 'محال'],
        'in_progress'          => ['bg-indigo-100 text-indigo-800', 'جاري'],
        'closed_won'           => ['bg-green-100 text-green-800', 'تم البيع'],
        'closed_lost'          => ['bg-red-100 text-red-800', 'خسارة'],
        'duplicate'            => ['bg-gray-100 text-gray-800', 'مكرر'],
        'cold'                 => ['bg-blue-100 text-blue-700', 'بارد'],
        'warm'                 => ['bg-orange-100 text-orange-700', 'دافئ'],
        'hot'                  => ['bg-red-100 text-red-700', 'ساخن'],
        'high_intent'          => ['bg-green-100 text-green-700', 'نية عالية'],
        'pending_review'       => ['bg-yellow-100 text-yellow-800', 'في انتظار المراجعة'],
        'under_review'         => ['bg-blue-100 text-blue-800', 'تحت المراجعة'],
        'payable'              => ['bg-teal-100 text-teal-800', 'جاهز للدفع'],
        'paid'                 => ['bg-green-100 text-green-800', 'مدفوع'],
        'open'                 => ['bg-blue-100 text-blue-800', 'مفتوح'],
        'in_review'            => ['bg-yellow-100 text-yellow-800', 'تحت المراجعة'],
        'resolved'             => ['bg-green-100 text-green-800', 'محلول'],
        'closed'               => ['bg-gray-100 text-gray-800', 'مغلق'],
        'low'                  => ['bg-gray-100 text-gray-700', 'منخفض'],
        'medium'               => ['bg-yellow-100 text-yellow-700', 'متوسط'],
        'high'                 => ['bg-orange-100 text-orange-700', 'عالي'],
        'urgent'               => ['bg-red-100 text-red-700', 'عاجل'],
        'new_lead'             => ['bg-blue-100 text-blue-800', 'عميل جديد'],
        'attempted_contact'    => ['bg-yellow-100 text-yellow-800', 'محاولة تواصل'],
        'contacted'            => ['bg-indigo-100 text-indigo-800', 'تم التواصل'],
        'interested'           => ['bg-purple-100 text-purple-800', 'مهتم'],
        'viewing_scheduled'    => ['bg-orange-100 text-orange-800', 'معاينة مجدولة'],
        'viewing_completed'    => ['bg-teal-100 text-teal-800', 'اكتملت المعاينة'],
        'negotiation'          => ['bg-pink-100 text-pink-800', 'تفاوض'],
        'reservation'          => ['bg-green-100 text-green-700', 'حجز'],
        'processing'           => ['bg-blue-100 text-blue-800', 'جاري المعالجة'],
        'completed'            => ['bg-green-100 text-green-800', 'مكتمل'],
        'frozen'               => ['bg-gray-100 text-gray-800', 'مجمد'],
    ];
    $d = $map[$status] ?? ['bg-gray-100 text-gray-700', $status];
    return "<span class='inline-flex px-2 py-1 text-xs font-medium rounded-full {$d[0]}'>{$d[1]}</span>";
}

function flash(string $key, ?string $message = null): ?string {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
