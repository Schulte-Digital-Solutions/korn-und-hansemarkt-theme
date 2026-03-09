<script lang="ts">
  import Link from './Link.svelte';
  import { getConfig } from '../lib/api';
  import type { MenuItem } from '../types';

  const config = getConfig();
  const menuItems: MenuItem[] = config.menus?.primary ?? [];
  let mobileMenuOpen = $state(false);

  function toggleMobileMenu() {
    mobileMenuOpen = !mobileMenuOpen;
  }
</script>

<header class="bg-white shadow-sm sticky top-0 z-50">
  <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-16">
      <!-- Logo / Site Name -->
      <div class="flex-shrink-0">
        <Link href="/" class="flex items-center gap-3">
          {#if config.logo}
            <img src={config.logo} alt={config.siteName} class="h-10 w-auto" />
          {:else}
            <span class="text-xl text-gray-900 font-old">{config.siteName}</span>
          {/if}
        </Link>
      </div>

      <!-- Desktop Navigation -->
      <div class="hidden md:flex md:items-center md:space-x-8">
        {#each menuItems as item}
          <Link
            href={item.url || '/'}
            class="text-gray-700 hover:text-[var(--color-secondary)] transition-colors duration-200 text-sm font-medium"
          >
            {item.title}
          </Link>
        {/each}
      </div>

      <!-- Mobile Menu Hamburger -->
      <div class="md:hidden">
        <button
          onclick={toggleMobileMenu}
          class="inline-flex items-center justify-center p-2 rounded-md text-gray-700 hover:text-gray-900 hover:bg-gray-100 transition"
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
        <div class="flex flex-col space-y-2">
          {#each menuItems as item}
            <Link
              href={item.url || '/'}
              class="block px-3 py-2 rounded-md text-gray-700 hover:text-[var(--color-secondary)] hover:bg-gray-50 transition-colors"
            >
              {item.title}
            </Link>
          {/each}
        </div>
      </div>
    {/if}
  </nav>
</header>
