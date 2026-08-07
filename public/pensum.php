<?php

declare(strict_types=1);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/functions.php';

$pdo = get_db();
$courses = fetch_all_courses($pdo);
$grouped = group_by_periodo($courses);
$optativasStats = compute_stats($grouped['optativas']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pensum &mdash; Ingeniería de Software</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<?= render_topbar('pensum') ?>

<main class="grid-wrapper">
    <div class="periods-grid">
        <?php foreach ($grouped['periods'] as $period): ?>
        <section class="period-column">
            <?= render_period_header($period['nombre'], $period['stats'], 'period-' . $period['orden'], $period['orden']) ?>
            <?php foreach ($period['courses'] as $course): ?>
                <?= render_course_card($course, false, true) ?>
            <?php endforeach; ?>
        </section>
        <?php endforeach; ?>
    </div>
</main>

<?php if (!empty($grouped['optativas'])): ?>
<section class="optativas-section">
    <?= render_period_header('Optativas', $optativasStats, 'optativas') ?>
    <div class="optativas-grid">
        <?php foreach ($grouped['optativas'] as $course): ?>
            <?= render_course_card($course, false, true) ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<script src="assets/app.js" defer></script>
</body>
</html>
