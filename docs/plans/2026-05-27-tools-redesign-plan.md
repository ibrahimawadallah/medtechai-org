# Tools Pages Redesign — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Modernize all 23 tool pages with brand-aligned styling and a properly-positioned sidebar that doesn't overlap the header.

**Architecture:** New `_sidebar.js` injects a fixed left sidebar (below nav/search bars) with category navigation. Remove old sidebar injection from `_upload.js`. Update `shared.css` for brand visual refresh (teal primary, DM Sans headings, 12px radius, softer shadows).

**Tech Stack:** Vanilla JS, CSS custom properties, brand identity (Teal #38B8AE, Deep Navy #1A2D3A, Mint Mist #E6F7F6)

---

### Task 1: Create `_sidebar.js`

**Files:**
- Create: `tools/_sidebar.js`

**What it does:**
- Injects `<aside class="side">...</aside>` fixed left, `top:96px`, `z-index:1`
- Reads `.cat-banner` class to determine active category
- Sets `margin-left: 240px` on `.main`, `.tool-header`, `.breadcrumb`
- Skips injection if:
  - `.tool-srch` exists (tools listing page has its own layout)
  - `#uptodate-sidebar` exists (rx-upload suppresses with hidden div)
  - `.rx-dashboard` exists (rx-upload has its own sidebar)
- Letter "All Tools" link at top → `/tools/`
- 4 category links with colored dots: Pharmacy (teal #38b8ae), Clinical Support (cyan #0891b2), Smart Reports (green #059669), Advanced Clinical (purple #7c3aed)
- Active category gets bold + highlighted background

**Step 1: Create `_sidebar.js`**

```javascript
// Sidebar injection — brand-styled category navigation
(function() {
  if (document.querySelector('.tool-srch')) return;
  if (document.getElementById('uptodate-sidebar')) return;
  if (document.querySelector('.rx-dashboard')) return;

  var categories = [
    { name: 'Pharmacy', id: 'pharmacy', dot: '#38b8ae' },
    { name: 'Clinical Support', id: 'clinical', dot: '#0891b2' },
    { name: 'Smart Reports', id: 'smart-report', dot: '#059669' },
    { name: 'Advanced Clinical', id: 'advanced', dot: '#7c3aed' }
  ];

  var currentCat = '';
  var banner = document.querySelector('.cat-banner');
  if (banner) {
    for (var i = 0; i < categories.length; i++) {
      if (banner.classList.contains(categories[i].id)) { currentCat = categories[i].id; break; }
    }
  }

  // Inject CSS
  var style = document.createElement('style');
  style.textContent =
    '.side{position:fixed;top:96px;left:0;width:220px;height:calc(100vh - 96px);background:#f5fafa;border-right:1px solid #e2e8f0;padding:12px 0;overflow-y:auto;z-index:1}' +
    '.side-all{display:flex;align-items:center;gap:6px;padding:8px 16px;font-size:13px;color:#475569;text-decoration:none;font-weight:600;margin-bottom:8px}' +
    '.side-all:hover{color:#38b8ae;text-decoration:none}' +
    '.side-cat{display:flex;align-items:center;gap:10px;padding:8px 16px;font-size:13px;color:#475569;cursor:default;border-left:3px solid transparent;transition:all .15s}' +
    '.side-cat:hover{background:#e6f7f6;color:#38b8ae}' +
    '.side-cat.active{border-left-color:#38b8ae;background:#e6f7f6;color:#38b8ae;font-weight:600}' +
    '.side-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}';
  document.head.appendChild(style);

  // Build sidebar
  var s = document.createElement('aside');
  s.className = 'side';
  var html = '<a href="/tools/" class="side-all">\u2190 All Tools</a>';
  for (var j = 0; j < categories.length; j++) {
    var a = currentCat === categories[j].id;
    html += '<div class="side-cat' + (a ? ' active' : '') + '">' +
      '<span class="side-dot" style="background:' + categories[j].dot + '"></span>' +
      '<span>' + categories[j].name + '</span></div>';
  }
  s.innerHTML = html;
  document.body.appendChild(s);

  // Shift content
  var els = document.querySelectorAll('.main, .tool-header, .breadcrumb');
  for (var k = 0; k < els.length; k++) {
    var cur = parseInt(els[k].style.marginLeft, 10) || 0;
    els[k].style.marginLeft = (cur + 240) + 'px';
  }
})();
```

**Step 2: Verify**
- Open any tool page locally, confirm `<aside class="side">` appears
- Confirm `.main`, `.tool-header`, `.breadcrumb` have `margin-left: 240px`
- Confirm active category is highlighted
- Confirm tools listing page does NOT get sidebar (`.tool-srch` detection)
- Confirm rx-upload does NOT get sidebar (`.rx-dashboard` detection)

**Step 3: Commit**

```bash
git add tools/_sidebar.js
git commit -m "feat: add _sidebar.js for brand-styled category sidebar injection"
```

---

### Task 2: Update `shared.css` — Visual Modernization

**Files:**
- Modify: `tools/shared.css`

**Changes:**

1. **`--radius`: 8px → 12px** — larger card/input rounding
2. **`--shadow`: softer** — `0 2px 12px rgba(0,0,0,.06)`
3. **`.tool-name`: DM Sans** — `font-family: 'DM Sans', var(--font)`, bump to 22px
4. **`.card`: larger radius** — will inherit from `--radius`
5. **Input focus: teal instead of blue** — `border-color: var(--teal); box-shadow: 0 0 0 3px rgba(56,184,174,.15)`
6. **Upload area hover: teal instead of blue** — `border-color: var(--teal); background: var(--teal-bg)`
7. **`.btn-primary`: teal instead of blue** — `background: var(--teal); color: #fff` + hover `var(--teal-h)`
8. **`.r-section-title`: teal instead of blue** — `color: var(--teal)`
9. **`.r-list li::before`: teal instead of blue** — `color: var(--teal)`
10. **`.r-tag`: teal instead of blue** — `background: var(--teal-bg); color: #155e75`
11. **`.references a`: teal instead of blue** — `color: var(--teal)`
12. **`.upload-area.drag-over`: teal** — `border-color: var(--teal); background: var(--teal-bg)`
13. **`.tool-cat`: more rounded** — `border-radius: 6px` (up from 4px)
14. **`.side-*` classes**: NOT needed — injected by `_sidebar.js`

**Step 1: Apply CSS edits**

```css
/* Change --radius and --shadow */
:root{...--radius:12px;...--shadow:0 2px 12px rgba(0,0,0,.06)}

/* DM Sans on tool name */
.tool-name{font-family:'DM Sans',var(--font);font-size:22px;font-weight:700;color:var(--slate);margin-bottom:4px}

/* Teal focus ring on inputs */
.field input:focus,.field textarea:focus,.field select:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(56,184,174,.15)}

/* Teal upload area hover */
.upload-area:hover{border-color:var(--teal);background:var(--teal-bg)}
.upload-area.drag-over{border-color:var(--teal);background:var(--teal-bg)}

/* Teal primary button */
.btn-primary{background:var(--teal);color:#fff}
.btn-primary:hover{background:var(--teal-h)}

/* Teal section titles */
.r-section-title{...color:var(--teal)}

/* Teal list bullets */
.r-list li::before{...color:var(--teal)}

/* Teal tags */
.r-tag{...background:var(--teal-bg);color:#155e75}

/* Teal reference links */
.references a{color:var(--teal)}
```

**Step 2: Verify**
- `--radius: 12px` — cards, inputs, buttons all rounder
- `--shadow` softer — cards have gentler shadow
- Input focus ring is teal (not blue)
- Buttons are teal (not blue)
- `.tool-name` renders in DM Sans at 22px

**Step 3: Commit**

```bash
git add tools/shared.css
git commit -m "feat: modernize shared.css — teal primary, DM Sans headings, 12px radius, softer shadows"
```

---

### Task 3: Remove Sidebar Injection from `_upload.js`

**Files:**
- Modify: `tools/_upload.js`

**Changes:**
1. Remove `injectSidebar()` function definition (lines 265-298)
2. Remove `window.injectSidebar = injectSidebar` from line 243
3. Remove `injectSidebar()` call on line 222
4. Remove `syncSidebarToHash()` function (lines 207-216) — no longer needed without sidebar
5. Remove `syncSidebarToHash()` call on line 224
6. Remove `syncSidebarToHash` from hashchange listener on line 235
7. Keep `scrollToCategory()` — still used on tools listing page for hash-scrolling

**Step 1: Apply edits**

Remove `syncSidebarToHash` function:
```
Old:   function syncSidebarToHash() { ... }
```
Delete entirely.

Remove `syncSidebarToHash` call from DOMContentLoaded:
```
Old:   document.addEventListener('DOMContentLoaded', function() {
         initDragDrop();
         injectSearchBar();
         injectSidebar();
         scrollToCategory();
         syncSidebarToHash();
```
New:
```
       document.addEventListener('DOMContentLoaded', function() {
         initDragDrop();
         injectSearchBar();
         scrollToCategory();
```

Remove `syncSidebarToHash` from hashchange:
```
Old:   window.addEventListener('hashchange', function() {
         scrollToCategory();
         syncSidebarToHash();
       });
```
New:
```
       window.addEventListener('hashchange', function() {
         scrollToCategory();
       });
```

Remove `window.injectSidebar` export:
```
Old:   window.injectSidebar = injectSidebar;
```
Delete line 243.

Remove `injectSidebar` function:
```
Old:   // ── Specialty sidebar injection ──────────────────────────────────
       function injectSidebar() { ... }
```
Delete lines 265-298.

**Step 2: Verify**
- `_upload.js` no longer has `injectSidebar` or `syncSidebarToHash`
- DOMContentLoaded only calls initDragDrop, injectSearchBar, scrollToCategory
- hashchange only calls scrollToCategory

**Step 3: Commit**

```bash
git add tools/_upload.js
git commit -m "refactor: remove sidebar injection from _upload.js (replaced by _sidebar.js)"
```

---

### Task 4: Add `_sidebar.js` to All Tool Pages

**Files:**
- Modify: All 23 tool `index.html` files

**What to add:** `<script src="/tools/_sidebar.js" defer></script>` just before `</body>` on each tool page. Skip `rx-upload/index.html` (it has its own sidebar and `_sidebar.js` already skips via `.rx-dashboard` detection, but adding it is harmless).

**Pages to modify:**
1. `tools/clinical-calculators/index.html`
2. `tools/clinical-decision-support/index.html`
3. `tools/clinical-notes/index.html`
4. `tools/clinical-pathways/index.html`
5. `tools/diagnostic-check/index.html`
6. `tools/discharge-summary/index.html`
7. `tools/dose-calculator/index.html`
8. `tools/drug-comparison/index.html`
9. `tools/drug-search/index.html`
10. `tools/formulary/index.html`
11. `tools/g6pd-checker/index.html`
12. `tools/icd10-lookup/index.html`
13. `tools/imaging-reader/index.html`
14. `tools/interaction-checker/index.html`
15. `tools/iv-compatibility/index.html`
16. `tools/lab-analyzer/index.html`
17. `tools/medication-safety/index.html`
18. `tools/pathology-reader/index.html`
19. `tools/pregnancy-safety/index.html`
20. `tools/report-composer/index.html`
21. `tools/smart-report-oic/index.html`
22. `tools/stewardship/index.html`
23. `tools/symptom-checker/index.html`

**Note:** rx-upload already has its own sidebar layout. The `.rx-dashboard` check in `_sidebar.js` will prevent double injection even if we add the script tag. Add it for consistency.

24. `tools/rx-upload/index.html` (optional — _sidebar.js self-skips via `.rx-dashboard` check)

**Step 1: Add script tag to each file**

Insert before `</body>`:
```html
<script src="/tools/_sidebar.js" defer></script>
```

**Step 2: Quick verify (spot-check 3-4 tools)**
- Open tool page, confirm sidebar renders
- Confirm no layout overlap with header
- Confirm no duplicate sidebars

**Step 3: Commit**

```bash
git add tools/*/index.html tools/_sidebar.js
git commit -m "feat: add sidebar script to all 23 tool pages"
```

---

### Verification

1. Visit a pharmacy tool → sidebar highlights "Pharmacy" with teal dot
2. Visit a clinical tool → sidebar highlights "Clinical Support" with cyan dot  
3. Visit a smart-report tool → sidebar highlights "Smart Reports" with green dot
4. Visit advanced tool → sidebar highlights "Advanced Clinical" with purple dot
5. All Tools link → navigates to `/tools/`
6. Breadcrumb and tool header are NOT overlapped by sidebar (240px margin)
7. On mobile (<768px), sidebar hidden (existing media query)
8. Tools listing page at `/tools/` has no sidebar (`.tool-srch` detection)
9. rx-upload page has no double sidebar (`.rx-dashboard` detection)
10. Input fields have teal focus ring, buttons are teal, cards have 12px radius
