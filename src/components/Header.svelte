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

  function toggleMobileMenu() {
    mobileMenuOpen = !mobileMenuOpen;
    openSubmenuId = null;
  }

  function toggleSubmenu(id: number) {
    openSubmenuId = openSubmenuId === id ? null : id;
  }

  const isTransparent = config.header?.transparent === true;
  const hasAdminBar = document.body.classList.contains('admin-bar');
</script>

<header
  class="z-50 transition-all duration-300 left-0 right-0"
  class:fixed={isTransparent && config.header?.sticky !== false}
  class:absolute={isTransparent && config.header?.sticky === false}
  class:sticky={!isTransparent && config.header?.sticky !== false}
  class:bg-transparent={isTransparent}
  class:backdrop-blur-md={isTransparent}
  class:shadow-sm={!isTransparent}
  style:top={hasAdminBar ? 'var(--wp-admin--admin-bar--height, 32px)' : '0'}
  style:background-color={isTransparent ? undefined : (config.header?.bg ?? '#ffffff')}
  style:color={config.header?.text ?? '#111827'}
>
  <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-18">
      <!-- Logo / Site Name -->
      <div class="shrink-0">
        <Link href="/" class="flex items-center gap-3">
          {#if config.header?.display === 'image' && config.logo}
            <img src={config.logo} alt={config.siteName} class="h-10 w-auto" />
          {:else}
            <span class="text-xl font-old tracking-wide">{config.siteName}</span>
          {/if}
        </Link>
      </div>

      <!-- Desktop Navigation -->
      <div class="hidden md:flex md:items-center md:space-x-8">
        {#each menuItems as item}
          {#if item.children && item.children.length > 0}
            <div class="relative group">
              <button
                class="hover:text-secondary transition-colors duration-200 text-sm font-medium inline-flex items-center gap-1"
              >
                {item.title}
                <svg class="w-3 h-3 transition-transform group-hover:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <div class="absolute left-0 top-full pt-2 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                <div class="rounded-lg shadow-xl ring-1 ring-black/10 py-2 min-w-50 backdrop-blur-lg bg-white/90 text-gray-900">
                  {#each item.children as child}
                    <Link
                      href={child.url || '/'}
                      class="block px-4 py-2 text-sm hover:text-secondary hover:bg-black/5 transition-colors"
                    >
                      {child.title}
                    </Link>
                  {/each}
                </div>
              </div>
            </div>
          {:else}
            <Link
              href={item.url || '/'}
              class="hover:text-secondary transition-colors duration-200 text-sm font-medium"
            >
              {item.title}
            </Link>
          {/if}
        {/each}
      </div>

      <!-- Mobile Menu Hamburger -->
      <div class="md:hidden">
        <button
          onclick={toggleMobileMenu}
          class="inline-flex items-center justify-center p-2 rounded-md hover:opacity-75 transition"
          aria-label="Menü öffnen"
        >
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            {#if mobileMenuOpen}
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            {:else}
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            {/if}
          </svg>
        </button>
      </div>
    </div>

    <!-- Mobile Navigation -->
    {#if mobileMenuOpen}
      <div class="md:hidden pb-4">
        <div class="flex flex-col space-y-1">
          {#each menuItems as item}
            {#if item.children && item.children.length > 0}
              <button
                onclick={() => toggleSubmenu(item.id)}
                class="flex items-center justify-between w-full px-3 py-2 rounded-md hover:text-secondary hover:bg-black/5 transition-colors text-left"
              >
                {item.title}
                <svg class="w-4 h-4 transition-transform" class:rotate-180={openSubmenuId === item.id} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              {#if openSubmenuId === item.id}
                <div class="flex flex-col space-y-1 pl-4">
                  {#each item.children as child}
                    <Link
                      href={child.url || '/'}
                      class="block px-3 py-2 rounded-md hover:text-secondary hover:bg-black/5 transition-colors text-sm"
                    >
                      {child.title}
                    </Link>
                  {/each}
                </div>
              {/if}
            {:else}
              <Link
                href={item.url || '/'}
                class="block px-3 py-2 rounded-md hover:text-secondary hover:bg-black/5 transition-colors"
              >
                {item.title}
              </Link>
            {/if}
          {/each}
        </div>
      </div>
    {/if}
  </nav>
</header>
