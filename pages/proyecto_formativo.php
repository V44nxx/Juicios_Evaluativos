<?php
require_once '../includes/layout.php';
ob_start();
?>
<header class="topbar">
  <div>
    <div class="topbar-title">Proyecto Formativo — Fases y Actividades</div>
    <div class="topbar-subtitle">Relaciona competencias y resultados de aprendizaje a las fases del proyecto</div>
  </div>
  <div class="topbar-actions">
    <button class="btn btn-primary" onclick="openModal('modal-fase')">➕ Nueva Fase</button>
  </div>
</header>

<main class="page-content">
  <div id="fases-container">
    <div class="loading-overlay"><div class="spinner"></div> Cargando fases...</div>
  </div>
</main>

<!-- Modal Nueva Fase -->
<div class="modal-overlay" id="modal-fase">
  <div class="modal">
    <div class="modal-header">
      <span>🗂️</span>
      <h3>Nueva Fase del Proyecto</h3>
      <button class="btn btn-secondary btn-sm btn-icon" onclick="closeModal('modal-fase')">✕</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Nombre de la Fase *</label>
        <input type="text" id="fase-nombre" class="form-control" placeholder="Ej: Fase de Planeación">
      </div>
      <div class="form-group">
        <label class="form-label">Descripción</label>
        <textarea id="fase-desc" class="form-control" rows="3" placeholder="Descripción de la fase..."></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Orden</label>
        <input type="number" id="fase-orden" class="form-control" value="1" min="1">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-fase')">Cancelar</button>
      <button class="btn btn-primary" onclick="crearFase()">✅ Crear Fase</button>
    </div>
  </div>
</div>

<!-- Modal Nueva Actividad -->
<div class="modal-overlay" id="modal-actividad">
  <div class="modal">
    <div class="modal-header">
      <span>📋</span>
      <h3>Nueva Actividad</h3>
      <button class="btn btn-secondary btn-sm btn-icon" onclick="closeModal('modal-actividad')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="act-id-fase">
      <div class="form-group">
        <label class="form-label">Nombre de la Actividad *</label>
        <input type="text" id="act-nombre" class="form-control" placeholder="Ej: Diseño del prototipo">
      </div>
      <div class="form-group">
        <label class="form-label">Descripción</label>
        <textarea id="act-desc" class="form-control" rows="2" placeholder="Descripción de la actividad..."></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Competencias relacionadas</label>
        <div id="checkboxes-comp" style="max-height:150px;overflow-y:auto;background:var(--bg-input);border:1px solid var(--border);border-radius:6px;padding:10px"></div>
      </div>
      <div class="form-group">
        <label class="form-label">Resultados de Aprendizaje relacionados</label>
        <div id="checkboxes-res" style="max-height:150px;overflow-y:auto;background:var(--bg-input);border:1px solid var(--border);border-radius:6px;padding:10px"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-actividad')">Cancelar</button>
      <button class="btn btn-primary" onclick="crearActividad()">✅ Crear Actividad</button>
    </div>
  </div>
</div>

<script>
let competenciasData = [], resultadosData = [];

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

async function loadFases() {
  const fases = await api('api/proyecto.php', { action: 'listar_fases' });
  const container = document.getElementById('fases-container');

  if (!fases.length) {
    container.innerHTML = `<div class="empty-state"><div class="empty-icon">📋</div><h3>Sin fases definidas</h3><p class="text-muted">Crea la primera fase del proyecto formativo</p><br><button class="btn btn-primary" onclick="openModal('modal-fase')">➕ Nueva Fase</button></div>`;
    return;
  }

  container.innerHTML = fases.map(fase => `
    <div class="card mb-4" id="fase-${fase.id_fase}">
      <div class="card-header" style="background:linear-gradient(135deg,rgba(57,169,0,0.1),rgba(57,169,0,0.02))">
        <span style="font-size:20px">🗂️</span>
        <div style="flex:1">
          <h3>${fase.nombre}</h3>
          ${fase.descripcion ? `<div class="text-sm text-muted mt-1">${fase.descripcion}</div>` : ''}
        </div>
        <span class="badge badge-gray">Orden: ${fase.orden}</span>
        <span class="badge badge-info">${fase.total_actividades} actividades</span>
        <button class="btn btn-primary btn-sm" onclick="openNuevaActividad(${fase.id_fase})">➕ Actividad</button>
        <button class="btn btn-danger btn-sm" onclick="eliminarFase(${fase.id_fase})">🗑️</button>
      </div>
      <div class="card-body p-0" id="actividades-${fase.id_fase}">
        <div class="loading-overlay" style="padding:20px"><div class="spinner"></div></div>
      </div>
    </div>
  `).join('');

  // Load actividades for each phase
  fases.forEach(f => loadActividades(f.id_fase));
}

