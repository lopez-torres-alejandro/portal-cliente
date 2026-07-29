/**
 * Portal del Cliente + Admin Dashboard — Alejandro Lopez
 * SPA completa con modo administrador y multi-cliente.
 */

/* ============================================================
 * 1. DATOS DE LA APLICACIÓN
 * ============================================================ */
const AppData = {
  devBrand: {
    name: "Alejandro Lopez",
    tagline: "Desarrollo Web & Tecnología"
  },
  adminCredentials: { email: "admin@developer.com" },
  clientCredentials: {
    identifier: "contacto@virgomotosport.com",
    password: "virmoto2026"
  },
  clients: []
};

/* ============================================================
 * 1.5. PERSISTENCIA (localStorage + API)
 * ============================================================ */
function persistData() {
  fetch("api/index.php?action=save-clients", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ clients: AppData.clients })
  }).catch(() => {});
}

/* ============================================================
 * 1.6. CARGA ASÍNCRONA DESDE API
 * ============================================================ */
async function loadAppData() {
  try {
    const res = await fetch("api/index.php?action=get-all");
    if (res.ok) {
      const data = await res.json();
      if (data.clients) AppData.clients = data.clients;
      if (data.dev_brand) Object.assign(AppData.devBrand, data.dev_brand);
      if (data.admin_credentials) Object.assign(AppData.adminCredentials, data.admin_credentials);
      if (data.client_credentials) Object.assign(AppData.clientCredentials, data.client_credentials);
    }
  } catch (_) {}
}

/* ============================================================
 * 2. ESTADO GLOBAL (MUTABLE)
 * ============================================================ */
const state = {
  theme: localStorage.getItem("nc-theme") || "dark",
  loggedIn: sessionStorage.getItem("nc-logged") === "true",
  isAdmin: sessionStorage.getItem("nc-admin") === "true",
  view: "home",
  activeClientId: parseInt(sessionStorage.getItem("nc-client")) || 1,
  editClientId: null,
  editProjectId: null,
  editPanelOpen: false,
  toastTimer: null,
  regEmail: null       // Email pendiente de verificación (registro)
};

/* ============================================================
 * 3. UTILIDADES
 * ============================================================ */
const $ = (sel, ctx) => (ctx || document).querySelector(sel);
const $$ = (sel, ctx) => [...(ctx || document).querySelectorAll(sel)];

function copyToClipboard(text) {
  navigator.clipboard.writeText(text)
    .then(() => showToast("Copiado", `Color ${text} copiado al portapapeles`));
}

function showToast(title, message, icon = "check_circle") {
  const toast = $("#toast");
  if (!toast) return;
  $("#toast-title").textContent = title;
  $("#toast-message").textContent = message;
  $("#toast-icon").textContent = icon;
  toast.classList.add("visible");
  clearTimeout(state.toastTimer);
  state.toastTimer = setTimeout(() => toast.classList.remove("visible"), 3000);
}

function closeToast() {
  const toast = $("#toast");
  if (toast) toast.classList.remove("visible");
  clearTimeout(state.toastTimer);
}

function getClient(id) {
  return AppData.clients.find(c => c.id === id);
}

function getActiveClient() {
  return getClient(state.activeClientId);
}

function updateClient(id, updates) {
  const idx = AppData.clients.findIndex(c => c.id === id);
  if (idx === -1) return;
  AppData.clients[idx] = { ...AppData.clients[idx], ...updates };
}

function serviceStatusValue(status) {
  const map = { active: 2, "in-progress": 1, "about-to-expire": 0, pending: 0, completed: 2, suspended: -1 };
  return map[status] ?? 0;
}

/* ============================================================
 * 4. TEMA
 * ============================================================ */
function setTheme(theme) {
  state.theme = theme;
  document.documentElement.setAttribute("data-theme", theme);
  localStorage.setItem("nc-theme", theme);
  $$("#theme-icon").forEach(el => el.textContent = theme === "dark" ? "dark_mode" : "light_mode");
}

function toggleTheme() {
  setTheme(state.theme === "dark" ? "light" : "dark");
}

/* ============================================================
 * 5. COMPONENTES REUTILIZABLES
 * ============================================================ */
