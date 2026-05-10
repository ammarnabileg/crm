"use client";

import { useState, useEffect, useCallback } from "react";
import Card, { CardHeader, CardTitle } from "@/components/ui/Card";
import Button from "@/components/ui/Button";
import Input from "@/components/ui/Input";
import Modal from "@/components/ui/Modal";
import toast from "react-hot-toast";
import { Settings, Plus, HelpCircle } from "lucide-react";

interface Setting {
  id: string;
  key: string;
  value: string;
  group: string;
}

interface GlobalFaq {
  id: string;
  question: string;
  answer: string;
  isActive: boolean;
  order: number;
}

const settingLabels: Record<string, string> = {
  sales_phone: "هاتف المبيعات",
  whatsapp_phone: "رقم واتساب",
  commission_rate: "نسبة عمولة الكاتب",
  site_name: "اسم الموقع",
  site_email: "البريد الإلكتروني",
};

export default function AdminSettingsPage() {
  const [settings, setSettings] = useState<Setting[]>([]);
  const [faqs, setFaqs] = useState<GlobalFaq[]>([]);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState<string | null>(null);
  const [editValue, setEditValue] = useState("");
  const [faqModal, setFaqModal] = useState(false);
  const [newFaq, setNewFaq] = useState({ question: "", answer: "" });
  const [saving, setSaving] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [settingsRes, faqsRes] = await Promise.all([
        fetch("/api/admin/settings"),
        fetch("/api/admin/faqs"),
      ]);
      const [settingsData, faqsData] = await Promise.all([
        settingsRes.json(),
        faqsRes.json(),
      ]);
      setSettings(settingsData.data || []);
      setFaqs(faqsData.data || []);
    } catch {
      toast.error("حدث خطأ في التحميل");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const saveSetting = async (key: string, value: string) => {
    setSaving(true);
    try {
      await fetch("/api/admin/settings", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ key, value }),
      });
      toast.success("تم حفظ الإعداد");
      setEditing(null);
      loadData();
    } catch {
      toast.error("حدث خطأ");
    } finally {
      setSaving(false);
    }
  };

  const addFaq = async () => {
    if (!newFaq.question || !newFaq.answer) {
      toast.error("السؤال والإجابة مطلوبان");
      return;
    }
    try {
      await fetch("/api/admin/faqs", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(newFaq),
      });
      toast.success("تم إضافة السؤال");
      setFaqModal(false);
      setNewFaq({ question: "", answer: "" });
      loadData();
    } catch {
      toast.error("حدث خطأ");
    }
  };

  const defaultSettings = [
    { key: "sales_phone", value: "01234567890", group: "contact" },
    { key: "whatsapp_phone", value: "201234567890", group: "contact" },
    { key: "commission_rate", value: "20", group: "business" },
    { key: "site_name", value: "مربح", group: "general" },
  ];

  const allSettings = [
    ...defaultSettings.filter((d) => !settings.find((s) => s.key === d.key)),
    ...settings,
  ];

  if (loading) return <div className="p-8 text-center text-gray-400">جاري التحميل...</div>;

  return (
    <div className="p-6 lg:p-8">
      <div className="mb-8">
        <h1 className="text-2xl font-black text-gray-900">إعدادات المنصة</h1>
        <p className="text-gray-500 mt-1">إدارة إعدادات موقع مربح</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* General Settings */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Settings className="h-5 w-5 text-primary-500" />
              إعدادات عامة
            </CardTitle>
          </CardHeader>
          <div className="space-y-4">
            {allSettings.map((setting) => (
              <div key={setting.key} className="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                <div className="flex-1">
                  <p className="text-sm font-bold text-gray-700">
                    {settingLabels[setting.key] || setting.key}
                  </p>
                  {editing === setting.key ? (
                    <div className="flex gap-2 mt-2">
                      <input
                        type="text"
                        className="input flex-1 py-1.5 text-sm"
                        value={editValue}
                        onChange={(e) => setEditValue(e.target.value)}
                        autoFocus
                      />
                      <Button
                        size="sm"
                        variant="primary"
                        onClick={() => saveSetting(setting.key, editValue)}
                        loading={saving}
                      >
                        حفظ
                      </Button>
                      <Button size="sm" variant="ghost" onClick={() => setEditing(null)}>
                        إلغاء
                      </Button>
                    </div>
                  ) : (
                    <p className="text-gray-900 font-mono text-sm mt-0.5">{setting.value}</p>
                  )}
                </div>
                {editing !== setting.key && (
                  <button
                    onClick={() => {
                      setEditing(setting.key);
                      setEditValue(setting.value);
                    }}
                    className="text-xs text-primary-600 font-bold hover:text-primary-700 px-2 py-1 rounded hover:bg-primary-50"
                  >
                    تعديل
                  </button>
                )}
              </div>
            ))}
          </div>
        </Card>

        {/* Global FAQs */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <HelpCircle className="h-5 w-5 text-primary-500" />
              الأسئلة الشائعة العامة
            </CardTitle>
            <Button variant="outline" size="sm" onClick={() => setFaqModal(true)}>
              <Plus className="h-4 w-4" />
              إضافة سؤال
            </Button>
          </CardHeader>
          <div className="space-y-3">
            {faqs.length === 0 ? (
              <div className="text-center py-8 text-gray-400 text-sm">
                لا توجد أسئلة شائعة بعد
              </div>
            ) : (
              faqs.map((faq) => (
                <div key={faq.id} className="p-4 bg-gray-50 rounded-xl">
                  <p className="font-bold text-gray-900 text-sm">{faq.question}</p>
                  <p className="text-gray-500 text-sm mt-1 line-clamp-2">{faq.answer}</p>
                </div>
              ))
            )}
          </div>
        </Card>
      </div>

      {/* FAQ Modal */}
      <Modal isOpen={faqModal} onClose={() => setFaqModal(false)} title="إضافة سؤال شائع">
        <div className="space-y-4">
          <div>
            <label className="label">السؤال</label>
            <input
              type="text"
              className="input"
              placeholder="ما هي أسعار شقق المشروع؟"
              value={newFaq.question}
              onChange={(e) => setNewFaq({ ...newFaq, question: e.target.value })}
            />
          </div>
          <div>
            <label className="label">الإجابة</label>
            <textarea
              className="input min-h-28 resize-none"
              placeholder="الإجابة التفصيلية..."
              value={newFaq.answer}
              onChange={(e) => setNewFaq({ ...newFaq, answer: e.target.value })}
            />
          </div>
          <div className="flex gap-3">
            <Button variant="primary" fullWidth onClick={addFaq}>
              إضافة السؤال
            </Button>
            <Button variant="ghost" fullWidth onClick={() => setFaqModal(false)}>
              إلغاء
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
