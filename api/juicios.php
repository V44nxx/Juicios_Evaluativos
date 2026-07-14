<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once '../config/database.php';
$conn = getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'importar') {
    if (!isset($_FILES['archivo'])) {
        echo json_encode(['error' => 'No se envió archivo']);
        exit;
    }

    $file = $_FILES['archivo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
        echo json_encode(['error' => 'Formato no soportado. Use CSV, XLSX o XLS.']);
        exit;
    }
    // Los archivos xlsx/xls son convertidos a CSV por el navegador antes de enviarse
    $ext = 'csv'; // siempre procesar como CSV

    $uploadDir = '../uploads/temp/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $tmpPath = $uploadDir . uniqid() . '.csv';
    move_uploaded_file($file['tmp_name'], $tmpPath);

    function normalizar($str) {
        $str = mb_strtolower(trim($str), 'UTF-8');
        $str = str_replace("\xEF\xBB\xBF", '', $str);
        $from = ['á','à','é','è','í','ì','ó','ò','ú','ù','ñ'];
        $to   = ['a','a','e','e','i','i','o','o','u','u','n'];
        $str = str_replace($from, $to, $str);
        $str = preg_replace('/[^a-z0-9 _]/', '', $str);
        return preg_replace('/\s+/', ' ', trim($str));
    }

    $rows = [];
    if (($handle = fopen($tmpPath, 'r')) !== false) {
        $firstLine = fgets($handle);
        rewind($handle);
        $bom = "\xEF\xBB\xBF";
        if (strpos($firstLine, $bom) === 0) { fread($handle, 3); }
        
        // Detectar delimitador
        $delims = [',', ';', "\t", '|'];
        $delimCount = [];
        foreach ($delims as $d) { $delimCount[$d] = substr_count($firstLine, $d); }
        arsort($delimCount);
        $delim = array_key_first($delimCount);
        if ($delimCount[$delim] === 0) $delim = ',';
        
        rewind($handle);
        $first = fgetc($handle);
        if (ord($first) !== 0xEF) rewind($handle);
        else { fgetc($handle); fgetc($handle); }

        $headerRaw = null;
        $rawHeaders = [];
        $headerNorm = [];
        $expectedKeywords = ['documento', 'competencia', 'resultado', 'funcionario', 'juicio', 'estado', 'evaluacion', 'instructor'];

        while (($row = fgetcsv($handle, 2000, $delim)) !== false) {
            $row = array_map(function($v){ return trim($v, " \t\r\n\0\x0B\""); }, $row);
            $nonEmptyCount = count(array_filter($row, function($v) { return $v !== ''; }));

            if ($headerRaw === null) {
                $normRow = array_map('normalizar', $row);
                $matches = 0;
                foreach ($normRow as $col) {
                    foreach ($expectedKeywords as $kw) {
                        if (strpos($col, $kw) !== false) { $matches++; break; }
                    }
                }
                if ($matches >= 2 || ($nonEmptyCount >= 4 && $matches >= 1)) {
                    $headerRaw = $row;
                    $rawHeaders = $row;
                    $headerNorm = $normRow;
                }
                continue;
            }

            if ($nonEmptyCount === 0) continue;

            $r = [];
            for ($i = 0; $i < count($headerNorm); $i++) {
                $r[$headerNorm[$i]] = $row[$i] ?? '';
            }
            $rows[] = $r;
        }
        fclose($handle);
    }

    $inserted = 0; $errors = [];
    $colMapNorm = [
        'numero_documento' => ['numero de documento', 'documento', 'identificacion', 'num documento', 'numero identificacion', 'cedula'],
        'nombre_competencia' => ['competencia', 'nombre competencia', 'denominacion de la competencia'],
        'nombre_resultado' => ['resultado de aprendizaje', 'resultado', 'nombre resultado', 'denominacion del resultado'],
        'nombre_funcionario' => ['funcionario', 'instructor', 'evaluador', 'nombre instructor'],
        'tipo'             => ['juicio', 'juicio de evaluacion', 'estado juicio', 'evaluacion', 'calificacion'],
        'fecha'            => ['fecha', 'fecha evaluacion', 'fecha juicio'],
    ];

    foreach ($rows as $i => $row) {
        $data = [];
        foreach ($colMapNorm as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($row[$alias]) && $row[$alias] !== '') {
                    $data[$field] = $row[$alias];
                    break;
                }
            }
        }

        // Positional fallback for juicios if headers failed
        $vals = array_values($row);
        
        // Buscar Documento
        if (empty($data['numero_documento'])) {
            foreach ($vals as $idx => $val) {
                $cleanVal = preg_replace('/[^\d]/', '', trim($val));
                if (strlen($cleanVal) >= 5 && strlen($cleanVal) <= 15) {
                    $data['numero_documento'] = $cleanVal;
                    break;
                }
            }
        }
        
        // Buscar Juicio (Aprobado/Por Evaluar) como "Ancla"
        $idxTipo = -1;
        foreach ($vals as $idx => $val) {
            $v = strtolower(trim($val));
            if (strpos($v, 'aprobado') !== false || $v === 'a') { 
                if (empty($data['tipo'])) $data['tipo'] = 'APROBADO'; 
                $idxTipo = $idx; 
                break; 
            }
            if (strpos($v, 'por evaluar') !== false || strpos($v, 'no aprobado') !== false || $v === 'd') { 
                if (empty($data['tipo'])) $data['tipo'] = 'POR_EVALUAR'; 
                $idxTipo = $idx; 
                break; 
            }
        }

        // Si encontramos la columna del Juicio, usamos su posición para encontrar Competencia, Resultado e Instructor
        if ($idxTipo >= 2) {
            if (empty($data['nombre_resultado']))   $data['nombre_resultado'] = trim($vals[$idxTipo - 1]);
            if (empty($data['nombre_competencia'])) $data['nombre_competencia'] = trim($vals[$idxTipo - 2]);
            if (empty($data['nombre_funcionario']) && isset($vals[$idxTipo + 1])) $data['nombre_funcionario'] = trim($vals[$idxTipo + 1]);
        }

        if (empty($data['numero_documento'])) {
            $errors[] = "Fila " . ($i + 2) . ": Sin documento de aprendiz";
            continue;
        }

        // Buscar aprendiz
        $stmt = $conn->prepare("SELECT id_aprendiz FROM aprendiz WHERE numero_documento = ?");
        $stmt->bind_param('s', $data['numero_documento']);
        $stmt->execute();
        $aprendiz = $stmt->get_result()->fetch_assoc();
        if (!$aprendiz) {
            $errors[] = "Fila " . ($i + 2) . ": Aprendiz {$data['numero_documento']} no encontrado";
            continue;
        }
        $id_aprendiz = $aprendiz['id_aprendiz'];

        // Buscar o crear resultado de aprendizaje
        $id_resultado = null;
        if (!empty($data['nombre_resultado'])) {
            // Buscar competencia
            $id_competencia = null;
            if (!empty($data['nombre_competencia'])) {
                $stmt = $conn->prepare("SELECT id_competencia FROM competencia WHERE nombre = ?");
                $stmt->bind_param('s', $data['nombre_competencia']);
                $stmt->execute();
                $comp = $stmt->get_result()->fetch_assoc();
                if (!$comp) {
                    $stmt2 = $conn->prepare("INSERT INTO competencia (nombre) VALUES (?)");
                    $stmt2->bind_param('s', $data['nombre_competencia']);
                    $stmt2->execute();
                    $id_competencia = $conn->insert_id;
                } else {
                    $id_competencia = $comp['id_competencia'];
                }
            }

            $stmt = $conn->prepare("SELECT id_resultado FROM resultado WHERE nombre = ?");
            $stmt->bind_param('s', $data['nombre_resultado']);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            if (!$res) {
                $stmt2 = $conn->prepare("INSERT INTO resultado (nombre, id_competencia) VALUES (?,?)");
                $stmt2->bind_param('si', $data['nombre_resultado'], $id_competencia);
                $stmt2->execute();
                $id_resultado = $conn->insert_id;
            } else {
                $id_resultado = $res['id_resultado'];
            }
        } elseif (!empty($data['id_resultado'])) {
            $id_resultado = intval($data['id_resultado']);
        }

        // Buscar o crear funcionario
        $id_funcionario = null;
        if (!empty($data['nombre_funcionario'])) {
            $stmt = $conn->prepare("SELECT id_funcionario FROM funcionario WHERE nombre = ?");
            $stmt->bind_param('s', $data['nombre_funcionario']);
            $stmt->execute();
            $func = $stmt->get_result()->fetch_assoc();
            if (!$func) {
                $stmt2 = $conn->prepare("INSERT INTO funcionario (nombre) VALUES (?)");
                $stmt2->bind_param('s', $data['nombre_funcionario']);
                $stmt2->execute();
                $id_funcionario = $conn->insert_id;
            } else {
                $id_funcionario = $func['id_funcionario'];
            }
        } elseif (!empty($data['id_funcionario'])) {
            $id_funcionario = intval($data['id_funcionario']);
        }

        // Normalizar tipo
        $tipo = strtoupper($data['tipo'] ?? 'POR_EVALUAR');
        if (!in_array($tipo, ['APROBADO', 'POR_EVALUAR'])) $tipo = 'POR_EVALUAR';

        $fecha = !empty($data['fecha']) ? $data['fecha'] : date('Y-m-d H:i:s');

        // Validar campos obligatorios
        if (empty($id_resultado) || empty($id_aprendiz)) {
            $errors[] = "Fila " . ($i + 2) . ": Faltan datos clave (Competencia/Resultado) para " . $data['numero_documento'];
            continue;
        }

        // Insertar juicio
        $stmt = $conn->prepare("INSERT INTO juicio_evaluacion (id_aprendiz, id_resultado, id_funcionario, tipo, fecha) VALUES (?,?,?,?,?)");
        $stmt->bind_param('iiiss', $id_aprendiz, $id_resultado, $id_funcionario, $tipo, $fecha);
        if ($stmt->execute()) {
            $inserted++;
        } else {
            $errors[] = "Fila " . ($i + 2) . ": Error BD - " . $stmt->error;
        }
    }

    unlink($tmpPath);
    echo json_encode([
        'success' => true, 
        'insertados' => $inserted, 
        'errores' => $errors,
        'headers_detectados' => $rawHeaders,
        'total_filas' => count($rows)
    ]);
    exit;
}

