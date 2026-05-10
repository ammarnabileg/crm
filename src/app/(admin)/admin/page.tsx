import { auth } from "@/lib/auth";
import { db } from "@/lib/db";
import Card, { CardHeader, CardTitle } from "@/components/ui/Card";
import Badge from "@/components/ui/Badge";
import Link from "next/link";
import {
  FileText,
  Users,
  Phone,
  Building2,
  Banknote,
  TrendingUp,
  AlertCircle,
  ArrowLeft,
  Clock,
} from "lucide-react";
import { LEAD_STATUS_LABELS, ARTICLE_STATUS_LABELS } from "@/types";

async function getDashboardStats() {
  const [
    totalArticles,
    pendingArticles,
    totalLeads,
    newLeads,
    totalUsers,
    activeWriters,
    totalCommissions,
    pendingComplaints,
    brokerCompanies,
  ] = await Promise.all([
    db.article.count({ where: { isDeleted: false } }),
    db.article.count({ where: { status: "PENDING", isDeleted: false } }),
    db.lead.count({ where: { isDeleted: false } }),
    db.lead.count({ where: { status: "NEW", isDeleted: false } }),
    db.user.count({ where: { isDeleted: false, isActive: true } }),
    db.user.count({ where: { role: "WRITER", isDeleted: false, isActive: true } }),
    db.commission.aggregate({ where: { isDeleted: false }, _sum: { amount: true } }),
    db.complaint.count({ where: { status: { in: ["OPEN", "IN_REVIEW"] }, isDeleted: false } }),
    db.brokerCompany.count({ where: { isDeleted: false, isActive: true } }),
  ]);

  return {
    totalArticles,
    pendingArticles,
    totalLeads,
    newLeads,
    totalUsers,
    activeWriters,
    totalCommissions: totalCommissions._sum.amount || 0,
    pendingComplaints,
    brokerCompanies,
  };
}

async function getRecentLeads() {
  return db.lead.findMany({
    where: { isDeleted: false },
    orderBy: { createdAt: "desc" },
    take: 8,
    include: {
      writer: { select: { name: true } },
      city: { select: { nameAr: true } },
      project: { select: { name: true } },
    },
  });
}

async function getPendingArticles() {
  return db.article.findMany({
    where: { status: "PENDING", isDeleted: false },
    orderBy: { createdAt: "desc" },
    take: 5,
    include: {
      author: { select: { name: true } },
    },
  });
}

const leadStatusVariant: Record<string, "success" | "warning" | "danger" | "info" | "gray"> = {
  CLOSED_WON: "success",
  IN_PROGRESS: "info",
  ASSIGNED: "warning",
  NEW: "primary" as "success",
  CLOSED_LOST: "danger",
  DUPLICATE: "gray",
};

