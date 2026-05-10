import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { db } from "@/lib/db";

export async function PATCH(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session?.user || !["SUPER_ADMIN", "ADMIN", "ACCOUNT_MANAGER"].includes(session.user.role)) {
    return NextResponse.json({ error: "غير مصرح" }, { status: 401 });
  }

  const { id } = await params;
  const body = await req.json();
  const { status, rejectionReason } = body;

  const validStatuses = ["DRAFT", "PENDING", "APPROVED", "REJECTED", "NEEDS_EDIT"];
  if (!validStatuses.includes(status)) {
    return NextResponse.json({ error: "حالة غير صالحة" }, { status: 400 });
  }

  const article = await db.article.update({
    where: { id },
    data: {
      status,
      rejectionReason: rejectionReason || null,
      publishedAt: status === "APPROVED" ? new Date() : undefined,
    },
  });

  await db.auditLog.create({
    data: {
      entity: "Article",
      entityId: id,
      action: `status_changed_to_${status}`,
      changes: { status, rejectionReason },
      userId: session.user.id,
    },
  }).catch(() => {});

  return NextResponse.json({ success: true, data: article });
}
