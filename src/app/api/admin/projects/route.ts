import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { db } from "@/lib/db";
import { z } from "zod";

const projectSchema = z.object({
  name: z.string().min(2),
  nameAr: z.string().optional(),
  slug: z.string().min(2),
  developerId: z.string().min(1),
  cityId: z.string().min(1),
  location: z.string().optional(),
  salesPhone: z.string().optional(),
  minPrice: z.number().optional(),
  minDownPayment: z.number().optional(),
  minInstallment: z.number().optional(),
  minArea: z.number().optional(),
  description: z.string().optional(),
  seoTitle: z.string().optional(),
  seoDescription: z.string().optional(),
  seoKeywords: z.string().optional(),
  images: z.array(z.string()).optional(),
});

export async function GET(req: NextRequest) {
  const session = await auth();

  const { searchParams } = new URL(req.url);
  const published = searchParams.get("published");

  const projects = await db.project.findMany({
    where: {
      isDeleted: false,
      ...(published === "true" ? { isPublished: true } : {}),
    },
    orderBy: { createdAt: "desc" },
    include: {
      developer: { select: { name: true, nameAr: true } },
      city: { select: { nameAr: true, id: true } },
      _count: { select: { leads: true, articles: true } },
    },
  });

  return NextResponse.json({ success: true, data: projects });
}

export async function POST(req: NextRequest) {
  const session = await auth();
  if (!session?.user || !["SUPER_ADMIN", "ADMIN", "ACCOUNT_MANAGER"].includes(session.user.role)) {
    return NextResponse.json({ error: "غير مصرح" }, { status: 401 });
  }

  try {
    const body = await req.json();
    const data = projectSchema.parse(body);

    const existing = await db.project.findUnique({ where: { slug: data.slug } });
    if (existing) {
      return NextResponse.json({ error: "هذا الرابط مستخدم بالفعل" }, { status: 400 });
    }

    const project = await db.project.create({
      data: {
        ...data,
        nameAr: data.nameAr || null,
        location: data.location || null,
        salesPhone: data.salesPhone || "01234567890",
        description: data.description || null,
        images: data.images || [],
      },
    });

    return NextResponse.json({ success: true, data: project }, { status: 201 });
  } catch (error) {
    if (error instanceof z.ZodError) {
      return NextResponse.json({ error: "بيانات غير صالحة", details: error.errors }, { status: 400 });
    }
    return NextResponse.json({ error: "حدث خطأ" }, { status: 500 });
  }
}
