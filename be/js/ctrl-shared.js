/* ctrl-shared.js — gemeinsames JS für alle Produkt-Seiten
 * Eingebunden per PHP include (wegen TABLE-Variable)
 * FIX v6: War in jeder ctrl-Datei 1:1 kopiert (5× identischer Code)
 */
function toggleGroup(h) {
    const key  = h.dataset.group;
    const body = document.querySelector('[data-group-body="' + key + '"]');
    if (!body) return;
    const collapsed = body.classList.toggle('collapsed');
    h.classList.toggle('collapsed', collapsed);
    // Sync für food.php (doppelte Header list/gallery)
    document.querySelectorAll('.group-header[data-group="' + key + '"]').forEach(el => {
        if (el !== h) el.classList.toggle('collapsed', collapsed);
    });
    localStorage.setItem(key, collapsed ? '1' : '0');
}

function setView(view) {
    const c = document.getElementById('items-container');
    if (!c) return;
    if (view === 'gallery') {
        c.classList.replace('view-list', 'view-gallery');
        document.getElementById('btn-list').classList.remove('active');
        document.getElementById('btn-gallery').classList.add('active');
    } else {
        c.classList.replace('view-gallery', 'view-list');
        document.getElementById('btn-gallery').classList.remove('active');
        document.getElementById('btn-list').classList.add('active');
    }
    localStorage.setItem('viewPref_' + TABLE, view);
}

function handleCardClick(e, nr) {
    const c = document.getElementById('items-container');
    if (!c || !c.classList.contains('view-gallery')) return;
    if (e.target.tagName === 'INPUT') return;
    const cb = document.getElementById('cb-' + nr);
    if (cb) { cb.checked = !cb.checked; upd(cb, 'toggle'); }
}

function upd(el, mode) {
    const nr  = el.getAttribute('data-nr');
    const val = mode === 'toggle' ? (el.checked ? '1' : '0') : el.value;
    if (mode === 'toggle') {
        const card = document.getElementById('card-' + nr);
        if (card) card.classList.toggle('card-off', !el.checked);
    }
    const s = document.getElementById('s-' + nr);
    if (!s) return;
    s.textContent = '…'; s.className = 'status-msg visible';
    fetch('/be/update_ticket.php', {
        method : 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body   : 'table=' + TABLE + '&nummer=' + nr + '&mode=' + mode + '&value=' + encodeURIComponent(val)
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            s.textContent = 'OK'; s.className = 'status-msg visible success';
            setTimeout(() => { s.textContent = ''; s.className = 'status-msg'; }, 1500);
        } else {
            s.textContent = 'Fehler'; s.className = 'status-msg visible error';
            console.error(d);
        }
    })
    .catch(() => { s.textContent = 'Err'; s.className = 'status-msg visible error'; });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.group-header').forEach(h => {
        const key = h.dataset.group;
        if (localStorage.getItem(key) === '1') {
            h.classList.add('collapsed');
            const b = document.querySelector('[data-group-body="' + key + '"]');
            if (b) b.classList.add('collapsed');
        }
    });
    const pref = localStorage.getItem('viewPref_' + TABLE);
    if (pref === 'gallery') setView('gallery');
});
