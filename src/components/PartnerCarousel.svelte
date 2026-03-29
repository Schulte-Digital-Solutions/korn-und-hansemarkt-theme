<script lang="ts">
  import { onMount } from 'svelte';

  interface Partner {
    id: number;
    name: string;
    logo: string;
    url: string;
  }

  interface Props {
    title?: string;
    showTitle?: boolean;
    logoHeight?: number;
    speed?: number;
    variant?: 'carousel' | 'grid';
    partners: Partner[];
  }

  let {
    title = 'Unsere Partner & Unterstützer',
    showTitle = true,
    logoHeight = 48,
    speed = 30,
    variant = 'carousel',
    partners,
  }: Props = $props();

  const hasPartners = $derived(partners && partners.length > 0);
  const visiblePartners = $derived(
    partners
      .filter((p) => p.logo)
      .sort(() => Math.random() - 0.5)
  );

  let trackEl: HTMLDivElement | undefined = $state();
  let groupWidth = $state(0);

  onMount(() => {
    if (!trackEl) return;

    // Erste Gruppe messen sobald Bilder geladen sind
    function measure() {
      if (!trackEl) return;
      const firstGroup = trackEl.children[0] as HTMLElement;
      if (firstGroup) {
        groupWidth = firstGroup.scrollWidth;
      }
    }

    // Sofort messen + nochmal nach Bilder-Load
    measure();
    const imgs = trackEl.querySelectorAll('img');
    let loaded = 0;
    const total = imgs.length;
    if (total === 0) return;

    imgs.forEach((img) => {
      if (img.complete) {
        loaded++;
        if (loaded >= total / 2) measure();
      } else {
        img.addEventListener('load', () => {
          loaded++;
          if (loaded >= total / 2) measure();
        }, { once: true });
      }
    });
  });
</script>

{#if hasPartners}
  <section
    class="py-12 overflow-hidden"
    class:bg-surface-container-low={variant === 'carousel'}
  >
    {#if showTitle}
      <div class="max-w-7xl mx-auto px-6 mb-8 text-center">
        <h2 class="text-4xl font-headline text-primary">{title}</h2>
      </div>
    {/if}

    {#if variant === 'carousel'}
      <div class="relative w-full overflow-hidden">
        <div
          bind:this={trackEl}
          class="carousel-track flex"
          style:--scroll-width="{groupWidth}px"
          style:animation-duration="{speed}s"
        >
          <!-- Group 1 -->
          <div class="flex items-center gap-12 px-6 shrink-0">
            {#each visiblePartners as partner (partner.id)}
              {#if partner.url}
                <a
                  href={partner.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  class="grayscale opacity-60 hover:grayscale-0 hover:opacity-100 transition-all shrink-0"
                >
                  <img
                    src={partner.logo}
                    alt={partner.name}
                    class="w-auto object-contain"
                    style:height="{logoHeight}px"
                    loading="lazy"
                  />
                </a>
              {:else}
                <img
                  src={partner.logo}
                  alt={partner.name}
                  class="w-auto object-contain grayscale opacity-60 shrink-0"
                  style:height="{logoHeight}px"
                  loading="lazy"
                />
              {/if}
            {/each}
          </div>
          <!-- Group 2 (identical clone for seamless loop) -->
          <div class="flex items-center gap-12 px-6 shrink-0" aria-hidden="true">
            {#each visiblePartners as partner (partner.id)}
              {#if partner.url}
                <a
                  href={partner.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  class="grayscale opacity-60 hover:grayscale-0 hover:opacity-100 transition-all shrink-0"
                  tabindex="-1"
                >
                  <img
                    src={partner.logo}
                    alt=""
                    class="w-auto object-contain"
                    style:height="{logoHeight}px"
                    loading="lazy"
                  />
                </a>
              {:else}
                <img
                  src={partner.logo}
                  alt=""
                  class="w-auto object-contain grayscale opacity-60 shrink-0"
                  style:height="{logoHeight}px"
                  loading="lazy"
                />
              {/if}
            {/each}
          </div>
        </div>
      </div>
    {:else}
      <!-- Grid variant -->
      <div class="max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-8 items-center justify-items-center">
          {#each visiblePartners as partner (partner.id)}
            {#if partner.url}
              <a
                href={partner.url}
                target="_blank"
                rel="noopener noreferrer"
                class="grayscale opacity-60 hover:grayscale-0 hover:opacity-100 transition-all duration-300"
              >
                <img
                  src={partner.logo}
                  alt={partner.name}
                  class="w-auto object-contain"
                  style:height="{logoHeight}px"
                  loading="lazy"
                />
              </a>
            {:else}
              <img
                src={partner.logo}
                alt={partner.name}
                class="w-auto object-contain grayscale opacity-60"
                style:height="{logoHeight}px"
                loading="lazy"
              />
            {/if}
          {/each}
        </div>
      </div>
    {/if}
  </section>
{/if}

<style>
  @keyframes infinite-scroll {
    0% {
      transform: translateX(0);
    }
    100% {
      transform: translateX(calc(-1 * var(--scroll-width, 50%)));
    }
  }

  .carousel-track {
    animation: infinite-scroll linear infinite;
    will-change: transform;
  }

  .carousel-track:hover {
    animation-play-state: paused;
  }
</style>
