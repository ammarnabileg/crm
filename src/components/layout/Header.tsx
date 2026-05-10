"use client";

import Link from "next/link";
import { useState } from "react";
import { Menu, X, Phone, MessageCircle } from "lucide-react";
import Button from "@/components/ui/Button";

export default function Header() {
  const [menuOpen, setMenuOpen] = useState(false);

  return (
    <header className="bg-white shadow-sm border-b border-gray-100 sticky top-0 z-40">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          {/* Logo */}
          <Link href="/" className="flex items-center gap-2">
            <div className="bg-primary-500 rounded-xl w-9 h-9 flex items-center justify-center font-black text-gray-900 text-lg">
              م
            </div>
            <span className="font-black text-xl text-gray-900">مربح</span>
          </Link>

          {/* Desktop Nav */}
          <nav className="hidden md:flex items-center gap-6">
            <Link href="/" className="text-gray-600 hover:text-gray-900 font-medium transition-colors">
              الرئيسية
            </Link>
            <Link href="/articles" className="text-gray-600 hover:text-gray-900 font-medium transition-colors">
              المقالات
            </Link>
            <Link href="/projects" className="text-gray-600 hover:text-gray-900 font-medium transition-colors">
              المشاريع
            </Link>
          </nav>

          {/* Actions */}
          <div className="hidden md:flex items-center gap-3">
            <a
              href="https://wa.me/201234567890"
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-1.5 text-green-600 hover:text-green-700 font-medium text-sm"
            >
              <MessageCircle className="h-4 w-4" />
              واتساب
            </a>
            <Link href="/login">
              <Button variant="primary" size="sm">
                تسجيل الدخول
              </Button>
            </Link>
          </div>

          {/* Mobile menu toggle */}
          <button
            className="md:hidden p-2 rounded-xl hover:bg-gray-100"
            onClick={() => setMenuOpen(!menuOpen)}
          >
            {menuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
          </button>
        </div>
      </div>

      {/* Mobile Menu */}
      {menuOpen && (
        <div className="md:hidden bg-white border-t border-gray-100 px-4 py-4 space-y-3">
          <Link href="/" className="block text-gray-700 font-medium py-2">
            الرئيسية
          </Link>
          <Link href="/articles" className="block text-gray-700 font-medium py-2">
            المقالات
          </Link>
          <Link href="/projects" className="block text-gray-700 font-medium py-2">
            المشاريع
          </Link>
          <Link href="/login">
            <Button variant="primary" size="md" fullWidth>
              تسجيل الدخول
            </Button>
          </Link>
        </div>
      )}
    </header>
  );
}
