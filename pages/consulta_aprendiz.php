<?php
require_once '../includes/layout.php';
$id_aprendiz = intval($_GET['id'] ?? 0);
ob_start();
?>
<header class="topbar">
  <div>
    <div class="topbar-title">Consulta por Aprendiz</div>
    <div class="topbar-subtitle">Avance detallado, competencias y juicios evaluativos individuales</div>
  </div>
  <div class="topbar-actions" id="export-btns" style="display:none">
    <button class="btn btn-secondary btn-sm" onclick="exportToPDF('tabla-juicios-aprendiz','Juicios del Aprendiz','juicios_aprendiz')">📄 PDF</button>
    <button class="btn btn-primary btn-sm" onclick="exportToExcel('tabla-juicios-aprendiz','juicios_aprendiz')">📊 Excel</button>
  </div>
</header>

<main class="page-content">
  <!-- SEARCH FORM -->
  <div class="card mb-6" id="card-buscar">
    <div class="card-header"><span>🔍</span><h3>Buscar Aprendiz</h3></div>
    <div class="card-body">
      <div class="flex gap-3 items-center">
        <div style="flex:1">
          <label class="form-label">Número de Documento</label>
          <input type="text" id="inp-documento" class="form-control" 
                 placeholder="Ingresa el número de documento del aprendiz..."
                 onkeydown="if(event.key==='Enter') buscarAprendiz()">
        </div>
        <div>
          <label class="form-label">&nbsp;</label>
          <button class="btn btn-primary" onclick="buscarAprendiz()">🔍 Buscar</button>
        </div>
        <div>
          <label class="form-label">&nbsp;</label>
          <select id="sel-aprendiz" class="form-control" style="min-width:220px" onchange="cargarPorId(this.value)">
            <option value="">— Seleccionar de la lista —</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- PERFIL DEL APRENDIZ -->
  <div id="perfil-section" class="hidden">
    <!-- Info card -->
    <div class="card mb-6" id="perfil-card">
      <div class="card-body" style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap">
        <div style="width:64px;height:64px;background:linear-gradient(135deg,var(--sena-green),var(--sena-green-dark));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;flex-shrink:0">👤</div>
        <div style="flex:1">
          <h2 id="p-nombre" style="font-size:22px;font-weight:800;font-family:'Plus Jakarta Sans',sans-serif;margin-bottom:4px"></h2>
          <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:8px">
            <span id="p-doc" class="badge badge-gray">📄 —</span>
            <span id="p-ficha" class="badge badge-info">🗂️ —</span>
            <span id="p-estado" class="badge badge-success">● —</span>
          </div>
        </div>
        <div style="text-align:right">
          <div style="font-size:11px;color:var(--text-muted)">% de Avance Global</div>
          <div id="p-avance" style="font-size:36px;font-weight:900;color:var(--sena-green-light);font-family:'Plus Jakarta Sans',sans-serif">0%</div>
        </div>
      </div>
    </div>

    <!-- Charts row -->
    <div class="grid-2 mb-6">
      <div class="card">
        <div class="card-header"><span>🎯</span><h3>Avance por Competencia</h3></div>
        <div class="card-body">
          <div class="chart-wrapper" style="height:260px;">
            <canvas id="chart-comp-aprendiz"></canvas>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><span>📈</span><h3>Estado de Juicios</h3></div>
        <div class="card-body">
          <div class="chart-wrapper" style="height:260px;">
            <canvas id="chart-donut-aprendiz"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabla de juicios -->
    <div class="card">
      <div class="card-header">
        <span>📋</span>
        <h3>Detalle de Juicios Evaluativos</h3>
        <div class="topbar-actions gap-2 ml-auto" style="display:flex;align-items:center">
          <div class="search-box">
            <span class="search-icon">🔍</span>
            <input type="text" id="buscar-juicio" class="form-control" placeholder="Filtrar juicios..."
                   oninput="filtrarJuiciosTable()" style="width:200px;padding-left:32px">
          </div>
          <button class="btn btn-secondary btn-sm" onclick="exportToPDF('tabla-juicios-aprendiz','Detalle de Juicios','juicios_aprendiz')">📄 PDF</button>
          <button class="btn btn-primary btn-sm" onclick="exportToExcel('tabla-juicios-aprendiz','juicios_aprendiz')">📊 Excel</button>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-container">
          <table class="table" id="tabla-juicios-aprendiz">
            <thead>
              <tr>
                <th>#</th>
                <th>Competencia</th>
                <th>Resultado de Aprendizaje</th>
                <th>Juicio</th>
                <th>Fecha</th>
                <th>Funcionario</th>
              </tr>
            </thead>
            <tbody id="tbody-juicios-aprendiz"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Progreso por competencia (barra) -->
    <div class="card mt-4">
      <div class="card-header"><span>🏆</span><h3>Seguimiento por Competencia y Resultado</h3></div>
      <div class="card-body" id="seguimiento-comp"></div>
    </div>
  </div>
