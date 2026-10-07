<?php

declare(strict_types=1);

/**
 * User Manual process map: one swimlane diagram per phase, drawn as inline SVG.
 * Steps name a lane, a column and a page link; edges connect step ids (dashed = only when needed).
 */

const MKT_FLOW_LABEL_W = 112;
const MKT_FLOW_PITCH = 134;
const MKT_FLOW_BOX_W = 118;
const MKT_FLOW_BOX_H = 100;
const MKT_FLOW_LANE_H = 120;

/** Screen names shown under each step, keyed by page slug then link suffix ('' = the page's default tab). */
const MKT_FLOW_SCREENS = [
    'research-interests'    => ['?tab=interests' => 'Interests', '?tab=sources' => 'Sources'],
    'research-harvester'    => ['?tab=queue' => 'Item queue', '?tab=add' => 'Add item', '?tab=runs' => 'Harvest runs'],
    'research-topics'       => ['?tab=board' => 'Board', '?tab=runs' => 'Scoring runs'],
    'marketing-content'     => ['?tab=new' => 'New piece', '?tab=board' => 'Board', '?tab=review' => 'Review queue', '?tab=list' => 'All content'],
    'marketing-campaigns'   => ['' => 'Campaigns', '?tab=new' => 'New campaign', '?tab=review' => 'Review queue'],
    'marketing-calendar'    => ['?tab=ready' => 'Ready to schedule', '?tab=todo' => 'Coordinator to-do'],
    'marketing-performance' => [
        '?tab=scores' => 'Scores', '?tab=digests' => 'Weekly digests', '?tab=overview' => 'Overview',
        'metrics.php' => 'Enter metrics', 'inbox.php?tab=add' => 'Add a response', 'inbox.php?tab=action' => 'Needs action', 'inbox.php?tab=escalated' => 'With compliance',
    ],
    'marketing-pages'       => ['' => 'Pages'],
    'marketing-tasks'       => ['?tab=mine' => 'My tasks'],
];

/** Sub-pages with their own title, keyed by the start of the link suffix. */
const MKT_FLOW_SUBPAGES = ['inbox.php' => 'Response Inbox', 'metrics.php' => 'Asset Metrics'];

/** [page title, screen name] for a step link, e.g. ['Interests & Sources', 'Interests']. */
function mkt_manual_flow_screen(array $link): array
{
    [$slug, $suffix] = [$link[0], (string) ($link[1] ?? '')];
    $title = mkt_manual_page_index()[$slug]['title'] ?? $slug;
    foreach (MKT_FLOW_SUBPAGES as $prefix => $subTitle) {
        if (str_starts_with($suffix, $prefix)) {
            $title = $subTitle;
        }
    }

    return [$title, MKT_FLOW_SCREENS[$slug][$suffix] ?? ''];
}

function mkt_manual_flow_lanes(): array
{
    return [
        'admin'      => ['label' => ['Marketing', 'admin'], 'band' => '#e8f5f4', 'stroke' => '#3d8b85', 'ink' => '#2a6b65'],
        'coord'      => ['label' => ['Coordinator', '/ writer'], 'band' => '#eef3f9', 'stroke' => '#3b6ea5', 'ink' => '#2c5586'],
        'compliance' => ['label' => ['Compliance', 'reviewer'], 'band' => '#fbf1eb', 'stroke' => '#b35632', 'ink' => '#8f4225'],
        'system'     => ['label' => ['System', '(automatic)'], 'band' => '#f2f4f4', 'stroke' => '#8a9d9c', 'ink' => '#5a7170'],
    ];
}

