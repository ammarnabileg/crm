# FIRST IMPRESSION ENGINE — HaHireAI

> **Status:** Adopted · **Version:** 1.0.0 · **Last updated:** 2026-06-30
> **Defers to:** `PROJECT_CONSTITUTION.md`, `ARCHITECTURE.md`, `AI_ENGINE.md`,
> `JOB_CONFIGURATION_AND_SCREENING.md`.

---

## 0. Purpose

The **First Impression Engine** is a **fully rule-based, ZERO-AI** gate that runs
**between “Apply” and the (paid) AI interview**. It exists to **stop AI credits
being spent on applicants who are clearly far from the job**, while still
capturing every applicant as a candidate with a complete, explainable report.

It spends **no** AI credits by construction — no OpenAI/Anthropic/Gemini/DeepSeek/
HeyGen/Whisper/ElevenLabs/Azure provider is ever called on this path. Everything
is deterministic: regex, keyword/skill matching with an ontology, structured
parsing, and weighted scoring.

It is **opt-in per job** (`jobs.first_impression_enabled`, default **off**), so
existing jobs and flows are unchanged until a recruiter turns it on.

## 1. Where it sits in the flow

```
Career page / public job / portal
   ↓  Apply
Application Preparation   (social profiles — optional · résumé — mandatory)
   ↓  Continue
First Impression Engine   (rule-based, no AI, no credits)
   ├─ score ≥ job minimum → AI Interview → Pipeline
   └─ score <  job minimum → Application created · candidate saved · CV saved ·
                              report saved · status "Filtered Before AI" · HR can
                              read the full report and OVERRIDE to allow AI.
```

The gate is applied on **both** apply paths (the public job page and the
in-portal apply) through the same orchestrator.

## 2. Architecture (build on the existing, break nothing)

The engine reuses the existing modular-monolith boundaries exactly:

```
┌─ Resume Parsing Layer  (Recruitment/Infrastructure + Domain) ─────────────┐
│  ResumeParserInterface ─ PdfResumeParser (smalot/pdfparser + native        │
│  fallback) · DocxResumeParser (native ZipArchive) · TxtResumeParser        │
│  → ResumeParserManager (registry; add a format = add a parser)             │
│  → Normalised text → ResumeStructurer → ParsedResume (name, email, phone,  │
│    address, titles, companies, dates, skills, education, certs, languages,  │
│    projects, links, publications, awards)                                   │
└────────────────────────────────────────────────────────────────────────────┘
        │ text + structured data ONLY (the engine never sees a file)
        ▼
┌─ Engine 1: ResumeAnalysisEngine  (Recruitment/Domain — pure) ─────────────┐
│  Job-aware: skill_match · experience_match · seniority_match ·            │
│             keyword_density · education_match · language_match            │
│  Quality:   completeness · formatting_quality · employment_stability       │
│  → job_match_score (the BASIS) · resume_score = 0.70·jobMatch + 0.30·qual  │
└────────────────────────────────────────────────────────────────────────────┘
┌─ Engine 2: Social Credibility  (Integration Platform — adapters) ─────────┐
│  SocialProfileProbe (Core contract) ← Recruitment depends on this only     │
│  SocialAdapterRegistry: GithubAdapter (REST API) · StackOverflowAdapter    │
│  (StackExchange API) · WebsiteAdapter (portfolios/blogs: title/meta/SSL) · │
│  RecognizedProfileAdapter (LinkedIn/X/… recorded but neutral — no scraping)│
│  → normalised snapshots → SocialRelevanceScorer (Recruitment, job-aware)   │
│  → SocialScoring.roll → bounded ±30 boost                                  │
└────────────────────────────────────────────────────────────────────────────┘
        ▼
FirstImpressionScore.decide → overall = clamp(core + socialBoost) ; decision
        ▼
FirstImpressionService (orchestrator) → persists NORMALISED tables → publishes
  first_impression.completed / .passed / .failed / .override on the Event Bus.
```

**Boundaries honoured:** all external network access (social data) lives **only**
behind the **Integration Platform** (`ARCHITECTURE.md` §8). Recruitment depends
on the **Core `SocialProfileProbe` contract** and never learns which sources
exist. New sources (LinkedIn API, Kaggle, Behance, Google Scholar, Medium…) are
**new Integration adapters** with **no change to Recruitment or the scoring
engine** — plug-and-play.

