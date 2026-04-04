<script lang="ts">
  import Link from './Link.svelte';

  interface ScheduleEvent {
    type: 'main' | 'side' | 'recurring';
    time: string;
    timeEnd?: string;
    /** Komma-getrennte Uhrzeiten, z.B. "11:00, 14:00, 17:00" */
    times?: string;
    /** Anzeige-Text, z.B. "stündlich", "alle 30 Min." */
    interval?: string;
    title: string;
    description?: string;
    location?: string;
    locationSlug?: string;
    icon?: string;
  }

  interface ScheduleDay {
    label: string;
    subtitle?: string;
    date: string;
    events: ScheduleEvent[];
  }

  interface Props {
    days: ScheduleDay[];
    titleFont?: string;
  }

  let { days, titleFont = 'headline' }: Props = $props();
  let activeDay = $state(0);

  const fontClass: Record<string, string> = {
    headline: 'font-headline',
    body: 'font-body',
    'serif-italic': 'font-serif-italic',
  };
</script>

<!-- Day Selector: sticky below header -->
<div class="sticky top-18 z-40 bg-surface py-2 mb-6">
  <div class="flex overflow-x-auto hide-scrollbar px-4 gap-3">
    {#each days as day, i}
      <button
        onclick={() => activeDay = i}
        class="shrink-0 px-6 py-3 rounded-xl transition-all
          {activeDay === i
            ? 'bg-surface-container-highest text-primary font-bold shadow-sm'
            : 'bg-surface-container-low text-on-surface/60'}"
      >
        {#if day.subtitle}
          <div class="text-[0.6rem] uppercase tracking-[0.15em] opacity-70">{day.subtitle}</div>
        {/if}
        <div class="text-[0.7rem] uppercase tracking-tighter opacity-70">{day.label}</div>
        <div class="text-sm">{day.date}</div>
      </button>
    {/each}
  </div>
</div>

<!-- Events: Card layout -->
<div class="px-4 space-y-3 max-w-3xl mx-auto">
  {#each days[activeDay]?.events ?? [] as event, i}
    {@const evType = event.type || 'main'}

    {#if evType === 'main'}
      <!-- Haupt-Event: prominent, groß -->
      <div class="bg-surface rounded-2xl p-6 flex gap-5 items-start shadow-sm border border-outline-variant/10">
        <div class="flex flex-col items-center shrink-0">
          <span class="text-xl font-bold text-primary tracking-tighter">{event.time}</span>
          {#if i < (days[activeDay]?.events?.length ?? 0) - 1}
            <div class="w-px h-10 bg-outline-variant/30 my-2"></div>
          {/if}
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="{fontClass[titleFont] || 'font-headline'} text-2xl md:text-3xl text-primary-container leading-tight mb-1">
            {event.title}
          </h3>
          {#if event.description}
            <p class="text-on-surface/70 text-sm leading-relaxed mt-1">{event.description}</p>
          {/if}
          {#if event.location}
            {#if event.locationSlug}
              <Link href="/karte?ort={event.locationSlug}" class="mt-3 no-underline inline-flex items-center gap-1 text-secondary hover:text-primary transition-colors">
                <span class="material-symbols-outlined text-[1rem]" style="font-variation-settings: 'FILL' 1;">{event.icon || 'location_on'}</span>
                <span class="text-[0.75rem] font-semibold uppercase tracking-wider underline decoration-secondary/30 underline-offset-2 hover:decoration-current">{event.location}</span>
              </Link>
            {:else}
              <div class="mt-3 flex items-center gap-1 text-secondary">
                <span class="material-symbols-outlined text-[1rem]" style="font-variation-settings: 'FILL' 1;">{event.icon || 'location_on'}</span>
                <span class="text-[0.75rem] font-semibold uppercase tracking-wider">{event.location}</span>
              </div>
            {/if}
          {/if}
        </div>
      </div>

    {:else if evType === 'side'}
      <!-- Neben-Event: kompakter, dezenter -->
      <div class="bg-surface-container-low rounded-xl px-5 py-4 flex gap-4 items-start">
        <div class="shrink-0">
          <span class="text-base font-bold text-on-surface/60 tracking-tighter">{event.time}</span>
        </div>
        <div class="flex-1 min-w-0">
          <h4 class="text-base font-semibold text-on-surface leading-tight">{event.title}</h4>
          {#if event.description}
            <p class="text-on-surface/50 text-sm mt-0.5">{event.description}</p>
          {/if}
          {#if event.location}
            {#if event.locationSlug}
              <Link href="/karte?ort={event.locationSlug}" class="mt-2 no-underline inline-flex items-center gap-1 text-secondary/70 hover:text-primary transition-colors">
                <span class="material-symbols-outlined text-[0.875rem]">{event.icon || 'location_on'}</span>
                <span class="text-[0.7rem] font-medium uppercase tracking-wider underline decoration-secondary/30 underline-offset-2 hover:decoration-current">{event.location}</span>
              </Link>
            {:else}
              <div class="mt-2 flex items-center gap-1 text-secondary/70">
                <span class="material-symbols-outlined text-[0.875rem]">{event.icon || 'location_on'}</span>
                <span class="text-[0.7rem] font-medium uppercase tracking-wider">{event.location}</span>
              </div>
            {/if}
          {/if}
        </div>
      </div>

    {:else}
      <!-- Wiederkehrend: Badge, Zeitraum/Uhrzeiten/Intervall -->
      <div class="bg-primary-container/5 rounded-xl px-5 py-3 flex gap-4 items-center border border-primary-container/15">
        <div class="shrink-0 text-center">
          {#if event.times}
            <!-- Feste Uhrzeiten -->
            <div class="flex flex-col gap-0.5">
              {#each event.times.split(',').map(t => t.trim()).filter(Boolean) as t}
                <span class="text-xs font-bold text-primary bg-primary-container/10 px-1.5 py-0.5 rounded">{t}</span>
              {/each}
            </div>
          {:else}
            <span class="text-sm font-bold text-primary tracking-tighter">{event.time}</span>
            {#if event.timeEnd}
              <span class="block text-xs text-on-surface/40">– {event.timeEnd}</span>
            {/if}
          {/if}
        </div>
        <div class="flex-1 min-w-0 flex flex-col gap-1">
          <span class="text-sm font-semibold text-on-surface">{event.title}</span>
          <span class="inline-block self-start text-[0.6rem] font-bold uppercase tracking-[0.05em] bg-primary-container/80 text-white px-2 py-0.5 rounded">
            {event.interval || 'Wiederkehrend'}
          </span>
          {#if event.location}
            {#if event.locationSlug}
              <Link href="/karte?ort={event.locationSlug}" class="no-underline inline-flex items-center gap-0.5 text-secondary/60 hover:text-primary text-[0.7rem] transition-colors">
                <span class="material-symbols-outlined text-[0.8rem]">{event.icon || 'location_on'}</span>
                <span class="underline decoration-secondary/30 underline-offset-2 hover:decoration-current">{event.location}</span>
              </Link>
            {:else}
              <span class="flex items-center gap-0.5 text-secondary/60 text-[0.7rem]">
                <span class="material-symbols-outlined text-[0.8rem]">{event.icon || 'location_on'}</span>
                {event.location}
              </span>
            {/if}
          {/if}
        </div>
      </div>
    {/if}
  {/each}
</div>

<style>
  .hide-scrollbar::-webkit-scrollbar {
    display: none;
  }
  .hide-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
  }
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
