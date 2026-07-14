<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once '../config/database.php';
$conn = getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

/* ================================================================
   FUNCIÓN: Normalizar string para comparación flexible de headers
   Elimina tildes, mayúsculas, espacios duplicados y caracteres especiales
   ================================================================ */
function normalizar($str) {
    // Detectar y corregir codificación si no es UTF-8
    if (mb_detect_encoding($str, 'UTF-8', true) === false) {
        $str = mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
    }
    $str = mb_strtolower(trim($str), 'UTF-8');
    // Eliminar BOM
    $str = str_replace("\xEF\xBB\xBF", '', $str);
    // Reemplazar tildes y caracteres especiales
    $from = ['á','à','ä','â','é','è','ë','ê','í','ì','ï','î','ó','ò','ö','ô','ú','ù','ü','û','ñ','ç'];
    $to   = ['a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','u','u','u','u','n','c'];
    $str = str_replace($from, $to, $str);
    // Eliminar caracteres no alfanuméricos excepto espacio
    $str = preg_replace('/[^a-z0-9 _]/', '', $str);
    // Colapsar espacios múltiples
    $str = preg_replace('/\s+/', ' ', trim($str));
    return $str;
}

function fix_utf8($str) {
    if (mb_detect_encoding($str, 'UTF-8', true) === false) {
        return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
    }
    return $str;
}

/* ================================================================
   MAPEO FLEXIBLE DE COLUMNAS
   Clave = campo interno, Valor = array de variantes posibles (normalizadas)
   Cubre: Sofia Plus, CSV manual, exportaciones varias
   ================================================================ */
$columnMapNorm = [
    'numero_documento' => [
        'numero de documento','num documento','numero documento','no documento',
        'documento','doc','identificacion','numero de identificacion',
        'no de documento','nro documento','num_documento','numero_documento',
        'numero identificacion','cedula','num identificacion','n documento',
        'no identificacion','numero id','n de documento'
    ],
    'tipo_documento' => [
        'tipo de documento','tipo documento','tipo_documento','tipo doc',
        'tipo de identificacion','tipo identificacion','tipo id','t documento'
    ],
    'nombre' => [
        'nombres','nombre','nombres aprendiz','primer nombre','nombres del aprendiz',
        'nombre aprendiz','nombres y apellidos','nombre completo'
    ],
    'apellido' => [
        'apellidos','apellido','apellidos aprendiz','primer apellido',
        'apellidos del aprendiz','apellido aprendiz'
    ],
    'estado' => [
        'estado','estado del aprendiz','estado aprendiz','estado formacion',
        'estado de formacion','situacion','situacion actual'
    ],
    'id_ficha' => [
        'ficha','numero de ficha','num ficha','no ficha','ficha numero',
        'numero ficha','id ficha','ficha de caracterizacion','numero de la ficha',
        'id_ficha','ficha formacion','numero formacion','formacion'
    ],
];

