# NutraAxis SEO Operations Platform — Build Specification

**Version 2.1 · 2026-09-28 · Track C (portal extension) + Content Engine loop**  
Companion to: *NutraAxis SEO Operations Platform Framework v2* (Word)  
Source of truth for Cursor. Work **one phase at a time**. Do not start a phase until the previous phase’s acceptance criteria pass. When the spec and existing code disagree, the spec wins; propose a change in the PR description rather than silently diverging.

---

## 0. Locked decisions (2026-09-22)

| # | Decision |
|---|---|
| 1 | **Portal extension** — build inside the existing NutraAxis PHP operations portal (`nutraaxis` / `nutraaxisweb`). No Laravel greenfield, no separate `seo-ops` app. |
| 2 | **SEO-standalone until Research App lands** — local taxonomy + in-repo prompts in `seo_ops` / Prompt tables. When Research Config / Prompt Lab exist, read them via shared views; do not block S0–S4 on Track A/B. |
| 3 | **Content + social first** — new domain needs demand creation before deep analytics. Phase 2 stubs for modules not needed to produce/publish/promote. |
| 4 | **Jobs vs AI** — nightly/batch work on **Azure Function Apps + Python** (cheap, idempotent). AI only where generative judgment is required (briefs, drafts, social copy, claims pre-check, digests, fix specs). |
| 5 | **No Adobe CMS write path** — editors publish in CMS manually; pipeline records `published_url`; crawler verifies. Revisit only when Adobe has capable AI/MCP integrations. |
| 6 | **Attribution (store join) deferred** — Performance phase 2; UTM + Magento/store read is not a S0–S3 blocker. |

**Boundary (unchanged):** system of record for SEO + content ops. Never writes to the website or any ad platform. Paid media is read-only when connected. No PII to AI providers.

---

## 1. Purpose (new-domain reality)

nutraaxislabs.com is a **new domain**. Flat GSC/GA4 charts are expected until content and social drive crawl/index/engagement. The first product value is the **Content Engine loop** (§3A):

1. **Interests & Sources** — define what to watch (human).
2. **Harvest** — scheduled search and crawl of those sources (automated, no AI).
3. **Synthesize** — compile harvested items into topics (AI), then interpret and accept (human).
4. **Generate** — single posts, series, and email campaigns from accepted topics (AI draft, human approve, clinical gate).
5. **Track** — responses, engagement, and site performance per asset, fed back into steps 1 and 3.

SEO (keywords, long-form articles, technical health) runs alongside and shares the same keyword list, review gates, and tracking. Measurement is the **feedback loop**, not the first deliverable.

---

## 2. Environments & stack

| Env | Where | Details |
|---|---|---|
| Local | Existing NutraAxis MAMP / local PHP | Same portal codebase; MySQL schema `seo_ops` (or `dbo`-style tables with `Seo` prefix if matching portal SQL Server conventions — **prefer Azure SQL / MySQL to match portal’s live DB**; follow existing `sql/NNN_*.sql` + `node scripts/run-sql-file.js` pattern). |
| Staging / Production | `nutraaxisweb` App Service + existing Function Apps | Selective FTP or git-push deploy per `AGENTS.md`. SEO jobs register in `functions/src/lib/process-runner.js` like other nightly jobs. |

### Stack conventions (portal, not Laravel)

- PHP pages: `includes/init.php` first; domain logic in `includes/seo-*.php`; hub via `includes/app.php` + `MODULE_PERMISSION_COLUMNS` in `includes/auth.php`.
- UI: existing operations CSS / `render_list_page_header` / form-group patterns — no Tailwind/Laravel Blade.
- Workers: Node/Python Function App jobs under `functions/src/lib/jobs/seo-*.js` (or `.py` via the same runner pattern used for heavy jobs). Prefer **Python** for crawl/GSC/GA4/Semrush REST when libraries are clearer; Node is fine when matching existing clients.
- Auth/RBAC: extend portal roles; map Framework roles → portal permission columns (Section 8).
- Secrets: App Service settings / Key Vault — never commit `.env` secrets.
- Nav: one hub **SEO Operations** under Admin (or Marketing group if added); leaf modules listed below. Phase 2 stubs appear as hub cards labeled **Phase 2** and stay in `app_nav_hidden_module_slugs()` or show a “Coming soon” leaf until built.

---

## 3. Module map

