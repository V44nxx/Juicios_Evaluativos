<?php
require_once '../includes/layout.php';
ob_start();
?>
<header class="topbar">
  <div>
    <div class="topbar-title">Carga Masiva de Aprendices</div>
    <div class="topbar-subtitle">Importar aprendices desde archivo Sofia Plus (CSV o Excel)</div>
  </div>
  <div class="topbar-actions">
    <a href="<?= $GLOBALS['base_url'] ?>assets/templates/template_aprendices.csv"
       class="btn btn-secondary btn-sm" download>📥 Descargar Plantilla CSV</a>
  </div>
</header>

<main class="page-content">
  <div class="grid-2">
    <!-- Upload Card -->
    <div class="card">
      <div class="card-header">
        <span>📤</span>
        <h3>Cargar Archivo de Aprendices</h3>
      </div>
      <div class="card-body">
        <div class="alert alert-info">
          ℹ️ <div>
            <strong>Formatos aceptados:</strong> CSV, XLSX o XLS exportado de Sofia Plus.<br>
            <strong>Columnas requeridas:</strong> Número de documento, Tipo de documento, Nombres, Apellidos, Estado, Ficha.<br>
            El sistema detecta automáticamente las columnas y crea fichas si no existen.
          </div>
        </div>

        <!-- Badge de tipo de archivo detectado -->
        <div id="file-type-badge" class="hidden mb-4" style="display:none">
          <span class="badge" id="badge-tipo-archivo" style="font-size:13px;padding:6px 12px"></span>
          <span id="badge-conversion-info" class="text-sm text-muted ml-auto" style="margin-left:8px"></span>
        </div>

        <form id="form-carga" enctype="multipart/form-data">
          <div class="upload-zone" id="upload-zone" onclick="document.getElementById('file-input').click()">
            <div class="upload-icon" id="upload-icon">📁</div>
            <div class="upload-title" id="upload-title">Arrastra tu archivo aquí o haz clic para seleccionar</div>
            <div class="upload-sub" id="upload-sub">Formatos: CSV, XLSX, XLS (exportado de Sofia Plus) — Máx. 10MB</div>
          </div>
          <input type="file" id="file-input" name="archivo"
                 accept=".csv,.xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                 style="display:none" onchange="onFileSelected(this)">

          <div id="file-preview" class="hidden mt-3">
            <div class="alert alert-success" id="file-info"></div>
          </div>

          <!-- Barra de progreso de conversión -->
          <div id="conv-progress" class="hidden mt-3">
            <div class="alert alert-info" style="align-items:center;gap:12px">
              <div class="spinner" style="width:18px;height:18px;border-width:2px;flex-shrink:0"></div>
              <span id="conv-msg">Procesando archivo Excel...</span>
            </div>
          </div>

          <div class="form-group mt-4" style="max-width:250px">
            <label class="form-label">Número de Ficha (Manual / Opcional)</label>
            <input type="text" id="manual-ficha" class="form-control" placeholder="Ej: 2889732">
            <small class="text-muted">Si el sistema no detecta la ficha automáticamente, usa este campo.</small>
          </div>

          <div class="mt-4 flex gap-2 justify-between">
            <button type="button" class="btn btn-secondary" onclick="previewFile()">👁️ Vista Previa</button>
            <button type="submit" class="btn btn-primary btn-lg" id="btn-subir" disabled>
              🚀 Cargar Aprendices
            </button>
          </div>
        </form>

        <div id="result-card" class="hidden mt-4"></div>
      </div>
    </div>

    <!-- Gestión de Base de Datos -->
    <div class="card">
      <div class="card-header">
        <span>⚙️</span>
        <h3>Gestión de Base de Datos</h3>
      </div>
      <div class="card-body">
        <div class="alert alert-warning">
          ⚠️ <div><strong>Zona de Peligro:</strong> Estas acciones son permanentes y no se pueden deshacer.</div>
        </div>
        
        <div class="form-group">
          <label class="form-label">Eliminar por Ficha</label>
          <div class="flex gap-2">
            <select id="sel-delete-ficha" class="form-control">
              <option value="">— Seleccionar Ficha —</option>
            </select>
            <button class="btn btn-danger" onclick="eliminarFicha()">🗑️ Eliminar</button>
          </div>
        </div>

        <hr style="border:0;border-top:1px solid var(--border);margin:20px 0">
        
        <div class="flex flex-col gap-2">
          <label class="form-label">Limpiar todo el sistema</label>
          <button class="btn btn-danger btn-lg w-full justify-center" onclick="vaciarTodo()">
            💣 Vaciar Toda la Base de Datos
          </button>
        </div>
      </div>
    </div>

    <!-- Vista previa -->
    <div class="card">
      <div class="card-header">
        <span>📋</span>
        <h3>Vista Previa del Archivo</h3>
      </div>
      <div class="card-body p-0">
        <div id="preview-container">
          <div class="empty-state">
            <div class="empty-icon">📄</div>
            <h3>Sin archivo cargado</h3>
            <p class="text-muted">Selecciona un CSV o Excel para ver la vista previa aquí</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Lista actual de aprendices -->
  <div class="card mt-4">
    <div class="card-header">
      <span>👥</span>
      <h3>Aprendices Registrados en el Sistema</h3>
      <div class="topbar-actions gap-2 ml-auto">
        <div class="search-box">
          <span class="search-icon">🔍</span>
          <input type="text" id="buscar-aprendiz" class="form-control" placeholder="Buscar..."
                 oninput="filtrarTabla()" style="width:200px;padding-left:32px">
        </div>
        <button class="btn btn-secondary btn-sm" onclick="exportToPDF('tabla-aprendices','Lista de Aprendices','aprendices')">📄 PDF</button>
        <button class="btn btn-primary btn-sm" onclick="exportToExcel('tabla-aprendices','aprendices')">📊 Excel</button>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-container">
        <table class="table" id="tabla-aprendices">
          <thead>
            <tr>
              <th>#</th>
              <th>Documento</th>
              <th>Tipo</th>
              <th>Nombre Completo</th>
              <th>Estado</th>
              <th>Ficha</th>
              <th width="80">Acciones</th>
            </tr>
          </thead>
          <tbody id="tbody-aprendices">
            <tr><td colspan="7" class="loading-overlay"><div class="spinner"></div></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<script>
