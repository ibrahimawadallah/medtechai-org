<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

define('GROQ_KEY',        getenv('GROQ_API_KEY')        ?: '');
define('GEMINI_KEY',      getenv('GEMINI_API_KEY')      ?: getenv('GOOGLE_API_KEY') ?: '');
define('OPENROUTER_KEY',  getenv('OPENROUTER_API_KEY')  ?: '');

$uri   = $_SERVER['REQUEST_URI'];
$parts = explode('/', trim(parse_url($uri, PHP_URL_PATH), '/'));
$tool  = $parts[2] ?? '';
$raw   = file_get_contents('php://input');
$body  = json_decode($raw, true) ?: [];
foreach ($_POST as $k => $v) $body[$k] = $v;

// Centralized file upload handler
$upData = null; $upMime = null;
$magic = [
    'image/jpeg' => ["\xFF\xD8\xFF"],
    'image/png'  => ["\x89\x50\x4E\x47"],
    'image/gif'  => ["\x47\x49\x46"],
    'image/webp' => ["\x52\x49\x46\x46"],
    'application/pdf' => ["\x25\x50\x44\x46"],
];
foreach ($_FILES as $f) {
    if ($f['error'] !== UPLOAD_ERR_OK) continue;
    $mime = mime_content_type($f['tmp_name']);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($f['tmp_name']);
    if (!isset($magic[$realMime]) || $mime !== $realMime) continue;
    if ($f['size'] > 5242880) continue;
    $bin = file_get_contents($f['tmp_name']);
    $header = substr($bin, 0, 8);
    $ok = false;
    foreach ($magic[$realMime] as $sig) {
        if (str_starts_with($header, $sig)) { $ok = true; break; }
    }
    if (!$ok) continue;
    $upData = base64_encode($bin);
    $upMime = $realMime;
    break;
}
if ($upData && $upMime && GEMINI_KEY) {
    $extractPrompt = "Extract all readable text, drug names, lab values, and findings from this medical document/image. Return the raw content as plain text. If you cannot read anything, say 'No readable content found.'";
    $extracted = callGeminiVision(GEMINI_KEY, $extractPrompt, $upData, $upMime);
    if ($extracted) $body['_fileText'] = $extracted;
}
$fileContext = ($body['_fileText']??'') ? "Uploaded document context:\n" . $body['_fileText'] . "\n\n---\n\n" : '';

function parseAI(string $text): array {
    $text = preg_replace('/```json\n?|```\n?/', '', $text);
    $text = trim($text);
    preg_match('/\{[\s\S]*\}/u', $text, $m);
    if ($m) { $p = json_decode($m[0], true); if ($p) return $p; }
    return ['raw_text' => $text];
}

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

function callGemini(string $key, string $prompt): ?string {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $key;
    $payload = json_encode(['contents'=>[['parts'=>[['text'=>$prompt]]]],'generationConfig'=>['temperature'=>0.3,'maxOutputTokens'=>2048]]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>30]);
    $resp = curl_exec($ch); curl_close($ch);
    $json = json_decode($resp, true);
    return $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

function callGeminiVision(string $key, string $prompt, string $imageData, string $mimeType): ?string {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $key;
    $payload = json_encode([
        'contents'=>[[
            'parts'=>[
                ['inline_data'=>['mime_type'=>$mimeType, 'data'=>$imageData]],
                ['text'=>$prompt]
            ]
        ]],
        'generationConfig'=>['temperature'=>0.3,'maxOutputTokens'=>2048]
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>60]);
    $resp = curl_exec($ch); curl_close($ch);
    $json = json_decode($resp, true);
    return $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

function geminiVision(string $prompt, string $imageData, string $mimeType): array {
    $text = null;
    if (GEMINI_KEY) {
        $text = callGeminiVision(GEMINI_KEY, $prompt, $imageData, $mimeType);
    }
    if (!$text) {
        $fb = $prompt . " (Note: an image was uploaded but could not be analyzed visually. Use text findings only.)";
        if (GROQ_KEY) {
            $text = callOpenAI('https://api.groq.com/openai/v1/chat/completions', GROQ_KEY, 'llama-3.3-70b-versatile', $fb);
        }
        if (!$text && OPENROUTER_KEY) {
            $text = callOpenAI('https://openrouter.ai/api/v1/chat/completions', OPENROUTER_KEY, 'meta-llama/llama-3.3-70b-instruct:free', $fb);
        }
    }
    if (!$text) return ['error' => 'No AI provider available. Set GEMINI_API_KEY for image analysis or GROQ/OPENROUTER for text.'];
    return parseAI($text);
}

function gemini(string $prompt): array {
    $text = null;
    if (GROQ_KEY) {
        $text = callOpenAI('https://api.groq.com/openai/v1/chat/completions', GROQ_KEY, 'llama-3.3-70b-versatile', $prompt);
    }
    if (!$text && GEMINI_KEY) {
        $text = callGemini(GEMINI_KEY, $prompt);
    }
    if (!$text && OPENROUTER_KEY) {
        $text = callOpenAI('https://openrouter.ai/api/v1/chat/completions', OPENROUTER_KEY, 'meta-llama/llama-3.3-70b-instruct:free', $prompt);
    }
    if (!$text) {
        return ['error' => 'No AI provider configured. Set GROQ_API_KEY, GEMINI_API_KEY, or OPENROUTER_API_KEY on the server.'];
    }
    return parseAI($text);
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function rl(array $items): string {
    $o='<ul class="r-list">';
    foreach($items as $i) $o.='<li>'.h($i).'</li>';
    return $o.'</ul>';
}

function bdg(string $l): string {
    $m=['high'=>'badge-red','severe'=>'badge-red','critical'=>'badge-red',
        'moderate'=>'badge-amber','mild'=>'badge-amber','caution'=>'badge-amber',
        'low'=>'badge-green','none'=>'badge-gray','normal'=>'badge-green',
        'safe'=>'badge-green','emergency'=>'badge-red','urgent'=>'badge-amber',
        'routine'=>'badge-blue','self-care'=>'badge-green','appropriate'=>'badge-green',
        'compatible'=>'badge-green','incompatible'=>'badge-red',
        'needs review'=>'badge-amber','high risk'=>'badge-red',
        'high'=>'badge-red','medium'=>'badge-amber','low'=>'badge-green',
        'yes'=>'badge-red','no'=>'badge-green',
        'restricted'=>'badge-amber','non-formulary'=>'badge-red','formulary'=>'badge-green',
        'safe|caution|avoid'=>'badge-amber'];
    $cl=$m[strtolower($l)]??'badge-gray';
    return '<span class="badge '.$cl.'">'.h(strtoupper($l)).'</span>';
}

function autoAccent(string $t): string {
    $t2=strtolower($t);
    $m=['indications'=>'primary','indication'=>'primary','summary'=>'primary','impression'=>'primary','study type'=>'primary','study'=>'primary','technique'=>'primary','assessment'=>'primary','overall assessment'=>'primary','overview'=>'primary','diagnosis'=>'primary','clinical indication'=>'primary','clinical summary'=>'primary','clinical application'=>'primary','clinical correlation'=>'primary','structured report'=>'primary','diagnostic criteria'=>'primary','recommendations'=>'green','recommendation'=>'green','management'=>'green','management plan'=>'green','care advice'=>'green','home care'=>'green','administration'=>'green','administration guidance'=>'green','patient education'=>'green','patient management'=>'green','alternative'=>'green','next steps'=>'green','plan'=>'green','workup'=>'green','procedures'=>'green','discharge medications'=>'green','safe'=>'primary','green'=>'primary',
    'warning'=>'red','red flag'=>'red','contraindication'=>'red','contraindicated'=>'red','contraindication differences'=>'red','allergy'=>'red','critical value'=>'red','red'=>'red','risk factor'=>'amber','risk factors'=>'amber','adverse'=>'amber','interaction'=>'amber','dosing error'=>'amber','precaution'=>'amber','caveats'=>'amber','risk'=>'amber','safety'=>'amber','considerations'=>'amber','consideration'=>'amber','monitoring'=>'teal','monitoring recommendation'=>'teal','follow-up'=>'teal','follow up'=>'teal','followup'=>'teal','trends analysis'=>'teal','trends'=>'teal','prognosis'=>'teal','outcome'=>'teal','pregnancy'=>'purple','lactation'=>'purple','breastfeeding'=>'purple','genetic'=>'purple','molecular'=>'purple','biomarker'=>'purple','biomarkers'=>'purple','fda category'=>'purple','teratogenic'=>'purple','male reproductive'=>'purple','secondar'=>'purple','special populations'=>'purple','demographic'=>'purple','pregnancy risk'=>'purple','preconception'=>'purple','pregnancy registry'=>'purple','diagnoses'=>'purple','secondary diagnoses'=>'purple','icd-10'=>'purple','icd10'=>'purple'];
    foreach($m as $k=>$v) if(str_contains($t2,$k)) return $v;
    return 'primary';
}
function sec(string $t, string $c, string $accent = ''): string {
    if(!$accent) $accent=autoAccent($t);
    $ac=$accent?' '.$accent:'';
    return '<div class="r-section'.$ac.'"><div class="r-section-header no-collapse"><span class="r-section-title">'.h($t).'</span></div><div class="r-section-body">'.$c.'</div></div>';
}

function alert(string $type, string $text): string {
    $m=['red'=>'r-alert-red','amber'=>'r-alert-amber','blue'=>'r-alert-blue','green'=>'r-alert-green'];
    $cl=$m[$type]??'r-alert-blue';
    return '<div class="r-alert '.$cl.'"><div>'.$text.'</div></div>';
}

function dailymedGet(string $url): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_USERAGENT=>'MedTechAI/1.0']);
    $r = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ($http === 200 && $r) ? $r : null;
}

function cleanDMText(string $html): string {
    $t = preg_replace('/<br\s*\/?>\s*/i', "\n", $html);
    $t = preg_replace('/<\/p>\s*/i', "\n\n", $t);
    $t = preg_replace('/<li[^>]*>\s*/i', "\n  - ", $t);
    $t = preg_replace('/<\/li>\s*/i', '', $t);
    $t = preg_replace('/<h3>.*?<\/h3>/i', '', $t);
    $t = strip_tags($t);
    $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/[ \t]+/', ' ', $t);
    $t = preg_replace('/\n{3,}/', "\n\n", $t);
    return trim($t);
}

