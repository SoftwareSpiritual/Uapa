<?php

declare(strict_types=1);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/functions.php';

$pdo = get_db();
$courses = fetch_all_courses($pdo);
$stats = compute_stats($courses);
$enProgreso = courses_by_estado($courses, 'en_progreso');
$proximas = next_courses($courses);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inicio &mdash; Pensum Ingeniería de Software</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<?= render_topbar('inicio') ?>

<section class="dashboard-hero">
    <div class="hero-card">
        <div class="hero-top">
            <div class="hero-headline">
                <span class="hero-pct"><?= $stats['pct_aprobada'] ?>%</span>
                <span class="hero-label">de créditos aprobados<br><?= $stats['creditos_aprobados'] ?> de <?= $stats['creditos_totales'] ?> créditos</span>
            </div>
            <ul class="legend">
                <li><span class="dot dot-aprobada"></span> Aprobada</li>
                <li><span class="dot dot-en_progreso"></span> En progreso</li>
                <li><span class="dot dot-planificada"></span> Planificada</li>
                <li><span class="dot dot-pendiente"></span> Pendiente</li>
            </ul>
        </div>

        <?= render_progress_bar($stats, true, 'lg') ?>

        <div class="stat-chips">
            <div class="chip chip-aprobada">
                <span class="chip-value"><?= $stats['aprobadas'] ?></span>
                <span class="chip-label">Materias aprobadas</span>
            </div>
            <div class="chip chip-en_progreso">
                <span class="chip-value"><?= $stats['en_progreso'] ?></span>
                <span class="chip-label">En progreso</span>
            </div>
            <div class="chip chip-planificada">
                <span class="chip-value"><?= $stats['planificadas'] ?></span>
                <span class="chip-label">Planificadas</span>
            </div>
            <div class="chip chip-pendiente">
                <span class="chip-value"><?= $stats['pendientes'] ?></span>
                <span class="chip-label">Pendientes</span>
            </div>
            <div class="chip">
                <span class="chip-value"><?= $stats['total_cursos'] ?></span>
                <span class="chip-label">Materias totales</span>
            </div>
        </div>
    </div>
</section>

<main class="home-sections">
    <section class="home-section home-section-progreso">
        <div class="section-heading">
            <div class="section-heading-left">
                <span class="section-icon section-icon-progreso" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3.5 2"></path></svg>
                </span>
                <h2>En curso ahora</h2>
            </div>
            <span class="section-count"><?= count($enProgreso) ?> materia<?= count($enProgreso) === 1 ? '' : 's' ?></span>
        </div>
        <?php if (empty($enProgreso)): ?>
        <p class="empty-note">No tienes materias en progreso registradas.</p>
        <?php else: ?>
        <div class="cards-grid">
            <?php foreach ($enProgreso as $course): ?>
                <?= render_course_card($course, true) ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <section class="home-section home-section-proximas">
        <div class="section-heading">
            <div class="section-heading-left">
                <span class="section-icon section-icon-proximas" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h13"></path><path d="M13 6l6 6-6 6"></path></svg>
                </span>
                <div>
                    <h2>Próximas materias</h2>
                    <p class="section-subtitle">Listas para tomar una vez completes lo que está en curso.</p>
                </div>
            </div>
            <span class="section-count"><?= count($proximas) ?> disponible<?= count($proximas) === 1 ? '' : 's' ?></span>
        </div>
        <?php if (empty($proximas)): ?>
        <p class="empty-note">No hay materias nuevas disponibles todavía.</p>
        <?php else: ?>
        <div class="cards-grid">
            <?php foreach ($proximas as $course): ?>
                <?= render_course_card($course, true) ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</main>

</body>
</html>
