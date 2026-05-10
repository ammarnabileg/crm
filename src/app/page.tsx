import Link from "next/link";
import Image from "next/image";
import { Search, Phone, MessageCircle, MapPin, ChevronLeft } from "lucide-react";
import Header from "@/components/layout/Header";
import Footer from "@/components/layout/Footer";
import LeadForm from "@/components/forms/LeadForm";
import { db } from "@/lib/db";

async function getCities() {
  try {
    return await db.city.findMany({
      where: { isActive: true, isDeleted: false },
      take: 8,
      orderBy: { createdAt: "asc" },
    });
  } catch {
    return [];
  }
}

async function getLatestArticles() {
  try {
    return await db.article.findMany({
      where: { status: "APPROVED", isDeleted: false },
      take: 6,
      orderBy: { publishedAt: "desc" },
      include: {
        author: { select: { name: true } },
        city: { select: { nameAr: true } },
        project: { select: { name: true } },
      },
    });
  } catch {
    return [];
  }
}

export default async function HomePage() {
  const [cities, articles] = await Promise.all([getCities(), getLatestArticles()]);

  return (
    <>
      <Header />
      <main>
        {/* Hero Section */}
        <section className="bg-hero min-h-[500px] flex items-center relative overflow-hidden">
          <div className="absolute inset-0 opacity-10">
            <div className="absolute top-10 right-10 w-40 h-40 bg-yellow-600 rounded-full blur-3xl" />
            <div className="absolute bottom-10 left-10 w-64 h-64 bg-yellow-800 rounded-full blur-3xl" />
          </div>
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 w-full relative z-10">
            <div className="max-w-2xl">
              <div className="inline-block bg-black/10 text-gray-900 text-sm font-bold px-4 py-1.5 rounded-full mb-6">
                🏠 منصة العقارات الأولى في مصر
              </div>
              <h1 className="text-4xl sm:text-5xl font-black text-gray-900 leading-tight mb-4">
                ابحث عن عقارك المميز
              </h1>
              <p className="text-xl text-gray-800 font-medium mb-8">
                نساعدك في إيجاد أفضل العقارات في مصر بأفضل الأسعار وبضمان الحصول على أفضل صفقة
              </p>

              {/* Search Box */}
              <div className="bg-white rounded-2xl shadow-xl p-4 flex flex-col sm:flex-row gap-3">
                <div className="flex-1 flex items-center gap-3 border border-gray-200 rounded-xl px-4 py-3">
                  <Search className="h-5 w-5 text-gray-400 flex-shrink-0" />
                  <input
                    type="text"
                    placeholder="اسم المشروع أو المنطقة..."
                    className="flex-1 outline-none text-gray-900 placeholder-gray-400 text-sm bg-transparent"
                  />
                </div>
                <select className="border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-700 outline-none bg-transparent min-w-36">
                  <option value="">كل الأنواع</option>
                  <option value="residential">سكني</option>
                  <option value="commercial">تجاري</option>
                </select>
                <button className="btn-primary text-sm px-6 py-3">
                  بحث
                </button>
              </div>
            </div>
          </div>
        </section>

        {/* Stats Bar */}
        <section className="bg-gray-900 py-6">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="grid grid-cols-3 gap-6 text-center">
              <div>
                <p className="text-primary-400 text-2xl font-black">500+</p>
                <p className="text-gray-400 text-sm mt-1">مشروع عقاري</p>
              </div>
              <div>
                <p className="text-primary-400 text-2xl font-black">50+</p>
                <p className="text-gray-400 text-sm mt-1">شركة وسيطة</p>
              </div>
              <div>
                <p className="text-primary-400 text-2xl font-black">10,000+</p>
                <p className="text-gray-400 text-sm mt-1">عميل راضٍ</p>
              </div>
            </div>
          </div>
        </section>

        {/* Cities Section */}
        <section className="py-16 bg-gray-50">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="flex items-center justify-between mb-8">
              <div>
                <h2 className="text-2xl sm:text-3xl font-black text-gray-900">اختر منطقتك</h2>
                <p className="text-gray-500 mt-1">استكشف أفضل المناطق العقارية في مصر</p>
              </div>
              <Link
                href="/projects"
                className="flex items-center gap-1 text-primary-600 font-bold text-sm hover:text-primary-700"
              >
                عرض الكل
                <ChevronLeft className="h-4 w-4" />
              </Link>
            </div>

            {cities.length > 0 ? (
              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                {cities.map((city) => (
                  <Link
                    key={city.id}
                    href={`/projects?city=${city.id}`}
                    className="group relative bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-all duration-300 border border-gray-100"
                  >
                    <div className="aspect-[4/3] relative bg-gradient-to-br from-yellow-100 to-yellow-200">
                      {city.image ? (
                        <Image
                          src={city.image}
                          alt={city.nameAr}
                          fill
                          className="object-cover group-hover:scale-105 transition-transform duration-300"
                        />
                      ) : (
                        <div className="absolute inset-0 flex items-center justify-center">
                          <MapPin className="h-12 w-12 text-yellow-400" />
                        </div>
                      )}
                      <div className="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent" />
                    </div>
                    <div className="absolute bottom-0 left-0 right-0 p-4">
                      <h3 className="text-white font-bold text-sm">{city.nameAr}</h3>
                    </div>
                  </Link>
                ))}
              </div>
            ) : (
              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                {["العاصمة الإدارية", "6 أكتوبر", "الشيخ زايد", "مدينة نصر", "القاهرة الجديدة", "المعادي", "الرحاب", "التجمع الخامس"].map((name) => (
                  <div
                    key={name}
                    className="relative bg-gradient-to-br from-yellow-100 to-yellow-200 rounded-2xl overflow-hidden shadow-sm border border-yellow-100 aspect-[4/3] flex items-end"
                  >
                    <div className="absolute inset-0 flex items-center justify-center">
                      <MapPin className="h-10 w-10 text-yellow-500" />
                    </div>
                    <div className="relative w-full bg-gradient-to-t from-black/40 to-transparent p-4">
                      <h3 className="text-white font-bold text-sm">{name}</h3>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </section>

        {/* Latest Articles */}
        <section className="py-16 bg-white">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="flex items-center justify-between mb-8">
              <div>
                <h2 className="text-2xl sm:text-3xl font-black text-gray-900">
                  آخر التطورات العقارية
                </h2>
                <p className="text-gray-500 mt-1">اطلع على أحدث المقالات والتقارير العقارية</p>
              </div>
              <Link
                href="/articles"
                className="flex items-center gap-1 text-primary-600 font-bold text-sm hover:text-primary-700"
              >
                جميع المقالات
                <ChevronLeft className="h-4 w-4" />
              </Link>
            </div>

            {articles.length > 0 ? (
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                {articles.map((article) => (
                  <Link
                    key={article.id}
                    href={`/articles/${article.slug}`}
                    className="group bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-lg transition-all duration-300"
                  >
                    <div className="aspect-video bg-gradient-to-br from-gray-100 to-gray-200 relative">
                      {article.coverImage ? (
                        <Image
                          src={article.coverImage}
                          alt={article.title}
                          fill
                          className="object-cover group-hover:scale-105 transition-transform duration-300"
                        />
                      ) : (
                        <div className="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-yellow-50 to-yellow-100">
                          <span className="text-4xl">🏠</span>
                        </div>
                      )}
                    </div>
                    <div className="p-5">
                      {article.city && (
                        <span className="inline-block bg-primary-100 text-primary-700 text-xs font-bold px-2 py-0.5 rounded-full mb-3">
                          {article.city.nameAr}
                        </span>
                      )}
                      <h3 className="font-bold text-gray-900 line-clamp-2 group-hover:text-primary-700 transition-colors">
                        {article.title}
                      </h3>
                      {article.excerpt && (
                        <p className="text-gray-500 text-sm mt-2 line-clamp-2">{article.excerpt}</p>
                      )}
                      <div className="mt-4 flex items-center justify-between text-xs text-gray-400">
                        <span>{article.author.name}</span>
                        <span>{new Date(article.publishedAt || article.createdAt).toLocaleDateString("ar-EG")}</span>
                      </div>
                    </div>
                  </Link>
                ))}
              </div>
            ) : (
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                {Array.from({ length: 3 }).map((_, i) => (
                  <div key={i} className="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                    <div className="aspect-video bg-gradient-to-br from-yellow-50 to-yellow-100 flex items-center justify-center">
                      <span className="text-4xl">🏗️</span>
                    </div>
                    <div className="p-5">
                      <div className="h-4 bg-gray-100 rounded mb-3 w-20" />
                      <div className="h-5 bg-gray-100 rounded mb-2" />
                      <div className="h-5 bg-gray-100 rounded w-3/4" />
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </section>

        {/* CTA Section with Lead Form */}
        <section className="py-16 bg-gray-50">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="bg-gradient-to-br from-gray-900 to-gray-800 rounded-3xl overflow-hidden shadow-2xl">
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-0">
                {/* Left: Text */}
                <div className="p-10 lg:p-14 flex flex-col justify-center">
                  <div className="inline-block bg-primary-500 text-gray-900 text-xs font-black px-3 py-1 rounded-full mb-6 w-fit">
                    استشارة مجانية
                  </div>
                  <h2 className="text-3xl sm:text-4xl font-black text-white leading-tight mb-4">
                    هل تبحث عن عقارك المثالي؟
                  </h2>
                  <p className="text-gray-300 text-lg mb-8">
                    فريقنا من خبراء العقارات جاهز لمساعدتك في اتخاذ أفضل قرار استثماري.
                  </p>
                  <div className="flex flex-col sm:flex-row gap-3">
                    <a
                      href="https://wa.me/201234567890"
                      target="_blank"
                      rel="noopener noreferrer"
                      className="flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 text-white font-bold py-3.5 px-6 rounded-xl transition-all"
                    >
                      <MessageCircle className="h-5 w-5" />
                      واتساب
                    </a>
                    <a
                      href="tel:01234567890"
                      className="flex items-center justify-center gap-2 bg-gray-700 hover:bg-gray-600 text-white font-bold py-3.5 px-6 rounded-xl transition-all"
                    >
                      <Phone className="h-5 w-5" />
                      اتصل بنا
                    </a>
                  </div>
                </div>

                {/* Right: Lead Form */}
                <div className="bg-white p-8 lg:p-10">
                  <LeadForm />
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* Why Morbeh */}
        <section className="py-16 bg-white">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="text-center mb-12">
              <h2 className="text-2xl sm:text-3xl font-black text-gray-900 mb-3">لماذا مربح؟</h2>
              <p className="text-gray-500">نقدم لك تجربة عقارية متكاملة ومميزة</p>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
              {[
                {
                  icon: "🎯",
                  title: "دقة في الاختيار",
                  desc: "نساعدك في اختيار العقار المناسب لاحتياجاتك وميزانيتك",
                },
                {
                  icon: "🤝",
                  title: "شركاء موثوقون",
                  desc: "نتعاون مع أفضل الشركات العقارية والوسطاء المعتمدين",
                },
                {
                  icon: "💰",
                  title: "أفضل الأسعار",
                  desc: "نضمن لك أفضل عروض الأسعار وأفضل خطط التمويل",
                },
              ].map((item) => (
                <div
                  key={item.title}
                  className="text-center p-8 bg-gray-50 rounded-2xl border border-gray-100 hover:border-primary-200 transition-colors"
                >
                  <div className="text-5xl mb-4">{item.icon}</div>
                  <h3 className="text-lg font-black text-gray-900 mb-2">{item.title}</h3>
                  <p className="text-gray-500 text-sm">{item.desc}</p>
                </div>
              ))}
            </div>
          </div>
        </section>
      </main>
      <Footer />
    </>
  );
}
