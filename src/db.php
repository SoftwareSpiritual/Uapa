<?php

declare(strict_types=1);

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

    if ($needsInit) {
        seed_db($pdo);
    }

    return $pdo;
}

function seed_db(PDO $pdo): void
{
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    $pdo->exec($schema);

    $jsonPath = __DIR__ . '/../data/pensum.json';
    $courses = json_decode(file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);

    $stmt = $pdo->prepare(
        'INSERT INTO courses
            (clave, nombre, periodo_orden, periodo_nombre, tipo, horas_teoricas,
             horas_practicas, horas_totales, horas_estudio_independiente, creditos,
             prerrequisito, estado)
         VALUES
            (:clave, :nombre, :periodo_orden, :periodo_nombre, :tipo, :horas_teoricas,
             :horas_practicas, :horas_totales, :horas_estudio_independiente, :creditos,
             :prerrequisito, :estado)'
    );

    $pdo->beginTransaction();
    foreach ($courses as $course) {
        $stmt->execute([
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
