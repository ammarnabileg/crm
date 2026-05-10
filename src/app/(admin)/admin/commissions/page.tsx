"use client";

import { useState, useEffect, useCallback } from "react";
import Badge from "@/components/ui/Badge";
import Card from "@/components/ui/Card";
import Button from "@/components/ui/Button";
import toast from "react-hot-toast";
import { COMMISSION_STATUS_LABELS } from "@/types";
import { Banknote, CheckCircle, XCircle } from "lucide-react";

interface Commission {
  id: string;
  amount: number;
  rate: number;
  status: string;
  createdAt: string;
  writer: { name: string; email: string };
  lead: { name: string; phone: string };
  deal: { saleAmount: number; netProfit: number; status: string };
}

const statusVariant: Record<string, "success" | "warning" | "danger" | "info" | "gray"> = {
  PAID: "success",
  PAYABLE: "info",
  APPROVED: "success",
  UNDER_REVIEW: "warning",
  PENDING: "gray",
  REJECTED: "danger",
};

export default function AdminCommissionsPage() {
  const [commissions, setCommissions] = useState<Commission[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState("PENDING");
  const [processing, setProcessing] = useState<string | null>(null);

  const loadCommissions = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetch(`/api/admin/commissions?status=${filter}`);
      const data = await res.json();
      setCommissions(data.data || []);
    } catch {
      toast.error("حدث خطأ");
    } finally {
      setLoading(false);
    }
  }, [filter]);

  useEffect(() => { loadCommissions(); }, [loadCommissions]);

  const updateStatus = async (id: string, status: string) => {
    setProcessing(id);
    try {
      const res = await fetch(`/api/admin/commissions/${id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ status }),
      });
      if (!res.ok) throw new Error();
      toast.success("تم تحديث حالة العمولة");
      loadCommissions();
    } catch {
      toast.error("حدث خطأ");
    } finally {
      setProcessing(null);
    }
  };

  const total = commissions.reduce((sum, c) => sum + c.amount, 0);

  return (
    <div className="p-6 lg:p-8">
      <div className="mb-8">
        <h1 className="text-2xl font-black text-gray-900">إدارة العمولات</h1>
        <p className="text-gray-500 mt-1">مراجعة وإدارة عمولات كتّاب المحتوى</p>
      </div>

      {/* Summary */}
      <div className="bg-gradient-to-br from-yellow-400 to-yellow-500 rounded-2xl p-6 mb-6 text-gray-900">
        <Banknote className="h-8 w-8 mb-3" />
        <p className="text-4xl font-black">{total.toLocaleString("ar-EG")} ج.م</p>
        <p className="font-bold mt-1">إجمالي العمولات المعروضة</p>
      </div>

      {/* Filter */}
      <div className="flex flex-wrap gap-2 mb-6">
        {[
          { key: "PENDING", label: "معلقة" },
          { key: "APPROVED", label: "موافق عليها" },
          { key: "PAYABLE", label: "قابلة للدفع" },
          { key: "PAID", label: "مدفوعة" },
          { key: "", label: "الكل" },
        ].map((f) => (
          <button
            key={f.key}
            onClick={() => setFilter(f.key)}
            className={`px-4 py-2 rounded-xl font-bold text-sm transition-all ${
              filter === f.key
                ? "bg-primary-500 text-gray-900"
                : "bg-white border border-gray-200 text-gray-600 hover:bg-gray-50"
            }`}
          >
            {f.label}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="text-center py-12 text-gray-400">جاري التحميل...</div>
      ) : commissions.length === 0 ? (
        <Card>
          <div className="text-center py-12 text-gray-400">
            <Banknote className="h-12 w-12 mx-auto mb-3 opacity-30" />
            <p>لا توجد عمولات</p>
          </div>
        </Card>
      ) : (
        <div className="space-y-3">
          {commissions.map((commission) => (
            <Card key={commission.id} className="hover:shadow-md transition-shadow">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div className="flex-1">
                  <div className="flex items-center gap-2 mb-2">
                    <Badge variant={statusVariant[commission.status] || "gray"}>
                      {COMMISSION_STATUS_LABELS[commission.status as keyof typeof COMMISSION_STATUS_LABELS]}
                    </Badge>
                    <span className="text-xs text-gray-400">
                      نسبة {(commission.rate * 100).toFixed(0)}%
                    </span>
                  </div>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <p className="text-xs text-gray-400">الكاتب</p>
                      <p className="font-bold text-gray-900">{commission.writer.name}</p>
                      <p className="text-xs text-gray-400">{commission.writer.email}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-400">العميل</p>
                      <p className="font-bold text-gray-900">{commission.lead.name}</p>
                      <p className="text-xs text-gray-400">{commission.lead.phone}</p>
                    </div>
                  </div>
                  <div className="mt-2 text-xs text-gray-400">
                    مبيعات: {commission.deal.saleAmount.toLocaleString("ar-EG")} ج.م |
                    صافي ربح: {commission.deal.netProfit.toLocaleString("ar-EG")} ج.م
                  </div>
                </div>

                <div className="flex flex-col items-end gap-3">
                  <div className="text-left">
                    <p className="text-2xl font-black text-primary-700">
                      {commission.amount.toLocaleString("ar-EG")} ج.م
                    </p>
                    <p className="text-xs text-gray-400">
                      {new Date(commission.createdAt).toLocaleDateString("ar-EG")}
                    </p>
                  </div>
                  <div className="flex gap-2">
                    {commission.status === "PENDING" && (
                      <>
                        <Button
                          variant="primary"
                          size="sm"
                          onClick={() => updateStatus(commission.id, "APPROVED")}
                          loading={processing === commission.id}
                        >
                          <CheckCircle className="h-4 w-4" />
                          موافقة
                        </Button>
                        <Button
                          variant="danger"
                          size="sm"
                          onClick={() => updateStatus(commission.id, "REJECTED")}
                          disabled={processing !== null}
                        >
                          <XCircle className="h-4 w-4" />
                          رفض
                        </Button>
                      </>
                    )}
                    {commission.status === "APPROVED" && (
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => updateStatus(commission.id, "PAYABLE")}
                        loading={processing === commission.id}
                      >
                        قابل للدفع
                      </Button>
                    )}
                    {commission.status === "PAYABLE" && (
                      <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => updateStatus(commission.id, "PAID")}
                        loading={processing === commission.id}
                      >
                        تم الدفع
                      </Button>
                    )}
                  </div>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
