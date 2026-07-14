<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once '../config/database.php';

$conn = getConnection();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'stats':
        $id_ficha = intval($_GET['id_ficha'] ?? 0);
        $where = "1=1";
        $whereJ = "1=1";
        $params = [];
        $types = '';

        if ($id_ficha) {
            $where = "id_ficha = ?";
            // Para juicios, necesitamos un JOIN con aprendiz
            $whereJ = "id_aprendiz IN (SELECT id_aprendiz FROM aprendiz WHERE id_ficha = ?)";
            $params[] = $id_ficha;
            $types = 'i';
        }

        $data = [];

        // Total aprendices
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM aprendiz WHERE $where");
        if ($id_ficha) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['total_aprendices'] = $stmt->get_result()->fetch_assoc()['total'];

        // Aprendices por estado
        $stmt = $conn->prepare("SELECT estado, COUNT(*) AS cantidad FROM aprendiz WHERE $where GROUP BY estado");
        if ($id_ficha) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['por_estado'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Juicios aprobados
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM juicio_evaluacion WHERE tipo='APROBADO' AND $whereJ");
        if ($id_ficha) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['juicios_aprobados'] = $stmt->get_result()->fetch_assoc()['total'];

        // Juicios por evaluar
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM juicio_evaluacion WHERE tipo='POR_EVALUAR' AND $whereJ");
        if ($id_ficha) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['juicios_por_evaluar'] = $stmt->get_result()->fetch_assoc()['total'];

        // Total juicios
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM juicio_evaluacion WHERE $whereJ");
        if ($id_ficha) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['total_juicios'] = $stmt->get_result()->fetch_assoc()['total'];

        // Total fichas
        $r = $conn->query("SELECT COUNT(*) AS total FROM ficha");
        $data['total_fichas'] = $r->fetch_assoc()['total'];

        // Aprendices por ficha
        $r = $conn->query("SELECT f.nombre AS ficha, COUNT(a.id_aprendiz) AS cantidad FROM ficha f LEFT JOIN aprendiz a ON f.id_ficha = a.id_ficha GROUP BY f.id_ficha ORDER BY cantidad DESC");
        $data['por_ficha'] = $r->fetch_all(MYSQLI_ASSOC);

        echo json_encode($data);
        break;

    case 'stats_premium':
        try {
            $id_ficha = isset($_GET['id_ficha']) ? intval($_GET['id_ficha']) : 0;
            $data = [
                'total_aprendices' => 0,
                'aprendices_retirados' => 0,
                'total_competencias' => 0,
                'juicios_aprobados' => 0,
                'juicios_pendientes' => 0,
                'total_juicios' => 0,
                'porcentaje_general' => 0,
                'actividad_reciente' => [],
                'top_aprendices' => [],
                'top_competencias' => [],
                'alertas' => []
            ];

            $whereA = "1=1";
            $whereJ = "1=1";
            if ($id_ficha > 0) {
                $whereA = "id_ficha = $id_ficha";
                $whereJ = "j.id_aprendiz IN (SELECT id_aprendiz FROM aprendiz WHERE id_ficha = $id_ficha)";
            }

            // 1. KPIs de Aprendices
            $qAprendices = $conn->query("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN UPPER(estado) LIKE '%RETIR%' THEN 1 ELSE 0 END) as retirados
                FROM aprendiz WHERE $whereA");
            if ($qAprendices) {
                $row = $qAprendices->fetch_assoc();
                $data['total_aprendices'] = intval($row['total'] ?? 0);
                $data['aprendices_retirados'] = intval($row['retirados'] ?? 0);
            }

            // 2. KPIs de Juicios
            $whereJ_stats = ($id_ficha > 0) ? "j.id_aprendiz IN (SELECT id_aprendiz FROM aprendiz WHERE id_ficha = $id_ficha)" : "1=1";
            $qJuicios = $conn->query("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN tipo='APROBADO' THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN tipo='POR_EVALUAR' THEN 1 ELSE 0 END) as pendientes
                FROM juicio_evaluacion j WHERE $whereJ_stats");
            if ($qJuicios) {
                $row = $qJuicios->fetch_assoc();
                $data['total_juicios'] = intval($row['total']);
                $data['juicios_aprobados'] = intval($row['aprobados']);
                $data['juicios_pendientes'] = intval($row['pendientes']);
                if ($data['total_juicios'] > 0) {
                    $data['porcentaje_general'] = round(($data['juicios_aprobados'] * 100) / $data['total_juicios'], 2);
                }
            }

            // 3. Total Competencias (relevantes para la ficha si se selecciona)
            $sqlCompCount = "SELECT COUNT(DISTINCT r.id_competencia) 
                             FROM resultado r 
                             JOIN juicio_evaluacion j ON r.id_resultado = j.id_resultado 
                             WHERE $whereJ";
            if ($id_ficha == 0) {
                $sqlCompCount = "SELECT COUNT(*) FROM competencia";
            }
            $resCompCount = $conn->query($sqlCompCount);
            $data['total_competencias'] = $resCompCount ? intval($resCompCount->fetch_row()[0]) : 0;

            // 4. Actividad Reciente
            $qRecent = $conn->query("SELECT 
                j.fecha, j.tipo, a.nombre as aprendiz_nom, a.apellido as aprendiz_ape, 
                r.nombre as resultado, f.nombre as instructor
                FROM juicio_evaluacion j
                JOIN aprendiz a ON j.id_aprendiz = a.id_aprendiz
                JOIN resultado r ON j.id_resultado = r.id_resultado
                LEFT JOIN funcionario f ON j.id_funcionario = f.id_funcionario
                WHERE $whereJ 
                ORDER BY j.fecha DESC LIMIT 6");
            if ($qRecent) $data['actividad_reciente'] = $qRecent->fetch_all(MYSQLI_ASSOC);

            // 5. Rankings
            $whereA_alias = $id_ficha > 0 ? "a.id_ficha = $id_ficha" : "1=1";
            $qTopA = $conn->query("SELECT 
                CONCAT(a.nombre, ' ', a.apellido) as nombre_completo,
                ROUND(COALESCE((SUM(CASE WHEN j.tipo='APROBADO' THEN 1 ELSE 0 END)*100)/NULLIF(COUNT(j.id_juicio),0), 0), 2) as porcentaje_avance
                FROM aprendiz a
                LEFT JOIN juicio_evaluacion j ON a.id_aprendiz = j.id_aprendiz
                WHERE $whereA_alias
                GROUP BY a.id_aprendiz
                ORDER BY porcentaje_avance DESC LIMIT 5");
            if ($qTopA) $data['top_aprendices'] = $qTopA->fetch_all(MYSQLI_ASSOC);

            // 6. Competencias
            $qTopC = $conn->query("SELECT 
                c.nombre as competencia,
                ROUND(COALESCE((SUM(CASE WHEN j.tipo='APROBADO' THEN 1 ELSE 0 END)*100)/NULLIF(COUNT(j.id_juicio),0), 0), 2) as porcentaje_aprobacion
                FROM competencia c
                JOIN resultado r ON c.id_competencia = r.id_competencia
                JOIN juicio_evaluacion j ON r.id_resultado = j.id_resultado
                JOIN aprendiz a ON j.id_aprendiz = a.id_aprendiz
                WHERE ($whereA_alias)
                GROUP BY c.id_competencia
                ORDER BY porcentaje_aprobacion DESC LIMIT 5");
            if ($qTopC) $data['top_competencias'] = $qTopC->fetch_all(MYSQLI_ASSOC);

            // 7. Alertas de Riesgo
            $qRiesgo = $conn->query("SELECT COUNT(*) FROM (
                SELECT a.id_aprendiz FROM aprendiz a 
                JOIN juicio_evaluacion j ON a.id_aprendiz = j.id_aprendiz
                WHERE j.tipo='POR_EVALUAR' AND $whereA_alias
                GROUP BY a.id_aprendiz HAVING COUNT(j.id_juicio) > 10
            ) as t");
            if ($qRiesgo) {
                $riesgoCount = intval($qRiesgo->fetch_row()[0]);
                if ($riesgoCount > 0) {
                    $data['alertas'][] = ["msg" => "$riesgoCount aprendices con más de 10 juicios pendientes", "type" => "danger"];
                }
            }

            echo json_encode($data);
        } catch (Exception $e) {
            ob_clean();
            echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
        break;

    case 'avance_aprendices':
        $where = "1=1";
        $params = [];
        $types = '';

        if (!empty($_GET['id_ficha'])) {
            $where .= " AND a.id_ficha = ?";
            $params[] = intval($_GET['id_ficha']);
            $types .= 'i';
        }
        if (!empty($_GET['estado'])) {
            $where .= " AND a.estado = ?";
            $params[] = $_GET['estado'];
            $types .= 's';
        }

        $sql = "SELECT 
            a.id_aprendiz,
            a.nombre,
            a.apellido,
            a.numero_documento,
            a.estado,
            f.nombre AS ficha,
            COUNT(j.id_juicio) AS total_juicios,
            SUM(CASE WHEN j.tipo='APROBADO' THEN 1 ELSE 0 END) AS aprobados,
            SUM(CASE WHEN j.tipo='POR_EVALUAR' THEN 1 ELSE 0 END) AS por_evaluar,
            ROUND(
                CASE WHEN COUNT(j.id_juicio) > 0 
                THEN (SUM(CASE WHEN j.tipo='APROBADO' THEN 1 ELSE 0 END) * 100.0) / COUNT(j.id_juicio)
                ELSE 0 END, 2
            ) AS porcentaje_avance
        FROM aprendiz a
        LEFT JOIN ficha f ON a.id_ficha = f.id_ficha
        LEFT JOIN juicio_evaluacion j ON a.id_aprendiz = j.id_aprendiz
        WHERE $where
        GROUP BY a.id_aprendiz
        ORDER BY porcentaje_avance DESC";

        if (!empty($params)) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }

        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        break;

    case 'aprobacion_competencia':
        $id_ficha = intval($_GET['id_ficha'] ?? 0);
        $where = "1=1";
        $params = [];
        $types = '';

        if ($id_ficha) {
            $where = "j.id_aprendiz IN (SELECT id_aprendiz FROM aprendiz WHERE id_ficha = ?)";
            $params[] = $id_ficha;
            $types = 'i';
        }

        $sql = "SELECT 
            c.id_competencia,
            c.nombre AS competencia,
            COUNT(j.id_juicio) AS total,
            SUM(CASE WHEN j.tipo='APROBADO' THEN 1 ELSE 0 END) AS aprobados,
            ROUND(
                CASE WHEN COUNT(j.id_juicio) > 0 
                THEN (SUM(CASE WHEN j.tipo='APROBADO' THEN 1 ELSE 0 END) * 100.0) / COUNT(j.id_juicio)
                ELSE 0 END, 2
            ) AS porcentaje
        FROM competencia c
        JOIN resultado r ON c.id_competencia = r.id_competencia
        JOIN juicio_evaluacion j ON r.id_resultado = j.id_resultado
        WHERE $where
        GROUP BY c.id_competencia
        ORDER BY porcentaje DESC";

        if ($id_ficha) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }
        
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        break;

    case 'juicios_por_aprendiz':
        $id = intval($_GET['id_aprendiz'] ?? 0);
        if (!$id) { echo json_encode([]); break; }

        $sql = "SELECT 
            a.nombre, a.apellido, a.numero_documento, a.tipo_documento, a.estado,
            f.nombre AS ficha,
            c.nombre AS competencia,
            r.nombre AS resultado_aprendizaje,
            j.tipo AS juicio,
            j.fecha,
            fn.nombre AS funcionario
        FROM aprendiz a
        LEFT JOIN ficha f ON a.id_ficha = f.id_ficha
        LEFT JOIN juicio_evaluacion j ON a.id_aprendiz = j.id_aprendiz
        LEFT JOIN resultado r ON j.id_resultado = r.id_resultado
        LEFT JOIN competencia c ON r.id_competencia = c.id_competencia
        LEFT JOIN funcionario fn ON j.id_funcionario = fn.id_funcionario
        WHERE a.id_aprendiz = ?
        ORDER BY c.nombre, r.nombre";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        break;

    case 'filtro_avanzado':
        $where = "1=1";
        $params = [];
        $types = '';

        if (!empty($_GET['nombre'])) {
            $where .= " AND (a.nombre LIKE ? OR a.apellido LIKE ?)";
            $like = '%' . $_GET['nombre'] . '%';
            $params[] = $like; $params[] = $like;
            $types .= 'ss';
        }
        if (!empty($_GET['documento'])) {
            $where .= " AND a.numero_documento LIKE ?";
            $params[] = '%' . $_GET['documento'] . '%';
            $types .= 's';
        }
        if (!empty($_GET['estado'])) {
            $where .= " AND UPPER(a.estado) LIKE ?";
            $params[] = '%' . strtoupper($_GET['estado']) . '%';
            $types .= 's';
        }
        if (!empty($_GET['id_ficha'])) {
            $where .= " AND a.id_ficha = ?";
            $params[] = intval($_GET['id_ficha']);
            $types .= 'i';
        }
        if (!empty($_GET['id_competencia'])) {
            $where .= " AND c.id_competencia = ?";
            $params[] = intval($_GET['id_competencia']);
            $types .= 'i';
        }
        if (!empty($_GET['id_resultado'])) {
            $where .= " AND r.id_resultado = ?";
            $params[] = intval($_GET['id_resultado']);
            $types .= 'i';
        }
        if (!empty($_GET['tipo_juicio'])) {
            $where .= " AND j.tipo = ?";
            $params[] = $_GET['tipo_juicio'];
            $types .= 's';
        }

        $sql = "SELECT DISTINCT
            a.id_aprendiz, a.nombre, a.apellido, a.numero_documento, a.estado,
            f.nombre AS ficha,
            c.nombre AS competencia,
            r.nombre AS resultado,
            j.tipo AS juicio, j.fecha,
            fn.nombre AS funcionario
        FROM aprendiz a
        LEFT JOIN ficha f ON a.id_ficha = f.id_ficha
        LEFT JOIN juicio_evaluacion j ON a.id_aprendiz = j.id_aprendiz
        LEFT JOIN resultado r ON j.id_resultado = r.id_resultado
        LEFT JOIN competencia c ON r.id_competencia = c.id_competencia
        LEFT JOIN funcionario fn ON j.id_funcionario = fn.id_funcionario
        WHERE $where
        ORDER BY a.apellido, a.nombre";

        if (!empty($params)) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        break;

    case 'fichas':
        $r = $conn->query("SELECT id_ficha, nombre FROM ficha ORDER BY nombre");
        echo json_encode($r->fetch_all(MYSQLI_ASSOC));
        break;

    case 'competencias':
        $r = $conn->query("SELECT id_competencia, nombre FROM competencia ORDER BY nombre");
        echo json_encode($r->fetch_all(MYSQLI_ASSOC));
        break;

    case 'resultados':
        $id_comp = intval($_GET['id_competencia'] ?? 0);
        if ($id_comp) {
            $stmt = $conn->prepare("SELECT id_resultado, nombre FROM resultado WHERE id_competencia = ? ORDER BY nombre");
            $stmt->bind_param('i', $id_comp);
            $stmt->execute();
            $r = $stmt->get_result();
        } else {
            $r = $conn->query("SELECT id_resultado, nombre FROM resultado ORDER BY nombre");
        }
        echo json_encode($r->fetch_all(MYSQLI_ASSOC));
        break;

    case 'aprendices_lista':
        $r = $conn->query("SELECT id_aprendiz, CONCAT(nombre,' ',apellido) AS nombre_completo, numero_documento FROM aprendiz ORDER BY apellido, nombre");
        echo json_encode($r->fetch_all(MYSQLI_ASSOC));
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}

$conn->close();
?>
