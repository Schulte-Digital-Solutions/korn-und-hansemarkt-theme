<script lang="ts">
  import { theme, setThemeMode, type ThemeMode } from '../lib/theme.svelte';

  const order: ThemeMode[] = ['light', 'dark', 'auto'];

  const icon = $derived(
    theme.mode === 'light' ? 'light_mode'
    : theme.mode === 'dark' ? 'dark_mode'
    : 'contrast'
  );

  const label = $derived(
    theme.mode === 'light' ? 'Hell'
    : theme.mode === 'dark' ? 'Dunkel'
    : 'Automatisch'
  );

  function cycle() {
    const next = order[(order.indexOf(theme.mode) + 1) % order.length];
    setThemeMode(next);
  }
</script>

<button
  type="button"
  onclick={cycle}
  class="inline-flex items-center justify-center rounded-md p-2 text-stone-600 transition-colors hover:bg-stone-100/90 hover:text-emerald-900 dark:text-on-surface-variant dark:hover:bg-white/10 dark:hover:text-on-surface cursor-pointer"
  aria-label="Farbmodus umschalten: {label}"
  title="Farbmodus: {label}"
>
  <span class="material-symbols-outlined text-xl!">{icon}</span>
</button>
