/**
 * Leichtgewichtiger History-API Router für Svelte 5
 */

export interface Route {
  path: string;
  component: any;
}

export interface RouteMatch {
  component: any;
  params: Record<string, string>;
}

/**
 * Route-Pattern mit Parametern matchen
 * Unterstützt:
 *  - /path/:param          – einzelnes Segment
 *  - /path/:rest*          – „Rest"-Parameter, matched 1..n Segmente (nur am Ende)
 *  - *                     – Wildcard, matched alles
 */
function matchRoute(pattern: string, path: string): Record<string, string> | null {
  // Wildcard: matcht alles
  if (pattern === '*') {
    return { wild: path };
  }

  const patternParts = pattern.split('/').filter(Boolean);
  const pathParts = path.split('/').filter(Boolean);

  // Leerer Pfad = Startseite
  if (patternParts.length === 0 && pathParts.length === 0) {
    return {};
  }

  const lastPat = patternParts[patternParts.length - 1];
  const hasRest = typeof lastPat === 'string' && lastPat.startsWith(':') && lastPat.endsWith('*');

  if (hasRest) {
    // Rest-Parameter: mindestens so viele Segmente wie Fix-Teile davor.
    if (pathParts.length < patternParts.length) {
      return null;
    }
  } else if (patternParts.length !== pathParts.length) {
    return null;
  }

  const params: Record<string, string> = {};
  const fixedLen = hasRest ? patternParts.length - 1 : patternParts.length;

  for (let i = 0; i < fixedLen; i++) {
    const pat = patternParts[i];
    const val = pathParts[i];

    if (pat.startsWith(':')) {
      params[pat.slice(1)] = decodeURIComponent(val);
    } else if (pat !== val) {
      return null;
    }
  }

  if (hasRest) {
    const name = lastPat.slice(1, -1); // ":foo*" -> "foo"
    const rest = pathParts.slice(fixedLen).map(decodeURIComponent).join('/');
    params[name] = rest;
  }

  return params;
}

/**
 * WordPress-Basispfad ermitteln (z.B. "/korn-und-hansemarkt/" oder "/")
 */
function getBasePath(): string {
  try {
    const homeUrl = window.kuhData?.homeUrl;
    if (homeUrl) {
      return new URL(homeUrl).pathname.replace(/\/+$/, '');
    }
  } catch {
    // Fallback
  }
  return '';
}

function normalizePath(path: string): string {
  const clean = path.split('?')[0].split('#')[0];
  const normalized = clean.replace(/\/+$/, '');
  return normalized === '' ? '/' : normalized.startsWith('/') ? normalized : '/' + normalized;
}

function getStickyHeaderOffset(): number {
  const header = document.querySelector('header');
  if (!header) return 0;

  const style = window.getComputedStyle(header);
  const isOverlaying = style.position === 'sticky' || style.position === 'fixed';
  if (!isOverlaying) return 0;

  // Kleiner Puffer, damit Ueberschriften nicht direkt am Header kleben.
  return Math.ceil(header.getBoundingClientRect().height + 12);
}

/** So lange auf den Anker warten – Seiteninhalte kommen per REST nachgeladen. */
const HASH_WAIT_MS = 5000;
/** So lange nach dem Fund nachkorrigieren – Blockmount, Bilder und Fonts verschieben das Layout. */
const HASH_SETTLE_MS = 900;
/** Abstand der Versuche. */
const HASH_POLL_MS = 50;

/** Laufende Anker-Verfolgung; ein neuer Wunsch bricht die vorherige ab. */
let _hashRequestId = 0;

/**
 * Zum Anker scrollen – robust gegen die Asynchronitaet der SPA.
 *
 * Das Ziel existiert beim Direktaufruf von z.B. /programm#buehnenplan noch
 * nicht: Der Router mountet zuerst, der Seiteninhalt kommt erst per REST,
 * danach mounten die Svelte-Bloecke (reinitBlocks) und zum Schluss laden
 * Bilder und Fonts. Deshalb wird auf den Anker gewartet und die Position
 * anschliessend noch kurz nachkorrigiert.
 *
 * Bewusst setTimeout statt requestAnimationFrame: in einem Hintergrund-Tab
 * (Link per Mittelklick geoeffnet) laufen keine Animation-Frames, der Sprung
 * wuerde dort nie passieren. Scrollt der Nutzer selbst, bricht die
 * Verfolgung ab.
 */
