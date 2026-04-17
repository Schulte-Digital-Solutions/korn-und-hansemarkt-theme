<script lang="ts">
  import { onMount } from 'svelte';
  import Link from './Link.svelte';
  import { getCurrentPath } from '../lib/router';
  import { getConfig } from '../lib/api';
  import type { MenuItem } from '../types';

  const config = getConfig();
  const flatItems: MenuItem[] = config.menus?.primary ?? [];
  let mobileMenuOpen = $state(false);
  let openSubmenuId = $state<number | null>(null);
  let currentPath = $state(getCurrentPath());

  // Build tree from flat menu items
  function buildMenuTree(items: MenuItem[]): MenuItem[] {
    const map = new Map<number, MenuItem>();
    const roots: MenuItem[] = [];
    for (const item of items) {
      map.set(item.id, { ...item, children: [] });
    }
    for (const item of items) {
      const node = map.get(item.id)!;
      if (item.parent && map.has(item.parent)) {
        map.get(item.parent)!.children!.push(node);
      } else {
        roots.push(node);
      }
    }
    return roots;
  }

  const menuItems = buildMenuTree(flatItems);

  function decodeHtml(html: string): string {
    const el = document.createElement('textarea');
    el.innerHTML = html;
    return el.value;
  }

  function toggleMobileMenu() {
    mobileMenuOpen = !mobileMenuOpen;
    openSubmenuId = null;
  }

  function toggleSubmenu(id: number) {
    openSubmenuId = openSubmenuId === id ? null : id;
  }

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
      const parsed = new URL(url, window.location.origin);
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

  function isUrlActive(url?: string | null): boolean {
    if (!url) return false;
    return normalizePath(currentPath) === toAppPath(url);
  }

  function isItemActive(item: MenuItem): boolean {
    if (isUrlActive(item.url)) return true;
    if (!item.children || item.children.length === 0) return false;
    return item.children.some((child) => isUrlActive(child.url));
  }

  onMount(() => {
    const onNavChange = () => {
      currentPath = getCurrentPath();
    };

    const closeMobileMenuOnDesktop = () => {
      if (window.matchMedia('(min-width: 768px)').matches) {
        mobileMenuOpen = false;
        openSubmenuId = null;
      }
    };

    onNavChange();
    closeMobileMenuOnDesktop();
    window.addEventListener('popstate', onNavChange);
    window.addEventListener('resize', closeMobileMenuOnDesktop);

    return () => {
      window.removeEventListener('popstate', onNavChange);
      window.removeEventListener('resize', closeMobileMenuOnDesktop);
    };
  });

  const hasAdminBar = document.body.classList.contains('admin-bar');
</script>

<header
  class="sticky top-0 w-full z-50 shadow-sm transition-colors duration-300"
  style:top={hasAdminBar ? 'var(--wp-admin--admin-bar--height, 32px)' : '0'}
  style:background-color={config.header?.bg || '#ffffff'}
  style:color={config.header?.text || '#111827'}
>
  <div class="flex justify-between items-center px-6 py-4 max-w-7xl mx-auto">
    <div class="flex items-center gap-4">
      <!-- Hamburger (sichtbar auf mobil, unsichtbar auf desktop) -->
      <button
        onclick={toggleMobileMenu}
        class="md:hidden! material-symbols-outlined text-stone-600 cursor-pointer"
        aria-label="Menü öffnen"
      >menu</button>

      <Link href="/" class="no-underline">
        {#if config.header?.display === 'image' && config.logo}
          <img src={config.logo} alt={config.siteName} class="h-10 w-auto" />
        {:else}
          <h1 class="text-2xl font-black text-emerald-900 font-headline tracking-tight">
            Korn- und Hansemarkt
          </h1>
        {/if}
      </Link>
    </div>

    <!-- Desktop Navigation -->
    <nav class="hidden md:flex! items-center gap-2">
      {#each menuItems as item}
        {#if item.children && item.children.length > 0}
          {@const parentActive = isItemActive(item)}
          <div class="relative group">
            <button
              type="button"
              class="{parentActive
                ? 'border-emerald-900/20 bg-emerald-50/70 text-emerald-900'
                : 'border-transparent text-stone-600 hover:border-stone-300/80 hover:bg-stone-100/90 hover:text-emerald-900'} flex items-center gap-1 rounded-md px-3 py-2 text-sm uppercase tracking-widest transition-colors"
            >
              {decodeHtml(item.title)}
              <span class="material-symbols-outlined text-base!">expand_more</span>
            </button>
            <div class="absolute left-1/2 top-full z-50 hidden -translate-x-1/2 pt-3 group-hover:block group-focus-within:block">
              <div class="min-w-56 overflow-hidden rounded-2xl border border-stone-200/70 bg-white/95 p-1 shadow-[0_18px_35px_-22px_rgba(0,0,0,0.45)] backdrop-blur-md">
                {#each item.children as child}
                  {@const childActive = isUrlActive(child.url)}
                  <Link
                    href={child.url || '/'}
                    class="{childActive
                      ? 'bg-emerald-50/80 text-emerald-900'
                      : 'text-stone-600 hover:bg-stone-100/85 hover:text-emerald-900'} block rounded-xl px-4 py-2.5 text-sm transition-colors"
                  >
                    {decodeHtml(child.title)}
                  </Link>
                {/each}
              </div>
            </div>
          </div>
        {:else}
          {@const itemActive = isItemActive(item)}
          <Link
            href={item.url || '/'}
            class="{itemActive
              ? 'rounded-md border border-emerald-900/20 bg-emerald-50/70 px-3 py-2 text-emerald-900 font-bold'
              : 'rounded-md border border-transparent px-3 py-2 text-stone-600 hover:border-stone-300/80 hover:bg-stone-100/90 hover:text-emerald-900'} transition-colors text-sm uppercase tracking-widest"
          >
            {decodeHtml(item.title)}
          </Link>
        {/if}
      {/each}
    </nav>
  </div>

  <!-- Mobile Navigation Drawer -->
  {#if mobileMenuOpen}
    <div class="md:hidden! border-t border-stone-200/50" style:background-color={config.header?.bg || '#ffffff'}>
      <div class="flex flex-col px-6 py-4 space-y-1">
        {#each menuItems as item}
          {#if item.children && item.children.length > 0}
            <button
              onclick={() => toggleSubmenu(item.id)}
              class="flex items-center justify-between w-full px-3 py-2 text-stone-600 hover:text-emerald-900 hover:bg-stone-200/50 transition-colors text-left text-sm uppercase tracking-widest"
            >
              {decodeHtml(item.title)}
              <span class="material-symbols-outlined text-sm transition-transform {openSubmenuId === item.id ? 'rotate-180' : ''}">
                expand_more
              </span>
            </button>
            {#if openSubmenuId === item.id}
              <div class="flex flex-col space-y-1 pl-4">
                {#each item.children as child}
                  <Link
                    href={child.url || '/'}
                    class="block px-3 py-2 text-sm text-stone-500 hover:text-emerald-900 hover:bg-stone-200/50 transition-colors"
                  >
                    {decodeHtml(child.title)}
                  </Link>
                {/each}
              </div>
            {/if}
          {:else}
            <Link
              href={item.url || '/'}
              class="block px-3 py-2 text-stone-600 hover:text-emerald-900 hover:bg-stone-200/50 transition-colors text-sm uppercase tracking-widest"
            >
              {decodeHtml(item.title)}
            </Link>
          {/if}
        {/each}
      </div>
    </div>
  {/if}
</header>
