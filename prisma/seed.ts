import { PrismaClient } from "@prisma/client";
import bcrypt from "bcryptjs";

const prisma = new PrismaClient();

async function main() {
  console.log("🌱 Starting seed...");

  // Create Egypt country
  const egypt = await prisma.country.upsert({
    where: { code: "EG" },
    update: {},
    create: {
      name: "Egypt",
      nameAr: "مصر",
      code: "EG",
      isActive: true,
    },
  });
  console.log("✅ Country created:", egypt.nameAr);

  // Create cities
  const citiesData = [
    { name: "New Administrative Capital", nameAr: "العاصمة الإدارية الجديدة" },
    { name: "6th of October", nameAr: "السادس من أكتوبر" },
    { name: "Sheikh Zayed", nameAr: "الشيخ زايد" },
    { name: "New Cairo", nameAr: "القاهرة الجديدة" },
    { name: "Madinaty", nameAr: "مدينتي" },
    { name: "Obour City", nameAr: "مدينة العبور" },
    { name: "Badr City", nameAr: "مدينة بدر" },
    { name: "Shorouk City", nameAr: "مدينة الشروق" },
  ];

  const cities: Record<string, string> = {};
  for (const city of citiesData) {
    const created = await prisma.city.upsert({
      where: { id: city.name.toLowerCase().replace(/\s+/g, "-") },
      update: {},
      create: {
        name: city.name,
        nameAr: city.nameAr,
        countryId: egypt.id,
        isActive: true,
      },
    });
    cities[city.name] = created.id;
    console.log("✅ City created:", city.nameAr);
  }

  // Create admin user
  const adminPassword = await bcrypt.hash("admin123456", 12);
  const admin = await prisma.user.upsert({
    where: { email: "admin@morbeh.com" },
    update: {},
    create: {
      name: "مدير النظام",
      email: "admin@morbeh.com",
      password: adminPassword,
      role: "SUPER_ADMIN",
      phone: "01234567890",
      isActive: true,
    },
  });
  console.log("✅ Admin created:", admin.email);

  // Create writer user
  const writerPassword = await bcrypt.hash("writer123456", 12);
  const writer = await prisma.user.upsert({
    where: { email: "writer@morbeh.com" },
    update: {},
    create: {
      name: "أحمد محمد",
      email: "writer@morbeh.com",
      password: writerPassword,
      role: "WRITER",
      phone: "01012345678",
      isActive: true,
    },
  });
  console.log("✅ Writer created:", writer.email);

  // Create a developer company
  const developer = await prisma.developerCompany.create({
    data: {
      name: "Emaar Misr",
      nameAr: "إعمار مصر",
      isActive: true,
    },
  });
  console.log("✅ Developer company created:", developer.nameAr);

  // Create a broker company
  const brokerCompany = await prisma.brokerCompany.create({
    data: {
      name: "Top Real Estate",
      nameAr: "توب للعقارات",
      phone: "01234567890",
      email: "info@topre.com",
      commissionRate: 5,
      isActive: true,
    },
  });
  console.log("✅ Broker company created:", brokerCompany.nameAr);

  // Create a sample project
  const newCairoId = Object.values(cities)[3] || Object.values(cities)[0];
  const project = await prisma.project.create({
    data: {
      name: "Uptown Cairo",
      nameAr: "أبتاون القاهرة",
      slug: "uptown-cairo",
      developerId: developer.id,
      cityId: newCairoId,
      location: "على بعد 5 كيلومتر من الجامعة الأمريكية",
      salesPhone: "01234567890",
      minPrice: 2000000,
      minDownPayment: 400000,
      minInstallment: 20000,
      minArea: 120,
      description: "مشروع سكني فاخر في قلب القاهرة الجديدة، يضم شققاً ومنازل متعددة الأنواع بأسعار تنافسية وخطط سداد مرنة.",
      isPublished: true,
      images: [],
    },
  });
  console.log("✅ Project created:", project.nameAr);

  // Create a sample article
  const article = await prisma.article.create({
    data: {
      title: "كل ما تريد معرفته عن مشروع أبتاون القاهرة",
      slug: "uptown-cairo-guide",
      content: `
        يُعدّ مشروع أبتاون القاهرة من أبرز المشاريع العقارية في منطقة القاهرة الجديدة.
        يمتد المشروع على مساحة واسعة ويضم وحدات سكنية متنوعة تناسب مختلف الاحتياجات.

        المميزات:
        - موقع استراتيجي في قلب القاهرة الجديدة
        - تصاميم عصرية وبنية تحتية متطورة
        - أسعار تنافسية وخطط سداد مرنة
        - مرافق وخدمات متكاملة

        الأسعار تبدأ من 2,000,000 جنيه مصري بمقدم 20% فقط.
      `,
      excerpt: "دليلك الشامل لمشروع أبتاون القاهرة - المميزات والأسعار وخطط السداد",
      authorId: writer.id,
      projectId: project.id,
      cityId: newCairoId,
      status: "APPROVED",
      publishedAt: new Date(),
    },
  });
  console.log("✅ Article created:", article.title);

  // Create global FAQs
  const faqs = [
    {
      question: "كيف يمكنني التواصل مع مستشار عقاري؟",
      answer: "يمكنك التواصل معنا عبر النموذج الموجود في الصفحة، أو الاتصال المباشر، أو عبر واتساب.",
    },
    {
      question: "هل الاستشارة العقارية مجانية؟",
      answer: "نعم، الاستشارة العقارية الأولى مجانية تماماً لجميع العملاء.",
    },
    {
      question: "ما هي خطط التمويل المتاحة؟",
      answer: "تتوفر خطط تمويل متنوعة تصل إلى 20 سنة بأسعار فائدة تنافسية.",
    },
  ];

  for (const faq of faqs) {
    await prisma.globalFaq.create({ data: faq });
  }
  console.log("✅ Global FAQs created");

  // Create site settings
  const settings = [
    { key: "sales_phone", value: "01234567890", group: "contact" },
    { key: "whatsapp_phone", value: "201234567890", group: "contact" },
    { key: "commission_rate", value: "20", group: "business" },
    { key: "site_name", value: "مربح", group: "general" },
    { key: "site_email", value: "info@morbeh.com", group: "contact" },
  ];

  for (const setting of settings) {
    await prisma.setting.upsert({
      where: { key: setting.key },
      update: { value: setting.value },
      create: setting,
    });
  }
  console.log("✅ Settings created");

  console.log("\n🎉 Seed completed successfully!");
  console.log("\n📧 Admin credentials:");
  console.log("   Email: admin@morbeh.com");
  console.log("   Password: admin123456");
  console.log("\n📧 Writer credentials:");
  console.log("   Email: writer@morbeh.com");
  console.log("   Password: writer123456");
}

main()
  .catch((e) => {
    console.error("❌ Seed failed:", e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
