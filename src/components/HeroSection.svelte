<script lang="ts">
  interface Props {
    /** Bild-URL für das Hintergrundbild */
    imageUrl: string;
    /** Alt-Text für das Hintergrundbild */
    imageAlt: string;
    /** Gerenderter InnerBlocks-HTML-Content */
    contentHtml: string;
    /** Overlay-Deckkraft (0–100) */
    overlayOpacity: number;
  }

  let {
    imageUrl,
    imageAlt,
    contentHtml,
    overlayOpacity,
  }: Props = $props();
</script>

<section class="relative min-h-[751px] flex items-center overflow-hidden">
  <!-- Fullscreen Hintergrundbild -->
  <div class="absolute inset-0 z-0">
    {#if imageUrl}
      <img
        src={imageUrl}
        alt={imageAlt}
        class="absolute inset-0 w-full h-full object-cover"
        loading="eager"
      />
    {/if}
    <!-- Gradient Overlay -->
    <div
      class="absolute inset-0 bg-linear-to-r from-[--color-primary]/80 via-[--color-primary]/40 to-transparent"
      style:--overlay-opacity="{overlayOpacity / 100}"
    ></div>
  </div>

  <!-- Content -->
  <div class="relative z-10 max-w-7xl mx-auto px-6 w-full">
    <div class="max-w-2xl hero-section-content">
      {@html contentHtml}
    </div>
  </div>
</section>

<style>
  /* Headline-Styling für InnerBlocks im Hero */
  :global(.hero-section-content h1),
  :global(.hero-section-content h2) {
    font-family: var(--font-headline);
    color: var(--color-on-primary);
    font-size: clamp(3rem, 8vw, 6rem);
    line-height: 1;
    margin-bottom: 1rem;
    margin-left: -0.25rem;
    opacity: 0.95;
  }
  @media (min-width: 768px) {
    :global(.hero-section-content h1),
    :global(.hero-section-content h2) {
      margin-left: -2rem;
    }
  }
  :global(.hero-section-content h3) {
    color: var(--color-on-primary);
    opacity: 0.95;
  }
  :global(.hero-section-content p) {
    font-family: var(--font-serif-italic);
    font-style: italic;
    color: rgba(255, 255, 255, 0.9);
    font-size: clamp(1.125rem, 2.5vw, 1.5rem);
    margin-bottom: 2rem;
  }
  :global(.hero-section-content .wp-block-buttons) {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    align-items: center;
  }
  :global(.hero-section-content .wp-block-button__link) {
    background-color: var(--color-primary);
    color: var(--color-on-primary);
    padding: 1rem 2rem;
    font-weight: 700;
    letter-spacing: -0.025em;
    border-radius: 0.5rem;
    transition: all 0.2s;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25);
    text-decoration: none;
  }
  :global(.hero-section-content .wp-block-button__link:hover) {
    background-color: var(--color-primary-container);
  }
  :global(.hero-section-content .wp-block-button__link:active) {
    transform: scale(0.95);
  }
  :global(.hero-section-content .wp-block-button.is-style-outline .wp-block-button__link) {
    background: transparent;
    border: none;
    border-bottom: 2px solid rgba(114, 92, 12, 0.5);
    border-radius: 0;
    padding: 0.25rem 0;
    box-shadow: none;
    color: var(--color-on-primary);
    font-weight: 700;
  }
  :global(.hero-section-content .wp-block-button.is-style-outline .wp-block-button__link:hover) {
    border-bottom-color: var(--color-secondary);
  }
</style>
