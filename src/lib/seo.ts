/**
 * Clientseitige SEO-Meta-Tag-Verwaltung für SPA-Navigation
 */

interface SeoData {
  title: string;
  description?: string;
  ogType?: string;
  ogImage?: string;
  canonical?: string;
}

function setMeta(name: string, content: string, property = false) {
  const attr = property ? 'property' : 'name';
  let el = document.querySelector(`meta[${attr}="${name}"]`) as HTMLMetaElement | null;
  if (content) {
    if (!el) {
      el = document.createElement('meta');
      el.setAttribute(attr, name);
      document.head.appendChild(el);
    }
    el.content = content;
  } else if (el) {
    el.remove();
  }
}

function setCanonical(href: string) {
  let el = document.querySelector('link[rel="canonical"]') as HTMLLinkElement | null;
  if (href) {
    if (!el) {
      el = document.createElement('link');
      el.rel = 'canonical';
      document.head.appendChild(el);
    }
    el.href = href;
  } else if (el) {
    el.remove();
  }
}

export function updateSeo(data: SeoData) {
  const siteName = window.kuhData?.siteName || '';
  const fullTitle = data.title ? `${data.title} – ${siteName}` : siteName;

  document.title = fullTitle;
  setMeta('description', data.description || '');
  setMeta('og:title', fullTitle, true);
  setMeta('og:description', data.description || '', true);
  setMeta('og:type', data.ogType || 'website', true);
  setMeta('og:image', data.ogImage || '', true);
  setMeta('og:site_name', siteName, true);

  if (data.canonical) {
    setCanonical(data.canonical);
    setMeta('og:url', data.canonical, true);
  }
}
