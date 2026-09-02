<script lang="ts">
  import Link from './Link.svelte';

  interface Show {
    date: string;
    dayLabel: string;
    dateLabel: string;
    start: string;
    end?: string;
    stage?: string;
    stageSlug?: string;
    location?: string;
  }

  interface Act {
    id: number;
    slug: string;
    name: string;
    genre?: string;
    url?: string;
    excerpt?: string;
    text?: string;
    image?: string;
    imageAlt?: string;
    shows: Show[];
  }

  interface Props {
    title?: string;
    showTitle?: boolean;
    titleFont?: string;
    cardMinWidth?: number;
    showSearch?: boolean;
    showShows?: boolean;
    mapPath?: string;
    acts: Act[];
  }

  let {
    title = 'Künstler & Gruppen',
    showTitle = true,
    titleFont = 'headline',
    cardMinWidth = 260,
    showSearch = true,
    showShows = true,
    mapPath = '/karte',
    acts = [],
  }: Props = $props();

  let query = $state('');
  let activeGenre = $state('all');
  let selected = $state<Act | null>(null);

  const genres = $derived.by(() => {
    const set = new Set<string>();
    for (const act of acts) {
      if (act.genre) set.add(act.genre);
    }
    return [...set].sort((a, b) => a.localeCompare(b, 'de'));
  });

  const filtered = $derived.by(() => {
    const needle = query.trim().toLowerCase();
    return acts.filter((act) => {
      if (activeGenre !== 'all' && act.genre !== activeGenre) return false;
      if (!needle) return true;
      return (
        act.name.toLowerCase().includes(needle) ||
        (act.genre ?? '').toLowerCase().includes(needle) ||
        (act.excerpt ?? '').toLowerCase().includes(needle) ||
        act.shows.some((s) => (s.stage ?? '').toLowerCase().includes(needle))
      );
    });
  });

  function showTime(show: Show): string {
    return show.end ? `${show.start}–${show.end}` : `ab ${show.start}`;
  }

  const fontClass: Record<string, string> = {
    headline: 'font-headline',
    body: 'font-body',
    'serif-italic': 'font-serif-italic',
  };
</script>

