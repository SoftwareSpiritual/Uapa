<?php

declare(strict_types=1);

function fetch_all_courses(PDO $pdo, int $carreraId): array
{
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE carrera_id = :carrera_id ORDER BY periodo_orden IS NULL, periodo_orden, id');
    $stmt->execute([':carrera_id' => $carreraId]);
    return $stmt->fetchAll();
}

function get_all_carreras(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM carreras ORDER BY id')->fetchAll();
}

function get_current_carrera(PDO $pdo, array $carreras): array
{
    $slug = $_COOKIE['carrera'] ?? '';
    foreach ($carreras as $carrera) {
        if ($carrera['slug'] === $slug) {
            return $carrera;
        }
    }

    return $carreras[0];
}

function carrera_abbr(string $nombre): string
{
    $words = array_values(array_filter(
        preg_split('/\s+/', $nombre),
        static fn (string $w): bool => mb_strlen($w) > 2
    ));
    $letters = array_map(
        static fn (string $w): string => mb_strtoupper(mb_substr($w, 0, 1)),
        array_slice($words, 0, 2)
    );

    $abbr = implode('', $letters);

    return $abbr !== '' ? $abbr : mb_strtoupper(mb_substr($nombre, 0, 2));
}

function group_by_periodo(array $courses): array
{
    $periods = [];
    $optativas = [];

    foreach ($courses as $course) {
        if ($course['periodo_orden'] === null) {
            $optativas[] = $course;
            continue;
        }

        $orden = (int) $course['periodo_orden'];
        if (!isset($periods[$orden])) {
            $periods[$orden] = [
                'orden' => $orden,
                'nombre' => $course['periodo_nombre'],
                'courses' => [],
            ];
        }
        $periods[$orden]['courses'][] = $course;
    }

    ksort($periods);
    $periods = array_values($periods);

    foreach ($periods as &$period) {
        $period['stats'] = compute_stats($period['courses']);
    }
    unset($period);

    return ['periods' => $periods, 'optativas' => $optativas];
}

function compute_stats(array $courses): array
{
    $stats = [
        'total_cursos' => count($courses),
        'aprobadas' => 0,
        'en_progreso' => 0,
        'planificadas' => 0,
        'pendientes' => 0,
        'creditos_totales' => 0,
        'creditos_aprobados' => 0,
        'creditos_en_progreso' => 0,
        'creditos_planificados' => 0,
        'creditos_pendientes' => 0,
    ];

    foreach ($courses as $course) {
        $creditos = (int) $course['creditos'];
        $stats['creditos_totales'] += $creditos;

        switch ($course['estado']) {
            case 'aprobada':
                $stats['aprobadas']++;
                $stats['creditos_aprobados'] += $creditos;
                break;
            case 'en_progreso':
                $stats['en_progreso']++;
                $stats['creditos_en_progreso'] += $creditos;
                break;
            case 'planificada':
                $stats['planificadas']++;
                $stats['creditos_planificados'] += $creditos;
                break;
            default:
                $stats['pendientes']++;
                $stats['creditos_pendientes'] += $creditos;
                break;
        }
    }

    $total = $stats['creditos_totales'];
    $stats['pct_aprobada'] = $total > 0 ? round(($stats['creditos_aprobados'] / $total) * 100, 1) : 0.0;
    $stats['pct_en_progreso'] = $total > 0 ? round(($stats['creditos_en_progreso'] / $total) * 100, 1) : 0.0;
    $stats['pct_planificada'] = $total > 0 ? round(($stats['creditos_planificados'] / $total) * 100, 1) : 0.0;
    $stats['pct_pendiente'] = $total > 0
        ? max(0.0, round(100 - $stats['pct_aprobada'] - $stats['pct_en_progreso'] - $stats['pct_planificada'], 1))
        : 0.0;
    $stats['porcentaje_completado'] = $stats['pct_aprobada'];

    return $stats;
}

