"use client";

import { useState, useEffect, useCallback } from "react";
import Badge from "@/components/ui/Badge";
import Card from "@/components/ui/Card";
import Button from "@/components/ui/Button";
import Modal from "@/components/ui/Modal";
import Input from "@/components/ui/Input";
import toast from "react-hot-toast";
import { ARTICLE_STATUS_LABELS } from "@/types";
import { CheckCircle, XCircle, Edit, Eye, Clock, FileText } from "lucide-react";
import Link from "next/link";

interface Article {
  id: string;
  title: string;
  slug: string;
  status: string;
  createdAt: string;
  publishedAt?: string;
  author: { name: string };
  city?: { nameAr: string };
  project?: { name: string };
  viewCount: number;
  leadCount: number;
}

const statusVariant: Record<string, "success" | "warning" | "danger" | "info" | "gray"> = {
  APPROVED: "success",
  PENDING: "warning",
  REJECTED: "danger",
  DRAFT: "gray",
  NEEDS_EDIT: "info",
};

export default function AdminArticlesPage() {
  const [articles, setArticles] = useState<Article[]>([]);
  const [filter, setFilter] = useState("PENDING");
  const [loading, setLoading] = useState(true);
  const [rejectModal, setRejectModal] = useState<{ open: boolean; articleId: string | null }>({
    open: false,
    articleId: null,
  });
  const [rejectionReason, setRejectionReason] = useState("");
  const [processing, setProcessing] = useState<string | null>(null);

  const loadArticles = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetch(`/api/admin/articles?status=${filter}`);
      const data = await res.json();
      setArticles(data.data || []);
    } catch {
      toast.error("حدث خطأ في تحميل المقالات");
    } finally {
      setLoading(false);
    }
  }, [filter]);

  useEffect(() => { loadArticles(); }, [loadArticles]);

  const updateStatus = async (articleId: string, status: string, reason?: string) => {
    setProcessing(articleId);
    try {
      const res = await fetch(`/api/admin/articles/${articleId}/status`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ status, rejectionReason: reason }),
      });
      if (!res.ok) throw new Error();
      toast.success(
        status === "APPROVED"
          ? "تم قبول المقالة"
          : status === "REJECTED"
          ? "تم رفض المقالة"
          : "تم تحديث الحالة"
      );
      loadArticles();
    } catch {
      toast.error("حدث خطأ");
    } finally {
      setProcessing(null);
    }
  };

  const handleReject = async () => {
    if (!rejectModal.articleId || !rejectionReason.trim()) {
      toast.error("يرجى إدخال سبب الرفض");
      return;
    }
    await updateStatus(rejectModal.articleId, "REJECTED", rejectionReason);
    setRejectModal({ open: false, articleId: null });
    setRejectionReason("");
  };

  const filterTabs = [
    { key: "PENDING", label: "تنتظر المراجعة", icon: Clock },
    { key: "APPROVED", label: "مقبولة", icon: CheckCircle },
    { key: "REJECTED", label: "مرفوضة", icon: XCircle },
    { key: "", label: "الكل", icon: FileText },
  ];

  return (
    <div className="p-6 lg:p-8">
      <div className="mb-8">
        <h1 className="text-2xl font-black text-gray-900">إدارة المقالات</h1>
        <p className="text-gray-500 mt-1">مراجعة وإدارة مقالات الكتّاب</p>
      </div>

      {/* Filter Tabs */}
      <div className="flex flex-wrap gap-2 mb-6">
        {filterTabs.map((tab) => {
          const Icon = tab.icon;
          return (
            <button
              key={tab.key}
              onClick={() => setFilter(tab.key)}
              className={`flex items-center gap-2 px-4 py-2 rounded-xl font-bold text-sm transition-all ${
                filter === tab.key
                  ? "bg-primary-500 text-gray-900"
                  : "bg-white border border-gray-200 text-gray-600 hover:bg-gray-50"
              }`}
            >
              <Icon className="h-4 w-4" />
              {tab.label}
            </button>
          );
        })}
      </div>

      {loading ? (
        <div className="text-center py-12 text-gray-400">جاري التحميل...</div>
      ) : articles.length === 0 ? (
        <Card>
          <div className="text-center py-16">
            <FileText className="h-16 w-16 text-gray-200 mx-auto mb-4" />
            <p className="text-gray-500">لا توجد مقالات في هذا التصنيف</p>
          </div>
        </Card>
      ) : (
        <div className="space-y-3">
          {articles.map((article) => (
            <Card key={article.id} className="hover:shadow-md transition-shadow">
              <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                <div className="flex-1 min-w-0">
                  <div className="flex flex-wrap items-center gap-2 mb-2">
                    <Badge variant={statusVariant[article.status] || "gray"}>
                      {ARTICLE_STATUS_LABELS[article.status as keyof typeof ARTICLE_STATUS_LABELS]}
                    </Badge>
                    {article.city && (
                      <span className="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                        {article.city.nameAr}
                      </span>
                    )}
                    {article.project && (
                      <span className="text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full">
                        {article.project.name}
                      </span>
                    )}
                  </div>
                  <h3 className="font-bold text-gray-900">{article.title}</h3>
                  <div className="flex items-center gap-4 mt-2 text-xs text-gray-400">
                    <span>بقلم: {article.author.name}</span>
                    <span className="flex items-center gap-1">
                      <Eye className="h-3 w-3" />
                      {article.viewCount}
                    </span>
                    <span>{new Date(article.createdAt).toLocaleDateString("ar-EG")}</span>
                  </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                  {article.status === "APPROVED" && (
                    <Link
                      href={`/articles/${article.slug}`}
                      target="_blank"
                      className="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-700 px-3 py-1.5 border border-blue-200 rounded-lg hover:bg-blue-50 transition-colors"
                    >
                      <Eye className="h-3.5 w-3.5" />
                      عرض
                    </Link>
                  )}

                  {article.status === "PENDING" && (
                    <>
                      <Button
                        variant="primary"
                        size="sm"
                        onClick={() => updateStatus(article.id, "APPROVED")}
                        loading={processing === article.id}
                      >
                        <CheckCircle className="h-4 w-4" />
                        قبول
                      </Button>
                      <Button
                        variant="danger"
                        size="sm"
                        onClick={() => setRejectModal({ open: true, articleId: article.id })}
                        disabled={processing !== null}
                      >
                        <XCircle className="h-4 w-4" />
                        رفض
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => updateStatus(article.id, "NEEDS_EDIT")}
                        disabled={processing !== null}
                      >
                        <Edit className="h-4 w-4" />
                        يحتاج تعديل
                      </Button>
                    </>
                  )}

                  {["APPROVED", "REJECTED", "NEEDS_EDIT"].includes(article.status) && (
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => updateStatus(article.id, "PENDING")}
                      disabled={processing !== null}
                    >
                      إعادة للمراجعة
                    </Button>
                  )}
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}

      {/* Reject Modal */}
      <Modal
        isOpen={rejectModal.open}
        onClose={() => setRejectModal({ open: false, articleId: null })}
        title="سبب رفض المقالة"
        size="md"
      >
        <div className="space-y-4">
          <p className="text-gray-500 text-sm">
            يرجى إدخال سبب واضح لرفض المقالة حتى يتمكن الكاتب من تحسينها.
          </p>
          <div>
            <label className="label">سبب الرفض</label>
            <textarea
              className="input min-h-28 resize-none"
              placeholder="مثال: المحتوى غير كافٍ، المعلومات غير دقيقة، ..."
              value={rejectionReason}
              onChange={(e) => setRejectionReason(e.target.value)}
            />
          </div>
          <div className="flex gap-3">
            <Button variant="danger" onClick={handleReject} fullWidth>
              تأكيد الرفض
            </Button>
            <Button
              variant="ghost"
              onClick={() => setRejectModal({ open: false, articleId: null })}
              fullWidth
            >
              إلغاء
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
