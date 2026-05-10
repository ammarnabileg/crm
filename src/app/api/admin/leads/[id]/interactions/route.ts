import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { db } from "@/lib/db";

export async function POST(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session?.user) {
    return NextResponse.json({ error: "غير مصرح" }, { status: 401 });
  }

  const { id } = await params;
  const body = await req.json();

  const interaction = await db.leadInteraction.create({
    data: {
      leadId: id,
      type: body.type || "note",
      content: body.content,
      createdBy: session.user.id,
    },
  });

  return NextResponse.json({ success: true, data: interaction }, { status: 201 });
}
