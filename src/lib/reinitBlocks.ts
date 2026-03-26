/**
 * Block-Plugin-Scripts nach SPA-Content-Injection re-initialisieren.
 *
 * Das PHP-Gegenstück (inc/block-compat.php) stellt zwei Mechanismen bereit:
 *
 * 1. DOMContentLoaded/load-Callbacks werden abgefangen und erneut ausgeführt
 *    (für Plugins wie Spectra, die Event-Listener nutzen).
 *
 * 2. Scripts mit data-kuh-reinit werden durch neue <script>-Tags ersetzt,
 *    was ihre IIFEs erneut ausführt (für Plugins wie CoBlocks, deren Scripts
 *    sofort bei Evaluation laufen).
 *
 * Beides wird über window.__kuhReinitBlocks() ausgelöst.
 *
 * Zusätzlich werden eigene Svelte-Blöcke (z.B. HeroSection) in den
 * gerenderten Content gemountet.
 */
import { mountBlocks, unmountBlocks } from './blockMounter';

export function reinitBlocks(): void {
    // Zuerst alte Svelte-Block-Instanzen aufräumen
    unmountBlocks();

    if (typeof window.__kuhReinitBlocks === 'function') {
        requestAnimationFrame(() => {
            window.__kuhReinitBlocks!();
        });
        // Complianz: DOM neu scannen für Content-Blocker & Platzhalter
        reinitComplianz();
    }
}

/**
 * Complianz Cookie-Consent nach SPA-Navigation neu triggern.
 *
 * Complianz blockiert Iframes/Scripts serverseitig (rest-api.php → replace_tags),
 * aber die clientseitige Platzhalter-Rendering (cmplz_set_blocked_content_container)
 * läuft nur beim initialen Seitenladen und optional im 2s-Intervall.
 *
 * Da cmplz_set_blocked_content_container eine globale Funktion in complianz.js ist,
 * rufen wir sie nach SPA-Navigation direkt auf. Elemente ohne die Klasse
 * 'cmplz-processed' werden dabei erkannt, Platzhalter gerendert und
 * bei bestehendem Consent direkt freigeschaltet.
 */
function reinitComplianz(): void {
    if (typeof window.cmplz_set_blocked_content_container === 'function') {
        window.cmplz_set_blocked_content_container();
    }

    // Svelte-Blöcke im neuen Content mounten
    requestAnimationFrame(() => {
        mountBlocks();
    });
}
