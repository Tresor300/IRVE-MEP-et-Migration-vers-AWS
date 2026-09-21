<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta
    name="description"
    content="Prédiction IA des points de recharge par Random Forest et SVM."
  />
  <title>IA Classification – INFRA-CHARGE</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
  />

  <link rel="stylesheet" href="../css/style.css" />


  <script src="https://cdn.plot.ly/plotly-2.32.0.min.js" defer></script>
  <script src="../js/prediction.js" defer></script>
</head>


    <!-- On définit quelle page est active et on inclut le menu directement -->

<body>
      <?php 
        $page_active = 'ia_classification';
        require __DIR__ . '/../../fonctionnalite_1/includes/menu.php';
    ?>

  <main class="page-content">
    <header class="page-header">
      <h1>
        Classification des Points de Recharge<br />
        (SVM, Random Forest)
      </h1>

      <p>
        Prédiction du type d'implantation et de la puissance nominale des
        points de charge.
      </p>
    </header>

    <section class="section-card" aria-labelledby="titre-selection">
      <h2 id="titre-selection">📋 Sélection du point de charge</h2>

      <p class="section-intro">
        Recherchez ou sélectionnez un point de charge, puis lancez la
        prédiction souhaitée.
      </p>

      <div id="zone-erreur-tableau" class="message-zone" role="alert"></div>

      <div class="table-tools">
        <label for="search-pdc">Rechercher :</label>

        <input
          id="search-pdc"
          type="search"
          placeholder="ID, puissance, accès, horaires…"
          autocomplete="off"
        />

        <span
          id="table-count"
          class="table-count"
          aria-live="polite"
        ></span>
      </div>

      <div
        class="table-wrapper"
        role="region"
        aria-label="Liste des points de charge"
      >
        <table id="tbl-pdc">
          <thead>
            <tr>
              <th aria-label="Sélection"></th>
              <th>ID</th>
              <th>Nb PDC</th>
              <th>Puissance (kW)</th>
              <th>Type 2</th>
              <th>Combo CCS</th>
              <th>CHAdeMO</th>
              <th>Condition accès</th>
              <th>PMR</th>
              <th>Horaires</th>
            </tr>
          </thead>

          <tbody id="tbl-body">
            <tr>
              <td colspan="10" class="table-status">
                Chargement des points de charge…
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <nav
        id="pagination-bar"
        class="pagination-bar"
        aria-label="Pagination des points de charge"
      ></nav>

      <div class="actions-bar">
        <button
          id="btn-predict-implantation"
          class="btn btn-primary"
          type="button"
          disabled
        >
          🏗️ Prédire l'implantation
        </button>

        <button
          id="btn-predict-puissance"
          class="btn btn-outline"
          type="button"
          disabled
        >
          ⚡ Prédire la puissance nominale
        </button>

        <button
          id="btn-reset"
          class="btn btn-ghost btn-right"
          type="button"
        >
          ↺ Réinitialiser
        </button>
      </div>
    </section>

    <div id="zone-erreur-globale" class="message-zone" role="alert"></div>

    <section
      id="section-resultats"
      class="results-section"
      aria-live="polite"
    >
      <div class="results-grid">
        <div class="metrics-col">
          <article class="kpi-card">
            <p id="kpi1-label" class="kpi-label">—</p>
            <p id="kpi1-value" class="kpi-value">—</p>
            <p id="kpi1-sub" class="kpi-sub"></p>
          </article>

          <article class="kpi-card">
            <p id="kpi2-label" class="kpi-label">—</p>
            <p id="kpi2-value" class="kpi-value">—</p>
            <p id="kpi2-sub" class="kpi-sub"></p>
          </article>

          <article class="kpi-card">
            <p class="kpi-label">Dernière analyse</p>
            <p id="date-analyse" class="date-value">—</p>
          </article>

          <article id="bloc-confusion" class="confusion-card">
            <h3>Comparaison des modèles</h3>
            <div id="conf-matrix" class="conf-matrix"></div>
          </article>
        </div>

        <div class="charts-col">
          <article class="chart-card">
            <h2>Model comparison</h2>
            <div id="chart-comparison" class="chart"></div>
          </article>

          <article class="chart-card">
            <h2>Importance des Caractéristiques (Random Forest)</h2>
            <div id="chart-importance" class="chart chart-large"></div>
          </article>
        </div>
      </div>

      <div id="verdict-banner" class="verdict-banner"></div>

      <div class="new-prediction-wrap">
        <button
          id="btn-nouvelle-prediction"
          class="btn btn-ghost"
          type="button"
        >
          ← Nouvelle prédiction
        </button>
      </div>
    </section>
  </main>
</body>
</html>