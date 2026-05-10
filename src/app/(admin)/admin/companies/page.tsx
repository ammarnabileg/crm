"use client";

import { useState, useEffect, useCallback } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import Card from "@/components/ui/Card";
import Button from "@/components/ui/Button";
import Input from "@/components/ui/Input";
import Modal from "@/components/ui/Modal";
import Badge from "@/components/ui/Badge";
import toast from "react-hot-toast";
import { Building, Plus, Building2 } from "lucide-react";

const brokerSchema = z.object({
  name: z.string().min(2, "الاسم مطلوب"),
  nameAr: z.string().optional(),
  phone: z.string().min(10, "الهاتف مطلوب"),
  email: z.string().email().optional().or(z.literal("")),
  commissionRate: z.number().min(0).max(100),
});

const developerSchema = z.object({
  name: z.string().min(2, "الاسم مطلوب"),
  nameAr: z.string().optional(),
});

type BrokerValues = z.infer<typeof brokerSchema>;
type DeveloperValues = z.infer<typeof developerSchema>;

interface Company {
  id: string;
  name: string;
  nameAr?: string;
  phone?: string;
  email?: string;
  commissionRate?: number;
  isActive: boolean;
  _count?: { leads?: number; projects?: number };
}

export default function AdminCompaniesPage() {
  const [activeTab, setActiveTab] = useState<"broker" | "developer">("broker");
  const [brokers, setBrokers] = useState<Company[]>([]);
  const [developers, setDevelopers] = useState<Company[]>([]);
  const [modalOpen, setModalOpen] = useState(false);
  const [loading, setLoading] = useState(true);

  const brokerForm = useForm<BrokerValues>({
    resolver: zodResolver(brokerSchema),
    defaultValues: { commissionRate: 0 },
  });

  const developerForm = useForm<DeveloperValues>({
    resolver: zodResolver(developerSchema),
  });

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [brokersRes, devsRes] = await Promise.all([
        fetch("/api/admin/companies?type=broker"),
        fetch("/api/admin/companies?type=developer"),
      ]);
      const [brokersData, devsData] = await Promise.all([
        brokersRes.json(),
        devsRes.json(),
      ]);
      setBrokers(brokersData.data || []);
      setDevelopers(devsData.data || []);
    } catch {
      toast.error("حدث خطأ");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const onSubmitBroker = async (data: BrokerValues) => {
    try {
      const res = await fetch("/api/admin/companies", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ...data, type: "broker" }),
      });
      if (!res.ok) throw new Error();
      toast.success("تم إضافة الشركة");
      setModalOpen(false);
      brokerForm.reset();
      loadData();
    } catch {
      toast.error("حدث خطأ");
    }
  };

  const onSubmitDeveloper = async (data: DeveloperValues) => {
    try {
      const res = await fetch("/api/admin/companies", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ...data, type: "developer" }),
      });
      if (!res.ok) throw new Error();
      toast.success("تم إضافة شركة التطوير");
      setModalOpen(false);
      developerForm.reset();
      loadData();
    } catch {
      toast.error("حدث خطأ");
    }
  };

  const companies = activeTab === "broker" ? brokers : developers;

  return (
    <div className="p-6 lg:p-8">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-black text-gray-900">إدارة الشركات</h1>
          <p className="text-gray-500 mt-1">إدارة شركات الوساطة والشركات المالكة</p>
        </div>
        <Button onClick={() => setModalOpen(true)} variant="primary">
          <Plus className="h-5 w-5" />
          إضافة شركة
        </Button>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 mb-6">
        {[
          { key: "broker", label: "الشركات الوسيطة", icon: Building },
          { key: "developer", label: "الشركات المالكة", icon: Building2 },
        ].map((tab) => {
          const Icon = tab.icon;
          return (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key as "broker" | "developer")}
              className={`flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-sm transition-all ${
                activeTab === tab.key
                  ? "bg-primary-500 text-gray-900"
                  : "bg-white border border-gray-200 text-gray-600 hover:bg-gray-50"
              }`}
            >
              <Icon className="h-4 w-4" />
              {tab.label}
            </button>
          );
        })}
      </div>

      {loading ? (
        <div className="text-center py-12 text-gray-400">جاري التحميل...</div>
      ) : companies.length === 0 ? (
        <Card>
          <div className="text-center py-12 text-gray-400">
            <Building className="h-12 w-12 mx-auto mb-3 opacity-30" />
            <p>لا توجد شركات مضافة</p>
          </div>
        </Card>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {companies.map((company) => (
            <Card key={company.id} className="hover:shadow-md transition-shadow">
              <div className="flex items-start gap-3">
                <div className="bg-gray-100 p-2.5 rounded-xl">
                  {activeTab === "broker" ? (
                    <Building className="h-5 w-5 text-gray-600" />
                  ) : (
                    <Building2 className="h-5 w-5 text-gray-600" />
                  )}
                </div>
                <div className="flex-1 min-w-0">
                  <h3 className="font-bold text-gray-900 truncate">
                    {company.nameAr || company.name}
                  </h3>
                  {company.phone && (
                    <p className="text-sm text-gray-500 mt-0.5">{company.phone}</p>
                  )}
                  {company.email && (
                    <p className="text-xs text-gray-400 truncate">{company.email}</p>
                  )}
                  {activeTab === "broker" && company.commissionRate !== undefined && (
                    <p className="text-xs text-primary-600 font-bold mt-1">
                      نسبة العمولة: {company.commissionRate}%
                    </p>
                  )}
                </div>
                <Badge variant={company.isActive ? "success" : "gray"}>
                  {company.isActive ? "نشط" : "معطل"}
                </Badge>
              </div>
              {activeTab === "broker" && (
                <div className="mt-3 pt-3 border-t border-gray-100 text-xs text-gray-400">
                  {company._count?.leads || 0} عميل
                </div>
              )}
            </Card>
          ))}
        </div>
      )}

      {/* Add Modal */}
      <Modal
        isOpen={modalOpen}
        onClose={() => setModalOpen(false)}
        title={activeTab === "broker" ? "إضافة شركة وسيطة" : "إضافة شركة تطوير"}
        size="lg"
      >
        {activeTab === "broker" ? (
          <form onSubmit={brokerForm.handleSubmit(onSubmitBroker)} className="space-y-4">
            <Input
              label="اسم الشركة *"
              placeholder="Company Name"
              error={brokerForm.formState.errors.name?.message}
              {...brokerForm.register("name")}
            />
            <Input
              label="الاسم بالعربية"
              placeholder="اسم الشركة"
              {...brokerForm.register("nameAr")}
            />
            <Input
              label="رقم الهاتف *"
              placeholder="01234567890"
              error={brokerForm.formState.errors.phone?.message}
              {...brokerForm.register("phone")}
            />
            <Input
              label="البريد الإلكتروني"
              type="email"
              placeholder="company@email.com"
              {...brokerForm.register("email")}
            />
            <div>
              <label className="label">نسبة العمولة (%)</label>
              <input
                type="number"
                className="input"
                placeholder="0"
                min="0"
                max="100"
                step="0.5"
                {...brokerForm.register("commissionRate", { valueAsNumber: true })}
              />
            </div>
            <div className="flex gap-3">
              <Button type="submit" variant="primary" fullWidth loading={brokerForm.formState.isSubmitting}>
                إضافة الشركة
              </Button>
              <Button type="button" variant="ghost" fullWidth onClick={() => setModalOpen(false)}>
                إلغاء
              </Button>
            </div>
          </form>
        ) : (
          <form onSubmit={developerForm.handleSubmit(onSubmitDeveloper)} className="space-y-4">
            <Input
              label="اسم الشركة (إنجليزي) *"
              placeholder="Developer Company Name"
              error={developerForm.formState.errors.name?.message}
              {...developerForm.register("name")}
            />
            <Input
              label="الاسم بالعربية"
              placeholder="اسم شركة التطوير"
              {...developerForm.register("nameAr")}
            />
            <div className="flex gap-3">
              <Button type="submit" variant="primary" fullWidth loading={developerForm.formState.isSubmitting}>
                إضافة الشركة
              </Button>
              <Button type="button" variant="ghost" fullWidth onClick={() => setModalOpen(false)}>
                إلغاء
              </Button>
            </div>
          </form>
        )}
      </Modal>
    </div>
  );
}
