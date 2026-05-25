# File Upload for All 23 Tools — Design

## Goal
Add drag-drop file upload (images + PDFs) to every tool page, with centralized PHP handling so individual tool cases need minimal changes.

## Architecture

### PHP — Centralized file handler (`handler.php`)

1. **At request parse time** (top of file after POST body parsing):
   - Iterate `$_FILES`, validate magic bytes + mime type (`image/jpeg`, `image/png`, `image/gif`, `image/webp`, `application/pdf`), max 5 MB
   - Base64-encode the file bytes
   - Call Gemini Vision once with a generic extraction prompt: `"Extract all readable text and describe this medical document/image in detail."`
   - Store extracted text in `$body['_fileText']`

2. **Prompt injection**: Each tool case prepends `$body['_fileText']` to its AI prompt when present. No new `ai()` function needed — all tools continue using `gemini()`.

3. **Smart Report OIC** — keeps its existing `geminiVision()` call (needs raw Vision for structured image analysis). The file text extraction at the top is redundant for this tool but harmless.

### PHP — Magic byte validation

```php
$magicBytes = [
    'image/jpeg' => ["\xFF\xD8\xFF"],
    'image/png'  => ["\x89\x50\x4E\x47"],
    'image/gif'  => ["\x47\x49\x46"],
    'image/webp' => ["\x52\x49\x46\x46"],
    'application/pdf' => ["\x25\x50\x44\x46"],
];
```

### JavaScript — Client-side handling

1. **Client-side resize**: Before upload, resize images to max 2000px via canvas to keep base64 payloads small.

2. **Drag-drop area**: Shared HTML snippet included in each tool page.

3. **FormData auto-detection**: `getFormData()` returns FormData when file present, JSON otherwise. `runTool()` adjusts Content-Type.

### HTML — PHP include pattern

Create `tools/_upload.html` with the upload area HTML + JS. Each tool page includes it via `<?php include '../_upload.html'; ?>`. Since tool pages are `.html`, switch to `.php` extension or use a JS-based include.

### Tools affected (22 new + 1 existing Smart Report OIC)

| Case | Current | After |
|---|---|---|
| drug-search | `gemini(...)` | prepend `$body['_fileText']` |
| interaction-checker | `gemini(...)` | prepend `$body['_fileText']` |
| dose-calculator | `gemini(...)` | prepend `$body['_fileText']` |
| pregnancy-safety | `gemini(...)` | prepend `$body['_fileText']` |
| g6pd-checker | `gemini(...)` | prepend `$body['_fileText']` |
| drug-comparison | `gemini(...)` | prepend `$body['_fileText']` |
| clinical-decision-support | `gemini(...)` | prepend `$body['_fileText']` |
| diagnostic-check | `gemini(...)` | prepend `$body['_fileText']` |
| symptom-checker | `gemini(...)` | prepend `$body['_fileText']` |
| icd10-lookup | `gemini(...)` | prepend `$body['_fileText']` |
| report-composer | `gemini(...)` | prepend `$body['_fileText']` |
| lab-analyzer | `gemini(...)` | prepend `$body['_fileText']` |
| imaging-reader | `gemini(...)` | prepend `$body['_fileText']` |
| pathology-reader | `gemini(...)` | prepend `$body['_fileText']` |
| discharge-summary | `gemini(...)` | prepend `$body['_fileText']` |
| clinical-notes | `gemini(...)` | prepend `$body['_fileText']` |
| medication-safety | `gemini(...)` | prepend `$body['_fileText']` |
| formulary | `gemini(...)` | prepend `$body['_fileText']` |
| iv-compatibility | `gemini(...)` | prepend `$body['_fileText']` |
| clinical-pathways | `gemini(...)` | prepend `$body['_fileText']` |
| clinical-calculators | `gemini(...)` | prepend `$body['_fileText']` |
| stewardship | `gemini(...)` | prepend `$body['_fileText']` |
| smart-report-oic | `geminiVision()` | unchanged (already had file upload) |

## Error Handling

- File too large (>5 MB): client-side validation + server-side check, show alert
- Unsupported type: show alert listing accepted formats
- Gemini Vision extraction fails: set `$body['_fileText']` to empty, tool proceeds with user text only
- File upload corrupt: magic byte check, reject with error message

## Cost

All APIs used have free tiers:
- Gemini Vision: free tier, 60 req/min, handles images + PDFs
- All costs are API call costs only — no libraries, no servers
