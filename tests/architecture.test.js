const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");

const root = path.resolve(__dirname, "..");
const read = (file) => fs.readFileSync(path.join(root, file), "utf8");

test("HTTP routes match the action handlers and use explicit methods", () => {
  const router = read("api/index.php");
  const actions = [...router.matchAll(/'([a-z-]+)'\s*=>\s*\['(GET|POST)',\s*'([A-Za-z]+)'/g)];
  assert.equal(actions.length, 8);
  assert.equal(actions.some(([, action]) => action === "init"), false);

  for (const [, , , handler] of actions) {
    const routeFile = ["auth", "clients", "registration"]
      .map((name) => read(`api/routes/${name}.php`))
      .find((contents) => contents.includes(`function ${handler}(`));
    assert.ok(routeFile, `missing handler ${handler}`);
  }
  assert.match(router, /header\('Allow: ' \. \$method\)/);
});

test("database schema covers the portal tables, RLS, and foreign-key indexes", () => {
  const schema = read("database/init.sql");
  const tables = ["clients", "projects", "milestones", "services", "brand_resources", "admin_credentials"];
  for (const table of tables) {
    assert.match(schema, new RegExp(`CREATE TABLE IF NOT EXISTS ${table}\\b`, "i"));
    assert.match(schema, new RegExp(`ALTER TABLE ${table} ENABLE ROW LEVEL SECURITY`, "i"));
  }
  for (const index of ["projects_client_id_idx", "milestones_project_id_idx", "services_project_id_idx"]) {
    assert.match(schema, new RegExp(`CREATE INDEX IF NOT EXISTS ${index}\\b`, "i"));
  }
  assert.match(schema, /CREATE UNIQUE INDEX IF NOT EXISTS clients_email_lower_unique_idx/i);
});

test("admin setup is CLI-only and blocks public execution of the Supabase helper", () => {
  const setup = read("api/setup.php");
  assert.ok(setup.indexOf("PHP_SAPI !== 'cli'") < setup.indexOf("require_once __DIR__ . '/config.php'"));
  assert.match(setup, /to_regclass\('public\.admin_credentials'\)/);
  assert.match(read("database/init.sql"), /REVOKE EXECUTE ON FUNCTION public\.rls_auto_enable\(\) FROM PUBLIC/);
  assert.match(read("database/init.sql"), /FROM anon/);
  assert.match(read("database/init.sql"), /FROM authenticated/);
});

test("frontend API actions all have a router entry", () => {
  const frontend = read("js/app.js");
  const router = read("api/index.php");
  const actions = [...frontend.matchAll(/api\/index\.php\?action=([a-z-]+)/g)].map(([, action]) => action);
  for (const action of new Set(actions)) {
    assert.match(router, new RegExp(`'${action}'\\s*=>`), `missing API action ${action}`);
  }
});

test("the client response never includes password hashes and the registration module loads its mailer", () => {
  const clients = read("api/routes/clients.php");
  const registration = read("api/routes/registration.php");
  assert.doesNotMatch(clients, /\$c\['password'\]\s*=/);
  assert.match(clients, /\$savedPasswords\[\(string\)\$c\['id'\]\]/);
  assert.match(registration, /dirname\(__DIR__\) \. '\/mailer\.php'/);
});

test("saving a service with no due date writes a null date", () => {
  const clients = read("api/routes/clients.php");
  assert.match(clients, /\$dueDate === '' \? null : \$dueDate/);
  assert.match(clients, /function validateClientsPayload\(/);
});
