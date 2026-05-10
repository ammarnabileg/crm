import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { db } from "@/lib/db";

export async function PATCH(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session?.user || !["BROKER", "ADMIN", "SUPER_ADMIN"].includes(session.user.role)) {
    return NextResponse.json({ error: "غير مصرح" }, { status: 401 });
  }

  const { id } = await params;
  const body = await req.json();

  const updateData: Record<string, unknown> = {};
  if (body.pipelineStage) updateData.pipelineStage = body.pipelineStage;
  if (body.status) updateData.status = body.status;

  if (Object.keys(updateData).length > 0) {
    await db.lead.update({ where: { id }, data: updateData });
  }

  // Add a note/interaction if provided
  if (body.note) {
    await db.leadInteraction.create({
      data: {
        leadId: id,
        type: "note",
        content: body.note,
        createdBy: session.user.id,
      },
    });
  }

  return NextResponse.json({ success: true });
}
