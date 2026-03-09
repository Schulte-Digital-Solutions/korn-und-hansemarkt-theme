# korn-und-hansemarkt-theme

Neue Website für den Korn- und Hansemarkt Haselünne. Ziel ist eine moderne, schnelle und leicht pflegbare Website auf Basis von WordPress.

## Technologie-Stack

- **WordPress** – CMS & REST API Backend
- **Svelte 5** – Reaktives Frontend-Framework (SPA)
- **TailwindCSS 4** – Utility-First CSS Framework
- **Vite** – Build-Tool & Dev-Server
- **TypeScript** – Typisiertes JavaScript

## Projektstruktur

```
├── functions.php          # Theme-Setup, Konstanten & Includes
├── index.php              # Minimales PHP-Template (SPA-Shell)
├── style.css              # WordPress Theme-Metadaten
├── svelte.config.js       # Svelte-Konfiguration
├── package.json           # Node.js Abhängigkeiten
├── vite.config.ts         # Vite Build-Konfiguration
├── tsconfig.json          # TypeScript-Konfiguration
├── inc/
│   ├── helpers.php        # Logo, Menüs, SVG-Support
│   ├── assets.php         # Vite Dev Server / Production Build
│   ├── customizer.php     # Farben & Header Customizer
│   ├── rest-api.php       # REST-Felder, Menü-Endpoint, CORS
│   ├── meta-fields.php    # Custom Meta-Felder & Meta-Boxen
│   └── seo.php            # SEO Meta-Tags & Open Graph
├── src/
│   ├── main.ts            # Svelte App Einstiegspunkt
│   ├── App.svelte         # Root-Komponente mit Layout
│   ├── app.css            # Globale Styles & TailwindCSS
│   ├── types.ts           # TypeScript Typdefinitionen
│   ├── vite-env.d.ts      # Vite Typdeklarationen
│   ├── assets/
│   │   └── fonts/         # Selbst gehostete Schriftarten (woff2)
│   ├── lib/
│   │   ├── api.ts         # WordPress REST API Client
│   │   └── router.ts      # Client-Side Router Logik
│   ├── components/
│   │   ├── Header.svelte  # Navigation & Logo
│   │   ├── Footer.svelte  # Footer mit Links
│   │   ├── Loading.svelte # Lade-Spinner
│   │   ├── Link.svelte    # SPA-Link-Komponente
│   │   └── Router.svelte  # Client-Side Router
│   └── routes/
│       ├── index.ts       # Router-Konfiguration
│       ├── Home.svelte    # Startseite
│       ├── Blog.svelte    # Blog-Übersicht
│       ├── SinglePost.svelte  # Einzelner Beitrag
│       ├── Page.svelte    # WordPress-Seite
│       └── NotFound.svelte    # 404-Seite
└── dist/                  # Build-Output (gitignored)
```

## Entwicklung

### Voraussetzungen

- Node.js >= 18
- npm
- Lokale WordPress-Installation

### Installation

```bash
npm install
```

### Development Server starten

```bash
npm run dev
```

Der Vite Dev-Server läuft auf `http://localhost:5173`. In der `wp-config.php` muss `WP_DEBUG` auf `true` stehen, damit das Theme den Dev-Server nutzt.

### Produktion Build

```bash
npm run build
```

Erstellt optimierte Assets im `dist/` Ordner. Das Theme liest automatisch das Vite-Manifest und bindet die Build-Dateien ein.

### Theme installieren (Entwicklung)

1. Repository klonen nach `wp-content/themes/korn-und-hansemarkt-theme/`
2. `npm install` ausführen
3. Theme in WordPress aktivieren
4. `npm run dev` starten
5. Menüs unter *Design → Menüs* zuweisen (Hauptnavigation & Footer)
6. Optional: Statische Frontpage unter *Einstellungen → Lesen* festlegen

### Deployment (Produktion)

Das Deployment läuft automatisch über **GitHub Actions**. Beim Pushen eines Version-Tags (`v*`) wird das Theme gebaut, per rsync auf den Server deployt und ein GitHub Release erstellt.

Auf dem Produktionsserver landen **nur** die benötigten Dateien:

```
korn-und-hansemarkt-theme/
├── functions.php
├── index.php
├── style.css
├── LICENSE
├── screenshot.png   # falls vorhanden
├── inc/             # PHP-Module
└── dist/            # Build-Output
```

#### GitHub Secrets einrichten

Im Repository unter *Settings → Secrets and variables → Actions* folgende Secrets anlegen:

| Secret | Beschreibung | Beispiel |
|--------|-------------|----------|
| `DEPLOY_HOST` | Hostname/IP des Servers | `example.com` |
| `DEPLOY_USER` | SSH-Benutzername | `www-data` |
| `DEPLOY_PASSWORD` | SSH-Passwort | `***` |
| `DEPLOY_PATH` | Zielpfad auf dem Server | `/var/www/html/wp-content/themes/korn-und-hansemarkt-theme/` |
| `DEPLOY_PORT` | SSH-Port (optional, Standard: 22) | `22` |

#### Deployment-Ablauf

1. Tag pushen (`git tag v1.0.0 && git push --tags`) triggert den Workflow
2. Version in `style.css` wird automatisch aus dem Tag aktualisiert
3. GitHub Actions baut das Projekt (`npm ci && npm run build`)
4. Nur produktionsrelevante Dateien werden per rsync auf den Server kopiert
5. Ein GitHub Release mit ZIP-Archiv wird erstellt
6. `WP_DEBUG` in `wp-config.php` sollte auf dem Server auf `false` stehen

## SPA-Routing

Das Theme nutzt History-API-basiertes Client-Side-Routing mit sauberen URLs (SEO-freundlich). WordPress leitet alle Frontend-Requests auf `index.php` um, Svelte übernimmt das Routing im Browser.

| Route | Komponente | Beschreibung |
|-------|-----------|--------------|
| `/` | Home | Startseite mit Frontpage & neuesten Posts |
| `/blog` | Blog | Blog-Übersicht mit Pagination |
| `/post/:slug` | SinglePost | Einzelner Blog-Beitrag |
| `/:slug` | Page | Beliebige WordPress-Seite |
| `*` | NotFound | 404-Fehlerseite |

### SEO

WordPress generiert serverseitig `<title>`, `<meta description>` und Open-Graph-Tags für jede URL, damit Suchmaschinen und Social-Media-Crawler die richtigen Inhalte sehen – auch ohne JavaScript.

## REST API

Das Theme erweitert die WordPress REST API um:

- **`/wp-json/kuh/v1/menus`** – Navigationsmenüs
- **Featured Image URLs** – Automatisch an Posts/Pages angehängt
- **Frontend-Daten** – Site-Info, Menüs, Nonce via `window.kuhData`

## Custom Meta-Felder

| Feld | Post-Typen | Beschreibung |
|------|-----------|-------------|
| `kuh_hide_title` | Post, Page | Titel auf dieser Seite/Beitrag ausblenden (Checkbox im Editor) |

