<?php
require_once '../includes/layout.php';
ob_start();
?>

<div class="dash-wrapper fade-in-up">

  <!-- ============ HEADER ============ -->
  <header class="dash-header">
    <div class="dash-header-left">
      <div class="dash-brand">
        <div class="dash-brand-icon">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 16l4-4 4 4 5-5"/></svg>
        </div>
        <div>
          <h1 class="dash-title">Dashboard</h1>
          <p class="dash-subtitle">Sistema de Juicios Evaluativos · SENA</p>
        </div>
      </div>
    </div>

    <div class="dash-header-right">
      <div class="dash-filter">
        <label class="dash-filter-label">Ficha</label>
        <select id="sel-ficha" class="dash-select" onchange="loadPremiumDashboard()">
          <option value="">Todas las fichas</option>
        </select>
      </div>
      <button class="dash-btn dash-btn-ghost" onclick="window.print()" title="Imprimir reporte">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
      </button>
      <button class="dash-btn dash-btn-primary" onclick="loadPremiumDashboard()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        <span>Actualizar</span>
      </button>
    </div>
  </header>

  <!-- ============ ALERTS ============ -->
  <div id="alert-container"></div>

  <!-- ============ KPI METRICS ============ -->
  <section class="kpi-grid">
    <div class="kpi-card kpi-emerald">
      <div class="kpi-head">
        <div class="kpi-icon">👥</div>
        <span class="kpi-tag">Aprendices</span>
      </div>
      <div class="kpi-value" id="kpi-aprendices">0</div>
      <div class="kpi-label">Total matriculados</div>
    </div>

    <div class="kpi-card kpi-blue">
      <div class="kpi-head">
        <div class="kpi-icon">✓</div>
        <span class="kpi-tag">Aprobados</span>
      </div>
      <div class="kpi-value" id="kpi-aprobados">0</div>
      <div class="kpi-label">Juicios evaluados</div>
    </div>

    <div class="kpi-card kpi-amber">
      <div class="kpi-head">
        <div class="kpi-icon">⏱</div>
        <span class="kpi-tag">Pendientes</span>
      </div>
      <div class="kpi-value" id="kpi-pendientes">0</div>
      <div class="kpi-label">Por evaluar</div>
    </div>

    <div class="kpi-card kpi-violet">
      <div class="kpi-head">
        <div class="kpi-icon">◎</div>
        <span class="kpi-tag">Programa</span>
      </div>
      <div class="kpi-value" id="kpi-competencias">0</div>
      <div class="kpi-label">Competencias</div>
    </div>

    <div class="kpi-card kpi-rose">
      <div class="kpi-head">
        <div class="kpi-icon">⚠</div>
        <span class="kpi-tag">Bajas</span>
      </div>
      <div class="kpi-value" id="kpi-retirados">0</div>
      <div class="kpi-label">Retirados</div>
    </div>

    <div class="kpi-card kpi-featured">
      <div class="kpi-head">
        <div class="kpi-icon">📈</div>
        <span class="kpi-tag-light">Avance</span>
      </div>
      <div class="kpi-value-light" id="kpi-avance">0%</div>
      <div class="kpi-label-light">Progreso general</div>
    </div>
  </section>

  <!-- ============ CHARTS ROW ============ -->
  <section class="dash-row">
    <div class="dash-card span-2">
      <div class="dash-card-head">
        <div>
          <h3 class="dash-card-title">Rendimiento por Competencia</h3>
          <p class="dash-card-sub">Top 5 con mayor porcentaje de aprobación</p>
        </div>
        <span class="chip chip-blue">Analytics</span>
      </div>
      <div class="dash-card-body" style="height: 360px;">
        <canvas id="chart-competencias-premium"></canvas>
      </div>
    </div>

    <div class="dash-card">
      <div class="dash-card-head">
        <div>
          <h3 class="dash-card-title">Estado General</h3>
          <p class="dash-card-sub">Distribución de juicios</p>
        </div>
      </div>
      <div class="dash-card-body" style="height: 360px;">
        <canvas id="chart-estado-premium"></canvas>
      </div>
    </div>
  </section>

  <!-- ============ ACTIVITY ROW ============ -->
  <section class="dash-row dash-row-3">
    <div class="dash-card">
      <div class="dash-card-head">
        <div>
          <h3 class="dash-card-title">Actividad Reciente</h3>
          <p class="dash-card-sub">Últimos juicios registrados</p>
        </div>
      </div>
      <div class="dash-card-body">
        <ul class="activity-list" id="list-activity">
          <li class="empty-msg">Cargando actividad...</li>
        </ul>
      </div>
    </div>

    <div class="dash-card">
      <div class="dash-card-head">
        <div>
          <h3 class="dash-card-title">Ranking de Aprendices</h3>
          <p class="dash-card-sub">Mejor desempeño</p>
        </div>
        <span class="chip chip-emerald">Top 5</span>
      </div>
      <div class="dash-card-body">
        <div id="top-aprendices-list">
          <div class="empty-msg">Cargando ranking...</div>
        </div>
      </div>
    </div>

    <div class="dash-card">
      <div class="dash-card-head">
        <div>
          <h3 class="dash-card-title">Semáforo de Riesgo</h3>
          <p class="dash-card-sub">Aprendices que requieren atención</p>
        </div>
        <span class="chip chip-rose">Crítico</span>
      </div>
      <div class="dash-card-body">
        <div id="risk-analysis">
          <div class="empty-msg">Analizando datos...</div>
        </div>
      </div>
    </div>
  </section>

