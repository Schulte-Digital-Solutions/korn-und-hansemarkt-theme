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
 * Unterstützt: /path/:param und Wildcard *
 */
function matchRoute(pattern: string, path: string): Record<string, string> | null {
  // Wildcard: matcht alles
  if (pattern === '*') {
    return { wild: path };
  }

  const patternParts = pattern.split('/').filter(Boolean);
  const pathParts = path.split('/').filter(Boolean);

  // Exakte Länge muss stimmen (außer bei leerem pattern & path)
  if (patternParts.length !== pathParts.length) {
    return null;
  }

  // Leerer Pfad = Startseite
  if (patternParts.length === 0 && pathParts.length === 0) {
    return {};
  }

  const params: Record<string, string> = {};

  for (let i = 0; i < patternParts.length; i++) {
    const pat = patternParts[i];
    const val = pathParts[i];

    if (pat.startsWith(':')) {
      params[pat.slice(1)] = decodeURIComponent(val);
    } else if (pat !== val) {
      return null;
    }
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

/**
 * Aktuelle Route aus dem Pathname lesen (ohne WordPress-Basispfad)
 */
export function getCurrentPath(): string {
  const base = getBasePath();
  let path = window.location.pathname || '/';
  if (base && path.startsWith(base)) {
    path = path.slice(base.length) || '/';
  }
  return path.startsWith('/') ? path : '/' + path;
}

/**
 * Navigation zu einem Pfad
 */
export function navigate(path: string) {
  const base = getBasePath();
  const target = path.startsWith('/') ? path : '/' + path;
  window.history.pushState({}, '', base + target);
  window.dispatchEvent(new PopStateEvent('popstate'));
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
