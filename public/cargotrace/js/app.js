/* CargoTrace Frontend v1.0 - Laravel Blade + vanilla JavaScript, connected to the existing Sanctum API. */
(() => {
  'use strict';

  const API = '/api';
  const ROOT = document.getElementById('app');
  const TOKEN_KEY = 'cargotrace_access_token';
  const USER_KEY = 'cargotrace_user';
  const STATUS_FLOW = ['CREATED', 'BOOKED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED'];
  const EXCEPTION_TYPES = ['CUSTOMS_HOLD', 'WEATHER_DELAY', 'PORT_CONGESTION', 'DAMAGED', 'MISROUTED', 'DOCUMENTATION_ISSUE'];
  const state = {
    token: sessionStorage.getItem(TOKEN_KEY) || '',
    user: null,
    page: 'dashboard',
    pageTitle: 'Dashboard',
    pageData: null,
    selectedShipment: null,
    currentPage: 1,
    filters: {},
    map: null,
    mapLayer: null,
    notificationsUnread: 0,
  };

  const icons = {
    dashboard: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
    users: '<path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20"/><circle cx="9.5" cy="7" r="3.5"/><path d="M17 11a3.5 3.5 0 1 0-1-6.85M21 20v-1.5a4 4 0 0 0-3-3.87"/>',
    carriers: '<path d="M3 7h18l-2 12H5L3 7Z"/><path d="M7 7l2-4h6l2 4M8 12h8M9 16h6"/>',
    locations: '<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
    shipments: '<path d="m3 7 9-4 9 4-9 4-9-4Z"/><path d="M3 7v10l9 4 9-4V7M12 11v10M7 5l9 4"/>',
    audit: '<path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/>',
    tracking: '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
    exceptions: '<path d="M12 3 2.8 19h18.4L12 3Z"/><path d="M12 9v4M12 16.5v.1"/>',
    bell: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
    search: '<circle cx="10.8" cy="10.8" r="6.3"/><path d="m16 16 4 4"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>',
    chevron: '<path d="m9 18 6-6-6-6"/>',
    back: '<path d="M19 12H5M11 18l-6-6 6-6"/>',
    menu: '<path d="M4 7h16M4 12h16M4 17h16"/>',
    logout: '<path d="M10 17l5-5-5-5M15 12H3M21 4v16"/>',
    box: '<path d="m12 3 9 4.5v9L12 21l-9-4.5v-9L12 3Z"/><path d="m3 7.5 9 4.5 9-4.5M12 12v9"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    check: '<path d="m5 12 4 4L19 6"/>',
    edit: '<path d="m4 16-.8 4.8L8 20l11-11-4-4L4 16Z"/><path d="m13.5 6.5 4 4"/>',
    eye: '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/>',
    filter: '<path d="M4 6h16M7 12h10M10 18h4"/>',
    download: '<path d="M12 3v12m-5-5 5 5 5-5M4 20h16"/>',
    close: '<path d="m6 6 12 12M18 6 6 18"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
    shield: '<path d="M12 3 20 6v5c0 5-3.4 8.1-8 10-4.6-1.9-8-5-8-10V6l8-3Z"/><path d="m9 12 2 2 4-4"/>',
    map: '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"/><path d="M9 3v15M15 6v15"/>',
    profile: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    file: '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
    calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
    refresh: '<path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.5 9a7 7 0 0 1 12-2L20 12M4 12l2.5 5a7 7 0 0 0 12-2"/>',
    archive: '<path d="M4 4h16v4H4zM6 8v12h12V8M10 12h4"/>',
  };
  const icon = (name, cls = '') => `<svg class="${cls}" viewBox="0 0 24 24" aria-hidden="true">${icons[name] || icons.info}</svg>`;
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  const initial = (name = '') => name.trim().split(/\s+/).slice(0, 2).map(x => x[0] || '').join('').toUpperCase() || 'CT';
  const roleName = (role) => ({admin:'Administrator', operator:'Operations specialist', customer:'Customer'}[role] || role || 'User');
  const human = (value = '') => String(value).toLowerCase().replaceAll('_',' ').replace(/\b\w/g, x => x.toUpperCase());
  const dateFmt = (value, options = {}) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat('en-GB', {day:'2-digit', month:'short', year:options.year ? 'numeric' : undefined, hour:options.time === false ? undefined : '2-digit', minute:options.time === false ? undefined : '2-digit', hour12:false, timeZone:'UTC'}).format(date) + (options.zone === false ? '' : ' UTC');
  };
  const inputDate = (value) => {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    return d.toISOString().slice(0,16); // inputs are labeled UTC throughout CargoTrace
  };
  const isoDate = (value) => value ? new Date(value).toISOString() : null;
  const localIso = (value) => value ? new Date(`${value}:00Z`).toISOString() : null; // datetime-local is interpreted as UTC, not browser-local time

  const fmtNumber = (value) => Number(value || 0).toLocaleString('en-US');
  const etaAssessment = (shipment) => {
    const expected = shipment?.expected_arrival_at ? new Date(shipment.expected_arrival_at).getTime() : 0;
    const current = shipment?.current_eta ? new Date(shipment.current_eta).getTime() : expected;
    const sec = Math.max(0, (current - expected) / 1000);
    let severity = 'ON_TIME';
    if (sec > 0 && sec <= 6 * 3600) severity = 'LOW';
    else if (sec > 6 * 3600 && sec <= 24 * 3600) severity = 'MEDIUM';
    else if (sec > 24 * 3600) severity = 'HIGH';
    return {severity, seconds:sec, hours:sec/3600, delayed:sec > 0 && shipment?.status !== 'DELIVERED'};
  };
  const statusBadge = (status) => `<span class="status-badge status-${String(status || 'created').toLowerCase()}">${esc(status || 'UNKNOWN')}</span>`;
  const severityBadge = (severity) => `<span class="severity-badge severity-${String(severity || 'on_time').toLowerCase()}">${esc(severity || 'ON_TIME')}</span>`;
  const exceptionState = (status) => `<span class="state-badge state-${String(status || 'open').toLowerCase()}">${esc(status || 'OPEN')}</span>`;
  const roleBadge = (role) => `<span class="role-badge role-${String(role || 'customer').toLowerCase()}">${esc(role || 'customer')}</span>`;
  const locationName = (location) => location ? `${location.name || location.city || 'Location'}${location.country ? ', ' + location.country : ''}` : '—';
  const routeName = (shipment) => `${shipment?.origin?.city || shipment?.origin?.name || 'Origin'} → ${shipment?.destination?.city || shipment?.destination?.name || 'Destination'}`;
  const carrierName = (shipment) => shipment?.carrier?.name || shipment?.carrier_name || '—';
  const customerName = (shipment) => shipment?.customer?.name || shipment?.customer_name || '—';
  const showToast = (message, type = 'success') => {
    const host = document.getElementById('toast-region');
    if (!host) return;
    const el = document.createElement('div'); el.className = `toast ${type}`; el.textContent = message;
    host.appendChild(el); setTimeout(() => el.remove(), 4200);
  };
  const setLoading = (target, message = 'Loading data…') => { const node = typeof target === 'string' ? document.querySelector(target) : target; if (node) node.innerHTML = `<div class="loading-state"><span class="spinner"></span>${esc(message)}</div>`; };

  class ApiError extends Error {
    constructor(message, status, payload) { super(message); this.status = status; this.payload = payload; }
  }
  async function api(path, options = {}) {
    const headers = {Accept:'application/json', ...(options.headers || {})};
    if (state.token) headers.Authorization = `Bearer ${state.token}`;
    let body = options.body;
    if (body !== undefined && body !== null && !(body instanceof FormData)) {
      headers['Content-Type'] = 'application/json'; body = JSON.stringify(body);
    }
    let response;
    try { response = await fetch(`${API}${path}`, {method:options.method || 'GET', headers, body, credentials:'same-origin'}); }
    catch (err) { throw new ApiError('Tidak dapat terhubung ke server. Pastikan php artisan serve masih berjalan.', 0, null); }
    let payload = null;
    const text = await response.text();
    try { payload = text ? JSON.parse(text) : {}; } catch { payload = {message:text || 'Unexpected server response.'}; }
    if (response.status === 401 && state.token && !options.allowUnauthenticated) {
      clearSession(false); showLogin('Sesi berakhir. Silakan masuk kembali.'); throw new ApiError('Sesi berakhir.', 401, payload);
    }
    if (!response.ok) {
      const errors = payload?.errors ? Object.values(payload.errors).flat().join(' ') : '';
      throw new ApiError(errors || payload?.message || `Request gagal (${response.status}).`, response.status, payload);
    }
    return payload;
  }
  const queryString = (params) => {
    const qs = new URLSearchParams();
    Object.entries(params || {}).forEach(([key, value]) => { if (value !== '' && value !== null && value !== undefined) qs.set(key, value); });
    return qs.toString() ? `?${qs.toString()}` : '';
  };
  // Laravel's default JSON paginator exposes pagination fields at the top level (not under meta).
  const paginator = (response) => {
    const items = Array.isArray(response?.data) ? response.data : [];
    const source = response?.meta || response || {};
    return {
      items,
      meta: {
        current_page: Number(source.current_page || 1),
        last_page: Number(source.last_page || 1),
        total: Number(source.total ?? items.length),
        per_page: Number(source.per_page || items.length || 20),
      },
    };
  };

  const roleNavigation = (user) => {
    const role = user?.role;
    if (role === 'admin') return [
      {section:'Workspace'},
      {id:'dashboard',label:'Dashboard',icon:'dashboard'},
      {id:'users',label:'Users',icon:'users'},
      {id:'carriers',label:'Carriers',icon:'carriers'},
      {id:'locations',label:'Locations',icon:'locations'},
      {id:'shipments',label:'All Shipments',icon:'shipments'},
      {id:'audit',label:'Audit Logs',icon:'audit'},
    ];
    if (role === 'operator') return [
      {section:'Workspace'},
      {id:'dashboard',label:'Dashboard',icon:'dashboard'},
      {id:'shipments',label:'Shipments',icon:'shipments'},
      {id:'create',label:'Create Shipment',icon:'plus'},
      {id:'tracking',label:'Tracking',icon:'tracking'},
      {id:'exceptions',label:'Exceptions',icon:'exceptions'},
    ];
    return [
      {section:'Workspace'},
      {id:'dashboard',label:'Dashboard',icon:'dashboard'},
      {id:'shipments',label:'My Shipments',icon:'shipments'},
      {id:'notifications',label:'Notifications',icon:'bell',count:state.notificationsUnread},
      {id:'profile',label:'Profile',icon:'profile'},
    ];
  };
  const titleFor = (page) => ({dashboard:'Dashboard',users:'Users',carriers:'Carriers',locations:'Locations',shipments:state.user?.role === 'customer' ? 'My Shipments' : 'Shipments',audit:'Audit Logs',create:'Create Shipment',tracking:'Tracking',exceptions:'Exceptions',notifications:'Notifications',profile:'Profile',detail:state.selectedShipment?.shipment_number || 'Shipment detail'}[page] || 'CargoTrace');
  const buildShell = () => {
    const user = state.user;
    const role = user.role;
    const nav = roleNavigation(user);
    const navHtml = nav.map(item => item.section
      ? `<div class="workspace-label">${esc(item.section)}</div><div class="role-chip">${esc(role.toUpperCase())}</div><div class="tenant-label">${role === 'customer' ? esc(user.name) : 'Global operations'}</div>`
      : `<button class="nav-link ${state.page === item.id || (state.page === 'detail' && item.id === 'shipments') || (state.page === 'create' && item.id === 'create') ? 'active' : ''}" data-nav="${item.id}"><span class="nav-icon">${icon(item.icon)}</span><span>${esc(item.label)}</span>${item.count > 0 ? `<span class="nav-count">${item.count}</span>` : ''}</button>`
    ).join('');
    ROOT.innerHTML = `
      <div class="app-shell">
        <div class="mobile-scrim" id="mobile-scrim"></div>
        <aside class="sidebar" id="sidebar">
          <div class="brand"><div class="brand-icon">C</div><div><div class="brand-name">CargoTrace</div><span class="brand-sub">GLOBAL SHIPMENT VISIBILITY</span></div></div>
          ${navHtml}
          <div class="sidebar-bottom"><a href="javascript:void(0)" data-action="help">${icon('info')} Help &amp; documentation</a><div class="system-state"><span class="status-dot"></span>All systems operational</div></div>
        </aside>
        <main class="main-area">
          <header class="topbar">
            <div style="display:flex;align-items:center;gap:13px"><button class="icon-button mobile-toggle" data-action="toggle-menu" aria-label="Open navigation">${icon('menu')}</button><div class="breadcrumbs"><span>${role === 'admin' ? 'Administration' : role === 'operator' ? 'Operations' : user.name}</span>${icon('chevron')}<strong id="breadcrumb-current">${esc(titleFor(state.page))}</strong></div></div>
            <div class="top-actions"><span class="utc-label">All times UTC</span>${role === 'customer' ? `<button class="icon-button" data-nav="notifications" title="Notifications">${icon('bell')}${state.notificationsUnread ? '<span class="notify-dot"></span>' : ''}</button>` : ''}<button class="user-menu" data-action="user-menu"><span class="avatar">${esc(initial(user.name))}</span><div><strong>${esc(user.name)}</strong><small>${esc(roleName(role))}</small></div></button><button class="icon-button" data-action="logout" title="Sign out">${icon('logout')}</button></div>
          </header>
          <div id="page-content" class="page-wrap"></div>
          <footer class="footerbar"><span>All systems operational · CargoTrace workspace</span><span>CargoTrace / ${esc(roleName(role))}</span></footer>
        </main>
      </div>
      <div id="modal-root"></div>`;
    ROOT.querySelectorAll('[data-nav]').forEach(btn => btn.addEventListener('click', () => navigate(btn.dataset.nav)));
    ROOT.querySelector('[data-action="toggle-menu"]')?.addEventListener('click', toggleMenu);
    ROOT.querySelector('#mobile-scrim')?.addEventListener('click', closeMenu);
    ROOT.querySelector('[data-action="logout"]')?.addEventListener('click', logout);
    ROOT.querySelector('[data-action="help"]')?.addEventListener('click', () => showToast('Bantuan: gunakan menu sesuai role. Hubungi administrator untuk akses akun.', 'success'));
    ROOT.querySelector('[data-action="user-menu"]')?.addEventListener('click', () => { if (state.user.role === 'customer') navigate('profile'); else showToast(`${state.user.name} · ${roleName(state.user.role)}`); });
  };
  function toggleMenu(){document.getElementById('sidebar')?.classList.toggle('open');document.getElementById('mobile-scrim')?.classList.toggle('visible');}
  function closeMenu(){document.getElementById('sidebar')?.classList.remove('open');document.getElementById('mobile-scrim')?.classList.remove('visible');}

  function brandMarkup(light = false) {
    return `<div class="brand ${light ? 'brand-light' : ''}"><div class="brand-icon">C</div><div><div class="brand-name">CargoTrace</div><span class="brand-sub">GLOBAL SHIPMENT VISIBILITY</span></div></div>`;
  }
  function showLanding() {
    state.user = null; state.page = 'welcome';
    ROOT.innerHTML = `
      <div class="welcome-page">
        <header class="welcome-header">
          ${brandMarkup(true)}
          <div class="welcome-header-actions"><span class="welcome-header-label">GLOBAL LOGISTICS WORKSPACE</span><button class="welcome-login-link" type="button" data-action="landing-login">Masuk <span aria-hidden="true">→</span></button></div>
        </header>
        <main class="welcome-main">
          <section class="welcome-copy">
            <div class="welcome-eyebrow"><span></span> MODERN ENTERPRISE LOGISTICS</div>
            <h1>Clarity at every <em>checkpoint.</em></h1>
            <p class="welcome-description">One connected workspace for shipment visibility, operational decisions and customer confidence — across every route.</p>
            <div class="welcome-cta-row">
              <button class="welcome-cta" type="button" data-action="landing-login">Masuk ke CargoTrace ${icon('arrow')}</button>
              <span class="welcome-cta-note">Secure access · Role-based workspace</span>
            </div>
            <div class="welcome-feature-row">
              <div class="welcome-feature"><span class="welcome-feature-icon">${icon('check')}</span><div><strong>End-to-end visibility</strong><small>Follow each shipment lifecycle</small></div></div>
              <div class="welcome-feature"><span class="welcome-feature-icon">${icon('map')}</span><div><strong>Recorded checkpoints</strong><small>Tracking history and ETA updates</small></div></div>
              <div class="welcome-feature"><span class="welcome-feature-icon">${icon('profile')}</span><div><strong>Workspace by role</strong><small>Access shaped by your account</small></div></div>
            </div>
          </section>
          <aside class="welcome-visual" aria-label="Illustration of a shipment route">
            <div class="welcome-orbit welcome-orbit-one"></div><div class="welcome-orbit welcome-orbit-two"></div>
            <div class="route-preview-card">
              <div class="route-preview-head"><div><span class="route-preview-kicker">CARGOTRACE VISIBILITY</span><strong>Journey overview</strong></div><span class="route-preview-chip">RECORDED ROUTE</span></div>
              <div class="route-preview-map">
                <svg viewBox="0 0 520 260" role="img" aria-label="Illustrative route connecting origin, checkpoint and destination">
                  <defs><linearGradient id="routeLineGradient" x1="0" x2="1"><stop offset="0" stop-color="#79A9FF"/><stop offset="1" stop-color="#2563EB"/></linearGradient><pattern id="routeDots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="1.5" cy="1.5" r="1" fill="#9FB8D4" opacity=".35"/></pattern></defs>
                  <rect width="520" height="260" rx="15" fill="url(#routeDots)"/>
                  <path d="M26 160 C105 128 115 66 194 92 S298 190 362 124 S439 83 495 51" fill="none" stroke="#D8E3F0" stroke-width="15" stroke-linecap="round" opacity=".55"/>
                  <path d="M26 160 C105 128 115 66 194 92 S298 190 362 124 S439 83 495 51" fill="none" stroke="url(#routeLineGradient)" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="7 8"/>
                  <circle cx="26" cy="160" r="8" fill="#fff" stroke="#2563EB" stroke-width="3"/><circle cx="194" cy="92" r="8" fill="#fff" stroke="#2563EB" stroke-width="3"/><circle cx="362" cy="124" r="8" fill="#fff" stroke="#2563EB" stroke-width="3"/><circle cx="495" cy="51" r="8" fill="#fff" stroke="#142D4E" stroke-width="3"/>
                  <circle cx="194" cy="92" r="19" fill="#2563EB" opacity=".10"/><circle cx="362" cy="124" r="19" fill="#2563EB" opacity=".10"/>
                  <g fill="#526D8C" font-family="Inter, sans-serif" font-size="11" font-weight="600"><text x="24" y="190">ORIGIN</text><text x="194" y="62" text-anchor="middle">CHECKPOINT</text><text x="362" y="158" text-anchor="middle">CHECKPOINT</text><text x="496" y="26" text-anchor="end">DESTINATION</text></g>
                </svg>
                <div class="route-preview-caption"><span><i class="route-dot-blue"></i> Saved tracking events</span><span>Illustrative route · Not live GPS</span></div>
              </div>
              <div class="route-preview-bottom"><div><span class="route-mini-icon">${icon('clock')}</span><div><strong>One clear timeline</strong><small>Events recorded in UTC</small></div></div><div><span class="route-mini-icon">${icon('shield-check')}</span><div><strong>Role-based access</strong><small>Each account sees its workspace</small></div></div></div>
            </div>
            <div class="welcome-float-note"><span class="welcome-float-symbol">${icon('check')}</span><div><strong>Clarity at every step</strong><small>One view for every team</small></div></div>
          </aside>
        </main>
        <footer class="welcome-footer"><span>© ${new Date().getUTCFullYear()} CargoTrace · All times UTC</span><span>Global shipment visibility, made clear.</span></footer>
      </div>`;
    ROOT.querySelectorAll('[data-action="landing-login"]').forEach(button => button.addEventListener('click', () => showLogin()));
  }
  function showLogin(message = '') {
    state.user = null; state.page = 'login';
    ROOT.innerHTML = `
      <div class="auth-page">
        <header class="auth-header">${brandMarkup()}<button class="auth-back" type="button" data-action="back-welcome">${icon('back')} Back to welcome</button></header>
        <main class="auth-main">
          <section class="login-card">
            <div class="auth-mark">C</div>
            <div class="auth-heading"><h1>Welcome back</h1><p>Sign in to access your CargoTrace workspace.</p></div>
            ${message ? `<div class="error-banner">${esc(message)}</div>` : ''}
            <form id="login-form">
              <div class="form-field"><label for="login-email">Email address</label><input id="login-email" type="email" autocomplete="username" required placeholder="you@company.com"></div>
              <div class="form-field"><label for="login-password">Password</label><div class="password-wrap"><input id="login-password" type="password" autocomplete="current-password" required placeholder="Enter your password"><button class="password-toggle" type="button" data-action="toggle-password">Show</button></div></div>
              <button class="btn" id="login-submit" type="submit">Sign in to CargoTrace ${icon('arrow')}</button>
            </form>
            <div class="login-security-note"><span class="security-icon">${icon('info')}</span><span>Use the email and password assigned to your CargoTrace account. Your workspace and access permissions are determined by your account role.</span></div>
            <div class="auth-switch">Belum punya akun? <button type="button" data-action="show-register">Daftar sebagai Customer</button></div>
          </section>
          <div class="auth-caption">Akun Administrator dan Operator dibuat oleh administrator. Registrasi mandiri hanya untuk Customer.</div>
        </main>
        <footer class="auth-footer">© ${new Date().getUTCFullYear()} CargoTrace · All times UTC</footer>
      </div>`;
    document.getElementById('login-form')?.addEventListener('submit', doLogin);
    ROOT.querySelector('[data-action="back-welcome"]')?.addEventListener('click', showLanding);
    ROOT.querySelector('[data-action="show-register"]')?.addEventListener('click', () => showRegister());
    ROOT.querySelector('[data-action="toggle-password"]')?.addEventListener('click', () => { const input=document.getElementById('login-password'); const btn=ROOT.querySelector('[data-action="toggle-password"]'); input.type=input.type==='password'?'text':'password'; btn.textContent=input.type==='password'?'Show':'Hide'; });
  }
  function showRegister(message = '', previous = {}) {
    state.user = null; state.page = 'register';
    ROOT.innerHTML = `
      <div class="auth-page">
        <header class="auth-header">${brandMarkup()}<button class="auth-back" type="button" data-action="back-welcome">${icon('back')} Kembali</button></header>
        <main class="auth-main">
          <section class="login-card">
            <div class="auth-mark">C</div>
            <div class="auth-heading"><h1>Buat akun Customer</h1><p>Daftar untuk melihat shipment CargoTrace yang ditautkan ke akunmu.</p></div>
            ${message ? `<div class="error-banner" role="alert">${esc(message)}</div>` : ''}
            <form id="register-form">
              <div class="form-field"><label for="register-name">Nama lengkap</label><input id="register-name" name="name" type="text" autocomplete="name" maxlength="255" required placeholder="Nama lengkap" value="${esc(previous.name || '')}"></div>
              <div class="form-field"><label for="register-email">Email address</label><input id="register-email" name="email" type="email" autocomplete="email" maxlength="255" required placeholder="you@company.com" value="${esc(previous.email || '')}"></div>
              <div class="form-field"><label for="register-password">Password</label><input id="register-password" name="password" type="password" autocomplete="new-password" minlength="8" required placeholder="Minimal 8 karakter"></div>
              <div class="form-field"><label for="register-password-confirmation">Konfirmasi password</label><input id="register-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required placeholder="Ulangi password"></div>
              <button class="btn" id="register-submit" type="submit">Buat akun Customer ${icon('arrow')}</button>
            </form>
            <div class="register-policy-note"><span class="security-icon">${icon('shield')}</span><span>Registrasi publik hanya membuat role CUSTOMER. Akun Admin dan Operator hanya dibuat oleh administrator. Shipment akan muncul setelah tim operasional menautkannya ke akunmu.</span></div>
            <div class="auth-switch">Sudah punya akun? <button type="button" data-action="back-login">Masuk di sini</button></div>
          </section>
          <div class="auth-caption">CargoTrace · Customer access</div>
        </main>
        <footer class="auth-footer">© ${new Date().getUTCFullYear()} CargoTrace · All times UTC</footer>
      </div>`;
    document.getElementById('register-form')?.addEventListener('submit', doRegister);
    ROOT.querySelector('[data-action="back-welcome"]')?.addEventListener('click', showLanding);
    ROOT.querySelector('[data-action="back-login"]')?.addEventListener('click', () => showLogin());
  }
  async function doRegister(event) {
    event.preventDefault();
    const name = document.getElementById('register-name').value.trim();
    const email = document.getElementById('register-email').value.trim();
    const password = document.getElementById('register-password').value;
    const confirmation = document.getElementById('register-password-confirmation').value;
    if (password !== confirmation) {
      showRegister('Konfirmasi password tidak sama.', {name, email});
      return;
    }
    const btn = document.getElementById('register-submit');
    btn.disabled = true;
    btn.textContent = 'Membuat akun…';
    try {
      const result = await api('/register', {
        method: 'POST',
        body: {name, email, password, password_confirmation: confirmation},
        allowUnauthenticated: true,
      });
      state.token = result.token;
      state.user = result.user;
      sessionStorage.setItem(TOKEN_KEY, state.token);
      sessionStorage.setItem(USER_KEY, JSON.stringify(state.user));
      await enterWorkspace();
      if (state.user?.role === 'customer') {
        showToast('Akun Customer berhasil dibuat. Shipment akan tampil setelah ditautkan oleh tim operasional.');
      }
    } catch (err) {
      showRegister(err.message, {name, email});
    }
  }
  async function doLogin(event) {
    event.preventDefault(); const btn=document.getElementById('login-submit'); btn.disabled=true; btn.textContent='Signing in…';
    try {
      const result = await api('/login',{method:'POST',body:{email:document.getElementById('login-email').value.trim(),password:document.getElementById('login-password').value},allowUnauthenticated:true});
      state.token=result.token; state.user=result.user; sessionStorage.setItem(TOKEN_KEY,state.token); sessionStorage.setItem(USER_KEY,JSON.stringify(state.user));
      await enterWorkspace();
    } catch(err) { showLogin(err.message); }
    finally { const current=document.getElementById('login-submit'); if(current){current.disabled=false;current.innerHTML=`Sign in to CargoTrace ${icon('arrow')}`;} }
  }
  function clearSession(revoke = false) { if(revoke){} state.token='';state.user=null;sessionStorage.removeItem(TOKEN_KEY);sessionStorage.removeItem(USER_KEY);state.notificationsUnread=0; }
  async function logout() { try { if(state.token) await api('/logout',{method:'POST'}); } catch {} clearSession();showLanding(); }
  async function enterWorkspace() {
    try {
      if (!state.user && state.token) { const me=await api('/me'); state.user=me.data; }
      if (!state.user || !['admin','operator','customer'].includes(state.user.role)) throw new Error('Role akun tidak dikenali.');
      const notif = state.user.role === 'customer' ? await api('/notifications?unread=1&per_page=1').catch(()=>null) : null;
      state.notificationsUnread = Number(notif?.meta?.total ?? notif?.total ?? 0);
      state.page='dashboard'; buildShell(); await navigate('dashboard',false);
    } catch(err) { clearSession();showLogin(err.message); }
  }

  async function navigate(page, closeMobile = true) {
    if (!state.user) return showLogin();
    const allowed = roleNavigation(state.user).filter(x=>x.id).map(x=>x.id).concat(['detail']);
    if (page === 'create' && state.user.role !== 'operator') page='shipments';
    if (page === 'tracking' && state.user.role !== 'operator') page='shipments';
    if (page === 'exceptions' && state.user.role !== 'operator') page='dashboard';
    if (page === 'users' && state.user.role !== 'admin') page='dashboard';
    if (page === 'carriers' && state.user.role !== 'admin') page='dashboard';
    if (page === 'locations' && state.user.role !== 'admin') page='dashboard';
    if (page === 'audit' && state.user.role !== 'admin') page='dashboard';
    if (page === 'notifications' && state.user.role !== 'customer') page='dashboard';
    if (page === 'profile' && state.user.role !== 'customer') page='dashboard';
    if (!allowed.includes(page) && page !== 'create' && page !== 'tracking' && page !== 'exceptions' && page !== 'profile' && page !== 'notifications') page='dashboard';
    state.page=page; state.pageTitle=titleFor(page); state.currentPage=1;
    document.getElementById('breadcrumb-current').textContent=state.pageTitle;
    ROOT.querySelectorAll('.nav-link').forEach(btn => btn.classList.toggle('active', btn.dataset.nav === page || (page==='detail' && btn.dataset.nav==='shipments')));
    if(closeMobile)closeMenu();
    await renderPage();
  }
  async function renderPage() {
    const host=document.getElementById('page-content'); if(!host)return;
    state.map=null;
    setLoading(host,'Loading your workspace…');
    try {
      switch(state.page) {
        case 'dashboard': await renderDashboard(host); break;
        case 'shipments': await renderShipments(host); break;
        case 'create': await renderShipmentForm(host); break;
        case 'detail': await renderShipmentDetail(host); break;
        case 'users': await renderUsers(host); break;
        case 'carriers': await renderCarriers(host); break;
        case 'locations': await renderLocations(host); break;
        case 'audit': await renderAudit(host); break;
        case 'tracking': await renderTracking(host); break;
        case 'exceptions': await renderExceptions(host); break;
        case 'notifications': await renderNotifications(host); break;
        case 'profile': renderProfile(host); break;
        default: await renderDashboard(host);
      }
    } catch(err) {
      if(err.status===401)return;
      host.innerHTML=`<div class="error-banner">${esc(err.message)} <button class="btn-link" data-action="retry-page">Try again</button></div>`;
      host.querySelector('[data-action="retry-page"]')?.addEventListener('click',renderPage);
    }
  }
  function heading(eyebrow,title,subtitle,action='') { return `<div class="page-heading"><div><div class="eyebrow">${esc(eyebrow)}</div><h1>${esc(title)}</h1><p>${esc(subtitle)}</p></div><div class="heading-actions">${action}</div></div>`; }
  const actionBtn = (label, action, iconName='plus', secondary=false) => `<button class="btn ${secondary?'btn-secondary':''}" data-action="${action}">${icon(iconName)}${esc(label)}</button>`;
  const tableEmpty = (text='No records found.') => `<tr><td colspan="9"><div class="empty-state"><div class="empty-icon">${icon('box')}</div><strong>${esc(text)}</strong><p>Try another search or clear the active filters.</p></div></td></tr>`;
  function statCard(label,value,note,iconName='box') { return `<div class="stat-card"><div class="stat-label"><span>${esc(label)}</span><span class="stat-icon">${icon(iconName)}</span></div><div class="stat-value">${esc(value)}</div><div class="stat-note">${esc(note)}</div><div class="stat-accent"></div></div>`; }
  function paginatorHtml(meta, action) {
    if(!meta || meta.last_page<=1) return `<div class="pagination"><span>Showing ${meta?.total || 0} records</span><span></span></div>`;
    return `<div class="pagination"><span>Showing ${(meta.current_page-1)*meta.per_page+1}–${Math.min(meta.current_page*meta.per_page,meta.total)} of ${meta.total} records</span><div class="pagination-actions"><button class="page-button" data-page="${meta.current_page-1}" data-action="${action}" ${meta.current_page<=1?'disabled':''}>‹</button><span class="page-button active">${meta.current_page}</span><button class="page-button" data-page="${meta.current_page+1}" data-action="${action}" ${meta.current_page>=meta.last_page?'disabled':''}>›</button></div></div>`;
  }

  function statusChartHtml(rows) {
    const total = rows.reduce((sum,row)=>sum+Number(row.total||0),0);
    const colors = ['#2563EB','#5892F2','#8AB0F1','#B8CDF1','#DCE7F6'];
    let cursor=0; const gradient = total ? rows.map((row,i)=>{const start=cursor;cursor+=Number(row.total||0)/total*360;return `${colors[i%colors.length]} ${start}deg ${cursor}deg`;}).join(',') : '#E8EEF8 0deg 360deg';
    const legend = rows.map((row,i)=>`<div class="legend-item"><span class="legend-swatch" style="background:${colors[i%colors.length]}"></span><span>${esc(row.status)}</span><strong style="margin-left:auto;color:var(--navy)">${row.total}</strong></div>`).join('');
    return `<div class="status-chart"><div class="donut" style="background:conic-gradient(${gradient || '#E8EEF8 0deg 360deg'})"><div class="donut-center"><strong>${total}</strong><span>shipments</span></div></div><div class="legend-list">${legend || '<div class="muted small">No status data yet.</div>'}</div></div>`;
  }
  function carrierChartHtml(rows) {
    const max=Math.max(1,...rows.map(x=>Number(x.total||0)));
    return `<div class="chart-list">${rows.slice(0,6).map(row=>`<div class="chart-row"><span>${esc(row.name)}</span><div class="bar-track"><div class="bar-fill" style="width:${Number(row.total||0)/max*100}%"></div></div><span class="bar-value">${row.total}</span></div>`).join('') || '<div class="muted small">No carrier activity yet.</div>'}</div>`;
  }
  function exceptionListHtml(items, readOnly=false) {
    if(!items?.length) return `<div class="empty-state"><div class="empty-icon">${icon('check')}</div><strong>No open exceptions</strong><p>There are no unresolved disruptions in this view.</p></div>`;
    return items.map(item=>{
      const shipment=item.shipment || {};
      const title=item.title || human(item.exception_type || 'Exception');
      return `<div class="exception-card"><div class="exception-card-head"><div><div class="strong" style="font-size:10px">${esc(shipment.shipment_number || item.shipment_number || 'Shipment')}</div><div class="cell-secondary">${esc(title)}</div></div><div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap">${severityBadge(item.severity)}${exceptionState(item.status)}</div></div><p>${esc(item.description || 'No description provided.')}</p>${!readOnly && state.user.role==='operator' ? `<div style="display:flex;justify-content:flex-end;margin-top:8px"><button class="btn-link" data-action="manage-exception" data-id="${item.id}">Manage exception →</button></div>` : ''}</div>`;
    }).join('');
  }
  function shipmentTable(items, options={}) {
    const customerView=state.user.role==='customer';
    const rows=(items||[]).map(s=>{
      const eta=etaAssessment(s); const etaTime=s.status==='DELIVERED'?s.delivered_at:(s.current_eta||s.expected_arrival_at);
      return `<tr>
        <td><button class="btn-link cell-primary" data-action="view-shipment" data-id="${s.id}">${esc(s.shipment_number)}</button><div class="cell-secondary">${esc(carrierName(s))}</div></td>
        ${customerView?'':`<td><span class="strong">${esc(customerName(s))}</span><div class="cell-secondary">${esc(s.customer_reference || 'No customer reference')}</div></td>`}
        <td>${esc(routeName(s))}<div class="cell-secondary">${esc(s.origin?.country || '')}${s.origin?.country && s.destination?.country?' → ':''}${esc(s.destination?.country || '')}</div></td>
        <td>${statusBadge(s.status)}</td>
        <td class="cell-nowrap">${dateFmt(etaTime,{time:true,zone:false})}<div class="cell-secondary">UTC</div></td>
        <td>${s.status==='DELIVERED'?'<span class="severity-badge severity-on_time">On time</span>':(eta.delayed?`<span class="severity-badge severity-${eta.severity.toLowerCase()}">Delayed +${Math.round(eta.hours*10)/10}h</span>`:'<span class="severity-badge severity-on_time">On time</span>')}</td>
        <td><button class="btn-link" data-action="view-shipment" data-id="${s.id}">View ${icon('chevron')}</button></td>
      </tr>`;
    }).join('');
    const headers=`<th>Shipment / carrier</th>${customerView?'':'<th>Customer / reference</th>'}<th>${customerView?'Route':'Customer / route'}</th><th>Status</th><th>ETA / delivered · UTC</th><th>Delay</th><th></th>`;
    return `<div class="table-wrap"><table class="data-table"><thead><tr>${headers}</tr></thead><tbody>${rows || tableEmpty('No shipments match your filters.')}</tbody></table></div>`;
  }
  async function renderDashboard(host) {
    const response=await api('/dashboard'); const data=response.data||{}; const stats=data.stats||{};
    const customer=state.user.role==='customer';
    const title=customer?'Your shipments, in view':state.user.role==='operator'?'Operations overview':'Global overview';
    const subtitle=customer?`Welcome back, ${state.user.name}. Here’s the latest for your organization.`:state.user.role==='operator'?`Good afternoon, ${state.user.name}. Here’s what needs your attention today.`:'Your network at a glance. Every shipment, every exception, one clear picture.';
    const action=state.user.role==='operator'?actionBtn('Create shipment','go-create','plus'):customer?actionBtn('View my shipments','go-shipments','arrow') : '';
    const cards=customer
      ? [statCard('My shipments',stats.total_shipments||0,`${state.user.name} only`,'shipments'),statCard('In transit',stats.in_transit||0,`Of ${stats.total_shipments||0} shipments`,'tracking'),statCard('Delivered',stats.delivered||0,`Of ${stats.total_shipments||0} shipments`,'check'),statCard('Delayed',stats.delayed||0,`${stats.active_shipments?Math.round((stats.delayed/stats.active_shipments)*100):0}% of active shipments`,'clock')]
      : [statCard('Total shipments',stats.total_shipments||0,'All-time shipment portfolio','shipments'),statCard('In transit',stats.in_transit||0,`${stats.total_shipments?((stats.in_transit/stats.total_shipments)*100).toFixed(1):'0.0'}% of total shipments`,'tracking'),statCard('Delivered',stats.delivered||0,`${stats.total_shipments?((stats.delivered/stats.total_shipments)*100).toFixed(1):'0.0'}% of total shipments`,'check'),statCard('Delayed',stats.delayed||0,`${stats.delayed||0} of ${stats.active_shipments||0} active`,'clock'),statCard('Open exceptions',stats.open_exceptions||0,'OPEN + INVESTIGATING','exceptions')];
    const recent=data.recent_shipments||[];
    const delayed=data.delayed_shipments||[];
    const exceptions=data.open_exceptions||[];
    const byStatus=data.charts?.shipments_by_status||[];
    const byCarrier=data.charts?.shipments_by_carrier||[];
    const orgAlert=customer ? (delayed.length ? `<div class="notice warning"><span class="notice-icon">${icon('info')}</span><div><strong>${esc(delayed[0].shipment_number)} · Revised arrival</strong><br>Current ETA: ${dateFmt(delayed[0].current_eta||delayed[0].expected_arrival_at,{year:true})}. Your operations team is monitoring this shipment.</div></div>`:'') : '';
    const delayedPanel=customer?'':`<section class="panel"><div class="panel-head"><div><h3 class="panel-title">Needs attention</h3><p class="panel-subtitle">${delayed.length} delayed shipments · ranked by delay</p></div><button class="panel-action" data-nav="shipments">View delayed →</button></div>${delayed.length?delayed.slice(0,4).map(s=>{const eta=etaAssessment(s);return `<div class="exception-card"><div class="exception-card-head"><div><button class="btn-link cell-primary" data-action="view-shipment" data-id="${s.id}">${esc(s.shipment_number)}</button><div class="cell-secondary">${esc(customerName(s))} · ${esc(routeName(s))}</div></div><div style="text-align:right">${severityBadge(eta.severity)}<div class="cell-secondary">+${Math.round(eta.hours*10)/10}h</div></div></div></div>`}).join(''):'<div class="muted small">No delayed shipments.</div>'}</section>`;
    const quickActions=state.user.role==='operator'?`<section class="panel"><div class="panel-head"><div><h3 class="panel-title">Quick actions</h3><p class="panel-subtitle">Keep your shipment data moving</p></div></div><div class="info-list"><button class="btn btn-secondary" data-nav="create" style="justify-content:flex-start">${icon('plus')} Create shipment</button><button class="btn btn-secondary" data-nav="tracking" style="justify-content:flex-start">${icon('tracking')} Add tracking event</button><button class="btn btn-secondary" data-action="go-shipments" style="justify-content:flex-start">${icon('clock')} Update ETA</button></div><div class="notice" style="margin:15px 0 0"><span class="notice-icon">${icon('info')}</span><div><strong>Data freshness</strong><br>Latest saved updates are shown from the API.</div></div></section>`:
      customer?`<section class="panel"><div class="panel-head"><div><h3 class="panel-title">Your account</h3><p class="panel-subtitle">Organization and shipment scope</p></div></div><div class="avatar" style="height:44px;width:44px;margin-bottom:10px">${esc(initial(state.user.name))}</div><div class="strong">${esc(state.user.name)}</div><div class="cell-secondary">${esc(state.user.email)}</div><div class="notice" style="margin-top:14px"><span class="notice-icon">${icon('info')}</span><div>Need help with a shipment? Contact your CargoTrace operations team.</div></div></section>`:
      `<section class="panel"><div class="panel-head"><div><h3 class="panel-title">Delay rate</h3><p class="panel-subtitle">Active shipment delays</p></div></div><div class="metric-large">${Number(stats.delay_rate||0).toFixed(1)}%</div><div class="metric-sub">${stats.delayed||0} delayed / ${stats.active_shipments||0} active shipments</div><div class="bar-track" style="height:9px;margin-top:14px"><div class="bar-fill" style="width:${Math.min(100,stats.delay_rate||0)}%"></div></div><div style="margin-top:17px;padding-top:14px;border-top:1px solid var(--line)"><div class="muted tiny">Exception rate</div><div class="metric-large" style="font-size:21px;margin-top:5px">${Number(stats.exception_rate||0).toFixed(1)}%</div><div class="metric-sub">${stats.open_exceptions||0} unresolved exceptions in this portfolio</div></div></section>`;
    const exceptionPanel=`<section class="panel"><div class="panel-head"><div><h3 class="panel-title">Open exceptions</h3><p class="panel-subtitle">${exceptions.length} unresolved · prioritize high-severity disruptions</p></div><span class="tiny muted">${state.user.role==='admin'?'Read-only overview':'Manage exceptions'}</span></div>${exceptionListHtml(exceptions,state.user.role==='admin')}</section>`;
    const recentPanel=`<section class="panel section-spacer"><div class="panel-head"><div><h3 class="panel-title">Recent shipments</h3><p class="panel-subtitle">Latest created shipments across ${customer?'your account':'the authorized portfolio'}</p></div><button class="panel-action" data-nav="shipments">View all shipments →</button></div>${shipmentTable(recent)}</section>`;
    const customerNotifications=`<section class="panel"><div class="panel-head"><div><h3 class="panel-title">Unread notifications</h3><p class="panel-subtitle">${stats.unread_notifications||0} updates waiting for you</p></div><button class="panel-action" data-nav="notifications">View notifications →</button></div>${await customerNotificationPreview()}</section>`;
    const customerAccess=`<section class="panel"><div class="panel-head"><div><h3 class="panel-title">Your account</h3><p class="panel-subtitle">Managed by your administrator</p></div></div><div class="avatar" style="height:44px;width:44px;margin-bottom:10px">${esc(initial(state.user.name))}</div><div class="strong">${esc(state.user.name)}</div><div class="cell-secondary">${esc(state.user.email)}</div><div class="notice" style="margin-top:14px"><span class="notice-icon">${icon('info')}</span><div>Need help with a shipment? Contact your CargoTrace operations team.</div></div></section>`;
    const adminWidgets=`<div class="grid-3 section-spacer"><section class="panel"><div class="panel-head"><div><h3 class="panel-title">Shipments by status</h3><p class="panel-subtitle">${stats.total_shipments||0} shipments · current lifecycle</p></div></div>${statusChartHtml(byStatus)}</section><section class="panel"><div class="panel-head"><div><h3 class="panel-title">Shipments by carrier</h3><p class="panel-subtitle">All-time volume · ${stats.total_shipments||0} shipments</p></div></div>${carrierChartHtml(byCarrier)}</section>${quickActions}</div><div class="grid-2 section-spacer">${delayedPanel}${exceptionPanel}</div>${recentPanel}`;
    const operatorWidgets=`<div class="grid-2-1 section-spacer">${delayedPanel}${quickActions}</div>${recentPanel}<div class="section-spacer">${exceptionPanel}</div>`;
    const customerWidgets=`${recentPanel}<div class="grid-2 section-spacer">${customerNotifications}${customerAccess}</div>`;
    host.innerHTML = `${heading(state.user.role==='admin'?'Global operations':state.user.role==='operator'?'Operations':'Customer portal',title,subtitle,action)}${orgAlert}<div class="stats-grid ${customer?'customer-stats':''}">${cards.join('')}</div>${customer?customerWidgets:state.user.role==='operator'?operatorWidgets:adminWidgets}`;
    bindCommonActions(host);
  }
  async function customerNotificationPreview(){
    const response=await api('/notifications?per_page=3'); const items=response.data||[];
    if(!items.length)return '<div class="muted small">No notifications yet.</div>';
    return items.map(n=>`<div class="notification-item"><div class="notification-icon">${icon('bell')}</div><div class="notification-main"><strong>${esc(n.title)}</strong><p>${esc(n.message)}</p><span class="notification-meta">${dateFmt(n.created_at,{time:true})}</span></div>${!n.read_at?'<span class="unread-dot"></span>':''}</div>`).join('');
  }
  function bindCommonActions(host=document) {
    host.querySelectorAll('[data-action="view-shipment"]').forEach(btn=>btn.addEventListener('click',()=>viewShipment(btn.dataset.id)));
    host.querySelectorAll('[data-nav]').forEach(btn=>btn.addEventListener('click',()=>navigate(btn.dataset.nav)));
    host.querySelectorAll('[data-action="go-create"]').forEach(btn=>btn.addEventListener('click',()=>navigate('create')));
    host.querySelectorAll('[data-action="go-shipments"]').forEach(btn=>btn.addEventListener('click',()=>navigate('shipments')));
    host.querySelectorAll('[data-action="manage-exception"]').forEach(btn=>btn.addEventListener('click',()=>manageException(btn.dataset.id)));
  }

  async function renderShipments(host) {
    const isCustomer=state.user.role==='customer';
    const isOperator=state.user.role==='operator';
    const query={search:state.filters.search||'',status:state.filters.status||'',carrier_id:state.filters.carrier_id||'',delayed:state.filters.delayed||'',per_page:15,page:state.currentPage};
    const [response,carrierResponse]=await Promise.all([api(`/shipments${queryString(query)}`),api('/carriers?per_page=100').catch(()=>({data:[]}))]);
    const {items,meta}=paginator(response); const carriers=carrierResponse.data||[];
    const toolbar=`<form id="shipment-filter-form" class="toolbar"><div class="searchbox">${icon('search')}<input class="control" name="search" placeholder="Search shipment ID or cargo" value="${esc(query.search)}"></div><select class="control" name="status"><option value="">All statuses</option>${STATUS_FLOW.map(s=>`<option value="${s}" ${query.status===s?'selected':''}>${s}</option>`).join('')}</select>${!isCustomer?`<select class="control" name="carrier_id"><option value="">All carriers</option>${carriers.map(c=>`<option value="${c.id}" ${String(query.carrier_id)===String(c.id)?'selected':''}>${esc(c.name)}</option>`).join('')}</select>`:''}<select class="control" name="delayed"><option value="">Delayed: all</option><option value="1" ${query.delayed==='1'?'selected':''}>Delayed only</option><option value="0" ${query.delayed==='0'?'selected':''}>Not delayed</option></select><button class="btn btn-secondary btn-sm" type="submit">${icon('filter')} Filter</button><button class="btn btn-quiet btn-sm" type="button" id="clear-shipment-filters">Clear</button></form>`;
    const title=isCustomer?'My shipments':'Shipments';
    host.innerHTML=`${heading(isCustomer?'Customer portal':isOperator?'Operations':'Global operations',title,isCustomer?'Shipment visibility for your account. Track progress and plan with confidence.':'Plan, monitor and manage every shipment across your network.',isOperator?actionBtn('Create shipment','go-create','plus'):isCustomer?'':`<span class="tiny muted">Global read-only portfolio</span>`)}
      ${isCustomer?`<div class="notice"><span class="notice-icon">${icon('info')}</span><div><strong>Customer view</strong><br>Only shipments assigned to your account are shown. Shipment changes are managed by the operations team.</div></div>`:`<div class="notice"><span class="notice-icon">${icon('info')}</span><div><strong>Shipment portfolio</strong><br>${meta.total} shipments · ${isOperator?'Operator access includes shipment operations, tracking and ETA changes.':'Administrator view is read-only for operational shipment data.'}</div></div>`}
      <section class="panel"><div class="panel-head"><div><h3 class="panel-title">${isCustomer?'Your shipment portfolio':'Shipment directory'}</h3><p class="panel-subtitle">${meta.total} shipments · ${isCustomer?'account-scoped visibility':'all customers in the authorized portfolio'}</p></div><div class="tiny muted">${meta.total} records</div></div>
      <div class="toolbar" style="margin-bottom:12px"><button class="btn btn-quiet btn-sm" data-filter-tab="">All shipments (${meta.total})</button><button class="btn btn-secondary btn-sm" data-filter-tab="ontime">On time</button><button class="btn btn-secondary btn-sm" data-filter-tab="delayed">Delayed</button><button class="btn btn-secondary btn-sm" data-filter-tab="delivered">Delivered</button></div>${toolbar}${shipmentTable(items)}${paginatorHtml(meta,'shipments-page')}</section>`;
    document.getElementById('shipment-filter-form').addEventListener('submit',async e=>{e.preventDefault();const f=new FormData(e.currentTarget);state.filters={...state.filters,search:f.get('search'),status:f.get('status'),carrier_id:f.get('carrier_id')||'',delayed:f.get('delayed')};state.currentPage=1;await renderPage();});
    document.getElementById('clear-shipment-filters').addEventListener('click',async()=>{state.filters={};state.currentPage=1;await renderPage();});
    host.querySelectorAll('[data-filter-tab]').forEach(btn=>btn.addEventListener('click',async()=>{
      const tab=btn.dataset.filterTab;state.filters={...state.filters,status:tab==='delivered'?'DELIVERED':'',delayed:tab==='delayed'?'1':tab==='ontime'?'0':''};state.currentPage=1;await renderPage();
    }));
    bindCommonActions(host);
    host.querySelectorAll('[data-action="shipments-page"]').forEach(btn=>btn.addEventListener('click',async()=>{state.currentPage=Number(btn.dataset.page);await renderPage();}));
  }
  async function loadMasterData(){
    const [customers,carriers,locations]=await Promise.all([
      api('/customers?per_page=100').catch(()=>({data:[]})),
      api('/carriers?active_only=1&per_page=100').catch(()=>({data:[]})),
      api('/locations?per_page=100').catch(()=>({data:[]})),
    ]);
    return {customers:customers.data||[],carriers:carriers.data||[],locations:locations.data||[]};
  }
  function locationOption(location){return `<option value="${location.id}">${esc(location.name)}${location.city?` · ${esc(location.city)}`:''}${location.country?`, ${esc(location.country)}`:''}${location.code?` · ${esc(location.code)}`:''}</option>`;}
  function carrierOption(carrier){return `<option value="${carrier.id}">${esc(carrier.name)} · ${esc(carrier.code)}</option>`;}
  async function renderShipmentForm(host, existing=null) {
    const isEdit=!!existing;
    const master=await loadMasterData();
    const selected=(field,id)=>String(existing?.[field]||'')===String(id)?'selected':'';
    const dateNow=inputDate(existing?.expected_departure_at || new Date(Date.now()+86400000).toISOString());
    const arrival=inputDate(existing?.expected_arrival_at || new Date(Date.now()+7*86400000).toISOString());
    host.innerHTML=`${heading('Operations',isEdit?'Edit shipment':'Create shipment',isEdit?`${existing.shipment_number} · Update shipment details.`:'Start with the essentials. Tracking and exceptions can be added after creation.',`<button class="btn btn-secondary" data-action="back-shipments">${icon('back')} Back to shipments</button>`)}
      <div class="grid-2-1"><section class="panel"><form id="shipment-create-form">
        <div class="panel-head"><div><h3 class="panel-title">Customer &amp; carrier</h3><p class="panel-subtitle">Required fields are marked with *</p></div></div>
        <div class="form-grid">
          ${!isEdit?`<div class="form-field"><label>Customer <span class="req">*</span></label><select name="customer_id" required><option value="">Select customer</option>${master.customers.map(c=>`<option value="${c.id}">${esc(c.name)} · ${esc(c.email)}</option>`).join('')}</select></div>`:`<div class="form-field"><label>Customer</label><input value="${esc(existing.customer?.name||'')}" disabled></div>`}
          <div class="form-field"><label>Carrier <span class="req">*</span></label><select name="carrier_id" required><option value="">Select carrier</option>${master.carriers.map(c=>`<option value="${c.id}" ${selected('carrier_id',c.id)}>${esc(c.name)} · ${esc(c.code)}</option>`).join('')}</select></div>
          <div class="form-field full"><label>Customer reference</label><input name="customer_reference" value="${esc(existing?.customer_reference||'')}" placeholder="e.g. PO-2026-0048"></div>
        </div>
        <div class="panel-head" style="margin-top:12px"><div><h3 class="panel-title">Route &amp; schedule</h3><p class="panel-subtitle">Use UTC for expected departure and arrival times.</p></div></div>
        <div class="form-grid">
          <div class="form-field"><label>Origin <span class="req">*</span></label><select name="origin_location_id" required><option value="">Select origin</option>${master.locations.map(l=>`<option value="${l.id}" ${selected('origin_location_id',l.id)}>${esc(l.name)} · ${esc(l.code||l.city||l.country||'')}</option>`).join('')}</select></div>
          <div class="form-field"><label>Destination <span class="req">*</span></label><select name="destination_location_id" required><option value="">Select destination</option>${master.locations.map(l=>`<option value="${l.id}" ${selected('destination_location_id',l.id)}>${esc(l.name)} · ${esc(l.code||l.city||l.country||'')}</option>`).join('')}</select></div>
          <div class="form-field"><label>Expected departure · UTC <span class="req">*</span></label><input name="expected_departure_at" type="datetime-local" required value="${dateNow}"></div>
          <div class="form-field">${isEdit?`<label>Expected arrival · original / locked</label><input value="${dateFmt(existing.expected_arrival_at,{time:true})}" disabled><small>Use Update ETA to revise the current estimate. The original commitment stays unchanged.</small>`:`<label>Expected arrival · UTC <span class="req">*</span></label><input name="expected_arrival_at" type="datetime-local" required value="${arrival}">`}</div>
        </div>
        <div class="panel-head" style="margin-top:10px"><div><h3 class="panel-title">Cargo details</h3><p class="panel-subtitle">Weight and volume must be greater than zero.</p></div></div>
        <div class="form-grid">
          <div class="form-field full"><label>Cargo type <span class="req">*</span></label><input name="cargo_type" required value="${esc(existing?.cargo_type||'')}" placeholder="e.g. Consumer goods"></div>
          <div class="form-field"><label>Package count <span class="req">*</span></label><input name="package_count" type="number" min="1" required value="${esc(existing?.package_count||1)}"></div>
          <div class="form-field"><label>Weight <span class="req">*</span></label><div style="display:flex;gap:6px"><input name="total_weight" type="number" step="0.001" min="0.001" required value="${esc(existing?.total_weight||'')}" placeholder="0.000"><select name="weight_unit" style="max-width:85px"><option ${existing?.weight_unit==='KG'?'selected':''}>KG</option><option ${existing?.weight_unit==='LB'?'selected':''}>LB</option></select></div></div>
          <div class="form-field"><label>Volume <span class="req">*</span></label><div style="display:flex;gap:6px"><input name="total_volume" type="number" step="0.001" min="0.001" required value="${esc(existing?.total_volume||'')}" placeholder="0.000"><select name="volume_unit" style="max-width:85px"><option ${existing?.volume_unit==='CBM'?'selected':''}>CBM</option><option ${existing?.volume_unit==='FT3'?'selected':''}>FT3</option></select></div></div>
          <div class="form-field full"><label>Cargo description</label><textarea name="cargo_description" placeholder="Optional customer-friendly cargo description">${esc(existing?.cargo_description||'')}</textarea></div>
          <div class="form-field full"><label>Internal notes</label><textarea name="notes" placeholder="Operational notes">${esc(existing?.notes||'')}</textarea><small>Internal notes are not shown in the customer view.</small></div>
        </div>
        <div class="form-actions"><button type="button" class="btn btn-secondary" data-action="back-shipments">Cancel</button><button class="btn" type="submit" id="save-shipment">${isEdit?'Save changes':'Create shipment'}</button></div>
      </form></section>
      <aside class="panel"><div class="panel-head"><div><h3 class="panel-title">Shipment preview</h3><p class="panel-subtitle">${isEdit?'Current saved shipment':'New shipment · not yet saved'}</p></div></div><div class="exception-card"><span class="status-badge status-created">${esc(existing?.status||'CREATED')}</span><div class="strong" style="margin-top:10px">${esc(existing?.shipment_number||'New shipment')}</div><div class="cell-secondary">${esc(existing?.customer?.name||'Select a customer')}</div><div class="route-summary" style="margin-top:12px"><div><div class="route-place">${esc(existing?.origin?.city||'Origin')}</div><div class="route-city">Origin</div></div><div class="route-arrow">→</div><div><div class="route-place">${esc(existing?.destination?.city||'Destination')}</div><div class="route-city">Destination</div></div></div></div><div class="notice" style="margin-top:14px"><span class="notice-icon">${icon('info')}</span><div><strong>What happens next?</strong><br>${isEdit?'Changes are saved to the shipment record and an audit entry is retained.':'Creating a shipment sets its lifecycle to CREATED and adds the initial tracking event.'}</div></div><div class="notice"><span class="notice-icon">${icon('check')}</span><div><strong>Role boundary</strong><br>Only Logistics Operators can create and edit shipment records. Customer visibility remains read-only.</div></div></aside></div>`;
    host.querySelectorAll('[data-action="back-shipments"]').forEach(btn=>btn.addEventListener('click',()=>navigate('shipments')));
    document.getElementById('shipment-create-form').addEventListener('submit',async e=>{
      e.preventDefault(); const form=e.currentTarget; const f=new FormData(form); const submit=document.getElementById('save-shipment'); submit.disabled=true;submit.textContent='Saving…';
      const body={
        ...(isEdit?{}:{customer_id:Number(f.get('customer_id'))}),carrier_id:Number(f.get('carrier_id')),
        origin_location_id:Number(f.get('origin_location_id')),destination_location_id:Number(f.get('destination_location_id')),
        customer_reference:f.get('customer_reference')||null,cargo_type:f.get('cargo_type').trim(),cargo_description:f.get('cargo_description')||null,
        package_count:Number(f.get('package_count')),total_weight:Number(f.get('total_weight')),weight_unit:f.get('weight_unit'),total_volume:Number(f.get('total_volume')),volume_unit:f.get('volume_unit'),
        expected_departure_at:localIso(f.get('expected_departure_at')),
        notes:f.get('notes')||null,
      };
      if(!isEdit) body.expected_arrival_at=localIso(f.get('expected_arrival_at'));
      try{
        const result=await api(isEdit?`/shipments/${existing.id}`:'/shipments',{method:isEdit?'PATCH':'POST',body});
        showToast(isEdit?'Shipment updated successfully.':'Shipment created successfully.');
        const shipment=result.data; if(shipment?.id) await viewShipment(shipment.id); else navigate('shipments');
      }catch(err){showToast(err.message,'error');submit.disabled=false;submit.textContent=isEdit?'Save changes':'Create shipment';}
    });
  }
  async function viewShipment(id){try{const response=await api(`/shipments/${id}`);state.selectedShipment=response.data;state.page='detail';state.pageTitle=titleFor('detail');document.getElementById('breadcrumb-current').textContent=state.pageTitle;ROOT.querySelectorAll('.nav-link').forEach(b=>b.classList.toggle('active',b.dataset.nav==='shipments'));await renderPage();}catch(err){showToast(err.message,'error');}}

  function lifecycleHtml(shipment){
    const current=STATUS_FLOW.indexOf(shipment.status); const fields=['created_at','booked_at','picked_up_at','in_transit_at','delivered_at'];
    return `<div class="lifecycle">${STATUS_FLOW.map((status,i)=>`<div class="life-step ${i<current?'done':i===current?'current':''}"><div class="life-track"><span style="width:${i<current?100:i===current?55:0}%"></span></div><div class="life-name">${status}</div><div class="life-date">${dateFmt(shipment[fields[i]],{time:false,zone:false}) || (i===current?'Current':'Pending')}</div></div>`).join('')}</div>`;
  }
  function eventHtml(event,index){
    const loc=event.location?`${event.location.name || event.location.city || 'Location'}${event.location.country?', '+event.location.country:''}`:(event.latitude!=null?`${Number(event.latitude).toFixed(4)}, ${Number(event.longitude).toFixed(4)}`:'Location not recorded');
    return `<div class="timeline-event"><div class="timeline-rail"><div class="timeline-dot"></div><div class="timeline-line"></div></div><div class="timeline-main"><strong>${esc(event.status || human(event.event_type||'Tracking event'))}</strong><div class="cell-secondary">${esc(loc)}</div><p>${esc(event.description||'')}</p><small>${esc(event.creator?.name||'CargoTrace')} · ${esc(event.event_type||'')}</small></div><div class="timeline-time">${dateFmt(event.event_time,{time:true})}</div></div>`;
  }
  async function renderShipmentDetail(host){
    let s=state.selectedShipment; if(!s)return navigate('shipments');
    const events=(s.tracking_events||[]).slice().sort((a,b)=>new Date(b.event_time)-new Date(a.event_time));
    const exceptions=s.exceptions||[]; const delivered=s.status==='DELIVERED';
    const rawEta=s.eta_assessment;
    const eta=rawEta?{...rawEta,delayed:Boolean(rawEta.is_delayed),hours:Number(rawEta.delay_hours||0),severity:rawEta.delay_severity||'ON_TIME'}:etaAssessment(s);
    const canOperate=state.user.role==='operator';
    const action=canOperate?`<button class="btn btn-secondary btn-sm" data-action="edit-shipment">${icon('edit')} Edit shipment</button><button class="btn btn-secondary btn-sm" data-action="update-status" ${delivered?'disabled':''}>Update status</button><button class="btn btn-sm" data-action="add-tracking">${icon('plus')} Add tracking event</button><button class="btn btn-secondary btn-sm" data-action="update-eta" ${delivered?'disabled':''}>Update ETA</button><button class="btn btn-secondary btn-sm" data-action="create-exception">Manage exception</button>`:`<button class="btn btn-secondary btn-sm" data-action="back-shipments">${icon('back')} ${state.user.role==='customer'?'Back to my shipments':'Back to shipments'}</button>`;
    host.innerHTML=`${heading(state.user.role==='customer'?'Customer portal':state.user.role==='admin'?'Administration':'Operations',s.shipment_number,`${esc(customerName(s))} · ${esc(s.customer_reference||'No customer reference')} · Created ${dateFmt(s.created_at,{time:false,zone:false})}`,action)}
      <div class="eta-grid">
        <div class="eta-card"><div class="eta-label">Expected arrival · original commitment</div><div class="eta-value">${dateFmt(s.expected_arrival_at,{time:true,zone:false})}</div><div class="eta-note">Original schedule · UTC</div></div>
        <div class="eta-card"><div class="eta-label">${delivered?'Actual delivery time':`Current ETA · ${s.current_eta?'revised':'original estimate'}`}</div><div class="eta-value">${dateFmt(delivered?(s.delivered_at||s.current_eta||s.expected_arrival_at):(s.current_eta||s.expected_arrival_at),{time:true,zone:false})}</div><div class="eta-note">${delivered?'Recorded completion · UTC':esc(s.destination?.name || s.destination?.city || 'Destination')}</div></div>
        <div class="eta-card"><div class="eta-label">${delivered?'Delivery outcome':'Delay · derived from ETA'}</div>${delivered?'<div class="eta-value">Delivered</div><div class="eta-note">Active ETA delay tracking ends at delivery.</div>':`<div style="display:flex;align-items:center;gap:9px"><div class="eta-value">${eta.delayed?`+${Math.round((eta.hours||0)*10)/10}h`:'On time'}</div>${severityBadge(eta.severity)}</div><div class="eta-note">Current ETA − original expected arrival</div>`}</div>
      </div>
      <section class="panel section-spacer"><div class="panel-head"><div><h3 class="panel-title">Shipment lifecycle</h3><p class="panel-subtitle">Sequential lifecycle · delivery is terminal in the normal workflow</p></div>${statusBadge(s.status)}</div>${lifecycleHtml(s)}</section>
      <section class="panel section-spacer" style="padding:0"><div class="map-top"><div><strong>${esc(routeName(s))}</strong><div class="cell-secondary">Recorded checkpoints · select a marker for details</div></div><span>Route view · ${events.length} saved events</span></div>${buildMapContainer(s,events)}<div class="map-legend"><span><i class="legend-dot"></i> Origin</span><span><i class="legend-dot" style="background:#0E9B88"></i> Recorded checkpoint</span><span><i class="legend-dot" style="background:var(--navy)"></i> Destination</span><span>Not live GPS</span></div></section>
      <div class="detail-grid"><section class="panel"><div class="panel-head"><div><h3 class="panel-title">Tracking history</h3><p class="panel-subtitle">${events.length} recorded events · newest first · all times UTC</p></div><span class="tiny muted">Append-only history</span></div><div class="timeline">${events.length?events.map(eventHtml).join(''):'<div class="empty-state"><div class="empty-icon">'+icon('tracking')+'</div><strong>No tracking events yet</strong><p>Recorded events will appear here in chronological order.</p></div>'}</div></section>
      <aside style="display:grid;gap:13px"><section class="panel"><div class="panel-head"><div><h3 class="panel-title">Shipment information</h3><p class="panel-subtitle">Customer, carrier and cargo details</p></div></div><div class="info-list"><div class="info-row"><span>Customer</span><strong>${esc(customerName(s))}</strong></div><div class="info-row"><span>Carrier</span><strong>${esc(carrierName(s))} · ${esc(s.carrier?.code||'')}</strong></div><div class="info-row"><span>Cargo type</span><strong>${esc(s.cargo_type||'—')}</strong></div><div class="info-row"><span>Weight / volume</span><strong>${fmtNumber(s.total_weight)} ${esc(s.weight_unit||'KG')} / ${fmtNumber(s.total_volume)} ${esc(s.volume_unit||'CBM')}</strong></div><div class="info-row"><span>Packages</span><strong>${fmtNumber(s.package_count)}</strong></div><div class="info-row"><span>Expected departure</span><strong>${dateFmt(s.expected_departure_at,{time:true})}</strong></div><div class="info-row"><span>Customer reference</span><strong>${esc(s.customer_reference||'—')}</strong></div><div class="info-row"><span>Booking reference</span><strong>${esc(s.booking_reference||'—')}</strong></div></div></section>
      <section class="panel"><div class="panel-head"><div><h3 class="panel-title">Relevant exceptions</h3><p class="panel-subtitle">${exceptions.length} total · severity is independent from ETA delay</p></div>${canOperate?`<button class="panel-action" data-action="create-exception">Manage →</button>`:''}</div>${exceptions.length?exceptions.map(e=>`<div class="exception-card"><div class="exception-card-head"><strong style="font-size:10px;color:var(--navy)">${esc(e.title||human(e.exception_type))}</strong>${severityBadge(e.severity)}</div><div style="margin-top:7px">${exceptionState(e.status)}</div><p>${esc(e.description||'')}</p>${canOperate?`<button class="btn-link" data-action="manage-exception" data-id="${e.id}">Manage exception →</button>`:''}</div>`).join(''):'<div class="muted small">No exceptions recorded for this shipment.</div>'}</section></aside></div>`;
    host.querySelector('[data-action="back-shipments"]')?.addEventListener('click',()=>navigate('shipments'));
    host.querySelector('[data-action="edit-shipment"]')?.addEventListener('click',()=>renderShipmentForm(host,s));
    host.querySelector('[data-action="update-status"]')?.addEventListener('click',()=>openStatusDrawer(s));
    host.querySelector('[data-action="add-tracking"]')?.addEventListener('click',()=>openTrackingDrawer(s));
    host.querySelector('[data-action="update-eta"]')?.addEventListener('click',()=>openEtaDrawer(s));
    host.querySelectorAll('[data-action="create-exception"]').forEach(b=>b.addEventListener('click',()=>openExceptionDrawer(s)));
    host.querySelectorAll('[data-action="manage-exception"]').forEach(b=>b.addEventListener('click',()=>manageException(b.dataset.id,s)));
    bindCommonActions(host);
    initMap(s,events);
  }
  function buildMapContainer(s,events){
    const validPair=(lat,lng)=>lat!==null&&lat!==undefined&&lng!==null&&lng!==undefined&&Number.isFinite(Number(lat))&&Number.isFinite(Number(lng));
    const hasKnown=validPair(s.origin?.latitude,s.origin?.longitude)||validPair(s.destination?.latitude,s.destination?.longitude)||events.some(e=>validPair(e.latitude,e.longitude)||validPair(e.location?.latitude,e.location?.longitude));
    if(!hasKnown)return `<div class="map-fallback-empty"><div class="empty-icon">${icon('map')}</div><strong>Coordinates missing</strong><span>This route cannot be plotted yet. Keep the tracking timeline available; only saved coordinates can be mapped.</span></div><div class="map-disclaimer">No locations have enough saved coordinates for this route.</div>`;
    if(!window.L)return `<div class="map-fallback-empty"><div class="empty-icon">${icon('map')}</div><strong>Map temporarily unavailable</strong><span>Saved coordinates and tracking history are retained. Check your connection and reload to display map tiles.</span></div><div class="map-disclaimer">The map library or tile service could not be loaded. No locations have been invented.</div>`;
    return `<div id="shipment-map" class="map-canvas"></div><div class="map-disclaimer">Recorded checkpoint map · no physical live GPS or direct carrier position feed.</div>`;
  }
  function initMap(s,events){
    const el=document.getElementById('shipment-map'); if(!el || !window.L) return;
    const points=[];
    const add=(name,lat,lng,type,time,description)=>{if(lat!==null&&lat!==undefined&&lng!==null&&lng!==undefined&&Number.isFinite(Number(lat))&&Number.isFinite(Number(lng)))points.push({name,lat:Number(lat),lng:Number(lng),type,time,description});};
    add(`${s.origin?.name||'Origin'} · Origin`,s.origin?.latitude,s.origin?.longitude,'Origin',null,'Shipment origin');
    events.slice().reverse().forEach(e=>add(e.location?.name||e.location?.city||'Recorded checkpoint',e.latitude??e.location?.latitude,e.longitude??e.location?.longitude,'Tracking event',e.event_time,e.description));
    add(`${s.destination?.name||'Destination'} · Destination`,s.destination?.latitude,s.destination?.longitude,'Destination',null,'Shipment destination');
    if(!points.length)return;
    const map=window.L.map(el,{scrollWheelZoom:false,zoomControl:true}); state.map=map;
    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
    const latlngs=[];
    points.forEach((p,i)=>{const ll=[p.lat,p.lng];latlngs.push(ll);const color=p.type==='Origin'?'#2563EB':p.type==='Destination'?'#142D4E':'#0E9B88';const marker=window.L.circleMarker(ll,{radius:p.type==='Tracking event'?6:8,color:'#fff',weight:2,fillColor:color,fillOpacity:1}).addTo(map);marker.bindPopup(`<strong>${esc(p.name)}</strong><br>${esc(p.type)}${p.time?`<br>${esc(dateFmt(p.time,{time:true}))}`:''}${p.description?`<br>${esc(p.description)}`:''}`);});
    if(latlngs.length>1)window.L.polyline(latlngs,{color:'#2563EB',weight:3,opacity:.8,dashArray:'7 6'}).addTo(map);
    map.fitBounds(latlngs,{padding:[24,24],maxZoom:5});
    setTimeout(()=>map.invalidateSize(),100);
  }

  function openDrawer(title,subtitle,bodyHtml,submitHandler,submitLabel='Save changes'){
    const root=document.getElementById('modal-root');
    root.innerHTML=`<div class="modal-layer" id="modal-layer"><aside class="modal-drawer"><div class="modal-head"><div><h2>${esc(title)}</h2><p>${esc(subtitle||'Required fields are marked *')}</p></div><button class="modal-close" data-action="close-drawer" aria-label="Close">×</button></div><div id="drawer-error"></div><form id="drawer-form">${bodyHtml}<div class="modal-footer"><button class="btn btn-secondary" type="button" data-action="close-drawer">Cancel</button><button class="btn" type="submit" id="drawer-submit">${esc(submitLabel)}</button></div></form></aside></div>`;
    const layer=document.getElementById('modal-layer');requestAnimationFrame(()=>layer.classList.add('open'));
    root.querySelectorAll('[data-action="close-drawer"]').forEach(b=>b.addEventListener('click',closeDrawer));
    layer.addEventListener('click',e=>{if(e.target===layer)closeDrawer();});
    document.getElementById('drawer-form').addEventListener('submit',async e=>{e.preventDefault();const button=document.getElementById('drawer-submit');button.disabled=true;button.textContent='Saving…';document.getElementById('drawer-error').innerHTML='';try{await submitHandler(new FormData(e.currentTarget),e.currentTarget);}catch(err){document.getElementById('drawer-error').innerHTML=`<div class="error-banner">${esc(err.message)}</div>`;button.disabled=false;button.textContent=submitLabel;}});
  }
  function closeDrawer(){const layer=document.getElementById('modal-layer');if(layer){layer.classList.remove('open');setTimeout(()=>{document.getElementById('modal-root').innerHTML='';},180);}}
  function openStatusDrawer(s){
    const index=STATUS_FLOW.indexOf(s.status);const next=STATUS_FLOW[index+1];
    if(!next){showToast('This shipment is already DELIVERED.','error');return;}
    openDrawer('Update shipment status',`${s.shipment_number} · ${s.status} → ${next}`,`<div class="notice"><span class="notice-icon">${icon('info')}</span><div>Only the next sequential lifecycle status can be selected. Saving appends a tracking event, audit record and customer notification.</div></div><div class="form-field"><label>Current status</label><input value="${esc(s.status)}" disabled></div><div class="form-field"><label>New status <span class="req">*</span></label><select name="status" required><option value="${next}">${next}</option></select></div><div class="form-field"><label>Event description</label><textarea name="description" placeholder="Add a customer-friendly status update">Status updated from ${esc(s.status)} to ${esc(next)}.</textarea></div>`,async f=>{await api(`/shipments/${s.id}/status`,{method:'PATCH',body:{status:f.get('status'),description:f.get('description')||null}});closeDrawer();showToast('Shipment status updated.');await viewShipment(s.id);},'Confirm transition');
  }
  async function openTrackingDrawer(s){
    const master=await loadMasterData(); const now=inputDate(new Date().toISOString());
    openDrawer('Add tracking event',`${s.shipment_number} · ${routeName(s)} · ${s.status}`,`<div class="notice"><span class="notice-icon">${icon('info')}</span><div>Tracking is an append-only historical checkpoint. It will inherit the shipment’s current lifecycle status. Event time must not be in the future.</div></div><div class="form-field"><label>Shipment status</label><input value="${esc(s.status)}" disabled></div><div class="form-field"><label>Event time · UTC <span class="req">*</span></label><input name="event_time" type="datetime-local" required value="${now}" max="${now}"></div><div class="form-field"><label>Saved location</label><select name="location_id"><option value="">Choose a location (optional)</option>${master.locations.map(locationOption).join('')}</select><small>Coordinates from the selected saved location will be used.</small></div><div class="form-grid"><div class="form-field"><label>Latitude</label><input name="latitude" type="number" min="-90" max="90" step="any" placeholder="Optional"></div><div class="form-field"><label>Longitude</label><input name="longitude" type="number" min="-180" max="180" step="any" placeholder="Optional"></div></div><div class="form-field"><label>Description <span class="req">*</span></label><textarea name="description" required placeholder="Describe what happened at this checkpoint."></textarea></div>`,async f=>{const lat=f.get('latitude'),lng=f.get('longitude');if((lat&&!lng)||(!lat&&lng))throw new Error('Latitude and longitude must be entered together.');const body={event_time:localIso(f.get('event_time')),description:f.get('description').trim(),location_id:f.get('location_id')?Number(f.get('location_id')):null,latitude:lat?Number(lat):null,longitude:lng?Number(lng):null};await api(`/shipments/${s.id}/tracking-events`,{method:'POST',body});closeDrawer();showToast('Tracking event added to the shipment history.');await viewShipment(s.id);},'Save tracking event');
  }
  function openEtaDrawer(s){
    const current=inputDate(s.current_eta||s.expected_arrival_at);
    openDrawer('Update ETA',`${s.shipment_number} · Original expected arrival stays unchanged`,`<div class="form-grid"><div class="form-field"><label>Expected arrival · original</label><input value="${dateFmt(s.expected_arrival_at,{time:true})}" disabled></div><div class="form-field"><label>Current ETA · saved</label><input value="${dateFmt(s.current_eta||s.expected_arrival_at,{time:true})}" disabled></div></div><div class="form-field"><label>New current ETA · UTC <span class="req">*</span></label><input name="current_eta" type="datetime-local" required value="${current}"></div><div class="form-field"><label>Reason for change</label><textarea name="reason" placeholder="Explain the revised schedule for customer visibility."></textarea></div><div class="notice"><span class="notice-icon">${icon('info')}</span><div>Delay severity is calculated from the new Current ETA against the original Expected Arrival. Exactly +24 hours remains MEDIUM. Updating ETA notifies the customer and adds an audit record.</div></div>`,async f=>{await api(`/shipments/${s.id}/eta`,{method:'PATCH',body:{current_eta:localIso(f.get('current_eta')),reason:f.get('reason')||null}});closeDrawer();showToast('Current ETA updated and customer notified.');await viewShipment(s.id);},'Update ETA');
  }
  function openExceptionDrawer(s){
    openDrawer('Add exception',`${s.shipment_number} · ${customerName(s)}`,`<div class="form-field"><label>Exception type <span class="req">*</span></label><select name="exception_type" required>${EXCEPTION_TYPES.map(t=>`<option value="${t}">${human(t)}</option>`).join('')}</select></div><div class="form-field"><label>Severity <span class="req">*</span></label><select name="severity" required><option>LOW</option><option selected>MEDIUM</option><option>HIGH</option></select></div><div class="form-field"><label>Title <span class="req">*</span></label><input name="title" required maxlength="150" placeholder="Short, customer-friendly title"></div><div class="form-field"><label>Description</label><textarea name="description" placeholder="Describe the operational disruption."></textarea></div><div class="form-field"><label>Detected at · UTC</label><input name="detected_at" type="datetime-local" value="${inputDate(new Date().toISOString())}" max="${inputDate(new Date().toISOString())}"></div><div class="notice"><span class="notice-icon">${icon('info')}</span><div>Delay does not automatically create an exception. Exception severity is selected independently from ETA delay severity. New exceptions begin as OPEN.</div></div>`,async f=>{await api(`/shipments/${s.id}/exceptions`,{method:'POST',body:{exception_type:f.get('exception_type'),severity:f.get('severity'),title:f.get('title').trim(),description:f.get('description')||null,detected_at:f.get('detected_at')?localIso(f.get('detected_at')):null}});closeDrawer();showToast('Exception created and customer notified.');await viewShipment(s.id);},'Create exception');
  }
  async function manageException(id,shipment=state.selectedShipment){
    let ex=shipment?.exceptions?.find(x=>String(x.id)===String(id));
    if(!ex){const all=await loadAllExceptions();ex=all.find(x=>String(x.id)===String(id));}
    if(!ex){showToast('Exception record was not found.','error');return;}
    const next=ex.status==='OPEN'?'INVESTIGATING':ex.status==='INVESTIGATING'?'RESOLVED':null;
    if(!next){showToast('This exception is already resolved.');return;}
    openDrawer('Manage exception',`${ex.exception_type} · ${ex.severity} · ${ex.status}`,`<div class="info-list" style="margin-bottom:14px"><div class="info-row"><span>Shipment</span><strong>${esc(ex.shipment?.shipment_number||shipment?.shipment_number||'Shipment')}</strong></div><div class="info-row"><span>Type</span><strong>${esc(human(ex.exception_type))}</strong></div><div class="info-row"><span>Severity</span><strong>${esc(ex.severity)}</strong></div></div><div class="form-field"><label>New status <span class="req">*</span></label><input value="${next}" disabled><input type="hidden" name="status" value="${next}"></div><div class="form-field"><label>${next==='RESOLVED'?'Resolution notes *':'Investigation notes'}</label><textarea name="resolution_notes" ${next==='RESOLVED'?'required':''} placeholder="${next==='RESOLVED'?'Describe the confirmed resolution.':'Add investigation or recovery notes.'}">${esc(ex.resolution_notes||'')}</textarea><small>Resolution notes are required when resolving an exception.</small></div><div class="notice"><span class="notice-icon">${icon('info')}</span><div>Allowed flow: OPEN → INVESTIGATING → RESOLVED. The customer receives an update when the status changes.</div></div>`,async f=>{await api(`/exceptions/${ex.id}/status`,{method:'PATCH',body:{status:f.get('status'),resolution_notes:f.get('resolution_notes')||null}});closeDrawer();showToast('Exception status updated and customer notified.');if(shipment?.id)await viewShipment(shipment.id);else await renderPage();},next==='RESOLVED'?'Resolve exception':'Start investigation');
  }

  async function renderUsers(host){
    const filter=state.filters.users||{};
    const response=await api(`/users${queryString({search:filter.search||'',role:filter.role||'',active:filter.active,per_page:20,page:state.currentPage})}`);const {items,meta}=paginator(response);
    const active=items.filter(u=>u.is_active).length;const roleCount=new Set(items.map(u=>u.role)).size;
    host.innerHTML=`${heading('Administration','Users','Manage workspace access and keep responsibilities clear.',actionBtn('Add user','add-user','plus'))}
      <div class="stats-grid customer-stats" style="margin-bottom:14px">${statCard('Workspace users',meta.total,'All registered accounts','users')}${statCard('Active accounts',active,'On this result page','check')}${statCard('Roles represented',roleCount,'Admin, Operator and Customer','profile')}${statCard('Current page',meta.current_page,`of ${meta.last_page} pages`,'file')}</div>
      <section class="panel"><div class="panel-head"><div><h3 class="panel-title">Directory</h3><p class="panel-subtitle">${meta.total} users · least-privilege access by role</p></div></div>
      <form id="user-filter-form" class="toolbar"><div class="searchbox">${icon('search')}<input class="control" name="search" placeholder="Search name or email" value="${esc(filter.search||'')}"></div><select class="control" name="role"><option value="">All roles</option><option value="admin" ${filter.role==='admin'?'selected':''}>Admin</option><option value="operator" ${filter.role==='operator'?'selected':''}>Operator</option><option value="customer" ${filter.role==='customer'?'selected':''}>Customer</option></select><select class="control" name="active"><option value="">All statuses</option><option value="1" ${filter.active==='1'?'selected':''}>Active</option><option value="0" ${filter.active==='0'?'selected':''}>Inactive</option></select><button class="btn btn-secondary btn-sm" type="submit">${icon('filter')} Filter</button><button class="btn btn-quiet btn-sm" type="button" id="clear-user-filters">Clear</button></form>
      <div class="table-wrap"><table class="data-table"><thead><tr><th>Name / email</th><th>Role</th><th>Status</th><th>User since</th><th>Action</th></tr></thead><tbody>${items.map(u=>`<tr><td><div style="display:flex;gap:9px;align-items:center"><span class="avatar">${esc(initial(u.name))}</span><div><div class="strong">${esc(u.name)}</div><div class="cell-secondary">${esc(u.email)}</div></div></div></td><td>${roleBadge(u.role)}</td><td>${u.is_active?'<span class="severity-badge severity-on_time">Active</span>':'<span class="state-badge state-open">Inactive</span>'}</td><td>${dateFmt(u.created_at,{time:false,zone:false})}</td><td><div class="table-actions"><button class="btn btn-secondary btn-sm" data-action="edit-user" data-id="${u.id}">Edit user</button><button class="btn ${u.is_active?'btn-danger':'btn-quiet'} btn-sm" data-action="toggle-user" data-id="${u.id}" ${Number(u.id)===Number(state.user.id)?'disabled':''}>${u.is_active?'Disable':'Enable'}</button></div></td></tr>`).join('')||tableEmpty('No users found.')}</tbody></table></div>${paginatorHtml(meta,'users-page')}</section>`;
    document.getElementById('user-filter-form').addEventListener('submit',async e=>{e.preventDefault();const f=new FormData(e.currentTarget);state.filters.users={search:f.get('search'),role:f.get('role'),active:f.get('active')};state.currentPage=1;await renderPage();});
    document.getElementById('clear-user-filters').addEventListener('click',async()=>{state.filters.users={};state.currentPage=1;await renderPage();});
    host.querySelector('[data-action="add-user"]')?.addEventListener('click',()=>openUserDrawer());
    host.querySelectorAll('[data-action="edit-user"]').forEach(b=>b.addEventListener('click',()=>openUserDrawer(items.find(u=>String(u.id)===b.dataset.id))));
    host.querySelectorAll('[data-action="toggle-user"]').forEach(b=>b.addEventListener('click',()=>toggleUser(b.dataset.id)));
    host.querySelectorAll('[data-action="users-page"]').forEach(b=>b.addEventListener('click',async()=>{state.currentPage=Number(b.dataset.page);await renderPage();}));
  }
  function openUserDrawer(user=null){
    openDrawer(user?'Edit user':'Add user',user?'Update account details and role-based access.':'Create an account for a CargoTrace workspace member.',`<div class="form-field"><label>Full name <span class="req">*</span></label><input name="name" required value="${esc(user?.name||'')}" placeholder="Full name"></div><div class="form-field"><label>Email address <span class="req">*</span></label><input name="email" type="email" required value="${esc(user?.email||'')}" placeholder="name@company.com"></div><div class="form-field"><label>Role <span class="req">*</span></label><select name="role" required><option value="admin" ${user?.role==='admin'?'selected':''}>ADMIN</option><option value="operator" ${user?.role==='operator'?'selected':''}>OPERATOR</option><option value="customer" ${(!user||user?.role==='customer')?'selected':''}>CUSTOMER</option></select></div><div class="form-field"><label>${user?'New password':'Temporary password'} ${user?'':'*'}</label><input name="password" type="password" minlength="8" ${user?'':'required'} placeholder="At least 8 characters"><small>${user?'Leave blank to keep the current password.':'Share this temporary password securely.'}</small></div><div class="form-field"><label>Account status</label><select name="is_active"><option value="1" ${user?.is_active===false?'':'selected'}>Active</option><option value="0" ${user?.is_active===false?'selected':''}>Inactive</option></select></div><div class="notice"><span class="notice-icon">${icon('info')}</span><div>Only CUSTOMER accounts are scoped to their own shipments. Operators manage operational records. Administrators have global oversight; audit logs are read-only.</div></div>`,async f=>{const body={name:f.get('name').trim(),email:f.get('email').trim(),role:f.get('role'),is_active:f.get('is_active')==='1'};if(f.get('password'))body.password=f.get('password');await api(user?`/users/${user.id}`:'/users',{method:user?'PATCH':'POST',body});closeDrawer();showToast(user?'User account updated.':'User account created.');await renderPage();},user?'Save changes':'Create user');
  }
  async function toggleUser(id){
    const user=state.pageData?.users?.find(x=>String(x.id)===String(id));
    if(!confirm('Change this account active status?'))return;
    try{const result=await api(`/users/${id}/toggle-active`,{method:'PATCH'});showToast(`Account ${result.data.is_active?'enabled':'disabled'}.`);await renderPage();}catch(err){showToast(err.message,'error');}
  }
  async function renderCarriers(host){
    const filter=state.filters.carriers||{};const response=await api(`/carriers${queryString({search:filter.search||'',transport_mode:filter.transport_mode||'',per_page:20,page:state.currentPage})}`);const {items,meta}=paginator(response);
    host.innerHTML=`${heading('Administration','Carriers','Maintain carrier records and operational contact information.',actionBtn('Add carrier','add-carrier','plus'))}<div class="stats-grid customer-stats" style="margin-bottom:14px">${statCard('Active carriers',items.filter(c=>c.is_active).length,'On this result page','carriers')}${statCard('Portfolio volume',items.reduce((sum,c)=>sum+Number(c.shipments_count||0),0),'Across listed carriers','shipments')}${statCard('Transport modes',new Set(items.map(c=>c.transport_mode).filter(Boolean)).size,'Ocean, road or other modes','map')}${statCard('Carrier records',meta.total,'Master data records','file')}</div>
      <section class="panel"><div class="panel-head"><div><h3 class="panel-title">Carrier directory</h3><p class="panel-subtitle">Master data · ${meta.total} records</p></div></div><form id="carrier-filter-form" class="toolbar"><div class="searchbox">${icon('search')}<input class="control" name="search" value="${esc(filter.search||'')}" placeholder="Search carrier name or code"></div><select class="control" name="transport_mode"><option value="">All modes</option>${['Ocean','Road','Air','Rail','Multimodal'].map(m=>`<option ${filter.transport_mode===m?'selected':''}>${m}</option>`).join('')}</select><button class="btn btn-secondary btn-sm" type="submit">${icon('filter')} Filter</button><button class="btn btn-quiet btn-sm" type="button" id="clear-carrier-filter">Clear</button></form>
      <div class="table-wrap"><table class="data-table"><thead><tr><th>Carrier / code</th><th>Mode</th><th>Operations contact</th><th>Shipments</th><th>Phone / email</th><th>Status</th><th>Action</th></tr></thead><tbody>${items.map(c=>`<tr><td><div class="strong">${esc(c.name)}</div><div class="cell-secondary">${esc(c.code)}</div></td><td>${esc(c.transport_mode||'—')}</td><td>${esc(c.contact_name||'—')}</td><td>${fmtNumber(c.shipments_count||0)}</td><td><div>${esc(c.support_phone||'—')}</div><div class="cell-secondary">${esc(c.support_email||'')}</div></td><td>${c.is_active?'<span class="severity-badge severity-on_time">Active</span>':'<span class="state-badge state-open">Inactive</span>'}</td><td><button class="btn btn-secondary btn-sm" data-action="edit-carrier" data-id="${c.id}">Edit carrier</button></td></tr>`).join('')||tableEmpty('No carriers found.')}</tbody></table></div>${paginatorHtml(meta,'carriers-page')}</section>`;
    document.getElementById('carrier-filter-form').addEventListener('submit',async e=>{e.preventDefault();const f=new FormData(e.currentTarget);state.filters.carriers={search:f.get('search'),transport_mode:f.get('transport_mode')};state.currentPage=1;await renderPage();});
    document.getElementById('clear-carrier-filter').addEventListener('click',async()=>{state.filters.carriers={};state.currentPage=1;await renderPage();});
    host.querySelector('[data-action="add-carrier"]')?.addEventListener('click',()=>openCarrierDrawer());
    host.querySelectorAll('[data-action="edit-carrier"]').forEach(b=>b.addEventListener('click',()=>openCarrierDrawer(items.find(c=>String(c.id)===b.dataset.id))));
    host.querySelectorAll('[data-action="carriers-page"]').forEach(b=>b.addEventListener('click',async()=>{state.currentPage=Number(b.dataset.page);await renderPage();}));
  }
  function openCarrierDrawer(carrier=null){
    openDrawer(carrier?'Edit carrier':'Add carrier',carrier?'Update carrier contact information and booking availability.':'Create a carrier record for shipment booking.',`<div class="form-field"><label>Carrier name <span class="req">*</span></label><input name="name" required value="${esc(carrier?.name||'')}" placeholder="e.g. Maersk"></div><div class="form-grid"><div class="form-field"><label>Carrier code <span class="req">*</span></label><input name="code" required value="${esc(carrier?.code||'')}" placeholder="e.g. MAEU"></div><div class="form-field"><label>Transport mode</label><select name="transport_mode"><option value="">Select mode</option>${['Ocean','Road','Air','Rail','Multimodal'].map(x=>`<option ${carrier?.transport_mode===x?'selected':''}>${x}</option>`).join('')}</select></div></div><div class="form-field"><label>Contact name</label><input name="contact_name" value="${esc(carrier?.contact_name||'')}" placeholder="Operations contact"></div><div class="form-field"><label>Contact email</label><input name="support_email" type="email" value="${esc(carrier?.support_email||'')}" placeholder="ops@carrier.com"></div><div class="form-field"><label>Phone number</label><input name="support_phone" value="${esc(carrier?.support_phone||'')}" placeholder="+1 555 0100"></div><div class="form-field"><label>Website</label><input name="website" type="url" value="${esc(carrier?.website||'')}" placeholder="https://"></div><div class="form-field"><label>Status</label><select name="is_active"><option value="1" ${carrier?.is_active===false?'':'selected'}>Active</option><option value="0" ${carrier?.is_active===false?'selected':''}>Inactive</option></select></div><div class="notice"><span class="notice-icon">${icon('info')}</span><div>Active carriers become available in the Create Shipment carrier selector. Carriers referenced by shipments cannot be deleted; deactivate them instead.</div></div>`,async f=>{const body={name:f.get('name').trim(),code:f.get('code').trim().toUpperCase(),contact_name:f.get('contact_name')||null,transport_mode:f.get('transport_mode')||null,support_email:f.get('support_email')||null,support_phone:f.get('support_phone')||null,website:f.get('website')||null,is_active:f.get('is_active')==='1'};await api(carrier?`/carriers/${carrier.id}`:'/carriers',{method:carrier?'PATCH':'POST',body});closeDrawer();showToast(carrier?'Carrier updated.':'Carrier created.');await renderPage();},carrier?'Save changes':'Create carrier');
  }
  async function renderLocations(host){
    const filter=state.filters.locations||{};const response=await api(`/locations${queryString({search:filter.search||'',type:filter.type||'',country:filter.country||'',per_page:20,page:state.currentPage})}`);const {items,meta}=paginator(response);
    host.innerHTML=`${heading('Administration','Locations','Standardized ports, terminals and warehouses across your network.',actionBtn('Add location','add-location','plus'))}<div class="stats-grid customer-stats" style="margin-bottom:14px">${statCard('Locations',meta.total,'Master data records','locations')}${statCard('Countries',new Set(items.map(l=>l.country).filter(Boolean)).size,'On this result page','map')}${statCard('With coordinates',items.filter(l=>l.latitude!==null&&l.longitude!==null).length,'Saved decimal degree coordinates','map')}${statCard('Location types',new Set(items.map(l=>l.type).filter(Boolean)).size,'Ports and facilities','box')}</div>
      <section class="panel"><div class="panel-head"><div><h3 class="panel-title">Location directory</h3><p class="panel-subtitle">${meta.total} records · coordinates in decimal degrees</p></div></div><form id="location-filter-form" class="toolbar"><div class="searchbox">${icon('search')}<input class="control" name="search" value="${esc(filter.search||'')}" placeholder="Search city, country or UN/LOCODE"></div><select class="control" name="type"><option value="">All types</option>${['PORT','WAREHOUSE','TERMINAL','AIRPORT','OTHER'].map(t=>`<option ${filter.type===t?'selected':''}>${t}</option>`).join('')}</select><input class="control" style="max-width:150px" name="country" value="${esc(filter.country||'')}" placeholder="Country"><button class="btn btn-secondary btn-sm" type="submit">${icon('filter')} Filter</button><button class="btn btn-quiet btn-sm" id="clear-location-filter" type="button">Clear</button></form>
      <div class="table-wrap"><table class="data-table"><thead><tr><th>Location / code</th><th>Country</th><th>Type</th><th>Coordinates</th><th>Timezone</th><th>Action</th></tr></thead><tbody>${items.map(l=>`<tr><td><div class="strong">${esc(l.name)}</div><div class="cell-secondary">${esc(l.code||'No code')} · ${esc(l.city||'')}</div></td><td>${esc(l.country)}</td><td>${esc(l.type||'—')}</td><td class="cell-nowrap">${l.latitude!==null&&l.longitude!==null?`${Number(l.latitude).toFixed(4)}, ${Number(l.longitude).toFixed(4)}`:'<span class="muted">Missing</span>'}</td><td>${esc(l.timezone||'—')}</td><td><button class="btn btn-secondary btn-sm" data-action="edit-location" data-id="${l.id}">Edit location</button></td></tr>`).join('')||tableEmpty('No locations found.')}</tbody></table></div>${paginatorHtml(meta,'locations-page')}</section>`;
    document.getElementById('location-filter-form').addEventListener('submit',async e=>{e.preventDefault();const f=new FormData(e.currentTarget);state.filters.locations={search:f.get('search'),type:f.get('type'),country:f.get('country')};state.currentPage=1;await renderPage();});
    document.getElementById('clear-location-filter').addEventListener('click',async()=>{state.filters.locations={};state.currentPage=1;await renderPage();});
    host.querySelector('[data-action="add-location"]')?.addEventListener('click',()=>openLocationDrawer());
    host.querySelectorAll('[data-action="edit-location"]').forEach(b=>b.addEventListener('click',()=>openLocationDrawer(items.find(l=>String(l.id)===b.dataset.id))));
    host.querySelectorAll('[data-action="locations-page"]').forEach(b=>b.addEventListener('click',async()=>{state.currentPage=Number(b.dataset.page);await renderPage();}));
  }
  function openLocationDrawer(location=null){
    openDrawer(location?'Edit location':'Add location',location?'Keep routing and tracking coordinates accurate.':'Add a standardized port, terminal or warehouse.',`<div class="form-field"><label>Location name <span class="req">*</span></label><input name="name" required value="${esc(location?.name||'')}" placeholder="e.g. Rotterdam"></div><div class="form-grid"><div class="form-field"><label>UN/LOCODE</label><input name="code" value="${esc(location?.code||'')}" placeholder="NLRTM"></div><div class="form-field"><label>Type <span class="req">*</span></label><select name="type" required>${['PORT','WAREHOUSE','TERMINAL','AIRPORT','OTHER'].map(t=>`<option ${location?.type===t?'selected':''}>${t}</option>`).join('')}</select></div></div><div class="form-field"><label>Address</label><input name="address" value="${esc(location?.address||'')}" placeholder="Street, terminal or facility"></div><div class="form-grid"><div class="form-field"><label>Country <span class="req">*</span></label><input name="country" required value="${esc(location?.country||'')}" placeholder="Country"></div><div class="form-field"><label>Country code</label><input name="country_code" maxlength="2" value="${esc(location?.country_code||'')}" placeholder="US"></div><div class="form-field"><label>City</label><input name="city" value="${esc(location?.city||'')}" placeholder="City"></div><div class="form-field"><label>Region / state</label><input name="region" value="${esc(location?.region||'')}" placeholder="Region"></div></div><div class="form-field"><label>Timezone</label><input name="timezone" value="${esc(location?.timezone||'')}" placeholder="Europe/Amsterdam"></div><div class="form-field"><label>Find coordinates</label><div style="display:flex;gap:7px"><input id="geocode-query" style="flex:1" placeholder="City, country and address"><button type="button" class="btn btn-secondary btn-sm" id="geocode-button">Find coordinates</button></div><small>Geocoding uses OpenStreetMap Nominatim. Review returned coordinates before saving.</small><div id="geocode-results"></div></div><div class="form-grid"><div class="form-field"><label>Latitude</label><input name="latitude" type="number" min="-90" max="90" step="any" value="${esc(location?.latitude??'')}" placeholder="-90 to 90"></div><div class="form-field"><label>Longitude</label><input name="longitude" type="number" min="-180" max="180" step="any" value="${esc(location?.longitude??'')}" placeholder="-180 to 180"></div></div><div class="notice"><span class="notice-icon">${icon('info')}</span><div>Coordinates are required for map plotting but may be entered manually if geocoding is unavailable. Do not use assumed or live GPS locations.</div></div>`,async f=>{const lat=f.get('latitude'),lng=f.get('longitude');if((lat&&!lng)||(!lat&&lng))throw new Error('Latitude and longitude must be entered together.');const body={name:f.get('name').trim(),code:f.get('code')||null,type:f.get('type'),address:f.get('address')||null,country:f.get('country').trim(),country_code:f.get('country_code')?f.get('country_code').toUpperCase():null,city:f.get('city')||null,region:f.get('region')||null,timezone:f.get('timezone')||null,latitude:lat?Number(lat):null,longitude:lng?Number(lng):null};await api(location?`/locations/${location.id}`:'/locations',{method:location?'PATCH':'POST',body});closeDrawer();showToast(location?'Location updated.':'Location created.');await renderPage();},location?'Save changes':'Create location');
    document.getElementById('geocode-button')?.addEventListener('click',async()=>{const q=document.getElementById('geocode-query').value.trim();if(q.length<3){showToast('Enter at least 3 characters for geocoding.','error');return;}const button=document.getElementById('geocode-button');button.disabled=true;button.textContent='Finding…';try{const res=await api(`/locations/geocode/search${queryString({query:q})}`);const results=res.data||[];const host=document.getElementById('geocode-results');host.innerHTML=results.length?`<div class="form-field" style="margin-top:8px"><label>Choose a result</label><select id="geocode-select"><option value="">Select result</option>${results.map((r,i)=>`<option value="${i}">${esc(r.display_name)}</option>`).join('')}</select></div>`:'<div class="muted tiny" style="margin-top:7px">No matching coordinates found.</div>';document.getElementById('geocode-select')?.addEventListener('change',e=>{const r=results[Number(e.target.value)];if(!r)return;document.querySelector('#drawer-form [name="latitude"]').value=r.latitude;document.querySelector('#drawer-form [name="longitude"]').value=r.longitude;if(r.country_code)document.querySelector('#drawer-form [name="country_code"]').value=r.country_code;});}catch(err){document.getElementById('geocode-results').innerHTML=`<div class="error-banner">${esc(err.message)} Enter coordinates manually.</div>`;}finally{button.disabled=false;button.textContent='Find coordinates';}});
  }

  async function renderAudit(host){
    const filter=state.filters.audit||{};const response=await api(`/audit-logs${queryString({action:filter.action||'',entity_type:filter.entity_type||'',date_from:filter.date_from||'',date_to:filter.date_to||'',per_page:30,page:state.currentPage})}`);const {items,meta}=paginator(response);
    host.innerHTML=`${heading('Administration','Audit logs','A read-only history of access, master-data and shipment changes.',`<span class="tiny muted">${meta.total} immutable records</span>`)}<div class="notice"><span class="notice-icon">${icon('info')}</span><div><strong>Read-only audit trail</strong><br>Records cannot be edited or deleted. Timestamps, actors and field changes are retained for accountability.</div></div><section class="panel"><div class="panel-head"><div><h3 class="panel-title">Activity history</h3><p class="panel-subtitle">All timestamps are UTC · newest activity first</p></div></div><form id="audit-filter-form" class="toolbar"><div class="searchbox">${icon('search')}<input class="control" name="action" value="${esc(filter.action||'')}" placeholder="Search exact action name"></div><select class="control" name="entity_type"><option value="">All entities</option>${['Shipment','ShipmentException','Carrier','Location','User'].map(x=>`<option ${filter.entity_type===x?'selected':''}>${x}</option>`).join('')}</select><input class="control" name="date_from" type="date" value="${esc(filter.date_from||'')}" aria-label="From date"><input class="control" name="date_to" type="date" value="${esc(filter.date_to||'')}" aria-label="To date"><button class="btn btn-secondary btn-sm" type="submit">${icon('filter')} Filter</button><button class="btn btn-quiet btn-sm" type="button" id="clear-audit-filter">Clear</button></form><div class="table-wrap"><table class="data-table"><thead><tr><th>Timestamp · UTC</th><th>Actor</th><th>Action</th><th>Entity</th><th>Change summary</th></tr></thead><tbody>${items.map(a=>`<tr><td class="cell-nowrap">${dateFmt(a.created_at,{time:true})}</td><td><div class="strong">${esc(a.user?.name||'System')}</div><div class="cell-secondary">${esc(a.user?.email||'')}</div></td><td><span class="role-badge role-operator">${esc(a.action)}</span></td><td>${esc(a.entity_type)}${a.entity_id?`<div class="cell-secondary">#${a.entity_id}</div>`:''}</td><td>${esc(a.description||'—')}</td></tr>`).join('')||tableEmpty('No audit records found.')}</tbody></table></div>${paginatorHtml(meta,'audit-page')}<div class="notice" style="margin:14px 0 0"><span class="notice-icon">${icon('check')}</span><div>Immutable activity history · stored by the backend · no edit or delete actions are available.</div></div></section>`;
    document.getElementById('audit-filter-form').addEventListener('submit',async e=>{e.preventDefault();const f=new FormData(e.currentTarget);state.filters.audit={action:f.get('action'),entity_type:f.get('entity_type'),date_from:f.get('date_from'),date_to:f.get('date_to')};state.currentPage=1;await renderPage();});
    document.getElementById('clear-audit-filter').addEventListener('click',async()=>{state.filters.audit={};state.currentPage=1;await renderPage();});
    host.querySelectorAll('[data-action="audit-page"]').forEach(b=>b.addEventListener('click',async()=>{state.currentPage=Number(b.dataset.page);await renderPage();}));
  }
  async function renderTracking(host){
    const response=await api(`/shipments${queryString({search:state.filters.tracking||'',per_page:30,page:state.currentPage})}`);const {items,meta}=paginator(response);
    host.innerHTML=`${heading('Operations','Tracking','Record manual checkpoints and review the saved shipment journey.',`<button class="btn btn-secondary" data-nav="shipments">View shipments ${icon('arrow')}</button>`)}<div class="notice"><span class="notice-icon">${icon('info')}</span><div><strong>Recorded checkpoints only</strong><br>Tracking events are append-only historical records. No physical live GPS or direct carrier integration is used.</div></div><section class="panel"><div class="panel-head"><div><h3 class="panel-title">Shipment tracking workspace</h3><p class="panel-subtitle">Select a shipment to inspect its history or add a checkpoint.</p></div></div><form id="tracking-filter-form" class="toolbar"><div class="searchbox">${icon('search')}<input name="search" class="control" placeholder="Search shipment number or cargo" value="${esc(state.filters.tracking||'')}"></div><button class="btn btn-secondary btn-sm" type="submit">${icon('search')} Search</button></form><div class="table-wrap"><table class="data-table"><thead><tr><th>Shipment</th><th>Customer / route</th><th>Current status</th><th>Last checkpoint</th><th>Current ETA</th><th>Action</th></tr></thead><tbody>${items.map(s=>{const e=s.latest_tracking_event;return `<tr><td><button class="btn-link cell-primary" data-action="view-shipment" data-id="${s.id}">${esc(s.shipment_number)}</button><div class="cell-secondary">${esc(carrierName(s))}</div></td><td><span class="strong">${esc(customerName(s))}</span><div class="cell-secondary">${esc(routeName(s))}</div></td><td>${statusBadge(s.status)}</td><td>${esc(e?.location?.name||e?.location?.city||'Not recorded')}<div class="cell-secondary">${e?.event_time?dateFmt(e.event_time,{time:true}):'No event yet'}</div></td><td>${dateFmt(s.current_eta||s.expected_arrival_at,{time:true})}</td><td><div class="table-actions"><button class="btn btn-secondary btn-sm" data-action="add-tracking-list" data-id="${s.id}">Add event</button><button class="btn-link" data-action="view-shipment" data-id="${s.id}">View →</button></div></td></tr>`}).join('')||tableEmpty('No shipments available.')}</tbody></table></div>${paginatorHtml(meta,'tracking-page')}</section>`;
    document.getElementById('tracking-filter-form').addEventListener('submit',async e=>{e.preventDefault();state.filters.tracking=new FormData(e.currentTarget).get('search');state.currentPage=1;await renderPage();});
    host.querySelectorAll('[data-action="add-tracking-list"]').forEach(b=>b.addEventListener('click',async()=>{try{const res=await api(`/shipments/${b.dataset.id}`);await openTrackingDrawer(res.data);}catch(err){showToast(err.message,'error');}}));
    host.querySelectorAll('[data-action="tracking-page"]').forEach(b=>b.addEventListener('click',async()=>{state.currentPage=Number(b.dataset.page);await renderPage();}));bindCommonActions(host);
  }
  async function loadAllExceptions(){
    const response=await api('/shipments?per_page=100');const shipments=response.data||[];
    const nested=await Promise.all(shipments.map(async shipment=>{try{const res=await api(`/shipments/${shipment.id}/exceptions`);return (res.data||[]).map(ex=>({...ex,shipment:{id:shipment.id,shipment_number:shipment.shipment_number,customer:shipment.customer,customer_id:shipment.customer_id,origin:shipment.origin,destination:shipment.destination}}));}catch{return [];}}));
    return nested.flat().sort((a,b)=>new Date(b.detected_at)-new Date(a.detected_at));
  }
  async function renderExceptions(host){
    const items=await loadAllExceptions();const filter=state.filters.exceptions||{};
    const filtered=items.filter(ex=>(!filter.status||ex.status===filter.status)&&(!filter.unresolved||ex.status!=='RESOLVED')&&(!filter.severity||ex.severity===filter.severity)&&(!filter.search||`${ex.id} ${ex.shipment?.shipment_number||''} ${ex.title||''} ${ex.exception_type}`.toLowerCase().includes(filter.search.toLowerCase())));
    const counts={OPEN:items.filter(x=>x.status==='OPEN').length,INVESTIGATING:items.filter(x=>x.status==='INVESTIGATING').length,RESOLVED:items.filter(x=>x.status==='RESOLVED').length};
    host.innerHTML=`${heading('Operations','Exceptions','Triage disruptions, coordinate a response and keep customers informed.',actionBtn('Add exception','create-exception-from-list','plus'))}<div class="exception-summary" style="margin-bottom:14px"><div class="exception-stat"><strong>${counts.OPEN}</strong><span>Open · awaiting investigation</span></div><div class="exception-stat"><strong>${counts.INVESTIGATING}</strong><span>Investigating · response in progress</span></div><div class="exception-stat"><strong>${counts.RESOLVED}</strong><span>Resolved · history retained</span></div></div><section class="panel"><div class="panel-head"><div><h3 class="panel-title">Exception workspace</h3><p class="panel-subtitle">${items.length} total exceptions across the shipment portfolio</p></div></div><div class="toolbar"><button class="btn btn-quiet btn-sm" data-ex-tab="">All exceptions (${items.length})</button><button class="btn btn-secondary btn-sm" data-ex-tab="unresolved">Unresolved (${counts.OPEN+counts.INVESTIGATING})</button><button class="btn btn-secondary btn-sm" data-ex-tab="RESOLVED">Resolved (${counts.RESOLVED})</button></div><form id="exception-filter-form" class="toolbar"><div class="searchbox">${icon('search')}<input class="control" name="search" value="${esc(filter.search||'')}" placeholder="Search exception or shipment ID"></div><select class="control" name="severity"><option value="">All severities</option>${['LOW','MEDIUM','HIGH'].map(x=>`<option ${filter.severity===x?'selected':''}>${x}</option>`).join('')}</select><select class="control" name="status"><option value="">All statuses</option>${['OPEN','INVESTIGATING','RESOLVED'].map(x=>`<option ${filter.status===x?'selected':''}>${x}</option>`).join('')}</select><button class="btn btn-secondary btn-sm" type="submit">Filter</button><button class="btn btn-quiet btn-sm" type="button" id="clear-ex-filter">Clear</button></form><div class="table-wrap"><table class="data-table"><thead><tr><th>Exception / shipment</th><th>Type</th><th>Severity</th><th>Status</th><th>Owner</th><th>Updated</th><th>Action</th></tr></thead><tbody>${filtered.map(ex=>`<tr><td><div class="strong">EX-${String(ex.id).padStart(3,'0')}</div><div class="cell-secondary"><button class="btn-link" data-action="view-shipment" data-id="${ex.shipment?.id}">${esc(ex.shipment?.shipment_number||'Shipment')}</button> · ${esc(ex.shipment?.customer?.name||'')}</div></td><td>${esc(human(ex.exception_type))}</td><td>${severityBadge(ex.severity)}</td><td>${exceptionState(ex.status)}</td><td>${esc(ex.reporter?.name||'—')}</td><td>${dateFmt(ex.updated_at||ex.detected_at,{time:true})}</td><td>${ex.status!=='RESOLVED'?`<button class="btn btn-secondary btn-sm" data-action="manage-exception-list" data-id="${ex.id}">Manage</button>`:'<span class="tiny muted">Closed</span>'}</td></tr>`).join('')||tableEmpty('No exceptions match your filters.')}</tbody></table></div><div class="notice" style="margin:13px 0 0"><span class="notice-icon">${icon('info')}</span><div>Exception handling flow: OPEN → INVESTIGATING → RESOLVED. Severity is selected independently from ETA delay. Resolve only when recovery is confirmed.</div></div></section>`;
    document.getElementById('exception-filter-form').addEventListener('submit',async e=>{e.preventDefault();const f=new FormData(e.currentTarget);state.filters.exceptions={search:f.get('search'),severity:f.get('severity'),status:f.get('status')};await renderPage();});
    document.getElementById('clear-ex-filter').addEventListener('click',async()=>{state.filters.exceptions={};await renderPage();});
    host.querySelectorAll('[data-ex-tab]').forEach(b=>b.addEventListener('click',async()=>{const v=b.dataset.exTab;state.filters.exceptions={...(state.filters.exceptions||{}),status:v==='unresolved'?'':v==='RESOLVED'?'RESOLVED':'',search:state.filters.exceptions?.search||'',severity:state.filters.exceptions?.severity||''};state.filters.exceptions.unresolved=v==='unresolved';await renderPage();if(v==='unresolved'){document.querySelector('#exception-filter-form [name="status"]').value='';document.querySelectorAll('#exception-filter-form option[value="OPEN"],#exception-filter-form option[value="INVESTIGATING"]').forEach(o=>o.disabled=false);}}));
    host.querySelector('[data-action="create-exception-from-list"]')?.addEventListener('click',async()=>{try{const res=await api('/shipments?per_page=100');const s=res.data?.[0];if(!s){showToast('Create a shipment before adding an exception.','error');return;}const full=await api(`/shipments/${s.id}`);openExceptionDrawer(full.data);}catch(err){showToast(err.message,'error');}});
    host.querySelectorAll('[data-action="manage-exception-list"]').forEach(b=>b.addEventListener('click',()=>manageException(b.dataset.id,null)));
    bindCommonActions(host);
  }
  async function renderNotifications(host){
    const filter=state.filters.notifications||{};const response=await api(`/notifications${queryString({unread:filter.unread?'1':'',per_page:30,page:state.currentPage})}`);const {items,meta}=paginator(response);const unread=items.filter(n=>!n.read_at).length;
    host.innerHTML=`${heading('Customer portal','Notifications','Important updates for your shipments.',`<button class="btn btn-secondary btn-sm" data-action="mark-all-read">Mark all as read</button>`)}<section class="panel"><div class="panel-head"><div><h3 class="panel-title">Shipment updates</h3><p class="panel-subtitle">${meta.total} notifications · all timestamps UTC</p></div><span class="tiny muted">${state.notificationsUnread} unread</span></div><div class="toolbar"><button class="btn btn-quiet btn-sm" data-notif-tab="all">All updates</button><button class="btn btn-secondary btn-sm" data-notif-tab="unread">Unread</button></div>${items.length?items.map(n=>`<div class="notification-item"><div class="notification-icon">${icon('bell')}</div><div class="notification-main"><strong>${esc(n.title)}</strong><div class="cell-secondary">${n.shipment?.shipment_number?`${esc(n.shipment.shipment_number)} · `:''}${dateFmt(n.created_at,{time:true})}</div><p>${esc(n.message)}</p><div style="display:flex;gap:12px;align-items:center;margin-top:8px">${!n.read_at?`<button class="btn-link" data-action="mark-notification" data-id="${n.id}">Mark as read</button>`:'<span class="tiny muted">Read</span>'}${n.shipment_id?`<button class="btn-link" data-action="notification-shipment" data-id="${n.shipment_id}">View shipment →</button>`:''}</div></div>${!n.read_at?'<span class="unread-dot"></span>':''}</div>`).join(''):`<div class="empty-state"><div class="empty-icon">${icon('bell')}</div><strong>No notifications found</strong><p>Shipment updates will appear here when they are created by operations.</p></div>`}${paginatorHtml(meta,'notifications-page')}</section>`;
    host.querySelector('[data-action="mark-all-read"]')?.addEventListener('click',async()=>{try{await api('/notifications/read-all',{method:'PATCH'});state.notificationsUnread=0;showToast('All notifications marked as read.');await renderPage();}catch(err){showToast(err.message,'error');}});
    host.querySelectorAll('[data-action="mark-notification"]').forEach(b=>b.addEventListener('click',async()=>{try{await api(`/notifications/${b.dataset.id}/read`,{method:'PATCH'});state.notificationsUnread=Math.max(0,state.notificationsUnread-1);await renderPage();}catch(err){showToast(err.message,'error');}}));
    host.querySelectorAll('[data-action="notification-shipment"]').forEach(b=>b.addEventListener('click',()=>viewShipment(b.dataset.id)));
    host.querySelectorAll('[data-notif-tab]').forEach(b=>b.addEventListener('click',async()=>{state.filters.notifications={unread:b.dataset.notifTab==='unread'};state.currentPage=1;await renderPage();}));
    host.querySelectorAll('[data-action="notifications-page"]').forEach(b=>b.addEventListener('click',async()=>{state.currentPage=Number(b.dataset.page);await renderPage();}));
  }
  function renderProfile(host){
    host.innerHTML=`${heading('Customer portal','Profile & preferences','Your account information and access scope are managed by your administrator.')}
      <div class="grid-2-1"><section class="panel"><div class="panel-head"><div><h3 class="panel-title">Personal information</h3><p class="panel-subtitle">Your account contact details</p></div></div><div style="display:flex;align-items:center;gap:12px;margin-bottom:20px"><div class="avatar" style="height:48px;width:48px;font-size:14px">${esc(initial(state.user.name))}</div><div><div class="strong">${esc(state.user.name)}</div>${roleBadge(state.user.role)}</div></div><div class="info-list"><div class="info-row"><span>Full name</span><strong>${esc(state.user.name)}</strong></div><div class="info-row"><span>Email address</span><strong>${esc(state.user.email)}</strong></div><div class="info-row"><span>Role</span><strong>Customer</strong></div><div class="info-row"><span>Account since</span><strong>${dateFmt(state.user.created_at,{time:false,zone:false})}</strong></div></div><div class="notice" style="margin-top:18px"><span class="notice-icon">${icon('info')}</span><div>Profile editing and password change are not exposed by the current backend API. Contact your administrator to verify account changes.</div></div></section>
      <aside style="display:grid;gap:14px"><section class="panel"><div class="panel-head"><div><h3 class="panel-title">Account access</h3><p class="panel-subtitle">Managed by your administrator</p></div></div><div class="info-list"><div class="info-row"><span>Organization</span><strong>Customer account</strong></div><div class="info-row"><span>Role</span><strong>Customer</strong></div><div class="info-row"><span>Shipment visibility</span><strong>Own shipments only</strong></div><div class="info-row"><span>Account status</span><strong><span class="severity-badge severity-on_time">Active</span></strong></div></div></section><section class="panel"><div class="panel-head"><div><h3 class="panel-title">Security</h3><p class="panel-subtitle">Current session</p></div></div><div class="info-list"><div class="info-row"><span>Authentication</span><strong>Laravel Sanctum</strong></div><div class="info-row"><span>Session storage</span><strong>Current browser tab</strong></div></div><button class="btn btn-secondary" style="width:100%;margin-top:14px" data-action="logout">${icon('logout')} Sign out</button></section></aside></div>`;
    host.querySelector('[data-action="logout"]')?.addEventListener('click',logout);
  }

  async function openGlobalExceptionDrawer(){
    const response=await api('/shipments?per_page=100');const shipments=response.data||[];
    if(!shipments.length){showToast('Create a shipment before adding an exception.','error');return;}
    openDrawer('Add exception','Choose the affected shipment and record the disruption.',`<div class="form-field"><label>Shipment <span class="req">*</span></label><select name="shipment_id" required>${shipments.map(s=>`<option value="${s.id}">${esc(s.shipment_number)} · ${esc(customerName(s))} · ${esc(routeName(s))}</option>`).join('')}</select></div><div class="form-field"><label>Exception type <span class="req">*</span></label><select name="exception_type" required>${EXCEPTION_TYPES.map(t=>`<option value="${t}">${human(t)}</option>`).join('')}</select></div><div class="form-field"><label>Severity <span class="req">*</span></label><select name="severity"><option>LOW</option><option selected>MEDIUM</option><option>HIGH</option></select></div><div class="form-field"><label>Title <span class="req">*</span></label><input name="title" required maxlength="150" placeholder="Short customer-friendly title"></div><div class="form-field"><label>Description</label><textarea name="description" placeholder="Describe the disruption."></textarea></div><div class="notice"><span class="notice-icon">${icon('info')}</span><div>New exceptions begin as OPEN. Delay does not automatically create an exception; severity is selected independently.</div></div>`,async f=>{const id=f.get('shipment_id');await api(`/shipments/${id}/exceptions`,{method:'POST',body:{exception_type:f.get('exception_type'),severity:f.get('severity'),title:f.get('title').trim(),description:f.get('description')||null}});closeDrawer();showToast('Exception created and customer notified.');await renderPage();},'Create exception');
  }

  async function start(){
    if(state.token){await enterWorkspace();}
    else showLanding();
  }
  start();
})();
