<?php
/**
 * Seeds the Marketing intake starter set: product lines, competitors, pillar interests,
 * tested harvest sources, and product SEO keywords (derived from the 6.23.26 practitioner flyers).
 *
 * Idempotent: interests/sources are matched by name and merged (existing terms and links are kept).
 *
 *   php scripts/seed-marketing-starter.php            # apply
 *   php scripts/seed-marketing-starter.php --dry-run  # print the plan only
 */

require_once __DIR__ . '/../includes/marketing-intake.php';

$dryRun = in_array('--dry-run', $argv, true);

const SEED_PRODUCTS = [
    'LeanAxis'   => 'Metabolic and Weight Health',
    'MetaGI'     => 'Metabolic and Weight Health',
    'MyoProtect' => 'Metabolic and Weight Health',
    'IronAxis'   => 'Metabolic and Weight Health',
    'AndroAxis'  => 'Hormone and Reproductive Health',
    'EstroAxis'  => 'Hormone and Reproductive Health',
    'DIMAxis'    => 'Hormone and Reproductive Health',
    'MagRenew'   => 'Mood, Stress and Sleep',
    'AdrenaAxis' => 'Mood, Stress and Sleep',
    'MultiAxis'  => 'Healthy Aging and Longevity',
    'MitoAxis'   => 'Healthy Aging and Longevity',
    'ProbioAxis' => 'Digestive and Gut Health',
    'OmegaAxis'  => 'Pain, Immune Response and Physical Comfort',
    'ResolvAxis' => 'Pain, Immune Response and Physical Comfort',
];

const SEED_COMPETITORS = ['Thorne', 'Metagenics', 'Pure Encapsulations', 'Designs for Health', 'Xymogen', 'Douglas Laboratories', 'Biote'];

