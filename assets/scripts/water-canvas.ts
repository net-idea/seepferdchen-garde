/**
 * Ambient "water" background: slowly drifting light patches (like caustics on a pool
 * floor) and a few rising bubbles, drawn on one fixed canvas behind the content.
 *
 * Guard rails:
 *  - prefers-reduced-motion  -> a single static frame, no animation loop
 *  - hidden tab              -> loop pauses
 *  - frame rate capped       -> ~30 fps, devicePixelRatio capped at 1.5
 *  - small screens           -> fewer elements
 *  - print                   -> hidden via print.css
 */

interface Patch {
  x: number; // base position in % of viewport
  y: number;
  r: number; // radius in % of the larger viewport side
  ax: number; // drift amplitude (%)
  ay: number;
  sx: number; // drift speed
  sy: number;
  phase: number;
  color: [number, number, number];
  alpha: number;
}

interface Bubble {
  x: number; // px
  y: number;
  r: number;
  speed: number; // px per second upwards
  sway: number;
  phase: number;
  alpha: number;
}

const FPS_CAP = 30;

function rand(min: number, max: number): number {
  return min + Math.random() * (max - min);
}

export function initWaterCanvas(): void {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const canvas = document.createElement('canvas');
  canvas.id = 'water-canvas';
  canvas.setAttribute('aria-hidden', 'true');
  document.body.prepend(canvas);

  const ctx = canvas.getContext('2d', { alpha: true });
  if (!ctx) return;

  let width = 0;
  let height = 0;
  let dpr = 1;
  let small = false;

  const patches: Patch[] = [
    { x: 12, y: 18, r: 42, ax: 6, ay: 4, sx: 0.045, sy: 0.031, phase: 0.0, color: [156, 195, 230], alpha: 0.22 },
    { x: 78, y: 12, r: 38, ax: 5, ay: 6, sx: 0.038, sy: 0.052, phase: 1.7, color: [15, 61, 76], alpha: 0.55 },
    { x: 88, y: 70, r: 44, ax: 7, ay: 4, sx: 0.029, sy: 0.041, phase: 3.1, color: [156, 195, 230], alpha: 0.18 },
    { x: 30, y: 85, r: 40, ax: 5, ay: 5, sx: 0.051, sy: 0.036, phase: 4.4, color: [18, 53, 91], alpha: 0.6 },
    { x: 55, y: 45, r: 30, ax: 8, ay: 6, sx: 0.033, sy: 0.047, phase: 2.3, color: [197, 221, 242], alpha: 0.1 },
    { x: 5, y: 55, r: 28, ax: 4, ay: 7, sx: 0.042, sy: 0.028, phase: 5.2, color: [10, 54, 93], alpha: 0.5 },
  ];

  let bubbles: Bubble[] = [];

  const makeBubble = (startAnywhere: boolean): Bubble => ({
    x: rand(0, width),
    y: startAnywhere ? rand(0, height) : height + rand(10, 80),
    r: rand(2, 7),
    speed: rand(10, 26),
    sway: rand(6, 18),
    phase: rand(0, Math.PI * 2),
    alpha: rand(0.22, 0.5),
  });

  const resize = (): void => {
    width = window.innerWidth;
    height = window.innerHeight;
    small = width < 768;
    dpr = Math.min(window.devicePixelRatio || 1, 1.5);
    canvas.width = Math.round(width * dpr);
    canvas.height = Math.round(height * dpr);
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    const count = small ? 6 : 20;
    bubbles = Array.from({ length: count }, () => makeBubble(true));
  };

  const draw = (t: number, dt: number): void => {
    ctx.clearRect(0, 0, width, height);

    // --- light patches (additive, very soft) ---
    ctx.globalCompositeOperation = 'lighter';
    const base = Math.max(width, height);
    const visiblePatches = small ? patches.slice(0, 4) : patches;

    for (const p of visiblePatches) {
      const cx = (p.x + Math.sin(t * p.sx + p.phase) * p.ax) * 0.01 * width;
      const cy = (p.y + Math.cos(t * p.sy + p.phase) * p.ay) * 0.01 * height;
      const r = p.r * 0.01 * base;
      const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, r);
      const [cr, cg, cb] = p.color;
      g.addColorStop(0, `rgba(${cr}, ${cg}, ${cb}, ${p.alpha})`);
      g.addColorStop(0.55, `rgba(${cr}, ${cg}, ${cb}, ${p.alpha * 0.35})`);
      g.addColorStop(1, `rgba(${cr}, ${cg}, ${cb}, 0)`);
      ctx.fillStyle = g;
      ctx.beginPath();
      ctx.arc(cx, cy, r, 0, Math.PI * 2);
      ctx.fill();
    }

    // --- bubbles ---
    ctx.globalCompositeOperation = 'source-over';
    for (const b of bubbles) {
      if (dt > 0) {
        b.y -= b.speed * dt;
        if (b.y < -20) {
          Object.assign(b, makeBubble(false));
        }
      }
      const x = b.x + Math.sin(t * 0.8 + b.phase) * b.sway;
      ctx.beginPath();
      ctx.arc(x, b.y, b.r, 0, Math.PI * 2);
      ctx.strokeStyle = `rgba(197, 221, 242, ${b.alpha})`;
      ctx.lineWidth = 1;
      ctx.stroke();
      ctx.beginPath();
      ctx.arc(x - b.r * 0.35, b.y - b.r * 0.35, Math.max(0.6, b.r * 0.28), 0, Math.PI * 2);
      ctx.fillStyle = `rgba(255, 255, 255, ${b.alpha * 0.9})`;
      ctx.fill();
    }
  };

  resize();
  window.addEventListener('resize', resize, { passive: true });

  if (reduceMotion) {
    draw(0, 0);
    return;
  }

  let last = 0;
  let acc = 0;
  let running = true;
  const minFrame = 1000 / FPS_CAP;

  const loop = (now: number): void => {
    if (!running) return;
    window.requestAnimationFrame(loop);
    if (!last) last = now;
    const elapsed = now - last;
    if (elapsed < minFrame) return;
    last = now;
    acc += elapsed / 1000;
    draw(acc, Math.min(elapsed / 1000, 0.1));
  };

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      running = false;
    } else if (!running) {
      running = true;
      last = 0;
      window.requestAnimationFrame(loop);
    }
  });

  window.requestAnimationFrame(loop);
}

initWaterCanvas();
