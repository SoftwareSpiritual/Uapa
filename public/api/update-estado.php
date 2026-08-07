<?php

declare(strict_types=1);

require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int) $input['id'] : 0;
$estado = isset($input['estado']) ? (string) $input['estado'] : '';

if ($id <= 0 || !in_array($estado, ESTADOS_DISPONIBLES, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
    exit;
}

$pdo = get_db();

try {
    $course = update_course_estado($pdo, $id, $estado);
} catch (RuntimeException $e) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

$allCourses = fetch_all_courses($pdo, (int) $course['carrera_id']);

if ($course['periodo_orden'] !== null) {
    $periodoOrden = (int) $course['periodo_orden'];
    $scopeCourses = array_values(array_filter(
        $allCourses,
        static fn (array $c): bool => $c['periodo_orden'] !== null && (int) $c['periodo_orden'] === $periodoOrden
    ));
    $scope = 'period-' . $periodoOrden;
} else {
    $scopeCourses = array_values(array_filter(
        $allCourses,
        static fn (array $c): bool => $c['periodo_orden'] === null
    ));
    $scope = 'optativas';
}

$scopeStats = compute_stats($scopeCourses);

echo json_encode([
    'ok' => true,
    'course' => [
        'id' => (int) $course['id'],
        'estado' => $course['estado'],
        'estado_label' => estado_label($course['estado']),
    ],
    'scope' => $scope,
    'stats' => $scopeStats,
]);
