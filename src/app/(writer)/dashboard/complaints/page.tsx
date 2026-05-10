"use client";

import { useState, useEffect } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import Card from "@/components/ui/Card";
import Badge from "@/components/ui/Badge";
import Button from "@/components/ui/Button";
import Input from "@/components/ui/Input";
import Modal from "@/components/ui/Modal";
import Select from "@/components/ui/Select";
import toast from "react-hot-toast";
import { AlertCircle, Plus } from "lucide-react";

const complaintSchema = z.object({
  title: z.string().min(5, "العنوان مطلوب"),
  description: z.string().min(20, "الوصف مطلوب"),
  type: z.enum(["COMMISSION_DISPUTE", "LEAD_OWNERSHIP", "BROKER_BEHAVIOR", "CONTENT_ISSUE", "PAYMENT_ISSUE", "OTHER"]),
});

type ComplaintValues = z.infer<typeof complaintSchema>;

interface Complaint {
  id: string;
  title: string;
  type: string;
  status: string;
  priority: string;
  createdAt: string;
  resolution?: string;
}

const statusLabels: Record<string, string> = {
  OPEN: "مفتوحة",
  IN_REVIEW: "قيد المراجعة",
  RESOLVED: "تم الحل",
  CLOSED: "مغلقة",
};

const typeLabels: Record<string, string> = {
  COMMISSION_DISPUTE: "نزاع عمولة",
  LEAD_OWNERSHIP: "ملكية عميل",
  BROKER_BEHAVIOR: "سلوك وسيط",
  CONTENT_ISSUE: "مشكلة محتوى",
  PAYMENT_ISSUE: "مشكلة دفع",
  OTHER: "أخرى",
};

const statusVariant: Record<string, "success" | "warning" | "danger" | "info" | "gray"> = {
  RESOLVED: "success",
  IN_REVIEW: "warning",
  OPEN: "info",
  CLOSED: "gray",
};

export default function WriterComplaintsPage() {
  const [complaints, setComplaints] = useState<Complaint[]>([]);
  const [modalOpen, setModalOpen] = useState(false);
  const [loading, setLoading] = useState(true);

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<ComplaintValues>({
    resolver: zodResolver(complaintSchema),
    defaultValues: { type: "OTHER" },
  });

  const loadComplaints = async () => {
    try {
      const res = await fetch("/api/writer/complaints");
      const data = await res.json();
      setComplaints(data.data || []);
    } catch {
      toast.error("حدث خطأ في تحميل الشكاوي");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadComplaints(); }, []);

  const onSubmit = async (data: ComplaintValues) => {
    try {
      const res = await fetch("/api/writer/complaints", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });
      if (!res.ok) throw new Error();
      toast.success("تم إرسال الشكوى بنجاح");
      setModalOpen(false);
      reset();
      loadComplaints();
    } catch {
      toast.error("حدث خطأ في إرسال الشكوى");
    }
  };

  return (
    <div className="p-6 lg:p-8">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-black text-gray-900">الشكاوي والنزاعات</h1>
          <p className="text-gray-500 mt-1">تتبع شكاواك ومشاكلك مع الإدارة</p>
        </div>
        <Button onClick={() => setModalOpen(true)} variant="primary">
          <Plus className="h-5 w-5" />
          شكوى جديدة
        </Button>
      </div>

      {loading ? (
        <div className="text-center py-12 text-gray-400">جاري التحميل...</div>
      ) : complaints.length === 0 ? (
        <Card>
          <div className="text-center py-16">
            <AlertCircle className="h-16 w-16 text-gray-200 mx-auto mb-4" />
            <h3 className="text-xl font-bold text-gray-900 mb-2">لا توجد شكاوي</h3>
            <p className="text-gray-500">يمكنك رفع شكوى في حال وجود أي مشكلة</p>
          </div>
        </Card>
      ) : (
        <div className="space-y-3">
          {complaints.map((c) => (
            <Card key={c.id}>
              <div className="flex items-center justify-between">
                <div>
                  <div className="flex items-center gap-2 mb-1">
                    <Badge variant={statusVariant[c.status] || "gray"}>
                      {statusLabels[c.status]}
                    </Badge>
                    <span className="text-xs text-gray-400">{typeLabels[c.type]}</span>
                  </div>
                  <h3 className="font-bold text-gray-900">{c.title}</h3>
                  {c.resolution && (
                    <p className="text-sm text-green-600 mt-1">الحل: {c.resolution}</p>
                  )}
                </div>
                <p className="text-xs text-gray-400">
                  {new Date(c.createdAt).toLocaleDateString("ar-EG")}
                </p>
              </div>
            </Card>
          ))}
        </div>
      )}

      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title="رفع شكوى جديدة" size="lg">
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <Select
            label="نوع الشكوى"
            options={Object.entries(typeLabels).map(([value, label]) => ({ value, label }))}
            {...register("type")}
          />
          <Input
            label="عنوان الشكوى"
            placeholder="وصف مختصر للمشكلة"
            error={errors.title?.message}
            {...register("title")}
          />
          <div>
            <label className="label">تفاصيل الشكوى</label>
            <textarea
              className="input min-h-32 resize-none"
              placeholder="اشرح مشكلتك بالتفصيل..."
              {...register("description")}
            />
            {errors.description && (
              <p className="text-sm text-red-600 mt-1">{errors.description.message}</p>
            )}
          </div>
          <div className="flex gap-3">
            <Button type="submit" variant="primary" loading={isSubmitting}>
              إرسال الشكوى
            </Button>
            <Button type="button" variant="ghost" onClick={() => setModalOpen(false)}>
              إلغاء
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
