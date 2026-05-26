import { useState } from "react";
import { Check, Copy } from "lucide-react";
import brandImage from "@/imports/Gemini_Generated_Image_h128jsh128jsh128__1_.png";
import { ImageWithFallback } from "@/app/components/figma/ImageWithFallback";

const brand = {
  colors: [
    { name: "Teal Primary", hex: "#38B8AE", rgb: "56, 184, 174", usage: "Logo, CTAs, highlights" },
    { name: "Deep Navy", hex: "#1A2D3A", rgb: "26, 45, 58", usage: "Headlines, body text" },
    { name: "Mint Mist", hex: "#E6F7F6", rgb: "230, 247, 246", usage: "Backgrounds, cards" },
    { name: "Soft Teal", hex: "#D1EFED", rgb: "209, 239, 237", usage: "Dividers, accents" },
    { name: "Slate Gray", hex: "#6B8A8E", rgb: "107, 138, 142", usage: "Secondary text, icons" },
    { name: "Pure White", hex: "#FFFFFF", rgb: "255, 255, 255", usage: "Surfaces, contrast" },
  ],
  typography: [
    { scale: "Display", family: "DM Sans", weight: "700", size: "48–64px", sample: "Modernizing Medicine" },
    { scale: "Heading 1", family: "DM Sans", weight: "600", size: "32px", sample: "AI-Powered Diagnostics" },
    { scale: "Heading 2", family: "DM Sans", weight: "500", size: "24px", sample: "Patient-Centered Care" },
    { scale: "Body", family: "Inter", weight: "400", size: "16px", sample: "Advancing healthcare outcomes through intelligent automation and data-driven insights." },
    { scale: "Caption", family: "Inter", weight: "400", size: "12px", sample: "Digital Branding in Context · UI Components · Print Applications" },
  ],
};

function MedTechLogo({ size = 40, showText = true }: { size?: number; showText?: boolean }) {
  const bar = size * 0.18;
  const r = size * 0.28;
  return (
    <div className="flex items-center gap-3">
      <svg width={size} height={size} viewBox="0 0 100 100" fill="none">
        <rect width="100" height="100" rx="22" fill="#38B8AE" />
        <rect x="38" y="16" width="24" height="68" rx="8" fill="white" />
        <rect x="16" y="38" width="68" height="24" rx="8" fill="white" />
      </svg>
      {showText && (
        <div>
          <div
            className="font-bold leading-tight tracking-tight"
            style={{ fontFamily: "DM Sans, sans-serif", fontSize: size * 0.4, color: "#1A2D3A" }}
          >
            MedTech <span style={{ color: "#38B8AE" }}>AI</span>
          </div>
          <div
            className="leading-none tracking-wide uppercase"
            style={{ fontFamily: "Inter, sans-serif", fontSize: size * 0.17, color: "#6B8A8E", letterSpacing: "0.1em" }}
          >
            Advancing Healthcare with AI
          </div>
        </div>
      )}
    </div>
  );
}

function ColorSwatch({ color }: { color: typeof brand.colors[0] }) {
  const [copied, setCopied] = useState(false);

  const handleCopy = () => {
    navigator.clipboard.writeText(color.hex);
    setCopied(true);
    setTimeout(() => setCopied(false), 1500);
  };

  const isDark = ["#1A2D3A", "#38B8AE"].includes(color.hex);

  return (
    <div className="rounded-2xl overflow-hidden border border-border shadow-sm group">
      <div
        className="h-28 w-full flex items-end p-3 relative cursor-pointer"
        style={{ backgroundColor: color.hex }}
        onClick={handleCopy}
      >
        <button
          className="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg p-1.5"
          style={{ backgroundColor: "rgba(255,255,255,0.25)" }}
        >
          {copied ? (
            <Check size={14} color={isDark ? "white" : "#1A2D3A"} />
          ) : (
            <Copy size={14} color={isDark ? "white" : "#1A2D3A"} />
          )}
        </button>
        <span
          className="font-mono text-sm font-semibold"
          style={{ color: isDark ? "rgba(255,255,255,0.9)" : "#1A2D3A" }}
        >
          {color.hex}
        </span>
      </div>
      <div className="p-4 bg-white">
        <p className="font-semibold text-sm text-foreground" style={{ fontFamily: "DM Sans, sans-serif" }}>
          {color.name}
        </p>
        <p className="text-xs text-muted-foreground mt-0.5 font-mono">rgb({color.rgb})</p>
        <p className="text-xs text-muted-foreground mt-1">{color.usage}</p>
      </div>
    </div>
  );
}

