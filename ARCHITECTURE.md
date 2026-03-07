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

### Übersicht

```
/WebSpace/
├── wordpress/
│   └── wp-content/
│       └── plugins/
│           └── wcr-digital-signage/          ← WordPress-Plugin (PUBLIC)
│               ├── wcr-digital-signage.php   # Haupt-Plugin-Datei
│               ├── includes/
│               │   ├── db.php                # DB-Verbindung (WordPress wpdb)
│               │   ├── rest-api.php          # REST-API (READ-ONLY)
│               │   ├── rest-screenshot.php   # ⚠️ Screenshot-API (WRITE, CSRF-geschützt)
│               │   ├── shortcodes.php        # Shortcode-Registrierung
│               │   ├── shortcodes-content.php    # Menü & Preislisten
│               │   ├── shortcodes-display.php    # Live-Daten & Wetter
│               │   ├── shortcodes-widgets.php    # Animationen & Widgets
│               │   ├── enqueue.php           # CSS/JS Assets
│               │   └── instagram.php         # Instagram-Integration
│               ├── assets/
│               │   ├── css/
│               │   │   └── plugin-styles.css # Plugin-Styles (NICHT Backend!)
│               │   └── js/
│               │       └── wcr-frontend.js   # Frontend-JavaScript
│               └── README-CI-SYSTEM.md       # CI/CD Dokumentation
│
└── be/                                        ← Standalone Backend (PROTECTED)
    ├── index.php                             # Backend-Dashboard (Session-geschützt)
    ├── login.php                             # Login-Seite
    ├── logout.php                            # Logout-Handler
    ├── update_ticket.php                     # ⚠️ Ticket-Update (DEPRECATED)
    │
    ├── inc/                                  # Backend-Logik
    │   ├── auth.php                          # Session + Rollen + CSRF
    │   ├── db.php                            # DB-Verbindung (PDO)
    │   ├── 403.php                           # 403-Fehlerseite
    │   └── csrf.php                          # CSRF-Protection-Helfer
    │
    ├── ctrl/                                 # Controller (Seiten-Logik)
    │   ├── drinks.php                        # Getränke-Verwaltung
    │   ├── softdrinks.php                    # Softdrinks-Verwaltung
    │   ├── food.php                          # Speisen-Verwaltung
    │   ├── coffee.php                        # Kaffee-Verwaltung
    │   ├── ice.php                           # Eis-Verwaltung
    │   ├── cable.php                         # Cable-Park-Preise
    │   ├── camping.php                       # Camping-Preise
    │   ├── kino.php                          # Kino-Verwaltung
    │   ├── obstacles.php                     # Obstacles-Verwaltung
    │   ├── tickets.php                       # Ticket-System
    │   ├── users.php                         # Benutzer-Verwaltung
    │   └── debug.php                         # Debug-Panel (nur cernal)
    │
    ├── api/                                  # Backend-REST-APIs (WRITE)
    │   ├── drinks.php                        # Getränke-API (POST/PUT/DELETE)
    │   ├── softdrinks.php                    # Softdrinks-API
    │   ├── food.php                          # Speisen-API
    │   ├── coffee.php                        # Kaffee-API
    │   ├── ice.php                           # Eis-API
    │   ├── cable.php                         # Cable-Park-API
    │   ├── camping.php                       # Camping-API
    │   ├── kino.php                          # Kino-API
    │   ├── obstacles.php                     # Obstacles-API
    │   ├── tickets.php                       # Ticket-API
    │   └── users.php                         # Benutzer-API
    │
    ├── css/
    │   └── backend-styles.css                # Backend-Styles (NICHT Plugin!)
    │
    ├── js/
    │   └── backend.js                        # Backend-JavaScript
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
| `wcr-digital-signage/includes/rest-screenshot.php` | Plugin | Screenshot-API | ⚠️ CSRF-geschützt |
| `be/` | Backend | Admin-Interface | 🔒 Session-geschützt |
| `be/api/` | Backend | Write-REST-APIs | 🔒 Session + CSRF |
| `be/inc/auth.php` | Backend | Session + Rollen | 🔒 Backend-intern |

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

**Implementiert in:** `be/inc/auth.php`[cite:380]

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

**Implementiert in:** `be/inc/auth.php`[cite:380]

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
// REST-Route registriert im Plugin
register_rest_route('wcr/v1', '/screenshot/capture', [
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

**Namespace:** `wcr/v1`

**Öffentliche Read-Only-Routen:**

| Route | Zweck | Beispiel |
|-------|-------|----------|
| `GET /wcr/v1/drinks` | Getränke-Liste | Menükarte-Shortcode |
| `GET /wcr/v1/softdrinks` | Softdrinks-Liste | Softdrinks-Shortcode |
| `GET /wcr/v1/food` | Speisen-Liste | Speisekarte-Shortcode |
| `GET /wcr/v1/coffee` | Kaffee-Liste | Kaffeekarte-Shortcode |
| `GET /wcr/v1/ice` | Eis-Liste | Eiskarte-Shortcode |
| `GET /wcr/v1/cable` | Cable-Park-Preise | Cable-Preisliste |
| `GET /wcr/v1/camping` | Camping-Preise | Camping-Preisliste |
| `GET /wcr/v1/kino` | Kino-Programm | Kino-Shortcode |
| `GET /wcr/v1/obstacles` | Obstacles-Daten | Obstacles-Shortcode |
| `GET /wcr/v1/starter_pack_items` | Starter-Pack-Produkte | Starter-Pack-Animation |
| `GET /wcr/v1/instagram` | Instagram-Feed | Instagram-Widget |

**Besonderheiten:**
- ✅ **Keine Authentifizierung** – Öffentlich zugänglich
- ✅ **Keine CSRF-Prüfung** – Nur lesende Zugriffe
- ✅ **Caching-Header** – Performance-Optimierung
- ✅ **JSON-Response** – Standard REST-API-Format

**Code-Beispiel:**

```php
// wcr-digital-signage/includes/rest-api.php
register_rest_route('wcr/v1', '/drinks', [
    'methods' => 'GET',
    'callback' => 'wcr_get_drinks',
    'permission_callback' => '__return_true' // 👈 Öffentlich zugänglich
]);

