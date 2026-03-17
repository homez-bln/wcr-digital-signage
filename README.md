# 🖥️ WCR Digital Signage System

**Enterprise Digital Signage Solution für Freizeiteinrichtungen**

[![Deploy Status](https://img.shields.io/badge/deploy-automated-success)](https://github.com/homez-bln/wcr-digital-signage/actions)
[![WordPress](https://img.shields.io/badge/WordPress-6.4+-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0+-purple)](https://php.net)
[![License](https://img.shields.io/badge/license-Proprietary-red)](LICENSE)

---

## 📋 Projekt-Übersicht

**WCR Digital Signage** ist eine spezialisierte Lösung zur dynamischen Verwaltung und Anzeige von Content auf 7 Displays in einer Freizeiteinrichtung. Das System verbindet ein WordPress-Plugin für die öffentliche Content-Ausgabe mit einem geschützten PHP-Backend für die Verwaltung sowie einem Live-Control-Dashboard zur Steuerung von PiSignage-Playern über ein MIDI-Controller-ähnliches Interface.

### Kernfunktionen

- 🍹 **Menü-Management** – Getränkekarten, Speisekarten, Kaffee, Eis (inkl. Highlight- und Portrait-Templates)
- 💰 **Preislisten** – Cable-Park, Camping, Verleih, dynamische Preisänderungen
- 🌤️ **Live-Daten** – Wetter-Widget, Windkarte mit Open-Meteo API
- 🎬 **Content-Management** – Kino-Programm, Obstacles-Verwaltung
- 🎟️ **Ticket-System** – Event-Tickets mit Live-Status
- 👥 **Benutzer-Verwaltung** – Rollen-basiertes Zugriffssystem
- 📺 **Playlist Engine** – Dynamische Playlist-Zuweisung zu PiSignage-Gruppen mit Presets, Tageszeit-Logik und manuellen Triggern
- 🎛️ **Live-Control-Dashboard** – 4-Tab-Interface (Stream Deck, Monitor Matrix, Presets, Content Editor)

---

## 🏗️ Architektur-Übersicht

> **Hinweis:** Die folgende Darstellung ist eine **konzeptionelle Übersicht** zur Verdeutlichung der System-Architektur. Die tatsächliche Dateistruktur weicht davon ab – siehe Abschnitt „Verzeichnisstruktur" für exakte Pfade.

**Drei-System-Architektur:**

```
┌─────────────────────────────────────┐
│   WordPress Installation            │
│   /WebSpace/wordpress/              │
│                                     │
│   ┌───────────────────────────────┐ │
│   │  wcr-digital-signage/         │ │  ← WordPress-Plugin
│   │  (PUBLIC FRONTEND)            │ │     • Öffentliche Signage-Anzeige
│   │  • Shortcodes                 │ │     • Read-Only REST-API
│   │  • REST-API (Read-Only)       │ │     • Content-Templates (16:9 + 9:16)
│   └───────────────────────────────┘ │
└─────────────────────────────────────┘
              ↓ (DB-Zugriff)
      ┌───────────────────┐
      │   MySQL Database  │
      │   be_*-Tabellen   │
      └───────────────────┘
              ↑ (DB-Zugriff)
┌─────────────────────────────────────┐
│   Standalone PHP Application        │
│   /WebSpace/be/                     │
│                                     │
│   be/ (PROTECTED BACKEND)           │  ← Separates Backend
│   • Session-basiertes Login         │     • Content-Verwaltung
│   • Schreibende REST-APIs           │     • PiSignage API Proxy
│   • Admin-Interface                 │     • Playlist & Preset Management
│   • Rollen-System (3 Rollen)        │
└─────────────────────────────────────┘
              ↓ (HTTPS API)
┌─────────────────────────────────────┐
│   GitHub Pages                      │
│   docs/control/index.html           │  ← Live-Control-Dashboard
│   • TAB 1: Stream Deck              │     • Playlist-Trigger (MIDI-Style)
│   • TAB 2: Monitor Matrix           │     • 7 Displays live überwachen
│   • TAB 3: Presets & Zeitplan       │     • Drag & Drop Zuweisung
│   • TAB 4: Content Editor           │     • Priority-System
└─────────────────────────────────────┘
              ↓ (PiSignage API)
┌─────────────────────────────────────┐
│   PiSignage Cloud / Server          │  ← Display-Steuerung
│   5 Gruppen / 7 Player              │     • Playlist-Switching
│   Schedules / Domination / Emergency│     • Gruppen-Management
└─────────────────────────────────────┘
```

---

## 📺 Display-Matrix (7 Screens)

| ID | Standort | Ausrichtung | Gruppe | Primäre Playlist |
|----|----------|-------------|--------|-----------------|
| `tv-eingang` | Eingang | Portrait 9:16 | `grp-eingang` | Standard Info, Kurs-Begrüßung |
| `tv-tresen-1` | Haupttresen links | Landscape 16:9 | `grp-tresen` | Food & Getränke |
| `tv-tresen-2` | Haupttresen rechts | Landscape 16:9 | `grp-tresen` | Drinks Specials |
| `tv-kaffebar` | Kaffebar seitlich | Landscape 16:9 | `grp-kaffebar` | Kaffee & Frühstück |
| `tv-umkleide-1` | Umkleide 1 | Landscape 16:9 | `grp-umkleide` | Kursplan & Info |
| `tv-umkleide-2` | Umkleide 2 | Landscape 16:9 | `grp-umkleide` | Kursplan & Info |
| `tv-eis` | Eisbereich | Portrait 9:16 | `grp-eis` | Eis Preisliste (Portrait) |

---

## 🎛️ Playlist Engine & Control System

### Playlist-Typen

Jede Playlist besteht aus **WordPress-Seiten als Slides** – PiSignage lädt diese als Web-Assets:

| Playlist | Slides (WordPress-URLs) | Primäre Displays |
|----------|------------------------|-----------------|
| Standard Info | `/signage/info`, `/signage/wetter` | tv-eingang |
| Food & Getränke | `/signage/food`, `/signage/getraenke`, `/signage/wetter` | tv-tresen-1/2 |
| Drinks Specials | `/signage/specials`, `/signage/instagram` | tv-tresen-2 |
| Kaffee & Frühstück | `/signage/kaffee`, `/signage/food`, `/signage/wetter` | tv-kaffebar |
| Kursplan Info | `/signage/kursplan`, `/signage/wetter` | tv-umkleide |
| Eis Preisliste | `/signage/eis-portrait` (9:16) | tv-eis |
| **Einsteigerkurs** ✨ | `/signage/kurs-welcome`, `/signage/kursplan`, `/signage/kurs-regeln` | tv-eingang, tv-tresen-1 |
| **Verleih getrennt** ✨ | `/signage/verleih`, `/signage/getraenke` | grp-tresen (Override) |

### Content-Templates: Ein Datensatz – mehrere Formate

Derselbe DB-Datensatz wird je nach Display-Kontext in verschiedenen Templates gerendert:

| Shortcode | Format | Zweck | Display |
|-----------|--------|-------|---------|
| `[wcr_eis]` | 16:9 Standard | Eis in normaler Playlist | tv-tresen (als Slide) |
| `[wcr_eis_highlight]` ✨ | 16:9 Highlight | Top 2–3 Sorten, Foto groß, Preis fett | tv-tresen (Domination) |
| `[wcr_eis_portrait]` ✨ | 9:16 Portrait | Volle Liste, Stimmungsbild oben | tv-eis (Dedicated) |

Das gleiche Prinzip gilt für Food, Kaffee und Verleih.

### Priority-System

Trigger-Typ-Hierarchie (fest, hardcoded):

```
🔴 NOTFALL-OVERRIDE        Priorität 1000  (alle Screens gleichzeitig)
🟠 MANUELLER TRIGGER       Priorität 100   (Dashboard-Button)
🟡 EVENT-PRESET            Priorität 50    (Sa Morgen, Anfängerkurs, etc.)
🟢 TAGESZEIT-PRESET        Priorität 20    (Morgen / Tag / Abend)
⚪ STANDARD-FALLBACK       Priorität 0     (immer aktiv wenn nichts greift)
```

Innerhalb derselben Ebene: Feinjustierung per **Drag-Reihenfolge** oder **Slider (0–100)** im Dashboard. Konflikte werden live visualisiert (Timeline-Ansicht mit Konflikt-Checker).

### Beispiel-Presets

| Preset | Zone | Wochentag | Zeit | Playlist |
|--------|------|-----------|------|----------|
| Morgen Standard | Haupttresen | Mo–Fr | 08–12 | Food & Kaffee |
| Sa Anfängerkurs | Eingang + Tresen | Sa | 09–11:30 | Einsteigerkurs |
| Verleih getrennt | Tresen | Manuell | – | Nur Essen & Trinken |
| Abend Bar | Tresen | Mo–So | 18–23 | Drinks Specials + Instagram |
| Eis Highlight | Tresen | Manuell | – | Domination: Eis Highlight |

---

## 🗂️ Verzeichnisstruktur

```
wcr-digital-signage/
│
├── wcr-digital-signage/              # WordPress-Plugin
│   ├── wcr-digital-signage.php       # Haupt-Plugin-Datei
│   ├── includes/
│   │   ├── rest-api.php              # Read-Only REST-API (Namespace: wakecamp/v1)
│   │   ├── rest-screenshot.php       # Screenshot-API (CSRF-geschützt)
│   │   ├── shortcodes.php            # Shortcode-Registrierung (zentral)
│   │   ├── shortcodes-content.php    # Content-Shortcodes (Menü, Preislisten, Templates)
│   │   ├── shortcodes-display.php    # Display-Shortcodes (Wetter, Windkarte)
│   │   ├── shortcodes-widgets.php    # Widget-Shortcodes (Animationen)
│   │   ├── playlist-pages.php        # ✨ NEU: Kurs-, Willkommen-, Event-Seiten
│   │   ├── shortcode-kino.php        # LEGACY
│   │   ├── shortcode-produkte.php    # LEGACY
│   │   ├── instagram.php             # Instagram-API-Klasse
│   │   ├── screenshot.php            # Screenshot-Generator
│   │   ├── enqueue.php               # CSS/JS Assets Enqueue
│   │   └── db.php                    # WordPress-DB-Connection
│   └── assets/
│       ├── css/
│       │   ├── wcr-ds-global.css
│       │   ├── wcr-ds-components.css
│       │   ├── wcr-ds-landscape.css
│       │   ├── wcr-ds-portrait.css
│       │   ├── wcr-ds-unified.css
│       │   ├── wcr-ds-theme-glass.css
│       │   ├── wcr-produkte.css
│       │   ├── wcr-kino-slider.css
│       │   ├── wcr-instagram.css
│       │   ├── wcr-instagram-video.css
│       │   ├── wcr-obstacles-map.css
│       │   └── themes/
│       └── js/
│           ├── wcr-frontend.js
│           ├── wcr-wetter.js
│           └── ...
│
├── be/                               # Standalone Backend
│   ├── index.php                     # Dashboard
│   ├── login.php
│   ├── logout.php
│   ├── inc/
│   │   ├── auth.php                  # Session + Rollen + CSRF
│   │   ├── db.php                    # DB-Verbindung (PDO)
│   │   ├── menu.php                  # Navigation
│   │   ├── style.css                 # Backend-Styles (NUR hier, nicht be/css/!)
│   │   ├── debug.php                 # Debug-Panel (nur cernal)
│   │   └── pisignage-client.php      # ✨ NEU: PiSignage HTTP Client
│   ├── ctrl/
│   │   ├── drinks.php
│   │   ├── food.php
│   │   ├── times.php
│   │   ├── kino.php
│   │   ├── obstacles.php
│   │   ├── ds-seiten.php
│   │   ├── ds-settings.php
│   │   ├── users.php
│   │   └── signage-control.php       # ✨ NEU: Playlist/Preset/Trigger Controller
│   ├── api/
│   │   ├── drinks.php
│   │   ├── food.php
│   │   ├── users.php
│   │   └── pisignage.php             # ✨ NEU: PiSignage API Proxy (CSRF-geschützt)
│   ├── js/
│   ├── img/
│   └── _deprecated/
│       └── README.md
│
├── docs/                             # ✨ NEU: GitHub Pages
│   └── control/
│       └── index.html                # Live-Control-Dashboard (4 Tabs)
│
├── .github/workflows/
│   └── deploy.yml                    # GitHub Actions: IONOS SFTP + GitHub Pages
│
├── ARCHITECTURE.md                   # Vollständige technische Dokumentation
├── KORREKTUREN.md
└── README.md
```

---

## 🗄️ Datenbank-Tabellen

### Bestehende Tabellen

| Tabelle | Inhalt |
|---------|--------|
| `be_drinks` | Getränkekarte |
| `be_food` | Speisekarte |
| `be_ice` | Eiskarte |
| `be_cable` | Cable-Park-Preise |
| `be_camping` | Camping-Preise |
| `be_users` | Benutzer & Rollen |

### Neue Tabellen (Playlist Engine) ✨

```sql
-- Playlisten-Definition
CREATE TABLE be_playlists (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  zone VARCHAR(50),         -- 'haupttresen' | 'verleih' | 'eingang' | 'kurs'
  content_blocks JSON,      -- [{"url":"/signage/food","duration":15}, ...]
  is_active TINYINT DEFAULT 1
);

-- Zeit-Presets (automatische Trigger)
CREATE TABLE be_presets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  playlist_id INT,
  weekdays VARCHAR(20),     -- '1,2,3,4,5' = Mo–Fr | '6' = Sa | '0' = So
  time_start TIME,
  time_end TIME,
  priority INT DEFAULT 0
);

-- Manuelle Trigger / Overrides
CREATE TABLE be_triggers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  playlist_id INT,
  zone VARCHAR(50),
  triggered_by VARCHAR(50), -- 'manual' | 'event' | 'schedule'
  active_until DATETIME,    -- NULL = bis nächstes Preset
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🚀 Schnelleinstieg

### Voraussetzungen

- **WordPress:** 6.4+
- **PHP:** 8.0+
- **MySQL/MariaDB:** 5.7+
- **PiSignage:** Cloud- oder Self-hosted Server
- **Git** + **GitHub Actions**

### Installation

```bash
# 1. Repository klonen
git clone https://github.com/homez-bln/wcr-digital-signage.git

# 2. Plugin nach WordPress kopieren
cp -r wcr-digital-signage /path/to/wordpress/wp-content/plugins/

# 3. Backend nach WebSpace kopieren
cp -r be /path/to/webspace/be/

# 4. DB-Credentials eintragen
# → be/inc/db.php

# 5. Plugin in WordPress aktivieren
# → WP-Admin → Plugins → "WCR Digital Signage" aktivieren

# 6. Backend-Login
# → https://your-domain.de/be/login.php

# 7. Control-Dashboard (GitHub Pages)
# → https://homez-bln.github.io/wcr-digital-signage/control/
```

---

## 🔐 Sicherheitskonzept

### Rollen-System

| Rolle | Berechtigungen |
|-------|---------------|
| **cernal** | Alle Rechte + Debug-Panel + PiSignage-Admin |
| **admin** | Content-Management + Playlist-Management + User-Management |
| **user** | Toggle-Funktionen, manuelle Trigger (kein Preset-Edit) |

### Session-Sicherheit

- ✅ Secure Cookies (HTTPS-only, HttpOnly, SameSite=Strict)
- ✅ Session-Fingerprinting (User-Agent + IP-Präfix)
- ✅ 8-Stunden-Timeout
- ✅ Session-Regeneration bei Login

### CSRF-Protection

- ✅ Token-Rotation nach erfolgreicher Validierung
- ✅ Constant-Time Comparison
- ✅ Alle Write-APIs geschützt (inkl. `be/api/pisignage.php`)

---

## 🌐 REST-API

### Plugin REST-API (Read-Only)

**Namespace:** `wakecamp/v1` *(nicht `wcr/v1`!)*

| Route | Zweck |
|-------|-------|
| `GET /wakecamp/v1/drinks` | Getränkekarte |
| `GET /wakecamp/v1/food` | Speisekarte |
| `GET /wakecamp/v1/ice` | Eiskarte |
| `GET /wakecamp/v1/cable` | Cable-Park-Preise |
| `GET /wakecamp/v1/camping` | Camping-Preise |
| `GET /wakecamp/v1/kino` | Kino-Programm |
| `GET /wakecamp/v1/events` | Events |
| `GET /wakecamp/v1/obstacles` | Obstacles-Map |
| `GET /wakecamp/v1/instagram` | Instagram-Posts |
| `GET /wakecamp/v1/ping` | Health-Check |

### Backend REST-API (Write)

**Basis-URL:** `https://your-domain.de/be/api/`

| API | Methoden | Zweck |
|-----|----------|-------|
| `be/api/drinks.php` | POST, PUT, DELETE | Getränke verwalten |
| `be/api/food.php` | POST, PUT, DELETE | Speisen verwalten |
| `be/api/users.php` | POST, PUT, DELETE | Benutzer verwalten |
| `be/api/pisignage.php` ✨ | POST | PiSignage Proxy (Playlist-Switching, Trigger, Status) |

### PiSignage Proxy Endpoints ✨

| Route | Zweck |
|-------|-------|
| `POST be/api/pisignage.php?action=resolve` | Aktive Playlist für Zone ermitteln |
| `POST be/api/pisignage.php?action=trigger` | Manuellen Override setzen |
| `POST be/api/pisignage.php?action=status` | Alle Zonen + aktive Playlisten (für Dashboard) |
| `POST be/api/pisignage.php?action=emergency` | Notfall-Override alle Screens |

---

## 🎨 Shortcodes

### Content-Shortcodes (Menü & Preislisten)

| Shortcode | Beschreibung | Format |
|-----------|-------------|--------|
| `[wcr_getraenke]` | Getränkekarte | 16:9 |
| `[wcr_softdrinks]` | Softdrinks | 16:9 |
| `[wcr_essen]` | Speisekarte | 16:9 |
| `[wcr_kaffee]` | Kaffeekarte | 16:9 |
| `[wcr_eis]` | Eiskarte Standard | 16:9 |
| `[wcr_eis_highlight]` ✨ | Eis Top 3 – Foto groß, Preis fett | 16:9 Highlight |
| `[wcr_eis_portrait]` ✨ | Eis volle Liste mit Stimmungsbild | 9:16 Portrait |
| `[wcr_cable]` | Cable-Park-Preise | 16:9 |
| `[wcr_camping]` | Camping-Preise | 16:9 |

### Display-Shortcodes (Live-Daten)

| Shortcode | Beschreibung |
|-----------|-------------|
| `[wcr_windmap]` | Windkarte (Leaflet + Open-Meteo) |
| `[wcr_wetter]` | Wetter-Widget (7-Tage-Forecast) |

### Playlist/Kurs-Shortcodes ✨ NEU

| Shortcode | Beschreibung |
|-----------|-------------|
| `[wcr_kurs_welcome]` | Kurs-Begrüßungsseite (dynamisch, zieht Kursname aus DB) |
| `[wcr_kursplan]` | Kursplan-Anzeige |
| `[wcr_kurs_regeln]` | Kurs-Regeln / Sicherheitshinweise |

### Widget-Shortcodes

| Shortcode | Beschreibung |
|-----------|-------------|
| `[wcr_starter_pack]` | Starter-Pack-Animation (GSAP) |

---

## 🎛️ Control-Dashboard (GitHub Pages)

Das Dashboard läuft unter `https://homez-bln.github.io/wcr-digital-signage/control/` und kommuniziert per Fetch-API gegen `be/api/pisignage.php`.

### TAB 1 — Stream Deck
Playlist-Buttons im MIDI-Controller-Stil (ähnlich Elgato Stream Deck XL). Jeder Button zeigt ob eine Playlist aktiv ist und auf wie vielen Screens sie läuft. Breite Szenario-Buttons triggern mehrere Playlisten gleichzeitig (z.B. „SA MORGEN MODUS", „VERLEIH TRENNEN"). Live-Update alle 5 Sekunden.

### TAB 2 — Monitor Matrix
Alle 7 Displays als Kacheln. Eingang-Display als schmale Portrait-Kachel dargestellt. Zeigt live welche Playlist auf welchem Screen läuft. Drag & Drop: Playlist-Badge auf Display-Kachel ziehen um zuzuweisen. Online/Offline-Status per Farbindikator.

### TAB 3 — Presets & Zeitplan
Preset-Liste links, Editor rechts. Wochentag-Auswahl, Zeitfenster, Display-spezifische Prioritäten. Inline Timeline-Vorschau mit Konflikt-Checker: zeigt welches Preset bei Überschneidung gewinnt und schlägt automatisch die nächsthöhere Priorität vor.

### TAB 4 — Content Editor
Playlist-Liste links, Slide-Editor rechts. Reihenfolge per Drag & Drop. Slide-Typen: WordPress-URL oder Bild-Upload. Dauer pro Slide einstellbar. Zeigt in welchen Presets eine Playlist verwendet wird.

---

## 🚀 Deployment

### Automatisches Deployment via GitHub Actions

**Trigger:**
- Push auf `main` Branch
- Manueller Workflow-Trigger

**Deploy-Ziele:**
1. **IONOS WebSpace** (SFTP) – Plugin + Backend
2. **GitHub Pages** (`gh-pages` Branch) – Control-Dashboard (`docs/control/`)

**Konfiguration:** `.github/workflows/deploy.yml`

**Secrets:** `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`

---

## 🛠️ Development-Workflow

### Coding-Standards

**Plugin (`wcr-digital-signage/`):**
- ✅ Nur Read-Only REST-APIs (`wakecamp/v1`)
- ✅ Shortcodes für Frontend-Ausgabe
- ✅ CSS/JS via `wp_enqueue_style`/`wp_enqueue_script`
- ❌ Keine schreibenden Operationen
- ❌ Kein Session-Management

**Backend (`be/`):**
- ✅ Session-basiertes Login (`require_login()`)
- ✅ Berechtigungsprüfung (`wcr_require()`)
- ✅ CSRF-Protection (`wcr_verify_csrf()`) auf **allen** Write-APIs inkl. PiSignage-Proxy
- ✅ Write-REST-APIs (POST/PUT/DELETE)
- ❌ Keine Frontend-Ausgabe
- ❌ Keine Shortcodes

**Dashboard (`docs/control/`):**
- ✅ Vanilla JS + CSS Grid (kein Framework)
- ✅ Kommuniziert ausschließlich über `be/api/pisignage.php`
- ✅ Auth per WordPress-Nonce oder Basic Auth Header
- ❌ Kein direkter DB-Zugriff

### Neues Feature entwickeln

```bash
git checkout -b feature/neue-funktion
# Code schreiben
git add .
git commit -m "✨ Feature: Neue Funktion"
git push origin feature/neue-funktion
# Pull Request → Review → Merge → Automatisches Deployment
```

---

## 📚 Vollständige Dokumentation

👉 **[ARCHITECTURE.md](ARCHITECTURE.md)** – Vollständige technische Dokumentation inkl. QA-Checkliste

---

## 📧 Support

**Entwickler:** Marcus Kempe
**E-Mail:** marcus.kempe88@gmail.com
**Repository:** [github.com/homez-bln/wcr-digital-signage](https://github.com/homez-bln/wcr-digital-signage)

---

## 📄 Lizenz

**Proprietary** – Alle Rechte vorbehalten.
Dieses Projekt ist für den internen Einsatz in der WCR-Freizeiteinrichtung entwickelt.

---

**Stand:** März 2026
**Version:** 3.0
