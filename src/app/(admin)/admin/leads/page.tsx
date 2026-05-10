"use client";

import { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import Badge from "@/components/ui/Badge";
import Card from "@/components/ui/Card";
import Input from "@/components/ui/Input";
import Select from "@/components/ui/Select";
import { LEAD_STATUS_LABELS, LEAD_SCORE_LABELS } from "@/types";
import { Phone, Search, Filter, Eye } from "lucide-react";

interface Lead {
  id: string;
  name: string;
  phone: string;
  status: string;
  score: string;
  source: string;
  pipelineStage: string;
  createdAt: string;
  writer?: { name: string } | null;
  city?: { nameAr: string } | null;
  project?: { name: string } | null;
  brokerCompany?: { name: string } | null;
}

const statusVariant: Record<string, "success" | "warning" | "danger" | "info" | "gray"> = {
  CLOSED_WON: "success",
  IN_PROGRESS: "info",
  ASSIGNED: "warning",
  NEW: "primary" as "success",
  CLOSED_LOST: "danger",
  DUPLICATE: "gray",
};

const scoreVariant: Record<string, "success" | "warning" | "danger" | "info" | "gray"> = {
  HIGH_INTENT: "success",
  HOT: "danger",
  WARM: "warning",
  COLD: "gray",
};

export default function AdminLeadsPage() {
  const [leads, setLeads] = useState<Lead[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [scoreFilter, setScoreFilter] = useState("");

  const loadLeads = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (statusFilter) params.set("status", statusFilter);
      if (scoreFilter) params.set("score", scoreFilter);
      if (search) params.set("search", search);

      const res = await fetch(`/api/admin/leads?${params}`);
      const data = await res.json();
      setLeads(data.data || []);
    } catch {
      console.error("Error loading leads");
    } finally {
      setLoading(false);
    }
  }, [statusFilter, scoreFilter, search]);

  useEffect(() => {
    const timer = setTimeout(loadLeads, 400);
    return () => clearTimeout(timer);
  }, [loadLeads]);

  return (
    <div className="p-6 lg:p-8">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-black text-gray-900">إدارة العملاء (CRM)</h1>
          <p className="text-gray-500 mt-1">متابعة وإدارة جميع العملاء والصفقات</p>
        </div>
        <div className="bg-primary-100 text-primary-800 font-bold px-4 py-2 rounded-xl text-sm">
          {leads.length} عميل
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-2xl border border-gray-100 p-4 mb-6">
        <div className="flex flex-wrap gap-4">
          <div className="flex-1 min-w-48">
            <div className="relative">
              <Search className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                type="text"
                placeholder="بحث بالاسم أو الهاتف..."
                className="input pr-10"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>
          </div>
          <div className="w-40">
            <select
              className="input"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
            >
              <option value="">كل الحالات</option>
              <option value="NEW">جديد</option>
              <option value="ASSIGNED">معين</option>
              <option value="IN_PROGRESS">قيد المعالجة</option>
              <option value="CLOSED_WON">مغلق (ناجح)</option>
              <option value="CLOSED_LOST">مغلق (خسارة)</option>
            </select>
          </div>
          <div className="w-40">
            <select
              className="input"
              value={scoreFilter}
              onChange={(e) => setScoreFilter(e.target.value)}
            >
              <option value="">كل الدرجات</option>
              <option value="HIGH_INTENT">نية شراء عالية</option>
              <option value="HOT">ساخن</option>
              <option value="WARM">دافئ</option>
              <option value="COLD">بارد</option>
            </select>
          </div>
        </div>
      </div>

      {loading ? (
        <div className="text-center py-12 text-gray-400">جاري التحميل...</div>
      ) : leads.length === 0 ? (
        <Card>
          <div className="text-center py-16">
            <Phone className="h-16 w-16 text-gray-200 mx-auto mb-4" />
            <p className="text-gray-500">لا توجد عملاء</p>
          </div>
        </Card>
      ) : (
        <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-gray-100 bg-gray-50">
                  <th className="table-header">العميل</th>
                  <th className="table-header">الهاتف</th>
                  <th className="table-header">المصدر</th>
                  <th className="table-header">الحالة</th>
                  <th className="table-header">الدرجة</th>
                  <th className="table-header">الوسيط</th>
                  <th className="table-header">التاريخ</th>
                  <th className="table-header">إجراء</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {leads.map((lead) => (
                  <tr key={lead.id} className="hover:bg-gray-50 transition-colors">
                    <td className="table-cell">
                      <div>
                        <p className="font-bold text-gray-900">{lead.name}</p>
                        {lead.writer && (
                          <p className="text-xs text-gray-400">كاتب: {lead.writer.name}</p>
                        )}
                      </div>
                    </td>
                    <td className="table-cell">
                      <a href={`tel:${lead.phone}`} className="text-blue-600 hover:underline font-medium">
                        {lead.phone}
                      </a>
                    </td>
                    <td className="table-cell">
                      <div>
                        {lead.city && (
                          <p className="text-sm text-gray-600">{lead.city.nameAr}</p>
                        )}
                        {lead.project && (
                          <p className="text-xs text-gray-400">{lead.project.name}</p>
                        )}
                      </div>
                    </td>
                    <td className="table-cell">
                      <Badge variant={statusVariant[lead.status] || "gray"}>
                        {LEAD_STATUS_LABELS[lead.status as keyof typeof LEAD_STATUS_LABELS]}
                      </Badge>
                    </td>
                    <td className="table-cell">
                      <Badge variant={scoreVariant[lead.score] || "gray"}>
                        {LEAD_SCORE_LABELS[lead.score as keyof typeof LEAD_SCORE_LABELS]}
                      </Badge>
                    </td>
                    <td className="table-cell">
                      {lead.brokerCompany ? (
                        <span className="text-sm text-gray-700">{lead.brokerCompany.name}</span>
                      ) : (
                        <span className="text-sm text-orange-500 font-medium">لم يُعيَّن</span>
                      )}
                    </td>
                    <td className="table-cell text-xs text-gray-400">
                      {new Date(lead.createdAt).toLocaleDateString("ar-EG")}
                    </td>
                    <td className="table-cell">
                      <Link
                        href={`/admin/leads/${lead.id}`}
                        className="flex items-center gap-1 text-xs text-primary-600 hover:text-primary-700 font-bold px-3 py-1.5 border border-primary-200 rounded-lg hover:bg-primary-50 transition-colors"
                      >
                        <Eye className="h-3.5 w-3.5" />
                        تفاصيل
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
