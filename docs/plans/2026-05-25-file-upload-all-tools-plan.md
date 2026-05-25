# File Upload for All 23 Tools — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add drag-drop file upload (images + PDFs) to all 23 tool pages with centralized PHP handling.

**Architecture:** One Gemini Vision call at the top of handler.php extracts text from uploaded files; that text gets prepended to each tool's AI prompt. Client-side resize + magic byte validation for safety.

**Tech Stack:** PHP (Gemini Vision API), vanilla JS (canvas resize, FormData), magic byte validation

---

### Task 1: PHP — Centralized file upload handler

**Files:**
- Modify: `api/tools/handler.php` (top section, after line 17 `foreach ($_POST ...)`)

**Step 1: Insert file detection + magic byte validation + Vision extraction**

Replace:
```php
foreach ($_POST as $k => $v) $body[$k] = $v;
```

With:
```php
foreach ($_POST as $k => $v) $body[$k] = $v;

// Centralized file upload handler
$upData = null; $upMime = null; $upText = null;
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
    if ($f['size'] > 5242880) continue; // 5 MB
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
```

**Step 2: Verify parse**

Run: `php -l api/tools/handler.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/tools/handler.php
git commit -m "feat: add centralized file upload handler with magic byte validation"
```

---

### Task 2: PHP — Inject file context into 22 tool prompts

**Files:**
- Modify: `api/tools/handler.php` (22 tool cases)

**Pattern for each tool case:**

Find lines like:
```php
$d=gemini("Your prompt " . ($body['field']??'') . " ...");
```

Replace with:
```php
$ft = ($body['_fileText']??'') ? "Uploaded document context:\n" . $body['_fileText'] . "\n\n---\n\n" : '';
$d=gemini($ft . "Your prompt " . ($body['field']??'') . " ...");
```

**Tools to update (22 cases):**

| Tool | Line ~ | Pattern to find |
|---|---|---|
| drug-search | 177 | `$d=gemini("Drug info for: "` |
| interaction-checker | 241 | already broken across lines; add `$ft` to prompt |
| dose-calculator | 261 | `$d=gemini("Dose for: "` |
| pregnancy-safety | 283 | `$d=gemini("Pregnancy safety for: "` |
| g6pd-checker | 305 | `$d=gemini("G6PD safety for: "` |
| drug-comparison | 323 | `$d=gemini("Compare drugs: "` |
| clinical-decision-support | 343 | `$d=gemini("Clinical decision support."` |
| diagnostic-check | 365 | `$d=gemini("Diagnostic check."` |
| symptom-checker | 385 | `$d=gemini("Symptom checker triage."` |
| icd10-lookup | 403 | `$d=gemini("ICD-10 codes for: "` |
| report-composer | 459 | `$d=gemini("Generate "` |
| lab-analyzer | 476 | `$d=gemini("Analyze lab results: "` |
| imaging-reader | 494 | `$d=gemini("Analyze ` |
| pathology-reader | 513 | `$d=gemini("Pathology report."` |
| discharge-summary | 533 | `$d=gemini("Discharge summary."` |
| clinical-notes | 552 | `$d=gemini("Generate "` |
| medication-safety | 572 | `$d=gemini("Medication safety: "` |
| formulary | 593 | `$d=gemini("Formulary search: "` |
| iv-compatibility | 615 | `$d=gemini("IV compatibility for: "` |
| clinical-pathways | 637 | `$d=gemini("Clinical pathway for: "` |
| clinical-calculators | 656 | `$d=gemini("Calculate "` |
| stewardship | 672 | `$d=gemini("AMS review."` |

**Skip:** smart-report-oic (line 422) — already handles file upload with `geminiVision()`

**Step 1: Edit each tool case**

For each of the 22 tools, prepend the `$ft` variable and inject it into the prompt.

```php
$ft = ($body['_fileText']??'') ? "Uploaded document context:\n" . $body['_fileText'] . "\n\n---\n\n" : '';
$d = gemini($ft . "...existing prompt...");
```

**Step 2: Verify syntax**

Run: `php -l api/tools/handler.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/tools/handler.php
git commit -m "feat: inject file upload context into 22 tool prompts"
```

---

### Task 3: HTML — Create shared upload area snippet

**Files:**
- Create: `tools/_upload.html`

**Step 1: Write the shared upload area HTML + JS**