| Module | S1–S4 | Phase 2 stub | Notes |
|---|---|---|---|
| Config & Taxonomy | Live (thin) | — | Competitors, brand terms, settings, therapeutic areas (local seed) |
| Keyword Universe | **S1** | — | Seed, cluster, priority, map-to-page |
| Content Pipeline | **S1** | — | Kanban + AI brief/draft/claims + medical gate + publish URL |
| Task Engine (thin) | **S1** | Full SLA / templates later | Assign editor + social; Friday roll-up light |
| Social queue | **S1** | — | Content type `social`; calendar/status; Claude drafts; coordinator executes off-platform |
| Page Inventory | **S2** | — | Grows as URLs publish; light crawl |
| Performance (thin) | **S3** | Full + attribution | GSC/GA4 explore, recommendations → content/tasks |
| Audit & Issues | **S4** | — | OpenRush + crawler checks |
| Rank Tracker | Stub nav | **Phase 2** | Until rankings exist at scale |
| Backlinks & Outreach | Stub nav | **Phase 2** | |
| Reports (full) | Monday digest in S3 | Monthly Word/PDF Phase 2 | |
| Admin & Governance | Jobs + api_usage in S0 | Prompt Lab polish Phase 2 | |

---

## 3A. Content Engine — five-stage loop

The engine is a loop, not a line: stage 5 results re-weight stages 1 and 3. Three rules hold throughout:

- **Python fetches; AI interprets and discovers.** Known sources are fetched and deduplicated by plain Python jobs. AI runs on a schedule too — in batch mode to interpret the queue, and as a research agent to find what the feeds miss (see "AI execution modes").
- **Humans own interpretation and release.** AI proposes topics and drafts; only a person accepts a topic, approves an asset, or replies to a response.
- **Tracking is designed in at generation, not bolted on after.** Every asset gets an `asset_id` and UTM tags when it is created, or stage 5 cannot attribute results.

### Stage overview

| # | Stage | Hub card | Driver | Runs on | AI role | Output |
|---|---|---|---|---|---|---|
| 1 | Interests & Sources | Interests & Sources | Human setup; monthly review | PHP UI | Assist only: suggest terms (Semrush/OpenRush), draft prompts | Interest profiles, terms, sources, prompts, schedules |
| 2 | Scheduled search & crawl | Content Harvester | Scheduled | Function App (Python) timers + weekly AI research agent | None for fetching; research agent discovers new items and sources | Deduplicated `harvested_items` queue |
| 3 | Compile & synthesize | Topic Synthesis | Automated compile, then human interpretation | Nightly batch AI (Anthropic Message Batches / OpenAI Batch) + PHP Topic Board | Score relevance, tag, extract study facts, cluster, summarize | Topics: proposed → accepted, with human angle + evidence |
| 4 | Generate posts & campaigns | Campaign Studio → Publishing Calendar | Human-triggered | AI runner + review gates | Draft single posts, series, email | Campaigns and assets with UTM, claims score, approvals |
| 5 | Track responses & performance | Engagement & Performance | Scheduled collection; human responses | Function App ingest + PHP inbox | Triage responses, weekly narrative | Metrics per asset, response inbox, scores fed back to 1 and 3 |

### Stage 1 — Interests & Sources (human)

An **interest** is a watched topic tied to the taxonomy (therapeutic area, product line, audience), e.g. "GLP-1 companion nutrition", "berberine evidence", "practitioner dispensary trends". Each interest holds:

- Include terms, exclude terms, hashtags, and search queries.
- Priority, harvest cadence, owner.
- Prompt versions (from Prompt Lab) used for relevance scoring and synthesis.
- Linked sources (many-to-many).

Interest terms and the SEO **Keyword Universe** share one keyword table with a `purpose` flag — no second keyword list.

Source types and realistic access:

| Source type | Examples | Access |
|---|---|---|
| RSS / Atom | NutraIngredients, CRN, NBJ, Examine, AHPA; FDA / FTC feeds; journal feeds | `feedparser` — S1 |
| Search-as-feed | Google News RSS queries, Google Alerts RSS, PubMed E-utilities saved searches, ClinicalTrials.gov API | API/RSS — S1 |
| Targeted site crawl | Competitor blogs, association news pages | robots-respecting, rate-limited crawler — S1 |
| Keyword trend signal | Semrush / OpenRush keyword volume for interest terms | Weekly job, unit budget guard — S1 |
| Social (open) | Reddit subreddit/search RSS, YouTube channel RSS | RSS — S1 |
| Social (restricted) | X, LinkedIn | Paid or restricted APIs — Phase 2 or manual clip |
| Interest groups | Facebook groups, closed practitioner communities | Not programmatically accessible — manual clip only |
| Newsletters | Practitioner associations, competitor newsletters | Dedicated mailbox parse + manual PDF/URL import — S2 |
| AI research agent | Weekly per-interest discovery queries across the open web | Claude / OpenAI with web search tool — S1a; every cited URL verified by Python before use |

