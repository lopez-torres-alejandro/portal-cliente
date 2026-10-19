const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const vm = require("node:vm");

function createApp(fetchImpl) {
  const callbacks = {};
  const document = {
    addEventListener: (name, callback) => { callbacks[name] = callback; },
    querySelector: () => null,
    querySelectorAll: () => [],
    documentElement: { setAttribute() {} },
  };
  const window = { addEventListener() {} };
  const context = vm.createContext({
    document,
    window,
    location: { hash: "", href: "http://localhost/portal-cliente/" },
    localStorage: { getItem: () => null, setItem() {} },
    navigator: {},
    fetch: fetchImpl,
    URL,
    TextEncoder,
    Response,
    setTimeout,
    clearTimeout,
    requestAnimationFrame() {},
    console,
  });
  const source = fs.readFileSync(path.resolve(__dirname, "../js/app.js"), "utf8");
  vm.runInContext(`${source}\nglobalThis.__hooks = { AppData, state, restoreSession, escapeHtml, safeExternalUrl, safeStatus, getActiveClient };`, context);
  return context.__hooks;
}

test("frontend restores a client session using the server-scoped response", async () => {
  const calls = [];
  const app = createApp(async (url) => {
    calls.push(url);
    if (url.endsWith("action=session")) {
      return new Response(JSON.stringify({ role: "client", client_id: 7 }), { status: 200 });
    }
    if (url.endsWith("action=get-all")) {
      return new Response(JSON.stringify({
        dev_brand: { name: "Portal" },
        clients: [{ id: 7, company: "Cliente", email: "cliente@example.com", projects: [] }],
      }), { status: 200 });
    }
    throw new Error(`Unexpected request ${url}`);
  });

  assert.equal(await app.restoreSession(), true);
  assert.equal(app.state.loggedIn, true);
  assert.equal(app.state.isAdmin, false);
  assert.equal(app.state.adminAccess, false);
  assert.equal(app.state.activeClientId, 7);
  assert.equal(app.getActiveClient().email, "cliente@example.com");
  assert.deepEqual(calls, ["api/index.php?action=session", "api/index.php?action=get-all"]);
});

test("frontend escapes content and rejects unsafe external URL schemes", () => {
  const app = createApp(async () => new Response("{}", { status: 200 }));
  assert.equal(app.escapeHtml(`<img src=x onerror='alert(1)'>`), "&lt;img src=x onerror=&#39;alert(1)&#39;&gt;");
  assert.equal(app.safeExternalUrl("javascript:alert(1)"), "#");
  assert.equal(app.safeExternalUrl("https://example.com/resource"), "https://example.com/resource");
  assert.equal(app.safeStatus("unknown"), "pending");
});
