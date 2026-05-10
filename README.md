\# HalaOps — CRM \& Operational Intelligence System



> Blueprint for the next-generation CRM for \*\*Hala Career\*\*: not a sales CRM,

> but an Operational Intelligence System that combines tasks, performance,

> gamification, and AI insights — bilingual AR/EN by design.



\## Documents

| File | Contents |

|------|----------|

| \[00-MASTER-BLUEPRINT.md](./00-MASTER-BLUEPRINT.md) | Vision, architecture, modules, roadmap (the main doc) |

| \[01-database-schema.sql](./01-database-schema.sql) | Postgres 16 schema with RLS + pgvector |

| \[02-api-spec.md](./02-api-spec.md) | REST + WebSocket + state machines |

| \[03-gamification-and-kpi.md](./03-gamification-and-kpi.md) | XP formulas, anti-cheat, KPI engine, Truth Index |



\## TL;DR

\- \*\*Modular Monolith\*\* in NestJS + Next.js 15, event-driven core, RLS multi-tenant.

\- \*\*Five Pillars\*\* for performance: Performance, Reliability, Leadership, Consistency, Growth.

\- \*\*Truth Index\*\* prevents fake KPIs by requiring corroborating events.

\- \*\*Gamification\*\* modeled on Duolingo (leagues, streaks, missions) but tied to real work, with anti-farming caps and after-hours dampers.

\- \*\*AI Copilot\*\* embedded everywhere: smart assignment, voice-to-action, RAG chat, burnout detection, deal win prediction.

\- \*\*Bilingual-native\*\*: RTL/LTR from day one, not a translation layer.

\- \*\*Roadmap\*\*: MVP in 8 weeks → V2 (insights+AI) by week 20 → V3 (full game+mobile) by week 32 → Enterprise after.



\## Why this is different

Most CRMs assume self-reported truth. HalaOps assumes nothing — every metric is derived from events the system observes, every score has an evidence trail, every leaderboard has anti-cheat guardrails. Management gets a live, honest view of the company.

