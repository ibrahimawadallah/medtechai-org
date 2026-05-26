# UpToDate Redesign Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Transform MedTechAI's 21 tool pages into an UpToDate-style clinical interface with persistent search, specialty sidebar, evidence grading, and patient handout mode.

**Architecture:** Hybrid approach — shared.css full redesign + `_upload.js` injection of search bar and sidebar + API prompt updates. No individual tool page markup changes needed.

**Tech Stack:** PHP/HTML tool pages, shared.css, vanilla JavaScript (`_upload.js`, `_suggest.js`), API endpoints in `default.php`

---

### Task 1: Redesign `shared.css` — Colors, Layout, Cards

**Files:**
- Modify: `tools/shared.css` (full rewrite)

**Step 1: Replace CSS variables**

Add UpToDate brand variables:
```css
:root {
  --blue:#0066aa; --blue-h:#00508a; --blue-bg:#e8f4fd;
  --teal:#38b8ae; --teal-h:#2a9d8f; --teal-bg:#e6f7f6;
  --navy:#1a2d3a;
  --bg:#ffffff; --surface:#ffffff; --sidebar-bg:#f8f9fa;
  --border:#e5e7eb; --radius:8px;
  --shadow:0 1px 3px rgba(0,0,0,.08);
  --font:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
  --nav-bg:#1a2d3a; --nav-text:#f1f5f9;
  --grade-1a:#059669; --grade-1b:#2563eb; --grade-2c:#d97706;
}
```

**Step 2: Redesign card component**

Make cards have shadow instead of border, rounded corners:
```css
.card{background:var(--surface);border:none;border-radius:var(--radius);box-shadow:var(--shadow)}
.card-header{padding:14px 18px;border-bottom:1px solid var(--border);font-size:11px;font-weight:700;color:var(--slate2);text-transform:uppercase;letter-spacing:.04em;background:transparent}
.card-body{padding:18px}
```

**Step 3: Redesign form inputs**

Match UpToDate's clean input styling:
```css
.field input,.field textarea,.field select{
  width:100%;padding:10px 12px;border:1px solid var(--border);
  border-radius:6px;font-size:14px;font-family:inherit;
  color:var(--navy);background:var(--surface);outline:none;
  transition:border-color .15s,box-shadow .15s
}
.field input:focus,.field textarea:focus,.field select:focus{
  border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,102,170,.1)
}
```

**Step 4: Add evidence grade badge styles**

```css
.grade{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;line-height:1.4}
.grade-1a{background:#dcfce7;color:#059669}
.grade-1b{background:#dbeafe;color:#2563eb}
.grade-2c{background:#fef3c7;color:#d97706}
```

**Step 5: Add references section style**

```css
.references{border-top:1px solid var(--border);padding-top:14px;margin-top:18px;font-size:12px;color:var(--slate4);line-height:1.6}
.references a{color:var(--blue)}
```

**Step 6: Add patient handout styles**

```css
.patient-handout{display:none;font-size:16px;line-height:1.8;color:var(--navy);max-width:680px;margin:0 auto;padding:20px}
.patient-handout.on{display:block}
.patient-handout h2{font-size:20px;font-weight:700;margin-bottom:12px}
.btn-handout{background:var(--teal);color:#fff}
.btn-handout:hover{background:var(--teal-h)}
@media print{.clinical-ui,.nav,.sidebar,.search-bar,.breadcrumb,.cat-banner,.footer{display:none!important}.patient-handout{display:block!important}}
```

**Step 7: Restructure main layout for sidebar**

Add sidebar-aware layout:
```css
.main-wrap{display:flex;max-width:1120px;margin:0 auto;padding:0 20px 36px;flex:1;gap:0}
.main{flex:1;max-width:860px;margin-left:220px}
.sidebar{width:200px;position:fixed;top:52px;left:0;height:100%;background:var(--sidebar-bg);border-right:1px solid var(--border);padding:16px 0;overflow-y:auto}
.sidebar-item{display:flex;align-items:center;gap:8px;padding:8px 16px;font-size:13px;color:var(--slate3);border-left:3px solid transparent;cursor:pointer}
.sidebar-item:hover{background:var(--teal-bg);color:var(--teal)}
.sidebar-item.active{border-left-color:var(--teal);background:var(--teal-bg);color:var(--teal);font-weight:600}
@media(max-width:768px){.sidebar{display:none}.main{margin-left:0}}
```

