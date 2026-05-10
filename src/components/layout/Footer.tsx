import Link from "next/link";
import { Phone, MessageCircle, Mail } from "lucide-react";

export default function Footer() {
  return (
    <footer className="bg-gray-900 text-gray-300 pt-12 pb-6">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-8 mb-10">
          {/* Brand */}
          <div className="md:col-span-2">
            <div className="flex items-center gap-2 mb-4">
              <div className="bg-primary-500 rounded-xl w-9 h-9 flex items-center justify-center font-black text-gray-900 text-lg">
                م
              </div>
              <span className="font-black text-xl text-white">مربح</span>
            </div>
            <p className="text-gray-400 text-sm leading-relaxed mb-4">
              منصة مربح للعقارات - الوجهة الأولى للبحث عن عقارك المميز في مصر.
              نربط بين الباحثين عن العقارات وأفضل الشركات العقارية.
            </p>
            <div className="flex items-center gap-4">
              <a
                href="tel:+201234567890"
                className="flex items-center gap-1.5 text-yellow-400 hover:text-yellow-300 text-sm font-medium"
              >
                <Phone className="h-4 w-4" />
                01234567890
              </a>
              <a
                href="https://wa.me/201234567890"
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center gap-1.5 text-green-400 hover:text-green-300 text-sm font-medium"
              >
                <MessageCircle className="h-4 w-4" />
                واتساب
              </a>
            </div>
          </div>

          {/* Quick Links */}
          <div>
            <h4 className="font-bold text-white mb-4">روابط سريعة</h4>
            <ul className="space-y-2 text-sm">
              <li>
                <Link href="/" className="hover:text-yellow-400 transition-colors">
                  الرئيسية
                </Link>
              </li>
              <li>
                <Link href="/articles" className="hover:text-yellow-400 transition-colors">
                  المقالات العقارية
                </Link>
              </li>
              <li>
                <Link href="/projects" className="hover:text-yellow-400 transition-colors">
                  المشاريع العقارية
                </Link>
              </li>
            </ul>
          </div>

          {/* For Writers */}
          <div>
            <h4 className="font-bold text-white mb-4">للكتّاب</h4>
            <ul className="space-y-2 text-sm">
              <li>
                <Link href="/register" className="hover:text-yellow-400 transition-colors">
                  سجل كاتبًا
                </Link>
              </li>
              <li>
                <Link href="/login" className="hover:text-yellow-400 transition-colors">
                  لوحة تحكم الكاتب
                </Link>
              </li>
              <li>
                <Link href="/login" className="hover:text-yellow-400 transition-colors">
                  برنامج العمولات
                </Link>
              </li>
            </ul>
          </div>
        </div>

        <div className="border-t border-gray-800 pt-6 text-center text-sm text-gray-500">
          <p>© {new Date().getFullYear()} مربح. جميع الحقوق محفوظة.</p>
        </div>
      </div>
    </footer>
  );
}
