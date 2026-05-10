"use client";

import { useState, useEffect, useCallback } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import Card, { CardHeader, CardTitle } from "@/components/ui/Card";
import Button from "@/components/ui/Button";
import Input from "@/components/ui/Input";
import Modal from "@/components/ui/Modal";
import Badge from "@/components/ui/Badge";
import toast from "react-hot-toast";
import { MapPin, Plus } from "lucide-react";

const citySchema = z.object({
  name: z.string().min(2, "الاسم (إنجليزي) مطلوب"),
  nameAr: z.string().min(2, "الاسم (عربي) مطلوب"),
  image: z.string().url().optional().or(z.literal("")),
  countryId: z.string().min(1, "الدولة مطلوبة"),
});

type CityValues = z.infer<typeof citySchema>;

interface City {
  id: string;
  name: string;
  nameAr: string;
  image?: string;
  isActive: boolean;
  country: { name: string };
  _count: { projects: number };
}

interface Country {
  id: string;
  name: string;
  nameAr: string;
}

export default function AdminCitiesPage() {
  const [cities, setCities] = useState<City[]>([]);
  const [countries, setCountries] = useState<Country[]>([]);
  const [modalOpen, setModalOpen] = useState(false);
  const [loading, setLoading] = useState(true);

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<CityValues>({
    resolver: zodResolver(citySchema),
  });

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [citiesRes, countriesRes] = await Promise.all([
        fetch("/api/admin/cities"),
        fetch("/api/admin/countries"),
      ]);
      const [citiesData, countriesData] = await Promise.all([
        citiesRes.json(),
        countriesRes.json(),
      ]);
      setCities(citiesData.data || []);
      setCountries(countriesData.data || []);
    } catch {
      toast.error("حدث خطأ في التحميل");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const onSubmit = async (data: CityValues) => {
    try {
      const res = await fetch("/api/admin/cities", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });
      if (!res.ok) throw new Error();
      toast.success("تم إضافة المدينة بنجاح");
      setModalOpen(false);
      reset();
      loadData();
    } catch {
      toast.error("حدث خطأ في الإضافة");
    }
  };

  const toggleActive = async (id: string, current: boolean) => {
    try {
      await fetch(`/api/admin/cities/${id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ isActive: !current }),
      });
      toast.success("تم تحديث الحالة");
      loadData();
    } catch {
      toast.error("حدث خطأ");
    }
  };

  return (
    <div className="p-6 lg:p-8">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-black text-gray-900">إدارة المدن</h1>
          <p className="text-gray-500 mt-1">إدارة المدن والمناطق العقارية</p>
        </div>
        <Button onClick={() => setModalOpen(true)} variant="primary">
          <Plus className="h-5 w-5" />
          إضافة مدينة
        </Button>
      </div>

      {loading ? (
        <div className="text-center py-12 text-gray-400">جاري التحميل...</div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {cities.map((city) => (
            <Card key={city.id} className="hover:shadow-md transition-shadow">
              <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                  <div className="bg-yellow-100 p-2.5 rounded-xl">
                    <MapPin className="h-5 w-5 text-yellow-600" />
                  </div>
                  <div>
                    <h3 className="font-bold text-gray-900">{city.nameAr}</h3>
                    <p className="text-sm text-gray-500">{city.name}</p>
                    <p className="text-xs text-gray-400 mt-0.5">{city.country.name}</p>
                  </div>
                </div>
                <button
                  onClick={() => toggleActive(city.id, city.isActive)}
                  className={`px-3 py-1 rounded-full text-xs font-bold transition-colors ${
                    city.isActive
                      ? "bg-green-100 text-green-700 hover:bg-green-200"
                      : "bg-gray-100 text-gray-500 hover:bg-gray-200"
                  }`}
                >
                  {city.isActive ? "نشط" : "معطل"}
                </button>
              </div>
              <div className="mt-3 pt-3 border-t border-gray-100 flex items-center gap-3 text-xs text-gray-400">
                <span>{city._count?.projects || 0} مشروع</span>
              </div>
            </Card>
          ))}
        </div>
      )}

      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title="إضافة مدينة جديدة">
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div>
            <label className="label">الدولة *</label>
            <select className="input" {...register("countryId")}>
              <option value="">-- اختر الدولة --</option>
              {countries.map((c) => (
                <option key={c.id} value={c.id}>{c.nameAr} ({c.name})</option>
              ))}
            </select>
            {errors.countryId && <p className="text-sm text-red-600 mt-1">{errors.countryId.message}</p>}
          </div>
          <Input
            label="الاسم بالعربية *"
            placeholder="القاهرة الجديدة"
            error={errors.nameAr?.message}
            {...register("nameAr")}
          />
          <Input
            label="الاسم بالإنجليزية *"
            placeholder="New Cairo"
            error={errors.name?.message}
            {...register("name")}
          />
          <Input
            label="رابط الصورة (اختياري)"
            placeholder="https://..."
            error={errors.image?.message}
            {...register("image")}
          />
          <div className="flex gap-3">
            <Button type="submit" variant="primary" fullWidth loading={isSubmitting}>
              إضافة المدينة
            </Button>
            <Button type="button" variant="ghost" fullWidth onClick={() => setModalOpen(false)}>
              إلغاء
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
