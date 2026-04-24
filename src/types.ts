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
    behavior: 'sticky' | 'normal' | 'autohide';
    display: 'text' | 'image';
    titleSize: number;
  };
  footer: {
    description: string;
    contactName: string;
    contactAddr: string;
    contactZip: string;
    contactCity: string;
    copyright: string;
  };
  darkmode: {
    enabled: boolean;
    defaultMode: 'auto' | 'light' | 'dark';
  };
  contact: {
    hcaptchaEnabled: boolean;
    hcaptchaSiteKey: string;
  };
}

interface MenuItem {
  id: number;
  title: string;
  url: string;
  parent: number;
  target: string;
  classes: string;
  children?: MenuItem[];
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
    cmplz_set_blocked_content_container?: () => void;
    cmplz_has_consent?: (category: string) => boolean;
    hcaptcha?: {
      render: (container: HTMLElement, params: { sitekey: string; theme?: 'light' | 'dark'; callback?: (token: string) => void; 'expired-callback'?: () => void; 'error-callback'?: () => void }) => string | number;
      reset: (widgetId?: string | number) => void;
      remove: (widgetId?: string | number) => void;
    };
  }
}

export type { KuhData, MenuItem, WPPost, WPPage };
