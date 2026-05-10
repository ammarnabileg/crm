"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { useRouter } from "next/navigation";
import Link from "next/link";
import Input from "@/components/ui/Input";
import Button from "@/components/ui/Button";
import { Mail, Lock, User, Phone, Eye, EyeOff } from "lucide-react";
import toast from "react-hot-toast";

const registerSchema = z
  .object({
    name: z.string().min(2, "الاسم مطلوب"),
    email: z.string().email("بريد إلكتروني غير صالح"),
    phone: z.string().min(10, "رقم الهاتف مطلوب"),
    password: z.string().min(8, "كلمة المرور يجب أن تكون 8 أحرف على الأقل"),
    confirmPassword: z.string(),
  })
  .refine((data) => data.password === data.confirmPassword, {
    message: "كلمتا المرور غير متطابقتان",
    path: ["confirmPassword"],
  });

type RegisterValues = z.infer<typeof registerSchema>;

export default function RegisterPage() {
  const router = useRouter();
  const [showPassword, setShowPassword] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<RegisterValues>({
    resolver: zodResolver(registerSchema),
  });

  const onSubmit = async (data: RegisterValues) => {
    try {
      const res = await fetch("/api/auth/register", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: data.name,
          email: data.email,
          phone: data.phone,
          password: data.password,
        }),
      });

      const result = await res.json();

      if (!res.ok) {
        toast.error(result.error || "حدث خطأ أثناء التسجيل");
        return;
      }

      toast.success("تم التسجيل بنجاح! يمكنك الآن تسجيل الدخول");
      router.push("/login");
    } catch {
      toast.error("حدث خطأ، يرجى المحاولة مرة أخرى");
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full mx-auto">
        {/* Logo */}
        <div className="text-center mb-8">
          <Link href="/" className="inline-flex items-center gap-2">
            <div className="bg-primary-500 rounded-xl w-12 h-12 flex items-center justify-center font-black text-gray-900 text-2xl">
              م
            </div>
            <span className="font-black text-2xl text-gray-900">مربح</span>
          </Link>
          <h2 className="mt-6 text-2xl font-black text-gray-900">إنشاء حساب كاتب</h2>
          <p className="mt-2 text-sm text-gray-500">
            لديك حساب بالفعل؟{" "}
            <Link href="/login" className="text-primary-600 font-bold hover:text-primary-700">
              سجل دخولك
            </Link>
          </p>
        </div>

        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
          {/* Why Join */}
          <div className="bg-primary-50 border border-primary-100 rounded-xl p-4 mb-6">
            <h3 className="font-bold text-gray-900 text-sm mb-2">🎯 لماذا تنضم إلينا؟</h3>
            <ul className="text-xs text-gray-600 space-y-1">
              <li>✅ اكتب مقالات عقارية واكسب عمولات</li>
              <li>✅ 20% عمولة على كل صفقة تُغلق من خلال مقالاتك</li>
              <li>✅ تتبع العملاء والعمولات لحظة بلحظة</li>
            </ul>
          </div>

          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            <Input
              label="الاسم الكامل"
              placeholder="أدخل اسمك الكامل"
              icon={<User className="h-4 w-4" />}
              error={errors.name?.message}
              {...register("name")}
            />

            <Input
              label="البريد الإلكتروني"
              type="email"
              placeholder="example@email.com"
              icon={<Mail className="h-4 w-4" />}
              error={errors.email?.message}
              {...register("email")}
            />

            <Input
              label="رقم الهاتف"
              type="tel"
              placeholder="01xxxxxxxxx"
              icon={<Phone className="h-4 w-4" />}
              error={errors.phone?.message}
              {...register("phone")}
            />

            <div className="relative">
              <Input
                label="كلمة المرور"
                type={showPassword ? "text" : "password"}
                placeholder="8 أحرف على الأقل"
                icon={<Lock className="h-4 w-4" />}
                error={errors.password?.message}
                {...register("password")}
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute left-3 top-9 text-gray-400"
              >
                {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
              </button>
            </div>

            <Input
              label="تأكيد كلمة المرور"
              type="password"
              placeholder="••••••••"
              icon={<Lock className="h-4 w-4" />}
              error={errors.confirmPassword?.message}
              {...register("confirmPassword")}
            />

            <Button
              type="submit"
              variant="primary"
              size="lg"
              fullWidth
              loading={isSubmitting}
            >
              إنشاء الحساب
            </Button>
          </form>
        </div>
      </div>
    </div>
  );
}
