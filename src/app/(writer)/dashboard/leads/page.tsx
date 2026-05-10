import { auth } from "@/lib/auth";
import { db } from "@/lib/db";
import Card from "@/components/ui/Card";
import Badge from "@/components/ui/Badge";
import { LEAD_STATUS_LABELS, LEAD_SCORE_LABELS } from "@/types";
import { Users, Phone } from "lucide-react";

const scoreVariant: Record<string, "success" | "warning" | "danger" | "info" | "gray"> = {
  HIGH_INTENT: "success",
  HOT: "danger",
  WARM: "warning",
  COLD: "gray",
};

const statusVariant: Record<string, "success" | "warning" | "danger" | "info" | "gray"> = {
  CLOSED_WON: "success",
  IN_PROGRESS: "info",
  ASSIGNED: "warning",
  NEW: "gray",
  CLOSED_LOST: "danger",
  DUPLICATE: "gray",
};

export default async function WriterLeadsPage() {
  const session = await auth();
  if (!session?.user) return null;

  const leads = await db.lead.findMany({
    where: { writerId: session.user.id, isDeleted: false },
    orderBy: { createdAt: "desc" },
    include: {
      article: { select: { title: true } },
      city: { select: { nameAr: true } },
      project: { select: { name: true } },
      commission: { select: { amount: true, status: true } },
    },
  });

  const stats = {
    total: leads.length,
    won: leads.filter((l) => l.status === "CLOSED_WON").length,
    inProgress: leads.filter((l) => l.status === "IN_PROGRESS" || l.status === "ASSIGNED").length,
  };

  return (
    <div className="p-6 lg:p-8">
      <div className="mb-8">
        <h1 className="text-2xl font-black text-gray-900">متابعة العملاء</h1>
        <p className="text-gray-500 mt-1">تتبع العملاء الذين تواصلوا عبر مقالاتك</p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-3 gap-4 mb-8">
        <div className="bg-white rounded-2xl border border-gray-100 p-5 text-center">
          <p className="text-3xl font-black text-gray-900">{stats.total}</p>
          <p className="text-sm text-gray-500 mt-1">إجمالي العملاء</p>
        </div>
        <div className="bg-white rounded-2xl border border-gray-100 p-5 text-center">
          <p className="text-3xl font-black text-blue-600">{stats.inProgress}</p>
          <p className="text-sm text-gray-500 mt-1">قيد المعالجة</p>
        </div>
        <div className="bg-white rounded-2xl border border-gray-100 p-5 text-center">
          <p className="text-3xl font-black text-green-600">{stats.won}</p>
          <p className="text-sm text-gray-500 mt-1">صفقات مغلقة</p>
        </div>
      </div>

      {leads.length === 0 ? (
        <Card>
          <div className="text-center py-16">
            <Users className="h-16 w-16 text-gray-200 mx-auto mb-4" />
            <h3 className="text-xl font-bold text-gray-900 mb-2">لا توجد عملاء بعد</h3>
            <p className="text-gray-500">ستظهر هنا قائمة العملاء الذين يتواصلون عبر مقالاتك</p>
          </div>
        </Card>
      ) : (
        <div className="space-y-3">
          {leads.map((lead) => (
            <Card key={lead.id} className="hover:shadow-md transition-shadow">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div className="flex-1">
                  <div className="flex flex-wrap items-center gap-2 mb-2">
                    <Badge variant={statusVariant[lead.status] || "gray"}>
                      {LEAD_STATUS_LABELS[lead.status]}
                    </Badge>
                    <Badge variant={scoreVariant[lead.score] || "gray"}>
                      {LEAD_SCORE_LABELS[lead.score]}
                    </Badge>
                    {lead.commission && (
                      <Badge variant="success">
                        عمولة: {lead.commission.amount.toLocaleString("ar-EG")} ج.م
                      </Badge>
                    )}
                  </div>
                  <h3 className="font-bold text-gray-900">{lead.name}</h3>
                  <div className="flex items-center gap-4 mt-1 text-sm text-gray-500">
                    <a href={`tel:${lead.phone}`} className="flex items-center gap-1 hover:text-gray-900">
                      <Phone className="h-3.5 w-3.5" />
                      {lead.phone}
                    </a>
                    {lead.article && (
                      <span className="text-xs">عبر: {lead.article.title.slice(0, 30)}...</span>
                    )}
                  </div>
                </div>
                <div className="text-left text-xs text-gray-400">
                  <p>{new Date(lead.createdAt).toLocaleDateString("ar-EG")}</p>
                  {lead.city && <p>{lead.city.nameAr}</p>}
                  {lead.project && <p>{lead.project.name}</p>}
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
