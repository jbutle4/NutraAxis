<?php

require_once __DIR__ . '/marketing.php';
require_once __DIR__ . '/marketing-jobs.php';
require_once __DIR__ . '/marketing-intake.php';
require_once __DIR__ . '/marketing-topics.php';
require_once __DIR__ . '/marketing-campaigns.php';
require_once __DIR__ . '/marketing-content.php';
require_once __DIR__ . '/marketing-engagement.php';
require_once __DIR__ . '/marketing-tasks.php';
require_once __DIR__ . '/marketing-issues.php';

/*
 * User Manual content for Marketing & Research. Text is plain; **bold** is the only markup.
 * Links are [slug, suffix]: the slug resolves through the hub registry so the manual follows page moves.
 * Schedules, task deadlines, alert rules and compliance rules are read live from the registry and settings.
 */

/** @return array<string, array{title: string, href: string, desc: string, placeholder: bool}> */
function mkt_manual_page_index(): array
{
    $pages = [];
    foreach (app_marketing_submodules() as $module) {
        $pages[(string) $module['slug']] = [
            'title'       => (string) $module['title'],
            'href'        => (string) $module['href'],
            'desc'        => (string) ($module['desc'] ?? ''),
            'placeholder' => str_contains((string) ($module['desc'] ?? ''), 'Placeholder'),
        ];
    }

    return $pages;
}

/** @param array{0: string, 1?: string}|null $link */
function mkt_manual_link(?array $link): ?array
{
    if ($link === null) {
        return null;
    }
    $page = mkt_manual_page_index()[$link[0]] ?? null;
    if ($page === null) {
        return null;
    }
    $suffix = (string) ($link[1] ?? '');
    $label = $page['title'] . (isset($link[2]) ? ' → ' . $link[2] : '');

    return ['href' => $page['href'] . $suffix, 'label' => $label];
}

function mkt_manual_text(string $text): string
{
    return preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', htmlspecialchars($text));
}

/** What the signed-in user can do, for the "Your access" box. */
function mkt_manual_my_access(): array
{
    return [
        'read'       => true,
        'create'     => marketing_can_create(),
        'update'     => marketing_can_update(),
        'delete'     => marketing_can_delete(),
        'admin'      => marketing_can_admin(),
        'compliance' => mkt_can_compliance_review(),
    ];
}

function mkt_manual_golden_rules(): array
{
    return [
        '**Nothing posts or publishes automatically.** The portal writes drafts and tracks work; a person loads every post into GoHighLevel, publishes every web page (a blog post goes live only when a marketing admin clicks **Publish to blog**), and writes every reply.',
        '**No health claims.** Never say or imply a product or ingredient diagnoses, treats, cures, mitigates or prevents a disease. Anything that touches health, products, ingredients or conditions goes through the claims check and, when flagged, compliance review.',
        '**Nobody approves their own work.** Whoever last edited or submitted an asset or article cannot clear its compliance or editorial review.',
        '**Compliance first, then editorial.** Editorial approval is only possible after compliance clears (or the claims check finds nothing that needs it).',
        '**Possible adverse events go to compliance immediately** and nobody replies until compliance records a decision.',
        '**Times are Central** unless a screen says UTC. Scheduled jobs move one hour earlier (Central) from November to March.',
        '**Only nutraaxislabs.com.** Search Console, GA4 and audit imports refuse any other property.',
    ];
}

function mkt_manual_roles(): array
{
    return [
        [
            'role'   => 'Marketing admin / editor',
            'access' => 'Marketing: Create, Read, Update, Delete (roles: Admin, Management User)',
            'does'   => 'Everything below, plus **editorial approval** of campaign assets and articles, Admin & Jobs (job runs, AI spend, settings), Prompt Lab changes, deletes, and receives alert emails.',
        ],
        [
            'role'   => 'Marketing coordinator / writer',
            'access' => 'Marketing: Create, Read, Update (no Delete)',
            'does'   => 'Works interests and sources, the Topic Board, campaigns and articles up to submission, the Publishing Calendar and GoHighLevel loading, metrics entry, the Response Inbox, issues and tasks. Cannot give editorial approval or open Admin & Jobs.',
        ],
        [
            'role'   => 'Compliance reviewer',
            'access' => 'Role “Marketing Compliance Reviewer” (Marketing: Read, Update + Marketing Compliance Review: Update)',
            'does'   => 'Clears or returns compliance on campaign assets and articles; records the compliance decision on escalated Response Inbox items (claims risk, possible adverse event). Decides whether an adverse event must be reported.',
        ],
        [
            'role'   => 'Read-only viewer',
            'access' => 'Marketing: Read',
            'does'   => 'Sees every page except Admin & Jobs; no action buttons are shown.',
        ],
        [
            'role'   => 'Web developer / site author (outside the portal)',
            'access' => 'Receives fix specs and exports',
            'does'   => 'Makes the website changes described in Audit & Issues fix specs (Adobe Commerce / Edge Delivery storefront). The portal never edits the website.',
        ],
    ];
}

