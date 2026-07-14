<?php
require_once '../includes/layout.php';
ob_start();
?>

<!-- Premium Header -->
<header class="topbar glass mb-6" style="border-radius: 16px; margin: 1rem;">
  <div class="d-flex justify-content-between align-items-center w-100 p-3">
    <div>
      <h1 class="h3 font-weight-bold mb-0 text-dark">Analytics Dashboard</h1>
      <p class="text-muted small mb-0">Gestión Inteligente de Juicios Evaluativos</p>
    </div>
    <div class="d-flex align-items-center gap-3">
      <select id="sel-ficha" class="form-control glass" style="width:250px; border-radius: 10px;" onchange="loadPremiumDashboard()">
        <option value="">🎯 Todas las Fichas</option>
      </select>
      <div class="btn-group">
        <button class="btn btn-outline-primary btn-sm" onclick="window.print()">🖨️ Reporte</button>
        <button class="btn btn-primary btn-sm" onclick="loadPremiumDashboard()">🔄 Actualizar</button>
      </div>
    </div>
  </div>
</header>

<main class="container-fluid px-4">
  
  <!-- Alertas Inteligentes -->
  <div id="alert-container"></div>

  <!-- KPI Cards -->
  <div class="premium-grid">
    <div class="premium-card primary">
      <div class="icon">👥</div>
      <div class="value" id="kpi-aprendices">0</div>
      <div class="label">Total Aprendices</div>
    </div>
    <div class="premium-card blue">
      <div class="icon">✅</div>
      <div class="value" id="kpi-aprobados">0</div>
      <div class="label">Juicios Aprobados</div>
    </div>
    <div class="premium-card orange">
      <div class="icon">⏳</div>
      <div class="value" id="kpi-pendientes">0</div>
      <div class="label">Juicios Pendientes</div>
    </div>
    <div class="premium-card purple">
      <div class="icon">🎯</div>
      <div class="value" id="kpi-competencias">0</div>
      <div class="label">Competencias</div>
    </div>
    <div class="premium-card red">
      <div class="icon">📉</div>
      <div class="value" id="kpi-retirados">0</div>
      <div class="label">Aprendices Retirados</div>
    </div>
    <div class="premium-card primary">
      <div class="icon">📈</div>
      <div class="value" id="kpi-avance">0%</div>
      <div class="label">Avance General</div>
    </div>
  </div>

  <div class="row mb-6">
    <!-- Main Charts -->
    <div class="col-lg-8">
      <div class="chart-container mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h3 class="h5 mb-0 font-weight-bold">Aprobación por Competencia</h3>
          <span class="badge badge-primary">Top Rendimiento</span>
        </div>
        <canvas id="chart-competencias-premium" height="300"></canvas>
      </div>
    </div>
    
    <!-- Secondary Analytics -->
    <div class="col-lg-4">
      <div class="chart-container mb-4">
        <h3 class="h5 mb-4 font-weight-bold">Estado de Formación</h3>
        <canvas id="chart-estado-premium" height="300"></canvas>
      </div>
    </div>
  </div>

  <div class="row mb-6">
    <!-- Recent Activity -->
    <div class="col-lg-4">
      <div class="chart-container">
        <h3 class="h5 mb-4 font-weight-bold">Actividad Reciente</h3>
        <ul class="recent-activity" id="list-activity">
          <li class="text-center py-4 text-muted">Cargando actividad...</li>
        </ul>
      </div>
    </div>

    <!-- Top Performance Ranking -->
    <div class="col-lg-4">
      <div class="chart-container">
        <h3 class="h5 mb-4 font-weight-bold">Top Aprendices 🏆</h3>
        <div id="top-aprendices-list">
           <li class="text-center py-4 text-muted">Cargando ranking...</li>
        </div>
      </div>
    </div>

    <!-- Risk Prediction -->
    <div class="col-lg-4">
      <div class="chart-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h3 class="h5 mb-0 font-weight-bold">Predicción de Riesgo</h3>
          <span class="badge badge-danger">Crítico</span>
        </div>
        <div id="risk-analysis">
          <li class="text-center py-4 text-muted">Analizando datos...</li>
        </div>
      </div>
    </div>
  </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
let chartComp, chartEstado;

async function loadPremiumDashboard() {
    const idFicha = document.getElementById('sel-ficha').value;
    const data = await api('api/dashboard.php', { action: 'stats_premium', id_ficha: idFicha });
    
    // 1. Update KPIs
    updateKPIs(data);
    
    // 2. Update Charts
    updateCharts(data);
    
    // 3. Update Activity
    updateActivity(data.actividad_reciente);
    
    // 4. Update Ranking
    updateRanking(data.top_aprendices);
    
    // 5. Update Risk Analysis
    updateRiskAnalysis(data.top_aprendices);

    // 6. Alertas
    updateAlerts(data.alertas);
}

function updateKPIs(data) {
    document.getElementById('kpi-aprendices').innerText = data.total_aprendices;
    document.getElementById('kpi-aprobados').innerText = data.juicios_aprobados;
    document.getElementById('kpi-pendientes').innerText = data.juicios_pendientes;
    document.getElementById('kpi-competencias').innerText = data.total_competencias;
    document.getElementById('kpi-retirados').innerText = data.aprendices_retirados;
    document.getElementById('kpi-avance').innerText = data.porcentaje_general + '%';
    
    // Animate values
    const kpis = ['kpi-aprendices', 'kpi-aprobados', 'kpi-pendientes', 'kpi-competencias', 'kpi-retirados'];
    kpis.forEach(id => {
        const el = document.getElementById(id);
        const val = parseInt(el.innerText);
        animateNumber(el, 0, val, 1000);
    });
}