</div>

<script>
let chartComp, chartEstado;

async function loadPremiumDashboard() {
  const sel = document.getElementById('sel-ficha');
  const idFicha = sel.value;
  console.log("Filtrando por ficha ID:", idFicha);
  
  // Visual loading state
  const btn = document.querySelector('.dash-btn-primary');
  const btnContent = btn.innerHTML;
  btn.innerHTML = '<span>Cargando...</span>';
  btn.disabled = true;

  try {
    const data = await api('api/dashboard.php', { action: 'stats_premium', id_ficha: idFicha });
    console.log("Datos recibidos:", data);
    
    if (data.error) {
      throw new Error(data.error);
    }

    updateKPIs(data);
    updateCharts(data);
    updateActivity(data.actividad_reciente);
    updateRanking(data.top_aprendices);
    updateRiskAnalysis(data.top_aprendices);
    updateAlerts(data.alertas);
    
    if (idFicha) {
      showToast("Datos filtrados correctamente", "success");
    }
  } catch (e) {
    console.error("Dashboard error:", e);
    showToast("Error: " + e.message, "error");
  } finally {
    btn.innerHTML = btnContent;
    btn.disabled = false;
  }
}

function updateKPIs(data) {
  const kpis = {
    'kpi-aprendices': data.total_aprendices,
    'kpi-aprobados': data.juicios_aprobados,
    'kpi-pendientes': data.juicios_pendientes,
    'kpi-competencias': data.total_competencias,
    'kpi-retirados': data.aprendices_retirados
  };
  Object.keys(kpis).forEach(id => animateNumber(document.getElementById(id), 0, parseInt(kpis[id]) || 0, 800));
  document.getElementById('kpi-avance').innerText = (data.porcentaje_general || 0) + '%';
}

function animateNumber(obj, start, end, duration) {
  if (!obj) return;
  if (start === end) { obj.innerHTML = end; return; }
  let startTimestamp = null;
  const step = (timestamp) => {
    if (!startTimestamp) startTimestamp = timestamp;
    const progress = Math.min((timestamp - startTimestamp) / duration, 1);
    obj.innerHTML = Math.floor(progress * (end - start) + start).toLocaleString();
    if (progress < 1) window.requestAnimationFrame(step);
  };
  window.requestAnimationFrame(step);
}

function updateCharts(data) {
  const ctxComp = document.getElementById('chart-competencias-premium').getContext('2d');
  if (chartComp) chartComp.destroy();
  chartComp = new Chart(ctxComp, {
    type: 'bar',
    data: {
      labels: (data.top_competencias || []).map(c => c.competencia.length > 28 ? c.competencia.substring(0,28) + '…' : c.competencia),
      datasets: [{
        label: '% Aprobación',
        data: (data.top_competencias || []).map(c => c.porcentaje_aprobacion),
        backgroundColor: 'rgba(57, 169, 0, 0.85)',
        borderRadius: 6,
        barThickness: 18
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#0d1526', padding: 12, titleColor: '#fff', bodyColor: '#cbd5e1' }
      },
      scales: {
        x: { max: 100, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#8896b3', callback: v => v + '%' } },
        y: { grid: { display: false }, ticks: { color: '#cbd5e1', font: { size: 12 } } }
      }
    }
  });

  const ctxEst = document.getElementById('chart-estado-premium').getContext('2d');
  if (chartEstado) chartEstado.destroy();
  chartEstado = new Chart(ctxEst, {
    type: 'doughnut',
    data: {
      labels: ['Aprobados', 'Pendientes'],
      datasets: [{
        data: [data.juicios_aprobados || 0, data.juicios_pendientes || 0],
        backgroundColor: ['#39a900', '#fa8c16'],
        borderWidth: 0,
        hoverOffset: 12
      }]
    },
    options: {
      cutout: '72%',
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { color: '#cbd5e1', usePointStyle: true, padding: 18, font: { size: 12 } } },
        tooltip: { backgroundColor: '#0d1526', padding: 12 }
      }
    }
  });
}