if ($method === 'POST' && $action === 'carga_masiva') {
    if (!isset($_FILES['archivo'])) {
        echo json_encode(['error' => 'No se envió archivo']);
        exit;
    }

    $file = $_FILES['archivo'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
        echo json_encode(['error' => 'Formato no soportado. Use CSV, XLSX o XLS.']);
        exit;
    }

    $uploadDir = '../uploads/temp/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $tmpPath = $uploadDir . uniqid() . '.csv';
    move_uploaded_file($file['tmp_name'], $tmpPath);

    /* ── Leer CSV ── */
    $rawHeaders = [];
    $rows = [];

    if (($handle = fopen($tmpPath, 'r')) !== false) {
        // Detectar separador automáticamente (coma, punto y coma, tabulación)
        $firstLine = fgets($handle);
        rewind($handle);

        // Quitar BOM si existe
        $bom = "\xEF\xBB\xBF";
        if (strpos($firstLine, $bom) === 0) {
            fread($handle, 3); // saltar BOM
            $firstLine = ltrim($firstLine, $bom);
        }

        // Detectar delimitador
        $delimiters = [',', ';', "\t", '|'];
        $delimCount = [];
        foreach ($delimiters as $d) {
            $delimCount[$d] = substr_count($firstLine, $d);
        }
        arsort($delimCount);
        $delim = array_key_first($delimCount);
        if ($delimCount[$delim] === 0) $delim = ','; // fallback

        rewind($handle);
        // Saltamos el BOM nuevamente
        $first = fgetc($handle);
        if (ord($first) !== 0xEF) rewind($handle);
        else { fgetc($handle); fgetc($handle); }

        // --- SCAN GLOBAL FICHA ---
        $globalFicha = $_POST['ficha_manual'] ?? null;
        
        // 1. Intentar extraer de los metadatos si no se envió manual
        if (!$globalFicha) {
            $scanCount = 0;
            while ($scanCount < 30 && ($line = fgets($handle)) !== false) {
                if (preg_match('/(?:ficha|caracterizacion|codigo)[\s\S]*?(\d{6,8})/i', $line, $m)) {
                    $globalFicha = $m[1];
                    break;
                }
                $scanCount++;
            }
        }
        
        // 2. Si no se encontró, intentar extraer del nombre del archivo
        if (!$globalFicha) {
            if (preg_match('/(\d{6,8})/', $file['name'], $m)) {
                $globalFicha = $m[1];
            }
        }

        rewind($handle);
        $first = fgetc($handle);
        if (ord($first) !== 0xEF) rewind($handle);
        else { fgetc($handle); fgetc($handle); }
        // -------------------------

        $headerRaw = null;
        $rawHeaders = [];
        $headerNorm = [];
        $expectedKeywords = ['documento', 'nombre', 'apellido', 'estado', 'ficha', 'competencia', 'resultado', 'identificacion', 'nombres', 'tipo'];

        while (($row = fgetcsv($handle, 2000, $delim)) !== false) {
            // Limpiar celdas vacías al final
            $row = array_map(function($v){ return trim($v, " \t\r\n\0\x0B\""); }, $row);
            $nonEmptyCount = count(array_filter($row, function($v) { return $v !== ''; }));

            if ($headerRaw === null) {
                // Buscar si esta fila parece ser el encabezado real
                $normRow = array_map('normalizar', $row);
                $matches = 0;
                foreach ($normRow as $col) {
                    foreach ($expectedKeywords as $kw) {
                        if (strpos($col, $kw) !== false) {
                            $matches++;
                            break;
                        }
                    }
                }
                
                // Si encontramos al menos 2 palabras clave, asumimos que es el encabezado real
                if ($matches >= 2) {
                    $headerRaw  = $row;
                    $rawHeaders = $row;
                    $headerNorm = $normRow;
                }
                continue;
            }

            // Solo procesar filas con al menos un valor
            if ($nonEmptyCount === 0) continue;

            // Combinar con headers
            $combined = [];
            for ($i = 0; $i < count($headerNorm); $i++) {
                $combined[$headerNorm[$i]] = $row[$i] ?? '';
            }
            $rows[] = $combined;
        }
        fclose($handle);
    }

    /* ── Si no se detectaron headers, retornar diagnóstico ── */
    if (empty($rawHeaders)) {
        unlink($tmpPath);
        echo json_encode([
            'error' => 'No se pudo leer el archivo. Verifique que no esté vacío.',
            'insertados' => 0, 'actualizados' => 0
        ]);
        exit;
    }

    /* ── Mapear columnas ── */
    $inserted = 0;
    $updated  = 0;
    $errors   = [];

    foreach ($rows as $i => $row) {
        $data = [];

        // Mapeo flexible: buscar cada alias normalizado en los headers normalizados
        foreach ($columnMapNorm as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($row[$alias]) && $row[$alias] !== '') {
                    $data[$field] = fix_utf8(trim($row[$alias]));
                    break;
                }
            }
        }

        /* ── FALLBACK INTELIGENTE ──
           Si fallaron algunos encabezados, intentamos rescatar los datos por su formato
        */
        $vals = array_values($row);

        // 1. Buscar Documento
        if (empty($data['numero_documento'])) {
            foreach ($vals as $idx => $val) {
                // Puede que el documento traiga puntos o espacios (ej: 1.098.765.432)
                $cleanVal = preg_replace('/[^\d]/', '', trim($val));
                if (strlen($cleanVal) >= 5 && strlen($cleanVal) <= 15) {
                    $data['numero_documento'] = $cleanVal;
                    if ($idx > 0 && empty($data['tipo_documento'])) {
                        $prev = trim($vals[$idx - 1]);
                        if (in_array(strtoupper($prev), ['CC','TI','CE','PA','PEP','RC'])) {
                            $data['tipo_documento'] = strtoupper($prev);
                        }
                    }
                    // Intentar rescatar Nombres y Apellidos si están vacíos
                    if (empty($data['apellido']) && isset($vals[$idx+1]) && !preg_match('/^\d+$/', trim($vals[$idx+1]))) {
                        $data['apellido'] = fix_utf8(trim($vals[$idx+1]));
                    }
                    if (empty($data['nombre']) && isset($vals[$idx+2]) && !preg_match('/^\d+$/', trim($vals[$idx+2]))) {
                        $data['nombre'] = fix_utf8(trim($vals[$idx+2]));
                    }
                    break;
                }
            }
        }

        if (empty($data['numero_documento'])) {
            if (count(array_filter($vals)) > 0) {
                $errors[] = "Fila " . ($i + 2) . ": Sin número de documento (headers detectados: " . implode(', ', $rawHeaders) . ")";
            }
            if (count($errors) >= 3) {
                $errors[] = "... (puede haber más filas con error de mapeo de columnas)";
                break;
            }
            continue;
        }

        // 2. Normalizar Nombres y Apellidos (Si están en una sola columna)
        if (empty($data['apellido']) && !empty($data['nombre'])) {
            $parts = explode(' ', trim($data['nombre']));
            if (count($parts) > 1) {
                $half = ceil(count($parts) / 2); // Ej: Juan Carlos (2) Perez Gomez (2)
                $data['nombre'] = implode(' ', array_slice($parts, 0, $half));
                $data['apellido'] = implode(' ', array_slice($parts, $half));
            }
        } elseif (empty($data['nombre']) && !empty($data['apellido'])) {
            $parts = explode(' ', trim($data['apellido']));
            if (count($parts) > 1) {
                $half = ceil(count($parts) / 2);
                $data['nombre'] = implode(' ', array_slice($parts, 0, $half));
                $data['apellido'] = implode(' ', array_slice($parts, $half));
            }
        }

        // 3. Buscar Estado si está vacío
        if (empty($data['estado'])) {
            foreach ($vals as $val) {
                $v = normalizar($val);
                if (strpos($v, 'formacion') !== false || strpos($v, 'activo') !== false) { $data['estado'] = 'En Formación'; break; }
                if (strpos($v, 'retir') !== false || strpos($v, 'cancel') !== false || strpos($v, 'deser') !== false) { $data['estado'] = 'Retiro Voluntario'; break; }
                if (strpos($v, 'traslad') !== false) { $data['estado'] = 'Trasladado'; break; }
            }
        }

        /* ── Guardar el estado exacto (ej: "Retiro voluntario") ── */
        if (!empty($data['estado'])) {
            $data['estado'] = mb_convert_case(trim($data['estado']), MB_CASE_TITLE, "UTF-8");
        } else {
            $data['estado'] = 'En Formación';
        }

        // 5. Detectar si la fila contiene información de Juicios Evaluativos (Unified Upload)
        $idxTipo = -1;
        $juicioTipo = null;
        foreach ($vals as $idx => $val) {
            $v = normalizar($val);
            // Ignorar coincidencias sueltas, buscar las palabras ancla de juicios
            if (strpos($v, 'aprobado') !== false || $v === 'a') { $juicioTipo = 'APROBADO'; $idxTipo = $idx; break; }
            if (strpos($v, 'por evaluar') !== false || strpos($v, 'no aprobado') !== false || $v === 'd') { $juicioTipo = 'POR_EVALUAR'; $idxTipo = $idx; break; }
        }

        $juicioData = null;
        if ($idxTipo >= 2) {
            $juicioData = [
                'tipo' => $juicioTipo,
                'resultado' => fix_utf8(trim($vals[$idxTipo - 1])),
                'competencia' => fix_utf8(trim($vals[$idxTipo - 2])),
                'funcionario' => isset($vals[$idxTipo + 1]) ? fix_utf8(trim($vals[$idxTipo + 1])) : 'Instructor'
            ];
        }

        /* ── Buscar o crear ficha ── */
        $id_ficha = null;
        if (!empty($data['id_ficha'])) {
            $fichaVal = trim($data['id_ficha']);
            $stmt = $conn->prepare("SELECT id_ficha FROM ficha WHERE nombre = ? OR CAST(id_ficha AS CHAR) = ?");
            $stmt->bind_param('ss', $fichaVal, $fichaVal);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            if ($res) {
                $id_ficha = $res['id_ficha'];
            } else {
                $stmt2 = $conn->prepare("INSERT INTO ficha (nombre) VALUES (?)");
                $stmt2->bind_param('s', $fichaVal);
                $stmt2->execute();
                $id_ficha = $conn->insert_id;
            }
        }

        // 6. Asignar Ficha Global si la fila no trae
        if (empty($id_ficha) && $globalFicha) {
            $fichaVal = trim($globalFicha);
            $stmt = $conn->prepare("SELECT id_ficha FROM ficha WHERE nombre = ? OR CAST(id_ficha AS CHAR) = ?");
            $stmt->bind_param('ss', $fichaVal, $fichaVal);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            if ($res) {
                $id_ficha = $res['id_ficha'];
            } else {
                $stmt2 = $conn->prepare("INSERT INTO ficha (nombre) VALUES (?)");
                $stmt2->bind_param('s', $fichaVal);
                $stmt2->execute();
                $id_ficha = $conn->insert_id;
            }
        }

        /* ── Insertar o actualizar aprendiz ── */
        $stmt = $conn->prepare("SELECT * FROM aprendiz WHERE numero_documento = ?");
        $stmt->bind_param('s', $data['numero_documento']);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();

        $nombre   = $data['nombre']          ?? '';
        $apellido = $data['apellido']         ?? '';
        $tipodoc  = strtoupper($data['tipo_documento'] ?? 'CC');
        $estado   = $data['estado'];
        $numDoc   = $data['numero_documento'];

        $id_aprendiz = null;

        if ($exists) {
            $id_aprendiz = $exists['id_aprendiz'];
            
            // PRESERVAR FICHA: Si el nuevo archivo no trae ficha, mantenemos la de la BD
            if (empty($id_ficha) && !empty($exists['id_ficha'])) {
                $id_ficha = $exists['id_ficha'];
            }
            
            // PRESERVAR ESTADO: Si el archivo es de Juicios (no trae estado), mantenemos el estado real de la BD
            if (($estado === 'En Formación' || empty($estado)) && !empty($exists['estado']) && $exists['estado'] !== 'En Formación') {
                $estado = $exists['estado'];
            }

            $stmt = $conn->prepare(
                "UPDATE aprendiz SET nombre=?, apellido=?, tipo_documento=?, estado=?, id_ficha=? WHERE numero_documento=?"
            );
            $stmt->bind_param('ssssis', $nombre, $apellido, $tipodoc, $estado, $id_ficha, $numDoc);
            if ($stmt->execute()) {
                $updated++;
            } else {
                $errors[] = "Fila " . ($i + 2) . ": Error BD Aprendiz - " . $stmt->error;
            }
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO aprendiz (numero_documento, tipo_documento, nombre, apellido, estado, id_ficha) VALUES (?,?,?,?,?,?)"
            );
            $stmt->bind_param('sssssi', $numDoc, $tipodoc, $nombre, $apellido, $estado, $id_ficha);
            if ($stmt->execute()) {
                $id_aprendiz = $conn->insert_id;
                $inserted++;
            } else {
                $errors[] = "Fila " . ($i + 2) . ": Error BD Aprendiz - " . $stmt->error;
            }
        }

        /* ── Insertar Juicio si se detectó ── */
        if ($id_aprendiz && $juicioData && !empty($juicioData['resultado']) && !empty($juicioData['competencia'])) {
            // Competencia
            $stmtC = $conn->prepare("SELECT id_competencia FROM competencia WHERE nombre = ?");
            $stmtC->bind_param('s', $juicioData['competencia']);
            $stmtC->execute();
            $resC = $stmtC->get_result()->fetch_assoc();
            $id_comp = $resC ? $resC['id_competencia'] : null;
            if (!$id_comp) {
                $stmtInC = $conn->prepare("INSERT INTO competencia (nombre) VALUES (?)");
                $stmtInC->bind_param('s', $juicioData['competencia']);
                $stmtInC->execute();
                $id_comp = $conn->insert_id;
            }

            // Resultado
            $stmtR = $conn->prepare("SELECT id_resultado FROM resultado WHERE nombre = ? AND id_competencia = ?");
            $stmtR->bind_param('si', $juicioData['resultado'], $id_comp);
            $stmtR->execute();
            $resR = $stmtR->get_result()->fetch_assoc();
            $id_res = $resR ? $resR['id_resultado'] : null;
            if (!$id_res) {
                $stmtInR = $conn->prepare("INSERT INTO resultado (nombre, id_competencia) VALUES (?, ?)");
                $stmtInR->bind_param('si', $juicioData['resultado'], $id_comp);
                $stmtInR->execute();
                $id_res = $conn->insert_id;
            }

            // Funcionario
            $stmtF = $conn->prepare("SELECT id_funcionario FROM funcionario WHERE nombre = ?");
            $stmtF->bind_param('s', $juicioData['funcionario']);
            $stmtF->execute();
            $resF = $stmtF->get_result()->fetch_assoc();
            $id_func = $resF ? $resF['id_funcionario'] : null;
            if (!$id_func) {
                $stmtInF = $conn->prepare("INSERT INTO funcionario (nombre) VALUES (?)");
                $stmtInF->bind_param('s', $juicioData['funcionario']);
                $stmtInF->execute();
                $id_func = $conn->insert_id;
            }

            // Juicio Evaluacion (Insertar o Actualizar)
            $stmtJ = $conn->prepare("SELECT id_juicio FROM juicio_evaluacion WHERE id_aprendiz = ? AND id_resultado = ?");
            $stmtJ->bind_param('ii', $id_aprendiz, $id_res);
            $stmtJ->execute();
            $existsJ = $stmtJ->get_result()->fetch_assoc();
            $fecha = date('Y-m-d H:i:s');

            if ($existsJ) {
                $stmtUpJ = $conn->prepare("UPDATE juicio_evaluacion SET id_funcionario=?, tipo=?, fecha=? WHERE id_juicio=?");
                $stmtUpJ->bind_param('issi', $id_func, $juicioData['tipo'], $fecha, $existsJ['id_juicio']);
                $stmtUpJ->execute();
            } else {
                $stmtInJ = $conn->prepare("INSERT INTO juicio_evaluacion (id_aprendiz, id_resultado, id_funcionario, tipo, fecha) VALUES (?,?,?,?,?)");
                $stmtInJ->bind_param('iiiss', $id_aprendiz, $id_res, $id_func, $juicioData['tipo'], $fecha);
                $stmtInJ->execute();
            }
        }
    }

    unlink($tmpPath);
    echo json_encode([
        'success'      => true,
        'insertados'   => $inserted,
        'actualizados' => $updated,
        'errores'      => $errors,
        'headers_detectados' => $rawHeaders,
        'ficha_detectada' => $globalFicha,
        'total_filas'  => count($rows),
    ]);
    exit;
}

