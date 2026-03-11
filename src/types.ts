/// <reference types="svelte" />

interface KuhData {
  restUrl: string;
  restNonce: string;
  themeUrl: string;
  homeUrl: string;
  siteName: string;
  siteDesc: string;
  logo: string;
  menus: Record<string, MenuItem[]>;
  header: {
    bg: string;
    text: string;
    sticky: boolean;
  };
}

interface MenuItem {
  id: number;
  title: string;
  url: string;
  parent: number;
  target: string;
  classes: string;
}

interface WPPost {
  id: number;
  slug: string;
  title: { rendered: string };
  content: { rendered: string };
  excerpt: { rendered: string };
  date: string;
  featured_image_url: {
    thumbnail: string;
    medium: string;
    large: string;
    full: string;
  } | null;
  meta?: {
    kuh_hide_title?: boolean;
  };
  _embedded?: {
    author?: Array<{ name: string; avatar_urls: Record<string, string> }>;
  };
}

interface WPPage {
  id: number;
  slug: string;
  title: { rendered: string };
  content: { rendered: string };
  featured_image_url: {
    thumbnail: string;
    medium: string;
    large: string;
    full: string;
  } | null;
  meta?: {
    kuh_hide_title?: boolean;
  };
}

declare global {
  interface Window {
    kuhData: KuhData;
    __kuhReinitBlocks?: () => void;
  }
}

export type { KuhData, MenuItem, WPPost, WPPage };
