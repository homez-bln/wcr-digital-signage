# 🖥️ WCR Digital Signage System

**Enterprise Digital Signage Solution für Freizeiteinrichtungen**

[![Deploy Status](https://img.shields.io/badge/deploy-automated-success)](https://github.com/homez-bln/wcr-digital-signage/actions)
[![WordPress](https://img.shields.io/badge/WordPress-6.4+-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0+-purple)](https://php.net)
[![License](https://img.shields.io/badge/license-Proprietary-red)](LICENSE)

---

## 📋 Projekt-Übersicht

**WCR Digital Signage** ist eine spezialisierte Lösung zur Verwaltung und Anzeige von Content (Menükarten, Preislisten, Wetterdaten, Kino-Programm, etc.) auf Digital Signage Displays.

### Kernfunktionen

- 🍹 **Menü-Management** – Getränkekarten, Speisekarten, Kaffee, Eis
- 💰 **Preislisten** – Cable-Park, Camping, dynamische Preisänderungen
- 🌤️ **Live-Daten** – Wetter-Widget, Windkarte mit Open-Meteo API
- 🎬 **Content-Management** – Kino-Programm, Obstacles-Verwaltung
- 🎟️ **Ticket-System** – Event-Tickets mit Live-Status
- 👥 **Benutzer-Verwaltung** – Rollen-basiertes Zugriffssystem

---

## 🏗️ Architektur-Übersicht

**Zwei-System-Architektur** für optimale Trennung von Frontend und Backend:

```
┌─────────────────────────────────────┐
│   WordPress Installation            │
│   /WebSpace/wordpress/              │
│                                     │
│   ┌───────────────────────────────┐ │
│   │  wcr-digital-signage/         │ │  ← WordPress-Plugin
│   │  (PUBLIC FRONTEND)            │ │     • Öffentliche Anzeige
│   │  • Shortcodes                 │ │     • Read-Only REST-API
│   │  • REST-API (Read-Only)       │ │     • Frontend-Assets
│   └───────────────────────────────┘ │
└─────────────────────────────────────┘
            ↓ (DB-Zugriff)
    ┌───────────────────┐
    │  MySQL Database   │
    │  be_*-Tabellen    │
    └───────────────────┘
            ↑ (DB-Zugriff)
┌─────────────────────────────────────┐
│   Standalone PHP Application        │
│   /WebSpace/be/                     │
│                                     │
│   be/ (PROTECTED BACKEND)           │  ← Separates Backend
│   • Session-basiertes Login         │     • Content-Verwaltung
│   • Schreibende REST-APIs           │     • Admin-Interface
│   • Admin-Interface                 │     • User-Management
│   • Rollen-System (3 Rollen)        │
└─────────────────────────────────────┘
```

### Warum zwei Systeme?

| Vorteil | Beschreibung |
|---------|-------------|
| ⚡ **Performance** | Plugin: WordPress-optimiert, Backend: direkter DB-Zugriff |
| 🔒 **Sicherheit** | Strikte Trennung: Öffentlich vs. geschützt |
| 🧹 **Wartbarkeit** | Klare Code-Organisation, keine Vermischung |
| 🎯 **Fokus** | Plugin für Anzeige, Backend für Verwaltung |

---

## 🚀 Schnelleinstieg für Entwickler

### Voraussetzungen

- **WordPress:** Version 6.4+
- **PHP:** Version 8.0+
- **MySQL/MariaDB:** Version 5.7+
- **Git:** Für Deployment
- **GitHub Actions:** Für automatisches Deployment

### Repository klonen

```bash
git clone https://github.com/homez-bln/wcr-digital-signage.git
cd wcr-digital-signage
```

### Verzeichnisstruktur

```
wcr-digital-signage/
├── wcr-digital-signage/          # WordPress-Plugin
│   ├── wcr-digital-signage.php   # Haupt-Plugin-Datei
│   ├── includes/                 # Plugin-Logik
│   │   ├── rest-api.php          # Read-Only REST-API
│   │   ├── shortcodes*.php       # Shortcode-Funktionen
│   │   └── enqueue.php           # CSS/JS Assets
│   └── assets/                   # Frontend-Assets
│       ├── css/plugin-styles.css
│       └── js/wcr-frontend.js
│
├── be/                           # Standalone Backend
│   ├── index.php                 # Dashboard
│   ├── login.php                 # Login-Seite
│   ├── inc/
│   │   ├── auth.php              # Session + Rollen + CSRF
│   │   └── db.php                # DB-Verbindung (PDO)
│   ├── ctrl/                     # Controller (Seiten)
│   ├── api/                      # REST-APIs (Write)
│   ├── css/backend-styles.css    # Backend-Styles
│   └── _deprecated/              # Archiv alter Dateien
│
├── .github/workflows/deploy.yml  # GitHub Actions Deployment
├── ARCHITECTURE.md               # 📚 Vollständige technische Dokumentation
├── CHANGELOG.md                  # Änderungsprotokoll
└── README.md                     # Diese Datei
```

### Erste Schritte

**1. Plugin installieren:**
```bash
# wcr-digital-signage/ nach WordPress-Plugin-Verzeichnis kopieren
cp -r wcr-digital-signage /path/to/wordpress/wp-content/plugins/
```

**2. Backend installieren:**
```bash
# be/ nach WebSpace-Root kopieren
cp -r be /path/to/webspace/be/
```

**3. Datenbank konfigurieren:**
```bash
# DB-Credentials in be/inc/db.php eintragen
# Tabellen-Schema in be/sql/ ausführen (falls vorhanden)
```

**4. Plugin aktivieren:**
- WordPress-Admin → Plugins → "WCR Digital Signage" aktivieren

**5. Backend-Login:**
- Browser: `https://your-domain.de/be/login.php`
- Credentials: Siehe Backend-Admin

---

## 🔐 Sicherheitskonzept

### Rollen-System

| Rolle | Berechtigungen | Anzahl |
|-------|----------------|--------|
| **cernal** | Alle Rechte + Debug-Panel | 1 (Owner) |
| **admin** | Content-Management + User-Management | 1-3 (Team) |
| **user** | Toggle-Funktionen (An/Aus) | Beliebig |

### Session-Sicherheit

- ✅ **Secure Cookies** (HTTPS-only, HttpOnly, SameSite=Strict)
- ✅ **Session-Fingerprinting** (User-Agent + IP-Präfix)
- ✅ **8-Stunden-Timeout** (automatischer Logout)
- ✅ **Session-Regeneration** bei Login (verhindert Session-Fixation)

### CSRF-Protection

- ✅ **Token-Rotation** (nach erfolgreicher Validierung neues Token)
- ✅ **Constant-Time Comparison** (verhindert Timing-Angriffe)
- ✅ **Alle Write-APIs geschützt** (POST/PUT/DELETE)

---

## 🌐 REST-API

### Plugin REST-API (Read-Only)

**Namespace:** `wcr/v1`

| Route | Zweck | Zugriff |
|-------|-------|--------|
| `GET /wcr/v1/drinks` | Getränkekarte | ✅ Öffentlich |
| `GET /wcr/v1/food` | Speisekarte | ✅ Öffentlich |
| `GET /wcr/v1/coffee` | Kaffeekarte | ✅ Öffentlich |
| `GET /wcr/v1/kino` | Kino-Programm | ✅ Öffentlich |
| `GET /wcr/v1/wetter` | Wetter-Daten | ✅ Öffentlich |

### Backend REST-API (Write)

**Basis-URL:** `https://your-domain.de/be/api/`

| API | HTTP-Methoden | Zugriff |
|-----|---------------|--------|
| `be/api/drinks.php` | POST, PUT, DELETE | 🔒 Session + CSRF |
| `be/api/food.php` | POST, PUT, DELETE | 🔒 Session + CSRF |
| `be/api/users.php` | POST, PUT, DELETE | 🔒 Session + CSRF |

---

## 🎨 Shortcodes

### Verfügbare Shortcodes

**Content-Shortcodes (Menü & Preislisten):**
- `[wcr_getraenke]` – Getränkekarte
- `[wcr_softdrinks]` – Softdrinks-Karte
- `[wcr_essen]` – Speisekarte
- `[wcr_kaffee]` – Kaffeekarte
- `[wcr_eis]` – Eiskarte
- `[wcr_cable]` – Cable-Park-Preise
- `[wcr_camping]` – Camping-Preise

**Display-Shortcodes (Live-Daten):**
- `[wcr_windmap]` – Windkarte mit Leaflet + Open-Meteo
- `[wcr_wetter]` – Wetter-Widget mit 7-Tage-Forecast

**Widget-Shortcodes (Animationen):**
- `[wcr_starter_pack]` – Starter-Pack-Animation (GSAP)

---

## 🚀 Deployment

### Automatisches Deployment via GitHub Actions

**Trigger:**
- ✅ Push auf `main`-Branch
- ✅ Manueller Workflow-Trigger

**Deployment-Prozess:**
1. Code auschecken
2. SFTP-Upload zu IONOS WebSpace
3. Delete-Mode: Alte Dateien entfernen (Deploy-Hygiene)
4. Exclude: `.git/`, `.github/`, `node_modules/`

**Konfiguration:** `.github/workflows/deploy.yml`

**Secrets:** `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`

---

## 📚 Vollständige Dokumentation

**Für detaillierte technische Informationen siehe:**

👉 **[ARCHITECTURE.md](ARCHITECTURE.md)** – Vollständige Architektur-Dokumentation (40+ Seiten)

**Themen:**
- Architektur-Prinzipien
- Verzeichnisstruktur
- Sicherheitskonzept (Session, CSRF, Rollen)
- REST-API-Konzept
- Shortcode-System
- CSS-Trennung
- Deployment-Workflow
- Entwicklerregeln
- Best Practices

---

## 🛠️ Development-Workflow

### Neue Feature entwickeln

**1. Branch erstellen:**
```bash
git checkout -b feature/neue-funktion
```

**2. Code schreiben:**
- Plugin-Code: `wcr-digital-signage/`
- Backend-Code: `be/`
- **WICHTIG:** Plugin und Backend nicht vermischen!

**3. Testen:**
- Lokaler WordPress-Server
- Backend-Login testen
- Shortcodes in Elementor testen

**4. Commit & Push:**
```bash
git add .
git commit -m "✨ Feature: Neue Funktion hinzugefügt"
git push origin feature/neue-funktion
```

**5. Pull Request:**
- GitHub: Create Pull Request
- Review durch Team
- Merge in `main` → Automatisches Deployment

### Coding-Standards

**Plugin (wcr-digital-signage/):**
- ✅ Nur Read-Only REST-APIs
- ✅ Shortcodes für Frontend-Ausgabe
- ✅ CSS/JS via `wp_enqueue_style`/`wp_enqueue_script`
- ❌ Keine schreibenden Operationen
- ❌ Kein Session-Management

**Backend (be/):**
- ✅ Session-basiertes Login (`require_login()`)
- ✅ Berechtigungsprüfung (`wcr_require()`)
- ✅ CSRF-Protection (`wcr_verify_csrf()`)
- ✅ Write-REST-APIs (POST/PUT/DELETE)
- ❌ Keine Frontend-Ausgabe
- ❌ Keine Shortcodes

---

## 📝 Changelog

Siehe [CHANGELOG.md](wcr-digital-signage/CHANGELOG.md) für detaillierte Änderungen.

---

## 📧 Support

**Entwickler:** Marcus Kempe  
**E-Mail:** marcus.kempe88@gmail.com  
**Repository:** [github.com/homez-bln/wcr-digital-signage](https://github.com/homez-bln/wcr-digital-signage)

---

## 📄 Lizenz

**Proprietary** – Alle Rechte vorbehalten.

Dieses Projekt ist für den internen Einsatz in der WCR-Freizeiteinrichtung entwickelt.
Keine Weitergabe oder kommerzielle Nutzung ohne Genehmigung.

---

**Stand:** März 2026  
**Version:** 2.0