function render_progress_bar(array $stats, bool $animated = false, string $size = 'sm'): string
{
    $pctA = $stats['pct_aprobada'];
    $pctE = $stats['pct_en_progreso'];
    $pctP = $stats['pct_planificada'];
    $pctFilled = min(100.0, $pctA + $pctE + $pctP);

    $wave = '';
    if ($animated) {
        $wave = <<<HTML
        <div class="bar-wave" style="width:{$pctFilled}%">
            <svg class="wave-svg wave-svg-back" viewBox="0 0 2400 40" preserveAspectRatio="none">
                <path class="wave-path" d="M0 22 Q150 8 300 22 T600 22 T900 22 T1200 22 T1500 22 T1800 22 T2100 22 T2400 22 V40 H0 Z"></path>
            </svg>
            <svg class="wave-svg wave-svg-front" viewBox="0 0 2400 40" preserveAspectRatio="none">
                <path class="wave-path" d="M0 20 Q150 2 300 20 T600 20 T900 20 T1200 20 T1500 20 T1800 20 T2100 20 T2400 20 V40 H0 Z"></path>
            </svg>
        </div>
        HTML;
    }

    return <<<HTML
    <div class="progress-tank size-{$size}">
        <div class="tank-track">
            <div class="tank-segment tank-aprobada" style="width:{$pctA}%"></div>
            <div class="tank-segment tank-en_progreso" style="width:{$pctE}%"></div>
            <div class="tank-segment tank-planificada" style="width:{$pctP}%"></div>
        </div>
        {$wave}
    </div>
    HTML;
}

