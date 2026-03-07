# Archivierungs-Historie

Dieses Dokument protokolliert alle Archivierungs- und Löschvorgänge im Backend.

## 2026-03-07 – Initiale Archivierung

### Hintergrund
Security-Audit des Backend-Systems hat veraltete Dateien ohne aktive Verwendung identifiziert.
Ziel: Aktive Codebasis sauber halten, wertvollen Code als Referenz bewahren.

### Archiviert

#### `functions/upload_handler.php` → `_deprecated/functions/upload_handler.php`
- **Grund:** Ersetzt durch `api/save_opening_hours.php` (2026-02) + `api/upload_image.php` (2026-03)
- **Keine Referenzen:** Code-Suche ergab keine aktiven Aufrufe
- **Sicherheitsprobleme:** Kein Login-Check, kein CSRF-Schutz, direkter POST-Handler
- **Wert:** Enthält vollständige Portrait-Upload-Logik (1080×1920 Cover-Mode)
- **Original-SHA:** [wird beim Archivieren eingefügt]
- **Archiv-Commit:** [wird beim Archivieren eingefügt]

#### `functions/times_data.php` → `_deprecated/functions/times_data.php`
- **Grund:** Keine aktive Verwendung gefunden, möglicherweise inline in `ctrl/times.php` integriert
- **Keine Referenzen:** Code-Suche ergab keine aktiven Aufrufe
- **Sicherheitsprobleme:** Keine (read-only Helper-Funktion)
- **Wert:** Modulare Daten-Loader-Logik (Sunset-API, DB, Zeitraum-Generierung)
- **Original-SHA:** [wird beim Archivieren eingefügt]
- **Archiv-Commit:** [wird beim Archivieren eingefügt]

### Gelöscht

#### `api/save_map_config.php`
- **Grund:** Vollständig redundant - identische Funktion in `ctrl/obstacles.php` (Zeile 117-141)
- **Keine Referenzen:** Code-Suche ergab keine aktiven Aufrufe
- **Redundanz:** 1:1 Duplikat der Map-Config-Speicherung (lat/lon/zoom → WordPress-API)
- **Kein Archiv:** Code hat keinen Referenzwert (nur Boilerplate-Proxy)
- **Letzter Commit:** [wird beim Löschen eingefügt]
- **Wiederherstellung:** `git checkout [SHA] -- be/api/save_map_config.php`

### Tests durchgeführt
- ✅ `ctrl/obstacles.php` – Map-Config speichern (Landscape + Portrait)
- ✅ `ctrl/times.php` – Öffnungszeiten anzeigen und bearbeiten
- ✅ `api/save_opening_hours.php` – Öffnungszeiten + Foto-Upload
- ✅ `api/upload_image.php` – Produkt-Bilder hochladen

### Zusammenfassung
- **3 Dateien** bearbeitet (2 archiviert, 1 gelöscht)
- **0 funktionale Probleme** nach Archivierung
- **Codebasis bereinigt** – nur aktive Dateien in Haupt-Verzeichnissen
- **Wertvoller Code bewahrt** – Referenz für zukünftige Features

---

## Template für zukünftige Einträge

```markdown
## YYYY-MM-DD – [Kurzbeschreibung]

### Archiviert

#### `pfad/datei.php` → `_deprecated/pfad/datei.php`
- **Grund:** [Warum archiviert?]
- **Keine Referenzen:** [Code-Suche Ergebnis]
- **Sicherheitsprobleme:** [Bekannte Issues]
- **Wert:** [Warum aufbewahrt?]
- **Original-SHA:** [git rev-parse HEAD:pfad/datei.php]
- **Archiv-Commit:** [git log --oneline -1]

### Gelöscht

#### `pfad/datei.php`
- **Grund:** [Warum gelöscht?]
- **Keine Referenzen:** [Code-Suche Ergebnis]
- **Redundanz:** [Mit was redundant?]
- **Letzter Commit:** [git log --oneline -1 -- pfad/datei.php]
- **Wiederherstellung:** `git checkout [SHA] -- pfad/datei.php`

### Tests durchgeführt
- ✅ [Test 1]
- ✅ [Test 2]
```
