<script lang="ts" module>
  // Pfad beim Laden der App – dient als Marker für den direkten Seitenaufruf.
  const initialPath = typeof window !== 'undefined' ? window.location.pathname : '';
</script>

<script lang="ts">
  import { onMount } from 'svelte';
  import Link from '../components/Link.svelte';

  onMount(() => {
    const url = new URL(window.location.href);

    if (url.pathname !== initialPath || url.searchParams.get('wp_fallback') === '1') {
      return;
    }

    url.searchParams.set('wp_fallback', '1');
    window.location.replace(`${url.pathname}${url.search}${url.hash}`);
  });
</script>

<div class="max-w-4xl mx-auto px-4 py-20 text-center">
  <h1 class="text-6xl font-bold text-gray-300 mb-4">404</h1>
  <h2 class="text-2xl font-semibold text-gray-900 mb-4">Seite nicht gefunden</h2>
  <p class="text-gray-600 mb-8">
    Die angeforderte Seite konnte leider nicht gefunden werden.
  </p>
  <Link
    href="/"
    class="inline-block px-6 py-3 bg-secondary text-white rounded-lg hover:bg-primary-container transition-colors font-medium"
  >
    Zur Startseite
  </Link>
</div>
