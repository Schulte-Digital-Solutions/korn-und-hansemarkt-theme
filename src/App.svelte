<script lang="ts">
  import Router from './components/Router.svelte';
  import { routes } from './routes';
  import Header from './components/Header.svelte';
  import Footer from './components/Footer.svelte';
  import Link from './components/Link.svelte';
  import { getConfig } from './lib/api';
  import { getCurrentPath } from './lib/router';
  import type { MenuItem } from './types';

  const config = getConfig();
  const mobileMenuItems: MenuItem[] = config.menus?.mobile ?? [];

  /**
   * Mobile-Nav-Items aus dem WP-Menü ableiten.
   * Das Icon wird über das CSS-Klassen-Feld des Menüeintrags gesetzt
   * (z.B. "home", "calendar_today", "map").
   * Die Klasse "fill" aktiviert die gefüllte Icon-Variante im aktiven Zustand.
   */
  const mobileNavItems = mobileMenuItems.map((item) => {
    const classes = (item.classes ?? '').split(/\s+/).filter(Boolean);
    const fillOnActive = classes.includes('fill');
    const icon = classes.find((c) => c !== 'fill') ?? 'link';
    return { href: item.url || '/', icon, label: item.title, fillOnActive };
  });

  let currentPath = $state(getCurrentPath());

  function onNavChange() {
    currentPath = getCurrentPath();
  }

  $effect(() => {
    window.addEventListener('popstate', onNavChange);
    return () => window.removeEventListener('popstate', onNavChange);
  });
</script>

<div class="flex flex-col min-h-screen">
  <Header />

  <main class="flex-1">
    <Router {routes} />
  </main>

  <Footer />

  <!-- Mobile Bottom Navigation (nur wenn in WP unter "Mobile Navigation" gepflegt) -->
  {#if mobileNavItems.length > 0}
  <nav class="md:hidden fixed bottom-0 left-0 w-full h-16 bg-stone-50 flex justify-around items-center px-4 z-50 shadow-[0_-4px_20px_0_rgba(0,0,0,0.05)] rounded-t-xl transition-colors">
    {#each mobileNavItems as item}
      {@const isActive = currentPath === item.href || (item.href !== '/' && currentPath.startsWith(item.href))}
      <Link
        href={item.href}
        class="flex flex-col items-center justify-center {isActive
          ? 'text-emerald-900 bg-emerald-50 rounded-xl px-3 py-1'
          : 'text-stone-500 hover:text-emerald-700'} transition-transform duration-200 no-underline"
      >
        <span
          class="material-symbols-outlined"
          style={isActive && item.fillOnActive ? "font-variation-settings: 'FILL' 1;" : ''}
        >{item.icon}</span>
        <span class="text-[10px] font-bold uppercase tracking-wider mt-0.5">{item.label}</span>
      </Link>
    {/each}
  </nav>
  {/if}
</div>
