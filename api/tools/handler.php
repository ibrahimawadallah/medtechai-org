<?php
/**
 * Arab MedTechAI — Tools API Handler
 * Provider priority: Groq (Llama 3.3 70B) → Gemini 1.5 Flash → OpenRouter
 * Add keys via server env vars or .htaccess SetEnv
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ── API keys (set at least one) ────────────────────────────────────────────
define('GROQ_KEY',        getenv('GROQ_API_KEY')        ?: '');
define('GEMINI_KEY',      getenv('GEMINI_API_KEY')      ?: getenv('GOOGLE_API_KEY') ?: '');
define('OPENROUTER_KEY',  getenv('OPENROUTER_API_KEY')  ?: '');

// ── Request routing ────────────────────────────────────────────────────────
$uri   = $_SERVER['REQUEST_URI'];
$parts = explode('/', trim(parse_url($uri, PHP_URL_PATH), '/'));
$tool  = $parts[2] ?? '';
$raw   = file_get_contents('php://input');
$body  = json_decode($raw, true) ?: [];
foreach ($_POST as $k => $v) $body[$k] = $v;

// ── Parse AI text response (strips markdown fences, extracts JSON) ─────────
function parseAI(string $text): array {
    $text = preg_replace('/```json\n?|```\n?/', '', $text);
    $text = trim($text);
    preg_match('/\{[\s\S]*\}/u', $text, $m);
    if ($m) { $p = json_decode($m[0], true); if ($p) return $p; }
    return ['raw_text' => $text];
}

// ── OpenAI-compatible call (Groq / OpenRouter) ────────────────────────────
function callOpenAI(string $url, string $key, string $model, string $prompt): ?string {
    $payload = json_encode([
        'model'    => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.3,
        'max_tokens'  => 2048,
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_POSTFIELDS    => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER    => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
        CURLOPT_TIMEOUT       => 30,
    ]);
    $resp = curl_exec($ch); curl_close($ch);
    $json = json_decode($resp, true);
    return $json['choices'][0]['message']['content'] ?? null;
}

// ── Gemini call ────────────────────────────────────────────────────────────
function callGemini(string $key, string $prompt): ?string {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $key;
    $payload = json_encode(['contents'=>[['parts'=>[['text'=>$prompt]]]],'generationConfig'=>['temperature'=>0.3,'maxOutputTokens'=>2048]]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>30]);
    $resp = curl_exec($ch); curl_close($ch);
    $json = json_decode($resp, true);
    return $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

// ── Main AI dispatcher — tries providers in priority order ─────────────────
function gemini(string $prompt): array {
    $text = null;

    // 1st: Groq (Llama 3.3 70B — fastest free tier, 14,400 req/day)
    if (GROQ_KEY) {
        $text = callOpenAI(
            'https://api.groq.com/openai/v1/chat/completions',
            GROQ_KEY,
            'llama-3.3-70b-versatile',
            $prompt
        );
    }

    // 2nd: Gemini 1.5 Flash (1,500 req/day free)
    if (!$text && GEMINI_KEY) {
        $text = callGemini(GEMINI_KEY, $prompt);
    }

    // 3rd: OpenRouter — free models (DeepSeek V3 / Llama)
    if (!$text && OPENROUTER_KEY) {
        $text = callOpenAI(
            'https://openrouter.ai/api/v1/chat/completions',
            OPENROUTER_KEY,
            'meta-llama/llama-3.3-70b-instruct:free',
            $prompt
        );
    }

    if (!$text) {
        return ['error' => 'No AI provider configured. Set GROQ_API_KEY, GEMINI_API_KEY, or OPENROUTER_API_KEY on the server.'];
    }

    return parseAI($text);
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rl(array $items, string $c='#059669'): string {
    $o='<ul class="space-y-1.5 mt-2">';
    foreach($items as $i) $o.='<li class="flex items-start gap-2 text-sm text-gray-700"><span style="color:'.$c.';font-weight:700;flex-shrink:0">&#10003;</span>'.h($i).'</li>';
    return $o.'</ul>';
}
function bdg(string $l): string {
    $m=['high'=>'bg-red-100 text-red-700','severe'=>'bg-red-100 text-red-700','critical'=>'bg-red-100 text-red-700',
        'moderate'=>'bg-amber-100 text-amber-700','mild'=>'bg-yellow-100 text-yellow-700','caution'=>'bg-amber-100 text-amber-700',
        'low'=>'bg-emerald-100 text-emerald-700','none'=>'bg-gray-100 text-gray-600','normal'=>'bg-emerald-100 text-emerald-700',
        'safe'=>'bg-emerald-100 text-emerald-700','emergency'=>'bg-red-100 text-red-700','urgent'=>'bg-amber-100 text-amber-700',
        'routine'=>'bg-blue-100 text-blue-700','self-care'=>'bg-emerald-100 text-emerald-700','appropriate'=>'bg-emerald-100 text-emerald-700',
        'compatible'=>'bg-emerald-100 text-emerald-700','incompatible'=>'bg-red-100 text-red-700',
        'needs review'=>'bg-amber-100 text-amber-700','high risk'=>'bg-red-100 text-red-700'];
    $cl=$m[strtolower($l)]??'bg-gray-100 text-gray-600';
    return '<span class="text-xs font-bold px-2.5 py-1 rounded-full '.$cl.'">'.h(strtoupper($l)).'</span>';
}
function sec(string $t, string $c): string { return '<div class="mb-4"><p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">'.h($t).'</p>'.$c.'</div>'; }

switch ($tool) {

// ── DRUG SEARCH
case 'drug-search':
    $drug = trim($body['drug']??''); if(!$drug){echo json_encode(['html'=>'<p class="text-red-500">Enter a drug name.</p>']);exit;}
    $d = gemini("Explain drug \"{$drug}\" comprehensively. Return ONLY JSON: {\"genericName\":\"str\",\"brandNames\":[\"str\"],\"drugClass\":\"str\",\"therapeuticCategory\":\"str\",\"mechanismOfAction\":\"str\",\"indications\":[\"str\"],\"dosageForms\":[\"str\"],\"adultDosing\":\"str\",\"pediatricDosing\":\"str\",\"renalAdjustment\":\"str\",\"hepaticAdjustment\":\"str\",\"administration\":\"str\",\"adverseReactions\":[{\"system\":\"str\",\"reactions\":\"str\",\"frequency\":\"str\"}],\"contraindications\":[\"str\"],\"clinicalWarnings\":[\"str\"],\"drugInteractions\":[\"str\"],\"pregnancyCategory\":\"str\",\"lactationSafety\":\"str\",\"monitoringParameters\":[\"str\"],\"pharmacokinetics\":\"str\",\"patientEducation\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html=sec('Generic Name','<p class="font-bold text-gray-900 text-lg">'.h($d['genericName']??$drug).'</p>');
    if(!empty($d['brandNames'])) $html.=sec('Brand Names','<p class="text-gray-700 text-sm">'.h(implode(', ',$d['brandNames'])).'</p>');
    if(!empty($d['drugClass'])) $html.=sec('Drug Class','<p class="text-gray-700 text-sm font-semibold">'.h($d['drugClass']).'</p>');
    if(!empty($d['therapeuticCategory'])) $html.=sec('Therapeutic Category','<p class="text-gray-700 text-sm">'.h($d['therapeuticCategory']).'</p>');
    if(!empty($d['mechanismOfAction'])) $html.=sec('Mechanism of Action','<p class="text-gray-700 text-sm leading-relaxed">'.h($d['mechanismOfAction']).'</p>');
    if(!empty($d['indications'])) $html.=sec('Indications',rl($d['indications']));
    if(!empty($d['dosageForms'])) $html.=sec('Dosage Forms','<p class="text-gray-700 text-sm">'.h(implode(', ',$d['dosageForms'])).'</p>');
    if(!empty($d['adultDosing'])) $html.=sec('Adult Dosing','<p class="text-gray-700 text-sm">'.h($d['adultDosing']).'</p>');
    if(!empty($d['pediatricDosing'])) $html.=sec('Pediatric Dosing','<p class="text-gray-700 text-sm">'.h($d['pediatricDosing']).'</p>');
    if(!empty($d['renalAdjustment'])) $html.=sec('Renal Adjustment','<p class="text-amber-700 text-sm">'.h($d['renalAdjustment']).'</p>');
    if(!empty($d['hepaticAdjustment'])) $html.=sec('Hepatic Adjustment','<p class="text-amber-700 text-sm">'.h($d['hepaticAdjustment']).'</p>');
    if(!empty($d['administration'])) $html.=sec('Administration','<p class="text-gray-700 text-sm">'.h($d['administration']).'</p>');
    if(!empty($d['adverseReactions'])){$html.=sec('Adverse Reactions','');foreach($d['adverseReactions'] as $ar)$html.='<div class="border border-gray-100 rounded-xl p-3 mb-2"><div class="flex items-center justify-between"><span class="text-xs font-bold text-gray-400 uppercase">'.h($ar['system']??'').'</span><span class="text-xs text-gray-500">'.h($ar['frequency']??'').'</span></div><p class="text-sm text-gray-700 mt-1">'.h($ar['reactions']??'').'</p></div>';}
    if(!empty($d['contraindications'])) $html.=sec('Contraindications',rl($d['contraindications'],'#dc2626'));
    if(!empty($d['clinicalWarnings'])) $html.=sec('Warnings',rl($d['clinicalWarnings'],'#dc2626'));
    if(!empty($d['drugInteractions'])) $html.=sec('Drug Interactions',rl($d['drugInteractions'],'#ea580c'));
    if(!empty($d['pregnancyCategory'])) $html.=sec('Pregnancy Category','<p class="text-gray-700 text-sm">'.h($d['pregnancyCategory']).'</p>');
    if(!empty($d['lactationSafety'])) $html.=sec('Lactation Safety','<p class="text-gray-700 text-sm">'.h($d['lactationSafety']).'</p>');
    if(!empty($d['monitoringParameters'])) $html.=sec('Monitoring',rl($d['monitoringParameters'],'#7c3aed'));
    if(!empty($d['pharmacokinetics'])) $html.=sec('Pharmacokinetics','<p class="text-gray-700 text-sm">'.h($d['pharmacokinetics']).'</p>');
    if(!empty($d['patientEducation'])) $html.='<div class="mt-4 bg-blue-50 rounded-xl p-3 text-sm text-blue-800"><strong>Patient Education:</strong> '.h($d['patientEducation']).'</div>';
    echo json_encode(['html'=>$html]); break;

// ── INTERACTION CHECKER
case 'interaction-checker':
    $drugs=array_filter(array_map('trim',$body['drugs']??[]));
    if(count($drugs)<2){echo json_encode(['html'=>'<p class="text-red-500">Enter at least 2 drugs.</p>']);exit;}
    $dl=implode(', ',$drugs);
    $d=gemini("Drug interactions for: {$dl}. Return ONLY JSON: {\"interactions\":[{\"drugs\":[\"A\",\"B\"],\"severity\":\"high|moderate|low\",\"description\":\"str\",\"mechanism\":\"str\",\"clinicalSignificance\":\"str\",\"onset\":\"str\",\"management\":\"str\"}],\"overallRisk\":\"high|moderate|low\",\"summary\":\"str\",\"riskFactors\":[\"str\"],\"monitoringRecommendation\":\"str\",\"patientManagement\":\"str\",\"alternativeCombinations\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="flex items-center gap-2 mb-4"><p class="text-sm font-semibold text-gray-600">Overall Risk:</p>'.bdg($d['overallRisk']??'low').'</div>';
    if(!empty($d['summary'])) $html.=sec('Summary','<p class="text-gray-700 text-sm">'.h($d['summary']).'</p>');
    if(!empty($d['riskFactors'])) $html.=sec('Risk Factors',rl($d['riskFactors'],'#ea580c'));
    foreach(($d['interactions']??[]) as $ix){
        $html.='<div class="border border-gray-100 rounded-xl p-4 mb-3"><div class="flex items-center gap-2 mb-2"><span class="font-semibold text-gray-900 text-sm">'.h(implode(' + ',$ix['drugs']??[])).'</span>'.bdg($ix['severity']??'').'</div><p class="text-gray-600 text-sm mb-1">'.h($ix['description']??'').'</p>';
        if(!empty($ix['mechanism'])) $html.='<p class="text-xs text-gray-500 mt-1"><strong>Mechanism:</strong> '.h($ix['mechanism']).'</p>';
        if(!empty($ix['clinicalSignificance'])) $html.='<p class="text-xs text-gray-500"><strong>Significance:</strong> '.h($ix['clinicalSignificance']).'</p>';
        if(!empty($ix['onset'])) $html.='<p class="text-xs text-gray-500"><strong>Onset:</strong> '.h($ix['onset']).'</p>';
        if(!empty($ix['management'])) $html.='<p class="text-xs text-blue-700 bg-blue-50 rounded-lg p-2 mt-2"><strong>Management:</strong> '.h($ix['management']).'</p>';
        $html.='</div>';
    }
    if(!empty($d['monitoringRecommendation'])) $html.=sec('Monitoring Recommendation','<p class="text-gray-700 text-sm">'.h($d['monitoringRecommendation']).'</p>');
    if(!empty($d['patientManagement'])) $html.=sec('Patient Management','<p class="text-gray-700 text-sm">'.h($d['patientManagement']).'</p>');
    if(!empty($d['alternativeCombinations'])) $html.=sec('Alternative Combinations',rl($d['alternativeCombinations'],'#2563EB'));
    echo json_encode(['html'=>$html]); break;

// ── DOSE CALCULATOR
case 'dose-calculator':
    $d=gemini("Dose for: ".($body['drug']??'').", weight ".($body['weight']??'')."kg, age ".($body['age']??'').", indication: ".($body['indication']??'').", renal: ".($body['renal']??'Normal').". Return ONLY JSON: {\"recommendedDose\":\"str\",\"frequency\":\"str\",\"route\":\"str\",\"duration\":\"str\",\"renalAdjustment\":\"str\",\"hepaticAdjustment\":\"str\",\"pediatricDose\":\"str\",\"loadingDose\":\"str\",\"maxDose\":\"str\",\"bsaDose\":\"str\",\"therapeuticDrugMonitoring\":\"str\",\"administrationGuidance\":\"str\",\"precautions\":[\"str\"],\"monitoringParameters\":[\"str\"],\"warnings\":[\"str\"],\"notes\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html=sec('Recommended Dose','<p class="text-2xl font-black text-emerald-700">'.h($d['recommendedDose']??'').'</p>');
    $html.=sec('Frequency','<p class="text-gray-700 text-sm">'.h($d['frequency']??'').'</p>');
    $html.=sec('Route','<p class="text-gray-700 text-sm">'.h($d['route']??'').'</p>');
    if(!empty($d['duration'])) $html.=sec('Duration','<p class="text-gray-700 text-sm">'.h($d['duration']).'</p>');
    if(!empty($d['loadingDose'])) $html.=sec('Loading Dose','<p class="text-gray-700 text-sm">'.h($d['loadingDose']).'</p>');
    if(!empty($d['pediatricDose'])) $html.=sec('Pediatric Dose','<p class="text-gray-700 text-sm">'.h($d['pediatricDose']).'</p>');
    if(!empty($d['bsaDose'])) $html.=sec('BSA-Based Dose','<p class="text-gray-700 text-sm">'.h($d['bsaDose']).'</p>');
    if(!empty($d['maxDose'])) $html.=sec('Max Dose','<p class="text-gray-700 text-sm">'.h($d['maxDose']).'</p>');
    if(!empty($d['renalAdjustment'])) $html.=sec('Renal Adjustment','<p class="text-amber-700 text-sm">'.h($d['renalAdjustment']).'</p>');
    if(!empty($d['hepaticAdjustment'])) $html.=sec('Hepatic Adjustment','<p class="text-amber-700 text-sm">'.h($d['hepaticAdjustment']).'</p>');
    if(!empty($d['therapeuticDrugMonitoring'])) $html.=sec('Therapeutic Drug Monitoring','<p class="text-gray-700 text-sm">'.h($d['therapeuticDrugMonitoring']).'</p>');
    if(!empty($d['administrationGuidance'])) $html.=sec('Administration Guidance','<p class="text-gray-700 text-sm">'.h($d['administrationGuidance']).'</p>');
    if(!empty($d['precautions'])) $html.=sec('Precautions',rl($d['precautions'],'#ea580c'));
    if(!empty($d['monitoringParameters'])) $html.=sec('Monitoring',rl($d['monitoringParameters'],'#7c3aed'));
    if(!empty($d['warnings'])) $html.=sec('Warnings',rl($d['warnings'],'#dc2626'));
    if(!empty($d['notes'])) $html.=sec('Notes','<p class="text-gray-700 text-sm">'.h($d['notes']).'</p>');
    echo json_encode(['html'=>$html]); break;

// ── PREGNANCY SAFETY
case 'pregnancy-safety':
    $d=gemini("Pregnancy safety for: ".($body['drug']??'').", trimester: ".($body['trimester']??'1').". Return ONLY JSON: {\"fdaCategory\":\"A|B|C|D|X|N\",\"safety\":\"Safe|Caution|Avoid\",\"risk\":\"str\",\"fdaCategoryRationale\":\"str\",\"mechanismOfAction\":\"str\",\"trimesterSpecific\":\"str\",\"animalData\":\"str\",\"humanData\":\"str\",\"lactationSafety\":\"str\",\"breastfeedingRecommendation\":\"str\",\"maleReproductiveEffects\":\"str\",\"preconceptionCounseling\":\"str\",\"pregnancyRegistry\":\"str\",\"alternatives\":[\"str\"],\"recommendation\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $cats=['A'=>'text-emerald-700','B'=>'text-emerald-600','C'=>'text-amber-600','D'=>'text-red-600','X'=>'text-red-700'];
    $cat=$d['fdaCategory']??'N'; $cc=$cats[$cat]??'text-gray-700';
    $html='<div class="flex items-center gap-4 mb-4"><div class="text-center"><p class="text-xs text-gray-400 mb-1">FDA</p><p class="text-4xl font-black '.$cc.'">'.$cat.'</p></div><div class="flex-1">'.bdg($d['safety']??'').'<p class="text-gray-700 text-sm mt-2">'.h($d['risk']??'').'</p></div></div>';
    if(!empty($d['fdaCategoryRationale'])) $html.=sec('FDA Category Rationale','<p class="text-gray-700 text-sm">'.h($d['fdaCategoryRationale']).'</p>');
    if(!empty($d['trimesterSpecific'])) $html.=sec('Trimester Notes','<p class="text-gray-700 text-sm">'.h($d['trimesterSpecific']).'</p>');
    if(!empty($d['mechanismOfAction'])) $html.=sec('Teratogenic Mechanism','<p class="text-gray-700 text-sm">'.h($d['mechanismOfAction']).'</p>');
    if(!empty($d['animalData'])) $html.=sec('Animal Data','<p class="text-gray-700 text-sm">'.h($d['animalData']).'</p>');
    if(!empty($d['humanData'])) $html.=sec('Human Data','<p class="text-gray-700 text-sm">'.h($d['humanData']).'</p>');
    if(!empty($d['lactationSafety'])) $html.=sec('Lactation','<p class="text-gray-700 text-sm">'.h($d['lactationSafety']).'</p>');
    if(!empty($d['breastfeedingRecommendation'])) $html.=sec('Breastfeeding','<p class="text-gray-700 text-sm">'.h($d['breastfeedingRecommendation']).'</p>');
    if(!empty($d['maleReproductiveEffects'])) $html.=sec('Male Reproductive Effects','<p class="text-gray-700 text-sm">'.h($d['maleReproductiveEffects']).'</p>');
    if(!empty($d['preconceptionCounseling'])) $html.=sec('Preconception Counseling','<p class="text-gray-700 text-sm">'.h($d['preconceptionCounseling']).'</p>');
    if(!empty($d['pregnancyRegistry'])) $html.=sec('Pregnancy Registry','<p class="text-gray-700 text-sm">'.h($d['pregnancyRegistry']).'</p>');
    if(!empty($d['alternatives'])) $html.=sec('Safer Alternatives',rl($d['alternatives']));
    if(!empty($d['recommendation'])) $html.='<div class="bg-blue-50 rounded-xl p-3 text-sm text-blue-800 mt-2">'.h($d['recommendation']).'</div>';
    echo json_encode(['html'=>$html]); break;

// ── G6PD CHECKER
case 'g6pd-checker':
    $drug=$body['drug']??'';
    $d=gemini("G6PD safety for: {$drug}. Return ONLY JSON: {\"riskLevel\":\"Safe|Low Risk|Moderate Risk|High Risk|Contraindicated\",\"classification\":\"str\",\"description\":\"str\",\"mechanism\":\"str\",\"hemolyticPotential\":\"str\",\"onsetOfHemolysis\":\"str\",\"severityOfReaction\":\"str\",\"monitoringParameters\":[\"str\"],\"geneticCounseling\":\"str\",\"patientEducation\":\"str\",\"recommendation\":\"str\",\"alternatives\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="mb-4">'.bdg($d['riskLevel']??'Unknown').'</div>';
    $html.=sec('Classification','<p class="text-gray-700 text-sm font-semibold">'.h($d['classification']??'').'</p>');
    $html.=sec('Description','<p class="text-gray-700 text-sm">'.h($d['description']??'').'</p>');
    if(!empty($d['mechanism'])) $html.=sec('Mechanism','<p class="text-gray-700 text-sm">'.h($d['mechanism']).'</p>');
    if(!empty($d['hemolyticPotential'])) $html.=sec('Hemolytic Potential','<p class="text-gray-700 text-sm">'.h($d['hemolyticPotential']).'</p>');
    if(!empty($d['onsetOfHemolysis'])) $html.=sec('Onset of Hemolysis','<p class="text-gray-700 text-sm">'.h($d['onsetOfHemolysis']).'</p>');
    if(!empty($d['severityOfReaction'])) $html.=sec('Severity of Reaction','<p class="text-gray-700 text-sm">'.h($d['severityOfReaction']).'</p>');
    if(!empty($d['monitoringParameters'])) $html.=sec('Monitoring',rl($d['monitoringParameters'],'#7c3aed'));
    if(!empty($d['geneticCounseling'])) $html.=sec('Genetic Counseling','<p class="text-gray-700 text-sm">'.h($d['geneticCounseling']).'</p>');
    if(!empty($d['patientEducation'])) $html.='<div class="mt-4 bg-blue-50 rounded-xl p-3 text-sm text-blue-800"><strong>Patient Education:</strong> '.h($d['patientEducation']).'</div>';
    $html.=sec('Recommendation','<p class="text-gray-700 text-sm font-medium">'.h($d['recommendation']??'').'</p>');
    if(!empty($d['alternatives'])) $html.=sec('Alternatives',rl($d['alternatives']));
    echo json_encode(['html'=>$html]); break;

// ── DRUG COMPARISON
case 'drug-comparison':
    $d=gemini("Compare drugs: ".($body['drugA']??'')." vs ".($body['drugB']??'').". Return ONLY JSON: {\"summary\":\"str\",\"mechanismOfActionComparison\":\"str\",\"indicationsOverlap\":\"str\",\"contraindicationDifferences\":\"str\",\"monitoringRequirements\":\"str\",\"specialPopulations\":\"str\",\"guidelinePreference\":\"str\",\"comparison\":{\"efficacy\":{\"winner\":\"str\",\"reasoning\":\"str\"},\"safety\":{\"winner\":\"str\",\"reasoning\":\"str\"},\"cost\":{\"winner\":\"str\",\"reasoning\":\"str\"},\"convenience\":{\"winner\":\"str\",\"reasoning\":\"str\"}},\"considerations\":[\"str\"],\"recommendation\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html=sec('Summary','<p class="text-gray-700 text-sm">'.h($d['summary']??'').'</p>');
    if(!empty($d['mechanismOfActionComparison'])) $html.=sec('Mechanism Comparison','<p class="text-gray-700 text-sm">'.h($d['mechanismOfActionComparison']).'</p>');
    if(!empty($d['indicationsOverlap'])) $html.=sec('Indications Overlap','<p class="text-gray-700 text-sm">'.h($d['indicationsOverlap']).'</p>');
    if(!empty($d['contraindicationDifferences'])) $html.=sec('Contraindication Differences','<p class="text-gray-700 text-sm">'.h($d['contraindicationDifferences']).'</p>');
    if(!empty($d['monitoringRequirements'])) $html.=sec('Monitoring Requirements','<p class="text-gray-700 text-sm">'.h($d['monitoringRequirements']).'</p>');
    if(!empty($d['specialPopulations'])) $html.=sec('Special Populations','<p class="text-gray-700 text-sm">'.h($d['specialPopulations']).'</p>');
    if(!empty($d['guidelinePreference'])) $html.=sec('Guideline Preference','<p class="text-gray-700 text-sm">'.h($d['guidelinePreference']).'</p>');
    if(!empty($d['comparison'])){
        $html.='<div class="grid grid-cols-2 gap-3 my-4">';
        foreach($d['comparison'] as $asp=>$data) $html.='<div class="border border-gray-100 rounded-xl p-3"><p class="text-xs font-bold text-gray-400 uppercase mb-1">'.h(ucfirst($asp)).'</p><p class="font-bold text-gray-900 text-sm">'.h($data['winner']??'').'</p><p class="text-xs text-gray-500 mt-1">'.h($data['reasoning']??'').'</p></div>';
        $html.='</div>';
    }
    if(!empty($d['considerations'])) $html.=sec('Considerations',rl($d['considerations']));
    if(!empty($d['recommendation'])) $html.='<div class="bg-emerald-50 rounded-xl p-3 text-sm text-emerald-800">'.h($d['recommendation']).'</div>';
    echo json_encode(['html'=>$html]); break;

// ── CLINICAL DECISION SUPPORT
case 'clinical-decision-support':
    $d=gemini("Clinical decision support. Symptoms: ".($body['symptoms']??'').", History: ".($body['hx']??'').". Return ONLY JSON: {\"assessment\":\"str\",\"differentialDiagnosis\":[{\"condition\":\"str\",\"probability\":\"High|Medium|Low\",\"reasoning\":\"str\"}],\"recommendedWorkup\":[\"str\"],\"managementPlan\":[\"str\"],\"urgency\":\"Emergency|Urgent|Routine\",\"referral\":\"str\",\"evidenceBasedGuidelines\":[\"str\"],\"riskFactors\":[\"str\"],\"prognosis\":\"str\",\"patientEducation\":\"str\",\"followUpTimeline\":\"str\",\"clinicalPearls\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="flex items-center gap-2 mb-4"><p class="text-sm font-semibold text-gray-600">Urgency:</p>'.bdg($d['urgency']??'routine').'</div>';
    $html.=sec('Assessment','<p class="text-gray-700 text-sm leading-relaxed">'.h($d['assessment']??'').'</p>');
    if(!empty($d['differentialDiagnosis'])){
        $html.='<p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Differential Diagnosis</p>';
        foreach($d['differentialDiagnosis'] as $dd) $html.='<div class="border-b border-gray-50 py-1.5"><div class="flex items-center justify-between"><span class="text-sm text-gray-700">'.h($dd['condition']??'').'</span>'.bdg($dd['probability']??'').'</div>'.(!empty($dd['reasoning'])?'<p class="text-xs text-gray-500 mt-0.5">'.h($dd['reasoning']).'</p>':'').'</div>';
        $html.='<div class="mb-4"></div>';
    }
    if(!empty($d['evidenceBasedGuidelines'])) $html.=sec('Guidelines',rl($d['evidenceBasedGuidelines'],'#2563EB'));
    if(!empty($d['riskFactors'])) $html.=sec('Risk Factors',rl($d['riskFactors'],'#ea580c'));
    if(!empty($d['recommendedWorkup'])) $html.=sec('Workup',rl($d['recommendedWorkup'],'#2563EB'));
    if(!empty($d['managementPlan'])) $html.=sec('Management',rl($d['managementPlan']));
    if(!empty($d['prognosis'])) $html.=sec('Prognosis','<p class="text-gray-700 text-sm">'.h($d['prognosis']).'</p>');
    if(!empty($d['followUpTimeline'])) $html.=sec('Follow-up Timeline','<p class="text-gray-700 text-sm">'.h($d['followUpTimeline']).'</p>');
    if(!empty($d['clinicalPearls'])) $html.=sec('Clinical Pearls',rl($d['clinicalPearls'],'#7c3aed'));
    if(!empty($d['referral'])) $html.=sec('Referral','<p class="text-gray-700 text-sm">'.h($d['referral']).'</p>');
    if(!empty($d['patientEducation'])) $html.='<div class="mt-4 bg-blue-50 rounded-xl p-3 text-sm text-blue-800"><strong>Patient Education:</strong> '.h($d['patientEducation']).'</div>';
    echo json_encode(['html'=>$html]); break;

// ── DIAGNOSTIC CHECK
case 'diagnostic-check':
    $d=gemini("Diagnostic check. Patient: ".($body['age']??'').". Symptoms: ".($body['symptoms']??'').". Vitals: ".($body['vitals']??'').". Return ONLY JSON: {\"potentialConditions\":[{\"name\":\"str\",\"probability\":\"High|Medium|Low\",\"reasoning\":\"str\"}],\"recommendedTests\":[\"str\"],\"recommendedImaging\":[\"str\"],\"laboratoryTests\":[\"str\"],\"redFlags\":[\"str\"],\"nextSteps\":\"str\",\"diagnosticCriteria\":\"str\",\"riskStratification\":\"str\",\"specialistConsultation\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='';
    if(!empty($d['redFlags'])) $html.='<div class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4">'.sec('&#9888; Red Flags',rl($d['redFlags'],'#dc2626')).'</div>';
    $html.='<p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Potential Conditions</p>';
    foreach(($d['potentialConditions']??[]) as $c) $html.='<div class="border border-gray-100 rounded-xl p-3 mb-2"><div class="flex items-center justify-between mb-1"><span class="font-semibold text-gray-900 text-sm">'.h($c['name']??'').'</span>'.bdg($c['probability']??'').'</div><p class="text-xs text-gray-500">'.h($c['reasoning']??'').'</p></div>';
    if(!empty($d['recommendedTests'])) $html.=sec('Tests',rl($d['recommendedTests'],'#2563EB'));
    if(!empty($d['recommendedImaging'])) $html.=sec('Imaging',rl($d['recommendedImaging'],'#7c3aed'));
    if(!empty($d['laboratoryTests'])) $html.=sec('Laboratory Tests',rl($d['laboratoryTests'],'#0891b2'));
    if(!empty($d['diagnosticCriteria'])) $html.=sec('Diagnostic Criteria','<p class="text-gray-700 text-sm">'.h($d['diagnosticCriteria']).'</p>');
    if(!empty($d['riskStratification'])) $html.=sec('Risk Stratification','<p class="text-gray-700 text-sm">'.h($d['riskStratification']).'</p>');
    if(!empty($d['specialistConsultation'])) $html.=sec('Specialist Consultation','<p class="text-gray-700 text-sm">'.h($d['specialistConsultation']).'</p>');
    if(!empty($d['nextSteps'])) $html.=sec('Next Steps','<p class="text-gray-700 text-sm">'.h($d['nextSteps']).'</p>');
    echo json_encode(['html'=>$html]); break;

// ── SYMPTOM CHECKER
case 'symptom-checker':
    $d=gemini("Symptom checker triage. Patient: ".($body['age']??'')."yr ".($body['gender']??'').". Symptoms: ".($body['symptoms']??'').". Return ONLY JSON: {\"triageLevel\":\"emergency|urgent|routine|self-care\",\"summary\":\"str\",\"potentialCauses\":[\"str\"],\"durationOfSymptoms\":\"str\",\"associatedSymptoms\":[\"str\"],\"riskFactors\":[\"str\"],\"careAdvice\":[\"str\"],\"homeCareMeasures\":[\"str\"],\"urgentCareIndicators\":[\"str\"],\"emergencyIndicators\":[\"str\"],\"demographicConsiderations\":\"str\",\"whenToSeekCare\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="flex items-center gap-2 mb-4"><p class="text-sm font-semibold text-gray-600">Triage Level:</p>'.bdg($d['triageLevel']??'routine').'</div>';
    $html.=sec('Summary','<p class="text-gray-700 text-sm">'.h($d['summary']??'').'</p>');
    if(!empty($d['potentialCauses'])) $html.=sec('Potential Causes',rl($d['potentialCauses']));
    if(!empty($d['durationOfSymptoms'])) $html.=sec('Duration','<p class="text-gray-700 text-sm">'.h($d['durationOfSymptoms']).'</p>');
    if(!empty($d['associatedSymptoms'])) $html.=sec('Associated Symptoms',rl($d['associatedSymptoms'],'#7c3aed'));
    if(!empty($d['riskFactors'])) $html.=sec('Risk Factors',rl($d['riskFactors'],'#ea580c'));
    if(!empty($d['careAdvice'])) $html.=sec('Care Advice',rl($d['careAdvice'],'#2563EB'));
    if(!empty($d['homeCareMeasures'])) $html.=sec('Home Care',rl($d['homeCareMeasures'],'#059669'));
    if(!empty($d['urgentCareIndicators'])) $html.='<div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mt-4 mb-2"><p class="text-xs font-bold text-amber-800 uppercase mb-1">&#9888; Urgent Care Indicators</p>'.rl($d['urgentCareIndicators'],'#d97706').'</div>';
    if(!empty($d['emergencyIndicators'])) $html.='<div class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4"><p class="text-xs font-bold text-red-800 uppercase mb-1">&#9888; Emergency Indicators</p>'.rl($d['emergencyIndicators'],'#dc2626').'</div>';
    if(!empty($d['demographicConsiderations'])) $html.=sec('Demographic Considerations','<p class="text-gray-700 text-sm">'.h($d['demographicConsiderations']).'</p>');
    if(!empty($d['whenToSeekCare'])) $html.='<div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-sm text-amber-800 mt-2"><strong>When to seek care:</strong> '.h($d['whenToSeekCare']).'</div>';
    echo json_encode(['html'=>$html]); break;

// ── ICD-10 LOOKUP
case 'icd10-lookup':
    $d=gemini("ICD-10 codes for: \"".($body['diagnosis']??'')."\" context: ".($body['context']??'').". Return ONLY JSON: {\"mappings\":[{\"primaryCode\":\"str\",\"description\":\"str\",\"confidence\":0.9,\"reasoning\":\"str\",\"clinicalCriteria\":\"str\",\"documentationRequirements\":\"str\",\"codingGuidelines\":\"str\",\"chapterCategory\":\"str\",\"billingImplications\":\"str\",\"secondaryCodes\":[{\"code\":\"str\",\"description\":\"str\"}]}]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='';
    foreach(($d['mappings']??[]) as $m){
        $conf=round(($m['confidence']??0)*100);
        $html.='<div class="border border-gray-100 rounded-xl p-4 mb-3"><div class="flex items-start justify-between mb-2"><div><span class="font-black text-blue-700 text-xl">'.h($m['primaryCode']??'').'</span><span class="text-gray-500 text-sm ml-2">'.h($m['description']??'').'</span></div><span class="text-xs bg-blue-50 text-blue-600 px-2.5 py-1 rounded-full font-bold">'.$conf.'% match</span></div>';
        $html.='<p class="text-xs text-gray-500 mb-2">'.h($m['reasoning']??'').'</p>';
        if(!empty($m['clinicalCriteria'])) $html.='<p class="text-xs text-gray-500 mb-1"><strong>Clinical Criteria:</strong> '.h($m['clinicalCriteria']).'</p>';
        if(!empty($m['documentationRequirements'])) $html.='<p class="text-xs text-gray-500 mb-1"><strong>Documentation:</strong> '.h($m['documentationRequirements']).'</p>';
        if(!empty($m['codingGuidelines'])) $html.='<p class="text-xs text-gray-500 mb-1"><strong>Coding Guidelines:</strong> '.h($m['codingGuidelines']).'</p>';
        if(!empty($m['chapterCategory'])) $html.='<p class="text-xs text-gray-500 mb-1"><strong>Chapter:</strong> '.h($m['chapterCategory']).'</p>';
        if(!empty($m['billingImplications'])) $html.='<p class="text-xs text-amber-700 bg-amber-50 rounded px-2 py-1 mt-1"><strong>Billing:</strong> '.h($m['billingImplications']).'</p>';
        if(!empty($m['secondaryCodes'])){$html.='<div class="border-t border-gray-50 pt-2 mt-2"><p class="text-xs text-gray-400 mb-1">Secondary codes:</p>';foreach($m['secondaryCodes'] as $sc) $html.='<span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded mr-1">'.h($sc['code']).' — '.h($sc['description']).'</span>';$html.='</div>';}
        $html.='</div>';
    }
    echo json_encode(['html'=>$html]); break;

// ── SMART REPORT OIC
case 'smart-report-oic':
    $txt=$body['text']??''; $typ=$body['type']??'Radiology'; $mod=$body['modality']??'';
    $d=gemini("Analyze {$typ} ({$mod}): \"{$txt}\". Return ONLY JSON: {\"studyType\":\"str\",\"clinicalFindings\":[\"str\"],\"impression\":\"str\",\"recommendations\":[\"str\"],\"severity\":\"Normal|Mild|Moderate|Severe|Critical\",\"urgency\":\"Routine|Urgent|Emergency\",\"potentialICD10\":[{\"code\":\"str\",\"description\":\"str\"}],\"followUpSuggestions\":[\"str\"],\"technique\":\"str\",\"comparisonStudy\":\"str\",\"clinicalIndication\":\"str\",\"anatomyVisualized\":[\"str\"],\"technicalQuality\":\"str\",\"incidentalFindings\":[\"str\"],\"structuredReport\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="flex gap-2 mb-4">'.bdg($d['severity']??'Normal').bdg($d['urgency']??'Routine').'</div>';
    if(!empty($d['clinicalIndication'])) $html.=sec('Indication','<p class="text-gray-700 text-sm">'.h($d['clinicalIndication']).'</p>');
    if(!empty($d['studyType'])) $html.=sec('Study','<p class="font-semibold text-gray-900">'.h($d['studyType']).'</p>');
    if(!empty($d['technique'])) $html.=sec('Technique','<p class="text-gray-700 text-sm">'.h($d['technique']).'</p>');
    if(!empty($d['comparisonStudy'])) $html.=sec('Comparison','<p class="text-gray-700 text-sm">'.h($d['comparisonStudy']).'</p>');
    if(!empty($d['anatomyVisualized'])) $html.=sec('Anatomy Visualized',rl($d['anatomyVisualized'],'#7c3aed'));
    if(!empty($d['technicalQuality'])) $html.=sec('Technical Quality','<p class="text-gray-700 text-sm">'.h($d['technicalQuality']).'</p>');
    if(!empty($d['clinicalFindings'])) $html.=sec('Findings',rl($d['clinicalFindings']));
    if(!empty($d['incidentalFindings'])) $html.=sec('Incidental Findings',rl($d['incidentalFindings'],'#ea580c'));
    if(!empty($d['impression'])) $html.=sec('Impression','<p class="text-gray-700 text-sm font-medium leading-relaxed">'.h($d['impression']).'</p>');
    if(!empty($d['recommendations'])) $html.=sec('Recommendations',rl($d['recommendations'],'#2563EB'));
    if(!empty($d['potentialICD10'])){$html.='<p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">ICD-10</p><div class="flex flex-wrap gap-1.5 mb-4">';foreach($d['potentialICD10'] as $c) $html.='<span class="text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded font-mono">'.h($c['code']).' — '.h($c['description']).'</span>';$html.='</div>';}
    if(!empty($d['followUpSuggestions'])) $html.=sec('Follow-up',rl($d['followUpSuggestions'],'#7c3aed'));
    if(!empty($d['structuredReport'])) $html.=sec('Structured Report','<pre class="text-xs text-gray-700 whitespace-pre-wrap font-mono bg-gray-50 rounded-xl p-3">'.h($d['structuredReport']).'</pre>');
    echo json_encode(['html'=>$html]); break;

// ── REPORT COMPOSER
case 'report-composer':
    $d=gemini("Generate ".($body['type']??'Progress Note')." for patient: ".($body['patient']??'').". Chief complaint: ".($body['chiefComplaint']??'').". Findings: ".($body['findings']??'').". Return ONLY JSON: {\"reportTitle\":\"str\",\"subjective\":\"str\",\"objective\":\"str\",\"assessment\":\"str\",\"plan\":\"str\",\"chiefComplaint\":\"str\",\"historyOfPresentIllness\":\"str\",\"pastMedicalHistory\":\"str\",\"medicationsAtHome\":\"str\",\"socialHistory\":\"str\",\"reviewOfSystems\":\"str\",\"diagnosisList\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="font-mono text-sm border border-gray-200 rounded-xl p-5 bg-gray-50"><p class="font-bold text-center text-base mb-3">'.h($d['reportTitle']??'Medical Report').'</p><p class="text-gray-400 text-xs text-center mb-4">'.date('Y-m-d').'</p>';
    if(!empty($d['chiefComplaint'])) $html.='<div class="mb-3"><p class="font-bold text-gray-900">Chief Complaint</p><p class="text-gray-700 ml-2">'.h($d['chiefComplaint']).'</p></div>';
    if(!empty($d['historyOfPresentIllness'])) $html.='<div class="mb-3"><p class="font-bold text-gray-900">HPI</p><p class="text-gray-700 ml-2">'.h($d['historyOfPresentIllness']).'</p></div>';
    if(!empty($d['pastMedicalHistory'])) $html.='<div class="mb-3"><p class="font-bold text-gray-900">PMH</p><p class="text-gray-700 ml-2">'.h($d['pastMedicalHistory']).'</p></div>';
    if(!empty($d['medicationsAtHome'])) $html.='<div class="mb-3"><p class="font-bold text-gray-900">Medications</p><p class="text-gray-700 ml-2">'.h($d['medicationsAtHome']).'</p></div>';
    if(!empty($d['socialHistory'])) $html.='<div class="mb-3"><p class="font-bold text-gray-900">Social History</p><p class="text-gray-700 ml-2">'.h($d['socialHistory']).'</p></div>';
    if(!empty($d['reviewOfSystems'])) $html.='<div class="mb-3"><p class="font-bold text-gray-900">ROS</p><p class="text-gray-700 ml-2">'.h($d['reviewOfSystems']).'</p></div>';
    foreach(['subjective'=>'S — Subjective','objective'=>'O — Objective','assessment'=>'A — Assessment','plan'=>'P — Plan'] as $k=>$l) if(!empty($d[$k])) $html.='<div class="mb-3"><p class="font-bold text-gray-900">'.$l.'</p><p class="text-gray-700 ml-2">'.h($d[$k]).'</p></div>';
    if(!empty($d['diagnosisList'])){$html.='<div class="mb-3 border-t pt-2"><p class="font-bold text-gray-900">Diagnoses</p><ul class="ml-2 list-disc list-inside">';foreach($d['diagnosisList'] as $dx) $html.='<li class="text-gray-700 text-sm">'.h($dx).'</li>';$html.='</ul></div>';}
    $html.='<p class="text-xs text-gray-400 border-t pt-2 mt-3">Generated by Arab MedTechAI</p></div>';
    echo json_encode(['html'=>$html]); break;

// ── LAB ANALYZER
case 'lab-analyzer':
    $d=gemini("Analyze lab results: ".($body['text']??'').". Return ONLY JSON: {\"summary\":\"str\",\"abnormalValues\":[{\"test\":\"str\",\"value\":\"str\",\"flag\":\"High|Low|Critical\",\"interpretation\":\"str\",\"normalRange\":\"str\"}],\"overallAssessment\":\"str\",\"recommendations\":[\"str\"],\"urgency\":\"Routine|Urgent|Emergency\",\"trendsAnalysis\":\"str\",\"criticalValues\":[\"str\"],\"organSystemImpact\":\"str\",\"medicationEffectsOnLabs\":[\"str\"],\"confirmatoryTesting\":[\"str\"],\"nutritionalAssessment\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="flex items-center gap-2 mb-4"><p class="text-sm font-semibold text-gray-600">Urgency:</p>'.bdg($d['urgency']??'Routine').'</div>';
    $html.=sec('Overall Assessment','<p class="text-gray-700 text-sm">'.h($d['overallAssessment']??'').'</p>');
    if(!empty($d['abnormalValues'])){$html.='<p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Abnormal Values</p>';foreach($d['abnormalValues'] as $v) $html.='<div class="flex items-start justify-between border border-red-100 bg-red-50 rounded-xl p-3 mb-2"><div><p class="font-semibold text-gray-900 text-sm">'.h($v['test']??'').'</p><p class="text-xs text-gray-500">'.h($v['interpretation']??'').'</p></div><div class="text-right"><span class="font-bold text-red-600">'.h($v['value']??'').'</span><br>'.bdg($v['flag']??'').(!empty($v['normalRange'])?'<span class="text-xs text-gray-400 mt-1 block">NR: '.h($v['normalRange']).'</span>':'').'</div></div>';}
    if(!empty($d['criticalValues'])) $html.=sec('Critical Values',rl($d['criticalValues'],'#dc2626'));
    if(!empty($d['summary'])) $html.=sec('Clinical Summary','<p class="text-gray-700 text-sm">'.h($d['summary']).'</p>');
    if(!empty($d['trendsAnalysis'])) $html.=sec('Trends Analysis','<p class="text-gray-700 text-sm">'.h($d['trendsAnalysis']).'</p>');
    if(!empty($d['organSystemImpact'])) $html.=sec('Organ System Impact','<p class="text-gray-700 text-sm">'.h($d['organSystemImpact']).'</p>');
    if(!empty($d['medicationEffectsOnLabs'])) $html.=sec('Medication Effects on Labs',rl($d['medicationEffectsOnLabs'],'#ea580c'));
    if(!empty($d['confirmatoryTesting'])) $html.=sec('Confirmatory Testing',rl($d['confirmatoryTesting'],'#7c3aed'));
    if(!empty($d['nutritionalAssessment'])) $html.=sec('Nutritional Assessment','<p class="text-gray-700 text-sm">'.h($d['nutritionalAssessment']).'</p>');
    if(!empty($d['recommendations'])) $html.=sec('Recommendations',rl($d['recommendations'],'#2563EB'));
    echo json_encode(['html'=>$html]); break;

// ── IMAGING READER
case 'imaging-reader':
    $mod=$body['modality']??'MRI'; $part=$body['bodyPart']??'';
    $d=gemini("Analyze {$mod} {$part}: ".($body['text']??'').". Return ONLY JSON: {\"studyDescription\":\"str\",\"findings\":[\"str\"],\"impression\":\"str\",\"severity\":\"Normal|Mild|Moderate|Severe|Critical\",\"urgency\":\"Routine|Urgent|Emergency\",\"recommendations\":[\"str\"],\"technique\":\"str\",\"comparisonStudy\":\"str\",\"clinicalIndication\":\"str\",\"anatomyVisualized\":[\"str\"],\"technicalQuality\":\"str\",\"incidentalFindings\":[\"str\"],\"structuredReport\":\"str\",\"clinicalCorrelation\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="flex gap-2 mb-4">'.bdg($d['severity']??'Normal').bdg($d['urgency']??'Routine').'</div>';
    if(!empty($d['clinicalIndication'])) $html.=sec('Indication','<p class="text-gray-700 text-sm">'.h($d['clinicalIndication']).'</p>');
    if(!empty($d['studyDescription'])) $html.=sec('Study','<p class="font-semibold text-gray-900">'.h($d['studyDescription']).'</p>');
    if(!empty($d['technique'])) $html.=sec('Technique','<p class="text-gray-700 text-sm">'.h($d['technique']).'</p>');
    if(!empty($d['comparisonStudy'])) $html.=sec('Comparison','<p class="text-gray-700 text-sm">'.h($d['comparisonStudy']).'</p>');
    if(!empty($d['anatomyVisualized'])) $html.=sec('Anatomy Visualized',rl($d['anatomyVisualized'],'#7c3aed'));
    if(!empty($d['technicalQuality'])) $html.=sec('Technical Quality','<p class="text-gray-700 text-sm">'.h($d['technicalQuality']).'</p>');
    if(!empty($d['findings'])) $html.=sec('Findings',rl($d['findings']));
    if(!empty($d['incidentalFindings'])) $html.=sec('Incidental Findings',rl($d['incidentalFindings'],'#ea580c'));
    if(!empty($d['impression'])) $html.=sec('Impression','<p class="text-gray-700 text-sm font-medium">'.h($d['impression']).'</p>');
    if(!empty($d['clinicalCorrelation'])) $html.=sec('Clinical Correlation','<p class="text-gray-700 text-sm">'.h($d['clinicalCorrelation']).'</p>');
    if(!empty($d['recommendations'])) $html.=sec('Recommendations',rl($d['recommendations'],'#7c3aed'));
    if(!empty($d['structuredReport'])) $html.=sec('Structured Report','<pre class="text-xs text-gray-700 whitespace-pre-wrap font-mono bg-gray-50 rounded-xl p-3">'.h($d['structuredReport']).'</pre>');
    echo json_encode(['html'=>$html]); break;

// ── PATHOLOGY READER
case 'pathology-reader':
    $d=gemini("Pathology report. Specimen: ".($body['specimenType']??'Biopsy').". Report: ".($body['text']??'').". Return ONLY JSON: {\"diagnosis\":\"str\",\"specimenType\":\"str\",\"grossDescription\":\"str\",\"microscopicDescription\":\"str\",\"staging\":{\"t\":\"str\",\"n\":\"str\",\"m\":\"str\"},\"pathologicalStaging\":\"str\",\"grade\":\"str\",\"biomarkers\":[{\"name\":\"str\",\"result\":\"str\",\"interpretation\":\"str\"}],\"immunohistochemistry\":[{\"marker\":\"str\",\"result\":\"str\",\"interpretation\":\"str\"}],\"molecularTesting\":\"str\",\"tumorBurden\":\"str\",\"marginStatus\":\"str\",\"lymphovascularInvasion\":\"str\",\"recommendations\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html=sec('Diagnosis','<p class="text-lg font-black text-gray-900">'.h($d['diagnosis']??'').'</p>');
    if(!empty($d['specimenType'])) $html.=sec('Specimen','<p class="text-gray-700 text-sm">'.h($d['specimenType']).'</p>');
    if(!empty($d['grossDescription'])) $html.=sec('Gross Description','<p class="text-gray-700 text-sm">'.h($d['grossDescription']).'</p>');
    if(!empty($d['microscopicDescription'])) $html.=sec('Microscopic','<p class="text-gray-700 text-sm">'.h($d['microscopicDescription']).'</p>');
    if(!empty($d['staging']['t'])) $html.=sec('TNM Staging','<p class="text-gray-700 text-sm">T: '.h($d['staging']['t']).' | N: '.h($d['staging']['n']).' | M: '.h($d['staging']['m']).'</p>');
    if(!empty($d['pathologicalStaging'])) $html.=sec('Pathological Staging','<p class="text-gray-700 text-sm">'.h($d['pathologicalStaging']).'</p>');
    if(!empty($d['grade'])) $html.=sec('Grade','<p class="text-gray-700 text-sm">'.h($d['grade']).'</p>');
    if(!empty($d['tumorBurden'])) $html.=sec('Tumor Burden','<p class="text-gray-700 text-sm">'.h($d['tumorBurden']).'</p>');
    if(!empty($d['marginStatus'])) $html.=sec('Margin Status','<p class="text-gray-700 text-sm">'.h($d['marginStatus']).'</p>');
    if(!empty($d['lymphovascularInvasion'])) $html.=sec('Lymphovascular Invasion','<p class="text-gray-700 text-sm">'.h($d['lymphovascularInvasion']).'</p>');
    if(!empty($d['immunohistochemistry'])){$html.='<p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">IHC Markers</p>';foreach($d['immunohistochemistry'] as $ihc) $html.='<div class="flex justify-between border-b border-gray-50 py-1.5 text-sm"><span class="text-gray-700">'.h($ihc['marker']??'').'</span><span class="font-semibold">'.h($ihc['result']??'').(!empty($ihc['interpretation'])?' <span class="text-xs text-gray-500">('.h($ihc['interpretation']).')</span>':'').'</span></div>';$html.='<div class="mb-4"></div>';}
    if(!empty($d['biomarkers'])){$html.='<p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Biomarkers</p>';foreach($d['biomarkers'] as $b) $html.='<div class="flex justify-between border-b border-gray-50 py-1.5 text-sm"><span class="text-gray-700">'.h($b['name']??'').'</span><span class="font-semibold">'.h($b['result']??'').'</span></div>';$html.='<div class="mb-4"></div>';}
    if(!empty($d['molecularTesting'])) $html.=sec('Molecular Testing','<p class="text-gray-700 text-sm">'.h($d['molecularTesting']).'</p>');
    if(!empty($d['recommendations'])) $html.=sec('Recommendations',rl($d['recommendations'],'#e11d48'));
    echo json_encode(['html'=>$html]); break;

// ── DISCHARGE SUMMARY
case 'discharge-summary':
    $d=gemini("Discharge summary. Diagnosis: ".($body['diagnosis']??'').". Course: ".($body['course']??'').". Meds: ".($body['medications']??'').". Return ONLY JSON: {\"diagnoses\":{\"primary\":\"str\",\"secondary\":[\"str\"]},\"hospitalCourse\":\"str\",\"procedures\":[\"str\"],\"dischargeMedications\":[{\"name\":\"str\",\"dose\":\"str\",\"frequency\":\"str\",\"duration\":\"str\"}],\"followUpPlan\":[\"str\"],\"patientInstructions\":\"str\",\"admissionDate\":\"str\",\"dischargeDate\":\"str\",\"attendingPhysician\":\"str\",\"dischargeDisposition\":\"str\",\"pendingResults\":[\"str\"],\"conditionAtDischarge\":\"str\",\"functionalStatus\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html=sec('Primary Diagnosis','<p class="text-lg font-black text-gray-900">'.h($d['diagnoses']['primary']??'').'</p>');
    if(!empty($d['diagnoses']['secondary'])) $html.=sec('Secondary Diagnoses',rl($d['diagnoses']['secondary']));
    if(!empty($d['admissionDate']) || !empty($d['dischargeDate'])) $html.=sec('Dates','<p class="text-gray-700 text-sm">Admit: '.h($d['admissionDate']??'—').' | Discharge: '.h($d['dischargeDate']??'—').'</p>');
    if(!empty($d['attendingPhysician'])) $html.=sec('Attending','<p class="text-gray-700 text-sm">'.h($d['attendingPhysician']).'</p>');
    if(!empty($d['dischargeDisposition'])) $html.=sec('Disposition','<p class="text-gray-700 text-sm">'.h($d['dischargeDisposition']).'</p>');
    $html.=sec('Hospital Course','<p class="text-gray-700 text-sm leading-relaxed">'.h($d['hospitalCourse']??'').'</p>');
    if(!empty($d['procedures'])) $html.=sec('Procedures',rl($d['procedures']));
    if(!empty($d['conditionAtDischarge'])) $html.=sec('Condition at Discharge','<p class="text-gray-700 text-sm">'.h($d['conditionAtDischarge']).'</p>');
    if(!empty($d['functionalStatus'])) $html.=sec('Functional Status','<p class="text-gray-700 text-sm">'.h($d['functionalStatus']).'</p>');
    if(!empty($d['dischargeMedications'])){$html.='<p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Discharge Medications</p>';foreach($d['dischargeMedications'] as $m) $html.='<div class="border border-gray-100 rounded-xl p-3 mb-2"><p class="font-semibold text-gray-900 text-sm">'.h($m['name']??'').'</p><p class="text-xs text-gray-500">'.h($m['dose']??'').' — '.h($m['frequency']??'').' × '.h($m['duration']??'').'</p></div>';$html.='<div class="mb-4"></div>';}
    if(!empty($d['pendingResults'])) $html.=sec('Pending Results',rl($d['pendingResults'],'#ea580c'));
    if(!empty($d['followUpPlan'])) $html.=sec('Follow-up',rl($d['followUpPlan'],'#0891b2'));
    if(!empty($d['patientInstructions'])) $html.=sec('Patient Instructions','<p class="text-gray-700 text-sm">'.h($d['patientInstructions']).'</p>');
    echo json_encode(['html'=>$html]); break;

// ── CLINICAL NOTES
case 'clinical-notes':
    $d=gemini("Generate ".($body['noteType']??'SOAP Note')." for ".($body['age']??'')."yr ".($body['gender']??'').". Info: ".($body['info']??'').". Return ONLY JSON: {\"noteType\":\"str\",\"subjective\":\"str\",\"objective\":{\"vitalSigns\":\"str\",\"physicalExam\":\"str\"},\"assessment\":\"str\",\"plan\":\"str\",\"icd10Suggestions\":[\"str\"],\"reviewOfSystems\":\"str\",\"medicationList\":[\"str\"],\"allergies\":[\"str\"],\"pastMedicalHistory\":\"str\",\"familyHistory\":\"str\",\"socialHistory\":\"str\",\"assessmentPlanDetailed\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="border border-gray-200 rounded-xl p-5 font-mono text-sm bg-gray-50"><p class="font-bold text-center mb-4">'.h($d['noteType']??'Clinical Note').'</p>';
    if(!empty($d['subjective'])) $html.='<div class="mb-3"><p class="font-bold">S — Subjective</p><p class="text-gray-700 ml-2">'.h($d['subjective']).'</p></div>';
    if(!empty($d['reviewOfSystems'])) $html.='<div class="mb-3"><p class="font-bold">ROS</p><p class="text-gray-700 ml-2">'.h($d['reviewOfSystems']).'</p></div>';
    if(!empty($d['pastMedicalHistory'])) $html.='<div class="mb-3"><p class="font-bold">PMH</p><p class="text-gray-700 ml-2">'.h($d['pastMedicalHistory']).'</p></div>';
    if(!empty($d['familyHistory'])) $html.='<div class="mb-3"><p class="font-bold">Family History</p><p class="text-gray-700 ml-2">'.h($d['familyHistory']).'</p></div>';
    if(!empty($d['socialHistory'])) $html.='<div class="mb-3"><p class="font-bold">Social History</p><p class="text-gray-700 ml-2">'.h($d['socialHistory']).'</p></div>';
    if(!empty($d['medicationList'])) $html.='<div class="mb-3"><p class="font-bold">Medications</p><ul class="ml-2 list-disc list-inside">';foreach(($d['medicationList']??[]) as $med) $html.='<li class="text-gray-700 text-sm">'.h($med).'</li>';$html.='</ul></div>';
    if(!empty($d['allergies'])) $html.='<div class="mb-3"><p class="font-bold">Allergies</p><ul class="ml-2 list-disc list-inside">';foreach(($d['allergies']??[]) as $a) $html.='<li class="text-red-600 text-sm">'.h($a).'</li>';$html.='</ul></div>';
    if(!empty($d['objective'])) $html.='<div class="mb-3"><p class="font-bold">O — Objective</p><p class="text-gray-700 ml-2"><strong>Vitals:</strong> '.h($d['objective']['vitalSigns']??'').'</p><p class="text-gray-700 ml-2"><strong>Exam:</strong> '.h($d['objective']['physicalExam']??'').'</p></div>';
    if(!empty($d['assessment'])) $html.='<div class="mb-3"><p class="font-bold">A — Assessment</p><p class="text-gray-700 ml-2">'.h($d['assessment']).'</p></div>';
    if(!empty($d['plan'])) $html.='<div class="mb-3"><p class="font-bold">P — Plan</p><p class="text-gray-700 ml-2">'.h($d['plan']).'</p></div>';
    if(!empty($d['assessmentPlanDetailed'])) $html.='<div class="mb-3 border-t pt-2"><p class="font-bold">A/P Detailed</p><p class="text-gray-700 ml-2">'.h($d['assessmentPlanDetailed']).'</p></div>';
    $html.='</div>';
    if(!empty($d['icd10Suggestions'])) $html.='<div class="mt-3"><p class="text-xs text-gray-400 mb-1">ICD-10 suggestions:</p><div class="flex flex-wrap gap-1">'.implode('',array_map(fn($c)=>'<span class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded font-mono">'.h($c).'</span>',$d['icd10Suggestions'])).'</div></div>';
    echo json_encode(['html'=>$html]); break;

// ── MEDICATION SAFETY
case 'medication-safety':
    $d=gemini("Medication safety: ".($body['drug']??'').". Allergies: ".($body['allergies']??'').". Current meds: ".($body['currentMeds']??'').". Return ONLY JSON: {\"overallSafety\":\"Safe|Caution|High Risk\",\"lasaRisk\":\"Yes|No\",\"highAlert\":\"Yes|No\",\"allergyConflict\":\"str or null\",\"interactions\":[{\"drug\":\"str\",\"severity\":\"str\",\"description\":\"str\"}],\"safetyRecommendations\":[\"str\"],\"monitoringParameters\":[\"str\"],\"duplicateTherapy\":[\"str\"],\"dosingErrors\":[\"str\"],\"renalDosingAdjustment\":\"str\",\"hepaticDosingAdjustment\":\"str\",\"pregnancyRiskAssessment\":\"str\",\"geriatricConsiderations\":\"str\",\"pediatricConsiderations\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="flex gap-2 mb-4">'.bdg($d['overallSafety']??'Caution');
    if(($d['highAlert']??'')=='Yes') $html.='<span class="text-xs font-bold bg-red-100 text-red-700 px-2.5 py-1 rounded-full">&#9888; HIGH ALERT</span>';
    if(($d['lasaRisk']??'')=='Yes') $html.='<span class="text-xs font-bold bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full">LASA Risk</span>';
    $html.='</div>';
    if(!empty($d['allergyConflict'])) $html.='<div class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4 text-red-800 text-sm"><strong>&#9888; Allergy Conflict:</strong> '.h($d['allergyConflict']).'</div>';
    if(!empty($d['interactions'])){$html.='<p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Interactions</p>';foreach($d['interactions'] as $ix) $html.='<div class="border border-gray-100 rounded-xl p-3 mb-2"><div class="flex items-center justify-between mb-1"><span class="font-semibold text-sm">'.h($ix['drug']??'').'</span>'.bdg($ix['severity']??'').'</div><p class="text-xs text-gray-500">'.h($ix['description']??'').'</p></div>';$html.='<div class="mb-4"></div>';}
    if(!empty($d['duplicateTherapy'])) $html.=sec('Duplicate Therapy',rl($d['duplicateTherapy'],'#ea580c'));
    if(!empty($d['dosingErrors'])) $html.=sec('Dosing Errors',rl($d['dosingErrors'],'#dc2626'));
    if(!empty($d['renalDosingAdjustment'])) $html.=sec('Renal Adjustment','<p class="text-amber-700 text-sm">'.h($d['renalDosingAdjustment']).'</p>');
    if(!empty($d['hepaticDosingAdjustment'])) $html.=sec('Hepatic Adjustment','<p class="text-amber-700 text-sm">'.h($d['hepaticDosingAdjustment']).'</p>');
    if(!empty($d['pregnancyRiskAssessment'])) $html.=sec('Pregnancy Risk','<p class="text-gray-700 text-sm">'.h($d['pregnancyRiskAssessment']).'</p>');
    if(!empty($d['geriatricConsiderations'])) $html.=sec('Geriatric Considerations','<p class="text-gray-700 text-sm">'.h($d['geriatricConsiderations']).'</p>');
    if(!empty($d['pediatricConsiderations'])) $html.=sec('Pediatric Considerations','<p class="text-gray-700 text-sm">'.h($d['pediatricConsiderations']).'</p>');
    if(!empty($d['safetyRecommendations'])) $html.=sec('Recommendations',rl($d['safetyRecommendations']));
    if(!empty($d['monitoringParameters'])) $html.=sec('Monitoring',rl($d['monitoringParameters'],'#7c3aed'));
    echo json_encode(['html'=>$html]); break;

// ── FORMULARY
case 'formulary':
    $d=gemini("Formulary search: ".($body['query']??'').". Status: ".($body['status']??'').". Route: ".($body['route']??'').". Return ONLY JSON: {\"results\":[{\"name\":\"str\",\"genericName\":\"str\",\"formularyStatus\":\"Formulary|Non-Formulary|Restricted\",\"route\":\"str\",\"therapeuticClass\":\"str\",\"restrictions\":\"str\",\"alternatives\":[\"str\"],\"costInformation\":\"str\",\"copayLevel\":\"str\",\"priorAuthorizationRequired\":\"Yes|No\",\"stepTherapyRequired\":\"Yes|No\",\"quantityLimits\":\"str\",\"formularyTier\":\"str\"}]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $sc=['Formulary'=>'bg-emerald-100 text-emerald-700','Non-Formulary'=>'bg-red-100 text-red-700','Restricted'=>'bg-amber-100 text-amber-700'];
    $html='';
    foreach(($d['results']??[]) as $r){
        $cls=$sc[$r['formularyStatus']??'']??'bg-gray-100 text-gray-600';
        $html.='<div class="border border-gray-100 rounded-xl p-4 mb-3"><div class="flex items-start justify-between mb-2"><div><p class="font-black text-gray-900">'.h($r['name']??'').'</p><p class="text-xs text-gray-500">'.h($r['genericName']??'').'</p></div><span class="text-xs font-bold px-2.5 py-1 rounded-full '.$cls.'">'.h($r['formularyStatus']??'').'</span></div>';
        $html.='<p class="text-xs text-gray-500 mb-1">'.h($r['therapeuticClass']??'').' — '.h($r['route']??'').'</p>';
        if(!empty($r['formularyTier'])) $html.='<p class="text-xs text-gray-500 mb-1"><strong>Tier:</strong> '.h($r['formularyTier']).'</p>';
        if(!empty($r['costInformation'])) $html.='<p class="text-xs text-gray-500 mb-1"><strong>Cost:</strong> '.h($r['costInformation']).'</p>';
        if(!empty($r['copayLevel'])) $html.='<p class="text-xs text-gray-500 mb-1"><strong>Copay:</strong> '.h($r['copayLevel']).'</p>';
        if(!empty($r['priorAuthorizationRequired']) && $r['priorAuthorizationRequired']=='Yes') $html.='<span class="text-xs bg-amber-50 text-amber-700 px-2 py-0.5 rounded mr-1">PA Required</span>';
        if(!empty($r['stepTherapyRequired']) && $r['stepTherapyRequired']=='Yes') $html.='<span class="text-xs bg-amber-50 text-amber-700 px-2 py-0.5 rounded mr-1">Step Therapy</span>';
        if(!empty($r['quantityLimits'])) $html.='<span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded mr-1">QL: '.h($r['quantityLimits']).'</span>';
        if(!empty($r['restrictions'])) $html.='<p class="text-xs text-amber-700 bg-amber-50 rounded px-2 py-1 mt-1">'.h($r['restrictions']).'</p>';
        if(!empty($r['alternatives'])) $html.='<p class="text-xs text-gray-400 mt-2">Alternatives: '.h(implode(', ',$r['alternatives'])).'</p>';
        $html.='</div>';
    }
    echo json_encode(['html'=>$html?:'<p class="text-gray-500">No results found.</p>']); break;

// ── IV COMPATIBILITY
case 'iv-compatibility':
    $drugs=implode(', ',array_filter([$body['drugA']??'',$body['drugB']??'',$body['drugC']??'']));
    $d=gemini("IV compatibility for: {$drugs} in ".($body['diluent']??'Normal Saline').". Return ONLY JSON: {\"overallCompatibility\":\"Compatible|Incompatible|Conditionally Compatible\",\"pairs\":[{\"drug1\":\"str\",\"drug2\":\"str\",\"compatibility\":\"Compatible|Incompatible|Conditionally Compatible\",\"evidence\":\"str\",\"notes\":\"str\"}],\"recommendation\":\"str\",\"alternativeApproach\":\"str\",\"ySiteCompatibility\":\"str\",\"syringeCompatibility\":\"str\",\"stabilityData\":\"str\",\"lightSensitivity\":\"str\",\"concentrationDependentInfo\":\"str\",\"administrationGuidelines\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $cc=['Compatible'=>'bg-emerald-100 text-emerald-700','Incompatible'=>'bg-red-100 text-red-700','Conditionally Compatible'=>'bg-amber-100 text-amber-700'];
    $ov=$d['overallCompatibility']??'Unknown'; $ocl=$cc[$ov]??'bg-gray-100 text-gray-600';
    $html='<div class="text-center mb-4"><span class="text-xs font-bold px-4 py-2 rounded-full '.$ocl.'">'.h($ov).'</span></div>';
    foreach(($d['pairs']??[]) as $p){$pcl=$cc[$p['compatibility']??'']??'bg-gray-100 text-gray-600';$html.='<div class="border border-gray-100 rounded-xl p-4 mb-3"><div class="flex items-center justify-between mb-2"><span class="font-semibold text-sm text-gray-900">'.h($p['drug1']??'').' + '.h($p['drug2']??'').'</span><span class="text-xs font-bold px-2.5 py-1 rounded-full '.$pcl.'">'.h($p['compatibility']??'').'</span></div><p class="text-xs text-gray-500">'.h($p['evidence']??'').'</p>';if(!empty($p['notes'])) $html.='<p class="text-xs text-blue-700 bg-blue-50 rounded px-2 py-1 mt-1">'.h($p['notes']).'</p>';$html.='</div>';}
    if(!empty($d['ySiteCompatibility'])) $html.=sec('Y-Site Compatibility','<p class="text-gray-700 text-sm">'.h($d['ySiteCompatibility']).'</p>');
    if(!empty($d['syringeCompatibility'])) $html.=sec('Syringe Compatibility','<p class="text-gray-700 text-sm">'.h($d['syringeCompatibility']).'</p>');
    if(!empty($d['stabilityData'])) $html.=sec('Stability Data','<p class="text-gray-700 text-sm">'.h($d['stabilityData']).'</p>');
    if(!empty($d['lightSensitivity'])) $html.=sec('Light Sensitivity','<p class="text-gray-700 text-sm">'.h($d['lightSensitivity']).'</p>');
    if(!empty($d['concentrationDependentInfo'])) $html.=sec('Concentration-Dependent','<p class="text-gray-700 text-sm">'.h($d['concentrationDependentInfo']).'</p>');
    if(!empty($d['administrationGuidelines'])) $html.=sec('Administration Guidelines',rl($d['administrationGuidelines'],'#2563EB'));
    if(!empty($d['recommendation'])) $html.=sec('Recommendation','<p class="text-gray-700 text-sm">'.h($d['recommendation']).'</p>');
    if(!empty($d['alternativeApproach'])) $html.='<div class="bg-blue-50 rounded-xl p-3 text-sm text-blue-800">'.h($d['alternativeApproach']).'</div>';
    echo json_encode(['html'=>$html]); break;

// ── CLINICAL PATHWAYS
case 'clinical-pathways':
    $d=gemini("Clinical pathway for: ".($body['condition']??'').". Return ONLY JSON: {\"condition\":\"str\",\"overview\":\"str\",\"initialAssessment\":[\"str\"],\"diagnosticWorkup\":[\"str\"],\"treatmentSteps\":[{\"step\":1,\"action\":\"str\",\"timeframe\":\"str\",\"notes\":\"str\"}],\"monitoring\":[\"str\"],\"inclusionCriteria\":[\"str\"],\"exclusionCriteria\":[\"str\"],\"outcomeMeasures\":[\"str\"],\"dischargeCriteria\":[\"str\"],\"followUpSchedule\":\"str\",\"referralCriteria\":[\"str\"],\"patientResources\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<h3 class="font-black text-gray-900 text-xl mb-3">'.h($d['condition']??'').'</h3>';
    if(!empty($d['overview'])) $html.=sec('Overview','<p class="text-gray-700 text-sm leading-relaxed">'.h($d['overview']).'</p>');
    if(!empty($d['inclusionCriteria'])) $html.=sec('Inclusion Criteria',rl($d['inclusionCriteria'],'#059669'));
    if(!empty($d['exclusionCriteria'])) $html.=sec('Exclusion Criteria',rl($d['exclusionCriteria'],'#dc2626'));
    if(!empty($d['initialAssessment'])) $html.=sec('Initial Assessment',rl($d['initialAssessment']));
    if(!empty($d['diagnosticWorkup'])) $html.=sec('Diagnostic Workup',rl($d['diagnosticWorkup'],'#7c3aed'));
    if(!empty($d['treatmentSteps'])){$html.='<p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Treatment Steps</p>';foreach($d['treatmentSteps'] as $step) $html.='<div class="flex gap-3 mb-3"><div class="flex-shrink-0 w-7 h-7 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-bold">'.h((string)($step['step']??'')).'</div><div><p class="font-semibold text-gray-900 text-sm">'.h($step['action']??'').'</p><p class="text-xs text-gray-400">'.h($step['timeframe']??'').'</p>'.(!empty($step['notes'])?'<p class="text-xs text-gray-500 mt-0.5">'.h($step['notes']).'</p>':'').'</div></div>';}
    if(!empty($d['outcomeMeasures'])) $html.=sec('Outcome Measures',rl($d['outcomeMeasures'],'#0891b2'));
    if(!empty($d['dischargeCriteria'])) $html.=sec('Discharge Criteria',rl($d['dischargeCriteria'],'#2563EB'));
    if(!empty($d['followUpSchedule'])) $html.=sec('Follow-up Schedule','<p class="text-gray-700 text-sm">'.h($d['followUpSchedule']).'</p>');
    if(!empty($d['referralCriteria'])) $html.=sec('Referral Criteria',rl($d['referralCriteria'],'#ea580c'));
    if(!empty($d['monitoring'])) $html.=sec('Monitoring',rl($d['monitoring'],'#0891b2'));
    if(!empty($d['patientResources'])) $html.=sec('Patient Resources',rl($d['patientResources'],'#059669'));
    echo json_encode(['html'=>$html]); break;

// ── CLINICAL CALCULATORS (AI-backed ones)
case 'clinical-calculators':
    $d=gemini("Calculate ".($body['calculator']??'')." score. Data: ".($body['data']??'').". Return ONLY JSON: {\"calculator\":\"str\",\"result\":\"str\",\"score\":\"str\",\"interpretation\":\"str\",\"riskCategory\":\"str\",\"recommendations\":[\"str\"],\"calculationMethodology\":\"str\",\"variablesUsed\":[\"str\"],\"clinicalApplication\":\"str\",\"evidenceLevel\":\"str\",\"validationStudies\":\"str\",\"caveats\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<p class="text-3xl font-black text-orange-500 mb-1">'.h($d['result']??$d['score']??'').'</p>';
    if(!empty($d['riskCategory'])) $html.='<div class="mb-3">'.bdg($d['riskCategory']).'</div>';
    $html.=sec('Interpretation','<p class="text-gray-700 text-sm">'.h($d['interpretation']??'').'</p>');
    if(!empty($d['calculationMethodology'])) $html.=sec('Methodology','<p class="text-gray-700 text-sm">'.h($d['calculationMethodology']).'</p>');
    if(!empty($d['variablesUsed'])) $html.=sec('Variables',rl($d['variablesUsed'],'#7c3aed'));
    if(!empty($d['clinicalApplication'])) $html.=sec('Clinical Application','<p class="text-gray-700 text-sm">'.h($d['clinicalApplication']).'</p>');
    if(!empty($d['evidenceLevel'])) $html.=sec('Evidence Level','<p class="text-gray-700 text-sm">'.h($d['evidenceLevel']).'</p>');
    if(!empty($d['validationStudies'])) $html.=sec('Validation Studies','<p class="text-gray-700 text-sm">'.h($d['validationStudies']).'</p>');
    if(!empty($d['caveats'])) $html.=sec('Caveats',rl($d['caveats'],'#ea580c'));
    if(!empty($d['recommendations'])) $html.=sec('Recommendations',rl($d['recommendations'],'#ea580c'));
    echo json_encode(['html'=>$html]); break;

// ── STEWARDSHIP
case 'stewardship':
    $d=gemini("AMS review. Antibiotic: ".($body['antibiotic']??'').". Indication: ".($body['indication']??'').". Culture: ".($body['culture']??'').". Day ".($body['dayOfTherapy']??'')." of therapy. Return ONLY JSON: {\"appropriateness\":\"Appropriate|Inappropriate|Needs Review\",\"recommendation\":\"Continue|De-escalate|Discontinue|Modify\",\"justification\":\"str\",\"deEscalationOption\":\"str\",\"suggestedDuration\":\"str\",\"monitoringParameters\":[\"str\"],\"resistanceRisk\":\"Low|Moderate|High\",\"durationAssessment\":\"str\",\"cultureResults\":\"str\",\"sensitivityPatterns\":\"str\",\"ivToOralConversion\":\"str\",\"renalDosingAdjustment\":\"str\",\"allergyAssessment\":\"str\",\"stewardshipCategory\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="flex gap-2 mb-4">'.bdg($d['appropriateness']??'Needs Review').bdg($d['recommendation']??'').'</div>';
    $html.=sec('Justification','<p class="text-gray-700 text-sm leading-relaxed">'.h($d['justification']??'').'</p>');
    if(!empty($d['stewardshipCategory'])) $html.=sec('AMS Category','<p class="text-gray-700 text-sm">'.h($d['stewardshipCategory']).'</p>');
    if(!empty($d['cultureResults'])) $html.=sec('Culture Results','<p class="text-gray-700 text-sm">'.h($d['cultureResults']).'</p>');
    if(!empty($d['sensitivityPatterns'])) $html.=sec('Sensitivity Patterns','<p class="text-gray-700 text-sm">'.h($d['sensitivityPatterns']).'</p>');
    if(!empty($d['durationAssessment'])) $html.=sec('Duration Assessment','<p class="text-gray-700 text-sm">'.h($d['durationAssessment']).'</p>');
    if(!empty($d['deEscalationOption'])) $html.='<div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 mb-4 text-emerald-800 text-sm"><strong>De-escalation:</strong> '.h($d['deEscalationOption']).'</div>';
    if(!empty($d['ivToOralConversion'])) $html.=sec('IV-to-Oral Conversion','<p class="text-emerald-700 text-sm">'.h($d['ivToOralConversion']).'</p>');
    if(!empty($d['renalDosingAdjustment'])) $html.=sec('Renal Adjustment','<p class="text-amber-700 text-sm">'.h($d['renalDosingAdjustment']).'</p>');
    if(!empty($d['suggestedDuration'])) $html.=sec('Duration','<p class="text-gray-700 text-sm">'.h($d['suggestedDuration']).'</p>');
    if(!empty($d['allergyAssessment'])) $html.=sec('Allergy Assessment','<p class="text-gray-700 text-sm">'.h($d['allergyAssessment']).'</p>');
    if(!empty($d['monitoringParameters'])) $html.=sec('Monitoring',rl($d['monitoringParameters'],'#4338ca'));
    $html.='<div class="flex items-center gap-2 mt-2"><p class="text-xs text-gray-500">Resistance Risk:</p>'.bdg($d['resistanceRisk']??'Low').'</div>';
    echo json_encode(['html'=>$html]); break;

default:
    http_response_code(404);
    echo json_encode(['error'=>'Unknown tool: '.$tool]);
    break;
}