function wcr_get_drinks() {
    global $wpdb;
    $drinks = $wpdb->get_results("SELECT * FROM be_drinks WHERE active=1 ORDER BY id ASC");
    return rest_ensure_response($drinks);
}
```

### Backend REST-API (Write)

**Basis-URL:** `https://domain.de/be/api/`

**Geschützte Write-APIs:**

| Datei | HTTP-Methoden | Zweck |
|-------|---------------|-------|
| `be/api/drinks.php` | `POST`, `PUT`, `DELETE` | Getränke anlegen/bearbeiten/löschen |
| `be/api/softdrinks.php` | `POST`, `PUT`, `DELETE` | Softdrinks anlegen/bearbeiten/löschen |
| `be/api/food.php` | `POST`, `PUT`, `DELETE` | Speisen anlegen/bearbeiten/löschen |
| `be/api/coffee.php` | `POST`, `PUT`, `DELETE` | Kaffee anlegen/bearbeiten/löschen |
| `be/api/ice.php` | `POST`, `PUT`, `DELETE` | Eis anlegen/bearbeiten/löschen |
| `be/api/cable.php` | `POST`, `PUT`, `DELETE` | Cable-Preise anlegen/bearbeiten/löschen |
| `be/api/camping.php` | `POST`, `PUT`, `DELETE` | Camping-Preise anlegen/bearbeiten/löschen |
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
    
    $stmt = $pdo->prepare("INSERT INTO be_drinks (name, price) VALUES (?, ?)");
    $stmt->execute([$name, $price]);
    
    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
}
```

### Sonderfälle & Migration-Kandidaten

**Screenshot-API (Sonderfall):**

| Was | Wo | Status | Warum Sonderfall | Langfristig |
|-----|----|----|------------------|-------------|
| Screenshot-Capture | `wcr-digital-signage/includes/rest-screenshot.php` | ⚠️ Funktioniert, aber in Plugin | Muss Elementor-Pages erfassen (braucht WordPress-Kontext) | 📝 TODO: Migration ins Backend (Screenshot extern aufrufen) |

**Instagram-API:**

| Was | Wo | Status | Warum hier | Langfristig |
|-----|----|----|------------|-------------|
| Instagram-Feed | `wcr-digital-signage/includes/instagram.php` | ✅ Korrekt | Read-Only, braucht WordPress für Caching | ✅ Bleibt im Plugin |

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
└── shortcodes-widgets.php      # ← Animationen & Widgets (1 Funktion)
```

