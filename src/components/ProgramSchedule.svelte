<script lang="ts">
  import { untrack } from 'svelte';
  import Link from './Link.svelte';

  interface Stage {
    slug: string;
    name: string;
    subtitle?: string;
    locationSlug?: string;
    order?: number;
  }

  interface Slot {
    id: number;
    stage: string;
    start: string;
    end?: string;
    title?: string;
    act?: string;
    actName?: string;
    note?: string;
  }

  interface Day {
    date: string;
    slug: string;
    label: string;
    dateLabel: string;
    stages: Stage[];
    slots: Slot[];
  }

  interface Act {
    slug: string;
    name: string;
    genre?: string;
    url?: string;
    excerpt?: string;
    /** Palettenindex 0-7 aus dem Backend; leer = automatisch verteilen. */
    color?: string;
    text?: string;
    image?: string;
  }

  interface Props {
    title?: string;
    showTitle?: boolean;
    titleFont?: string;
    defaultView?: 'grid' | 'list';
    showNowMarker?: boolean;
    showActPanel?: boolean;
    pixelsPerHour?: number;
    mapPath?: string;
    days: Day[];
    acts?: Act[];
  }

  let {
    title = 'Bühnenplan',
    showTitle = true,
    titleFont = 'headline',
    defaultView = 'grid',
    showNowMarker = true,
    showActPanel = true,
    pixelsPerHour = 120,
    mapPath = '/karte',
    days = [],
    acts = [],
  }: Props = $props();

  const MIN_SLOT_MINUTES = 25;
  const PALETTE_SIZE = 8;

  /** „HH:MM" → Minuten seit Mitternacht; Nachtstunden landen hinter dem Tagesende. */
  function toMinutes(time?: string): number {
    if (!time) return -1;
    const m = /^(\d{1,2}):(\d{2})$/.exec(time);
    if (!m) return -1;
    const value = Number(m[1]) * 60 + Number(m[2]);
    return value < 360 ? value + 1440 : value;
  }

  function slotStart(slot: Slot): number {
    return toMinutes(slot.start);
  }

  function slotEnd(slot: Slot): number {
    const end = toMinutes(slot.end);
    const start = slotStart(slot);
    if (end < 0 || end <= start) return start + MIN_SLOT_MINUTES;
    return end;
  }

  function timeLabel(slot: Slot): string {
    return slot.end ? `${slot.start}–${slot.end}` : `ab ${slot.start}`;
  }

  function slotLabel(slot: Slot): string {
    return slot.title || slot.actName || '';
  }

  /** Feste Farbe je Act, damit derselbe Act über alle Tage gleich aussieht. */
  const actColor = $derived(
    new Map(
      acts.map((act, i) => {
        const fixed = Number(act.color);
        const useFixed = act.color !== undefined && act.color !== '' && Number.isInteger(fixed);
        return [act.slug, useFixed ? fixed % PALETTE_SIZE : i % PALETTE_SIZE];
      }),
    ),
  );

  function colorIndex(slot: Slot): number {
    if (!slot.act) return PALETTE_SIZE;
    return actColor.get(slot.act) ?? 0;
  }

  const actBySlug = $derived(new Map(acts.map((a) => [a.slug, a])));

  /* ---------------------------------------------------------------- Tage */

  function parseDate(value: string): Date | null {
    if (!value) return null;
    const d = new Date(`${value}T00:00:00`);
    return Number.isNaN(d.getTime()) ? null : d;
  }

  function initialDay(): number {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    for (let i = 0; i < days.length; i++) {
      const d = parseDate(days[i].date);
      if (d && d.getTime() === today.getTime()) return i;
    }

    let closest = -1;
    let closestDiff = Infinity;
    for (let i = 0; i < days.length; i++) {
      const d = parseDate(days[i].date);
      if (!d) continue;
      const diff = d.getTime() - today.getTime();
      if (diff > 0 && diff < closestDiff) {
        closest = i;
        closestDiff = diff;
      }
    }
    return closest >= 0 ? closest : Math.max(0, days.length - 1);
  }

  let activeDay = $state(initialDay());
  /**
   * Startansicht. Auf schmalen Screens beginnt der Plan als Liste, weil das
   * Zeitraster dort seitlich scrollen muss – umschalten geht trotzdem.
   */
  function initialView(): 'grid' | 'list' {
    const wanted = untrack(() => defaultView) === 'list' ? 'list' : 'grid';
    if (typeof window !== 'undefined' && window.innerWidth < 768) return 'list';
    return wanted;
  }

  let view = $state<'grid' | 'list'>(initialView());
  let activeStage = $state('all');
  let selectedAct = $state<string | null>(null);

  const day = $derived(days[activeDay]);
  const stages = $derived(day?.stages ?? []);

  // Bühnenfilter zurücksetzen, wenn die Bühne am neuen Tag nicht existiert.
  $effect(() => {
    if (activeStage !== 'all' && !stages.some((s) => s.slug === activeStage)) {
      activeStage = 'all';
    }
  });

  /* ------------------------------------------------------------ Zeitachse */

  const range = $derived.by(() => {
    const all = day?.slots ?? [];
    if (!all.length) return { from: 600, to: 1380 };

    let from = Infinity;
    let to = -Infinity;
    for (const slot of all) {
      from = Math.min(from, slotStart(slot));
      to = Math.max(to, slotEnd(slot));
    }
    return {
      from: Math.floor(from / 60) * 60,
      to: Math.ceil(to / 60) * 60,
    };
  });

  const hours = $derived.by(() => {
    const list: { minutes: number; label: string }[] = [];
    for (let m = range.from; m <= range.to; m += 60) {
      const h = Math.floor((m % 1440) / 60);
      list.push({ minutes: m, label: `${String(h).padStart(2, '0')}:00` });
    }
    return list;
  });

  const gridHeight = $derived(((range.to - range.from) / 60) * pixelsPerHour);

  function offsetPx(minutes: number): number {
    return ((minutes - range.from) / 60) * pixelsPerHour;
  }

  function slotHeightPx(slot: Slot): number {
    return Math.max(28, ((slotEnd(slot) - slotStart(slot)) / 60) * pixelsPerHour - 4);
  }

  /** Slots einer Bühne in überschneidungsfreie Spuren aufteilen. */
  function laneLayout(stageSlug: string) {
    const slots = (day?.slots ?? [])
      .filter((s) => s.stage === stageSlug)
      .sort((a, b) => slotStart(a) - slotStart(b) || slotEnd(a) - slotEnd(b));

    const laneEnds: number[] = [];
    const placed = slots.map((slot) => {
      const start = slotStart(slot);
      let lane = laneEnds.findIndex((end) => end <= start);
      if (lane === -1) {
        lane = laneEnds.length;
        laneEnds.push(0);
      }
      laneEnds[lane] = slotEnd(slot);
      return { slot, lane };
    });

    return { items: placed, lanes: Math.max(1, laneEnds.length) };
  }

  const stageLayouts = $derived(
    new Map(stages.map((stage) => [stage.slug, laneLayout(stage.slug)])),
  );

  /**
   * Feste Höhe der Kopfzeile im Zeitraster. Nötig, damit Zeitspalte und
   * Bühnenspalten auf derselben Linie beginnen – die Bühnennamen sind
   * unterschiedlich lang und würden sonst verschieden hohe Köpfe erzeugen.
   */
  const HEADER_HEIGHT = 64;

  /** Breite der Zeitspalte in rem. */
  const TIME_COLUMN_REM = 4.5;

  /**
   * Spaltenbreite einer Bühne in rem. Bühnen mit parallel laufenden Acts
   * (z. B. die Walking Acts, die alle im selben Zeitfenster unterwegs sind)
   * bekommen entsprechend mehr Platz.
   */
  function stageWidthRem(stageSlug: string): number {
    const lanes = stageLayouts.get(stageSlug)?.lanes ?? 1;
    return Math.max(11, lanes * 7.5);
  }

  /**
   * Gesamtbreite des Rasters. Der Flex-Container braucht sie explizit: eine
   * `sticky`-Spalte kann nur innerhalb ihres Containers wandern, und mit
   * automatischer Breite wäre der nur so breit wie der sichtbare Bereich.
   */
  const contentWidthRem = $derived(
    TIME_COLUMN_REM + stages.reduce((sum, stage) => sum + stageWidthRem(stage.slug), 0),
  );

  /**
   * Abstand, bei dem die Tabellenkopfzeile kleben bleibt: die Höhe des
   * Seiten-Headers, sofern der selbst sticky ist. Gemessen statt geraten, weil
   * der Header je nach Breakpoint und Customizer-Einstellung unterschiedlich
   * hoch ist (mobil 58px, Desktop mehr) oder gar nicht mitscrollt.
   */
  let stickyTop = $state(0);

  $effect(() => {
    const siteHeader = document.querySelector('header');
    if (!siteHeader) return;

    const update = () => {
      const position = getComputedStyle(siteHeader).position;
      stickyTop =
        position === 'sticky' || position === 'fixed'
          ? Math.round(siteHeader.getBoundingClientRect().height)
          : 0;
    };

    update();
    const observer = new ResizeObserver(update);
    observer.observe(siteHeader);
    return () => observer.disconnect();
  });

  let headerViewport = $state<HTMLElement | undefined>();
  let bodyViewport = $state<HTMLElement | undefined>();

  /**
   * Kopfzeile horizontal mit dem Körper mitziehen. Die Kopfzeile hat
   * `overflow: hidden` und lässt sich nicht selbst scrollen – `scrollLeft`
   * per Script funktioniert trotzdem.
   */
  function syncHeaderScroll() {
    if (headerViewport && bodyViewport) {
      headerViewport.scrollLeft = bodyViewport.scrollLeft;
    }
  }

  // Beim Tageswechsel beginnt der Körper wieder links – Kopfzeile nachziehen.
  $effect(() => {
    void activeDay;
    void view;
    syncHeaderScroll();
  });

  function stageSlots(stageSlug: string): Slot[] {
    return (day?.slots ?? [])
      .filter((s) => s.stage === stageSlug)
      .sort((a, b) => slotStart(a) - slotStart(b));
  }

  const listSlots = $derived.by(() => {
    const source =
      activeStage === 'all'
        ? (day?.slots ?? [])
        : (day?.slots ?? []).filter((s) => s.stage === activeStage);
    return [...source].sort((a, b) => slotStart(a) - slotStart(b));
  });

  const stageBySlug = $derived(new Map(stages.map((s) => [s.slug, s])));

  function stageHref(stage?: Stage): string {
    return stage?.locationSlug ? `${mapPath}#${stage.locationSlug}` : '';
  }

  /* ----------------------------------------------------------------- Jetzt */

  let now = $state(new Date());
  $effect(() => {
    if (!showNowMarker) return;
    const timer = setInterval(() => (now = new Date()), 60_000);
    return () => clearInterval(timer);
  });

  const nowMinutes = $derived.by(() => {
    if (!showNowMarker || !day) return -1;
    const today = new Date(now);
    today.setHours(0, 0, 0, 0);
    const dayDate = parseDate(day.date);
    if (!dayDate || dayDate.getTime() !== today.getTime()) return -1;

    const minutes = now.getHours() * 60 + now.getMinutes();
    const normalized = minutes < 360 ? minutes + 1440 : minutes;
    return normalized >= range.from && normalized <= range.to ? normalized : -1;
  });

  function isRunning(slot: Slot): boolean {
    if (nowMinutes < 0) return false;
    return nowMinutes >= slotStart(slot) && nowMinutes < slotEnd(slot);
  }

  /* ------------------------------------------------------------- Act-Panel */

  function openAct(slot: Slot) {
    if (!showActPanel || !slot.act) return;
    selectedAct = slot.act;
  }

  const selectedActData = $derived(selectedAct ? actBySlug.get(selectedAct) : undefined);

  /** Hintergrund nicht mitscrollen lassen, solange das Panel offen ist. */
  $effect(() => {
    if (!selectedActData) return;
    const previous = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = previous;
    };
  });

  const selectedActShows = $derived.by(() => {
    if (!selectedAct) return [];
    const result: { day: Day; slot: Slot }[] = [];
    for (const d of days) {
      for (const slot of d.slots) {
        if (slot.act === selectedAct) result.push({ day: d, slot });
      }
    }
    return result.sort(
      (a, b) => a.day.date.localeCompare(b.day.date) || slotStart(a.slot) - slotStart(b.slot),
    );
  });

  const fontClass: Record<string, string> = {
    headline: 'font-headline',
    body: 'font-body',
    'serif-italic': 'font-serif-italic',
  };
