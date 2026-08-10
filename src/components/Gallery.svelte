<script lang="ts">
  import { onMount, onDestroy, untrack } from 'svelte';

  interface Photographer {
    slug: string;
    name: string;
    url: string;
  }

  interface GalleryImage {
    id: number;
    title: string;
    caption: string;
    alt: string;
    thumb: string;
    full: string;
    width: number;
    height: number;
    years: string[];
    photographers: Photographer[];
  }

  interface FilterTerm {
    slug: string;
    name: string;
    count: number;
    url: string;
  }

  interface Props {
    title?: string;
    showTitle?: boolean;
    columns?: number;
    showYearFilter?: boolean;
    showPhotographerFilter?: boolean;
    showResultCount?: boolean;
    showCredit?: boolean;
    defaultYear?: string;
    images: GalleryImage[];
    years: FilterTerm[];
    photographers: FilterTerm[];
  }

  let {
    title = 'Bildergalerie',
    showTitle = true,
    columns = 3,
    showYearFilter = true,
    showPhotographerFilter = true,
    showResultCount = true,
    showCredit = true,
    defaultYear = '',
    images,
    years,
    photographers,
  }: Props = $props();

  const params = new URLSearchParams(window.location.search);

  /** Filterwert aus URL bzw. Block-Vorgabe lesen und gegen die vorhandenen Terms prüfen. */
  function initialFilter(key: string, fallback: string, terms: FilterTerm[]): string {
    const value = params.get(key) ?? fallback;
    return terms.some((term) => term.slug === value) ? value : '';
  }

  let activeYear = $state(untrack(() => initialFilter('jahr', defaultYear, years)));
  let activePhotographer = $state(untrack(() => initialFilter('fotograf', '', photographers)));
  let lightboxIndex = $state(-1);

  const filtered = $derived(
    images.filter(
      (image) =>
        (!activeYear || image.years.includes(activeYear)) &&
        (!activePhotographer || image.photographers.some((p) => p.slug === activePhotographer))
    )
  );

  const hasActiveFilter = $derived(Boolean(activeYear || activePhotographer));
  const lightboxImage = $derived(lightboxIndex >= 0 ? filtered[lightboxIndex] : undefined);

  /** Filterzustand in die URL schreiben, damit Ansichten verlinkbar bleiben. */
  function syncUrl() {
    const next = new URLSearchParams(window.location.search);
    activeYear ? next.set('jahr', activeYear) : next.delete('jahr');
    activePhotographer ? next.set('fotograf', activePhotographer) : next.delete('fotograf');
    const query = next.toString();
    history.replaceState(
      history.state,
      '',
      window.location.pathname + (query ? `?${query}` : '') + window.location.hash
    );
  }

  function selectYear(slug: string) {
    activeYear = activeYear === slug ? '' : slug;
    syncUrl();
  }

  function resetFilters() {
    activeYear = '';
    activePhotographer = '';
    syncUrl();
  }

  function openLightbox(index: number) {
    lightboxIndex = index;
    document.body.style.overflow = 'hidden';
  }

  function closeLightbox() {
    lightboxIndex = -1;
    document.body.style.overflow = '';
  }

  function step(direction: number) {
    if (filtered.length === 0) return;
    lightboxIndex = (lightboxIndex + direction + filtered.length) % filtered.length;
  }

  function onKeydown(event: KeyboardEvent) {
    if (lightboxIndex < 0) return;
    if (event.key === 'Escape') closeLightbox();
    else if (event.key === 'ArrowRight') step(1);
    else if (event.key === 'ArrowLeft') step(-1);
  }

  onMount(() => {
    window.addEventListener('keydown', onKeydown);
  });

  onDestroy(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
  });
