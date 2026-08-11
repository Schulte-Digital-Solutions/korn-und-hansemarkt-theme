<script lang="ts">
  import Link from './Link.svelte';
  import { getConfig } from '../lib/api';
  import { getCurrentPath } from '../lib/router';
  import type { MenuItem } from '../types';

  type MobileNavItem = {
    id: number;
    href: string;
    icon: string;
    label: string;
    fillOnActive: boolean;
  };

  const config = getConfig();
  const anchorPriorityActive = Boolean(config.header?.anchorPriorityActive);
  const mobileMenuItems: MenuItem[] = config.menus?.mobile ?? [];

  const mappedItems: MobileNavItem[] = mobileMenuItems.map((item) => {
    const classes = (item.classes ?? '').split(/\s+/).filter(Boolean);
    const fillOnActive = classes.includes('fill');
    const icon = classes.find((c) => c !== 'fill') ?? 'link';
    return { id: item.id, href: item.url || '/', icon, label: item.title, fillOnActive };
  });

  const items: MobileNavItem[] = mappedItems;
  const templateItems = items as MobileNavItem[];

  let currentPath = $state(getCurrentPath());
  let currentHash = $state(window.location.hash || '');

  function normalizePath(path: string): string {
    const clean = path.split('?')[0].split('#')[0];
    const normalized = clean.replace(/\/+$/, '');
    return normalized === '' ? '/' : normalized.startsWith('/') ? normalized : '/' + normalized;
  }

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

  function toAppPath(url: string): string {
    try {
      const parsed = new URL(url, window.location.href);
      const base = getBasePath();
      let pathname = parsed.pathname || '/';
      if (base && pathname.startsWith(base)) {
        pathname = pathname.slice(base.length) || '/';
      }
      return normalizePath(pathname);
    } catch {
      return normalizePath(url);
    }
  }

  function normalizeHash(hash: string): string {
    if (!hash) return '';
    const clean = hash.trim();
    if (!clean) return '';
    return clean.startsWith('#') ? clean : `#${clean}`;
  }

  function toUrlHash(url: string): string {
    try {
      const parsed = new URL(url, window.location.href);
      return normalizeHash(parsed.hash || '');
    } catch {
      return '';
    }
  }

  function findSingleActiveItemId(): number | null {
    const normalizedCurrentPath = normalizePath(currentPath);
    const normalizedCurrentHash = normalizeHash(currentHash);

    if (anchorPriorityActive && normalizedCurrentHash) {
      const anchorMatch = templateItems.find((item) => {
        return toAppPath(item.href) === normalizedCurrentPath && toUrlHash(item.href) === normalizedCurrentHash;
      });

      if (anchorMatch) {
        return anchorMatch.id;
      }
    }

    const pathMatches = templateItems.filter((item) => toAppPath(item.href) === normalizedCurrentPath);
    if (pathMatches.length === 0) return null;

    if (normalizedCurrentHash) {
      const noHashMatch = pathMatches.find((item) => toUrlHash(item.href) === '');
      if (noHashMatch) {
        return noHashMatch.id;
      }
    }

    return pathMatches[0]?.id ?? null;
  }

  const activeItemId = $derived(findSingleActiveItemId());

  function onNavChange() {
    currentPath = getCurrentPath();
    currentHash = window.location.hash || '';
  }

  function onHashChange() {
    currentHash = window.location.hash || '';
  }

  $effect(() => {
    window.addEventListener('popstate', onNavChange);
    window.addEventListener('hashchange', onHashChange);
    return () => {
      window.removeEventListener('popstate', onNavChange);
      window.removeEventListener('hashchange', onHashChange);
    };
  });

  function isItemActive(item: MobileNavItem) {
    return activeItemId !== null && item.id === activeItemId;
  }
</script>

{#if templateItems.length > 0}
  <nav class="md:hidden fixed bottom-0 left-0 w-full h-16 bg-stone-50 dark:bg-surface-container flex items-center px-3 z-50 shadow-[0_-4px_20px_0_rgba(0,0,0,0.05)] rounded-t-xl transition-colors">
    {#each templateItems as item}
      {@const navItem = item as MobileNavItem}
      {@const isActive = isItemActive(navItem)}
      <Link
        href={navItem.href}
        class="flex-1 basis-0 min-w-0 h-11 mx-1 rounded-xl flex flex-col items-center justify-center no-underline transition-colors duration-200 {isActive ? 'text-emerald-900 bg-emerald-50 dark:text-primary dark:bg-white/10' : 'text-stone-500 hover:text-emerald-700 dark:text-on-surface-variant dark:hover:text-on-surface dark:hover:bg-white/5'}"
      >
        <span
          class="material-symbols-outlined"
          style={isActive && navItem.fillOnActive ? "font-variation-settings: 'FILL' 1;" : ''}
        >{navItem.icon}</span>
        <span class="text-[10px] font-bold uppercase tracking-wider mt-0.5 w-full text-center truncate px-1">{navItem.label}</span>
      </Link>
    {/each}
  </nav>
{/if}
