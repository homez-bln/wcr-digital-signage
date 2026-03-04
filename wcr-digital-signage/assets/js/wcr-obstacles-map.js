(function(){
    function initObstaclesMap(){
        var el = document.getElementById('wcr-obstacles-map');
        if (!el) return;

        var apiUrl = (window.wcrObstaclesMap && window.wcrObstaclesMap.apiUrl) || el.getAttribute('data-api');
        if (!apiUrl) return;

        fetch(apiUrl)
            .then(function(res){ return res.ok ? res.json() : []; })
            .then(function(data){
                if (!Array.isArray(data)) return;
                data.forEach(function(o){
                    var x = parseFloat(o.pos_x);
                    var y = parseFloat(o.pos_y);
                    if (isNaN(x) || isNaN(y)) return;

                    var icon = o.icon_url || '';
                    var rot  = parseFloat(o.rotation || 0) || 0;

                    var d = document.createElement('div');
                    d.className = 'wcr-obstacle';
                    d.style.left = x + '%';
                    d.style.top  = y + '%';
                    if (icon) {
                        d.style.backgroundImage = 'url(' + icon + ')';
                    }
                    var baseTransform = 'translate(-50%, -50%)';
                    if (rot !== 0) {
                        d.style.transform = baseTransform + ' rotate(' + rot + 'deg)';
                    } else {
                        d.style.transform = baseTransform;
                    }
                    if (o.name) {
                        d.setAttribute('data-name', o.name);
                    }
                    el.appendChild(d);
                });
            })
            .catch(function(err){
                console && console.warn && console.warn('wcr-obstacles-map error', err);
            });
    }

    if (document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', initObstaclesMap);
    } else {
        initObstaclesMap();
    }
})();
