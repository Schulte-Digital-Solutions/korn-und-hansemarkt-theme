<script lang="ts">
  interface Props {
    /** HTML-Inhalt des Absatzes */
    text: string;
    /** Farbe des Initial-Buchstabens (CSS-Wert) */
    dropCapColor?: string;
    /** Schriftart des Initials: 'gothic' | 'serif' | 'inherit' */
    dropCapFont?: string;
    /** Maximale Breite als CSS-Wert (z. B. '65ch', '100%') */
    maxWidth?: string;
  }

  let { text, dropCapColor = '', dropCapFont = 'gothic', maxWidth = '65ch' }: Props = $props();

  const fontMap: Record<string, string> = {
    gothic: "'Manuskript Gotisch', serif",
    serif: "'Newsreader', serif",
    inherit: 'inherit',
  };

  const initialFont = $derived(fontMap[dropCapFont] || fontMap.gothic);
  const initialColor = $derived(dropCapColor || 'var(--color-primary)');
</script>

<p
  class="kuh-drop-cap-text"
  style:--kuh-dc-font={initialFont}
  style:--kuh-dc-color={initialColor}
  style:--kuh-dc-max-width={maxWidth}
>
  {@html text}
</p>

<style>
  .kuh-drop-cap-text {
    font-size: 1.125rem;
    line-height: 1.8;
    max-width: var(--kuh-dc-max-width, 65ch);
    margin: 0 auto 2rem;
    color: var(--color-on-surface);
  }

  .kuh-drop-cap-text::first-letter {
    font-size: 4em;
    float: left;
    line-height: 0.8;
    margin-right: 0.1em;
    margin-top: 0.05em;
    font-weight: normal;
    font-family: var(--kuh-dc-font);
    color: var(--kuh-dc-color);
  }
</style>
