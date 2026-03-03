'use strict';

function updateTime(input, date, col) {
    input.classList.remove('is-fallback');
    input.classList.add('saving');
    sendData(date, col, input.value,
        () => { input.classList.remove('saving'); input.classList.add('saved'); setTimeout(() => input.classList.remove('saved'), 1200); },
        () => { input.classList.remove('saving'); input.classList.add('error'); }
    );
}

function clearTimes(date) {
    if (!confirm('Start- und Endzeit für ' + date + ' wirklich löschen?')) return;
    const startInput = document.getElementById('start-' + date);
    const endInput   = document.getElementById('end-'   + date);
    const btn        = document.querySelector('[onclick="clearTimes(\'' + date + '\')"]');
    if (startInput) { startInput.value = ''; startInput.classList.add('saving'); }
    if (endInput)   { endInput.value   = ''; endInput.classList.add('saving'); endInput.classList.add('is-fallback'); }
    sendData(date, 'start_time', '', () => {
        if (startInput) { startInput.classList.remove('saving'); startInput.classList.add('saved'); setTimeout(() => startInput.classList.remove('saved'), 1200); }
        sendData(date, 'end_time', '', () => {
            if (endInput) { endInput.classList.remove('saving'); endInput.classList.add('saved'); setTimeout(() => endInput.classList.remove('saved'), 1200); }
            if (btn) btn.remove();
        }, (err) => { if (endInput) endInput.classList.add('error'); alert('Fehler beim Löschen der Endzeit: ' + err); });
    }, (err) => { if (startInput) startInput.classList.add('error'); alert('Fehler beim Löschen der Startzeit: ' + err); });
}

function toggleCourse(date, col, toggleBtn) {
    const pill      = toggleBtn.closest('.course-pill');
    const wasActive = pill.classList.contains('active');
    const newVal    = wasActive ? 0 : 1;
    const textInput = pill.querySelector('.course-input');
    const textVal   = textInput ? textInput.value : '';
    const textCol   = col + '_text';
    const startVal  = document.getElementById('start-' + date)?.value ?? '';
    const endVal    = document.getElementById('end-'   + date)?.value ?? '';
    pill.classList.toggle('active');
    toggleBtn.style.opacity = '0.5';
    sendData(date, col, newVal, () => {
        toggleBtn.style.opacity = '1';
        const fallback = textInput ? (textInput.dataset.fallback || '') : '';
        const saveText = newVal ? textVal : '';
        sendData(date, textCol, saveText, () => {
            if (!newVal && textInput) textInput.value = fallback;
        }, (err) => { console.warn('Text-Speicherfehler: ' + err); });
    }, (err) => {
        toggleBtn.style.opacity = '1';
        pill.classList.toggle('active');
        alert('Fehler beim Speichern: ' + err);
    }, { preserve_start_time: startVal, preserve_end_time: endVal });
}

function updateCourseText(input, date, col) {
    input.style.color = '#ccc';
    sendData(date, col, input.value,
        () => { input.style.color = input.closest('.course-pill').classList.contains('active') ? '#333' : '#aaa'; },
        () => { input.style.color = 'red'; alert('Speicherfehler beim Kurstext'); }
    );
}

function sendData(date, col, val, onSuccess, onError, extra = {}) {
    fetch('../api/save_opening_hours.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ datum: date, field: col, value: val, ...extra })
    })
    .then(r => r.json())
    .then(data => { if (data.success) onSuccess(); else onError(data.error || 'Unbekannter Fehler'); })
    .catch(err => onError(err.message || err));
}

/* ================================================================
   Screenshot: Seite öffnet sich selbst mit ?wcr_screenshot=1,
   macht html2canvas intern (same-origin!) und schickt JPG ans BE.
   BE-Fenster empfängt postMessage und startet Download.
   ================================================================ */
function downloadAsJPG() {
    const btn = event.target;
    btn.textContent = 'Lädt…';
    btn.disabled    = true;

    // Popup öffnen — 1080×1920, zentriert
    const pw = 1080, ph = 600; // sichtbare Höhe begrenzen
    const left = Math.round((screen.width  - pw) / 2);
    const top  = Math.round((screen.height - ph) / 2);
    const popup = window.open(
        'https://wcr-webpage.de/oeffnungszeiten-story/?wcr_screenshot=1',
        'wcr_screenshot',
        'width=' + pw + ',height=' + ph + ',left=' + left + ',top=' + top
    );

    if (!popup) {
        alert('Popup wurde blockiert — bitte Popup-Blocker für diese Seite erlauben.');
        btn.textContent = 'Als JPG';
        btn.disabled    = false;
        return;
    }

    // Auf Signal von der Seite warten
    function onMessage(e) {
        if (!e.data || !e.data.wcr_screenshot_done) return;
        window.removeEventListener('message', onMessage);
        btn.textContent = 'Als JPG';
        btn.disabled    = false;

        // Direkter Download des gespeicherten Bildes
        const a = document.createElement('a');
        a.href     = e.data.url + '?t=' + Date.now();
        a.download = 'oeffnungszeiten.jpg';
        a.click();
    }
    window.addEventListener('message', onMessage);

    // Timeout falls Popup geschlossen wird ohne Signal
    setTimeout(function () {
        window.removeEventListener('message', onMessage);
        if (!popup.closed) popup.close();
        btn.textContent = 'Als JPG';
        btn.disabled    = false;
    }, 20000);
}

function toggleClosed(date, btn) {
    const wasClosed = btn.classList.contains('active');
    const newVal    = wasClosed ? 0 : 1;
    btn.style.opacity = '0.5';
    sendData(date, 'is_closed', newVal, () => {
        btn.style.opacity = '1';
        btn.classList.toggle('active');
        btn.textContent = newVal ? '🔓' : '🔒';
        btn.title       = newVal ? 'Wieder öffnen' : 'Als geschlossen markieren';
        const cell       = btn.closest('.cell');
        const existBadge = cell.querySelector('.badge-closed');
        if (newVal) {
            cell.classList.add('cell-closed');
            if (!existBadge) {
                const badge = document.createElement('span');
                badge.className   = 'badge-closed';
                badge.textContent = 'Geschlossen';
                cell.querySelector('div').appendChild(badge);
            }
            ['start', 'end'].forEach(t => { const i = document.getElementById(t + '-' + date); if (i) i.disabled = true; });
        } else {
            cell.classList.remove('cell-closed');
            if (existBadge) existBadge.remove();
            ['start', 'end'].forEach(t => { const i = document.getElementById(t + '-' + date); if (i) i.disabled = false; });
        }
    }, (err) => { btn.style.opacity = '1'; alert('Fehler: ' + err); });
}
