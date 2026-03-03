(function () {
    const API     = wcrInstagramVideo.restUrl + 'wakecamp/v1/instagram/videos';
    const HASHTAG = wcrInstagramVideo.hashtag || 'wakecampruhlsdorf';

    let videos  = [];
    let current = 0;

    const player   = document.getElementById('wcr-iv-player');
    const fadeEl   = document.querySelector('.wcr-iv-fade');
    const username = document.getElementById('wcr-iv-username');
    const timeEl   = document.getElementById('wcr-iv-time');
    const counter  = document.getElementById('wcr-iv-counter');
    const muteBtn  = document.getElementById('wcr-iv-mute');

    // ─── Placeholder ───────────────────────────────────────────────────────

    function showVideoPlaceholder(type) {
        const wrap = document.querySelector('.wcr-iv-wrap');
        if (!wrap) return;

        // Vorhandenen Placeholder entfernen
        const old = wrap.querySelector('.wcr-iv-placeholder');
        if (old) old.remove();

        if (player) player.style.visibility = 'hidden';

        const titles = {
            no_token: 'Instagram nicht eingerichtet',
            no_videos: 'Keine Videos verfügbar',
            error: 'Feed konnte nicht geladen werden'
        };
        const subs = {
            no_token: 'Token & User-ID in den DS-Einstellungen hinterlegen',
            no_videos: 'Aktuell sind keine Videos im Pool vorhanden',
            error: 'Bitte Verbindung & Token prüfen'
        };
        const icons = { no_token: '🔑', no_videos: '🎬', error: '⚠️' };

        const ph = document.createElement('div');
        ph.className = 'wcr-iv-placeholder wcr-iv-placeholder--' + type;
        ph.innerHTML = `
            <div class="wcr-iv-ph-bg"></div>
            <div class="wcr-iv-ph-inner">
                <div class="wcr-iv-ph-icon">${icons[type] || '🎬'}</div>
                <div class="wcr-iv-ph-title">${titles[type] || 'Video nicht verfügbar'}</div>
                <div class="wcr-iv-ph-sub">${subs[type] || ''}</div>
                <div class="wcr-iv-ph-hashtag">#${HASHTAG}</div>
            </div>`;
        wrap.appendChild(ph);
    }

    function clearVideoPlaceholder() {
        const ph = document.querySelector('.wcr-iv-placeholder');
        if (ph) ph.remove();
        if (player) player.style.visibility = '';
    }

    // ─── Helfer ───────────────────────────────────────────────────────────

    function getDots() { return document.querySelectorAll('.wcr-iv-dot'); }

    function timeAgo(iso) {
        const s = Math.floor((Date.now() - new Date(iso)) / 1000);
        if (s < 60)    return 'Gerade eben';
        if (s < 3600)  return Math.floor(s / 60) + ' Min';
        if (s < 86400) return Math.floor(s / 3600) + ' Std';
        return Math.floor(s / 86400) + ' Tag' + (Math.floor(s / 86400) > 1 ? 'e' : '');
    }

    function updateDots() {
        getDots().forEach((d, i) => {
            d.classList.remove('active', 'done');
            d.querySelector('.wcr-iv-dot-fill').style.cssText = '';
            if (i < current) {
                d.classList.add('done');
                d.querySelector('.wcr-iv-dot-fill').style.width = '100%';
            }
            if (i === current) d.classList.add('active');
        });
    }

    function startProgress() {
        const fill = getDots()[current]?.querySelector('.wcr-iv-dot-fill');
        if (!fill || !player.duration) return;
        const dur = player.duration * 1000;
        fill.style.transition = `width ${dur}ms linear`;
        fill.style.width = '100%';
    }

    function rebuildDots() {
        const wrap = document.querySelector('.wcr-iv-dots');
        if (!wrap) return;
        wrap.innerHTML = '';
        videos.forEach((_, i) => {
            const d = document.createElement('div');
            d.className = 'wcr-iv-dot' + (i === 0 ? ' active' : '');
            d.innerHTML = '<div class="wcr-iv-dot-fill"></div>';
            wrap.appendChild(d);
        });
    }

    function playVideo(index) {
        if (!videos[index]) return;
        const v = videos[index];
        fadeEl.classList.add('active');
        setTimeout(() => {
            player.src    = v.media_url || v.thumbnail_url || '';
            player.poster = v.thumbnail_url || '';

            // Fallback wenn Video-URL leer oder fehlschlägt
            player.onerror = () => {
                showVideoPlaceholder('error');
            };

            username.textContent = v.username ? `@${v.username}` : `#${HASHTAG}`;
            timeEl.textContent   = timeAgo(v.timestamp);
            counter.textContent  = `${index + 1} / ${videos.length}`;
            updateDots();
            player.load();
            player.play().catch(() => {});
            fadeEl.classList.remove('active');
        }, 600);
    }

    function load() {
        // Kein Token?
        if (!wcrInstagramVideo.hasToken) {
            showVideoPlaceholder('no_token');
            return;
        }

        fetch(API)
            .then(r => r.json())
            .then(data => {
                if (!Array.isArray(data) || !data.length) {
                    showVideoPlaceholder('no_videos');
                    return;
                }
                clearVideoPlaceholder();
                videos  = data;
                current = 0;
                rebuildDots();
                playVideo(0);
            })
            .catch(() => showVideoPlaceholder('error'));
    }

    if (player) {
        player.addEventListener('loadedmetadata', startProgress);
        player.addEventListener('ended', () => {
            current = (current + 1) % videos.length;
            if (current === 0) { load(); return; }
            playVideo(current);
        });
    }

    if (muteBtn) {
        muteBtn.addEventListener('click', () => {
            player.muted = !player.muted;
            muteBtn.textContent = player.muted ? '🔇' : '🔊';
        });
    }

    setInterval(load, 30 * 60 * 1000);
    document.addEventListener('DOMContentLoaded', load);
})();
