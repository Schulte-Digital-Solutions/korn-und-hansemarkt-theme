/**
 * Theme-Steuerung (Light / Dark / Auto).
 *
 * - Persistiert den gewählten Modus in localStorage unter `kuh-theme`.
 * - Setzt die Klasse `dark` auf <html>, wenn effektiv Darkmode aktiv ist.
 * - `auto` folgt `prefers-color-scheme: dark` und reagiert live auf Änderungen.
 */

export type ThemeMode = 'light' | 'dark' | 'auto';

const STORAGE_KEY = 'kuh-theme';

function getDefaultMode(): ThemeMode {
  const m = typeof window !== 'undefined' ? window.kuhData?.darkmode?.defaultMode : undefined;
  return m === 'light' || m === 'dark' || m === 'auto' ? m : 'auto';
}

function isDarkmodeEnabled(): boolean {
  return typeof window !== 'undefined' && window.kuhData?.darkmode?.enabled === true;
}

function readStored(): ThemeMode {
  try {
    const v = localStorage.getItem(STORAGE_KEY);
    if (v === 'light' || v === 'dark' || v === 'auto') return v;
  } catch {
    // ignore
  }
  return getDefaultMode();
}

function prefersDark(): boolean {
  return typeof window !== 'undefined'
    && window.matchMedia?.('(prefers-color-scheme: dark)').matches === true;
}

function apply(mode: ThemeMode) {
  const isDark = isDarkmodeEnabled() && (mode === 'dark' || (mode === 'auto' && prefersDark()));
  const root = document.documentElement;
  root.classList.toggle('dark', isDark);
  root.style.colorScheme = isDark ? 'dark' : 'light';
}

let currentMode = $state<ThemeMode>(
  typeof window !== 'undefined' ? readStored() : 'auto'
);

const isDark = $derived(
  currentMode === 'dark'
    || (currentMode === 'auto' && typeof window !== 'undefined' && prefersDark())
);

/**
 * Initialisiert das Theme-System. Wird einmalig beim App-Start aufgerufen.
 * Idempotent – mehrfacher Aufruf ist unproblematisch.
 */
let initialized = false;
export function initTheme() {
  if (initialized || typeof window === 'undefined') return;
  initialized = true;

  apply(currentMode);

  // System-Änderungen nur bei 'auto' übernehmen
  const mq = window.matchMedia('(prefers-color-scheme: dark)');
  mq.addEventListener('change', () => {
    if (currentMode === 'auto') apply('auto');
  });
}

export function getThemeMode(): ThemeMode {
  return currentMode;
}

export function setThemeMode(mode: ThemeMode) {
  currentMode = mode;
  try {
    localStorage.setItem(STORAGE_KEY, mode);
  } catch {
    // ignore
  }
  apply(mode);
}

/** Reaktiver Getter (Svelte 5 Rune-basiert) – in Components über `theme.mode` konsumierbar. */
export const theme = {
  get mode() {
    return currentMode;
  },
  get isDark() {
    return isDark;
  },
  get enabled() {
    return isDarkmodeEnabled();
  },
};
