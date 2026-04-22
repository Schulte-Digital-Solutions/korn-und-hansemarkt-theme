/**
 * Outline-Buttons: vom User gesetzte Hintergrundfarbe als Border- und
 * Textfarbe übernehmen, Hintergrund transparent machen.
 *
 * Hintergrund: theme.json/Editor speichert Farben pro Button. Damit
 * "Gefüllt" (Fill) und "Konturen" (Outline) sich optisch unterscheiden,
 * ohne dass der Redakteur die Farben doppelt setzen muss, übernehmen wir
 * für Outline die Hintergrundfarbe als Akzentfarbe.
 *
 * Wird nach jedem Content-Mount aufgerufen.
 */
function resolvePresetColor(slug: string): string | null {
    const root = window.getComputedStyle(document.documentElement);
    const value = root.getPropertyValue(`--wp--preset--color--${slug}`).trim();
    return value || null;
}

function getGutenbergBackgroundColor(btn: HTMLElement, computed: CSSStyleDeclaration): string {
    // 1) Inline-Style (Custom Color im Editor)
    const inlineBg = btn.style.backgroundColor;
    if (inlineBg) return inlineBg;

    // 2) Preset-Klasse: has-<slug>-background-color
    for (const cls of btn.classList) {
        const match = cls.match(/^has-(.+)-background-color$/);
        if (match) {
            const preset = resolvePresetColor(match[1]);
            if (preset) return preset;
        }
    }

    // 3) Fallback
    return computed.backgroundColor;
}

function getGutenbergTextColor(btn: HTMLElement, computed: CSSStyleDeclaration): string {
    // 1) Inline-Style (Custom Color im Editor)
    const inlineColor = btn.style.color;
    if (inlineColor) return inlineColor;

    // 2) Preset-Klasse: has-<slug>-color (ohne background/link-Helferklassen)
    for (const cls of btn.classList) {
        if (cls === 'has-text-color' || cls === 'has-link-color') continue;
        if (cls.endsWith('-background-color')) continue;
        const match = cls.match(/^has-(.+)-color$/);
        if (match) {
            const preset = resolvePresetColor(match[1]);
            if (preset) return preset;
        }
    }

    // 3) Fallback
    return resolvePresetColor('on-primary') || computed.color;
}

export function applyOutlineButtonColors(root: ParentNode = document): void {
    const fillButtons = root.querySelectorAll<HTMLElement>(
        '.wp-block-button:not(.is-style-outline) > .wp-block-button__link, .wp-block-button:not(.is-style-outline) > .wp-element-button',
    );

    fillButtons.forEach((btn) => {
        const computed = window.getComputedStyle(btn);
        btn.style.setProperty('--kuh-button-fill-bg-color', computed.backgroundColor);
        btn.style.setProperty('--kuh-button-fill-text-color', computed.color);
    });

    const buttons = root.querySelectorAll<HTMLElement>(
        '.wp-block-button.is-style-outline > .wp-block-button__link, .wp-block-button.is-style-outline > .wp-element-button',
    );

    buttons.forEach((btn) => {
        const computed = window.getComputedStyle(btn);
        const bg = getGutenbergBackgroundColor(btn, computed);
        const text = getGutenbergTextColor(btn, computed);

        // Nur anwenden, wenn überhaupt eine Hintergrundfarbe gesetzt ist
        // (transparent / rgba(0,0,0,0) ignorieren)
        const isTransparent =
            bg === 'transparent' ||
            bg === 'rgba(0, 0, 0, 0)' ||
            bg === 'rgba(0,0,0,0)' ||
            bg === '';

        const outlineColor = isTransparent ? computed.color : bg;
        const outlineTextColor = text;

        btn.style.setProperty('--kuh-button-outline-color', outlineColor);
        btn.style.setProperty('--kuh-button-outline-text-color', outlineTextColor);
    });
}
