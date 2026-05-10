"use client";

import { useState, useEffect, use } from "react";
import { useRouter } from "next/navigation";
import Badge from "@/components/ui/Badge";
import Card, { CardHeader, CardTitle } from "@/components/ui/Card";
import Button from "@/components/ui/Button";
import Modal from "@/components/ui/Modal";
import toast from "react-hot-toast";
import {
  LEAD_STATUS_LABELS,
  LEAD_SCORE_LABELS,
  PIPELINE_STAGE_LABELS,
  LEAD_SCORE_LABELS as SCORE_LABELS,
} from "@/types";
import { Phone, MapPin, Building2, User, Calendar, ChevronLeft, MessageSquare } from "lucide-react";

interface LeadDetail {
  id: string;
  name: string;
  phone: string;
  email?: string;
  interestedArea?: string;
  status: string;
  score: string;
  source: string;
  pipelineStage: string;
  notes?: string;
  utmSource?: string;
  utmMedium?: string;
  utmCampaign?: string;
  referrer?: string;
  ipAddress?: string;
  createdAt: string;
  writer?: { id: string; name: string } | null;
  city?: { nameAr: string } | null;
  project?: { name: string } | null;
  article?: { title: string } | null;
  brokerCompany?: { id: string; name: string } | null;
  interactions: {
    id: string;
    type: string;
    content?: string;
    createdBy: string;
    createdAt: string;
  }[];
  deal?: { id: string; saleAmount: number; netProfit: number; status: string } | null;
}

interface BrokerCompany {
  id: string;
  name: string;
}

const stageVariant: Record<string, "success" | "warning" | "danger" | "info" | "gray"> = {
  CLOSED_WON: "success",
  RESERVATION: "info",
  NEGOTIATION: "warning",
  CONTACTED: "info",
  NEW_LEAD: "gray",
  CLOSED_LOST: "danger",
};