### Shortcode-Kategorien

#### 1️⃣ **Content-Shortcodes** (`shortcodes-content.php`)

**Fokus:** Statische Content-Daten aus Datenbank (Menükarten, Preislisten)

| Shortcode | Funktion | Renderer | Zweck |
|-----------|----------|----------|-------|
| `[wcr_getraenke]` | `wcr_sc_getraenke()` | `WCR.renderDrinksList()` | Getränkekarte |
| `[wcr_softdrinks]` | `wcr_sc_softdrinks()` | `WCR.renderDrinksList()` | Softdrinks-Karte |
| `[wcr_essen]` | `wcr_sc_essen()` | `WCR.renderDrinksList()` | Speisekarte |
| `[wcr_kaffee]` | `wcr_sc_kaffee()` | Spezialisiertes JS | Kaffeekarte |
| `[wcr_eis]` | `wcr_sc_eis()` | `WCR.renderDrinksList()` | Eiskarte |
| `[wcr_cable]` | `wcr_sc_cable()` | `WCR.renderDrinksList()` | Cable-Park-Preise |
| `[wcr_camping]` | `wcr_sc_camping()` | `WCR.renderDrinksList()` | Camping-Preise |

**Gemeinsames Merkmal:**
- ✅ Nutzen REST-API: `GET /wcr/v1/{drinks|softdrinks|food|coffee|ice|cable|camping}`
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

| Shortcode | Funktion | Bibliothek | Zweck |
|-----------|----------|------------|-------|
| `[wcr_starter_pack]` | `wcr_sc_starter_pack()` | GSAP | Starter-Pack-Animation mit Timeline |

**Status:** ⚠️ **Aktuell nur 1 Funktion** – Datei vorbereitet für zukünftige Widget-Shortcodes

**Zukünftige Kandidaten:**
- Produkt-Slider
- Countdown-Timer
- Parallax-Effekte
- Animierte Hero-Sections

### Renderer-Mechanismus

**Gemeinsamer Renderer:** `WCR.renderDrinksList()` (JavaScript)

