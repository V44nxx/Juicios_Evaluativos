<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../config/database.php';
$conn = getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// GET: Listar fases
if ($method === 'GET' && $action === 'listar_fases') {
    $sql = "SELECT f.*, COUNT(a.id_actividad) AS total_actividades
            FROM fase_proyecto f
            LEFT JOIN actividad_proyecto a ON f.id_fase = a.id_fase
            GROUP BY f.id_fase ORDER BY f.orden, f.nombre";
    $result = $conn->query($sql);
    echo json_encode($result ? $result->fetch_all(MYSQLI_ASSOC) : []);
    exit;
}

// GET: Listar actividades de una fase
if ($method === 'GET' && $action === 'listar_actividades') {
    $id_fase = intval($_GET['id_fase'] ?? 0);
    $sql = "SELECT a.*, 
            GROUP_CONCAT(DISTINCT c.nombre SEPARATOR ', ') AS competencias,
            GROUP_CONCAT(DISTINCT r.nombre SEPARATOR ', ') AS resultados
            FROM actividad_proyecto a
            LEFT JOIN actividad_competencia ac ON a.id_actividad = ac.id_actividad
            LEFT JOIN competencia c ON ac.id_competencia = c.id_competencia
            LEFT JOIN actividad_resultado ar ON a.id_actividad = ar.id_actividad
            LEFT JOIN resultado r ON ar.id_resultado = r.id_resultado
            WHERE a.id_fase = ?
            GROUP BY a.id_actividad ORDER BY a.nombre";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_fase);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

// GET: Competencias y resultados disponibles
if ($method === 'GET' && $action === 'competencias_resultados') {
    $comps = $conn->query("SELECT * FROM competencia ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
    $results = $conn->query("SELECT r.*, c.nombre AS competencia FROM resultado r LEFT JOIN competencia c ON r.id_competencia = c.id_competencia ORDER BY c.nombre, r.nombre")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['competencias' => $comps, 'resultados' => $results]);
    exit;
}

// POST: Crear fase
if ($method === 'POST' && $action === 'crear_fase') {
    $body = json_decode(file_get_contents('php://input'), true);
    $nombre = trim($body['nombre'] ?? '');
    $descripcion = trim($body['descripcion'] ?? '');
    $orden = intval($body['orden'] ?? 1);
    if (!$nombre) { echo json_encode(['error' => 'Nombre requerido']); exit; }
    $stmt = $conn->prepare("INSERT INTO fase_proyecto (nombre, descripcion, orden) VALUES (?,?,?)");
    $stmt->bind_param('ssi', $nombre, $descripcion, $orden);
    $stmt->execute();
    echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    exit;
}

// POST: Crear actividad
if ($method === 'POST' && $action === 'crear_actividad') {
    $body = json_decode(file_get_contents('php://input'), true);
    $nombre = trim($body['nombre'] ?? '');
    $id_fase = intval($body['id_fase'] ?? 0);
    $descripcion = trim($body['descripcion'] ?? '');
    if (!$nombre || !$id_fase) { echo json_encode(['error' => 'Datos incompletos']); exit; }

    $stmt = $conn->prepare("INSERT INTO actividad_proyecto (nombre, descripcion, id_fase) VALUES (?,?,?)");
    $stmt->bind_param('ssi', $nombre, $descripcion, $id_fase);
    $stmt->execute();
    $id_act = $conn->insert_id;

    // Relacionar competencias
    if (!empty($body['competencias'])) {
        foreach ($body['competencias'] as $id_comp) {
            $s = $conn->prepare("INSERT IGNORE INTO actividad_competencia (id_actividad, id_competencia) VALUES (?,?)");
            $s->bind_param('ii', $id_act, $id_comp);
            $s->execute();
        }
    }

    // Relacionar resultados
    if (!empty($body['resultados'])) {
        foreach ($body['resultados'] as $id_res) {
            $s = $conn->prepare("INSERT IGNORE INTO actividad_resultado (id_actividad, id_resultado) VALUES (?,?)");
            $s->bind_param('ii', $id_act, $id_res);
            $s->execute();
        }
    }

    echo json_encode(['success' => true, 'id' => $id_act]);
    exit;
}

// DELETE: Fase o actividad
if ($method === 'DELETE') {
    if ($action === 'eliminar_fase') {
        $id = intval($_GET['id'] ?? 0);
        $conn->query("DELETE FROM fase_proyecto WHERE id_fase = $id");
        echo json_encode(['success' => true]);
        exit;
    }
    if ($action === 'eliminar_actividad') {
        $id = intval($_GET['id'] ?? 0);
        $conn->query("DELETE FROM actividad_proyecto WHERE id_actividad = $id");
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['error' => 'Acción no válida']);
$conn->close();
?>
