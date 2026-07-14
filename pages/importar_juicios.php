<?php
require_once '../includes/layout.php';
ob_start();
?>
<header class="topbar">
  <div>
    <div class="topbar-title">Importar Juicios Evaluativos</div>
    <div class="topbar-subtitle">Carga masiva de registros de juicios evaluativos en formato CSV o Excel</div>
  </div>
  <div class="topbar-actions">
    <a href="/juicios_evaluativos/assets/templates/template_juicios.csv"
       class="btn btn-secondary btn-sm" download>📥 Plantilla CSV</a>
  </div>
</header>

<main class="page-content">
  <div class="grid-2">
    <div class="card">
      <div class="card-header"><span>📥</span><h3>Importar Archivo de Juicios</h3></div>
      <div class="card-body">
        <div class="alert alert-info">
          ℹ️ <div>
            <strong>Formatos aceptados:</strong> CSV, XLSX o XLS.<br>
            <strong>Columnas requeridas:</strong>
            <code>Numero_Documento, Competencia, Resultado_Aprendizaje, Funcionario, Juicio (APROBADO/POR_EVALUAR), Fecha</code><br><br>
            Si una competencia o resultado no existe, se creará automáticamente.
          </div>
        </div>

        <!-- Badge tipo archivo -->
        <div id="file-type-badge-j" style="display:none;align-items:center;gap:8px;margin-bottom:12px">
          <span class="badge" id="badge-tipo-j" style="font-size:13px;padding:6px 12px"></span>
          <span id="badge-info-j" class="text-sm text-muted"></span>
        </div>

        <form id="form-juicios" enctype="multipart/form-data">
          <div class="upload-zone" id="upload-zone-j" onclick="document.getElementById('file-juicios').click()">
            <div class="upload-icon" id="icon-j">📊</div>
            <div class="upload-title" id="title-j">Arrastra el archivo de juicios aquí</div>
            <div class="upload-sub" id="sub-j">Formatos: CSV, XLSX, XLS — Máx. 10MB</div>
          </div>
          <input type="file" id="file-juicios" name="archivo"
                 accept=".csv,.xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                 style="display:none" onchange="onJuicioFile(this)">

          <!-- Progreso conversión -->
          <div id="conv-progress-j" class="hidden mt-3">
            <div class="alert alert-info" style="align-items:center;gap:12px">
              <div class="spinner" style="width:18px;height:18px;border-width:2px;flex-shrink:0"></div>
              <span id="conv-msg-j">Convirtiendo Excel a CSV...</span>
            </div>
          </div>

          <div class="mt-4 flex gap-2 justify-between">
            <button type="button" class="btn btn-secondary" onclick="previewJuicios()">👁️ Vista Previa</button>
            <button type="submit" class="btn btn-primary btn-lg" id="btn-importar" disabled>🚀 Importar Juicios</button>
          </div>
        </form>
        <div id="result-juicios" class="hidden mt-4"></div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span>📋</span><h3>Vista Previa del Archivo</h3></div>
      <div class="card-body p-0">
        <div id="preview-juicios">
          <div class="empty-state">
            <div class="empty-icon">📄</div>
            <h3>Sin archivo</h3>
            <p class="text-muted">Selecciona un CSV o Excel para ver la vista previa</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Juicios recientes -->
  <div class="card mt-4">
    <div class="card-header">
      <span>📋</span>
      <h3>Juicios Registrados Recientemente</h3>
      <div class="topbar-actions gap-2 ml-auto">
        <button class="btn btn-secondary btn-sm" onclick="exportToPDF('tabla-juicios','Juicios Evaluativos','juicios')">📄 PDF</button>
        <button class="btn btn-primary btn-sm" onclick="exportToExcel('tabla-juicios','juicios')">📊 Excel</button>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-container">
        <table class="table" id="tabla-juicios">
          <thead>
            <tr>
              <th>#</th>
              <th>Aprendiz</th>
              <th>Documento</th>
              <th>Competencia</th>
              <th>Resultado de Aprendizaje</th>
              <th>Juicio</th>
              <th>Fecha</th>
              <th>Funcionario</th>
            </tr>
          </thead>
          <tbody id="tbody-juicios">
            <tr><td colspan="8" class="loading-overlay"><div class="spinner"></div></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<script>
let selectedFileJ = null;
let convertedCsvBlobJ = null;

/* ── Helpers ── */
function getExtJ(name) { return name.split('.').pop().toLowerCase(); }

