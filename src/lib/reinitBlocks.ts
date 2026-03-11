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
 */
export function reinitBlocks(): void {
    if (typeof window.__kuhReinitBlocks === 'function') {
        requestAnimationFrame(() => {
            window.__kuhReinitBlocks!();
        });
    }
}
