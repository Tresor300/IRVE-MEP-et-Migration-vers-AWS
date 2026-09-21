"use strict";

const API_BASE = "../php";

const ENDPOINT_PDC = `${API_BASE}/get_pdc.php`;
const ENDPOINT_PREDIRE = `${API_BASE}/predire_caracteristique.php`;
const ENDPOINT_RESULTAT = `${API_BASE}/get_prediction.php`;
const ENDPOINT_METRICS = `${API_BASE}/get_metrics.php`;

const FETCH_TIMEOUT_MS = 30000;
const LIGNES_PAR_PAGE = 10;

const dom = {
  tableBody: document.getElementById("tbl-body"),
  search: document.getElementById("search-pdc"),
  count: document.getElementById("table-count"),
  pagination: document.getElementById("pagination-bar"),

  errorTable: document.getElementById("zone-erreur-tableau"),
  errorGlobal: document.getElementById("zone-erreur-globale"),

  btnImplantation: document.getElementById("btn-predict-implantation"),
  btnPuissance: document.getElementById("btn-predict-puissance"),
  btnReset: document.getElementById("btn-reset"),
  btnNew: document.getElementById("btn-nouvelle-prediction"),

  results: document.getElementById("section-resultats"),

  kpi1Label: document.getElementById("kpi1-label"),
  kpi1Value: document.getElementById("kpi1-value"),
  kpi1Sub: document.getElementById("kpi1-sub"),

  kpi2Label: document.getElementById("kpi2-label"),
  kpi2Value: document.getElementById("kpi2-value"),
  kpi2Sub: document.getElementById("kpi2-sub"),

  date: document.getElementById("date-analyse"),

  confusionBlock: document.getElementById("bloc-confusion"),
  confusion: document.getElementById("conf-matrix"),

  verdict: document.getElementById("verdict-banner")
};

const state = {
  pdc: [],
  page: 1,
  selectedId: null,
  selectedName: "",
  metrics: {
    implantation: null,
    puissance: null
  }
};

async function fetchWithTimeout(url, options = {}, timeout = FETCH_TIMEOUT_MS) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeout);

  try {
    const response = await fetch(url, {
      ...options,
      signal: controller.signal,
      cache: "no-store"
    });

    clearTimeout(timer);
    return response;
  } catch (error) {
    clearTimeout(timer);

    if (error.name === "AbortError") {
      throw new Error(
        `Timeout : la requête a dépassé ${timeout / 1000} secondes.`
      );
    }

    throw new Error(`Erreur réseau : ${error.message}`);
  }
}

async function fetchJson(url) {
  const response = await fetchWithTimeout(url, { method: "GET" });
  const raw = await response.text();

  if (!response.ok) {
    throw new Error(
      `Erreur HTTP ${response.status} : ${raw || response.statusText}`
    );
  }

  try {
    return JSON.parse(raw);
  } catch {
    console.error("Réponse non JSON :", raw);
    throw new Error("Le serveur a retourné une réponse invalide.");
  }
}

function escapeHtml(value) {
  const map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;"
  };

  return String(value ?? "").replace(/[&<>"']/g, (char) => map[char]);
}

function normalise(value) {
  return String(value ?? "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/#/g, "")
    .toLowerCase()
    .trim();
}

function boolBadge(value) {
  const yes = ["1", "true", "vrai", "oui", "yes"].includes(
    normalise(value)
  );

  return `
    <span class="badge-bool ${yes ? "vrai" : "faux"}">
      ${yes ? "Oui" : "Non"}
    </span>
  `;
}

function dateFr(value) {
  if (!value) return "—";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return String(value);
  }

  return new Intl.DateTimeFormat("fr-FR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit"
  }).format(date);
}

function showError(zone, message) {
  zone.style.display = "block";

  zone.innerHTML = `
    <div class="alert-error">
      <span>⚠️</span>
      <span>${escapeHtml(message)}</span>
    </div>
  `;
}

function hideError(zone) {
  zone.style.display = "none";
  zone.innerHTML = "";
}

function setButtons() {
  const enabled = state.selectedId !== null;

  dom.btnImplantation.disabled = !enabled;
  dom.btnPuissance.disabled = !enabled;
}

function hideResults() {
  dom.results.classList.remove("visible");
}

/* =========================
   CHARGEMENT DES PDC
========================= */

