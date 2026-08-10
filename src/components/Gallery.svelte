<script lang="ts">
  import { onMount, onDestroy, untrack } from 'svelte';

  interface Photographer {
    slug: string;
    name: string;
    url: string;
  }

  interface GalleryItem {
    id: number;
    type: 'image' | 'video';
    videoId: string;
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
    showTypeFilter?: boolean;
    showResultCount?: boolean;
    showCredit?: boolean;
    defaultYear?: string;
    items: GalleryItem[];
    years: FilterTerm[];
    photographers: FilterTerm[];
  }

  let {
    title = 'Bildergalerie',
    showTitle = true,
    columns = 3,
    showYearFilter = true,
    showPhotographerFilter = true,
    showTypeFilter = true,
    showResultCount = true,
    showCredit = true,
    defaultYear = '',
    items,
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
  let activeType = $state(
    untrack(() => (['bild', 'video'].includes(params.get('typ') ?? '') ? params.get('typ')! : ''))
  );
  let lightboxIndex = $state(-1);

  const imageCount = $derived(items.filter((item) => item.type === 'image').length);
  const videoCount = $derived(items.length - imageCount);

  const filtered = $derived(
    items.filter(
      (item) =>
        (!activeYear || item.years.includes(activeYear)) &&
        (!activePhotographer || item.photographers.some((p) => p.slug === activePhotographer)) &&
        (!activeType || (activeType === 'video' ? item.type === 'video' : item.type === 'image'))
    )
  );

  const hasActiveFilter = $derived(Boolean(activeYear || activePhotographer || activeType));
  const lightboxItem = $derived(lightboxIndex >= 0 ? filtered[lightboxIndex] : undefined);

  /** Filterzustand in die URL schreiben, damit Ansichten verlinkbar bleiben. */
  function syncUrl() {
    const next = new URLSearchParams(window.location.search);
    activeYear ? next.set('jahr', activeYear) : next.delete('jahr');
    activePhotographer ? next.set('fotograf', activePhotographer) : next.delete('fotograf');
    activeType ? next.set('typ', activeType) : next.delete('typ');
    const query = next.toString();
    history.replaceState(
      history.state,
      '',
      window.location.pathname + (query ? `?${query}` : '') + window.location.hash
    );
  }

  function resetFilters() {
    activeYear = '';
    activePhotographer = '';
    activeType = '';
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

  <div class="flex flex-wrap items-center gap-3 mb-8">
    {#if showYearFilter && years.length > 0}
      <label class="flex items-center gap-2 text-sm text-on-surface-variant">
        <span class="material-symbols-outlined text-[1.1rem]">calendar_month</span>
        <select
          bind:value={activeYear}
          onchange={syncUrl}
          aria-label="Jahr"
          class="rounded-xl border border-outline-variant/40 bg-surface-container-low px-3 py-2 text-sm text-on-surface"
        >
          <option value="">Alle Jahre</option>
          {#each years as year (year.slug)}
            <option value={year.slug}>{year.name} ({year.count})</option>
          {/each}
        </select>
      </label>
    {/if}

    {#if showPhotographerFilter && photographers.length > 0}
      <label class="flex items-center gap-2 text-sm text-on-surface-variant">
        <span class="material-symbols-outlined text-[1.1rem]">photo_camera</span>
        <select
          bind:value={activePhotographer}
          onchange={syncUrl}
          aria-label="Fotograf"
          class="rounded-xl border border-outline-variant/40 bg-surface-container-low px-3 py-2 text-sm text-on-surface"
        >
          <option value="">Alle Fotografen</option>
          {#each photographers as photographer (photographer.slug)}
            <option value={photographer.slug}>{photographer.name} ({photographer.count})</option>
          {/each}
        </select>
      </label>
    {/if}

    {#if showTypeFilter && imageCount > 0 && videoCount > 0}
      <label class="flex items-center gap-2 text-sm text-on-surface-variant">
        <span class="material-symbols-outlined text-[1.1rem]">perm_media</span>
        <select
          bind:value={activeType}
          onchange={syncUrl}
          aria-label="Medientyp"
          class="rounded-xl border border-outline-variant/40 bg-surface-container-low px-3 py-2 text-sm text-on-surface"
        >
          <option value="">Bilder & Videos</option>
          <option value="bild">Nur Bilder ({imageCount})</option>
          <option value="video">Nur Videos ({videoCount})</option>
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
        {filtered.length === 1 ? 'Beitrag' : 'Beiträge'} gefunden
      </span>
    {/if}
  </div>

  {#if filtered.length === 0}
    <p class="text-center text-on-surface-variant py-12">
      Für diese Auswahl gibt es keine Inhalte.
    </p>
  {:else}
    <div class="gallery-grid" style:--kuh-gallery-columns={columns}>
      {#each filtered as item, index (item.id)}
        <figure class="m-0">
          <button
            type="button"
            onclick={() => openLightbox(index)}
            class="relative block w-full overflow-hidden rounded-xl bg-surface-container-low"
            class:cursor-zoom-in={item.type === 'image'}
            class:cursor-pointer={item.type === 'video'}
            aria-label={item.type === 'video'
              ? `Video abspielen: ${item.title}`
              : `Bild vergrößern: ${item.alt || item.title}`}
          >
            {#if item.thumb}
              <img
                src={item.thumb}
                alt={item.alt || item.title}
                width={item.width}
                height={item.height}
                loading="lazy"
                decoding="async"
                class="w-full h-full object-cover aspect-4/3 transition-transform duration-300 hover:scale-105"
              />
            {:else}
              <span class="flex aspect-4/3 items-center justify-center px-3 text-center text-sm text-on-surface-variant">
                {item.title}
              </span>
            {/if}
            {#if item.type === 'video'}
              <span
                class="pointer-events-none absolute inset-0 flex items-center justify-center bg-scrim/25 transition-colors hover:bg-scrim/10"
              >
                <span
                  class="material-symbols-outlined !text-5xl rounded-full bg-black/60 p-2 text-white"
                  style="font-variation-settings: 'FILL' 1;"
                >play_arrow</span>
              </span>
            {/if}
          </button>
          {#if showCredit && item.photographers.length > 0}
            <figcaption class="mt-1 text-xs text-on-surface-variant">
              Foto:
              {#each item.photographers as photographer, i (photographer.slug)}
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

{#if lightboxItem}
  <div
    class="fixed inset-0 z-[1000] flex flex-col bg-black/90 p-4"
    role="dialog"
    aria-modal="true"
    aria-label={lightboxItem.type === 'video' ? 'Videoansicht' : 'Bildansicht'}
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
        aria-label="Vorheriger Beitrag"
      >
        <span class="material-symbols-outlined">chevron_left</span>
      </button>

      {#if lightboxItem.type === 'video'}
        <!-- Der Iframe entsteht erst hier – vorher geht kein Request an YouTube. -->
        <div class="mx-auto aspect-video w-full max-w-5xl">
          <iframe
            src={`https://www.youtube-nocookie.com/embed/${lightboxItem.videoId}?autoplay=1&rel=0`}
            title={lightboxItem.title}
            class="h-full w-full rounded-xl"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            referrerpolicy="strict-origin-when-cross-origin"
            allowfullscreen
          ></iframe>
        </div>
      {:else}
        <img
          src={lightboxItem.full}
          alt={lightboxItem.alt || lightboxItem.title}
          class="mx-auto max-h-full max-w-full object-contain"
        />
      {/if}

      <button
        type="button"
        onclick={() => step(1)}
        class="shrink-0 rounded-full p-2 text-white/80 hover:text-white"
        aria-label="Nächster Beitrag"
      >
        <span class="material-symbols-outlined">chevron_right</span>
      </button>
    </div>

    <div class="mt-3 text-center text-sm text-white/70">
      {#if lightboxItem.type === 'video'}
        <p class="mb-1 text-white/90">{lightboxItem.title}</p>
      {/if}
      {#if lightboxItem.caption}
        <p class="mb-1 text-white/90">{lightboxItem.caption}</p>
      {/if}
      {#if lightboxItem.photographers.length > 0}
        <p>
          Foto:
          {#each lightboxItem.photographers as photographer, i (photographer.slug)}
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
      {#if lightboxItem.type === 'video'}
        <p class="mt-1 text-xs text-white/50">
          Beim Abspielen wird eine Verbindung zu YouTube hergestellt.
        </p>
      {/if}
    </div>
  </div>
{/if}

<style>
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