$interests = [
    'metabolic' => [
        'name' => 'Metabolic and Weight Health',
        'therapeutic_area' => 'Metabolic and Weight Health',
        'description' => 'Pillar. Products: LeanAxis (BioBerb berberine + Trpti OEA), MetaGI (probiotics + Akkermansia AH39 postbiotic), MyoProtect (myHMB), IronAxis (Ferrochel iron + vitamin C). Satiety and appetite signaling, glucose metabolism, gut-metabolic axis, lean mass preservation, iron status.',
        'audience' => 'practitioner', 'priority' => 5, 'agent' => true,
        'include' => ['berberine', 'BioBerb', 'oleoylethanolamide', 'OEA', 'Trpti', 'satiety signaling', 'appetite regulation', 'AMPK', 'glucose metabolism', 'insulin sensitivity', 'Akkermansia muciniphila', 'postbiotic', 'Lactobacillus gasseri', 'body composition', 'HMB', 'myHMB', 'lean body mass', 'muscle protein breakdown', 'sarcopenia', 'iron deficiency', 'ferrous bisglycinate', 'Ferrochel', 'ferritin'],
        'exclude' => ['livestock', 'animal feed', 'pet supplement'],
        'query' => ['berberine glucose metabolism human trial', 'oleoylethanolamide satiety human study', 'Akkermansia muciniphila postbiotic metabolic health', 'HMB lean mass preservation caloric restriction older adults', 'ferrous bisglycinate tolerability ferritin'],
    ],
    'hormone' => [
        'name' => 'Hormone and Reproductive Health',
        'therapeutic_area' => 'Hormone and Reproductive Health',
        'description' => 'Pillar. Products: AndroAxis (KSM-66 ashwagandha, LJ100 Eurycoma longifolia, saw palmetto, boron), EstroAxis (KSM-66 + maca), DIMAxis (DIM, BroccoRaphanin glucoraphanin, myrosinase, polyphenols). Male and female vitality, libido, HPA axis, estrogen metabolism.',
        'audience' => 'practitioner', 'priority' => 5, 'agent' => true,
        'include' => ['ashwagandha', 'KSM-66', 'Eurycoma longifolia', 'tongkat ali', 'LJ100', 'saw palmetto', 'boron', 'testosterone', 'luteinizing hormone', 'libido', 'sexual wellness', 'HPA axis', 'cortisol', 'maca', 'Lepidium meyenii', 'female libido', 'diindolylmethane', 'DIM', 'estrogen metabolism', 'sulforaphane', 'glucoraphanin', 'myrosinase', 'BroccoRaphanin', 'phase II detoxification'],
        'exclude' => ['anabolic steroids', 'SARMs'],
        'query' => ['ashwagandha KSM-66 randomized trial testosterone libido', 'Eurycoma longifolia LJ100 male vitality study', 'maca female sexual function trial', 'DIM estrogen metabolite ratio human study', 'sulforaphane glucoraphanin myrosinase bioavailability'],
    ],
    'mood' => [
        'name' => 'Mood, Stress and Sleep',
        'therapeutic_area' => 'Mood, Stress and Sleep',
        'description' => 'Pillar. Products: MagRenew (magnesium bisglycinate), AdrenaAxis (Rhodiola rosea + L-theanine). Stress response, HPA axis, relaxation without sedation, sleep quality, neuromuscular tension.',
        'audience' => 'practitioner', 'priority' => 5, 'agent' => true,
        'include' => ['magnesium bisglycinate', 'magnesium glycinate', 'magnesium and sleep', 'sleep quality', 'occasional stress', 'Rhodiola rosea', 'L-theanine', 'adaptogen', 'HPA axis', 'cortisol response', 'alpha brain waves', 'mental performance under stress', 'premenstrual symptoms', 'muscle tension'],
        'exclude' => ['sleeping pill', 'prescription sedative'],
        'query' => ['magnesium bisglycinate sleep quality randomized trial', 'Rhodiola rosea stress fatigue clinical trial', 'L-theanine stress attention human study', 'adaptogens cortisol HPA axis review'],
    ],
    'aging' => [
        'name' => 'Healthy Aging and Longevity',
        'therapeutic_area' => 'Healthy Aging and Longevity',
        'description' => 'Pillar. Products: MultiAxis (methylated B vitamins, D3 + K2 MK-7, zinc picolinate, selenium), MitoAxis (Kaneka Ubiquinol, pterostilbene, astaxanthin, mixed tocopherols). Mitochondrial energy, oxidative balance, methylation, bone and immune foundations.',
        'audience' => 'practitioner', 'priority' => 4, 'agent' => true,
        'include' => ['ubiquinol', 'Kaneka Ubiquinol', 'CoQ10', 'coenzyme Q10', 'mitochondrial function', 'ATP production', 'pterostilbene', 'astaxanthin', 'mixed tocopherols', 'oxidative stress', 'methylation', 'methylfolate', 'L-5-MTHF', 'methylcobalamin', 'pyridoxal-5-phosphate', 'homocysteine', 'MTHFR', 'vitamin K2', 'MK-7', 'vitamin D3', 'zinc picolinate', 'selenium', 'statin CoQ10 depletion'],
        'exclude' => ['anti-aging cream', 'cosmetic'],
        'query' => ['ubiquinol supplementation older adults clinical trial', 'pterostilbene human study', 'methylfolate homocysteine supplementation trial', 'vitamin K2 MK-7 bone vascular health study'],
    ],
    'gut' => [
        'name' => 'Digestive and Gut Health',
        'therapeutic_area' => 'Digestive and Gut Health',
        'product_line' => 'ProbioAxis',
        'description' => 'Pillar. Product: ProbioAxis (synbiotic: LGG, B. lactis B420, La-14, Lpc-37, LP-115, Bl-05, L. bulgaricus + inavea acacia fiber). Microbiome diversity, SCFA production, gut barrier and tight junctions. MetaGI also relevant.',
        'audience' => 'practitioner', 'priority' => 4, 'agent' => true,
        'include' => ['synbiotic', 'probiotic', 'prebiotic fiber', 'acacia fiber', 'short-chain fatty acids', 'butyrate', 'gut barrier', 'tight junctions', 'intestinal permeability', 'microbiome diversity', 'Lactobacillus rhamnosus GG', 'Bifidobacterium lactis', 'Bacillus coagulans', 'spore-forming probiotic'],
        'exclude' => ['fecal transplant clinic', 'kombucha recipe'],
        'query' => ['synbiotic supplementation gut barrier randomized trial', 'acacia fiber short-chain fatty acids human study', 'Lactobacillus rhamnosus GG adult clinical trial', 'probiotic microbiome diversity meta-analysis'],
    ],
    'pain' => [
        'name' => 'Pain, Immune Response and Physical Comfort',
        'therapeutic_area' => 'Pain, Immune Response and Physical Comfort',
        'description' => 'Pillar. Products: OmegaAxis (1,000 mg EPA/DHA, triglyceride form), ResolvAxis (pro-resolving mediators 18-HEPE, 17-HDHA, 14-HDHA + EPA/DHA). Resolution of inflammation, joint and musculoskeletal recovery, immune balance.',
        'audience' => 'practitioner', 'priority' => 4, 'agent' => true,
        'include' => ['omega-3', 'EPA', 'DHA', 'triglyceride form fish oil', 'specialized pro-resolving mediators', 'SPMs', 'resolvins', 'protectins', 'maresins', '18-HEPE', '17-HDHA', 'resolution of inflammation', 'joint health', 'exercise recovery', 'musculoskeletal recovery', 'immune balance'],
        'exclude' => ['NSAID prescription', 'opioid'],
        'query' => ['specialized pro-resolving mediators supplementation human study', 'omega-3 joint health randomized trial', 'fish oil triglyceride vs ethyl ester bioavailability', 'SPM exercise recovery study'],
    ],
    'cognitive' => [
        'name' => 'Cognitive Health',
        'therapeutic_area' => 'Cognitive Health',
        'description' => 'Pillar (no dedicated product yet). Related: OmegaAxis (DHA), AdrenaAxis (L-theanine, rhodiola), MultiAxis (methylated B vitamins, homocysteine). Brain health, focus and attention, cognitive aging.',
        'audience' => 'practitioner', 'priority' => 3, 'agent' => true,
        'include' => ['cognitive function', 'brain health', 'DHA brain', 'L-theanine attention', 'rhodiola mental fatigue', 'focus', 'memory', 'homocysteine cognition', 'B vitamins cognitive decline', 'nootropic'],
        'exclude' => ['dementia drug', 'video game'],
        'query' => ['DHA supplementation cognitive function adults trial', 'L-theanine attention randomized trial', 'B vitamins homocysteine cognitive decline trial'],
    ],
    'cardio' => [
        'name' => 'Cardiovascular Health',
        'therapeutic_area' => 'Cardiovascular Health',
        'description' => 'Pillar (no dedicated product yet). Related: OmegaAxis (EPA/DHA), MitoAxis (ubiquinol), MultiAxis (K2 MK-7, D3, methylated B vitamins), MagRenew (magnesium). Lipids, vascular function, heart energy metabolism.',
        'audience' => 'practitioner', 'priority' => 3, 'agent' => true,
        'include' => ['heart health', 'EPA DHA cardiovascular', 'triglycerides', 'CoQ10 heart', 'statin-associated muscle symptoms', 'vitamin K2 vascular calcification', 'magnesium blood pressure', 'homocysteine cardiovascular', 'endothelial function'],
        'exclude' => ['cardiac surgery', 'stent'],
        'query' => ['omega-3 cardiovascular outcomes meta-analysis', 'coenzyme Q10 statin muscle symptoms trial', 'vitamin K2 arterial stiffness trial'],
    ],
    'glp1' => [
        'name' => 'GLP-1 Complementary Supplements',
        'include' => ['GLP-1', 'semaglutide', 'tirzepatide', 'GLP-1 muscle loss', 'lean mass preservation', 'GLP-1 nutrient deficiencies', 'micronutrient gaps', 'GLP-1 fatigue', 'HMB', 'Trpti', 'oleoylethanolamide'],
        'query' => ['GLP-1 receptor agonist lean mass loss nutrition', 'semaglutide tirzepatide micronutrient deficiency', 'supplements alongside GLP-1 therapy evidence'],
        'rename_terms' => ['Tripti' => 'Trpti'],
        'agent' => true,
    ],
    'menopause' => [
        'name' => 'Menopause and Perimenopause',
        'therapeutic_area' => 'Hormone and Reproductive Health',
        'description' => 'Cross-cutting theme across pillars. Related: EstroAxis, DIMAxis, AdrenaAxis (perimenopausal stress sensitivity), MagRenew (sleep, hormonal transitions), MitoAxis (ubiquinol during menopause/post-menopause).',
        'audience' => 'consumer', 'priority' => 4, 'agent' => true,
        'include' => ['perimenopause', 'menopause', 'hormonal transitions', 'menopausal sleep', 'vasomotor symptoms', 'menopause libido', 'perimenopause stress', 'estrogen metabolism'],
        'exclude' => ['hormone pellet clinic ad'],
        'query' => ['perimenopause non-hormonal supplement randomized trial', 'ashwagandha menopause trial', 'magnesium menopause sleep study'],
    ],
    'regulation' => [
        'name' => 'Dietary supplement regulation',
        'audience' => 'practitioner',
        'include' => ['structure/function claims', 'DSHEA', 'FTC health claims', 'state supplement law', 'supplement recall'],
        'agent' => true,
    ],
    'industry' => [
        'name' => 'Industry and Market Trends',
        'description' => 'Practitioner-channel supplement market: ingredient launches, trade news, consumer conversation (Reddit), category trends for content ideas.',
        'audience' => 'practitioner', 'priority' => 3, 'agent' => false,
        'include' => ['practitioner channel', 'ingredient launch', 'supplement market trends', 'nutraceutical'],
        'query' => ['practitioner supplement market trends 2026'],
    ],
    'conferences' => [
        'name' => 'Practitioner Conferences and Associations',
        'description' => 'Session themes, association news and trade-press coverage from the 2026–2027 conference plan (A4M, IHS, IFM, OMA, AMMG, ACLM, Parker, AANP, SupplySide, Expo West). Used to spot emerging topics and new interest areas.',
        'audience' => 'practitioner', 'priority' => 4, 'agent' => true,
        'include' => ['A4M', 'LongevityFest', 'Integrative Healthcare Symposium', 'Institute for Functional Medicine', 'Obesity Medicine Association', 'Age Management Medicine Group', 'American College of Lifestyle Medicine', 'Academy of Integrative Health and Medicine', 'Parker Seminars', 'AANP', 'SupplySide Global', 'Natural Products Expo West', 'Health Optimisation Summit', 'Personalized Lifestyle Medicine Institute', 'American Med Spa Association', 'Council for Responsible Nutrition'],
        'query' => ['A4M LongevityFest 2026 session topics', 'Integrative Healthcare Symposium 2027 agenda themes', 'Obesity Medicine Association 2027 annual meeting nutrition GLP-1 sessions', 'Institute for Functional Medicine annual conference 2027 topics', 'SupplySide Global 2026 ingredient trends', 'Natural Products Expo West 2027 trends', 'trending topics functional and longevity medicine conferences 2026'],
    ],
    'competitors' => [
        'name' => 'Competitor Watch',
        'description' => 'Company, product and ingredient news for practitioner-channel competitors. Names only; no sales talking points or superiority claims.',
        'audience' => 'practitioner', 'priority' => 3, 'agent' => false,
        'include' => SEED_COMPETITORS,
        'query' => ['Thorne OR Metagenics OR "Pure Encapsulations" OR "Designs for Health" new product'],
    ],
];

