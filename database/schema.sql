CREATE TABLE IF NOT EXISTS carreras (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    nombre TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS courses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    carrera_id INTEGER NOT NULL REFERENCES carreras(id),
    clave TEXT NOT NULL,
    nombre TEXT NOT NULL,
    periodo_orden INTEGER,
    periodo_nombre TEXT,
    tipo TEXT NOT NULL CHECK (tipo IN ('obligatoria', 'optativa')),
    horas_teoricas INTEGER NOT NULL DEFAULT 0,
    horas_practicas INTEGER NOT NULL DEFAULT 0,
    horas_totales INTEGER NOT NULL DEFAULT 0,
    horas_estudio_independiente INTEGER NOT NULL DEFAULT 0,
    creditos INTEGER NOT NULL DEFAULT 0,
    prerrequisito TEXT,
    estado TEXT NOT NULL CHECK (estado IN ('aprobada', 'en_progreso', 'planificada', 'pendiente'))
);

CREATE INDEX IF NOT EXISTS idx_courses_periodo ON courses (periodo_orden);
CREATE INDEX IF NOT EXISTS idx_courses_clave ON courses (clave);
CREATE INDEX IF NOT EXISTS idx_courses_carrera ON courses (carrera_id);
