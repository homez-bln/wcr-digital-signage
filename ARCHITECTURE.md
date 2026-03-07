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
11. [✅ Abschluss-Checkliste](#-abschluss-checkliste-qa--abnahme)

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

[... gesamter bisheriger Inhalt von ARCHITECTURE.md bleibt unverändert ...]

---

## ✅ ABSCHLUSS-CHECKLISTE (QA & ABNAHME)

### 📋 Projekt-Abnahme: WCR Digital Signage System

**Zweck:** Diese Checkliste dient zur Qualitätssicherung und Abnahme nach größeren Refactorings, Architektur-Änderungen oder vor Release-Deployments.

**Anwendung:** Alle Punkte durchgehen und abhaken. Bei ❌ → Issue erstellen oder Korrektur durchführen.

---

### 🔒 1. BACKEND-SICHERHEIT

| Check | Status | Beschreibung |
|-------|:------:|-------------|
| Session-System aktiv | ☐ | `be/inc/auth.php` → Hardened Security v10 implementiert |
| Fingerprinting funktioniert | ☐ | Session gebunden an User-Agent + IP-Präfix |
| 8h-Timeout aktiv | ☐ | Automatischer Logout nach Inaktivität |
| CSRF-Protection auf allen Write-APIs | ☐ | `wcr_verify_csrf()` in `be/api/*.php` vorhanden |
| Token-Rotation funktioniert | ☐ | Token wird nach Validierung neu generiert |
| Rollen-System funktioniert | ☐ | cernal/admin/user → Berechtigungen korrekt |
| Login-Seite erreichbar | ☐ | `/be/login.php` → Redirect zu Dashboard nach Login |
| Logout funktioniert | ☐ | `/be/logout.php` → Session zerstört, Redirect zu Login |
| 403-Fehlerseite funktioniert | ☐ | Zugriff ohne Berechtigung zeigt `be/inc/403.php` |

**Test-Szenarien:**
- ✅ Login mit User-Account → Dashboard erreichbar
- ✅ Session-Timeout: 8h warten → automatischer Logout
- ✅ CSRF-Token fehlt → API-Call blockiert (403)
- ✅ User ohne `edit_prices`-Berechtigung → Preise nicht änderbar

---

### ⚠️ 2. FEHLERBEHANDLUNG

| Check | Status | Beschreibung |
|-------|:------:|-------------|
| REST-API: 404 bei falscher Route | ☐ | Plugin REST-API: Falsche Route → 404 JSON |
| REST-API: 403 bei fehlender Berechtigung | ☐ | Backend-API: Keine Session → 403 + Exit |
| REST-API: 405 bei falschem HTTP-Method | ☐ | GET auf POST-Route → 405 Method Not Allowed |
| DB-Fehler abgefangen | ☐ | `try-catch` in `be/inc/db.php` und `wcr-digital-signage/includes/db.php` |
| Frontend: Fallback bei API-Fehler | ☐ | Shortcodes zeigen "Daten laden fehlgeschlagen" |
| Backend: Error-Logging aktiv | ☐ | PHP-Errors in Server-Log sichtbar |
| Debug-Panel nur für cernal | ☐ | `be/ctrl/debug.php` → Nur Rolle cernal |

**Test-Szenarien:**
- ✅ API-Call mit falscher Route → JSON mit Fehlermeldung
- ✅ Shortcode bei DB-Down → Fallback-Meldung sichtbar
- ✅ User-Account versucht Debug-Panel → 403 Forbidden

---

### 🎨 3. CSS-TRENNUNG

| Check | Status | Beschreibung |
|-------|:------:|-------------|
| Backend-CSS korrekt geladen | ☐ | `/be/inc/style.css` via `be/inc/menu.php` geladen |
| Backend-CSS NICHT im Plugin | ☐ | WordPress-Frontend lädt KEIN Backend-CSS |
| Plugin-CSS modular geladen | ☐ | 11+ CSS-Dateien: `wcr-ds-global.css`, `wcr-ds-components.css`, etc. |
| Plugin-CSS NICHT im Backend | ☐ | Backend-Interface lädt KEIN Plugin-CSS |
| CSS-Namespaces getrennt | ☐ | Backend: `.be-*`, Plugin: `.wcr-*` |
| Keine CSS-Konflikte | ☐ | Backend und Plugin parallel öffnen → keine Style-Probleme |

**Test-Szenarien:**
- ✅ Backend öffnen: `/be/index.php` → Nur `be/inc/style.css` geladen
- ✅ WordPress-Seite mit Shortcode → Nur Plugin-CSS geladen
- ✅ Beide parallel öffnen → Keine CSS-Konflikte

---

### 🔄 4. VERANTWORTLICHKEIT: BACKEND vs PLUGIN

| Check | Status | Beschreibung |
|-------|:------:|-------------|
| Plugin nur Read-Only REST-API | ☐ | `wcr-digital-signage/includes/rest-api.php` → nur GET-Routes |
| Backend nur Write-REST-APIs | ☐ | `be/api/*.php` → nur POST/PUT/DELETE |
| Plugin keine Session-Verwaltung | ☐ | Kein `session_start()` im Plugin |
| Backend keine Shortcodes | ☐ | Kein `add_shortcode()` im Backend |
| Plugin keine schreibenden DB-Calls | ☐ | Keine `INSERT/UPDATE/DELETE` in Plugin (außer Screenshot-Sonderfall) |
| Backend kein Frontend-Output | ☐ | Backend gibt nur Admin-HTML aus, keine Shortcode-Ausgabe |

**Test-Szenarien:**
- ✅ Plugin REST-API: POST-Request → 404 oder 405
- ✅ Backend: Shortcode verwenden → Fehler (nicht registriert)

---

### 🌐 5. REST-API IM PLUGIN

| Check | Status | Beschreibung |
|-------|:------:|-------------|
| Namespace korrekt: `wakecamp/v1` | ☐ | NICHT `wcr/v1`! Alle Routes: `/wp-json/wakecamp/v1/*` |
| Alle öffentlichen Routes funktionieren | ☐ | drinks, food, ice, cable, camping, kino, events, obstacles, instagram |
| Stock-Filter aktiv | ☐ | Nur Items mit `stock != 0` werden zurückgegeben |
| JSON-Response korrekt | ☐ | `Content-Type: application/json`, valides JSON |
| Screenshot-API CSRF-geschützt | ☐ | `/wakecamp/v1/screenshot/capture` → `permission_callback` prüft CSRF |
| Instagram-API funktioniert | ☐ | `/wakecamp/v1/instagram` → Instagram-Feed geladen |
| Ping-Route funktioniert | ☐ | `/wakecamp/v1/ping` → Health-Check OK |

**Test-Szenarien:**
- ✅ Browser: `/wp-json/wakecamp/v1/drinks` → JSON-Liste
- ✅ Browser: `/wp-json/wcr/v1/drinks` → 404 (falscher Namespace)
- ✅ cURL: Screenshot-API ohne CSRF → 403 Forbidden

---

### 📝 6. PLUGIN-STRUKTUR / SHORTCODES

| Check | Status | Beschreibung |
|-------|:------:|-------------|
| Zentrale Shortcode-Registrierung | ☐ | `wcr-digital-signage/includes/shortcodes.php` → alle `add_shortcode()` |
| Thematische Aufteilung korrekt | ☐ | `shortcodes-content.php`, `shortcodes-display.php`, `shortcodes-widgets.php` |
| Legacy-Dateien markiert | ☐ | `shortcode-kino.php`, `shortcode-produkte.php` als LEGACY dokumentiert |
| Alle Shortcodes funktionieren | ☐ | Elementor: `[wcr_getraenke]`, `[wcr_essen]`, `[wcr_wetter]`, etc. |
| REST-API-Calls mit korrektem Namespace | ☐ | JavaScript: `/wp-json/wakecamp/v1/drinks` (nicht `wcr/v1`) |
| CSS/JS korrekt enqueued | ☐ | `includes/enqueue.php` → `wp_enqueue_style/script` |

**Test-Szenarien:**
- ✅ Elementor: Shortcode einfügen → Korrekte Ausgabe
- ✅ Browser DevTools: API-Call → korrekte Route (`wakecamp/v1`)
- ✅ Shortcode ohne Daten → Fallback-Meldung sichtbar

---

### 🗑️ 7. DEPRECATED/ARCHIV

| Check | Status | Beschreibung |
|-------|:------:|-------------|
| `be/_deprecated/` Verzeichnis existiert | ☐ | Alte Dateien archiviert, nicht gelöscht |
| `be/_deprecated/README.md` vorhanden | ☐ | Dokumentation: Was ist deprecated & warum |
| Legacy-Dateien nicht in Produktion | ☐ | Deprecated-Dateien werden nicht verwendet |
| Legacy-Shortcodes dokumentiert | ☐ | `shortcode-kino.php`, `shortcode-produkte.php` als LEGACY markiert |
| Keine toten Code-Pfade | ☐ | Alte Funktionen entfernt oder archiviert |

**Test-Szenarien:**
- ✅ `be/_deprecated/README.md` öffnen → Vollständige Dokumentation
- ✅ Grep nach veralteten Funktionen → Keine Treffer in aktiven Dateien

---

### 🚀 8. DEPLOY-WORKFLOW

| Check | Status | Beschreibung |
|-------|:------:|-------------|
| GitHub Actions Workflow funktioniert | ☐ | `.github/workflows/deploy.yml` → Runs grün |
| Secrets korrekt konfiguriert | ☐ | `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` gesetzt |
| SFTP-Verbindung funktioniert | ☐ | Deploy-Run → Verbindung zu IONOS erfolgreich |
| Delete-Mode aktiv | ☐ | `delete_remote_files: true` → Server = Git-Zustand |
| Exclude-Liste korrekt | ☐ | `.git/`, `.github/`, `node_modules/` excluded |
| Timeout-Protection aktiv | ☐ | `timeout-minutes: 10` → Workflow bricht nach 10 Min ab |
| Deploy auf `main`-Push automatisch | ☐ | Push auf `main` triggert automatischen Deploy |

**Test-Szenarien:**
- ✅ Commit auf `main` pushen → Deploy-Run startet automatisch
- ✅ Deploy-Run grün → Server-Dateien synchron mit Git
- ✅ Server: Alte Dateien nach Refactoring → gelöscht (Delete-Mode)

---

### 📚 9. DOKUMENTATION

| Check | Status | Beschreibung |
|-------|:------:|-------------|
| README.md aktuell | ☐ | Spiegelt aktuellen Repository-Zustand wider |
| ARCHITECTURE.md vollständig | ☐ | Alle Architektur-Prinzipien dokumentiert |
| REST-Namespace korrekt: `wakecamp/v1` | ☐ | NICHT `wcr/v1` in Dokumentation |
| Backend-CSS-Pfad korrekt | ☐ | `be/inc/style.css` (NICHT `be/css/backend-styles.css`) |
| Plugin-CSS modular dokumentiert | ☐ | 11+ Dateien aufgelistet (keine generische `plugin-styles.css`) |
| Verzeichnisstruktur real | ☐ | Alle Pfade entsprechen echten Dateien |
| Legacy-Dateien markiert | ☐ | `shortcode-kino.php`, `shortcode-produkte.php` als LEGACY |
| Konzeptioneller Hinweis vorhanden | ☐ | Architektur-Diagramm mit Hinweis "konzeptionelle Übersicht" |
| CHANGELOG.md aktuell | ☐ | Letzte Änderungen dokumentiert |

**Test-Szenarien:**
- ✅ Neuer Entwickler: README.md lesen → Versteht Architektur sofort
- ✅ Dokumentation: Pfade suchen → Alle Dateien existieren
- ✅ API-Dokumentation: REST-Namespace → überall `wakecamp/v1`

---

### 🧪 10. PRAXIS-TEST (END-TO-END)

| Check | Status | Beschreibung |
|-------|:------:|-------------|
| **Frontend:** Shortcodes funktionieren | ☐ | Elementor-Seite öffnen → Alle Shortcodes laden Daten |
| **Frontend:** Wetter-Widget funktioniert | ☐ | `[wcr_wetter]` → Wetter-Daten von Open-Meteo |
| **Frontend:** Windmap funktioniert | ☐ | `[wcr_windmap]` → Karte lädt, Wind-Animation |
| **Backend:** Login funktioniert | ☐ | `/be/login.php` → Dashboard-Zugriff |
| **Backend:** Produktverwaltung funktioniert | ☐ | Getränk anlegen/bearbeiten/löschen |
| **Backend:** User-Management funktioniert | ☐ | User anlegen/bearbeiten/löschen |
| **Backend:** CSRF-Protection funktioniert | ☐ | Formular ohne Token → Fehlermeldung |
| **REST-API:** Public Routes funktionieren | ☐ | `/wp-json/wakecamp/v1/drinks` → JSON-Daten |
| **REST-API:** Write-Routes geschützt | ☐ | `be/api/drinks.php` ohne Session → 403 |
| **Deploy:** GitHub Actions funktioniert | ☐ | Push → Deploy → Server aktualisiert |

**Test-Szenarien (vollständiger Workflow):**
1. ✅ Backend: Login → Getränk anlegen → Speichern
2. ✅ REST-API: `/wp-json/wakecamp/v1/drinks` → Neues Getränk in Liste
3. ✅ Frontend: Elementor-Seite mit `[wcr_getraenke]` → Getränk sichtbar
4. ✅ Backend: Getränk löschen → Speichern
5. ✅ Frontend: Seite neu laden → Getränk nicht mehr sichtbar
6. ✅ Deploy: Commit pushen → Server aktualisiert → Live-System funktioniert

---

## ✅ ABNAHME-BESTÄTIGUNG

**Projekt:** WCR Digital Signage System v2.0  
**Datum:** _____________  
**Prüfer:** _____________

**Ergebnis:**
- ☐ Alle Checks bestanden → **PRODUKTIV-READY** ✅
- ☐ Einige Checks fehlgeschlagen → **NACHBESSERUNG ERFORDERLICH** ⚠️
- ☐ Kritische Checks fehlgeschlagen → **NICHT PRODUKTIV-READY** ❌

**Notizen:**
```
_____________________________________________
_____________________________________________
_____________________________________________
```

**Nächste Schritte:**
1. Falls ✅: Produktiv-Deployment freigeben
2. Falls ⚠️: Issues für fehlgeschlagene Checks erstellen
3. Falls ❌: Kritische Probleme beheben → Re-Test

---

**Stand:** März 2026 (inkl. Abschluss-Checkliste)  
**Autor:** Marcus Kempe  
**Repository:** [github.com/homez-bln/wcr-digital-signage](https://github.com/homez-bln/wcr-digital-signage)
