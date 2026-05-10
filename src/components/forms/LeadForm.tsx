"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import toast from "react-hot-toast";
import Input from "@/components/ui/Input";
import Button from "@/components/ui/Button";
import { Phone, MessageCircle, User, MapPin } from "lucide-react";

const leadSchema = z.object({
  name: z.string().min(2, "الاسم مطلوب"),
  phone: z.string().min(10, "رقم الهاتف مطلوب").max(15),
  interestedArea: z.string().optional(),
});

type LeadFormValues = z.infer<typeof leadSchema>;

interface LeadFormProps {
  articleId?: string;
  projectId?: string;
  cityId?: string;
  writerId?: string;
  salesPhone?: string;
  whatsappPhone?: string;
  compact?: boolean;
}

export default function LeadForm({
  articleId,
  projectId,
  cityId,
  writerId,
  salesPhone = "01234567890",
  whatsappPhone = "201234567890",
  compact = false,
}: LeadFormProps) {
  const [submitted, setSubmitted] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
    reset,
  } = useForm<LeadFormValues>({
    resolver: zodResolver(leadSchema),
  });

  const onSubmit = async (data: LeadFormValues) => {
    try {
      const res = await fetch("/api/leads", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          ...data,
          articleId,
          projectId,
          cityId,
          writerId,
          source: articleId ? "ARTICLE" : projectId ? "PROJECT_PAGE" : "FORM",
          referrer: typeof window !== "undefined" ? document.referrer : "",
          utmSource: new URLSearchParams(window.location.search).get("utm_source") || undefined,
          utmMedium: new URLSearchParams(window.location.search).get("utm_medium") || undefined,
          utmCampaign: new URLSearchParams(window.location.search).get("utm_campaign") || undefined,
        }),
      });

      if (!res.ok) throw new Error("فشل الإرسال");
      setSubmitted(true);
      toast.success("تم إرسال طلبك بنجاح! سنتواصل معك قريباً");
      reset();
    } catch {
      toast.error("حدث خطأ، يرجى المحاولة مرة أخرى");
    }
  };

  if (submitted) {
    return (
      <div className="text-center py-6">
        <div className="text-4xl mb-3">✅</div>
        <h3 className="text-lg font-bold text-gray-900 mb-1">تم استلام طلبك!</h3>
        <p className="text-gray-500 text-sm">سيتواصل معك أحد مستشارينا خلال 24 ساعة</p>
        <button
          onClick={() => setSubmitted(false)}
          className="mt-4 text-primary-600 text-sm font-medium hover:underline"
        >
          إرسال طلب آخر
        </button>
      </div>
    );
  }

  return (
    <div>
      {!compact && (
        <div className="mb-5">
          <h3 className="text-base font-black text-gray-900">الحجز والاستفسار</h3>
          <p className="text-xs text-gray-500 mt-1">أرسل بياناتك وسيتواصل معك مستشارنا فوراً</p>
        </div>
      )}

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        <Input
          label="الاسم الكامل"
          placeholder="أدخل اسمك الكامل"
          icon={<User className="h-4 w-4" />}
          error={errors.name?.message}
          {...register("name")}
        />
        <Input
          label="رقم الهاتف"
          type="tel"
          placeholder="01xxxxxxxxx"
          icon={<Phone className="h-4 w-4" />}
          error={errors.phone?.message}
          {...register("phone")}
        />
        <Input
          label="المنطقة المفضلة (اختياري)"
          placeholder="مثال: العاصمة الإدارية"
          icon={<MapPin className="h-4 w-4" />}
          {...register("interestedArea")}
        />

        <Button
          type="submit"
          variant="primary"
          size="lg"
          fullWidth
          loading={isSubmitting}
        >
          طلب استشارة مجانية
        </Button>
      </form>

      <div className="mt-4 space-y-2">
        <div className="flex items-center gap-2 text-gray-400 text-xs">
          <div className="flex-1 h-px bg-gray-200" />
          <span>أو تواصل مباشرة</span>
          <div className="flex-1 h-px bg-gray-200" />
        </div>

        <a
          href={`https://wa.me/${whatsappPhone}`}
          target="_blank"
          rel="noopener noreferrer"
          className="flex items-center justify-center gap-2 w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded-xl transition-all duration-200"
        >
          <MessageCircle className="h-4 w-4" />
          واتساب
        </a>

        <a
          href={`tel:${salesPhone}`}
          className="flex items-center justify-center gap-2 w-full bg-gray-800 hover:bg-gray-900 text-white font-bold py-3 px-4 rounded-xl transition-all duration-200"
        >
          <Phone className="h-4 w-4" />
          {salesPhone}
        </a>
      </div>
    </div>
  );
}
