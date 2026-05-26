# Visual Identity Extraction Plan

## Source Image
- File: `Gemini_Generated_Image_h128jsh128jsh128 (1).png`
- Size: 1.5 MB
- Location: `G:\2-Projects\Medical-Projects\medtechai org main\Gemini_Generated_Image_h128jsh128jsh128 (1).png`

## Elements to Extract

### 1. Logo
- **Purpose**: Primary brand identifier
- **Format**: SVG (preferred) or PNG with transparent background
- **Sizes**: 256x256px (master), 128x128px (favicon), 64x64px (thumbnail)
- **Usage**: Website header, favicon, social media, documents
- **Target location**: `assets/img/logo/`

### 2. Hero Image
- **Purpose**: Main visual for homepage/about pages
- **Format**: PNG or JPEG
- **Sizes**: 1920x1080px (desktop), 1200x800px (tablet), 800x600px (mobile)
- **Usage**: Homepage hero section, about page header
- **Target location**: `assets/img/hero/`

### 3. Visual Identity Elements
- **Color palette**: Primary, secondary, accent, text, background colors (HEX/RGB/HSL)
- **Typography**: Font families, weights, sizes for headings, body, captions
- **Spacing**: Grid system, margins, padding values
- **Icons**: Style, size, usage guidelines
- **Target location**: `docs/design/visual-identity.md`

## Extraction Process

### Manual Extraction (Recommended)
1. Open `Gemini_Generated_Image_h128jsh128jsh128 (1).png` in Photoshop/Figma/GIMP
2. Use selection tools to isolate logo and hero elements
3. Export as separate files with appropriate formats
4. Save to target locations

### Automated Extraction (Limited)
Without image processing libraries, automated extraction is not feasible. Consider:
- Using Python with OpenCV/Pillow
- Using ImageMagick command-line tools
- Using online extraction services

## Directory Structure

```
asets/
  img/
    logo/
      logo.svg          # Primary vector logo
      logo-256.png      # 256x256 PNG
      logo-128.png      # 128x128 PNG (favicon)
      logo-64.png        # 64x64 PNG (thumbnail)
    hero/
      hero-desktop.png   # 1920x1080
      hero-tablet.png    # 1200x800
      hero-mobile.png    # 800x600

docs/
  design/
    visual-identity.md  # Color palette, typography, spacing
```

## Next Steps

1. **Manual extraction**: Use design software to extract elements
2. **Document colors**: Use color picker to record HEX values
3. **Document fonts**: Identify font families and weights
4. **Save assets**: Place files in the directory structure above
5. **Update CSS**: Reference new assets in `shared.css`

## Tools for Manual Extraction

- **Photoshop**: Magic Wand, Pen Tool, Export As
- **Figma**: Frame selection, Export options
- **GIMP**: Fuzzy Select, Paths Tool, Export As
- **Online**: remove.bg, canva.com, photopea.com

## Notes

- Maintain transparency for logos
- Optimize PNGs for web (compression level 6-8)
- Document all colors in HEX format
- Specify fallback fonts for web
