<script lang="ts">
  interface CollageImage {
    id: number;
    url: string;
    alt: string;
  }

  interface Props {
    images: CollageImage[];
    /** Gerenderter InnerBlocks-HTML-Content */
    contentHtml: string;
    overlayOpacity: number;
    /** Wechselgeschwindigkeit in Sekunden (Intervall pro Zelle) */
    swapSpeed?: number;
  }

  let {
    images,
    contentHtml,
    overlayOpacity,
    swapSpeed = 5,
  }: Props = $props();

  const CELL_COUNT = 9;
  const FADE_MS = 800;

  /** Fisher-Yates Shuffle */
  function shuffle<T>(arr: T[]): T[] {
    const a = [...arr];
    for (let i = a.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
  }

  // Zwei Layer pro Zelle für Crossfade: back (darunter) + front (darüber)
  interface CellState {
    front: CollageImage;
    back: CollageImage;
    showFront: boolean; // true = front sichtbar, false = back sichtbar
  }

  let cells = $state<CellState[]>([]);
  /** Pool nicht-angezeigter Bilder – beim Wechsel wird getauscht */
  let pool = $state<CollageImage[]>([]);

  function initCells() {
    if (images.length === 0) return;
    const shuffled = shuffle(images);
    const cellImages = shuffled.slice(0, CELL_COUNT);
    pool = shuffle(shuffled.slice(CELL_COUNT));
    cells = cellImages.map((img) => ({
      front: img,
      back: img,
      showFront: true,
    }));
  }

  $effect(() => {
    initCells();
  });

  // Intervallbereich aus swapSpeed berechnen
  let minDelay = $derived(Math.max(swapSpeed * 600, 1500));
  let maxDelay = $derived(Math.max(swapSpeed * 1400, 3000));

  // Gestaffelter Bildwechsel mit Crossfade (nur wenn Pool vorhanden)
  $effect(() => {
    if (cells.length === 0 || pool.length === 0) return;

    const timers: ReturnType<typeof setTimeout>[] = [];

    function scheduleSwap(cellIndex: number) {
      const delay = minDelay + Math.random() * (maxDelay - minDelay);

      const timer = setTimeout(() => {
        const cell = cells[cellIndex];

        // Zufälliges Bild aus dem Pool nehmen
        const poolIndex = Math.floor(Math.random() * pool.length);
        const newImage = pool[poolIndex];

        if (cell.showFront) {
          // front sichtbar → neues Bild in back, altes front zurück in Pool
          pool[poolIndex] = cell.front;
          cell.back = newImage;
          cell.showFront = false;
        } else {
          // back sichtbar → neues Bild in front, altes back zurück in Pool
          pool[poolIndex] = cell.back;
          cell.front = newImage;
          cell.showFront = true;
        }

        scheduleSwap(cellIndex);
      }, delay);

      timers.push(timer);
    }

    for (let i = 0; i < CELL_COUNT; i++) {
      const initialDelay = 1000 + Math.random() * (maxDelay - minDelay);
      const timer = setTimeout(() => scheduleSwap(i), initialDelay);
      timers.push(timer);
    }

    return () => {
      timers.forEach(clearTimeout);
    };
  });
</script>

<section class="relative w-screen min-h-[80vh] overflow-hidden -mx-[calc((100vw-100%)/2)]">
  <!-- Vollflächiges Bilder-Grid als Hintergrund -->
  <div class="absolute inset-0 grid grid-cols-3 grid-rows-3 w-full h-full">
    {#each cells as cell, i}
      <div class="relative overflow-hidden">
        <!-- Back layer -->
        <img
          src={cell.back.url}
          alt={cell.back.alt}
          loading={i < 3 ? 'eager' : 'lazy'}
          class="absolute inset-0 w-full h-full object-cover"
        />
        <!-- Front layer (crossfade) -->
        <img
          src={cell.front.url}
          alt={cell.front.alt}
          loading={i < 3 ? 'eager' : 'lazy'}
          class="absolute inset-0 w-full h-full object-cover transition-opacity ease-in-out"
          style="transition-duration: {FADE_MS}ms;"
          class:opacity-100={cell.showFront}
          class:opacity-0={!cell.showFront}
        />
      </div>
    {/each}
  </div>

  <!-- Dunkles Overlay über alle Bilder -->
  <div
    class="absolute inset-0"
    style:background-color="rgba(0, 0, 0, {overlayOpacity / 100})"
  ></div>

  <!-- Content zentriert mit Blur-Hintergrund -->
  <div class="relative z-10 flex items-center justify-center min-h-[80vh] px-4">
    <div class="backdrop-blur-xl bg-black/30 border border-white/20 px-8 py-10 sm:px-14 sm:py-14 text-center max-w-2xl shadow-2xl hero-collage-content">
      {@html contentHtml}
    </div>
  </div>

  <!-- Bottom gradient fade -->
  <div class="absolute bottom-0 left-0 right-0 h-32 bg-linear-to-t from-black/60 to-transparent pointer-events-none"></div>
</section>

<style>
  /* Styling für InnerBlocks-Content im Hero-Bereich */
  :global(.hero-collage-content h1),
  :global(.hero-collage-content h2),
  :global(.hero-collage-content h3) {
    color: #fff;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
  }
  :global(.hero-collage-content h1) {
    font-size: clamp(1.875rem, 4vw, 3.75rem);
    line-height: 1.1;
    margin-bottom: 0.75rem;
  }
  :global(.hero-collage-content p) {
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 1.5rem;
    font-weight: 300;
    letter-spacing: 0.025em;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
  }
  :global(.hero-collage-content .wp-block-buttons) {
    justify-content: center;
  }
  :global(.hero-collage-content .wp-block-button__link) {
    font-weight: 600;
    transition: all 0.3s;
  }
  :global(.hero-collage-content .wp-block-button__link:hover) {
    transform: scale(1.05);
  }
</style>