function mkt_manual_flow_panels(): array
{
    return [
        'research' => [
            'letter'  => 'A',
            'title'   => 'Research: from interests to an accepted topic',
            'facts'   => [
                'Starts with'  => 'Setting up interests and sources. Do this first — nothing else has anything to work from until a topic is accepted.',
                'Hands off to' => 'Accepted topics (A6), which start articles (**B**) and campaigns (**C**). Themes no interest covers appear as Emerging interests and can become a new interest (back to A1).',
                'How often'    => 'Harvesting and scoring run automatically every hour; the admin accepts 2–4 topics each week.',
                'Keep in mind' => 'A topic is only usable once its angle is written and it is accepted. A campaign cannot be created without one.',
            ],
            'steps'   => [
                ['id' => 'A1', 'lane' => 'admin', 'col' => 1, 'label' => ['Define', 'interests'], 'when' => 'Once, then monthly', 'link' => ['research-interests', '?tab=interests']],
                ['id' => 'A2', 'lane' => 'admin', 'col' => 2, 'label' => ['Add information', 'sources'], 'when' => 'Once, then monthly', 'link' => ['research-interests', '?tab=sources']],
                ['id' => 'A3', 'lane' => 'system', 'col' => 3, 'label' => ['Crawl sources +', 'AI research', 'agent'], 'when' => 'Hourly · daily', 'link' => ['research-harvester', '?tab=queue']],
                ['id' => 'A4', 'lane' => 'coord', 'col' => 3, 'label' => ['Add an item', 'by hand'], 'when' => 'Any time', 'link' => ['research-harvester', '?tab=add'], 'optional' => true],
                ['id' => 'A5', 'lane' => 'system', 'col' => 4, 'label' => ['Score relevance,', 'cluster into', 'topics'], 'when' => 'Hourly · daily 07:30', 'link' => ['research-topics', '?tab=runs']],
                ['id' => 'A6', 'lane' => 'admin', 'col' => 5, 'label' => ['Synthesize topic:', 'evidence, claims,', 'angle → Accept'], 'when' => 'Weekly', 'link' => ['research-topics', '?tab=board']],
            ],
            'edges'   => [['A1', 'A2'], ['A2', 'A3'], ['A3', 'A5'], ['A4', 'A5', true], ['A5', 'A6']],
        ],
        'article' => [
            'letter'  => 'B',
            'title'   => 'Article or blog post: brief to published page',
            'facts'   => [
                'Starts with'  => 'An accepted topic from **A** (linking one is optional but recommended), a priority keyword from the Keyword map, or a task from the weekly review (**E**).',
                'Hands off to' => 'A live page or blog post. Its address can be the call-to-action link in a campaign (**C**), and its Search Console and GA4 traffic feeds the weekly review (**E**).',
                'How often'    => 'Typically 1–2 weeks per piece, from new piece to published.',
                'Keep in mind' => 'Compliance review happens only when the claims check flags claims; otherwise the piece goes straight to editorial. Changes requested at either review send it back to the writer (B6). Nothing goes on the website before editorial approval.',
            ],
            'steps'   => [
                ['id' => 'B1', 'lane' => 'admin', 'col' => 1, 'label' => ['New piece: type,', 'topic, keyword,', 'product'], 'when' => 'When planning', 'link' => ['marketing-content', '?tab=new']],
                ['id' => 'B2', 'lane' => 'system', 'col' => 1, 'label' => ['AI writes', 'the brief'], 'when' => 'Under a minute', 'link' => ['marketing-content', '?tab=board']],
                ['id' => 'B3', 'lane' => 'admin', 'col' => 2, 'label' => ['Review, edit and', 'approve brief'], 'when' => 'Within 2 days', 'link' => ['marketing-content', '?tab=board']],
                ['id' => 'B4', 'lane' => 'coord', 'col' => 3, 'label' => ['Write the draft', '(AI or by hand)'], 'when' => 'After brief approval', 'link' => ['marketing-content', '?tab=board']],
                ['id' => 'B5', 'lane' => 'system', 'col' => 3, 'label' => ['Claims check', 'on every save'], 'when' => 'Automatic', 'link' => ['marketing-content', '?tab=board']],
                ['id' => 'B6', 'lane' => 'coord', 'col' => 4, 'label' => ['Revise, then', 'submit', '(score ≥ 7)'], 'when' => 'When ready', 'link' => ['marketing-content', '?tab=board']],
                ['id' => 'B7', 'lane' => 'compliance', 'col' => 5, 'label' => ['Compliance', 'review', '(if flagged)'], 'when' => 'Within 2 days', 'link' => ['marketing-content', '?tab=review'], 'optional' => true],
                ['id' => 'B8', 'lane' => 'admin', 'col' => 6, 'label' => ['Editorial', 'approval'], 'when' => 'Within 2 days', 'link' => ['marketing-content', '?tab=review']],
                ['id' => 'B9', 'lane' => 'admin', 'col' => 7, 'label' => ['Publish to blog', 'or Mark', 'published'], 'when' => 'Within 3 days', 'link' => ['marketing-content', '?tab=list']],
                ['id' => 'B10', 'lane' => 'admin', 'col' => 8, 'label' => ['Monitor search', 'and traffic'], 'when' => 'After 4–8 weeks', 'link' => ['marketing-pages', '']],
            ],
            'edges'   => [['B1', 'B2'], ['B2', 'B3'], ['B3', 'B4'], ['B4', 'B5'], ['B5', 'B6'], ['B6', 'B7'], ['B7', 'B8'], ['B8', 'B9'], ['B9', 'B10']],
        ],
        'campaign' => [
            'letter'  => 'C',
            'title'   => 'Campaign: generate, review, schedule, post, measure',
            'facts'   => [
                'Starts with'  => 'An accepted topic from **A** (required). For a launch, publish the article (**B**) first so the posts can link to it.',
                'Hands off to' => 'Live posts and emails. Comments, replies and DMs on them go to Responses (**D**); post and email metrics and engagement scores go to the weekly review (**E**).',
                'How often'    => 'A weekly batch; each asset typically takes 2–5 days from generation to posting.',
                'Keep in mind' => 'Posts are never sent automatically: the coordinator loads each approved asset into GoHighLevel and marks it posted once it is live. Flagged assets need compliance before editorial approval.',
            ],
            'steps'   => [
                ['id' => 'C1', 'lane' => 'coord', 'col' => 1, 'label' => ['New campaign:', 'topic, format,', 'channels'], 'when' => 'Weekly', 'link' => ['marketing-campaigns', '?tab=new']],
                ['id' => 'C2', 'lane' => 'system', 'col' => 1, 'label' => ['AI writes assets', '+ claims check'], 'when' => 'Under a minute', 'link' => ['marketing-campaigns', '']],
                ['id' => 'C3', 'lane' => 'coord', 'col' => 2, 'label' => ['Edit or revise,', 'then submit'], 'when' => 'Same day', 'link' => ['marketing-campaigns', '']],
                ['id' => 'C4', 'lane' => 'compliance', 'col' => 3, 'label' => ['Compliance', 'review', '(if flagged)'], 'when' => 'Within task deadline', 'link' => ['marketing-campaigns', '?tab=review'], 'optional' => true],
                ['id' => 'C5', 'lane' => 'admin', 'col' => 4, 'label' => ['Editorial', 'approval'], 'when' => 'Within task deadline', 'link' => ['marketing-campaigns', '?tab=review']],
                ['id' => 'C6', 'lane' => 'coord', 'col' => 5, 'label' => ['Schedule on the', 'Publishing', 'Calendar'], 'when' => 'After approval', 'link' => ['marketing-calendar', '?tab=ready']],
                ['id' => 'C7', 'lane' => 'coord', 'col' => 6, 'label' => ['Load into', 'GoHighLevel,', 'Save ID'], 'when' => 'On the day', 'link' => ['marketing-calendar', '?tab=todo']],
                ['id' => 'C8', 'lane' => 'coord', 'col' => 7, 'label' => ['Mark posted', 'with the URL'], 'when' => 'Once live', 'link' => ['marketing-calendar', '?tab=todo']],
                ['id' => 'C9', 'lane' => 'coord', 'col' => 8, 'label' => ['Enter metrics', '(lifetime', 'totals)'], 'when' => 'Weekly', 'link' => ['marketing-performance', 'metrics.php']],
                ['id' => 'C10', 'lane' => 'system', 'col' => 8, 'label' => ['Engagement', 'scores'], 'when' => 'Daily 05:40', 'link' => ['marketing-performance', '?tab=scores']],
            ],
            'edges'   => [['C1', 'C2'], ['C2', 'C3'], ['C3', 'C4'], ['C4', 'C5'], ['C5', 'C6'], ['C6', 'C7'], ['C7', 'C8'], ['C8', 'C9'], ['C9', 'C10']],
        ],
        'responses' => [
            'letter'  => 'D',
            'title'   => 'Responses to our posts',
            'facts'   => [
                'Starts with'  => 'A comment, reply, DM, email reply or review on a live post or email from **C**.',
                'Hands off to' => 'A reply on the platform. Response counts (positive and risky) are added to the engagement scores used in the weekly review (**E**).',
                'How often'    => 'Daily; compliance decides within 24 hours.',
                'Keep in mind' => 'Claims risk and possible adverse events go to compliance before anyone replies.',
            ],
            'steps'   => [
                ['id' => 'D1', 'lane' => 'coord', 'col' => 1, 'label' => ['Log a response', 'on our posts'], 'when' => 'Daily', 'link' => ['marketing-performance', 'inbox.php?tab=add']],
                ['id' => 'D2', 'lane' => 'system', 'col' => 1, 'label' => ['AI triage', 'and label'], 'when' => 'On save · every 6 h', 'link' => ['marketing-performance', 'inbox.php?tab=action']],
                ['id' => 'D3', 'lane' => 'coord', 'col' => 2, 'label' => ['Reply on the', 'platform and', 'record it'], 'when' => 'Within 1 day', 'link' => ['marketing-performance', 'inbox.php?tab=action']],
                ['id' => 'D4', 'lane' => 'compliance', 'col' => 2, 'label' => ['Record decision', '(claims risk,', 'adverse event)'], 'when' => 'Within 24 hours', 'link' => ['marketing-performance', 'inbox.php?tab=escalated'], 'optional' => true],
                ['id' => 'D5', 'lane' => 'coord', 'col' => 3, 'label' => ['Reply with the', 'approved', 'wording'], 'when' => 'After the decision', 'link' => ['marketing-performance', 'inbox.php?tab=action'], 'optional' => true],
            ],
            'edges'   => [['D1', 'D2'], ['D2', 'D3'], ['D2', 'D4', true], ['D4', 'D5', true]],
        ],
        'review' => [
            'letter'  => 'E',
            'title'   => 'Weekly review: feed results back',
            'facts'   => [
                'Starts with'  => 'The automatic Monday digest, built from page traffic (**B**), post metrics (**C**) and responses (**D**).',
                'Hands off to' => 'Tasks for the week, and new interest priorities and sources — the start of the next research cycle (**A**).',
                'How often'    => 'Every Monday (about 20 minutes); priority changes monthly.',
                'Keep in mind' => 'Ignore “Early read” scores — they are too new to judge.',
            ],
            'steps'   => [
                ['id' => 'E1', 'lane' => 'system', 'col' => 1, 'label' => ['Monday digest', 'and', 'recommendations'], 'when' => 'Monday 07:00', 'link' => ['marketing-performance', '?tab=digests']],
                ['id' => 'E2', 'lane' => 'admin', 'col' => 2, 'label' => ['Review overview,', 'scores and', 'digest'], 'when' => 'Monday, 20 min', 'link' => ['marketing-performance', '?tab=overview']],
                ['id' => 'E3', 'lane' => 'admin', 'col' => 3, 'label' => ['Work the', 'digest tasks'], 'when' => 'This week', 'link' => ['marketing-tasks', '?tab=mine']],
                ['id' => 'E4', 'lane' => 'admin', 'col' => 4, 'label' => ['Adjust interest', 'priorities and', 'sources'], 'when' => 'Monthly', 'link' => ['research-interests', '?tab=interests']],
            ],
            'edges'   => [['E1', 'E2'], ['E2', 'E3'], ['E3', 'E4']],
        ],
    ];
}

