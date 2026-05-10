import { auth } from "@/lib/auth";
import { db } from "@/lib/db";
import Card from "@/components/ui/Card";
import Badge from "@/components/ui/Badge";
import Link from "next/link";
import { Phone, TrendingUp, CheckCircle, Clock, ArrowLeft } from "lucide-react";
import { LEAD_STATUS_LABELS, PIPELINE_STAGE_LABELS } from "@/types";

const statusVariant: Record<string, "success" | "warning" | "danger" | "info" | "gray"> = {
  CLOSED_WON: "success",
  IN_PROGRESS: "info",
  ASSIGNED: "warning",
  NEW: "gray",
  CLOSED_LOST: "danger",
  DUPLICATE: "gray",
};

export default async function BrokerDashboardPage() {
  const session = await auth();
  if (!session?.user) return null;

  // Get the broker's company
  const user = await db.user.findUnique({
    where: { id: session.user.id },
    include: { managedCompany: true },
  });

  const companyId = user?.managedCompany?.id;

  const leads = companyId
    ? await db.lead.findMany({
        where: { brokerCompanyId: companyId, isDeleted: false },
        orderBy: { createdAt: "desc" },
        take: 10,
        include: {
          city: { select: { nameAr: true } },
          project: { select: { name: true } },
        },
      })
    : [];

  const stats = {
    total: leads.length,
    inProgress: leads.filter((l) => ["ASSIGNED", "IN_PROGRESS"].includes(l.status)).length,
    won: leads.filter((l) => l.status === "CLOSED_WON").length,
    pending: leads.filter((l) => l.pipelineStage === "NEW_LEAD").length,
  };

  return (
    <div className="p-6 lg:p-8">
      <div className="mb-8">
        <h1 className="text-2xl font-black text-gray-900">لوحة التحكم</h1>
        <p className="text-gray-500 mt-1">
          {user?.managedCompany?.nameAr || user?.managedCompany?.name || "شركتك"}
        </p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <Card className="text-center">
          <Phone className="h-7 w-7 text-blue-500 mx-auto mb-2" />
          <p className="text-2xl font-black text-gray-900">{stats.total}</p>
          <p className="text-xs text-gray-500 mt-1">إجمالي العملاء</p>
        </Card>
        <Card className="text-center">
          <Clock className="h-7 w-7 text-orange-500 mx-auto mb-2" />
          <p className="text-2xl font-black text-gray-900">{stats.pending}</p>
          <p className="text-xs text-gray-500 mt-1">جدد يحتاجون متابعة</p>
        </Card>
        <Card className="text-center">
          <TrendingUp className="h-7 w-7 text-yellow-500 mx-auto mb-2" />
          <p className="text-2xl font-black text-gray-900">{stats.inProgress}</p>
          <p className="text-xs text-gray-500 mt-1">قيد المعالجة</p>
        </Card>
        <Card className="text-center">
          <CheckCircle className="h-7 w-7 text-green-500 mx-auto mb-2" />
          <p className="text-2xl font-black text-gray-900">{stats.won}</p>
          <p className="text-xs text-gray-500 mt-1">صفقات ناجحة</p>
        </Card>
      </div>

      {/* Recent Leads */}
      <Card>
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-lg font-bold text-gray-900">آخر العملاء المحالين</h2>
          <Link href="/broker/leads" className="text-sm text-primary-600 font-bold flex items-center gap-1">
            عرض الكل <ArrowLeft className="h-4 w-4" />
          </Link>
        </div>

        {leads.length === 0 ? (
          <div className="text-center py-12 text-gray-400">
            <Phone className="h-12 w-12 mx-auto mb-3 opacity-30" />
            <p>لا توجد عملاء محالون بعد</p>
          </div>
        ) : (
          <div className="space-y-3">
            {leads.map((lead) => (
              <Link
                key={lead.id}
                href={`/broker/leads/${lead.id}`}
                className="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors"
              >
                <div>
                  <p className="font-bold text-gray-900">{lead.name}</p>
                  <div className="flex items-center gap-3 mt-1 text-xs text-gray-400">
                    <span>{lead.phone}</span>
                    {lead.city && <span>{lead.city.nameAr}</span>}
                    {lead.project && <span>{lead.project.name}</span>}
                  </div>
                </div>
                <div className="text-left">
                  <Badge variant={statusVariant[lead.status] || "gray"}>
                    {LEAD_STATUS_LABELS[lead.status as keyof typeof LEAD_STATUS_LABELS]}
                  </Badge>
                  <p className="text-xs text-gray-400 mt-1">
                    {new Date(lead.createdAt).toLocaleDateString("ar-EG")}
                  </p>
                </div>
              </Link>
            ))}
          </div>
        )}
      </Card>
    </div>
  );
}