export function scrollToHash(hash: string, onMissing?: () => void) {
  const normalizedHash = hash && hash.startsWith('#') ? hash : '';
  if (!normalizedHash || normalizedHash === '#') {
    onMissing?.();
    return;
  }

  const targetId = decodeURIComponent(normalizedHash.slice(1));
  if (!targetId) {
    onMissing?.();
    return;
  }

  const requestId = ++_hashRequestId;
  const startedAt = Date.now();
  let foundAt = 0;
  let interrupted = false;

  const onUserInput = () => {
    interrupted = true;
  };
  const userEvents = ['wheel', 'touchstart', 'keydown', 'mousedown'] as const;
  const listen = (add: boolean) => {
    for (const type of userEvents) {
      if (add) {
        window.addEventListener(type, onUserInput, { passive: true });
      } else {
        window.removeEventListener(type, onUserInput);
      }
    }
  };
  listen(true);

  const tick = () => {
    const now = Date.now();

    // Abgeloest durch einen neueren Anker oder vom Nutzer uebersteuert.
    if (requestId !== _hashRequestId || interrupted) {
      listen(false);
      return;
    }

    const target = document.getElementById(targetId);

    if (!target) {
      if (now - startedAt > HASH_WAIT_MS) {
        listen(false);
        onMissing?.();
        return;
      }
      setTimeout(tick, HASH_POLL_MS);
      return;
    }

    if (!foundAt) {
      foundAt = now;
      // Der Anker gewinnt – die gemerkte Position darf nicht dazwischenfunken.
      try {
        sessionStorage.removeItem(SCROLL_KEY);
      } catch {
        // sessionStorage nicht verfügbar
      }
    }

    const offset = getStickyHeaderOffset();
    const targetTop = target.getBoundingClientRect().top + window.scrollY;
    const top = Math.max(0, targetTop - offset);

    if (Math.abs(window.scrollY - top) > 1) {
      window.scrollTo({ top, behavior: 'auto' });
    }

    if (now - foundAt < HASH_SETTLE_MS) {
      setTimeout(tick, HASH_POLL_MS);
      return;
    }

    listen(false);
  };

  tick();
}

/** Ist ein Anker in der URL, der angesprungen werden soll? */
function hasHashTarget(): boolean {
  const hash = window.location.hash || '';
  return hash.length > 1;
}

/**
 * Callback nach dem naechsten Layout ausfuehren.
 *
 * In Hintergrund-Tabs liefert der Browser keine Animation-Frames, deshalb
 * zusaetzlich ein Timer – es gewinnt, was zuerst kommt.
 */
function afterLayout(fn: () => void) {
  let done = false;
  const run = () => {
    if (done) return;
    done = true;
    fn();
  };
  requestAnimationFrame(run);
  setTimeout(run, 50);
}

/**
 * Aktuelle Route aus dem Pathname lesen (ohne WordPress-Basispfad)
 */
export function getCurrentPath(): string {
  const base = getBasePath();
  let path = window.location.pathname || '/';
  if (base && path.startsWith(base)) {
    path = path.slice(base.length) || '/';
  }
  return normalizePath(path);
}

/**
 * Navigation zu einem Pfad
 */
export function navigate(path: string) {
  const base = getBasePath();
  const parsed = new URL(path, window.location.href);
  let nextPath = parsed.pathname || '/';

  if (base && nextPath.startsWith(base)) {
    nextPath = nextPath.slice(base.length) || '/';
  }

  const currentPath = normalizePath(getCurrentPath());
  const targetPath = normalizePath(nextPath);
  const targetSearch = parsed.search || '';
  const targetHash = parsed.hash || '';

  const currentSearch = window.location.search || '';

  const sameRoute = currentPath === targetPath && currentSearch === targetSearch;

  window.history.pushState({ scrollY: 0 }, '', `${base}${targetPath}${targetSearch}${targetHash}`);

  _pendingScrollY = 0;

  if (!sameRoute) {
    window.scrollTo(0, 0);
  }

  window.dispatchEvent(new PopStateEvent('popstate'));

  if (targetHash) {
    scrollToHash(targetHash);
  }
}

