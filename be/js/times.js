/**
 * DATEI: be/js/times.js
 *
 * JavaScript für die Öffnungszeiten-Verwaltung (be/ctrl/times.php)
 *
 * Funktionen:
 *  - updateTime()        → Zeit-Input speichern
 *  - toggleCourse()      → Kurs an/aus schalten (ohne Startzeit zu ändern)
 *  - updateCourseText()  → Kurs-Zeittext speichern
 *  - sendData()          → Zentrale AJAX-Funktion
 *  - downloadAsJPG()     → Vorschau-Iframe als 1080×1920 JPG exportieren
 */

'use strict';

/* ================================================================
   1. ZEIT-INPUT SPEICHERN
   ================================================================ */

/**
 * Wird aufgerufen bei onchange an Start- oder End-Zeit-Input.
 *
 * Ablauf:
 *  1. .is-fallback entfernen (Sunset-Vorausfüllung → echte Eingabe)
 *  2. Visueller Speicher-Status: saving → saved / error
 *  3. AJAX-Speicherung via sendData()
 *
 * @param {HTMLInputElement} input  Das veränderte Input-Element
 * @param {string}           date   Datum "YYYY-MM-DD"
 * @param {string}           col    DB-Feld ("start_time" oder "end_time")
 */
function updateTime(input, date, col) {
    input.classList.remove('is-fallback');
    input.classList.add('saving');

    sendData(date, col, input.value,
        () => {
            input.classList.remove('saving');
            input.classList.add('saved');
            setTimeout(() => input.classList.remove('saved'), 1200);
        },
        () => {
            input.classList.remove('saving');
            input.classList.add('error');
        }
    );
}

/**
 * clearTimes()
 * Löscht Start- und Endzeit eines Tages.
 * Leert die Inputs visuell + sendet leere Werte an DB.
 * Button verschwindet nach erfolgreichem Löschen.
 *
 * @param {string} date  Datum "YYYY-MM-DD"
 */
function clearTimes(date) {
    if (!confirm('Start- und Endzeit für ' + date + ' wirklich löschen?')) return;

    const startInput = document.getElementById('start-' + date);
    const endInput   = document.getElementById('end-'   + date);
    const btn        = document.querySelector('[onclick="clearTimes(\'' + date + '\')"]');

    // Visuell sofort leeren
    if (startInput) { startInput.value = ''; startInput.classList.add('saving'); }
    if (endInput)   { endInput.value   = ''; endInput.classList.add('saving');
                      endInput.classList.add('is-fallback'); }

    // Beide Felder löschen — sequenziell
    sendData(date, 'start_time', '',
        () => {
            if (startInput) {
                startInput.classList.remove('saving');
                startInput.classList.add('saved');
                setTimeout(() => startInput.classList.remove('saved'), 1200);
            }
            // Nach start_time → end_time löschen
            sendData(date, 'end_time', '',
                () => {
                    if (endInput) {
                        endInput.classList.remove('saving');
                        endInput.classList.add('saved');
                        setTimeout(() => endInput.classList.remove('saved'), 1200);
                    }
                    // Button ausblenden (keine Zeiten mehr gesetzt)
                    if (btn) btn.remove();
                },
                (err) => {
                    if (endInput) endInput.classList.add('error');
                    alert('Fehler beim Löschen der Endzeit: ' + err);
                }
            );
        },
        (err) => {
            if (startInput) startInput.classList.add('error');
            alert('Fehler beim Löschen der Startzeit: ' + err);
        }
    );
}










