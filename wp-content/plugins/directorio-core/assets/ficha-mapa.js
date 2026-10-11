document.addEventListener('DOMContentLoaded', function () {
    if (typeof L === 'undefined') return;

    document.querySelectorAll('.dir-mapa-ficha').forEach(function (el) {
        var lat = parseFloat(el.dataset.lat);
        var lng = parseFloat(el.dataset.lng);
        if (isNaN(lat) || isNaN(lng)) return;

        var map = L.map(el).setView([lat, lng], 16);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        L.marker([lat, lng]).addTo(map);
    });
});
