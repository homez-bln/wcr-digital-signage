(function () {
    const API    = wcrInstagram.restUrl + 'wakecamp/v1/instagram';
    const RELOAD = (wcrInstagram.refresh || 10) * 60 * 1000;
    const HASHTAG = wcrInstagram.hashtag || 'wakecampruhlsdorf';
    const NEW_MS  = (wcrInstagram.newHours || 2) * 3600 * 1000;

    // ─── Platzhalter-Helfer ────────────────────────────────────────────────

    function showPlaceholder(type) {
        const grid = document.getElementById('wcr-insta-grid');
        if (!grid) return;
        grid.innerHTML = '';

        const wrap = document.getElementById('wcr-insta-placeholder');
        if (wrap) wrap.remove();

        const ph = document.createElement('div');
        ph.id = 'wcr-insta-placeholder';
        ph.className = 'wcr-insta-placeholder wcr-insta-placeholder--' + type;

        const icons = { no_token: '🔑', no_posts: '📭', error: '⚠️' };
        const titles = {
            no_token: 'Instagram nicht eingerichtet',
            no_posts: 'Keine Posts verfügbar',
            error:    'Feed konnte nicht geladen werden'
        };
        const subs = {
            no_token: 'Token & User-ID in den DS-Einstellungen hinterlegen',
            no_posts: 'Momentan sind keine Beiträge im Feed vorhanden',
            error:    'Bitte Verbindung & Token prüfen'
        };

        ph.innerHTML = `
            <div class="wcr-insta-ph-inner">
                <div class="wcr-insta-ph-icon">${icons[type] || '📷'}</div>
                <div class="wcr-insta-ph-title">${titles[type] || 'Feed nicht verfügbar'}</div>
                <div class="wcr-insta-ph-sub">${subs[type] || ''}</div>
                <div class="wcr-insta-ph-hashtag">#${HASHTAG}</div>
            </div>`;

        // Dummy-Kacheln als Hintergrund
        const dummyGrid = document.createElement('div');
        dummyGrid.className = 'wcr-insta-ph-dummy-grid';
        for (let i = 0; i < 8; i++) {
            const tile = document.createElement('div');
            tile.className = 'wcr-insta-ph-tile';
            tile.innerHTML = '<span>📷</span>';
            dummyGrid.appendChild(tile);
        }
        ph.prepend(dummyGrid);

        // Grid-Container durch Placeholder ersetzen
        grid.parentNode.insertBefore(ph, grid);
        grid.style.display = 'none';
    }

    function restoreGrid() {
        const ph = document.getElementById('wcr-insta-placeholder');
        if (ph) ph.remove();
        const grid = document.getElementById('wcr-insta-grid');
        if (grid) grid.style.display = '';
    }

    // ─── Post bauen ───────────────────────────────────────────────────────

    function timeAgo(iso) {
        const s = Math.floor((Date.now() - new Date(iso)) / 1000);
        if (s < 60)    return 'Gerade';
        if (s < 3600)  return Math.floor(s / 60) + ' Min';
        if (s < 86400) return Math.floor(s / 3600) + ' Std';
        return Math.floor(s / 86400) + ' T';
    }

    function isNew(iso) { return Date.now() - new Date(iso) < NEW_MS; }

    function buildPost(p) {
        const el = document.createElement('div');
        el.className = 'wcr-insta-post';

        if (p.media_type === 'VIDEO') {
            const vid = document.createElement('video');
            vid.autoplay = true;
            vid.muted    = true;
            vid.loop     = true;
            vid.playsInline = true;
            vid.src = p.media_url || '';
            if (p.thumbnail_url) vid.poster = p.thumbnail_url;
            // Fallback wenn Video nicht lädt
            vid.onerror = () => applyTileFallback(el);
            el.appendChild(vid);
        } else {
            const img = document.createElement('img');
            img.alt     = '';
            img.loading = 'lazy';
            img.src     = p.media_url || p.thumbnail_url || '';
            // Fallback wenn Bild nicht lädt
            img.onerror = () => applyTileFallback(el);
            if (!img.src) applyTileFallback(el);
            el.appendChild(img);
        }

        const typeIcon = p.media_type === 'VIDEO' ? '▶' : p.media_type === 'CAROUSEL_ALBUM' ? '⊞' : '';
        if (typeIcon)
            el.insertAdjacentHTML('beforeend', `<span class="wcr-insta-badge-type">${typeIcon}</span>`);

        if (isNew(p.timestamp))
            el.insertAdjacentHTML('beforeend', `<span class="wcr-insta-badge-new">Neu</span>`);

        if (wcrInstagram.showUser) {
            const user = p.username ? `@${p.username}` : `#${HASHTAG}`;
            el.insertAdjacentHTML('beforeend', `
                <div class="wcr-insta-overlay">
                    <span class="wcr-insta-username">${user}</span>
                    <span class="wcr-insta-time">${timeAgo(p.timestamp)}</span>
                </div>`);
        }

        el.addEventListener('click', () => window.open(p.permalink, '_blank'));
        return el;
    }

    // Einzelne Kachel als Fallback rendern (kein Bild vorhanden)
    function applyTileFallback(tile) {
        tile.querySelectorAll('img, video').forEach(m => m.remove());
        tile.classList.add('wcr-insta-post--fallback');
        if (!tile.querySelector('.wcr-insta-tile-fallback')) {
            tile.insertAdjacentHTML('afterbegin',
                '<div class="wcr-insta-tile-fallback"><span>📷</span><p>Bild nicht verfügbar</p></div>');
        }
    }

    // ─── Render & Load ─────────────────────────────────────────────────────

    function render(posts) {
        const grid = document.getElementById('wcr-insta-grid');
        if (!grid) return;

        if (!posts || !posts.length) {
            showPlaceholder('no_posts');
            return;
        }

        restoreGrid();
        grid.innerHTML = '';
        posts.slice(0, wcrInstagram.maxPosts || 8).forEach(p => grid.appendChild(buildPost(p)));
    }

    function load() {
        // Kein Token gesetzt? Sofort Placeholder
        if (!wcrInstagram.hasToken) {
            showPlaceholder('no_token');
            return;
        }

        fetch(API)
            .then(r => r.json())
            .then(data => {
                if (Array.isArray(data)) render(data);
                else showPlaceholder('error');
            })
            .catch(() => showPlaceholder('error'));
    }

    document.addEventListener('DOMContentLoaded', load);
    setInterval(load, RELOAD);

    // ── Wochenbest ─────────────────────────────────────────────────────────
    if (wcrInstagram.weeklyBest) {
        setInterval(() => {
            const now = new Date();
            if (now.getDay() === 0 && now.getHours() === 10 && now.getMinutes() === 0) {
                fetch(wcrInstagram.restUrl + 'wakecamp/v1/instagram/weekly-best')
                    .then(r => r.json())
                    .then(post => {
                        if (!post || !post.media_url) return;
                        const overlay = document.getElementById('wcr-insta-weekly');
                        if (!overlay) return;
                        overlay.querySelector('img').src = post.media_url;
                        overlay.querySelector('.wcr-insta-weekly-user').textContent =
                            post.username ? `@${post.username}` : `#${HASHTAG}`;
                        overlay.style.display = 'flex';
                        setTimeout(() => { overlay.style.display = 'none'; }, 60000);
                    });
            }
        }, 60000);
    }
})();
