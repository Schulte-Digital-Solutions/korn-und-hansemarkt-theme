<script lang="ts">
  interface Props {
    /** Gerenderter InnerBlocks-HTML-Content (Headline, Text, Button) */
    contentHtml: string;
    /** Bild-URL für das optionale Hintergrundbild */
    imageUrl?: string;
    /** Alt-Text für das Hintergrundbild */
    imageAlt?: string;
    /** Overlay-Deckkraft (0–100) */
    overlayOpacity?: number;
  }

  let {
    contentHtml,
    imageUrl = '',
    imageAlt = '',
    overlayOpacity = 85,
  }: Props = $props();
</script>

<section class="mx-6 mb-24 max-w-7xl lg:mx-auto bg-primary rounded-xl overflow-hidden relative">
  {#if imageUrl}
    <img
      src={imageUrl}
      alt={imageAlt}
      class="absolute inset-0 w-full h-full object-cover"
      loading="lazy"
    />
  {/if}
  <div
    class="absolute inset-0 pointer-events-none"
    style="background-color: var(--color-primary); opacity: {overlayOpacity / 100};"
  ></div>
  <div class="relative z-10 py-20 px-12 text-center flex flex-col items-center cta-section-content">
    {@html contentHtml}
  </div>
</section>

<style>
  :global(.cta-section-content h2),
  :global(.cta-section-content h3) {
    font-family: var(--font-headline);
    color: var(--color-on-primary);
    font-size: clamp(2.5rem, 6vw, 4.5rem);
    margin-bottom: 1.5rem;
  }
  :global(.cta-section-content p) {
    color: rgba(255, 255, 255, 0.8);
    max-width: 36rem;
    margin-bottom: 2.5rem;
    font-size: 1.125rem;
  }
  :global(.cta-section-content .wp-block-buttons) {
    justify-content: center;
  }
  :global(.cta-section-content .wp-block-button__link) {
    background-color: var(--color-surface-container-lowest);
    color: var(--color-primary);
    padding: 1.25rem 3rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: -0.05em;
    border-radius: 0.5rem;
    transition: background-color 0.2s;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    text-decoration: none;
  }
  :global(.cta-section-content .wp-block-button__link:hover) {
    background-color: var(--color-secondary-container);
  }
</style>