/** The order the flows run in, shown under the overview diagram. */
function mkt_manual_flow_order(): array
{
    return [
        '**Set up Research (A) first.** Define interests and add sources. From then on the system harvests and scores around the clock, and proposed topics build up on the Topic Board.',
        '**Accept topics every week (A6).** Each accepted topic can start an article (**B**), a campaign (**C**), or both. B and C then run side by side.',
        '**Publish before you promote.** An article takes 1–2 weeks and a campaign asset 2–5 days. When a campaign should link to a new article or blog post, finish B first and use its live address as the campaign’s call-to-action link.',
        '**Responses (D) start when posts go live.** Anything people say on a C post or email is logged and answered daily, with compliance first when needed.',
        '**The weekly review (E) closes the loop every Monday.** It reads page traffic from B, metrics from C and responses from D, then adjusts interest priorities and sources, which steers the next round of research (A).',
    ];
}

/** Overview diagram: how flows A–E connect. Coordinates are laid out by hand for a 1176-wide canvas. */
function mkt_manual_flow_overview_svg(array $panels): string
{
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES);
    $w = 210;
    $h = 84;
    $nodes = [
        'research'  => ['x' => 16, 'y' => 150, 'sub' => 'Output: accepted topics', 'when' => 'Always on · accept weekly'],
        'article'   => ['x' => 320, 'y' => 24, 'sub' => 'Output: live page or blog post', 'when' => '1–2 weeks per piece'],
        'campaign'  => ['x' => 320, 'y' => 150, 'sub' => 'Output: live posts and emails', 'when' => 'Weekly · 2–5 days per asset'],
        'responses' => ['x' => 640, 'y' => 276, 'sub' => 'Output: replies, escalations', 'when' => 'Daily'],
        'review'    => ['x' => 950, 'y' => 150, 'sub' => 'Output: tasks, new priorities', 'when' => 'Every Monday'],
    ];
    $short = ['research' => 'Research', 'article' => 'Article / blog post', 'campaign' => 'Campaign', 'responses' => 'Responses', 'review' => 'Weekly review'];
    $edges = [
        ['d' => 'M121 150 V66 H316', 'label' => ['Accepted topic (optional)'], 'x' => 214, 'y' => 58, 'dashed' => true],
        ['d' => 'M226 192 H316', 'label' => ['Accepted', 'topic (required)'], 'x' => 271, 'y' => 170],
        ['d' => 'M425 108 V146', 'label' => ['Campaign can link to', 'the published page'], 'x' => 436, 'y' => 124, 'dashed' => true, 'anchor' => 'start'],
        ['d' => 'M530 66 H1055 V146', 'label' => ['Search Console and GA4 traffic for published pages'], 'x' => 790, 'y' => 58],
        ['d' => 'M530 192 H946', 'label' => ['Post and email metrics, engagement scores'], 'x' => 738, 'y' => 184],
        ['d' => 'M425 234 V318 H636', 'label' => ['Comments, replies, DMs'], 'x' => 530, 'y' => 310],
        ['d' => 'M850 318 H1020 V238', 'label' => ['Counted in', 'engagement scores'], 'x' => 935, 'y' => 336],
        ['d' => 'M1110 234 V400 H121 V238', 'label' => ['New interest priorities and sources start the next research cycle'], 'x' => 600, 'y' => 392, 'loop' => true],
    ];

    $svg = [];
    $svg[] = '<svg viewBox="0 0 1176 412" role="img" aria-labelledby="flow-overview-title" xmlns="http://www.w3.org/2000/svg">';
    $svg[] = '<title id="flow-overview-title">How the flows connect: Research feeds Articles and Campaigns; Campaign posts produce Responses; traffic, metrics and responses feed the Weekly review, which feeds Research.</title>';
    $svg[] = '<defs><marker id="mkt-flow-arrow-overview" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#5a7170"/></marker>'
        . '<marker id="mkt-flow-arrow-loop" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#3d8b85"/></marker></defs>';

    foreach ($edges as $edge) {
        $loop = !empty($edge['loop']);
        $svg[] = '<path d="' . $edge['d'] . '" fill="none" stroke="' . ($loop ? '#3d8b85' : '#5a7170') . '" stroke-width="' . ($loop ? 2 : 1.5) . '"'
            . (!empty($edge['dashed']) ? ' stroke-dasharray="4 3"' : '')
            . ' marker-end="url(#' . ($loop ? 'mkt-flow-arrow-loop' : 'mkt-flow-arrow-overview') . ')"/>';
        $anchor = $edge['anchor'] ?? 'middle';
        $label = '';
        foreach ($edge['label'] as $i => $line) {
            $label .= '<tspan x="' . $edge['x'] . '" y="' . ($edge['y'] + $i * 12) . '">' . $e($line) . '</tspan>';
        }
        $svg[] = '<text class="mkt-flow-edge' . ($loop ? ' mkt-flow-edge--loop' : '') . '" text-anchor="' . $anchor . '">' . $label . '</text>';
    }

    foreach ($nodes as $key => $node) {
        $panel = $panels[$key];
        $cx = $node['x'] + $w / 2;
        $svg[] = '<a class="mkt-flow-step" href="#flow-' . $key . '">'
            . '<title>' . $e($panel['letter'] . '. ' . $panel['title'] . ' — jump to the diagram') . '</title>'
            . '<rect class="mkt-flow-box" x="' . $node['x'] . '" y="' . $node['y'] . '" width="' . $w . '" height="' . $h . '" rx="10" fill="#ffffff" stroke="#2a6b65" stroke-width="1.5"/>'
            . '<circle cx="' . ($node['x'] + 22) . '" cy="' . ($node['y'] + 24) . '" r="12" fill="#2a6b65"/>'
            . '<text class="mkt-flow-node-letter" x="' . ($node['x'] + 22) . '" y="' . ($node['y'] + 28.5) . '">' . $e($panel['letter']) . '</text>'
            . '<text class="mkt-flow-node-title" x="' . ($node['x'] + 42) . '" y="' . ($node['y'] + 29) . '">' . $e($short[$key]) . '</text>'
            . '<text class="mkt-flow-node-sub" x="' . $cx . '" y="' . ($node['y'] + 56) . '" text-anchor="middle">' . $e($node['sub']) . '</text>'
            . '<text class="mkt-flow-when" x="' . $cx . '" y="' . ($node['y'] + 73) . '" text-anchor="middle">' . $e($node['when']) . '</text>'
            . '</a>';
    }
    $svg[] = '</svg>';

    return implode("\n", $svg);
}