function fetchDailyMedLabel(string $drug): array {
    $r = ['indications'=>'', 'warnings'=>'', 'boxedWarning'=>''];
    // Search
    $j = dailymedGet('https://dailymed.nlm.nih.gov/dailymed/services/v2/spls.json?drug_name='.urlencode(strtoupper($drug)).'&name_type=both&pagesize=3');
    if (!$j) return $r;
    $d = json_decode($j, true);
    if (empty($d['data'])) return $r;
    $setid = $d['data'][0]['setid'] ?? '';
    if (!$setid) return $r;
    // Fetch label HTML
    $html = dailymedGet('https://dailymed.nlm.nih.gov/dailymed/drugInfo.cfm?setid='.$setid);
    if (!$html) return $r;
    // Remove script/style tags
    $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
    $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
    // Indications (section 1)
    if (preg_match('/<h3>\s*1\s+INDICATIONS?\s+(?:AND\s+)?USAGE\s*<\/h3>\s*(.*?)(?=<h3>\s*\d+\s+|$)/si', $html, $m)) {
        $r['indications'] = cleanDMText($m[1]);
    }
    // Warnings (section 5)
    if (preg_match('/<h3>\s*5\s+WARNINGS?\s+(?:AND\s+)?PRECAUTIONS?\s*<\/h3>\s*(.*?)(?=<h3>\s*\d+\s+|$)/si', $html, $m)) {
        $r['warnings'] = cleanDMText($m[1]);
    }
    // Boxed warning
    if (preg_match('/<h3[^>]*>\s*BOXED\s+WARNING/i', $html)) {
        if (preg_match('/<h3[^>]*>\s*BOXED\s+WARNING[^<]*<\/h3>\s*(.*?)(?=<h3|$)/si', $html, $m)) {
            $r['boxedWarning'] = cleanDMText($m[1]);
        }
    }
    // If no boxed warning section found, look in highlights
    if (!$r['boxedWarning'] && preg_match('/WARNING:\s*(?:[^<]*<br\s*\/?>\s*)*(?:[^<]*)(?=<br|<h|$)/i', $html, $m)) {
        $r['boxedWarning'] = cleanDMText($m[0]);
    }
    return $r;
}