/* ── GET: Buscar por documento ── */
if ($method === 'GET' && $action === 'buscar') {
    $doc  = $_GET['documento'] ?? '';
    $stmt = $conn->prepare(
        "SELECT a.*, f.nombre AS ficha FROM aprendiz a LEFT JOIN ficha f ON a.id_ficha = f.id_ficha WHERE a.numero_documento = ? LIMIT 1"
    );
    $stmt->bind_param('s', $doc);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    echo json_encode($row ?: ['error' => 'No encontrado']);
    exit;
}

/* ── GET: Listar todos ── */
if ($method === 'GET' && $action === 'listar') {
    $sql    = "SELECT a.*, f.nombre AS ficha FROM aprendiz a LEFT JOIN ficha f ON a.id_ficha = f.id_ficha ORDER BY a.apellido, a.nombre LIMIT 1000";
    $result = $conn->query($sql);
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}

/* ── DELETE: Eliminar un aprendiz ── */
if ($method === 'DELETE' && $action === 'eliminar') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'ID no válido']); exit; }
    
    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM juicio_evaluacion WHERE id_aprendiz = $id");
        $conn->query("DELETE FROM aprendiz WHERE id_aprendiz = $id");
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

/* ── DELETE: Eliminar por ficha ── */
if ($method === 'DELETE' && $action === 'eliminar_ficha') {
    $id_ficha = intval($_GET['id_ficha'] ?? 0);
    if (!$id_ficha) { echo json_encode(['error' => 'Ficha no válida']); exit; }

    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM juicio_evaluacion WHERE id_aprendiz IN (SELECT id_aprendiz FROM aprendiz WHERE id_ficha = $id_ficha)");
        $conn->query("DELETE FROM aprendiz WHERE id_ficha = $id_ficha");
        $conn->query("DELETE FROM ficha WHERE id_ficha = $id_ficha");
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

/* ── DELETE: Vaciar base de datos ── */
if ($method === 'DELETE' && $action === 'vaciar') {
    if (($_GET['confirm'] ?? '') !== 'SENA_RESET_2026') {
        echo json_encode(['error' => 'Código de confirmación incorrecto']);
        exit;
    }

    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $conn->query("TRUNCATE TABLE juicio_evaluacion");
    $conn->query("TRUNCATE TABLE aprendiz");
    $conn->query("TRUNCATE TABLE ficha");
    $conn->query("TRUNCATE TABLE resultado");
    $conn->query("TRUNCATE TABLE competencia");
    $conn->query("TRUNCATE TABLE funcionario");
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Acción no válida']);
$conn->close();
?>
