import { auth } from "@/lib/auth";
import { db } from "@/lib/db";
import Badge from "@/components/ui/Badge";
import Card from "@/components/ui/Card";
import { ROLE_LABELS } from "@/types";
import { Users, UserCheck, UserX } from "lucide-react";

const roleVariant: Record<string, "success" | "warning" | "danger" | "info" | "gray"> = {
  SUPER_ADMIN: "danger",
  ADMIN: "warning",
  ACCOUNT_MANAGER: "info",
  WRITER: "success",
  BROKER: "gray",
};

export default async function AdminUsersPage() {
  const users = await db.user.findMany({
    where: { isDeleted: false },
    orderBy: { createdAt: "desc" },
    select: {
      id: true,
      name: true,
      email: true,
      phone: true,
      role: true,
      isActive: true,
      trustScore: true,
      affiliateCode: true,
      createdAt: true,
      _count: {
        select: {
          articles: true,
          leads: true,
          commissions: true,
        },
      },
    },
  });

  const stats = {
    total: users.length,
    writers: users.filter((u) => u.role === "WRITER").length,
    admins: users.filter((u) => ["SUPER_ADMIN", "ADMIN"].includes(u.role)).length,
    brokers: users.filter((u) => u.role === "BROKER").length,
  };

  return (
    <div className="p-6 lg:p-8">
      <div className="mb-8">
        <h1 className="text-2xl font-black text-gray-900">إدارة المستخدمين</h1>
        <p className="text-gray-500 mt-1">عرض وإدارة جميع مستخدمي المنصة</p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        {[
          { label: "إجمالي المستخدمين", value: stats.total, icon: Users },
          { label: "الكتّاب", value: stats.writers, icon: UserCheck },
          { label: "المدراء", value: stats.admins, icon: UserCheck },
          { label: "الوسطاء", value: stats.brokers, icon: UserCheck },
        ].map((item) => {
          const Icon = item.icon;
          return (
            <Card key={item.label} className="text-center">
              <Icon className="h-7 w-7 text-primary-500 mx-auto mb-2" />
              <p className="text-2xl font-black text-gray-900">{item.value}</p>
              <p className="text-xs text-gray-500 mt-1">{item.label}</p>
            </Card>
          );
        })}
      </div>

      {/* Users Table */}
      <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-gray-100 bg-gray-50">
                <th className="table-header">المستخدم</th>
                <th className="table-header">الدور</th>
                <th className="table-header">المقالات</th>
                <th className="table-header">العملاء</th>
                <th className="table-header">العمولات</th>
                <th className="table-header">الحالة</th>
                <th className="table-header">تاريخ التسجيل</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
              {users.map((user) => (
                <tr key={user.id} className="hover:bg-gray-50">
                  <td className="table-cell">
                    <div>
                      <p className="font-bold text-gray-900">{user.name}</p>
                      <p className="text-xs text-gray-400">{user.email}</p>
                      {user.phone && <p className="text-xs text-gray-400">{user.phone}</p>}
                    </div>
                  </td>
                  <td className="table-cell">
                    <Badge variant={roleVariant[user.role] || "gray"}>
                      {ROLE_LABELS[user.role]}
                    </Badge>
                  </td>
                  <td className="table-cell text-center font-medium">
                    {user._count.articles}
                  </td>
                  <td className="table-cell text-center font-medium">
                    {user._count.leads}
                  </td>
                  <td className="table-cell text-center font-medium">
                    {user._count.commissions}
                  </td>
                  <td className="table-cell">
                    <div className="flex items-center gap-1">
                      {user.isActive ? (
                        <UserCheck className="h-4 w-4 text-green-500" />
                      ) : (
                        <UserX className="h-4 w-4 text-red-500" />
                      )}
                      <span className={`text-xs font-medium ${user.isActive ? "text-green-600" : "text-red-500"}`}>
                        {user.isActive ? "نشط" : "معطل"}
                      </span>
                    </div>
                  </td>
                  <td className="table-cell text-xs text-gray-400">
                    {new Date(user.createdAt).toLocaleDateString("ar-EG")}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