**Step 8: Verify**

Run: n/a (CSS only — visual check)

**Step 9: Commit**

```bash
git add tools/shared.css
git commit -m "feat: redesign shared.css to UpToDate style"
```

---

### Task 2: Add Persistent Search Bar + Sidebar Injection to `_upload.js`

**Files:**
- Modify: `tools/_upload.js`

**Step 1: Add `injectSearchBar()` function**

```javascript
function injectSearchBar() {
  if (document.getElementById('uptodate-search')) return;
  var bar = document.createElement('div');
  bar.id = 'uptodate-search';
  bar.style.cssText = 'position:fixed;top:0;left:0;right:0;height:48px;background:#fff;border-bottom:1px solid #e5e7eb;box-shadow:0 1px 3px rgba(0,0,0,.06);display:flex;align-items:center;padding:0 16px;z-index:1000;gap:12px';
  bar.innerHTML = '<a href="/" style="font-weight:700;font-size:15px;color:#1a2d3a;text-decoration:none;white-space:nowrap">MedTech<span style="color:#38b8ae">AI</span></a>' +
    '<span style="color:#d1d5db;font-size:14px">|</span>' +
    '<form action="/tools/" method="get" style="flex:1;max-width:480px;display:flex">' +
    '<input type="text" name="q" placeholder="Search drugs, topics, tools…" style="flex:1;height:32px;padding:0 10px;border:1px solid #e5e7eb;border-radius:6px 0 0 6px;font-size:13px;font-family:inherit;color:#1a2d3a;outline:none;background:#f9fafb" aria-label="Search">' +
    '<button type="submit" style="height:32px;padding:0 12px;background:#0066aa;color:#fff;border:none;border-radius:0 6px 6px 0;font-size:12px;font-weight:600;cursor:pointer">Search</button></form>' +
    '<span style="flex:1"></span>' +
    '<a href="/about/" style="font-size:12px;color:#6b7280;text-decoration:none">About</a>' +
    '<a href="/contact/" style="font-size:12px;color:#6b7280;text-decoration:none">Contact</a>';
  document.body.prepend(bar);
  document.body.style.paddingTop = '48px';
}
```

**Step 2: Add `injectSidebar()` function**

```javascript
function injectSidebar() {
  if (document.getElementById('uptodate-sidebar')) return;
  var categories = [
    { name: 'Pharmacy', id: 'pharmacy', icon: 'P' },
    { name: 'Clinical Support', id: 'clinical', icon: 'C' },
    { name: 'Smart Reports', id: 'smart-report', icon: 'R' },
    { name: 'Advanced Clinical', id: 'advanced', icon: 'A' },
  ];
  var currentCat = '';
  var banner = document.querySelector('.cat-banner');
  if (banner) {
    for (var i = 0; i < categories.length; i++) {
      if (banner.classList.contains(categories[i].id)) { currentCat = categories[i].id; break; }
    }
  }
  var s = document.createElement('div');
  s.id = 'uptodate-sidebar';
  s.style.cssText = 'width:200px;position:fixed;top:48px;left:0;height:calc(100% - 48px);background:#f8f9fa;border-right:1px solid #e5e7eb;padding:16px 0;overflow-y:auto;z-index:999';
  var html = '<div style="padding:4px 16px 12px;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:1px">Specialties</div>';
  html += '<a href="/tools/" style="display:flex;align-items:center;gap:8px;padding:8px 16px;font-size:13px;color:#4b5563;text-decoration:none;border-left:3px solid ' + (currentCat === '' ? '#38b8ae' : 'transparent') + ';background:' + (currentCat === '' ? '#e6f7f6' : 'transparent') + '">‹ All Tools</a>';
  for (var j = 0; j < categories.length; j++) {
    var isActive = currentCat === categories[j].id;
    html += '<a href="/tools/#' + categories[j].id + '" style="display:flex;align-items:center;gap:8px;padding:8px 16px;font-size:13px;color:' + (isActive ? '#38b8ae' : '#4b5563') + ';text-decoration:none;font-weight:' + (isActive ? '600' : '400') + ';border-left:3px solid ' + (isActive ? '#38b8ae' : 'transparent') + ';background:' + (isActive ? '#e6f7f6' : 'transparent') + '">' + categories[j].icon + ' ' + categories[j].name + '</a>';
  }
  s.innerHTML = html;
  document.body.appendChild(s);
  var main = document.querySelector('.main');
  if (main) main.style.marginLeft = '220px';
}
```