const C = {
  statusBadge(status) {
    const labels = {
      active: "Activo", "in-progress": "En Proceso", "about-to-expire": "Por Vencer",
      pending: "Pendiente", completed: "Completado", suspended: "Suspendido"
    };
    return `<span class="badge badge--${status}">${labels[status] || status}</span>`;
  },

  card(title, content, cls = "") {
    return `<div class="card ${cls}"><h3 class="card__title">${title}</h3><div class="card__body">${content}</div></div>`;
  },

  circularProgress(percent, size = 120) {
    const r = size / 2 - 8;
    const c = 2 * Math.PI * r;
    const off = c - (percent / 100) * c;
    return `<div class="circular-progress" style="width:${size}px;height:${size}px">
      <svg viewBox="0 0 ${size} ${size}">
        <circle cx="${size/2}" cy="${size/2}" r="${r}" class="circular-progress__bg"/>
        <circle cx="${size/2}" cy="${size/2}" r="${r}" class="circular-progress__fg"
          stroke-dasharray="${c}" stroke-dashoffset="${off}"/>
      </svg><span class="circular-progress__text">${percent}%</span></div>`;
  },

  milestoneList(ms) {
    const icons = { completed: "check_circle", "in-progress": "pending", pending: "radio_button_unchecked" };
    const labels = { completed: "Completado", "in-progress": "En Proceso", pending: "Pendiente" };
    return `<ul class="milestone-list">${ms.map(m => `
      <li class="milestone milestone--${m.status}">
        <span class="milestone__icon material-icons">${icons[m.status]}</span>
        <span class="milestone__title">${m.title}</span>
        <span class="milestone__tag">${labels[m.status]}</span>
      </li>`).join("")}</ul>`;
  },

  serviceTable(svcs) {
    return `<div class="table-responsive"><table class="service-table">
      <thead><tr><th>Servicio</th><th>Estado</th><th>Vencimiento</th><th>Precio</th></tr></thead>
      <tbody>${svcs.map(s => `<tr>
        <td class="service-table__name">${s.name}</td>
        <td>${C.statusBadge(s.status)}</td>
        <td>${s.dueDate}</td>
        <td>${s.price}</td>
      </tr>`).join("")}</tbody></table></div>`;
  },

  colorPalette(pal) {
    return `<div class="palette-grid">${pal.map(c => `
      <button class="palette-swatch" style="background:${c.hex}" data-color="${c.hex}"
        onclick="copyToClipboard('${c.hex}')" title="Copiar ${c.hex}">
        <span class="palette-swatch__code">${c.hex}</span>
        <span class="palette-swatch__name">${c.name}</span>
      </button>`).join("")}</div>`;
  },

  downloadBtn(label, url, icon = "download") {
    return `<a href="${url}" class="btn btn--outline" target="_blank" rel="noopener">
      <span class="material-icons">${icon}</span> ${label}</a>`;
  },

  /* --- Sidebar (dinámica: admin vs cliente) --- */
  sidebar() {
    const isAdmin = state.isAdmin;
    const links = isAdmin ? [
      { id: "admin", icon: "dashboard", label: "Panel General" },
      { id: "admin-clients", icon: "group", label: "Gestión de Clientes" }
    ] : [
      { id: "home", icon: "home", label: "Inicio" },
      { id: "services", icon: "build", label: "Servicios" },
      { id: "brand", icon: "palette", label: "Identidad" },
      { id: "support", icon: "headset_mic", label: "Soporte" }
    ];

    const active = state.view;
    const brandLabel = isAdmin ? "Panel Admin" : "Portal del Cliente";

    return `<aside class="sidebar" id="sidebar">
      <div class="sidebar__header">
        <div class="sidebar__brand">
          <div class="sidebar__logo">A</div>
          <div class="sidebar__brand-text">
            <span class="sidebar__brand-name">Alejandro Lopez</span>
            <span class="sidebar__brand-tag">${brandLabel}</span>
          </div>
        </div>
        <button class="sidebar__close" id="sidebar-close" aria-label="Cerrar">
          <span class="material-icons">close</span>
        </button>
      </div>
      <nav class="sidebar__nav">${links.map(n => `
        <a href="#${n.id}" class="sidebar__link ${active === n.id ? "sidebar__link--active" : ""}">
          <span class="material-icons">${n.icon}</span><span>${n.label}</span>
        </a>`).join("")}</nav>
      <div class="sidebar__footer">
        <button class="btn btn--ghost sidebar__toggle-mode" onclick="toggleMode()">
          <span class="material-icons">${isAdmin ? "person" : "admin_panel_settings"}</span>
          <span>${isAdmin ? "Ver como Cliente" : "Panel Admin"}</span>
        </button>
        <button class="btn btn--ghost sidebar__theme-btn" onclick="toggleTheme()">
          <span class="material-icons" id="theme-icon">${state.theme === "dark" ? "dark_mode" : "light_mode"}</span>
          <span>${state.theme === "dark" ? "Oscuro" : "Claro"}</span>
        </button>
        <button class="btn btn--ghost sidebar__logout" onclick="logout()">
          <span class="material-icons">logout</span><span>Cerrar sesión</span>
        </button>
      </div>
    </aside>`;
  },

  bottomNav() {
    const isAdmin = state.isAdmin;
    const links = isAdmin ? [
      { id: "admin", icon: "dashboard", label: "Panel" },
      { id: "admin-clients", icon: "group", label: "Clientes" }
    ] : [
      { id: "home", icon: "home", label: "Inicio" },
      { id: "services", icon: "build", label: "Servicios" },
      { id: "brand", icon: "palette", label: "Marca" },
      { id: "support", icon: "headset_mic", label: "Soporte" }
    ];
    return `<nav class="bottom-nav">${links.map(n => `
      <a href="#${n.id}" class="bottom-nav__link ${state.view === n.id ? "bottom-nav__link--active" : ""}">
        <span class="material-icons">${n.icon}</span>
        <span class="bottom-nav__label">${n.label}</span>
      </a>`).join("")}
      <button class="bottom-nav__link bottom-nav__toggle-mode" onclick="toggleMode()" title="Cambiar modo">
        <span class="material-icons">${isAdmin ? "person" : "admin_panel_settings"}</span>
        <span class="bottom-nav__label">${isAdmin ? "Cliente" : "Admin"}</span>
      </button>
    </nav>`;
  },

  /* ============================================================
   * VISTAS — CLIENTE
   * ============================================================ */
  loginView() {
    return `<div class="login" id="login-view">
      <div class="login__card">
        <div class="login__brand">
          <div class="login__logo">A</div>
          <h1 class="login__title">Alejandro Lopez</h1>
          <p class="login__subtitle">Desarrollo Web & Tecnología</p>
        </div>
        <form class="login__form" id="login-form" autocomplete="off">
          <div class="form-group">
            <label class="form-label">Correo o RUC</label>
            <input class="form-input" type="text" id="login-identifier" placeholder="correo@ejemplo.com" required autofocus>
          </div>
          <div class="form-group">
            <label class="form-label">Contraseña</label>
            <input class="form-input" type="password" id="login-password" placeholder="••••••••" required>
          </div>
          <button class="btn btn--primary btn--full" type="submit">Iniciar Sesión</button>
          <button class="btn btn--outline btn--full" type="button" onclick="showRegister()" style="margin-top:.5rem">
            Crear cuenta nueva
          </button>
          <p class="login__hint">
            Demo cliente: <strong>contacto@virgomotosport.com</strong> / <strong>virmoto2026</strong><br>
            Demo admin: <strong>admin@developer.com</strong> / (cualquier contraseña)
          </p>
        </form>
      </div>
    </div>`;
  },

  registerView() {
    return `<div class="register" id="register-view">
      <div class="register__card">
        <h2 class="register__title">Crear cuenta</h2>
        <p class="register__subtitle">Regístrate para acceder a tu portal de proyectos</p>
        <div class="register__error" id="register-error"></div>
        <form class="register__form" id="register-form" autocomplete="off">
          <div class="form-group">
            <label class="form-label">Empresa / Negocio *</label>
            <input class="form-input" type="text" id="reg-company" placeholder="Tu empresa" required>
          </div>
          <div class="form-group">
            <label class="form-label">Correo electrónico *</label>
            <input class="form-input" type="email" id="reg-email" placeholder="correo@ejemplo.com" required>
          </div>
          <div class="form-group">
            <label class="form-label">Teléfono</label>
            <input class="form-input" type="tel" id="reg-phone" placeholder="+51 999 999 999">
          </div>
          <div class="form-group">
            <label class="form-label">RUC</label>
            <input class="form-input" type="text" id="reg-ruc" placeholder="20XXXXXXXXX">
          </div>
          <div class="form-group">
            <label class="form-label">Contraseña *</label>
            <input class="form-input" type="password" id="reg-password" placeholder="Mín. 8 caracteres, 1 mayúscula, 1 número" required minlength="8">
          </div>
          <div class="form-group">
            <label class="form-label">Confirmar contraseña *</label>
            <input class="form-input" type="password" id="reg-confirm" placeholder="Repite la contraseña" required>
          </div>
          <button class="btn btn--primary btn--full" type="submit">Crear cuenta</button>
        </form>
        <span class="register__login-link" onclick="showLogin()">¿Ya tienes cuenta? Inicia sesión</span>
      </div>
    </div>`;
  },

  verifyView(email) {
    return `<div class="register" id="register-view">
      <div class="register__card">
        <h2 class="register__title">Verifica tu correo</h2>
        <p class="register__subtitle">Te enviamos un código de 6 dígitos a <strong>${email}</strong></p>
        <div class="register__error" id="register-error"></div>
        <form class="register__form" id="verify-form" autocomplete="off">
          <div class="form-group">
            <label class="form-label">Código de verificación</label>
            <input class="form-input verify__code-input" type="text" id="verify-code" placeholder="000000" maxlength="6" inputmode="numeric" pattern="[0-9]*" required autofocus>
          </div>
          <button class="btn btn--primary btn--full" type="submit">Verificar cuenta</button>
        </form>
        <div class="verify__resend">
          ¿No recibiste el código? <a onclick="resendCode()">Reenviar</a>
        </div>
        <span class="register__login-link" onclick="showLogin()">Volver a inicio de sesión</span>
      </div>
    </div>`;
  },

  homeView() {
    const cl = getActiveClient();
    if (!cl) return "<p>Cliente no encontrado</p>";
    const projects = cl.projects.filter(p => p.progress > 0);

    return `<div class="view home-view">
      <header class="view__header">
        <h2 class="view__title">¡Hola, ${cl.company}!</h2>
        <p class="view__subtitle">Progreso de tus proyectos</p>
        <button class="btn btn--icon sidebar-toggle" aria-label="Menú"><span class="material-icons">menu</span></button>
      </header>
      ${projects.length === 0 ? `
        <div class="empty-state">
          <span class="material-icons empty-state__icon">task_alt</span>
          <p class="empty-state__title">Sin proyectos pendientes</p>
          <p class="empty-state__desc">Todos tus proyectos están al día. Te notificaremos cuando tengamos novedades.</p>
        </div>` : `
        <div class="projects-list">${projects.map(p => C.projectCard(p)).join("")}</div>`}
    </div>`;
  },

  projectCard(p) {
    const icons = { completed: "check_circle", "in-progress": "pending", pending: "radio_button_unchecked" };
    const labels = { completed: "Completado", "in-progress": "En Proceso", pending: "Pendiente" };
    const msList = p.milestones.map(m => `
      <li class="milestone milestone--${m.status}">
        <span class="milestone__icon material-icons">${icons[m.status]}</span>
        <span class="milestone__title">${m.title}</span>
        <span class="milestone__tag">${labels[m.status]}</span>
      </li>`).join("");

    return `<div class="project-card">
      <div class="project-card__main">
        <div class="project-card__progress">${C.circularProgress(p.progress, 80)}</div>
        <div class="project-card__info">
          <h4 class="project-card__name">${p.name}</h4>
          <p class="project-card__phase"><span class="material-icons">flag</span> ${p.phase}</p>
          <div class="progress-bar"><div class="progress-bar__fill" style="width:${p.progress}%"></div></div>
          <span class="progress-bar__label">${p.progress}% completado</span>
        </div>
        <button class="project-card__toggle" onclick="toggleMilestones(this)" aria-label="Ver hitos">
          <span class="material-icons">expand_more</span>
        </button>
      </div>
      <div class="project-card__milestones">
        <ul class="milestone-list">${msList}</ul>
      </div>
    </div>`;
  },

  servicesView() {
    const cl = getActiveClient();
    if (!cl) return "<p>Cliente no encontrado</p>";
    const allSvcs = cl.projects.flatMap(p =>
      p.services.map(s => ({ ...s, projectName: p.name, projectId: p.id }))
    );
    return `<div class="view services-view">
      <header class="view__header">
        <h2 class="view__title">Mis Servicios</h2>
        <p class="view__subtitle">Servicios contratados por proyecto</p>
        <button class="btn btn--icon sidebar-toggle" aria-label="Menú"><span class="material-icons">menu</span></button>
      </header>
      <div class="services-summary">
        <div class="stat-card"><span class="stat-card__value">${allSvcs.length}</span><span class="stat-card__label">Total</span></div>
        <div class="stat-card"><span class="stat-card__value">${allSvcs.filter(s => s.status === "active").length}</span><span class="stat-card__label">Activos</span></div>
        <div class="stat-card"><span class="stat-card__value">${allSvcs.filter(s => s.status === "in-progress").length}</span><span class="stat-card__label">En Proceso</span></div>
        <div class="stat-card stat-card--warn"><span class="stat-card__value">${allSvcs.filter(s => s.status === "about-to-expire").length}</span><span class="stat-card__label">Por Vencer</span></div>
      </div>
      ${cl.projects.filter(p => p.services.length > 0).map(p => `
        <div class="project-services-group">
          <h4 class="project-services-group__title">
            <span class="material-icons">folder</span> ${p.name}
          </h4>
          ${C.serviceTable(p.services)}
        </div>`).join("")}
      ${allSvcs.length === 0 ? '<p style="text-align:center;color:var(--text-muted);padding:2rem">No tienes servicios contratados.</p>' : ""}
    </div>`;
  },

  brandView() {
    const cl = getActiveClient();
    if (!cl) return "<p>Cliente no encontrado</p>";
    return `<div class="view brand-view">
      <header class="view__header">
        <h2 class="view__title">Identidad de Marca</h2>
        <p class="view__subtitle">Recursos visuales por proyecto — ${cl.company}</p>
        <button class="btn btn--icon sidebar-toggle" aria-label="Menú"><span class="material-icons">menu</span></button>
      </header>
      ${cl.projects.filter(p => p.brand).map(p => `
        <div class="brand-project-group">
          <h3 class="brand-project-group__title">
            <span class="material-icons">folder</span> ${p.name}
          </h3>
          ${C.card("Paleta de Colores", C.colorPalette(p.brand.palette))}
          <div class="brand-grid" style="margin-top:1rem">
            ${C.card("Recursos", `
              <div class="resource-list">
                ${C.downloadBtn("Logotipo PNG", p.brand.logoUrl, "image")}
                ${C.downloadBtn("Manual de Marca (PDF)", p.brand.brandManualUrl, "picture_as_pdf")}
                ${C.downloadBtn("Repositorio", p.brand.repoUrl, "code")}
              </div>`)}
            ${C.card("Tipografía", `
              <div class="typography-info">
                <div class="font-sample font-sample--primary">
                  <span class="font-sample__label">Fuente Principal</span>
                  <span class="font-sample__preview" style="font-family:Montserrat,sans-serif">Montserrat</span>
                  <span class="font-sample__text">ABCDEFGHIJKLMNOPQRSTUVWXYZ</span>
                </div>
                <div class="font-sample">
                  <span class="font-sample__label">Fuente Secundaria</span>
                  <span class="font-sample__preview" style="font-family:'Roboto Condensed',sans-serif">Roboto Condensed</span>
                  <span class="font-sample__text">ABCDEFGHIJKLMNOPQRSTUVWXYZ</span>
                </div>
              </div>`)}
          </div>
        </div>`).join("")}
    </div>`;
  },

  supportView() {
    const cl = getActiveClient();
    const dev = AppData.devBrand;
    return `<div class="view support-view">
      <header class="view__header">
        <h2 class="view__title">Soporte Técnico</h2>
        <p class="view__subtitle">Estamos aquí para ayudarte</p>
        <button class="btn btn--icon sidebar-toggle" aria-label="Menú"><span class="material-icons">menu</span></button>
      </header>
      <div class="support-grid">
        ${C.card("Contacto Directo", `
          <p class="support__intro">¿Necesitas ayuda con tu proyecto? Escríbenos.</p>
          <div class="support__actions">
            <a href="https://wa.me/51999000000?text=Soporte%20${encodeURIComponent(cl?.company || '')}" target="_blank" rel="noopener" class="btn btn--whatsapp btn--full">
              <span class="material-icons">chat</span> WhatsApp Técnico
            </a>
            <a href="mailto:soporte@alejandrolopez.dev" class="btn btn--outline btn--full">
              <span class="material-icons">email</span> soporte@alejandrolopez.dev
            </a>
          </div>`)}
        ${C.card("Horarios", `
          <div class="support__info">
            <div class="support__info-row"><span class="material-icons">schedule</span><span>Lun - Vie: 9:00 AM - 6:00 PM</span></div>
            <div class="support__info-row"><span class="material-icons">speed</span><span>Respuesta: Máximo 2 horas hábiles</span></div>
            <div class="support__info-row"><span class="material-icons">business</span><span>${dev.name}</span></div>
          </div>`)}
        ${cl ? C.card("Tus Proyectos", `
          <div class="support__project">
            <p><strong>${cl.company}</strong></p>
            ${cl.projects.map(p => `<p>• ${p.name} <span class="support__phase">(${p.phase})</span></p>`).join("")}
            <p>Contacto: ${cl.email}</p>
          </div>`) : ""}
      </div>
    </div>`;
  },

  /* ============================================================
   * VISTAS — ADMIN
   * ============================================================ */

  /* --- Overview: métricas rápidas --- */
  adminOverview() {
    const clients = AppData.clients;
    const total = clients.length;
    const inProgress = clients.reduce((count, c) => {
      return count + c.projects.filter(p => p.progress > 0 && p.progress < 100).length;
    }, 0);
    const mrr = clients.reduce((sum, c) => {
      const allSvcs = c.projects.flatMap(p => p.services);
      const monthly = allSvcs
        .filter(s => s.status === "active" || s.status === "in-progress")
        .reduce((acc, s) => {
          const p = s.price.replace(/[^0-9.]/g, "");
          const n = parseFloat(p) || 0;
          if (s.price.includes("/mes")) return acc + n;
          if (s.price.includes("/año")) return acc + n / 12;
          return acc + n / (s.status === "in-progress" ? 6 : 1);
        }, 0);
      return sum + monthly;
    }, 0);
    const soon = clients.reduce((arr, c) => {
      c.projects.forEach(p => {
        p.services.forEach(s => {
          if (s.dueDate && new Date(s.dueDate) < new Date(Date.now() + 30*86400000) && s.status !== "completed") {
            arr.push({ client: c.company, project: p.name, service: s.name, date: s.dueDate });
          }
        });
      });
      return arr;
    }, []);

    return `<div class="view admin-overview">
      <header class="view__header">
        <h2 class="view__title">Panel de Administración</h2>
        <p class="view__subtitle">Resumen general de tu negocio</p>
        <button class="btn btn--icon sidebar-toggle" aria-label="Menú"><span class="material-icons">menu</span></button>
      </header>
      <div class="admin-metrics">
        <div class="admin-metric">
          <span class="admin-metric__icon" style="background:var(--accent-soft);color:var(--accent)"><span class="material-icons">business</span></span>
          <div><span class="admin-metric__value">${total}</span><span class="admin-metric__label">Total Clientes</span></div>
        </div>
        <div class="admin-metric">
          <span class="admin-metric__icon" style="background:rgba(59,130,246,.15);color:#60a5fa"><span class="material-icons">construction</span></span>
          <div><span class="admin-metric__value">${inProgress}</span><span class="admin-metric__label">Proyectos en Proceso</span></div>
        </div>
        <div class="admin-metric">
          <span class="admin-metric__icon" style="background:rgba(16,185,129,.15);color:var(--success)"><span class="material-icons">trending_up</span></span>
          <div><span class="admin-metric__value">$${mrr.toFixed(0)}</span><span class="admin-metric__label">MRR Estimado</span></div>
        </div>
        <div class="admin-metric">
          <span class="admin-metric__icon" style="background:rgba(245,158,11,.15);color:var(--warning)"><span class="material-icons">notification_important</span></span>
          <div><span class="admin-metric__value">${soon.length}</span><span class="admin-metric__label">Próximos Vencimientos</span></div>
        </div>
      </div>
      ${soon.length > 0 ? C.card("Vencimientos Próximos (30 días)", `
        <div class="admin-renewals">${soon.map(r => `
          <div class="admin-renewal">
            <span class="admin-renewal__client">${r.client}</span>
            <span class="admin-renewal__service">${r.project} — ${r.service}</span>
            <span class="admin-renewal__date">${r.date}</span>
          </div>`).join("")}</div>`) : ""}
      <div style="margin-top:1.5rem;text-align:center;color:var(--text-muted);font-size:.8125rem">
        <span class="material-icons" style="vertical-align:middle;font-size:1rem">info</span>
        Ve a <strong>Gestión de Clientes</strong> para modificar datos en tiempo real.
      </div>
    </div>`;
  },

  /* --- Tabla de gestión de clientes --- */
  adminClients() {
    return `<div class="view admin-clients">
      <header class="view__header">
        <h2 class="view__title">Gestión de Clientes</h2>
        <p class="view__subtitle">Selecciona un cliente para editar sus datos</p>
        <button class="btn btn--icon sidebar-toggle" aria-label="Menú"><span class="material-icons">menu</span></button>
      </header>
      <div class="table-responsive">
        <table class="admin-table">
          <thead><tr>
            <th>Negocio</th><th>Proyecto</th><th>Avance</th><th>Servicios</th><th style="width:180px">Acciones</th>
          </tr></thead>
          <tbody>${AppData.clients.map(c => {
            const allSvcs = c.projects.flatMap(p => p.services);
            const activeSvcs = allSvcs.filter(s => s.status === "active" || s.status === "in-progress").length;
            const firstProj = c.projects[0];
            return `<tr>
              <td><strong>${c.company}</strong><br><span class="admin-table__sub">${c.email}</span></td>
              <td>${firstProj?.name || "—"}${c.projects.length > 1 ? ` <span class="badge badge--active">+${c.projects.length - 1}</span>` : ""}</td>
              <td>
                <div class="admin-progress-mini">
                  <div class="progress-bar" style="width:80px">
                    <div class="progress-bar__fill" style="width:${firstProj?.progress || 0}%"></div>
                  </div>
                  <span>${firstProj?.progress || 0}%</span>
                </div>
              </td>
              <td>${activeSvcs} activos</td>
              <td>
                <div class="admin-table__actions">
                  <button class="btn btn--ghost" onclick="openEditPanel(${c.id})" title="Editar">
                    <span class="material-icons">edit</span>
                  </button>
                  <button class="btn btn--ghost" onclick="viewAsClient(${c.id})" title="Ver como cliente">
                    <span class="material-icons">visibility</span>
                  </button>
                </div>
              </td>
            </tr>`;
          }).join("")}</tbody>
        </table>
      </div>
    </div>`;
  },

  /* --- Panel de edición (slide-out drawer) --- */
  editPanel(clientId) {
    const cl = getClient(clientId);
    if (!cl) return "";
    const { projects } = cl;
    const projId = state.editProjectId || projects[0]?.id;
    const proj = projects.find(p => p.id === projId) || projects[0];

    return `<div class="edit-panel" id="edit-panel">
      <div class="edit-panel__header">
        <h3>Editando: ${cl.company}</h3>
        <button class="btn btn--ghost" onclick="closeEditPanel()"><span class="material-icons">close</span></button>
      </div>
      <div class="edit-panel__body">
        <!-- Proyectos -->
        <section class="edit-section">
          <h4 class="edit-section__title">Proyectos</h4>
          ${projects.length > 1 ? `
          <div class="form-group">
            <label class="form-label">Seleccionar Proyecto</label>
            <select class="form-input" id="edit-project-select" onchange="switchEditProject(${clientId}, parseInt(this.value))">
              ${projects.map(p => `<option value="${p.id}" ${p.id === projId ? "selected" : ""}>${p.name}</option>`).join("")}
            </select>
          </div>` : `<input type="hidden" id="edit-project-select" value="${proj.id}">`}
          <button class="btn btn--outline" onclick="addProject(${clientId})" style="margin-top:.5rem;width:100%">
            <span class="material-icons">add</span> Agregar Proyecto
          </button>

          <div class="form-group" style="margin-top:.75rem">
            <label class="form-label">Nombre del Proyecto</label>
            <input class="form-input" type="text" id="edit-project-name" value="${proj.name}">
          </div>
          <div class="edit-slider-group">
            <label>Avance: <strong id="edit-progress-val">${proj.progress}%</strong></label>
            <input type="range" min="0" max="100" value="${proj.progress}" class="edit-slider"
              oninput="previewProgress(this.value)" id="edit-progress">
          </div>
          <div class="form-group">
            <label class="form-label">Fase Actual</label>
            <input class="form-input" type="text" id="edit-phase" value="${proj.phase}">
          </div>
        </section>

        <!-- Hitos (editables) -->
        <section class="edit-section">
          <h4 class="edit-section__title">Hitos del Proyecto</h4>
          <div class="edit-checklist" id="edit-milestones-list">${proj.milestones.map((m, i) => `
            <div class="edit-milestone-row" data-ms-idx="${i}">
              <input type="checkbox" class="edit-checkbox" ${m.status === "completed" ? "checked" : ""}
                onchange="updateMilestoneStatus(${clientId}, ${proj.id}, ${m.id}, this)">
              <input class="form-input edit-milestone-name" value="${m.title}" placeholder="Nombre del hito"
                oninput="updateMilestoneName(${clientId}, ${proj.id}, ${m.id}, this.value)">
              <button class="btn btn--ghost" onclick="removeMilestone(${clientId}, ${proj.id}, ${m.id})" title="Eliminar" style="color:var(--danger);padding:.25rem">
                <span class="material-icons">delete</span>
              </button>
            </div>`).join("")}</div>
          <button class="btn btn--outline" onclick="addMilestone(${clientId}, ${proj.id})" style="margin-top:.5rem">
            <span class="material-icons">add</span> Agregar Hito
          </button>
        </section>

        <!-- Servicios del proyecto -->
        <section class="edit-section">
          <h4 class="edit-section__title">Servicios de este Proyecto</h4>
          <div id="edit-services-list">${proj.services.map((s, i) => `
            <div class="edit-service-row" data-svc-idx="${i}">
              <input class="form-input edit-service-name" value="${s.name}" placeholder="Nombre">
              <select class="form-input edit-service-status" onchange="updateServiceStatus(${clientId}, ${proj.id}, ${i}, this.value)">
                <option value="active" ${s.status === "active" ? "selected" : ""}>Activo</option>
                <option value="in-progress" ${s.status === "in-progress" ? "selected" : ""}>En Proceso</option>
                <option value="about-to-expire" ${s.status === "about-to-expire" ? "selected" : ""}>Por Vencer</option>
                <option value="pending" ${s.status === "pending" ? "selected" : ""}>Pendiente</option>
                <option value="completed" ${s.status === "completed" ? "selected" : ""}>Completado</option>
              </select>
              <input class="form-input edit-service-date" type="date" value="${s.dueDate}">
              <input class="form-input edit-service-price" value="${s.price}" placeholder="$0" style="width:80px">
              <button class="btn btn--ghost" onclick="removeService(${clientId}, ${proj.id}, ${i})" title="Eliminar" style="color:var(--danger)">
                <span class="material-icons">delete</span>
              </button>
            </div>`).join("")}</div>
          <button class="btn btn--outline" onclick="addService(${clientId}, ${proj.id})" style="margin-top:.5rem">
            <span class="material-icons">add</span> Agregar Servicio
          </button>
        </section>

        <!-- Marca -->
        <section class="edit-section">
          <h4 class="edit-section__title">Identidad de Marca</h4>
          <div class="edit-colors" id="edit-colors-list">${proj.brand?.palette ? proj.brand.palette.map((c, i) => `
            <div class="edit-color-row">
              <input class="form-input edit-color-name" value="${c.name}" placeholder="Nombre" style="flex:1">
              <input class="form-input edit-color-hex" type="color" value="${c.hex}" style="width:50px;padding:2px;height:38px"
                oninput="this.parentNode.querySelector('.edit-color-hex-text').value=this.value">
              <input class="form-input edit-color-hex-text" value="${c.hex}" placeholder="#HEX" style="width:90px;font-family:monospace"
                oninput="this.parentNode.querySelector('.edit-color-hex').value=this.value">
              <button class="btn btn--ghost" onclick="removeColor(${clientId}, ${proj.id}, ${i})" title="Eliminar" style="color:var(--danger)">
                <span class="material-icons">delete</span>
              </button>
            </div>`).join("") : ""}</div>
          <button class="btn btn--outline" onclick="addColor(${clientId}, ${proj.id})" style="margin-top:.5rem">
            <span class="material-icons">add</span> Agregar Color
          </button>
          <div class="form-group" style="margin-top:.75rem">
            <label class="form-label">URL Logotipo</label>
            <input class="form-input" type="text" id="edit-logo-url" value="${proj.brand?.logoUrl || "#"}" placeholder="#">
          </div>
          <div class="form-group">
            <label class="form-label">URL Manual de Marca</label>
            <input class="form-input" type="text" id="edit-manual-url" value="${proj.brand?.brandManualUrl || "#"}" placeholder="#">
          </div>
          <div class="form-group">
            <label class="form-label">URL Repositorio</label>
            <input class="form-input" type="text" id="edit-repo-url" value="${proj.brand?.repoUrl || "#"}" placeholder="#">
          </div>
        </section>
      </div>
      <div class="edit-panel__footer">
        <button class="btn btn--ghost" onclick="closeEditPanel()">Cancelar</button>
        <button class="btn btn--primary" onclick="saveEditPanel(${clientId})">
          <span class="material-icons">save</span> Guardar Cambios
        </button>
      </div>
    </div>`;
  }
};

