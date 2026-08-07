<?php

declare(strict_types=1);

const CARRERAS_SEED = [
    ['slug' => 'ingenieria-software', 'nombre' => 'Ingeniería en Software', 'archivo' => 'pensum.json'],
    ['slug' => 'derecho', 'nombre' => 'Licenciatura en Derecho', 'archivo' => 'derecho.json'],
];

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbPath = __DIR__ . '/../database/uapa.sqlite';
    $needsInit = !file_exists($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ($needsInit) {
        seed_db($pdo);
    } else {
        migrate_db($pdo);
        migrate_ingenieria_clave_fixes($pdo);
    }

    return $pdo;
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
    $stmt->execute([':name' => $table]);

    return $stmt->fetchColumn() !== false;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $columns = $pdo->query("PRAGMA table_info({$table})")->fetchAll();
    foreach ($columns as $col) {
        if ($col['name'] === $column) {
            return true;
        }
    }

    return false;
}

function seed_db(PDO $pdo): void
{
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    $pdo->exec($schema);

    $pdo->beginTransaction();
    foreach (CARRERAS_SEED as $carrera) {
        $carreraId = insert_carrera($pdo, $carrera['slug'], $carrera['nombre']);
        insert_courses_from_file($pdo, $carreraId, $carrera['archivo']);
    }
    $pdo->commit();
}