let selectedFile = null;
let convertedCsvBlob = null;   // Blob CSV listo para enviar
let allAprendices = [];

/* ── Drag & Drop ── */
const uploadZone = document.getElementById('upload-zone');
uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
uploadZone.addEventListener('drop', e => {
  e.preventDefault();
  uploadZone.classList.remove('dragover');
  const file = e.dataTransfer.files[0];
  if (file) {
    // Asignar al input vía DataTransfer
    try {
      const dt = new DataTransfer();
      dt.items.add(file);
      document.getElementById('file-input').files = dt.files;
    } catch(ex) {}
    onFileSelected({ files: [file] });
  }
});

/* ── Detectar extensión ── */
function getExt(filename) {
  return filename.split('.').pop().toLowerCase();
}

/* ── Convertir Excel → CSV usando SheetJS ── */
function excelToCSV(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = function(e) {
      try {
        const data = new Uint8Array(e.target.result);
        const wb = XLSX.read(data, { type: 'array' });
        // Usar la primera hoja
        const ws = wb.Sheets[wb.SheetNames[0]];
        const csv = XLSX.utils.sheet_to_csv(ws);
        resolve(csv);
      } catch(err) {
        reject(err);
      }
    };
    reader.onerror = reject;
    reader.readAsArrayBuffer(file);
  });
}

