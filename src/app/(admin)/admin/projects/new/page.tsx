"use client";

import { useState, useEffect } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { useRouter } from "next/navigation";
import Input from "@/components/ui/Input";
import Button from "@/components/ui/Button";
import Card from "@/components/ui/Card";
import toast from "react-hot-toast";
import { Plus, ChevronLeft } from "lucide-react";

const projectSchema = z.object({
  name: z.string().min(2, "اسم المشروع مطلوب"),
  nameAr: z.string().optional(),
  slug: z.string().min(2, "الرابط مطلوب").regex(/^[a-z0-9-]+$/),
  developerId: z.string().min(1, "شركة التطوير مطلوبة"),
  cityId: z.string().min(1, "المدينة مطلوبة"),
  location: z.string().optional(),
  salesPhone: z.string().optional(),
  minPrice: z.number().optional(),
  minDownPayment: z.number().optional(),
  minInstallment: z.number().optional(),
  minArea: z.number().optional(),
  description: z.string().optional(),
  seoTitle: z.string().max(70).optional(),
  seoDescription: z.string().max(160).optional(),
  seoKeywords: z.string().optional(),
});

type ProjectFormValues = z.infer<typeof projectSchema>;

interface City { id: string; nameAr: string; }
interface Developer { id: string; name: string; nameAr?: string; }