async function loadPdc() {
  hideError(dom.errorTable);

  try {
    const json = await fetchJson(ENDPOINT_PDC);

    if (!json.success || !Array.isArray(json.data)) {
      throw new Error(json.error || "Réponse invalide de get_pdc.php.");
    }

    state.pdc = json.data;
    state.page = 1;

    renderTable();
  } catch (error) {
    dom.tableBody.innerHTML = `
      <tr>
        <td colspan="10" class="table-status">
          Impossible de charger les points de charge.
        </td>
      </tr>
    `;

    showError(dom.errorTable, error.message);
  }
}

function filteredPdc() {
  const term = normalise(dom.search.value);

  if (!term) {
    return state.pdc;
  }

  return state.pdc.filter((pdc) => {
    const searchable = [
      pdc.id_pdc,
      String(pdc.id_pdc ?? "").padStart(8, "0"),
      pdc.nbre_pdc,
      pdc.puissance_nominale,
      pdc.condition_acces,
      pdc.accessibilite_pmr,
      pdc.horaires,
      pdc.nom_enseigne,
      pdc.adresse_station
    ]
      .map(normalise)
      .join(" ");

    return searchable.includes(term);
  });
}

function renderTable() {
  const rows = filteredPdc();

  const totalPages = Math.max(
    1,
    Math.ceil(rows.length / LIGNES_PAR_PAGE)
  );

  if (state.page > totalPages) {
    state.page = totalPages;
  }

  const start = (state.page - 1) * LIGNES_PAR_PAGE;
  const pageRows = rows.slice(start, start + LIGNES_PAR_PAGE);

  renderRows(pageRows);
  renderPagination(rows.length, totalPages, start);
}

function renderRows(rows) {
  if (rows.length === 0) {
    dom.tableBody.innerHTML = `
      <tr>
        <td colspan="10" class="table-status">
          Aucun point de charge ne correspond à votre recherche.
        </td>
      </tr>
    `;

    return;
  }

  dom.tableBody.innerHTML = rows
    .map((pdc) => {
      const id = Number.parseInt(pdc.id_pdc, 10);
      const selected = id === state.selectedId;

      return `
        <tr
          class="${selected ? "selected" : ""}"
          data-id="${id}"
          data-name="${escapeHtml(pdc.nom_enseigne ?? "")}"
        >
          <td>
            <input
              class="radio-pdc"
              type="radio"
              name="pdc"
              value="${id}"
              ${selected ? "checked" : ""}
            />
          </td>

          <td>
            <span class="id-pdc">
              #${escapeHtml(String(id).padStart(8, "0"))}
            </span>
          </td>

          <td>${escapeHtml(pdc.nbre_pdc ?? "—")}</td>

          <td>
            ${
              pdc.puissance_nominale != null
                ? `${escapeHtml(pdc.puissance_nominale)} kW`
                : "—"
            }
          </td>

          <td>${boolBadge(pdc.prise_type_2)}</td>
          <td>${boolBadge(pdc.prise_type_combo_ccs)}</td>
          <td>${boolBadge(pdc.prise_type_chademo)}</td>

          <td>${escapeHtml(pdc.condition_acces ?? "—")}</td>
          <td>${escapeHtml(pdc.accessibilite_pmr ?? "—")}</td>
          <td>${escapeHtml(pdc.horaires ?? "—")}</td>
        </tr>
      `;
    })
    .join("");

  dom.tableBody.querySelectorAll("tr[data-id]").forEach((row) => {
    const select = () => {
      selectPdc(Number(row.dataset.id), row.dataset.name || "");
    };

    row.addEventListener("click", select);

    row.querySelector(".radio-pdc").addEventListener("click", (event) => {
      event.stopPropagation();
    });

    row.querySelector(".radio-pdc").addEventListener("change", select);
  });
}

