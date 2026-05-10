import type { Metadata } from "next";
import { Cairo } from "next/font/google";
import "./globals.css";
import { Toaster } from "react-hot-toast";

const cairo = Cairo({
  subsets: ["arabic", "latin"],
  variable: "--font-cairo",
  display: "swap",
});

export const metadata: Metadata = {
  title: "مربح | منصة العقارات الرائدة",
  description: "منصة مربح للعقارات - ابحث عن عقارك المميز واستثمر بذكاء",
  keywords: "عقارات, شقق, فلل, مباني, استثمار عقاري, مربح",
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="ar" dir="rtl" className={cairo.variable}>
      <body className="font-arabic bg-gray-50 text-gray-900 antialiased">
        <Toaster
          position="top-center"
          toastOptions={{
            duration: 4000,
            style: {
              fontFamily: "Cairo, sans-serif",
              direction: "rtl",
            },
          }}
        />
        {children}
      </body>
    </html>
  );
}
