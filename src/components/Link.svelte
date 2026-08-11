<script lang="ts">
  import { navigate } from '../lib/router';
  import type { Snippet } from 'svelte';

  interface Props {
    href: string;
    class?: string;
    children: Snippet;
  }

  let { href, class: className = '', children }: Props = $props();

  // Basispfad für das href-Attribut
  const base = (() => {
    try {
      const homeUrl = window.kuhData?.homeUrl;
      if (homeUrl) return new URL(homeUrl).pathname.replace(/\/+$/, '');
    } catch {}
    return '';
  })();

  const fullHref = $derived(base + (href.startsWith('/') ? href : '/' + href));

  const isExternal = $derived(/^https?:\/\//i.test(href));
  const isHashOnly = $derived(href.startsWith('#'));

  function handleClick(e: MouseEvent) {
    if (isExternal || isHashOnly) {
      return;
    }

    e.preventDefault();
    navigate(href);
  }
</script>

<a href={fullHref} class={className} onclick={handleClick}>
  {@render children()}
</a>
