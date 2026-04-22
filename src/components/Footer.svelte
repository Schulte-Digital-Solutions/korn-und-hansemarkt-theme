<script lang="ts">
  import Link from './Link.svelte';
  import { getConfig } from '../lib/api';
  import type { MenuItem } from '../types';

  const config = getConfig();
  const footerMenuItems: MenuItem[] = config.menus?.footer ?? [];
  const legalMenuItems: MenuItem[] = config.menus?.footer_legal ?? [];
  const footer = config.footer;
  const currentYear = new Date().getFullYear();
</script>

<footer class="bg-stone-100 dark:bg-surface-container-low w-full relative mt-auto">
  <div class="grid grid-cols-1 md:grid-cols-4 gap-12 px-8 py-16 max-w-7xl mx-auto">
    <!-- Über uns -->
    <div class="space-y-6">
      <h3 class="text-xl font-headline text-emerald-900 dark:text-primary">{config.siteName}</h3>
      {#if footer?.description}
        <p class="text-sm opacity-70 leading-relaxed">{footer.description}</p>
      {/if}
    </div>

    <!-- Navigation -->
    {#if footerMenuItems.length > 0}
    <div>
      <h4 class="font-bold text-emerald-900 dark:text-primary mb-6 text-sm uppercase tracking-widest">Navigation</h4>
      <ul class="space-y-4">
        {#each footerMenuItems as item}
          <li>
            <Link
              href={item.url || '/'}
              class="text-stone-700 dark:text-on-surface/80 hover:text-secondary dark:hover:text-secondary transition-colors text-sm"
            >
              {item.title}
            </Link>
          </li>
        {/each}
      </ul>
    </div>
    {/if}

    <!-- Rechtliches -->
    {#if legalMenuItems.length > 0}
    <div>
      <h4 class="font-bold text-emerald-900 dark:text-primary mb-6 text-sm uppercase tracking-widest">Rechtliches</h4>
      <ul class="space-y-4">
        {#each legalMenuItems as item}
          <li>
            <Link
              href={item.url || '/'}
              class="text-stone-700 dark:text-on-surface/80 hover:text-secondary dark:hover:text-secondary transition-colors text-sm"
            >
              {item.title}
            </Link>
          </li>
        {/each}
      </ul>
    </div>
    {/if}

    <!-- Kontakt -->
    {#if footer?.contactName}
    <div>
      <h4 class="font-bold text-emerald-900 dark:text-primary mb-6 text-sm uppercase tracking-widest">Kontakt</h4>
      <p class="text-sm text-stone-700 dark:text-on-surface/80">
        {footer.contactName}<br />
        {footer.contactAddr}<br />
        {footer.contactZip} {footer.contactCity}
      </p>
    </div>
    {/if}
  </div>

  <!-- Copyright -->
  <div class="max-w-7xl mx-auto px-8 py-8 border-t border-outline/5 flex flex-col md:flex-row items-center md:justify-between gap-2 text-xs opacity-70">
    {#if footer?.copyright}
      <p>&copy; {currentYear} {footer.copyright}</p>
    {/if}
    <p>
      Made with <span class="text-red-500">&hearts;</span> by
      <a href="https://schultedigital.de" target="_blank" rel="noopener noreferrer" class="hover:text-secondary transition-colors underline underline-offset-2">
        Schulte Digital Solutions
      </a>
    </p>
  </div>
</footer>