Every source records terms-of-service notes, auth reference (Key Vault), schedule, and health.

### Stage 2 — Content Harvester (scheduled, no AI)

- One job per source type: `research-harvest-rss`, `research-harvest-search`, `research-harvest-crawl`, `research-harvest-trends`, later `research-harvest-newsletter`.
- Per-source schedule (hourly / daily / weekly) from stage 1.
- Dedup by canonical URL hash and content hash; near-duplicates by simhash (embeddings optional, not an LLM call).
- Raw payload to Blob; cleaned text + metadata to `harvested_items` with status `new`.
- Source health: last success, consecutive failures, auto-pause after N failures, surfaced on the card.
- Manual "add item" (URL, PDF, pasted text) enters the same queue.
- **AI research agent** (`research-agent-discover`, weekly per interest): runs the interest's discovery prompt with a web search tool. Results enter `harvested_items` with `source_type = ai_research` and the prompt version. Python fetches every cited URL; items whose URL fails or does not contain the claimed content are rejected. Domains the agent finds repeatedly are proposed as new sources for stage 1.

### AI execution modes

| Mode | Used for | Why |
|---|---|---|
| Python only (no model) | Fetching, crawling, dedup, URL verification, metrics ingest | Deterministic and nearly free; LLMs add cost with no benefit here |
| Embeddings | Near-duplicate detection, clustering input | Cheap; not a generative call |
| Batch AI — Anthropic Message Batches / OpenAI Batch (~50% of standard price, results within 24 h) | Nightly relevance scoring, tagging, study-fact extraction, cluster summaries, weekly synthesis | Context-aware interpretation at the lowest cost; nothing waits on it |
| Scheduled real-time AI with web search | Weekly research-agent discovery per interest | Needs live search and multi-step reasoning |
| Interactive real-time AI | Topic Board assists, Campaign Studio drafts, response triage, claims check | A person is waiting on the result |

Claude is the primary provider and OpenAI the secondary (same dual-provider runner); every run logs provider, mode, prompt version, tokens, and cost to `api_usage`.

### Stage 3 — Topic Synthesis (AI compile + human interpretation)

**3a Automated (after each harvest batch):**

- Relevance score per interest; items below threshold are discarded (kept for audit, hidden by default).
- Taxonomy tags and evidence type (peer-reviewed, regulatory, news, competitor, opinion).
- Weekly clustering of relevant items into **candidate topics**, each with a summary, "why it matters", source list, and trend signal (item velocity, source diversity, keyword volume change).
- Peer-reviewed and regulatory items are flagged for promotion to **Literature & Intelligence** (the durable evidence library).

**3b Human — the Topic Board:**

- Operator accepts, rejects, merges, or parks candidate topics.
- Operator writes the **angle**: NutraAxis point of view, audience, what to avoid.
- Operator links Claims Matrix entries and Literature items as allowed evidence.
- Only an accepted topic can seed generation. AI never promotes a topic on its own.

AI run history for 3a lives on the Topic Synthesis card (replaces the separate Research Runs card).

### Stage 4 — Campaign Studio → Publishing Calendar (AI draft, human release)

From an accepted topic (or a keyword / published article), the operator picks a format:

| Format | Description |
|---|---|
| Single post | One message with per-channel variants (LinkedIn, X, Facebook, Instagram caption) |
| Series | N posts over a cadence with a narrative arc (e.g. 5-part evidence explainer) |
| Email | Single send or sequence; subject/preview variants; delivered through GoHighLevel |
| Long-form | Hands off to **Content Pipeline** (web article, SEO, CMS publish) |

Every generated **asset** carries:

- `asset_id`, `campaign_id`, channel, sequence number, body, media notes, CTA URL.
- UTM on the CTA: `utm_source={channel}`, `utm_medium=social|email`, `utm_campaign={campaign_slug}`, `utm_content={asset_id}`.
- Claims score, review state, scheduled time, and — once posted — external post URL / ID.