$notAnimal = 'NOT (mice[tiab] OR mouse[tiab] OR rats[tiab] OR rat[ti] OR murine[tiab] OR broiler*[tiab] OR piglet*[tiab] OR pigs[tiab] OR poultry[tiab] OR aquaculture[tiab] OR trout[tiab] OR zebrafish[tiab] OR fish[ti] OR "in vitro"[ti])';
$human = "(randomized[tiab] OR randomised[tiab] OR trial[tiab] OR meta-analysis[tiab] OR \"systematic review\"[tiab]) $notAnimal";
$tos = 'Public feed/search; polite UA, robots-aware.';

$sources = [
    ['FTC press releases', 'rss', 'https://www.ftc.gov/feeds/press-release.xml', 'daily', ['regulation']],
    ['FDA recalls', 'rss', 'https://www.fda.gov/about-fda/contact-fda/stay-informed/rss-feeds/recalls/rss.xml', 'daily', ['regulation']],
    ['NutraIngredients-USA', 'rss', 'https://www.nutraingredients-usa.com/arc/outboundfeeds/rss/', 'daily', ['industry', 'regulation']],
    ['SupplySide Supplement Journal', 'rss', 'https://www.supplysidesj.com/rss.xml', 'daily', ['industry', 'regulation']],
    ['Nutritional Outlook', 'rss', 'https://www.nutritionaloutlook.com/rss.xml', 'daily', ['industry', 'regulation']],
    ['Nutraceutical Business Review', 'rss', 'https://www.nutraceuticalbusinessreview.com/rss', 'daily', ['industry']],
    ['New Hope Network', 'rss', 'https://www.newhope.com/rss.xml', 'daily', ['industry', 'conferences']],
    ['Integrative Practitioner', 'rss', 'https://www.integrativepractitioner.com/feed/', 'daily', ['industry', 'conferences']],
    ['Natural Practitioner', 'rss', 'https://www.naturalpractitionermag.com/feed/', 'daily', ['industry']],
    ['Holistic Primary Care', 'rss', 'https://holisticprimarycare.net/feed/', 'weekly', ['industry']],
    ['Reddit r/Supplements', 'reddit', 'https://www.reddit.com/r/Supplements/.rss', 'daily', ['industry']],

    ['ACLM — Lifestyle Medicine news', 'rss', 'https://lifestylemedicine.org/feed/', 'weekly', ['conferences']],
    ['AMMG — Age Management Medicine', 'rss', 'https://agemed.org/feed/', 'weekly', ['conferences', 'aging']],
    ['AmSpa — Med spa news', 'rss', 'https://www.americanmedspa.org/feed/', 'weekly', ['conferences']],
    ['PLMI — Personalized Lifestyle Medicine', 'rss', 'https://plminstitute.org/feed/', 'weekly', ['conferences']],
    ['News — Functional & longevity medicine events', 'google_news', '"Institute for Functional Medicine" OR "Age Management Medicine" OR "A4M" longevity OR "Academy of Integrative Health"', 'weekly', ['conferences']],
    ['News — Obesity & lifestyle medicine', 'google_news', '"Obesity Medicine Association" OR "American College of Lifestyle Medicine" OR "obesity medicine" GLP-1', 'weekly', ['conferences', 'glp1']],
    ['News — Supplement trade shows', 'google_news', '"SupplySide Global" OR "SupplySide West" OR "Expo West" OR "Council for Responsible Nutrition" OR "Health Optimisation Summit"', 'weekly', ['conferences', 'industry']],
    ['News — Functional & integrative medicine', 'google_news', '"functional medicine" OR "integrative medicine" supplements', 'weekly', ['conferences', 'industry']],
    ['News — Longevity clinics', 'google_news', 'longevity clinic OR "longevity medicine"', 'weekly', ['conferences', 'aging']],

    ['Thorne — Take 5 Daily blog', 'crawl', 'https://www.thorne.com/take-5-daily', 'weekly', ['competitors'], ['link_pattern' => '/take-5-daily/article/', 'max_pages' => 10]],
    ['Designs for Health — blog', 'crawl', 'https://www.designsforhealth.com/blog', 'weekly', ['competitors'], ['link_pattern' => '/blog/[a-z0-9-]+$', 'max_pages' => 10]],
    ['News — Thorne', 'google_news', '"Thorne" supplements', 'weekly', ['competitors']],
    ['News — Metagenics', 'google_news', 'Metagenics', 'weekly', ['competitors']],
    ['News — Pure Encapsulations', 'google_news', '"Pure Encapsulations"', 'weekly', ['competitors']],
    ['News — Designs for Health', 'google_news', '"Designs for Health"', 'weekly', ['competitors']],
    ['News — Xymogen', 'google_news', 'Xymogen', 'weekly', ['competitors']],
    ['News — Douglas Laboratories', 'google_news', '"Douglas Laboratories"', 'weekly', ['competitors']],
    ['News — Biote', 'google_news', '"Biote" hormone OR "biote" pellet OR "Biote Medical"', 'weekly', ['competitors']],

    ['PubMed — Metabolic & weight', 'pubmed', "(berberine OR oleoylethanolamide OR \"Akkermansia muciniphila\" OR \"beta-hydroxy-beta-methylbutyrate\" OR \"ferrous bisglycinate\" OR (probiotic AND \"body composition\")) AND $human", 'weekly', ['metabolic']],
    ['News — Metabolic & weight', 'google_news', 'berberine OR Akkermansia OR "GLP-1" supplement OR "muscle loss" GLP-1', 'weekly', ['metabolic', 'glp1']],
    ['Trials — Metabolic & weight', 'clinicaltrials', 'berberine OR oleoylethanolamide OR Akkermansia OR HMB', 'weekly', ['metabolic']],

    ['PubMed — Hormone & reproductive', 'pubmed', "(ashwagandha OR \"Eurycoma longifolia\" OR \"saw palmetto\" OR boron OR maca OR diindolylmethane OR sulforaphane) AND (testosterone OR libido OR estrogen OR menopause) AND $human", 'weekly', ['hormone']],
    ['News — Hormone & reproductive', 'google_news', 'ashwagandha OR "tongkat ali" OR "DIM supplement" OR "testosterone booster" OR "libido supplement" OR "estrogen dominance" OR maca', 'weekly', ['hormone']],
    ['Trials — Hormone & reproductive', 'clinicaltrials', 'ashwagandha OR Eurycoma OR diindolylmethane OR sulforaphane OR maca', 'weekly', ['hormone']],

    ['PubMed — Mood, stress & sleep', 'pubmed', "(\"Rhodiola rosea\" OR theanine OR \"magnesium glycinate\" OR \"magnesium bisglycinate\" OR (magnesium AND sleep) OR ashwagandha) AND (stress OR cortisol OR sleep OR anxiety OR mood) AND $human", 'weekly', ['mood']],
    ['News — Mood, stress & sleep', 'google_news', 'rhodiola OR "L-theanine" OR "magnesium glycinate" OR cortisol supplement OR "sleep supplement"', 'weekly', ['mood']],
    ['Trials — Mood, stress & sleep', 'clinicaltrials', 'rhodiola OR theanine OR magnesium sleep OR ashwagandha stress', 'weekly', ['mood']],

    ['PubMed — Healthy aging & longevity', 'pubmed', "(ubiquinol OR \"coenzyme Q10\" OR pterostilbene OR astaxanthin OR methylfolate OR \"vitamin K2\" OR menaquinone) AND (aging OR mitochondria OR \"oxidative stress\" OR homocysteine) AND $human", 'weekly', ['aging']],
    ['News — Healthy aging & longevity', 'google_news', 'ubiquinol OR CoQ10 OR longevity supplement OR mitochondrial health OR "methylated B vitamins"', 'weekly', ['aging']],
    ['Trials — Healthy aging & longevity', 'clinicaltrials', 'ubiquinol OR pterostilbene OR astaxanthin OR menaquinone', 'weekly', ['aging']],

    ['PubMed — Digestive & gut', 'pubmed', "(synbiotic OR probiotic OR prebiotic OR \"acacia fiber\" OR \"short-chain fatty acids\" OR \"Lactobacillus rhamnosus GG\") AND (\"gut barrier\" OR \"intestinal permeability\" OR microbiome) AND $human", 'weekly', ['gut']],
    ['News — Digestive & gut', 'google_news', 'probiotic OR synbiotic OR "gut microbiome" supplement OR "gut barrier"', 'weekly', ['gut']],
    ['Trials — Digestive & gut', 'clinicaltrials', 'synbiotic OR probiotic gut barrier OR acacia fiber', 'weekly', ['gut']],

    ['PubMed — Pain, immune & comfort', 'pubmed', "(\"specialized pro-resolving mediators\" OR resolvin OR \"18-HEPE\" OR \"17-HDHA\" OR (omega-3 AND (inflammation OR joint OR recovery))) AND $human", 'weekly', ['pain']],
    ['News — Pain, immune & comfort', 'google_news', 'omega-3 inflammation OR "fish oil" OR "joint pain" supplement OR "joint health" OR "pro-resolving"', 'weekly', ['pain']],
    ['Trials — Pain, immune & comfort', 'clinicaltrials', 'pro-resolving mediators OR omega-3 inflammation OR fish oil joint', 'weekly', ['pain']],

    ['PubMed — Cognitive health', 'pubmed', "(docosahexaenoic OR DHA OR theanine OR \"Rhodiola rosea\" OR citicoline OR phosphatidylserine) AND (cognition OR cognitive OR memory OR attention) AND $human", 'weekly', ['cognitive']],
    ['News — Cognitive health', 'google_news', 'nootropic OR "brain health" supplement OR DHA cognition OR "cognitive decline" supplement', 'weekly', ['cognitive']],
    ['Trials — Cognitive health', 'clinicaltrials', 'DHA cognition OR theanine attention OR nutraceutical cognitive', 'weekly', ['cognitive']],

    ['PubMed — Cardiovascular health', 'pubmed', "(eicosapentaenoic OR \"omega-3\" OR ubiquinol OR \"coenzyme Q10\" OR menaquinone OR \"vitamin K2\" OR (magnesium AND \"blood pressure\")) AND (cardiovascular OR lipid OR \"vascular calcification\" OR \"blood pressure\") AND $human", 'weekly', ['cardio']],
    ['News — Cardiovascular health', 'google_news', '"heart health" supplements OR omega-3 heart OR CoQ10 OR "vitamin K2" OR "fish oil" heart', 'weekly', ['cardio']],
    ['Trials — Cardiovascular health', 'clinicaltrials', 'omega-3 cardiovascular OR coenzyme Q10 heart OR vitamin K2 vascular', 'weekly', ['cardio']],

    ['PubMed — GLP-1 & nutrition', 'pubmed', "(\"GLP-1 receptor agonist\" OR semaglutide OR tirzepatide) AND (\"lean mass\" OR \"muscle mass\" OR supplement* OR micronutrient* OR \"protein intake\" OR sarcopenia) $notAnimal", 'weekly', ['glp1', 'metabolic']],
    ['News — GLP-1 & supplements', 'google_news', 'GLP-1 supplements OR "Ozempic" muscle OR "GLP-1" nutrition deficiency', 'weekly', ['glp1']],
    ['PubMed — Menopause & perimenopause', 'pubmed', "(menopause OR perimenopause) AND (supplement OR ashwagandha OR magnesium OR maca OR diindolylmethane OR ubiquinol) AND $human", 'weekly', ['menopause', 'hormone']],
    ['News — Menopause & perimenopause', 'google_news', 'perimenopause supplement OR menopause supplements OR "menopause" natural support', 'weekly', ['menopause']],
];

