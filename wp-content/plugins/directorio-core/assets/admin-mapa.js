document.addEventListener('DOMContentLoaded', function () {
    var latInput = document.getElementById('dir_lat');
    var lngInput = document.getElementById('dir_lng');
    var mapEl = document.getElementById('dir-mapa-admin');
    if (!latInput || !lngInput || !mapEl || typeof L === 'undefined') return;

    var lat = parseFloat(latInput.value);
    var lng = parseFloat(lngInput.value);
    if (isNaN(lat) || isNaN(lng)) { lat = -16.5; lng = -68.15; }

    var map = L.map(mapEl).setView([lat, lng], 15);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var marker = L.marker([lat, lng], { draggable: true }).addTo(map);

    function actualizar(pos) {
        latInput.value = pos.lat.toFixed(7);
        lngInput.value = pos.lng.toFixed(7);
    }

    marker.on('dragend', function () { actualizar(marker.getLatLng()); });
    map.on('click', function (e) {
        marker.setLatLng(e.latlng);
        actualizar(e.latlng);
    });

    // El metabox puede estar oculto al cargar: recalcular el tamaño
    setTimeout(function () { map.invalidateSize(); }, 300);
});
