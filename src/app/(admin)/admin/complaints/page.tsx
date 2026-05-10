import { auth } from "@/lib/auth";
import { db } from "@/lib/db";
import Badge from "@/components/ui/Badge";
import Card from "@/components/ui/Card";
import { COMPLAINT_TYPE_LABELS, PRIORITY_LABELS } from "@/types";
import { AlertCircle } from "lucide-react";

const statusVariant: Record<string, "success" | "warning" | "danger" | "info" | "gray"> = {
  RESOLVED: "success",
  IN_REVIEW: "warning",
  OPEN: "info",
  CLOSED: "gray",
};

const priorityVariant: Record<string, "success" | "warning" | "danger" | "info" | "gray"> = {
  URGENT: "danger",
  HIGH: "warning",
  MEDIUM: "info",
  LOW: "gray",
};

const statusLabels: Record<string, string> = {
  OPEN: "مفتوحة",
  IN_REVIEW: "قيد المراجعة",
  RESOLVED: "تم الحل",
  CLOSED: "مغلقة",
};

export default async function AdminComplaintsPage() {
  const complaints = await db.complaint.findMany({
    where: { isDeleted: false },
    orderBy: [{ priority: "desc" }, { createdAt: "desc" }],
    include: {
      submittedBy: { select: { name: true, email: true, role: true } },
      lead: { select: { name: true, phone: true } },
    },
  });

  return (
    <div className="p-6 lg:p-8">
      <div className="mb-8">
        <h1 className="text-2xl font-black text-gray-900">إدارة الشكاوي</h1>
        <p className="text-gray-500 mt-1">مراجعة وحل شكاوي المستخدمين</p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        {[
          { label: "مفتوحة", count: complaints.filter((c) => c.status === "OPEN").length, variant: "info" },
          { label: "قيد المراجعة", count: complaints.filter((c) => c.status === "IN_REVIEW").length, variant: "warning" },
          { label: "تم الحل", count: complaints.filter((c) => c.status === "RESOLVED").length, variant: "success" },
          { label: "مغلقة", count: complaints.filter((c) => c.status === "CLOSED").length, variant: "gray" },
        ].map((stat) => (
          <div key={stat.label} className="bg-white rounded-2xl border border-gray-100 p-4 text-center">
            <p className="text-2xl font-black text-gray-900">{stat.count}</p>
            <p className="text-sm text-gray-500 mt-1">{stat.label}</p>
          </div>
        ))}
      </div>

      {complaints.length === 0 ? (
        <Card>
          <div className="text-center py-16">
            <AlertCircle className="h-16 w-16 text-gray-200 mx-auto mb-4" />
            <p className="text-gray-500">لا توجد شكاوي</p>
          </div>
        </Card>
      ) : (
        <div className="space-y-3">
          {complaints.map((complaint) => (
            <Card key={complaint.id} className="hover:shadow-md transition-shadow">
              <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                <div className="flex-1">
                  <div className="flex flex-wrap items-center gap-2 mb-2">
                    <Badge variant={statusVariant[complaint.status] || "gray"}>
                      {statusLabels[complaint.status]}
                    </Badge>
                    <Badge variant={priorityVariant[complaint.priority] || "gray"}>
                      {PRIORITY_LABELS[complaint.priority]}
                    </Badge>
                    <span className="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                      {COMPLAINT_TYPE_LABELS[complaint.type]}
                    </span>
                  </div>
                  <h3 className="font-bold text-gray-900">{complaint.title}</h3>
                  <p className="text-sm text-gray-500 mt-1 line-clamp-2">{complaint.description}</p>
                  <div className="flex items-center gap-4 mt-2 text-xs text-gray-400">
                    <span>مقدم من: {complaint.submittedBy.name}</span>
                    {complaint.lead && (
                      <span>عميل: {complaint.lead.name}</span>
                    )}
                    <span>{new Date(complaint.createdAt).toLocaleDateString("ar-EG")}</span>
                  </div>
                  {complaint.resolution && (
                    <div className="mt-2 bg-green-50 border border-green-100 rounded-lg p-3">
                      <p className="text-sm text-green-700">
                        <span className="font-bold">الحل: </span>
                        {complaint.resolution}
                      </p>
                    </div>
                  )}
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