/** Operating rhythm: when each person does what. */
function mkt_manual_rhythm(): array
{
    return [
        ['when' => 'Daily, from 06:00', 'who' => 'Marketing admin', 'what' => 'Read the alert email (sent about 06:00 when something new fires). Open Audit & Issues → Alerts, acknowledge, and act.', 'link' => ['marketing-issues', '?tab=alerts', 'Alerts']],
        ['when' => 'Daily, morning', 'who' => 'Coordinator', 'what' => 'Response Inbox → Needs action: reply on the platform, then record it. Check anything With compliance is moving.', 'link' => ['marketing-performance', 'inbox.php', 'Response Inbox']],
        ['when' => 'Daily', 'who' => 'Everyone', 'what' => 'Tasks → My tasks. Work overdue and high-priority tasks first; automatic tasks close themselves when the work is done.', 'link' => ['marketing-tasks', '?tab=mine', 'My tasks']],
        ['when' => 'Daily', 'who' => 'Compliance reviewer', 'what' => 'Campaign Studio and Content Pipeline → Review queue; Response Inbox → With compliance (24-hour clock).', 'link' => ['marketing-campaigns', '?tab=review', 'Review queue']],
        ['when' => 'Daily', 'who' => 'Coordinator', 'what' => 'Publishing Calendar → Coordinator to-do: load today’s approved posts into GoHighLevel, save the ID, mark posted once live.', 'link' => ['marketing-calendar', '?tab=todo', 'Coordinator to-do']],
        ['when' => 'Monday, from 07:00', 'who' => 'Marketing admin', 'what' => 'Read the Monday digest (Engagement & Performance → Weekly digests). Its recommendations open tasks.', 'link' => ['marketing-performance', '?tab=digests', 'Weekly digests']],
        ['when' => 'Weekly (Mon–Tue)', 'who' => 'Marketing admin + coordinator', 'what' => 'Topic Board: accept 2–4 topics and write their angles; generate and approve the week’s posts, series and emails; check the calendar for gaps.', 'link' => ['research-topics', '?tab=board', 'Board']],
        ['when' => 'Weekly (after the Monday crawl)', 'who' => 'Marketing admin', 'what' => 'Audit & Issues → Open: triage new issues, assign, generate fix specs for developers.', 'link' => ['marketing-issues', '', null]],
        ['when' => 'Weekly', 'who' => 'Coordinator', 'what' => 'Enter or import last week’s post and email metrics (lifetime totals).', 'link' => ['marketing-performance', 'metrics.php', 'Enter metrics']],
        ['when' => 'Monthly', 'who' => 'Marketing admin', 'what' => 'Review Interests & Sources (pause failing or low-yield sources, apply suggested priorities), Emerging interests, AI spend vs budget, and the Keyword map.', 'link' => ['research-interests', '', null]],
        ['when' => 'Quarterly', 'who' => 'Marketing admin', 'what' => 'Re-score the Keyword Universe, review prompts in Prompt Lab, retire interests that produce nothing.', 'link' => ['marketing-keywords', '', null]],
    ];
}

/**
 * Step-by-step workflows. Each step: who, when, what, link.
 */