function renderPagination(total, totalPages, start) {
  if (total === 0) {
    dom.count.textContent = "Aucun résultat";
    dom.pagination.innerHTML = "";
    return;
  }

  const end = Math.min(start + LIGNES_PAR_PAGE, total);

  dom.count.textContent =
    `${total.toLocaleString("fr-FR")} résultat(s) — ${start + 1} à ${end}`;

  const first = Math.max(1, state.page - 2);
  const last = Math.min(totalPages, state.page + 2);

  const numbers = [];

  for (let current = first; current <= last; current += 1) {
    numbers.push(`
      <button
        class="pagination-btn ${current === state.page ? "active" : ""}"
        data-page="${current}"
        type="button"
      >
        ${current}
      </button>
    `);
  }

  dom.pagination.innerHTML = `
    <button
      class="pagination-btn"
      data-page="${state.page - 1}"
      type="button"
      ${state.page === 1 ? "disabled" : ""}
    >
      ‹ Précédent
    </button>

    <span class="pagination-pages">
      ${numbers.join("")}
    </span>

    <button
      class="pagination-btn"
      data-page="${state.page + 1}"
      type="button"
      ${state.page === totalPages ? "disabled" : ""}
    >
      Suivant ›
    </button>
  `;

  dom.pagination.querySelectorAll("button[data-page]").forEach((button) => {
    button.addEventListener("click", () => {
      const nextPage = Number(button.dataset.page);

      if (
        Number.isInteger(nextPage) &&
        nextPage >= 1 &&
        nextPage <= totalPages
      ) {
        state.page = nextPage;
        renderTable();

        document.querySelector(".table-wrapper").scrollTop = 0;
      }
    });
  });
}

function selectPdc(id, name) {
  state.selectedId = id;
  state.selectedName = name;

  hideError(dom.errorGlobal);
  hideResults();

  setButtons();
  renderTable();
}

/* =========================
   METRIQUES
========================= */

async function loadMetrics(type) {
  try {
    const json = await fetchJson(
      `${ENDPOINT_METRICS}?type=${encodeURIComponent(type)}`
    );

    state.metrics[type] = json.success ? json.data : null;
  } catch (error) {
    console.warn(`Métriques ${type} indisponibles :`, error.message);
    state.metrics[type] = null;
  }
}

/* =========================
   GRAPHIQUES
========================= */

const plotConfig = {
  responsive: true,
  displayModeBar: false
};

function baseLayout() {
  return {
    paper_bgcolor: "transparent",
    plot_bgcolor: "transparent",

    font: {
      family: "Inter, system-ui, sans-serif",
      color: "#475569"
    },

    margin: {
      t: 35,
      r: 20,
      b: 45,
      l: 60
    },

    legend: {
      orientation: "h",
      y: 1.12,
      x: 0
    },

    xaxis: {
      gridcolor: "#E2E8F0"
    },

    yaxis: {
      gridcolor: "#E2E8F0"
    }
  };
}

function drawImportance(importances = {}) {
  const values = Object.entries(importances).sort(
    (a, b) => b[1] - a[1]
  );

  Plotly.react(
    "chart-importance",
    [
      {
        type: "bar",
        orientation: "h",
        x: values.map((item) => item[1]),
        y: values.map((item) => item[0]),
        marker: {
          color: "#3B82F6"
        },
        hovertemplate: "%{y} : %{x:.3f}<extra></extra>"
      }
    ],
    {
      ...baseLayout(),

      margin: {
        t: 10,
        r: 20,
        b: 45,
        l: 175
      },

      xaxis: {
        title: "Importance",
        gridcolor: "#E2E8F0"
      },

      yaxis: {
        automargin: true
      }
    },
    plotConfig
  );
}

function drawImplantationMetrics(metrics) {
  const categories = ["Précision", "Rappel", "F1-Score"];

  const svm = [
    metrics.svm_precision,
    metrics.svm_recall,
    metrics.svm_f1
  ];

  const rf = [
    metrics.rf_precision,
    metrics.rf_recall,
    metrics.rf_f1
  ];

  Plotly.react(
    "chart-comparison",
    [
      {
        name: "SVM",
        type: "bar",
        x: categories,
        y: svm,

        marker: {
          color: "#93C5FD"
        },

        text: svm.map((value) => `${(value * 100).toFixed(1)}%`),
        textposition: "outside"
      },

      {
        name: "Random Forest",
        type: "bar",
        x: categories,
        y: rf,

        marker: {
          color: "#3B82F6"
        },

        text: rf.map((value) => `${(value * 100).toFixed(1)}%`),
        textposition: "outside"
      }
    ],
    {
      ...baseLayout(),

      barmode: "group",

      yaxis: {
        range: [0, 1.15],
        tickformat: ".0%",
        gridcolor: "#E2E8F0"
      }
    },
    plotConfig
  );
}