function toggleCourse(date, col, toggleBtn) {
    const pill      = toggleBtn.closest('.course-pill');
    const wasActive = pill.classList.contains('active');
    const newVal    = wasActive ? 0 : 1;

    // Text-Input in der gleichen Pille auslesen
    const textInput = pill.querySelector('.course-input');
    const textVal   = textInput ? textInput.value : '';

    // DB-Feld f�r den Text: course1 ? course1_text, course2 ? course2_text
    const textCol   = col + '_text';

    // Zeiten aus den Inputs
    const startVal  = document.getElementById('start-' + date)?.value ?? '';
    const endVal    = document.getElementById('end-'   + date)?.value ?? '';

    // Optimistisches UI-Update
    pill.classList.toggle('active');
    toggleBtn.style.opacity = '0.5';

    // 1. Kurs-Status (0/1) speichern
    sendData(date, col, newVal,
        () => {
            toggleBtn.style.opacity = '1';

            // 2. Text speichern (ON ? aktueller Text, OFF ? leer in DB, Fallback im UI)
			const fallback = textInput ? (textInput.dataset.fallback || '') : '';
			const saveText = newVal ? textVal : '';  // DB bekommt immer leer bei OFF

			sendData(date, textCol, saveText,
				() => {
					if (!newVal && textInput) {
						textInput.value = fallback;  // ? UI zeigt Fallback, DB ist leer
					}
				},
				(err) => { console.warn('Text-Speicherfehler: ' + err); }
			);

        },
        (err) => {
            // Rollback bei Fehler
            toggleBtn.style.opacity = '1';
            pill.classList.toggle('active');
            alert('Fehler beim Speichern: ' + err);
        },
        { preserve_start_time: startVal, preserve_end_time: endVal }  // ? Bug-Fix
    );
}


/* ================================================================
   3. KURS-TEXT SPEICHERN
   ================================================================ */

/**
 * Speichert den Kurs-Zeittext (z.B. "09:00 - 11:00").
 *
 * @param {HTMLInputElement} input  Das Textfeld
 * @param {string}           date   Datum "YYYY-MM-DD"
 * @param {string}           col    DB-Feld ("course1_text" oder "course2_text")
 */
function updateCourseText(input, date, col) {
    input.style.color = '#ccc'; // Visuelles "Laden"-Feedback

    sendData(date, col, input.value,
        () => {
            // Farbe je nach Aktiv-Zustand der Pille
            input.style.color = input.closest('.course-pill').classList.contains('active')
                ? '#333'
                : '#aaa';
        },
        () => {
            input.style.color = 'red';
            alert('Speicherfehler beim Kurstext');
        }
    );
}

/* ================================================================
   4. ZENTRALE AJAX-FUNKTION
   ================================================================ */

/**
 * Sendet ein Feld-Update an save_opening_hours.php.
 *
 * Request-Body (JSON):
 * {
 *   datum:               "YYYY-MM-DD",
 *   field:               "start_time" | "end_time" | "course1" | ...,
 *   value:               string | number,
 *   preserve_start_time: string,   // nur bei toggleCourse()
 *   preserve_end_time:   string    // nur bei toggleCourse()
 * }
 *
 * @param {string}   date       Datum "YYYY-MM-DD"
 * @param {string}   col        DB-Feldname (Whitelist im PHP)
 * @param {*}        val        Neuer Wert
 * @param {Function} onSuccess  Callback bei success: true
 * @param {Function} onError    Callback bei Fehler (erhält Fehlermeldung)
 * @param {Object}   extra      Optionale Zusatzfelder (z.B. preserve_*)
 */
function sendData(date, col, val, onSuccess, onError, extra = {}) {
    fetch('../api/save_opening_hours.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ datum: date, field: col, value: val, ...extra })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            onSuccess();
        } else {
            onError(data.error || 'Unbekannter Fehler');
        }
    })
    .catch(err => onError(err.message || err));
}

/* ================================================================
   5. JPG-EXPORT (html2canvas)
   ================================================================ */

/**
 * Rendert den Vorschau-Iframe als 1080×1920 JPG und startet Download.
 *
 * Ablauf:
 *  1. Iframe temporär auf 1080×1920px setzen
 *  2. 1s warten (DOM-Settling)
 *  3. Elementor "loaded"-Klasse setzen + CSS-Links aktivieren
 *  4. 2s warten (Render)
 *  5. html2canvas → Canvas → toDataURL → Download-Link
 *  6. Iframe-Größe wiederherstellen
 *
 * Benötigt html2canvas (CDN-Script im HTML geladen).
 */