function mkt_manual_workflows(): array
{
    return [
        'research' => [
            'title'   => 'Research: from watched interests to accepted topics',
            'summary' => 'What we watch is harvested automatically, scored by AI against each interest, and grouped into candidate topics. A person accepts a topic by writing its angle — that brief is what campaigns and articles are written from.',
            'cadence' => 'Set up once, review monthly; topics weekly.',
            'steps'   => [
                ['who' => 'Marketing admin', 'when' => 'Once, then monthly', 'what' => 'Create or edit an **interest**: name, include terms, exclude terms, search queries, priority. Include terms flow into the Keyword Universe automatically.', 'link' => ['research-interests', '?tab=interests', 'Interests']],
                ['who' => 'Marketing admin', 'when' => 'Once, then monthly', 'what' => 'Add **sources** (RSS feeds, search feeds, sites) with an hourly, daily or weekly schedule. Check Suggested sources in the Content Harvester for domains the research agent keeps citing.', 'link' => ['research-interests', '?tab=sources', 'Sources']],
                ['who' => 'System', 'when' => 'Hourly at :15', 'what' => 'Due sources are fetched and de-duplicated into the item queue. Sources that keep failing auto-pause.', 'link' => ['research-harvester', '?tab=queue', 'Item queue']],
                ['who' => 'System', 'when' => 'Daily 06:00 (each interest weekly)', 'what' => 'The AI research agent searches for new evidence per interest and adds verified finds.', 'link' => ['research-harvester', '?tab=runs', 'Harvest runs']],
                ['who' => 'Coordinator', 'when' => 'Any time', 'what' => 'Add a single item by hand (a paper, article or post someone sent) with **Add to queue**.', 'link' => ['research-harvester', '?tab=add', 'Add item']],
                ['who' => 'System', 'when' => 'Hourly at :45; clustering 07:30', 'what' => 'Items are scored 0–1 for relevance to each interest (below the threshold they are discarded), then clustered into proposed topics once 8+ new items are scored.', 'link' => ['research-topics', '?tab=runs', 'Scoring runs']],
                ['who' => 'Marketing admin', 'when' => 'Weekly', 'what' => 'Topic Board: open each proposed topic, read its items, mark the evidence to cite, link approved claims, write the **angle / brief**, then **Save and accept**. Park, reject or merge the rest.', 'link' => ['research-topics', '?tab=board', 'Board']],
                ['who' => 'Marketing admin', 'when' => 'Monthly', 'what' => 'Emerging interests: themes no interest covers. Create an interest to start watching one, or park it.', 'link' => ['research-topics', '?tab=emerging', 'Emerging interests']],
            ],
        ],
        'campaign' => [
            'title'   => 'Campaign: generate, review, schedule, post, measure',
            'summary' => 'A campaign turns an accepted topic into a single post, a series or an email sequence for chosen channels. Every asset is claims-checked; flagged assets need compliance, then editorial approval, before they can be scheduled.',
            'cadence' => 'Weekly batch; each asset typically 2–5 days from generation to posting.',
            'steps'   => [
                ['who' => 'Coordinator or admin', 'when' => 'Weekly', 'what' => 'Campaign Studio → New campaign: pick the accepted topic, name, format (post, series, email), audience, social channels, parts and days between parts (series / email), call-to-action text and URL, and any extra brief, then **Create and generate**. The AI writes each asset (under a minute).', 'link' => ['marketing-campaigns', '?tab=new', 'New campaign']],
                ['who' => 'Writer', 'when' => 'Same day', 'what' => 'Open each asset. Edit directly or use **Revise with AI** with an instruction. Every save re-runs the claims check.', 'link' => ['marketing-campaigns', '', null]],
                ['who' => 'Writer', 'when' => 'Same day', 'what' => 'Check the **claims score** (0–10). Below the minimum the asset cannot be submitted; fix the flagged sentences. Then **Submit for review**.', 'link' => ['marketing-campaigns', '', null]],
                ['who' => 'Compliance reviewer', 'when' => 'Within the task deadline', 'what' => 'Review queue: read the copy-ready preview against the compliance rules and approved claims. **Clear compliance** or **Request changes** with a note. Skipped automatically when the claims check finds nothing that needs compliance.', 'link' => ['marketing-campaigns', '?tab=review', 'Review queue']],
                ['who' => 'Marketing admin (editorial)', 'when' => 'Within the task deadline', 'what' => 'Review queue: editorial approval for tone, accuracy and brand. Changes requested sends it back to the writer.', 'link' => ['marketing-campaigns', '?tab=review', 'Review queue']],
                ['who' => 'Coordinator', 'when' => 'After approval', 'what' => 'Publishing Calendar → Ready to schedule: set the date and time (Central), or **Lay out campaign** to space a series.', 'link' => ['marketing-calendar', '?tab=ready', 'Ready to schedule']],
                ['who' => 'Coordinator', 'when' => 'On or before the scheduled day', 'what' => 'Coordinator to-do: create the post in GoHighLevel Social Planner (or the email campaign) with the copy-ready text — it already contains the tracked link — and **Save ID**. Keep Social Planner RSS auto-post off.', 'link' => ['marketing-calendar', '?tab=todo', 'Coordinator to-do']],
                ['who' => 'Coordinator', 'when' => 'Once live', 'what' => '**Mark posted** with the public URL (email has none). Past-due items appear under “Past due — confirm posted”.', 'link' => ['marketing-calendar', '?tab=todo', 'Coordinator to-do']],
                ['who' => 'Coordinator', 'when' => 'Weekly', 'what' => 'Enter lifetime likes, comments, shares, reach (or sends, opens, clicks) for posted assets, or import a CSV. Site visits come from GA4 automatically.', 'link' => ['marketing-performance', 'metrics.php', 'Enter metrics']],
                ['who' => 'System', 'when' => 'Daily 05:40', 'what' => 'Engagement scores (0–100) are computed per asset and rolled up to campaigns, topics and interests.', 'link' => ['marketing-performance', '?tab=scores', 'Scores']],
            ],
        ],
        'content' => [
            'title'   => 'Web article: brief to published page (Content Pipeline)',
            'summary' => 'Long-form pages and blog posts for nutraaxislabs.com. Each piece moves Idea → Brief → Draft → Compliance review → Editorial review → Approved → Published → Monitoring. Every edit is kept as a version. Choose the Blog post type for posts meant for nutraaxislabs.com/our-blog; any approved piece can be published there.',
            'cadence' => 'Typically 1–2 weeks per piece.',
            'steps'   => [
                ['who' => 'Marketing admin', 'when' => 'When planning', 'what' => 'Content Pipeline → New piece: topic, target keyword, product, then **Create and write the brief** (AI, under a minute) or Create only.', 'link' => ['marketing-content', '?tab=new', 'New piece']],
                ['who' => 'Marketing admin', 'when' => 'Within 2 days', 'what' => 'Edit the brief, **Save brief**, then **Approve brief**. Approval locks it and moves the piece to drafting.', 'link' => ['marketing-content', '?tab=board', 'Board']],
                ['who' => 'Writer', 'when' => 'After brief approval', 'what' => '**Write the draft with AI** or write it yourself. Revise with AI or edit; each save is a new version and re-runs the claims check.', 'link' => ['marketing-content', '?tab=board', 'Board']],
                ['who' => 'Writer', 'when' => 'When ready', 'what' => 'Check the meta title (≤ 60 characters), meta description (≤ 155) and claims score, then **Submit version**.', 'link' => ['marketing-content', '?tab=board', 'Board']],
                ['who' => 'Compliance reviewer', 'when' => 'Within 2 days', 'what' => 'Review queue: **Clear compliance** or **Request changes** (a note is required).', 'link' => ['marketing-content', '?tab=review', 'Review queue']],
                ['who' => 'Marketing admin (editorial)', 'when' => 'Within 2 days', 'what' => 'Review queue: editorial approval. The piece becomes Approved.', 'link' => ['marketing-content', '?tab=review', 'Review queue']],
                ['who' => 'Marketing admin', 'when' => 'Within 3 days (blog posts)', 'what' => 'Check the **Blog preview** on the piece, then under Publish set the post address, history summary and byline and press **Publish to blog**. It is live on /our-blog within about a minute; the live URL is recorded for you.', 'link' => ['marketing-content', '?tab=list', 'All content']],
                ['who' => 'Site author', 'when' => 'Within 3 days (other pages)', 'what' => 'Put the approved version live on the website (the portal shows “Not approved — do not publish” on anything else). Then **Mark published** with the live URL. The page joins the Page Inventory automatically.', 'link' => ['marketing-content', '?tab=list', 'All content']],
                ['who' => 'Marketing admin', 'when' => 'Once indexed (1–4 weeks)', 'what' => '**Move to monitoring**. Watch its Search Console queries and GA4 sessions on the page’s Page Inventory detail.', 'link' => ['marketing-pages', '', null]],
            ],
        ],
        'responses' => [
            'title'   => 'Responses and compliance escalations (Response Inbox)',
            'summary' => 'Comments, replies, DMs, email replies and reviews on our posts are logged here, triaged by AI and keyword rules, and answered by a person on the platform. Claims risk and possible adverse events go to compliance first.',
            'cadence' => 'Daily; compliance within the escalation window.',
            'steps'   => [
                ['who' => 'Coordinator', 'when' => 'Daily', 'what' => 'Response Inbox → Add a response: paste the text (email addresses, phone numbers and @handles are redacted; don’t paste names or order details), choose channel and kind, **Save and triage**.', 'link' => ['marketing-performance', 'inbox.php?tab=add', 'Add a response']],
                ['who' => 'System', 'when' => 'On save, and every 6 hours', 'what' => 'AI labels it (question, complaint, praise, claims risk, possible adverse event, spam, other). Adverse-event words force the adverse-event label.', 'link' => ['marketing-performance', 'inbox.php?tab=action', 'Needs action']],
                ['who' => 'Coordinator', 'when' => 'Within 1 day', 'what' => 'Needs action: follow the “what to do” guidance on the label, reply on the platform using approved wording, then **I replied on the platform** (or **Close without reply**).', 'link' => ['marketing-performance', 'inbox.php?tab=action', 'Needs action']],
                ['who' => 'Compliance reviewer', 'when' => 'Within 24 hours', 'what' => 'With compliance: **Record decision** — what may be said, and for possible adverse events whether it is a serious adverse event that must be reported (FDA reports are due within 15 business days of receipt).', 'link' => ['marketing-performance', 'inbox.php?tab=escalated', 'With compliance']],
                ['who' => 'Coordinator', 'when' => 'After the decision', 'what' => 'Reply using only the wording compliance recorded (or point the person to their practitioner), then record the reply.', 'link' => ['marketing-performance', 'inbox.php?tab=action', 'Needs action']],
            ],
        ],
        'seo' => [
            'title'   => 'SEO: site health, issues and fixes',
            'summary' => 'The crawler checks every page weekly; findings become one issue per problem with the pages it is on. A fix spec tells the developer exactly what to change; marking an issue fixed rechecks those pages.',
            'cadence' => 'Weekly triage after the Monday crawl; fixes as developers deliver.',
            'steps'   => [
                ['who' => 'System', 'when' => 'Monday 05:00', 'what' => 'Full crawl of every active page; new, recurring and resolved issues are recorded. Search Console (04:30) and GA4 (04:45) load nightly.', 'link' => ['marketing-pages', '?tab=issues', 'Issues']],
                ['who' => 'Marketing admin', 'when' => 'Monday/Tuesday', 'what' => 'Audit & Issues → Open: sort by severity. Open each new issue, check its pages, **Acknowledge** or **Ignore** (with a reason) if intended — for example account and checkout pages kept out of search.', 'link' => ['marketing-issues', '?tab=open', 'Open']],
                ['who' => 'Marketing admin', 'when' => 'Same day', 'what' => '**Generate fix spec** (AI) and assign an owner. Send the spec, or the CSV / Markdown export, to the developer or site author.', 'link' => ['marketing-issues', '', null]],
                ['who' => 'Developer / site author', 'when' => 'Per their queue', 'what' => 'Make the change in the website (template, block, or page content as the spec says) and publish it.', 'link' => null],
                ['who' => 'Marketing admin', 'when' => 'After the fix is live', 'what' => '**Mark fixed and recheck**: the listed pages are recrawled. Verified when every page passes; anything still failing stays open. An issue that comes back later reopens.', 'link' => ['marketing-issues', '?tab=fixed', 'Fixed']],
                ['who' => 'Marketing admin', 'when' => 'Monthly (optional)', 'what' => 'Import an OpenRush audit for a second opinion (see “Other instructions”).', 'link' => ['marketing-issues', '', null]],
                ['who' => 'Marketing admin', 'when' => 'Monthly', 'what' => 'Page Inventory → Keyword map: every priority keyword should have exactly one primary page; map “Not mapped” search queries to a page.', 'link' => ['marketing-pages', '?tab=keywords', 'Keyword map']],
            ],
        ],
        'performance' => [
            'title'   => 'Weekly performance review',
            'summary' => 'Twenty minutes each Monday to see what worked and feed it back into topics and interests.',
            'cadence' => 'Weekly, Monday.',
            'steps'   => [
                ['who' => 'Marketing admin', 'when' => 'Monday', 'what' => 'Overview (last 7 or 28 days): Google clicks, impressions, CTR and position; GA4 sessions by channel — each against the prior period.', 'link' => ['marketing-performance', '?tab=overview', 'Overview']],
                ['who' => 'Marketing admin', 'when' => 'Monday', 'what' => 'Scores: which assets, campaigns, topics and interests earned the most engagement (ignore “Early read” rows).', 'link' => ['marketing-performance', '?tab=scores', 'Scores']],
                ['who' => 'Marketing admin', 'when' => 'Monday', 'what' => 'Weekly digest: read the recommendations; the tasks it opens go to Tasks.', 'link' => ['marketing-performance', '?tab=digests', 'Weekly digests']],
                ['who' => 'Marketing admin', 'when' => 'Monthly', 'what' => 'Interests: apply suggested priorities where scores support them.', 'link' => ['research-interests', '?tab=interests', 'Interests']],
            ],
        ],
        'admin' => [
            'title'   => 'Admin: jobs, budget, settings and prompts',
            'summary' => 'For marketing admins only (full Marketing access).',
            'cadence' => 'Weekly glance; monthly budget check.',
            'steps'   => [
                ['who' => 'Marketing admin', 'when' => 'Weekly', 'what' => 'Admin & Jobs → Jobs: every scheduled job should show Success. Use **Run now** to rerun one after fixing its cause.', 'link' => ['marketing-admin', '?tab=jobs', 'Jobs']],
                ['who' => 'Marketing admin', 'when' => 'Weekly / monthly', 'what' => 'AI & API Usage: spend vs budget, month-end forecast and cost by job. An ai_budget alert fires at 80% or when the forecast passes the budget.', 'link' => ['marketing-admin', '?tab=usage', 'AI & API Usage']],
                ['who' => 'Marketing admin', 'when' => 'As needed', 'what' => 'Settings: brand terms, competitors, channels, compliance rules, alert rules and recipients, budget. Changes apply to the next run.', 'link' => ['marketing-admin', '?tab=settings', 'Settings']],
                ['who' => 'Marketing admin', 'when' => 'Quarterly', 'what' => 'Prompt Lab: edit a prompt to create a new version; **Activate** the one to use. Older versions stay for rollback.', 'link' => ['research-prompt-lab', '', null]],
            ],
        ],
    ];
}

