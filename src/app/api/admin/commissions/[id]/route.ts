import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { db } from "@/lib/db";

export async function PATCH(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session?.user || !["SUPER_ADMIN", "ADMIN"].includes(session.user.role)) {
    return NextResponse.json({ error: "غير مصرح" }, { status: 401 });
  }

  const { id } = await params;
  const { status } = await req.json();

  const commission = await db.commission.update({
    where: { id },
    data: { status },
  });

  await db.auditLog.create({
    data: {
      entity: "Commission",
      entityId: id,
      action: `status_changed_to_${status}`,
      userId: session.user.id,
    },
  }).catch(() => {});

  return NextResponse.json({ success: true, data: commission });
}