Gates (same engine as Content Pipeline):

- Claims check runs on every asset.
- Medical review is required when claims flags exist or the asset references efficacy or a condition.
- Editorial approval before an asset reaches the calendar. Editors cannot approve their own work.
- Brand voice and audience (practitioner vs consumer) come from Research Config.

Distribution — **Publishing Calendar** (social + email in one schedule), delivered through **GoHighLevel**:

- v1: coordinator loads approved assets into GHL Social Planner / email campaigns and records the GHL post or campaign ID on the asset.
- v2: portal pushes approved assets via the GHL API — Social Planner posts (per-platform variants, scheduled time) and email campaigns as drafts. A person still approves every asset; the platform never posts autonomously.
- GHL Social Planner **RSS auto-post stays off** (it would bypass the claims gate). GHL Content AI is not used for claims-bearing copy.
- Optional: interest topics map to GHL tags on email links so GHL workflows can start follow-up sequences. Contact data never leaves GHL.

### Stage 5 — Engagement & Performance (scheduled collection, human response)

Collected on schedule and joined to `asset_id`:

| Signal | Source |
|---|---|
| Per-asset clicks → site sessions, engagement, conversions | GA4 by `utm_content` = `asset_id` (primary per-post social signal) |
| Social account totals (reach, engagement) by date range | GHL Social Planner statistics API — account-level, **not per post** |
| Per-post likes / comments / shares | Not in GHL API; native platform APIs (Phase 2) or manual entry |
| Email sent, delivered, opens, clicks, replies, unsubscribes, bounces | GHL campaign stats API (per campaign / workflow step / bulk action) |
| New leads by campaign | GHL contact attribution (UTM) — nightly job aggregates counts and discards PII |
| Organic search | GSC for long-form pages |

**Response Inbox:** DMs and email replies via the GHL Conversations API; public post comments where an API allows (GHL comment coverage to be confirmed before S3). AI triages each one (question / praise / complaint / claims-risk). Claims-risk responses escalate to the Medical Director within 24 hours. People write the replies.

**Scoring and feedback:** asset score rolls up to campaign → topic → interest. The weekly digest recommends: double down on a topic, start a follow-up series, retire an interest, or add a source. Scores adjust interest priority (stage 1) and relevance weights (stage 3).

**PII scope (change from v2.0):** responses contain names and handles. Store only the external URL, text, and timestamp; redact handles before any AI call; never store email contact lists here — GoHighLevel stays the contact system of record, and email metrics are aggregate per asset.

### Content Engine data model

| Table | Purpose |
|---|---|
| `interests`, `interest_terms` | Watched topics and their include / exclude / hashtag / query terms |
| `sources`, `interest_sources` | Typed source registry and many-to-many link to interests |
| `harvest_runs`, `harvested_items` | Job runs and the deduplicated item queue (`new` → `scored` → `discarded` / `clustered` / `promoted`) |
| `item_scores`, `item_tags` | Relevance per interest, taxonomy and evidence tags |
| `topics`, `topic_items`, `topic_briefs` | Candidate / accepted topics, member items, human angle and evidence links |
| `campaigns`, `assets`, `asset_versions`, `asset_reviews` | Single / series / email campaigns and their gated assets |
| `asset_metrics_daily` | Per-asset social, email, and site metrics |
| `responses` | Response inbox rows with triage label and escalation state |
| `interest_scores`, `topic_scores` | Rolled-up performance used by the feedback loop |

### Content Engine jobs and prompts

| Job (no LLM) | When |
|---|---|
| `research-harvest-rss` / `-search` / `-crawl` / `-trends` | Per-source schedule |
| `research-harvest-newsletter` | On mailbox poll (S2) |
| `research-verify-citations` | After each research-agent run |
| `research-batch-submit` / `research-batch-collect` | Nightly submit; collect when the batch completes |

| Scheduled AI job | When |
|---|---|
| `research-agent-discover` (web search) | Weekly per interest |
| `engagement-social-ingest`, `engagement-email-ingest` | Daily |
| `engagement-score` | Daily after ingest |