/**
 * Page guide: purpose, screens, how to analyze, actions, who. Keyed by hub slug.
 */
function mkt_manual_pages(): array
{
    return [
        'research-interests' => [
            'who'     => 'Marketing admin (edit); everyone (view)',
            'purpose' => 'What we watch (interests and their terms) and where we look (feeds, searches, sites). Stage 1 of the content engine.',
            'screens' => ['**Interests** — each interest with priority, include terms, items (7 days / total), AI agent status, performance and suggested priority from engagement scores.', '**Sources** — feeds, search feeds and sites with type, schedule, last success, items and health.', '**Taxonomy** — shared lists (products, audiences, areas) used by the interest form and generation prompts.'],
            'analyze' => ['A source with no items for weeks, or an error streak, is broken or stale — fix the URL or pause it. Auto-paused means it failed repeatedly.', 'An interest with high volume but low engagement scores is producing noise: tighten include terms or add exclude terms.', '“Suggested priority” comes from engagement; apply it only when the interest has several medium/high-confidence scores.'],
            'actions' => ['Add / edit interests and sources', 'Apply suggested priority', 'Save taxonomy'],
        ],
        'research-harvester' => [
            'who'     => 'Coordinator, marketing admin',
            'purpose' => 'Everything harvested: the item queue, each harvest run, manual additions and suggested new sources.',
            'screens' => ['**Item queue** — items with status (new, scored, clustered, promoted, discarded, duplicate…).', '**Harvest runs** — each run with items found, new, duplicates and errors.', '**Add item** — add one URL or text by hand.', '**Suggested sources** — domains the research agent cited at least twice that are not yet sources.'],
            'analyze' => ['Many duplicates from one source means it overlaps another; keep the better one.', 'A high discard rate for an interest means its sources or terms are off-target.'],
            'actions' => ['Add to queue', 'Add a suggested domain as a source on Interests & Sources'],
        ],
        'research-topics' => [
            'who'     => 'Marketing admin',
            'purpose' => 'AI scores harvested items against each interest and groups them into candidate topics. Accepting a topic with a written angle is the hand-off to campaigns and articles.',
            'screens' => ['**Board** — proposed topics sorted by trend score.', '**Accepted** — topics ready for Campaign Studio and the Content Pipeline.', '**Emerging interests** — themes no interest covers.', '**Parked & rejected**', '**Scored items** — every item with relevance per interest.', '**Scoring runs** — batch scoring and clustering history with cost.'],
            'analyze' => ['**Trend score** = recent volume ×2 + week-over-week growth + distinct sources ×1.5 + peer-reviewed or regulatory items. High trend with peer-reviewed evidence is the best candidate.', 'Prefer topics with several independent sources and evidence you can cite; one news item is not a topic.', 'Relevance is 0–1; items below the threshold (Settings: research.relevance_threshold) are discarded.'],
            'actions' => ['Save brief / Save and accept', 'Park, Reject, Reopen, Merge', 'Mark evidence items; Link / Unlink approved claims', 'Score waiting items; Cluster topics now'],
        ],
        'research-literature' => [
            'who'     => 'Marketing admin, coordinator; compliance reviewer for regulatory items',
            'purpose' => 'The evidence library behind our claims: studies chosen from the research feed or added by ID, the product-flyer reference lists matched to their papers, how well each approved claim is backed, and a watch list of regulatory news.',
            'screens' => ['**Library** — sources with evidence level, study facts, products, and how many claims and topics use each. **Export citations** downloads them as CSV.', '**To review** — peer-reviewed items the feed scored as relevant, strongest evidence first: **Add to library** or **Not needed**.', '**Flyer references** — each product flyer’s numbered references, with PubMed candidates to confirm.', '**Claims coverage** — every approved claim, least-backed first.', '**Regulatory watch** — FDA, FTC and other regulator news with its review status.', '**Add a source** — add a paper or trial by PubMed ID, DOI or NCT number.'],
            'analyze' => ['**Evidence level** runs meta-analysis → randomised trial → other human study → registered trial → animal or lab study → narrative review → other. Clinical claims need a human study; mechanistic claims also accept lab studies and reviews.', '**Coverage**: *No references* means the claim cites nothing and has no linked study; *No study cited* means it cites only non-article references; *Some not matched yet* means some cited studies are not yet matched to a library paper; *All studies matched* means all are. Label claims need no study. “No matched human study yet” under a clinical claim means its only matched evidence is lab work or reviews.', 'Coverage is for information only — it never blocks a claim, piece or campaign.'],
            'actions' => ['Add to library / Not needed', 'Look up and add', 'Find matches; This one / None of these / Not a journal article / Match by PMID or DOI', 'Save study facts and notes; Retire / Restore; Link / Unlink a claim', 'Reviewed — no action / Needs action (opens a Compliance task) / Action done'],
        ],
        'marketing-campaigns' => [
            'who'     => 'Writer, coordinator, compliance reviewer, marketing admin',
            'purpose' => 'Generate single posts, series and emails from accepted topics, with claims check and the compliance → editorial approval gates.',
            'screens' => ['**Campaigns** — each campaign with its assets and their status.', '**Review queue** — assets waiting for your review (compliance or editorial, depending on your role).', '**New campaign**', '**Archived**'],
            'analyze' => ['**Claims score** 0–10: at or above the minimum (Settings: claims.min_score) the asset can be submitted. Read the flagged sentences — the score is a guide, the rules are what matter.', 'Asset status runs Draft → In review → Approved → Scheduled → Posted; Changes requested returns it to the writer.', 'Compliance “Not required” means the claims check found no health, product, ingredient or condition statement.'],
            'actions' => ['Create and generate / Create only', 'Revise with AI; Save and re-check; Run claims check', 'Submit for review; Clear compliance; Approve (editorial); Request changes (note required)', 'Archive / Restore'],
        ],
        'marketing-calendar' => [
            'who'     => 'Coordinator',
            'purpose' => 'Approved social posts and emails on one schedule (Central time), and the checklist for loading them into GoHighLevel.',
            'screens' => ['**Month** and **Upcoming** — what goes out when.', '**Ready to schedule** — approved assets without a date.', '**Coordinator to-do** — load into GoHighLevel, and past-due items to confirm.', '**Posted** — history with GoHighLevel IDs and URLs.'],
            'analyze' => ['Gaps of several days on a channel mean the week’s batch is short — generate more from accepted topics.', 'Anything under “Past due — confirm posted” either went out (mark posted) or didn’t (reschedule).'],
            'actions' => ['Schedule; Lay out campaign', 'Save ID (GoHighLevel post or campaign ID)', 'Posted (with URL); Take off the calendar', 'CSV export'],
        ],
        'marketing-performance' => [
            'who'     => 'Marketing admin (analysis); coordinator (metrics, inbox)',
            'purpose' => 'How nutraaxislabs.com performs in Google and GA4, what each campaign asset drove, engagement scores, weekly digests — plus the Response Inbox and metrics entry.',
            'screens' => ['**Overview** — Google search (clicks, impressions, CTR, average position), GA4 site visits, sessions by channel, top queries and pages, vs the prior period.', '**Scores** — engagement scores for assets, campaigns, long-form content, topics and interests.', '**Campaign assets** — each posted asset with sessions, engagement, key events, purchases and responses.', '**Search queries** and **Landing pages**.', '**Weekly digests** — the Monday summary and its recommendations.', '**Response Inbox** and **Enter metrics** (buttons on this page).'],
            'analyze' => ['**Clicks** = visits from Google; **impressions** = times a page was shown; **CTR** = clicks ÷ impressions; **position** = average rank (lower is better; 1–10 is page one).', 'Rising impressions with flat clicks → titles and descriptions need work (CTR). Falling impressions → rankings or indexing problem; check Audit & Issues.', 'Search Console lags about 3 days and GA4 about 1 day; each window ends on that source’s latest day. “Prior” is the same-length window just before.', '**Engagement score** 0–100 = 100 × points ÷ (points + half-points). Points come from sessions, engaged sessions, key events, purchases, leads and post/email interactions (weights in Settings: engagement.weights). “Early read” = under 7 days old or no data — don’t act on it yet.', '“(not set)” landing page is GA4’s label for sessions without a recorded landing page. “Other tagged traffic” is UTM traffic that matched no asset.'],
            'actions' => ['Change period (7 / 28 / 90 days)', 'Refresh data now; Run scoring now', 'Generate last week’s digest', 'Enter metrics / Import CSV; Response Inbox actions'],
        ],
        'marketing-keywords' => [
            'who'     => 'Marketing admin',
            'purpose' => 'One keyword list for SEO targets and interest terms, with purpose, priority, cluster, intent, volume and difficulty.',
            'screens' => ['Keyword list with filters; CSV import.'],
            'analyze' => ['Priority keywords with no page in the Keyword map are content gaps — candidates for the Content Pipeline.', 'High volume with low difficulty is the best opportunity.'],
            'actions' => ['Import CSV (header: keyword, purpose, priority, cluster, intent, volume, difficulty, notes — existing keywords are updated)'],
        ],
        'research-prompt-lab' => [
            'who'     => 'Marketing admin',
            'purpose' => 'Versioned AI prompts for discovery, scoring, synthesis, generation, claims checks, triage and fix specs; provider and model per prompt.',
            'screens' => ['Each prompt key with its versions; the active one is used by every AI call.'],
            'analyze' => ['Compare a prompt’s cost and failures on Admin & Jobs → AI & API Usage (by prompt and model) before and after a change.'],
            'actions' => ['Save new version; Activate (roll back by activating an older version)'],
        ],
        'marketing-tasks' => [
            'who'     => 'Everyone',
            'purpose' => 'Work assigned from campaigns, content, responses, issues and the digest. Automatic tasks open when something needs someone and close themselves when it is done.',
            'screens' => ['**My tasks**', '**All open**', '**Closed**', '**New task** (manual)'],
            'analyze' => ['Overdue tasks are the bottleneck: a pile-up at compliance or editorial means reviewers need time or backup.'],
            'actions' => ['Done; Cancel; Reopen; Create task'],
        ],
        'marketing-content' => [
            'who'     => 'Writer, compliance reviewer, marketing admin, site author',
            'purpose' => 'Long-form web articles from brief to live page, with every version kept.',
            'screens' => ['**Board** — pieces by stage.', '**All content**', '**Review queue**', '**New piece**', '**Archived**'],
            'analyze' => ['A piece sitting in one stage past its task deadline is blocked — check who owns the task.', 'After publishing, judge it on the page’s Search Console queries and GA4 sessions (Page Inventory detail), usually after 4–8 weeks.'],
            'actions' => ['Write the brief with AI; Save brief; Approve brief; Reopen the brief', 'Write the draft with AI; Revise with AI; Save as new version and re-check', 'Submit version; Clear compliance; Approve (editorial); Request changes', 'Publish to blog; Update blog post; Unpublish from blog', 'Mark published; Update URL; Move to monitoring; Archive'],
        ],
        'marketing-pages' => [
            'who'     => 'Marketing admin',
            'purpose' => 'Every public page on nutraaxislabs.com with crawl health, SEO issues, target keywords, and Search Console and GA4 numbers.',
            'screens' => ['**Pages** — type, status, issues, search clicks and sessions.', '**Issues** — checks from the last crawl, per page.', '**Changes** — what changed between crawls (status, title, description, H1, canonical, robots, body text).', '**Keyword map** — which page targets which keyword.', 'Page detail — what to fix, Google queries for the page (90 days), crawl history, keywords.'],
            'analyze' => ['An unexpected Change (title or robots changed, page went 404) right before a traffic drop is usually the cause.', 'A keyword that is primary on two pages makes them compete — keep one primary.', 'Product details load in the browser, so word counts on product pages only reflect the page shell.'],
            'actions' => ['Crawl site now; Recrawl now', 'Add / Add and crawl (pages missing from the sitemap)', 'Exclude / Include in inventory', 'Map keyword (primary / secondary)'],
        ],
        'marketing-issues' => [
            'who'     => 'Marketing admin; developer (fix specs)',
            'purpose' => 'One issue per problem found by the crawler or an OpenRush import, with the pages it is on, AI fix specs, fixed → recheck verification, and alerts.',
            'screens' => ['**Open** (new + open), **Fixed — rechecking**, **Resolved** (verified), **Ignored**.', '**Audits** — each crawl, recheck and import.', '**Alerts** — what the daily check found.', 'Issue detail — pages, fix spec, actions.'],
            'analyze' => ['Work **high** severity first (missing title, noindex, broken pages, legacy brand name), then medium; low is polish.', 'Owner says who fixes it: Developer (templates, head, sitemap) or Content (per-page copy).', 'One issue on many pages is usually one template fix — say so to the developer.'],
            'actions' => ['Acknowledge; Assign; Ignore / Stop ignoring', 'Generate / Regenerate fix spec', 'Mark fixed and recheck; Recheck pages now', 'Export CSV / Markdown packet', 'Crawl site now; Import audit; Check now (alerts)'],
        ],
        'marketing-admin' => [
            'who'     => 'Marketing admin only',
            'purpose' => 'Scheduled job runs, AI and API spend against budget, and the settings that drive harvesting, generation and alerts.',
            'screens' => ['**Jobs** — every job, its schedule, last run and result; recent runs.', '**AI & API Usage** — budget used and forecast, daily and monthly spend, cost by job and prompt, recent calls.', '**Settings** — every Marketing & Research setting with its description.'],
            'analyze' => ['Month-end forecast = month-to-date + the last 7 days’ daily rate × days left. If it passes the budget, AI work stops when the budget is reached.', 'Cost by job shows what to trim: batch scoring and the research agent are usually the largest.', 'A job whose last run failed shows why in the Result column; the Process Log has detail.'],
            'actions' => ['Run now', 'Save settings'],
        ],
    ];
}

