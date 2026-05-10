import { auth } from "@/lib/auth";
import { redirect } from "next/navigation";
import { db } from "@/lib/db";
import WriterSidebar from "@/components/layout/WriterSidebar";

export default async function WriterDashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const session = await auth();

  if (!session?.user) {
    redirect("/login?callbackUrl=/dashboard");
  }

  if (!["WRITER", "ADMIN", "SUPER_ADMIN", "ACCOUNT_MANAGER"].includes(session.user.role)) {
    redirect("/");
  }

  // Get available balance
  const commissions = await db.commission.aggregate({
    where: {
      writerId: session.user.id,
      status: "PAYABLE",
      isDeleted: false,
    },
    _sum: { amount: true },
  });

  const availableBalance = commissions._sum.amount || 0;

  return (
    <div className="flex min-h-screen bg-gray-50">
      <WriterSidebar
        userName={session.user.name}
        affiliateCode={session.user.affiliateCode}
        availableBalance={availableBalance}
      />
      <main className="flex-1 min-w-0 overflow-auto">
        {children}
      </main>
    </div>
  );
}
