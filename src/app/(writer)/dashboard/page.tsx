import { auth } from "@/lib/auth";
import { db } from "@/lib/db";
import Card, { CardHeader, CardTitle } from "@/components/ui/Card";
import Badge from "@/components/ui/Badge";
import Link from "next/link";
import {
  FileText,
  Users,
  Banknote,
  TrendingUp,
  Plus,
  ArrowLeft,
  CheckCircle,
  Clock,
  XCircle,
} from "lucide-react";
import { ARTICLE_STATUS_LABELS } from "@/types";

async function getWriterStats(writerId: string) {
  const [articles, leads, commissions, payableCommissions] = await Promise.all([
    db.article.groupBy({
      by: ["status"],
      where: { authorId: writerId, isDeleted: false },
      _count: true,
    }),
    db.lead.count({
      where: { writerId, isDeleted: false },
    }),
    db.commission.aggregate({
      where: { writerId, isDeleted: false },
      _sum: { amount: true },
      _count: true,
    }),
    db.commission.aggregate({
      where: { writerId, status: "PAYABLE", isDeleted: false },
      _sum: { amount: true },
    }),
  ]);

  const articleStats = {
    total: articles.reduce((sum, a) => sum + a._count, 0),
    approved: articles.find((a) => a.status === "APPROVED")?._count || 0,
    pending: articles.find((a) => a.status === "PENDING")?._count || 0,
    rejected: articles.find((a) => a.status === "REJECTED")?._count || 0,
  };

  return {
    ...articleStats,
    totalLeads: leads,
    totalCommissions: commissions._sum.amount || 0,
    commissionCount: commissions._count,
    availableBalance: payableCommissions._sum.amount || 0,
  };
}

async function getRecentArticles(writerId: string) {
  return db.article.findMany({
    where: { authorId: writerId, isDeleted: false },
    orderBy: { createdAt: "desc" },
    take: 5,
    include: { city: { select: { nameAr: true } } },
  });
}

async function getRecentLeads(writerId: string) {
  return db.lead.findMany({
    where: { writerId, isDeleted: false },
    orderBy: { createdAt: "desc" },
    take: 5,
    include: {
      article: { select: { title: true } },
      city: { select: { nameAr: true } },
    },
  });
}

const statusBadgeVariant: Record<string, "success" | "warning" | "danger" | "info" | "gray"> = {
  APPROVED: "success",
  PENDING: "warning",
  REJECTED: "danger",
  DRAFT: "gray",
  NEEDS_EDIT: "info",
};

