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
    $d = gemini("Explain drug \"{$drug}\". Return ONLY JSON: {\"genericName\":\"str\",\"brandNames\":[\"str\"],\"drugClass\":\"str\",\"mechanismOfAction\":\"str\",\"indications\":[\"str\"],\"dosing\":\"str\",\"commonSideEffects\":[\"str\"],\"clinicalWarnings\":[\"str\"],\"contraindications\":[\"str\"],\"pregnancyCategory\":\"str\",\"patientSummary\":\"2-sentence patient explanation\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html=sec('Generic Name','<p class="font-bold text-gray-900 text-lg">'.h($d['genericName']??$drug).'</p>');
    if(!empty($d['brandNames'])) $html.=sec('Brand Names','<p class="text-gray-700 text-sm">'.h(implode(', ',$d['brandNames'])).'</p>');
    if(!empty($d['drugClass'])) $html.=sec('Drug Class','<p class="text-gray-700 text-sm">'.h($d['drugClass']).'</p>');
    if(!empty($d['mechanismOfAction'])) $html.=sec('Mechanism of Action','<p class="text-gray-700 text-sm leading-relaxed">'.h($d['mechanismOfAction']).'</p>');
    if(!empty($d['indications'])) $html.=sec('Indications',rl($d['indications']));
    if(!empty($d['dosing'])) $html.=sec('Dosing','<p class="text-gray-700 text-sm">'.h($d['dosing']).'</p>');
    if(!empty($d['commonSideEffects'])) $html.=sec('Side Effects',rl($d['commonSideEffects'],'#ea580c'));
    if(!empty($d['clinicalWarnings'])) $html.=sec('Warnings',rl($d['clinicalWarnings'],'#dc2626'));
    if(!empty($d['pregnancyCategory'])) $html.=sec('Pregnancy Category','<p class="text-gray-700 text-sm">'.h($d['pregnancyCategory']).'</p>');
    if(!empty($d['patientSummary'])) $html.='<div class="mt-4 bg-blue-50 rounded-xl p-3 text-sm text-blue-800"><strong>Patient Summary:</strong> '.h($d['patientSummary']).'</div>';
    echo json_encode(['html'=>$html]); break;

// ── INTERACTION CHECKER
case 'interaction-checker':
    $drugs=array_filter(array_map('trim',$body['drugs']??[]));
    if(count($drugs)<2){echo json_encode(['html'=>'<p class="text-red-500">Enter at least 2 drugs.</p>']);exit;}
    $dl=implode(', ',$drugs);
    $d=gemini("Drug interactions for: {$dl}. Return ONLY JSON: {\"interactions\":[{\"drugs\":[\"A\",\"B\"],\"severity\":\"high|moderate|low\",\"description\":\"str\",\"management\":\"str\"}],\"overallRisk\":\"high|moderate|low\",\"summary\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="flex items-center gap-2 mb-4"><p class="text-sm font-semibold text-gray-600">Overall Risk:</p>'.bdg($d['overallRisk']??'low').'</div>';
    if(!empty($d['summary'])) $html.=sec('Summary','<p class="text-gray-700 text-sm">'.h($d['summary']).'</p>');
    foreach(($d['interactions']??[]) as $ix){
        $html.='<div class="border border-gray-100 rounded-xl p-4 mb-3"><div class="flex items-center gap-2 mb-2"><span class="font-semibold text-gray-900 text-sm">'.h(implode(' + ',$ix['drugs']??[])).'</span>'.bdg($ix['severity']??'').'</div><p class="text-gray-600 text-sm mb-1">'.h($ix['description']??'').'</p>';
        if(!empty($ix['management'])) $html.='<p class="text-xs text-blue-700 bg-blue-50 rounded-lg p-2 mt-1"><strong>Management:</strong> '.h($ix['management']).'</p>';
        $html.='</div>';
    }
    echo json_encode(['html'=>$html]); break;

// ── DOSE CALCULATOR
case 'dose-calculator':
    $d=gemini("Dose for: ".($body['drug']??'').", weight ".($body['weight']??'')."kg, age ".($body['age']??'').", indication: ".($body['indication']??'').", renal: ".($body['renal']??'Normal').". Return ONLY JSON: {\"recommendedDose\":\"str\",\"frequency\":\"str\",\"route\":\"str\",\"duration\":\"str\",\"renalAdjustment\":\"str\",\"maxDose\":\"str\",\"warnings\":[\"str\"],\"notes\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html=sec('Recommended Dose','<p class="text-2xl font-black text-emerald-700">'.h($d['recommendedDose']??'').'</p>');
    $html.=sec('Frequency','<p class="text-gray-700 text-sm">'.h($d['frequency']??'').'</p>');
    $html.=sec('Route','<p class="text-gray-700 text-sm">'.h($d['route']??'').'</p>');
    if(!empty($d['renalAdjustment'])) $html.=sec('Renal Adjustment','<p class="text-amber-700 text-sm">'.h($d['renalAdjustment']).'</p>');
    if(!empty($d['maxDose'])) $html.=sec('Max Dose','<p class="text-gray-700 text-sm">'.h($d['maxDose']).'</p>');
    if(!empty($d['warnings'])) $html.=sec('Warnings',rl($d['warnings'],'#dc2626'));
    echo json_encode(['html'=>$html]); break;

// ── PREGNANCY SAFETY
case 'pregnancy-safety':
    $d=gemini("Pregnancy safety for: ".($body['drug']??'').", trimester: ".($body['trimester']??'1').". Return ONLY JSON: {\"fdaCategory\":\"A|B|C|D|X\",\"safety\":\"Safe|Caution|Avoid\",\"risk\":\"str\",\"trimesterSpecific\":\"str\",\"lactationSafety\":\"str\",\"alternatives\":[\"str\"],\"recommendation\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $cats=['A'=>'text-emerald-700','B'=>'text-emerald-600','C'=>'text-amber-600','D'=>'text-red-600','X'=>'text-red-700'];
    $cat=$d['fdaCategory']??'N'; $cc=$cats[$cat]??'text-gray-700';
    $html='<div class="flex items-center gap-4 mb-4"><div class="text-center"><p class="text-xs text-gray-400 mb-1">FDA</p><p class="text-4xl font-black '.$cc.'">'.$cat.'</p></div><div class="flex-1">'.bdg($d['safety']??'').'<p class="text-gray-700 text-sm mt-2">'.h($d['risk']??'').'</p></div></div>';
    if(!empty($d['trimesterSpecific'])) $html.=sec('Trimester Notes','<p class="text-gray-700 text-sm">'.h($d['trimesterSpecific']).'</p>');
    if(!empty($d['lactationSafety'])) $html.=sec('Lactation','<p class="text-gray-700 text-sm">'.h($d['lactationSafety']).'</p>');
    if(!empty($d['alternatives'])) $html.=sec('Alternatives',rl($d['alternatives']));
    if(!empty($d['recommendation'])) $html.='<div class="bg-blue-50 rounded-xl p-3 text-sm text-blue-800 mt-2">'.h($d['recommendation']).'</div>';
    echo json_encode(['html'=>$html]); break;

// ── G6PD CHECKER
case 'g6pd-checker':
    $drug=$body['drug']??'';
    $d=gemini("G6PD safety for: {$drug}. Return ONLY JSON: {\"riskLevel\":\"Safe|Low Risk|Moderate Risk|High Risk|Contraindicated\",\"classification\":\"str\",\"description\":\"str\",\"mechanism\":\"str\",\"recommendation\":\"str\",\"alternatives\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="mb-4">'.bdg($d['riskLevel']??'Unknown').'</div>';
    $html.=sec('Classification','<p class="text-gray-700 text-sm font-semibold">'.h($d['classification']??'').'</p>');
    $html.=sec('Description','<p class="text-gray-700 text-sm">'.h($d['description']??'').'</p>');
    if(!empty($d['mechanism'])) $html.=sec('Mechanism','<p class="text-gray-700 text-sm">'.h($d['mechanism']).'</p>');
    $html.=sec('Recommendation','<p class="text-gray-700 text-sm font-medium">'.h($d['recommendation']??'').'</p>');
    if(!empty($d['alternatives'])) $html.=sec('Alternatives',rl($d['alternatives']));
    echo json_encode(['html'=>$html]); break;

// ── DRUG COMPARISON
case 'drug-comparison':
    $d=gemini("Compare drugs: ".($body['drugA']??'')." vs ".($body['drugB']??'').". Return ONLY JSON: {\"summary\":\"str\",\"comparison\":{\"efficacy\":{\"winner\":\"str\",\"reasoning\":\"str\"},\"safety\":{\"winner\":\"str\",\"reasoning\":\"str\"},\"cost\":{\"winner\":\"str\",\"reasoning\":\"str\"},\"convenience\":{\"winner\":\"str\",\"reasoning\":\"str\"}},\"considerations\":[\"str\"],\"recommendation\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html=sec('Summary','<p class="text-gray-700 text-sm">'.h($d['summary']??'').'</p>');
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
    $d=gemini("Clinical decision support. Symptoms: ".($body['symptoms']??'').", History: ".($body['hx']??'').". Return ONLY JSON: {\"assessment\":\"str\",\"differentialDiagnosis\":[{\"condition\":\"str\",\"probability\":\"High|Medium|Low\"}],\"recommendedWorkup\":[\"str\"],\"managementPlan\":[\"str\"],\"urgency\":\"Emergency|Urgent|Routine\",\"referral\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="flex items-center gap-2 mb-4"><p class="text-sm font-semibold text-gray-600">Urgency:</p>'.bdg($d['urgency']??'routine').'</div>';
    $html.=sec('Assessment','<p class="text-gray-700 text-sm leading-relaxed">'.h($d['assessment']??'').'</p>');
    if(!empty($d['differentialDiagnosis'])){
        $html.='<p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Differential Diagnosis</p>';
        foreach($d['differentialDiagnosis'] as $dd) $html.='<div class="flex items-center justify-between border-b border-gray-50 py-1.5"><span class="text-sm text-gray-700">'.h($dd['condition']??'').'</span>'.bdg($dd['probability']??'').'</div>';
        $html.='<div class="mb-4"></div>';
    }
    if(!empty($d['recommendedWorkup'])) $html.=sec('Workup',rl($d['recommendedWorkup'],'#2563EB'));
    if(!empty($d['managementPlan'])) $html.=sec('Management',rl($d['managementPlan']));
    if(!empty($d['referral'])) $html.=sec('Referral','<p class="text-gray-700 text-sm">'.h($d['referral']).'</p>');
    echo json_encode(['html'=>$html]); break;

// ── DIAGNOSTIC CHECK
case 'diagnostic-check':
    $d=gemini("Diagnostic check. Patient: ".($body['age']??'').". Symptoms: ".($body['symptoms']??'').". Vitals: ".($body['vitals']??'').". Return ONLY JSON: {\"potentialConditions\":[{\"name\":\"str\",\"probability\":\"High|Medium|Low\",\"reasoning\":\"str\"}],\"recommendedTests\":[\"str\"],\"redFlags\":[\"str\"],\"nextSteps\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='';
    if(!empty($d['redFlags'])) $html.='<div class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4">'.sec('&#9888; Red Flags',rl($d['redFlags'],'#dc2626')).'</div>';
    $html.='<p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Potential Conditions</p>';
    foreach(($d['potentialConditions']??[]) as $c) $html.='<div class="border border-gray-100 rounded-xl p-3 mb-2"><div class="flex items-center justify-between mb-1"><span class="font-semibold text-gray-900 text-sm">'.h($c['name']??'').'</span>'.bdg($c['probability']??'').'</div><p class="text-xs text-gray-500">'.h($c['reasoning']??'').'</p></div>';
    if(!empty($d['recommendedTests'])) $html.=sec('Tests',rl($d['recommendedTests'],'#2563EB'));
    if(!empty($d['nextSteps'])) $html.=sec('Next Steps','<p class="text-gray-700 text-sm">'.h($d['nextSteps']).'</p>');
    echo json_encode(['html'=>$html]); break;

// ── SYMPTOM CHECKER
case 'symptom-checker':
    $d=gemini("Symptom checker triage. Patient: ".($body['age']??'')."yr ".($body['gender']??'').". Symptoms: ".($body['symptoms']??'').". Return ONLY JSON: {\"triageLevel\":\"emergency|urgent|routine|self-care\",\"summary\":\"str\",\"potentialCauses\":[\"str\"],\"careAdvice\":[\"str\"],\"whenToSeekCare\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="flex items-center gap-2 mb-4"><p class="text-sm font-semibold text-gray-600">Triage Level:</p>'.bdg($d['triageLevel']??'routine').'</div>';
    $html.=sec('Summary','<p class="text-gray-700 text-sm">'.h($d['summary']??'').'</p>');
    if(!empty($d['potentialCauses'])) $html.=sec('Potential Causes',rl($d['potentialCauses']));
    if(!empty($d['careAdvice'])) $html.=sec('Care Advice',rl($d['careAdvice'],'#2563EB'));
    if(!empty($d['whenToSeekCare'])) $html.='<div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-sm text-amber-800 mt-2"><strong>When to seek care:</strong> '.h($d['whenToSeekCare']).'</div>';
    echo json_encode(['html'=>$html]); break;

// ── ICD-10 LOOKUP
case 'icd10-lookup':
    $d=gemini("ICD-10 codes for: \"".($body['diagnosis']??'')."\" context: ".($body['context']??'').". Return ONLY JSON: {\"mappings\":[{\"primaryCode\":\"str\",\"description\":\"str\",\"confidence\":0.9,\"reasoning\":\"str\",\"secondaryCodes\":[{\"code\":\"str\",\"description\":\"str\"}]}]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='';
    foreach(($d['mappings']??[]) as $m){
        $conf=round(($m['confidence']??0)*100);
        $html.='<div class="border border-gray-100 rounded-xl p-4 mb-3"><div class="flex items-start justify-between mb-2"><div><span class="font-black text-blue-700 text-xl">'.h($m['primaryCode']??'').'</span><span class="text-gray-500 text-sm ml-2">'.h($m['description']??'').'</span></div><span class="text-xs bg-blue-50 text-blue-600 px-2.5 py-1 rounded-full font-bold">'.$conf.'% match</span></div>';
        $html.='<p class="text-xs text-gray-500 mb-2">'.h($m['reasoning']??'').'</p>';
        if(!empty($m['secondaryCodes'])){$html.='<div class="border-t border-gray-50 pt-2 mt-2"><p class="text-xs text-gray-400 mb-1">Secondary codes:</p>';foreach($m['secondaryCodes'] as $sc) $html.='<span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded mr-1">'.h($sc['code']).' — '.h($sc['description']).'</span>';$html.='</div>';}
        $html.='</div>';
    }
    echo json_encode(['html'=>$html]); break;

// ── SMART REPORT OIC
case 'smart-report-oic':
    $txt=$body['text']??''; $typ=$body['type']??'Radiology'; $mod=$body['modality']??'';
    $d=gemini("Analyze {$typ} ({$mod}): \"{$txt}\". Return ONLY JSON: {\"studyType\":\"str\",\"clinicalFindings\":[\"str\"],\"impression\":\"str\",\"recommendations\":[\"str\"],\"severity\":\"Normal|Mild|Moderate|Severe|Critical\",\"urgency\":\"Routine|Urgent|Emergency\",\"potentialICD10\":[{\"code\":\"str\",\"description\":\"str\"}],\"followUpSuggestions\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="flex gap-2 mb-4">'.bdg($d['severity']??'Normal').bdg($d['urgency']??'Routine').'</div>';
    if(!empty($d['studyType'])) $html.=sec('Study','<p class="font-semibold text-gray-900">'.h($d['studyType']).'</p>');
    if(!empty($d['clinicalFindings'])) $html.=sec('Findings',rl($d['clinicalFindings']));
    if(!empty($d['impression'])) $html.=sec('Impression','<p class="text-gray-700 text-sm font-medium leading-relaxed">'.h($d['impression']).'</p>');
    if(!empty($d['recommendations'])) $html.=sec('Recommendations',rl($d['recommendations'],'#2563EB'));
    if(!empty($d['potentialICD10'])){$html.='<p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">ICD-10</p><div class="flex flex-wrap gap-1.5 mb-4">';foreach($d['potentialICD10'] as $c) $html.='<span class="text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded font-mono">'.h($c['code']).' — '.h($c['description']).'</span>';$html.='</div>';}
    if(!empty($d['followUpSuggestions'])) $html.=sec('Follow-up',rl($d['followUpSuggestions'],'#7c3aed'));
    echo json_encode(['html'=>$html]); break;

// ── REPORT COMPOSER
case 'report-composer':
    $d=gemini("Generate ".($body['type']??'Progress Note')." for patient: ".($body['patient']??'').". Chief complaint: ".($body['chiefComplaint']??'').". Findings: ".($body['findings']??'').". Return ONLY JSON: {\"reportTitle\":\"str\",\"subjective\":\"str\",\"objective\":\"str\",\"assessment\":\"str\",\"plan\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="font-mono text-sm border border-gray-200 rounded-xl p-5 bg-gray-50"><p class="font-bold text-center text-base mb-3">'.h($d['reportTitle']??'Medical Report').'</p><p class="text-gray-400 text-xs text-center mb-4">'.date('Y-m-d').'</p>';
    foreach(['subjective'=>'S — Subjective','objective'=>'O — Objective','assessment'=>'A — Assessment','plan'=>'P — Plan'] as $k=>$l) if(!empty($d[$k])) $html.='<div class="mb-3"><p class="font-bold text-gray-900">'.$l.'</p><p class="text-gray-700 ml-2">'.h($d[$k]).'</p></div>';
    $html.='<p class="text-xs text-gray-400 border-t pt-2 mt-3">Generated by Arab MedTechAI</p></div>';
    echo json_encode(['html'=>$html]); break;

// ── LAB ANALYZER
case 'lab-analyzer':
    $d=gemini("Analyze lab results: ".($body['text']??'').". Return ONLY JSON: {\"summary\":\"str\",\"abnormalValues\":[{\"test\":\"str\",\"value\":\"str\",\"flag\":\"High|Low|Critical\",\"interpretation\":\"str\"}],\"overallAssessment\":\"str\",\"recommendations\":[\"str\"],\"urgency\":\"Routine|Urgent|Emergency\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="flex items-center gap-2 mb-4"><p class="text-sm font-semibold text-gray-600">Urgency:</p>'.bdg($d['urgency']??'Routine').'</div>';
    $html.=sec('Overall Assessment','<p class="text-gray-700 text-sm">'.h($d['overallAssessment']??'').'</p>');
    if(!empty($d['abnormalValues'])){$html.='<p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Abnormal Values</p>';foreach($d['abnormalValues'] as $v) $html.='<div class="flex items-start justify-between border border-red-100 bg-red-50 rounded-xl p-3 mb-2"><div><p class="font-semibold text-gray-900 text-sm">'.h($v['test']??'').'</p><p class="text-xs text-gray-500">'.h($v['interpretation']??'').'</p></div><div class="text-right"><span class="font-bold text-red-600">'.h($v['value']??'').'</span><br>'.bdg($v['flag']??'').'</div></div>';}
    if(!empty($d['summary'])) $html.=sec('Clinical Summary','<p class="text-gray-700 text-sm">'.h($d['summary']).'</p>');
    if(!empty($d['recommendations'])) $html.=sec('Recommendations',rl($d['recommendations'],'#2563EB'));
    echo json_encode(['html'=>$html]); break;

// ── IMAGING READER
case 'imaging-reader':
    $mod=$body['modality']??'MRI'; $part=$body['bodyPart']??'';
    $d=gemini("Analyze {$mod} {$part}: ".($body['text']??'').". Return ONLY JSON: {\"studyDescription\":\"str\",\"findings\":[\"str\"],\"impression\":\"str\",\"severity\":\"Normal|Mild|Moderate|Severe|Critical\",\"urgency\":\"Routine|Urgent|Emergency\",\"recommendations\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="flex gap-2 mb-4">'.bdg($d['severity']??'Normal').bdg($d['urgency']??'Routine').'</div>';
    if(!empty($d['studyDescription'])) $html.=sec('Study','<p class="font-semibold text-gray-900">'.h($d['studyDescription']).'</p>');
    if(!empty($d['findings'])) $html.=sec('Findings',rl($d['findings']));
    if(!empty($d['impression'])) $html.=sec('Impression','<p class="text-gray-700 text-sm font-medium">'.h($d['impression']).'</p>');
    if(!empty($d['recommendations'])) $html.=sec('Recommendations',rl($d['recommendations'],'#7c3aed'));
    echo json_encode(['html'=>$html]); break;

// ── PATHOLOGY READER
case 'pathology-reader':
    $d=gemini("Pathology report. Specimen: ".($body['specimenType']??'Biopsy').". Report: ".($body['text']??'').". Return ONLY JSON: {\"diagnosis\":\"str\",\"specimenType\":\"str\",\"microscopicDescription\":\"str\",\"staging\":{\"t\":\"str\",\"n\":\"str\",\"m\":\"str\"},\"grade\":\"str\",\"biomarkers\":[{\"name\":\"str\",\"result\":\"str\",\"interpretation\":\"str\"}],\"recommendations\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html=sec('Diagnosis','<p class="text-lg font-black text-gray-900">'.h($d['diagnosis']??'').'</p>');
    if(!empty($d['staging']['t'])) $html.=sec('Staging (TNM)','<p class="text-gray-700 text-sm">T: '.h($d['staging']['t']).' | N: '.h($d['staging']['n']).' | M: '.h($d['staging']['m']).'</p>');
    if(!empty($d['grade'])) $html.=sec('Grade','<p class="text-gray-700 text-sm">'.h($d['grade']).'</p>');
    if(!empty($d['microscopicDescription'])) $html.=sec('Microscopic','<p class="text-gray-700 text-sm">'.h($d['microscopicDescription']).'</p>');
    if(!empty($d['biomarkers'])){$html.='<p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Biomarkers</p>';foreach($d['biomarkers'] as $b) $html.='<div class="flex justify-between border-b border-gray-50 py-1.5 text-sm"><span class="text-gray-700">'.h($b['name']??'').'</span><span class="font-semibold">'.h($b['result']??'').'</span></div>';$html.='<div class="mb-4"></div>';}
    if(!empty($d['recommendations'])) $html.=sec('Recommendations',rl($d['recommendations'],'#e11d48'));
    echo json_encode(['html'=>$html]); break;

// ── DISCHARGE SUMMARY
case 'discharge-summary':
    $d=gemini("Discharge summary. Diagnosis: ".($body['diagnosis']??'').". Course: ".($body['course']??'').". Meds: ".($body['medications']??'').". Return ONLY JSON: {\"diagnoses\":{\"primary\":\"str\",\"secondary\":[\"str\"]},\"hospitalCourse\":\"str\",\"procedures\":[\"str\"],\"dischargeMedications\":[{\"name\":\"str\",\"dose\":\"str\",\"frequency\":\"str\",\"duration\":\"str\"}],\"followUpPlan\":[\"str\"],\"patientInstructions\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html=sec('Primary Diagnosis','<p class="text-lg font-black text-gray-900">'.h($d['diagnoses']['primary']??'').'</p>');
    if(!empty($d['diagnoses']['secondary'])) $html.=sec('Secondary',rl($d['diagnoses']['secondary']));
    $html.=sec('Hospital Course','<p class="text-gray-700 text-sm leading-relaxed">'.h($d['hospitalCourse']??'').'</p>');
    if(!empty($d['procedures'])) $html.=sec('Procedures',rl($d['procedures']));
    if(!empty($d['dischargeMedications'])){$html.='<p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Discharge Medications</p>';foreach($d['dischargeMedications'] as $m) $html.='<div class="border border-gray-100 rounded-xl p-3 mb-2"><p class="font-semibold text-gray-900 text-sm">'.h($m['name']??'').'</p><p class="text-xs text-gray-500">'.h($m['dose']??'').' — '.h($m['frequency']??'').' × '.h($m['duration']??'').'</p></div>';$html.='<div class="mb-4"></div>';}
    if(!empty($d['followUpPlan'])) $html.=sec('Follow-up',rl($d['followUpPlan'],'#0891b2'));
    if(!empty($d['patientInstructions'])) $html.=sec('Patient Instructions','<p class="text-gray-700 text-sm">'.h($d['patientInstructions']).'</p>');
    echo json_encode(['html'=>$html]); break;

// ── CLINICAL NOTES
case 'clinical-notes':
    $d=gemini("Generate ".($body['noteType']??'SOAP Note')." for ".($body['age']??'')."yr ".($body['gender']??'').". Info: ".($body['info']??'').". Return ONLY JSON: {\"noteType\":\"str\",\"subjective\":\"str\",\"objective\":{\"vitalSigns\":\"str\",\"physicalExam\":\"str\"},\"assessment\":\"str\",\"plan\":\"str\",\"icd10Suggestions\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="border border-gray-200 rounded-xl p-5 font-mono text-sm bg-gray-50"><p class="font-bold text-center mb-4">'.h($d['noteType']??'Clinical Note').'</p>';
    if(!empty($d['subjective'])) $html.='<div class="mb-3"><p class="font-bold">S — Subjective</p><p class="text-gray-700 ml-2">'.h($d['subjective']).'</p></div>';
    if(!empty($d['objective'])) $html.='<div class="mb-3"><p class="font-bold">O — Objective</p><p class="text-gray-700 ml-2">'.h($d['objective']['vitalSigns']??'').'</p><p class="text-gray-700 ml-2">'.h($d['objective']['physicalExam']??'').'</p></div>';
    if(!empty($d['assessment'])) $html.='<div class="mb-3"><p class="font-bold">A — Assessment</p><p class="text-gray-700 ml-2">'.h($d['assessment']).'</p></div>';
    if(!empty($d['plan'])) $html.='<div class="mb-3"><p class="font-bold">P — Plan</p><p class="text-gray-700 ml-2">'.h($d['plan']).'</p></div>';
    $html.='</div>';
    if(!empty($d['icd10Suggestions'])) $html.='<div class="mt-3"><p class="text-xs text-gray-400 mb-1">ICD-10 suggestions:</p><div class="flex flex-wrap gap-1">'.implode('',array_map(fn($c)=>'<span class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded font-mono">'.h($c).'</span>',$d['icd10Suggestions'])).'</div></div>';
    echo json_encode(['html'=>$html]); break;

// ── MEDICATION SAFETY
case 'medication-safety':
    $d=gemini("Medication safety: ".($body['drug']??'').". Allergies: ".($body['allergies']??'').". Current meds: ".($body['currentMeds']??'').". Return ONLY JSON: {\"overallSafety\":\"Safe|Caution|High Risk\",\"lasaRisk\":\"Yes|No\",\"highAlert\":\"Yes|No\",\"allergyConflict\":\"str or null\",\"interactions\":[{\"drug\":\"str\",\"severity\":\"str\",\"description\":\"str\"}],\"safetyRecommendations\":[\"str\"],\"monitoringParameters\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="flex gap-2 mb-4">'.bdg($d['overallSafety']??'Caution');
    if(($d['highAlert']??'')=='Yes') $html.='<span class="text-xs font-bold bg-red-100 text-red-700 px-2.5 py-1 rounded-full">&#9888; HIGH ALERT</span>';
    if(($d['lasaRisk']??'')=='Yes') $html.='<span class="text-xs font-bold bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full">LASA Risk</span>';
    $html.='</div>';
    if(!empty($d['allergyConflict'])) $html.='<div class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4 text-red-800 text-sm"><strong>&#9888; Allergy Conflict:</strong> '.h($d['allergyConflict']).'</div>';
    if(!empty($d['interactions'])){$html.='<p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Interactions</p>';foreach($d['interactions'] as $ix) $html.='<div class="border border-gray-100 rounded-xl p-3 mb-2"><div class="flex items-center justify-between mb-1"><span class="font-semibold text-sm">'.h($ix['drug']??'').'</span>'.bdg($ix['severity']??'').'</div><p class="text-xs text-gray-500">'.h($ix['description']??'').'</p></div>';$html.='<div class="mb-4"></div>';}
    if(!empty($d['safetyRecommendations'])) $html.=sec('Recommendations',rl($d['safetyRecommendations']));
    if(!empty($d['monitoringParameters'])) $html.=sec('Monitoring',rl($d['monitoringParameters'],'#7c3aed'));
    echo json_encode(['html'=>$html]); break;

// ── FORMULARY
case 'formulary':
    $d=gemini("Formulary search: ".($body['query']??'').". Status: ".($body['status']??'').". Route: ".($body['route']??'').". Return ONLY JSON: {\"results\":[{\"name\":\"str\",\"genericName\":\"str\",\"formularyStatus\":\"Formulary|Non-Formulary|Restricted\",\"route\":\"str\",\"therapeuticClass\":\"str\",\"restrictions\":\"str\",\"alternatives\":[\"str\"]}]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $sc=['Formulary'=>'bg-emerald-100 text-emerald-700','Non-Formulary'=>'bg-red-100 text-red-700','Restricted'=>'bg-amber-100 text-amber-700'];
    $html='';
    foreach(($d['results']??[]) as $r){
        $cls=$sc[$r['formularyStatus']??'']??'bg-gray-100 text-gray-600';
        $html.='<div class="border border-gray-100 rounded-xl p-4 mb-3"><div class="flex items-start justify-between mb-2"><div><p class="font-black text-gray-900">'.h($r['name']??'').'</p><p class="text-xs text-gray-500">'.h($r['genericName']??'').'</p></div><span class="text-xs font-bold px-2.5 py-1 rounded-full '.$cls.'">'.h($r['formularyStatus']??'').'</span></div>';
        $html.='<p class="text-xs text-gray-500 mb-1">'.h($r['therapeuticClass']??'').' — '.h($r['route']??'').'</p>';
        if(!empty($r['restrictions'])) $html.='<p class="text-xs text-amber-700 bg-amber-50 rounded px-2 py-1 mt-1">'.h($r['restrictions']).'</p>';
        if(!empty($r['alternatives'])) $html.='<p class="text-xs text-gray-400 mt-2">Alternatives: '.h(implode(', ',$r['alternatives'])).'</p>';
        $html.='</div>';
    }
    echo json_encode(['html'=>$html?:'<p class="text-gray-500">No results found.</p>']); break;

// ── IV COMPATIBILITY
case 'iv-compatibility':
    $drugs=implode(', ',array_filter([$body['drugA']??'',$body['drugB']??'',$body['drugC']??'']));
    $d=gemini("IV compatibility for: {$drugs} in ".($body['diluent']??'Normal Saline').". Return ONLY JSON: {\"overallCompatibility\":\"Compatible|Incompatible|Conditionally Compatible\",\"pairs\":[{\"drug1\":\"str\",\"drug2\":\"str\",\"compatibility\":\"Compatible|Incompatible|Conditionally Compatible\",\"evidence\":\"str\",\"notes\":\"str\"}],\"recommendation\":\"str\",\"alternativeApproach\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $cc=['Compatible'=>'bg-emerald-100 text-emerald-700','Incompatible'=>'bg-red-100 text-red-700','Conditionally Compatible'=>'bg-amber-100 text-amber-700'];
    $ov=$d['overallCompatibility']??'Unknown'; $ocl=$cc[$ov]??'bg-gray-100 text-gray-600';
    $html='<div class="text-center mb-4"><span class="text-xs font-bold px-4 py-2 rounded-full '.$ocl.'">'.h($ov).'</span></div>';
    foreach(($d['pairs']??[]) as $p){$pcl=$cc[$p['compatibility']??'']??'bg-gray-100 text-gray-600';$html.='<div class="border border-gray-100 rounded-xl p-4 mb-3"><div class="flex items-center justify-between mb-2"><span class="font-semibold text-sm text-gray-900">'.h($p['drug1']??'').' + '.h($p['drug2']??'').'</span><span class="text-xs font-bold px-2.5 py-1 rounded-full '.$pcl.'">'.h($p['compatibility']??'').'</span></div><p class="text-xs text-gray-500">'.h($p['evidence']??'').'</p>';if(!empty($p['notes'])) $html.='<p class="text-xs text-blue-700 bg-blue-50 rounded px-2 py-1 mt-1">'.h($p['notes']).'</p>';$html.='</div>';}
    if(!empty($d['recommendation'])) $html.=sec('Recommendation','<p class="text-gray-700 text-sm">'.h($d['recommendation']).'</p>');
    if(!empty($d['alternativeApproach'])) $html.='<div class="bg-blue-50 rounded-xl p-3 text-sm text-blue-800">'.h($d['alternativeApproach']).'</div>';
    echo json_encode(['html'=>$html]); break;

// ── CLINICAL PATHWAYS
case 'clinical-pathways':
    $d=gemini("Clinical pathway for: ".($body['condition']??'').". Return ONLY JSON: {\"condition\":\"str\",\"overview\":\"str\",\"initialAssessment\":[\"str\"],\"diagnosticWorkup\":[\"str\"],\"treatmentSteps\":[{\"step\":1,\"action\":\"str\",\"timeframe\":\"str\",\"notes\":\"str\"}],\"monitoring\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<h3 class="font-black text-gray-900 text-xl mb-3">'.h($d['condition']??'').'</h3>';
    if(!empty($d['overview'])) $html.=sec('Overview','<p class="text-gray-700 text-sm leading-relaxed">'.h($d['overview']).'</p>');
    if(!empty($d['initialAssessment'])) $html.=sec('Initial Assessment',rl($d['initialAssessment']));
    if(!empty($d['diagnosticWorkup'])) $html.=sec('Diagnostic Workup',rl($d['diagnosticWorkup'],'#7c3aed'));
    if(!empty($d['treatmentSteps'])){$html.='<p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Treatment Steps</p>';foreach($d['treatmentSteps'] as $step) $html.='<div class="flex gap-3 mb-3"><div class="flex-shrink-0 w-7 h-7 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-bold">'.h((string)($step['step']??'')).'</div><div><p class="font-semibold text-gray-900 text-sm">'.h($step['action']??'').'</p><p class="text-xs text-gray-400">'.h($step['timeframe']??'').'</p>'.(!empty($step['notes'])?'<p class="text-xs text-gray-500 mt-0.5">'.h($step['notes']).'</p>':'').'</div></div>';}
    if(!empty($d['monitoring'])) $html.=sec('Monitoring',rl($d['monitoring'],'#0891b2'));
    echo json_encode(['html'=>$html]); break;

// ── CLINICAL CALCULATORS (AI-backed ones)
case 'clinical-calculators':
    $d=gemini("Calculate ".($body['calculator']??'')." score. Data: ".($body['data']??'').". Return ONLY JSON: {\"calculator\":\"str\",\"result\":\"str\",\"score\":\"str\",\"interpretation\":\"str\",\"riskCategory\":\"str\",\"recommendations\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<p class="text-3xl font-black text-orange-500 mb-1">'.h($d['result']??$d['score']??'').'</p>';
    if(!empty($d['riskCategory'])) $html.='<div class="mb-3">'.bdg($d['riskCategory']).'</div>';
    $html.=sec('Interpretation','<p class="text-gray-700 text-sm">'.h($d['interpretation']??'').'</p>');
    if(!empty($d['recommendations'])) $html.=sec('Recommendations',rl($d['recommendations'],'#ea580c'));
    echo json_encode(['html'=>$html]); break;

// ── STEWARDSHIP
case 'stewardship':
    $d=gemini("AMS review. Antibiotic: ".($body['antibiotic']??'').". Indication: ".($body['indication']??'').". Culture: ".($body['culture']??'').". Day ".($body['dayOfTherapy']??'')." of therapy. Return ONLY JSON: {\"appropriateness\":\"Appropriate|Inappropriate|Needs Review\",\"recommendation\":\"Continue|De-escalate|Discontinue|Modify\",\"justification\":\"str\",\"deEscalationOption\":\"str\",\"suggestedDuration\":\"str\",\"monitoringParameters\":[\"str\"],\"resistanceRisk\":\"Low|Moderate|High\"}");
    if(isset($d['error'])){echo json_encode(['html'=>'<p class="text-red-500">'.h($d['error']).'</p>']);exit;}
    $html='<div class="flex gap-2 mb-4">'.bdg($d['appropriateness']??'Needs Review').bdg($d['recommendation']??'').'</div>';
    $html.=sec('Justification','<p class="text-gray-700 text-sm leading-relaxed">'.h($d['justification']??'').'</p>');
    if(!empty($d['deEscalationOption'])) $html.='<div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 mb-4 text-emerald-800 text-sm"><strong>De-escalation:</strong> '.h($d['deEscalationOption']).'</div>';
    if(!empty($d['suggestedDuration'])) $html.=sec('Duration','<p class="text-gray-700 text-sm">'.h($d['suggestedDuration']).'</p>');
    if(!empty($d['monitoringParameters'])) $html.=sec('Monitoring',rl($d['monitoringParameters'],'#4338ca'));
    $html.='<div class="flex items-center gap-2 mt-2"><p class="text-xs text-gray-500">Resistance Risk:</p>'.bdg($d['resistanceRisk']??'Low').'</div>';
    echo json_encode(['html'=>$html]); break;

default:
    http_response_code(404);
    echo json_encode(['error'=>'Unknown tool: '.$tool]);
    break;
}