async function loadActividades(id_fase) {
  const data = await api('api/proyecto.php', { action: 'listar_actividades', id_fase });
  const container = document.getElementById(`actividades-${id_fase}`);

  if (!data.length) {
    container.innerHTML = '<div class="empty-state" style="padding:24px"><p class="text-muted">Sin actividades aún. Agrega la primera actividad.</p></div>';
    return;
  }

  container.innerHTML = `
    <div class="table-container">
      <table class="table">
        <thead><tr><th>#</th><th>Actividad</th><th>Descripción</th><th>Competencias</th><th>Resultados de Aprendizaje</th><th>Acciones</th></tr></thead>
        <tbody>
          ${data.map((a, i) => `<tr>
            <td class="text-muted">${i+1}</td>
            <td><strong>${a.nombre}</strong></td>
            <td class="text-muted">${a.descripcion || '—'}</td>
            <td>${a.competencias ? a.competencias.split(', ').map(c => `<span class="badge badge-success" style="margin:1px">${c.slice(0,20)}</span>`).join('') : '—'}</td>
            <td>${a.resultados ? a.resultados.split(', ').map(r => `<span class="badge badge-info" style="margin:1px">${r.slice(0,25)}</span>`).join('') : '—'}</td>
            <td><button class="btn btn-danger btn-sm" onclick="eliminarActividad(${a.id_actividad}, ${id_fase})">🗑️</button></td>
          </tr>`).join('')}
        </tbody>
      </table>
    </div>`;
}

async function loadCompetenciasResultados() {
  if (competenciasData.length) return;
  const data = await api('api/proyecto.php', { action: 'competencias_resultados' });
  competenciasData = data.competencias || [];
  resultadosData = data.resultados || [];
}

async function openNuevaActividad(id_fase) {
  document.getElementById('act-id-fase').value = id_fase;
  document.getElementById('act-nombre').value = '';
  document.getElementById('act-desc').value = '';

  await loadCompetenciasResultados();

  document.getElementById('checkboxes-comp').innerHTML = competenciasData.map(c => `
    <label style="display:flex;align-items:center;gap:8px;padding:4px 0;cursor:pointer;font-size:13px">
      <input type="checkbox" class="chk-comp" value="${c.id_competencia}" style="accent-color:var(--sena-green)">
      ${c.nombre}
    </label>
  `).join('') || '<p class="text-muted text-sm">Sin competencias disponibles</p>';

  document.getElementById('checkboxes-res').innerHTML = resultadosData.map(r => `
    <label style="display:flex;align-items:center;gap:8px;padding:4px 0;cursor:pointer;font-size:13px">
      <input type="checkbox" class="chk-res" value="${r.id_resultado}" style="accent-color:var(--sena-green)">
      <span><strong>${r.nombre.slice(0,40)}</strong> <span class="text-muted text-sm">— ${r.competencia||''}</span></span>
    </label>
  `).join('') || '<p class="text-muted text-sm">Sin resultados disponibles</p>';

  openModal('modal-actividad');
}

async function crearFase() {
  const nombre = document.getElementById('fase-nombre').value.trim();
  if (!nombre) { showToast('El nombre es requerido', 'warning'); return; }

  const res = await fetch('/juicios_evaluativos/api/proyecto.php?action=crear_fase', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      nombre,
      descripcion: document.getElementById('fase-desc').value,
      orden: parseInt(document.getElementById('fase-orden').value) || 1
    })
  });
  const data = await res.json();
  if (data.success) {
    showToast('Fase creada exitosamente', 'success');
    closeModal('modal-fase');
    loadFases();
  } else {
    showToast(data.error || 'Error al crear fase', 'error');
  }
}

async function crearActividad() {
  const nombre = document.getElementById('act-nombre').value.trim();
  const id_fase = document.getElementById('act-id-fase').value;
  if (!nombre) { showToast('El nombre es requerido', 'warning'); return; }

  const competencias = [...document.querySelectorAll('.chk-comp:checked')].map(c => parseInt(c.value));
  const resultados = [...document.querySelectorAll('.chk-res:checked')].map(r => parseInt(r.value));

  const res = await fetch('/juicios_evaluativos/api/proyecto.php?action=crear_actividad', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ nombre, descripcion: document.getElementById('act-desc').value, id_fase: parseInt(id_fase), competencias, resultados })
  });
  const data = await res.json();
  if (data.success) {
    showToast('Actividad creada', 'success');
    closeModal('modal-actividad');
    loadActividades(id_fase);
  } else {
    showToast(data.error || 'Error al crear actividad', 'error');
  }
}

async function eliminarFase(id) {
  if (!confirm('¿Eliminar esta fase y todas sus actividades?')) return;
  const res = await fetch(`/juicios_evaluativos/api/proyecto.php?action=eliminar_fase&id=${id}`, { method: 'DELETE' });
  const data = await res.json();
  if (data.success) { showToast('Fase eliminada', 'success'); loadFases(); }
}

async function eliminarActividad(id, id_fase) {
  if (!confirm('¿Eliminar esta actividad?')) return;
  const res = await fetch(`/juicios_evaluativos/api/proyecto.php?action=eliminar_actividad&id=${id}`, { method: 'DELETE' });
  const data = await res.json();
  if (data.success) { showToast('Actividad eliminada', 'success'); loadActividades(id_fase); }
}

loadFases();
</script>
<?php
$content = ob_get_clean();
renderLayout('Proyecto Formativo', 'proyecto_formativo', $content);
?>