export default function NewProjectPage() {
  const router = useRouter();
  const [cities, setCities] = useState<City[]>([]);
  const [developers, setDevelopers] = useState<Developer[]>([]);
  const [showNewDeveloper, setShowNewDeveloper] = useState(false);
  const [newDevName, setNewDevName] = useState("");
  const [addingDev, setAddingDev] = useState(false);

  const {
    register,
    handleSubmit,
    watch,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<ProjectFormValues>({
    resolver: zodResolver(projectSchema),
    defaultValues: { salesPhone: "01234567890" },
  });

  const nameValue = watch("name");

  useEffect(() => {
    if (nameValue) {
      const slug = nameValue
        .toLowerCase()
        .replace(/\s+/g, "-")
        .replace(/[^a-z0-9-]/g, "")
        .replace(/-+/g, "-")
        .slice(0, 80);
      setValue("slug", slug);
    }
  }, [nameValue, setValue]);

  useEffect(() => {
    Promise.all([
      fetch("/api/admin/cities").then((r) => r.json()),
      fetch("/api/admin/companies?type=developer").then((r) => r.json()),
    ]).then(([citiesData, devsData]) => {
      setCities(citiesData.data || []);
      setDevelopers(devsData.data || []);
    }).catch(() => {});
  }, []);

  const addDeveloper = async () => {
    if (!newDevName.trim()) return;
    setAddingDev(true);
    try {
      const res = await fetch("/api/admin/companies", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ type: "developer", name: newDevName }),
      });
      const data = await res.json();
      if (data.data) {
        setDevelopers((prev) => [...prev, data.data]);
        setValue("developerId", data.data.id);
        toast.success("تم إضافة شركة التطوير");
        setShowNewDeveloper(false);
        setNewDevName("");
      }
    } catch {
      toast.error("حدث خطأ");
    } finally {
      setAddingDev(false);
    }
  };

  const onSubmit = async (data: ProjectFormValues) => {
    try {
      const res = await fetch("/api/admin/projects", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });
      const result = await res.json();
      if (!res.ok) {
        toast.error(result.error || "حدث خطأ");
        return;
      }
      toast.success("تم إضافة المشروع بنجاح");
      router.push("/admin/projects");
    } catch {
      toast.error("حدث خطأ");
    }
  };

  return (
    <div className="p-6 lg:p-8 max-w-4xl">
      <div className="flex items-center gap-4 mb-8">
        <button onClick={() => router.back()} className="p-2 hover:bg-gray-100 rounded-xl transition-colors">
          <ChevronLeft className="h-5 w-5 rotate-180" />
        </button>
        <div>
          <h1 className="text-2xl font-black text-gray-900">إضافة مشروع جديد</h1>
          <p className="text-gray-500 mt-1">أضف مشروعًا عقاريًا جديدًا للمنصة</p>
        </div>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        {/* Basic Info */}
        <Card>
          <h3 className="font-bold text-gray-900 mb-5">معلومات المشروع الأساسية</h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label="اسم المشروع (إنجليزي)"
              placeholder="Project Name"
              error={errors.name?.message}
              {...register("name")}
            />
            <Input
              label="اسم المشروع (عربي)"
              placeholder="اسم المشروع"
              error={errors.nameAr?.message}
              {...register("nameAr")}
            />
            <div>
              <label className="label">الرابط (Slug)</label>
              <div className="flex items-center">
                <span className="bg-gray-100 border border-l-0 border-gray-300 rounded-r-xl px-3 py-3 text-gray-500 text-sm flex-shrink-0">
                  /projects/
                </span>
                <input
                  className="input rounded-r-none border-r-0"
                  placeholder="project-slug"
                  {...register("slug")}
                />
              </div>
              {errors.slug && <p className="text-sm text-red-600 mt-1">{errors.slug.message}</p>}
            </div>
            <div>
              <label className="label">المدينة *</label>
              <select className="input" {...register("cityId")}>
                <option value="">-- اختر المدينة --</option>
                {cities.map((city) => (
                  <option key={city.id} value={city.id}>{city.nameAr}</option>
                ))}
              </select>
              {errors.cityId && <p className="text-sm text-red-600 mt-1">{errors.cityId.message}</p>}
            </div>

            {/* Developer with inline add */}
            <div className="sm:col-span-2">
              <label className="label">شركة التطوير *</label>
              <div className="flex gap-2">
                <select className="input flex-1" {...register("developerId")}>
                  <option value="">-- اختر شركة التطوير --</option>
                  {developers.map((dev) => (
                    <option key={dev.id} value={dev.id}>{dev.nameAr || dev.name}</option>
                  ))}
                </select>
                <button
                  type="button"
                  onClick={() => setShowNewDeveloper(!showNewDeveloper)}
                  className="flex items-center gap-1 px-3 py-2.5 border border-gray-300 rounded-xl text-sm text-gray-600 hover:bg-gray-50 transition-colors flex-shrink-0"
                >
                  <Plus className="h-4 w-4" />
                  جديد
                </button>
              </div>
              {errors.developerId && (
                <p className="text-sm text-red-600 mt-1">{errors.developerId.message}</p>
              )}
              {showNewDeveloper && (
                <div className="flex gap-2 mt-2">
                  <input
                    type="text"
                    className="input flex-1"
                    placeholder="اسم الشركة الجديدة"
                    value={newDevName}
                    onChange={(e) => setNewDevName(e.target.value)}
                  />
                  <Button type="button" variant="primary" size="sm" onClick={addDeveloper} loading={addingDev}>
                    إضافة
                  </Button>
                </div>
              )}
            </div>
          </div>
        </Card>

        {/* Pricing */}
        <Card>
          <h3 className="font-bold text-gray-900 mb-5">معلومات التسعير</h3>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
              <label className="label">السعر يبدأ من (ج.م)</label>
              <input
                type="number"
                className="input"
                placeholder="1500000"
                {...register("minPrice", { valueAsNumber: true })}
              />
            </div>
            <div>
              <label className="label">المقدم يبدأ من (ج.م)</label>
              <input
                type="number"
                className="input"
                placeholder="300000"
                {...register("minDownPayment", { valueAsNumber: true })}
              />
            </div>
            <div>
              <label className="label">القسط يبدأ من (ج.م)</label>
              <input
                type="number"
                className="input"
                placeholder="15000"
                {...register("minInstallment", { valueAsNumber: true })}
              />
            </div>
            <div>
              <label className="label">المساحة تبدأ من (م²)</label>
              <input
                type="number"
                className="input"
                placeholder="120"
                {...register("minArea", { valueAsNumber: true })}
              />
            </div>
          </div>
          <div className="mt-4">
            <Input
              label="هاتف المبيعات"
              placeholder="01234567890"
              {...register("salesPhone")}
            />
          </div>
        </Card>

        {/* Details */}
        <Card>
          <h3 className="font-bold text-gray-900 mb-5">التفاصيل والموقع</h3>
          <div className="space-y-4">
            <Input
              label="الموقع / الوصف الجغرافي"
              placeholder="على بعد 10 كيلو من..."
              {...register("location")}
            />
            <div>
              <label className="label">وصف المشروع</label>
              <textarea
                className="input min-h-40 resize-y"
                placeholder="وصف تفصيلي عن المشروع..."
                {...register("description")}
              />
            </div>
          </div>
        </Card>

        {/* SEO */}
        <Card>
          <h3 className="font-bold text-gray-900 mb-5">تحسين محركات البحث (SEO)</h3>
          <div className="space-y-4">
            <Input
              label="عنوان SEO (70 حرف)"
              maxLength={70}
              {...register("seoTitle")}
            />
            <div>
              <label className="label">وصف Meta (160 حرف)</label>
              <textarea
                className="input h-20 resize-none"
                maxLength={160}
                {...register("seoDescription")}
              />
            </div>
            <Input
              label="الكلمات المفتاحية"
              placeholder="عقارات, شقق, - مفصولة بفواصل"
              {...register("seoKeywords")}
            />
          </div>
        </Card>

        <div className="flex items-center gap-4">
          <Button type="submit" variant="primary" size="lg" loading={isSubmitting}>
            إضافة المشروع
          </Button>
          <Button type="button" variant="ghost" size="lg" onClick={() => router.back()}>
            إلغاء
          </Button>
        </div>
      </form>
    </div>
  );
}