/* ── Selección de archivo ── */
async function onFileSelected(input) {
  const file = input.files[0];
  if (!file) return;

  selectedFile = file;
  convertedCsvBlob = null;

  const ext = getExt(file.name);
  const isExcel = (ext === 'xlsx' || ext === 'xls');

  // UI: nombre y tamaño
  document.getElementById('upload-icon').textContent = isExcel ? '📊' : '📄';
  document.getElementById('upload-title').textContent = file.name;
  document.getElementById('upload-sub').textContent =
    `${(file.size / 1024).toFixed(1)} KB — ${ext.toUpperCase()}${isExcel ? ' (se convertirá a CSV automáticamente)' : ''}`;
  document.getElementById('file-info').textContent = `Archivo seleccionado: ${file.name}`;
  document.getElementById('file-preview').classList.remove('hidden');

  // Badge de tipo
  const badge = document.getElementById('badge-tipo-archivo');
  const badgeInfo = document.getElementById('badge-conversion-info');
  const badgeWrap = document.getElementById('file-type-badge');
  badgeWrap.style.display = 'flex';
  badgeWrap.style.alignItems = 'center';

  if (isExcel) {
    badge.className = 'badge badge-success';
    badge.textContent = `📊 ${ext.toUpperCase()} detectado`;
    badgeInfo.textContent = 'El Excel se convertirá a CSV internamente antes de enviar.';

    // Convertir ya para tenerlo listo
    document.getElementById('conv-progress').classList.remove('hidden');
    document.getElementById('conv-msg').textContent = 'Convirtiendo Excel a CSV...';
    document.getElementById('btn-subir').disabled = true;

    try {
      const csv = await excelToCSV(file);
      convertedCsvBlob = new Blob([csv], { type: 'text/csv' });
      document.getElementById('conv-progress').classList.add('hidden');
      document.getElementById('upload-icon').textContent = '✅';
      showToast(`Excel convertido: ${(convertedCsvBlob.size / 1024).toFixed(1)} KB en CSV`, 'success');
    } catch(err) {
      document.getElementById('conv-progress').classList.add('hidden');
      showToast('Error al leer el archivo Excel: ' + err.message, 'error');
      return;
    }
  } else {
    badge.className = 'badge badge-info';
    badge.textContent = '📄 CSV detectado';
    badgeInfo.textContent = 'El archivo se enviará directamente.';
    document.getElementById('upload-icon').textContent = '✅';
  }

  document.getElementById('btn-subir').disabled = false;
}

/* ── Vista Previa ── */
async function previewFile() {
  if (!selectedFile) { showToast('Selecciona un archivo primero', 'warning'); return; }

  const ext = getExt(selectedFile.name);

  if (ext === 'xlsx' || ext === 'xls') {
    // Usar SheetJS para extraer datos
    const reader = new FileReader();
    reader.onload = function(e) {
      const wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
      const ws = wb.Sheets[wb.SheetNames[0]];
      const rows = XLSX.utils.sheet_to_json(ws, { header: 1 });
      const headers = rows[0] || [];
      const body = rows.slice(1, 6);
      document.getElementById('preview-container').innerHTML = `
        <div class="table-container" style="max-height:300px;overflow:auto">
          <table class="table">
            <thead><tr>${headers.map(h => `<th>${h ?? ''}</th>`).join('')}</tr></thead>
            <tbody>${body.map(r => `<tr>${headers.map((_, i) => `<td>${r[i] ?? ''}</td>`).join('')}</tr>`).join('')}</tbody>
          </table>
        </div>
        <p class="text-muted text-sm mt-2" style="padding:8px 16px">
          Hoja: <strong>${wb.SheetNames[0]}</strong> — Mostrando primeras 5 filas
        </p>`;
    };
    reader.readAsArrayBuffer(selectedFile);
  } else {
    // CSV normal
    const reader = new FileReader();
    reader.onload = function(e) {
      const lines = e.target.result.split('\n').slice(0, 6);
      const headers = lines[0]?.split(',') || [];
      const rows = lines.slice(1);
      document.getElementById('preview-container').innerHTML = `
        <div class="table-container" style="max-height:300px;overflow:auto">
          <table class="table">
            <thead><tr>${headers.map(h => `<th>${h.trim()}</th>`).join('')}</tr></thead>
            <tbody>${rows.map(r => `<tr>${r.split(',').map(c => `<td>${c.trim()}</td>`).join('')}</tr>`).join('')}</tbody>
          </table>
        </div>
        <p class="text-muted text-sm mt-2" style="padding:8px 16px">Mostrando primeras 5 filas del archivo</p>`;
    };
    reader.readAsText(selectedFile, 'UTF-8');
  }
}

