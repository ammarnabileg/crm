"use client";

import { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import Badge from "@/components/ui/Badge";
import Card from "@/components/ui/Card";
import Button from "@/components/ui/Button";
import toast from "react-hot-toast";
import { Building2, Plus, Eye, Edit, CheckCircle, XCircle } from "lucide-react";

interface Project {
  id: string;
  name: string;
  nameAr?: string;
  slug: string;
  isPublished: boolean;
  minPrice?: number;
  createdAt: string;
  developer: { name: string };
  city: { nameAr: string };
  _count: { leads: number; articles: number };
}

export default function AdminProjectsPage() {
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);

  const loadProjects = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetch("/api/admin/projects");
      const data = await res.json();
      setProjects(data.data || []);
    } catch {
      toast.error("حدث خطأ في تحميل المشاريع");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadProjects(); }, [loadProjects]);

  const togglePublish = async (id: string, current: boolean) => {
    try {
      const res = await fetch(`/api/admin/projects/${id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ isPublished: !current }),
      });
      if (!res.ok) throw new Error();
      toast.success(current ? "تم إيقاف نشر المشروع" : "تم نشر المشروع");
      loadProjects();
    } catch {
      toast.error("حدث خطأ");
    }
  };

  return (
    <div className="p-6 lg:p-8">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-black text-gray-900">إدارة المشاريع</h1>
          <p className="text-gray-500 mt-1">إدارة المشاريع العقارية المنشورة على المنصة</p>
        </div>
        <Link href="/admin/projects/new">
          <Button variant="primary">
            <Plus className="h-5 w-5" />
            إضافة مشروع
          </Button>
        </Link>
      </div>

      {loading ? (
        <div className="text-center py-12 text-gray-400">جاري التحميل...</div>
      ) : projects.length === 0 ? (
        <Card>
          <div className="text-center py-16">
            <Building2 className="h-16 w-16 text-gray-200 mx-auto mb-4" />
            <h3 className="text-xl font-bold text-gray-900 mb-2">لا توجد مشاريع</h3>
            <Link href="/admin/projects/new">
              <Button variant="primary">
                <Plus className="h-5 w-5" />
                إضافة مشروع جديد
              </Button>
            </Link>
          </div>
        </Card>
      ) : (
        <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-gray-100 bg-gray-50">
                  <th className="table-header">المشروع</th>
                  <th className="table-header">المطور</th>
                  <th className="table-header">المدينة</th>
                  <th className="table-header">السعر</th>
                  <th className="table-header">العملاء</th>
                  <th className="table-header">الحالة</th>
                  <th className="table-header">إجراءات</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {projects.map((project) => (
                  <tr key={project.id} className="hover:bg-gray-50">
                    <td className="table-cell">
                      <p className="font-bold text-gray-900">{project.nameAr || project.name}</p>
                      <p className="text-xs text-gray-400">/{project.slug}</p>
                    </td>
                    <td className="table-cell text-sm text-gray-600">
                      {project.developer.name}
                    </td>
                    <td className="table-cell text-sm text-gray-600">
                      {project.city.nameAr}
                    </td>
                    <td className="table-cell text-sm">
                      {project.minPrice
                        ? `${project.minPrice.toLocaleString("ar-EG")} ج.م`
                        : "-"}
                    </td>
                    <td className="table-cell">
                      <div className="text-center">
                        <p className="font-bold text-gray-900">{project._count?.leads || 0}</p>
                        <p className="text-xs text-gray-400">عميل</p>
                      </div>
                    </td>
                    <td className="table-cell">
                      <Badge variant={project.isPublished ? "success" : "gray"}>
                        {project.isPublished ? "منشور" : "مخفي"}
                      </Badge>
                    </td>
                    <td className="table-cell">
                      <div className="flex items-center gap-2">
                        {project.isPublished && (
                          <Link
                            href={`/projects/${project.slug}`}
                            target="_blank"
                            className="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                          >
                            <Eye className="h-4 w-4" />
                          </Link>
                        )}
                        <button
                          onClick={() => togglePublish(project.id, project.isPublished)}
                          className={`p-1.5 rounded-lg transition-colors ${
                            project.isPublished
                              ? "text-orange-600 hover:bg-orange-50"
                              : "text-green-600 hover:bg-green-50"
                          }`}
                          title={project.isPublished ? "إيقاف النشر" : "نشر"}
                        >
                          {project.isPublished ? (
                            <XCircle className="h-4 w-4" />
                          ) : (
                            <CheckCircle className="h-4 w-4" />
                          )}
                        </button>
                        <Link
                          href={`/admin/projects/${project.id}/edit`}
                          className="p-1.5 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                        >
                          <Edit className="h-4 w-4" />
                        </Link>
                      </div>
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