/** Product SEO keywords: [keyword, intent] grouped by product (Cluster). */
$seoKeywords = [
    'LeanAxis'   => [['berberine supplement', 'commercial'], ['berberine for metabolic health', 'informational'], ['oleoylethanolamide OEA supplement', 'commercial'], ['natural appetite control supplement', 'commercial']],
    'MetaGI'     => [['akkermansia probiotic', 'commercial'], ['probiotics for weight management', 'commercial'], ['akkermansia muciniphila benefits', 'informational']],
    'MyoProtect' => [['HMB supplement', 'commercial'], ['HMB for muscle loss', 'informational'], ['muscle loss on GLP-1', 'informational'], ['how to keep muscle on semaglutide', 'informational']],
    'IronAxis'   => [['iron bisglycinate', 'commercial'], ['gentle iron supplement', 'commercial'], ['iron supplement with vitamin C', 'commercial']],
    'AndroAxis'  => [['tongkat ali benefits', 'informational'], ['ashwagandha for men', 'informational'], ['natural testosterone support', 'commercial'], ['KSM-66 ashwagandha', 'commercial']],
    'EstroAxis'  => [['maca for women', 'informational'], ['ashwagandha for women', 'informational'], ['female libido supplement', 'commercial']],
    'DIMAxis'    => [['DIM supplement', 'commercial'], ['DIM for estrogen balance', 'informational'], ['sulforaphane supplement', 'commercial']],
    'MagRenew'   => [['magnesium glycinate for sleep', 'informational'], ['magnesium bisglycinate', 'commercial'], ['best magnesium for stress', 'commercial']],
    'AdrenaAxis' => [['rhodiola and l-theanine', 'commercial'], ['rhodiola rosea benefits', 'informational'], ['supplement for stress without drowsiness', 'commercial']],
    'MultiAxis'  => [['methylated multivitamin', 'commercial'], ['multivitamin with methylfolate', 'commercial'], ['vitamin D3 K2 MK-7', 'commercial']],
    'MitoAxis'   => [['ubiquinol vs CoQ10', 'informational'], ['ubiquinol supplement', 'commercial'], ['mitochondrial support supplement', 'commercial'], ['CoQ10 and statins', 'informational']],
    'ProbioAxis' => [['synbiotic supplement', 'commercial'], ['probiotic with prebiotic fiber', 'commercial'], ['gut barrier support', 'informational']],
    'OmegaAxis'  => [['triglyceride form fish oil', 'commercial'], ['omega-3 EPA DHA supplement', 'commercial'], ['fish oil for heart health', 'informational']],
    'ResolvAxis' => [['SPM supplement', 'commercial'], ['specialized pro-resolving mediators', 'informational'], ['omega-3 for joint recovery', 'informational']],
];

