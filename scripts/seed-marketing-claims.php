<?php
/**
 * Seeds the Claims Matrix from the 6.23.26 practitioner flyers (the approved one-pager wording).
 * Idempotent: products matched by name (updated), claims matched by product + wording (skipped if present).
 * Seeded claims are approved unless marked 'draft' (flyer wording that needs review).
 *
 *   php scripts/seed-marketing-claims.php [--dry-run]
 */

require_once __DIR__ . '/../includes/marketing-claims.php';

$dryRun = in_array('--dry-run', $argv, true);
const SEED_SOURCE = 'Practitioner flyer 6.23.26';
const SEED_DEFAULT_TIER = ['headline' => 'label', 'benefit' => 'clinical', 'mechanism' => 'mechanistic', 'ingredient' => 'clinical', 'general' => 'clinical'];

$refs = static fn(string $list): string => implode("\n", array_map(
    static fn(int $i, string $r): string => ($i + 1) . '. ' . trim($r),
    array_keys($parts = array_values(array_filter(array_map('trim', explode(';', $list))))),
    $parts
));

/**
 * Claim rows: [type, wording, reference numbers, ingredient, tier override, status override, note]
 */
$products = [
    'LeanAxis' => [
        'area' => 'Metabolic and Weight Health',
        'headline' => 'Supports Healthy Metabolic Signaling',
        'summary' => 'Combines BioBerb berberine and Trpti oleoylethanolamide (OEA) to support complementary pathways involved in metabolic regulation and appetite signaling.',
        'formula' => "Trpti (90% oleoylethanolamide) 300 mg\nBioBerb (Berberis aristata extract, providing 188 mg berberine) 300 mg",
        'use' => '2 capsules daily or as recommended by your HCP. Take 15–30 minutes prior to a meal.',
        'intended' => "Increased appetite or cravings\nDifficulty maintaining portion control\nMetabolic stress related to diet or lifestyle\nIndividuals actively working on weight-management goals\nImplementing lifestyle strategies for metabolic health\nSeeking metabolic support alongside medical weight-management programs",
        'refs' => $refs('Fu J, et al. Nature. 2003; Tutunchi H, et al. Pharmacol Res. 2020; Tutunchi H, et al. Front Pharmacol. 2023; Yin J, et al. Metabolism. 2008; Zhang Y, et al. J Clin Endocrinol Metab. 2008; Batacan R, et al. Gut Microbes Rep. 2026; Tutunchi H, et al. Pharmacol Res. 2020; Romano A, et al. Front Pharmacol. 2015; Lan J, et al. J Ethnopharmacol. 2015; Dong H, et al. Evid Based Complement Alternat Med. 2012; Kong W, et al. Nat Med. 2004; Zhang H, et al. Metabolism. 2010'),
        'notes' => 'Flyer references 2 and 7 are the same paper (Tutunchi 2020).',
        'claims' => [
            ['headline', 'Supports Healthy Metabolic Signaling', null],
            ['benefit', 'Supports healthy satiety signaling', '1-3, 7-8'],
            ['benefit', 'Supports healthy metabolic signaling', '4-5, 9-11'],
            ['benefit', 'Supports healthy glucose metabolism', '4-5, 9-10, 12'],
            ['mechanism', 'Trpti provides oleoylethanolamide (OEA), a naturally occurring lipid mediator produced in the small intestine that activates PPAR-α, a receptor involved in satiety signaling, appetite regulation and lipid metabolism.', '1-3, 7-8', 'Trpti (OEA)'],
            ['mechanism', 'BioBerb provides a standardized form of berberine, a plant alkaloid widely studied for its effects on AMP-activated protein kinase (AMPK), a key metabolic regulatory pathway that helps maintain cellular energy balance.', '4-5, 9-11', 'BioBerb (berberine)'],
            ['ingredient', 'Berberine supports healthy glucose metabolism and insulin function, which are central aspects of metabolic health.', '4-5, 9-10, 12', 'BioBerb (berberine)'],
            ['general', 'Together, these patented ingredients support satiety signaling, metabolic balance, and healthy glucose metabolism, providing a targeted strategy for individuals seeking support for appetite regulation and metabolic health.', null],
        ],
    ],
    'MetaGI' => [
        'area' => 'Metabolic and Weight Health',
        'headline' => 'Supports Healthy Gut Microbiome, Waistline and Abdominal Body Composition',
        'summary' => 'Three clinically studied probiotic strains with Akkermansia muciniphila postbiotic to support microbiome balance, gut barrier function, and metabolic signaling pathways.',
        'formula' => "Total probiotic blend 223 mg (20 billion CFU): Lactobacillus gasseri LG08, Bifidobacterium lactis IDCC 4301, Bacillus coagulans BC99\nAkkermansia muciniphila AH39 postbiotic 10 mg (1 billion TFU)",
        'use' => '1 capsule daily or as recommended by your HCP. Taking with food is optional.',
        'intended' => "Individuals working on metabolic health\nIndividuals with digestive imbalance\nIndividuals pursuing weight-management strategies",
        'refs' => $refs('Depommier C, et al. Nat Med. 2019; Pedret A, et al. Int J Obes (Lond). 2019; Kadooka Y, et al. Eur J Clin Nutr. 2010; Stenman LK, et al. EBioMedicine. 2016; Everard A, et al. Proc Natl Acad Sci U S A. 2013; Turnbaugh PJ, et al. Nature. 2006; Cani PD, et al. Diabetes. 2008; Cani PD, et al. Gut. 2009; Majeed M, et al. Food Res Int. 2019; Canfora EE, et al. Nat Rev Endocrinol. 2015'),
        'notes' => 'Flyer supplement facts misspell "Akkemansia mucinphila".',
        'claims' => [
            ['headline', 'Supports Healthy Gut Microbiome, Waistline and Abdominal Body Composition', null],
            ['benefit', 'Supports healthy gut microbiome, waistline and abdominal body composition', '1-5'],
            ['benefit', 'Supports intestinal barrier integrity', '6-8'],
            ['benefit', 'Supports metabolic health pathways', '1-3, 6, 9'],
            ['mechanism', 'Certain probiotic strains have been shown to influence microbial diversity and metabolite production, including short-chain fatty acids (SCFAs), which play a role in epithelial integrity, immune signaling, and metabolic processes.', '1-5'],
            ['ingredient', 'Select strains such as Lactobacillus gasseri and Bacillus coagulans have been studied for their effects on body composition, gastrointestinal function, and microbiome composition.', '1-5', 'L. gasseri, B. coagulans'],
            ['mechanism', 'Certain microbes, including Akkermansia muciniphila, reside within the mucus layer and are involved in mucin turnover and maintenance of the gut barrier.', '6-8', 'Akkermansia muciniphila AH39'],
            ['general', 'The addition of Akkermansia muciniphila AH39 postbiotic supports gut barrier integrity and metabolic signaling pathways that influence energy balance and metabolic function.', null, 'Akkermansia muciniphila AH39'],
        ],
    ],
    'MyoProtect' => [
        'area' => 'Metabolic and Weight Health',
        'headline' => 'Maintenance of Lean Body Mass',
        'summary' => 'Provides 2,000 mg of myHMB, a clinically studied metabolite of leucine researched for muscle protein turnover, recovery and lean tissue preservation during metabolic stress, aging, caloric restriction, or reduced activity.',
        'formula' => "Calcium β-hydroxy β-methylbutyrate monohydrate (myHMB) 2,000 mg\nCalcium 260 mg",
        'use' => '4 capsules, 1–2x daily or as recommended by your healthcare practitioner. Taking with food is optional but may reduce GI discomfort.',
        'intended' => "Aging adults concerned about muscle loss\nPatients on GLP-1 journey\nIndividuals in a caloric deficit\nIndividuals initiating resistance training\nPost-illness or reduced activity recovery",
        'refs' => $refs('Vukovich MD, et al. J Nutr. 2001; Nissen S, et al. J Appl Physiol (1985). 1996; Deutz NE, et al. Clin Nutr. 2013; Baier S, et al. JPEN J Parenter Enteral Nutr. 2009; Osuka Y, et al. Am J Clin Nutr. 2021; Wu H, et al. Arch Gerontol Geriatr. 2015; Kim D, Kim J. Phys Act Nutr. 2022; Smith HJ, et al. Cancer Res. 2005; Wilkinson DJ, et al. J Physiol. 2013; Wilson JM, et al. Br J Nutr. 2013'),
        'claims' => [
            ['headline', 'Maintenance of Lean Body Mass', null],
            ['benefit', 'Supports healthy muscle protein turnover', '1, 2, 3'],
            ['benefit', 'Supports maintenance of lean body mass', '1, 4, 6'],
            ['benefit', 'Supports muscle strength and physical performance', '5, 6, 9, 10'],
            ['mechanism', 'HMB is a downstream metabolite of leucine that supports muscle protein synthesis signaling (mTOR pathway).', '2, 3', 'myHMB'],
            ['mechanism', 'HMB supports reduction of muscle protein breakdown.', '7, 8', 'myHMB'],
            ['ingredient', 'HMB has been studied in older adults and during muscle stress conditions.', '4, 6', 'myHMB'],
            ['ingredient', 'Clinical research suggests that HMB may support muscle strength, functional performance, and recovery from resistance training.', '5, 9, 10', 'myHMB'],
        ],
    ],
    'IronAxis' => [
        'area' => 'Metabolic and Weight Health',
        'headline' => 'Supports Healthy Iron Status',
        'summary' => 'Combines Ferrochel iron bisglycinate chelate with vitamin C to support iron absorption and utilization with improved GI tolerability.',
        'formula' => "Iron 25 mg (as Ferrochel ferrous bisglycinate chelate)\nVitamin C (as ascorbic acid) 90 mg",
        'use' => '1 liquid capsule daily or as recommended by your HCP. For optimal use take on an empty stomach; taking with food can reduce GI discomfort.',
        'intended' => "Women of reproductive age\nIndividuals with increased physiological demand for iron\nActive individuals\nIndividuals with low dietary iron intake (restrictive diets, etc.)\nThe occasionally fatigued individual looking to support healthy iron levels and energy metabolism",
        'refs' => $refs('Fischer JAJ, et al. Nutr Rev. 2023;81(8):904-920; Milman N, et al. J Perinat Med. 2014;42(2):197-206; Bagna R, et al. Curr Pediatr Rev. 2018;14(2):123-129; Jeppsen RB, Borzelleca JF. Food Chem Toxicol. 1999;37(7):723-731; Hsu CY, et al. J Chin Med Assoc. 2022;85(5):566-570; Hallberg L, et al. Int J Vitam Nutr Res Suppl. 1989;30:103-108; Pineda O, Ashmead HD. Nutrition. 2001;17(5):381-384; Szarfarc SC, et al. Arch Latinoam Nutr. 2001;51(1 Suppl 1):42-47'),
        'claims' => [
            ['headline', 'Supports Healthy Iron Status', null],
            ['benefit', 'Promotes iron absorption', '1, 6, 7'],
            ['benefit', 'Supports gastrointestinal tolerability', '2, 3, 4, 8'],
            ['benefit', 'Supports healthy iron status', '1, 2, 5, 7'],
            ['mechanism', 'Ferrous bisglycinate chelate (Ferrochel) is an amino acid chelated form of iron designed to improve absorption, while vitamin C enhances non-heme iron uptake by converting ferric iron to the more absorbable ferrous form and increasing solubility in the intestine.', '1, 6, 7', 'Ferrochel, vitamin C'],
            ['ingredient', 'Ferrous bisglycinate chelate has been shown to cause fewer GI side effects than commonly used iron salts.', '2, 3, 4, 8', 'Ferrochel'],
            ['ingredient', 'Research shows ferrous bisglycinate chelate improves iron status markers, and enhanced absorption with vitamin C may help support effective iron utilization.', '1, 2, 5, 7', 'Ferrochel, vitamin C'],
            ['general', 'Iron is essential for oxygen transport, cellular energy production, and red blood cell formation.', null, 'Iron', 'label'],
        ],
    ],
    'AndroAxis' => [
        'area' => 'Hormone and Reproductive Health',
        'headline' => 'Supports Male Libido, Energy and Sexual Wellness',
        'summary' => 'Supports male libido, sexual vitality and hormonal balance through HPA axis stress support, healthy androgen signaling and libido support, and healthy hormone metabolism.',
        'formula' => "Organic ashwagandha root extract (KSM-66) 600 mg\nLJ100 Eurycoma longifolia root extract (22% bioactive eurypeptides, 40% glycosaponins) 400 mg\nSaw palmetto fruit extract 320 mg\nBoron (as boron citrate) 3 mg",
        'use' => '3 capsules daily or as recommended by your HCP. Take with or without food.',
        'intended' => "Reduced libido or sexual drive\nStress-related vitality changes\nAge-related hormonal shifts\nDecreased stamina or performance capacity",
        'refs' => $refs('Chandrasekhar K, et al. Indian J Psychol Med. 2012; Ambiye VR, et al. Evid Based Complement Alternat Med. 2013; Chauhan S, et al. Health Sci Rep. 2022; Mutha AS, et al. J Ayurveda Integr Med. 2025; Chandrasekhar K, et al. Indian J Psychol Med. 2012; Choudhary D, et al. J Evid Based Complementary Altern Med. 2017; Salve J, et al. Cureus. 2019; Pakhale K, et al. J Med Life. 2025; Kelgane SB, et al. Cureus. 2020; Langade D, et al. Cureus. 2019; Langade D, et al. J Ethnopharmacol. 2021; Henkel RR, et al. Phytother Res. 2014; Leisegang K, et al. Medicina (Kaunas). 2022; Tambi MI, et al. Asian J Androl. 2010; Ismail SB, et al. Evid Based Complement Alternat Med. 2012; George A, et al. Andrologia. 2014; Udani J, et al. Evid Based Complement Alternat Med. 2014; Tambi MI, et al. First Asian Andrology. 2002; Int J Androl. 2005 / Asian J Androl. 2006 / Aging Male. 2007; Ismail SB, et al. Evid Based Complement Alternat Med. 2012; Chinnappan SM, et al. Food Nutr Res. 2021; Chan KQ, et al. Andrologia. 2021; Leitão AE, et al. Maturitas. 2021'),
        'notes' => 'Flyer header also shows "Supports Mood Balance and Cognitive Performance Under Stress" and "Promotes Relaxation without Tiredness" — AdrenaAxis wording, likely a copy error; seeded as draft. Also typos "Functio" and "Supports a Supports".',
        'claims' => [
            ['headline', 'Supports Male Libido, Energy and Sexual Wellness', null],
            ['benefit', 'Supports male libido, energy and sexual wellness', '1, 2, 3, 14, 15, 17, 20-23'],
            ['benefit', 'Supports a healthy stress response', '1, 2, 3, 4, 6-11'],
            ['benefit', 'Supports healthy hormone metabolism and normal male reproductive function', '1, 2, 13, 14, 21'],
            ['benefit', 'Supports mood balance and cognitive performance under stress', null, null, null, 'draft', 'Appears in the AndroAxis flyer header but matches AdrenaAxis wording — confirm before use.'],
            ['benefit', 'Promotes relaxation without tiredness', null, null, null, 'draft', 'Appears in the AndroAxis flyer header but matches AdrenaAxis wording — confirm before use.'],
            ['mechanism', 'Supports healthy stress response pathways associated with male vitality. Chronic stressors and elevated cortisol can influence androgen signaling and reproductive physiology.', '1-4, 6-11'],
            ['ingredient', 'KSM-66 Ashwagandha has been studied for its role in supporting stress resilience, healthy cortisol regulation, and male vitality and reproductive health.', '1-4, 6-11', 'KSM-66 ashwagandha'],
            ['ingredient', 'Eurycoma longifolia (LJ100) has been studied for its role in supporting male hormone physiology, including support of healthy testosterone levels already within normal range and luteinizing hormone signaling.', '1, 3, 14, 21-23', 'LJ100 Eurycoma longifolia'],
            ['ingredient', 'Saw palmetto and boron support healthy hormone metabolism associated with male reproductive physiology.', '1, 2, 13, 14, 21', 'Saw palmetto, boron'],
        ],
    ],
    'EstroAxis' => [
        'area' => 'Hormone and Reproductive Health',
        'headline' => 'Supports Female Energy, Vitality & Sexual Drive',
        'summary' => 'Combines KSM-66 ashwagandha and maca root to support two key drivers of female vitality: stress physiology (HPA axis) and energy, stamina and libido.',
        'formula' => "KSM-66 ashwagandha root extract\nMaca root (Lepidium meyenii)\n(Per-serving amounts not captured from the flyer text — confirm against the label.)",
        'use' => '2 capsules daily or as recommended by your HCP. Taking with food can reduce GI discomfort.',
        'intended' => "Reduced libido\nStress-related vitality changes\nHormonal transitions\nMood-related sexual wellbeing changes",
        'refs' => $refs('Chandrasekhar et al. Indian J Psychol Med. 2012;34(3):255-262; Salve et al. Cureus. 2019;11(12):e6466; Choudhary et al. J Evid Based Complementary Altern Med. 2017;22(1):96-106; Pakhale et al. J Med Life. 2025;18(12):1140-1154; Langade et al. Cureus. 2019;11(9):e5797; Langade et al. J Ethnopharmacol. 2021;264:113276; Kelgane et al. Cureus. 2020;12(2):e7083; Dongre et al. Biomed Res Int. 2015;2015:284154; Ajgaonkar et al. Cureus. 2022;14(10):e30787; Dording et al. CNS Neurosci Ther. 2008;14(3):182-191; Brooks et al. Menopause. 2008;15(6):1157-1162; Meissner et al. Int J Biomed Sci. 2006;2(4):360-374; Stojanovska et al. Climacteric. 2015;18(1):69-78; Gonzales et al. Plant Foods Hum Nutr. 2013;68(4):347-351'),
        'notes' => 'Flyer typo: "Occasional stress may can influence…". Formula amounts to confirm.',
        'claims' => [
            ['headline', 'Supports Female Energy, Vitality & Sexual Drive', null],
            ['benefit', 'Supports HPA axis regulation', '1-11'],
            ['benefit', 'Supports female sexual desire and wellbeing', '10-14'],
            ['benefit', 'Supports energy, vitality and sexual drive', '10, 11, 13, 14'],
            ['ingredient', 'Ashwagandha (KSM-66) is an adaptogenic botanical that supports healthy cortisol levels, stress resilience, energy and vitality.', '1-9', 'KSM-66 ashwagandha'],
            ['ingredient', 'Ashwagandha also supports overall wellbeing, including mood, sleep and quality of life in women.', '8-11', 'KSM-66 ashwagandha'],
            ['ingredient', 'Maca root (Lepidium meyenii) has traditionally been used to support energy, stamina, and vitality.', '10, 11, 13, 14', 'Maca', 'traditional'],
            ['ingredient', 'Maca root supports female sexual desire and overall wellbeing.', '10-14', 'Maca'],
            ['general', 'Together, these botanicals help support female libido, mood resilience, and reproductive wellbeing.', '1-14'],
        ],
    ],
    'DIMAxis' => [
        'area' => 'Hormone and Reproductive Health',
        'headline' => 'Supports Healthy Estrogen Metabolism Pathways',
        'summary' => 'A bioavailable blend of DIM, BroccoRaphanin glucoraphanin with myrosinase, and a polyphenol activation blend to support the systems that process, balance and respond to hormone shifts during lifestage changes.',
        'formula' => "Diindolylmethane (crystalline DIM) 150 mg\nBroccoRaphanin Plus broccoli seed 350 mg (providing 25 mg glucoraphanin)\nMustard seed powder (Sinapis alba) 150 mg (providing 15 enzyme units of myrosinase)\nPolyphenol activation blend (pomegranate seed oil) 950 mg",
        'use' => '2 liquid capsules, 1–2x daily or as recommended by your healthcare practitioner. Take with a meal containing fat.',
        'intended' => "Individuals seeking support for healthy estrogen metabolism and balance\nLooking to support hormone metabolism and clearance pathways\nExperiencing normal hormonal fluctuations\nSeeking support for liver detoxification and metabolic processing\nLooking to support cellular detoxification and antioxidant activity\nFocused on endocrine system balance and healthy aging",
        'refs' => $refs('Newman M, Smeaton J. BMC Complement Med Ther. 2024; Newman MS, Smeaton J. Menopause. 2025; Rajoria S, et al. Thyroid. 2011; Thomson CA, et al. Breast Cancer Res Treat. 2017; Atwell LL, et al. Cancer Prev Res (Phila). 2015; Axelsson AS, et al. Sci Transl Med. 2017; Fahey JW, et al. PLoS One. 2015; Shapiro TA, et al. Cancer Epidemiol Biomarkers Prev. 1998; Ghawi SK, et al. Food Chem. 2013'),
        'claims' => [
            ['headline', 'Supports Healthy Estrogen Metabolism Pathways', null],
            ['benefit', 'Supports healthy estrogen metabolism pathways', '1-9'],
            ['benefit', 'Supports phase II detoxification pathways', '5, 6, 8'],
            ['benefit', 'Supports healthy inflammatory and oxidative signaling', '4, 6, 7'],
            ['mechanism', 'DIM supports balanced estrogen metabolite formation.', '1-4', 'DIM'],
            ['mechanism', 'Myrosinase activation enhances conversion of glucoraphanin to sulforaphane; without it, sulforaphane yield is inconsistent.', '7, 9', 'Myrosinase, glucoraphanin'],
            ['mechanism', 'Crucifer-derived compounds support glutathione-related pathways.', '5, 6, 8', 'BroccoRaphanin'],
            ['mechanism', 'Sulforaphane precursors support Nrf2 activation.', '6, 7', 'Glucoraphanin'],
            ['mechanism', 'Polyphenols support redox balance and cellular signaling.', '6', 'Polyphenol activation blend'],
            ['general', 'Estrogen must be properly metabolized before elimination. Supporting healthy metabolite balance helps maintain normal physiologic hormone signaling.', '1-3'],
        ],
    ],
    'MagRenew' => [
        'area' => 'Mood, Stress and Sleep',
        'headline' => 'Highly Bioavailable & Tolerable Form of Magnesium',
        'summary' => 'A highly absorbable magnesium bisglycinate chelate designed to support relaxation and neuromuscular function.',
        'formula' => 'Magnesium 200 mg (as magnesium bisglycinate)',
        'use' => '2 capsules daily, 1–2x daily, or as recommended by your HCP. Take with or without food.',
        'intended' => "Occasional stress or high workload\nDifficulty relaxing in the evening\nPoor sleep quality or restless sleep\nMuscle tension or tightness\nPhysically active individuals\nWomen experiencing premenstrual symptoms, stress during normal hormonal transitions, and sleep disturbances\nIndividuals with restricted dietary intake",
        'refs' => $refs('Schuster J, et al. Nat Sci Sleep. 2025;17:2027-2040; Rawji A, et al. Cureus. 2024;16(4):e59317; Patel V, et al. Front Endocrinol. 2024;15:1406455; Uberti F, et al. Nutrients. 2020;12(2):573; Rondanelli M, et al. Biometals. 2021;34(4):715-736; Kirkland AE, et al. Nutrients. 2018;10(6):730; Schwalfenberg GK, Genuis SJ. Scientifica (Cairo). 2017;2017:4179326; Tan X, Huang Y. Gynecol Endocrinol. 2022;38(3):202-206; Supakatisant C, Phupong V. Matern Child Nutr. 2015;11(2):139-145; Allen MJ, Sharma S. StatPearls. 2026; Zhang Y, et al. Sleep. 2022;45(4):zsab276; Abbasi B, et al. J Res Med Sci. 2012;17(12):1161-1169; Coudray C, et al. Magnes Res. 2005;18(4):215-223'),
        'notes' => 'Flyer typo: "Woman experiencing…".',
        'claims' => [
            ['headline', 'Highly Bioavailable & Tolerable Form of Magnesium', null],
            ['benefit', 'Highly bioavailable and tolerable form', '1-3'],
            ['benefit', 'Promotes neuromuscular, bone and heart health', '4-7'],
            ['benefit', 'Supports healthy sleep, stress and mood', '8-11'],
            ['mechanism', 'Magnesium is involved in over 300 enzymatic reactions in the body and plays a central role in regulating neuromuscular signaling, stress response and nervous system balance, and energy metabolism and mitochondrial function.', '3, 4, 5, 7, 10', 'Magnesium'],
            ['mechanism', 'Magnesium helps regulate the movement of calcium and potassium ions across cell membranes, which is fundamental for proper neuromuscular signaling.', '4-7', 'Magnesium'],
            ['ingredient', 'Magnesium bisglycinate (magnesium bound to the amino acid glycine) is noted for its high bioavailability and is less likely to cause the laxative effect commonly associated with other forms like magnesium oxide.', '1-3', 'Magnesium bisglycinate'],
            ['ingredient', 'Research suggests that magnesium supplementation may help reduce measures of occasional anxiousness and stress.', '8-11', 'Magnesium'],
            ['ingredient', 'Magnesium bisglycinate supplementation has been reported to improve occasional sleeplessness in adults reporting poor sleep quality.', '8-11', 'Magnesium bisglycinate'],
        ],
    ],
    'AdrenaAxis' => [
        'area' => 'Mood, Stress and Sleep',
        'headline' => 'Supports a Healthy Stress Response',
        'summary' => 'Combines an adaptogenic botanical (Rhodiola rosea) with a calming amino acid (L-theanine) to support healthy stress response signaling, mental performance under pressure, and balanced nervous system tone without sleepiness.',
        'formula' => "Rhodiola extract (root) (Rhodiola rosea L.) 200 mg\nL-Theanine 100 mg",
        'use' => '1 liquid capsule up to 3x daily or as recommended by your HCP. For optimal results take 30–60 minutes before a stressor on an empty stomach.',
        'intended' => "High-demand professionals\nIndividuals on their GLP-1 journey experiencing fatigue\nIndividuals under occasional stress\nPerimenopausal individuals experiencing stress sensitivity\nStudents / cognitive performance support",
        'refs' => '',
        'notes' => 'The 6.23.26 flyer reference list is a copy of the MultiAxis references (Lamers, Homocysteine Studies Collaboration, Leklem…) and does not support rhodiola / L-theanine claims. Reference numbers are omitted until the flyer is corrected.',
        'claims' => [
            ['headline', 'Supports a Healthy Stress Response', null],
            ['benefit', 'Supports a healthy stress response', null],
            ['benefit', 'Supports mood balance and cognitive performance under stress', null],
            ['benefit', 'Promotes relaxation without tiredness', null],
            ['ingredient', 'Rhodiola rosea has been studied for its role in supporting hypothalamic pituitary adrenal axis balance, healthy cortisol response to stress, and adaptation to physical and mental stressors.', null, 'Rhodiola rosea'],
            ['ingredient', 'Rhodiola has been shown to influence serotonin signaling, support of dopamine pathways, and stress related neurotransmission.', null, 'Rhodiola rosea', 'mechanistic'],
            ['ingredient', 'L-Theanine has been studied for increased alpha brainwave activity, reduced perceived stress, and improved attention without sleepiness.', null, 'L-theanine'],
            ['general', 'Rhodiola supports adaptive stress response and HPA axis signaling, while L-theanine promotes calm, focused cognitive function.', null],
        ],
    ],
    'MultiAxis' => [
        'area' => 'Healthy Aging and Longevity',
        'headline' => 'Supports Healthy Methylation & Cellular Energy Metabolism',
        'summary' => 'A comprehensive multivitamin with activated B-vitamins, bioavailable trace minerals, and vitamin D3 + K2 to support foundational biochemical pathways.',
        'formula' => "Vitamin D (as cholecalciferol) 62.5 mcg\nRiboflavin (as riboflavin 5-phosphate sodium) 15 mg\nVitamin B6 (as pyridoxal 5'-phosphate) 8 mg\nFolate (as L-5-methyltetrahydrofolic acid, calcium salt) 500 mcg DFE\nVitamin B12 (as methylcobalamin) 300 mcg\nSelenium (as L-selenomethionine) 50 mcg\nZinc (as zinc picolinate) 7.5 mg\nVitamin K2 (as MK-7 menaquinone-7) 45 mcg",
        'use' => '1 capsule 1–2x daily or as recommended by your HCP. Take with food for optimal results and GI comfort.',
        'intended' => "Individuals on GLP-1 journey or on restricted diets\nIndividuals undergoing hormone-related lifestyle changes\nHigh-stress individuals\nAging adults\nIndividuals with methylation support needs",
        'refs' => $refs('Lamers Y, et al. Am J Clin Nutr. 2006; Homocysteine Studies Collaboration. JAMA. 2002; Leklem JE. J Nutr. 1990; Aranow C. J Investig Med. 2011; Martineau AR, et al. BMJ. 2017; Knapen MH, et al. Osteoporos Int. 2013; Schurgers LJ, et al. Biochim Biophys Acta. 2002; Rayman MP. Lancet. 2000; Prasad AS. Mol Med. 2008; Powers HJ. Am J Clin Nutr. 2003; de Jager J, et al. BMJ. 2010; Shojania AM. Can Med Assoc J. 1982; Vieth R. Am J Clin Nutr. 1999; Aloia JF, et al. Am J Clin Nutr. 2008; Pokushalov E, et al. Nutrients. 2024; Obersby D, et al. Curr Res Nutr Food Sci. 2015; McKinley MC, et al. Am J Clin Nutr. 2001; McNulty H, et al. Arch Public Health. 2014; Bonomini M, et al. Nephrol Dial Transplant. 1995; Duffield-Lillico AJ, et al. J Natl Cancer Inst. 2003; Mocchegiani E, et al. Age (Dordr). 2013'),
        'claims' => [
            ['headline', 'Supports Healthy Methylation & Cellular Energy Metabolism', null],
            ['benefit', 'Supports healthy methylation and cellular energy metabolism', '1, 2, 3, 10, 11, 12, 15, 16, 17'],
            ['benefit', 'Supports immune health and antioxidant activity', '4, 5, 8, 9, 18, 19, 20'],
            ['benefit', 'Supports bone and cardiovascular health', '2, 6, 7, 13, 14, 15'],
            ['mechanism', "Activated B-vitamins (riboflavin-5'-phosphate, pyridoxal-5'-phosphate, L-5-methyltetrahydrofolate, methylcobalamin) bypass common enzymatic conversion steps and support homocysteine metabolism, neurotransmitter synthesis, cellular energy production, and DNA synthesis.", '1, 2, 3, 10, 11, 12, 15, 16, 17', 'Methylated B vitamins'],
            ['mechanism', 'Vitamin D3, zinc picolinate, and selenium play a role in immune cell signaling, antioxidant enzyme activity (glutathione peroxidase), and cellular defense mechanisms.', '4, 5, 8, 9, 18, 19, 20', 'Vitamin D3, zinc, selenium'],
            ['mechanism', 'Vitamin K2 (MK-7), vitamin D3, and magnesium-dependent pathways support healthy calcium utilization, vascular integrity, and bone metabolism signaling.', '2, 6, 7, 13, 14, 15', 'Vitamin K2 MK-7, vitamin D3'],
        ],
    ],
    'MitoAxis' => [
        'area' => 'Healthy Aging and Longevity',
        'headline' => 'Supports Mitochondrial Energy Production',
        'summary' => 'Kaneka Ubiquinol, pterostilbene, mixed tocopherols and astaxanthin in a liquid capsule to support mitochondrial function, cellular energy metabolism, and oxidative balance.',
        'formula' => "Ubiquinol (Kaneka Ubiquinol) 100 mg\nPterostilbene (trans-pterostilbene) 25 mg\nMixed tocopherols (gamma, delta, beta, alpha) 20 mg\nAstaxanthin (from Haematococcus pluvialis) 2 mg",
        'use' => '1 liquid capsule daily or as recommended by your HCP. Take with food.',
        'intended' => "Adults experiencing age-related cellular energy decline\nIndividuals experiencing metabolic or oxidative stress\nIndividuals looking to support cardiovascular health\nActive individuals\nIndividuals taking medications that deplete CoQ10 status",
        'refs' => $refs('NCBI PubChem. Ubiquinol Compound Summary. 2026; Mizuno et al. Nutrients. 2020;12(6):1640; Orlando et al. Aging (Albany NY). 2020;12(15):15514-15531; Kalenikova et al. Life. 2024;14(1):134; Sabbatinelli et al. Nutrients. 2020;12(4):1098; Stocker et al. Proc Natl Acad Sci U S A. 1991;88(5):1646-1650; de la Bella-Garzón et al. Antioxidants (Basel). 2022;11(2):279; Kaneka Internal Report. Expansion Consulteam. 2024; Langsjoen PH, Langsjoen AM. Clin Pharmacol Drug Dev. 2014;3(1):13-17; Hosoe et al. Regul Toxicol Pharmacol. 2007;47(1):19-28; McCormack D, McFadden D. Oxid Med Cell Longev. 2013;2013:575482; Riche et al. Evid Based Complement Alternat Med. 2014;2014:459165; Kapetanovic et al. Cancer Chemother Pharmacol. 2011;68(3):593-601; (Duplicate intentionally removed); Karppi et al. Int J Vitam Nutr Res. 2007;77(1):3-11; Yoshida et al. Atherosclerosis. 2010;209(2):520-523; Fassett RG, Coombes JS. Mar Drugs. 2011;9(3):447-465; Jiang Q. Free Radic Biol Med. 2014;72:76-90; Brigelius-Flohé R, Traber MG. FASEB J. 1999;13(10):1145-1155; Hargreaves IP. Int J Biochem Cell Biol. 2014;49:105-111'),
        'claims' => [
            ['headline', 'Supports Mitochondrial Energy Production', null],
            ['benefit', 'Supports mitochondrial energy production', '1, 2, 3, 7, 11, 13, 20'],
            ['benefit', 'Supports cellular antioxidant defense', '6, 15-19'],
            ['benefit', 'Supports cardiovascular and metabolic cellular function', '5, 7, 8, 11, 12, 20'],
            ['mechanism', 'Ubiquinol provides the active reduced form of CoQ10, a critical cofactor in the mitochondrial electron transport chain responsible for cellular ATP production.', '1, 20', 'Kaneka Ubiquinol'],
            ['ingredient', 'Pterostilbene may further support mitochondrial signaling pathways involved in metabolic regulation.', '11-13', 'Pterostilbene', 'mechanistic'],
            ['ingredient', "Astaxanthin and mixed tocopherols help support the body's antioxidant network by protecting cell membranes and mitochondrial lipids from oxidative stress.", '6, 15-19', 'Astaxanthin, mixed tocopherols', 'mechanistic'],
            ['general', 'CoQ10 plays an important role in tissues with high energy demands such as heart muscle, while polyphenols like pterostilbene support metabolic signaling pathways associated with healthy aging.', '5, 7, 11, 12'],
            ['ingredient', 'Kaneka Ubiquinol has been shown in clinical trials to protect against oxidative stress associated with aging and replenish plasma ubiquinol levels to maintain a healthy CoQ10 balance.', null, 'Kaneka Ubiquinol'],
        ],
    ],
    'ProbioAxis' => [
        'area' => 'Digestive and Gut Health',
        'headline' => 'Supports Gut Barrier Function & Healthy Intestinal Environment',
        'summary' => 'A synbiotic combining multiple Lactobacillus and Bifidobacterium strains with targeted prebiotic fibers to support microbial diversity, fermentation metabolism, and intestinal barrier function.',
        'formula' => "Total probiotic blend 225 mg (30.3 billion CFU): Lactobacillus rhamnosus GG, Bifidobacterium lactis B420, Lactobacillus acidophilus La-14, Lactobacillus paracasei Lpc-37, Lactobacillus plantarum LP-115, Bifidobacterium longum Bl-05\ninavea Original (Acacia senegal) 50 mg\nCalceobiotic 5000PB (Lactobacillus bulgaricus) 50 mg\n(Per-strain CFU to confirm against the label.)",
        'use' => '1 capsule daily or as recommended by your HCP. Take with or without food.',
        'intended' => "Digestive health support\nMicrobiome balance\nGut barrier support\nFoundational microbiome maintenance",
        'refs' => $refs('Gibson GR, et al. Nat Rev Gastroenterol Hepatol. 2017;14(8):491-502; Uusitupa HM, et al. Nutrients. 2020;12(4):892; Elnour AAM, et al. Int J Health Sci (Qassim). 2023;17(6):4-5; Cherbut C, et al. Microb Ecol Health Dis. 2003;15(1):43-50; Koh A, et al. Cell. 2016;165(6):1332-1345; Anderson RC, et al. BMC Microbiol. 2010;10:316; Bron PA, et al. Br J Nutr. 2017;117(1):93-107; Szajewska H, Kołodziej M. Aliment Pharmacol Ther. 2015;42(10):1149-1157; Segers ME, Lebeer S. Microb Cell Fact. 2014;13 Suppl 1:S7; Ringel-Kulka T, et al. J Clin Gastroenterol. 2011;45(6):518-525; Cheng S, et al. Nutrients. 2023;15(4):839; Heeney DD, et al. Gut Microbes. 2019;10(3):382-397; Nobaek S, et al. Am J Gastroenterol. 2000;95(5):1231-1238'),
        'notes' => 'Flyer headline misspells the product as "ProvioAxis". Claims cite references 25 and 29, which are not in the 13-item reference list.',
        'claims' => [
            ['headline', 'Supports Gut Barrier Function & Healthy Intestinal Environment', null],
            ['benefit', 'Supports healthy microbiome diversity', '2, 8-11, 13'],
            ['benefit', 'Supports short chain fatty acid production', '1, 3-7'],
            ['benefit', 'Supports gut barrier function and a healthy intestinal environment', '6, 7, 12'],
            ['ingredient', 'The inclusion of acacia fiber provides a highly fermentable substrate for beneficial bacteria, supporting the production of short-chain fatty acids such as acetate and butyrate.', '1, 3, 4, 5', 'inavea acacia fiber'],
            ['mechanism', 'The combination of Lactobacillus and Bifidobacterium strains supports microbial balance across multiple regions of the gastrointestinal tract.', '2, 8-11, 13'],
            ['mechanism', 'Microbial metabolites and probiotics influence tight junction signaling and epithelial integrity.', '6, 7, 12'],
            ['general', 'Together, these probiotic strains and prebiotic components support microbiome balance, intestinal health, and digestive function, key factors in overall immune health.', '2, 8-11, 13'],
        ],
    ],
    'OmegaAxis' => [
        'area' => 'Pain, Immune Response and Physical Comfort',
        'headline' => 'Helps Maintain a Balanced Immune Response',
        'summary' => '1,000 mg of highly concentrated omega-3 fatty acids (EPA and DHA) in triglyceride form to support cardiovascular health, oxidative stress balance, and cellular membrane function.',
        'formula' => "EPA (eicosapentaenoic acid) as triglyceride 500 mg\nDHA (docosahexaenoic acid) as triglyceride 500 mg\n(Highly refined fish oil: anchovy, sardine, mackerel. Contains fish.)",
        'use' => '2 liquid capsules daily or as recommended by your HCP. Take with food.',
        'intended' => "Cardiovascular health\nHealthy inflammatory balance\nCognitive function\nHealthy aging",
        'refs' => $refs('Calder PC. Nutrients. 2010;2(3):355-374; Julliard et al. Semin Immunol. 2022;59:101605; Vomero et al. Autoimmun Rev. 2025;24(11):103896; Serhan CN. Nature. 2014;510(7503):92-101; Ramon et al. J Immunol. 2012;189:1036-1042; Hsiao et al. PLoS One. 2013;8:e58258; Qu et al. J Pathol. 2012;228:506-519; Möller et al. J Transl Med. 2023;21(1):423'),
        'notes' => 'Flyer cites references 9 and 10, which are not in the 8-item reference list. Typo "Healty Aging".',
        'claims' => [
            ['headline', 'Helps Maintain a Balanced Immune Response', null],
            ['benefit', 'Supports cardiovascular health', '1, 2, 8'],
            ['benefit', 'Supports brain and nervous system function', '5, 6'],
            ['benefit', 'Helps maintain a balanced immune response', '3, 4'],
            ['ingredient', 'The triglyceride form mirrors the natural structure of omega-3s found in fish and has been shown to support efficient absorption and bioavailability.', '2, 3, 4, 5, 7', 'EPA/DHA triglyceride form'],
            ['mechanism', 'Omega-3 fatty acids serve as precursors for specialized pro-resolving mediators (SPMs) including resolvins, protectins and maresins. These molecules help regulate inflammatory responses.', '3, 4', 'EPA/DHA'],
            ['mechanism', 'DHA is a major structural component of neuronal membranes and plays a role in maintaining healthy brain signaling and cognitive function.', '5, 6', 'DHA'],
            ['ingredient', 'EPA and DHA have been extensively studied for their role in supporting healthy lipid metabolism, vascular function, and heart health.', '1, 2, 8', 'EPA/DHA'],
        ],
    ],
    'ResolvAxis' => [
        'area' => 'Pain, Immune Response and Physical Comfort',
        'headline' => 'Supports the Natural Resolution of Inflammation',
        'summary' => 'Pro-resolving mediators (PRMs) derived from omega-3 fatty acids, with EPA and DHA, to support balanced immune response and tissue recovery.',
        'formula' => "Total PRMs (including 18-HEPE, 17-HDHA and 14-HDHA) 300 mcg\nEPA (eicosapentaenoic acid) 150 mg\nDHA (docosahexaenoic acid) 100 mg\n(Highly refined fish oil: anchovy, sardine, mackerel. Contains fish.)",
        'use' => '1 softgel 1–2x daily or as recommended by your HCP. Take with food.',
        'intended' => "Joint health\nMusculoskeletal recovery\nBody's natural immune balance\nPhysical recovery after activity",
        'refs' => $refs('GISSI-Prevenzione Trial. Lancet. 1999;354(9177):447-455; Mozaffarian D, Wu JH. J Am Coll Cardiol. 2011;58(20):2047-2067; Serhan CN, Chiang N, Van Dyke TE. Nat Rev Immunol. 2008;8(5):349-361; Calder PC. Nutrients. 2010;2(3):355-374; Gómez-Pinilla F. Nat Rev Neurosci. 2008;9(7):568-578; Yurko-Mauro K, et al. Alzheimers Dement. 2010;6(6):456-464; Dyerberg J, et al. Prostaglandins Leukot Essent Fatty Acids. 2010;83(3):137-141; Manson JE, et al. N Engl J Med. 2019;380(1):23-32; Serhan CN, Levy BD. J Clin Invest. 2018;128(7):2657-2669; Yurko-Mauro K, et al. PLoS One. 2015;10(3):e0120391'),
        'notes' => 'Flyer text reads "HDHA, and 14-HDHA" (missing 17-/18-HEPE); corrected from the supplement facts. Typos "Docosanexaenoic" and "Eicosapentanoic".',
        'claims' => [
            ['headline', 'Supports the Natural Resolution of Inflammation', null],
            ['benefit', 'Supports the natural resolution of inflammation', '1-4'],
            ['benefit', 'Supports joint, musculoskeletal and immune health', '1-5'],
            ['benefit', 'Supports tissue recovery following strenuous activity', '2, 3, 4, 6, 7, 8'],
            ['mechanism', '18-HEPE, 17-HDHA, and 14-HDHA serve as precursors to specialized pro-resolving mediators (SPMs) such as resolvins, protectins, and maresins.', '1, 2, 4', 'PRMs (18-HEPE, 17-HDHA, 14-HDHA)'],
            ['mechanism', 'PRMs are precursors to specialized pro-resolving mediators that help guide the body from the inflammatory phase toward recovery and tissue repair.', '1-4', 'PRMs'],
            ['mechanism', 'Omega-3–derived lipid mediators help regulate immune signaling pathways involved in healthy inflammatory response.', '1-5', 'PRMs'],
            ['general', 'Specialized lipid mediators derived from omega-3 fatty acids help support the physiological processes involved in returning tissues to homeostasis after inflammatory signaling.', '2, 3, 4, 6, 7, 8'],
        ],
    ],
];