function downloadAsJPG() {
    const btn      = event.target;
    const origText = btn.textContent;
    btn.textContent = 'Erstelle Bild…';
    btn.disabled    = true;

    const iframe = document.getElementById('preview-iframe');
    if (!iframe) {
        alert('Vorschau-Iframe nicht gefunden');
        btn.textContent = origText;
        btn.disabled    = false;
        return;
    }

    // Iframe auf Original-Rendermaß setzen
    const origW        = iframe.style.width;
    const origH        = iframe.style.height;
    iframe.style.width  = '1080px';
    iframe.style.height = '1920px';

    // Phase 1: DOM settling abwarten
    setTimeout(() => {
        iframe.contentWindow.focus();
        const doc = iframe.contentDocument || iframe.contentWindow.document;

        if (doc) {
            // Elementor: Seite als "geladen" markieren
            doc.body.classList.add('elementor-loaded');
            // Deferred Stylesheets sofort laden
            doc.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
                if (link.href.includes('opening-hours.css')) link.media = '';
            });
        }

        // Phase 2: Render-Zeit für Fonts/CSS abwarten
        setTimeout(() => {
            html2canvas(doc.documentElement, {
                backgroundColor : '#ffffff',
                scale           : 1,
                width           : 1080,
                height          : 1920,
                windowWidth     : 1080,
                windowHeight    : 1920,
                useCORS         : true,
                allowTaint      : false,
                // Elementor-Animationselemente ausblenden (würden leer erscheinen)
                ignoreElements  : el => el.classList.contains('elementor-invisible')
            })
            .then(canvas => {
                // Finales Canvas mit weißem Hintergrund
                const out = document.createElement('canvas');
                out.width  = 1080;
                out.height = 1920;
                const ctx  = out.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, 1080, 1920);
                ctx.drawImage(canvas, 0, 0);

                // Download auslösen
                const link    = document.createElement('a');
                const dateStr = new Date().toISOString().slice(0, 10);
                link.download = 'oeffnungszeiten_' + dateStr + '.jpg';
                link.href     = out.toDataURL('image/jpeg', 0.95);
                link.click();

                _restoreIframe(iframe, origW, origH, btn, origText);
            })
            .catch(err => {
                alert('Export-Fehler: ' + err);
                _restoreIframe(iframe, origW, origH, btn, origText);
            });

        }, 2000); // Render-Wartezeit
    }, 1000);    // DOM-Settling-Wartezeit
}

/**
 * Hilfsfunktion: Iframe-Größe + Button nach Export wiederherstellen.
 * @private
 */
function _restoreIframe(iframe, origW, origH, btn, origText) {
    iframe.style.width  = origW;
    iframe.style.height = origH;
    btn.textContent     = origText;
    btn.disabled        = false;
}
/**
 * toggleClosed()
 * Markiert einen Tag als geschlossen oder öffnet ihn wieder.
 * Deaktiviert/Aktiviert Start+Endzeit-Inputs visuell.
 *
 * @param {string}      date  Datum "YYYY-MM-DD"
 * @param {HTMLElement} btn   Der geklickte Button
 */
function toggleClosed(date, btn) {
    const wasClosed = btn.classList.contains('active');
    const newVal    = wasClosed ? 0 : 1;

    btn.style.opacity = '0.5';

    sendData(date, 'is_closed', newVal,
        () => {
            btn.style.opacity = '1';

            // Button-State updaten
            btn.classList.toggle('active');
            btn.textContent = newVal ? '🔓' : '🔒';
            btn.title       = newVal ? 'Wieder öffnen' : 'Als geschlossen markieren';

            // Datum-Zelle: Badge ein-/ausblenden
            const cell       = btn.closest('.cell');
            const existBadge = cell.querySelector('.badge-closed');

            if (newVal) {
                // Schließen: Badge hinzufügen + Inputs sperren
                cell.classList.add('cell-closed');
                if (!existBadge) {
                    const badge = document.createElement('span');
                    badge.className   = 'badge-closed';
                    badge.textContent = 'Geschlossen';
                    cell.querySelector('div').appendChild(badge);
                }
                const row = cell.parentElement;
                ['start', 'end'].forEach(type => {
                    const input = document.getElementById(type + '-' + date);
                    if (input) input.disabled = true;
                });
            } else {
                // Öffnen: Badge entfernen + Inputs freigeben
                cell.classList.remove('cell-closed');
                if (existBadge) existBadge.remove();
                ['start', 'end'].forEach(type => {
                    const input = document.getElementById(type + '-' + date);
                    if (input) input.disabled = false;
                });
            }
        },
        (err) => {
            btn.style.opacity = '1';
            alert('Fehler: ' + err);
        }
    );
}
