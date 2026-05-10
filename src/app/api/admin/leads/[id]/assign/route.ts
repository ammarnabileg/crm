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

  const updateData: Record<string, unknown> = {};

  if (body.brokerCompanyId) {
    updateData.brokerCompanyId = body.brokerCompanyId;
    updateData.status = "ASSIGNED";
  }
  if (body.pipelineStage) {
    updateData.pipelineStage = body.pipelineStage;
  }
  if (body.score) {
    updateData.score = body.score;
  }
  if (body.status) {
    updateData.status = body.status;
  }
  if (body.notes) {
    updateData.notes = body.notes;
  }

  const lead = await db.lead.update({
    where: { id },
    data: updateData,
  });

  // Log the action
  await db.auditLog.create({
    data: {
      entity: "Lead",
      entityId: id,
      action: body.brokerCompanyId ? "assigned_to_broker" : "updated",
      changes: body,
      userId: session.user.id,
    },
  }).catch(() => {});

  return NextResponse.json({ success: true, data: lead });
}
