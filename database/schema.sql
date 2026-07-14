-- =========================================================
-- SISTEMA DE JUICIOS EVALUATIVOS - SENA
-- Script de base de datos completo
-- Ejecutar en: juicios_evaluativos (phpMyAdmin)
-- =========================================================

CREATE DATABASE IF NOT EXISTS juicios_evaluativos
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE juicios_evaluativos;

-- -------- FICHA --------
CREATE TABLE IF NOT EXISTS ficha (
  id_ficha   INT AUTO_INCREMENT PRIMARY KEY,
  nombre     VARCHAR(255) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------- APRENDIZ --------
CREATE TABLE IF NOT EXISTS aprendiz (
  id_aprendiz      INT AUTO_INCREMENT PRIMARY KEY,
  numero_documento VARCHAR(50)  NOT NULL UNIQUE,
  tipo_documento   VARCHAR(20)  NOT NULL DEFAULT 'CC'
                   COMMENT 'CC, TI, CE, PA, etc.',
  nombre           VARCHAR(255) NOT NULL,
  apellido         VARCHAR(255) NOT NULL,
  estado           ENUM('EN_FORMACION','RETIRADO','TRASLADADO') NOT NULL DEFAULT 'EN_FORMACION',
  id_ficha         INT NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_aprendiz_ficha FOREIGN KEY (id_ficha) REFERENCES ficha(id_ficha) ON DELETE SET NULL
);

-- -------- COMPETENCIA --------
CREATE TABLE IF NOT EXISTS competencia (
  id_competencia INT AUTO_INCREMENT PRIMARY KEY,
  nombre         VARCHAR(500) NOT NULL,
  codigo         VARCHAR(50)  NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------- RESULTADO DE APRENDIZAJE --------
CREATE TABLE IF NOT EXISTS resultado (
  id_resultado   INT AUTO_INCREMENT PRIMARY KEY,
  nombre         VARCHAR(500) NOT NULL,
  id_competencia INT NOT NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_resultado_competencia FOREIGN KEY (id_competencia) REFERENCES competencia(id_competencia) ON DELETE CASCADE
);

-- -------- FUNCIONARIO --------
CREATE TABLE IF NOT EXISTS funcionario (
  id_funcionario INT AUTO_INCREMENT PRIMARY KEY,
  nombre         VARCHAR(255) NOT NULL,
  email          VARCHAR(255) NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------- JUICIO EVALUATIVO --------
CREATE TABLE IF NOT EXISTS juicio_evaluacion (
  id_juicio      INT AUTO_INCREMENT PRIMARY KEY,
  id_aprendiz    INT NOT NULL,
  id_resultado   INT NULL,
  id_funcionario INT NULL,
  tipo           ENUM('APROBADO','POR_EVALUAR') NOT NULL DEFAULT 'POR_EVALUAR',
  fecha          DATETIME DEFAULT CURRENT_TIMESTAMP,
  observacion    TEXT NULL,
  CONSTRAINT fk_juicio_aprendiz    FOREIGN KEY (id_aprendiz)    REFERENCES aprendiz(id_aprendiz)    ON DELETE CASCADE,
  CONSTRAINT fk_juicio_resultado   FOREIGN KEY (id_resultado)   REFERENCES resultado(id_resultado)  ON DELETE SET NULL,
  CONSTRAINT fk_juicio_funcionario FOREIGN KEY (id_funcionario) REFERENCES funcionario(id_funcionario) ON DELETE SET NULL
);

-- -------- PROYECTO FORMATIVO: FASES --------
CREATE TABLE IF NOT EXISTS fase_proyecto (
  id_fase     INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(255) NOT NULL,
  descripcion TEXT NULL,
  orden       INT NOT NULL DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------- PROYECTO FORMATIVO: ACTIVIDADES --------
CREATE TABLE IF NOT EXISTS actividad_proyecto (
  id_actividad INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(500) NOT NULL,
  descripcion  TEXT NULL,
  id_fase      INT NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_actividad_fase FOREIGN KEY (id_fase) REFERENCES fase_proyecto(id_fase) ON DELETE CASCADE
);

-- -------- RELACIÓN ACTIVIDAD - COMPETENCIA --------
CREATE TABLE IF NOT EXISTS actividad_competencia (
  id_actividad   INT NOT NULL,
  id_competencia INT NOT NULL,
  PRIMARY KEY (id_actividad, id_competencia),
  CONSTRAINT fk_ac_actividad   FOREIGN KEY (id_actividad)   REFERENCES actividad_proyecto(id_actividad) ON DELETE CASCADE,
  CONSTRAINT fk_ac_competencia FOREIGN KEY (id_competencia) REFERENCES competencia(id_competencia)      ON DELETE CASCADE
);

-- -------- RELACIÓN ACTIVIDAD - RESULTADO --------
CREATE TABLE IF NOT EXISTS actividad_resultado (
  id_actividad INT NOT NULL,
  id_resultado INT NOT NULL,
  PRIMARY KEY (id_actividad, id_resultado),
  CONSTRAINT fk_ar_actividad FOREIGN KEY (id_actividad) REFERENCES actividad_proyecto(id_actividad) ON DELETE CASCADE,
  CONSTRAINT fk_ar_resultado FOREIGN KEY (id_resultado) REFERENCES resultado(id_resultado)          ON DELETE CASCADE
);

-- =========================================================
-- ÍNDICES PARA RENDIMIENTO
-- =========================================================
CREATE INDEX IF NOT EXISTS idx_aprendiz_doc    ON aprendiz(numero_documento);
CREATE INDEX IF NOT EXISTS idx_aprendiz_estado ON aprendiz(estado);
CREATE INDEX IF NOT EXISTS idx_aprendiz_ficha  ON aprendiz(id_ficha);
CREATE INDEX IF NOT EXISTS idx_juicio_aprendiz ON juicio_evaluacion(id_aprendiz);
CREATE INDEX IF NOT EXISTS idx_juicio_tipo     ON juicio_evaluacion(tipo);
CREATE INDEX IF NOT EXISTS idx_juicio_fecha    ON juicio_evaluacion(fecha);
CREATE INDEX IF NOT EXISTS idx_resultado_comp  ON resultado(id_competencia);

-- =========================================================
-- DATOS DE PRUEBA (OPCIONAL — comentar si ya tienes datos)
-- =========================================================

-- Fichas de ejemplo
INSERT IGNORE INTO ficha (nombre) VALUES
  ('2889732 - Tecnología en Análisis y Desarrollo de Software'),
  ('2945621 - Técnico en Programación de Software'),
  ('3012455 - Tecnología en Gestión Empresarial');

-- Funcionarios de ejemplo
INSERT IGNORE INTO funcionario (nombre, email) VALUES
  ('Carlos Hernández', 'c.hernandez@sena.edu.co'),
  ('María López', 'm.lopez@sena.edu.co'),
  ('Juan Pérez', 'j.perez@sena.edu.co');

-- Competencias y resultados de ejemplo (ADSO)
INSERT IGNORE INTO competencia (nombre, codigo) VALUES
  ('Aplicar el proceso de desarrollo de software en la construcción de soluciones informáticas', 'C220101501'),
  ('Construir bases de datos de acuerdo con el diseño y las necesidades del negocio', 'C220501001'),
  ('Aplicar las técnicas de programación en el desarrollo de soluciones informáticas', 'C220501028');

-- Resultados de aprendizaje
INSERT IGNORE INTO resultado (nombre, id_competencia) VALUES
  ('Analizar los requerimientos del cliente y del negocio', 1),
  ('Diseñar soluciones de software conforme a estándares', 1),
  ('Construir el modelo entidad-relación de la base de datos', 2),
  ('Implementar la base de datos según el modelo diseñado', 2),
  ('Codificar algoritmos usando estructuras de control', 3),
  ('Desarrollar interfaces de usuario para el software', 3);

-- =========================================================
-- VISTAS ÚTILES
-- =========================================================
CREATE OR REPLACE VIEW v_avance_aprendiz AS
SELECT 
  a.id_aprendiz,
  a.numero_documento,
  CONCAT(a.nombre, ' ', a.apellido) AS nombre_completo,
  a.estado,
  a.id_ficha,
  f.nombre AS ficha,
  COUNT(j.id_juicio) AS total_juicios,
  SUM(CASE WHEN j.tipo = 'APROBADO' THEN 1 ELSE 0 END) AS aprobados,
  SUM(CASE WHEN j.tipo = 'POR_EVALUAR' THEN 1 ELSE 0 END) AS por_evaluar,
  ROUND(
    CASE WHEN COUNT(j.id_juicio) > 0
    THEN (SUM(CASE WHEN j.tipo = 'APROBADO' THEN 1 ELSE 0 END) * 100.0) / COUNT(j.id_juicio)
    ELSE 0 END, 2
  ) AS porcentaje_avance
FROM aprendiz a
LEFT JOIN ficha f ON a.id_ficha = f.id_ficha
LEFT JOIN juicio_evaluacion j ON a.id_aprendiz = j.id_aprendiz
GROUP BY a.id_aprendiz;

CREATE OR REPLACE VIEW v_aprobacion_competencia AS
SELECT 
  c.id_competencia,
  c.nombre AS competencia,
  COUNT(j.id_juicio) AS total_juicios,
  SUM(CASE WHEN j.tipo = 'APROBADO' THEN 1 ELSE 0 END) AS aprobados,
  ROUND(
    CASE WHEN COUNT(j.id_juicio) > 0
    THEN (SUM(CASE WHEN j.tipo = 'APROBADO' THEN 1 ELSE 0 END) * 100.0) / COUNT(j.id_juicio)
    ELSE 0 END, 2
  ) AS porcentaje_aprobacion,
  a.id_ficha
FROM competencia c
LEFT JOIN resultado r ON c.id_competencia = r.id_competencia
LEFT JOIN juicio_evaluacion j ON r.id_resultado = j.id_resultado
LEFT JOIN aprendiz a ON j.id_aprendiz = a.id_aprendiz
GROUP BY c.id_competencia, a.id_ficha;