function animateNumber(obj, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        obj.innerHTML = Math.floor(progress * (end - start) + start);
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

function updateCharts(data) {
    // Competencias Chart (Chart.js)
    const ctxComp = document.getElementById('chart-competencias-premium').getContext('2d');
    if (chartComp) chartComp.destroy();
    
    chartComp = new Chart(ctxComp, {
        type: 'bar',
        data: {
            labels: data.top_competencias.map(c => c.competencia.length > 20 ? c.competencia.substring(0,20) + '...' : c.competencia),
            datasets: [{
                label: '% Aprobación',
                data: data.top_competencias.map(c => c.porcentaje_aprobacion),
                backgroundColor: 'rgba(57, 169, 0, 0.7)',
                borderColor: '#39a900',
                borderWidth: 1,
                borderRadius: 8
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { max: 100, grid: { display: false } } }
        }
    });

    // Estado Chart (Doughnut)
    const ctxEst = document.getElementById('chart-estado-premium').getContext('2d');
    if (chartEstado) chartEstado.destroy();
    
    chartEstado = new Chart(ctxEst, {
        type: 'doughnut',
        data: {
            labels: ['Aprobados', 'Pendientes'],
            datasets: [{
                data: [data.juicios_aprobados, data.juicios_pendientes],
                backgroundColor: ['#39a900', '#ff9900'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            cutout: '70%',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

function updateActivity(activities) {
    const list = document.getElementById('list-activity');
    if (!activities || activities.length === 0) {
        list.innerHTML = '<li class="text-center py-4 text-muted">Sin actividad reciente</li>';
        return;
    }
    
    list.innerHTML = activities.map(a => `
        <li class="activity-item">
            <div class="activity-icon">${a.tipo === 'APROBADO' ? '✅' : '⏳'}</div>
            <div class="activity-content">
                <h4 class="mb-0 font-weight-bold">${a.aprendiz_nom} ${a.aprendiz_ape}</h4>
                <p class="mb-0 text-truncate" style="max-width: 200px;">${a.resultado}</p>
                <div class="d-flex justify-content-between">
                    <span class="activity-time">${new Date(a.fecha).toLocaleString()}</span>
                    <span class="badge badge-${a.tipo === 'APROBADO' ? 'success' : 'warning'} p-1">${a.tipo}</span>
                </div>
            </div>
        </li>
    `).join('');
}

function updateRanking(top) {
    const list = document.getElementById('top-aprendices-list');
    list.innerHTML = top.map((a, i) => `
        <div class="d-flex align-items-center mb-3 p-2 glass" style="border-radius: 12px;">
            <div class="mr-3 font-weight-bold h4 mb-0 text-primary">${i+1}</div>
            <div class="flex-grow-1">
                <div class="font-weight-bold">${a.nombre_completo}</div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-success" style="width: ${a.porcentaje_avance}%"></div>
                </div>
            </div>
            <div class="ml-3 font-weight-bold text-dark">${a.porcentaje_avance}%</div>
        </div>
    `).join('');
}

function updateRiskAnalysis(aprendices) {
    const container = document.getElementById('risk-analysis');
    // Simple mock logic for risk based on progress
    container.innerHTML = aprendices.reverse().slice(0, 5).map(a => {
        const pct = parseFloat(a.porcentaje_avance);
        const riskClass = pct < 30 ? 'red' : pct < 60 ? 'yellow' : 'green';
        const riskText = pct < 30 ? 'Riesgo Crítico' : pct < 60 ? 'Riesgo Medio' : 'Buen Rendimiento';
        
        return `
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="font-weight-bold small">${a.nombre_completo}</span>
                    <span class="badge badge-light small">${riskText}</span>
                </div>
                <div class="d-flex align-items-center">
                    <div class="semaforo-dot semaforo-${riskClass}"></div>
                    <div class="flex-grow-1 small text-muted">Progreso: ${pct}%</div>
                </div>
            </div>
        `;
    }).join('');
}

function updateAlerts(alerts) {
    const container = document.getElementById('alert-container');
    if (!alerts || alerts.length === 0) {
        container.innerHTML = '';
        return;
    }
    
    container.innerHTML = alerts.map(a => `
        <div class="alert-banner ${a.type}">
            <span>${a.type === 'danger' ? '🚨' : '⚠️'}</span>
            <div class="font-weight-bold">${a.msg}</div>
        </div>
    `).join('');
}

async function loadFichas() {
    const data = await api('api/dashboard.php', { action: 'fichas' });
    const sel = document.getElementById('sel-ficha');
    data.forEach(f => {
        const opt = document.createElement('option');
        opt.value = f.id_ficha;
        opt.textContent = '📂 ' + f.nombre;
        sel.appendChild(opt);
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadFichas();
    loadPremiumDashboard();
});
</script>

<?php
$content = ob_get_clean();
renderLayout('SaaS Premium Dashboard', 'dashboard', $content);
?>
