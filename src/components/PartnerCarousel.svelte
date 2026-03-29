<script lang="ts">
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
      <div class="relative w-full">
        <div
          class="flex w-[200%] carousel-track"
          style:animation-duration="{speed}s"
        >
          <!-- Group 1 -->
          <div class="flex items-center justify-around w-1/2 min-w-max gap-12 px-6">
            {#each visiblePartners as partner (partner.id)}
              {#if partner.url}
                <a
                  href={partner.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  class="grayscale opacity-60 hover:grayscale-0 hover:opacity-100 transition-all"
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
          <!-- Group 2 (Clone for seamless loop) -->
          <div class="flex items-center justify-around w-1/2 min-w-max gap-12 px-6" aria-hidden="true">
            {#each visiblePartners as partner (partner.id)}
              {#if partner.url}
                <a
                  href={partner.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  class="grayscale opacity-60 hover:grayscale-0 hover:opacity-100 transition-all"
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
                  class="w-auto object-contain grayscale opacity-60"
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
      transform: translateX(-50%);
    }
  }

  .carousel-track {
    animation: infinite-scroll linear infinite;
  }

  .carousel-track:hover {
    animation-play-state: paused;
  }
</style>