</script>

<section class="kuh-schedule py-8">
  {#if showTitle && title}
    <h2
      class="{fontClass[titleFont] || 'font-headline'} text-3xl md:text-4xl text-primary dark:text-on-primary-container text-center px-4"
      style="margin-bottom:1.5rem;"
    >
      {title}
    </h2>
  {/if}

  <!-- Tagesauswahl -->
  <div class="py-2">
    <!-- Umbrechend statt seitlich scrollend, damit auch mobil alle Tage sichtbar sind. -->
    <div class="flex flex-wrap px-4 gap-3 justify-center">
      {#each days as d, i}
        <button
          type="button"
          onclick={() => (activeDay = i)}
          aria-pressed={activeDay === i}
          class="px-6 py-3 rounded-xl transition-all
            {activeDay === i
              ? 'bg-surface-container-highest text-primary dark:text-on-primary-container font-bold shadow-sm'
              : 'bg-surface-container-low text-on-surface/60 hover:text-on-surface'}"
        >
          <div class="text-[0.7rem] uppercase tracking-tighter opacity-70">{d.label}</div>
          <div class="text-sm">{d.dateLabel}</div>
        </button>
      {/each}
    </div>
  </div>

  {#if day}
    <!-- Steuerleiste -->
    <div class="px-4 max-w-6xl mx-auto flex flex-wrap items-center gap-3 mt-3 mb-3 justify-center">
      <div class="flex rounded-xl bg-surface-container-low p-1">
        <button
          type="button"
          onclick={() => (view = 'grid')}
          class="px-4 py-2 rounded-lg text-sm transition-colors
            {view === 'grid'
              ? 'bg-surface-container-highest text-primary dark:text-on-primary-container font-semibold'
              : 'text-on-surface/60'}"
        >
          <span class="material-symbols-outlined align-middle text-[1.1rem] mr-1">grid_view</span>Zeitraster
        </button>
        <button
          type="button"
          onclick={() => (view = 'list')}
          class="px-4 py-2 rounded-lg text-sm transition-colors
            {view === 'list'
              ? 'bg-surface-container-highest text-primary dark:text-on-primary-container font-semibold'
              : 'text-on-surface/60'}"
        >
          <span class="material-symbols-outlined align-middle text-[1.1rem] mr-1">view_list</span>Liste
        </button>
      </div>

      {#if nowMinutes >= 0}
        <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-error">
          <span class="w-2 h-2 rounded-full bg-error animate-pulse"></span>
          Läuft gerade
        </span>
      {/if}
    </div>

    <!-- Bühnenfilter (nur in der Listenansicht relevant) -->
    {#if view === 'list'}
      <div class="px-4 mb-3">
        <!-- Umbrechend statt seitlich scrollend: bei acht Bühnen sonst unauffindbar. -->
        <div class="flex flex-wrap gap-2 max-w-6xl mx-auto">
          <button
            type="button"
            onclick={() => (activeStage = 'all')}
            class="px-4 py-2 rounded-full text-sm transition-colors
              {activeStage === 'all'
                ? 'bg-primary text-on-primary font-semibold'
                : 'bg-surface-container-low text-on-surface/70'}"
          >
            Alle Bühnen
          </button>
          {#each stages as stage}
            <button
              type="button"
              onclick={() => (activeStage = stage.slug)}
              class="px-4 py-2 rounded-full text-sm transition-colors
                {activeStage === stage.slug
                  ? 'bg-primary text-on-primary font-semibold'
                  : 'bg-surface-container-low text-on-surface/70'}"
            >
              {stage.name}
            </button>
          {/each}
        </div>
      </div>
    {/if}

    <!-- Zeitraster -->
    {#if view === 'grid' && stages.length}
      <div class="px-4 max-w-[100rem] mx-auto">
        <!--
          Kopfzeile und Körper sind zwei getrennte Container, deren horizontaler
          Scroll per JS gekoppelt ist. Grund: ein Element mit `overflow-x: auto`
          ist der Scrollport seiner Kinder – senkrechtes `sticky` würde sich darin
          auf diesen Container beziehen und damit nichts tun. Nur außerhalb des
          Scroll-Containers kann die Kopfzeile an der Seite kleben.
          Der Rahmen selbst bekommt bewusst kein `overflow`, sonst wäre er wieder
          der Scrollport und die Kopfzeile klebte erneut ins Leere.
        -->
        <div class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest">
          <!-- Kopfzeile: klebt unter dem Seiten-Header -->
          <div
            bind:this={headerViewport}
            class="sticky z-30 overflow-hidden rounded-t-2xl"
            style="top: {stickyTop}px;"
          >
            <div class="flex" style="width: max(100%, {contentWidthRem}rem);">
              <!-- Eckzelle über der Zeitspalte -->
              <div
                class="sticky left-0 z-20 shrink-0 bg-surface-container/75 backdrop-blur-sm"
                style="width: {TIME_COLUMN_REM}rem; height: {HEADER_HEIGHT}px;"
              ></div>
              {#each stages as stage}
                {@const colWidth = stageWidthRem(stage.slug)}
                <div
                  class="bg-surface-container/75 backdrop-blur-sm border-l border-outline-variant/30 px-3 flex flex-col items-center justify-center text-center overflow-hidden"
                  style="flex: 1 0 {colWidth}rem; min-width: {colWidth}rem; height: {HEADER_HEIGHT}px;"
                >
                  <div class="font-semibold text-sm text-primary dark:text-on-primary-container leading-tight">
                    {#if stage.locationSlug}
                      <Link
                        href={stageHref(stage)}
                        class="no-underline inline-flex items-center gap-1 hover:underline decoration-dotted underline-offset-4"
                      >
                        <span class="material-symbols-outlined text-[0.9rem]">location_on</span>
                        {stage.name}
                      </Link>
                    {:else}
                      {stage.name}
                    {/if}
                  </div>
                  {#if stage.subtitle}
                    <div class="text-[0.65rem] uppercase tracking-wider text-on-surface/50 mt-0.5">
                      {stage.subtitle}
                    </div>
                  {/if}
                </div>
              {/each}
            </div>
          </div>

          <!-- Körper: pb-3, weil die letzte Stundenmarke unten herausragt -->
          <div
            bind:this={bodyViewport}
            onscroll={syncHeaderScroll}
            class="overflow-x-auto overflow-y-hidden border-t border-outline-variant/30 pb-3 rounded-b-2xl"
          >
            <div class="flex" style="width: max(100%, {contentWidthRem}rem);">
              <!-- Zeitspalte -->
              <div
                class="sticky left-0 z-20 shrink-0 bg-surface-container-lowest"
                style="width: {TIME_COLUMN_REM}rem;"
              >
                <div class="relative border-r border-outline-variant/30" style="height: {gridHeight}px;">
                  {#each hours as hour, i}
                    <!-- Die erste Marke wird nicht zentriert, sonst ragte ihre obere
                         Hälfte über den Rand hinaus. -->
                    <div
                      class="absolute left-0 right-0 text-right pr-2 text-xs font-semibold text-on-surface/50 tabular-nums {i ===
                      0
                        ? 'pt-1'
                        : '-translate-y-1/2'}"
                      style="top: {offsetPx(hour.minutes)}px;"
                    >
                      {hour.label}
                    </div>
                  {/each}
                </div>
              </div>

              <!-- Bühnenspalten -->
              {#each stages as stage}
                {@const layout = stageLayouts.get(stage.slug) ?? { items: [], lanes: 1 }}
                {@const colWidth = stageWidthRem(stage.slug)}
                <div style="flex: 1 0 {colWidth}rem; min-width: {colWidth}rem;">
                  <div class="relative border-l border-outline-variant/30" style="height: {gridHeight}px;">
                    {#each hours as hour}
                      <div
                        class="absolute left-0 right-0 border-t border-outline-variant/20 pointer-events-none"
                        style="top: {offsetPx(hour.minutes)}px;"
                      ></div>
                    {/each}

                    {#each layout.items as { slot, lane } (slot.id)}
                      {@const height = slotHeightPx(slot)}
                      {@const width = 100 / layout.lanes}
                      <button
                        type="button"
                        onclick={() => openAct(slot)}
                        disabled={!showActPanel || !slot.act}
                        title="{timeLabel(slot)} Uhr · {slotLabel(slot)}"
                        class="kuh-slot group absolute overflow-hidden rounded-lg px-2 py-1 text-left transition-shadow
                          {slot.act && showActPanel ? 'cursor-pointer hover:shadow-md' : 'cursor-default'}
                          {isRunning(slot) ? 'ring-2 ring-error' : ''}"
                        data-color={colorIndex(slot)}
                        style="top: {offsetPx(slotStart(slot))}px; height: {height}px; left: {lane *
                          width}%; width: calc({width}% - 4px); margin-left: 2px;"
                      >
                        {#if slot.act && showActPanel && height >= 38}
                          <!-- Zeigt an, dass sich der Slot öffnen lässt. -->
                          <span
                            class="material-symbols-outlined absolute top-1 right-1 text-[0.9rem] opacity-40
                                   transition-opacity group-hover:opacity-90"
                            aria-hidden="true">info</span>
                        {/if}

                        {#if height < 38}
                          <!-- Sehr kurze Slots: Zeit und Titel in eine Zeile. -->
                          <span class="block truncate text-[0.7rem] leading-tight">
                            <span class="font-bold tabular-nums opacity-70">{slot.start}</span>
                            <span class="font-semibold">{slotLabel(slot)}</span>
                          </span>
                        {:else}
                          <span class="block text-[0.65rem] font-bold tabular-nums opacity-70">
                            {timeLabel(slot)}
                          </span>
                          <span class="block text-[0.8rem] font-semibold leading-tight">
                            {slotLabel(slot)}
                          </span>
                          {#if slot.note && height > 56}
                            <span class="block text-[0.65rem] opacity-70 leading-snug mt-0.5">{slot.note}</span>
                          {/if}
                        {/if}
                      </button>
                    {/each}

                    {#if nowMinutes >= 0}
                      <div
                        class="absolute left-0 right-0 h-px bg-error z-10 pointer-events-none"
                        style="top: {offsetPx(nowMinutes)}px;"
                      ></div>
                    {/if}
                  </div>
                </div>
              {/each}
            </div>
          </div>
        </div>
        <p class="text-xs text-on-surface/50 mt-2 text-center">
          Angaben ohne Gewähr – Programmänderungen vorbehalten.
        </p>
      </div>
    {/if}

    <!-- Listenansicht -->
    {#if view === 'list'}
    <div class="px-4 max-w-3xl mx-auto">
      {#if activeStage !== 'all'}
        {@const stage = stageBySlug.get(activeStage)}
        {#if stage}
          <h3 class="{fontClass[titleFont] || 'font-headline'} text-2xl text-primary dark:text-on-primary-container mb-1">
            {#if stage.locationSlug}
              <Link
                href={stageHref(stage)}
                class="no-underline inline-flex items-center gap-1.5 hover:underline decoration-dotted underline-offset-4"
              >
                <span class="material-symbols-outlined text-[1.2rem]">location_on</span>
                {stage.name}
              </Link>
            {:else}
              {stage.name}
            {/if}
          </h3>
          <p class="text-xs uppercase tracking-wider text-on-surface/50 mb-4">
            {#if stage.subtitle}{stage.subtitle}{/if}
            {#if stage.locationSlug}
              {#if stage.subtitle}·{/if}
              <Link href={stageHref(stage)} class="no-underline underline decoration-dotted underline-offset-2">
                auf dem Geländeplan zeigen
              </Link>
            {/if}
          </p>
        {/if}
      {/if}

      <div class="space-y-3">
        {#each activeStage === 'all' ? listSlots : stageSlots(activeStage) as slot (slot.id)}
          {@const stage = stageBySlug.get(slot.stage)}
          <div
            class="kuh-slot-row rounded-xl px-4 py-3 flex gap-4 items-start {isRunning(slot)
              ? 'ring-2 ring-error'
              : ''}"
            data-color={colorIndex(slot)}
          >
            <div class="shrink-0 w-20 text-sm font-bold tabular-nums leading-tight">
              {slot.start}
              {#if slot.end}<span class="block text-[0.7rem] font-normal opacity-70">bis {slot.end}</span>{/if}
            </div>
            <div class="min-w-0 flex-1">
              <button
                type="button"
                onclick={() => openAct(slot)}
                disabled={!showActPanel || !slot.act}
                class="group text-left font-semibold leading-tight {slot.act && showActPanel
                  ? 'cursor-pointer hover:underline underline-offset-2'
                  : 'cursor-default'}"
              >
                {slotLabel(slot)}
                {#if slot.act && showActPanel}
                  <!-- Zeigt an, dass sich der Eintrag öffnen lässt. -->
                  <span
                    class="material-symbols-outlined align-[-0.15em] text-[1rem] opacity-50
                           transition-opacity group-hover:opacity-90"
                    aria-hidden="true">info</span>
                {/if}
              </button>
              {#if slot.note}
                <p class="text-xs opacity-70 mt-0.5">{slot.note}</p>
              {/if}
              {#if activeStage === 'all'}
                <p class="text-[0.7rem] uppercase tracking-wider font-semibold opacity-70 mt-1">
                  {#if stage && stage.locationSlug}
                    <Link href={stageHref(stage)} class="no-underline inline-flex items-center gap-1">
                      <span class="material-symbols-outlined text-[0.85rem]">location_on</span>
                      <span class="underline decoration-dotted underline-offset-2">{stage.name}</span>
                    </Link>
                  {:else}
                    <!-- Ohne verknüpften Ort kein Pin – das Icon soll nur dort stehen,
                         wo es auch auf die Karte führt. -->
                    <span>{stage?.name ?? ''}</span>
                  {/if}
                </p>
              {/if}
            </div>
          </div>
        {/each}
      </div>
    </div>
    {/if}
  {/if}
</section>

<svelte:window
  onkeydown={(event) => {
    if (selectedAct && event.key === 'Escape') selectedAct = null;
  }}
/>

<!-- Act-Detailpanel -->
{#if selectedActData}
  <!-- z-80: über dem Seiten-Header (z-70) und der mobilen Bottom-Nav (z-50),
     damit der Scrim beide verdeckt und der Schatten nicht abgeschnitten wird. -->
  <div class="fixed inset-0 z-80 flex items-end md:items-center justify-center">
    <button
      type="button"
      aria-label="Schließen"
      class="absolute inset-0 bg-scrim/50"
      onclick={() => (selectedAct = null)}
    ></button>

    <div
      class="relative w-full md:max-w-lg max-h-[92dvh] md:max-h-[85vh] overflow-y-auto overscroll-contain
             bg-surface-container-lowest rounded-t-3xl md:rounded-3xl shadow-xl"
      role="dialog"
      aria-modal="true"
      aria-label={selectedActData.name}
    >
      <!-- Griff: macht mobil erkennbar, dass es ein eigenes Overlay ist. -->
      <div class="md:hidden sticky top-0 z-20 flex justify-center bg-surface-container-lowest pt-3 pb-2">
        <span class="h-1.5 w-12 rounded-full bg-on-surface/25"></span>
      </div>

      <button
        type="button"
        onclick={() => (selectedAct = null)}
        class="absolute top-3 right-3 md:top-4 md:right-4 z-30 flex h-11 w-11 items-center justify-center
               rounded-full bg-surface/90 text-on-surface/70 shadow-sm backdrop-blur-sm
               hover:text-on-surface"
        aria-label="Schließen"
      >
        <span class="material-symbols-outlined">close</span>
      </button>

      <div class="px-5 pt-2 pb-[calc(1.5rem+env(safe-area-inset-bottom))] md:p-6">

      {#if selectedActData.image}
        <img
          src={selectedActData.image}
          alt={selectedActData.name}
          class="w-full h-40 object-cover rounded-2xl mb-4"
        />
      {/if}

      <h3 class="{fontClass[titleFont] || 'font-headline'} text-2xl text-primary dark:text-on-primary-container leading-tight pr-12">
        {selectedActData.name}
      </h3>
      {#if selectedActData.genre}
        <p class="text-xs uppercase tracking-wider font-semibold text-secondary dark:text-on-secondary-container mt-1">
          {selectedActData.genre}
        </p>
      {/if}
      {#if selectedActData.text}
        <p class="text-sm text-on-surface/70 leading-relaxed mt-3 whitespace-pre-line">{selectedActData.text}</p>
      {/if}
      {#if selectedActData.url}
        <a
          href={selectedActData.url}
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-1 text-sm text-secondary dark:text-on-secondary-container mt-3"
        >
          <span class="material-symbols-outlined text-[1rem]">open_in_new</span>Website
        </a>
      {/if}

      <!-- Größe und Abstand inline: die globalen h4-Regeln aus den WordPress-
             Global-Styles sind unlayered und schlagen sonst die Tailwind-Klassen. -->
          <h4
            class="font-body font-bold uppercase tracking-wider text-on-surface/60"
            style="font-size:0.8125rem; margin:1.5rem 0 0.5rem;"
          >
        Alle Auftritte
      </h4>
      <ul class="space-y-2">
        {#each selectedActShows as show}
          {@const stage = show.day.stages.find((s) => s.slug === show.slot.stage)}
          <li class="flex flex-wrap gap-x-3 gap-y-1 text-sm bg-surface-container-low rounded-lg px-3 py-2">
            <span class="shrink-0 font-semibold text-primary dark:text-on-primary-container w-16">
              {show.day.label.slice(0, 2)}.
            </span>
            <span class="shrink-0 tabular-nums w-24">{timeLabel(show.slot)}</span>
            {#if stage && stage.locationSlug}
              <Link
                href={stageHref(stage)}
                class="no-underline inline-flex items-center gap-1 text-on-surface/70"
              >
                <span class="material-symbols-outlined text-[0.9rem]">location_on</span>
                <span class="underline decoration-dotted underline-offset-2">{stage.name}</span>
              </Link>
            {:else}
              <span class="text-on-surface/70">{stage?.name ?? ''}</span>
            {/if}
          </li>
        {/each}
      </ul>
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

  /* Act-Farben: pro Act stabil, damit derselbe Act über alle Bühnen und Tage
     wiedererkennbar bleibt. Index 8 = Slot ohne verknüpften Act. */
  .kuh-slot,
  .kuh-slot-row {
    background: var(--slot-bg, #eef1ec);
    color: var(--slot-fg, #1b1c1c);
  }
  .kuh-slot[data-color='0'], .kuh-slot-row[data-color='0'] { --slot-bg: #d7f0d9; --slot-fg: #062b12; }
  .kuh-slot[data-color='1'], .kuh-slot-row[data-color='1'] { --slot-bg: #cfe5ff; --slot-fg: #001d36; }
  .kuh-slot[data-color='2'], .kuh-slot-row[data-color='2'] { --slot-bg: #ffdcc2; --slot-fg: #2f1500; }
  .kuh-slot[data-color='3'], .kuh-slot-row[data-color='3'] { --slot-bg: #e7ddff; --slot-fg: #21005d; }
  .kuh-slot[data-color='4'], .kuh-slot-row[data-color='4'] { --slot-bg: #bcf0c9; --slot-fg: #002110; }
  .kuh-slot[data-color='5'], .kuh-slot-row[data-color='5'] { --slot-bg: #ffd8e4; --slot-fg: #31111d; }
  .kuh-slot[data-color='6'], .kuh-slot-row[data-color='6'] { --slot-bg: #fcdd83; --slot-fg: #241a00; }
  .kuh-slot[data-color='7'], .kuh-slot-row[data-color='7'] { --slot-bg: #cfe8e4; --slot-fg: #00201d; }

  :global(.dark) .kuh-slot[data-color='0'], :global(.dark) .kuh-slot-row[data-color='0'] { --slot-bg: #204d2a; --slot-fg: #d7f0d9; }
  :global(.dark) .kuh-slot[data-color='1'], :global(.dark) .kuh-slot-row[data-color='1'] { --slot-bg: #1b3a56; --slot-fg: #cfe5ff; }
  :global(.dark) .kuh-slot[data-color='2'], :global(.dark) .kuh-slot-row[data-color='2'] { --slot-bg: #55341a; --slot-fg: #ffdcc2; }
  :global(.dark) .kuh-slot[data-color='3'], :global(.dark) .kuh-slot-row[data-color='3'] { --slot-bg: #38306b; --slot-fg: #e7ddff; }
  :global(.dark) .kuh-slot[data-color='4'], :global(.dark) .kuh-slot-row[data-color='4'] { --slot-bg: #1c4a2e; --slot-fg: #bcf0c9; }
  :global(.dark) .kuh-slot[data-color='5'], :global(.dark) .kuh-slot-row[data-color='5'] { --slot-bg: #56303c; --slot-fg: #ffd8e4; }
  :global(.dark) .kuh-slot[data-color='6'], :global(.dark) .kuh-slot-row[data-color='6'] { --slot-bg: #55440f; --slot-fg: #fcdd83; }
  :global(.dark) .kuh-slot[data-color='7'], :global(.dark) .kuh-slot-row[data-color='7'] { --slot-bg: #1e4441; --slot-fg: #cfe8e4; }
  :global(.dark) .kuh-slot, :global(.dark) .kuh-slot-row { --slot-bg: #2a2d29; --slot-fg: #e4e2e2; }
</style>
