// Generates Open Graph images for Seepferdchen‑Garde into public/assets/og/
const fs = require('fs');
const path = require('path');
const sharp = require('sharp');

const OUT_DIR = path.join(process.cwd(), 'public', 'assets', 'og');
const WIDTH = 1200;
const HEIGHT = 630;
const BRAND = 'Seepferdchen‑Garde';
const BRAND_SUBLINE = 'Schwimmschule Riccardo Nappa';


const pages = [
  {slug: 'start', title: 'Startseite', subtitle: 'Schwimmschule Riccardo Nappa'},
  {slug: 'schwimmkurse', title: 'Schwimmkurse', subtitle: 'In kleinen Gruppen sicher zum Seepferdchen'},
  {slug: 'ueber-mich', title: 'Über mich', subtitle: 'Erfahrung, Sicherheit und Freude am Schwimmen'},
  {slug: 'kontakt', title: 'Kontakt', subtitle: 'Telefon, WhatsApp oder Kontaktformular'},
  {slug: 'anmeldung', title: 'Anmeldung', subtitle: 'Anmeldung zu den Schwimmkursen'},
  {slug: 'impressum', title: 'Impressum', subtitle: 'Anbieterkennzeichnung und Kontakt'},
  {slug: 'haftungsausschluss', title: 'Haftungsausschluss', subtitle: 'Haftungsausschluss für die Teilnahme am Schwimmkurs'},
  {slug: 'datenschutz', title: 'Datenschutzerklärung', subtitle: 'Informationen zum Datenschutz'},
];

const palettes = {
  start: ['#86f2ff', '#4dd9ff', '#0098d6', '#004f9d'],
  schwimmkurse: ['#7ef6ea', '#34d3db', '#028fb0', '#02506d'],
  'ueber-mich': ['#8ac5ff', '#4c9ef5', '#1d66c0', '#103a72'],
  kontakt: ['#99e9ff', '#52cfff', '#118bb7', '#0a5574'],
  anmeldung: ['#88ffe9', '#39e5d3', '#0399c9', '#045489'],
  impressum: ['#b1d3e6', '#78aac6', '#38617d', '#1f3a4c'],
  haftungsausschluss: ['#b9e9ff', '#6fc8ff', '#1f81c7', '#074a7d'],
  datenschutz: ['#8fe0e7', '#47bcc7', '#0e768a', '#064754'],
};

const LOGO_SVG_PATH = path.join(process.cwd(), 'public', 'favicon.svg');
let logoDataUri = null;
try {
  const raw = fs.readFileSync(LOGO_SVG_PATH, 'utf8');
  const cleaned = raw.replace(/<!--.*?-->/gs, '').replace(/\r?\n+/g, ' ').replace(/>\s+</g, '><').trim();
  logoDataUri = 'data:image/svg+xml;base64,' + Buffer.from(cleaned).toString('base64');
} catch (e) {
  console.warn('Logo SVG not found or unreadable, continuing without embedded logo.');
}

const NUNITO_FONT_PATH = path.join(process.cwd(), 'assets', 'fonts', 'nunito-variablefontwght.woff2');
let nunitoFontFace = '';
try {
  const nunito = fs.readFileSync(NUNITO_FONT_PATH);
  nunitoFontFace = `@font-face{font-family:"Nunito";font-style:normal;font-weight:200 900;font-display:swap;src:url(data:font/woff2;base64,${nunito.toString('base64')}) format('woff2');}`;
} catch(e){ console.warn('Nunito font not found, falling back to system fonts'); }

function wrapLines(text, maxChars) {
  const words = (text || '').toString().split(/\s+/).filter(Boolean);
  const lines = [];
  let line='';
  for (const w of words){ if ((line+' '+w).trim().length>maxChars){ if(line) lines.push(line); line=w; } else { line=(line?line+' ':'')+w; } }
  if(line) lines.push(line); return lines;
}

