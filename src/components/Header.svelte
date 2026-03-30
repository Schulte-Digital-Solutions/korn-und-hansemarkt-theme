<script lang="ts">
  import Link from './Link.svelte';
  import { getConfig } from '../lib/api';
  import type { MenuItem } from '../types';

  const config = getConfig();
  const flatItems: MenuItem[] = config.menus?.primary ?? [];
  let mobileMenuOpen = $state(false);
  let openSubmenuId = $state<number | null>(null);

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
        class="md:hidden material-symbols-outlined text-stone-600 cursor-pointer"
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
    <nav class="hidden md:flex items-center gap-8">
      {#each menuItems as item, i}
        {#if item.children && item.children.length > 0}
          <div class="relative group">
            <button
              class="text-stone-600 hover:bg-stone-200/50 transition-colors px-2 py-1 text-sm uppercase tracking-widest"
            >
              {decodeHtml(item.title)}
            </button>
            <div class="absolute left-0 top-full pt-2 hidden group-hover:block z-50">
              <div class="bg-white/90 backdrop-blur-lg shadow-xl ring-1 ring-black/5 py-2 min-w-[12rem]">
                {#each item.children as child}
                  <Link
                    href={child.url || '/'}
                    class="block px-4 py-2 text-sm text-stone-600 hover:bg-stone-100 hover:text-emerald-900 transition-colors"
                  >
                    {decodeHtml(child.title)}
                  </Link>
                {/each}
              </div>
            </div>
          </div>
        {:else}
          <Link
            href={item.url || '/'}
            class="{i === 0
              ? 'text-emerald-900 font-bold'
              : 'text-stone-600 hover:bg-stone-200/50'} transition-colors px-2 py-1 text-sm uppercase tracking-widest"
          >
            {decodeHtml(item.title)}
          </Link>
        {/if}
      {/each}
    </nav>
  </div>

  <!-- Mobile Navigation Drawer -->
  {#if mobileMenuOpen}
    <div class="md:hidden border-t border-stone-200/50" style:background-color={config.header?.bg || '#ffffff'}>
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
