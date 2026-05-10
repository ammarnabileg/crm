import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { db } from "@/lib/db";

export async function GET() {
  const settings = await db.setting.findMany({
    orderBy: { group: "asc" },
  });
  return NextResponse.json({ success: true, data: settings });
}

export async function PUT(req: NextRequest) {
  const session = await auth();
  if (!session?.user || !["SUPER_ADMIN", "ADMIN"].includes(session.user.role)) {
    return NextResponse.json({ error: "غير مصرح" }, { status: 401 });
  }

  const { key, value, group } = await req.json();

  const setting = await db.setting.upsert({
    where: { key },
    update: { value },
    create: { key, value, group: group || "general" },
  });

  return NextResponse.json({ success: true, data: setting });
}
