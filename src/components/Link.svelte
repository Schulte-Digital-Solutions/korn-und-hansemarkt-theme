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

  // Externe Ziele (http(s), protokoll-relativ, mailto:, tel: usw.) und reine
  // Anker dürfen nicht mit dem Basispfad präfixiert werden.
  const isExternal = $derived(/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i.test(href));
  const isHashOnly = $derived(href.startsWith('#'));

  const fullHref = $derived(
    isExternal || isHashOnly
      ? href
      : base + (href.startsWith('/') ? href : '/' + href)
  );

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