<section class="kuh-acts py-8 px-4">
  {#if showTitle && title}
    <h2
      class="{fontClass[titleFont] || 'font-headline'} text-3xl md:text-4xl text-primary dark:text-on-primary-container text-center px-4"
      style="margin-bottom:1.5rem;"
    >
      {title}
    </h2>
  {/if}

  {#if showSearch && acts.length > 4}
    <div class="max-w-3xl mx-auto mb-6 flex flex-col sm:flex-row gap-3">
      <label class="relative flex-1">
        <span class="sr-only">Acts durchsuchen</span>
        <span
          class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/40 text-[1.2rem]"
        >search</span>
        <input
          type="search"
          bind:value={query}
          placeholder="Nach Name, Genre oder Bühne suchen"
          class="w-full rounded-xl bg-surface-container-low pl-10 pr-4 py-3 text-sm text-on-surface
                 placeholder:text-on-surface/40 border border-outline-variant/30
                 focus:outline-none focus:border-primary"
        />
      </label>

      {#if genres.length > 1}
        <select
          bind:value={activeGenre}
          class="rounded-xl bg-surface-container-low px-4 py-3 text-sm text-on-surface
                 border border-outline-variant/30 focus:outline-none focus:border-primary"
        >
          <option value="all">Alle Genres</option>
          {#each genres as genre}
            <option value={genre}>{genre}</option>
          {/each}
        </select>
      {/if}
    </div>
  {/if}

  {#if filtered.length === 0}
    <p class="text-center text-on-surface/60 py-8">Keine Acts gefunden.</p>
  {:else}
    <div
      class="max-w-[80rem] mx-auto grid gap-6"
      style="grid-template-columns: repeat(auto-fill, minmax({cardMinWidth}px, 1fr));"
    >
      {#each filtered as act (act.id)}
        <article
          class="group bg-surface-container-low rounded-2xl overflow-hidden shadow-sm
                 border border-outline-variant/20 flex flex-col"
        >
          <button
            type="button"
            onclick={() => (selected = act)}
            class="text-left w-full cursor-pointer"
            aria-label="Details zu {act.name}"
          >
            {#if act.image}
              <img
                src={act.image}
                alt={act.imageAlt || act.name}
                loading="lazy"
                class="w-full h-44 object-cover transition-transform duration-300 group-hover:scale-[1.03]"
              />
            {:else}
              <div class="w-full h-44 bg-surface-container-high flex items-center justify-center">
                <span class="material-symbols-outlined text-[2.5rem] text-on-surface/20">music_note</span>
              </div>
            {/if}

            <div class="p-4">
              <h3
                class="{fontClass[titleFont] || 'font-headline'} text-xl text-primary dark:text-on-primary-container leading-tight"
              >
                {act.name}
              </h3>
              {#if act.genre}
                <p class="text-[0.7rem] uppercase tracking-wider font-semibold text-secondary dark:text-secondary-container mt-1">
                  {act.genre}
                </p>
              {/if}
              {#if act.excerpt}
                <p class="text-sm text-on-surface/70 leading-relaxed mt-2">{act.excerpt}</p>
              {/if}
            </div>
          </button>

          {#if showShows && act.shows.length}
            <ul class="px-4 pb-4 space-y-1">
              {#each act.shows as show}
                <!-- Umbrechen statt kürzen: Bühnennamen sind länger als die Kartenbreite. -->
                <li class="flex flex-wrap gap-x-2 text-[0.8rem] text-on-surface/70">
                  <span class="font-bold text-on-surface/90">{show.dayLabel}</span>
                  <span class="tabular-nums">{showTime(show)}</span>
                  {#if show.stage}
                    {#if show.location}
                      <Link
                        href="{mapPath}#{show.location}"
                        class="no-underline hover:underline decoration-dotted underline-offset-2"
                      >
                        {show.stage}
                      </Link>
                    {:else}
                      <span>{show.stage}</span>
                    {/if}
                  {/if}
                </li>
              {/each}
            </ul>
          {/if}
        </article>
      {/each}
    </div>
  {/if}
</section>

<!-- Detailpanel -->
{#if selected}
  <div class="fixed inset-0 z-[60] flex items-end md:items-center justify-center">
    <button
      type="button"
      aria-label="Schließen"
      class="absolute inset-0 bg-scrim/50"
      onclick={() => (selected = null)}
    ></button>

    <div
      class="relative w-full md:max-w-2xl max-h-[85vh] overflow-y-auto bg-surface-container-lowest
             rounded-t-3xl md:rounded-3xl shadow-xl"
    >
      <button
        type="button"
        onclick={() => (selected = null)}
        class="absolute top-4 right-4 z-10 rounded-full bg-surface/80 p-1 text-on-surface/70 hover:text-on-surface"
        aria-label="Schließen"
      >
        <span class="material-symbols-outlined">close</span>
      </button>

      {#if selected.image}
        <img src={selected.image} alt={selected.imageAlt || selected.name} class="w-full h-56 object-cover" />
      {/if}

      <div class="p-6">
        <h3 class="{fontClass[titleFont] || 'font-headline'} text-3xl text-primary dark:text-on-primary-container leading-tight pr-8">
          {selected.name}
        </h3>
        {#if selected.genre}
          <p class="text-xs uppercase tracking-wider font-semibold text-secondary dark:text-secondary-container mt-1">
            {selected.genre}
          </p>
        {/if}

        {#if selected.text}
          <p class="text-sm text-on-surface/75 leading-relaxed mt-4 whitespace-pre-line">{selected.text}</p>
        {:else if selected.excerpt}
          <p class="text-sm text-on-surface/75 leading-relaxed mt-4">{selected.excerpt}</p>
        {/if}

        {#if selected.url}
          <a
            href={selected.url}
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-1 text-sm text-secondary dark:text-secondary-container mt-4"
          >
            <span class="material-symbols-outlined text-[1rem]">open_in_new</span>Website
          </a>
        {/if}

        {#if selected.shows.length}
          <h4 class="font-body text-sm font-bold uppercase tracking-wider text-on-surface/60 mt-6 mb-2">
            Auftritte
          </h4>
          <ul class="space-y-2">
            {#each selected.shows as show}
              <li class="flex flex-wrap gap-x-3 gap-y-1 text-sm bg-surface-container-low rounded-lg px-3 py-2">
                <span class="font-semibold text-primary dark:text-on-primary-container w-28 shrink-0">
                  {show.dayLabel}, {show.dateLabel}
                </span>
                <span class="tabular-nums w-24 shrink-0">{showTime(show)}</span>
                {#if show.stage}
                  {#if show.location}
                    <Link href="{mapPath}#{show.location}" class="no-underline text-on-surface/70 inline-flex items-center gap-1">
                      <span class="material-symbols-outlined text-[0.9rem]">location_on</span>
                      <span class="underline decoration-dotted underline-offset-2">{show.stage}</span>
                    </Link>
                  {:else}
                    <span class="text-on-surface/70">{show.stage}</span>
                  {/if}
                {/if}
              </li>
            {/each}
          </ul>
        {/if}
      </div>
    </div>
  </div>
{/if}

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
