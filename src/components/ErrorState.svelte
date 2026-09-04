<script lang="ts">
  import type { Snippet } from 'svelte';
  import Link from './Link.svelte';

  interface Props {
    /** HTTP-Status oder Kurzcode, wird als dezente Wasserzeichen-Ziffer gezeigt. */
    code?: string | number | null;
    /** Material-Symbols-Ligatur, z.B. "travel_explore" oder "wifi_off". */
    icon?: string;
    title: string;
    description?: string;
    /** Wenn gesetzt, wird ein "Erneut versuchen"-Button angezeigt. */
    onRetry?: (() => void) | null;
    /** Link "Zur Startseite" anzeigen. */
    showHome?: boolean;
    /** Zusaetzliche Aktionen unterhalb der Buttons. */
    children?: Snippet;
  }

  let {
    code = null,
    icon = 'error_outline',
    title,
    description = '',
    onRetry = null,
    showHome = true,
    children,
  }: Props = $props();
</script>

<div class="mx-auto flex min-h-[60vh] max-w-2xl flex-col items-center justify-center px-4 py-16 text-center">
  <!-- Statuscode gross, Icon als kleines Badge daneben – kein Ueberlappen. -->
  <div class="relative mb-6 inline-block">
    {#if code}
      <span
        class="block select-none font-headline text-7xl leading-none text-outline-variant sm:text-8xl dark:text-outline"
      >
        {code}
      </span>
      <span
        class="absolute -right-4 -top-3 flex size-12 items-center justify-center rounded-full bg-secondary-container text-on-secondary-container shadow-sm"
      >
        <span class="material-symbols-outlined !text-2xl" aria-hidden="true">{icon}</span>
      </span>
    {:else}
      <span
        class="flex size-20 items-center justify-center rounded-full bg-secondary-container text-on-secondary-container shadow-sm"
      >
        <span class="material-symbols-outlined !text-4xl" aria-hidden="true">{icon}</span>
      </span>
    {/if}
  </div>

  <h1 class="mb-4 text-3xl text-on-surface sm:text-4xl">
    {title}
  </h1>

  {#if description}
    <p class="mb-8 max-w-prose text-base text-on-surface-variant sm:text-lg">
      {description}
    </p>
  {/if}

  <div class="flex flex-col items-center gap-3 sm:flex-row">
    {#if onRetry}
      <button
        type="button"
        onclick={onRetry}
        class="inline-flex items-center gap-2 rounded-full bg-secondary px-6 py-3 font-medium text-on-secondary transition-colors hover:bg-primary-container hover:text-on-primary-container focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary"
      >
        <span class="material-symbols-outlined !text-xl" aria-hidden="true">refresh</span>
        Erneut versuchen
      </button>
    {/if}

    {#if showHome}
      <Link
        href="/"
        class={onRetry
          ? 'inline-flex items-center gap-2 rounded-full border border-outline px-6 py-3 font-medium text-on-surface transition-colors hover:bg-surface-container-high'
          : 'inline-flex items-center gap-2 rounded-full bg-secondary px-6 py-3 font-medium text-on-secondary transition-colors hover:bg-primary-container hover:text-on-primary-container'}
      >
        <span class="material-symbols-outlined !text-xl" aria-hidden="true">home</span>
        Zur Startseite
      </Link>
    {/if}
  </div>

  {#if children}
    <div class="mt-8 text-sm text-on-surface-variant">
      {@render children()}
    </div>
  {/if}
</div>
