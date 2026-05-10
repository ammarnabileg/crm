import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { db } from "@/lib/db";

export async function GET(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session?.user || !["SUPER_ADMIN", "ADMIN", "ACCOUNT_MANAGER"].includes(session.user.role)) {
    return NextResponse.json({ error: "غير مصرح" }, { status: 401 });
  }

  const { id } = await params;

  const lead = await db.lead.findUnique({
    where: { id, isDeleted: false },
    include: {
      writer: { select: { id: true, name: true } },
      city: { select: { nameAr: true } },
      project: { select: { name: true } },
      article: { select: { title: true } },
      brokerCompany: { select: { id: true, name: true } },
      interactions: { orderBy: { createdAt: "desc" } },
      deal: { select: { id: true, saleAmount: true, netProfit: true, status: true } },
    },
  });

  if (!lead) {
    return NextResponse.json({ error: "العميل غير موجود" }, { status: 404 });
  }

  return NextResponse.json({ success: true, data: lead });
}
