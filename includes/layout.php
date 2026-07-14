<?php
function renderLayout($title, $activePage, $content) { ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?> | Juicios Evaluativos SENA</title>
  <meta name="description" content="Sistema de gestión de juicios evaluativos para el SENA - Control de aprendices, competencias y resultados de aprendizaje">
  <link rel="stylesheet" href="/juicios_evaluativos/assets/css/styles.css">
  <link rel="stylesheet" href="/juicios_evaluativos/assets/css/dashboard-elegant.css">
  <script>
  const BASE_URL = '/juicios_evaluativos/';

  // Toast utility
  function showToast(msg, type = 'info', duration = 4000) {
    const icons = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span>${icons[type]}</span><span>${msg}</span>`;
    const tc = document.getElementById('toast-container');
    if (tc) tc.appendChild(t);
    setTimeout(() => t.remove(), duration);
  }

  // API helper
  async function api(endpoint, params = {}, method = 'GET') {
    let url = window.location.origin + BASE_URL + endpoint.replace(/^\//, '');
    const sp = new URLSearchParams();
    Object.entries(params).forEach(([k,v]) => { if(v !== '' && v !== null && v !== undefined) sp.set(k, v); });
    sp.set('_t', Date.now());
    const qs = sp.toString();
    if (qs) url += (url.includes('?') ? '&' : '?') + qs;
    
    console.log("API CALL:", url);
    const res = await fetch(url, { method });
    return res.json();
  }

  async function apiPost(endpoint, formData) {
    const res = await fetch(window.location.origin + BASE_URL + endpoint.replace(/^\//, ''), {
      method: 'POST', body: formData
    });
    return res.json();
  }

  // Excel export
  function exportToExcel(tableId, filename) {
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(document.getElementById(tableId));
    XLSX.utils.book_append_sheet(wb, ws, 'Datos');
    XLSX.writeFile(wb, filename + '.xlsx');
  }

  // PDF export
  function exportToPDF(tableId, title, filename) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
    doc.setFontSize(14);
    doc.text(title, 14, 15);
    doc.setFontSize(10);
    doc.text('SENA — Sistema de Juicios Evaluativos — ' + new Date().toLocaleDateString('es-CO'), 14, 22);
    doc.autoTable({ html: '#' + tableId, startY: 28, styles: { fontSize: 8 }, headStyles: { fillColor: [57,169,0] } });
    doc.save(filename + '.pdf');
  }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
<div class="app-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">🎓</div>
      <div class="logo-text">
        <h2>Juicios Evaluativos</h2>
        <span>SENA — Sistema de Gestión</span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-group-label">Principal</div>
      <a href="/juicios_evaluativos/pages/dashboard.php" 
         class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>">
        <span class="nav-icon">📊</span> Dashboard Principal
      </a>

      <div class="nav-group-label">Carga de Datos</div>
      <a href="/juicios_evaluativos/pages/carga_aprendices.php"
         class="nav-item <?= $activePage === 'carga_aprendices' ? 'active' : '' ?>">
        <span class="nav-icon">👥</span> Carga Masiva Aprendices
      </a>
      <a href="/juicios_evaluativos/pages/importar_juicios.php"
         class="nav-item <?= $activePage === 'importar_juicios' ? 'active' : '' ?>">
        <span class="nav-icon">📥</span> Importar Juicios
      </a>

      <div class="nav-group-label">Consultas</div>
      <a href="/juicios_evaluativos/pages/consulta_aprendiz.php"
         class="nav-item <?= $activePage === 'consulta_aprendiz' ? 'active' : '' ?>">
        <span class="nav-icon">🔍</span> Consulta por Aprendiz
      </a>
      <a href="/juicios_evaluativos/pages/filtro_avanzado.php"
         class="nav-item <?= $activePage === 'filtro_avanzado' ? 'active' : '' ?>">
        <span class="nav-icon">⚙️</span> Filtro Avanzado
      </a>

      <div class="nav-group-label">Proyecto Formativo</div>
      <a href="/juicios_evaluativos/pages/proyecto_formativo.php"
         class="nav-item <?= $activePage === 'proyecto_formativo' ? 'active' : '' ?>">
        <span class="nav-icon">📋</span> Fases y Actividades
      </a>
      <a href="/juicios_evaluativos/pages/dashboard_fases.php"
         class="nav-item <?= $activePage === 'dashboard_fases' ? 'active' : '' ?>">
        <span class="nav-icon">🗂️</span> Dashboard de Fases
      </a>
    </nav>

    <div class="sidebar-footer">
      <strong>SENA</strong> &mdash; <?= date('d/m/Y') ?>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main-content">
    <div id="toast-container"></div>
    <?= $content ?>
  </div>
</div>

<script>
// Chart.js global defaults — set after CDN loads
document.addEventListener('DOMContentLoaded', function() {
  if (typeof Chart !== 'undefined') {
    Chart.defaults.color = '#8896b3';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';
    Chart.defaults.font.family = "'Inter', sans-serif";
  }
});
</script>
</body>
</html>
<?php }
?>