function drawPuissanceMetrics(metrics) {
  Plotly.react(
    "chart-comparison",
    [
      {
        name: "SVM",
        type: "bar",
        x: ["Erreur moyenne (MAE)"],
        y: [metrics.svm_mae],

        marker: {
          color: "#93C5FD"
        },

        text: [`${metrics.svm_mae} kW`],
        textposition: "outside"
      },

      {
        name: "Random Forest",
        type: "bar",
        x: ["Erreur moyenne (MAE)"],
        y: [metrics.rf_mae],

        marker: {
          color: "#3B82F6"
        },

        text: [`${metrics.rf_mae} kW`],
        textposition: "outside"
      }
    ],
    {
      ...baseLayout(),

      barmode: "group",

      yaxis: {
        title: "kW",
        gridcolor: "#E2E8F0"
      }
    },
    plotConfig
  );
}

/* =========================
   RESULTATS IMPLANTATION
========================= */

function showImplantation(data) {
  const metrics = state.metrics.implantation;

  const rf = data.random_forest;
  const svm = data.svm;

  const agreed = rf === svm;

  dom.kpi1Label.textContent = "Précision globale (SVM)";

  dom.kpi1Value.textContent = metrics
    ? `${(metrics.svm_precision * 100).toFixed(1)}%`
    : "—";

  dom.kpi1Sub.textContent = metrics
    ? `Rappel : ${(metrics.svm_recall * 100).toFixed(1)}% · F1 : ${(metrics.svm_f1 * 100).toFixed(1)}%`
    : "Métriques indisponibles";

  dom.kpi2Label.textContent = "Rappel global (Random Forest)";

  dom.kpi2Value.textContent = metrics
    ? `${(metrics.rf_recall * 100).toFixed(1)}%`
    : "—";

  dom.kpi2Value.className = "kpi-value green";

  dom.kpi2Sub.textContent = metrics
    ? `Précision : ${(metrics.rf_precision * 100).toFixed(1)}% · F1 : ${(metrics.rf_f1 * 100).toFixed(1)}%`
    : "";

  dom.date.textContent = metrics
    ? `Entraîné le ${dateFr(metrics.trained_at)}`
    : "—";

  dom.confusionBlock.classList.add("visible");

  dom.confusion.innerHTML = `
    <div></div>
    <div class="conf-header">RF : ${escapeHtml(rf)}</div>
    <div class="conf-header">RF : ${escapeHtml(svm)}</div>

    <div class="conf-row-label">SVM : ${escapeHtml(rf)}</div>
    <div class="conf-cell correct">
      ${agreed ? "✓ Accord" : "—"}
    </div>
    <div class="conf-cell incorrect">
      ${agreed ? "0" : "✗ Désaccord"}
    </div>

    <div class="conf-row-label">SVM : ${escapeHtml(svm)}</div>
    <div class="conf-cell incorrect">
      ${agreed ? "0" : "✗"}
    </div>
    <div class="conf-cell correct">
      ${agreed ? "—" : "✓"}
    </div>
  `;

  drawImplantationMetrics(
    metrics || {
      rf_precision: 0,
      rf_recall: 0,
      rf_f1: 0,
      svm_precision: 0,
      svm_recall: 0,
      svm_f1: 0
    }
  );

  drawImportance(metrics?.feature_importances || {});

  dom.verdict.className =
    `verdict-banner ${agreed ? "accord" : "desaccord"}`;

  dom.verdict.innerHTML = agreed
    ? `
      <div class="verdict-icon">✅</div>
      <div>
        <p class="verdict-label">Consensus des modèles</p>
        <p class="verdict-value">${escapeHtml(rf)}</p>
        <p class="verdict-sub">
          RF et SVM s'accordent pour le PDC #${state.selectedId}.
        </p>
      </div>
    `
    : `
      <div class="verdict-icon">⚖️</div>
      <div>
        <p class="verdict-label">Désaccord entre les modèles</p>
        <p class="verdict-value">Résultats divergents</p>
        <p class="verdict-sub">
          RF prédit <strong>${escapeHtml(rf)}</strong> —
          SVM prédit <strong>${escapeHtml(svm)}</strong>.
        </p>
      </div>
    `;

  showResults();
}

/* =========================
   RESULTATS PUISSANCE
========================= */