/** Intrinsic SVG width of a panel, used to size side-by-side panels proportionally. */
function mkt_manual_flow_width(array $panel): int
{
    return MKT_FLOW_LABEL_W + max(array_column($panel['steps'], 'col')) * MKT_FLOW_PITCH + 8;
}

function mkt_manual_flow_svg(string $key, array $panel): string
{
    $lanes = array_filter(mkt_manual_flow_lanes(), static fn(string $lane): bool => in_array($lane, array_column($panel['steps'], 'lane'), true), ARRAY_FILTER_USE_KEY);
    $laneRow = array_flip(array_keys($lanes));
    $width = mkt_manual_flow_width($panel);
    $height = count($lanes) * MKT_FLOW_LANE_H + 2;
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES);
    $marker = 'mkt-flow-arrow-' . $key;

    $boxes = [];
    foreach ($panel['steps'] as $step) {
        $x = MKT_FLOW_LABEL_W + ($step['col'] - 1) * MKT_FLOW_PITCH + (MKT_FLOW_PITCH - MKT_FLOW_BOX_W) / 2;
        $y = $laneRow[$step['lane']] * MKT_FLOW_LANE_H + (MKT_FLOW_LANE_H - MKT_FLOW_BOX_H) / 2 + 1;
        $boxes[$step['id']] = ['x' => $x, 'y' => $y, 'row' => $laneRow[$step['lane']]] + $step;
    }

    $svg = [];
    $svg[] = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-labelledby="flow-' . $key . '-title" xmlns="http://www.w3.org/2000/svg">';
    $svg[] = '<title id="flow-' . $key . '-title">' . $e($panel['letter'] . '. ' . $panel['title']) . '</title>';
    $svg[] = '<defs><marker id="' . $marker . '" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#5a7170"/></marker></defs>';

    foreach ($lanes as $lane => $meta) {
        $y = $laneRow[$lane] * MKT_FLOW_LANE_H + 1;
        $svg[] = '<rect x="0" y="' . $y . '" width="' . $width . '" height="' . MKT_FLOW_LANE_H . '" fill="' . $meta['band'] . '"/>';
        $svg[] = '<line x1="0" x2="' . $width . '" y1="' . $y . '" y2="' . $y . '" stroke="#ffffff" stroke-width="2"/>';
        $svg[] = '<rect x="0" y="' . $y . '" width="6" height="' . MKT_FLOW_LANE_H . '" fill="' . $meta['stroke'] . '"/>';
        $mid = $y + MKT_FLOW_LANE_H / 2;
        $svg[] = '<text class="mkt-flow-lane" x="16" y="' . ($mid - 3) . '" fill="' . $meta['ink'] . '">' . $e($meta['label'][0]) . '<tspan x="16" dy="14">' . $e($meta['label'][1]) . '</tspan></text>';
    }

    foreach ($panel['edges'] as $edge) {
        [$a, $b] = [$boxes[$edge[0]], $boxes[$edge[1]]];
        $dash = !empty($edge[2]) ? ' stroke-dasharray="4 3"' : '';
        $acx = $a['x'] + MKT_FLOW_BOX_W / 2;
        $acy = $a['y'] + MKT_FLOW_BOX_H / 2;
        $bcy = $b['y'] + MKT_FLOW_BOX_H / 2;
        if ($a['col'] === $b['col']) {
            $down = $b['row'] > $a['row'];
            $d = 'M' . $acx . ' ' . ($down ? $a['y'] + MKT_FLOW_BOX_H : $a['y']) . ' V' . ($down ? $b['y'] - 2 : $b['y'] + MKT_FLOW_BOX_H + 2);
        } elseif ($a['row'] === $b['row']) {
            $d = 'M' . ($a['x'] + MKT_FLOW_BOX_W) . ' ' . $acy . ' H' . ($b['x'] - 2);
        } else {
            $xm = $a['x'] + MKT_FLOW_BOX_W + ($b['x'] - $a['x'] - MKT_FLOW_BOX_W) / 2;
            $d = 'M' . ($a['x'] + MKT_FLOW_BOX_W) . ' ' . $acy . ' H' . $xm . ' V' . $bcy . ' H' . ($b['x'] - 2);
        }
        $svg[] = '<path d="' . $d . '" fill="none" stroke="#5a7170" stroke-width="1.5"' . $dash . ' marker-end="url(#' . $marker . ')"/>';
    }

    foreach ($boxes as $box) {
        $meta = $lanes[$box['lane']];
        $link = mkt_manual_link($box['link']);
        $cx = $box['x'] + MKT_FLOW_BOX_W / 2;
        $lines = $box['label'];
        $top = $box['y'] + (count($lines) === 3 ? 18 : 24);
        $label = '';
        foreach ($lines as $i => $line) {
            $label .= '<tspan x="' . $cx . '" y="' . ($top + $i * 13) . '">' . $e($line) . '</tspan>';
        }
        $badgeW = 8 + 6.5 * strlen($box['id']);
        $open = $link !== null ? '<a class="mkt-flow-step" href="' . $e($link['href']) . '" target="_blank" rel="noopener">' : '<g>';
        $close = $link !== null ? '</a>' : '</g>';
        [$screenPage, $screenTab] = mkt_manual_flow_screen($box['link']);
        $fit = static fn(string $text): string => mb_strlen($text) > 21 ? ' textLength="' . (MKT_FLOW_BOX_W - 10) . '" lengthAdjust="spacingAndGlyphs"' : '';
        $where = $link === null ? '' :
            '<line x1="' . ($box['x'] + 8) . '" x2="' . ($box['x'] + MKT_FLOW_BOX_W - 8) . '" y1="' . ($box['y'] + 65) . '" y2="' . ($box['y'] + 65) . '" stroke="' . $meta['stroke'] . '" stroke-opacity="0.35"/>'
            . '<text class="mkt-flow-page" x="' . $cx . '" y="' . ($box['y'] + 78) . '" text-anchor="middle"' . $fit($screenPage) . '>' . $e($screenPage) . '</text>'
            . ($screenTab !== '' ? '<text class="mkt-flow-page mkt-flow-page--tab" x="' . $cx . '" y="' . ($box['y'] + 91) . '" text-anchor="middle">› ' . $e($screenTab) . '</text>' : '');
        $svg[] = $open
            . '<title>' . $e($box['id'] . ': ' . implode(' ', $lines) . ' — ' . $box['when'] . ($link !== null ? ' (opens in a new tab: ' . $screenPage . ($screenTab !== '' ? ' › ' . $screenTab : '') . ')' : '')) . '</title>'
            . '<rect class="mkt-flow-box" x="' . $box['x'] . '" y="' . $box['y'] . '" width="' . MKT_FLOW_BOX_W . '" height="' . MKT_FLOW_BOX_H . '" rx="8" fill="#ffffff" stroke="' . $meta['stroke'] . '" stroke-width="1.5"' . (!empty($box['optional']) ? ' stroke-dasharray="5 3"' : '') . '/>'
            . '<rect x="' . ($box['x'] - 5) . '" y="' . ($box['y'] - 7) . '" width="' . $badgeW . '" height="15" rx="7.5" fill="' . $meta['stroke'] . '"/>'
            . '<text class="mkt-flow-badge" x="' . ($box['x'] - 5 + $badgeW / 2) . '" y="' . ($box['y'] + 4) . '">' . $e($box['id']) . '</text>'
            . '<text class="mkt-flow-label" text-anchor="middle">' . $label . '</text>'
            . '<text class="mkt-flow-when" x="' . $cx . '" y="' . ($box['y'] + 57) . '" text-anchor="middle">' . $e($box['when']) . '</text>'
            . $where
            . $close;
    }
    $svg[] = '</svg>';

    return implode("\n", $svg);
}
