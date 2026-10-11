document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('dir-mapa-front');
    if (!el || typeof L === 'undefined') return;

    var negocios = window.DIR_NEGOCIOS || [];
    var map = L.map(el).setView([-16.5, -68.15], 12);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var puntos = [];
    negocios.forEach(function (n) {
        var marker = L.marker([n.lat, n.lng]).addTo(map);

        // Se construye con textContent para evitar inyección de HTML
        var popup = document.createElement('div');
        var a = document.createElement('a');
        a.href = n.url;
        a.textContent = n.titulo;
        var p = document.createElement('p');
        p.textContent = n.dir || '';
        popup.appendChild(a);
        popup.appendChild(p);

        marker.bindPopup(popup);
        puntos.push([n.lat, n.lng]);
    });

    if (puntos.length) {
        map.fitBounds(puntos, { padding: [30, 30], maxZoom: 16 });
    }
});
