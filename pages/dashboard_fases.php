<?php
require_once '../includes/layout.php';
ob_start();
?>
<header class="topbar">
  <div>
    <div class="topbar-title">Dashboard de Fases del Proyecto</div>
    <div class="topbar-subtitle">Cumplimiento por fase — Aprendices aprobados y pendientes en cada etapa</div>
  </div>
  <div class="topbar-actions">
    <button class="btn btn-secondary btn-sm" onclick="exportToPDF('tabla-fases','Dashboard de Fases','dashboard_fases')">📄 PDF</button>
    <button class="btn btn-primary btn-sm" onclick="exportToExcel('tabla-fases','dashboard_fases')">📊 Excel</button>
  </div>
</header>

<main class="page-content">
  <!-- Stats globales de fases -->
  <div class="stat-grid mb-6" id="fases-stats"></div>

  <!-- Gráfica de cumplimiento por fase -->
  <div class="card mb-6">
    <div class="card-header"><span>📊</span><h3>Porcentaje de Cumplimiento por Fase</h3></div>
    <div class="card-body">
      <div class="chart-wrapper" style="height:300px">
        <canvas id="chart-fases"></canvas>
      </div>
    </div>
  </div>

  <!-- Cards por fase con detalles -->
  <div id="fases-detail-container"></div>

  <!-- Tabla resumen -->
  <div class="card mt-4">
    <div class="card-header"><span>📋</span><h3>Resumen por Fase</h3></div>
    <div class="card-body p-0">
      <div class="table-container">
        <table class="table" id="tabla-fases">
          <thead>
            <tr>
              <th>Orden</th>
              <th>Fase</th>
              <th>Actividades</th>
              <th>Competencias</th>
              <th>Resultados</th>
              <th>Aprendices Aprobados</th>
              <th>Pendientes</th>
              <th>% Cumplimiento</th>
              <th>Progreso</th>
            </tr>
          </thead>
          <tbody id="tbody-fases"></tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<script>
let chartFases;

