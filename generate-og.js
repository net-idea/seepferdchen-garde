// Generates Open Graph images for Seepferdchen‑Garde into public/assets/og/
const fs = require('fs');
const path = require('path');
const sharp = require('sharp');

const OUT_DIR = path.join(process.cwd(), 'public', 'assets', 'og');
const WIDTH = 1200;
const HEIGHT = 630;
const BRAND = 'Seepferdchen‑Garde | Schwimmschule Riccardo Nappa';

const pages = [
  {slug: 'start', title: 'Startseite', subtitle: 'Schwimmschule Riccardo Nappa'},
  {slug: 'schwimmkurse', title: 'Schwimmkurse', subtitle: 'In kleinen Gruppen sicher zum Seepferdchen'},
  {slug: 'ueber-mich', title: 'Über mich', subtitle: 'Erfahrung, Sicherheit und Freude am Schwimmen'},
  {slug: 'kontakt', title: 'Kontakt', subtitle: 'Telefon, WhatsApp oder Kontaktformular'},
  {slug: 'anmeldung', title: 'Anmeldung', subtitle: 'Voranmeldung und Informationen'},
  {slug: 'impressum', title: 'Impressum', subtitle: 'Anbieterkennzeichnung und Kontakt'},
  {slug: 'datenschutz', title: 'Datenschutzerklärung', subtitle: 'Informationen zum Datenschutz'},
];

// Color palettes per page (top to bottom gradient)
const palettes = {
  start: ['#0ea5e9', '#0369a1'],
  schwimmkurse: ['#22c55e', '#15803d'],
  'ueber-mich': ['#8b5cf6', '#6d28d9'],
  kontakt: ['#06b6d4', '#0e7490'],
  anmeldung: ['#f97316', '#c2410c'],
  impressum: ['#64748b', '#334155'],
  datenschutz: ['#14b8a6', '#0f766e'],
};

function wrapLines(text, maxChars) {
  const words = (text || '').toString().split(/\s+/).filter(Boolean);
  const lines = [];
  let line = '';
  for (const w of words) {
    if ((line + ' ' + w).trim().length > maxChars) {
      if (line) lines.push(line);
      line = w;
    } else {
      line = (line ? line + ' ' : '') + w;
    }
  }
  if (line) lines.push(line);
  return lines;
}

function escapeXml(str) {
  return (str || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function makeSVG({title, subtitle, brand, colors}) {
  const [c1, c2] = colors;
  const pad = 80;
  const titleLines = wrapLines(title, 22);
  const subtitleLines = wrapLines(subtitle, 40);

  const titleYStart = 250 - (titleLines.length - 1) * 56; // center-ish for multi-line
  const subtitleYStart = titleYStart + titleLines.length * 86 + 10;

  const titleTspans = titleLines
    .map((line, i) => `<tspan x="${pad}" dy="${i === 0 ? 0 : 86}">${escapeXml(line)}</tspan>`)
    .join('');

  const subtitleTspans = subtitleLines
    .map((line, i) => `<tspan x="${pad}" dy="${i === 0 ? 0 : 46}">${escapeXml(line)}</tspan>`)
    .join('');

  return `
<svg width="${WIDTH}" height="${HEIGHT}" viewBox="0 0 ${WIDTH} ${HEIGHT}" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="${c1}"/>
      <stop offset="100%" stop-color="${c2}"/>
    </linearGradient>
    <linearGradient id="glow" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="rgba(255,255,255,0.25)"/>
      <stop offset="100%" stop-color="rgba(255,255,255,0)"/>
    </linearGradient>
  </defs>

  <!-- Background -->
  <rect width="100%" height="100%" fill="url(#bg)"/>

  <!-- Soft spotlight -->
  <ellipse cx="${WIDTH * 0.7}" cy="${HEIGHT * 0.1}" rx="${WIDTH * 0.6}" ry="${HEIGHT * 0.5}" fill="url(#glow)" />

  <!-- Brand -->
  <text x="${pad}" y="${pad}" fill="rgba(255,255,255,0.92)" font-family="system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif" font-size="28" font-weight="700">
    ${escapeXml(brand)}
  </text>

  <!-- Title -->
  <text x="${pad}" y="${titleYStart}" fill="#ffffff" font-family="system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif" font-size="76" font-weight="800" letter-spacing="0.5">
    ${titleTspans}
  </text>

  <!-- Subtitle -->
  <text x="${pad}" y="${subtitleYStart}" fill="rgba(255,255,255,0.92)" font-family="system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif" font-size="36" font-weight="600">
    ${subtitleTspans}
  </text>

  <!-- Footer mark -->
  <text x="${pad}" y="${HEIGHT - pad / 2}" fill="rgba(255,255,255,0.65)" font-family="system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif" font-size="22" font-weight="600">
    ${escapeXml('seepferdchen-garde.de')}
  </text>
</svg>
`.trim();
}

async function ensureOutDir() {
  await fs.promises.mkdir(OUT_DIR, {recursive: true});
}

async function generateOne({slug, title, subtitle}) {
  const colors = palettes[slug] || ['#0ea5e9', '#0369a1'];
  const svg = makeSVG({title, subtitle, brand: BRAND, colors});
  const outFile = path.join(OUT_DIR, `${slug}.jpg`);
  await sharp(Buffer.from(svg))
    .jpeg({quality: 90, progressive: true, chromaSubsampling: '4:4:4'})
    .toFile(outFile);
  process.stdout.write(`✓ ${path.relative(process.cwd(), outFile)}\n`);
}

(async function main() {
  await ensureOutDir();
  for (const p of pages) {
    // Safety: fallback titles if ever undefined
    const title = p.title || 'Seahorse Guard';
    const subtitle = p.subtitle || 'Schwimmschule Riccardo Nappa';
    await generateOne({slug: p.slug, title, subtitle});
  }
})().catch(err => {
  console.error('OG generation failed:', err);
  process.exit(1);
});
