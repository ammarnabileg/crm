"use client";

import { useState, useEffect } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { useRouter } from "next/navigation";
import Input from "@/components/ui/Input";
import Button from "@/components/ui/Button";
import toast from "react-hot-toast";
import { FileText, Image as ImageIcon, Search, AlertCircle } from "lucide-react";

const articleSchema = z.object({
  title: z.string().min(10, "العنوان يجب أن يكون 10 أحرف على الأقل"),
  slug: z.string().min(3, "الرابط مطلوب").regex(/^[a-z0-9-]+$/, "الرابط يجب أن يحتوي على أحرف إنجليزية وأرقام وشرطات فقط"),
  content: z.string().min(200, "المحتوى يجب أن يكون 200 حرف على الأقل"),
  excerpt: z.string().max(300).optional(),
  coverImage: z.string().url("رابط الصورة غير صالح").optional().or(z.literal("")),
  cityId: z.string().optional(),
  projectId: z.string().optional(),
  unit: z.string().optional(),
  seoTitle: z.string().max(70).optional(),
  seoDescription: z.string().max(160).optional(),
  seoKeywords: z.string().optional(),
});

type ArticleFormValues = z.infer<typeof articleSchema>;

interface City { id: string; nameAr: string; }
interface Project { id: string; name: string; cityId: string; }