```html
<!-- Shared file upload area — include in tool pages -->
<div class="field">
  <label>Upload File <span style="font-weight:400;color:var(--slate4)">(optional — photo or PDF)</span></label>
  <div class="upload-area" id="upArea" onclick="document.getElementById('upFile').click()">
    <input type="file" id="upFile" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf" style="display:none" onchange="handleUpload(this)">
    <div id="upPlaceholder"><span>Click or drag a photo or PDF here (max 5 MB)</span></div>
    <div id="upPreview" style="display:none">
      <img id="upImg" style="max-height:140px;border:1px solid var(--border);border-radius:3px;display:block;margin-bottom:6px">
      <div id="upName" style="font-size:11px;color:var(--slate4);margin-bottom:4px"></div>
      <button type="button" class="btn btn-outline btn-sm" onclick="event.stopPropagation();removeUpload()">Remove</button>
    </div>
  </div>
</div>
<noscript><p style="font-size:12px;color:var(--slate4)">JavaScript required for file upload.</p></noscript>
```

**Step 2: Commit**

```bash
git add tools/_upload.html
git commit -m "feat: create shared upload area snippet"
```

---

### Task 4: JS — Shared upload functions

**Files:**
- Create: `tools/_upload.js`

**Step 1: Write the shared upload JS**

```javascript
// Shared file upload handlers — include in tool pages
var _upFile = null;
function handleUpload(input) {
  if (!input.files || !input.files[0]) { removeUpload(); return; }
  var f = input.files[0];
  if (f.size > 5242880) { alert('File too large (max 5 MB).'); input.value = ''; return; }
  var okTypes = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
  if (!okTypes.includes(f.type)) { alert('Unsupported file type. Use JPG, PNG, WEBP, or PDF.'); input.value = ''; return; }
  _upFile = f;
  document.getElementById('upPlaceholder').style.display = 'none';
  var pv = document.getElementById('upPreview');
  pv.style.display = 'block';
  // Resize large images client-side
  if (f.type.startsWith('image/')) {
    var r = new FileReader();
    r.onload = function(e) {
      var img = new Image();
      img.onload = function() {
        var maxDim = 2000;
        if (img.width > maxDim || img.height > maxDim) {
          var c = document.createElement('canvas');
          var scale = Math.min(maxDim / img.width, maxDim / img.height);
          c.width = Math.round(img.width * scale);
          c.height = Math.round(img.height * scale);
          var ctx = c.getContext('2d');
          ctx.drawImage(img, 0, 0, c.width, c.height);
          c.toBlob(function(blob) { _upFile = new File([blob], f.name, {type: f.type}); }, f.type, 0.85);
        }
        document.getElementById('upImg').src = e.target.result;
      };
      img.src = e.target.result;
    };
    r.readAsDataURL(f);
  } else {
    document.getElementById('upImg').style.display = 'none';
  }
  document.getElementById('upName').textContent = f.name;
}
function removeUpload() {
  _upFile = null;
  var inp = document.getElementById('upFile');
  if (inp) inp.value = '';
  var ph = document.getElementById('upPlaceholder');
  if (ph) ph.style.display = '';
  var pv = document.getElementById('upPreview');
  if (pv) pv.style.display = 'none';
}
// Drag-drop
document.addEventListener('DOMContentLoaded', function() {
  var ua = document.getElementById('upArea');
  if (ua) {
    ua.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('drag-over'); });
    ua.addEventListener('dragleave', function() { this.classList.remove('drag-over'); });
    ua.addEventListener('drop', function(e) {
      e.preventDefault(); this.classList.remove('drag-over');
      var dt = e.dataTransfer;
      if (dt.files && dt.files[0]) {
        document.getElementById('upFile').files = dt.files;
        handleUpload(document.getElementById('upFile'));
      }
    });
  }
});
```

**Step 2: Commit**

```bash
git add tools/_upload.js
git commit -m "feat: create shared upload JS with client-side resize"
```

---

### Task 5: HTML — Include upload area in 23 tool pages

**Files:**
- Modify: All 23 `tools/*/index.html` files
- The include is done by inserting the `_upload.html` content and a `<script src="/tools/_upload.js">` tag

**Step 1: Add to each tool page's form**

For each tool page, after the last form field and before the Run Tool button, insert:
```html
<?php include '../_upload.html'; ?>
```