</script>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-12">
  {#if showTitle && title}
    <h2 class="text-4xl font-headline text-primary text-center mb-8">{title}</h2>
  {/if}

  {#if showYearFilter && years.length > 0}
    <div class="flex overflow-x-auto hide-scrollbar gap-2 pb-2 mb-4">
      {#each years as year (year.slug)}
        <button
          type="button"
          onclick={() => selectYear(year.slug)}
          aria-pressed={activeYear === year.slug}
          class="shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all border
                 {activeYear === year.slug
                   ? 'bg-primary-container text-on-primary border-transparent'
                   : 'bg-surface-container-low text-on-surface border-outline-variant/40 hover:bg-surface-container'}"
        >
          {year.name}
          <span class="opacity-60 text-xs">({year.count})</span>
        </button>
      {/each}
    </div>
  {/if}

  <div class="flex flex-wrap items-center gap-3 mb-8">
    {#if showPhotographerFilter && photographers.length > 0}
      <label class="flex items-center gap-2 text-sm text-on-surface-variant">
        <span class="material-symbols-outlined text-[1.1rem]">photo_camera</span>
        <select
          bind:value={activePhotographer}
          onchange={syncUrl}
          class="rounded-xl border border-outline-variant/40 bg-surface-container-low px-3 py-2 text-sm text-on-surface"
        >
          <option value="">Alle Fotografen</option>
          {#each photographers as photographer (photographer.slug)}
            <option value={photographer.slug}>{photographer.name} ({photographer.count})</option>
          {/each}
        </select>
      </label>
    {/if}

    {#if hasActiveFilter}
      <button
        type="button"
        onclick={resetFilters}
        class="inline-flex items-center gap-1 rounded-xl border border-outline-variant/40 px-3 py-2 text-sm text-on-surface-variant hover:text-primary transition-colors"
      >
        <span class="material-symbols-outlined text-[1.1rem]">filter_alt_off</span>
        Filter zurücksetzen
      </button>
    {/if}

    {#if showResultCount}
      <span class="text-sm text-on-surface-variant ml-auto">
        {filtered.length}
        {filtered.length === 1 ? 'Bild' : 'Bilder'} gefunden
      </span>
    {/if}
  </div>

  {#if filtered.length === 0}
    <p class="text-center text-on-surface-variant py-12">
      Für diese Auswahl gibt es keine Bilder.
    </p>
  {:else}
    <div class="gallery-grid" style:--kuh-gallery-columns={columns}>
      {#each filtered as image, index (image.id)}
        <figure class="m-0">
          <button
            type="button"
            onclick={() => openLightbox(index)}
            class="block w-full overflow-hidden rounded-xl bg-surface-container-low cursor-zoom-in"
            aria-label={`Bild vergrößern: ${image.alt || image.title}`}
          >
            <img
              src={image.thumb}
              alt={image.alt || image.title}
              width={image.width}
              height={image.height}
              loading="lazy"
              decoding="async"
              class="w-full h-full object-cover aspect-4/3 transition-transform duration-300 hover:scale-105"
            />
          </button>
          {#if showCredit && image.photographers.length > 0}
            <figcaption class="mt-1 text-xs text-on-surface-variant">
              Foto:
              {#each image.photographers as photographer, i (photographer.slug)}
                {#if i > 0},{/if}
                {#if photographer.url}
                  <a
                    href={photographer.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-secondary hover:text-primary transition-colors"
                  >{photographer.name}</a>
                {:else}
                  {photographer.name}
                {/if}
              {/each}
            </figcaption>
          {/if}
        </figure>
      {/each}
    </div>
  {/if}
</section>

{#if lightboxImage}
  <div
    class="fixed inset-0 z-[1000] flex flex-col bg-black/90 p-4"
    role="dialog"
    aria-modal="true"
    aria-label="Bildansicht"
  >
    <div class="flex justify-end">
      <button
        type="button"
        onclick={closeLightbox}
        class="rounded-full p-2 text-white/80 hover:text-white"
        aria-label="Schließen"
      >
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <div class="flex flex-1 items-center gap-2 min-h-0">
      <button
        type="button"
        onclick={() => step(-1)}
        class="shrink-0 rounded-full p-2 text-white/80 hover:text-white"
        aria-label="Vorheriges Bild"
      >
        <span class="material-symbols-outlined">chevron_left</span>
      </button>

      <img
        src={lightboxImage.full}
        alt={lightboxImage.alt || lightboxImage.title}
        class="mx-auto max-h-full max-w-full object-contain"
      />

      <button
        type="button"
        onclick={() => step(1)}
        class="shrink-0 rounded-full p-2 text-white/80 hover:text-white"
        aria-label="Nächstes Bild"
      >
        <span class="material-symbols-outlined">chevron_right</span>
      </button>
    </div>

    <div class="mt-3 text-center text-sm text-white/70">
      {#if lightboxImage.caption}
        <p class="mb-1 text-white/90">{lightboxImage.caption}</p>
      {/if}
      {#if lightboxImage.photographers.length > 0}
        <p>
          Foto:
          {#each lightboxImage.photographers as photographer, i (photographer.slug)}
            {#if i > 0},{/if}
            {#if photographer.url}
              <a
                href={photographer.url}
                target="_blank"
                rel="noopener noreferrer"
                class="underline hover:text-white"
              >{photographer.name}</a>
            {:else}
              {photographer.name}
            {/if}
          {/each}
        </p>
      {/if}
      <p class="mt-1 text-xs text-white/50">{lightboxIndex + 1} / {filtered.length}</p>
    </div>
  </div>
{/if}

<style>
  .hide-scrollbar::-webkit-scrollbar {
    display: none;
  }

  .hide-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
  }

  .gallery-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  @media (min-width: 640px) {
    .gallery-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }

  @media (min-width: 1024px) {
    .gallery-grid {
      grid-template-columns: repeat(var(--kuh-gallery-columns, 3), minmax(0, 1fr));
    }
  }
</style>
