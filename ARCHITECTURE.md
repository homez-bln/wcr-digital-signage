# 📚 TECHNISCHE ARCHITEKTUR-DOKUMENTATION
## WCR Digital Signage System

**Version:** 2.0 (März 2026)  
**Status:** Produktiv im Einsatz  
**Zweck:** Vollständige technische Dokumentation für Entwickler

---

## 📖 INHALTSVERZEICHNIS

1. [Projektüberblick](#projektüberblick)
2. [Architektur-Prinzipien](#architektur-prinzipien)
3. [Verzeichnisstruktur](#verzeichnisstruktur)
4. [Sicherheitskonzept](#sicherheitskonzept)
5. [REST-API Konzept](#rest-api-konzept)
6. [Shortcode-System](#shortcode-system)
7. [CSS-Trennung](#css-trennung)
8. [Deployment](#deployment)
9. [Deprecated/Archiv](#deprecatedarchiv)
10. [Entwicklerregeln](#entwicklerregeln)

---

## 📋 PROJEKTÜBERBLICK

### Zielsetzung

Das **WCR Digital Signage System** ist eine spezialisierte Lösung zur Verwaltung und Anzeige von Content (Menükarten, Preislisten, Wetterdaten, Kino-Programm, etc.) auf Digital Signage Displays in einer Freizeiteinrichtung.

### Kern-Anforderungen

- ✅ **Frontend-Ausgabe:** Öffentliche, performante Darstellung via WordPress-Plugin
- ✅ **Backend-Verwaltung:** Separates, geschütztes Admin-Interface für Content-Management
- ✅ **Sicherheit:** Strikte Trennung zwischen öffentlichen und geschützten Bereichen
- ✅ **Performance:** Keine WordPress-Admin-Last, direkter Datenbankzugriff
- ✅ **Wartbarkeit:** Klare Architektur, saubere Code-Organisation

### Technologie-Stack

- **Frontend:** WordPress Plugin + Elementor + Custom Shortcodes
- **Backend:** Standalone PHP-Applikation (kein WordPress)
- **Datenbank:** MySQL/MariaDB (direkte PDO-Verbindung)
- **Deployment:** GitHub Actions → IONOS SFTP
- **Server:** IONOS WebSpace (Shared Hosting)

---

## 🏗️ ARCHITEKTUR-PRINZIPIEN

### 🔴 KRITISCHE REGEL: ZWEI GETRENNTE SYSTEME

Dieses Projekt verwendet **BEWUSST zwei separate, unabhängige Systeme**:

> **⚠️ HINWEIS:** Das folgende Diagramm ist eine **konzeptionelle Übersicht** zur Verdeutlichung der System-Architektur. Die tatsächliche Dateistruktur ist detaillierter – siehe Abschnitt "Verzeichnisstruktur" für exakte Pfade.

```
┌─────────────────────────────────────┐
│   WordPress Installation            │
│   /WebSpace/wordpress/              │
│                                     │
│   ┌───────────────────────────┐   │
│   │  wcr-digital-signage/     │   │  ← WordPress-Plugin
│   │  (PUBLIC FRONTEND)        │   │     Öffentliche Anzeige
│   └───────────────────────────┘   │     Read-Only REST-API
└─────────────────────────────────────┘
                 ↓ (DB-Zugriff)
         ┌───────────────────┐
         │   MySQL Database  │
         │   be_*-Tabellen   │
         └───────────────────┘
                 ↑ (DB-Zugriff)
┌─────────────────────────────────────┐
│   Standalone PHP App                │
│   /WebSpace/be/                     │
│                                     │
│   be/ (PROTECTED BACKEND)           │  ← Separates Backend
│   - Session-basiertes Login         │     Content-Verwaltung
│   - Schreibende REST-APIs           │     Admin-Interface
│   - Admin-Interface                 │     Benutzer-Management
└─────────────────────────────────────┘
```

### Warum zwei Systeme?

| Anforderung | Plugin allein | Backend allein | **Zwei Systeme (aktuell)** |
|-------------|---------------|----------------|----------------------------|
| Öffentliche Anzeige | ✅ Ja | ❌ Nein | ✅ Plugin macht Anzeige |
| Geschütztes Admin | ⚠️ WordPress-Admin (langsam, überladen) | ✅ Ja | ✅ Backend macht Admin |
| Performance | ⚠️ WordPress-Overhead | ✅ Direkte DB | ✅ Plugin: schnell, Backend: schnell |
| Sicherheit | ⚠️ WordPress-Rechte komplex | ✅ Eigenes System | ✅ Backend: volle Kontrolle |
| Wartbarkeit | ⚠️ Plugin + WordPress vermischt | ✅ Klar | ✅ Klare Trennung |

**Ergebnis:** ✅ **Zwei Systeme = Best of Both Worlds**

---

## 📁 VERZEICHNISSTRUKTUR

### Reale Dateistruktur (Stand: März 2026)

> **📌 WICHTIG:** Dies ist die **tatsächliche Dateistruktur** im Repository. Alle Pfade entsprechen den echten Dateien.

```
/WebSpace/
├── wordpress/
│   └── wp-content/
│       └── plugins/
│           └── wcr-digital-signage/          ← WordPress-Plugin (PUBLIC)
│               ├── wcr-digital-signage.php   # Haupt-Plugin-Datei
│               │
│               ├── includes/                  # Plugin-Logik
│               │   ├── db.php                # DB-Verbindung (WordPress wpdb)
│               │   ├── rest-api.php          # REST-API (READ-ONLY, Namespace: wakecamp/v1)
│               │   ├── rest-screenshot.php   # ⚠️ Screenshot-API (WRITE, CSRF-geschützt, Sonderfall)
│               │   │
│               │   ├── shortcodes.php        # Zentrale Shortcode-Registrierung
│               │   ├── shortcodes-content.php    # Content-Shortcodes (Menü & Preislisten)
│               │   ├── shortcodes-display.php    # Display-Shortcodes (Live-Daten & Wetter)
│               │   ├── shortcodes-widgets.php    # Widget-Shortcodes (Animationen)
│               │   ├── shortcode-kino.php        # ⚠️ LEGACY (alt, vor Refactoring)
│               │   ├── shortcode-produkte.php    # ⚠️ LEGACY (alt, vor Refactoring)
│               │   │
│               │   ├── instagram.php         # Instagram-API-Klasse (WCR_Instagram)
│               │   ├── screenshot.php        # Screenshot-Generator
│               │   └── enqueue.php           # CSS/JS Assets Enqueue
│               │
│               ├── assets/                   # Frontend-Assets
│               │   ├── css/                  # Plugin-CSS (MODULAR, keine einzelne "plugin-styles.css"!)
│               │   │   ├── wcr-ds-global.css       # Globale Styles
│               │   │   ├── wcr-ds-components.css   # Komponenten
│               │   │   ├── wcr-ds-landscape.css    # Landscape-Layout
│               │   │   ├── wcr-ds-portrait.css     # Portrait-Layout
│               │   │   ├── wcr-ds-unified.css      # Unified Design System
│               │   │   ├── wcr-ds-theme-glass.css  # Theme: Glass
│               │   │   ├── wcr-produkte.css        # Produkte-Styles
│               │   │   ├── wcr-kino-slider.css     # Kino-Slider
│               │   │   ├── wcr-instagram.css       # Instagram-Widget
│               │   │   ├── wcr-instagram-video.css # Instagram-Video
│               │   │   ├── wcr-obstacles-map.css   # Obstacles-Karte
│               │   │   └── themes/                 # Theme-Unterordner
│               │   │
│               │   └── js/                   # Plugin-JavaScript
│               │       ├── wcr-frontend.js   # Hauptlogik
│               │       ├── wcr-wetter.js     # Wetter-Widget
│               │       └── ...
│               │
│               ├── CHANGELOG.md              # Änderungsprotokoll
│               └── README-CI-SYSTEM.md       # CI/CD Dokumentation
│
└── be/                                        ← Standalone Backend (PROTECTED)
    ├── index.php                             # Backend-Dashboard (Session-geschützt)
    ├── login.php                             # Login-Seite
    ├── logout.php                            # Logout-Handler
    ├── update_ticket.php                     # ⚠️ DEPRECATED (Legacy-Ticket-Update)
    │
    ├── inc/                                  # Backend-Includes
    │   ├── auth.php                          # Session + Rollen + CSRF
    │   ├── db.php                            # DB-Verbindung (PDO)
    │   ├── menu.php                          # Navigation (lädt style.css)
    │   ├── style.css                         # ✅ BACKEND-CSS (HIER! Nicht in be/css/!)
    │   ├── debug.php                         # Debug-Panel
    │   └── 403.php                           # 403-Fehlerseite
    │
    ├── ctrl/                                 # Controller (Seiten-Logik)
    │   ├── drinks.php                        # Getränke-Verwaltung
    │   ├── food.php                          # Speisen-Verwaltung
    │   ├── times.php                         # Öffnungszeiten-Verwaltung
    │   ├── kino.php                          # Kino-Verwaltung
    │   ├── obstacles.php                     # Obstacles-Verwaltung
    │   ├── media.php                         # Media-Verwaltung
    │   ├── ds-seiten.php                     # DS-Seiten-Vorschau
    │   ├── ds-settings.php                   # DS-Settings (Theme, Farben)
    │   ├── users.php                         # Benutzer-Verwaltung
    │   ├── list.php                          # Generischer Listen-Controller
    │   └── debug.php                         # Debug-Panel (nur cernal)
    │
    ├── api/                                  # Backend-REST-APIs (WRITE)
    │   ├── drinks.php                        # Getränke-API (POST/PUT/DELETE)
    │   ├── food.php                          # Speisen-API
    │   ├── kino.php                          # Kino-API
    │   ├── obstacles.php                     # Obstacles-API
    │   ├── tickets.php                       # Ticket-API
    │   └── users.php                         # Benutzer-API
    │
    ├── js/                                   # Backend-JavaScript
    │   └── (verschiedene JS-Dateien)
    │
    ├── img/                                  # Backend-Assets (Icons, etc.)
    │
    └── _deprecated/                          # 🗑️ Archiv alter Dateien
        └── README.md                         # Dokumentation: Was ist deprecated
```

### System-Zuordnung

| Dateipfad | System | Zweck | Zugriff |
|-----------|--------|-------|--------|
| `wcr-digital-signage/` | Plugin | Frontend-Ausgabe | ✅ Öffentlich |
| `wcr-digital-signage/includes/rest-api.php` | Plugin | Read-Only REST-API | ✅ Öffentlich |
| `wcr-digital-signage/includes/rest-screenshot.php` | Plugin | Screenshot-API | ⚠️ CSRF-geschützt (Sonderfall) |
| `wcr-digital-signage/includes/instagram.php` | Plugin | Instagram-Klasse | ✅ Öffentlich (Read-Only) |
| `wcr-digital-signage/includes/shortcode-*.php` | Plugin | Legacy-Shortcodes | ⚠️ ALT (vor Refactoring) |
| `wcr-digital-signage/includes/shortcodes-*.php` | Plugin | Neue Shortcodes | ✅ AKTUELL (nach Refactoring) |
| `be/` | Backend | Admin-Interface | 🔒 Session-geschützt |
| `be/api/` | Backend | Write-REST-APIs | 🔒 Session + CSRF |
| `be/inc/auth.php` | Backend | Session + Rollen | 🔒 Backend-intern |
| `be/inc/style.css` | Backend | Backend-CSS | 🔒 Backend-Styles |

---

## 🔒 SICHERHEITSKONZEPT

### Sicherheits-Philosophie

**Plugin (wcr-digital-signage/):**
- ✅ **Read-Only** – Keine schreibenden Operationen (außer Screenshot-Sonderfall)
- ✅ **Öffentlich zugänglich** – Jeder kann Daten lesen (Menükarten, Wetter, etc.)
- ⚠️ **Sonderfall:** Screenshot-API mit CSRF-Protection (technischer Workaround)

**Backend (be/):**
- 🔒 **Session-geschützt** – Zugriff nur nach Login
- 🔒 **Rollen-basiert** – Berechtigungen nach User-Rolle
- 🔒 **CSRF-geschützt** – Alle schreibenden Operationen mit Token
- 🔒 **Fingerprinting** – Session gebunden an User-Agent + IP-Präfix

### Rollen-System

**Drei Rollen:**

| Rolle | Beschreibung | Berechtigung | Anzahl User |
|-------|--------------|--------------|-------------|
| **cernal** | Superadmin | Alle Rechte + Debug-Panel | 1 (Owner) |
| **admin** | Administrator | Content-Verwaltung + User-Management | 1-3 (Team-Leads) |
| **user** | Standard-Nutzer | Toggle-Funktionen (An/Aus) | Beliebig viele |

### Berechtigungsmatrix

| Berechtigung | cernal | admin | user | Beschreibung |
|--------------|:------:|:-----:|:----:|-------------|
| `edit_prices` | ✅ | ✅ | ❌ | Preise ändern (Drinks, Food, Cable, Camping, Ice) |
| `edit_products` | ✅ | ✅ | ❌ | Produkte verwalten (Drinks, Food, Cable, etc.) |
| `edit_content` | ✅ | ✅ | ❌ | Content verwalten (Kino, Obstacles, etc.) |
| `edit_tickets` | ✅ | ✅ | ❌ | Tickets bearbeiten |
| `view_times` | ✅ | ✅ | ❌ | Öffnungszeiten-Seite anzeigen |
| `view_media` | ✅ | ✅ | ❌ | Media-Verwaltung anzeigen |
| `view_ds` | ✅ | ✅ | ❌ | Digital Signage Seiten-Vorschau |
| `manage_users` | ✅ | ✅ | ❌ | Benutzer anlegen/verwalten |
| `debug` | ✅ | ❌ | ❌ | Debug-Panel (nur Owner) |
| `toggle` | ✅ | ✅ | ✅ | An/Aus-Schalter bedienen |

**Code-Verwendung:**

```php
// Berechtigung prüfen
if (wcr_can('edit_prices')) {
    // User darf Preise ändern
}

// Berechtigung erzwingen (403 bei fehlender Berechtigung)
wcr_require('edit_products');
```

### Session-Sicherheit (Hardened Security v10)

**Implementiert in:** `be/inc/auth.php`

#### Session-Features:

1. **Secure Cookie Flags**
   - `secure: true` → Nur HTTPS (Auto-Detection)
   - `httponly: true` → Kein JavaScript-Zugriff (XSS-Schutz)
   - `samesite: Strict` → Maximaler CSRF-Schutz
   - `path: /be` → Cookie nur für Backend gültig

2. **Session-Fingerprinting**
   - Session gebunden an: `hash(User-Agent + IP-Präfix)`
   - **Warum IP-Präfix statt volle IP?** Mobile Nutzer wechseln IPs (WiFi → 4G)
   - Bei abweichendem Fingerprint → Session beenden (Session-Hijacking-Schutz)

3. **Session-Timeout**
   - **8 Stunden Inaktivität** → Automatischer Logout
   - `last_seen`-Timestamp wird bei jeder Aktion aktualisiert

4. **Strict Session Mode**
   - Nur Server-generierte Session-IDs akzeptiert
   - Session-ID niemals in URL (verhindert Session-Fixation)
   - `use_only_cookies: 1` → Session-ID nur via Cookie

5. **Session-Regeneration**
   - Bei Login: Neue Session-ID generiert (alte ungültig)
   - Verhindert Session-Fixation-Angriffe

**Code-Beispiel:**

```php
// Session-geschützte Seite
require_once __DIR__ . '/inc/auth.php';
require_login(); // 👈 Erzwingt Login, sonst Redirect zu /be/login.php

// Rolle prüfen
echo "Eingeloggt als: " . wcr_role_badge();

// Berechtigung prüfen
if (wcr_can('edit_prices')) {
    // Preise dürfen geändert werden
}
```

### CSRF-Protection (Token-Rotation)

**Implementiert in:** `be/inc/auth.php`

#### CSRF-Features:

1. **Token-Generierung**
   - 256-Bit-Token (`bin2hex(random_bytes(32))`)
   - Gespeichert in `$_SESSION['wcr_csrf_token']`

2. **Token-Rotation**
   - Nach erfolgreicher Validierung: **neues Token generiert**
   - Verhindert Token-Replay-Angriffe (altes Token funktioniert nur 1x)

3. **Validierung**
   - `hash_equals()` → Verhindert Timing-Angriffe (constant-time comparison)
   - Token aus `$_POST['csrf_token']` oder `HTTP_X_CSRF_TOKEN`-Header

4. **Helper-Funktionen**

```php
// HTML-Formular: Hidden Input
echo wcr_csrf_field();
// Ausgabe: <input type="hidden" name="csrf_token" value="abc123...">

// JavaScript: Data-Attribut
echo '<button data-csrf="' . wcr_csrf_attr() . '">Speichern</button>';

// Validierung (auto-fail: 403 + exit bei Fehler)
wcr_verify_csrf();

// Validierung (silent: return false bei Fehler)
if (!wcr_verify_csrf_silent()) {
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}
```

**Alle schreibenden Backend-APIs verwenden CSRF-Protection:**

```php
// be/api/drinks.php
require_once __DIR__ . '/../inc/auth.php';
require_login();
wcr_verify_csrf(); // 👈 Erzwingt gültiges CSRF-Token

// POST/PUT/DELETE-Logik...
```

### Plugin-Sonderfall: Screenshot-API

**Datei:** `wcr-digital-signage/includes/rest-screenshot.php`

**Problem:** Screenshot-Funktion muss aus Plugin schreiben (Elementor-Pages erfassen)

**Lösung:** ⚠️ **CSRF-geschützte REST-Route im Plugin**

```php
// REST-Route registriert im Plugin (Namespace: wakecamp/v1)
register_rest_route('wakecamp/v1', '/screenshot/capture', [
    'methods'  => 'POST',
    'callback' => 'wcr_capture_screenshot',
    'permission_callback' => function() {
        // CSRF-Token aus be/inc/auth.php prüfen
        return wcr_verify_csrf_from_plugin();
    }
]);
```

**Status:**
- ✅ **Aktuell:** Funktioniert, ist CSRF-geschützt
- ⚠️ **Langfristig:** Sollte ins Backend migriert werden (Screenshot aus be/ aufrufen)
- 📝 **TODO:** Screenshot-Logik vollständig ins Backend verschieben

---

## 🌐 REST-API KONZEPT

### API-Architektur

**Zwei getrennte REST-API-Bereiche:**

| API-Bereich | Datei | Zweck | Zugriff | HTTP-Methoden |
|-------------|-------|-------|---------|---------------|
| **Plugin REST-API** | `wcr-digital-signage/includes/rest-api.php` | Read-Only Daten für Frontend | ✅ Öffentlich | `GET` |
| **Backend REST-API** | `be/api/*.php` | Write-Operationen für Admin | 🔒 Session + CSRF | `POST`, `PUT`, `DELETE` |

### Plugin REST-API (Read-Only)

**Namespace:** `wakecamp/v1` *(WICHTIG: Nicht `wcr/v1`!)*

**Öffentliche Read-Only-Routen:**

| Route | Zweck | Beispiel |
|-------|-------|----------|
| `GET /wakecamp/v1/drinks` | Getränke-Liste | Menükarte-Shortcode |
| `GET /wakecamp/v1/drinks/{typ}` | Getränke nach Typ | Softdrinks, Bier, etc. |
| `GET /wakecamp/v1/food` | Speisen-Liste | Speisekarte-Shortcode |
| `GET /wakecamp/v1/food/{typ}` | Speisen nach Typ | Burger, Pizza, etc. |
| `GET /wakecamp/v1/food/gruppen` | Food-Gruppen Status | Aktiv/Inaktiv-Status |
| `GET /wakecamp/v1/ice` | Eis-Liste | Eiskarte-Shortcode |
| `GET /wakecamp/v1/ice/{typ}` | Eis nach Typ | Kugeln, Becher, etc. |
| `GET /wakecamp/v1/cable` | Cable-Park-Preise | Cable-Preisliste |
| `GET /wakecamp/v1/cable/{typ}` | Cable nach Typ | Tickets, Kurse, etc. |
| `GET /wakecamp/v1/camping` | Camping-Preise | Camping-Preisliste |
| `GET /wakecamp/v1/camping/{typ}` | Camping nach Typ | Stellplätze, etc. |
| `GET /wakecamp/v1/events` | Events-Liste | Event-Widget |
| `GET /wakecamp/v1/extra` | Merch-Produkte | Merchandise-Shortcode |
| `GET /wakecamp/v1/kino` | Kino-Programm | Kino-Shortcode |
| `GET /wakecamp/v1/obstacles` | Obstacles-Daten | Obstacles-Map-Shortcode |
| `GET /wakecamp/v1/obstacles/map-config` | Karten-Config | Map-Rendering (GET = öffentlich) |
| `GET /wakecamp/v1/item/{id}` | Single Item by ID | QR-Code/Deeplink |
| `GET /wakecamp/v1/instagram` | Instagram-Feed | Instagram-Widget |
| `GET /wakecamp/v1/instagram/videos` | Instagram-Videos | Video-Pool für Rotation |
| `GET /wakecamp/v1/ping` | Health-Check | Monitoring |

**Besonderheiten:**
- ✅ **Keine Authentifizierung** – Öffentlich zugänglich
- ✅ **Keine CSRF-Prüfung** – Nur lesende Zugriffe
- ✅ **Stock-Filter** – Nur vorrätige Items (`stock != 0`)
- ✅ **JSON-Response** – Standard REST-API-Format

**Code-Beispiel:**

```php
// wcr-digital-signage/includes/rest-api.php
register_rest_route('wakecamp/v1', '/drinks', [
    'methods' => 'GET',
    'callback' => function() {
        $db = get_ionos_db_connection();
        return rest_ensure_response(
            $db->get_results("SELECT * FROM drinks WHERE stock != 0 ORDER BY typ, produkt", ARRAY_A) ?: []
        );
    },
    'permission_callback' => '__return_true' // 👈 Öffentlich zugänglich
]);
```

### Backend REST-API (Write)

**Basis-URL:** `https://domain.de/be/api/`

**Geschützte Write-APIs:**

| Datei | HTTP-Methoden | Zweck |
|-------|---------------|-------|
| `be/api/drinks.php` | `POST`, `PUT`, `DELETE` | Getränke anlegen/bearbeiten/löschen |
| `be/api/food.php` | `POST`, `PUT`, `DELETE` | Speisen anlegen/bearbeiten/löschen |
| `be/api/kino.php` | `POST`, `PUT`, `DELETE` | Kino-Programm anlegen/bearbeiten/löschen |
| `be/api/obstacles.php` | `POST`, `PUT`, `DELETE` | Obstacles anlegen/bearbeiten/löschen |
| `be/api/tickets.php` | `POST`, `PUT`, `DELETE` | Tickets anlegen/bearbeiten/löschen |
| `be/api/users.php` | `POST`, `PUT`, `DELETE` | Benutzer anlegen/bearbeiten/löschen |

**Sicherheits-Requirements:**
- 🔒 **Session-Prüfung:** `require_login()`
- 🔒 **Berechtigungsprüfung:** `wcr_require('edit_products')`
- 🔒 **CSRF-Protection:** `wcr_verify_csrf()`

**Code-Beispiel:**

```php
// be/api/drinks.php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

// 1️⃣ Session-Prüfung
require_login();

// 2️⃣ Berechtigungsprüfung
wcr_require('edit_products');

// 3️⃣ CSRF-Protection
wcr_verify_csrf();

// 4️⃣ Request-Method
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Neues Getränk anlegen
    $name = $_POST['name'] ?? '';
    $price = $_POST['price'] ?? 0;
    
    $stmt = $pdo->prepare("INSERT INTO drinks (name, price) VALUES (?, ?)");
    $stmt->execute([$name, $price]);
    
    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
}
```

### Sonderfälle & Migration-Kandidaten

**Screenshot-API (Sonderfall):**

| Was | Wo | Status | Warum Sonderfall | Langfristig |
|-----|----|----|------------------|-------------|
| Screenshot-Capture | `wcr-digital-signage/includes/rest-screenshot.php` | ⚠️ Funktioniert, aber in Plugin | Muss Elementor-Pages erfassen (braucht WordPress-Kontext) | 📝 TODO: Migration ins Backend (Screenshot extern aufrufen) |

**Obstacles Map-Config POST (Legacy-Brücke):**

| Was | Wo | Status | Warum hier | Langfristig |
|-----|----|----|------------|-------------|
| Map-Config POST | `wcr-digital-signage/includes/rest-api.php` → `POST /wakecamp/v1/obstacles/map-config` | ⚠️ Funktioniert, Secret-geschützt | Backend nutzt diese Route noch (be/ctrl/obstacles.php) | 📝 TODO: Direkt WordPress-Options im Backend nutzen |

**DS-Settings (Legacy-Brücke):**

| Was | Wo | Status | Warum hier | Langfristig |
|-----|----|----|------------|-------------|
| DS-Settings GET/POST | `wcr-digital-signage/includes/rest-api.php` → `GET/POST /wakecamp/v1/ds-settings` | ⚠️ Funktioniert, Admin-geschützt | Backend nutzt diese Route noch (be/ctrl/ds-settings.php) | 📝 TODO: Komplett ins Backend (be/) verlagern |

**Instagram-API (Korrekt im Plugin):**

| Was | Wo | Status | Warum hier | Langfristig |
|-----|----|----|------------|-------------|
| Instagram-Feed | `wcr-digital-signage/includes/instagram.php` + `rest-api.php` → `GET /wakecamp/v1/instagram` | ✅ Korrekt | Read-Only, braucht WordPress für Caching | ✅ Bleibt im Plugin |

---

## 📝 SHORTCODE-SYSTEM

### Shortcode-Architektur

**Zentrale Registrierung:**
- Datei: `wcr-digital-signage/includes/shortcodes.php`
- Alle Shortcodes werden hier via `add_shortcode()` registriert

**Thematische Aufteilung (Funktionen):**

```
wcr-digital-signage/includes/
├── shortcodes.php              # ← Zentrale Registrierung (add_shortcode)
├── shortcodes-content.php      # ← Menü & Preislisten (7 Funktionen)
├── shortcodes-display.php      # ← Live-Daten & Wetter (2 Funktionen)
├── shortcodes-widgets.php      # ← Animationen & Widgets (1 Funktion)
│
├── shortcode-kino.php          # ⚠️ LEGACY (vor Refactoring)
└── shortcode-produkte.php      # ⚠️ LEGACY (vor Refactoring)
```

### Shortcode-Kategorien

#### 1️⃣ **Content-Shortcodes** (`shortcodes-content.php`)

**Fokus:** Statische Content-Daten aus Datenbank (Menükarten, Preislisten)

| Shortcode | Funktion | REST-API | Renderer | Zweck |
|-----------|----------|----------|----------|-------|
| `[wcr_getraenke]` | `wcr_sc_getraenke()` | `GET /wakecamp/v1/drinks` | `WCR.renderDrinksList()` | Getränkekarte |
| `[wcr_softdrinks]` | `wcr_sc_softdrinks()` | `GET /wakecamp/v1/drinks/softdrinks` | `WCR.renderDrinksList()` | Softdrinks-Karte |
| `[wcr_essen]` | `wcr_sc_essen()` | `GET /wakecamp/v1/food` | `WCR.renderDrinksList()` | Speisekarte |
| `[wcr_kaffee]` | `wcr_sc_kaffee()` | `GET /wakecamp/v1/coffee` | Spezialisiertes JS | Kaffeekarte |
| `[wcr_eis]` | `wcr_sc_eis()` | `GET /wakecamp/v1/ice` | `WCR.renderDrinksList()` | Eiskarte |
| `[wcr_cable]` | `wcr_sc_cable()` | `GET /wakecamp/v1/cable` | `WCR.renderDrinksList()` | Cable-Park-Preise |
| `[wcr_camping]` | `wcr_sc_camping()` | `GET /wakecamp/v1/camping` | `WCR.renderDrinksList()` | Camping-Preise |

**Gemeinsames Merkmal:**
- ✅ Nutzen REST-API: `GET /wakecamp/v1/{drinks|food|ice|cable|camping}` (Namespace: wakecamp/v1)
- ✅ Gleicher Renderer-Mechanismus (außer Kaffee)
- ✅ Keine Live-APIs, keine Auto-Refresh

#### 2️⃣ **Display-Shortcodes** (`shortcodes-display.php`)

**Fokus:** Live-Daten von externen APIs (Wetter, Windkarte)

| Shortcode | Funktion | API | Zweck |
|-----------|----------|-----|-------|
| `[wcr_windmap]` | `wcr_sc_windmap()` | Open-Meteo API + Leaflet | Windkarte mit Canvas-Visualisierung |
| `[wcr_wetter]` | `wcr_sc_wetter()` | Open-Meteo API | Wetter-Widget mit 7-Tage-Forecast |

**Gemeinsames Merkmal:**
- ✅ Externe Live-APIs (Open-Meteo)
- ✅ Große UI-Komponenten (Karten, Charts)
- ✅ Auto-Refresh (alle 10-15 Min)
- ✅ Komplexe Visualisierungen (Canvas, SVG, Leaflet)

#### 3️⃣ **Widget-Shortcodes** (`shortcodes-widgets.php`)

**Fokus:** Animationen, Spezial-Effekte, interaktive Widgets

| Shortcode | Funktion | REST-API | Bibliothek | Zweck |
|-----------|----------|----------|------------|-------|
| `[wcr_starter_pack]` | `wcr_sc_starter_pack()` | `GET /wakecamp/v1/starter_pack_items` | GSAP | Starter-Pack-Animation mit Timeline |

**Status:** ⚠️ **Aktuell nur 1 Funktion** – Datei vorbereitet für zukünftige Widget-Shortcodes

**Zukünftige Kandidaten:**
- Produkt-Slider
- Countdown-Timer
- Parallax-Effekte
- Animierte Hero-Sections

#### 4️⃣ **Legacy-Shortcodes** (⚠️ VOR REFACTORING)

**Status:** ⚠️ **VERALTET** – Diese Dateien existieren noch, sollten aber nicht mehr verwendet werden

| Datei | Status | Ersetzt durch | Warum deprecated |
|-------|--------|---------------|------------------|
| `shortcode-kino.php` | ⚠️ LEGACY | `shortcodes-display.php` (oder `shortcodes-content.php`) | Vor thematischer Aufteilung erstellt |
| `shortcode-produkte.php` | ⚠️ LEGACY | `shortcodes-content.php` | Vor thematischer Aufteilung erstellt |

**Hinweis für Entwickler:**
- ✅ **Neue Features:** Nutze `shortcodes-{content|display|widgets}.php`
- ⚠️ **Legacy-Dateien:** Nicht löschen (Kompatibilität), aber nicht erweitern
- 📝 **TODO:** Legacy-Shortcodes migrieren in neue Struktur

### Renderer-Mechanismus

**Gemeinsamer Renderer:** `WCR.renderDrinksList()` (JavaScript)

**Verwendung:**
```javascript
// assets/js/wcr-frontend.js
WCR.renderDrinksList({
    apiEndpoint: '/wp-json/wakecamp/v1/drinks',  // ← Namespace: wakecamp/v1
    container: '#wcr-getraenke-list',
    showPrices: true,
    columns: 2
});
```

**Shortcode-Integration:**
```php
// includes/shortcodes-content.php
function wcr_sc_getraenke($atts) {
    $id = 'wcr-getraenke-' . uniqid();
    ob_start();
    ?>
    <div id="<?= $id ?>" class="wcr-drinks-container"></div>
    <script>
        WCR.renderDrinksList({
            apiEndpoint: '/wp-json/wakecamp/v1/drinks',  // ← Namespace: wakecamp/v1
            container: '#<?= $id ?>'
        });
    </script>
    <?php
    return ob_get_clean();
}
```

---

## 🎨 CSS-TRENNUNG

### Strikte Trennung: Backend vs Plugin

**🔴 KRITISCHE REGEL:** Backend-CSS und Plugin-CSS dürfen **NIEMALS** vermischt werden.

| System | CSS-Datei(en) | Zweck | Geladen in |
|--------|--------------|-------|------------|
| **Backend** | `be/inc/style.css` | Backend-Interface-Styles | `be/inc/menu.php` |
| **Plugin** | `wcr-digital-signage/assets/css/*.css` (11+ Dateien) | Frontend-Shortcode-Styles | WordPress-Frontend (via `wp_enqueue_style`) |

### Backend-CSS

**Datei:** `be/inc/style.css` *(WICHTIG: Nicht `be/css/backend-styles.css`! Verzeichnis `be/css/` existiert nicht.)*

**Wo geladen:**
```php
// be/inc/menu.php
<link rel="stylesheet" href="/be/inc/style.css">
```

**Inhalt:**
- Dashboard-Layout (Grid, Sidebar, Header)
- Tabellen-Styles (Produktlisten, Benutzer-Verwaltung)
- Formulare (Input-Felder, Buttons, Modals)
- Navigation (Menü, Tabs)
- Role-Badges, Status-Badges
- Responsive Design für Backend

### Plugin-CSS (Modulare Struktur)

**Verzeichnis:** `wcr-digital-signage/assets/css/`

**⚠️ WICHTIG:** Es gibt **KEINE einzelne `plugin-styles.css`**! Das Plugin nutzt **11+ modulare CSS-Dateien**:

#### Haupt-CSS-Dateien:

1. **`wcr-ds-global.css`** – Globale Styles (CSS-Variablen, Resets, Typography)
2. **`wcr-ds-components.css`** – Komponenten (Buttons, Cards, Badges)
3. **`wcr-ds-landscape.css`** – Landscape-Layout (für Querformat-Displays)
4. **`wcr-ds-portrait.css`** – Portrait-Layout (für Hochformat-Displays)
5. **`wcr-ds-unified.css`** – Unified Design System
6. **`wcr-ds-theme-glass.css`** – Theme: Glass (Glassmorphism-Effekte)

#### Komponenten-CSS-Dateien:

7. **`wcr-produkte.css`** – Produkte-Listen-Styles
8. **`wcr-kino-slider.css`** – Kino-Programm-Slider
9. **`wcr-instagram.css`** – Instagram-Widget
10. **`wcr-instagram-video.css`** – Instagram-Video-Rotation
11. **`wcr-obstacles-map.css`** – Obstacles-Karte (Leaflet-Overlays)

#### Theme-Unterordner:

- **`themes/`** – Zusätzliche Theme-Varianten

**Laden:**
```php
// wcr-digital-signage/includes/enqueue.php
function wcr_enqueue_assets() {
    // Globale Styles
    wp_enqueue_style('wcr-ds-global', 
        plugins_url('assets/css/wcr-ds-global.css', dirname(__FILE__)), [], '2.0.0');
    
    // Komponenten
    wp_enqueue_style('wcr-ds-components', 
        plugins_url('assets/css/wcr-ds-components.css', dirname(__FILE__)), [], '2.0.0');
    
    // Layout (conditional: landscape oder portrait)
    if (is_landscape_mode()) {
        wp_enqueue_style('wcr-ds-landscape', 
            plugins_url('assets/css/wcr-ds-landscape.css', dirname(__FILE__)), [], '2.0.0');
    } else {
        wp_enqueue_style('wcr-ds-portrait', 
            plugins_url('assets/css/wcr-ds-portrait.css', dirname(__FILE__)), [], '2.0.0');
    }
    
    // Theme
    wp_enqueue_style('wcr-ds-theme-glass', 
        plugins_url('assets/css/wcr-ds-theme-glass.css', dirname(__FILE__)), [], '2.0.0');
    
    // Komponenten (conditional)
    if (is_page_with_produkte()) {
        wp_enqueue_style('wcr-produkte', 
            plugins_url('assets/css/wcr-produkte.css', dirname(__FILE__)), [], '2.0.0');
    }
}
add_action('wp_enqueue_scripts', 'wcr_enqueue_assets');
```

### Warum strikte Trennung?

| Problem bei Vermischung | Lösung durch Trennung |
|-------------------------|------------------------|
| ❌ Backend-Styles auf Frontend-Seiten | ✅ Plugin-CSS nur im WordPress-Frontend |
| ❌ Frontend-Styles im Backend-Interface | ✅ Backend-CSS nur in /be/ |
| ❌ Konflikte zwischen CSS-Klassen | ✅ Separate Namespaces (`.wcr-*` vs `.be-*`) |
| ❌ Unnötige CSS-Last auf Seiten | ✅ Nur relevante Styles geladen |

**Code-Konvention:**
```css
/* Backend-CSS (be/inc/style.css): Präfix .be-* */
.be-dashboard { ... }
.be-table { ... }
.be-form-group { ... }

/* Plugin-CSS (wcr-digital-signage/assets/css/*.css): Präfix .wcr-* */
.wcr-drinks-list { ... }
.wcr-weather-widget { ... }
.wcr-windmap-container { ... }
```

---

## 🚀 DEPLOYMENT

### GitHub Actions Workflow

**Datei:** `.github/workflows/deploy.yml`

**Trigger:**
- ✅ **Push auf `main`-Branch** → Automatischer Deploy
- ✅ **Manueller Trigger** → `workflow_dispatch`

### Deployment-Prozess

```yaml
jobs:
  deploy:
    runs-on: ubuntu-latest
    timeout-minutes: 10  # ← Timeout-Protection
    
    steps:
      # 1️⃣ Code auschecken
      - uses: actions/checkout@v4
      
      # 2️⃣ SFTP-Deploy mit Retry-Logik
      - uses: wlixcc/SFTP-Deploy-Action@v1.2.4
        with:
          server: ${{ secrets.FTP_SERVER }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
          
          # Plugin-Verzeichnis deployen
          local_path: './wcr-digital-signage/*'
          remote_path: '/WebSpace/wordpress/wp-content/plugins/wcr-digital-signage/'
          
          # ✅ Delete-Mode: Alte Dateien nach Refactorings entfernen
          delete_remote_files: true
          
          # ✅ Exclude-Liste: Unnötige Dateien nicht deployen
          args: '--exclude=node_modules/ --exclude=.git/ --exclude=.github/'
```

### Deployment-Verbesserungen (März 2026)

**Problem vorher:**
- ❌ Workflow konnte stundenlang hängen (Action-Download-Timeout)
- ❌ Ein Netzwerkfehler = fehlgeschlagener Deploy
- ⚠️ Alte Dateien blieben nach Refactorings auf Server liegen

**Lösung jetzt:**
- ✅ **Timeout-Protection:** `timeout-minutes: 10` → Workflow bricht nach 10 Min ab
- ✅ **Delete-Mode:** `delete_remote_files: true` → Server = exakte Git-Kopie
- ✅ **Exclude-Liste:** `.git/`, `.github/`, `node_modules/` nicht deployen
- ✅ **Deploy-Bestätigung:** Explizites Logging im Workflow

### Deploy-Hygiene

**Beispiel-Szenario (Refactoring):**

**Vorher (delete_remote_files: false):**
```
Git:    shortcodes-content.php (7 KB, fokussiert)
        shortcodes-display.php (8 KB, neu)
        shortcodes-widgets.php (4 KB, neu)
        
Server: shortcodes-content.php (12 KB, alte gemischte Version) ← BLEIBT LIEGEN!
        shortcodes-display.php (8 KB)
        shortcodes-widgets.php (4 KB)
```

**Nachher (delete_remote_files: true):**
```
Git:    shortcodes-content.php (7 KB, fokussiert)
        shortcodes-display.php (8 KB, neu)
        shortcodes-widgets.php (4 KB, neu)
        
Server: shortcodes-content.php (7 KB) ← ÜBERSCHRIEBEN!
        shortcodes-display.php (8 KB)
        shortcodes-widgets.php (4 KB)
```

**Ergebnis:** ✅ **Server = Git-Zustand** (keine Altlasten)

### Secrets Configuration

**GitHub Repository Secrets:**

| Secret | Zweck | Beispiel |
|--------|-------|----------|
| `FTP_SERVER` | SFTP-Server-Adresse | `access123456789.webspace-data.io` |
| `FTP_USERNAME` | SFTP-Benutzername | `u123456789` |
| `FTP_PASSWORD` | SFTP-Passwort | `***` |

**Setup:** GitHub Repository → Settings → Secrets and variables → Actions

---

## 🗑️ DEPRECATED/ARCHIV

### Archivierung alter Dateien

**Verzeichnis:** `be/_deprecated/`

**Zweck:** Alte, nicht mehr verwendete Dateien werden **NICHT gelöscht**, sondern archiviert.

### Warum Archivierung statt Löschen?

| Vorteil | Beschreibung |
|---------|-------------|
| ✅ **Git-History bleibt sauber** | Keine gelöschten Dateien in Git-Commits |
| ✅ **Referenz für alte Logik** | Bei Fragen: "Wie funktionierte das früher?" |
| ✅ **Einfache Wiederherstellung** | Falls alte Funktionalität doch noch gebraucht wird |
| ✅ **Dokumentation** | README.md erklärt, warum Datei deprecated ist |

### Struktur

```
be/_deprecated/
├── README.md                    # Dokumentation: Was ist deprecated & warum
├── old-api-structure/           # Alte API-Dateien vor Refactoring
│   ├── drinks_old.php
│   └── food_old.php
└── experimental/                # Experimente, die nicht produktiv gingen
    └── websocket-prototype.php
```

### README.md Template

```markdown
# 🗑️ DEPRECATED FILES

## Warum diese Dateien hier sind

Diese Dateien sind **veraltet und nicht mehr in Verwendung**.
Sie werden archiviert statt gelöscht:
- Git-History bleibt sauber
- Referenz für alte Logik
- Dokumentation für zukünftige Entwickler

## Dateien

### old-api-structure/
**Datum:** 2026-03-05  
**Grund:** API wurde von `be/api/drinks_old.php` zu `be/api/drinks.php` refactored.  
**Ersetzt durch:** `be/api/drinks.php` (neue Struktur mit CSRF-Protection)

### experimental/websocket-prototype.php
**Datum:** 2026-02-10  
**Grund:** Websocket-Experiment für Live-Updates. Shared Hosting unterstützt keine Websockets.  
**Ersetzt durch:** Polling-Mechanismus in Frontend (Auto-Refresh alle 10 Min)

## Regel

**Niemals aus _deprecated/ in Produktion verwenden!**
Diese Dateien sind aus gutem Grund deprecated.
```

### Legacy-Dateien im Plugin

**Status:** ⚠️ **Nicht im _deprecated/ Verzeichnis, aber veraltet**

| Datei | Status | Warum veraltet | Ersetzt durch |
|-------|--------|----------------|---------------|
| `wcr-digital-signage/includes/shortcode-kino.php` | ⚠️ LEGACY | Vor thematischer Aufteilung | `shortcodes-display.php` oder `shortcodes-content.php` |
| `wcr-digital-signage/includes/shortcode-produkte.php` | ⚠️ LEGACY | Vor thematischer Aufteilung | `shortcodes-content.php` |

**Warum nicht gelöscht:**
- ✅ Kompatibilität mit bestehenden Elementor-Pages
- ✅ Keine Breaking Changes für Live-System
- 📝 **TODO:** Schrittweise Migration zu neuen Shortcodes

---

## 📋 ENTWICKLERREGELN

### 🔴 KRITISCHE REGELN (Niemals brechen!)

#### 1️⃣ **Plugin vs Backend: Klare Trennung**

```
┌─────────────────────────────────────────┐
│ PLUGIN (wcr-digital-signage/)           │
├─────────────────────────────────────────┤
│ ✅ Frontend-Ausgabe (Shortcodes)        │
│ ✅ Read-Only REST-API (wakecamp/v1)     │
│ ✅ Assets (CSS/JS für Frontend)         │
│ ❌ KEINE schreibenden Operationen       │
│ ❌ KEINE Session-Verwaltung             │
│ ❌ KEIN Admin-Interface                 │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│ BACKEND (be/)                           │
├─────────────────────────────────────────┤
│ ✅ Admin-Interface                      │
│ ✅ Session + Login + Rollen             │
│ ✅ Write-REST-APIs (POST/PUT/DELETE)    │
│ ✅ CSRF-Protection                      │
│ ❌ KEINE Frontend-Ausgabe               │
│ ❌ KEINE Shortcodes                     │
└─────────────────────────────────────────┘
```

**Entscheidungshilfe:**

| Anforderung | Plugin | Backend |
|-------------|:------:|:-------:|
| Daten lesen (öffentlich) | ✅ | ❌ |
| Daten schreiben | ❌ | ✅ |
| Shortcode erstellen | ✅ | ❌ |
| Admin-Seite erstellen | ❌ | ✅ |
| CSS/JS für Frontend | ✅ | ❌ |
| CSS/JS für Backend | ❌ | ✅ |
| Session-Prüfung | ❌ | ✅ |
| CSRF-Token prüfen | ❌ | ✅ |

#### 2️⃣ **CSS-Trennung: Backend ≠ Plugin**

```
❌ FALSCH:
- Backend-CSS in Plugin laden
- Plugin-CSS in Backend laden
- Gemeinsame CSS-Datei für beides

✅ RICHTIG:
- be/inc/style.css → Nur Backend
- wcr-digital-signage/assets/css/*.css → Nur Plugin (11+ modulare Dateien)
- Separate Namespaces: .be-* vs .wcr-*
```

#### 3️⃣ **REST-API: Read vs Write**

```
✅ READ (Plugin):
- GET-Requests
- Namespace: wakecamp/v1 (NICHT wcr/v1!)
- Öffentlich zugänglich
- Keine Authentifizierung
- Keine CSRF-Prüfung

✅ WRITE (Backend):
- POST/PUT/DELETE-Requests
- Session-geschützt (require_login)
- Berechtigungsprüfung (wcr_require)
- CSRF-geschützt (wcr_verify_csrf)
```

#### 4️⃣ **REST-Namespace: IMMER `wakecamp/v1`**

```php
❌ FALSCH:
fetch('/wp-json/wcr/v1/drinks')  // 404 Not Found!

✅ RICHTIG:
fetch('/wp-json/wakecamp/v1/drinks')  // 200 OK
```

#### 5️⃣ **Shortcode-Struktur: Thematische Aufteilung**

```
NEUE SHORTCODES HINZUFÜGEN:

1️⃣ Shortcode-Funktion schreiben in passender Datei:
   - Menü/Preislisten → shortcodes-content.php
   - Live-Daten/APIs → shortcodes-display.php
   - Animationen/Widgets → shortcodes-widgets.php

2️⃣ Shortcode registrieren in shortcodes.php:
   add_shortcode('wcr_new_shortcode', 'wcr_sc_new_shortcode');

3️⃣ REST-API-Call mit korrektem Namespace:
   fetch('/wp-json/wakecamp/v1/new_endpoint')  // ← wakecamp/v1!

4️⃣ Dokumentation updaten:
   - ARCHITECTURE.md → Shortcode-System-Sektion
   - Kommentare in Code
```

#### 6️⃣ **Backend-CSS: Pfad beachten**

```php
❌ FALSCH:
<link rel="stylesheet" href="/be/css/backend-styles.css">  // Verzeichnis existiert nicht!

✅ RICHTIG:
<?php include __DIR__ . '/inc/menu.php'; ?>  // Lädt automatisch /be/inc/style.css
```

#### 7️⃣ **Sicherheits-Checkliste für neue Features**

**Neue Backend-Seite erstellen:**
```php
// be/ctrl/new_feature.php
require_once __DIR__ . '/../inc/auth.php';

// 1️⃣ Login erzwingen
require_login();

// 2️⃣ Berechtigung prüfen
wcr_require('edit_content');

// 3️⃣ CSRF-Token in Formulare
echo wcr_csrf_field();

// 4️⃣ Session-Timeout beachten (8h)
```

**Neue Backend-API erstellen:**
```php
// be/api/new_feature.php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

// 1️⃣ Login erzwingen
require_login();

// 2️⃣ Berechtigung prüfen
wcr_require('edit_content');

// 3️⃣ CSRF-Token prüfen
wcr_verify_csrf();

// 4️⃣ Request-Method prüfen
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// 5️⃣ Input-Validierung
// 6️⃣ Prepared Statements für DB
```

### ⚠️ HÄUFIGE FEHLER VERMEIDEN

#### Fehler 1: Backend-Logik im Plugin

```php
❌ FALSCH (wcr-digital-signage/includes/rest-api.php):
register_rest_route('wakecamp/v1', '/drinks', [
    'methods' => 'POST',  // ← POST im Plugin!
    'callback' => 'wcr_create_drink'
]);

✅ RICHTIG:
- POST/PUT/DELETE gehört ins Backend (be/api/drinks.php)
- Plugin nur GET-Requests
```

#### Fehler 2: Backend-CSS-Pfad falsch

```php
❌ FALSCH (be/index.php):
<link rel="stylesheet" href="/be/css/backend-styles.css">  // Verzeichnis existiert nicht!

✅ RICHTIG:
<?php include __DIR__ . '/inc/menu.php'; ?>  // Lädt /be/inc/style.css
```

#### Fehler 3: REST-Namespace falsch

```javascript
❌ FALSCH:
fetch('/wp-json/wcr/v1/drinks')  // 404 Not Found!

✅ RICHTIG:
fetch('/wp-json/wakecamp/v1/drinks')  // 200 OK
```

#### Fehler 4: Fehlende CSRF-Protection

```php
❌ FALSCH (be/api/drinks.php):
require_login();
// CSRF-Prüfung vergessen!
$name = $_POST['name'];

✅ RICHTIG:
require_login();
wcr_verify_csrf();  // ← CSRF-Token prüfen
$name = $_POST['name'];
```

#### Fehler 5: Shortcode-Funktion falsch platziert

```php
❌ FALSCH:
- Neue Shortcode-Funktion in shortcodes.php schreiben
- Shortcode-Funktion in wcr-digital-signage.php schreiben
- Legacy-Dateien (shortcode-kino.php) erweitern

✅ RICHTIG:
- Shortcode-Funktion in shortcodes-{content|display|widgets}.php schreiben
- Registrierung in shortcodes.php (add_shortcode)
```

### 📝 BEST PRACTICES

#### Neue Produkt-Kategorie hinzufügen (Beispiel: "Snacks")

**Schritt 1: Datenbank-Tabelle**
```sql
CREATE TABLE snacks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nummer INT NOT NULL,
    produkt VARCHAR(255) NOT NULL,
    typ VARCHAR(100),
    menge VARCHAR(50),
    preis DECIMAL(10,2) NOT NULL,
    stock TINYINT(1) DEFAULT 1,
    sortorder INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Schritt 2: Backend-Controller erstellen**
```php
// be/ctrl/snacks.php
require_once __DIR__ . '/../inc/auth.php';
require_login();
wcr_require('edit_products');

// HTML-Formular für Snacks-Verwaltung
```

**Schritt 3: Backend-API erstellen**
```php
// be/api/snacks.php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_login();
wcr_require('edit_products');
wcr_verify_csrf();

// POST/PUT/DELETE-Logik für Snacks
```

**Schritt 4: Plugin REST-API erweitern**
```php
// wcr-digital-signage/includes/rest-api.php
register_rest_route('wakecamp/v1', '/snacks', [  // ← Namespace: wakecamp/v1
    'methods' => 'GET',
    'callback' => function() {
        $db = get_ionos_db_connection();
        return rest_ensure_response(
            $db->get_results("SELECT * FROM snacks WHERE stock != 0 ORDER BY sortorder ASC", ARRAY_A) ?: []
        );
    },
    'permission_callback' => '__return_true'
]);
```

**Schritt 5: Shortcode erstellen**
```php
// wcr-digital-signage/includes/shortcodes-content.php
function wcr_sc_snacks($atts) {
    $id = 'wcr-snacks-' . uniqid();
    ob_start();
    ?>
    <div id="<?= $id ?>" class="wcr-snacks-container"></div>
    <script>
        WCR.renderDrinksList({
            apiEndpoint: '/wp-json/wakecamp/v1/snacks',  // ← Namespace: wakecamp/v1
            container: '#<?= $id ?>',
            showPrices: true
        });
    </script>
    <?php
    return ob_get_clean();
}
```

**Schritt 6: Shortcode registrieren**
```php
// wcr-digital-signage/includes/shortcodes.php
add_shortcode('wcr_snacks', 'wcr_sc_snacks');
```

**Schritt 7: Backend-Navigation erweitern**
```php
// be/inc/menu.php
$_menuItems = [
    // ...
    ['Snacks', 'ctrl/list.php?t=snacks', null],  // NEU
];
```

**Schritt 8: Dokumentation updaten**
- `ARCHITECTURE.md` → REST-API-Sektion + Shortcode-Sektion
- `CHANGELOG.md` → Neue Funktion dokumentieren

---

## 📐 ARCHITEKTUR-ZUSAMMENFASSUNG

### ✅ WICHTIGSTE ARCHITEKTURREGELN (Top 10)

1. **Plugin = Frontend, Backend = Admin** – Niemals vermischen!
2. **REST-Namespace: IMMER `wakecamp/v1`** – Nicht `wcr/v1`!
3. **Backend-CSS: `be/inc/style.css`** – Nicht `be/css/backend-styles.css`!
4. **Plugin-CSS: 11+ modulare Dateien** – Keine einzelne `plugin-styles.css`!
5. **Plugin nur Read-Only REST-APIs** – Keine POST/PUT/DELETE
6. **Backend immer Session + CSRF** – Alle Write-Operationen absichern
7. **Shortcodes thematisch aufteilen** – content, display, widgets
8. **Legacy-Dateien markieren** – shortcode-kino.php, shortcode-produkte.php nicht erweitern
9. **Deprecated nicht löschen** – Archivieren in be/_deprecated/
10. **Deploy-Hygiene** – delete_remote_files: true (Server = Git)

### ❌ WAS NIEMALS VERMISCHT WERDEN DARF

| Was | Wo | Warum |
|-----|----|----|---|
| **Schreibende REST-APIs** | ❌ Plugin | Plugin ist öffentlich, keine Schreibrechte |
| **Session-Management** | ❌ Plugin | Plugin nutzt WordPress, Backend eigene Session |
| **Admin-Interface** | ❌ Plugin | Backend-Aufgabe, nicht Plugin |
| **Frontend-Ausgabe** | ❌ Backend | Plugin-Aufgabe, nicht Backend |
| **Backend-CSS** | ❌ Plugin | Separate Styles: `be/inc/style.css` |
| **Plugin-CSS** | ❌ Backend | Separate Styles: `assets/css/*.css` |
| **REST-Namespace `wcr/v1`** | ❌ Überall | Korrekter Namespace: `wakecamp/v1` |

### 📚 WEITERE DOKUMENTATION

- **Korrekturen-Übersicht:** `KORREKTUREN.md` (Alle Doku-Korrekturen März 2026)
- **CI/CD:** `wcr-digital-signage/README-CI-SYSTEM.md`
- **Changelog:** `wcr-digital-signage/CHANGELOG.md`
- **Deprecated:** `be/_deprecated/README.md`
- **Deployment:** `.github/workflows/deploy.yml`

---

**Stand:** März 2026 (Korrigiert auf echte Dateistruktur)  
**Autor:** Marcus Kempe  
**Repository:** [github.com/homez-bln/wcr-digital-signage](https://github.com/homez-bln/wcr-digital-signage)
