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
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
});

// ✅ Interceptor CSRF — récupère le token et l'ajoute aux requêtes mutantes
let csrfToken = null;

api.interceptors.request.use(async (config) => {
  const isMutation = ['post', 'put', 'patch', 'delete']
    .includes(config.method?.toLowerCase() || '');

  if (isMutation && !csrfToken) {
    try {
      await axios.get(`${BASE_URL}/sanctum/csrf-cookie`, { withCredentials: true });
      const xsrfCookie = document.cookie
        .split('; ')
        .find(c => c.startsWith('XSRF-TOKEN='));
      if (xsrfCookie) {
        csrfToken = decodeURIComponent(xsrfCookie.split('=')[1]);
      }
    } catch (e) {
      console.warn('Impossible de récupérer le token CSRF', e);
    }
  }

  if (csrfToken && isMutation) {
    config.headers['X-XSRF-TOKEN'] = csrfToken;
  }

  return config;
}, (error) => Promise.reject(error));

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