if ($method === 'GET' && $action === 'listar') {
    $id_aprendiz = intval($_GET['id_aprendiz'] ?? 0);
    if ($id_aprendiz) {
        $stmt = $conn->prepare("SELECT j.*, r.nombre AS resultado, c.nombre AS competencia, fn.nombre AS funcionario
            FROM juicio_evaluacion j
            LEFT JOIN resultado r ON j.id_resultado = r.id_resultado
            LEFT JOIN competencia c ON r.id_competencia = c.id_competencia
            LEFT JOIN funcionario fn ON j.id_funcionario = fn.id_funcionario
            WHERE j.id_aprendiz = ?
            ORDER BY c.nombre, r.nombre");
        $stmt->bind_param('i', $id_aprendiz);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("SELECT j.*, 
            CONCAT(a.nombre,' ',a.apellido) AS aprendiz, a.numero_documento,
            r.nombre AS resultado, c.nombre AS competencia, fn.nombre AS funcionario
            FROM juicio_evaluacion j
            LEFT JOIN aprendiz a ON j.id_aprendiz = a.id_aprendiz
            LEFT JOIN resultado r ON j.id_resultado = r.id_resultado
            LEFT JOIN competencia c ON r.id_competencia = c.id_competencia
            LEFT JOIN funcionario fn ON j.id_funcionario = fn.id_funcionario
            ORDER BY j.fecha DESC LIMIT 500");
    }
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}

echo json_encode(['error' => 'Acción no válida']);
$conn->close();
?>
