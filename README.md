# مربح (Morbeh) - منصة العقارات العقارية

Real estate affiliate platform built with Next.js 15, Prisma, PostgreSQL, and NextAuth.js v5.

## Quick Start

1. Copy `.env.example` to `.env` and fill in your database URL and NextAuth secret
2. Run `npm install`
3. Run `npx prisma db push` to create tables
4. Run `npm run db:seed` to seed initial data
5. Run `npm run dev` to start the dev server

## Default Credentials (after seeding)

- **Admin**: admin@morbeh.com / admin123456
- **Writer**: writer@morbeh.com / writer123456

## Routes

- `/` — Public homepage with hero, cities grid, articles, lead form
- `/articles/[slug]` — Article page with split layout + sticky lead capture form
- `/projects/[slug]` — Project page with split layout + sticky lead capture form
- `/login`, `/register` — Auth pages
- `/dashboard/*` — Writer dashboard (WRITER role)
- `/admin/*` — Admin panel (ADMIN/SUPER_ADMIN/ACCOUNT_MANAGER roles)
- `/broker/*` — Broker dashboard (BROKER role)

## Tech Stack

- Next.js 15 (App Router)
- Prisma + PostgreSQL
- NextAuth.js v5 (Credentials provider, JWT)
- Tailwind CSS (RTL Arabic support)
- Cairo font (Google Fonts)
- React Hook Form + Zod validation
- Lucide React icons

## Business Flow

1. Writer publishes SEO article → Lead submits form on article
2. Admin assigns lead to broker company in CRM
3. Broker closes deal, submits deal details
4. System auto-calculates 20% commission for the writer
5. Admin reviews and approves commission payout
