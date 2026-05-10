import { NextRequest, NextResponse } from "next/server";
import { db } from "@/lib/db";
import { z } from "zod";

const leadSchema = z.object({
  name: z.string().min(2),
  phone: z.string().min(10),
  email: z.string().email().optional(),
  interestedArea: z.string().optional(),
  articleId: z.string().optional(),
  projectId: z.string().optional(),
  cityId: z.string().optional(),
  writerId: z.string().optional(),
  utmSource: z.string().optional(),
  utmMedium: z.string().optional(),
  utmCampaign: z.string().optional(),
  referrer: z.string().optional(),
  source: z.enum(["FORM", "WHATSAPP", "PHONE", "HOMEPAGE", "PROJECT_PAGE", "ARTICLE"]).optional(),
});

export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const data = leadSchema.parse(body);

    const ipAddress =
      req.headers.get("x-forwarded-for")?.split(",")[0] ||
      req.headers.get("x-real-ip") ||
      "unknown";

    const deviceInfo = req.headers.get("user-agent") || "unknown";

    // Check for duplicate (same phone in last 24h)
    const recentLead = await db.lead.findFirst({
      where: {
        phone: data.phone,
        createdAt: { gte: new Date(Date.now() - 24 * 60 * 60 * 1000) },
        isDeleted: false,
      },
    });

    const lead = await db.lead.create({
      data: {
        name: data.name,
        phone: data.phone,
        email: data.email,
        interestedArea: data.interestedArea,
        source: data.source || "FORM",
        articleId: data.articleId,
        projectId: data.projectId,
        cityId: data.cityId,
        writerId: data.writerId,
        utmSource: data.utmSource,
        utmMedium: data.utmMedium,
        utmCampaign: data.utmCampaign,
        referrer: data.referrer,
        ipAddress,
        deviceInfo,
        isDuplicate: !!recentLead,
      },
    });

    // Update article lead count
    if (data.articleId) {
      await db.article.update({
        where: { id: data.articleId },
        data: { leadCount: { increment: 1 } },
      }).catch(() => {});
    }

    // Log audit
    await db.auditLog.create({
      data: {
        entity: "Lead",
        entityId: lead.id,
        action: "created",
        changes: { source: data.source, articleId: data.articleId },
        ipAddress,
      },
    }).catch(() => {});

    return NextResponse.json(
      { success: true, message: "تم استلام طلبك بنجاح", leadId: lead.id },
      { status: 201 }
    );
  } catch (error) {
    if (error instanceof z.ZodError) {
      return NextResponse.json({ error: "بيانات غير صالحة" }, { status: 400 });
    }
    return NextResponse.json({ error: "حدث خطأ في الخادم" }, { status: 500 });
  }
}