export default function NewArticlePage() {
  const router = useRouter();
  const [cities, setCities] = useState<City[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [filteredProjects, setFilteredProjects] = useState<Project[]>([]);
  const [activeTab, setActiveTab] = useState<"content" | "seo">("content");

  const {
    register,
    handleSubmit,
    watch,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<ArticleFormValues>({
    resolver: zodResolver(articleSchema),
    defaultValues: { cityId: "", projectId: "" },
  });

  const titleValue = watch("title");
  const cityId = watch("cityId");
  const contentValue = watch("content") || "";

  // Auto-generate slug from title
  useEffect(() => {
    if (titleValue) {
      const slug = titleValue
        .toLowerCase()
        .replace(/\s+/g, "-")
        .replace(/[^؀-ۿa-z0-9-]/g, "")
        .replace(/[؀-ۿ]/g, (char) => {
          const map: Record<string, string> = {
            "ا": "a", "ب": "b", "ت": "t", "ث": "th", "ج": "j",
            "ح": "h", "خ": "kh", "د": "d", "ذ": "th", "ر": "r",
            "ز": "z", "س": "s", "ش": "sh", "ص": "s", "ض": "d",
            "ط": "t", "ظ": "z", "ع": "a", "غ": "gh", "ف": "f",
            "ق": "q", "ك": "k", "ل": "l", "م": "m", "ن": "n",
            "ه": "h", "و": "w", "ي": "y",
          };
          return map[char] || "-";
        })
        .replace(/-+/g, "-")
        .slice(0, 80);
      setValue("slug", slug);
    }
  }, [titleValue, setValue]);

  // Load cities
  useEffect(() => {
    fetch("/api/admin/cities")
      .then((r) => r.json())
      .then((data) => setCities(data.data || []))
      .catch(() => {});
  }, []);

  // Load projects
  useEffect(() => {
    fetch("/api/admin/projects")
      .then((r) => r.json())
      .then((data) => setProjects(data.data || []))
      .catch(() => {});
  }, []);

  // Filter projects by city
  useEffect(() => {
    if (cityId) {
      setFilteredProjects(projects.filter((p) => p.cityId === cityId));
    } else {
      setFilteredProjects(projects);
    }
    setValue("projectId", "");
  }, [cityId, projects, setValue]);

  const onSubmit = async (data: ArticleFormValues) => {
    try {
      const res = await fetch("/api/writer/articles", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });

      const result = await res.json();

      if (!res.ok) {
        toast.error(result.error || "حدث خطأ أثناء الحفظ");
        return;
      }

      toast.success("تم إرسال المقالة للمراجعة بنجاح!");
      router.push("/dashboard/articles");
    } catch {
      toast.error("حدث خطأ، يرجى المحاولة مرة أخرى");
    }
  };

  const charCount = contentValue.length;

  return (
    <div className="p-6 lg:p-8 max-w-4xl">
      <div className="mb-8">
        <h1 className="text-2xl font-black text-gray-900">إضافة مقالة جديدة</h1>
        <p className="text-gray-500 mt-1">اكتب مقالة عقارية مميزة واكسب عمولات من العملاء</p>
      </div>

      {/* Commission Info */}
      <div className="bg-primary-50 border border-primary-200 rounded-2xl p-5 mb-8 flex items-start gap-3">
        <AlertCircle className="h-5 w-5 text-primary-600 mt-0.5 flex-shrink-0" />
        <div>
          <p className="font-bold text-gray-900 text-sm">كيف تكسب العمولة؟</p>
          <p className="text-gray-600 text-sm mt-1">
            عندما يتواصل عميل عبر مقالتك ويتم إغلاق صفقة، ستحصل على 20% من صافي الربح.
            تأكد من إختيار المشروع الصحيح لضمان نسب العمولة.
          </p>
        </div>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        {/* Tabs */}
        <div className="flex border-b border-gray-200">
          <button
            type="button"
            onClick={() => setActiveTab("content")}
            className={`px-5 py-3 font-bold text-sm border-b-2 transition-colors ${
              activeTab === "content"
                ? "border-primary-500 text-primary-700"
                : "border-transparent text-gray-500 hover:text-gray-700"
            }`}
          >
            <span className="flex items-center gap-2">
              <FileText className="h-4 w-4" />
              المحتوى
            </span>
          </button>
          <button
            type="button"
            onClick={() => setActiveTab("seo")}
            className={`px-5 py-3 font-bold text-sm border-b-2 transition-colors ${
              activeTab === "seo"
                ? "border-primary-500 text-primary-700"
                : "border-transparent text-gray-500 hover:text-gray-700"
            }`}
          >
            <span className="flex items-center gap-2">
              <Search className="h-4 w-4" />
              تحسين SEO
            </span>
          </button>
        </div>

        {/* Content Tab */}
        {activeTab === "content" && (
          <div className="space-y-5">
            {/* Classification */}
            <div className="bg-white rounded-2xl border border-gray-100 p-6">
              <h3 className="font-bold text-gray-900 mb-4">تصنيف المقالة</h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="label">المدينة / المنطقة</label>
                  <select className="input" {...register("cityId")}>
                    <option value="">-- اختر المدينة --</option>
                    {cities.map((city) => (
                      <option key={city.id} value={city.id}>
                        {city.nameAr}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="label">المشروع العقاري</label>
                  <select className="input" {...register("projectId")}>
                    <option value="">-- اختر المشروع --</option>
                    {filteredProjects.map((project) => (
                      <option key={project.id} value={project.id}>
                        {project.name}
                      </option>
                    ))}
                  </select>
                </div>
                <Input
                  label="الوحدة (اختياري)"
                  placeholder="مثال: العمارة A - الشقة 3"
                  {...register("unit")}
                />
              </div>
            </div>

            {/* Main Content */}
            <div className="bg-white rounded-2xl border border-gray-100 p-6">
              <h3 className="font-bold text-gray-900 mb-4">محتوى المقالة</h3>
              <div className="space-y-4">
                <Input
                  label="عنوان المقالة *"
                  placeholder="مثال: كل ما تريد معرفته عن مشروع X في العاصمة الإدارية"
                  error={errors.title?.message}
                  {...register("title")}
                />

                <div>
                  <label className="label">
                    الرابط (Slug) *
                    <span className="text-xs text-gray-400 font-normal mr-2">سيظهر في رابط المقالة</span>
                  </label>
                  <div className="flex items-center gap-0">
                    <span className="bg-gray-100 border border-l-0 border-gray-300 rounded-r-xl px-3 py-3 text-gray-500 text-sm flex-shrink-0">
                      /articles/
                    </span>
                    <input
                      className="input rounded-r-none border-r-0"
                      placeholder="article-slug"
                      {...register("slug")}
                    />
                  </div>
                  {errors.slug && (
                    <p className="mt-1.5 text-sm text-red-600">{errors.slug.message}</p>
                  )}
                </div>

                <div>
                  <label className="label">الصورة الرئيسية (رابط URL)</label>
                  <Input
                    placeholder="https://example.com/image.jpg"
                    icon={<ImageIcon className="h-4 w-4" />}
                    error={errors.coverImage?.message}
                    {...register("coverImage")}
                  />
                </div>

                <div>
                  <label className="label">
                    مقتطف المقالة
                    <span className="text-xs text-gray-400 font-normal mr-2">ملخص قصير يظهر في نتائج البحث</span>
                  </label>
                  <textarea
                    className="input h-20 resize-none"
                    placeholder="ملخص مختصر عن المقالة (اختياري)"
                    maxLength={300}
                    {...register("excerpt")}
                  />
                </div>

                <div>
                  <label className="label">
                    محتوى المقالة *
                    <span className={`text-xs font-normal mr-2 ${charCount < 200 ? "text-red-500" : "text-green-600"}`}>
                      ({charCount} حرف {charCount < 200 ? `- تحتاج ${200 - charCount} حرف إضافي` : "- ممتاز!"})
                    </span>
                  </label>
                  <textarea
                    className="input min-h-[400px] resize-y font-arabic text-base leading-relaxed"
                    placeholder="اكتب محتوى مقالتك هنا... استخدم فقرات واضحة ومنسقة لضمان قبول المقالة"
                    {...register("content")}
                  />
                  {errors.content && (
                    <p className="mt-1.5 text-sm text-red-600">{errors.content.message}</p>
                  )}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* SEO Tab */}
        {activeTab === "seo" && (
          <div className="bg-white rounded-2xl border border-gray-100 p-6 space-y-5">
            <div className="flex items-center gap-2 mb-2">
              <Search className="h-5 w-5 text-primary-500" />
              <h3 className="font-bold text-gray-900">إعدادات تحسين محركات البحث</h3>
            </div>

            <div>
              <label className="label">
                عنوان SEO
                <span className="text-xs text-gray-400 font-normal mr-2">(الحد الأقصى 70 حرف)</span>
              </label>
              <Input
                placeholder="عنوان مُحسَّن لمحركات البحث"
                maxLength={70}
                error={errors.seoTitle?.message}
                {...register("seoTitle")}
              />
              <p className="text-xs text-gray-400 mt-1">
                {watch("seoTitle")?.length || 0}/70 حرف
              </p>
            </div>

            <div>
              <label className="label">
                وصف Meta
                <span className="text-xs text-gray-400 font-normal mr-2">(الحد الأقصى 160 حرف)</span>
              </label>
              <textarea
                className="input h-24 resize-none"
                placeholder="وصف مختصر يظهر في نتائج البحث"
                maxLength={160}
                {...register("seoDescription")}
              />
              <p className="text-xs text-gray-400 mt-1">
                {watch("seoDescription")?.length || 0}/160 حرف
              </p>
            </div>

            <Input
              label="الكلمات المفتاحية"
              placeholder="عقارات, شقق, فلل - مفصولة بفواصل"
              hint="أدخل الكلمات المفتاحية مفصولة بفواصل"
              {...register("seoKeywords")}
            />

            {/* SEO Preview */}
            <div className="border border-gray-200 rounded-xl p-4">
              <p className="text-xs text-gray-500 mb-3 font-medium">معاينة في نتائج البحث:</p>
              <div>
                <p className="text-blue-600 font-medium text-base hover:underline cursor-pointer line-clamp-1">
                  {watch("seoTitle") || watch("title") || "عنوان المقالة"}
                </p>
                <p className="text-green-700 text-xs my-1">morbeh.com/articles/{watch("slug") || "article-slug"}</p>
                <p className="text-gray-600 text-sm line-clamp-2">
                  {watch("seoDescription") || watch("excerpt") || "وصف المقالة يظهر هنا..."}
                </p>
              </div>
            </div>
          </div>
        )}

        {/* Submit */}
        <div className="flex items-center gap-4">
          <Button
            type="submit"
            variant="primary"
            size="lg"
            loading={isSubmitting}
          >
            إرسال للمراجعة
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="lg"
            onClick={() => router.back()}
          >
            إلغاء
          </Button>
        </div>
      </form>
    </div>
  );
}