| Prompt (AI) | Stage |
|---|---|
| `research.discover` (with web search) | 2 |
| `research.relevance_score`, `research.tag_item`, `research.extract_study` | 3a (batch) |
| `research.cluster_summary` | 3a |
| `campaign.single_post`, `campaign.series_plan`, `campaign.series_post`, `campaign.email` | 4 |
| `seo.claims_check` (shared) | 4 |
| `engagement.response_triage`, `engagement.weekly_digest` | 5 |

---

## 4. Jobs vs AI

### 4.1 Function App / Python jobs (no LLM)

| Job | When | Purpose |
|---|---|---|
| `seo-gsc-ingest` | Nightly | Search Analytics → `gsc_daily` |
| `seo-ga4-ingest` | Nightly | Landing/channel/events → `ga4_daily` |
| `seo-crawl` | Weekly + on-demand URL | Sitemap/pages → `page_crawls`, metadata diffs |
| `seo-semrush-keywords` | Weekly / on demand | Metrics history; unit budget guard |
| `seo-analyze` | Nightly after ingest | Priority scores, striking-distance, decay flags → `recommendations` |
| `seo-verify-published` | Hourly | Published URLs without `page_id` → crawl + link |
| `seo-openrush-audit` | Monthly | Findings → issues (S4) |
| `seo-alerts` | Daily | Threshold rules → Teams/Zapier webhook |

OpenRush MCP remains for **interactive** console/operator analysis in Cursor/Claude. Scheduled owned-data pulls use **Google APIs direct** once the service account is on GSC/GA4.

### 4.2 AI-only (on demand / queued)

| Prompt | Use |
|---|---|
| `seo.brief` | Content brief from keyword + SERP + evidence hooks |
| `seo.draft_article` / `seo.draft_pdp` | First draft |
| `seo.social_post` | Platform-ready post from published/approved asset |
| `seo.claims_check` | Score + flags; blocks medical_review if score &lt; 7 |
| `seo.meta_rewrite` | Title/meta suggestions → content_item `meta_rewrite` |
| `seo.cluster_keywords` | Clustering assist (operator approves) |
| `seo.weekly_digest` | Narrative + top 5 actions (S3+) |
| `seo.fix_spec` | Issue → developer fix spec (S4) |

Do **not** use AI to parse GSC CSVs, dedupe crawl rows, or compute CTR.

---

## 5. Data model (core)

Schema/tables as in Framework v2 §6 / prior BUILD_SPEC §5, delivered as `sql/NNN_create_seo_ops_*.sql` migrations (portal style), not Laravel migrations.

**Ship with S0–S1:** config tables, `keywords` / clusters / metrics_history, `content_items` / `content_versions` / `content_reviews`, `tasks` / `task_templates` (minimal), `jobs` / `api_usage` / `audit_log` / `prompts` / `settings` / `competitors` / `brand_terms`.

**S2:** `pages`, `page_crawls`, `page_keyword_map`, `page_links` (optional early).

**S3:** `gsc_daily`, `ga4_daily`, `page_weekly` / `kw_weekly` aggregates, `recommendations`, `reports` (weekly), `alerts` / `alert_rules`.

**S4:** `audits`, `issues`, `issue_urls`.

**Phase 2:** `rank_snapshots`, backlinks/prospects/outreach, store `conversions` join, monthly report blobs.

Content stages (enforced in PHP service layer):

`idea → brief → draft → medical_review → editorial → approved → published → monitoring`

- `draft → medical_review` requires `claims_score`.
- `medical_review → editorial` requires medical `approved` review row.
- `approved → published` requires `published_url` (manual CMS publish).
- Editors cannot approve their own versions.

Social items are `content_items.type = 'social'` (or linked child tasks). They may skip medical review when copy is non-claims; claims-bearing social still goes through medical.

---

## 6. Portal surfaces

Hub slug: `marketing`  
Display title: **Marketing & Research Hub**  
Href: `/marketing/`  
Group: `marketing` (Ops home section, above Supply Chain)  
Permission column: `Marketing` (label: Marketing & Research)

### Target card layout (organized by the Content Engine loop)

Five existing placeholder cards are renamed or merged so each loop stage has exactly one home. The card count stays at 20.

**Content Engine — the loop**

