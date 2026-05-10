import { auth } from "@/lib/auth";
import { db } from "@/lib/db";
import Card, { CardHeader, CardTitle } from "@/components/ui/Card";
import Badge from "@/components/ui/Badge";
import { COMMISSION_STATUS_LABELS } from "@/types";
import { Banknote, TrendingUp, Clock, CheckCircle } from "lucide-react";

export default async function WriterWalletPage() {
  const session = await auth();
  if (!session?.user) return null;

  const commissions = await db.commission.findMany({
    where: { writerId: session.user.id, isDeleted: false },
    orderBy: { createdAt: "desc" },
    include: {
      lead: { select: { name: true, phone: true } },
      deal: { select: { saleAmount: true, netProfit: true } },
      payout: { select: { status: true, processedAt: true } },
    },
  });

  const stats = {
    total: commissions.reduce((sum, c) => sum + c.amount, 0),
    payable: commissions.filter((c) => c.status === "PAYABLE").reduce((sum, c) => sum + c.amount, 0),
    paid: commissions.filter((c) => c.status === "PAID").reduce((sum, c) => sum + c.amount, 0),
    pending: commissions.filter((c) => ["PENDING", "UNDER_REVIEW", "APPROVED"].includes(c.status)).reduce((sum, c) => sum + c.amount, 0),
  };

  const statusVariant: Record<string, "success" | "warning" | "danger" | "info" | "gray"> = {
    PAID: "success",
    PAYABLE: "info",
    APPROVED: "success",
    UNDER_REVIEW: "warning",
    PENDING: "gray",
    REJECTED: "danger",
  };

  return (
    <div className="p-6 lg:p-8">
      <div className="mb-8">
        <h1 className="text-2xl font-black text-gray-900">المحفظة والعمولات</h1>
        <p className="text-gray-500 mt-1">إدارة عمولاتك وطلبات السحب</p>
      </div>

      {/* Balance Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div className="bg-gradient-to-br from-yellow-400 to-yellow-500 rounded-2xl p-5 text-gray-900">
          <Banknote className="h-7 w-7 mb-3" />
          <p className="text-3xl font-black">{stats.payable.toLocaleString("ar-EG")}</p>
          <p className="text-sm font-bold mt-1">الرصيد المتاح (ج.م)</p>
        </div>
        <div className="bg-white rounded-2xl border border-gray-100 p-5 text-center">
          <Clock className="h-7 w-7 text-blue-500 mx-auto mb-2" />
          <p className="text-2xl font-black text-gray-900">{stats.pending.toLocaleString("ar-EG")}</p>
          <p className="text-xs text-gray-500 mt-1">معلق (ج.م)</p>
        </div>
        <div className="bg-white rounded-2xl border border-gray-100 p-5 text-center">
          <CheckCircle className="h-7 w-7 text-green-500 mx-auto mb-2" />
          <p className="text-2xl font-black text-gray-900">{stats.paid.toLocaleString("ar-EG")}</p>
          <p className="text-xs text-gray-500 mt-1">مسحوب (ج.م)</p>
        </div>
        <div className="bg-white rounded-2xl border border-gray-100 p-5 text-center">
          <TrendingUp className="h-7 w-7 text-purple-500 mx-auto mb-2" />
          <p className="text-2xl font-black text-gray-900">{stats.total.toLocaleString("ar-EG")}</p>
          <p className="text-xs text-gray-500 mt-1">إجمالي العمولات (ج.م)</p>
        </div>
      </div>

      {/* Withdrawal Request */}
      {stats.payable > 0 && (
        <div className="bg-green-50 border border-green-200 rounded-2xl p-6 mb-8">
          <h3 className="font-black text-gray-900 mb-2">
            لديك {stats.payable.toLocaleString("ar-EG")} ج.م متاحة للسحب
          </h3>
          <p className="text-gray-600 text-sm mb-4">
            لطلب سحب رصيدك، يرجى التواصل مع الإدارة أو إرسال طلب عبر الشكاوي.
          </p>
          <button className="bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-2.5 rounded-xl transition-colors text-sm">
            طلب سحب الرصيد
          </button>
        </div>
      )}

      {/* Commission History */}
      <Card>
        <CardHeader>
          <CardTitle>سجل العمولات</CardTitle>
        </CardHeader>
        {commissions.length === 0 ? (
          <div className="text-center py-12 text-gray-400">
            <Banknote className="h-12 w-12 mx-auto mb-3 opacity-30" />
            <p>لا توجد عمولات بعد</p>
            <p className="text-xs mt-1">ستظهر هنا عمولاتك عند إغلاق الصفقات</p>
          </div>
        ) : (
          <div className="space-y-3">
            {commissions.map((commission) => (
              <div
                key={commission.id}
                className="flex items-center justify-between p-4 bg-gray-50 rounded-xl"
              >
                <div>
                  <p className="font-bold text-gray-900">{commission.lead.name}</p>
                  <p className="text-xs text-gray-400 mt-0.5">
                    مبيعات: {commission.deal.saleAmount.toLocaleString("ar-EG")} ج.م •
                    ربح: {commission.deal.netProfit.toLocaleString("ar-EG")} ج.م
                  </p>
                  <p className="text-xs text-gray-400">
                    {new Date(commission.createdAt).toLocaleDateString("ar-EG")}
                  </p>
                </div>
                <div className="text-left">
                  <p className="text-lg font-black text-primary-700">
                    {commission.amount.toLocaleString("ar-EG")} ج.م
                  </p>
                  <Badge variant={statusVariant[commission.status] || "gray"}>
                    {COMMISSION_STATUS_LABELS[commission.status]}
                  </Badge>
                </div>
              </div>
            ))}
          </div>
        )}
      </Card>
    </div>
  );
}