function seed_log(string $message): void
{
    fwrite(STDOUT, $message . "\n");
}

function seed_find_id(string $table, string $idCol, string $name): ?int
{
    $stmt = db()->prepare("SELECT $idCol FROM dbo.$table WHERE Name = :n");
    $stmt->execute(['n' => $name]);
    $id = $stmt->fetchColumn();

    return $id === false ? null : (int) $id;
}

/* ---------- Settings ---------- */

$settings = [
    'taxonomy.product_lines' => implode("\n", array_keys(SEED_PRODUCTS)),
    'brand.competitors'      => implode("\n", SEED_COMPETITORS),
];
seed_log('Settings: product_lines (' . count(SEED_PRODUCTS) . '), competitors (' . count(SEED_COMPETITORS) . ')');
if (!$dryRun) {
    seed_log('  changed ' . marketing_settings_save($settings));
}

/* ---------- Interests (terms merged with any existing ones; source links preserved) ---------- */

$interestIds = [];
foreach ($interests as $key => $spec) {
    $id = seed_find_id('MktInterest', 'InterestID', $spec['name']);
    $existing = $id !== null ? mkt_interest_get($id) : null;
    $terms = $existing['terms'] ?? array_fill_keys(array_keys(MKT_TERM_TYPES), []);
    foreach ($spec['rename_terms'] ?? [] as $from => $to) {
        $terms['include'] = array_map(static fn(string $t): string => strcasecmp($t, $from) === 0 ? $to : $t, $terms['include']);
    }
    foreach (['include', 'exclude', 'query'] as $type) {
        $merged = [];
        foreach (array_merge($terms[$type], $spec[$type] ?? []) as $term) {
            $merged[mb_strtolower($term)] ??= $term;
        }
        $terms[$type] = array_values($merged);
    }

    $input = [
        'name'             => $spec['name'],
        'description'      => $spec['description'] ?? ($existing['Description'] ?? ''),
        'therapeutic_area' => $spec['therapeutic_area'] ?? ($existing['TherapeuticArea'] ?? ''),
        'product_line'     => $spec['product_line'] ?? ($existing['ProductLine'] ?? ''),
        'audience'         => $spec['audience'] ?? ($existing['Audience'] ?? ''),
        'priority'         => $spec['priority'] ?? ($existing['Priority'] ?? 3),
        'status'           => $existing['Status'] ?? 'active',
        'agent_enabled'    => $spec['agent'] ?? !empty($existing['AgentEnabled']),
        'source_ids'       => $existing['source_ids'] ?? [],
    ];
    foreach (MKT_TERM_TYPES as $type => $_) {
        $input['terms_' . $type] = implode("\n", $terms[$type]);
    }

    seed_log(sprintf('Interest %s: %s (include %d, exclude %d, query %d, agent %s)', $id === null ? 'NEW' : "#$id", $spec['name'], count($terms['include']), count($terms['exclude']), count($terms['query']), $input['agent_enabled'] ? 'on' : 'off'));
    if ($dryRun) {
        $interestIds[$key] = $id ?? 0;
        continue;
    }
    $result = mkt_interest_save($input, $id);
    if (!$result['ok']) {
        seed_log('  ERROR ' . $result['error']);
        exit(1);
    }
    $interestIds[$key] = (int) $result['id'];
}

