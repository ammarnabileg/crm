import { notFound } from "next/navigation";
import Image from "next/image";
import { db } from "@/lib/db";
import Header from "@/components/layout/Header";
import Footer from "@/components/layout/Footer";
import LeadForm from "@/components/forms/LeadForm";
import { Calendar, Eye, User, MapPin, Building2 } from "lucide-react";

interface PageProps {
  params: Promise<{ slug: string }>;
}

async function getArticle(slug: string) {
  try {
    const article = await db.article.findUnique({
      where: { slug, status: "APPROVED", isDeleted: false },
      include: {
        author: { select: { id: true, name: true, affiliateCode: true } },
        city: { select: { nameAr: true } },
        project: {
          select: {
            id: true,
            name: true,
            salesPhone: true,
            minPrice: true,
            minDownPayment: true,
          },
        },
        faqItems: { orderBy: { order: "asc" } },
      },
    });
    return article;
  } catch {
    return null;
  }
}

async function getSettings() {
  try {
    const settings = await db.setting.findMany({
      where: { key: { in: ["sales_phone", "whatsapp_phone"] } },
    });
    const map: Record<string, string> = {};
    settings.forEach((s) => (map[s.key] = s.value));
    return map;
  } catch {
    return {};
  }
}

export async function generateMetadata({ params }: PageProps) {
  const { slug } = await params;
  const article = await getArticle(slug);
  if (!article) return { title: "مقال غير موجود" };
  return {
    title: article.seoTitle || article.title,
    description: article.seoDescription || article.excerpt,
    keywords: article.seoKeywords,
  };
}