switch ($tool) {

// DRUG SEARCH
case 'drug-search':
    $drug = trim($body['drug']??''); if(!$drug){echo json_encode(['html'=>alert('red','Enter a drug name.')]);exit;}
    $d = gemini($fileContext . "Explain drug \"{$drug}\" comprehensively. Return ONLY JSON: {\"genericName\":\"str\",\"brandNames\":[\"str\"],\"drugClass\":\"str\",\"therapeuticCategory\":\"str\",\"mechanismOfAction\":\"str\",\"indications\":[\"str\"],\"dosageForms\":[\"str\"],\"adultDosing\":\"str\",\"pediatricDosing\":\"str\",\"renalAdjustment\":\"str\",\"hepaticAdjustment\":\"str\",\"administration\":\"str\",\"adverseReactions\":[{\"system\":\"str\",\"reactions\":\"str\",\"frequency\":\"str\"}],\"contraindications\":[\"str\"],\"clinicalWarnings\":[\"str\"],\"drugInteractions\":[\"str\"],\"pregnancyCategory\":\"str\",\"lactationSafety\":\"str\",\"monitoringParameters\":[\"str\"],\"pharmacokinetics\":\"str\",\"patientEducation\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='<div class="r-value-lg" style="margin-bottom:8px">'.h($d['genericName']??$drug).'</div>';
    if(!empty($d['brandNames'])) $html.=sec('Brand Names','<span class="r-value">'.h(implode(', ',$d['brandNames'])).'</span>');
    if(!empty($d['drugClass'])) $html.=sec('Drug Class','<span class="r-value">'.h($d['drugClass']).'</span>');
    if(!empty($d['therapeuticCategory'])) $html.=sec('Therapeutic Category','<span class="r-value">'.h($d['therapeuticCategory']).'</span>');
    if(!empty($d['mechanismOfAction'])) $html.=sec('Mechanism of Action','<span class="r-value">'.h($d['mechanismOfAction']).'</span>');
    if(!empty($d['indications'])) $html.=sec('Indications',rl($d['indications']));
    if(!empty($d['dosageForms'])) $html.=sec('Dosage Forms','<span class="r-value">'.h(implode(', ',$d['dosageForms'])).'</span>');
    if(!empty($d['adultDosing'])) $html.=sec('Adult Dosing','<span class="r-value">'.h($d['adultDosing']).'</span>');
    if(!empty($d['pediatricDosing'])) $html.=sec('Pediatric Dosing','<span class="r-value">'.h($d['pediatricDosing']).'</span>');
    if(!empty($d['renalAdjustment'])) $html.=sec('Renal Adjustment','<span class="r-value">'.h($d['renalAdjustment']).'</span>');
    if(!empty($d['hepaticAdjustment'])) $html.=sec('Hepatic Adjustment','<span class="r-value">'.h($d['hepaticAdjustment']).'</span>');
    if(!empty($d['administration'])) $html.=sec('Administration','<span class="r-value">'.h($d['administration']).'</span>');
    if(!empty($d['adverseReactions'])){$ar='';foreach($d['adverseReactions'] as $arx)$ar.='<div style="margin-bottom:6px"><span class="badge badge-blue">'.h($arx['system']??'').'</span> <span class="r-value-sm">'.h($arx['frequency']??'').'</span><div class="r-value">'.h($arx['reactions']??'').'</div></div>';$html.=sec('Adverse Reactions',$ar);}
    if(!empty($d['contraindications'])) $html.=sec('Contraindications',rl($d['contraindications']));
    if(!empty($d['clinicalWarnings'])) $html.=sec('Warnings',rl($d['clinicalWarnings']));
    if(!empty($d['drugInteractions'])) $html.=sec('Drug Interactions',rl($d['drugInteractions']));
    if(!empty($d['pregnancyCategory'])) $html.=sec('Pregnancy Category','<span class="r-value">'.h($d['pregnancyCategory']).'</span>');
    if(!empty($d['lactationSafety'])) $html.=sec('Lactation Safety','<span class="r-value">'.h($d['lactationSafety']).'</span>');
    if(!empty($d['monitoringParameters'])) $html.=sec('Monitoring',rl($d['monitoringParameters']));
    if(!empty($d['pharmacokinetics'])) $html.=sec('Pharmacokinetics','<span class="r-value">'.h($d['pharmacokinetics']).'</span>');
    if(!empty($d['patientEducation'])) $html.=sec('Patient Education','<span class="r-value">'.h($d['patientEducation']).'</span>');
    // Augment with DailyMed FDA label data
    $dm = fetchDailyMedLabel($drug);
    if ($dm['boxedWarning']) $html.=sec('FDA Boxed Warning','<div class="r-alert r-alert-red"><div style="white-space:pre-wrap;font-size:12px">'.h($dm['boxedWarning']).'</div></div>');
    if ($dm['indications']) $html.=sec('FDA Indications (DailyMed)','<div style="white-space:pre-wrap;font-size:12px;color:var(--slate3)">'.h($dm['indications']).'</div>');
    if ($dm['warnings']) $html.=sec('FDA Warnings &amp; Precautions (DailyMed)','<div style="white-space:pre-wrap;font-size:12px;color:var(--slate3)">'.h($dm['warnings']).'</div>');
    echo json_encode(['html'=>$html]); break;

// INTERACTION CHECKER
case 'interaction-checker':
    $drugs=array_filter(array_map('trim',$body['drugs']??[]));
    if(count($drugs)<2){echo json_encode(['html'=>alert('red','Enter at least 2 drugs.')]);exit;}
    $dl=implode(', ',$drugs);
    $d=gemini($fileContext . "Drug interactions for: {$dl}. Return ONLY JSON: {\"interactions\":[{\"drugs\":[\"A\",\"B\"],\"severity\":\"high|moderate|low\",\"description\":\"str\",\"mechanism\":\"str\",\"clinicalSignificance\":\"str\",\"onset\":\"str\",\"management\":\"str\"}],\"overallRisk\":\"high|moderate|low\",\"summary\":\"str\",\"riskFactors\":[\"str\"],\"monitoringRecommendation\":\"str\",\"patientManagement\":\"str\",\"alternativeCombinations\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='<div style="margin-bottom:10px"><strong>Overall Risk:</strong> '.bdg($d['overallRisk']??'low').'</div>';
    if(!empty($d['summary'])) $html.=sec('Summary','<span class="r-value">'.h($d['summary']).'','primary');
    if(!empty($d['riskFactors'])) $html.=sec('Risk Factors',rl($d['riskFactors']),'amber');
    foreach(($d['interactions']??[]) as $ix){
        $html.='<div class="r-section amber"><div class="r-section-header no-collapse"><span class="r-section-title">'.h(implode(' + ',$ix['drugs']??[])).'</span>'.bdg($ix['severity']??'').'</div><div class="r-section-body"><span class="r-value">'.h($ix['description']??'').'</span>';
        if(!empty($ix['mechanism'])) $html.='<div class="r-field"><span class="r-label">Mechanism</span><span class="r-value-sm">'.h($ix['mechanism']).'</span></div>';
        if(!empty($ix['clinicalSignificance'])) $html.='<div class="r-field"><span class="r-label">Significance</span><span class="r-value-sm">'.h($ix['clinicalSignificance']).'</span></div>';
        if(!empty($ix['onset'])) $html.='<div class="r-field"><span class="r-label">Onset</span><span class="r-value-sm">'.h($ix['onset']).'</span></div>';
        if(!empty($ix['management'])) $html.='<div class="r-field"><span class="r-label">Management</span><span class="r-value-sm">'.h($ix['management']).'</span></div>';
        $html.='</div></div>';
    }
    if(!empty($d['monitoringRecommendation'])) $html.=sec('Monitoring Recommendation','<span class="r-value">'.h($d['monitoringRecommendation']).'','teal');
    if(!empty($d['patientManagement'])) $html.=sec('Patient Management','<span class="r-value">'.h($d['patientManagement']).'','green');
    if(!empty($d['alternativeCombinations'])) $html.=sec('Alternative Combinations',rl($d['alternativeCombinations']),'green');
    echo json_encode(['html'=>$html]); break;

// DOSE CALCULATOR
case 'dose-calculator':
    $d=gemini($fileContext . "Dose for: ".($body['drug']??'').", weight ".($body['weight']??'')."kg, age ".($body['age']??'').", indication: ".($body['indication']??'').", renal: ".($body['renal']??'Normal').". Return ONLY JSON: {\"recommendedDose\":\"str\",\"frequency\":\"str\",\"route\":\"str\",\"duration\":\"str\",\"renalAdjustment\":\"str\",\"hepaticAdjustment\":\"str\",\"pediatricDose\":\"str\",\"loadingDose\":\"str\",\"maxDose\":\"str\",\"bsaDose\":\"str\",\"therapeuticDrugMonitoring\":\"str\",\"administrationGuidance\":\"str\",\"precautions\":[\"str\"],\"monitoringParameters\":[\"str\"],\"warnings\":[\"str\"],\"notes\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='<div class="r-value-lg" style="margin-bottom:8px">'.h($d['recommendedDose']??'').'</div>';
    $html.=sec('Frequency','<span class="r-value">'.h($d['frequency']??'').'</span>');
    $html.=sec('Route','<span class="r-value">'.h($d['route']??'').'</span>');
    if(!empty($d['duration'])) $html.=sec('Duration','<span class="r-value">'.h($d['duration']).'</span>');
    if(!empty($d['loadingDose'])) $html.=sec('Loading Dose','<span class="r-value">'.h($d['loadingDose']).'</span>');
    if(!empty($d['pediatricDose'])) $html.=sec('Pediatric Dose','<span class="r-value">'.h($d['pediatricDose']).'</span>');
    if(!empty($d['bsaDose'])) $html.=sec('BSA-Based Dose','<span class="r-value">'.h($d['bsaDose']).'</span>');
    if(!empty($d['maxDose'])) $html.=sec('Max Dose','<span class="r-value">'.h($d['maxDose']).'</span>');
    if(!empty($d['renalAdjustment'])) $html.=sec('Renal Adjustment','<span class="r-value">'.h($d['renalAdjustment']).'</span>');
    if(!empty($d['hepaticAdjustment'])) $html.=sec('Hepatic Adjustment','<span class="r-value">'.h($d['hepaticAdjustment']).'</span>');
    if(!empty($d['therapeuticDrugMonitoring'])) $html.=sec('Therapeutic Drug Monitoring','<span class="r-value">'.h($d['therapeuticDrugMonitoring']).'</span>');
    if(!empty($d['administrationGuidance'])) $html.=sec('Administration Guidance','<span class="r-value">'.h($d['administrationGuidance']).'</span>');
    if(!empty($d['precautions'])) $html.=sec('Precautions',rl($d['precautions']));
    if(!empty($d['monitoringParameters'])) $html.=sec('Monitoring',rl($d['monitoringParameters']));
    if(!empty($d['warnings'])) $html.=sec('Warnings',rl($d['warnings']));
    if(!empty($d['notes'])) $html.=sec('Notes','<span class="r-value">'.h($d['notes']).'</span>');
    echo json_encode(['html'=>$html]); break;

// PREGNANCY SAFETY
case 'pregnancy-safety':
    $d=gemini($fileContext . "Pregnancy safety for: ".($body['drug']??'').", trimester: ".($body['trimester']??'1').". Return ONLY JSON: {\"fdaCategory\":\"A|B|C|D|X|N\",\"safety\":\"Safe|Caution|Avoid\",\"risk\":\"str\",\"fdaCategoryRationale\":\"str\",\"trimesterSpecific\":\"str\",\"mechanismOfAction\":\"str\",\"animalData\":\"str\",\"humanData\":\"str\",\"lactationSafety\":\"str\",\"breastfeedingRecommendation\":\"str\",\"maleReproductiveEffects\":\"str\",\"preconceptionCounseling\":\"str\",\"pregnancyRegistry\":\"str\",\"alternatives\":[\"str\"],\"recommendation\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $cat=$d['fdaCategory']??'N'; $cc=['A'=>'#059669','B'=>'#059669','C'=>'#d97706','D'=>'#dc2626','X'=>'#dc2626'];
    $html='<div style="display:flex;align-items:center;gap:12px;margin-bottom:10px"><div style="text-align:center"><span class="r-label">FDA</span><div style="font-size:28px;font-weight:900;color:'.($cc[$cat]??'#64748b').'">'.$cat.'</div></div>'.bdg($d['safety']??'').'</div>';
    if(!empty($d['risk'])) $html.=sec('Risk Assessment','<span class="r-value">'.h($d['risk']).'</span>');
    if(!empty($d['fdaCategoryRationale'])) $html.=sec('FDA Category Rationale','<span class="r-value">'.h($d['fdaCategoryRationale']).'</span>');
    if(!empty($d['trimesterSpecific'])) $html.=sec('Trimester Notes','<span class="r-value">'.h($d['trimesterSpecific']).'</span>');
    if(!empty($d['mechanismOfAction'])) $html.=sec('Teratogenic Mechanism','<span class="r-value">'.h($d['mechanismOfAction']).'</span>');
    if(!empty($d['animalData'])) $html.=sec('Animal Data','<span class="r-value">'.h($d['animalData']).'</span>');
    if(!empty($d['humanData'])) $html.=sec('Human Data','<span class="r-value">'.h($d['humanData']).'</span>');
    if(!empty($d['lactationSafety'])) $html.=sec('Lactation','<span class="r-value">'.h($d['lactationSafety']).'</span>');
    if(!empty($d['breastfeedingRecommendation'])) $html.=sec('Breastfeeding','<span class="r-value">'.h($d['breastfeedingRecommendation']).'</span>');
    if(!empty($d['maleReproductiveEffects'])) $html.=sec('Male Reproductive Effects','<span class="r-value">'.h($d['maleReproductiveEffects']).'</span>');
    if(!empty($d['preconceptionCounseling'])) $html.=sec('Preconception Counseling','<span class="r-value">'.h($d['preconceptionCounseling']).'</span>');
    if(!empty($d['pregnancyRegistry'])) $html.=sec('Pregnancy Registry','<span class="r-value">'.h($d['pregnancyRegistry']).'</span>');
    if(!empty($d['alternatives'])) $html.=sec('Safer Alternatives',rl($d['alternatives']));
    if(!empty($d['recommendation'])) $html.=alert('blue',h($d['recommendation']));
    echo json_encode(['html'=>$html]); break;

// G6PD CHECKER
case 'g6pd-checker':
    $drug=$body['drug']??'';
    $d=gemini($fileContext . "G6PD safety for: {$drug}. Return ONLY JSON: {\"riskLevel\":\"Safe|Low Risk|Moderate Risk|High Risk|Contraindicated\",\"classification\":\"str\",\"description\":\"str\",\"mechanism\":\"str\",\"hemolyticPotential\":\"str\",\"onsetOfHemolysis\":\"str\",\"severityOfReaction\":\"str\",\"monitoringParameters\":[\"str\"],\"geneticCounseling\":\"str\",\"patientEducation\":\"str\",\"recommendation\":\"str\",\"alternatives\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='<div style="margin-bottom:10px">'.bdg($d['riskLevel']??'Unknown').'</div>';
    if(!empty($d['classification'])) $html.=sec('Classification','<span class="r-value">'.h($d['classification']).'</span>');
    if(!empty($d['description'])) $html.=sec('Description','<span class="r-value">'.h($d['description']).'</span>');
    if(!empty($d['mechanism'])) $html.=sec('Mechanism','<span class="r-value">'.h($d['mechanism']).'</span>');
    if(!empty($d['hemolyticPotential'])) $html.=sec('Hemolytic Potential','<span class="r-value">'.h($d['hemolyticPotential']).'</span>');
    if(!empty($d['onsetOfHemolysis'])) $html.=sec('Onset of Hemolysis','<span class="r-value">'.h($d['onsetOfHemolysis']).'</span>');
    if(!empty($d['severityOfReaction'])) $html.=sec('Severity of Reaction','<span class="r-value">'.h($d['severityOfReaction']).'</span>');
    if(!empty($d['monitoringParameters'])) $html.=sec('Monitoring',rl($d['monitoringParameters']));
    if(!empty($d['geneticCounseling'])) $html.=sec('Genetic Counseling','<span class="r-value">'.h($d['geneticCounseling']).'</span>');
    if(!empty($d['patientEducation'])) $html.=sec('Patient Education','<span class="r-value">'.h($d['patientEducation']).'</span>');
    if(!empty($d['recommendation'])) $html.=sec('Recommendation','<span class="r-value">'.h($d['recommendation']).'</span>');
    if(!empty($d['alternatives'])) $html.=sec('Alternatives',rl($d['alternatives']));
    echo json_encode(['html'=>$html]); break;

// DRUG COMPARISON
case 'drug-comparison':
    $d=gemini($fileContext . "Compare drugs: ".($body['drugA']??'')." vs ".($body['drugB']??'').". Return ONLY JSON: {\"summary\":\"str\",\"mechanismOfActionComparison\":\"str\",\"indicationsOverlap\":\"str\",\"contraindicationDifferences\":\"str\",\"monitoringRequirements\":\"str\",\"specialPopulations\":\"str\",\"guidelinePreference\":\"str\",\"comparison\":{\"efficacy\":{\"winner\":\"str\",\"reasoning\":\"str\"},\"safety\":{\"winner\":\"str\",\"reasoning\":\"str\"},\"cost\":{\"winner\":\"str\",\"reasoning\":\"str\"},\"convenience\":{\"winner\":\"str\",\"reasoning\":\"str\"}},\"considerations\":[\"str\"],\"recommendation\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html=sec('Summary','<span class="r-value">'.h($d['summary']??'').'</span>');
    if(!empty($d['mechanismOfActionComparison'])) $html.=sec('Mechanism Comparison','<span class="r-value">'.h($d['mechanismOfActionComparison']).'</span>');
    if(!empty($d['indicationsOverlap'])) $html.=sec('Indications Overlap','<span class="r-value">'.h($d['indicationsOverlap']).'</span>');
    if(!empty($d['contraindicationDifferences'])) $html.=sec('Contraindication Differences','<span class="r-value">'.h($d['contraindicationDifferences']).'</span>');
    if(!empty($d['monitoringRequirements'])) $html.=sec('Monitoring Requirements','<span class="r-value">'.h($d['monitoringRequirements']).'</span>');
    if(!empty($d['specialPopulations'])) $html.=sec('Special Populations','<span class="r-value">'.h($d['specialPopulations']).'</span>');
    if(!empty($d['guidelinePreference'])) $html.=sec('Guideline Preference','<span class="r-value">'.h($d['guidelinePreference']).'</span>');
    if(!empty($d['comparison'])){
        $html.='<div class="r-grid" style="margin-bottom:10px">';
        foreach($d['comparison'] as $asp=>$data) $html.='<div class="r-field"><span class="r-label">'.h(ucfirst($asp)).'</span><div><strong>'.h($data['winner']??'').'</strong></div><span class="r-value-sm">'.h($data['reasoning']??'').'</span></div>';
        $html.='</div>';
    }
    if(!empty($d['considerations'])) $html.=sec('Considerations',rl($d['considerations']));
    if(!empty($d['recommendation'])) $html.=alert('green',h($d['recommendation']));
    echo json_encode(['html'=>$html]); break;

// CLINICAL DECISION SUPPORT
case 'clinical-decision-support':
    $d=gemini($fileContext . "Clinical decision support. Symptoms: ".($body['symptoms']??'').", History: ".($body['hx']??'').". Return ONLY JSON: {\"assessment\":\"str\",\"differentialDiagnosis\":[{\"condition\":\"str\",\"probability\":\"High|Medium|Low\",\"reasoning\":\"str\"}],\"recommendedWorkup\":[\"str\"],\"managementPlan\":[\"str\"],\"urgency\":\"Emergency|Urgent|Routine\",\"referral\":\"str\",\"evidenceBasedGuidelines\":[\"str\"],\"riskFactors\":[\"str\"],\"prognosis\":\"str\",\"patientEducation\":\"str\",\"followUpTimeline\":\"str\",\"clinicalPearls\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='<div style="margin-bottom:10px"><strong>Urgency:</strong> '.bdg($d['urgency']??'routine').'</div>';
    if(!empty($d['assessment'])) $html.=sec('Assessment','<span class="r-value">'.h($d['assessment']).'</span>');
    if(!empty($d['differentialDiagnosis'])){
        $dd='';
        foreach($d['differentialDiagnosis'] as $diag) $dd.='<div style="margin-bottom:6px"><div style="display:flex;align-items:center;gap:8px"><strong>'.h($diag['condition']??'').'</strong>'.bdg($diag['probability']??'').'</div><span class="r-value-sm">'.h($diag['reasoning']??'').'</span></div>';
        $html.=sec('Differential Diagnosis',$dd);
    }
    if(!empty($d['evidenceBasedGuidelines'])) $html.=sec('Guidelines',rl($d['evidenceBasedGuidelines']));
    if(!empty($d['riskFactors'])) $html.=sec('Risk Factors',rl($d['riskFactors']));
    if(!empty($d['recommendedWorkup'])) $html.=sec('Workup',rl($d['recommendedWorkup']));
    if(!empty($d['managementPlan'])) $html.=sec('Management',rl($d['managementPlan']));
    if(!empty($d['prognosis'])) $html.=sec('Prognosis','<span class="r-value">'.h($d['prognosis']).'</span>');
    if(!empty($d['followUpTimeline'])) $html.=sec('Follow-up Timeline','<span class="r-value">'.h($d['followUpTimeline']).'</span>');
    if(!empty($d['clinicalPearls'])) $html.=sec('Clinical Pearls',rl($d['clinicalPearls']));
    if(!empty($d['referral'])) $html.=sec('Referral','<span class="r-value">'.h($d['referral']).'</span>');
    if(!empty($d['patientEducation'])) $html.=sec('Patient Education','<span class="r-value">'.h($d['patientEducation']).'</span>');
    echo json_encode(['html'=>$html]); break;

// DIAGNOSTIC CHECK
case 'diagnostic-check':
    $d=gemini($fileContext . "Diagnostic check. Patient: ".($body['age']??'').". Symptoms: ".($body['symptoms']??'').". Vitals: ".($body['vitals']??'').". Return ONLY JSON: {\"potentialConditions\":[{\"name\":\"str\",\"probability\":\"High|Medium|Low\",\"reasoning\":\"str\"}],\"recommendedTests\":[\"str\"],\"recommendedImaging\":[\"str\"],\"laboratoryTests\":[\"str\"],\"redFlags\":[\"str\"],\"nextSteps\":\"str\",\"diagnosticCriteria\":\"str\",\"riskStratification\":\"str\",\"specialistConsultation\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='';
    if(!empty($d['redFlags'])) $html.=alert('red','<strong>Red Flags</strong>'.rl($d['redFlags']));
    if(!empty($d['potentialConditions'])){
        $pc='';
        foreach($d['potentialConditions'] as $c) $pc.='<div style="margin-bottom:6px"><div style="display:flex;align-items:center;gap:8px"><strong>'.h($c['name']??'').'</strong>'.bdg($c['probability']??'').'</div><span class="r-value-sm">'.h($c['reasoning']??'').'</span></div>';
        $html.=sec('Potential Conditions',$pc);
    }
    if(!empty($d['recommendedTests'])) $html.=sec('Tests',rl($d['recommendedTests']));
    if(!empty($d['recommendedImaging'])) $html.=sec('Imaging',rl($d['recommendedImaging']));
    if(!empty($d['laboratoryTests'])) $html.=sec('Laboratory Tests',rl($d['laboratoryTests']));
    if(!empty($d['diagnosticCriteria'])) $html.=sec('Diagnostic Criteria','<span class="r-value">'.h($d['diagnosticCriteria']).'</span>');
    if(!empty($d['riskStratification'])) $html.=sec('Risk Stratification','<span class="r-value">'.h($d['riskStratification']).'</span>');
    if(!empty($d['specialistConsultation'])) $html.=sec('Specialist Consultation','<span class="r-value">'.h($d['specialistConsultation']).'</span>');
    if(!empty($d['nextSteps'])) $html.=sec('Next Steps','<span class="r-value">'.h($d['nextSteps']).'</span>');
    echo json_encode(['html'=>$html]); break;

// SYMPTOM CHECKER
case 'symptom-checker':
    $d=gemini($fileContext . "Symptom checker triage. Patient: ".($body['age']??'')."yr ".($body['gender']??'').". Symptoms: ".($body['symptoms']??'').". Return ONLY JSON: {\"triageLevel\":\"emergency|urgent|routine|self-care\",\"summary\":\"str\",\"potentialCauses\":[\"str\"],\"durationOfSymptoms\":\"str\",\"associatedSymptoms\":[\"str\"],\"riskFactors\":[\"str\"],\"careAdvice\":[\"str\"],\"homeCareMeasures\":[\"str\"],\"urgentCareIndicators\":[\"str\"],\"emergencyIndicators\":[\"str\"],\"demographicConsiderations\":\"str\",\"whenToSeekCare\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='<div style="margin-bottom:10px"><strong>Triage Level:</strong> '.bdg($d['triageLevel']??'routine').'</div>';
    if(!empty($d['summary'])) $html.=sec('Summary','<span class="r-value">'.h($d['summary']).'</span>');
    if(!empty($d['potentialCauses'])) $html.=sec('Potential Causes',rl($d['potentialCauses']));
    if(!empty($d['durationOfSymptoms'])) $html.=sec('Duration','<span class="r-value">'.h($d['durationOfSymptoms']).'</span>');
    if(!empty($d['associatedSymptoms'])) $html.=sec('Associated Symptoms',rl($d['associatedSymptoms']));
    if(!empty($d['riskFactors'])) $html.=sec('Risk Factors',rl($d['riskFactors']));
    if(!empty($d['careAdvice'])) $html.=sec('Care Advice',rl($d['careAdvice']));
    if(!empty($d['homeCareMeasures'])) $html.=sec('Home Care',rl($d['homeCareMeasures']));
    if(!empty($d['urgentCareIndicators'])) $html.=alert('amber','<strong>Urgent Care Indicators</strong>'.rl($d['urgentCareIndicators']));
    if(!empty($d['emergencyIndicators'])) $html.=alert('red','<strong>Emergency Indicators</strong>'.rl($d['emergencyIndicators']));
    if(!empty($d['demographicConsiderations'])) $html.=sec('Demographic Considerations','<span class="r-value">'.h($d['demographicConsiderations']).'</span>');
    if(!empty($d['whenToSeekCare'])) $html.=alert('amber','<strong>When to seek care:</strong> '.h($d['whenToSeekCare']));
    echo json_encode(['html'=>$html]); break;

// ICD-10 LOOKUP
case 'icd10-lookup':
    $d=gemini($fileContext . "ICD-10 codes for: \"".($body['diagnosis']??'')."\" context: ".($body['context']??'').". Return ONLY JSON: {\"mappings\":[{\"primaryCode\":\"str\",\"description\":\"str\",\"confidence\":0.9,\"reasoning\":\"str\",\"clinicalCriteria\":\"str\",\"documentationRequirements\":\"str\",\"codingGuidelines\":\"str\",\"chapterCategory\":\"str\",\"billingImplications\":\"str\",\"secondaryCodes\":[{\"code\":\"str\",\"description\":\"str\"}]}]}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='';
    foreach(($d['mappings']??[]) as $m){
        $conf=round(($m['confidence']??0)*100);
        $html.='<div class="r-section purple"><div class="r-section-header no-collapse"><span class="r-section-title" style="font-size:18px;color:var(--purple)">'.h($m['primaryCode']??'').'</span><span class="badge badge-purple">'.$conf.'%</span></div><div class="r-section-body">';
        $html.='<span class="r-value">'.h($m['description']??'').'</span>';
        $html.='<span class="r-value-sm">'.h($m['reasoning']??'').'</span>';
        if(!empty($m['clinicalCriteria'])) $html.='<div class="r-field"><span class="r-label">Clinical Criteria</span><span class="r-value-sm">'.h($m['clinicalCriteria']).'</span></div>';
        if(!empty($m['documentationRequirements'])) $html.='<div class="r-field"><span class="r-label">Documentation</span><span class="r-value-sm">'.h($m['documentationRequirements']).'</span></div>';
        if(!empty($m['codingGuidelines'])) $html.='<div class="r-field"><span class="r-label">Coding Guidelines</span><span class="r-value-sm">'.h($m['codingGuidelines']).'</span></div>';
        if(!empty($m['chapterCategory'])) $html.='<div class="r-field"><span class="r-label">Chapter</span><span class="r-value-sm">'.h($m['chapterCategory']).'</span></div>';
        if(!empty($m['billingImplications'])) $html.='<div class="r-field"><span class="r-label">Billing</span><span class="r-value-sm">'.h($m['billingImplications']).'</span></div>';
        if(!empty($m['secondaryCodes'])){$sc='';foreach($m['secondaryCodes'] as $scx) $sc.='<span class="r-tag">'.h($scx['code']).' — '.h($scx['description']).'</span>';$html.='<div class="r-field"><span class="r-label">Secondary Codes</span><div class="r-tags">'.$sc.'</div></div>';}
        $html.='</div></div>';
    }
    echo json_encode(['html'=>$html]); break;

// SMART REPORT OIC
case 'smart-report-oic':
    $txt=$body['text']??''; $typ=$body['type']??'Radiology'; $mod=$body['modality']??'';
    $imgData=null; $imgMime=null;
    if(!empty($_FILES['scan']) && $_FILES['scan']['error']===UPLOAD_ERR_OK){
        $allowed=['image/jpeg','image/png','image/gif','image/webp'];
        $mime=mime_content_type($_FILES['scan']['tmp_name']);
        if(in_array($mime,$allowed) && $_FILES['scan']['size']<2097152){
            $imgData=base64_encode(file_get_contents($_FILES['scan']['tmp_name']));
            $imgMime=$mime;
        } else {
            echo json_encode(['html'=>alert('red','Unsupported file type or file too large (max 2 MB, JPG/PNG/WEBP).')]);exit;
        }
    }
    $prompt = $imgData
        ? "Analyze this medical {$typ} ({$mod}) image. Also consider these notes: \"{$txt}\". Return ONLY JSON: {\"studyType\":\"str\",\"clinicalFindings\":[\"str\"],\"impression\":\"str\",\"recommendations\":[\"str\"],\"severity\":\"Normal|Mild|Moderate|Severe|Critical\",\"urgency\":\"Routine|Urgent|Emergency\",\"potentialICD10\":[{\"code\":\"str\",\"description\":\"str\"}],\"followUpSuggestions\":[\"str\"],\"technique\":\"str\",\"comparisonStudy\":\"str\",\"clinicalIndication\":\"str\",\"anatomyVisualized\":[\"str\"],\"technicalQuality\":\"str\",\"incidentalFindings\":[\"str\"],\"structuredReport\":\"str\",\"imageDescription\":\"str\"}"
        : "Analyze {$typ} ({$mod}): \"{$txt}\". Return ONLY JSON: {\"studyType\":\"str\",\"clinicalFindings\":[\"str\"],\"impression\":\"str\",\"recommendations\":[\"str\"],\"severity\":\"Normal|Mild|Moderate|Severe|Critical\",\"urgency\":\"Routine|Urgent|Emergency\",\"potentialICD10\":[{\"code\":\"str\",\"description\":\"str\"}],\"followUpSuggestions\":[\"str\"],\"technique\":\"str\",\"comparisonStudy\":\"str\",\"clinicalIndication\":\"str\",\"anatomyVisualized\":[\"str\"],\"technicalQuality\":\"str\",\"incidentalFindings\":[\"str\"],\"structuredReport\":\"str\"}";
    $d = $imgData ? geminiVision($prompt, $imgData, $imgMime) : gemini($prompt);
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='<div style="display:flex;gap:6px;margin-bottom:10px">'.bdg($d['severity']??'Normal').bdg($d['urgency']??'Routine').'</div>';
    if(!empty($imgData) && !empty($d['imageDescription'])) $html.=sec('Image Analysis','<span class="r-value">'.h($d['imageDescription']).'</span>');
    if(!empty($d['clinicalIndication'])) $html.=sec('Indication','<span class="r-value">'.h($d['clinicalIndication']).'</span>');
    if(!empty($d['studyType'])) $html.=sec('Study','<span class="r-value">'.h($d['studyType']).'</span>');
    if(!empty($d['technique'])) $html.=sec('Technique','<span class="r-value">'.h($d['technique']).'</span>');
    if(!empty($d['comparisonStudy'])) $html.=sec('Comparison','<span class="r-value">'.h($d['comparisonStudy']).'</span>');
    if(!empty($d['anatomyVisualized'])) $html.=sec('Anatomy Visualized',rl($d['anatomyVisualized']));
    if(!empty($d['technicalQuality'])) $html.=sec('Technical Quality','<span class="r-value">'.h($d['technicalQuality']).'</span>');
    if(!empty($d['clinicalFindings'])) $html.=sec('Findings',rl($d['clinicalFindings']));
    if(!empty($d['incidentalFindings'])) $html.=sec('Incidental Findings',rl($d['incidentalFindings']));
    if(!empty($d['impression'])) $html.=sec('Impression','<span class="r-value">'.h($d['impression']).'</span>');
    if(!empty($d['recommendations'])) $html.=sec('Recommendations',rl($d['recommendations']));
    if(!empty($d['potentialICD10'])){$icd='';foreach($d['potentialICD10'] as $c) $icd.='<span class="r-tag" style="font-family:monospace">'.h($c['code']).' — '.h($c['description']).'</span>';$html.=sec('ICD-10 Codes','<div class="r-tags">'.$icd.'</div>');}
    if(!empty($d['followUpSuggestions'])) $html.=sec('Follow-up',rl($d['followUpSuggestions']));
    if(!empty($d['structuredReport'])) $html.=sec('Structured Report','<pre style="font-size:12px;color:#475569;white-space:pre-wrap;font-family:monospace">'.h($d['structuredReport']).'</pre>');
    echo json_encode(['html'=>$html]); break;

// REPORT COMPOSER
case 'report-composer':
    $d=gemini($fileContext . "Generate ".($body['type']??'Progress Note')." for patient: ".($body['patient']??'').". Chief complaint: ".($body['chiefComplaint']??'').". Findings: ".($body['findings']??'').". Return ONLY JSON: {\"reportTitle\":\"str\",\"subjective\":\"str\",\"objective\":\"str\",\"assessment\":\"str\",\"plan\":\"str\",\"chiefComplaint\":\"str\",\"historyOfPresentIllness\":\"str\",\"pastMedicalHistory\":\"str\",\"medicationsAtHome\":\"str\",\"socialHistory\":\"str\",\"reviewOfSystems\":\"str\",\"diagnosisList\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='<div style="font-family:monospace;font-size:13px;border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:10px"><div style="text-align:center;font-weight:700;margin-bottom:12px">'.h($d['reportTitle']??'Medical Report').'</div>';
    $html.='<div style="text-align:center;color:var(--slate5);font-size:11px;margin-bottom:12px">'.date('Y-m-d').'</div>';
    if(!empty($d['chiefComplaint'])) $html.='<div class="r-field"><span class="r-label">Chief Complaint</span><span class="r-value-sm">'.h($d['chiefComplaint']).'</span></div>';
    if(!empty($d['historyOfPresentIllness'])) $html.='<div class="r-field"><span class="r-label">HPI</span><span class="r-value-sm">'.h($d['historyOfPresentIllness']).'</span></div>';
    if(!empty($d['pastMedicalHistory'])) $html.='<div class="r-field"><span class="r-label">PMH</span><span class="r-value-sm">'.h($d['pastMedicalHistory']).'</span></div>';
    if(!empty($d['medicationsAtHome'])) $html.='<div class="r-field"><span class="r-label">Medications</span><span class="r-value-sm">'.h($d['medicationsAtHome']).'</span></div>';
    if(!empty($d['socialHistory'])) $html.='<div class="r-field"><span class="r-label">Social History</span><span class="r-value-sm">'.h($d['socialHistory']).'</span></div>';
    if(!empty($d['reviewOfSystems'])) $html.='<div class="r-field"><span class="r-label">ROS</span><span class="r-value-sm">'.h($d['reviewOfSystems']).'</span></div>';
    foreach(['subjective'=>'S — Subjective','objective'=>'O — Objective','assessment'=>'A — Assessment','plan'=>'P — Plan'] as $k=>$l) if(!empty($d[$k])) $html.='<div class="r-field"><span class="r-label">'.$l.'</span><span class="r-value-sm">'.h($d[$k]).'</span></div>';
    if(!empty($d['diagnosisList'])){$dx='<ul class="r-list">';foreach($d['diagnosisList'] as $di) $dx.='<li>'.h($di).'</li>';$dx.='</ul>';$html.='<div class="r-field"><span class="r-label">Diagnoses</span>'.$dx.'</div>';}
    $html.='<div style="text-align:center;font-size:10px;color:var(--slate5);border-top:1px solid var(--border);padding-top:8px;margin-top:8px">Generated by Arab MedTechAI</div></div>';
    echo json_encode(['html'=>$html]); break;

// LAB ANALYZER
case 'lab-analyzer':
    $d=gemini($fileContext . "Analyze lab results: ".($body['text']??'').". Return ONLY JSON: {\"summary\":\"str\",\"abnormalValues\":[{\"test\":\"str\",\"value\":\"str\",\"flag\":\"High|Low|Critical\",\"interpretation\":\"str\",\"normalRange\":\"str\"}],\"overallAssessment\":\"str\",\"recommendations\":[\"str\"],\"urgency\":\"Routine|Urgent|Emergency\",\"trendsAnalysis\":\"str\",\"criticalValues\":[\"str\"],\"organSystemImpact\":\"str\",\"medicationEffectsOnLabs\":[\"str\"],\"confirmatoryTesting\":[\"str\"],\"nutritionalAssessment\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='<div style="margin-bottom:10px"><strong>Urgency:</strong> '.bdg($d['urgency']??'Routine').'</div>';
    if(!empty($d['overallAssessment'])) $html.=sec('Overall Assessment','<span class="r-value">'.h($d['overallAssessment']).'</span>');
    if(!empty($d['summary'])) $html.=sec('Clinical Summary','<span class="r-value">'.h($d['summary']).'</span>');
    if(!empty($d['abnormalValues'])){$av='';foreach($d['abnormalValues'] as $v){$av.='<div class="r-alert r-alert-red" style="margin-bottom:6px"><div><div style="display:flex;align-items:center;gap:8px"><strong>'.h($v['test']??'').'</strong>'.bdg($v['flag']??'').' <span style="color:var(--red);font-weight:700">'.h($v['value']??'').'</span></div>';if(!empty($v['normalRange'])) $av.='<span style="font-size:11px;color:var(--slate5)">NR: '.h($v['normalRange']).'</span>';$av.='<div style="font-size:12px;margin-top:3px">'.h($v['interpretation']??'').'</div></div></div>';}$html.=sec('Abnormal Values',$av);}
    if(!empty($d['criticalValues'])) $html.=sec('Critical Values',rl($d['criticalValues']));
    if(!empty($d['trendsAnalysis'])) $html.=sec('Trends Analysis','<span class="r-value">'.h($d['trendsAnalysis']).'</span>');
    if(!empty($d['organSystemImpact'])) $html.=sec('Organ System Impact','<span class="r-value">'.h($d['organSystemImpact']).'</span>');
    if(!empty($d['medicationEffectsOnLabs'])) $html.=sec('Medication Effects on Labs',rl($d['medicationEffectsOnLabs']));
    if(!empty($d['confirmatoryTesting'])) $html.=sec('Confirmatory Testing',rl($d['confirmatoryTesting']));
    if(!empty($d['nutritionalAssessment'])) $html.=sec('Nutritional Assessment','<span class="r-value">'.h($d['nutritionalAssessment']).'</span>');
    if(!empty($d['recommendations'])) $html.=sec('Recommendations',rl($d['recommendations']));
    echo json_encode(['html'=>$html]); break;

// IMAGING READER
case 'imaging-reader':
    $mod=$body['modality']??'MRI'; $part=$body['bodyPart']??'';
    $d=gemini($fileContext . "Analyze {$mod} {$part}: ".($body['text']??'').". Return ONLY JSON: {\"studyDescription\":\"str\",\"findings\":[\"str\"],\"impression\":\"str\",\"severity\":\"Normal|Mild|Moderate|Severe|Critical\",\"urgency\":\"Routine|Urgent|Emergency\",\"recommendations\":[\"str\"],\"technique\":\"str\",\"comparisonStudy\":\"str\",\"clinicalIndication\":\"str\",\"anatomyVisualized\":[\"str\"],\"technicalQuality\":\"str\",\"incidentalFindings\":[\"str\"],\"structuredReport\":\"str\",\"clinicalCorrelation\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='<div style="display:flex;gap:6px;margin-bottom:10px">'.bdg($d['severity']??'Normal').bdg($d['urgency']??'Routine').'</div>';
    if(!empty($d['clinicalIndication'])) $html.=sec('Indication','<span class="r-value">'.h($d['clinicalIndication']).'</span>');
    if(!empty($d['studyDescription'])) $html.=sec('Study','<span class="r-value">'.h($d['studyDescription']).'</span>');
    if(!empty($d['technique'])) $html.=sec('Technique','<span class="r-value">'.h($d['technique']).'</span>');
    if(!empty($d['comparisonStudy'])) $html.=sec('Comparison','<span class="r-value">'.h($d['comparisonStudy']).'</span>');
    if(!empty($d['anatomyVisualized'])) $html.=sec('Anatomy Visualized',rl($d['anatomyVisualized']));
    if(!empty($d['technicalQuality'])) $html.=sec('Technical Quality','<span class="r-value">'.h($d['technicalQuality']).'</span>');
    if(!empty($d['findings'])) $html.=sec('Findings',rl($d['findings']));
    if(!empty($d['incidentalFindings'])) $html.=sec('Incidental Findings',rl($d['incidentalFindings']));
    if(!empty($d['impression'])) $html.=sec('Impression','<span class="r-value">'.h($d['impression']).'</span>');
    if(!empty($d['clinicalCorrelation'])) $html.=sec('Clinical Correlation','<span class="r-value">'.h($d['clinicalCorrelation']).'</span>');
    if(!empty($d['recommendations'])) $html.=sec('Recommendations',rl($d['recommendations']));
    if(!empty($d['structuredReport'])) $html.=sec('Structured Report','<pre style="font-size:12px;color:#475569;white-space:pre-wrap;font-family:monospace">'.h($d['structuredReport']).'</pre>');
    echo json_encode(['html'=>$html]); break;

// PATHOLOGY READER
case 'pathology-reader':
    $d=gemini($fileContext . "Pathology report. Specimen: ".($body['specimenType']??'Biopsy').". Report: ".($body['text']??'').". Return ONLY JSON: {\"diagnosis\":\"str\",\"specimenType\":\"str\",\"grossDescription\":\"str\",\"microscopicDescription\":\"str\",\"staging\":{\"t\":\"str\",\"n\":\"str\",\"m\":\"str\"},\"pathologicalStaging\":\"str\",\"grade\":\"str\",\"biomarkers\":[{\"name\":\"str\",\"result\":\"str\",\"interpretation\":\"str\"}],\"immunohistochemistry\":[{\"marker\":\"str\",\"result\":\"str\",\"interpretation\":\"str\"}],\"molecularTesting\":\"str\",\"tumorBurden\":\"str\",\"marginStatus\":\"str\",\"lymphovascularInvasion\":\"str\",\"recommendations\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='<div class="r-value-lg" style="margin-bottom:8px">'.h($d['diagnosis']??'').'</div>';
    if(!empty($d['specimenType'])) $html.=sec('Specimen','<span class="r-value">'.h($d['specimenType']).'</span>');
    if(!empty($d['grossDescription'])) $html.=sec('Gross Description','<span class="r-value">'.h($d['grossDescription']).'</span>');
    if(!empty($d['microscopicDescription'])) $html.=sec('Microscopic','<span class="r-value">'.h($d['microscopicDescription']).'</span>');
    if(!empty($d['staging']['t'])) $html.=sec('TNM Staging','<span class="r-value">T: '.h($d['staging']['t']).' | N: '.h($d['staging']['n']).' | M: '.h($d['staging']['m']).'</span>');
    if(!empty($d['pathologicalStaging'])) $html.=sec('Pathological Staging','<span class="r-value">'.h($d['pathologicalStaging']).'</span>');
    if(!empty($d['grade'])) $html.=sec('Grade','<span class="r-value">'.h($d['grade']).'</span>');
    if(!empty($d['tumorBurden'])) $html.=sec('Tumor Burden','<span class="r-value">'.h($d['tumorBurden']).'</span>');
    if(!empty($d['marginStatus'])) $html.=sec('Margin Status','<span class="r-value">'.h($d['marginStatus']).'</span>');
    if(!empty($d['lymphovascularInvasion'])) $html.=sec('Lymphovascular Invasion','<span class="r-value">'.h($d['lymphovascularInvasion']).'</span>');
    if(!empty($d['immunohistochemistry'])){$ihc='';foreach($d['immunohistochemistry'] as $ic) $ihc.='<div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border-light);font-size:13px"><span>'.h($ic['marker']??'').'</span><span style="font-weight:600">'.h($ic['result']??'').(!empty($ic['interpretation'])?' <span style="font-size:11px;color:var(--slate5)">('.h($ic['interpretation']).')</span>':'').'</span></div>';$html.=sec('IHC Markers',$ihc);}
    if(!empty($d['biomarkers'])){$bms='';foreach($d['biomarkers'] as $b) $bms.='<div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border-light);font-size:13px"><span>'.h($b['name']??'').'</span><span style="font-weight:600">'.h($b['result']??'').'</span></div>';$html.=sec('Biomarkers',$bms);}
    if(!empty($d['molecularTesting'])) $html.=sec('Molecular Testing','<span class="r-value">'.h($d['molecularTesting']).'</span>');
    if(!empty($d['recommendations'])) $html.=sec('Recommendations',rl($d['recommendations']));
    echo json_encode(['html'=>$html]); break;

// DISCHARGE SUMMARY
case 'discharge-summary':
    $d=gemini($fileContext . "Discharge summary. Diagnosis: ".($body['diagnosis']??'').". Course: ".($body['course']??'').". Meds: ".($body['medications']??'').". Return ONLY JSON: {\"diagnoses\":{\"primary\":\"str\",\"secondary\":[\"str\"]},\"hospitalCourse\":\"str\",\"procedures\":[\"str\"],\"dischargeMedications\":[{\"name\":\"str\",\"dose\":\"str\",\"frequency\":\"str\",\"duration\":\"str\"}],\"followUpPlan\":[\"str\"],\"patientInstructions\":\"str\",\"admissionDate\":\"str\",\"dischargeDate\":\"str\",\"attendingPhysician\":\"str\",\"dischargeDisposition\":\"str\",\"pendingResults\":[\"str\"],\"conditionAtDischarge\":\"str\",\"functionalStatus\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='<div class="r-value-lg" style="margin-bottom:8px">'.h($d['diagnoses']['primary']??'').'</div>';
    if(!empty($d['diagnoses']['secondary'])) $html.=sec('Secondary Diagnoses',rl($d['diagnoses']['secondary']));
    if(!empty($d['admissionDate']) || !empty($d['dischargeDate'])) $html.=sec('Dates','<span class="r-value">Admit: '.h($d['admissionDate']??'—').' | Discharge: '.h($d['dischargeDate']??'—').'</span>');
    if(!empty($d['attendingPhysician'])) $html.=sec('Attending','<span class="r-value">'.h($d['attendingPhysician']).'</span>');
    if(!empty($d['dischargeDisposition'])) $html.=sec('Disposition','<span class="r-value">'.h($d['dischargeDisposition']).'</span>');
    if(!empty($d['hospitalCourse'])) $html.=sec('Hospital Course','<span class="r-value">'.h($d['hospitalCourse']).'</span>');
    if(!empty($d['procedures'])) $html.=sec('Procedures',rl($d['procedures']));
    if(!empty($d['conditionAtDischarge'])) $html.=sec('Condition at Discharge','<span class="r-value">'.h($d['conditionAtDischarge']).'</span>');
    if(!empty($d['functionalStatus'])) $html.=sec('Functional Status','<span class="r-value">'.h($d['functionalStatus']).'</span>');
    if(!empty($d['dischargeMedications'])){$dm='';foreach($d['dischargeMedications'] as $mx) $dm.='<div style="margin-bottom:6px;border-bottom:1px solid var(--border-light);padding-bottom:6px"><strong>'.h($mx['name']??'').'</strong><br><span style="font-size:12px;color:var(--slate4)">'.h($mx['dose']??'').' — '.h($mx['frequency']??'').' × '.h($mx['duration']??'').'</span></div>';$html.=sec('Discharge Medications',$dm);}
    if(!empty($d['pendingResults'])) $html.=sec('Pending Results',rl($d['pendingResults']));
    if(!empty($d['followUpPlan'])) $html.=sec('Follow-up',rl($d['followUpPlan']));
    if(!empty($d['patientInstructions'])) $html.=sec('Patient Instructions','<span class="r-value">'.h($d['patientInstructions']).'</span>');
    echo json_encode(['html'=>$html]); break;

// CLINICAL NOTES
case 'clinical-notes':
    $d=gemini($fileContext . "Generate ".($body['noteType']??'SOAP Note')." for ".($body['age']??'')."yr ".($body['gender']??'').". Info: ".($body['info']??'').". Return ONLY JSON: {\"noteType\":\"str\",\"subjective\":\"str\",\"objective\":{\"vitalSigns\":\"str\",\"physicalExam\":\"str\"},\"assessment\":\"str\",\"plan\":\"str\",\"icd10Suggestions\":[\"str\"],\"reviewOfSystems\":\"str\",\"medicationList\":[\"str\"],\"allergies\":[\"str\"],\"pastMedicalHistory\":\"str\",\"familyHistory\":\"str\",\"socialHistory\":\"str\",\"assessmentPlanDetailed\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='<div style="font-family:monospace;font-size:13px;border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:10px"><div style="text-align:center;font-weight:700;margin-bottom:12px">'.h($d['noteType']??'Clinical Note').'</div>';
    if(!empty($d['subjective'])) $html.='<div class="r-field"><span class="r-label">S — Subjective</span><span class="r-value-sm">'.h($d['subjective']).'</span></div>';
    if(!empty($d['reviewOfSystems'])) $html.='<div class="r-field"><span class="r-label">ROS</span><span class="r-value-sm">'.h($d['reviewOfSystems']).'</span></div>';
    if(!empty($d['pastMedicalHistory'])) $html.='<div class="r-field"><span class="r-label">PMH</span><span class="r-value-sm">'.h($d['pastMedicalHistory']).'</span></div>';
    if(!empty($d['familyHistory'])) $html.='<div class="r-field"><span class="r-label">Family History</span><span class="r-value-sm">'.h($d['familyHistory']).'</span></div>';
    if(!empty($d['socialHistory'])) $html.='<div class="r-field"><span class="r-label">Social History</span><span class="r-value-sm">'.h($d['socialHistory']).'</span></div>';
    if(!empty($d['medicationList'])){$ml='<ul class="r-list">';foreach($d['medicationList'] as $med) $ml.='<li>'.h($med).'</li>';$ml.='</ul>';$html.='<div class="r-field"><span class="r-label">Medications</span>'.$ml.'</div>';}
    if(!empty($d['allergies'])){$al='<ul class="r-list">';foreach($d['allergies'] as $a) $al.='<li style="color:var(--red)">'.h($a).'</li>';$al.='</ul>';$html.='<div class="r-field"><span class="r-label">Allergies</span>'.$al.'</div>';}
    if(!empty($d['objective'])) $html.='<div class="r-field"><span class="r-label">O — Objective</span><span class="r-value-sm"><strong>Vitals:</strong> '.h($d['objective']['vitalSigns']??'').'<br><strong>Exam:</strong> '.h($d['objective']['physicalExam']??'').'</span></div>';
    if(!empty($d['assessment'])) $html.='<div class="r-field"><span class="r-label">A — Assessment</span><span class="r-value-sm">'.h($d['assessment']).'</span></div>';
    if(!empty($d['plan'])) $html.='<div class="r-field"><span class="r-label">P — Plan</span><span class="r-value-sm">'.h($d['plan']).'</span></div>';
    if(!empty($d['assessmentPlanDetailed'])) $html.='<div class="r-field"><span class="r-label">A/P Detailed</span><span class="r-value-sm">'.h($d['assessmentPlanDetailed']).'</span></div>';
    $html.='</div>';
    if(!empty($d['icd10Suggestions'])){$icd='';foreach($d['icd10Suggestions'] as $c) $icd.='<span class="r-tag" style="font-family:monospace">'.h($c).'</span>';$html.='<div style="margin-top:10px"><span class="r-label">ICD-10 suggestions</span><div class="r-tags">'.$icd.'</div></div>';}
    echo json_encode(['html'=>$html]); break;

// MEDICATION SAFETY
case 'medication-safety':
    $d=gemini($fileContext . "Medication safety: ".($body['drug']??'').". Allergies: ".($body['allergies']??'').". Current meds: ".($body['currentMeds']??'').". Return ONLY JSON: {\"overallSafety\":\"Safe|Caution|High Risk\",\"lasaRisk\":\"Yes|No\",\"highAlert\":\"Yes|No\",\"allergyConflict\":\"str or null\",\"interactions\":[{\"drug\":\"str\",\"severity\":\"str\",\"description\":\"str\"}],\"safetyRecommendations\":[\"str\"],\"monitoringParameters\":[\"str\"],\"duplicateTherapy\":[\"str\"],\"dosingErrors\":[\"str\"],\"renalDosingAdjustment\":\"str\",\"hepaticDosingAdjustment\":\"str\",\"pregnancyRiskAssessment\":\"str\",\"geriatricConsiderations\":\"str\",\"pediatricConsiderations\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='<div style="display:flex;gap:6px;margin-bottom:10px">'.bdg($d['overallSafety']??'Caution');
    if(($d['highAlert']??'')=='Yes') $html.='<span class="badge badge-red">HIGH ALERT</span>';
    if(($d['lasaRisk']??'')=='Yes') $html.='<span class="badge badge-amber">LASA Risk</span>';
    $html.='</div>';
    if(!empty($d['allergyConflict'])) $html.=alert('red','<strong>Allergy Conflict:</strong> '.h($d['allergyConflict']));
    if(!empty($d['interactions'])){$ixs='';foreach($d['interactions'] as $ix) $ixs.='<div style="margin-bottom:6px"><div style="display:flex;align-items:center;gap:8px"><strong>'.h($ix['drug']??'').'</strong>'.bdg($ix['severity']??'').'</div><span class="r-value-sm">'.h($ix['description']??'').'</span></div>';$html.=sec('Interactions',$ixs);}
    if(!empty($d['duplicateTherapy'])) $html.=sec('Duplicate Therapy',rl($d['duplicateTherapy']));
    if(!empty($d['dosingErrors'])) $html.=sec('Dosing Errors',rl($d['dosingErrors']));
    if(!empty($d['renalDosingAdjustment'])) $html.=sec('Renal Adjustment','<span class="r-value">'.h($d['renalDosingAdjustment']).'</span>');
    if(!empty($d['hepaticDosingAdjustment'])) $html.=sec('Hepatic Adjustment','<span class="r-value">'.h($d['hepaticDosingAdjustment']).'</span>');
    if(!empty($d['pregnancyRiskAssessment'])) $html.=sec('Pregnancy Risk','<span class="r-value">'.h($d['pregnancyRiskAssessment']).'</span>');
    if(!empty($d['geriatricConsiderations'])) $html.=sec('Geriatric Considerations','<span class="r-value">'.h($d['geriatricConsiderations']).'</span>');
    if(!empty($d['pediatricConsiderations'])) $html.=sec('Pediatric Considerations','<span class="r-value">'.h($d['pediatricConsiderations']).'</span>');
    if(!empty($d['safetyRecommendations'])) $html.=sec('Recommendations',rl($d['safetyRecommendations']));
    if(!empty($d['monitoringParameters'])) $html.=sec('Monitoring',rl($d['monitoringParameters']));
    echo json_encode(['html'=>$html]); break;

// FORMULARY
case 'formulary':
    $d=gemini($fileContext . "Formulary search: ".($body['query']??'').". Status: ".($body['status']??'').". Route: ".($body['route']??'').". Return ONLY JSON: {\"results\":[{\"name\":\"str\",\"genericName\":\"str\",\"formularyStatus\":\"Formulary|Non-Formulary|Restricted\",\"route\":\"str\",\"therapeuticClass\":\"str\",\"restrictions\":\"str\",\"alternatives\":[\"str\"],\"costInformation\":\"str\",\"copayLevel\":\"str\",\"priorAuthorizationRequired\":\"Yes|No\",\"stepTherapyRequired\":\"Yes|No\",\"quantityLimits\":\"str\",\"formularyTier\":\"str\"}]}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='';
    foreach(($d['results']??[]) as $r){
        $html.='<div class="r-section primary"><div class="r-section-header no-collapse"><span class="r-section-title">'.h($r['name']??'').'</span>'.bdg($r['formularyStatus']??'').'</div><div class="r-section-body">';
        $html.='<span class="r-value-sm">'.h($r['genericName']??'').'</span>';
        $html.='<div style="font-size:12px;color:var(--slate4);margin-bottom:4px">'.h($r['therapeuticClass']??'').' — '.h($r['route']??'').'</div>';
        if(!empty($r['formularyTier'])) $html.='<div class="r-field"><span class="r-label">Tier</span><span class="r-value-sm">'.h($r['formularyTier']).'</span></div>';
        if(!empty($r['costInformation'])) $html.='<div class="r-field"><span class="r-label">Cost</span><span class="r-value-sm">'.h($r['costInformation']).'</span></div>';
        if(!empty($r['copayLevel'])) $html.='<div class="r-field"><span class="r-label">Copay</span><span class="r-value-sm">'.h($r['copayLevel']).'</span></div>';
        if(!empty($r['priorAuthorizationRequired']) && $r['priorAuthorizationRequired']=='Yes') $html.='<span class="badge badge-amber">PA Required</span>';
        if(!empty($r['stepTherapyRequired']) && $r['stepTherapyRequired']=='Yes') $html.='<span class="badge badge-amber">Step Therapy</span>';
        if(!empty($r['quantityLimits'])) $html.='<span class="badge badge-gray">QL: '.h($r['quantityLimits']).'</span>';
        if(!empty($r['restrictions'])) $html.='<div class="r-alert r-alert-amber" style="margin-top:6px">'.h($r['restrictions']).'</div>';
        if(!empty($r['alternatives'])) $html.='<div style="font-size:12px;color:var(--slate4);margin-top:6px">Alternatives: '.h(implode(', ',$r['alternatives'])).'</div>';
        $html.='</div></div>';
    }
    echo json_encode(['html'=>$html?:'<span class="r-value-sm">No results found.</span>']); break;

// IV COMPATIBILITY
case 'iv-compatibility':
    $drugs=implode(', ',array_filter([$body['drugA']??'',$body['drugB']??'',$body['drugC']??'']));
    $d=gemini($fileContext . "IV compatibility for: {$drugs} in ".($body['diluent']??'Normal Saline').". Return ONLY JSON: {\"overallCompatibility\":\"Compatible|Incompatible|Conditionally Compatible\",\"pairs\":[{\"drug1\":\"str\",\"drug2\":\"str\",\"compatibility\":\"Compatible|Incompatible|Conditionally Compatible\",\"evidence\":\"str\",\"notes\":\"str\"}],\"recommendation\":\"str\",\"alternativeApproach\":\"str\",\"ySiteCompatibility\":\"str\",\"syringeCompatibility\":\"str\",\"stabilityData\":\"str\",\"lightSensitivity\":\"str\",\"concentrationDependentInfo\":\"str\",\"administrationGuidelines\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $ov=$d['overallCompatibility']??'Unknown';
    $html='<div style="text-align:center;margin-bottom:10px">'.bdg($ov).'</div>';
    foreach(($d['pairs']??[]) as $p){
        $html.='<div class="r-section primary"><div class="r-section-header no-collapse"><span class="r-section-title">'.h($p['drug1']??'').' + '.h($p['drug2']??'').'</span>'.bdg($p['compatibility']??'').'</div><div class="r-section-body">';
        if(!empty($p['evidence'])) $html.='<span class="r-value-sm">'.h($p['evidence']).'</span>';
        if(!empty($p['notes'])) $html.='<span class="r-value-sm" style="color:var(--blue)">'.h($p['notes']).'</span>';
        $html.='</div></div>';
    }
    if(!empty($d['ySiteCompatibility'])) $html.=sec('Y-Site Compatibility','<span class="r-value">'.h($d['ySiteCompatibility']).'</span>');
    if(!empty($d['syringeCompatibility'])) $html.=sec('Syringe Compatibility','<span class="r-value">'.h($d['syringeCompatibility']).'</span>');
    if(!empty($d['stabilityData'])) $html.=sec('Stability Data','<span class="r-value">'.h($d['stabilityData']).'</span>');
    if(!empty($d['lightSensitivity'])) $html.=sec('Light Sensitivity','<span class="r-value">'.h($d['lightSensitivity']).'</span>');
    if(!empty($d['concentrationDependentInfo'])) $html.=sec('Concentration-Dependent','<span class="r-value">'.h($d['concentrationDependentInfo']).'</span>');
    if(!empty($d['administrationGuidelines'])) $html.=sec('Administration Guidelines',rl($d['administrationGuidelines']));
    if(!empty($d['recommendation'])) $html.=sec('Recommendation','<span class="r-value">'.h($d['recommendation']).'</span>');
    if(!empty($d['alternativeApproach'])) $html.=alert('blue',h($d['alternativeApproach']));
    echo json_encode(['html'=>$html]); break;

// CLINICAL PATHWAYS
case 'clinical-pathways':
    $d=gemini($fileContext . "Clinical pathway for: ".($body['condition']??'').". Return ONLY JSON: {\"condition\":\"str\",\"overview\":\"str\",\"initialAssessment\":[\"str\"],\"diagnosticWorkup\":[\"str\"],\"treatmentSteps\":[{\"step\":1,\"action\":\"str\",\"timeframe\":\"str\",\"notes\":\"str\"}],\"monitoring\":[\"str\"],\"inclusionCriteria\":[\"str\"],\"exclusionCriteria\":[\"str\"],\"outcomeMeasures\":[\"str\"],\"dischargeCriteria\":[\"str\"],\"followUpSchedule\":\"str\",\"referralCriteria\":[\"str\"],\"patientResources\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='<div class="r-value-lg" style="margin-bottom:8px">'.h($d['condition']??'').'</div>';
    if(!empty($d['overview'])) $html.=sec('Overview','<span class="r-value">'.h($d['overview']).'</span>');
    if(!empty($d['inclusionCriteria'])) $html.=sec('Inclusion Criteria',rl($d['inclusionCriteria']));
    if(!empty($d['exclusionCriteria'])) $html.=sec('Exclusion Criteria',rl($d['exclusionCriteria']));
    if(!empty($d['initialAssessment'])) $html.=sec('Initial Assessment',rl($d['initialAssessment']));
    if(!empty($d['diagnosticWorkup'])) $html.=sec('Diagnostic Workup',rl($d['diagnosticWorkup']));
    if(!empty($d['treatmentSteps'])){$ts='';foreach($d['treatmentSteps'] as $step) $ts.='<div style="display:flex;gap:10px;margin-bottom:8px"><div style="flex-shrink:0;width:24px;height:24px;background:var(--blue);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700">'.h((string)($step['step']??'')).'</div><div><strong style="font-size:14px">'.h($step['action']??'').'</strong><br><span style="font-size:11px;color:var(--slate4)">'.h($step['timeframe']??'').'</span>'.(!empty($step['notes'])?'<br><span style="font-size:12px;color:var(--slate3)">'.h($step['notes']).'</span>':'').'</div></div>';$html.=sec('Treatment Steps',$ts);}
    if(!empty($d['outcomeMeasures'])) $html.=sec('Outcome Measures',rl($d['outcomeMeasures']));
    if(!empty($d['dischargeCriteria'])) $html.=sec('Discharge Criteria',rl($d['dischargeCriteria']));
    if(!empty($d['followUpSchedule'])) $html.=sec('Follow-up Schedule','<span class="r-value">'.h($d['followUpSchedule']).'</span>');
    if(!empty($d['referralCriteria'])) $html.=sec('Referral Criteria',rl($d['referralCriteria']));
    if(!empty($d['monitoring'])) $html.=sec('Monitoring',rl($d['monitoring']));
    if(!empty($d['patientResources'])) $html.=sec('Patient Resources',rl($d['patientResources']));
    echo json_encode(['html'=>$html]); break;

// CLINICAL CALCULATORS
case 'clinical-calculators':
    $d=gemini($fileContext . "Calculate ".($body['calculator']??'')." score. Data: ".($body['data']??'').". Return ONLY JSON: {\"calculator\":\"str\",\"result\":\"str\",\"score\":\"str\",\"interpretation\":\"str\",\"riskCategory\":\"str\",\"recommendations\":[\"str\"],\"calculationMethodology\":\"str\",\"variablesUsed\":[\"str\"],\"clinicalApplication\":\"str\",\"evidenceLevel\":\"str\",\"validationStudies\":\"str\",\"caveats\":[\"str\"]}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='<div style="font-size:28px;font-weight:900;margin-bottom:4px">'.h($d['result']??$d['score']??'').'</div>';
    if(!empty($d['riskCategory'])) $html.='<div style="margin-bottom:8px">'.bdg($d['riskCategory']).'</div>';
    if(!empty($d['interpretation'])) $html.=sec('Interpretation','<span class="r-value">'.h($d['interpretation']).'</span>');
    if(!empty($d['calculationMethodology'])) $html.=sec('Methodology','<span class="r-value">'.h($d['calculationMethodology']).'</span>');
    if(!empty($d['variablesUsed'])) $html.=sec('Variables',rl($d['variablesUsed']));
    if(!empty($d['clinicalApplication'])) $html.=sec('Clinical Application','<span class="r-value">'.h($d['clinicalApplication']).'</span>');
    if(!empty($d['evidenceLevel'])) $html.=sec('Evidence Level','<span class="r-value">'.h($d['evidenceLevel']).'</span>');
    if(!empty($d['validationStudies'])) $html.=sec('Validation Studies','<span class="r-value">'.h($d['validationStudies']).'</span>');
    if(!empty($d['caveats'])) $html.=sec('Caveats',rl($d['caveats']));
    if(!empty($d['recommendations'])) $html.=sec('Recommendations',rl($d['recommendations']));
    echo json_encode(['html'=>$html]); break;

// STEWARDSHIP
case 'stewardship':
    $d=gemini($fileContext . "AMS review. Antibiotic: ".($body['antibiotic']??'').". Indication: ".($body['indication']??'').". Culture: ".($body['culture']??'').". Day ".($body['dayOfTherapy']??'')." of therapy. Return ONLY JSON: {\"appropriateness\":\"Appropriate|Inappropriate|Needs Review\",\"recommendation\":\"Continue|De-escalate|Discontinue|Modify\",\"justification\":\"str\",\"deEscalationOption\":\"str\",\"suggestedDuration\":\"str\",\"monitoringParameters\":[\"str\"],\"resistanceRisk\":\"Low|Moderate|High\",\"durationAssessment\":\"str\",\"cultureResults\":\"str\",\"sensitivityPatterns\":\"str\",\"ivToOralConversion\":\"str\",\"renalDosingAdjustment\":\"str\",\"allergyAssessment\":\"str\",\"stewardshipCategory\":\"str\"}");
    if(isset($d['error'])){echo json_encode(['html'=>alert('red',h($d['error']))]);exit;}
    $html='<div style="display:flex;gap:6px;margin-bottom:10px">'.bdg($d['appropriateness']??'Needs Review').bdg($d['recommendation']??'').'</div>';
    if(!empty($d['justification'])) $html.=sec('Justification','<span class="r-value">'.h($d['justification']).'</span>');
    if(!empty($d['stewardshipCategory'])) $html.=sec('AMS Category','<span class="r-value">'.h($d['stewardshipCategory']).'</span>');
    if(!empty($d['cultureResults'])) $html.=sec('Culture Results','<span class="r-value">'.h($d['cultureResults']).'</span>');
    if(!empty($d['sensitivityPatterns'])) $html.=sec('Sensitivity Patterns','<span class="r-value">'.h($d['sensitivityPatterns']).'</span>');
    if(!empty($d['durationAssessment'])) $html.=sec('Duration Assessment','<span class="r-value">'.h($d['durationAssessment']).'</span>');
    if(!empty($d['deEscalationOption'])) $html.=alert('green','<strong>De-escalation:</strong> '.h($d['deEscalationOption']));
    if(!empty($d['ivToOralConversion'])) $html.=sec('IV-to-Oral Conversion','<span class="r-value">'.h($d['ivToOralConversion']).'</span>');
    if(!empty($d['renalDosingAdjustment'])) $html.=sec('Renal Adjustment','<span class="r-value">'.h($d['renalDosingAdjustment']).'</span>');
    if(!empty($d['suggestedDuration'])) $html.=sec('Duration','<span class="r-value">'.h($d['suggestedDuration']).'</span>');
    if(!empty($d['allergyAssessment'])) $html.=sec('Allergy Assessment','<span class="r-value">'.h($d['allergyAssessment']).'</span>');
    if(!empty($d['monitoringParameters'])) $html.=sec('Monitoring',rl($d['monitoringParameters']));
    if(!empty($d['resistanceRisk'])) $html.=sec('Resistance Risk','<span class="badge '.(['Low'=>'badge-green','Moderate'=>'badge-amber','High'=>'badge-red'][$d['resistanceRisk']]??'badge-gray').'">'.h($d['resistanceRisk']).'</span>');
    echo json_encode(['html'=>$html]); break;

default:
    http_response_code(404);
    echo json_encode(['error'=>'Unknown tool: '.$tool]);
    break;
}