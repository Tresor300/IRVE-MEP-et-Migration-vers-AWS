/**
 * clusters.js
 * ─────────────
 * Fonctionnalité 4 — Prédiction des clusters géographiques
 *
 * Responsabilités :
 *   - Appel AJAX vers predict_cluster.php (retourne JSON)
 *   - Initialisation de la carte Leaflet
 *   - Affichage des marqueurs colorés par cluster
 *   - Construction de la légende avec toggle de visibilité
 *   - Mise à jour de la barre de statistiques
 */

"use strict";

/* ── Configuration ── */
// Chemin relatif à clusters.php (même dossier php/) : le projet fonctionne
// quel que soit le dossier où il est installé sur le serveur.
const PHP_ENDPOINT = "predict_cluster.php";

/** Palette de couleurs indexées par numéro de cluster */
const CLUSTER_COLORS = [
    "#00C48C", // 0 — vert menthe
    "#FF6B6B", // 1 — corail
    "#FFD166", // 2 — jaune
    "#A78BFA", // 3 — violet
    "#38BDF8", // 4 — bleu ciel
    "#FB923C", // 5 — orange
    "#4ADE80", // 6 — vert clair
    "#F472B6", // 7 — rose
    "#FACC15", // 8 — or
    "#67E8F9", // 9 — cyan
];

/* ── Initialisation de la carte Leaflet ── */
const map = L.map("map", { center: [46.8, 2.3], zoom: 6 });


L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap France',
    maxZoom: 20
}).addTo(map);

/**
 * Crée une icône de marqueur circulaire colorée via DivIcon Leaflet.
 * @param {string} color - Couleur hexadécimale du cluster
 * @returns {L.DivIcon}
 */
function makeIcon(color) {
    return L.divIcon({
        className: "",
        html: `<div style="
            width:13px; height:13px;
            background:${color};
            border-radius:50%;
            border:2px solid rgba(255,255,255,.85);
            box-shadow:0 2px 6px rgba(0,0,0,.25);
        "></div>`,
        iconSize: [13, 13],
        iconAnchor: [6, 6],
        popupAnchor: [0, -8],
    });
}

/**
 * Récupère les données JSON depuis predict_cluster.php via fetch (AJAX).
 * Gère l'affichage des overlays de chargement et d'erreur.
 */
async function chargerClusters() {
    try {
        const reponse = await fetch(PHP_ENDPOINT);

        if (!reponse.ok) {
            throw new Error(`Erreur HTTP ${reponse.status}`);
        }

        const donnees = await reponse.json();

        if (donnees.error) {
            throw new Error(donnees.error);
        }

        afficherPoints(donnees);

    } catch (erreur) {
        document.getElementById("loading-overlay").style.display = "none";
        const overlayErreur = document.getElementById("error-overlay");
        overlayErreur.style.display = "flex";
        document.getElementById("error-text").textContent = "⚠ " + erreur.message;
    }
}

/**
 * Affiche les points de charge sur la carte avec leur couleur de cluster,
 * construit la légende et met à jour la barre de statistiques.
 * @param {Array} points - Tableau d'objets JSON retournés par predict_cluster.php
 */
function afficherPoints(points) {

    /* ── 1. Clusters distincts triés ── */
    const clustersUniques = [...new Set(points.map((p) => p.cluster))]
        .filter((c) => c !== -1)
        .sort((a, b) => a - b);

    /* ── 2. Attribution d'une couleur par cluster ── */
    const couleurParCluster = {};
    clustersUniques.forEach((c, i) => {
        couleurParCluster[c] = CLUSTER_COLORS[i % CLUSTER_COLORS.length];
    });

    /* ── 3. Comptages ── */
    const compteParCluster = {};
    const stationsVues = new Set();
    points.forEach((p) => {
        compteParCluster[p.cluster] = (compteParCluster[p.cluster] || 0) + 1;
        stationsVues.add(p.id_station);
    });

    /* ── 4. Création des couches Leaflet (une par cluster) ── */
    const couchesParCluster = {};

    points.forEach((point) => {
        if (point.cluster === -1) return; // ignorer les erreurs de prédiction

        const couleur = couleurParCluster[point.cluster];
        const marker = L.marker([point.latitude, point.longitude], {
            icon: makeIcon(couleur),
        });

        /* Popup au clic sur un marqueur */
        marker.bindPopup(
            `<div class="popup-title">⚡ ${point.nom_enseigne || "Station"}</div>
             <div class="popup-row"><b>Adresse :</b> ${point.adresse_station || "—"}</div>
             <div class="popup-row"><b>Puissance :</b> ${point.puissance_nominale ? point.puissance_nominale + " kW" : "—"}</div>
             <div class="popup-row"><b>Accès :</b> ${point.condition_acces || "—"}</div>
             <div class="popup-row"><b>Département :</b> ${point.code_departement || "—"}</div>
             <div class="popup-row"><b>ID PDC :</b> ${point.id_pdc}</div>
             <span class="popup-badge" style="background:${couleur}">Cluster ${point.cluster}</span>`,
            { maxWidth: 260 }
        );

        if (!couchesParCluster[point.cluster]) {
            couchesParCluster[point.cluster] = L.layerGroup();
        }
        couchesParCluster[point.cluster].addLayer(marker);
    });

    /* Ajouter toutes les couches à la carte */
    Object.values(couchesParCluster).forEach((couche) => couche.addTo(map));

    /* ── 5. Construction de la légende ── */
    const legendList = document.getElementById("legend-list");

    clustersUniques.forEach((c) => {
        const couleur = couleurParCluster[c];
        const nb      = compteParCluster[c] || 0;

        const item = document.createElement("div");
        item.className = "legend-item";
        item.innerHTML = `
            <div class="legend-dot" style="background:${couleur}"></div>
            <div>
                <div class="legend-name">Cluster ${c}</div>
                <div class="legend-count">${nb} point${nb > 1 ? "s" : ""}</div>
            </div>
            <span class="legend-toggle">👁</span>
        `;

        /* Toggle visibilité au clic sur un élément de légende */
        item.addEventListener("click", () => {
            const couche = couchesParCluster[c];
            if (map.hasLayer(couche)) {
                map.removeLayer(couche);
                item.classList.add("hidden-cluster");
                item.querySelector(".legend-toggle").textContent = "🚫";
            } else {
                map.addLayer(couche);
                item.classList.remove("hidden-cluster");
                item.querySelector(".legend-toggle").textContent = "👁";
            }
        });

        legendList.appendChild(item);
    });

    /* ── 6. Mise à jour de la barre de statistiques ── */
    document.getElementById("stat-total").textContent    = points.length;
    document.getElementById("stat-clusters").textContent = clustersUniques.length;
    document.getElementById("stat-stations").textContent = stationsVues.size;
    document.getElementById("stats-bar").style.display   = "flex";

    /* ── 7. Masquer l'overlay de chargement ── */
    document.getElementById("loading-overlay").style.display = "none";
}

/* ── Point d'entrée ── */
chargerClusters();