/* ============================================================
 * 6. EDIT PANEL — FUNCIONES GLOBALES
 * ============================================================ */
window.openEditPanel = function(clientId) {
  state.editClientId = clientId;
  state.editPanelOpen = true;
  const cl = getClient(clientId);
  state.editProjectId = cl?.projects[0]?.id || null;
  renderApp();
  requestAnimationFrame(() => {
    const panel = $("#edit-panel");
    if (panel) {
      void panel.offsetWidth;
      panel.classList.add("edit-panel--open");
    }
  });
};

window.closeEditPanel = function() {
  state.editPanelOpen = false;
  state.editClientId = null;
  state.editProjectId = null;
  const panel = $("#edit-panel");
  if (panel) panel.classList.remove("edit-panel--open");
  setTimeout(() => renderApp(), 300);
};

window.toggleMilestones = function(btn) {
  const card = btn.closest(".project-card");
  if (card) card.classList.toggle("project-card--expanded");
};

window.switchEditProject = function(clientId, projectId) {
  state.editProjectId = projectId;
  renderEditPanel(clientId);
};

window.previewProgress = function(val) {
  const el = $("#edit-progress-val");
  if (el) el.textContent = val + "%";
};

window.updateMilestoneStatus = function(clientId, projectId, msId, checkbox) {
  const cl = getClient(clientId);
  if (!cl) return;
  const proj = cl.projects.find(p => p.id === projectId);
  if (!proj) return;
  const ms = proj.milestones.find(m => m.id === msId);
  if (ms) {
    ms.status = checkbox.checked ? "completed" : "pending";
  } else {
    const titles = {
      1: "Diagnóstico inicial",
      2: "Prototipo e interfaz (UI)",
      3: "Programación y funcionalidades",
      4: "Pruebas y optimización",
      5: "Lanzamiento y hosting"
    };
    proj.milestones.push({
      id: msId,
      title: titles[msId] || `Hito ${msId}`,
      status: checkbox.checked ? "completed" : "pending"
    });
  }
  persistData();
};

