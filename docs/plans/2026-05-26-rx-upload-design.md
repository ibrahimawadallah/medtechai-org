# Unified Medical Document Upload (Rx Upload) — Design

## Purpose

Allow users to upload any medical document (prescription, lab results, or both) once, have OCR extract text, and receive analyses from all relevant MedTechAI services in a single dashboard view — without needing to run each tool separately.

## Architecture

```
Upload file (PDF/photo)
  → Gemini Vision OCR extracts text (existing centralized handler, lines 29-52)
  → Single comprehensive AI call (new `rx-upload` case in handler.php)
    → Classifies document type → extracts structured data → runs all relevant analyses
  → Returns structured JSON with per-service sections
  → Frontend renders results in clinical dashboard (sidebar layout)
```

## New Files

### `api/tools/handler.php` — new `rx-upload` case
- Leverages existing centralized file upload + OCR (runs before switch statement)
- Single comprehensive AI prompt that:
  1. Classifies document type (prescription / lab results / both / other)
  2. Extracts all drug names, doses, frequencies, routes
  3. Extracts all lab test names, values, flags, normal ranges
  4. Analyzes for every applicable domain in one response
- Returns JSON with optional per-service sections

### `tools/rx-upload/index.html` — new frontend page
- Single upload area (reuses `_upload.js` bundled with `_upload.html` snippet)
- Clinical dashboard layout with sidebar + content panel
- Uses brand design system (Teal #38B8AE, Deep Navy #1A2D3A, Mint Mist #E6F7F6)
- No separate form fields — just upload and analyze

## AI Prompt Design

The prompt instructs the model to classify and analyze in one pass:

```
Classify this medical document and analyze ALL relevant sections.
Document text: {extracted text}

Return ONLY JSON with these sections (omit empty ones):
{
  "documentType": "prescription|labs|both|other",
  "summary": "str",
  "pharmacy": {
    "drugsFound": ["str"],
    "drugDetails": [{ genericName, brandNames, drugClass, indications, dosageForms, ... }],
    "interactions": [{ between, severity, description, management }],
    "doseCheck": [{ drug, prescribedDose, assessment, concern }],
    "safetyCheck": { overallSafety, lasaRisk, highAlert, allergyConflict, ... },
    "g6pdCheck": [{ drug, riskLevel }],
    "pregnancyCheck": { drug, fdaCategory, safety, ... }
  },
  "labs": {
    "abnormalValues": [{ test, value, flag, interpretation, normalRange }],
    "overallAssessment": "str",
    "recommendations": ["str"],
    "criticalValues": ["str"],
    "organSystemImpact": "str"
  },
  "recommendations": ["str"],
  "urgency": "Routine|Urgent|Emergency"
}
```

## Frontend Layout

```
┌──────────────────────────────────────────────────────────┐
│  [MedTechAI Logo]   Medical Document Analyzer    [nav]   │
├──────────────────┬───────────────────────────────────────┤
│  ┌────────────┐  │  ┌───────────────────────────────┐   │
│  │ Upload     │  │  │ Dynamic sections per service   │   │
│  │ [drop zone]│  │  │                               │   │
│  └────────────┘  │  │ Only services with data show  │   │
│  ┌────────────┐  │  │                               │   │
│  │ Services   │  │  │ Drug Information card         │   │
│  │ ● Pharmacy │  │  │ Interactions card             │   │
│  │ ○ Labs     │  │  │ Lab Analysis card             │   │
│  │ ○ Safety   │  │  │ Safety card                   │   │
│  │ ○ Summary  │  │  │ Summary card                  │   │
│  └────────────┘  │  └───────────────────────────────┘   │
│  ┌────────────┐  │                                       │
│  │ Actions    │  │                                       │
│  │ Print      │  │                                       │
│  │ New        │  │                                       │
│  └────────────┘  │                                       │
└──────────────────┴───────────────────────────────────────┘
```

### Sidebar (left, 280px):
- **Upload area** — drag-drop file upload, brand teal dashed border. Uses `_upload.js` (shared) — requires matching IDs (`#upFile`, `#upArea`, `#upPlaceholder`, `#upPreview`, `#upImg`, `#upName`).
- **Paste text area** — monospace textarea with auto-grow, Ctrl+Enter shortcut hint.
- **Analyze button** — teal primary, shows spinner + "Analyzing…" during processing.
- **Services list** — each service has a 16×16 SVG icon (matching style). Automatically populated based on what was detected. Items without data are hidden. Clicking scrolls to that section.
- **Actions** — Print Report, New Analysis buttons.

### Content panel (right):
- Sections appear dynamically based on document type
- Each section header includes the matching 16×16 SVG icon + title
- Each section is a white card with mint mist header and brand typography
- Pharmacy section uses brand badges (teal for safe, amber for caution, red for critical)
- Lab section uses the same alert color scheme

### `_upload.js` Conflict Resolution

`_upload.js` injects a fixed search bar (`#uptodate-search`) and a specialty sidebar (`#uptodate-sidebar`) on page load. The rx-upload page has its own `.rx-sidebar`, so the injected sidebar must be suppressed:

1. Page includes `<div id="uptodate-sidebar" style="display:none">` so `injectSidebar()` returns early (it checks `getElementById` before creating)
2. Fallback CSS: `#uptodate-sidebar { display: none !important; }`
3. The injected search bar is kept (site convention, provides useful search), but `z-index` is adjusted to avoid overlap
4. Sticky sidebar accounts for both bars: `position:sticky; top: 96px` (48px search bar + 48px nav bar)

### Icon Set (16×16, consistent stroke style)

All icons use 16×16 viewBox, 1.5px stroke, `stroke-linecap="round"` `stroke-linejoin="round"`, no fill, with `currentColor`.

| Service | Icon | SVG Path |
|---------|------|----------|
| **Summary** | Clipboard | `<rect x="3" y="2" width="10" height="12" rx="1.5" stroke="currentColor" fill="none" stroke-width="1.5"/><line x1="5.5" y1="6" x2="10.5" y2="6" stroke="currentColor" stroke-width="1.5"/><line x1="5.5" y1="8.5" x2="10.5" y2="8.5" stroke="currentColor" stroke-width="1.5"/><line x1="5.5" y1="11" x2="8.5" y2="11" stroke="currentColor" stroke-width="1.5"/><path d="M6.5 2v-1h3v1" stroke="currentColor" fill="none" stroke-width="1.5"/>` |
| **Pharmacy** | Pill | `<rect x="4.5" y="2" width="7" height="12" rx="3.5" stroke="currentColor" fill="none" stroke-width="1.5"/><line x1="8" y1="2" x2="8" y2="14" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2 1.5"/>` |
| **Interactions** | Exchange | `<path d="M2 8l3-3v2h6V5l3 3-3 3v-2H5v2L2 8z" stroke="currentColor" fill="none" stroke-width="1.5"/>` |
| **Safety** | Shield | `<path d="M8 1.5l6 2.5v4c0 3.5-2.5 6.5-6 7.5-3.5-1-6-4-6-7.5V4L8 1.5z" stroke="currentColor" fill="none" stroke-width="1.5"/><path d="M6 8.5l1.5 1.5 3-3" stroke="currentColor" fill="none" stroke-width="1.5"/>` |
| **G6PD** | Blood drop | `<path d="M8 2.5C6 5.5 4 8 4 10c0 2.2 1.8 4 4 4s4-1.8 4-4c0-2-2-4.5-4-7.5z" stroke="currentColor" fill="none" stroke-width="1.5"/>` |
| **Pregnancy** | Mother & child | `<circle cx="8" cy="4" r="2" stroke="currentColor" fill="none" stroke-width="1.5"/><path d="M4 14c0-3 1.8-4.5 4-4.5s4 1.5 4 4.5" stroke="currentColor" fill="none" stroke-width="1.5"/><circle cx="11" cy="3" r="1.2" stroke="currentColor" fill="none" stroke-width="1.2"/>` |
| **Labs** | Test tube | `<path d="M6 14a3 3 0 01-3-3V2h6v9a3 3 0 01-3 3z" stroke="currentColor" fill="none" stroke-width="1.5"/><line x1="4" y1="5.5" x2="8" y2="5.5" stroke="currentColor" stroke-width="1.5"/>` |
| **Recommendations** | Check list | `<rect x="2" y="2.5" width="12" height="11" rx="1.5" stroke="currentColor" fill="none" stroke-width="1.5"/><path d="M5.5 7.5l1.5 1.5 3-3" stroke="currentColor" fill="none" stroke-width="1.5"/><line x1="5.5" y1="11" x2="9.5" y2="11" stroke="currentColor" stroke-width="1.5"/>` |
| **Urgency** | Alert bell | `<path d="M8 1.5c-2.5 0-4 2-4 4.5v2L2.5 10.5h11L12 8V6c0-2.5-1.5-4.5-4-4.5z" stroke="currentColor" fill="none" stroke-width="1.5"/><path d="M6.5 11.5a1.5 1.5 0 003 0" stroke="currentColor" fill="none" stroke-width="1.5"/>` |

## Implementation Steps

1. Add `rx-upload` case to `handler.php` with the comprehensive prompt
2. Create `tools/rx-upload/index.html` with sidebar dashboard layout
3. Add `shared.css` rules for the new sidebar layout (if needed)
4. Wire up the existing `_upload.js` for file handling
5. Test with sample prescriptions and lab reports

## Key Constraints

- Single AI call only — no fan-out, no multi-request orchestration
- Reuse existing OCR pipeline from centralized handler
- Reuse existing `shared.css` classes where possible, add minimal new CSS
- No database — fully ephemeral, API-to-API processing
