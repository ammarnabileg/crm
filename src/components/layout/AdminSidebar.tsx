"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { signOut } from "next-auth/react";
import {
  LayoutDashboard,
  FileText,
  Building2,
  Users,
  MapPin,
  UserCheck,
  Phone,
  Banknote,
  AlertCircle,
  Settings,
  LogOut,
  Building,
} from "lucide-react";
import { clsx } from "clsx";

interface AdminSidebarProps {
  userName: string;
  role: string;
}

const navItems = [
  { href: "/admin", label: "لوحة التحكم", icon: LayoutDashboard },
  { href: "/admin/articles", label: "المقالات", icon: FileText },
  { href: "/admin/projects", label: "المشاريع", icon: Building2 },
  { href: "/admin/leads", label: "إدارة العملاء (CRM)", icon: Phone },
  { href: "/admin/companies", label: "الشركات", icon: Building },
  { href: "/admin/cities", label: "المدن", icon: MapPin },
  { href: "/admin/users", label: "المستخدمون", icon: UserCheck },
  { href: "/admin/commissions", label: "العمولات", icon: Banknote },
  { href: "/admin/complaints", label: "الشكاوي", icon: AlertCircle },
  { href: "/admin/settings", label: "الإعدادات", icon: Settings },
];

export default function AdminSidebar({ userName, role }: AdminSidebarProps) {
  const pathname = usePathname();

  return (
    <aside className="sidebar flex flex-col py-6">
      {/* Logo */}
      <div className="px-5 mb-8">
        <div className="flex items-center gap-2">
          <div className="bg-primary-500 rounded-xl w-9 h-9 flex items-center justify-center font-black text-gray-900 text-lg">
            م
          </div>
          <span className="font-black text-xl text-white">مربح</span>
        </div>
      </div>

      {/* User Info */}
      <div className="px-5 mb-6">
        <div className="bg-gray-800 rounded-2xl p-4">
          <p className="text-gray-400 text-xs mb-1">مدير النظام</p>
          <p className="text-white font-bold truncate">{userName}</p>
          <span className="mt-2 inline-block bg-primary-500 text-gray-900 text-xs font-bold px-2 py-0.5 rounded-full">
            {role === "SUPER_ADMIN" ? "مدير عام" : role === "ADMIN" ? "مدير" : "مدير حساب"}
          </span>
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 px-3 space-y-0.5 overflow-y-auto scrollbar-hide">
        {navItems.map((item) => {
          const Icon = item.icon;
          const isActive =
            pathname === item.href ||
            (item.href !== "/admin" && pathname.startsWith(item.href));
          return (
            <Link
              key={item.href}
              href={item.href}
              className={clsx(
                isActive
                  ? "sidebar-link-active"
                  : "sidebar-link text-gray-300 hover:text-white hover:bg-gray-800"
              )}
            >
              <Icon className="h-5 w-5 flex-shrink-0" />
              <span className="text-sm">{item.label}</span>
            </Link>
          );
        })}
      </nav>

      {/* Logout */}
      <div className="px-3 mt-4">
        <button
          onClick={() => signOut({ callbackUrl: "/login" })}
          className="sidebar-link text-gray-400 hover:text-red-400 hover:bg-red-900/20 w-full"
        >
          <LogOut className="h-5 w-5 flex-shrink-0" />
          <span className="text-sm">تسجيل الخروج</span>
        </button>
      </div>
    </aside>
  );
}
