# 📝 DOKUMENTATIONS-KORREKTUREN
## Übersicht aller Pfad- und Namespace-Korrekturen

**Datum:** 7. März 2026  
**Grund:** Doku-Review zur Sicherstellung, dass alle Pfade den echten Dateien im Repo entsprechen

---

## 📊 KORREKTUREN-TABELLE

| Doku-Stelle | Bisher (falsch) | Korrigiert (richtig) | Warum Korrektur nötig |
|------------|----------------|---------------------|----------------------|
| **REST-API Namespace** | `wcr/v1` | `wakecamp/v1` | Der tatsächliche Namespace in `rest-api.php` ist `wakecamp/v1`, nicht `wcr/v1`. Alle API-Routen verwenden diesen Namespace. |
| **Backend-CSS Pfad** | `be/css/backend-styles.css` | `be/inc/style.css` | Backend-CSS liegt in `be/inc/style.css` (geladen via `menu.php`). Das Verzeichnis `be/css/` existiert nicht im Repository. |
| **Plugin-CSS Pfad** | `assets/css/plugin-styles.css` (generisch) | Mehrere spezifische Dateien:<br>• `wcr-ds-global.css`<br>• `wcr-ds-components.css`<br>• `wcr-ds-landscape.css`<br>• `wcr-ds-portrait.css`<br>• `wcr-ds-unified.css`<br>• `wcr-ds-theme-glass.css`<br>• `wcr-produkte.css`<br>• `wcr-kino-slider.css`<br>• `wcr-instagram.css`<br>• `wcr-instagram-video.css`<br>• `wcr-obstacles-map.css` | Es gibt keine einzelne `plugin-styles.css`. Das Plugin nutzt **modulare CSS-Dateien** für verschiedene Komponenten. Die Doku sollte dies klar machen oder die konkreten Dateien listen. |
| **Shortcode Legacy-Dateien** | Nicht dokumentiert | • `shortcode-kino.php`<br>• `shortcode-produkte.php` | Diese Legacy-Shortcode-Dateien existieren **parallel zu den neuen** `shortcodes-*.php` Dateien. Sie sollten in der Doku als **Legacy** markiert werden, damit Entwickler wissen, dass die neuen Dateien bevorzugt werden. |
| **Backend Verzeichnis `css/`** | `be/css/` in Verzeichnisstruktur aufgeführt | **Existiert nicht!**<br>CSS liegt in `be/inc/style.css` | Die Verzeichnisstruktur-Dokumentation zeigt ein nicht-existierendes Verzeichnis `be/css/`. Dies würde Entwickler verwirren. |
| **Backend Verzeichnis `js/`** | `be/js/backend.js` als Beispiel | **`be/js/` existiert**, aber Dateinamen sollten geprüft werden | Das Verzeichnis existiert, aber die Doku sollte **echte Dateinamen** aus dem Repo auflisten statt Platzhalter. |
| **Instagram-API** | Nicht als separate Datei erwähnt | `includes/instagram.php` existiert als eigenständige Klasse `WCR_Instagram` | Die Instagram-Integration ist ein wichtiger Bestandteil und hat eine **eigene Klasse in `instagram.php`**. Dies sollte in der Verzeichnisstruktur dokumentiert sein. |
| **REST-Screenshot-API Namespace** | Als Sonderfall erwähnt, aber Namespace falsch | Verwendet ebenfalls `wakecamp/v1` Namespace (nicht `wcr/v1`) | Die Screenshot-API in `rest-screenshot.php` registriert ihre Routen unter `wakecamp/v1/screenshot/*`, nicht `wcr/v1`. **Konsistenz mit echtem REST-Namespace!** |

---

## 🔴 KRITISCHSTE FEHLER

### 1️⃣ REST-Namespace falsch (`wcr/v1` → `wakecamp/v1`)

**Impact:** 🔴 **HOCH**

**Problem:**
- Alle API-Beispiele in der Doku verwenden den falschen Namespace `wcr/v1`
- Entwickler würden API-Calls mit falscher URL schreiben
- Frontend-Code würde 404-Fehler bekommen

**Beispiel (falsch):**
```javascript
fetch('/wp-json/wcr/v1/drinks')  // ❌ 404 Not Found
```

