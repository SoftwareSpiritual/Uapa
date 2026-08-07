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
    }

    return $pdo;
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
    $pdo->exec('CREATE TABLE IF NOT EXISTS carreras (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        nombre TEXT NOT NULL
    )');

    $columns = $pdo->query("PRAGMA table_info(courses)")->fetchAll();
    $hasCarreraId = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'carrera_id') {
            $hasCarreraId = true;
            break;
        }
    }

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
