/**
 * Block-Mount-System für die SPA.
 *
 * Durchsucht gerenderten WordPress-Content nach Block-Containern
 * (data-kuh-*-Attribute) und mountet die passende Svelte-Komponente.
 */
import { mount, unmount } from 'svelte';
import HeroCollage from '../components/HeroCollage.svelte';

interface MountedBlock {
  container: HTMLElement;
  instance: Record<string, any>;
}

const mountedBlocks: MountedBlock[] = [];

/** Registry: data-Attribut → Svelte-Komponente */
const blockRegistry: Record<string, any> = {
  'kuh-hero-collage': HeroCollage,
};

/**
 * Alle Svelte-Blöcke im gegebenen Container (oder document) finden und mounten.
 */
export function mountBlocks(root: HTMLElement | Document = document): void {
  for (const [attr, Component] of Object.entries(blockRegistry)) {
    const containers = root.querySelectorAll<HTMLElement>(`[data-${attr}]`);

    for (const container of containers) {
      // Bereits gemountet? Überspringen.
      if (container.dataset.kuhMounted === 'true') continue;

      const rawData = container.getAttribute(`data-${attr}`);
      if (!rawData) continue;

      try {
        const props = JSON.parse(rawData);

        // Wrapper-Element für Svelte erstellen (neben noscript)
        const target = document.createElement('div');
        container.appendChild(target);

        const instance = mount(Component, { target, props });
        container.dataset.kuhMounted = 'true';
        mountedBlocks.push({ container, instance });
      } catch (e) {
        console.error(`[kuh] Fehler beim Mounten von ${attr}:`, e);
      }
    }
  }
}

/**
 * Alle gemounteten Blöcke aufräumen (z.B. vor Navigation).
 */
export function unmountBlocks(): void {
  while (mountedBlocks.length > 0) {
    const entry = mountedBlocks.pop()!;
    try {
      unmount(entry.instance);
      entry.container.removeAttribute('data-kuh-mounted');
      // Svelte-Target-div entfernen
      const target = entry.container.querySelector(':scope > div:last-child');
      target?.remove();
    } catch {
      // Bereits entfernt
    }
  }
}
