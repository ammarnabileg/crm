import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function formatCurrency(amount: number, locale = "ar-EG"): string {
  return new Intl.NumberFormat(locale).format(amount) + " ج.م";
}

export function formatDate(date: Date | string, locale = "ar-EG"): string {
  return new Date(date).toLocaleDateString(locale, {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

export function generateSlug(text: string): string {
  return text
    .toLowerCase()
    .replace(/\s+/g, "-")
    .replace(/[^؀-ۿa-z0-9-]/g, "")
    .replace(/-+/g, "-")
    .slice(0, 100);
}

export function truncate(text: string, length = 100): string {
  if (text.length <= length) return text;
  return text.slice(0, length) + "...";
}

export function isAdmin(role: string): boolean {
  return ["SUPER_ADMIN", "ADMIN", "ACCOUNT_MANAGER"].includes(role);
}

export function isWriter(role: string): boolean {
  return role === "WRITER";
}

export function isBroker(role: string): boolean {
  return role === "BROKER";
}