window.updateServiceStatus = function(clientId, projectId, idx, value) {
  const cl = getClient(clientId);
  if (!cl) return;
  const proj = cl.projects.find(p => p.id === projectId);
  if (proj && proj.services[idx]) proj.services[idx].status = value;
  persistData();
};

window.removeService = function(clientId, projectId, idx) {
  const cl = getClient(clientId);
  if (!cl) return;
  const proj = cl.projects.find(p => p.id === projectId);
  if (!proj) return;
  proj.services.splice(idx, 1);
  persistData();
  renderEditPanel(clientId);
};

window.addProject = function(clientId) {
  const cl = getClient(clientId);
  if (!cl) return;
  const maxId = Math.max(...cl.projects.map(p => p.id), 0);
  const newId = maxId + 1;
  cl.projects.push({
    id: newId,
    name: "Nuevo Proyecto",
    progress: 0,
    phase: "Por definir",
    milestones: [
      { id: 1, title: "Diagnóstico inicial", status: "pending" },
      { id: 2, title: "Prototipo e interfaz (UI)", status: "pending" },
      { id: 3, title: "Programación y funcionalidades", status: "pending" },
      { id: 4, title: "Pruebas y optimización", status: "pending" },
      { id: 5, title: "Lanzamiento y hosting", status: "pending" }
    ],
    services: [],
    brand: {
      palette: [
        { name: "Color Principal", hex: "#10b981" },
        { name: "Gris", hex: "#94a3b8" },
        { name: "Blanco", hex: "#FFFFFF" }
      ],
      logoUrl: "#",
      brandManualUrl: "#",
      repoUrl: "#"
    }
  });
  state.editProjectId = newId;
  persistData();
  renderEditPanel(clientId);
};