| # | Card | Slug | Path | Replaces |
|---|---|---|---|---|
| 1 | Interests & Sources | `research-interests` | `/marketing/interests/` | Research Config (taxonomy moves to a tab here) |
| 2 | Content Harvester | `research-harvester` | `/marketing/content-harvester/` | — (runs, queue, source health) |
| 3 | Topic Synthesis | `research-topics` | `/marketing/topics/` | Research Runs (run log becomes a tab) |
| 4 | Campaign Studio | `marketing-campaigns` | `/marketing/campaigns/` | Post Candidates |
| 4 | Publishing Calendar | `marketing-calendar` | `/marketing/calendar/` | Social Queue (now social + email) |
| 5 | Engagement & Performance | `marketing-performance` | `/marketing/performance/` | Performance (adds Response Inbox) |

**Supporting libraries and governance**

| Card | Slug | Path | Used by stages |
|---|---|---|---|
| Keyword Universe | `marketing-keywords` | `/marketing/keywords/` | 1 (interest terms), SEO |
| Literature & Intelligence | `research-literature` | `/marketing/literature/` | 3, 4 (evidence) |
| Claims Matrix | `research-claims` | `/marketing/claims-matrix/` | 3, 4 (gate) |
| Prompt Lab | `research-prompt-lab` | `/marketing/prompt-lab/` | 1, 3, 4, 5 |
| Tasks | `marketing-tasks` | `/marketing/tasks/` | 4, 5 (human work) |
| Output Generator | `research-output` | `/marketing/output-generator/` | Reports, exports |

**SEO & Site**

| Card | Slug | Path | Phase |
|---|---|---|---|
| Content Pipeline (long-form web) | `marketing-content` | `/marketing/content/` | S1b |
| Page Inventory | `marketing-pages` | `/marketing/pages/` | S2 |
| Audit & Issues | `marketing-issues` | `/marketing/issues/` | S4 |
| Rank Tracker | `marketing-ranks` | `/marketing/ranks/` | Phase 2 |
| Backlinks & Outreach | `marketing-backlinks` | `/marketing/backlinks/` | Phase 2 |
| Reports | `marketing-reports` | `/marketing/reports/` | Phase 2 |

**Other**

| Card | Slug | Path | Notes |
|---|---|---|---|
| Original Research | `research-production` | `/marketing/original-research/` | Research Track B (surveys / studies) |
| Admin & Jobs | `marketing-admin` | `/marketing/admin/` | Job monitor, API usage, settings |

Nothing auto-publishes or auto-posts. Distribution push to the scheduler / GoHighLevel is a v2 decision (§3A stage 4).

---

## 7. Roles (portal mapping)

| Framework role | Portal capability |
|---|---|
| Admin | Full SEO module + Key Vault / budgets |
| Console Operator | Run jobs, approve keywords/briefs, assign tasks, record publish, social approve |
| Reviewer (medical / editorial) | Review content versions only |
| Editor (offshore) | Edit assigned drafts; record published URL; no self-approve; no api_usage |
| Coordinator (social) | Social queue tasks only |
| Developer | Issues fix/verify; crawl read |
| Viewer | Read dashboards |

Offshore must not see API keys, cost burn, or store attribution detail.

---

## 8. Integrations

| System | Path | Notes |
|---|---|---|
| Semrush | REST from Function App | Same units as MCP; budget abort |
| OpenRush | MCP interactive; optional job for `audit_site` | Credits in `api_usage` |
| GSC / GA4 | Service account direct | Preferred for nightly; OpenRush OK until SA wired |
| Google Ads | OpenRush read-only when account activated | Not required for S1 |
| Anthropic / OpenAI | On-demand AI runner | Logged per run |
| CMS | **None** | Manual publish |
| Store DB | Phase 2 | |
| Zapier / Teams | Webhooks for digest/alerts | |
| RSS / search feeds | `feedparser`, Google News RSS, PubMed E-utilities, ClinicalTrials.gov | Content Harvester — S1a |
| GoHighLevel (sub-account Private Integration Token, `Version` header) | Distribution + contact system of record: Social Planner (scheduler), email campaigns, conversations, lead attribution | `GHL_PIT`, `GHL_LOCATION_ID` in App Service / Key Vault; rotate every 90 days (7-day overlap). Start read-only scopes (Social Planner post/account read, email stats read, contacts read); add Social Planner post write + email campaign write only when v2 push is approved |
| X / LinkedIn APIs | Paid / restricted | Phase 2 harvesting; manual clip until then |

---

## 9. Build phases and acceptance criteria

One feature branch per phase: `phase/seo-s0-chassis`, `phase/seo-s1-demand`, …