function excelToCSV_J(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = e => {
      try {
        const wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
        resolve(XLSX.utils.sheet_to_csv(wb.Sheets[wb.SheetNames[0]]));
      } catch(err) { reject(err); }
    };
    reader.onerror = reject;
    reader.readAsArrayBuffer(file);
  });
}

/* ── Drag & Drop ── */
const uploadZoneJ = document.getElementById('upload-zone-j');
uploadZoneJ.addEventListener('dragover', e => { e.preventDefault(); uploadZoneJ.classList.add('dragover'); });
uploadZoneJ.addEventListener('dragleave', () => uploadZoneJ.classList.remove('dragover'));
uploadZoneJ.addEventListener('drop', e => {
  e.preventDefault();
  uploadZoneJ.classList.remove('dragover');
  const file = e.dataTransfer.files[0];
  if (file) {
    try { const dt = new DataTransfer(); dt.items.add(file); document.getElementById('file-juicios').files = dt.files; } catch(ex) {}
    onJuicioFile({ files: [file] });
  }
});

/* ── Selección de archivo ── */
async function onJuicioFile(input) {
  const file = input.files[0];
  if (!file) return;
  selectedFileJ = file;
  convertedCsvBlobJ = null;

  const ext = getExtJ(file.name);
  const isExcel = (ext === 'xlsx' || ext === 'xls');

  // UI
  document.getElementById('icon-j').textContent  = isExcel ? '📊' : '📄';
  document.getElementById('title-j').textContent = file.name;
  document.getElementById('sub-j').textContent   =
    `${(file.size / 1024).toFixed(1)} KB — ${ext.toUpperCase()}${isExcel ? ' (se convertirá a CSV automáticamente)' : ''}`;

  // Badge
  const bWrap = document.getElementById('file-type-badge-j');
  bWrap.style.display = 'flex';
  document.getElementById('badge-tipo-j').className = isExcel ? 'badge badge-success' : 'badge badge-info';
  document.getElementById('badge-tipo-j').textContent = isExcel ? `📊 ${ext.toUpperCase()} detectado` : '📄 CSV detectado';
  document.getElementById('badge-info-j').textContent = isExcel
    ? 'El Excel se convertirá a CSV automáticamente antes de enviarse.'
    : 'El archivo se enviará directamente al servidor.';

  if (isExcel) {
    document.getElementById('btn-importar').disabled = true;
    document.getElementById('conv-progress-j').classList.remove('hidden');
    try {
      const csv = await excelToCSV_J(file);
      convertedCsvBlobJ = new Blob([csv], { type: 'text/csv' });
      document.getElementById('conv-progress-j').classList.add('hidden');
      document.getElementById('icon-j').textContent = '✅';
      showToast(`Excel convertido: ${(convertedCsvBlobJ.size/1024).toFixed(1)} KB`, 'success');
    } catch(err) {
      document.getElementById('conv-progress-j').classList.add('hidden');
      showToast('Error al leer el Excel: ' + err.message, 'error');
      return;
    }
  } else {
    document.getElementById('icon-j').textContent = '✅';
  }

  document.getElementById('btn-importar').disabled = false;
}

/* ── Vista Previa ── */
async function previewJuicios() {
  if (!selectedFileJ) { showToast('Selecciona un archivo primero', 'warning'); return; }
  const ext = getExtJ(selectedFileJ.name);

  if (ext === 'xlsx' || ext === 'xls') {
    const reader = new FileReader();
    reader.onload = function(e) {
      const wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
      const ws = wb.Sheets[wb.SheetNames[0]];
      const rows = XLSX.utils.sheet_to_json(ws, { header: 1 });
      const headers = rows[0] || [];
      const body = rows.slice(1, 6);
      document.getElementById('preview-juicios').innerHTML = `
        <div class="table-container" style="max-height:300px;overflow:auto">
          <table class="table">
            <thead><tr>${headers.map(h => `<th>${h ?? ''}</th>`).join('')}</tr></thead>
            <tbody>${body.map(r => `<tr>${headers.map((_, i) => `<td>${r[i] ?? ''}</td>`).join('')}</tr>`).join('')}</tbody>
          </table>
        </div>
        <p class="text-muted text-sm" style="padding:8px 16px">
          Hoja: <strong>${wb.SheetNames[0]}</strong> — Mostrando primeras 5 filas
        </p>`;
    };
    reader.readAsArrayBuffer(selectedFileJ);
  } else {
    const reader = new FileReader();
    reader.onload = function(e) {
      const lines = e.target.result.split('\n').slice(0, 6);
      const headers = lines[0]?.split(',') || [];
      const rows = lines.slice(1);
      document.getElementById('preview-juicios').innerHTML = `
        <div class="table-container" style="max-height:300px;overflow:auto">
          <table class="table">
            <thead><tr>${headers.map(h => `<th>${h.trim()}</th>`).join('')}</tr></thead>
            <tbody>${rows.map(r => `<tr>${r.split(',').map(c => `<td>${c.trim()}</td>`).join('')}</tr>`).join('')}</tbody>
          </table>
        </div>`;
    };
    reader.readAsText(selectedFileJ, 'UTF-8');
  }
}

