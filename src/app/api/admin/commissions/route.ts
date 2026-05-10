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

  const commissions = await db.commission.findMany({
    where: {
      isDeleted: false,
      ...(status ? { status: status as any } : {}),
    },
    orderBy: { createdAt: "desc" },
    include: {
      writer: { select: { name: true, email: true } },
      lead: { select: { name: true, phone: true } },
      deal: { select: { saleAmount: true, netProfit: true, status: true } },
    },
  });

  return NextResponse.json({ success: true, data: commissions });
}