function updateActivity(activities) {
  const list = document.getElementById('list-activity');
  if (!activities || !activities.length) {
    list.innerHTML = '<li class="empty-msg">Sin actividad reciente</li>';
    return;
  }
  list.innerHTML = activities.map(a => `
    <li class="activity-row">
      <div class="activity-dot ${a.tipo === 'APROBADO' ? 'is-green' : 'is-amber'}"></div>
      <div class="activity-info">
        <div class="activity-name">${a.aprendiz_nom} ${a.aprendiz_ape}</div>
        <div class="activity-desc">${a.resultado || '—'}</div>
      </div>
      <div class="activity-meta">
        <span class="chip ${a.tipo === 'APROBADO' ? 'chip-emerald' : 'chip-amber'}">${a.tipo}</span>
        <small class="activity-time">${new Date(a.fecha).toLocaleDateString('es-CO', {day:'2-digit', month:'short'})}</small>
      </div>
    </li>
  `).join('');
}

function updateRanking(top) {
  const list = document.getElementById('top-aprendices-list');
  if (!top || !top.length) {
    list.innerHTML = '<div class="empty-msg">Sin datos de ranking</div>';
    return;
  }
  list.innerHTML = top.map((a, i) => `
    <div class="rank-row">
      <div class="rank-pos rank-${i+1}">${i+1}</div>
      <div class="rank-info">
        <div class="rank-name">${a.nombre_completo}</div>
        <div class="rank-bar"><div class="rank-bar-fill" style="width:${a.porcentaje_avance}%"></div></div>
      </div>
      <div class="rank-pct">${a.porcentaje_avance}%</div>
    </div>
  `).join('');
}

function updateRiskAnalysis(aprendices) {
  const container = document.getElementById('risk-analysis');
  if (!aprendices || !aprendices.length) {
    container.innerHTML = '<div class="empty-msg">Sin datos para análisis</div>';
    return;
  }
  const riskGroup = [...aprendices].sort((a,b) => parseFloat(a.porcentaje_avance) - parseFloat(b.porcentaje_avance)).slice(0, 5);
  container.innerHTML = riskGroup.map(a => {
    const pct = parseFloat(a.porcentaje_avance);
    let cls = 'is-green', label = 'Óptimo';
    if (pct < 35) { cls = 'is-red'; label = 'Crítico'; }
    else if (pct < 65) { cls = 'is-amber'; label = 'Atención'; }
    return `
      <div class="risk-row">
        <div class="risk-dot ${cls}"></div>
        <div class="risk-info">
          <div class="risk-name">${a.nombre_completo}</div>
          <div class="risk-meta">Rendimiento: ${pct}%</div>
        </div>
        <span class="chip chip-${cls === 'is-red' ? 'rose' : cls === 'is-amber' ? 'amber' : 'emerald'}">${label}</span>
      </div>
    `;
  }).join('');
}

function updateAlerts(alerts) {
  const container = document.getElementById('alert-container');
  if (!alerts || !alerts.length) { container.innerHTML = ''; return; }
  container.innerHTML = alerts.map(a => `
    <div class="dash-alert ${a.type}">
      <span class="dash-alert-icon">${a.type === 'danger' ? '⚠' : 'ℹ'}</span>
      <span>${a.msg}</span>
    </div>
  `).join('');
}

async function loadFichas() {
  try {
    const data = await api('api/dashboard.php', { action: 'fichas' });
    console.log("Fichas cargadas:", data);
    const sel = document.getElementById('sel-ficha');
    data.forEach(f => {
      const opt = document.createElement('option');
      opt.value = f.id_ficha;
      opt.textContent = f.nombre;
      sel.appendChild(opt);
    });
  } catch (e) { console.error("Fichas load error:", e); }
}

document.addEventListener('DOMContentLoaded', async () => {
  await loadFichas();
  loadPremiumDashboard();
});
</script>

<?php
$content = ob_get_clean();
renderLayout('Dashboard', 'dashboard', $content);
?>