function escapeXml(str){return (str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

// Estimate char width factor for Nunito bold (rough average)
const CHAR_WIDTH_FACTOR = 0.55; // width ≈ factor * fontSize

function computeDynamicTitleLayout(title, availableWidth){
  const baseSize=82; let fontSize=baseSize; let maxChars=Math.max(8,Math.floor(availableWidth/(fontSize*CHAR_WIDTH_FACTOR)));
  let lines=wrapLines(title,maxChars); let longest=Math.max(...lines.map(l=>l.length));
  let neededScale=availableWidth/(longest*fontSize*CHAR_WIDTH_FACTOR); if(neededScale<1) fontSize=Math.max(54,Math.round(fontSize*neededScale));
  maxChars=Math.max(8,Math.floor(availableWidth/(fontSize*CHAR_WIDTH_FACTOR))); lines=wrapLines(title,maxChars); longest=Math.max(...lines.map(l=>l.length));
  neededScale=availableWidth/(longest*fontSize*CHAR_WIDTH_FACTOR); if(neededScale<1) fontSize=Math.max(50,Math.floor(fontSize*neededScale));
  return {fontSize,lines};
}

function buildGradientStops(colors){
  if(colors.length < 2) colors = [colors[0], colors[0]];
  const stops=[]; const lastIndex=colors.length-1;
  colors.forEach((col,i)=>{ const offset = (i/lastIndex*100).toFixed(1); stops.push(`<stop offset="${offset}%" stop-color="${col}"/>`); });
  return stops.join('');
}

function makeSVG({title, subtitle, brand, brandSubline, colors, logoDataUri }) {
  const cFirst = colors[0];
  const cLast = colors[colors.length-1];
  const padLeft=80, padTop=80, padRight=30;
  const logoPaddingY=30; const logoHeight=HEIGHT-logoPaddingY*2; const logoWidth=logoHeight*1.05; const logoX=WIDTH-logoWidth-padRight; const logoY=logoPaddingY;
  const contentRight=logoX-50; const availableWidth=contentRight-padLeft;

  // Dynamic title sizing & wrapping
  const { fontSize:titleFontSize, lines:titleLines } = computeDynamicTitleLayout(title, availableWidth);
  const titleLineGap=Math.round(titleFontSize*1.05);
  // Subtitle sizing: relate to title size but capped
  const subtitleFontSize=Math.min(40,Math.max(30,Math.round(titleFontSize*0.48)));
  const subtitleLineGap=Math.round(subtitleFontSize*1.1);
  const subtitleMaxChars=Math.max(10,Math.floor(availableWidth/(subtitleFontSize*0.52)));
  const subtitleLines=wrapLines(subtitle,subtitleMaxChars);

  // Vertical layout: center title block around y ≈ 260 like before, adjusted for dynamic height
  const titleBlockHeight=titleFontSize+(titleLines.length-1)*titleLineGap;
  const titleCenterRef=260;
  const titleYStart=Math.max(padTop+titleFontSize,Math.round(titleCenterRef-titleBlockHeight/2));
  const subtitleYStart=titleYStart+titleBlockHeight+Math.round(subtitleFontSize*0.2);

  const titleTspans=titleLines.map((l,i)=>`<tspan x="${padLeft}" dy="${i===0?0:titleLineGap}">${escapeXml(l)}</tspan>`).join('');
  const subtitleTspans=subtitleLines.map((l,i)=>`<tspan x="${padLeft}" dy="${i===0?0:subtitleLineGap}">${escapeXml(l)}</tspan>`).join('');

  const lines=[];
  lines.push(`<svg width="${WIDTH}" height="${HEIGHT}" viewBox="0 0 ${WIDTH} ${HEIGHT}" xmlns="http://www.w3.org/2000/svg">`);
  lines.push('<defs>');
  lines.push(`<linearGradient id="bg" x1="0" y1="0" x2="0" y2="1">${buildGradientStops(colors)}</linearGradient>`);
  lines.push(`<linearGradient id="glow" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="rgba(255,255,255,0.32)"/><stop offset="100%" stop-color="rgba(255,255,255,0)"/></linearGradient>`);
  lines.push(`<filter id="dropshadow" height="140%"><feGaussianBlur in="SourceAlpha" stdDeviation="4"/><feOffset dx="0" dy="2" result="o"/><feComponentTransfer><feFuncA type="linear" slope="0.30"/></feComponentTransfer><feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge></filter>`);
  // Softer outline: smaller width + partial opacity
  const outlineClass = '.outline{paint-order:stroke fill;stroke:#01324e;stroke-opacity:.40;stroke-width:1.3;stroke-linejoin:round;}';
  if(nunitoFontFace) lines.push(`<style><![CDATA[${nunitoFontFace} text, tspan { font-kerning:normal; } ${outlineClass}]]></style>`); else lines.push(`<style><![CDATA[${outlineClass}]]></style>`);
  lines.push('</defs>');
  lines.push(`<rect width="100%" height="100%" fill="url(#bg)"/>`);
  lines.push(`<ellipse cx="${WIDTH*0.72}" cy="${HEIGHT*0.20}" rx="${WIDTH*0.55}" ry="${HEIGHT*0.50}" fill="url(#glow)" />`);
  lines.push(`<text x="${padLeft}" y="${padTop}" fill="#FFFFFF" class="outline" font-family="Nunito, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif" font-size="32" font-weight="800" letter-spacing="0.5" filter="url(#dropshadow)">${escapeXml(brand)}</text>`);
  if(brandSubline) lines.push(`<text x="${padLeft}" y="${padTop+42}" fill="rgba(255,255,255,0.93)" class="outline" font-family="Nunito, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif" font-size="24" font-weight="600" filter="url(#dropshadow)">${escapeXml(brandSubline)}</text>`);
  lines.push(`<text x="${padLeft}" y="${titleYStart}" fill="#FFFFFF" class="outline" font-family="Nunito, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif" font-size="${titleFontSize}" font-weight="900" letter-spacing="0.5" filter="url(#dropshadow)">${titleTspans}</text>`);
  lines.push(`<text x="${padLeft}" y="${subtitleYStart}" fill="rgba(255,255,255,0.96)" class="outline" font-family="Nunito, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif" font-size="${subtitleFontSize}" font-weight="700" filter="url(#dropshadow)">${subtitleTspans}</text>`);
  if(logoDataUri) lines.push(`<image href="${logoDataUri}" x="${logoX}" y="${logoY}" width="${logoWidth}" height="${logoHeight}" preserveAspectRatio="xMidYMid meet" style="opacity:0.98" />`);
  lines.push(`<text x="${padLeft}" y="${HEIGHT-40}" fill="rgba(255,255,255,0.80)" class="outline" font-family="Nunito, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif" font-size="24" font-weight="600" filter="url(#dropshadow)">${escapeXml('seepferdchen-garde.de')}</text>`);
  lines.push('</svg>');
  return lines.join('\n');
}

async function ensureOutDir(){ await fs.promises.mkdir(OUT_DIR,{recursive:true}); }
async function generateOne({slug,title,subtitle}){
  const colors = palettes[slug] || ['#5EE7FF','#0066B2'];
  const svg = makeSVG({title, subtitle, brand: BRAND, brandSubline: BRAND_SUBLINE, colors, logoDataUri});
  const svgFile = path.join(OUT_DIR, `${slug}.svg`);
  const jpgFile = path.join(OUT_DIR, `${slug}.jpg`);
  await fs.promises.writeFile(svgFile, svg, 'utf8');
  await sharp(Buffer.from(svg)).jpeg({quality:90, progressive:true, chromaSubsampling:'4:4:4'}).toFile(jpgFile);
  process.stdout.write(`✓ ${path.relative(process.cwd(), jpgFile)} (and svg)\n`);
}
(async function main(){
  await ensureOutDir();
  for (const p of pages){
    const title = p.title || 'Seepferdchen‑Garde';
    const subtitle = p.subtitle || 'Schwimmschule Riccardo Nappa';
    await generateOne({slug:p.slug,title,subtitle});
  }
})().catch(err=>{ console.error('OG generation failed:', err); process.exit(1); });
