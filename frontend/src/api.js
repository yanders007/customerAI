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
  },
  // Aide pour mobile : retry sur timeout
  validateStatus: function (status) {
    return status >= 200 && status < 500; // Accepte les 4xx pour gestion d'erreur propre
  }
});

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
    console.error(`[API] ✗ ${error.config?.method?.toUpperCase()} ${error.config?.url}`, {
      status: error.response?.status,
      data: error.response?.data
    });
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