**Verwendung:**
```javascript
// assets/js/wcr-frontend.js
WCR.renderDrinksList({
    apiEndpoint: '/wp-json/wcr/v1/drinks',
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
            apiEndpoint: '/wp-json/wcr/v1/drinks',
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

| System | CSS-Datei | Zweck | Geladen in |
|--------|-----------|-------|------------|
| **Backend** | `be/css/backend-styles.css` | Backend-Interface-Styles | `be/index.php`, `be/ctrl/*.php` |
| **Plugin** | `wcr-digital-signage/assets/css/plugin-styles.css` | Frontend-Shortcode-Styles | WordPress-Frontend (via `wp_enqueue_style`) |

### Backend-CSS

**Datei:** `be/css/backend-styles.css`

**Inhalt:**
- Dashboard-Layout (Grid, Sidebar, Header)
- Tabellen-Styles (Produktlisten, Benutzer-Verwaltung)
- Formulare (Input-Felder, Buttons, Modals)
- Navigation (Menü, Tabs)
- Role-Badges, Status-Badges
- Responsive Design für Backend

**Laden:**
```php
// be/index.php
<link rel="stylesheet" href="/be/css/backend-styles.css">
```

### Plugin-CSS

**Datei:** `wcr-digital-signage/assets/css/plugin-styles.css`

**Inhalt:**
- Shortcode-Styles (Menükarten, Preislisten, Wetter-Widget)
- Windkarte-Styles (Leaflet-Overlays)
- Kino-Programm-Layout
- Starter-Pack-Animation-Styles
- Responsive Design für Digital Signage Displays

**Laden:**
```php
// wcr-digital-signage/includes/enqueue.php
function wcr_enqueue_assets() {
    wp_enqueue_style(
        'wcr-plugin-styles',
        plugins_url('assets/css/plugin-styles.css', dirname(__FILE__)),
        [],
        '2.0.0'
    );
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
/* Backend-CSS: Präfix .be-* */
.be-dashboard { ... }
.be-table { ... }
.be-form-group { ... }

/* Plugin-CSS: Präfix .wcr-* */
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

---

## 📋 ENTWICKLERREGELN

### 🔴 KRITISCHE REGELN (Niemals brechen!)

#### 1️⃣ **Plugin vs Backend: Klare Trennung**

```
┌─────────────────────────────────────────┐
│ PLUGIN (wcr-digital-signage/)           │
├─────────────────────────────────────────┤
│ ✅ Frontend-Ausgabe (Shortcodes)        │
│ ✅ Read-Only REST-API                   │
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
- be/css/backend-styles.css → Nur Backend
- wcr-digital-signage/assets/css/plugin-styles.css → Nur Plugin
- Separate Namespaces: .be-* vs .wcr-*
```

#### 3️⃣ **REST-API: Read vs Write**

```
✅ READ (Plugin):
- GET-Requests
- Öffentlich zugänglich
- Keine Authentifizierung
- Keine CSRF-Prüfung

✅ WRITE (Backend):
- POST/PUT/DELETE-Requests
- Session-geschützt (require_login)
- Berechtigungsprüfung (wcr_require)
- CSRF-geschützt (wcr_verify_csrf)
```

#### 4️⃣ **Shortcode-Struktur: Thematische Aufteilung**

```
NEUE SHORTCODES HINZUFÜGEN:

1️⃣ Shortcode-Funktion schreiben in passender Datei:
   - Menü/Preislisten → shortcodes-content.php
   - Live-Daten/APIs → shortcodes-display.php
   - Animationen/Widgets → shortcodes-widgets.php

2️⃣ Shortcode registrieren in shortcodes.php:
   add_shortcode('wcr_new_shortcode', 'wcr_sc_new_shortcode');

3️⃣ Dokumentation updaten:
   - ARCHITECTURE.md → Shortcode-System-Sektion
   - Kommentare in Code
```

#### 5️⃣ **Sicherheits-Checkliste für neue Features**

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
register_rest_route('wcr/v1', '/drinks', [
    'methods' => 'POST',  // ← POST im Plugin!
    'callback' => 'wcr_create_drink'
]);

✅ RICHTIG:
- POST/PUT/DELETE gehört ins Backend (be/api/drinks.php)
- Plugin nur GET-Requests
```

#### Fehler 2: Plugin-CSS im Backend

```php
❌ FALSCH (be/index.php):
<link rel="stylesheet" href="/wordpress/wp-content/plugins/wcr-digital-signage/assets/css/plugin-styles.css">

✅ RICHTIG:
<link rel="stylesheet" href="/be/css/backend-styles.css">
```

#### Fehler 3: Fehlende CSRF-Protection

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

#### Fehler 4: Shortcode-Funktion falsch platziert

```php
❌ FALSCH:
- Neue Shortcode-Funktion in shortcodes.php schreiben
- Shortcode-Funktion in wcr-digital-signage.php schreiben

