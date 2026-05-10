import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { db } from "@/lib/db";
import { z } from "zod";

const schema = z.object({
  title: z.string().min(5),
  description: z.string().min(20),
  type: z.enum(["COMMISSION_DISPUTE", "LEAD_OWNERSHIP", "BROKER_BEHAVIOR", "CONTENT_ISSUE", "PAYMENT_ISSUE", "OTHER"]),
  leadId: z.string().optional(),
});

export async function GET() {
  const session = await auth();
  if (!session?.user) return NextResponse.json({ error: "غير مصرح" }, { status: 401 });

  const complaints = await db.complaint.findMany({
    where: { userId: session.user.id, isDeleted: false },
    orderBy: { createdAt: "desc" },
  });

  return NextResponse.json({ success: true, data: complaints });
}

export async function POST(req: NextRequest) {
  const session = await auth();
  if (!session?.user) return NextResponse.json({ error: "غير مصرح" }, { status: 401 });

  try {
    const body = await req.json();
    const data = schema.parse(body);

    const complaint = await db.complaint.create({
      data: {
        ...data,
        userId: session.user.id,
        status: "OPEN",
        priority: "MEDIUM",
      },
    });

    return NextResponse.json({ success: true, data: complaint }, { status: 201 });
  } catch {
    return NextResponse.json({ error: "حدث خطأ" }, { status: 500 });
  }
}