export default async function WriterDashboardPage() {
  const session = await auth();
  if (!session?.user) return null;

  const [stats, articles, leads] = await Promise.all([
    getWriterStats(session.user.id),
    getRecentArticles(session.user.id),
    getRecentLeads(session.user.id),
  ]);

  return (
    <div className="p-6 lg:p-8">
      <div className="mb-8">
        <h1 className="text-2xl font-black text-gray-900">
          مرحباً {session.user.name} 👋
        </h1>
        <p className="text-gray-500 mt-1">هنا نظرة عامة على أدائك في منصة مربح</p>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <Card className="text-center">
          <FileText className="h-8 w-8 text-primary-500 mx-auto mb-3" />
          <p className="text-3xl font-black text-gray-900">{stats.total}</p>
          <p className="text-sm text-gray-500 mt-1">إجمالي المقالات</p>
          <div className="flex justify-center gap-3 mt-3">
            <span className="text-xs text-green-600 font-bold">{stats.approved} موافق</span>
            <span className="text-xs text-yellow-600 font-bold">{stats.pending} انتظار</span>
          </div>
        </Card>

        <Card className="text-center">
          <Users className="h-8 w-8 text-blue-500 mx-auto mb-3" />
          <p className="text-3xl font-black text-gray-900">{stats.totalLeads}</p>
          <p className="text-sm text-gray-500 mt-1">إجمالي العملاء</p>
        </Card>

        <Card className="text-center">
          <Banknote className="h-8 w-8 text-green-500 mx-auto mb-3" />
          <p className="text-3xl font-black text-gray-900">
            {stats.availableBalance.toLocaleString("ar-EG")}
          </p>
          <p className="text-sm text-gray-500 mt-1">الرصيد المتاح (ج.م)</p>
        </Card>

        <Card className="text-center">
          <TrendingUp className="h-8 w-8 text-purple-500 mx-auto mb-3" />
          <p className="text-3xl font-black text-gray-900">
            {stats.totalCommissions.toLocaleString("ar-EG")}
          </p>
          <p className="text-sm text-gray-500 mt-1">إجمالي العمولات (ج.م)</p>
        </Card>
      </div>

      {/* Quick Actions */}
      <div className="flex flex-wrap gap-3 mb-8">
        <Link
          href="/dashboard/articles/new"
          className="flex items-center gap-2 bg-primary-500 hover:bg-primary-600 text-gray-900 font-bold px-5 py-2.5 rounded-xl transition-all"
        >
          <Plus className="h-5 w-5" />
          إضافة مقالة جديدة
        </Link>
        <Link
          href="/dashboard/wallet"
          className="flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white font-bold px-5 py-2.5 rounded-xl transition-all"
        >
          <Banknote className="h-5 w-5" />
          طلب سحب الرصيد
        </Link>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Recent Articles */}
        <Card>
          <CardHeader>
            <CardTitle>آخر المقالات</CardTitle>
            <Link href="/dashboard/articles" className="text-sm text-primary-600 font-bold flex items-center gap-1">
              عرض الكل <ArrowLeft className="h-4 w-4" />
            </Link>
          </CardHeader>
          <div className="space-y-3">
            {articles.length === 0 ? (
              <div className="text-center py-8 text-gray-400">
                <FileText className="h-10 w-10 mx-auto mb-2 opacity-50" />
                <p>لا توجد مقالات بعد</p>
                <Link href="/dashboard/articles/new" className="text-primary-600 text-sm font-bold mt-2 inline-block">
                  أضف مقالتك الأولى
                </Link>
              </div>
            ) : (
              articles.map((article) => (
                <div
                  key={article.id}
                  className="flex items-center justify-between p-3 bg-gray-50 rounded-xl"
                >
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-gray-900 truncate text-sm">
                      {article.title}
                    </p>
                    <p className="text-xs text-gray-400 mt-0.5">
                      {article.city?.nameAr} •{" "}
                      {new Date(article.createdAt).toLocaleDateString("ar-EG")}
                    </p>
                  </div>
                  <Badge variant={statusBadgeVariant[article.status] || "gray"}>
                    {ARTICLE_STATUS_LABELS[article.status]}
                  </Badge>
                </div>
              ))
            )}
          </div>
        </Card>

        {/* Recent Leads */}
        <Card>
          <CardHeader>
            <CardTitle>آخر العملاء</CardTitle>
            <Link href="/dashboard/leads" className="text-sm text-primary-600 font-bold flex items-center gap-1">
              عرض الكل <ArrowLeft className="h-4 w-4" />
            </Link>
          </CardHeader>
          <div className="space-y-3">
            {leads.length === 0 ? (
              <div className="text-center py-8 text-gray-400">
                <Users className="h-10 w-10 mx-auto mb-2 opacity-50" />
                <p>لا توجد عملاء بعد</p>
                <p className="text-xs mt-1">ستظهر هنا عندما يتواصل العملاء عبر مقالاتك</p>
              </div>
            ) : (
              leads.map((lead) => (
                <div
                  key={lead.id}
                  className="flex items-center justify-between p-3 bg-gray-50 rounded-xl"
                >
                  <div>
                    <p className="font-medium text-gray-900 text-sm">{lead.name}</p>
                    <p className="text-xs text-gray-400">
                      {lead.phone} • {lead.article?.title?.slice(0, 25)}...
                    </p>
                  </div>
                  <div className="text-left">
                    <p className="text-xs text-gray-400">
                      {new Date(lead.createdAt).toLocaleDateString("ar-EG")}
                    </p>
                  </div>
                </div>
              ))
            )}
          </div>
        </Card>
      </div>
    </div>
  );
}