/* ---------- Sources (interest links merged with existing) ---------- */

$sourceRenames = ['Natural Products Insider' => 'SupplySide Supplement Journal'];
foreach ($sourceRenames as $from => $to) {
    $oldId = seed_find_id('MktSource', 'SourceID', $from);
    if ($oldId !== null && seed_find_id('MktSource', 'SourceID', $to) === null) {
        seed_log("Source #$oldId renamed: $from -> $to");
        if (!$dryRun) {
            db()->prepare('UPDATE dbo.MktSource SET Name = :n, UpdatedAt = SYSUTCDATETIME() WHERE SourceID = :id')->execute(['n' => $to, 'id' => $oldId]);
        }
    }
}

foreach ($sources as $row) {
    [$name, $type, $target, $schedule, $keys] = $row;
    $config = $row[5] ?? [];
    $id = seed_find_id('MktSource', 'SourceID', $name);
    $existing = $id !== null ? mkt_source_get($id) : null;
    $links = array_values(array_unique(array_merge($existing['interest_ids'] ?? [], array_map(static fn(string $k): int => $interestIds[$k], $keys))));
    $field = MKT_SOURCE_TYPES[$type]['field'];

    seed_log(sprintf('Source %s: %s [%s, %s] -> %s', $id === null ? 'NEW' : "#$id", $name, $type, $schedule, implode(', ', $keys)));
    if ($dryRun) {
        continue;
    }
    $result = mkt_source_save([
        'name'         => $name,
        'source_type'  => $type,
        'url'          => $field === 'url' ? $target : '',
        'query'        => $field === 'query' ? $target : '',
        'link_pattern' => $config['link_pattern'] ?? '',
        'max_pages'    => $config['max_pages'] ?? 10,
        'schedule'     => $schedule,
        'status'       => $existing['Status'] ?? 'active',
        'tos_notes'    => $existing['TosNotes'] ?? $tos,
        'interest_ids' => $links,
    ], $id);
    if (!$result['ok']) {
        seed_log('  ERROR ' . $result['error']);
        exit(1);
    }
}

