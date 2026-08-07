# Pensum — Ingeniería de Software (UAPA)

App en PHP 8.3 + SQLite, dockerizada, para llevar el seguimiento del pensum:

- **Inicio** (`/`): progreso general, materias en curso y próximas materias disponibles.
- **Pensum** (`/pensum.php`): grilla completa por trimestre/cuatrimestre + optativas. Clic en cualquier materia para cambiar su estado (aprobada, en progreso, planificada, pendiente).

## Requisitos

- Docker Desktop (o Docker Engine + Compose).

## Levantar la app

```bash
git clone https://github.com/SoftwareSpiritual/Uapa.git
cd Uapa
docker compose up -d --build
```

Abre `http://localhost:8090`. Si ese puerto está ocupado, cámbialo en `docker-compose.yml` (`"8090:8000"` → `"OTRO_PUERTO:8000"`).

Para detenerla: `docker compose down`.

## Cómo se guarda el progreso

`database/uapa.sqlite` **está versionado en git** (no es solo data de ejemplo — ahí vive tu progreso real: qué materias marcaste como aprobada/en curso/planificada). Al clonar el repo, la app arranca ya con el progreso tal como quedó en el último push. `data/pensum.json` solo se usa una vez, si `uapa.sqlite` no existe todavía, para crear la base desde cero.

## Trabajar desde varias PCs

El `.sqlite` es un archivo binario: **una sola PC activa a la vez**. Sincroniza con commit/push/pull antes de cambiar de máquina — git no puede fusionar cambios binarios hechos en dos lugares distintos.

**Antes de dejar de trabajar en una PC:**

```bash
git add database/uapa.sqlite
git commit -m "Actualiza progreso"
git push
```

**Al empezar a trabajar en otra PC (o al volver a la misma):**

```bash
git pull
docker compose up -d --build
```

Si olvidas sincronizar y terminas editando el progreso en dos PCs sin hacer pull antes, git marcará un conflicto en `database/uapa.sqlite` que no puede resolver solo. En ese caso hay que elegir manualmente qué versión conservar:

```bash
git checkout --ours database/uapa.sqlite   # te quedas con tu versión local
# o
git checkout --theirs database/uapa.sqlite # te quedas con la versión del remoto
git add database/uapa.sqlite
git commit
```