export default async function AdminDashboardPage() {
  const [stats, leads, articles] = await Promise.all([
    getDashboardStats(),
    getRecentLeads(),
    getPendingArticles(),
  ]);

  return (
    <div className="p-6 lg:p-8">
      <div className="mb-8">
        <h1 className="text-2xl font-black text-gray-900">لوحة تحكم الإدارة</h1>
        <p className="text-gray-500 mt-1">نظرة شاملة على منصة مربح</p>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <Card className="text-center">
          <Phone className="h-8 w-8 text-blue-500 mx-auto mb-2" />
          <p className="text-3xl font-black text-gray-900">{stats.totalLeads}</p>
          <p className="text-sm text-gray-500 mt-1">إجمالي العملاء</p>
          {stats.newLeads > 0 && (
            <Badge variant="info" className="mt-2">{stats.newLeads} جديد</Badge>
          )}
        </Card>

        <Card className="text-center">
          <FileText className="h-8 w-8 text-orange-500 mx-auto mb-2" />
          <p className="text-3xl font-black text-gray-900">{stats.totalArticles}</p>
          <p className="text-sm text-gray-500 mt-1">إجمالي المقالات</p>
          {stats.pendingArticles > 0 && (
            <Badge variant="warning" className="mt-2">{stats.pendingArticles} بانتظار المراجعة</Badge>
          )}
        </Card>

        <Card className="text-center">
          <Users className="h-8 w-8 text-purple-500 mx-auto mb-2" />
          <p className="text-3xl font-black text-gray-900">{stats.totalUsers}</p>
          <p className="text-sm text-gray-500 mt-1">المستخدمون</p>
          <p className="text-xs text-gray-400 mt-1">{stats.activeWriters} كاتب نشط</p>
        </Card>

        <Card className="text-center">
          <Banknote className="h-8 w-8 text-green-500 mx-auto mb-2" />
          <p className="text-3xl font-black text-gray-900">
            {stats.totalCommissions.toLocaleString("ar-EG")}
          </p>
          <p className="text-sm text-gray-500 mt-1">إجمالي العمولات (ج.م)</p>
        </Card>
      </div>

      {/* Quick Stats Row */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        {[
          { label: "شركات الوساطة", value: stats.brokerCompanies, icon: Building2, color: "text-teal-500", href: "/admin/companies" },
          { label: "شكاوي مفتوحة", value: stats.pendingComplaints, icon: AlertCircle, color: "text-red-500", href: "/admin/complaints" },
        ].map((item) => (
          <Link key={item.label} href={item.href}>
            <div className="bg-white rounded-2xl border border-gray-100 p-5 hover:shadow-md transition-shadow">
              <div className="flex items-center gap-3">
                <item.icon className={`h-8 w-8 ${item.color}`} />
                <div>
                  <p className="text-2xl font-black text-gray-900">{item.value}</p>
                  <p className="text-xs text-gray-500">{item.label}</p>
                </div>
              </div>
            </div>
          </Link>
        ))}

        {/* Alert Cards */}
        {stats.newLeads > 0 && (
          <Link href="/admin/leads">
            <div className="bg-blue-50 border border-blue-200 rounded-2xl p-5 hover:shadow-md transition-shadow">
              <div className="flex items-center gap-3">
                <Clock className="h-8 w-8 text-blue-500" />
                <div>
                  <p className="text-2xl font-black text-blue-700">{stats.newLeads}</p>
                  <p className="text-xs text-blue-600">عميل جديد يحتاج إحالة</p>
                </div>
              </div>
            </div>
          </Link>
        )}

        {stats.pendingArticles > 0 && (
          <Link href="/admin/articles">
            <div className="bg-orange-50 border border-orange-200 rounded-2xl p-5 hover:shadow-md transition-shadow">
              <div className="flex items-center gap-3">
                <FileText className="h-8 w-8 text-orange-500" />
                <div>
                  <p className="text-2xl font-black text-orange-700">{stats.pendingArticles}</p>
                  <p className="text-xs text-orange-600">مقالة تنتظر المراجعة</p>
                </div>
              </div>
            </div>
          </Link>
        )}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Recent Leads */}
        <Card>
          <CardHeader>
            <CardTitle>آخر العملاء</CardTitle>
            <Link href="/admin/leads" className="text-sm text-primary-600 font-bold flex items-center gap-1">
              عرض الكل <ArrowLeft className="h-4 w-4" />
            </Link>
          </CardHeader>
          <div className="space-y-2">
            {leads.map((lead) => (
              <Link
                key={lead.id}
                href={`/admin/leads/${lead.id}`}
                className="flex items-center justify-between p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors"
              >
                <div>
                  <p className="font-medium text-gray-900 text-sm">{lead.name}</p>
                  <p className="text-xs text-gray-400">
                    {lead.phone} • {lead.writer?.name || "مباشر"}
                  </p>
                </div>
                <div className="text-left">
                  <Badge variant={leadStatusVariant[lead.status] || "gray"}>
                    {LEAD_STATUS_LABELS[lead.status]}
                  </Badge>
                  <p className="text-xs text-gray-400 mt-1">
                    {new Date(lead.createdAt).toLocaleDateString("ar-EG")}
                  </p>
                </div>
              </Link>
            ))}
          </div>
        </Card>

        {/* Pending Articles */}
        <Card>
          <CardHeader>
            <CardTitle>مقالات تنتظر المراجعة</CardTitle>
            <Link href="/admin/articles" className="text-sm text-primary-600 font-bold flex items-center gap-1">
              مراجعة الكل <ArrowLeft className="h-4 w-4" />
            </Link>
          </CardHeader>
          <div className="space-y-2">
            {articles.length === 0 ? (
              <div className="text-center py-8 text-gray-400 text-sm">
                لا توجد مقالات تنتظر المراجعة
              </div>
            ) : (
              articles.map((article) => (
                <Link
                  key={article.id}
                  href={`/admin/articles`}
                  className="flex items-center justify-between p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors"
                >
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-gray-900 text-sm truncate">
                      {article.title}
                    </p>
                    <p className="text-xs text-gray-400">{article.author.name}</p>
                  </div>
                  <Badge variant="warning">مراجعة</Badge>
                </Link>
              ))
            )}
          </div>
        </Card>
      </div>
    </div>
  );
}