/** Longer explanations for statuses users see most. */
function mkt_manual_statuses(): array
{
    return [
        'Campaign asset' => MKT_ASSET_STATUSES,
        'Review gate (compliance / editorial)' => MKT_GATE_STATUSES,
        'Article stage' => MKT_CONTENT_STAGES,
        'Topic' => MKT_TOPIC_STATUSES,
        'Harvested item' => MKT_ITEM_STATUSES,
        'Response' => MKT_RESPONSE_STATUSES,
        'Site issue' => MKT_ISSUE_STATUSES,
        'Alert' => MKT_ALERT_STATUSES,
        'Task' => MKT_TASK_STATUSES,
        'Source' => MKT_SOURCE_STATUSES,
    ];
}

function mkt_manual_alert_guide(): array
{
    return [
        'job_failed'         => ['A scheduled job’s latest run in the last 7 days failed.', 'Open the linked Process Log entry, fix the cause (see Troubleshooting), then Run now on Admin & Jobs.'],
        'traffic_drop'       => ['GA4 sessions or Google clicks for the last 7 days fell well below the 4 weeks before.', 'Rule out tracking first (site down, GA4 tag, consent banner), then check Page Inventory → Changes and Audit & Issues for what changed.'],
        'legacy_brand'       => ['A live page still shows an old brand name (for example NutraSync).', 'Fix the copy on the website, then Mark fixed on the issue.'],
        'site_error'         => ['A high-severity site issue is open (missing title, broken page, noindex).', 'Generate a fix spec, send it to the developer, Mark fixed once live. Ignore it if the page is meant to be that way.'],
        'escalation_overdue' => ['A claims-risk or possible adverse-event response is past its compliance deadline.', 'Compliance records a decision in the Response Inbox today.'],
        'ai_budget'          => ['AI spend reached the warning level, the forecast passes the budget, or the budget is reached.', 'Check cost by job on Admin & Jobs; raise the budget or accept that AI work pauses until the 1st.'],
    ];
}

