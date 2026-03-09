import type { KuhData, WPPost, WPPage } from './types';

/**
 * WordPress-Konfiguration aus dem globalen Objekt laden
 */
export function getConfig(): KuhData {
  return window.kuhData;
}

/**
 * Fetch-Wrapper für die WordPress REST API
 *
 * Unterstützt sowohl Pretty Permalinks (/wp-json/) als auch
 * Plain Permalinks (?rest_route=/).
 */
async function apiFetch<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
  const config = getConfig();
  let url: string;

  if (config.restUrl.includes('?rest_route=')) {
    // Plain Permalinks: ?rest_route=/wp/v2/posts&slug=xyz&_embed
    const [path, query] = endpoint.split('?');
    url = `${config.restUrl}${path}`;
    if (query) {
      url += '&' + query;
    }
  } else {
    url = `${config.restUrl}${endpoint}`;
  }

  const response = await fetch(url, {
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.restNonce,
      ...options.headers,
    },
    ...options,
  });

  if (!response.ok) {
    throw new Error(`API Error: ${response.status} ${response.statusText}`);
  }

  return response.json();
}

/**
 * Alle Posts laden
 */
export async function getPosts(page = 1, perPage = 10): Promise<WPPost[]> {
  return apiFetch<WPPost[]>(
    `wp/v2/posts?page=${page}&per_page=${perPage}&_embed`
  );
}

interface WPCategory {
  id: number;
  name: string;
  slug: string;
  description: string;
  count: number;
}

export { type WPCategory };

/**
 * Alle Kategorien laden (nur mit Beiträgen)
 */
export async function getCategories(): Promise<WPCategory[]> {
  return apiFetch<WPCategory[]>(
    'wp/v2/categories?per_page=100&hide_empty=true'
  );
}

/**
 * Kategorie nach Slug laden
 */
export async function getCategoryBySlug(slug: string): Promise<WPCategory | null> {
  const cats = await apiFetch<WPCategory[]>(
    `wp/v2/categories?slug=${encodeURIComponent(slug)}`
  );
  return cats.length > 0 ? cats[0] : null;
}

/**
 * Posts einer Kategorie laden
 */
export async function getPostsByCategory(categoryId: number, page = 1, perPage = 10): Promise<WPPost[]> {
  return apiFetch<WPPost[]>(
    `wp/v2/posts?categories=${categoryId}&page=${page}&per_page=${perPage}&_embed`
  );
}

/**
 * Einzelnen Post laden (nach Slug)
 */
export async function getPostBySlug(slug: string): Promise<WPPost | null> {
  const posts = await apiFetch<WPPost[]>(
    `wp/v2/posts?slug=${encodeURIComponent(slug)}&_embed`
  );
  return posts.length > 0 ? posts[0] : null;
}

/**
 * Seite nach Slug laden
 */
export async function getPageBySlug(slug: string): Promise<WPPage | null> {
  const pages = await apiFetch<WPPage[]>(
    `wp/v2/pages?slug=${encodeURIComponent(slug)}&_embed`
  );
  return pages.length > 0 ? pages[0] : null;
}

/**
 * Startseite laden (Statische Frontpage oder neueste Posts)
 */
export async function getFrontPage(): Promise<WPPage | null> {
  // Versuche die Seite mit slug "startseite" oder "home" zu laden
  const frontPage = await getPageBySlug('startseite') ?? await getPageBySlug('home');
  return frontPage;
}

/**
 * Menüs laden
 */
export async function getMenus() {
  const config = getConfig();
  return config.menus;
}
