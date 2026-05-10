import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { db } from "@/lib/db";

export async function GET() {
  const faqs = await db.globalFaq.findMany({
    where: { isActive: true },
    orderBy: { order: "asc" },
  });
  return NextResponse.json({ success: true, data: faqs });
}

export async function POST(req: NextRequest) {
  const session = await auth();
  if (!session?.user || !["SUPER_ADMIN", "ADMIN"].includes(session.user.role)) {
    return NextResponse.json({ error: "غير مصرح" }, { status: 401 });
  }

  const { question, answer } = await req.json();

  const faq = await db.globalFaq.create({
    data: { question, answer },
  });

  return NextResponse.json({ success: true, data: faq }, { status: 201 });
}