/* ---------- Product SEO keywords ---------- */

$created = 0;
$merged = 0;
foreach ($seoKeywords as $product => $list) {
    foreach ($list as [$keyword, $intent]) {
        $find = db()->prepare('SELECT * FROM dbo.MktKeyword WHERE Keyword = :k');
        $find->execute(['k' => $keyword]);
        $existing = $find->fetch() ?: null;
        if ($existing && in_array($existing['Purpose'], ['seo', 'both'], true)) {
            continue;
        }
        if ($dryRun) {
            $existing ? $merged++ : $created++;
            continue;
        }
        $result = mkt_keyword_save([
            'keyword'  => $keyword,
            'purpose'  => $existing && $existing['Purpose'] !== 'seo' ? 'both' : 'seo',
            'priority' => $existing['Priority'] ?? 3,
            'cluster'  => $existing['Cluster'] ?? $product,
            'intent'   => $existing['Intent'] ?? $intent,
            'volume'   => $existing['Volume'] ?? null,
            'difficulty' => $existing['Difficulty'] ?? null,
            'notes'    => $existing['Notes'] ?? 'From practitioner flyer (6.23.26).',
            'status'   => $existing['Status'] ?? 'active',
        ], $existing ? (int) $existing['KeywordID'] : null);
        if (!$result['ok']) {
            seed_log("  ERROR keyword $keyword: " . $result['error']);
            continue;
        }
        $existing ? $merged++ : $created++;
    }
}
seed_log("SEO keywords: $created new, $merged existing updated");

if (!$dryRun) {
    $orphan = db()->prepare("UPDATE dbo.MktKeyword SET Status = N'retired', UpdatedAt = SYSUTCDATETIME() WHERE Keyword = N'Tripti' AND NOT EXISTS (SELECT 1 FROM dbo.MktInterestTerm t WHERE t.KeywordID = dbo.MktKeyword.KeywordID)");
    $orphan->execute();
    if ($orphan->rowCount() > 0) {
        seed_log('Retired misspelled keyword "Tripti" (now "Trpti").');
    }
}

seed_log($dryRun ? 'Dry run only; nothing written.' : 'Done.');