**Beispiel (richtig):**
```javascript
fetch('/wp-json/wakecamp/v1/drinks')  // ✅ 200 OK
```

**Betroffene Dateien in Doku:**
- `ARCHITECTURE.md` → REST-API-Sektion (mehrfach)
- `README.md` → REST-API-Übersichtstabelle

---

### 2️⃣ Backend-CSS-Pfad falsch (`be/css/backend-styles.css` → `be/inc/style.css`)

**Impact:** 🔴 **HOCH**

**Problem:**
- Verzeichnis `be/css/` existiert **nicht** im Repository
- Backend-CSS liegt in `be/inc/style.css`
- Wird geladen via `<link rel="stylesheet" href="/be/inc/style.css">` in `be/inc/menu.php`

**Warum dieser Pfad?**
- `inc/` enthält alle Backend-Includes (auth, db, menu, style, debug)
- `style.css` ist Teil der Include-Logik, nicht separate Asset-Struktur

**Betroffene Dateien in Doku:**
- `ARCHITECTURE.md` → Verzeichnisstruktur
- `ARCHITECTURE.md` → CSS-Trennung-Sektion
- `README.md` → Verzeichnisstruktur

---

### 3️⃣ Nicht-existierendes Verzeichnis `be/css/` in Struktur dokumentiert

**Impact:** 🟭 **MITTEL**

**Problem:**
- Verzeichnisstruktur-Diagramme zeigen `be/css/` Verzeichnis
- Entwickler würden nach diesem Verzeichnis suchen und nicht finden
- Verwirrung: "Wo ist das CSS-Verzeichnis?"

**Lösung:**
- `be/css/` komplett aus Struktur entfernen
- Stattdessen: `be/inc/style.css` in `inc/`-Sektion auflisten

---

## ✅ KORREKTUREN UMGESETZT IN

- ✅ **README.md** (korrigiert am 7. März 2026)
  - REST-Namespace: `wakecamp/v1`
  - Backend-CSS: `be/inc/style.css`
  - Plugin-CSS: Konkrete Dateiliste
  - Strukturdiagramm als "konzeptionell" markiert

- 🔄 **ARCHITECTURE.md** (wird aktualisiert)
  - Alle 8 Korrekturen
  - Verzeichnisstruktur komplett überarbeitet
  - REST-API-Beispiele korrigiert
  - CSS-Trennung-Sektion korrigiert

---

## 📝 HINWEISE FÜR ENTWICKLER

### REST-API Namespace prüfen

**Immer `wakecamp/v1` verwenden:**
```bash
# Richtig:
curl https://domain.de/wp-json/wakecamp/v1/drinks

# Falsch:
curl https://domain.de/wp-json/wcr/v1/drinks  # 404!
```

### Backend-CSS einbinden

**Richtig:**
```php
// be/index.php oder andere Backend-Seiten
<?php include __DIR__ . '/inc/menu.php'; ?>
// menu.php lädt automatisch /be/inc/style.css
```

**Falsch:**
```html
<!-- NICHT VERWENDEN - Datei existiert nicht! -->
<link rel="stylesheet" href="/be/css/backend-styles.css">
```

### Plugin-CSS prüfen

**Es gibt keine einzelne "plugin-styles.css"!**

Stattdessen werden **modulare CSS-Dateien** via `enqueue.php` geladen:

```php
// wcr-digital-signage/includes/enqueue.php
wp_enqueue_style('wcr-ds-global', plugins_url('assets/css/wcr-ds-global.css', dirname(__FILE__)));
wp_enqueue_style('wcr-ds-components', plugins_url('assets/css/wcr-ds-components.css', dirname(__FILE__)));
// usw.
```

---

## 🚨 WICHTIG: KEINE ARCHITEKTUR-ÄNDERUNGEN

**Diese Korrekturen ändern KEINE Architektur!**

- ✅ Nur **Dokumentation** wird korrigiert
- ✅ Keine Code-Änderungen notwendig
- ✅ Keine Deployment-Änderungen
- ✅ Keine Struktur-Refactorings

**Ziel:** Dokumentation = Realität im Repository

---

**Stand:** 7. März 2026  
**Review durch:** Marcus Kempe
