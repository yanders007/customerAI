import axios from 'axios';

// ── Base URL ────────────────────────────────────────────────
// En production (Vercel) : VITE_API_URL pointe vers Render
// En local : proxy Vite redirige /api → localhost:8000
const BASE_URL = import.meta.env.VITE_API_URL
  ? `${import.meta.env.VITE_API_URL}`
  : '';

export const api = axios.create({
  baseURL: `${BASE_URL}/api`,
  withCredentials: true,
  timeout: 120000, // 2 minutes (N8N peut être lent au cold start)
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  }
});

// WebDev serves the frontend without the Laravel backend. Do not probe a
// missing local server from the preview; local and production deployments
// still probe normally through the Vite proxy or VITE_API_URL.
export const isWebdevPreview = typeof window !== 'undefined'
  && window.location.hostname.endsWith('.manus.computer')
  && !import.meta.env.VITE_API_URL;

// Logging des requêtes pour debug
api.interceptors.request.use((config) => {
  console.log(`[API] ${config.method?.toUpperCase()} ${config.url}`, {
    withCredentials: config.withCredentials,
    baseURL: config.baseURL
  });
  return config;
});

api.interceptors.response.use(
  (response) => {
    console.log(`[API] ✓ ${response.config.method?.toUpperCase()} ${response.config.url}`, response.status);
    return response;
  },
  (error) => {
    const method = error.config?.method?.toUpperCase();
    const url = error.config?.url;
    const status = error.response?.status;
    const isAnonymousSessionProbe = method === 'GET'
      && (url === '/client/me' || url === '/admin/me')
    // The static WebDev preview has no local Laravel server. A failed session
    // probe must not appear as a frontend error or block the login screen;
    // authenticated requests still surface their real failures below.
    const isExpectedAnonymousProbe = isAnonymousSessionProbe
      && (!import.meta.env.VITE_API_URL || status === 401 || status === 404 || status === 500);
    if (!isExpectedAnonymousProbe) {
      console.error(`[API] ✗ ${method} ${url}`, {
        status,
        data: error.response?.data
      });
    }
    return Promise.reject(error);
  }
);

// Note: Laravel sessions fonctionnent avec cookies PHPSESSID
// CSRF token géré automatiquement par Laravel (pas besoin de Sanctum)

// ── Session locale (1 heure) ────────────────────────────────
const SESSION_KEY = 'sia_session';
const SESSION_TTL = 60 * 60 * 1000;

export function saveSession(role, data) {
  const project = role === 'client' ? loadSessionRaw()?.project ?? null : undefined;
  localStorage.setItem(SESSION_KEY, JSON.stringify({ role, data, project, at: Date.now() }));
}
export function saveProject(project) {
  const raw = loadSessionRaw();
  if (!raw) return;
  localStorage.setItem(SESSION_KEY, JSON.stringify({ ...raw, project }));
}
function loadSessionRaw() {
  try {
    const s = JSON.parse(localStorage.getItem(SESSION_KEY) || '');
    if (!s?.at) return null;
    return s;
  } catch { return null; }
}
export function loadSession(role) {
  const s = loadSessionRaw();
  if (!s || s.role !== role) return null;
  if (Date.now() - s.at > SESSION_TTL) { clearSession(); return null; }
  return s;
}
export function clearSession() { localStorage.removeItem(SESSION_KEY); }

// ── Thème ───────────────────────────────────────────────────
const THEME_KEY = 'sia_theme';
export function initTheme() {
  const t = localStorage.getItem(THEME_KEY) === 'dark' ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', t);
  return t;
}
export function toggleTheme(current) {
  const next = current === 'dark' ? 'light' : 'dark';
  localStorage.setItem(THEME_KEY, next);
  document.documentElement.setAttribute('data-theme', next);
  return next;
}
export function getTheme() {
  return localStorage.getItem(THEME_KEY) === 'dark' ? 'dark' : 'light';
}
