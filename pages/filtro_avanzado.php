<?php
require_once '../includes/layout.php';
ob_start();
?>
<header class="topbar">
  <div>
    <div class="topbar-title">Filtro Avanzado de Juicios</div>
    <div class="topbar-subtitle">Busca y filtra por aprendiz, documento, estado, competencia y resultado de aprendizaje</div>
  </div>
</header>

<main class="page-content">
  <!-- Filtros -->
  <div class="card mb-4">
    <div class="card-header"><span>⚙️</span><h3>Filtros Avanzados</h3></div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px">
        <div class="form-group">
          <label class="form-label">Nombre / Apellido</label>
          <input type="text" id="f-nombre" class="form-control" placeholder="Buscar por nombre...">
        </div>
        <div class="form-group">
          <label class="form-label">Número de Documento</label>
          <input type="text" id="f-doc" class="form-control" placeholder="Nro. documento...">
        </div>
        <div class="form-group">
          <label class="form-label">Ficha de Formación</label>
          <select id="f-ficha" class="form-control">
            <option value="">Todas las fichas</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Estado del Aprendiz</label>
          <select id="f-estado" class="form-control">
            <option value="">Todos los estados</option>
            <option value="FORMACION">En Formación</option>
            <option value="RETIR">Retirado / Retiro Voluntario</option>
            <option value="TRASLAD">Trasladado</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Competencia</label>
          <select id="f-competencia" class="form-control" onchange="loadResultadosFiltro()">
            <option value="">Todas las competencias</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Resultado de Aprendizaje</label>
          <select id="f-resultado" class="form-control">
            <option value="">Todos los resultados</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Tipo de Juicio</label>
          <select id="f-juicio" class="form-control">
            <option value="">Todos</option>
            <option value="APROBADO">✅ Aprobado</option>
            <option value="POR_EVALUAR">⏳ Por Evaluar</option>
          </select>
        </div>
      </div>
      <div class="flex gap-2 mt-4">
        <button class="btn btn-primary" onclick="aplicarFiltros()">🔍 Aplicar Filtros</button>
        <button class="btn btn-secondary" onclick="limpiarFiltros()">🔄 Limpiar</button>
        <div style="margin-left:auto;display:flex;gap:8px">
          <button class="btn btn-secondary btn-sm" onclick="exportToPDF('tabla-filtro','Filtro Avanzado Juicios','filtro_juicios')">📄 PDF</button>
          <button class="btn btn-primary btn-sm" onclick="exportToExcel('tabla-filtro','filtro_juicios')">📊 Excel</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Resultados con mini stats -->
  <div class="stat-grid mb-4" id="filtro-stats" style="display:none">
    <div class="stat-card blue">
      <div class="stat-icon">📄</div>
      <div class="stat-value" id="f-count-total">0</div>
      <div class="stat-label">Registros encontrados</div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon">✅</div>
      <div class="stat-value" id="f-count-aprobados">0</div>
      <div class="stat-label">Aprobados</div>
    </div>
    <div class="stat-card orange">
      <div class="stat-icon">⏳</div>
      <div class="stat-value" id="f-count-por-evaluar">0</div>
      <div class="stat-label">Por Evaluar</div>
    </div>
    <div class="stat-card purple">
      <div class="stat-icon">📊</div>
      <div class="stat-value" id="f-pct-aprobacion">0%</div>
      <div class="stat-label">% Aprobación</div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span>📋</span>
      <h3 id="result-title">Resultados por Aprendiz</h3>
      <span id="result-count" class="badge badge-gray ml-auto" style="margin-left:auto"></span>
    </div>
    <div class="card-body p-0">
      <div id="resultados-grouped" class="aprendiz-list">
        <div class="empty-state">
          <div class="empty-icon">🔍</div>
          <h3>Aplica los filtros para buscar</h3>
          <p class="text-muted">Usa los controles de arriba para filtrar los datos</p>
        </div>
      </div>
      <!-- Tabla oculta para exportación -->
      <table class="table" id="tabla-filtro" style="display:none">
        <thead>
          <tr>
            <th>#</th><th>Aprendiz</th><th>Documento</th><th>Estado</th>
            <th>Ficha</th><th>Competencia</th><th>Resultado</th><th>Juicio</th><th>Fecha</th>
          </tr>
        </thead>
        <tbody id="tbody-filtro"></tbody>
      </table>
    </div>
  </div>
