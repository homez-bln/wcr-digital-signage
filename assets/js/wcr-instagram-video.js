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
        fetch(API)
            .then(r => r.json())
            .then(data => {
                if (!data.length) return;
                videos  = data;
                current = 0;
                rebuildDots();
                playVideo(0);
            })
            .catch(console.error);
    }

    player.addEventListener('loadedmetadata', startProgress);
    player.addEventListener('ended', () => {
        current = (current + 1) % videos.length;
        // Nach letztem Clip: neu laden fuer neue Zufallsauswahl
        if (current === 0) { load(); return; }
        playVideo(current);
    });

    muteBtn.addEventListener('click', () => {
        player.muted = !player.muted;
        muteBtn.textContent = player.muted ? '🔇' : '🔊';
    });

    // Alle 30 Min neu laden
    setInterval(load, 30 * 60 * 1000);
    document.addEventListener('DOMContentLoaded', load);
})();
