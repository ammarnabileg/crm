import { auth } from "@/lib/auth";
import { db } from "@/lib/db";
import Link from "next/link";
import Badge from "@/components/ui/Badge";
import Card from "@/components/ui/Card";
import { LEAD_STATUS_LABELS, PIPELINE_STAGE_LABELS } from "@/types";
import { Phone, Eye } from "lucide-react";

const statusVariant: Record<string, "success" | "warning" | "danger" | "info" | "gray"> = {
  CLOSED_WON: "success",
  IN_PROGRESS: "info",
  ASSIGNED: "warning",
  NEW: "gray",
  CLOSED_LOST: "danger",
  DUPLICATE: "gray",
};

export default async function BrokerLeadsPage() {
  const session = await auth();
  if (!session?.user) return null;

  const user = await db.user.findUnique({
    where: { id: session.user.id },
    include: { managedCompany: { select: { id: true } } },
  });

  const companyId = user?.managedCompany?.id;

  const leads = companyId
    ? await db.lead.findMany({
        where: { brokerCompanyId: companyId, isDeleted: false },
        orderBy: { createdAt: "desc" },
        include: {
          city: { select: { nameAr: true } },
          project: { select: { name: true } },
          article: { select: { title: true } },
          writer: { select: { name: true } },
        },
      })
    : [];

  return (
    <div className="p-6 lg:p-8">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-black text-gray-900">العملاء المحالون</h1>
          <p className="text-gray-500 mt-1">قائمة العملاء المحالين لشركتك</p>
        </div>
        <div className="bg-primary-100 text-primary-800 font-bold px-4 py-2 rounded-xl text-sm">
          {leads.length} عميل
        </div>
      </div>

      {leads.length === 0 ? (
        <Card>
          <div className="text-center py-16">
            <Phone className="h-16 w-16 text-gray-200 mx-auto mb-4" />
            <h3 className="text-xl font-bold text-gray-900 mb-2">لا توجد عملاء محالون</h3>
            <p className="text-gray-500">ستظهر هنا قائمة العملاء المحالين من إدارة مربح</p>
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
                      {LEAD_STATUS_LABELS[lead.status as keyof typeof LEAD_STATUS_LABELS]}
                    </Badge>
                    <span className="text-xs text-gray-400">
                      {PIPELINE_STAGE_LABELS[lead.pipelineStage as keyof typeof PIPELINE_STAGE_LABELS]}
                    </span>
                  </div>
                  <h3 className="font-bold text-gray-900">{lead.name}</h3>
                  <div className="flex flex-wrap items-center gap-4 mt-1 text-sm text-gray-500">
                    <a href={`tel:${lead.phone}`} className="flex items-center gap-1 hover:text-gray-900">
                      <Phone className="h-3.5 w-3.5" />
                      {lead.phone}
                    </a>
                    {lead.city && <span>{lead.city.nameAr}</span>}
                    {lead.project && <span>{lead.project.name}</span>}
                  </div>
                  {lead.writer && (
                    <p className="text-xs text-gray-400 mt-1">كاتب: {lead.writer.name}</p>
                  )}
                </div>
                <div className="flex items-center gap-3">
                  <div className="text-left text-xs text-gray-400">
                    {new Date(lead.createdAt).toLocaleDateString("ar-EG")}
                  </div>
                  <Link
                    href={`/broker/leads/${lead.id}`}
                    className="flex items-center gap-1 text-sm text-primary-600 font-bold px-3 py-1.5 border border-primary-200 rounded-lg hover:bg-primary-50 transition-colors"
                  >
                    <Eye className="h-4 w-4" />
                    تفاصيل
                  </Link>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
