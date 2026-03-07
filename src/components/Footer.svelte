<script lang="ts">
  import Link from './Link.svelte';
  import { getConfig } from '../lib/api';
  import type { MenuItem } from '../types';

  const config = getConfig();
  const footerMenuItems: MenuItem[] = config.menus?.footer ?? [];
  const currentYear = new Date().getFullYear();
</script>

<footer class="bg-gray-900 text-gray-300 mt-auto">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
      <!-- Info -->
      <div>
        <h3 class="text-white text-lg font-semibold mb-4">{config.siteName}</h3>
        {#if config.siteDesc}
          <p class="text-gray-400 text-sm">{config.siteDesc}</p>
        {/if}
      </div>

      <!-- Navigation -->
      {#if footerMenuItems.length > 0}
        <div>
          <h3 class="text-white text-lg font-semibold mb-4">Navigation</h3>
          <ul class="space-y-2">
            {#each footerMenuItems as item}
              <li>
                <Link
                  href={item.url || '/'}
                  class="text-gray-400 hover:text-white transition-colors text-sm"
                >
                  {item.title}
                </Link>
              </li>
            {/each}
          </ul>
        </div>
      {/if}

      <!-- Kontakt -->
      <div>
        <h3 class="text-white text-lg font-semibold mb-4">Kontakt</h3>
        <p class="text-gray-400 text-sm">
          Besuchen Sie uns gerne vor Ort oder kontaktieren Sie uns online.
        </p>
      </div>
    </div>

    <!-- Copyright -->
    <div class="border-t border-gray-800 mt-8 pt-8 text-center">
      <p class="text-gray-500 text-sm">
        &copy; {currentYear} {config.siteName}. Alle Rechte vorbehalten.
      </p>
    </div>
  </div>
</footer>