</main>

<script>
let chartCompAprendiz, chartDonutAprendiz;
let currentJuiciosData = []; // Guardar datos para filtrar localmente

async function loadAprendicesList() {
  const data = await api('api/dashboard.php', { action: 'aprendices_lista' });
  const sel = document.getElementById('sel-aprendiz');
  data.forEach(a => {
    const opt = document.createElement('option');
    opt.value = a.id_aprendiz;
    opt.textContent = `${a.nombre_completo} — ${a.numero_documento}`;
    sel.appendChild(opt);
  });
}

async function cargarPorId(id) {
  if (!id) return;
  await loadAprendizData(id);
}

async function buscarAprendiz() {
  const doc = document.getElementById('inp-documento').value.trim();
  if (!doc) { showToast('Ingresa un número de documento', 'warning'); return; }
  
  const data = await api('api/aprendiz.php', { action: 'buscar', documento: doc });
  if (data.error) { showToast('Aprendiz no encontrado', 'error'); return; }
  
  await loadAprendizData(data.id_aprendiz);
}

async function loadAprendizData(id_aprendiz) {
  const data = await api('api/dashboard.php', { action: 'juicios_por_aprendiz', id_aprendiz });
  if (!data.length) { 
    showToast('No hay juicios para este aprendiz aún', 'info'); 
    // Show profile without juicios
  }

  document.getElementById('perfil-section').classList.remove('hidden');
  document.getElementById('export-btns').style.display = 'flex';

  if (data.length) {
    const a = data[0];
    function getBadgeColor(estado) {
      const e = (estado || '').toLowerCase();
      if (e.includes('formacion') || e.includes('activo')) return 'info';
      if (e.includes('retir') || e.includes('cancel') || e.includes('deser')) return 'danger';
      if (e.includes('traslad')) return 'warning';
      return 'gray';
    }
    document.getElementById('p-nombre').textContent = `${a.nombre} ${a.apellido}`;
    document.getElementById('p-doc').textContent = `📄 ${a.numero_documento} (${a.tipo_documento})`;
    document.getElementById('p-ficha').textContent = `🗂️ ${a.ficha || 'Sin ficha'}`;
    document.getElementById('p-estado').className = `badge badge-${getBadgeColor(a.estado)}`;
    document.getElementById('p-estado').textContent = `● ${a.estado || '—'}`;
  }

  // Calcular avance global
  const total = data.length;
  const aprobados = data.filter(j => j.juicio === 'APROBADO').length;
  const pct = total > 0 ? Math.round((aprobados / total) * 100) : 0;
  document.getElementById('p-avance').textContent = pct + '%';

  // Tabla de juicios
  currentJuiciosData = data;
  renderJuiciosTable(data);

  // Agrupar por competencia para charts
  const byComp = {};
  data.forEach(j => {
    const comp = j.competencia || 'Sin competencia';
    if (!byComp[comp]) byComp[comp] = { total: 0, aprobados: 0, resultados: {} };
    byComp[comp].total++;
    if (j.juicio === 'APROBADO') byComp[comp].aprobados++;
    const res = j.resultado_aprendizaje || '—';
    if (!byComp[comp].resultados[res]) byComp[comp].resultados[res] = { total: 0, aprobados: 0 };
    byComp[comp].resultados[res].total++;
    if (j.juicio === 'APROBADO') byComp[comp].resultados[res].aprobados++;
  });

  const compNames = Object.keys(byComp);
  const compPcts = compNames.map(c => Math.round((byComp[c].aprobados / byComp[c].total) * 100));

  // Bar chart por competencia
  if (chartCompAprendiz) chartCompAprendiz.destroy();
  chartCompAprendiz = new Chart(document.getElementById('chart-comp-aprendiz'), {
    type: 'bar',
    data: {
      labels: compNames.map(c => c.slice(0, 20)),
      datasets: [{
        label: '% Aprobación',
        data: compPcts,
        backgroundColor: compPcts.map(p => p >= 80 ? 'rgba(57,169,0,0.7)' : p >= 50 ? 'rgba(250,140,22,0.7)' : 'rgba(255,77,79,0.7)'),
        borderRadius: 6, borderSkipped: false
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, max: 100, grid: { color: 'rgba(255,255,255,0.05)' } }, x: { grid: { display: false } } }
    }
  });

  // Donut aprobados/pendientes
  if (chartDonutAprendiz) chartDonutAprendiz.destroy();
  chartDonutAprendiz = new Chart(document.getElementById('chart-donut-aprendiz'), {
    type: 'doughnut',
    data: {
      labels: ['Aprobados', 'Por Evaluar'],
      datasets: [{ data: [aprobados, total - aprobados], backgroundColor: ['#39a900','#fa8c16'], borderWidth: 0 }]
    },
    options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom' } } }
  });

  // Seguimiento por competencia con barras individuales
  const seg = document.getElementById('seguimiento-comp');
  seg.innerHTML = compNames.map(comp => {
    const c = byComp[comp];
    const pctComp = Math.round((c.aprobados / c.total) * 100);
    const barColor = pctComp >= 80 ? '' : pctComp >= 50 ? 'orange' : 'red';
    const resultadosHTML = Object.entries(c.resultados).map(([res, r]) => {
      const pR = Math.round((r.aprobados / r.total) * 100);
      const bR = pR >= 80 ? '' : pR >= 50 ? 'orange' : 'red';
      return `<div style="margin-left:20px;margin-bottom:6px">
        <div style="display:flex;justify-content:space-between;margin-bottom:3px">
          <span class="text-sm text-muted">${res.slice(0,50)}</span>
          <span class="text-sm" style="font-weight:600">${pR}%</span>
        </div>
        <div class="progress-bar" style="height:4px"><div class="progress-fill ${bR}" style="width:${pR}%"></div></div>
      </div>`;
    }).join('');
    return `<div style="margin-bottom:20px">
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;align-items:center">
        <strong>${comp}</strong>
        <span class="badge ${pctComp >= 80 ? 'badge-success' : pctComp >= 50 ? 'badge-warning' : 'badge-danger'}">${pctComp}%</span>
      </div>
      <div class="progress-bar mb-2"><div class="progress-fill ${barColor}" style="width:${pctComp}%"></div></div>
      <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px">${c.aprobados}/${c.total} resultados aprobados — Resultados de aprendizaje:</div>
      ${resultadosHTML}
    </div>`;
  }).join('') || '<p class="text-muted">Sin datos de competencias</p>';
}

