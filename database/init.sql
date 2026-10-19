-- PostgreSQL schema for the client portal.
-- Run once with psql or the Supabase SQL editor. api/setup.php provisions the
-- first administrator separately from the public HTTP API.

CREATE TABLE IF NOT EXISTS clients (
    id SERIAL PRIMARY KEY,
    company VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    ruc VARCHAR(20) DEFAULT '',
    phone VARCHAR(50) DEFAULT '',
    password VARCHAR(255) DEFAULT '',
    since DATE DEFAULT CURRENT_DATE,
    verified BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS projects (
    id SERIAL PRIMARY KEY,
    client_id INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    progress INTEGER DEFAULT 0,
    phase VARCHAR(255) DEFAULT ''
);

CREATE TABLE IF NOT EXISTS milestones (
    id SERIAL PRIMARY KEY,
    project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    position INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS services (
    id SERIAL PRIMARY KEY,
    project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    due_date DATE DEFAULT NULL,
    price VARCHAR(50) DEFAULT ''
);

CREATE TABLE IF NOT EXISTS brand_resources (
    id SERIAL PRIMARY KEY,
    project_id INTEGER UNIQUE NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    palette JSONB DEFAULT '[]',
    logo_url VARCHAR(500) DEFAULT '#',
    brand_manual_url VARCHAR(500) DEFAULT '#',
    repo_url VARCHAR(500) DEFAULT '#'
);

CREATE TABLE IF NOT EXISTS admin_credentials (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) DEFAULT ''
);

CREATE UNIQUE INDEX IF NOT EXISTS clients_email_lower_unique_idx ON clients (LOWER(email));
DROP INDEX IF EXISTS clients_email_lower_idx;
CREATE INDEX IF NOT EXISTS clients_ruc_idx ON clients (ruc);
CREATE INDEX IF NOT EXISTS projects_client_id_idx ON projects (client_id);
CREATE INDEX IF NOT EXISTS milestones_project_id_idx ON milestones (project_id);
CREATE INDEX IF NOT EXISTS services_project_id_idx ON services (project_id);
CREATE UNIQUE INDEX IF NOT EXISTS admin_credentials_email_lower_unique_idx ON admin_credentials (LOWER(email));
DROP INDEX IF EXISTS admin_credentials_email_lower_idx;

-- The PHP API uses its own server-side session and a database owner connection.
-- No direct Data API policies are granted to anon/authenticated users.
ALTER TABLE clients ENABLE ROW LEVEL SECURITY;
ALTER TABLE projects ENABLE ROW LEVEL SECURITY;
ALTER TABLE milestones ENABLE ROW LEVEL SECURITY;
ALTER TABLE services ENABLE ROW LEVEL SECURITY;
ALTER TABLE brand_resources ENABLE ROW LEVEL SECURITY;
ALTER TABLE admin_credentials ENABLE ROW LEVEL SECURITY;

-- A live Supabase audit found this helper callable through the public Data API.
-- Keep owner access while removing execution from public application roles.
DO $$
BEGIN
    IF to_regprocedure('public.rls_auto_enable()') IS NOT NULL THEN
        EXECUTE 'REVOKE EXECUTE ON FUNCTION public.rls_auto_enable() FROM PUBLIC';
        IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
            EXECUTE 'REVOKE EXECUTE ON FUNCTION public.rls_auto_enable() FROM anon';
        END IF;
        IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
            EXECUTE 'REVOKE EXECUTE ON FUNCTION public.rls_auto_enable() FROM authenticated';
        END IF;
    END IF;
END
$$;