### S0 — Chassis (~1 week)

- SQL: config, settings, competitors, brand_terms, jobs, api_usage, audit_log, prompts, users permission columns.
- Hub `marketing` (Marketing & Research Hub) + placeholder cards (done 2026-09-25) + Admin jobs/usage page.
- Process-runner registration for `seo-noop` (writes `jobs` row).
- Rename placeholder cards to the §6 target layout.
- Acceptance: migrate clean; operator opens hub; noop job visible in jobs list; `audit-portal-nav.php` clean.
- **As built (2026-09-28):** `sql/152_create_marketing_chassis.sql` adds `MktSetting`, `MktPrompt`, `MktApiUsage`. Job runs reuse `dbo.ProcessExecutionLog`; operator writes reuse `dbo.AuditChangeLog`; brand terms and competitors are line-list settings rather than separate tables. Jobs run in the existing Node Function App (`functions/src/lib/mkt/registry.js`, merged into the process runner); PHP registry in `includes/marketing-jobs.php`. Admin & Jobs (`/marketing/admin/`) requires full Marketing CRUD so editors never see spend or settings. Local runs: `node scripts/run-marketing-job.js <code>`.

### S1a — Intake: stages 1–2 (~2 weeks) — **priority**

- Interests & Sources UI: interests, terms (shared keyword table), source registry, schedules, taxonomy tab.
- Keyword Universe basic CRUD + CSV import (interest terms and SEO keywords in one table).
- Harvester jobs: RSS, search-as-feed (Google News, PubMed, ClinicalTrials.gov), targeted crawl, keyword trends; dedup; source health; manual add item.
- Weekly AI research agent per interest, with Python citation verification.
- Acceptance: 10+ sources configured across 3+ interests; scheduled runs fill `harvested_items` with no duplicates on re-run; a failing source auto-pauses and shows on the card; fetch jobs make no LLM calls (verified in `api_usage`); research-agent items with unverifiable URLs are rejected.
- **As built (2026-09-28):** `sql/153_create_marketing_intake.sql` adds `MktKeyword`, `MktKeywordMetric`, `MktInterest`, `MktInterestTerm`, `MktSource`, `MktInterestSource`, `MktHarvestRun`, `MktHarvestedItem` and seeds prompt `research.discover` v1. Pages: `/marketing/interests/` (interests, sources, taxonomy), `/marketing/content-harvester/` (queue, runs, manual add, suggested sources), `/marketing/keywords/` (CRUD + CSV import), `/marketing/prompt-lab/` (versions, activate). Jobs: `research-harvest-due` (timer `marketing-harvest`, hourly at :15; adapters in `functions/src/lib/mkt/adapters.js`; dedup by canonical-URL hash, content hash, and 64-bit simhash) and `research-agent-discover` (timer `marketing-research-agent`, Monday 11:00 UTC). Citation verification runs in Node, not Python: each cited URL is fetched and kept only if the quote or title is on the page; 404/unreachable → `rejected`; sites that refuse all automated clients (401/403/429/503, e.g. ods.od.nih.gov) stay `new` with an `UNVERIFIED` note for manual confirmation. AI calls go through `functions/src/lib/mkt/ai.js` (Anthropic Messages / OpenAI Responses, web search, monthly budget guard, cost in `MktApiUsage`). Keyword trends (Semrush) deferred to S2 with GSC.

### S1b — Produce: stages 3–4 (~3 weeks)

- Topic Synthesis: nightly batch scoring, tagging, and study-fact extraction; weekly clustering; Topic Board (accept / reject / merge / angle / evidence links).
- Campaign Studio: single post, series, and email generation from an accepted topic; claims check; medical + editorial gates; UTM + `asset_id` on every asset.
- Publishing Calendar: social + email schedule; coordinator records external post URL / ID.
- Content Pipeline (long-form web) with the same gate engine; thin Task Engine.
- Acceptance: harvested items → accepted topic → 5-part series + email drafted → claims-flagged asset blocked until medical approval → calendar shows scheduled assets with valid UTMs; editor cannot self-approve; AI costs in `api_usage`.

### S2 — Findable + collect (~2 weeks)

