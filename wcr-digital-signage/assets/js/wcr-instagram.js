(function () {
    const API    = wcrInstagram.restUrl + 'wakecamp/v1/instagram';
    const RELOAD = (parseInt(wcrInstagram.refresh, 10) || 10) * 60 * 1000;
    const NEW_MS = (parseInt(wcrInstagram.newHours, 10) || 2) * 3600 * 1000;
    const SHOW_U = wcrInstagram.showUser === '1';
    const HASHTAG = wcrInstagram.hashtag || 'wakecampruhlsdorf';

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
            el.innerHTML = `<video src="${p.media_url}" autoplay muted loop playsinline poster="${p.thumbnail_url || ''}"></video>`;
        } else {
            el.innerHTML = `<img src="${p.media_url || p.thumbnail_url || ''}" alt="" loading="lazy">`;
        }
        const typeIcon = p.media_type === 'VIDEO' ? '▶' : p.media_type === 'CAROUSEL_ALBUM' ? '⊞' : '';
        if (typeIcon) el.insertAdjacentHTML('beforeend', `<span class="wcr-insta-badge-type">${typeIcon}</span>`);
        if (isNew(p.timestamp)) el.insertAdjacentHTML('beforeend', `<span class="wcr-insta-badge-new">Neu</span>`);
        if (SHOW_U) {
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

    function render(posts) {
        const grid = document.getElementById('wcr-insta-grid');
        if (!grid || !posts.length) return;
        grid.innerHTML = '';
        posts.slice(0, 8).forEach(p => grid.appendChild(buildPost(p)));
    }

    function load() {
        fetch(API)
            .then(r => r.json())
            .then(data => { if (Array.isArray(data)) render(data); })
            .catch(console.error);
    }

    document.addEventListener('DOMContentLoaded', load);
    setInterval(load, RELOAD);
})();