function showPuissance(data) {
  const metrics = state.metrics.puissance;

  const rf = Number(data.random_forest);
  const svm = Number(data.svm);

  const average = Number.isFinite(Number(data.moyenne))
    ? Number(data.moyenne)
    : (rf + svm) / 2;

  const gap = Number.isFinite(Number(data.ecart))
    ? Number(data.ecart)
    : Math.abs(rf - svm);

  const agreed = gap <= 5;

  dom.kpi1Label.textContent = "Puissance prédite (SVM)";
  dom.kpi1Value.textContent = `${svm.toFixed(2)} kW`;
  dom.kpi1Value.className = "kpi-value";

  dom.kpi1Sub.textContent = metrics
    ? `MAE : ${metrics.svm_mae} kW · RMSE : ${metrics.svm_rmse} kW · R² : ${metrics.svm_r2}`
    : "";

  dom.kpi2Label.textContent = "Puissance prédite (Random Forest)";
  dom.kpi2Value.textContent = `${rf.toFixed(2)} kW`;
  dom.kpi2Value.className = "kpi-value green";

  dom.kpi2Sub.textContent = metrics
    ? `MAE : ${metrics.rf_mae} kW · RMSE : ${metrics.rf_rmse} kW · Moyenne : ${average.toFixed(2)} kW`
    : `Moyenne : ${average.toFixed(2)} kW`;

  dom.date.textContent = metrics
    ? `Entraîné le ${dateFr(metrics.trained_at)}`
    : "—";

  dom.confusionBlock.classList.remove("visible");

  drawPuissanceMetrics(
    metrics || {
      rf_mae: 0,
      svm_mae: 0
    }
  );

  drawImportance(metrics?.feature_importances || {});

  dom.verdict.className =
    `verdict-banner ${agreed ? "accord" : "desaccord"}`;

  dom.verdict.innerHTML = `
    <div class="verdict-icon">${agreed ? "✅" : "⚖️"}</div>

    <div>
      <p class="verdict-label">
        ${agreed ? "Modèles convergents" : "Écart notable entre les modèles"}
      </p>

      <p class="verdict-value">
        Moyenne : ${average.toFixed(2)} kW
      </p>

      <p class="verdict-sub">
        RF : ${rf.toFixed(2)} kW ·
        SVM : ${svm.toFixed(2)} kW ·
        Écart : ${gap.toFixed(2)} kW
      </p>
    </div>
  `;

  showResults();
}

function showResults() {
  dom.results.classList.add("visible");

  dom.results.scrollIntoView({
    behavior: "smooth",
    block: "start"
  });
}

/* =========================
   PREDICTION
========================= */

async function predict(type, button) {
  if (state.selectedId === null) {
    showError(
      dom.errorGlobal,
      "Sélectionnez un point de charge avant de lancer une prédiction."
    );

    return;
  }

  hideError(dom.errorGlobal);
  hideResults();

  button.disabled = true;

  const otherButton =
    type === "implantation"
      ? dom.btnPuissance
      : dom.btnImplantation;

  otherButton.disabled = true;

  try {
    const predictionUrl =
      `${ENDPOINT_PREDIRE}?id_pdc=${encodeURIComponent(state.selectedId)}&type=${encodeURIComponent(type)}`;

    const prediction = await fetchJson(predictionUrl);

    if (!prediction.success) {
      throw new Error(
        prediction.error || "La prédiction a échoué."
      );
    }

    const stored = await fetchJson(
      `${ENDPOINT_RESULTAT}?type=${encodeURIComponent(type)}`
    );

    if (!stored.success || !stored.data) {
      throw new Error(
        stored.error ||
        "Impossible de relire la prédiction enregistrée en base."
      );
    }

    if (type === "implantation") {
      showImplantation(stored.data);
    } else {
      showPuissance(stored.data);
    }
  } catch (error) {
    showError(dom.errorGlobal, error.message);
  } finally {
    setButtons();
  }
}

/* =========================
   EVENEMENTS
========================= */

dom.search.addEventListener("input", () => {
  state.page = 1;
  renderTable();
});

dom.btnImplantation.addEventListener("click", () => {
  predict("implantation", dom.btnImplantation);
});

dom.btnPuissance.addEventListener("click", () => {
  predict("puissance", dom.btnPuissance);
});

dom.btnReset.addEventListener("click", () => {
  state.selectedId = null;
  state.selectedName = "";

  hideError(dom.errorGlobal);
  hideResults();

  setButtons();
  renderTable();
});

dom.btnNew.addEventListener("click", () => {
  hideResults();

  document.getElementById("titre-selection").scrollIntoView({
    behavior: "smooth",
    block: "start"
  });
});

/* =========================
   INITIALISATION
========================= */

Promise.all([
  loadMetrics("implantation"),
  loadMetrics("puissance")
]);

loadPdc();
setButtons();