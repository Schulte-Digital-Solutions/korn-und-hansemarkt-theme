<script lang="ts" module>
  // Pfad beim Laden der App – dient als Marker für den direkten Seitenaufruf.
  const initialPath = typeof window !== 'undefined' ? window.location.pathname : '';
</script>

<script lang="ts">
  import { onMount } from 'svelte';
  import ErrorState from '../components/ErrorState.svelte';

  onMount(() => {
    const url = new URL(window.location.href);

    if (url.pathname !== initialPath || url.searchParams.get('wp_fallback') === '1') {
      return;
    }

    url.searchParams.set('wp_fallback', '1');
    window.location.replace(`${url.pathname}${url.search}${url.hash}`);
  });

  function goBack() {
    if (window.history.length > 1) {
      window.history.back();
    } else {
      window.location.href = window.kuhData?.homeUrl ?? '/';
    }
  }
</script>

<ErrorState
  code="404"
  icon="travel_explore"
  title="Seite nicht gefunden"
  description="Diese Seite gibt es nicht (mehr). Vielleicht wurde sie verschoben oder der Link enthält einen Tippfehler."
>
  <button
    type="button"
    onclick={goBack}
    class="inline-flex items-center gap-1 text-on-surface-variant underline decoration-outline-variant underline-offset-4 transition-colors hover:text-on-surface"
  >
    <span class="material-symbols-outlined !text-base" aria-hidden="true">arrow_back</span>
    Zurück zur vorherigen Seite
  </button>
</ErrorState>