export default function LeadDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();
  const [lead, setLead] = useState<LeadDetail | null>(null);
  const [brokerCompanies, setBrokerCompanies] = useState<BrokerCompany[]>([]);
  const [loading, setLoading] = useState(true);
  const [assignModal, setAssignModal] = useState(false);
  const [selectedBroker, setSelectedBroker] = useState("");
  const [noteModal, setNoteModal] = useState(false);
  const [noteContent, setNoteContent] = useState("");
  const [stageUpdate, setStageUpdate] = useState("");
  const [scoreUpdate, setScoreUpdate] = useState("");

  const loadLead = async () => {
    try {
      const [leadRes, brokersRes] = await Promise.all([
        fetch(`/api/admin/leads/${id}`),
        fetch("/api/admin/companies?type=broker"),
      ]);
      const leadData = await leadRes.json();
      const brokersData = await brokersRes.json();
      setLead(leadData.data);
      setBrokerCompanies(brokersData.data || []);
    } catch {
      toast.error("حدث خطأ في تحميل البيانات");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadLead(); }, [id]);

  const assignBroker = async () => {
    if (!selectedBroker) return;
    try {
      const res = await fetch(`/api/admin/leads/${id}/assign`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ brokerCompanyId: selectedBroker }),
      });
      if (!res.ok) throw new Error();
      toast.success("تم إسناد العميل للشركة");
      setAssignModal(false);
      loadLead();
    } catch {
      toast.error("حدث خطأ في الإسناد");
    }
  };

  const addNote = async () => {
    if (!noteContent.trim()) return;
    try {
      const res = await fetch(`/api/admin/leads/${id}/interactions`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ type: "note", content: noteContent }),
      });
      if (!res.ok) throw new Error();
      toast.success("تم إضافة الملاحظة");
      setNoteModal(false);
      setNoteContent("");
      loadLead();
    } catch {
      toast.error("حدث خطأ");
    }
  };

  const updateStage = async (stage: string) => {
    try {
      const res = await fetch(`/api/admin/leads/${id}/assign`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ pipelineStage: stage }),
      });
      if (!res.ok) throw new Error();
      toast.success("تم تحديث مرحلة العميل");
      loadLead();
    } catch {
      toast.error("حدث خطأ");
    }
  };

  const updateScore = async (score: string) => {
    try {
      const res = await fetch(`/api/admin/leads/${id}/assign`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ score }),
      });
      if (!res.ok) throw new Error();
      toast.success("تم تحديث درجة العميل");
      loadLead();
    } catch {
      toast.error("حدث خطأ");
    }
  };

  if (loading) return <div className="p-8 text-center text-gray-400">جاري التحميل...</div>;
  if (!lead) return <div className="p-8 text-center text-red-500">العميل غير موجود</div>;

  return (
    <div className="p-6 lg:p-8">
      <div className="flex items-center gap-4 mb-8">
        <button onClick={() => router.back()} className="p-2 hover:bg-gray-100 rounded-xl transition-colors">
          <ChevronLeft className="h-5 w-5 rotate-180" />
        </button>
        <div>
          <h1 className="text-2xl font-black text-gray-900">{lead.name}</h1>
          <div className="flex items-center gap-2 mt-1">
            <Badge variant={stageVariant[lead.pipelineStage] || "gray"}>
              {PIPELINE_STAGE_LABELS[lead.pipelineStage as keyof typeof PIPELINE_STAGE_LABELS]}
            </Badge>
            <Badge variant={stageVariant[lead.score] || "gray"}>
              {LEAD_SCORE_LABELS[lead.score as keyof typeof LEAD_SCORE_LABELS]}
            </Badge>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left: Lead Info */}
        <div className="lg:col-span-2 space-y-6">
          {/* Contact Info */}
          <Card>
            <CardHeader>
              <CardTitle>معلومات التواصل</CardTitle>
            </CardHeader>
            <div className="grid grid-cols-2 gap-4">
              <div className="flex items-center gap-3">
                <div className="bg-blue-100 p-2 rounded-lg">
                  <Phone className="h-4 w-4 text-blue-600" />
                </div>
                <div>
                  <p className="text-xs text-gray-500">الهاتف</p>
                  <a href={`tel:${lead.phone}`} className="font-bold text-gray-900 hover:text-blue-600">
                    {lead.phone}
                  </a>
                </div>
              </div>
              {lead.email && (
                <div className="flex items-center gap-3">
                  <div className="bg-green-100 p-2 rounded-lg">
                    <User className="h-4 w-4 text-green-600" />
                  </div>
                  <div>
                    <p className="text-xs text-gray-500">البريد</p>
                    <p className="font-bold text-gray-900">{lead.email}</p>
                  </div>
                </div>
              )}
              {lead.city && (
                <div className="flex items-center gap-3">
                  <div className="bg-yellow-100 p-2 rounded-lg">
                    <MapPin className="h-4 w-4 text-yellow-600" />
                  </div>
                  <div>
                    <p className="text-xs text-gray-500">المنطقة</p>
                    <p className="font-bold text-gray-900">{lead.city.nameAr}</p>
                  </div>
                </div>
              )}
              {lead.project && (
                <div className="flex items-center gap-3">
                  <div className="bg-purple-100 p-2 rounded-lg">
                    <Building2 className="h-4 w-4 text-purple-600" />
                  </div>
                  <div>
                    <p className="text-xs text-gray-500">المشروع</p>
                    <p className="font-bold text-gray-900">{lead.project.name}</p>
                  </div>
                </div>
              )}
              {lead.article && (
                <div className="col-span-2 flex items-center gap-3">
                  <div className="bg-orange-100 p-2 rounded-lg">
                    <Calendar className="h-4 w-4 text-orange-600" />
                  </div>
                  <div>
                    <p className="text-xs text-gray-500">المقالة المصدر</p>
                    <p className="font-bold text-gray-900 text-sm">{lead.article.title}</p>
                  </div>
                </div>
              )}
            </div>
          </Card>

          {/* Attribution */}
          <Card>
            <CardHeader>
              <CardTitle>بيانات المصدر</CardTitle>
            </CardHeader>
            <div className="grid grid-cols-2 gap-3 text-sm">
              {[
                { label: "المصدر", value: lead.source },
                { label: "كاتب المحتوى", value: lead.writer?.name || "مباشر" },
                { label: "UTM Source", value: lead.utmSource || "-" },
                { label: "UTM Medium", value: lead.utmMedium || "-" },
                { label: "UTM Campaign", value: lead.utmCampaign || "-" },
                { label: "Referrer", value: lead.referrer || "-" },
                { label: "تاريخ الإنشاء", value: new Date(lead.createdAt).toLocaleDateString("ar-EG") },
              ].map(({ label, value }) => (
                <div key={label}>
                  <p className="text-xs text-gray-400">{label}</p>
                  <p className="font-medium text-gray-900 truncate">{value}</p>
                </div>
              ))}
            </div>
          </Card>

          {/* Interactions Timeline */}
          <Card>
            <CardHeader>
              <CardTitle>سجل التفاعلات</CardTitle>
              <Button variant="outline" size="sm" onClick={() => setNoteModal(true)}>
                <MessageSquare className="h-4 w-4" />
                إضافة ملاحظة
              </Button>
            </CardHeader>
            {lead.interactions.length === 0 ? (
              <div className="text-center py-8 text-gray-400 text-sm">
                لا توجد تفاعلات بعد
              </div>
            ) : (
              <div className="space-y-3">
                {lead.interactions.map((interaction) => (
                  <div key={interaction.id} className="flex gap-3">
                    <div className="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                      <MessageSquare className="h-4 w-4 text-primary-600" />
                    </div>
                    <div className="flex-1 bg-gray-50 rounded-xl p-3">
                      <div className="flex items-center justify-between mb-1">
                        <span className="text-xs font-bold text-gray-700">{interaction.type}</span>
                        <span className="text-xs text-gray-400">
                          {new Date(interaction.createdAt).toLocaleDateString("ar-EG")}
                        </span>
                      </div>
                      {interaction.content && (
                        <p className="text-sm text-gray-600">{interaction.content}</p>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </Card>
        </div>

        {/* Right: Actions */}
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

          {/* Score */}
          <Card>
            <CardTitle className="mb-4">تقييم العميل</CardTitle>
            <select
              className="input"
              value={lead.score}
              onChange={(e) => updateScore(e.target.value)}
            >
              {Object.entries(LEAD_SCORE_LABELS).map(([key, label]) => (
                <option key={key} value={key}>{label}</option>
              ))}
            </select>
          </Card>

          {/* Broker Assignment */}
          <Card>
            <CardTitle className="mb-4">إسناد للوسيط</CardTitle>
            {lead.brokerCompany ? (
              <div>
                <div className="flex items-center gap-2 mb-3">
                  <Badge variant="success">مُعين</Badge>
                  <span className="font-bold text-gray-900">{lead.brokerCompany.name}</span>
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  fullWidth
                  onClick={() => setAssignModal(true)}
                >
                  تغيير الوسيط
                </Button>
              </div>
            ) : (
              <div>
                <p className="text-sm text-orange-500 mb-3">لم يتم الإسناد بعد</p>
                <Button variant="primary" size="sm" fullWidth onClick={() => setAssignModal(true)}>
                  إسناد لشركة وسيطة
                </Button>
              </div>
            )}
          </Card>

          {/* Actions */}
          <Card>
            <CardTitle className="mb-4">إجراءات سريعة</CardTitle>
            <div className="space-y-2">
              <a
                href={`tel:${lead.phone}`}
                className="flex items-center gap-2 w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-xl transition-colors text-sm"
              >
                <Phone className="h-4 w-4" />
                اتصال مباشر
              </a>
              <a
                href={`https://wa.me/2${lead.phone.replace(/^0/, "")}`}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center gap-2 w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2.5 px-4 rounded-xl transition-colors text-sm"
              >
                <MessageSquare className="h-4 w-4" />
                واتساب
              </a>
            </div>
          </Card>
        </div>
      </div>

      {/* Assign Modal */}
      <Modal isOpen={assignModal} onClose={() => setAssignModal(false)} title="إسناد العميل لشركة وسيطة">
        <div className="space-y-4">
          <div>
            <label className="label">اختر الشركة الوسيطة</label>
            <select
              className="input"
              value={selectedBroker}
              onChange={(e) => setSelectedBroker(e.target.value)}
            >
              <option value="">-- اختر شركة --</option>
              {brokerCompanies.map((company) => (
                <option key={company.id} value={company.id}>
                  {company.name}
                </option>
              ))}
            </select>
          </div>
          <div className="flex gap-3">
            <Button variant="primary" fullWidth onClick={assignBroker} disabled={!selectedBroker}>
              تأكيد الإسناد
            </Button>
            <Button variant="ghost" fullWidth onClick={() => setAssignModal(false)}>
              إلغاء
            </Button>
          </div>
        </div>
      </Modal>

      {/* Note Modal */}
      <Modal isOpen={noteModal} onClose={() => setNoteModal(false)} title="إضافة ملاحظة">
        <div className="space-y-4">
          <div>
            <label className="label">الملاحظة</label>
            <textarea
              className="input min-h-28 resize-none"
              placeholder="اكتب ملاحظتك هنا..."
              value={noteContent}
              onChange={(e) => setNoteContent(e.target.value)}
            />
          </div>
          <div className="flex gap-3">
            <Button variant="primary" fullWidth onClick={addNote}>
              حفظ الملاحظة
            </Button>
            <Button variant="ghost" fullWidth onClick={() => setNoteModal(false)}>
              إلغاء
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
