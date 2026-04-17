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
    const willOpen = !mobileMenuOpen;
    mobileMenuOpen = willOpen;
    openSubmenuId = willOpen ? getActiveSubmenuParentId() : null;
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

  function hasActiveChild(item: MenuItem): boolean {
    if (!item.children || item.children.length === 0) return false;
    return item.children.some((child) => isUrlActive(child.url));
  }

  function getActiveSubmenuParentId(): number | null {
    for (const item of menuItems) {
      if (hasActiveChild(item)) {
        return item.id;
      }
    }
    return null;
  }

  onMount(() => {
    const onNavChange = () => {
      currentPath = getCurrentPath();
      mobileMenuOpen = false;
      openSubmenuId = null;
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
          {@const parentDirectActive = isUrlActive(item.url)}
          {@const parentHasActiveChild = hasActiveChild(item)}
          <div class="relative group">
            <button
              type="button"
              class="{parentDirectActive
                ? 'border-emerald-900/20 bg-emerald-50/70 text-emerald-900'
                : parentHasActiveChild
                  ? 'border-stone-300/80 bg-stone-100/80 text-stone-700'
                  : 'border-transparent text-stone-600 hover:border-stone-300/80 hover:bg-stone-100/90 hover:text-emerald-900'} flex items-center gap-1 rounded-md px-3 py-2 text-sm uppercase tracking-widest transition-colors"
            >
              {decodeHtml(item.title)}
              <span class="material-symbols-outlined text-base!">expand_more</span>
            </button>
            <div class="absolute left-1/2 top-full z-50 hidden -translate-x-1/2 pt-3 group-hover:block group-focus-within:block {parentHasActiveChild ? 'md:block' : ''}">
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
          {@const itemActive = isUrlActive(item.url)}
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
            {@const mobileParentHasActiveChild = hasActiveChild(item)}
            <div class="flex items-center gap-1">
              {#if item.url}
                {@const mobileParentDirectActive = isUrlActive(item.url)}
                <Link
                  href={item.url}
                  class="{mobileParentDirectActive
                    ? 'bg-emerald-50/80 text-emerald-900 font-semibold'
                    : 'text-stone-600 hover:text-emerald-900 hover:bg-stone-200/50'} min-w-0 flex-1 rounded-md px-3 py-2 transition-colors text-sm uppercase tracking-widest"
                >
                  {decodeHtml(item.title)}
                </Link>
              {:else}
                <span class="min-w-0 flex-1 px-3 py-2 text-sm uppercase tracking-widest text-stone-600">
                  {decodeHtml(item.title)}
                </span>
              {/if}
              <button
                type="button"
                onclick={() => toggleSubmenu(item.id)}
                class="shrink-0 rounded-md p-2 text-stone-600 transition-colors hover:bg-stone-200/50 hover:text-emerald-900"
                aria-label="Untermenü umschalten"
                aria-expanded={openSubmenuId === item.id || (mobileParentHasActiveChild && openSubmenuId === null)}
              >
                <span class="material-symbols-outlined text-sm transition-transform {openSubmenuId === item.id || (mobileParentHasActiveChild && openSubmenuId === null) ? 'rotate-180' : ''}">
                  expand_more
                </span>
              </button>
            </div>
            {#if openSubmenuId === item.id || (mobileParentHasActiveChild && openSubmenuId === null)}
              <div class="flex flex-col space-y-1 pl-4">
                {#each item.children as child}
                  {@const mobileChildActive = isUrlActive(child.url)}
                  <Link
                    href={child.url || '/'}
                    class="{mobileChildActive
                      ? 'bg-emerald-50/80 text-emerald-900'
                      : 'text-stone-500 hover:text-emerald-900 hover:bg-stone-200/50'} block rounded-md px-3 py-2 text-sm transition-colors"
                  >
                    {decodeHtml(child.title)}
                  </Link>
                {/each}
              </div>
            {/if}
          {:else}
            {@const mobileItemActive = isUrlActive(item.url)}
            <Link
              href={item.url || '/'}
              class="{mobileItemActive
                ? 'bg-emerald-50/80 text-emerald-900 font-semibold'
                : 'text-stone-600 hover:text-emerald-900 hover:bg-stone-200/50'} block rounded-md px-3 py-2 transition-colors text-sm uppercase tracking-widest"
            >
              {decodeHtml(item.title)}
            </Link>
          {/if}
        {/each}
      </div>
    </div>
  {/if}
</header>
