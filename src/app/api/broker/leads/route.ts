import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { db } from "@/lib/db";

export async function GET() {
  const session = await auth();
  if (!session?.user || !["BROKER", "ADMIN", "SUPER_ADMIN"].includes(session.user.role)) {
    return NextResponse.json({ error: "غير مصرح" }, { status: 401 });
  }

  const user = await db.user.findUnique({
    where: { id: session.user.id },
    include: { managedCompany: { select: { id: true } } },
  });

  if (!user?.managedCompany) {
    return NextResponse.json({ success: true, data: [] });
  }

  const leads = await db.lead.findMany({
    where: { brokerCompanyId: user.managedCompany.id, isDeleted: false },
    orderBy: { createdAt: "desc" },
    include: {
      city: { select: { nameAr: true } },
      project: { select: { name: true } },
    },
  });

  return NextResponse.json({ success: true, data: leads });
}
