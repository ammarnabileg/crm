"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { signOut } from "next-auth/react";
import { LayoutDashboard, Phone, LogOut } from "lucide-react";
import { clsx } from "clsx";

interface BrokerSidebarProps {
  userName: string;
  companyName?: string;
}

const navItems = [
  { href: "/broker", label: "لوحة التحكم", icon: LayoutDashboard },
  { href: "/broker/leads", label: "العملاء المحالون", icon: Phone },
];

export default function BrokerSidebar({ userName, companyName }: BrokerSidebarProps) {
  const pathname = usePathname();

  return (
    <aside className="sidebar flex flex-col py-6">
      <div className="px-5 mb-8">
        <div className="flex items-center gap-2">
          <div className="bg-primary-500 rounded-xl w-9 h-9 flex items-center justify-center font-black text-gray-900 text-lg">
            م
          </div>
          <span className="font-black text-xl text-white">مربح</span>
        </div>
      </div>

      <div className="px-5 mb-6">
        <div className="bg-gray-800 rounded-2xl p-4">
          <p className="text-gray-400 text-xs mb-1">وسيط عقاري</p>
          <p className="text-white font-bold truncate">{userName}</p>
          {companyName && (
            <p className="text-primary-400 text-sm mt-1">{companyName}</p>
          )}
        </div>
      </div>

      <nav className="flex-1 px-3 space-y-1">
        {navItems.map((item) => {
          const Icon = item.icon;
          const isActive =
            pathname === item.href ||
            (item.href !== "/broker" && pathname.startsWith(item.href));
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
              <span>{item.label}</span>
            </Link>
          );
        })}
      </nav>

      <div className="px-3 mt-4">
        <button
          onClick={() => signOut({ callbackUrl: "/login" })}
          className="sidebar-link text-gray-400 hover:text-red-400 hover:bg-red-900/20 w-full"
        >
          <LogOut className="h-5 w-5 flex-shrink-0" />
          <span>تسجيل الخروج</span>
        </button>
      </div>
    </aside>
  );
}