function estado_label(string $estado): string
{
    return match ($estado) {
        'aprobada' => 'Aprobada',
        'en_progreso' => 'En Progreso',
        'planificada' => 'Planificada',
        'pendiente' => 'Pendiente',
        default => ucfirst($estado),
    };
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

const ESTADOS_DISPONIBLES = ['aprobada', 'en_progreso', 'planificada', 'pendiente'];

function render_course_card(array $course, bool $showPeriod = false, bool $editable = false): string
{
    $periodTag = '';
    if ($showPeriod && $course['periodo_nombre']) {
        $periodTag = '<div class="course-period-tag">' . h($course['periodo_nombre']) . '</div>';
    }

    $prereq = $course['prerrequisito']
        ? '<div class="course-prereq">Prerreq: ' . h($course['prerrequisito']) . '</div>'
        : '<div class="course-prereq course-prereq-empty">&nbsp;</div>';

    $estado = h($course['estado']);
    $clave = h($course['clave']);
    $nombre = h($course['nombre']);
    $creditos = (int) $course['creditos'];
    $estadoLabel = estado_label($course['estado']);
    $id = (int) $course['id'];
    $editableAttrs = $editable
        ? ' data-course-id="' . $id . '" data-estado="' . $estado . '" tabindex="0" role="button" aria-haspopup="true"'
        : '';
    $editableClass = $editable ? ' is-editable' : '';

    return <<<HTML
    <article class="course-card estado-{$estado}{$editableClass}"{$editableAttrs}>
        {$periodTag}
        <div class="course-clave">{$clave}</div>
        <div class="course-nombre">{$nombre}</div>
        <div class="course-meta">
            <span class="creditos">{$creditos} créd.</span>
            <span class="estado-badge">{$estadoLabel}</span>
        </div>
        {$prereq}
    </article>
    HTML;
}

function update_course_estado(PDO $pdo, int $id, string $estado): array
{
    if (!in_array($estado, ESTADOS_DISPONIBLES, true)) {
        throw new InvalidArgumentException('Estado inválido');
    }

    $stmt = $pdo->prepare('UPDATE courses SET estado = :estado WHERE id = :id');
    $stmt->execute([':estado' => $estado, ':id' => $id]);

    $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $course = $stmt->fetch();

    if ($course === false) {
        throw new RuntimeException('Materia no encontrada');
    }

    return $course;
}

function render_period_header(string $label, array $stats, string $scope, ?int $index = null): string
{
    $badge = $index !== null ? (string) $index : '&#10022;';
    $title = h($label);
    $pct = $stats['pct_aprobada'];
    $creditosAprobados = $stats['creditos_aprobados'];
    $creditosTotales = $stats['creditos_totales'];
    $bar = render_progress_bar($stats, false, 'sm');

    return <<<HTML
    <header class="period-header" data-scope="{$scope}">
        <div class="period-header-top">
            <div class="period-heading">
                <span class="period-index">{$badge}</span>
                <h2 class="period-title">{$title}</h2>
            </div>
            <span class="period-pct">{$pct}%</span>
        </div>
        {$bar}
        <span class="period-credits">{$creditosAprobados} / {$creditosTotales} créd.</span>
    </header>
    HTML;
}

function courses_by_estado(array $courses, string $estado): array
{
    $filtered = array_values(array_filter($courses, static fn (array $c): bool => $c['estado'] === $estado));

    usort($filtered, static function (array $a, array $b): int {
        return ($a['periodo_orden'] ?? PHP_INT_MAX) <=> ($b['periodo_orden'] ?? PHP_INT_MAX);
    });

    return $filtered;
}

function next_courses(array $courses): array
{
    $byClave = [];
    foreach ($courses as $c) {
        if (!isset($byClave[$c['clave']])) {
            $byClave[$c['clave']] = $c;
        }
    }

    $next = [];
    foreach ($courses as $c) {
        if ($c['estado'] !== 'pendiente' || $c['tipo'] !== 'obligatoria') {
            continue;
        }

        $prereq = $c['prerrequisito'];
        $unlocked = $prereq === null
            || (isset($byClave[$prereq]) && in_array($byClave[$prereq]['estado'], ['aprobada', 'en_progreso'], true));

        if ($unlocked) {
            $next[] = $c;
        }
    }

    usort($next, static function (array $a, array $b): int {
        return ($a['periodo_orden'] ?? PHP_INT_MAX) <=> ($b['periodo_orden'] ?? PHP_INT_MAX);
    });

    return $next;
}

function render_topbar(string $active, array $carrera, array $carreras, string $currentPage): string
{
    $links = [
        'inicio' => ['href' => 'index.php', 'label' => 'Inicio'],
        'pensum' => ['href' => 'pensum.php', 'label' => 'Pensum'],
    ];

    $items = '';
    foreach ($links as $key => $link) {
        $activeClass = $key === $active ? ' active' : '';
        $items .= '<a class="nav-link' . $activeClass . '" href="' . h($link['href']) . '">' . h($link['label']) . '</a>';
    }

    $options = '';
    foreach ($carreras as $c) {
        $selected = (int) $c['id'] === (int) $carrera['id'] ? ' selected' : '';
        $options .= '<option value="' . h($c['slug']) . '"' . $selected . '>' . h($c['nombre']) . '</option>';
    }

    $abbr = h(carrera_abbr($carrera['nombre']));
    $nombre = h($carrera['nombre']);
    $currentPageAttr = h($currentPage);

    return <<<HTML
    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand">
                <span class="brand-mark">{$abbr}</span>
                <div class="brand-text">
                    <span class="brand-title">Pensum &mdash; {$nombre}</span>
                    <span class="brand-subtitle">UAPA &middot; Seguimiento de avance académico</span>
                </div>
            </div>
            <div class="topbar-right">
                <form class="carrera-switcher" method="get" action="switch-carrera.php">
                    <input type="hidden" name="redirect" value="{$currentPageAttr}">
                    <select name="slug" onchange="this.form.submit()" aria-label="Elegir carrera">
                        {$options}
                    </select>
                </form>
                <nav class="topbar-nav">
                    {$items}
                </nav>
            </div>
        </div>
    </header>
    HTML;
}
