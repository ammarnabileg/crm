import type {
  User,
  Article,
  Lead,
  Project,
  City,
  Country,
  BrokerCompany,
  DeveloperCompany,
  Commission,
  Deal,
  Complaint,
  Payout,
  AuditLog,
  LeadInteraction,
  Role,
  ArticleStatus,
  LeadStatus,
  LeadScore,
  LeadSource,
  PipelineStage,
  DealStatus,
  CommissionStatus,
  PayoutStatus,
  ComplaintStatus,
  ComplaintType,
  Priority,
} from "@prisma/client";

export type {
  User,
  Article,
  Lead,
  Project,
  City,
  Country,
  BrokerCompany,
  DeveloperCompany,
  Commission,
  Deal,
  Complaint,
  Payout,
  AuditLog,
  LeadInteraction,
  Role,
  ArticleStatus,
  LeadStatus,
  LeadScore,
  LeadSource,
  PipelineStage,
  DealStatus,
  CommissionStatus,
  PayoutStatus,
  ComplaintStatus,
  ComplaintType,
  Priority,
};

export type SafeUser = Omit<User, "password">;

export type ArticleWithAuthor = Article & {
  author: SafeUser;
  city?: City | null;
  project?: Project | null;
};

export type LeadWithRelations = Lead & {
  article?: Article | null;
  writer?: SafeUser | null;
  city?: City | null;
  project?: Project | null;
  brokerCompany?: BrokerCompany | null;
  interactions?: LeadInteraction[];
  deal?: Deal | null;
  commission?: Commission | null;
};

export type CommissionWithRelations = Commission & {
  writer: SafeUser;
  lead: Lead;
  deal: Deal;
  payout?: Payout | null;
};

export interface DashboardStats {
  totalArticles: number;
  pendingArticles: number;
  totalLeads: number;
  totalCommissions: number;
  pendingCommissions: number;
  activeWriters: number;
  activeBrokers: number;
}

export interface WriterStats {
  totalArticles: number;
  approvedArticles: number;
  pendingArticles: number;
  totalLeads: number;
  totalCommissions: number;
  availableBalance: number;
  pendingBalance: number;
}

export interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  error?: string;
  message?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  pageSize: number;
  totalPages: number;
}

export interface LeadFormData {
  name: string;
  phone: string;
  email?: string;
  interestedArea?: string;
  articleId?: string;
  projectId?: string;
  cityId?: string;
  writerId?: string;
  utmSource?: string;
  utmMedium?: string;
  utmCampaign?: string;
  referrer?: string;
  source?: LeadSource;
}

export interface ArticleFormData {
  title: string;
  slug: string;
  content: string;
  excerpt?: string;
  coverImage?: string;
  cityId?: string;
  projectId?: string;
  unit?: string;
  seoTitle?: string;
  seoDescription?: string;
  seoKeywords?: string;
}

export interface ProjectFormData {
  name: string;
  nameAr?: string;
  slug: string;
  developerId: string;
  cityId: string;
  location?: string;
  salesPhone?: string;
  minPrice?: number;
  minDownPayment?: number;
  minInstallment?: number;
  minArea?: number;
  description?: string;
  seoTitle?: string;
  seoDescription?: string;
  seoKeywords?: string;
  images?: string[];
}

export const PIPELINE_STAGE_LABELS: Record<PipelineStage, string> = {
  NEW_LEAD: "عميل جديد",
  ATTEMPTED_CONTACT: "محاولة التواصل",
  CONTACTED: "تم التواصل",
  INTERESTED: "مهتم",
  VIEWING_SCHEDULED: "جدول معاينة",
  VIEWING_COMPLETED: "تمت المعاينة",
  NEGOTIATION: "تفاوض",
  RESERVATION: "حجز",
  CLOSED_WON: "صفقة مغلقة",
  CLOSED_LOST: "خسر",
};

export const LEAD_STATUS_LABELS: Record<LeadStatus, string> = {
  NEW: "جديد",
  ASSIGNED: "معين",
  IN_PROGRESS: "قيد المعالجة",
  CLOSED_WON: "مغلق (ناجح)",
  CLOSED_LOST: "مغلق (خسارة)",
  DUPLICATE: "مكرر",
};

export const LEAD_SCORE_LABELS: Record<LeadScore, string> = {
  COLD: "بارد",
  WARM: "دافئ",
  HOT: "ساخن",
  HIGH_INTENT: "نية شراء عالية",
};

export const ARTICLE_STATUS_LABELS: Record<ArticleStatus, string> = {
  DRAFT: "مسودة",
  PENDING: "قيد المراجعة",
  APPROVED: "موافق عليه",
  REJECTED: "مرفوض",
  NEEDS_EDIT: "يحتاج تعديل",
};

export const ROLE_LABELS: Record<Role, string> = {
  SUPER_ADMIN: "مدير عام",
  ADMIN: "مدير",
  ACCOUNT_MANAGER: "مدير حساب",
  WRITER: "كاتب محتوى",
  BROKER: "وسيط عقاري",
};

export const COMMISSION_STATUS_LABELS: Record<CommissionStatus, string> = {
  PENDING: "معلقة",
  UNDER_REVIEW: "قيد المراجعة",
  APPROVED: "موافق عليها",
  PAYABLE: "قابلة للدفع",
  PAID: "مدفوعة",
  REJECTED: "مرفوضة",
};

export const COMPLAINT_TYPE_LABELS: Record<ComplaintType, string> = {
  COMMISSION_DISPUTE: "نزاع عمولة",
  LEAD_OWNERSHIP: "ملكية عميل",
  BROKER_BEHAVIOR: "سلوك وسيط",
  CONTENT_ISSUE: "مشكلة محتوى",
  PAYMENT_ISSUE: "مشكلة دفع",
  OTHER: "أخرى",
};

export const PRIORITY_LABELS: Record<Priority, string> = {
  LOW: "منخفض",
  MEDIUM: "متوسط",
  HIGH: "عالي",
  URGENT: "عاجل",
};
