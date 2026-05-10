import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { db } from "@/lib/db";

export async function GET(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session?.user || !["BROKER", "ADMIN", "SUPER_ADMIN"].includes(session.user.role)) {
    return NextResponse.json({ error: "غير مصرح" }, { status: 401 });
  }

  const { id } = await params;

  const user = await db.user.findUnique({
    where: { id: session.user.id },
    include: { managedCompany: { select: { id: true } } },
  });

  const lead = await db.lead.findFirst({
    where: {
      id,
      isDeleted: false,
      ...(user?.managedCompany ? { brokerCompanyId: user.managedCompany.id } : {}),
    },
    include: {
      city: { select: { nameAr: true } },
      project: { select: { name: true, salesPhone: true } },
      writer: { select: { name: true } },
      interactions: { orderBy: { createdAt: "desc" } },
      deal: { select: { saleAmount: true, status: true } },
    },
  });

  if (!lead) {
    return NextResponse.json({ error: "العميل غير موجود" }, { status: 404 });
  }

  return NextResponse.json({ success: true, data: lead });
}
