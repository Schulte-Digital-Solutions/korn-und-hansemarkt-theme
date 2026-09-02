<script lang="ts">
  /**
   * Detailpanel eines Acts.
   *
   * Wird vom Bühnenplan und von der Acts-Übersicht genutzt, damit das Popup in
   * beiden Blöcken identisch aussieht und sich gleich verhält. Die aufrufenden
   * Komponenten bringen die Auftritte in dieselbe Form (PanelShow) mit.
   */
  import Link from './Link.svelte';

  export interface PanelAct {
    name: string;
    genre?: string;
    url?: string;
    excerpt?: string;
    text?: string;
    image?: string;
    imageWidth?: number;
    imageHeight?: number;
    imageAlt?: string;
  }

  export interface PanelShow {
    /** Kurzer Wochentag, z. B. „Fr". */
    dayShort: string;
    /** Datum ohne Jahr, z. B. „11. September". */
    dateShort: string;
    start: string;
    end?: string;
    stageName: string;
    /** POI-ID auf dem Geländeplan; leer = keine Verlinkung. */
    locationSlug?: string;
  }

  interface Props {
    act: PanelAct;
    shows: PanelShow[];
    titleFont?: string;
    mapPath?: string;
    onclose: () => void;
  }

  let { act, shows, titleFont = 'headline', mapPath = '/karte', onclose }: Props = $props();

  const fontClass: Record<string, string> = {
    headline: 'font-headline',
    body: 'font-body',
    'serif-italic': 'font-serif-italic',
  };

  const description = $derived(act.text || act.excerpt || '');

  function showTime(show: PanelShow): string {
    return show.end ? `${show.start}–${show.end}` : `ab ${show.start}`;
  }

  /** Hintergrund nicht mitscrollen lassen, solange das Panel offen ist. */
  $effect(() => {
    const previous = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = previous;
    };
  });
</script>

<svelte:window
  onkeydown={(event) => {
    if (event.key === 'Escape') onclose();
  }}
/>

<!-- z-80: über dem Seiten-Header (z-70) und der mobilen Bottom-Nav (z-50),
     damit der Scrim beide verdeckt und der Schatten nicht abgeschnitten wird. -->
<div class="fixed inset-0 z-80 flex items-end md:items-center justify-center">
  <button type="button" aria-label="Schließen" class="absolute inset-0 bg-scrim/50" onclick={onclose}
  ></button>

  <div
    class="relative w-full md:max-w-2xl max-h-[92dvh] md:max-h-[85vh] overflow-y-auto overscroll-contain
           bg-surface-container-lowest rounded-t-3xl md:rounded-3xl shadow-xl"
    role="dialog"
    aria-modal="true"
    aria-label={act.name}
  >
    <!-- Griff: macht mobil erkennbar, dass es ein eigenes Overlay ist. -->
    <div class="md:hidden sticky top-0 z-20 flex justify-center bg-surface-container-lowest pt-3 pb-2">
      <span class="h-1.5 w-12 rounded-full bg-on-surface/25"></span>
    </div>

    <button
      type="button"
      onclick={onclose}
      class="absolute top-3 right-3 md:top-4 md:right-4 z-30 flex h-11 w-11 items-center justify-center
             rounded-full bg-surface/90 text-on-surface/70 shadow-sm backdrop-blur-sm
             hover:text-on-surface"
      aria-label="Schließen"
    >
      <span class="material-symbols-outlined">close</span>
    </button>

    {#if act.image}
      <!-- Volle Breite ohne Zuschnitt; width/height reservieren den Platz. -->
      <img
        src={act.image}
        alt={act.imageAlt || act.name}
        width={act.imageWidth || undefined}
        height={act.imageHeight || undefined}
        class="block w-full h-auto"
      />
    {/if}

    <div class="px-5 pt-4 pb-[calc(1.5rem+env(safe-area-inset-bottom))] md:p-6">
      <h3
        class="{fontClass[titleFont] || 'font-headline'} text-2xl md:text-3xl text-primary
               dark:text-on-primary-container leading-tight pr-12"
      >
        {act.name}
      </h3>

      {#if act.genre}
        <p class="text-xs uppercase tracking-wider font-semibold text-secondary dark:text-on-secondary-container mt-1">
          {act.genre}
        </p>
      {/if}

      {#if description}
        <p class="text-sm text-on-surface/75 leading-relaxed mt-3 whitespace-pre-line">{description}</p>
      {/if}

      {#if act.url}
        <a
          href={act.url}
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-1 text-sm text-secondary dark:text-on-secondary-container mt-3"
        >
          <span class="material-symbols-outlined text-[1rem]">open_in_new</span>Website
        </a>
      {/if}

      {#if shows.length}
        <!-- Größe und Abstand inline: die globalen h4-Regeln aus den WordPress-
             Global-Styles sind unlayered und schlagen sonst die Tailwind-Klassen. -->
        <h4
          class="font-body font-bold uppercase tracking-wider text-on-surface/60"
          style="font-size:0.8125rem; margin:1.5rem 0 0.5rem;"
        >
          Alle Auftritte
        </h4>
        <ul class="space-y-2">
          {#each shows as show}
            <li class="flex flex-wrap gap-x-3 gap-y-1 text-sm bg-surface-container-low rounded-lg px-3 py-2">
              <!-- dayShort kommt aus date_i18n('D') und enthält den Punkt bereits. -->
              <span class="shrink-0 font-semibold text-primary dark:text-on-primary-container w-36">
                {show.dayShort}, {show.dateShort}
              </span>
              <span class="shrink-0 tabular-nums w-24">{showTime(show)}</span>
              {#if show.locationSlug}
                <Link
                  href="{mapPath}#{show.locationSlug}"
                  class="no-underline inline-flex items-center gap-1 text-on-surface/70"
                >
                  <span class="material-symbols-outlined text-[0.9rem]">location_on</span>
                  <span class="underline decoration-dotted underline-offset-2">{show.stageName}</span>
                </Link>
              {:else}
                <span class="text-on-surface/70">{show.stageName}</span>
              {/if}
            </li>
          {/each}
        </ul>
      {/if}
    </div>
  </div>
</div>

<style>
  .font-headline {
    font-family: var(--font-headline);
  }
  .font-body {
    font-family: var(--font-body);
  }
  .font-serif-italic {
    font-family: var(--font-serif-italic);
    font-style: italic;
  }
</style>
