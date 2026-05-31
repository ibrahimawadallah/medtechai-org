# Testing Guide - MedTechAI Clinical Tools

## Quick Verification Checklist

### Clinical Calculators Dashboard (`tools/clinical-calculators/index.html`)

**Test Local Calculators:**
- [ ] **BMI** - Weight: 70kg, Height: 175cm → Should show ~22.9 kg/m², "Normal"
- [ ] **GFR** - Scr: 1.2, Age: 65, Sex: Male, Race: Non-Black → Should show ~68 mL/min/1.73m²
- [ ] **Corrected Ca²⁺** - Ca: 9.0, Alb: 3.0 → Should show ~10.4 mg/dL, "Normal"
- [ ] **Anion Gap** - Na: 140, Cl: 100, HCO3: 24 → Should show 16 mEq/L, "Elevated"
- [ ] **CHA₂DS₂-VASc** - All checkboxes checked → Should show 9 points, "Very High" risk

**Test API Calculators:**
- [ ] **TIMI Score** - All fields checked → Should call API or show fallback
- [ ] **APACHE II** - Enter vitals → Should display with methodology

**Test UI:**
- [ ] Category sidebar filtering works
- [ ] Search box filters calculators
- [ ] Calculator cards expand to show input panel
- [ ] Result panel displays with disclaimer
- [ ] Clear button resets results
- [ ] Print button works

### Other Tools Verification

**Drug Search (tools/drug-search/index.html):**
- [ ] Search for "Amoxicillin" → Should show drug info with DailyMed data

**Dose Calculator (tools/dose-calculator/index.html):**
- [ ] Enter drug + weight → Should show dosing with renal adjustment

**Interaction Checker (tools/interaction-checker/index.html):**
- [ ] Enter two drugs (e.g., "Warfarin", "Aspirin") → Should show interaction severity

**Lab Analyzer (tools/lab-analyzer/index.html):**
- [ ] Enter lab values → Should identify abnormal values and critical alerts

### Accessibility Testing
- [ ] Tab through calculator cards (should highlight)
- [ ] Enter key on card should select it
- [ ] Focus states visible on all interactive elements
- [ ] ARIA labels present on buttons and inputs

### Responsive Testing
- [ ] Desktop: Sidebar on left, grid on right
- [ ] Tablet (900px): Sidebar on top, grid below
- [ ] Mobile (500px): Single column grid

## Known Limitations
- API calculators require server with GROQ/GEMINI keys for full functionality
- File upload requires HTTPS and server configuration
- Local calculators work offline without server