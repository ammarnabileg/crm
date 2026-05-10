import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { db } from "@/lib/db";
import { z } from "zod";

const articleSchema = z.object({
  title: z.string().min(10),
  slug: z.string().min(3),
  content: z.string().min(200),
  excerpt: z.string().optional(),
  coverImage: z.string().optional(),
  cityId: z.string().optional(),
  projectId: z.string().optional(),
  unit: z.string().optional(),
  seoTitle: z.string().optional(),
  seoDescription: z.string().optional(),
  seoKeywords: z.string().optional(),
});

export async function GET(req: NextRequest) {
  const session = await auth();
  if (!session?.user) {
    return NextResponse.json({ error: "غير مصرح" }, { status: 401 });
  }

  const articles = await db.article.findMany({
    where: { authorId: session.user.id, isDeleted: false },
    orderBy: { createdAt: "desc" },
    include: {
      city: { select: { nameAr: true } },
      project: { select: { name: true } },
    },
  });

  return NextResponse.json({ success: true, data: articles });
}

export async function POST(req: NextRequest) {
  const session = await auth();
  if (!session?.user) {
    return NextResponse.json({ error: "غير مصرح" }, { status: 401 });
  }

  try {
    const body = await req.json();
    const data = articleSchema.parse(body);

    // Check slug uniqueness
    const existing = await db.article.findUnique({ where: { slug: data.slug } });
    if (existing) {
      return NextResponse.json(
        { error: "هذا الرابط مستخدم بالفعل، اختر رابطاً آخر" },
        { status: 400 }
      );
    }

    const article = await db.article.create({
      data: {
        ...data,
        authorId: session.user.id,
        status: "PENDING",
        cityId: data.cityId || null,
        projectId: data.projectId || null,
        coverImage: data.coverImage || null,
      },
    });

    await db.auditLog.create({
      data: {
        entity: "Article",
        entityId: article.id,
        action: "created",
        userId: session.user.id,
      },
    }).catch(() => {});

    return NextResponse.json(
      { success: true, data: article, message: "تم إرسال المقالة للمراجعة" },
      { status: 201 }
    );
  } catch (error) {
    if (error instanceof z.ZodError) {
      return NextResponse.json(
        { error: "بيانات غير صالحة", details: error.errors },
        { status: 400 }
      );
    }
    return NextResponse.json({ error: "حدث خطأ في الخادم" }, { status: 500 });
  }
}
