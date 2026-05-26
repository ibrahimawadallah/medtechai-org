# Visual Identity

## Color Palette

**Primary**: #2563eb (Blue)
**Secondary**: #0891b2 (Teal)
**Accent**: #7c3aed (Purple)
**Success**: #059669 (Green)
**Warning**: #d97706 (Amber)
**Danger**: #dc2626 (Red)
**Text**: #1e293b (Slate)
**Background**: #f8fafc (Light Gray)
**Surface**: #ffffff (White)
**Border**: #e2e8f0 (Light Gray)

## Typography

**Font Family**: Inter, system-ui, sans-serif
**Weights**: 400, 500, 600, 700

### Headings
- H1: 20px, 700, #1e293b
- H2: 18px, 700, #1e293b
- H3: 16px, 700, #1e293b
- H4: 14px, 700, #1e293b

### Body
- Base: 14px, 400, #1e293b
- Small: 12px, 400, #475569
- Label: 13px, 600, #334155

## Spacing

**Base Unit**: 4px
**Scale**: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80, 96, 128

### Layout
- Grid gap: 24px
- Card padding: 16px
- Field margin: 18px
- Section margin: 24px

## Icons

**Style**: Outline, 16x16px or 20x20px
**Color**: Inherit or context-appropriate (blue for info, red for error, etc.)

## Usage

### Logo
```html
<img src="/assets/img/logo/logo.svg" alt="MedTechAI" width="160" height="40">
```

### Hero
```html
<div class="hero" style="background-image: url('/assets/img/hero/hero-desktop.png')">
  <div class="hero-content">...</div>
</div>
```

### Colors
```css
:root {
  --blue: #2563eb;
  --teal: #0891b2;
  --purple: #7c3aed;
  --green: #059669;
  --amber: #d97706;
  --red: #dc2626;
  --slate: #1e293b;
  --bg: #f8fafc;
  --surface: #ffffff;
  --border: #e2e8f0;
}
```