window.addService = function(clientId, projectId) {
  const cl = getClient(clientId);
  if (!cl) return;
  const proj = cl.projects.find(p => p.id === projectId);
  if (!proj) return;
  const maxId = Math.max(...proj.services.map(s => s.id), 0);
  proj.services.push({ id: maxId + 1, name: "Nuevo Servicio", status: "pending", dueDate: "2026-12-31", price: "$0" });
  persistData();
  renderEditPanel(clientId);
};

window.addMilestone = function(clientId, projectId) {
  const cl = getClient(clientId);
  if (!cl) return;
  const proj = cl.projects.find(p => p.id === projectId);
  if (!proj) return;
  const maxId = Math.max(...proj.milestones.map(m => m.id), 0);
  proj.milestones.push({ id: maxId + 1, title: "Nuevo hito", status: "pending" });
  persistData();
  renderEditPanel(clientId);
};

window.removeMilestone = function(clientId, projectId, msId) {
  const cl = getClient(clientId);
  if (!cl) return;
  const proj = cl.projects.find(p => p.id === projectId);
  if (!proj) return;
  proj.milestones = proj.milestones.filter(m => m.id !== msId);
  persistData();
  renderEditPanel(clientId);
};

window.updateMilestoneName = function(clientId, projectId, msId, value) {
  const cl = getClient(clientId);
  if (!cl) return;
  const proj = cl.projects.find(p => p.id === projectId);
  if (!proj) return;
  const ms = proj.milestones.find(m => m.id === msId);
  if (ms) ms.title = value;
  persistData();
};