/** Task deadlines from tasks.sla_days, e.g. "brief|2". */
function mkt_manual_task_deadlines(): array
{
    $labels = [
        'brief'      => 'Approve a brief',
        'compliance' => 'Compliance review',
        'editorial'  => 'Editorial review',
        'publish'    => 'Publish approved content and record the URL',
        'schedule'   => 'Schedule an approved asset',
        'reply'      => 'Reply to a response',
    ];
    $out = [];
    foreach (marketing_setting_lines('tasks.sla_days') as $line) {
        [$key, $days] = array_pad(array_map('trim', explode('|', $line)), 2, '');
        if ($key !== '' && is_numeric($days)) {
            $out[] = ['task' => $labels[$key] ?? $key, 'days' => (int) $days];
        }
    }

    return $out;
}

function mkt_manual_other_instructions(): array
{
    return [
        'Tracked links' => 'Copy-ready text already contains the call-to-action link with UTM tags for that asset. Don’t shorten or edit the link — visits are matched to the asset through those tags.',
        'Entering metrics' => 'Enter lifetime totals as of a date (not the change since last time). The latest entry per asset counts. CSV imports need a header row; unknown columns are ignored.',
        'Importing an OpenRush audit' => 'In Cursor or Claude with OpenRush connected, ask it to run audit_site for nutraaxislabs.com. Copy the whole JSON reply, paste it into Audit & Issues → Import an OpenRush audit, and press Import audit. Only nutraaxislabs.com is accepted, and only pages already in the Page Inventory count.',
        'Asking for access' => 'Access comes from your role (Site Admin → Roles: the Marketing column, and Marketing Compliance Review for reviewers). Ask a portal admin to change your role.',
        'Alert emails' => 'Sent to the addresses in Settings → alerts.recipients (blank = everyone with full Marketing access). Acknowledging an alert marks it seen; it resolves on its own once the cause is gone.',
        'Editing after approval' => 'Saving a change to an approved or published piece sends it back to draft and clears its reviews. For a live article, the URL is kept so the update can be re-published over it. A live blog post keeps showing the approved version until the edit is approved and someone presses **Update blog post**.',
        'The blog' => 'nutraaxislabs.com/our-blog shows the selected post (the newest by default) with the posting history newest to oldest; each post has its own link (?post=address). Publish, update and unpublish take effect within about a minute. Unpublishing returns the piece to Approved and is kept in its review history. Publishing needs full Marketing access; unpublishing needs update access. Page setup is in docs/seo-ops/BLOG.md.',
        'Budget stop' => 'When month-to-date AI spend reaches the budget, every AI action (generate, revise, claims check, fix spec, scoring) fails with “Monthly AI budget reached” until the 1st of the next month (UTC) or until an admin raises the budget.',
        'Placeholders' => 'Cards marked Placeholder are planned but not built. Don’t record work there.',
    ];
}

