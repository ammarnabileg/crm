"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { signOut } from "next-auth/react";
import {
  LayoutDashboard,
  FileText,
  PlusCircle,
  Users,
  Wallet,
  AlertCircle,
  BarChart3,
  LogOut,
  Copy,
  CheckCheck,
} from "lucide-react";
import { clsx } from "clsx";
import { useState } from "react";
import toast from "react-hot-toast";

interface WriterSidebarProps {
  userName: string;
  affiliateCode: string;
  availableBalance?: number;
}

const navItems = [
  { href: "/dashboard", label: "الرئيسية", icon: LayoutDashboard },
  { href: "/dashboard/articles", label: "مقالاتي", icon: FileText },
  { href: "/dashboard/articles/new", label: "إضافة مقالة", icon: PlusCircle },
  { href: "/dashboard/leads", label: "متابعة العملاء", icon: Users },
  { href: "/dashboard/wallet", label: "المحفظة", icon: Wallet },
  { href: "/dashboard/complaints", label: "الشكاوي", icon: AlertCircle },
];

export default function WriterSidebar({
  userName,
  affiliateCode,
  availableBalance = 0,
}: WriterSidebarProps) {
  const pathname = usePathname();
  const [copied, setCopied] = useState(false);

  const copyCode = () => {
    navigator.clipboard.writeText(affiliateCode);
    setCopied(true);
    toast.success("تم نسخ كود الإحالة");
    setTimeout(() => setCopied(false), 2000);
  };

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
          <p className="text-gray-400 text-xs mb-1">مرحباً</p>
          <p className="text-white font-bold truncate">{userName}</p>
          <div className="mt-3 pt-3 border-t border-gray-700">
            <p className="text-gray-400 text-xs mb-1">الرصيد المتاح</p>
            <p className="text-primary-400 font-black text-lg">
              {availableBalance.toLocaleString("ar-EG")} ج.م
            </p>
          </div>
          <div className="mt-3 pt-3 border-t border-gray-700">
            <p className="text-gray-400 text-xs mb-1.5">كود الإحالة</p>
            <button
              onClick={copyCode}
              className="flex items-center gap-2 bg-gray-700 hover:bg-gray-600 rounded-lg px-3 py-1.5 text-xs text-gray-300 transition-colors w-full"
            >
              <span className="font-mono truncate flex-1">{affiliateCode}</span>
              {copied ? (
                <CheckCheck className="h-3.5 w-3.5 text-green-400 flex-shrink-0" />
              ) : (
                <Copy className="h-3.5 w-3.5 flex-shrink-0" />
              )}
            </button>
          </div>
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 px-3 space-y-1">
        {navItems.map((item) => {
          const Icon = item.icon;
          const isActive =
            pathname === item.href ||
            (item.href !== "/dashboard" && pathname.startsWith(item.href));
          return (
            <Link
              key={item.href}
              href={item.href}
              className={clsx(
                isActive ? "sidebar-link-active" : "sidebar-link text-gray-300 hover:text-white hover:bg-gray-800"
              )}
            >
              <Icon className="h-5 w-5 flex-shrink-0" />
              <span>{item.label}</span>
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
          <span>تسجيل الخروج</span>
        </button>
      </div>
    </aside>
  );
}
