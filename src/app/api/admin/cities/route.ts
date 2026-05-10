import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { db } from "@/lib/db";
import { z } from "zod";

const citySchema = z.object({
  name: z.string().min(2),
  nameAr: z.string().min(2),
  image: z.string().optional(),
  countryId: z.string().min(1),
});

export async function GET() {
  const cities = await db.city.findMany({
    where: { isDeleted: false },
    orderBy: { nameAr: "asc" },
    include: {
      country: { select: { name: true } },
      _count: { select: { projects: true } },
    },
  });

  return NextResponse.json({ success: true, data: cities });
}

export async function POST(req: NextRequest) {
  const session = await auth();
  if (!session?.user || !["SUPER_ADMIN", "ADMIN", "ACCOUNT_MANAGER"].includes(session.user.role)) {
    return NextResponse.json({ error: "غير مصرح" }, { status: 401 });
  }

  try {
    const body = await req.json();
    const data = citySchema.parse(body);

    const city = await db.city.create({
      data: {
        name: data.name,
        nameAr: data.nameAr,
        image: data.image || null,
        countryId: data.countryId,
      },
    });

    return NextResponse.json({ success: true, data: city }, { status: 201 });
  } catch (error) {
    if (error instanceof z.ZodError) {
      return NextResponse.json({ error: "بيانات غير صالحة" }, { status: 400 });
    }
    return NextResponse.json({ error: "حدث خطأ" }, { status: 500 });
  }
}