function mkt_manual_troubleshooting(): array
{
    return [
        ['A button is missing', 'Your role doesn’t allow that action (see Your access at the top). Ask a marketing admin.'],
        ['“You edited or submitted this asset — another reviewer must review it”', 'Working as intended: someone else must review.'],
        ['“Claims score is below the minimum”', 'Revise the flagged sentences (or Revise with AI with “remove the claims”) and save to re-check.'],
        ['“Monthly AI budget reached”', 'A marketing admin raises ai.monthly_budget_usd on Admin & Jobs → Settings, or wait for the 1st.'],
        ['AI action failed or timed out', 'Try again in a few minutes; if it keeps failing, a marketing admin checks Admin & Jobs → Recent runs and the Process Log.'],
        ['Search or GA4 numbers look stale', 'Search Console lags about 3 days and GA4 about 1. If older than that, an admin checks the nightly ingest jobs on Admin & Jobs.'],
        ['A post went out but still shows Scheduled', 'Mark posted on the Publishing Calendar (Coordinator to-do → Past due).'],
        ['A blog post isn’t showing on /our-blog', 'Wait a minute (the page caches for 60 seconds), then check the piece shows Blog: Live. If no posts show at all, the page’s html-loader block needs the blog script — see docs/seo-ops/BLOG.md.'],
        ['A page is missing from Page Inventory', 'Page Inventory → Add a page (it is crawled right away). Published Content Pipeline pieces are added automatically.'],
        ['An issue keeps reopening', 'The fix didn’t reach every page, or content is only added by JavaScript (the crawler reads raw HTML). Read the fix spec’s “How it will be verified”.'],
        ['Anything else', 'Contact the Marketing & Research admin (Joe Butler). Technical operations are documented in docs/seo-ops/RUNBOOK.md.'],
    ];
}
