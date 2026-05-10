import { auth } from "@/lib/auth";
import { db } from "@/lib/db";
import Link from "next/link";
import Badge from "@/components/ui/Badge";
import Card from "@/components/ui/Card";
import { ARTICLE_STATUS_LABELS } from "@/types";
import { Plus, Eye, Edit, FileText } from "lucide-react";

export default async function WriterArticlesPage() {
  const session = await auth();
  if (!session?.user) return null;

  const articles = await db.article.findMany({
    where: { authorId: session.user.id, isDeleted: false },
    orderBy: { createdAt: "desc" },
    include: {
      city: { select: { nameAr: true } },
      project: { select: { name: true } },
    },
  });

  const statusVariant: Record<string, "success" | "warning" | "danger" | "info" | "gray"> = {
    APPROVED: "success",
    PENDING: "warning",
    REJECTED: "danger",
    DRAFT: "gray",
    NEEDS_EDIT: "info",
  };

  return (
    <div className="p-6 lg:p-8">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-black text-gray-900">مقالاتي</h1>
          <p className="text-gray-500 mt-1">إدارة جميع مقالاتك العقارية</p>
        </div>
        <Link
          href="/dashboard/articles/new"
          className="flex items-center gap-2 bg-primary-500 hover:bg-primary-600 text-gray-900 font-bold px-5 py-2.5 rounded-xl transition-all"
        >
          <Plus className="h-5 w-5" />
          إضافة مقالة
        </Link>
      </div>

      {articles.length === 0 ? (
        <Card>
          <div className="text-center py-16">
            <FileText className="h-16 w-16 text-gray-200 mx-auto mb-4" />
            <h3 className="text-xl font-bold text-gray-900 mb-2">لا توجد مقالات بعد</h3>
            <p className="text-gray-500 mb-6">ابدأ بكتابة مقالتك الأولى وابدأ في كسب العمولات</p>
            <Link
              href="/dashboard/articles/new"
              className="inline-flex items-center gap-2 bg-primary-500 hover:bg-primary-600 text-gray-900 font-bold px-6 py-3 rounded-xl transition-all"
            >
              <Plus className="h-5 w-5" />
              إضافة مقالة جديدة
            </Link>
          </div>
        </Card>
      ) : (
        <div className="space-y-4">
          {articles.map((article) => (
            <Card key={article.id} className="hover:shadow-md transition-shadow">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div className="flex-1 min-w-0">
                  <div className="flex flex-wrap items-center gap-2 mb-2">
                    <Badge variant={statusVariant[article.status] || "gray"}>
                      {ARTICLE_STATUS_LABELS[article.status]}
                    </Badge>
                    {article.city && (
                      <span className="text-xs text-gray-400">{article.city.nameAr}</span>
                    )}
                    {article.project && (
                      <span className="text-xs text-gray-400">• {article.project.name}</span>
                    )}
                  </div>
                  <h3 className="font-bold text-gray-900 truncate">{article.title}</h3>
                  {article.rejectionReason && (
                    <p className="text-sm text-red-600 mt-1 bg-red-50 px-3 py-1.5 rounded-lg">
                      سبب الرفض: {article.rejectionReason}
                    </p>
                  )}
                  <div className="flex items-center gap-4 mt-2 text-xs text-gray-400">
                    <span>{new Date(article.createdAt).toLocaleDateString("ar-EG")}</span>
                    <span className="flex items-center gap-1">
                      <Eye className="h-3 w-3" />
                      {article.viewCount} مشاهدة
                    </span>
                    <span>{article.leadCount} عميل</span>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  {article.status === "APPROVED" && (
                    <Link
                      href={`/articles/${article.slug}`}
                      target="_blank"
                      className="flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700 font-medium px-3 py-1.5 border border-blue-200 rounded-lg hover:bg-blue-50 transition-colors"
                    >
                      <Eye className="h-4 w-4" />
                      عرض
                    </Link>
                  )}
                  <Link
                    href={`/dashboard/articles/${article.id}/edit`}
                    className="flex items-center gap-1 text-sm text-gray-600 hover:text-gray-700 font-medium px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                  >
                    <Edit className="h-4 w-4" />
                    تعديل
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