## 3. Scoring model (the algorithm)

### 3.1 The basis is job relevance, not social

```
core_score   = resume_score                      # Engine 1 (CV + job fit)
social_score = avg(per-source relevance ∈ [-100,100])   or NULL when none
social_boost = round(0.30 × social_score)  ∈ [-30, +30]  # social's 30% share
overall      = clamp(core_score + social_boost, 0, 100)
passed       = overall ≥ jobs.min_first_impression_score
```

- **CV / job-fit is the foundation (70%).** Social is an **optional 30% helper**.
- **Absence is never a penalty.** No social links → `social_score = NULL`,
  `boost = 0`, so `overall == core`. A link with no extractable info is **neutral
  (0)**, excluded from the average. A candidate is never punished for not using
  social media.
- Social **rises** for public, **job-relevant** footprint (overlapping skills /
  technical text), and **falls** only when a reachable footprint is **clearly
  off-target** — bounded, and never the sole reason a strong CV is rejected.

### 3.2 Engine 1 sub-scores (all 0..100)

| Sub-score | Job-aware? | What it measures |
|---|---|---|
| `skill_match` (w 40) | yes | required skills present (ontology, alias-aware) |
| `experience_match` (w 20) | yes | years vs `experience_min/max` |
| `seniority_match` (w 15) | yes | inferred seniority (titles + years) vs job seniority |
| `keyword_density` (w 12) | yes | HR `screening_keywords` present in the CV |
| `education_match` (w 8) | yes | detected level vs the level the seniority implies |
| `language_match` (w 5) | yes | required spoken languages present |
| `completeness` (w 45) | no | which CV sections are present |
| `formatting_quality` (w 30) | no | parse confidence + length + structure + contact |
| `employment_stability` (w 25) | no | average tenure, job-hopping penalty |

`job_match_score` = weighted blend of the job-aware rows (the basis).
`resume_score` = `0.70·job_match_score + 0.30·quality`.

Every report also stores **explainable evidence** (normalised, not a JSON blob):
matched/missing skills, keyword hits, present/missing sections, strengths,
weaknesses, recommendations, rule matches, rule failures, plus the structured CV
data (titles, companies, education, certs, languages, projects, links).

### 3.2b Candidate Intelligence (Engine 1b — advisory, never scores)

On top of the scoring sub-scores, a pure **`CandidateInsights`** derivation adds
**rule-based, zero-AI** intelligence *about* the candidate. It is **advisory
only** — it never changes `overall`, `passed`, or the decision; it produces
normalised `resume_analysis_details` rows (kinds prefixed `insight_*`) shown on
the report. Existing readers ignore the new kinds, so it is fully
backward-compatible. Derivations:

| Insight | Kind | What it derives (deterministically) |
|---|---|---|
| Career progression | `insight_progression` | upward / lateral / downward trajectory across the dated roles |
| Seniority | `insight_seniority` | inferred level in words (Intern…Executive) from titles + years |
| Employment stability | `insight_stability` | average tenure per role, in words |
| Technical stacks | `insight_stack` | skills clustered into Frontend/Backend/Mobile/Data/Database/DevOps-Cloud/Design |
| Industry experience | `insight_industry` | domains worked in (fintech, healthcare, e-commerce, …) via a cue ontology |
| Leadership indicators | `insight_leadership` | led/managed/mentored/"team of N"/P&L/hiring cues |
| Skill gaps | `insight_skill_gap` | required skills absent from the CV, each tagged `critical` (in the job title) or `core` |
| Consistency notes | `insight_consistency` | soft flags (e.g. stated years vs dated roles) — advisory, never a penalty |
| At-a-glance | `insight_highlight` | a short chip summary of the above |

### 3.3 Confidence

A 0..100 confidence is stored on every report (parse confidence + how much
structure was extracted), so an image-only/garbled PDF is scored conservatively
and flagged rather than trusted blindly.

## 4. Data model (normalised — no big JSON)

Global, owned by the **User** (reusable across every workspace):
- **`user_resumes`** — the candidate's CV library (bytes outside the web root,
  text parsed once and cached). Read access is per-user.