</main>

<style>
.aprendiz-list { padding: 16px; display: flex; flex-direction: column; gap: 12px; }

.aprendiz-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
  transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
}
.aprendiz-card:hover { border-color: var(--border-strong); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }
.aprendiz-card.is-open { border-color: var(--sena-green); }

.aprendiz-head {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 20px;
  cursor: pointer;
  user-select: none;
  transition: background 0.2s;
}
.aprendiz-head:hover { background: var(--bg-card-hover); }

.aprendiz-avatar {
  width: 44px; height: 44px;
  border-radius: 12px;
  background: linear-gradient(135deg, var(--sena-green), var(--sena-green-dark));
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 16px;
  flex-shrink: 0;
  font-family: 'Plus Jakarta Sans', sans-serif;
}

.aprendiz-info { flex: 1; min-width: 0; }
.aprendiz-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
  font-family: 'Plus Jakarta Sans', sans-serif;
}
.aprendiz-meta {
  display: flex;
  gap: 14px;
  font-size: 12px;
  color: var(--text-muted);
  margin-top: 4px;
  flex-wrap: wrap;
}
.aprendiz-meta span { display: inline-flex; align-items: center; gap: 4px; }

.aprendiz-stats {
  display: flex;
  gap: 8px;
  align-items: center;
}

.stat-pill {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
}
.stat-pill.green { background: rgba(57,169,0,0.12); color: #52c41a; }
.stat-pill.amber { background: rgba(250,140,22,0.12); color: #fa8c16; }
.stat-pill.gray { background: rgba(255,255,255,0.04); color: var(--text-secondary); }

.aprendiz-toggle {
  width: 32px; height: 32px;
  border-radius: 8px;
  background: var(--bg-input);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-secondary);
  transition: transform 0.3s, background 0.2s;
}
.aprendiz-card.is-open .aprendiz-toggle { transform: rotate(180deg); background: var(--sena-green); color: white; }

.aprendiz-body {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.4s cubic-bezier(0.4,0,0.2,1);
}
.aprendiz-card.is-open .aprendiz-body { 
  max-height: 450px; 
  overflow-y: auto; 
}

/* Custom Scrollbar for the expanded list */
.aprendiz-body::-webkit-scrollbar { width: 6px; }
.aprendiz-body::-webkit-scrollbar-track { background: var(--bg-base); }
.aprendiz-body::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 10px; }
.aprendiz-body::-webkit-scrollbar-thumb:hover { background: var(--sena-green); }

.juicios-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  margin: 0;
}
.juicios-table thead {
  position: sticky;
  top: 0;
  z-index: 10;
  background: var(--bg-card);
  box-shadow: 0 1px 0 var(--border);
}
.juicios-table th {
  padding: 10px 16px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-muted);
  text-align: left;
  border-bottom: 1px solid var(--border);
}
.juicios-table td {
  padding: 12px 16px;
  font-size: 12px;
  color: var(--text-secondary);
  border-bottom: 1px solid var(--border);
  vertical-align: top;
}
.juicios-table tr:last-child td { border-bottom: none; }
.juicios-table tr:hover { background: rgba(255,255,255,0.02); }

.comp-name { font-weight: 600; color: var(--text-primary); }