- GA4 + GSC nightly ingest; UTM → `asset_id` join; GoHighLevel email stats ingest.
- Page Inventory, `page_keyword_map`, verify published long-form URLs.
- Newsletter mailbox ingest for the harvester.
- GHL ingest: email campaign stats, Social Planner account statistics, lead counts by campaign (aggregate only).
- Acceptance: a test asset's UTM clicks appear against its `asset_id`; email stats per asset match GoHighLevel within 2%; no GHL contact PII persisted; ingest re-runs do not duplicate rows.

### S3 — Engagement loop: stage 5 (~2 weeks)

- Social metrics ingest by external post ID; Response Inbox with AI triage and 24-hour claims-risk escalation.
- Asset → campaign → topic → interest scoring; scores adjust interest priority and relevance weights.
- Monday digest (AI narrative) → ≤ 5 operator tasks, including "double down / follow-up series / retire interest".
- Acceptance: a claims-risk comment creates a Medical Director task; digest recommendations trace to scored assets; dashboards show empty states (not errors) on sparse new-site data.

### S4 — Harden (~2 weeks)

- OpenRush audit + crawler issue types; Issues UI; fix_spec AI; verify → recrawl.
- Alert rules (job failed, traffic drop when baseline exists, legacy brand string).
- Acceptance: duplicate audit does not duplicate fingerprints; recur → reopened; developer export works.

### Phase 2 (stubs only until scheduled)

- X / LinkedIn harvesting via paid APIs (budget decision).
- Push approved assets to the scheduler / GoHighLevel as scheduled drafts (if approved).
- Rank Tracker (Semrush/GSC positions at scale).
- Backlinks & Outreach.
- Full Reports (monthly Word/PDF).
- Attribution store join.
- Richer Task SLA / offshore MFA policies if not already in portal.
- Adobe CMS integration — **explicitly out of scope** until Adobe AI/MCP exists.

**Hardening after S4:** Key Vault for SEO keys, job failure alerts, unit-burn dashboard, runbook `docs/seo-ops/RUNBOOK.md`.

---

## 10. Operating cadence (once S1 live)

| Cadence | Focus |
|---|---|
| Daily (automated) | Harvest runs; metrics ingest; response triage |
| Daily (human) | Response Inbox replies and escalations; alert triage; task queue |
| Weekly (human) | Topic Board review (accept 2–4 topics, write angles); generate and approve posts / series / email; calendar check; Monday digest |
| Monthly (human) | Interests & Sources review — add / pause sources, re-weight interests from scores; (S4) audit triage; competitor gap refresh |
| Quarterly | Keyword universe re-score; prompt review; retire low-yield interests |

Console operator target remains ~8–10 h/week once habits form; early weeks may run higher while seeding keywords and backlog.

---

## 11. Definition of done (every PR)

- SQL migrations runnable via `node scripts/run-sql-file.js`; reversible notes in header comment.
- Portal nav audit passes; breadcrumbs use `app_module_hub_back_link('…')`.
- No secrets committed; no website/ad writes.
- Every operator write → audit log; every external API → `api_usage`.
- Jobs idempotent; AI only on listed prompts.
- PR lists phase, acceptance criteria verified, any spec deviations proposed.

---

## 12. Supersedes

- BUILD_SPEC **v1.0** (Laravel / separate `seo-ops` repo / measurement-first S0–S8) — **obsolete**.
- Framework v2 §10 phase table — follow **this document’s §9** instead; Framework activities (§2) and data concepts (§6) remain valid.
- Framework open decision “Laravel vs portal” — **resolved: portal**.
- Framework CMS write exploration — **deferred indefinitely** pending Adobe AI/MCP.

---

## 13. Open decisions (Content Engine)

| # | Decision | Recommendation |
|---|---|---|
| 1 | Social scheduler | **Resolved 2026-09-28: GoHighLevel Social Planner** (API supports create / schedule / list posts). Per-post social engagement is not in the GHL API — per-post signal comes from UTM clicks in GA4. |
| 2 | v2 push of approved assets to GoHighLevel | Yes, as scheduled posts / draft campaigns after human approval; never autonomous posting. Requires adding write scopes to the Private Integration. |
| 3 | Paid X / LinkedIn API for harvesting | Defer; use open RSS sources + manual clip until the loop proves value. |
| 4 | PII scope for Response Inbox | Accept minimal storage (URL, text, timestamp) with handle redaction before AI. |

## 14. Immediate next step after this spec

Rename the placeholder cards to the §6 target layout, then start **S1a** (Interests & Sources + Harvester) on branch `phase/seo-s1a-intake`.