/* ── Envío del formulario ── */
document.getElementById('form-carga').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-subir');
  btn.disabled = true;
  btn.innerHTML = '⏳ Cargando...';

  const fd = new FormData();
  const ext = getExt(selectedFile.name);
  
  // Agregar ficha manual si existe
  const manualFicha = document.getElementById('manual-ficha').value;
  if (manualFicha) fd.append('ficha_manual', manualFicha);

  if ((ext === 'xlsx' || ext === 'xls') && convertedCsvBlob) {
    // Enviar el CSV convertido con nombre .csv para que PHP lo procese normalmente
    fd.append('archivo', convertedCsvBlob, selectedFile.name.replace(/\.(xlsx|xls)$/i, '.csv'));
  } else {
    fd.append('archivo', document.getElementById('file-input').files[0]);
  }

  const data = await apiPost('api/aprendiz.php?action=carga_masiva', fd);

  btn.disabled = false;
  btn.innerHTML = '🚀 Cargar Aprendices';

  const rc = document.getElementById('result-card');
  rc.classList.remove('hidden');

  if (data.error) {
    rc.innerHTML = `<div class="alert alert-error">❌ ${data.error}</div>`;
    showToast(data.error, 'error');
    return;
  }

  // Construir bloque de diagnóstico de headers
  const headersInfo = data.headers_detectados?.length
    ? `<div style="margin-top:10px;padding:10px;background:var(--bg-input);border-radius:6px;font-size:12px">
        <strong>Columnas detectadas en tu archivo:</strong><br>
        ${data.headers_detectados.map((h,i) => `<code style="margin:2px 4px;padding:2px 6px;background:rgba(255,255,255,0.08);border-radius:3px">${i+1}. ${h}</code>`).join('')}
        ${data.ficha_detectada ? `<br><br><strong style="color:var(--accent-gold)">Ficha detectada en el archivo: ${data.ficha_detectada}</strong>` : ''}
      </div>`
    : '';

  if (data.insertados === 0 && data.actualizados === 0 && data.errores?.length) {
    // Fallo total — mostrar diagnóstico completo
    rc.innerHTML = `<div class="alert alert-warning">
      ⚠️ <div style="flex:1">
        <strong>No se procesó ningún aprendiz.</strong><br>
        El sistema no pudo encontrar la columna del número de documento.<br>
        <strong>Filas en el archivo:</strong> ${data.total_filas ?? '?'}<br>
        ${headersInfo}
        <details style="margin-top:10px">
          <summary style="cursor:pointer;color:var(--accent-gold)">Ver errores detallados (${data.errores.length})</summary>
          <ul style="margin-top:6px;font-size:12px">${data.errores.map(e => `<li>${e}</li>`).join('')}</ul>
        </details>
        <div style="margin-top:10px;padding:10px;background:rgba(250,173,20,0.08);border-radius:6px;font-size:12px">
          💡 <strong>Solución:</strong> Asegúrate de que una de las columnas de tu archivo se llame exactamente
          <code>Número de documento</code>, <code>Numero_Documento</code>, <code>Documento</code>
          o similares. Puedes descargar la plantilla como guía.
        </div>
      </div>
    </div>`;
    showToast('Sin coincidencia de columnas — revisa los headers detectados', 'warning');
  } else {
    // Éxito parcial o total
    const tieneErrores = data.errores?.length > 0;
    rc.innerHTML = `<div class="alert alert-${tieneErrores ? 'warning' : 'success'}">
      ${tieneErrores ? '⚠️' : '✅'} <div style="flex:1">
        <strong>Carga ${data.insertados + data.actualizados > 0 ? 'exitosa' : 'completada'}:</strong><br>
        Insertados: <strong>${data.insertados}</strong> | Actualizados: <strong>${data.actualizados}</strong> | Filas: <strong>${data.total_filas}</strong>
        ${tieneErrores ? `<br><span class="text-warning">Filas con error: ${data.errores.slice(0,3).join(' — ')}</span>` : ''}
        ${headersInfo}
      </div>
    </div>`;
    showToast(`✅ ${data.insertados} insertados, ${data.actualizados} actualizados`, 'success');
    loadAprendices();
  }
});