.estado-pill {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.estado-pill.info { background: rgba(24,144,255,0.12); color: #1890ff; }
.estado-pill.danger { background: rgba(255,77,79,0.12); color: #ff4d4f; }
.estado-pill.warning { background: rgba(250,140,22,0.12); color: #fa8c16; }
.estado-pill.gray { background: rgba(255,255,255,0.05); color: var(--text-secondary); }

.progress-mini {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 90px;
}
.progress-mini-bar {
  flex: 1;
  height: 5px;
  background: rgba(255,255,255,0.05);
  border-radius: 4px;
  overflow: hidden;
}
.progress-mini-fill {
  height: 100%;
  background: linear-gradient(90deg, #39a900, #52c41a);
  border-radius: 4px;
}
.progress-mini-pct { font-size: 11px; font-weight: 700; color: var(--text-primary); min-width: 32px; text-align: right; }
</style>

<script>
async function initFiltros() {
  const [comps, fichas] = await Promise.all([
    api('api/dashboard.php', { action: 'competencias' }),
    api('api/dashboard.php', { action: 'fichas' })
  ]);
  
  const selComp = document.getElementById('f-competencia');
  comps.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c.id_competencia;
    opt.textContent = c.nombre;
    selComp.appendChild(opt);
  });

  const selFicha = document.getElementById('f-ficha');
  fichas.forEach(f => {
    const opt = document.createElement('option');
    opt.value = f.id_ficha;
    opt.textContent = f.nombre;
    selFicha.appendChild(opt);
  });
}

async function loadResultadosFiltro() {
  const id = document.getElementById('f-competencia').value;
  const data = await api('api/dashboard.php', { action: 'resultados', id_competencia: id });
  const sel = document.getElementById('f-resultado');
  sel.innerHTML = '<option value="">Todos los resultados</option>';
  data.forEach(r => {
    const opt = document.createElement('option');
    opt.value = r.id_resultado;
    opt.textContent = r.nombre;
    sel.appendChild(opt);
  });
}

function getEstadoClass(estado) {
  const e = (estado || '').toLowerCase();
  if (e.includes('formacion') || e.includes('activo')) return 'info';
  if (e.includes('retir') || e.includes('cancel') || e.includes('deser')) return 'danger';
  if (e.includes('traslad')) return 'warning';
  return 'gray';
}

function getInitials(nombre, apellido) {
  return ((nombre || '').charAt(0) + (apellido || '').charAt(0)).toUpperCase();
}

function groupByAprendiz(data) {
  const groups = {};
  data.forEach(d => {
    const key = d.id_aprendiz;
    if (!groups[key]) {
      groups[key] = {
        id_aprendiz: d.id_aprendiz,
        nombre: d.nombre,
        apellido: d.apellido,
        numero_documento: d.numero_documento,
        estado: d.estado,
        ficha: d.ficha,
        juicios: []
      };
    }
    groups[key].juicios.push({
      competencia: d.competencia,
      resultado: d.resultado,
      juicio: d.juicio,
      fecha: d.fecha,
      funcionario: d.funcionario
    });
  });
  return Object.values(groups);
}

function toggleAprendiz(idx) {
  const card = document.getElementById('apr-card-' + idx);
  if (card) card.classList.toggle('is-open');
}

async function aplicarFiltros() {
  const params = {
    action: 'filtro_avanzado',
    nombre: document.getElementById('f-nombre').value,
    documento: document.getElementById('f-doc').value,
    estado: document.getElementById('f-estado').value,
    id_ficha: document.getElementById('f-ficha').value,
    id_competencia: document.getElementById('f-competencia').value,
    id_resultado: document.getElementById('f-resultado').value,
    tipo_juicio: document.getElementById('f-juicio').value,
  };

  const container = document.getElementById('resultados-grouped');
  container.innerHTML = '<div class="loading-overlay" style="padding:60px"><div class="spinner"></div> Buscando...</div>';

  const data = await api('api/dashboard.php', params);

  const aprobados = data.filter(d => d.juicio === 'APROBADO').length;
  const porEvaluar = data.filter(d => d.juicio === 'POR_EVALUAR').length;
  const pct = data.length > 0 ? Math.round((aprobados / data.length) * 100) : 0;

  document.getElementById('filtro-stats').style.display = 'grid';
  document.getElementById('f-count-total').textContent = data.length;
  document.getElementById('f-count-aprobados').textContent = aprobados;
  document.getElementById('f-count-por-evaluar').textContent = porEvaluar;
  document.getElementById('f-pct-aprobacion').textContent = pct + '%';

  // Agrupar por aprendiz
  const grouped = groupByAprendiz(data);
  document.getElementById('result-count').textContent = grouped.length + ' aprendices · ' + data.length + ' juicios';

  // Llenar tabla oculta para exportación
  document.getElementById('tbody-filtro').innerHTML = data.map((d,i) => `<tr>
    <td>${i+1}</td><td>${d.nombre} ${d.apellido}</td><td>${d.numero_documento||''}</td>
    <td>${d.estado||''}</td><td>${d.ficha||''}</td><td>${d.competencia||''}</td>
    <td>${d.resultado||''}</td><td>${d.juicio||''}</td><td>${d.fecha?d.fecha.substring(0,10):''}</td>
  </tr>`).join('');

  if (!grouped.length) {
    container.innerHTML = '<div class="empty-state"><div class="empty-icon">📭</div><h3>Sin resultados</h3><p class="text-muted">No se encontraron coincidencias para los filtros aplicados</p></div>';
    return;
  }

  container.innerHTML = grouped.map((g, i) => {
    const aprobs = g.juicios.filter(j => j.juicio === 'APROBADO').length;
    const pendientes = g.juicios.filter(j => j.juicio === 'POR_EVALUAR').length;
    const pctApr = g.juicios.length > 0 ? Math.round((aprobs / g.juicios.length) * 100) : 0;

    const juiciosHtml = g.juicios.map(j => `
      <tr>
        <td class="comp-name">${j.competencia || '—'}</td>
        <td>${j.resultado || '—'}</td>
        <td><span class="estado-pill ${j.juicio === 'APROBADO' ? 'info' : 'warning'}" style="background: ${j.juicio === 'APROBADO' ? 'rgba(57,169,0,0.12)' : 'rgba(250,140,22,0.12)'}; color: ${j.juicio === 'APROBADO' ? '#52c41a' : '#fa8c16'}">${j.juicio === 'APROBADO' ? '✓ Aprobado' : '⏱ Por Evaluar'}</span></td>
        <td>${j.fecha ? j.fecha.substring(0,10) : '—'}</td>
        <td>${j.funcionario || '—'}</td>
      </tr>
    `).join('');

    return `
      <div class="aprendiz-card" id="apr-card-${i}">
        <div class="aprendiz-head" onclick="toggleAprendiz(${i})">
          <div class="aprendiz-avatar">${getInitials(g.nombre, g.apellido)}</div>
          <div class="aprendiz-info">
            <h4 class="aprendiz-name">${g.nombre} ${g.apellido}</h4>
            <div class="aprendiz-meta">
              <span>📄 ${g.numero_documento || '—'}</span>
              <span>🗂 Ficha ${g.ficha || '—'}</span>
              <span class="estado-pill ${getEstadoClass(g.estado)}">${g.estado || '—'}</span>
            </div>
          </div>
          <div class="aprendiz-stats">
            <div class="progress-mini">
              <div class="progress-mini-bar"><div class="progress-mini-fill" style="width:${pctApr}%"></div></div>
              <div class="progress-mini-pct">${pctApr}%</div>
            </div>
            <span class="stat-pill green">✓ ${aprobs}</span>
            <span class="stat-pill amber">⏱ ${pendientes}</span>
            <span class="stat-pill gray">${g.juicios.length} total</span>
          </div>
          <div class="aprendiz-toggle">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          </div>
        </div>
        <div class="aprendiz-body">
          <table class="juicios-table">
            <thead>
              <tr>
                <th>Competencia</th>
                <th>Resultado de Aprendizaje</th>
                <th>Juicio</th>
                <th>Fecha</th>
                <th>Funcionario</th>
              </tr>
            </thead>
            <tbody>${juiciosHtml}</tbody>
          </table>
        </div>
      </div>
    `;
  }).join('');
}

function limpiarFiltros() {
  ['f-nombre','f-doc'].forEach(id => document.getElementById(id).value = '');
  ['f-estado','f-ficha','f-competencia','f-resultado','f-juicio'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('filtro-stats').style.display = 'none';
  document.getElementById('resultados-grouped').innerHTML = '<div class="empty-state"><div class="empty-icon">🔍</div><h3>Aplica los filtros para buscar</h3><p class="text-muted">Usa los controles de arriba para filtrar los datos</p></div>';
  document.getElementById('result-count').textContent = '';
}

initFiltros();
</script>
<?php
$content = ob_get_clean();
renderLayout('Filtro Avanzado', 'filtro_avanzado', $content);
?>
