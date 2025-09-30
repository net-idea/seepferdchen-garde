const STORAGE_KEY = 'color-theme';
const THEME_LIGHT = 'light';
const THEME_DARK = 'dark';

type Theme = 'light' | 'dark' | 'auto';

const KEY_NEW = 'theme';
const KEY_OLD = 'color-theme';

function getSystemPreference(): Theme {
  if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    return THEME_DARK;
  }
  return THEME_LIGHT;
}

function getStoredTheme(): Theme | null {
  const v = localStorage.getItem(KEY_NEW) || localStorage.getItem(KEY_OLD);
  return v === 'light' || v === 'dark' || v === 'auto' ? v : null;
}

function storeTheme(theme: Theme) {
  try {
    localStorage.setItem(KEY_NEW, theme);
  } catch { /* ignore */
  }
}

function systemPrefersDark(): boolean {
  return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function resolveTheme(theme: Theme | null): 'light' | 'dark' {
  if (theme === 'dark') return 'dark';
  if (theme === 'light') return 'light';
  return systemPrefersDark() ? 'dark' : 'light';
}

function applyTheme(theme: Theme | null) {
  const resolved = resolveTheme(theme);
  document.documentElement.setAttribute('data-bs-theme', resolved);
}

function updateDropdownUI(theme: Theme | null) {
  const current = theme ?? 'auto';
  const toggleBtn = document.getElementById('bd-theme');
  const activeIcon = toggleBtn?.querySelector<SVGUseElement>('.theme-icon-active use');
  const items = document.querySelectorAll<HTMLButtonElement>('button[data-bs-theme-value]');

  items.forEach((btn) => {
    const val = btn.getAttribute('data-bs-theme-value') as Theme | null;
    const isActive = val === current;
    btn.classList.toggle('active', isActive);
    btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    // Show checkmark on the active item
    const checks = btn.querySelectorAll<SVGElement>('.bi.ms-auto');
    checks.forEach((c) => c.classList.toggle('d-none', !isActive));
  });

  // Update toggle icon and aria-label
  if (toggleBtn && activeIcon) {
    let icon = '#circle-half';
    if (current === 'light') icon = '#sun-fill';
    else if (current === 'dark') icon = '#moon-stars-fill';
    activeIcon.setAttribute('href', icon);
    const labelMap: Record<Theme, string> = {
      light: 'Darstellung umschalten (hell)',
      dark: 'Darstellung umschalten (dunkel)',
      auto: 'Darstellung umschalten (auto)'
    };
    toggleBtn.setAttribute('aria-label', labelMap[current]);
  }
}

function initThemeDropdown() {
  const dropdown = document.querySelector('ul.dropdown-menu [data-bs-theme-value]');
  // If dropdown does not exist on this page, do nothing
  if (!dropdown) return;

  const stored = getStoredTheme();
  applyTheme(stored);
  updateDropdownUI(stored);

  const items = document.querySelectorAll<HTMLButtonElement>('button[data-bs-theme-value]');
  items.forEach((btn) => {
    btn.addEventListener('click', () => {
      const val = btn.getAttribute('data-bs-theme-value') as Theme | null;
      if (!val) return;
      storeTheme(val);
      applyTheme(val);
      updateDropdownUI(val);
    });
  });

  // React to system changes when auto is selected
  if (window.matchMedia) {
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    mq.addEventListener('change', () => {
      const current = getStoredTheme();
      if (current === 'auto') {
        applyTheme('auto');
        updateDropdownUI('auto');
      }
    });
  }
}

document.addEventListener('DOMContentLoaded', initThemeDropdown);
