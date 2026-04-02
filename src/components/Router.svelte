<script lang="ts">
  import type { Snippet } from 'svelte';
  import { getCurrentPath, resolveRoute, handlePopState, initScrollRestoration, type Route, type RouteMatch } from '../lib/router';

  interface Props {
    routes: Route[];
    fallback?: Snippet;
  }

  let { routes, fallback }: Props = $props();
  let currentPath = $state(getCurrentPath());
  let match: RouteMatch | null = $state(null);

  function onNavChange() {
    handlePopState();
    currentPath = getCurrentPath();
    match = resolveRoute(routes, currentPath);
  }

  $effect(() => {
    // Initial
    match = resolveRoute(routes, currentPath);
    initScrollRestoration();

    window.addEventListener('popstate', onNavChange);
    return () => window.removeEventListener('popstate', onNavChange);
  });
</script>

{#if match}
  {@const Component = match.component}
  <Component params={match.params} />
{:else if fallback}
  {@render fallback()}
{:else}
  <p>Seite nicht gefunden</p>
{/if}