window.removeColor = function(clientId, projectId, idx) {
  const cl = getClient(clientId);
  if (!cl) return;
  const proj = cl.projects.find(p => p.id === projectId);
  if (!proj || !proj.brand) return;
  proj.brand.palette.splice(idx, 1);
  persistData();
  renderEditPanel(clientId);
};

window.addColor = function(clientId, projectId) {
  const cl = getClient(clientId);
  if (!cl) return;
  const proj = cl.projects.find(p => p.id === projectId);
  if (!proj) return;
  if (!proj.brand) proj.brand = { palette: [], logoUrl: "#", brandManualUrl: "#", repoUrl: "#" };
  proj.brand.palette.push({ name: "Nuevo Color", hex: "#10b981" });
  persistData();
  renderEditPanel(clientId);
};

function renderEditPanel(clientId) {
  const container = $("#edit-panel");
  if (!container) return;
  const html = C.editPanel(clientId);
  container.outerHTML = html;
  requestAnimationFrame(() => {
    const p = $("#edit-panel");
    if (p) {
      void p.offsetWidth;
      p.classList.add("edit-panel--open");
    }
  });
}

window.saveEditPanel = function(clientId) {
  const cl = getClient(clientId);
  if (!cl) return;

  const selectEl = $("#edit-project-select");
  const projectId = selectEl ? parseInt(selectEl.value) : cl.projects[0]?.id;
  const proj = cl.projects.find(p => p.id === projectId);
  if (!proj) return;

  // Project name
  const nameInput = $("#edit-project-name");
  if (nameInput) proj.name = nameInput.value;

  // Progress
  const progressInput = $("#edit-progress");
  if (progressInput) proj.progress = parseInt(progressInput.value) || 0;

  // Phase
  const phaseInput = $("#edit-phase");
  if (phaseInput) proj.phase = phaseInput.value;

  // Milestone names from the UI (status already saved via onchange)
  $$(".edit-milestone-row").forEach((row) => {
    const nameEl = row.querySelector(".edit-milestone-name");
    // Find milestone by its index in the rendered list
    const idx = parseInt(row.dataset.msIdx);
    if (nameEl && proj.milestones[idx]) {
      proj.milestones[idx].title = nameEl.value;
    }
  });

  // Service names & dates from the UI
  $$(".edit-service-row").forEach((row, i) => {
    if (proj.services[i]) {
      const nameEl = row.querySelector(".edit-service-name");
      const dateEl = row.querySelector(".edit-service-date");
      const priceEl = row.querySelector(".edit-service-price");
      if (nameEl) proj.services[i].name = nameEl.value;
      if (dateEl) proj.services[i].dueDate = dateEl.value;
      if (priceEl) proj.services[i].price = priceEl.value;
    }
  });

  // Brand colors (per project)
  if (proj.brand) {
    $$(".edit-color-row").forEach((row, i) => {
      if (proj.brand.palette[i]) {
        const nameEl = row.querySelector(".edit-color-name");
        const hexEl = row.querySelector(".edit-color-hex");
        const hexText = row.querySelector(".edit-color-hex-text");
        if (nameEl) proj.brand.palette[i].name = nameEl.value;
        const hex = hexText ? hexText.value : (hexEl ? hexEl.value : "");
        if (hex) proj.brand.palette[i].hex = hex;
      }
    });

    const logoUrl = $("#edit-logo-url");
    const manualUrl = $("#edit-manual-url");
    const repoUrl = $("#edit-repo-url");
    if (logoUrl) proj.brand.logoUrl = logoUrl.value;
    if (manualUrl) proj.brand.brandManualUrl = manualUrl.value;
    if (repoUrl) proj.brand.repoUrl = repoUrl.value;
  }

  showToast("Guardado", `Datos de ${cl.company} actualizados correctamente`);
  persistData();
  closeEditPanel();
};

