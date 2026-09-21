// Fichier : js/map.js
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Initialisation de la carte Leaflet
    const map = L.map('map', {zoomControl: false}).setView([46.603354, 1.888334], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    L.control.zoom({ position: 'bottomleft' }).addTo(map);

    // 2. Appel AJAX vers notre API PHP
    fetch('php/get_points.php')
        .then(async response => {
            if (!response.ok) {
                throw new Error("Le fichier php/get_points.php est introuvable.");
            }
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (err) {
                throw new Error("Erreur PHP : Le serveur ne renvoie pas du JSON valide.");
            }
        })
        .then(data => {
            const tableBody = document.getElementById('points-table-body');
            tableBody.innerHTML = ''; // On vide le message de chargement

            if(data.error) {
                // On met colspan 6 car on va avoir 6 colonnes au final
                tableBody.innerHTML = `<tr><td colspan="6" style="color:red; text-align:center;"><b>Erreur BDD :</b> ${data.error}</td></tr>`;
                return;
            }

            // 3. Boucle sur chaque borne récupérée
            data.forEach(point => {
                // On prépare les variables (elles seront 'N/A' tant qu'on n'a pas fait l'étape PHP)
                const puissance = point.puissance ? point.puissance + " kW" : "N/A";
                const prise = point.prise ? point.prise : "N/A";
                const statut = point.statut ? point.statut : "N/A";

                if (point.latitude && point.longitude) {
                    // Mise à jour de l'info-bulle avec les nouvelles données
                    const popupContent = `
                        <div style="font-family: 'Assistant', sans-serif; min-width: 180px;">
                            <h3 style="color: #12255B; margin-bottom: 5px;">${point.adresse_station || 'Station inconnue'}</h3>
                            <p style="margin: 2px 0;"><b>Enseigne:</b> ${point.nom_enseigne || 'N/A'}</p>
                            <p style="margin: 2px 0;"><b>Puissance:</b> ${puissance}</p>
                            <p style="margin: 2px 0;"><b>Prise:</b> ${prise}</p>
                            <p style="margin: 2px 0;"><b>Statut:</b> ${statut}</p>
                        </div>
                    `;
                    L.marker([point.latitude, point.longitude]).addTo(map).bindPopup(popupContent);
                }

                // Mise à jour du tableau avec 6 colonnes
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${point.id_station || 'N/A'}</td>
                    <td>${point.adresse_station || 'N/A'}</td>
                    <td>${point.nom_enseigne || 'N/A'}</td>
                    <td>${puissance}</td>
                    <td>${prise}</td>
                    <td>${statut}</td>
                `;
                tableBody.appendChild(tr);
            });
        })
        .catch(error => {
            console.error("Détail de l'erreur :", error);
            const tableBody = document.getElementById('points-table-body');
            if (tableBody) {
                tableBody.innerHTML = `<tr><td colspan="6" style="color:red; text-align:center; padding:20px;">
                    <b>Le chargement a échoué !</b><br>${error.message}
                </td></tr>`;
            }
        });
});