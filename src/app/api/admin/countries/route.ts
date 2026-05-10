import { NextResponse } from "next/server";
import { db } from "@/lib/db";

export async function GET() {
  const countries = await db.country.findMany({
    where: { isActive: true },
    orderBy: { nameAr: "asc" },
  });

  return NextResponse.json({ success: true, data: countries });
}