/* ============================================================
 * 7. MODO ADMIN/CLIENTE
 * ============================================================ */
window.toggleMode = function() {
  state.editPanelOpen = false;
  state.editClientId = null;
  state.editProjectId = null;
  state.isAdmin = !state.isAdmin;
  sessionStorage.setItem("nc-admin", state.isAdmin);
  const target = state.isAdmin ? "admin" : "home";
  location.hash = target;
  state.view = target;
  renderApp();
};

window.viewAsClient = function(clientId) {
  state.editPanelOpen = false;
  state.editClientId = null;
  state.editProjectId = null;
  state.activeClientId = clientId;
  sessionStorage.setItem("nc-client", clientId);
  state.isAdmin = false;
  sessionStorage.setItem("nc-admin", "false");
  location.hash = "home";
  state.view = "home";
  renderApp();
};

/* ============================================================
 * 8. RENDERIZADO PRINCIPAL
 * ============================================================ */
function renderApp() {
  const app = $("#app");
  if (!app) return;

  if (!state.loggedIn) {
    if (state.view === "register") {
      app.innerHTML = C.registerView();
      $("#register-form")?.addEventListener("submit", handleRegister);
      return;
    }
    if (state.view === "verify" && state.regEmail) {
      app.innerHTML = C.verifyView(state.regEmail);
      $("#verify-form")?.addEventListener("submit", handleVerify);
      return;
    }
    app.innerHTML = C.loginView();
    $("#login-form")?.addEventListener("submit", handleLogin);
    return;
  }

  const views = {
    home: C.homeView, services: C.servicesView, brand: C.brandView, support: C.supportView,
    admin: C.adminOverview, "admin-clients": C.adminClients
  };
  const renderer = views[state.view] || (state.isAdmin ? views["admin"] : views["home"]);

  let content = renderer();

  // Append edit panel if admin and editing
  let editHtml = "";
  if (state.isAdmin && state.editClientId !== null && state.editPanelOpen) {
    editHtml = C.editPanel(state.editClientId);
  }

  app.innerHTML = `
    ${C.sidebar()}
    <div class="overlay" id="overlay"></div>
    <main class="main-content">${content}</main>
    ${C.bottomNav()}
    ${editHtml}
    <div class="toast" id="toast">
      <div class="toast__content">
        <span class="material-icons toast__icon" id="toast-icon">check_circle</span>
        <div><strong id="toast-title"></strong><p id="toast-message"></p></div>
      </div>
      <button class="toast__close" onclick="closeToast()"><span class="material-icons">close</span></button>
    </div>`;

  bindSidebarEvents();
  setTheme(state.theme);

  // Trigger edit panel animation after DOM update
  if (state.editPanelOpen) {
    requestAnimationFrame(() => {
      const panel = $("#edit-panel");
      if (panel) panel.classList.add("edit-panel--open");
    });
  }
}

/* ============================================================
 * 9. EVENT BINDING
 * ============================================================ */
function bindSidebarEvents() {
  $$(".sidebar-toggle").forEach(btn => {
    btn.onclick = (e) => {
      e.stopPropagation();
      $("#sidebar")?.classList.add("sidebar--open");
      $("#overlay")?.classList.add("overlay--visible");
    };
  });
  $("#sidebar-close")?.addEventListener("click", closeSidebar);
  $("#overlay")?.addEventListener("click", closeSidebar);
}

function closeSidebar() {
  $("#sidebar")?.classList.remove("sidebar--open");
  $("#overlay")?.classList.remove("overlay--visible");
}

/* ============================================================
 * 10. LOGIN / LOGOUT
 * ============================================================ */
async function doApiLogin(identifier, password) {
  try {
    const res = await fetch("api/index.php?action=login", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email: identifier, password })
    });
    if (res.ok) {
      const data = await res.json();
      return data.role === "admin" ? "admin" : "client";
    }
  } catch (_) {}
  return null;
}

function doLocalLogin(identifier, password) {
  if (identifier === AppData.adminCredentials.email) return "admin";
  const { clientCredentials } = AppData;
  if ((identifier === clientCredentials.identifier || identifier === clientCredentials.identifier.split("@")[0])
    && password === clientCredentials.password) return "client";
  return null;
}

