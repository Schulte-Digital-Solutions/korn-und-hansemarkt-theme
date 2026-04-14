<script lang="ts">
  import Link from './Link.svelte';
  import { getConfig } from '../lib/api';
  import { getCurrentPath } from '../lib/router';
  import type { MenuItem } from '../types';

  type MobileNavItem = {
    href: string;
    icon: string;
    label: string;
    fillOnActive: boolean;
  };

  const config = getConfig();
  const mobileMenuItems: MenuItem[] = config.menus?.mobile ?? [];

  const mappedItems: MobileNavItem[] = mobileMenuItems.map((item) => {
    const classes = (item.classes ?? '').split(/\s+/).filter(Boolean);
    const fillOnActive = classes.includes('fill');
    const icon = classes.find((c) => c !== 'fill') ?? 'link';
    return { href: item.url || '/', icon, label: item.title, fillOnActive };
  });

  const items: MobileNavItem[] = mappedItems;
  const templateItems = items as MobileNavItem[];

  let currentPath = $state(getCurrentPath());

  function onNavChange() {
    currentPath = getCurrentPath();
  }

  $effect(() => {
    window.addEventListener('popstate', onNavChange);
    return () => window.removeEventListener('popstate', onNavChange);
  });

  function isItemActive(href: string) {
    return currentPath === href || (href !== '/' && currentPath.startsWith(href));
  }
</script>

{#if templateItems.length > 0}
  <nav class="md:hidden fixed bottom-0 left-0 w-full h-16 bg-stone-50 flex items-center px-3 z-50 shadow-[0_-4px_20px_0_rgba(0,0,0,0.05)] rounded-t-xl transition-colors">
    {#each templateItems as item}
      {@const navItem = item as MobileNavItem}
      {@const isActive = isItemActive(navItem.href)}
      <Link
        href={navItem.href}
        class="flex-1 basis-0 min-w-0 h-11 mx-1 rounded-xl flex flex-col items-center justify-center no-underline transition-colors duration-200 {isActive ? 'text-emerald-900 bg-emerald-50' : 'text-stone-500 hover:text-emerald-700'}"
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
