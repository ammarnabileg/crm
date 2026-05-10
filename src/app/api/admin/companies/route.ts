import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { db } from "@/lib/db";
import { z } from "zod";

const brokerSchema = z.object({
  type: z.literal("broker"),
  name: z.string().min(2),
  nameAr: z.string().optional(),
  phone: z.string().min(10),
  email: z.string().email().optional(),
  commissionRate: z.number().min(0).max(100).optional(),
});

const developerSchema = z.object({
  type: z.literal("developer"),
  name: z.string().min(2),
  nameAr: z.string().optional(),
  logo: z.string().optional(),
});

export async function GET(req: NextRequest) {
  const { searchParams } = new URL(req.url);
  const type = searchParams.get("type");

  if (type === "broker") {
    const companies = await db.brokerCompany.findMany({
      where: { isDeleted: false },
      orderBy: { name: "asc" },
      include: {
        _count: { select: { leads: true } },
      },
    });
    return NextResponse.json({ success: true, data: companies });
  }

  if (type === "developer") {
    const companies = await db.developerCompany.findMany({
      where: { isDeleted: false },
      orderBy: { name: "asc" },
    });
    return NextResponse.json({ success: true, data: companies });
  }

  // Return both
  const [brokers, developers] = await Promise.all([
    db.brokerCompany.findMany({ where: { isDeleted: false } }),
    db.developerCompany.findMany({ where: { isDeleted: false } }),
  ]);

  return NextResponse.json({ success: true, data: { brokers, developers } });
}

export async function POST(req: NextRequest) {
  const session = await auth();
  if (!session?.user || !["SUPER_ADMIN", "ADMIN", "ACCOUNT_MANAGER"].includes(session.user.role)) {
    return NextResponse.json({ error: "غير مصرح" }, { status: 401 });
  }

  try {
    const body = await req.json();

    if (body.type === "broker") {
      const data = brokerSchema.parse(body);
      const company = await db.brokerCompany.create({
        data: {
          name: data.name,
          nameAr: data.nameAr || null,
          phone: data.phone,
          email: data.email || null,
          commissionRate: data.commissionRate || 0,
        },
      });
      return NextResponse.json({ success: true, data: company }, { status: 201 });
    }

    if (body.type === "developer") {
      const data = developerSchema.parse(body);
      const company = await db.developerCompany.create({
        data: {
          name: data.name,
          nameAr: data.nameAr || null,
          logo: data.logo || null,
        },
      });
      return NextResponse.json({ success: true, data: company }, { status: 201 });
    }

    return NextResponse.json({ error: "نوع الشركة غير صالح" }, { status: 400 });
  } catch (error) {
    if (error instanceof z.ZodError) {
      return NextResponse.json({ error: "بيانات غير صالحة" }, { status: 400 });
    }
    return NextResponse.json({ error: "حدث خطأ" }, { status: 500 });
  }
}