function handleLogin(e) {
  e.preventDefault();
  const identifier = $("#login-identifier")?.value.trim();
  const password = $("#login-password")?.value.trim();

  if (!identifier || !password) {
    showToast("Error", "Ingresa tus credenciales", "error");
    return;
  }

  const submitBtn = $("#login-form")?.querySelector("button[type=submit]");
  if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = "Validando..."; }

  // Try API first, fallback to local
  doApiLogin(identifier, password).then(role => {
    if (!role) role = doLocalLogin(identifier, password);
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = "Iniciar Sesión"; }

    if (role === "admin") {
      state.loggedIn = true;
      state.isAdmin = true;
      sessionStorage.setItem("nc-logged", "true");
      sessionStorage.setItem("nc-admin", "true");
      location.hash = "admin";
      handleRoute();
      return;
    }

    if (role === "client") {
      state.loggedIn = true;
      state.isAdmin = false;
      sessionStorage.setItem("nc-logged", "true");
      sessionStorage.setItem("nc-admin", "false");
      sessionStorage.setItem("nc-client", "1");
      state.activeClientId = 1;
      location.hash = "home";
      handleRoute();
      return;
    }

    showToast("Credenciales inválidas", "Revisa el correo/RUC y contraseña", "error");
  });
}

function logout() {
  state.loggedIn = false;
  state.isAdmin = false;
  state.editPanelOpen = false;
  state.editClientId = null;
  state.regEmail = null;
  sessionStorage.removeItem("nc-logged");
  sessionStorage.removeItem("nc-admin");
  sessionStorage.removeItem("nc-client");
  location.hash = "";
  renderApp();
}

/* ============================================================
 * 10.5. REGISTRO DE CLIENTES
 * ============================================================ */
function showRegister() {
  state.view = "register";
  location.hash = "register";
  renderApp();
}

function showLogin() {
  state.view = "login";
  state.regEmail = null;
  location.hash = "login";
  renderApp();
}

function handleRegister(e) {
  e.preventDefault();
  const errEl = $("#register-error");
  if (errEl) errEl.classList.remove("register__error--visible");

  const company = $("#reg-company")?.value.trim();
  const email = $("#reg-email")?.value.trim();
  const phone = $("#reg-phone")?.value.trim();
  const ruc = $("#reg-ruc")?.value.trim();
  const password = $("#reg-password")?.value;
  const confirm = $("#reg-confirm")?.value;

  if (!company || !email || !password || !confirm) {
    if (errEl) { errEl.textContent = "Completa todos los campos obligatorios."; errEl.classList.add("register__error--visible"); }
    return;
  }

  if (password.length < 8) {
    if (errEl) { errEl.textContent = "La contraseña debe tener al menos 8 caracteres."; errEl.classList.add("register__error--visible"); }
    return;
  }

  if (!/[A-Z]/.test(password)) {
    if (errEl) { errEl.textContent = "La contraseña debe tener al menos una letra mayúscula."; errEl.classList.add("register__error--visible"); }
    return;
  }

  if (!/[0-9]/.test(password)) {
    if (errEl) { errEl.textContent = "La contraseña debe tener al menos un número."; errEl.classList.add("register__error--visible"); }
    return;
  }

  if (password !== confirm) {
    if (errEl) { errEl.textContent = "Las contraseñas no coinciden."; errEl.classList.add("register__error--visible"); }
    return;
  }

  const submitBtn = $("#register-form")?.querySelector("button[type=submit]");
  if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = "Registrando..."; }

  fetch("api/index.php?action=register", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ company, email, password, ruc, phone })
  })
  .then(res => res.json())
  .then(data => {
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = "Crear cuenta"; }
    if (data.error) {
      if (errEl) { errEl.textContent = data.error; errEl.classList.add("register__error--visible"); }
      return;
    }
    state.regEmail = email;
    state.view = "verify";
    location.hash = "verify";
    renderApp();
    // Si no se pudo enviar el correo, mostrar código en consola
    if (!data.mail_sent && data.dev_code) {
      console.log("Código de verificación (desarrollo):", data.dev_code);
    }
  })
  .catch(err => {
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = "Crear cuenta"; }
    if (errEl) { errEl.textContent = "Error de conexión. Verifica que el servidor esté funcionando."; errEl.classList.add("register__error--visible"); }
  });
}

function handleVerify(e) {
  e.preventDefault();
  const errEl = $("#register-error");
  if (errEl) errEl.classList.remove("register__error--visible");

  const code = $("#verify-code")?.value.trim();
  if (!code || code.length !== 6) {
    if (errEl) { errEl.textContent = "Ingresa el código de 6 dígitos."; errEl.classList.add("register__error--visible"); }
    return;
  }

  const submitBtn = $("#verify-form")?.querySelector("button[type=submit]");
  if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = "Verificando..."; }

  fetch("api/index.php?action=verify", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ email: state.regEmail, code })
  })
  .then(res => res.json())
  .then(data => {
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = "Verificar cuenta"; }
    if (data.error) {
      if (errEl) { errEl.textContent = data.error; errEl.classList.add("register__error--visible"); }
      return;
    }
    // Éxito: recargar datos y mostrar login
    loadAppData().then(() => {
      state.regEmail = null;
      state.view = "login";
      renderApp();
      showToast("¡Bienvenido!", "Cuenta verificada. Ahora puedes iniciar sesión.", "check_circle");
    });
  })
  .catch(err => {
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = "Verificar cuenta"; }
    if (errEl) { errEl.textContent = "Error de conexión."; errEl.classList.add("register__error--visible"); }
  });
}

function resendCode() {
  if (!state.regEmail) return;
  const btn = document.querySelector(".verify__resend a");
  if (btn) { btn.textContent = "Enviando..."; btn.style.pointerEvents = "none"; }

  fetch("api/index.php?action=resend-code", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ email: state.regEmail })
  })
  .then(res => res.json())
  .then(data => {
    if (btn) { btn.textContent = "Reenviar"; btn.style.pointerEvents = "auto"; }
    if (data.error) {
      showToast("Error", data.error, "error");
    } else {
      showToast("Enviado", "Nuevo código enviado a tu correo.", "mail");
      if (!data.mail_sent && data.dev_code) {
        console.log("Nuevo código (desarrollo):", data.dev_code);
      }
    }
  })
  .catch(() => {
    if (btn) { btn.textContent = "Reenviar"; btn.style.pointerEvents = "auto"; }
    showToast("Error", "Error de conexión.", "error");
  });
}

/* ============================================================
 * 11. ENRUTADOR SPA
 * ============================================================ */
function handleRoute() {
  const hash = location.hash.replace("#", "") || "login";

  if (!state.loggedIn) {
    if (hash === "register" || state.view === "register") { state.view = "register"; renderApp(); return; }
    if (hash === "verify" || state.view === "verify") { renderApp(); return; }
    state.view = "login";
    renderApp();
    return;
  }

  const adminViews = ["admin", "admin-clients"];
  const clientViews = ["home", "services", "brand", "support"];

  const valid = state.isAdmin
    ? adminViews.includes(hash) ? hash : "admin"
    : clientViews.includes(hash) ? hash : "home";

  state.view = valid;
  renderApp();
}

window.addEventListener("hashchange", handleRoute);

/* ============================================================
 * 12. INICIALIZACIÓN
 * ============================================================ */
document.addEventListener("DOMContentLoaded", async () => {
  await loadAppData();
  const loader = document.querySelector(".app-loading");
  if (loader) loader.style.display = "none";
  handleRoute();
});
