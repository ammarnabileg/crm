import { auth } from "@/lib/auth";
import { redirect } from "next/navigation";
import { db } from "@/lib/db";
import BrokerSidebar from "@/components/layout/BrokerSidebar";

export default async function BrokerLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const session = await auth();

  if (!session?.user) {
    redirect("/login?callbackUrl=/broker");
  }

  if (!["BROKER", "ADMIN", "SUPER_ADMIN"].includes(session.user.role)) {
    redirect("/dashboard");
  }

  let companyName: string | undefined;
  if (session.user.role === "BROKER") {
    const user = await db.user.findUnique({
      where: { id: session.user.id },
      include: { managedCompany: { select: { name: true, nameAr: true } } },
    });
    companyName = user?.managedCompany?.nameAr || user?.managedCompany?.name;
  }

  return (
    <div className="flex min-h-screen bg-gray-50">
      <BrokerSidebar
        userName={session.user.name}
        companyName={companyName}
      />
      <main className="flex-1 min-w-0 overflow-auto">
        {children}
      </main>
    </div>
  );
}
