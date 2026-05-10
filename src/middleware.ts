import { auth } from "@/lib/auth";
import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

export default auth((req) => {
  const { nextUrl, auth: session } = req;
  const pathname = nextUrl.pathname;

  // Public routes - always accessible
  const publicRoutes = ["/", "/login", "/register", "/api/leads", "/api/auth"];
  const isPublicRoute =
    publicRoutes.some((route) => pathname.startsWith(route)) ||
    pathname.startsWith("/articles") ||
    pathname.startsWith("/projects") ||
    pathname.startsWith("/_next") ||
    pathname.startsWith("/favicon");

  if (isPublicRoute) {
    // Redirect logged-in users away from login/register
    if (session?.user && (pathname === "/login" || pathname === "/register")) {
      const role = session.user.role;
      if (["SUPER_ADMIN", "ADMIN", "ACCOUNT_MANAGER"].includes(role)) {
        return NextResponse.redirect(new URL("/admin", nextUrl));
      }
      if (role === "BROKER") {
        return NextResponse.redirect(new URL("/broker", nextUrl));
      }
      return NextResponse.redirect(new URL("/dashboard", nextUrl));
    }
    return NextResponse.next();
  }

  // Not authenticated
  if (!session?.user) {
    const loginUrl = new URL("/login", nextUrl);
    loginUrl.searchParams.set("callbackUrl", pathname);
    return NextResponse.redirect(loginUrl);
  }

  const role = session.user.role;

  // Admin routes
  if (pathname.startsWith("/admin")) {
    if (!["SUPER_ADMIN", "ADMIN", "ACCOUNT_MANAGER"].includes(role)) {
      if (role === "BROKER") return NextResponse.redirect(new URL("/broker", nextUrl));
      return NextResponse.redirect(new URL("/dashboard", nextUrl));
    }
  }

  // Broker routes
  if (pathname.startsWith("/broker")) {
    if (!["BROKER", "SUPER_ADMIN", "ADMIN"].includes(role)) {
      return NextResponse.redirect(new URL("/dashboard", nextUrl));
    }
  }

  // Writer dashboard routes
  if (pathname.startsWith("/dashboard")) {
    if (!["WRITER", "SUPER_ADMIN", "ADMIN", "ACCOUNT_MANAGER"].includes(role)) {
      if (role === "BROKER") return NextResponse.redirect(new URL("/broker", nextUrl));
      return NextResponse.redirect(new URL("/", nextUrl));
    }
  }

  return NextResponse.next();
});

export const config = {
  matcher: [
    "/((?!_next/static|_next/image|favicon.ico|.*\\.(?:svg|png|jpg|jpeg|gif|webp)$).*)",
  ],
};