- **`user_social_profiles`** — the candidate's social links (auto-filled on the
  Preparation screen; platform auto-detected).

Workspace-scoped (per report):
- **`first_impression_reports`** — headline scores + decision + override flags.
- **`resume_analysis`** — Engine 1 sub-scores + parse metadata.
- **`resume_analysis_details`** — flexible `kind`/`label` rows (skills, sections,
  rules, strengths, weaknesses, recommendations, structured CV data).
- **`social_analysis`** — the social roll-up (score, boost, counts, confidence).
- **`social_profiles_snapshot`** — one row per source evaluated.
- **`social_signals_snapshot`** — normalised metric rows per source.

Per-job controls on `jobs`: `first_impression_enabled`, `min_first_impression_score`.
New application status: **`filtered_pre_ai`** (“Filtered Before AI”).

## 5. Surfaces

- **Application Preparation** (the `portal.prepare` view, served at
  `GET /open-jobs/{jobId}/prepare`; the applicant surface uses clean unprefixed
  URLs — `portal.*` here names the **view**, not a `/portal/*` route — plus the
  inline equivalent on the public job page): social auto-fill + CV library
  select/upload (résumé mandatory).
- **Decision Center → “First Impression” tab** (candidate file): the full report,
  read-only, with a one-click **Override → allow AI interview** for filtered
  candidates (permission `pipeline.manage`).
- **Candidate “My Insights”** (`/my-insights`): the candidate's own reports,
  **read-only**, across every workspace, with a note that they refresh on each
  new application.
- **First Impression Analytics** (`/reports/first-impression`): applicants,
  passed/filtered, average score, distribution, top & missing skills, common
  weaknesses, funnel, conversion rate, and **AI credits saved** (interviews
  avoided + estimated tokens/cost), filterable per job, with a per-month table.
- **Workflow triggers**: `First Impression Completed / Passed / Failed /
  Override`, each carrying the report headline as node outputs. The bus events are
  `first_impression.completed` / `.passed` / `.failed` / `.overridden`
  (all past-tense per Constitution §7).

## 6. Guarantees

- **Zero AI credits** below the threshold (and on the whole gate). The gate is
  pure rule logic; the AI interview is only ever scheduled **after** a pass (or an
  explicit HR override).
- **Nothing is lost.** A filtered applicant is still a saved candidate with a CV
  and a complete report visible to HR.
- **Resilient & non-fragile.** Social fetching degrades gracefully (unreachable =
  neutral, never an exception, never a penalty); PDF parsing falls back natively
  if the library is absent; the whole social path is optional (Integration off →
  résumé-only scoring).
- **No breaking changes.** Opt-in per job; existing routes, contracts, module
  boundaries, and flows are untouched.

## 7. Extending it

- **A new résumé format** → implement `ResumeParserInterface`, register it in
  `ResumeParserManager`. Nothing else changes.
- **A new social source** → implement `SocialAdapter` in the Integration Platform,
  register it in `SocialAdapterRegistry` (specific adapters before the website
  catch-all). Recruitment and the scoring engine are untouched. Shipped adapters,
  all via **official, unauthenticated, legal** public APIs (or neutral recognition
  for login-walled platforms): **GitHub**, **GitLab**, **StackOverflow**,
  **dev.to** (article tags = strong technical signal), a **website** catch-all
  (title/meta/SSL/tech hints), and a neutral **RecognizedProfileAdapter** for
  LinkedIn/X/… (recorded, never scraped, always neutral). Every adapter is
  resilient (failure → unreachable/neutral, never an exception, never a penalty)
  and the whole layer is optional — **the résumé + job match remains the basis**;
  social is a bounded ±30% helper focused on **job relevance** (overlap of the
  candidate's public technical footprint with the job's required skills), not on a
  raw popularity score.

---

### Related Documents
`AI_ENGINE.md` · `JOB_CONFIGURATION_AND_SCREENING.md` · `ARCHITECTURE.md` ·
`INTEGRATION_PLATFORM.md` · `DATABASE_ARCHITECTURE.md` · `ER_DIAGRAM.md` ·
`WORKFLOW_EVENTS.md` · `RECRUITMENT` (`FEATURE_SPECIFICATIONS/Recruitment.md`)
