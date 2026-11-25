-- Runs once when the database volume is created. The test suite uses its
-- own database so running tests never touches development data.
CREATE DATABASE laundry_test OWNER laundry;
\connect laundry
CREATE EXTENSION IF NOT EXISTS postgis;
\connect laundry_test
CREATE EXTENSION IF NOT EXISTS postgis;
