import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { db } from "@/lib/db";

export async function GET(req: NextRequest) {
  const session = await auth();
  if (!session?.user || !["SUPER_ADMIN", "ADMIN", "ACCOUNT_MANAGER"].includes(session.user.role)) {
    return NextResponse.json({ error: "غير مصرح" }, { status: 401 });
  }

  const { searchParams } = new URL(req.url);
  const status = searchParams.get("status");
  const score = searchParams.get("score");
  const search = searchParams.get("search");

  const leads = await db.lead.findMany({
    where: {
      isDeleted: false,
      ...(status ? { status: status as any } : {}),
      ...(score ? { score: score as any } : {}),
      ...(search
        ? {
            OR: [
              { name: { contains: search, mode: "insensitive" } },
              { phone: { contains: search } },
            ],
          }
        : {}),
    },
    orderBy: { createdAt: "desc" },
    include: {
      writer: { select: { name: true } },
      city: { select: { nameAr: true } },
      project: { select: { name: true } },
      brokerCompany: { select: { name: true } },
    },
  });

  return NextResponse.json({ success: true, data: leads });
}