✅ RICHTIG:
- Shortcode-Funktion in shortcodes-{content|display|widgets}.php schreiben
- Registrierung in shortcodes.php (add_shortcode)
```

### 📝 BEST PRACTICES

#### Neue Produkt-Kategorie hinzufügen (Beispiel: "Snacks")

**Schritt 1: Datenbank-Tabelle**
```sql
CREATE TABLE be_snacks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    active TINYINT(1) DEFAULT 1,
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
register_rest_route('wcr/v1', '/snacks', [
    'methods' => 'GET',
    'callback' => 'wcr_get_snacks',
    'permission_callback' => '__return_true'
]);

function wcr_get_snacks() {
    global $wpdb;
    $snacks = $wpdb->get_results("SELECT * FROM be_snacks WHERE active=1 ORDER BY sortorder ASC");
    return rest_ensure_response($snacks);
}
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
            apiEndpoint: '/wp-json/wcr/v1/snacks',
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
// be/index.php
<nav>
    <a href="?page=drinks">Getränke</a>
    <a href="?page=food">Essen</a>
    <a href="?page=snacks">Snacks</a>  ← NEU
</nav>
```

**Schritt 8: Dokumentation updaten**
- `ARCHITECTURE.md` → REST-API-Sektion + Shortcode-Sektion
- `CHANGELOG.md` → Neue Funktion dokumentieren

---

## 📐 ARCHITEKTUR-ZUSAMMENFASSUNG

### ✅ WICHTIGSTE ARCHITEKTURREGELN (Top 10)

1. **Plugin = Frontend, Backend = Admin** – Niemals vermischen!
2. **Plugin nur Read-Only REST-APIs** – Keine POST/PUT/DELETE
3. **Backend immer Session + CSRF** – Alle Write-Operationen absichern
4. **CSS strikt getrennt** – be/css/ vs wcr-digital-signage/assets/css/
5. **Shortcodes thematisch aufteilen** – content, display, widgets
6. **Rollen-System beachten** – cernal, admin, user
7. **Session-Timeout 8h** – Automatischer Logout bei Inaktivität
8. **CSRF-Token rotieren** – Nach erfolgreicher Prüfung neues Token
9. **Deprecated nicht löschen** – Archivieren in be/_deprecated/
10. **Deploy-Hygiene** – delete_remote_files: true (Server = Git)

### ❌ WAS NIEMALS VERMISCHT WERDEN DARF

| Was | Wo | Warum |
|-----|----|----|---|
| **Schreibende REST-APIs** | ❌ Plugin | Plugin ist öffentlich, keine Schreibrechte |
| **Session-Management** | ❌ Plugin | Plugin nutzt WordPress, Backend eigene Session |
| **Admin-Interface** | ❌ Plugin | Backend-Aufgabe, nicht Plugin |
| **Frontend-Ausgabe** | ❌ Backend | Plugin-Aufgabe, nicht Backend |
| **Backend-CSS** | ❌ Plugin | Separate Styles für separate Systeme |
| **Plugin-CSS** | ❌ Backend | Separate Styles für separate Systeme |
| **CSRF-Tokens** | ❌ Plugin (außer Screenshot-Sonderfall) | Backend-Security-Feature |

### 📚 WEITERE DOKUMENTATION

- **CI/CD:** `wcr-digital-signage/README-CI-SYSTEM.md`
- **Changelog:** `wcr-digital-signage/CHANGELOG.md`
- **Deprecated:** `be/_deprecated/README.md`
- **Deployment:** `.github/workflows/deploy.yml`

---

**Stand:** März 2026  
**Autor:** Marcus Kempe  
**Repository:** [github.com/homez-bln/wcr-digital-signage](https://github.com/homez-bln/wcr-digital-signage)