async function loadDashboardFases() {
  const fases = await api('api/proyecto.php', { action: 'listar_fases' });

  if (!fases.length) {
    document.getElementById('fases-stats').innerHTML = '';
    document.getElementById('fases-detail-container').innerHTML = `
      <div class="card">
        <div class="empty-state" style="padding:60px">
          <div class="empty-icon">📋</div>
          <h3>No hay fases definidas</h3>
          <p class="text-muted">Ve al módulo de <a href="${BASE_URL}pages/proyecto_formativo.php">Fases y Actividades</a> para crear el proyecto formativo</p>
        </div>
      </div>`;
    return;
  }

  // Para cada fase cargar actividades y calcular cumplimiento
  const fasesDetalle = await Promise.all(fases.map(async f => {
    const actividades = await api('api/proyecto.php', { action: 'listar_actividades', id_fase: f.id_fase });
    
    // Recopilar todas las competencias y resultados de la fase
    const allComps = new Set();
    const allResults = new Set();
    actividades.forEach(a => {
      if (a.competencias) a.competencias.split(', ').forEach(c => c && allComps.add(c));
      if (a.resultados) a.resultados.split(', ').forEach(r => r && allResults.add(r));
    });

    return { ...f, actividades, competencias: [...allComps], resultados: [...allResults] };
  }));

  // Stats globales
  const totalFases = fasesDetalle.length;
  const totalActividades = fasesDetalle.reduce((s, f) => s + f.actividades.length, 0);
  const totalComps = new Set(fasesDetalle.flatMap(f => f.competencias)).size;
  const totalResults = new Set(fasesDetalle.flatMap(f => f.resultados)).size;

  document.getElementById('fases-stats').innerHTML = `
    <div class="stat-card green"><div class="stat-icon">🗂️</div><div class="stat-value">${totalFases}</div><div class="stat-label">Total Fases</div></div>
    <div class="stat-card blue"><div class="stat-icon">📋</div><div class="stat-value">${totalActividades}</div><div class="stat-label">Total Actividades</div></div>
    <div class="stat-card purple"><div class="stat-icon">🎯</div><div class="stat-value">${totalComps}</div><div class="stat-label">Competencias Cubiertas</div></div>
    <div class="stat-card orange"><div class="stat-icon">📚</div><div class="stat-value">${totalResults}</div><div class="stat-label">Resultados de Aprendizaje</div></div>
  `;

  // Gráfica de barras de cumplimiento
  // Nota: el % de cumplimiento se basa en qué tan pobladas están las actividades (actividades con competencias/resultados)
  const cumplimientos = fasesDetalle.map(f => {
    const totalActs = f.actividades.length;
    if (!totalActs) return 0;
    const actsConRelacion = f.actividades.filter(a => a.competencias || a.resultados).length;
    return Math.round((actsConRelacion / totalActs) * 100);
  });

  if (chartFases) chartFases.destroy();
  chartFases = new Chart(document.getElementById('chart-fases'), {
    type: 'bar',
    data: {
      labels: fasesDetalle.map(f => f.nombre.slice(0, 25)),
      datasets: [
        {
          label: '% Cumplimiento (actividades vinculadas)',
          data: cumplimientos,
          backgroundColor: cumplimientos.map(p => p >= 80 ? 'rgba(57,169,0,0.7)' : p >= 50 ? 'rgba(250,140,22,0.7)' : 'rgba(255,77,79,0.7)'),
          borderRadius: 8, borderSkipped: false, yAxisID: 'y'
        },
        {
          label: 'Actividades',
          data: fasesDetalle.map(f => f.actividades.length),
          type: 'line',
          borderColor: '#1890ff',
          backgroundColor: 'rgba(24,144,255,0.1)',
          pointBackgroundColor: '#1890ff',
          tension: 0.4,
          yAxisID: 'y2'
        }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } },
      scales: {
        y: { beginAtZero: true, max: 100, position: 'left', title: { display: true, text: '% Cumplimiento', color: '#8896b3' }, grid: { color: 'rgba(255,255,255,0.05)' } },
        y2: { beginAtZero: true, position: 'right', title: { display: true, text: 'Actividades', color: '#8896b3' }, grid: { display: false } },
        x: { grid: { display: false } }
      }
    }
  });

  // Cards detalle por fase
  const colors = ['green', 'blue', 'orange', 'purple', 'red'];
  document.getElementById('fases-detail-container').innerHTML = fasesDetalle.map((f, idx) => {
    const pct = cumplimientos[idx];
    const barColor = pct >= 80 ? '' : pct >= 50 ? 'orange' : 'red';
    const color = colors[idx % colors.length];
    return `
      <div class="card mb-4">
        <div class="card-header" style="background:linear-gradient(135deg,rgba(57,169,0,0.08),transparent)">
          <div style="width:32px;height:32px;border-radius:50%;background:var(--sena-green);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;color:white;flex-shrink:0">${f.orden}</div>
          <div style="flex:1">
            <h3>${f.nombre}</h3>
            ${f.descripcion ? `<div class="text-sm text-muted">${f.descripcion}</div>` : ''}
          </div>
          <span class="badge ${pct >= 80 ? 'badge-success' : pct >= 50 ? 'badge-warning' : 'badge-danger'}">${pct}% vinculado</span>
        </div>
        <div class="card-body">
          <div class="progress-bar mb-4" style="height:8px">
            <div class="progress-fill ${barColor}" style="width:${pct}%"></div>
          </div>
          <div class="grid-3" style="gap:12px;margin-bottom:16px">
            <div style="text-align:center;padding:12px;background:var(--bg-input);border-radius:var(--radius-md)">
              <div style="font-size:22px;font-weight:800;color:var(--sena-green-light)">${f.actividades.length}</div>
              <div class="text-sm text-muted">Actividades</div>
            </div>
            <div style="text-align:center;padding:12px;background:var(--bg-input);border-radius:var(--radius-md)">
              <div style="font-size:22px;font-weight:800;color:var(--accent-blue)">${f.competencias.length}</div>
              <div class="text-sm text-muted">Competencias</div>
            </div>
            <div style="text-align:center;padding:12px;background:var(--bg-input);border-radius:var(--radius-md)">
              <div style="font-size:22px;font-weight:800;color:var(--accent-purple)">${f.resultados.length}</div>
              <div class="text-sm text-muted">Resultados</div>
            </div>
          </div>
          ${f.competencias.length ? `<div class="mb-3"><div class="text-sm text-muted mb-2">Competencias:</div>${f.competencias.map(c => `<span class="badge badge-success" style="margin:2px">${c.slice(0,30)}</span>`).join('')}</div>` : ''}
          ${f.resultados.length ? `<div><div class="text-sm text-muted mb-2">Resultados de Aprendizaje:</div>${f.resultados.map(r => `<span class="badge badge-info" style="margin:2px">${r.slice(0,30)}</span>`).join('')}</div>` : ''}
        </div>
      </div>`;
  }).join('');

  // Tabla resumen
  const tbody = document.getElementById('tbody-fases');
  tbody.innerHTML = fasesDetalle.map(f => {
    const pct = cumplimientos[fasesDetalle.indexOf(f)];
    const barColor = pct >= 80 ? '' : pct >= 50 ? 'orange' : 'red';
    return `<tr>
      <td><strong>${f.orden}</strong></td>
      <td><strong>${f.nombre}</strong></td>
      <td class="text-center">${f.actividades.length}</td>
      <td class="text-center">${f.competencias.length}</td>
      <td class="text-center">${f.resultados.length}</td>
      <td class="text-center text-success">—</td>
      <td class="text-center text-warning">—</td>
      <td><span class="badge ${pct >= 80 ? 'badge-success' : pct >= 50 ? 'badge-warning' : 'badge-danger'}">${pct}%</span></td>
      <td style="min-width:120px"><div class="progress-bar"><div class="progress-fill ${barColor}" style="width:${pct}%"></div></div></td>
    </tr>`;
  }).join('');
}

loadDashboardFases();
</script>
<?php
$content = ob_get_clean();
renderLayout('Dashboard de Fases', 'dashboard_fases', $content);
?>