/**
 * Route für den aktuellen Pfad finden
 */
export function resolveRoute(routes: Route[], path: string): RouteMatch | null {
  for (const route of routes) {
    const params = matchRoute(route.path, path);
    if (params !== null) {
      return { component: route.component, params };
    }
  }
  return null;
}

// --- Scroll-Wiederherstellung ---

let _pendingScrollY = 0;
let _scrollTimer: ReturnType<typeof setTimeout> | null = null;
const SCROLL_KEY = 'kuh_scroll';

/**
 * Scrollposition laufend im history.state speichern (debounced).
 * So ist der Wert bei Back/Forward immer aktuell.
 */
function onScroll() {
  if (_scrollTimer) clearTimeout(_scrollTimer);
  _scrollTimer = setTimeout(() => {
    history.replaceState({ ...history.state, scrollY: window.scrollY }, '');
  }, 100);
}

/**
 * Wird vom Router bei popstate aufgerufen (Back/Forward-Navigation)
 */
export function handlePopState() {
  _pendingScrollY = history.state?.scrollY ?? 0;
}

/**
 * Scrollposition wiederherstellen (nach dem Laden des Inhalts aufrufen)
 */
export function restoreScrollPosition() {
  // Back/Forward-Navigation
  if (_pendingScrollY > 0) {
    const y = _pendingScrollY;
    afterLayout(() => window.scrollTo(0, y));
    _pendingScrollY = 0;
    return;
  }

  // Anker in der URL hat Vorrang vor der gemerkten Position: /programm#buehnenplan
  // soll zum Anker springen. Jetzt ist der Inhalt im DOM – hier greift der Sprung
  // auch beim Direktaufruf, wo der Versuch beim Router-Mount noch zu frueh kam.
  // Gibt es zum Hash kein Element (z.B. Karten-Hashes wie /karte#markt),
  // wird die gemerkte Position nachtraeglich wiederhergestellt.
  if (hasHashTarget()) {
    scrollToHash(window.location.hash, restoreSavedScroll);
    return;
  }

  restoreSavedScroll();
}

/**
 * Nach F5 gemerkte Scrollposition wiederherstellen.
 */
function restoreSavedScroll() {
  try {
    const raw = sessionStorage.getItem(SCROLL_KEY);
    if (!raw) return;
    const saved = JSON.parse(raw);
    if (saved.path === getCurrentPath()) {
      afterLayout(() => window.scrollTo(0, saved.y));
    }
    sessionStorage.removeItem(SCROLL_KEY);
  } catch {
    // ignorieren
  }
}

let _scrollRestorationReady = false;

/**
 * Scroll-Wiederherstellung initialisieren.
 *
 * Der Aufrufer (Router.svelte) ist ein $effect, der bei jedem Routenwechsel
 * neu laeuft – deshalb nur einmal ausfuehren. Sonst haengen die Listener
 * mehrfach am window und der Anker-Sprung wuerde bei Back/Forward die
 * gemerkte Scrollposition ueberschreiben.
 */
export function initScrollRestoration() {
  if (_scrollRestorationReady) return;
  _scrollRestorationReady = true;

  if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('hashchange', () => {
    scrollToHash(window.location.hash || '');
  });

  if (hasHashTarget()) {
    scrollToHash(window.location.hash);
  }

  window.addEventListener('beforeunload', () => {
    try {
      sessionStorage.setItem(SCROLL_KEY, JSON.stringify({
        path: getCurrentPath(),
        y: window.scrollY,
      }));
    } catch {
      // sessionStorage nicht verfügbar
    }
  });
}