function renderJuiciosTable(data) {
  const tbody = document.getElementById('tbody-juicios-aprendiz');
  tbody.innerHTML = data.map((j, i) => `<tr>
    <td class="text-muted">${i+1}</td>
    <td><strong>${j.competencia || '—'}</strong></td>
    <td>${j.resultado_aprendizaje || '—'}</td>
    <td><span class="badge ${j.juicio === 'APROBADO' ? 'badge-success' : 'badge-warning'}">${j.juicio === 'APROBADO' ? '✅ Aprobado' : '⏳ Por Evaluar'}</span></td>
    <td class="text-muted">${j.fecha ? j.fecha.substring(0,10) : '—'}</td>
    <td>${j.funcionario || '—'}</td>
  </tr>`).join('') || '<tr><td colspan="6" class="empty-state"><div class="empty-icon">📭</div><p>Sin resultados para el filtro</p></td></tr>';
}

function filtrarJuiciosTable() {
  const query = document.getElementById('buscar-juicio').value.toLowerCase();
  const filtered = currentJuiciosData.filter(j => {
    return (j.competencia || '').toLowerCase().includes(query) || 
           (j.resultado_aprendizaje || '').toLowerCase().includes(query) || 
           (j.funcionario || '').toLowerCase().includes(query);
  });
  renderJuiciosTable(filtered);
}

// Check if opened with ID
const urlId = <?= $id_aprendiz ?>;
loadAprendicesList().then(() => {
  if (urlId) {
    document.getElementById('sel-aprendiz').value = urlId;
    loadAprendizData(urlId);
  }
});
</script>
<?php
$content = ob_get_clean();
renderLayout('Consulta por Aprendiz', 'consulta_aprendiz', $content);
?>