function migrate_db(PDO $pdo): void
{
    // Fast path: once carreras exists and courses.carrera_id has been added, there is
    // nothing left to do here on every request.
    if (table_exists($pdo, 'carreras') && column_exists($pdo, 'courses', 'carrera_id')) {
        return;
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS carreras (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        nombre TEXT NOT NULL
    )');

    $hasCarreraId = column_exists($pdo, 'courses', 'carrera_id');

    $pdo->beginTransaction();

    if (!$hasCarreraId) {
        $pdo->exec('ALTER TABLE courses ADD COLUMN carrera_id INTEGER NOT NULL DEFAULT 1');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_courses_carrera ON courses (carrera_id)');
    }

    foreach (CARRERAS_SEED as $carrera) {
        $carreraId = find_carrera_id_by_slug($pdo, $carrera['slug']);
        if ($carreraId === null) {
            $carreraId = insert_carrera($pdo, $carrera['slug'], $carrera['nombre']);
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE carrera_id = :carrera_id');
        $stmt->execute([':carrera_id' => $carreraId]);
        $existing = (int) $stmt->fetchColumn();

        if ($existing === 0) {
            insert_courses_from_file($pdo, $carreraId, $carrera['archivo']);
        }
    }

    $pdo->commit();
}

/**
 * One-time data fix: the original Ingeniería seed had several transcription errors
 * against the official UAPA pensum (wrong claves, a duplicated INF-306, wrong hours,
 * a couple of stray prerrequisitos, and 8 missing materias). This corrects existing
 * rows in place by primary key -- never touching `estado` -- and inserts the missing
 * ones as pendiente. Guarded by checking for PAI-400 so it only ever runs once.
 */
function migrate_ingenieria_clave_fixes(PDO $pdo): void
{
    $carreraId = find_carrera_id_by_slug($pdo, 'ingenieria-software');
    if ($carreraId === null) {
        return;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE carrera_id = :carrera_id AND clave = :clave');
    $stmt->execute([':carrera_id' => $carreraId, ':clave' => 'PAI-400']);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    // [old_clave, periodo_orden (null = optativa), column => new value]
    $corrections = [
        ['CBC-102', 1, ['horas_teoricas' => 3, 'nombre' => 'Introducción a la Educación a Distancia']],
        ['FCG-102', 3, ['clave' => 'FGC-102', 'horas_teoricas' => 3]],
        ['CBC-107', 3, ['nombre' => 'Ser Humano y Desarrollo Sostenible']],
        ['FGM-103', 4, ['nombre' => 'Análisis Matemático II']],
        ['FGI-106', 4, ['nombre' => 'Sistema de Base de Datos']],
        ['FCG-205', 6, ['clave' => 'FGC-205']],
        ['FCG-206', 7, ['clave' => 'FGC-206', 'prerrequisito' => 'FGC-205']],
        ['FGM-206', 8, ['nombre' => 'Álgebra Lineal']],
        ['INF-302', 10, ['clave' => 'INF-303', 'horas_teoricas' => 1, 'horas_practicas' => 6]],
        ['ISW-305', 10, ['nombre' => 'Estructura de Datos y Algoritmos']],
        ['INF-303', 11, ['clave' => 'INF-304']],
        ['INF-304', 11, ['clave' => 'INF-305']],
        ['ISW-306', 11, ['nombre' => 'Desarrollo de Aplicaciones Web']],
        ['ISW-307', 12, ['nombre' => 'Programación de Dispositivos Móviles']],
        ['ISW-308', 12, ['nombre' => 'Gráficos por Computadoras']],
        ['INF-407', 13, ['clave' => 'INF-408', 'nombre' => 'Investigación de Operaciones']],
        ['INF-408', 13, ['clave' => 'INF-409', 'prerrequisito' => 'INF-304']],
        ['FCG-409', 14, ['clave' => 'FGC-409', 'nombre' => 'Ética Profesional']],
        ['INF-306', 14, ['clave' => 'INF-307', 'nombre' => 'Gestión del Conocimiento y la Toma de Decisiones', 'prerrequisito' => 'INF-304']],
        ['ISW-412', 15, ['prerrequisito' => 'INF-307']],
        ['ISW-413', 15, ['prerrequisito' => 'INF-408']],
        ['ADM-314', null, ['nombre' => 'Liderazgo y Gestión de Equipo']],
        ['ADM-309', null, ['nombre' => 'Formulación de Proyectos Emprendedores', 'prerrequisito' => null]],
        ['FGL-201', null, ['nombre' => 'Inglés de Sistema Informático']],
        ['INF-306', null, ['nombre' => 'Administración de Sistema de Información']],
    ];

    // Resolve every target row's id against the pristine, pre-migration data first.
    // Doing renames and lookups interleaved would let an earlier rename (e.g. INF-303
    // -> INF-304) shadow a later lookup for the original INF-304 row.
    $findByPeriodo = $pdo->prepare(
        'SELECT id FROM courses WHERE carrera_id = :carrera_id AND clave = :clave AND periodo_orden = :periodo_orden'
    );
    $findOptativa = $pdo->prepare(
        'SELECT id FROM courses WHERE carrera_id = :carrera_id AND clave = :clave AND periodo_orden IS NULL'
    );

    $targets = [];
    foreach ($corrections as [$oldClave, $periodo, $changes]) {
        if ($periodo === null) {
            $findOptativa->execute([':carrera_id' => $carreraId, ':clave' => $oldClave]);
            $id = $findOptativa->fetchColumn();
        } else {
            $findByPeriodo->execute([':carrera_id' => $carreraId, ':clave' => $oldClave, ':periodo_orden' => $periodo]);
            $id = $findByPeriodo->fetchColumn();
        }

        if ($id !== false) {
            $targets[] = [(int) $id, $changes];
        }
    }

    $pdo->beginTransaction();

    foreach ($targets as [$id, $changes]) {
        $setParts = [];
        $params = [':id' => $id];
        foreach ($changes as $column => $value) {
            $setParts[] = "{$column} = :{$column}";
            $params[":{$column}"] = $value;
        }
        $sql = 'UPDATE courses SET ' . implode(', ', $setParts) . ' WHERE id = :id';
        $pdo->prepare($sql)->execute($params);
    }

    $newCourses = [
        ['clave' => 'PAI-400', 'nombre' => 'Pasantía de 240 horas', 'periodo_orden' => 14, 'periodo_nombre' => 'Decimo Cuarto Cuatrimestre', 'tipo' => 'obligatoria', 'horas_teoricas' => 0, 'horas_practicas' => 240, 'horas_totales' => 0, 'horas_estudio_independiente' => 0, 'creditos' => 8, 'prerrequisito' => null, 'estado' => 'pendiente'],
        ['clave' => 'DER-313', 'nombre' => 'Derechos Intelectuales', 'periodo_orden' => null, 'periodo_nombre' => null, 'tipo' => 'optativa', 'horas_teoricas' => 3, 'horas_practicas' => 2, 'horas_totales' => 24, 'horas_estudio_independiente' => 96, 'creditos' => 4, 'prerrequisito' => null, 'estado' => 'pendiente'],
        ['clave' => 'MER-206', 'nombre' => 'Comportamiento del Consumidor', 'periodo_orden' => null, 'periodo_nombre' => null, 'tipo' => 'optativa', 'horas_teoricas' => 2, 'horas_practicas' => 4, 'horas_totales' => 24, 'horas_estudio_independiente' => 96, 'creditos' => 3, 'prerrequisito' => null, 'estado' => 'pendiente'],
        ['clave' => 'EDL-329', 'nombre' => 'Redacción Académica y Profesional', 'periodo_orden' => null, 'periodo_nombre' => null, 'tipo' => 'optativa', 'horas_teoricas' => 1, 'horas_practicas' => 6, 'horas_totales' => 24, 'horas_estudio_independiente' => 96, 'creditos' => 4, 'prerrequisito' => 'CBE-105', 'estado' => 'pendiente'],
        ['clave' => 'ADM-420', 'nombre' => 'Simulación de Negocios', 'periodo_orden' => null, 'periodo_nombre' => null, 'tipo' => 'optativa', 'horas_teoricas' => 1, 'horas_practicas' => 6, 'horas_totales' => 24, 'horas_estudio_independiente' => 96, 'creditos' => 4, 'prerrequisito' => null, 'estado' => 'pendiente'],
        ['clave' => 'MER-421', 'nombre' => 'Simulación de Marketing', 'periodo_orden' => null, 'periodo_nombre' => null, 'tipo' => 'optativa', 'horas_teoricas' => 1, 'horas_practicas' => 6, 'horas_totales' => 24, 'horas_estudio_independiente' => 96, 'creditos' => 4, 'prerrequisito' => null, 'estado' => 'pendiente'],
        ['clave' => 'FGC-208', 'nombre' => 'Educación Constitucional', 'periodo_orden' => null, 'periodo_nombre' => null, 'tipo' => 'optativa', 'horas_teoricas' => 3, 'horas_practicas' => 0, 'horas_totales' => 18, 'horas_estudio_independiente' => 72, 'creditos' => 3, 'prerrequisito' => null, 'estado' => 'pendiente'],
    ];

    $insertStmt = $pdo->prepare(
        'INSERT INTO courses
            (carrera_id, clave, nombre, periodo_orden, periodo_nombre, tipo, horas_teoricas,
             horas_practicas, horas_totales, horas_estudio_independiente, creditos,
             prerrequisito, estado)
         VALUES
            (:carrera_id, :clave, :nombre, :periodo_orden, :periodo_nombre, :tipo, :horas_teoricas,
             :horas_practicas, :horas_totales, :horas_estudio_independiente, :creditos,
             :prerrequisito, :estado)'
    );

    foreach ($newCourses as $course) {
        $insertStmt->execute([
            ':carrera_id' => $carreraId,
            ':clave' => $course['clave'],
            ':nombre' => $course['nombre'],
            ':periodo_orden' => $course['periodo_orden'],
            ':periodo_nombre' => $course['periodo_nombre'],
            ':tipo' => $course['tipo'],
            ':horas_teoricas' => $course['horas_teoricas'],
            ':horas_practicas' => $course['horas_practicas'],
            ':horas_totales' => $course['horas_totales'],
            ':horas_estudio_independiente' => $course['horas_estudio_independiente'],
            ':creditos' => $course['creditos'],
            ':prerrequisito' => $course['prerrequisito'],
            ':estado' => $course['estado'],
        ]);
    }

    $pdo->commit();
}

function find_carrera_id_by_slug(PDO $pdo, string $slug): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM carreras WHERE slug = :slug');
    $stmt->execute([':slug' => $slug]);
    $id = $stmt->fetchColumn();

    return $id === false ? null : (int) $id;
}

function insert_carrera(PDO $pdo, string $slug, string $nombre): int
{
    $stmt = $pdo->prepare('INSERT INTO carreras (slug, nombre) VALUES (:slug, :nombre)');
    $stmt->execute([':slug' => $slug, ':nombre' => $nombre]);

    return (int) $pdo->lastInsertId();
}

function insert_courses_from_file(PDO $pdo, int $carreraId, string $archivo): void
{
    $jsonPath = __DIR__ . '/../data/' . $archivo;
    $courses = json_decode(file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);

    $stmt = $pdo->prepare(
        'INSERT INTO courses
            (carrera_id, clave, nombre, periodo_orden, periodo_nombre, tipo, horas_teoricas,
             horas_practicas, horas_totales, horas_estudio_independiente, creditos,
             prerrequisito, estado)
         VALUES
            (:carrera_id, :clave, :nombre, :periodo_orden, :periodo_nombre, :tipo, :horas_teoricas,
             :horas_practicas, :horas_totales, :horas_estudio_independiente, :creditos,
             :prerrequisito, :estado)'
    );

    foreach ($courses as $course) {
        $stmt->execute([
            ':carrera_id' => $carreraId,
            ':clave' => $course['clave'],
            ':nombre' => $course['nombre'],
            ':periodo_orden' => $course['periodo_orden'],
            ':periodo_nombre' => $course['periodo_nombre'],
            ':tipo' => $course['tipo'],
            ':horas_teoricas' => $course['horas_teoricas'],
            ':horas_practicas' => $course['horas_practicas'],
            ':horas_totales' => $course['horas_totales'],
            ':horas_estudio_independiente' => $course['horas_estudio_independiente'],
            ':creditos' => $course['creditos'],
            ':prerrequisito' => $course['prerrequisito'],
            ':estado' => $course['estado'],
        ]);
    }
}