/* ── Tabla de aprendices ── */
async function loadAprendices() {
  const data = await api('api/aprendiz.php', { action: 'listar' });
  allAprendices = data;
  renderAprendices(data);
}

function renderAprendices(data) {
  renderTable(data);
}

function renderTable(data) {
  const tbody = document.getElementById('tbody-aprendices');
  tbody.innerHTML = data.map((a, i) => `<tr>
    <td class="text-muted">${i+1}</td>
    <td><strong>${a.numero_documento}</strong></td>
    <td class="text-muted">${a.tipo_documento}</td>
    <td>${a.nombre} ${a.apellido}</td>
    <td><span class="badge badge-info">${a.estado}</span></td>
    <td><span class="badge badge-gray">${a.ficha || '—'}</span></td>
    <td>
      <button class="btn btn-danger btn-sm btn-icon" onclick="eliminarAprendiz(${a.id_aprendiz}, '${(a.nombre + ' ' + a.apellido).replace(/'/g, "\\'")}')" title="Eliminar">
        🗑️
      </button>
    </td>
  </tr>`).join('') || '<tr><td colspan="7" class="empty-state">No hay aprendices registrados</td></tr>';
}

async function loadFichasDelete() {
  const data = await api('api/dashboard.php', { action: 'fichas' });
  const sel = document.getElementById('sel-delete-ficha');
  sel.innerHTML = '<option value="">— Seleccionar Ficha —</option>';
  data.forEach(f => {
    const opt = document.createElement('option');
    opt.value = f.id_ficha;
    opt.textContent = f.nombre;
    sel.appendChild(opt);
  });
}

async function eliminarAprendiz(id, nombre) {
  if (!confirm(`¿Estás seguro de eliminar a ${nombre}? Se borrarán también todos sus juicios.`)) return;
  const res = await api('api/aprendiz.php', { action: 'eliminar', id }, 'DELETE');
  if (res.success) {
    showToast('Aprendiz eliminado', 'success');
    loadAprendices();
  } else {
    showToast('Error: ' + res.error, 'error');
  }
}

async function eliminarFicha() {
  const id = document.getElementById('sel-delete-ficha').value;
  if (!id) { showToast('Selecciona una ficha', 'warning'); return; }
  const nombre = document.getElementById('sel-delete-ficha').options[document.getElementById('sel-delete-ficha').selectedIndex].text;
  
  if (!confirm(`⚠️ ¡ATENCIÓN! ¿Seguro que quieres borrar la ficha ${nombre}? SE ELIMINARÁN TODOS LOS APRENDICES Y JUICIOS DE ESTE GRUPO.`)) return;
  
  const res = await api('api/aprendiz.php', { action: 'eliminar_ficha', id_ficha: id }, 'DELETE');
  if (res.success) {
    showToast('Ficha eliminada correctamente', 'success');
    loadAprendices();
    loadFichasDelete();
  } else {
    showToast('Error: ' + res.error, 'error');
  }
}

async function vaciarTodo() {
  const pass = prompt('⚠️ ESTA ACCIÓN BORRARÁ TODO EL SISTEMA.\n\nPara confirmar, escribe el código de seguridad: SENA_RESET_2026');
  if (pass !== 'SENA_RESET_2026') {
    if (pass !== null) alert('Código incorrecto. Acción cancelada.');
    return;
  }
  
  const res = await api('api/aprendiz.php', { action: 'vaciar', confirm: 'SENA_RESET_2026' }, 'DELETE');
  if (res.success) {
    alert('Base de datos vaciada con éxito.');
    location.reload();
  } else {
    showToast('Error: ' + res.error, 'error');
  }
}

function filtrarTabla() {
  const query = document.getElementById('buscar-aprendiz').value.toLowerCase();
  const filtered = allAprendices.filter(a => 
    (a.nombre + ' ' + a.apellido).toLowerCase().includes(query) || 
    (a.numero_documento || '').includes(query) ||
    (a.ficha || '').toLowerCase().includes(query)
  );
  renderTable(filtered);
}

// Init
loadAprendices();
loadFichasDelete();
</script>
<?php
$content = ob_get_clean();
renderLayout('Carga Masiva de Aprendices', 'carga_aprendices', $content);
?>
