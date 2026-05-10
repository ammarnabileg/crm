import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { db } from "@/lib/db";
import { z } from "zod";

const dealSchema = z.object({
  saleAmount: z.number().min(1),
  netProfit: z.number().min(0),
});

export async function POST(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session?.user || !["BROKER", "ADMIN", "SUPER_ADMIN"].includes(session.user.role)) {
    return NextResponse.json({ error: "غير مصرح" }, { status: 401 });
  }

  const { id } = await params;
  const body = await req.json();

  try {
    const data = dealSchema.parse(body);

    const lead = await db.lead.findUnique({
      where: { id },
      include: { brokerCompany: true },
    });

    if (!lead) {
      return NextResponse.json({ error: "العميل غير موجود" }, { status: 404 });
    }

    if (!lead.brokerCompanyId) {
      return NextResponse.json({ error: "العميل غير مُسند لشركة" }, { status: 400 });
    }

    // Create deal
    const deal = await db.deal.create({
      data: {
        leadId: id,
        brokerCompanyId: lead.brokerCompanyId,
        saleAmount: data.saleAmount,
        netProfit: data.netProfit,
        status: "PENDING_REVIEW",
        proofFiles: [],
      },
    });

    // Update lead status
    await db.lead.update({
      where: { id },
      data: { status: "CLOSED_WON", pipelineStage: "CLOSED_WON" },
    });

    // If lead has a writer, create commission (20% of net profit)
    if (lead.writerId) {
      const commissionAmount = data.netProfit * 0.2;
      await db.commission.create({
        data: {
          leadId: id,
          dealId: deal.id,
          writerId: lead.writerId,
          amount: commissionAmount,
          rate: 0.2,
          status: "PENDING",
        },
      });
    }

    await db.auditLog.create({
      data: {
        entity: "Deal",
        entityId: deal.id,
        action: "deal_submitted",
        userId: session.user.id,
      },
    }).catch(() => {});

    return NextResponse.json({ success: true, data: deal }, { status: 201 });
  } catch (error) {
    if (error instanceof z.ZodError) {
      return NextResponse.json({ error: "بيانات غير صالحة" }, { status: 400 });
    }
    return NextResponse.json({ error: "حدث خطأ" }, { status: 500 });
  }
}