/* ── Submit ── */
document.getElementById('form-juicios').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-importar');
  btn.disabled = true;
  btn.textContent = '⏳ Importando...';

  const ext = getExtJ(selectedFileJ.name);
  const fd = new FormData();

  if ((ext === 'xlsx' || ext === 'xls') && convertedCsvBlobJ) {
    fd.append('archivo', convertedCsvBlobJ, selectedFileJ.name.replace(/\.(xlsx|xls)$/i, '.csv'));
  } else {
    fd.append('archivo', document.getElementById('file-juicios').files[0]);
  }

  const data = await apiPost('api/juicios.php?action=importar', fd);
  btn.disabled = false;
  btn.textContent = '🚀 Importar Juicios';

  const rc = document.getElementById('result-juicios');
  rc.classList.remove('hidden');

  if (data.error) {
    rc.innerHTML = `<div class="alert alert-error">❌ ${data.error}</div>`;
    showToast(data.error, 'error');
    return;
  }

  const headersInfo = data.headers_detectados?.length
    ? `<div style="margin-top:10px;padding:10px;background:var(--bg-input);border-radius:6px;font-size:12px">
        <strong>Columnas detectadas en tu archivo:</strong><br>
        ${data.headers_detectados.map((h,i) => `<code style="margin:2px 4px;padding:2px 6px;background:rgba(255,255,255,0.08);border-radius:3px">${i+1}. ${h}</code>`).join('')}
      </div>`
    : '';

  if (data.insertados === 0 && data.errores?.length) {
    rc.innerHTML = `<div class="alert alert-warning">
      ⚠️ <div style="flex:1">
        <strong>No se procesó ningún juicio.</strong><br>
        <strong>Filas en el archivo:</strong> ${data.total_filas ?? '?'}<br>
        ${headersInfo}
        <details style="margin-top:10px">
          <summary style="cursor:pointer;color:var(--accent-gold)">Ver errores detallados (${data.errores.length})</summary>
          <ul style="margin-top:6px;font-size:12px">${data.errores.map(e => `<li>${e}</li>`).join('')}</ul>
        </details>
      </div>
    </div>`;
    showToast('Revisa los encabezados de tu archivo', 'warning');
  } else {
    const tieneErrores = data.errores?.length > 0;
    rc.innerHTML = `<div class="alert alert-${tieneErrores ? 'warning' : 'success'}">
      ${tieneErrores ? '⚠️' : '✅'} <div style="flex:1">
        <strong>Importación ${data.insertados > 0 ? 'exitosa' : 'completada'}:</strong> ${data.insertados} juicios registrados.
        ${tieneErrores ? `<br><span class="text-warning">Advertencias: ${data.errores.slice(0,5).join(' — ')}</span>` : ''}
        ${headersInfo}
      </div>
    </div>`;
    showToast(`✅ ${data.insertados} juicios importados`, 'success');
    loadJuicios();
  }
});

/* ── Tabla de juicios ── */
async function loadJuicios() {
  const data = await api('api/juicios.php', { action: 'listar' });
  const tbody = document.getElementById('tbody-juicios');
  tbody.innerHTML = data.map((j, i) => `<tr>
    <td class="text-muted">${i+1}</td>
    <td><strong>${j.aprendiz || '—'}</strong></td>
    <td class="text-muted">${j.numero_documento || '—'}</td>
    <td>${j.competencia || '—'}</td>
    <td>${j.resultado || '—'}</td>
    <td><span class="badge ${j.tipo === 'APROBADO' ? 'badge-success' : 'badge-warning'}">
      ${j.tipo === 'APROBADO' ? '✅ Aprobado' : '⏳ Por Evaluar'}
    </span></td>
    <td class="text-muted">${j.fecha ? j.fecha.substring(0,10) : '—'}</td>
    <td>${j.funcionario || '—'}</td>
  </tr>`).join('') || '<tr><td colspan="8" class="empty-state"><div class="empty-icon">📭</div><p>Sin juicios registrados</p></td></tr>';
}

loadJuicios();
</script>
<?php
$content = ob_get_clean();
renderLayout('Importar Juicios Evaluativos', 'importar_juicios', $content);
?>
