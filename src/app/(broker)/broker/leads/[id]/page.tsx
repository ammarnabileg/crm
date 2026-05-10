"use client";

import { useState, useEffect, use } from "react";
import { useRouter } from "next/navigation";
import Card, { CardHeader, CardTitle } from "@/components/ui/Card";
import Button from "@/components/ui/Button";
import Badge from "@/components/ui/Badge";
import toast from "react-hot-toast";
import { PIPELINE_STAGE_LABELS } from "@/types";
import { Phone, MessageSquare, ChevronLeft, Upload } from "lucide-react";

interface LeadDetail {
  id: string;
  name: string;
  phone: string;
  email?: string;
  interestedArea?: string;
  status: string;
  score: string;
  pipelineStage: string;
  notes?: string;
  createdAt: string;
  city?: { nameAr: string } | null;
  project?: { name: string; salesPhone: string } | null;
  interactions: {
    id: string;
    type: string;
    content?: string;
    createdBy: string;
    createdAt: string;
  }[];
  deal?: { saleAmount: number; status: string } | null;
}

export default function BrokerLeadDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();
  const [lead, setLead] = useState<LeadDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [noteContent, setNoteContent] = useState("");
  const [dealData, setDealData] = useState({ saleAmount: "", netProfit: "" });
  const [addingDeal, setAddingDeal] = useState(false);
  const [showDealForm, setShowDealForm] = useState(false);

  const loadLead = async () => {
    try {
      const res = await fetch(`/api/broker/leads/${id}`);
      const data = await res.json();
      setLead(data.data);
    } catch {
      toast.error("حدث خطأ");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadLead(); }, [id]);

  const updateStage = async (stage: string) => {
    try {
      await fetch(`/api/broker/leads/${id}/update`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ pipelineStage: stage }),
      });
      toast.success("تم تحديث المرحلة");
      loadLead();
    } catch {
      toast.error("حدث خطأ");
    }
  };

  const addNote = async () => {
    if (!noteContent.trim()) return;
    try {
      await fetch(`/api/broker/leads/${id}/update`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ note: noteContent }),
      });
      toast.success("تم إضافة الملاحظة");
      setNoteContent("");
      loadLead();
    } catch {
      toast.error("حدث خطأ");
    }
  };

  const submitDeal = async () => {
    if (!dealData.saleAmount || !dealData.netProfit) {
      toast.error("يرجى إدخال بيانات الصفقة");
      return;
    }
    setAddingDeal(true);
    try {
      await fetch(`/api/broker/leads/${id}/deal`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          saleAmount: parseFloat(dealData.saleAmount),
          netProfit: parseFloat(dealData.netProfit),
        }),
      });
      toast.success("تم تقديم الصفقة للمراجعة");
      setShowDealForm(false);
      setDealData({ saleAmount: "", netProfit: "" });
      loadLead();
    } catch {
      toast.error("حدث خطأ");
    } finally {
      setAddingDeal(false);
    }
  };

  if (loading) return <div className="p-8 text-center text-gray-400">جاري التحميل...</div>;
  if (!lead) return <div className="p-8 text-center text-red-500">العميل غير موجود</div>;

  return (
    <div className="p-6 lg:p-8">
      <div className="flex items-center gap-4 mb-8">
        <button onClick={() => router.back()} className="p-2 hover:bg-gray-100 rounded-xl">
          <ChevronLeft className="h-5 w-5 rotate-180" />
        </button>
        <div>
          <h1 className="text-2xl font-black text-gray-900">{lead.name}</h1>
          <p className="text-gray-500">{lead.phone}</p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2 space-y-6">
          {/* Lead Info */}
          <Card>
            <CardHeader>
              <CardTitle>معلومات العميل</CardTitle>
            </CardHeader>
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p className="text-gray-400 text-xs">الهاتف</p>
                <a href={`tel:${lead.phone}`} className="font-bold text-blue-600 hover:underline">
                  {lead.phone}
                </a>
              </div>
              {lead.email && (
                <div>
                  <p className="text-gray-400 text-xs">البريد</p>
                  <p className="font-bold">{lead.email}</p>
                </div>
              )}
              {lead.city && (
                <div>
                  <p className="text-gray-400 text-xs">المنطقة</p>
                  <p className="font-bold">{lead.city.nameAr}</p>
                </div>
              )}
              {lead.project && (
                <div>
                  <p className="text-gray-400 text-xs">المشروع</p>
                  <p className="font-bold">{lead.project.name}</p>
                </div>
              )}
              {lead.interestedArea && (
                <div className="col-span-2">
                  <p className="text-gray-400 text-xs">المنطقة المفضلة</p>
                  <p className="font-bold">{lead.interestedArea}</p>
                </div>
              )}
            </div>
            <div className="mt-4 flex gap-2">
              <a
                href={`tel:${lead.phone}`}
                className="flex-1 flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 rounded-xl transition-colors text-sm"
              >
                <Phone className="h-4 w-4" />
                اتصال
              </a>
              <a
                href={`https://wa.me/2${lead.phone.replace(/^0/, "")}`}
                target="_blank"
                rel="noopener noreferrer"
                className="flex-1 flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 text-white font-bold py-2.5 rounded-xl transition-colors text-sm"
              >
                <MessageSquare className="h-4 w-4" />
                واتساب
              </a>
            </div>
          </Card>

          {/* Add Note */}
          <Card>
            <CardTitle className="mb-4">إضافة ملاحظة / تسجيل تفاعل</CardTitle>
            <div className="flex gap-3">
              <textarea
                className="input flex-1 min-h-20 resize-none"
                placeholder="اكتب ملاحظتك أو سجل نتيجة الاتصال..."
                value={noteContent}
                onChange={(e) => setNoteContent(e.target.value)}
              />
              <Button variant="primary" onClick={addNote} className="self-end">
                إضافة
              </Button>
            </div>
          </Card>

          {/* Interactions */}
          <Card>
            <CardTitle className="mb-4">سجل التفاعلات</CardTitle>
            {lead.interactions.length === 0 ? (
              <div className="text-center py-8 text-gray-400 text-sm">
                لا توجد تفاعلات بعد
              </div>
            ) : (
              <div className="space-y-3">
                {lead.interactions.map((i) => (
                  <div key={i.id} className="bg-gray-50 rounded-xl p-3">
                    <div className="flex justify-between mb-1">
                      <span className="text-xs font-bold text-gray-700">{i.type}</span>
                      <span className="text-xs text-gray-400">
                        {new Date(i.createdAt).toLocaleDateString("ar-EG")}
                      </span>
                    </div>
                    {i.content && <p className="text-sm text-gray-600">{i.content}</p>}
                  </div>
                ))}
              </div>
            )}
          </Card>
        </div>

        {/* Right sidebar */}
        <div className="space-y-4">
          {/* Pipeline Stage */}
          <Card>
            <CardTitle className="mb-4">مرحلة العميل</CardTitle>
            <select
              className="input"
              value={lead.pipelineStage}
              onChange={(e) => updateStage(e.target.value)}
            >
              {Object.entries(PIPELINE_STAGE_LABELS).map(([key, label]) => (
                <option key={key} value={key}>{label}</option>
              ))}
            </select>
          </Card>

          {/* Submit Deal */}
          <Card>
            <CardTitle className="mb-4">تسجيل صفقة</CardTitle>
            {lead.deal ? (
              <div className="text-sm">
                <Badge variant="warning">قيد المراجعة</Badge>
                <p className="text-gray-600 mt-2">
                  المبيعات: {lead.deal.saleAmount.toLocaleString("ar-EG")} ج.م
                </p>
              </div>
            ) : !showDealForm ? (
              <Button
                variant="primary"
                size="sm"
                fullWidth
                onClick={() => setShowDealForm(true)}
              >
                <Upload className="h-4 w-4" />
                تسجيل صفقة مغلقة
              </Button>
            ) : (
              <div className="space-y-3">
                <div>
                  <label className="label text-xs">مبلغ المبيعات (ج.م)</label>
                  <input
                    type="number"
                    className="input"
                    placeholder="1500000"
                    value={dealData.saleAmount}
                    onChange={(e) => setDealData({ ...dealData, saleAmount: e.target.value })}
                  />
                </div>
                <div>
                  <label className="label text-xs">صافي الربح (ج.م)</label>
                  <input
                    type="number"
                    className="input"
                    placeholder="150000"
                    value={dealData.netProfit}
                    onChange={(e) => setDealData({ ...dealData, netProfit: e.target.value })}
                  />
                </div>
                <div className="flex gap-2">
                  <Button variant="primary" size="sm" fullWidth onClick={submitDeal} loading={addingDeal}>
                    تقديم
                  </Button>
                  <Button variant="ghost" size="sm" onClick={() => setShowDealForm(false)}>
                    إلغاء
                  </Button>
                </div>
              </div>
            )}
          </Card>
        </div>
      </div>
    </div>
  );
}