function SectionLabel({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex items-center gap-3 mb-8">
      <div className="w-6 h-0.5 rounded-full" style={{ backgroundColor: "#38B8AE" }} />
      <span
        className="text-xs font-semibold tracking-widest uppercase"
        style={{ fontFamily: "Inter, sans-serif", color: "#38B8AE" }}
      >
        {children}
      </span>
    </div>
  );
}

export default function App() {
  return (
    <div className="min-h-screen" style={{ fontFamily: "Inter, sans-serif", backgroundColor: "#f5fafa" }}>

      {/* Top nav */}
      <header className="sticky top-0 z-10 bg-white/80 backdrop-blur border-b border-border">
        <div className="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
          <MedTechLogo size={32} />
          <span
            className="text-xs tracking-widest uppercase font-semibold"
            style={{ color: "#6B8A8E", fontFamily: "Inter, sans-serif" }}
          >
            Brand Identity System
          </span>
        </div>
      </header>

      <main className="max-w-6xl mx-auto px-6 py-16 space-y-24">

        {/* Hero */}
        <section>
          <div className="grid lg:grid-cols-2 gap-12 items-center">
            <div>
              <p
                className="text-xs tracking-widest uppercase font-semibold mb-4"
                style={{ color: "#38B8AE", fontFamily: "Inter, sans-serif" }}
              >
                Visual Identity — 2024
              </p>
              <h1
                className="text-5xl font-bold leading-tight mb-5"
                style={{ fontFamily: "DM Sans, sans-serif", color: "#1A2D3A" }}
              >
                MedTech&nbsp;AI<br />
                <span style={{ color: "#38B8AE" }}>Brand System</span>
              </h1>
              <p className="text-lg leading-relaxed mb-8" style={{ color: "#6B8A8E" }}>
                A unified visual language designed to communicate trust, innovation, and precision — bridging clinical authority with modern digital experience.
              </p>
              <div className="flex items-center gap-4">
                <div
                  className="px-6 py-3 rounded-xl text-sm font-semibold text-white"
                  style={{ backgroundColor: "#38B8AE", fontFamily: "DM Sans, sans-serif" }}
                >
                  Primary CTA
                </div>
                <div
                  className="px-6 py-3 rounded-xl text-sm font-semibold border-2"
                  style={{ borderColor: "#38B8AE", color: "#38B8AE", fontFamily: "DM Sans, sans-serif" }}
                >
                  Secondary CTA
                </div>
              </div>
            </div>
            <div className="rounded-3xl overflow-hidden shadow-xl border border-border">
              <ImageWithFallback
                src={brandImage}
                alt="MedTech AI brand identity board showing logo, business cards, UI screens, and print applications"
                className="w-full h-auto object-cover"
              />
            </div>
          </div>
        </section>

        {/* Logo */}
        <section>
          <SectionLabel>Logomark</SectionLabel>
          <h2
            className="text-2xl font-semibold mb-8"
            style={{ fontFamily: "DM Sans, sans-serif", color: "#1A2D3A" }}
          >
            Logo Usage
          </h2>
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            {/* Primary on light */}
            <div className="rounded-2xl border border-border bg-white p-10 flex flex-col items-center gap-3">
              <MedTechLogo size={48} />
              <span className="text-xs text-muted-foreground mt-2">Primary — Light Background</span>
            </div>
            {/* Primary on dark */}
            <div className="rounded-2xl p-10 flex flex-col items-center gap-3" style={{ backgroundColor: "#1A2D3A" }}>
              <div className="flex items-center gap-3">
                <svg width={48} height={48} viewBox="0 0 100 100" fill="none">
                  <rect width="100" height="100" rx="22" fill="#38B8AE" />
                  <rect x="38" y="16" width="24" height="68" rx="8" fill="white" />
                  <rect x="16" y="38" width="68" height="24" rx="8" fill="white" />
                </svg>
                <div>
                  <div
                    className="font-bold leading-tight tracking-tight"
                    style={{ fontFamily: "DM Sans, sans-serif", fontSize: "19px", color: "#ffffff" }}
                  >
                    MedTech <span style={{ color: "#38B8AE" }}>AI</span>
                  </div>
                  <div
                    className="leading-none tracking-wide uppercase"
                    style={{ fontFamily: "Inter, sans-serif", fontSize: "8.2px", color: "rgba(255,255,255,0.5)", letterSpacing: "0.1em" }}
                  >
                    Advancing Healthcare with AI
                  </div>
                </div>
              </div>
              <span className="text-xs mt-2" style={{ color: "rgba(255,255,255,0.4)" }}>Reversed — Dark Background</span>
            </div>
            {/* Icon only */}
            <div className="rounded-2xl border border-border bg-white p-10 flex flex-col items-center justify-center gap-3">
              <svg width={64} height={64} viewBox="0 0 100 100" fill="none">
                <rect width="100" height="100" rx="22" fill="#38B8AE" />
                <rect x="38" y="16" width="24" height="68" rx="8" fill="white" />
                <rect x="16" y="38" width="68" height="24" rx="8" fill="white" />
              </svg>
              <span className="text-xs text-muted-foreground mt-2">Icon Only — App / Favicon</span>
            </div>
            {/* On teal */}
            <div className="rounded-2xl p-10 flex flex-col items-center gap-3" style={{ backgroundColor: "#38B8AE" }}>
              <div className="flex items-center gap-3">
                <svg width={48} height={48} viewBox="0 0 100 100" fill="none">
                  <rect width="100" height="100" rx="22" fill="white" />
                  <rect x="38" y="16" width="24" height="68" rx="8" fill="#38B8AE" />
                  <rect x="16" y="38" width="68" height="24" rx="8" fill="#38B8AE" />
                </svg>
                <div>
                  <div
                    className="font-bold leading-tight tracking-tight"
                    style={{ fontFamily: "DM Sans, sans-serif", fontSize: "19px", color: "#ffffff" }}
                  >
                    MedTech AI
                  </div>
                  <div
                    className="leading-none tracking-wide uppercase"
                    style={{ fontFamily: "Inter, sans-serif", fontSize: "8.2px", color: "rgba(255,255,255,0.7)", letterSpacing: "0.1em" }}
                  >
                    Advancing Healthcare with AI
                  </div>
                </div>
              </div>
              <span className="text-xs mt-2" style={{ color: "rgba(255,255,255,0.6)" }}>On Brand Primary</span>
            </div>
            {/* Wordmark only */}
            <div className="rounded-2xl border border-border bg-white p-10 flex flex-col items-center justify-center gap-3">
              <div
                className="font-bold"
                style={{ fontFamily: "DM Sans, sans-serif", fontSize: "28px", color: "#1A2D3A" }}
              >
                MedTech <span style={{ color: "#38B8AE" }}>AI</span>
              </div>
              <span className="text-xs text-muted-foreground">Wordmark Only</span>
            </div>
            {/* Minimum size */}
            <div className="rounded-2xl border border-border bg-white p-10 flex flex-col items-center justify-center gap-4">
              <div className="flex items-end gap-4">
                <svg width={20} height={20} viewBox="0 0 100 100" fill="none">
                  <rect width="100" height="100" rx="22" fill="#38B8AE" />
                  <rect x="38" y="16" width="24" height="68" rx="8" fill="white" />
                  <rect x="16" y="38" width="68" height="24" rx="8" fill="white" />
                </svg>
                <svg width={32} height={32} viewBox="0 0 100 100" fill="none">
                  <rect width="100" height="100" rx="22" fill="#38B8AE" />
                  <rect x="38" y="16" width="24" height="68" rx="8" fill="white" />
                  <rect x="16" y="38" width="68" height="24" rx="8" fill="white" />
                </svg>
                <svg width={48} height={48} viewBox="0 0 100 100" fill="none">
                  <rect width="100" height="100" rx="22" fill="#38B8AE" />
                  <rect x="38" y="16" width="24" height="68" rx="8" fill="white" />
                  <rect x="16" y="38" width="68" height="24" rx="8" fill="white" />
                </svg>
              </div>
              <span className="text-xs text-muted-foreground">Scale — 20px / 32px / 48px</span>
            </div>
          </div>
        </section>

        {/* Color Palette */}
        <section>
          <SectionLabel>Color System</SectionLabel>
          <h2
            className="text-2xl font-semibold mb-8"
            style={{ fontFamily: "DM Sans, sans-serif", color: "#1A2D3A" }}
          >
            Brand Palette
          </h2>
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            {brand.colors.map((color) => (
              <ColorSwatch key={color.hex} color={color} />
            ))}
          </div>
          <p className="mt-4 text-xs text-muted-foreground">Click any swatch to copy the hex code.</p>
        </section>

        {/* Typography */}
        <section>
          <SectionLabel>Typography</SectionLabel>
          <h2
            className="text-2xl font-semibold mb-8"
            style={{ fontFamily: "DM Sans, sans-serif", color: "#1A2D3A" }}
          >
            Type System
          </h2>
          <div className="bg-white rounded-3xl border border-border overflow-hidden divide-y divide-border">
            {brand.typography.map((t) => (
              <div key={t.scale} className="p-6 sm:p-8 grid sm:grid-cols-[160px_1fr] gap-4 items-baseline">
                <div>
                  <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">{t.scale}</p>
                  <p className="text-xs text-muted-foreground mt-1 font-mono">{t.family} · {t.weight} · {t.size}</p>
                </div>
                <p
                  style={{
                    fontFamily: t.family === "DM Sans" ? "DM Sans, sans-serif" : "Inter, sans-serif",
                    fontWeight: parseInt(t.weight),
                    fontSize: t.scale === "Display" ? "clamp(28px, 5vw, 48px)"
                      : t.scale === "Heading 1" ? "clamp(22px, 3vw, 32px)"
                      : t.scale === "Heading 2" ? "24px"
                      : t.scale === "Body" ? "16px"
                      : "12px",
                    color: "#1A2D3A",
                    lineHeight: 1.3,
                  }}
                >
                  {t.sample}
                </p>
              </div>
            ))}
          </div>
        </section>

        {/* UI Components preview */}
        <section>
          <SectionLabel>UI Components</SectionLabel>
          <h2
            className="text-2xl font-semibold mb-8"
            style={{ fontFamily: "DM Sans, sans-serif", color: "#1A2D3A" }}
          >
            Interface Elements
          </h2>
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            {/* Buttons */}
            <div className="bg-white rounded-2xl border border-border p-6 space-y-3">
              <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-4">Buttons</p>
              <button
                className="w-full py-3 rounded-xl font-semibold text-sm text-white transition-all hover:opacity-90 active:scale-95"
                style={{ backgroundColor: "#38B8AE", fontFamily: "DM Sans, sans-serif" }}
              >
                Get Started
              </button>
              <button
                className="w-full py-3 rounded-xl font-semibold text-sm border-2 transition-all hover:bg-secondary active:scale-95"
                style={{ borderColor: "#38B8AE", color: "#38B8AE", fontFamily: "DM Sans, sans-serif" }}
              >
                Learn More
              </button>
              <button
                className="w-full py-3 rounded-xl font-semibold text-sm transition-all hover:opacity-80"
                style={{ backgroundColor: "#1A2D3A", color: "#ffffff", fontFamily: "DM Sans, sans-serif" }}
              >
                Dark Variant
              </button>
            </div>

            {/* Input */}
            <div className="bg-white rounded-2xl border border-border p-6 space-y-4">
              <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-4">Inputs</p>
              <div>
                <label className="text-xs font-semibold mb-1.5 block" style={{ color: "#1A2D3A", fontFamily: "DM Sans, sans-serif" }}>
                  Patient ID
                </label>
                <input
                  className="w-full px-4 py-3 rounded-xl text-sm border outline-none transition-all"
                  style={{ borderColor: "#D1EFED", backgroundColor: "#f0f8f8", color: "#1A2D3A", fontFamily: "Inter, sans-serif" }}
                  placeholder="Enter patient ID..."
                  readOnly
                />
              </div>
              <div>
                <label className="text-xs font-semibold mb-1.5 block" style={{ color: "#1A2D3A", fontFamily: "DM Sans, sans-serif" }}>
                  Department
                </label>
                <input
                  className="w-full px-4 py-3 rounded-xl text-sm border outline-none"
                  style={{ borderColor: "#38B8AE", backgroundColor: "#f0f8f8", color: "#1A2D3A", boxShadow: "0 0 0 3px rgba(56,184,174,0.15)", fontFamily: "Inter, sans-serif" }}
                  defaultValue="Radiology"
                  readOnly
                />
              </div>
            </div>

            {/* Badge / Tags */}
            <div className="bg-white rounded-2xl border border-border p-6">
              <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-4">Badges & Tags</p>
              <div className="flex flex-wrap gap-2">
                {["AI Assisted", "Verified", "Pending Review", "Critical", "Routine", "Urgent"].map((label, i) => {
                  const styles = [
                    { bg: "#38B8AE", text: "#fff" },
                    { bg: "#E6F7F6", text: "#38B8AE" },
                    { bg: "#F0F8F8", text: "#6B8A8E" },
                    { bg: "#FEE2E2", text: "#EF4444" },
                    { bg: "#D1EFED", text: "#1A2D3A" },
                    { bg: "#FEF3C7", text: "#D97706" },
                  ];
                  return (
                    <span
                      key={label}
                      className="px-3 py-1.5 rounded-full text-xs font-semibold"
                      style={{ backgroundColor: styles[i].bg, color: styles[i].text, fontFamily: "DM Sans, sans-serif" }}
                    >
                      {label}
                    </span>
                  );
                })}
              </div>
            </div>

            {/* Card */}
            <div className="bg-white rounded-2xl border border-border p-6 lg:col-span-2">
              <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-4">Data Card</p>
              <div className="rounded-xl p-5" style={{ backgroundColor: "#E6F7F6" }}>
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-xs text-muted-foreground" style={{ fontFamily: "Inter, sans-serif" }}>Diagnostic Accuracy</p>
                    <p className="text-3xl font-bold mt-1" style={{ fontFamily: "DM Sans, sans-serif", color: "#1A2D3A" }}>
                      97.4<span className="text-lg font-medium text-muted-foreground">%</span>
                    </p>
                  </div>
                  <div className="w-10 h-10 rounded-xl flex items-center justify-center" style={{ backgroundColor: "#38B8AE" }}>
                    <svg width="20" height="20" viewBox="0 0 100 100" fill="none">
                      <rect x="38" y="8" width="24" height="84" rx="8" fill="white" />
                      <rect x="8" y="38" width="84" height="24" rx="8" fill="white" />
                    </svg>
                  </div>
                </div>
                <div className="mt-4 flex items-center gap-2">
                  <span className="text-xs font-semibold" style={{ color: "#38B8AE" }}>↑ 2.1%</span>
                  <span className="text-xs text-muted-foreground">vs last quarter</span>
                </div>
              </div>
            </div>

            {/* Alert */}
            <div className="bg-white rounded-2xl border border-border p-6">
              <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-4">Alerts</p>
              <div className="space-y-3">
                <div className="rounded-xl p-4 flex gap-3" style={{ backgroundColor: "#E6F7F6", borderLeft: "3px solid #38B8AE" }}>
                  <div className="text-xs leading-relaxed" style={{ color: "#1A2D3A", fontFamily: "Inter, sans-serif" }}>
                    <strong>AI analysis complete.</strong> Results ready for review.
                  </div>
                </div>
                <div className="rounded-xl p-4 flex gap-3" style={{ backgroundColor: "#FEF3C7", borderLeft: "3px solid #D97706" }}>
                  <div className="text-xs leading-relaxed" style={{ color: "#92400E", fontFamily: "Inter, sans-serif" }}>
                    <strong>Action required.</strong> Patient consent pending.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* Footer */}
        <footer className="border-t border-border pt-10 pb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
          <MedTechLogo size={28} />
          <p className="text-xs text-muted-foreground" style={{ fontFamily: "Inter, sans-serif" }}>
            MedTech AI Brand Identity System · 2024
          </p>
        </footer>
      </main>
    </div>
  );
}
