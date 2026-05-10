import { notFound } from "next/navigation";
import Image from "next/image";
import { db } from "@/lib/db";
import Header from "@/components/layout/Header";
import Footer from "@/components/layout/Footer";
import LeadForm from "@/components/forms/LeadForm";
import { MapPin, Building2, DollarSign, Home, ArrowDown } from "lucide-react";

interface PageProps {
  params: Promise<{ slug: string }>;
}

async function getProject(slug: string) {
  try {
    return await db.project.findUnique({
      where: { slug, isPublished: true, isDeleted: false },
      include: {
        developer: { select: { name: true, nameAr: true, logo: true } },
        city: { select: { nameAr: true, id: true } },
        units: { where: { isDeleted: false }, take: 10 },
      },
    });
  } catch {
    return null;
  }
}

export async function generateMetadata({ params }: PageProps) {
  const { slug } = await params;
  const project = await getProject(slug);
  if (!project) return { title: "مشروع غير موجود" };
  return {
    title: project.seoTitle || project.nameAr || project.name,
    description: project.seoDescription || project.description?.slice(0, 160),
  };
}

export default async function ProjectPage({ params }: PageProps) {
  const { slug } = await params;
  const project = await getProject(slug);

  if (!project) notFound();

  const images = project.images?.length ? project.images : [];

  return (
    <>
      <Header />
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="flex flex-col lg:flex-row gap-8">
          {/* Main Content */}
          <div className="flex-1 min-w-0">
            {/* Breadcrumb */}
            <div className="flex items-center gap-2 text-sm text-gray-500 mb-6">
              <a href="/" className="hover:text-gray-900">الرئيسية</a>
              <span>/</span>
              <a href="/projects" className="hover:text-gray-900">المشاريع</a>
              <span>/</span>
              <span className="text-gray-700">{project.nameAr || project.name}</span>
            </div>

            {/* Header */}
            <div className="flex items-start gap-4 mb-6">
              {project.developer.logo && (
                <div className="relative w-16 h-16 rounded-xl overflow-hidden border border-gray-100 flex-shrink-0">
                  <Image
                    src={project.developer.logo}
                    alt={project.developer.nameAr || project.developer.name}
                    fill
                    className="object-contain p-2"
                  />
                </div>
              )}
              <div>
                <h1 className="text-2xl sm:text-3xl font-black text-gray-900">
                  {project.nameAr || project.name}
                </h1>
                <div className="flex flex-wrap items-center gap-3 mt-2">
                  <span className="flex items-center gap-1 text-sm text-gray-500">
                    <Building2 className="h-4 w-4" />
                    {project.developer.nameAr || project.developer.name}
                  </span>
                  <span className="flex items-center gap-1 text-sm text-gray-500">
                    <MapPin className="h-4 w-4" />
                    {project.city.nameAr}
                  </span>
                </div>
              </div>
            </div>

            {/* Images */}
            {images.length > 0 ? (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-8">
                <div className="sm:col-span-2 relative aspect-video rounded-2xl overflow-hidden">
                  <Image src={images[0]} alt={project.name} fill className="object-cover" priority />
                </div>
                {images.slice(1, 3).map((img, i) => (
                  <div key={i} className="relative aspect-video rounded-2xl overflow-hidden">
                    <Image src={img} alt={`${project.name} ${i + 2}`} fill className="object-cover" />
                  </div>
                ))}
              </div>
            ) : (
              <div className="aspect-video bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-2xl flex items-center justify-center mb-8 border border-yellow-100">
                <div className="text-center">
                  <Building2 className="h-16 w-16 text-yellow-300 mx-auto mb-2" />
                  <p className="text-yellow-600 font-medium">صور المشروع</p>
                </div>
              </div>
            )}

            {/* Key Info Cards */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
              {project.minPrice && (
                <div className="bg-white rounded-2xl border border-gray-100 p-4 text-center shadow-sm">
                  <DollarSign className="h-6 w-6 text-primary-500 mx-auto mb-2" />
                  <p className="text-xs text-gray-500 mb-1">السعر يبدأ من</p>
                  <p className="font-black text-gray-900">
                    {project.minPrice.toLocaleString("ar-EG")}
                  </p>
                  <p className="text-xs text-gray-400">ج.م</p>
                </div>
              )}
              {project.minDownPayment && (
                <div className="bg-white rounded-2xl border border-gray-100 p-4 text-center shadow-sm">
                  <ArrowDown className="h-6 w-6 text-green-500 mx-auto mb-2" />
                  <p className="text-xs text-gray-500 mb-1">المقدم يبدأ من</p>
                  <p className="font-black text-gray-900">
                    {project.minDownPayment.toLocaleString("ar-EG")}
                  </p>
                  <p className="text-xs text-gray-400">ج.م</p>
                </div>
              )}
              {project.minInstallment && (
                <div className="bg-white rounded-2xl border border-gray-100 p-4 text-center shadow-sm">
                  <Home className="h-6 w-6 text-blue-500 mx-auto mb-2" />
                  <p className="text-xs text-gray-500 mb-1">القسط يبدأ من</p>
                  <p className="font-black text-gray-900">
                    {project.minInstallment.toLocaleString("ar-EG")}
                  </p>
                  <p className="text-xs text-gray-400">ج.م / شهر</p>
                </div>
              )}
              {project.minArea && (
                <div className="bg-white rounded-2xl border border-gray-100 p-4 text-center shadow-sm">
                  <MapPin className="h-6 w-6 text-purple-500 mx-auto mb-2" />
                  <p className="text-xs text-gray-500 mb-1">المساحة تبدأ من</p>
                  <p className="font-black text-gray-900">{project.minArea}</p>
                  <p className="text-xs text-gray-400">م²</p>
                </div>
              )}
            </div>

            {/* Location */}
            {project.location && (
              <div className="bg-primary-50 border border-primary-100 rounded-2xl p-5 mb-6 flex items-start gap-3">
                <MapPin className="h-5 w-5 text-primary-600 mt-0.5 flex-shrink-0" />
                <div>
                  <p className="font-bold text-gray-900 mb-1">الموقع</p>
                  <p className="text-gray-600 text-sm">{project.location}</p>
                </div>
              </div>
            )}

            {/* Description */}
            {project.description && (
              <div className="mb-8">
                <h2 className="text-xl font-black text-gray-900 mb-4">عن المشروع</h2>
                <div className="text-gray-700 leading-relaxed">
                  {project.description.split("\n").map((line, i) => (
                    <p key={i} className="mb-3">{line}</p>
                  ))}
                </div>
              </div>
            )}

            {/* Units */}
            {project.units.length > 0 && (
              <div className="mb-8">
                <h2 className="text-xl font-black text-gray-900 mb-4">الوحدات المتاحة</h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  {project.units.map((unit) => (
                    <div
                      key={unit.id}
                      className="bg-white border border-gray-100 rounded-xl p-4 flex items-center gap-3 shadow-sm"
                    >
                      <Home className="h-5 w-5 text-primary-500" />
                      <span className="text-gray-800 font-medium">{unit.name}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Mobile Lead Form */}
            <div className="lg:hidden bg-white rounded-2xl border border-gray-100 shadow-md p-6">
              <LeadForm
                projectId={project.id}
                cityId={project.city.id}
                salesPhone={project.salesPhone}
              />
            </div>
          </div>

          {/* Sticky Lead Form */}
          <div className="hidden lg:block w-80 flex-shrink-0">
            <div className="sticky top-24 bg-white rounded-2xl border border-gray-100 shadow-lg p-6">
              <LeadForm
                projectId={project.id}
                cityId={project.city.id}
                salesPhone={project.salesPhone}
              />
            </div>
          </div>
        </div>
      </main>
      <Footer />
    </>
  );
}
