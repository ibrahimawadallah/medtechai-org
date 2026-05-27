# PowerPoint Capture Script for MedTechAI Commercial Product

## Slide 1: Title
- Title: MedTechAI - Clinical AI Tools for the Arab World
- Subtitle: Commercial SaaS Product Launch
- Content: Include logo, medtechai.net URL, Abu Dhabi, UAE

## Slide 2: The Problem
- Screenshots to capture:
  - Current manual drug lookup process (show time wasted)
  - Search engines with unreliable medical info
  - Spreadsheet-based interaction tracking
- Text overlay:
  - "Healthcare professionals waste 10+ hours weekly on drug research"
  - "Manual processes lead to medication errors"
  - "Keeping up with regulations is time-consuming"

## Slide 3: Our Solution
- Screenshot: https://www.medtechai.net (hero section)
- Text overlay:
  - "30+ AI-powered clinical tools in one platform"
  - "Save hours. Reduce errors. Stay compliant."
  - "Arabic language support built-in"

## Slide 4: Star Product - Medical Document Analyzer
- Screenshot: https://www.medtechai.net/tools/rx-upload/
- Text overlay:
  - "Upload prescription or lab report"
  - "AI analyzes in seconds"
  - "Drug info, interactions, safety, lab values"

## Slide 5: Pricing
- Screenshot: https://www.medtechai.net/pricing/
- Text overlay:
  - "Free: Basic tools, 5 searches/day"
  - "Professional: $29/month - All tools unlimited"
  - "Enterprise: API access, team management"

## Slide 6: Interactive Demo
- Screenshot: https://www.medtechai.net/demo/interactive/drug-search.html
- Text overlay:
  - "Try before you buy"
  - "60-second walkthrough"
  - "Instant drug insights"

## Slide 7: Key Features
- Screenshots to capture (grid layout):
  - Drug Search - comprehensive info
  - Interaction Checker - safety alerts
  - Dose Calculator - weight-based dosing
  - ICD-10 Lookup - coding assistance
- Text overlay:
  - "Drug Search & Insight"
  - "Interaction Checker"
  - "Dose Calculator"
  - "ICD-10 Lookup"

## Slide 8: Market Opportunity
- Screenshot: stats section from homepage
- Text overlay:
  - "180+ countries served"
  - "10K+ clinicians using"
  - "UAE/GCC focus for commercial launch"

## Slide 9: Target Customers
- List with icons:
  - Pharmacies (2,500+ target)
  - Clinics (1,800+ target)
  - Hospitals (320+ target)
  - HealthTech firms
- Text overlay: "30 high-value leads identified in Abu Dhabi"

## Slide 10: Call to Action
- Screenshot: Contact page or hero CTA
- Text overlay:
  - "Request Demo: /demo/"
  - "See Pricing: /pricing/"
  - "Contact: contact@medtechai.net"

---

## Command to capture screenshots (PowerShell):

powershell -Command "
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

# Get active window screenshot
$bounds = [System.Windows.Forms.Screen]::PrimaryScreen.Bounds
$bitmap = New-Object System.Drawing.Bitmap $bounds.Width, $bounds.Height
$graphics = [System.Drawing.Graphics]::FromImage($bitmap)
$graphics.CopyFromScreen($bounds.Location, [System.Drawing.Point]::Empty, $bounds.Size)
$bitmap.Save('G:\2-Projects\Medical-Projects\medtechai org main\screenshot.png')
"