Since tool pages are `.html`, either:
- Option A: Rename to `.php` (breaks existing PHP includes if any, needs server config)
- Option B: Server-Side Include via `.shtml` (needs Apache config)
- Option C: **Simplest** — Just paste the HTML directly and use `<script src="/tools/_upload.js">`

**Recommendation: Option C** — Paste the upload HTML snippet directly and add script tag. No rename needed.

Upload HTML to paste (before submit button):
```html
<div class="field">
  <label>Upload File <span style="font-weight:400;color:var(--slate4)">(optional — photo or PDF)</span></label>
  <div class="upload-area" id="upArea" onclick="document.getElementById('upFile').click()">
    <input type="file" id="upFile" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf" style="display:none" onchange="handleUpload(this)">
    <div id="upPlaceholder"><span>Click or drag a photo or PDF here (max 5 MB)</span></div>
    <div id="upPreview" style="display:none">
      <img id="upImg" style="max-height:140px;border:1px solid var(--border);border-radius:3px;display:block;margin-bottom:6px">
      <div id="upName" style="font-size:11px;color:var(--slate4);margin-bottom:4px"></div>
      <button type="button" class="btn btn-outline btn-sm" onclick="event.stopPropagation();removeUpload()">Remove</button>
    </div>
  </div>
</div>
```

Add before `</head>`:
```html
<script src="/tools/_upload.js"></script>
```

**Step 2: Modify each tool's `getFormData()` and `runTool()`**

Pattern change for each tool:
- Currently returns a plain object
- Need to check `_upFile` and return FormData if present

Example (generic template for each tool):

In `getFormData()`:
```javascript
function getFormData() {
  var d = { ... existing fields ... };
  if (!validate(d)) return null;
  if (_upFile) {
    var fd = new FormData();
    for (var k in d) fd.append(k, d[k]);
    fd.append('file', _upFile);
    return fd;
  }
  return d;
}
```

In `runTool()`, change fetch call:
```javascript
var opts = { method: 'POST' };
var body = getFormData();
if (!body) return;
if (body instanceof FormData) {
  opts.body = body;
} else {
  opts.headers = { 'Content-Type': 'application/json' };
  opts.body = JSON.stringify(body);
}
var res = await fetch(EP, opts);
```

**Step 3: Batch process with script**

Write a PowerShell script (`_add_upload.ps1`) that:
1. Reads each tool's `index.html`
2. Injects upload HTML before the submit button
3. Adds `<script src="/tools/_upload.js">` to `<head>`
4. Modifies `getFormData()` and `runTool()` patterns

**Step 4: Smart Report OIC — already has upload, skip. But add `_upload.js` loading.**

Smart Report OIC already has its own upload handling. Don't replace it, but add `<script src="/tools/_upload.js">` for shared functions (won't conflict since it uses different element IDs).

**Step 5: Commit**

```bash
git add tools/*/index.html tools/_upload.js tools/_upload.html
git commit -m "feat: add file upload to all 23 tool pages"
```

---

### Task 6: Smart Report OIC — align with shared upload

**Files:**
- Modify: `tools/smart-report-oic/index.html`

**Step 1: Add shared upload JS**

Add `<script src="/tools/_upload.js">` to the `<head>`.

**Step 2: The existing upload uses IDs `f_scan`, `uploadArea`, `uploadPlaceholder`, `uploadPreview`, `previewImg` — no conflict with shared IDs (`upFile`, `upArea`, etc.)

No conflicts, both systems coexist.

**Step 3: Commit**

```bash
git add tools/smart-report-oic/index.html
git commit -m "refactor: load shared upload JS in smart report OIC"
```

---

### Task 7: Remove temp scripts

**Step 1: Cleanup**

```bash
git rm --cached tools/_upload.html tools/_upload.js 2>/dev/null; echo "skip"
```

Actually, keep `_upload.html` and `_upload.js` as shared assets.

**Step 1: Delete any temp PowerShell scripts**

```bash
git clean -f -- *_update_*.ps1 *_add_*.ps1 2>/dev/null; echo "done"
```

---

## Execution

**Plan complete and saved.** Two options:

1. **Subagent-Driven (this session)** — I dispatch fresh subagent per task, review between tasks, fast iteration
2. **Parallel Session (separate)** — Open new session with executing-plans, batch execution with checkpoints

Which approach?