export default async function ArticlePage({ params }: PageProps) {
  const { slug } = await params;
  const [article, settings] = await Promise.all([getArticle(slug), getSettings()]);

  if (!article) notFound();

  // Increment view count
  db.article.update({
    where: { id: article.id },
    data: { viewCount: { increment: 1 } },
  }).catch(() => {});

  const salesPhone = article.project?.salesPhone || settings["sales_phone"] || "01234567890";
  const whatsappPhone = settings["whatsapp_phone"] || "201234567890";

  return (
    <>
      <Header />
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="flex flex-col lg:flex-row gap-8">
          {/* Main Content - 80% */}
          <div className="flex-1 min-w-0">
            {/* Breadcrumb */}
            <div className="flex items-center gap-2 text-sm text-gray-500 mb-6">
              <a href="/" className="hover:text-gray-900">الرئيسية</a>
              <span>/</span>
              <a href="/articles" className="hover:text-gray-900">المقالات</a>
              {article.city && (
                <>
                  <span>/</span>
                  <span className="text-gray-700">{article.city.nameAr}</span>
                </>
              )}
            </div>

            {/* Tags */}
            <div className="flex flex-wrap gap-2 mb-4">
              {article.city && (
                <span className="bg-primary-100 text-primary-700 text-xs font-bold px-3 py-1 rounded-full flex items-center gap-1">
                  <MapPin className="h-3 w-3" />
                  {article.city.nameAr}
                </span>
              )}
              {article.project && (
                <span className="bg-blue-100 text-blue-700 text-xs font-bold px-3 py-1 rounded-full flex items-center gap-1">
                  <Building2 className="h-3 w-3" />
                  {article.project.name}
                </span>
              )}
            </div>

            {/* Title */}
            <h1 className="text-2xl sm:text-3xl lg:text-4xl font-black text-gray-900 leading-tight mb-4">
              {article.title}
            </h1>

            {/* Meta */}
            <div className="flex flex-wrap items-center gap-5 text-sm text-gray-500 mb-6 pb-6 border-b border-gray-100">
              <span className="flex items-center gap-1.5">
                <User className="h-4 w-4" />
                {article.author.name}
              </span>
              <span className="flex items-center gap-1.5">
                <Calendar className="h-4 w-4" />
                {new Date(article.publishedAt || article.createdAt).toLocaleDateString("ar-EG", {
                  year: "numeric",
                  month: "long",
                  day: "numeric",
                })}
              </span>
              <span className="flex items-center gap-1.5">
                <Eye className="h-4 w-4" />
                {article.viewCount.toLocaleString("ar-EG")} مشاهدة
              </span>
            </div>

            {/* Cover Image */}
            {article.coverImage && (
              <div className="relative aspect-video rounded-2xl overflow-hidden mb-8">
                <Image
                  src={article.coverImage}
                  alt={article.title}
                  fill
                  className="object-cover"
                  priority
                />
              </div>
            )}

            {/* Excerpt */}
            {article.excerpt && (
              <div className="bg-primary-50 border-r-4 border-primary-400 p-5 rounded-xl mb-8">
                <p className="text-gray-800 font-medium leading-relaxed">{article.excerpt}</p>
              </div>
            )}

            {/* Content */}
            <div className="prose prose-lg max-w-none text-gray-800 leading-relaxed mb-8"
              dangerouslySetInnerHTML={{ __html: article.content.replace(/\n/g, "<br />") }}
            />

            {/* Mobile Lead Form */}
            <div className="lg:hidden bg-white rounded-2xl border border-gray-100 shadow-md p-6 mb-8">
              <LeadForm
                articleId={article.id}
                projectId={article.project?.id}
                cityId={article.cityId || undefined}
                writerId={article.author.id}
                salesPhone={salesPhone}
                whatsappPhone={whatsappPhone}
              />
            </div>

            {/* FAQ Section */}
            {article.faqItems.length > 0 && (
              <div className="mb-8">
                <h2 className="text-xl font-black text-gray-900 mb-4">الأسئلة الشائعة</h2>
                <div className="space-y-4">
                  {article.faqItems.map((faq, i) => (
                    <details
                      key={faq.id}
                      className="group bg-white rounded-xl border border-gray-100 shadow-sm"
                    >
                      <summary className="flex items-center justify-between p-5 cursor-pointer font-bold text-gray-900 list-none">
                        <span>{faq.question}</span>
                        <span className="text-primary-500 text-lg font-black group-open:rotate-45 transition-transform">+</span>
                      </summary>
                      <div className="px-5 pb-5 text-gray-600 leading-relaxed">
                        {faq.answer}
                      </div>
                    </details>
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* Sticky Lead Form - 20% */}
          <div className="hidden lg:block w-80 flex-shrink-0">
            <div className="sticky top-24">
              <div className="bg-white rounded-2xl border border-gray-100 shadow-lg p-6">
                <LeadForm
                  articleId={article.id}
                  projectId={article.project?.id}
                  cityId={article.cityId || undefined}
                  writerId={article.author.id}
                  salesPhone={salesPhone}
                  whatsappPhone={whatsappPhone}
                />
              </div>

              {article.project && (
                <div className="mt-4 bg-primary-50 border border-primary-100 rounded-2xl p-5">
                  <h4 className="font-bold text-gray-900 mb-3">تفاصيل المشروع</h4>
                  <div className="space-y-2 text-sm">
                    <div className="flex justify-between">
                      <span className="text-gray-500">اسم المشروع</span>
                      <span className="font-bold">{article.project.name}</span>
                    </div>
                    {article.project.minPrice && (
                      <div className="flex justify-between">
                        <span className="text-gray-500">السعر يبدأ من</span>
                        <span className="font-bold text-primary-700">
                          {article.project.minPrice.toLocaleString("ar-EG")} ج.م
                        </span>
                      </div>
                    )}
                    {article.project.minDownPayment && (
                      <div className="flex justify-between">
                        <span className="text-gray-500">المقدم يبدأ من</span>
                        <span className="font-bold">
                          {article.project.minDownPayment.toLocaleString("ar-EG")} ج.م
                        </span>
                      </div>
                    )}
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      </main>
      <Footer />
    </>
  );
}
