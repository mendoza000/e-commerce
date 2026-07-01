-- Se ejecuta automáticamente al inicializar el volumen de Postgres (solo la
-- primera vez, con el volumen vacío). Crea la base de datos separada que usa
-- phpunit.xml para correr los tests contra Postgres real (no SQLite) sin
-- pisar los datos de desarrollo de la base "ecommerce".
CREATE DATABASE ecommerce_test OWNER ecommerce;
