(function () {
    'use strict';

    var ESTADOS = [
        { value: 'aprobada', label: 'Aprobada' },
        { value: 'en_progreso', label: 'En progreso' },
        { value: 'planificada', label: 'Planificada' },
        { value: 'pendiente', label: 'Pendiente' },
    ];

    var openMenu = null;

    function closeMenu() {
        if (openMenu) {
            openMenu.remove();
            openMenu = null;
        }
    }

    function openMenuFor(card) {
        closeMenu();

        var menu = document.createElement('div');
        menu.className = 'estado-menu';

        ESTADOS.forEach(function (estado) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'estado-menu-item' + (card.dataset.estado === estado.value ? ' is-current' : '');
            btn.innerHTML = '<span class="dot dot-' + estado.value + '"></span>' + estado.label;
            btn.addEventListener('click', function (ev) {
                ev.stopPropagation();
                setEstado(card, estado.value);
                closeMenu();
            });
            menu.appendChild(btn);
        });

        document.body.appendChild(menu);

        var rect = card.getBoundingClientRect();
        var menuWidth = menu.offsetWidth || 170;
        var left = rect.left + window.scrollX;
        if (left + menuWidth > window.innerWidth - 8) {
            left = window.innerWidth - menuWidth - 8;
        }
        var top = rect.bottom + window.scrollY + 6;
        if (top + menu.offsetHeight > window.scrollY + window.innerHeight) {
            top = rect.top + window.scrollY - menu.offsetHeight - 6;
        }

        menu.style.left = left + 'px';
        menu.style.top = top + 'px';

        openMenu = menu;
    }

    function setEstado(card, estado) {
        if (card.dataset.estado === estado) {
            return;
        }

        card.classList.add('is-updating');

        fetch('api/update-estado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: card.dataset.courseId, estado: estado }),
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    if (!res.ok || !data.ok) {
                        throw new Error(data.error || 'No se pudo actualizar la materia');
                    }
                    return data;
                });
            })
            .then(function (data) {
                applyCourseUpdate(card, data.course);
                applyScopeUpdate(data.scope, data.stats);
            })
            .catch(function (err) {
                alert(err.message);
            })
            .finally(function () {
                card.classList.remove('is-updating');
            });
    }

    function applyCourseUpdate(card, course) {
        ESTADOS.forEach(function (estado) {
            card.classList.remove('estado-' + estado.value);
        });
        card.classList.add('estado-' + course.estado);
        card.dataset.estado = course.estado;

        var badge = card.querySelector('.estado-badge');
        if (badge) {
            badge.textContent = course.estado_label;
        }
    }

    function applyScopeUpdate(scope, stats) {
        var header = document.querySelector('[data-scope="' + scope + '"]');
        if (!header) {
            return;
        }

        var pctEl = header.querySelector('.period-pct');
        if (pctEl) {
            pctEl.textContent = stats.pct_aprobada + '%';
        }

        var creditsEl = header.querySelector('.period-credits');
        if (creditsEl) {
            creditsEl.textContent = stats.creditos_aprobados + ' / ' + stats.creditos_totales + ' créd.';
        }

        var segA = header.querySelector('.tank-aprobada');
        var segE = header.querySelector('.tank-en_progreso');
        var segP = header.querySelector('.tank-planificada');
        if (segA) segA.style.width = stats.pct_aprobada + '%';
        if (segE) segE.style.width = stats.pct_en_progreso + '%';
        if (segP) segP.style.width = stats.pct_planificada + '%';

        var wave = header.querySelector('.bar-wave');
        if (wave) {
            var pctFilled = Math.min(100, stats.pct_aprobada + stats.pct_en_progreso + stats.pct_planificada);
            wave.style.width = pctFilled + '%';
        }
    }

    document.addEventListener('click', function (ev) {
        var card = ev.target.closest('.course-card.is-editable');
        if (card) {
            ev.stopPropagation();
            openMenuFor(card);
            return;
        }
        if (!ev.target.closest('.estado-menu')) {
            closeMenu();
        }

        var openCarrera = document.querySelector('.carrera-switcher[open]');
        if (openCarrera && !ev.target.closest('.carrera-switcher')) {
            openCarrera.removeAttribute('open');
        }
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') {
            closeMenu();
            var openCarrera = document.querySelector('.carrera-switcher[open]');
            if (openCarrera) {
                openCarrera.removeAttribute('open');
            }
            return;
        }
        if (ev.key === 'Enter' || ev.key === ' ') {
            var card = ev.target.closest && ev.target.closest('.course-card.is-editable');
            if (card) {
                ev.preventDefault();
                openMenuFor(card);
            }
        }
    });
})();