**Step 3: Call injectors on DOMContentLoaded**

```javascript
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function() {
    var el = document.querySelector('.cat-banner, .tool-header');
    if (el) { injectSearchBar(); injectSidebar(); }
  });
} else {
  injectSearchBar(); injectSidebar();
}
```

**Step 4: Verify**

Open any tool page (e.g., `dose-calculator/index.html`) and check:
- Search bar appears at top
- Sidebar appears on left with correct active category
- Main content is shifted right

**Step 5: Commit**

```bash
git add tools/_upload.js
git commit -m "feat: inject UpToDate search bar and specialty sidebar via _upload.js"
```

---

### Task 3: Update `_suggest.js` — Broader Autocomplete

**Files:**
- Modify: `tools/_suggest.js`

**Step 1: Update suggest fetch to include tool names**

In the existing `fetchSuggestions()` function, expand the POST body to include the current search term. The API endpoint already exists (`POST /api/tools/suggest`). Ensure the suggestion dropdown renders under the new search bar (the global bar in Task 2).

**Step 2: Adjust positioning**

The existing `_suggest.js` positions its dropdown relative to the input. Since the search bar input is now fixed-position, ensure the dropdown `position: fixed` or `absolute` with correct `top` value.

No code changes needed if the existing relative positioning works — test to confirm.

**Step 3: Commit**

```bash
git add tools/_suggest.js
git commit -m "fix: adjust suggest dropdown positioning for fixed search bar"
```

---

### Task 4: Update API Prompts — Evidence Grading + Patient Handout

**Files:**
- Modify: `default.php` (or `docker-entrypoint.sh` if prompts are there)

**Step 1: Add GRADE evidence instruction**

Add to each tool prompt in the API:
```
IMPORTANT: Where applicable, include GRADE evidence ratings for key recommendations using the format: <span class="grade grade-1a">GRADE 1A</span>. Use green for strong recommendations (1A, 1B), blue for moderate (2A, 2B), amber for weak (2C).
```

**Step 2: Add patient handout instruction**

Add to each tool prompt:
```
If the word "HANDOUT" appears in the query, provide a plain-language patient summary at a 5th-6th grade reading level. Start with <div class="patient-handout on"><h2>What You Should Know</h2> and avoid clinical jargon.
```

**Step 3: Commit**

```bash
git add default.php
git commit -m "feat: add evidence grading and patient handout prompt instructions"
```

---

### Task 5: Update `tools/index.html` — Brand Design

**Files:**
- Modify: `tools/index.html`

**Step 1: Update page styles to inherit from shared.css**

No changes needed — `shared.css` already handles colors. Just ensure the page uses `.main` with sidebar-aware margin.

**Step 2: Ensure category items link correctly**

The sidebar links point to `/tools/#pharmacy` etc. These already work with the existing `cat` div `data-cat` attributes.

**Step 3: Commit**

```bash
git add tools/index.html
git commit -m "feat: update tools listing to UpToDate brand design"
```

---

### Task 6: Integration Verification

**Step 1: Spot-check 6 representative tool pages**

Check in browser:
1. `dose-calculator/index.html` — Pharmacy category
2. `symptom-checker/index.html` — Clinical Support category
3. `lab-analyzer/index.html` — Smart Reports category
4. `medication-safety/index.html` — Advanced Clinical category
5. `interaction-checker/index.html` — Pharmacy (has drug pair inputs)
6. `smart-report-oic/index.html` — Has file upload

For each, verify:
- Search bar renders at top
- Sidebar shows correct active category
- Main content is shifted right correctly
- Form and result cards have new shadow style
- Mobile responsive (sidebar hidden, search bar compact)
- No broken layout from injected elements

**Step 2: Verify evidence badges render**

Submit a test query on any tool and check that the AI response includes `<span class="grade grade-1a">` elements (requires API prompt update to be deployed).

**Step 3: Commit any fixes**

```bash
git add -A
git commit -m "fix: integration adjustments after UpToDate redesign"
```

---

## Execution Handoff

Plan complete and saved to `docs/plans/2026-05-26-uptodate-implementation.md`. Two execution options:

**1. Subagent-Driven (this session)** — I dispatch fresh subagent per task, review between tasks, fast iteration
**2. Parallel Session (separate)** — Open new session with executing-plans, batch execution with checkpoints

Which approach?