$findClaim = db()->prepare('SELECT ClaimID FROM dbo.MktClaim WHERE ProductID = :p AND ClaimType = :type AND ClaimText = :t');
$totals = ['products_new' => 0, 'products_updated' => 0, 'claims_new' => 0, 'claims_existing' => 0, 'approved' => 0, 'draft' => 0];

foreach ($products as $name => $spec) {
    $existing = mkt_product_find($name);
    fwrite(STDOUT, sprintf("%s %s (%d claims)\n", $existing ? 'Update' : 'New', $name, count($spec['claims'])));
    $existing ? $totals['products_updated']++ : $totals['products_new']++;
    if ($dryRun) {
        continue;
    }
    $result = mkt_product_save([
        'name' => $name, 'therapeutic_area' => $spec['area'], 'headline' => $spec['headline'], 'summary' => $spec['summary'],
        'formula' => $spec['formula'], 'suggested_use' => $spec['use'], 'intended_use' => $spec['intended'],
        'reference_list' => $spec['refs'], 'source_document' => SEED_SOURCE, 'notes' => $spec['notes'] ?? ($existing['Notes'] ?? ''),
        'status' => $existing['Status'] ?? 'active',
    ], $existing ? (int) $existing['ProductID'] : null);
    if (!$result['ok']) {
        fwrite(STDERR, "  ERROR {$result['error']}\n");
        exit(1);
    }
    $productId = (int) $result['id'];

    foreach ($spec['claims'] as $i => $row) {
        [$type, $text, $refNumbers] = $row;
        $findClaim->execute(['p' => $productId, 'type' => $type, 't' => $text]);
        if ($findClaim->fetchColumn() !== false) {
            $totals['claims_existing']++;
            continue;
        }
        $saved = mkt_claim_save([
            'product_id' => $productId, 'claim_text' => $text, 'claim_type' => $type, 'ingredient' => $row[3] ?? '',
            'evidence_tier' => $row[4] ?? SEED_DEFAULT_TIER[$type], 'reference_numbers' => $refNumbers ?? '',
            'audience' => 'both', 'requires_disclaimer' => true, 'sort_order' => ($i + 1) * 10,
            'source_document' => SEED_SOURCE, 'notes' => $row[6] ?? '', 'status' => 'draft',
        ]);
        if (!$saved['ok']) {
            fwrite(STDERR, "  ERROR claim: {$saved['error']}\n");
            continue;
        }
        $totals['claims_new']++;
        if (($row[5] ?? 'approved') === 'approved') {
            $approved = mkt_claim_approve((int) $saved['id']);
            $approved['ok'] ? $totals['approved']++ : fwrite(STDERR, "  approve failed: {$approved['error']}\n");
        } else {
            $totals['draft']++;
        }
    }
}

fwrite(STDOUT, json_encode($totals) . ($dryRun ? " (dry run)\n" : "\n"));
