/**
 * AiConfigPage — Page de configuration IA pour l'admin
 *
 * INTÉGRATION dans pages.jsx :
 *  1. Ajouter en haut :  import AiConfigPage from './AiConfigPage';
 *  2. Dans le routeur/switch de l'admin, ajouter le cas :
 *       case 'ai-config': return <AiConfigPage api={api} />;
 *  3. Dans la nav admin, ajouter un item pointant vers page='ai-config'
 */

import { useState, useEffect } from 'react';

// ── Catalogue des providers ───────────────────────────────────────────────
const PROVIDERS = [
  {
    id: 'gemini', name: 'Google Gemini', free: true,
    url: 'https://aistudio.google.com/apikey',
    models: [
      'gemini-3.6-flash',
      'gemini-3.5-flash', 
      'gemini-3.5-flash-lite',
      'gemini-3.1-flash-lite',
      'gemini-2.5-flash',
      'gemini-2.5-flash-lite',
      'gemini-3.1-flash'
    ],
    hint: 'Clé commence par AIza… (Free tier - modèles 2026)',
  },
  {
    id: 'groq', name: 'Groq', free: true,
    url: 'https://console.groq.com/keys',
    models: ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant', 'mixtral-8x7b-32768', 'gemma2-9b-it'],
    hint: 'Clé commence par gsk_…',
  },
  {
    id: 'together', name: 'Together AI', free: true,
    url: 'https://api.together.xyz/settings/api-keys',
    models: ['meta-llama/Llama-3-70b-chat-hf', 'meta-llama/Llama-3-8b-chat-hf', 'mistralai/Mixtral-8x7B-Instruct-v0.1'],
    hint: 'Clé commence par…',
  },
  {
    id: 'cohere-chat', name: 'Cohere', free: true,
    url: 'https://dashboard.cohere.com/api-keys',
    models: ['command-r', 'command-r-plus', 'command-light'],
    hint: 'Clé Cohere (trial ou production)',
  },
  {
    id: 'openai', name: 'OpenAI', free: false,
    url: 'https://platform.openai.com/api-keys',
    models: ['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo'],
    hint: 'Clé commence par sk-…',
  },
  {
    id: 'anthropic', name: 'Anthropic Claude', free: false,
    url: 'https://console.anthropic.com/api-keys',
    models: ['claude-3-haiku-20240307', 'claude-3-5-sonnet-20241022', 'claude-3-opus-20240229'],
    hint: 'Clé commence par sk-ant-…',
  },
  {
    id: 'mistral', name: 'Mistral AI', free: false,
    url: 'https://console.mistral.ai/api-keys/',
    models: ['mistral-small-latest', 'mistral-medium-latest', 'mistral-large-latest', 'open-mistral-7b'],
    hint: 'Clé Mistral Platform',
  },
  {
    id: 'deepseek', name: 'DeepSeek', free: false,
    url: 'https://platform.deepseek.com/api_keys',
    models: ['deepseek-chat', 'deepseek-reasoner'],
    hint: 'Clé DeepSeek Platform',
  },
  {
    id: 'openrouter', name: 'OpenRouter', free: false,
    url: 'https://openrouter.ai/keys',
    models: ['openai/gpt-4o-mini', 'google/gemini-flash-1.5', 'anthropic/claude-3-haiku', 'meta-llama/llama-3.1-70b-instruct'],
    hint: 'Agrégateur — accès à 200+ modèles',
  },
  {
    id: 'xai', name: 'xAI (Grok)', free: false,
    url: 'https://console.x.ai/',
    models: ['grok-beta', 'grok-2'],
    hint: 'Clé xAI Console',
  },
  {
    id: 'perplexity', name: 'Perplexity', free: false,
    url: 'https://www.perplexity.ai/settings/api',
    models: ['llama-3.1-sonar-small-128k-online', 'llama-3.1-sonar-large-128k-online'],
    hint: 'Clé Perplexity API',
  },
];

// ── Icônes ─────────────────────────────────────────────────────────────────
const IconExternalLink = () => (
  <svg width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
    <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/>
    <polyline points="15 3 21 3 21 9"/>
    <line x1="10" y1="14" x2="21" y2="3"/>
  </svg>
);
const IconCheck = () => (
  <svg width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
    <polyline points="20 6 9 17 4 12"/>
  </svg>
);
const IconTrash = () => (
  <svg width="15" height="15" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
    <path d="M10 11v6m4-6v6"/><path d="M9 6V4h6v2"/>
  </svg>
);
const IconKey = () => (
  <svg width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
    <circle cx="7.5" cy="15.5" r="4.5"/><path d="M21 2l-9.6 9.6M15.5 7.5l3 3"/>
  </svg>
);
const IconFlash = () => (
  <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24">
    <path d="M13 2L4.09 12.96A1 1 0 005 14.5h5.5l-1 7.5L19.91 11A1 1 0 0019 9.5h-5.5L13 2z"/>
  </svg>
);

export default function AiConfigPage({ api }) {
  const [activeConfig, setActiveConfig]     = useState(null);
  const [loadingConfig, setLoadingConfig]   = useState(true);

  // Formulaire
  const [selectedProvider, setSelectedProvider] = useState(null);
  const [apiKey, setApiKey]                     = useState('');
  const [selectedModel, setSelectedModel]       = useState('');
  const [systemPrompt, setSystemPrompt]         = useState('');
  const [showAdvanced, setShowAdvanced]         = useState(false);
  const [showKey, setShowKey]                   = useState(false);

  // États des actions
  const [testing, setTesting]   = useState(false);
  const [saving, setSaving]     = useState(false);
  const [testResult, setTestResult] = useState(null); // {success, message, preview}
  const [saveResult, setSaveResult] = useState(null);

  // ── Charger la config active ──────────────────────────────────────────
  useEffect(() => {
    api.get('/admin/ai-config')
      .then(r => {
        setActiveConfig(r.data.data);
        if (r.data.data) {
          const p = PROVIDERS.find(p => p.id === r.data.data.provider);
          setSelectedProvider(p || null);
          setSelectedModel(r.data.data.model || '');
          setSystemPrompt(r.data.data.system_prompt || '');
        }
      })
      .catch(() => {})
      .finally(() => setLoadingConfig(false));
  }, []);

  const providerInfo = selectedProvider
    ? PROVIDERS.find(p => p.id === selectedProvider.id)
    : null;

  // ── Test connexion ────────────────────────────────────────────────────
  const handleTest = async () => {
    if (!selectedProvider || !apiKey || !selectedModel) return;
    setTesting(true);
    setTestResult(null);
    try {
      const r = await api.post('/admin/ai-config/test', {
        provider: selectedProvider.id,
        model: selectedModel,
        api_key: apiKey,
      });
      setTestResult({ success: true, message: r.data.message, preview: r.data.preview });
    } catch (e) {
      setTestResult({ success: false, message: e.response?.data?.message || 'Erreur de connexion' });
    } finally {
      setTesting(false);
    }
  };

  // ── Sauvegarder ───────────────────────────────────────────────────────
  const handleSave = async () => {
    if (!selectedProvider || !apiKey || !selectedModel) return;
    setSaving(true);
    setSaveResult(null);
    try {
      const r = await api.post('/admin/ai-config', {
        provider: selectedProvider.id,
        model: selectedModel,
        api_key: apiKey,
        system_prompt: systemPrompt || null,
      });
      setSaveResult({ success: true, message: r.data.message });
      setActiveConfig({ provider: selectedProvider.id, model: selectedModel, api_key_hint: apiKey.slice(0, 8) + '••••••••••••' });
      setApiKey('');
    } catch (e) {
      setSaveResult({ success: false, message: e.response?.data?.message || 'Erreur lors de la sauvegarde' });
    } finally {
      setSaving(false);
    }
  };

  // ── Supprimer ─────────────────────────────────────────────────────────
  const handleDelete = async () => {
    if (!window.confirm('Supprimer la configuration IA active ?')) return;
    await api.delete('/admin/ai-config').catch(() => {});
    setActiveConfig(null);
    setSelectedProvider(null);
    setApiKey('');
    setSelectedModel('');
  };

  const canTest = selectedProvider && apiKey.length >= 10 && selectedModel;
  const canSave = canTest;

  return (
    <div style={{ maxWidth: 860, margin: '0 auto', padding: '24px 16px' }}>

      {/* ── En-tête ─────────────────────────────────────────────────── */}
      <div style={{ marginBottom: 28 }}>
        <h2 style={{ margin: 0, fontSize: 22, fontWeight: 700 }}>Configuration de l'IA</h2>
        <p style={{ margin: '6px 0 0', color: 'var(--text-secondary, #666)', fontSize: 14 }}>
          Connectez le provider IA de votre choix. Créez un compte chez le fournisseur et copiez votre clé API.
        </p>
      </div>

      {/* ── Config active ───────────────────────────────────────────── */}
      {!loadingConfig && activeConfig && (
        <div style={{
          background: 'var(--success-bg, #f0fdf4)',
          border: '1px solid var(--success-border, #86efac)',
          borderRadius: 10,
          padding: '14px 18px',
          marginBottom: 24,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          gap: 12,
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <span style={{ color: '#16a34a', display: 'flex' }}><IconCheck /></span>
            <div>
              <span style={{ fontWeight: 600, fontSize: 14 }}>
                {PROVIDERS.find(p => p.id === activeConfig.provider)?.name || activeConfig.provider}
              </span>
              <span style={{ color: '#64748b', fontSize: 13, marginLeft: 8 }}>
                {activeConfig.model}
              </span>
              {activeConfig.api_key_hint && (
                <span style={{ color: '#94a3b8', fontSize: 12, marginLeft: 8, fontFamily: 'monospace' }}>
                  {activeConfig.api_key_hint}
                </span>
              )}
            </div>
          </div>
          <button
            onClick={handleDelete}
            style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#ef4444', padding: 4, display: 'flex' }}
            title="Supprimer la configuration"
          >
            <IconTrash />
          </button>
        </div>
      )}

      {/* ── Grille providers ────────────────────────────────────────── */}
      <div style={{ marginBottom: 24 }}>
        <p style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-secondary, #666)', marginBottom: 10, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
          Obtenir une clé API
        </p>
        <p style={{ fontSize: 13, color: 'var(--text-secondary, #777)', marginBottom: 14 }}>
          Créez un compte chez le fournisseur de votre choix et générez une clé API.
          Les providers{' '}
          <span style={{ background: '#dcfce7', color: '#15803d', borderRadius: 4, padding: '1px 6px', fontSize: 12, fontWeight: 600 }}>
            gratuits
          </span>{' '}
          ne nécessitent pas de carte bancaire.
        </p>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: 8 }}>
          {PROVIDERS.map(p => (
            <a
              key={p.id}
              href={p.url}
              target="_blank"
              rel="noopener noreferrer"
              style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                padding: '10px 14px',
                borderRadius: 8,
                border: `1.5px solid ${selectedProvider?.id === p.id ? 'var(--primary, #6366f1)' : 'var(--border, #e2e8f0)'}`,
                background: selectedProvider?.id === p.id ? 'var(--primary-bg, #eef2ff)' : 'var(--card-bg, #fff)',
                textDecoration: 'none',
                color: 'inherit',
                transition: 'border-color 0.15s, background 0.15s',
                cursor: 'pointer',
              }}
              onClick={e => {
                e.preventDefault();
                const provider = PROVIDERS.find(x => x.id === p.id);
                setSelectedProvider(provider);
                setSelectedModel(provider.models[0]);
                setTestResult(null);
                setSaveResult(null);
                // Ouvrir le lien dans un nouvel onglet en parallèle
                window.open(p.url, '_blank', 'noopener');
              }}
            >
              <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <span style={{ fontSize: 14, fontWeight: 500 }}>{p.name}</span>
                {p.free && (
                  <span style={{
                    background: '#dcfce7', color: '#15803d',
                    borderRadius: 4, padding: '1px 6px', fontSize: 11, fontWeight: 600,
                    display: 'flex', alignItems: 'center', gap: 3,
                  }}>
                    <IconFlash /> gratuit
                  </span>
                )}
              </div>
              <span style={{ color: 'var(--text-secondary, #94a3b8)', display: 'flex' }}>
                <IconExternalLink />
              </span>
            </a>
          ))}
        </div>
      </div>

      {/* ── Formulaire de configuration ─────────────────────────────── */}
      {selectedProvider && (
        <div style={{
          background: 'var(--card-bg, #fff)',
          border: '1px solid var(--border, #e2e8f0)',
          borderRadius: 12,
          padding: 24,
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 20 }}>
            <IconKey />
            <h3 style={{ margin: 0, fontSize: 16, fontWeight: 600 }}>
              Configuration — {providerInfo?.name}
            </h3>
            {providerInfo?.free && (
              <span style={{ background: '#dcfce7', color: '#15803d', borderRadius: 4, padding: '2px 8px', fontSize: 12, fontWeight: 600 }}>
                Gratuit
              </span>
            )}
          </div>

          <div style={{ display: 'grid', gap: 16 }}>
            {/* Modèle */}
            <div>
              <label style={{ display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 6 }}>
                Modèle
              </label>
              <select
                value={selectedModel}
                onChange={e => setSelectedModel(e.target.value)}
                style={{
                  width: '100%', padding: '9px 12px', borderRadius: 8,
                  border: '1px solid var(--border, #d1d5db)',
                  background: 'var(--input-bg, #fff)', fontSize: 14,
                  color: 'var(--text, #111)',
                }}
              >
                {providerInfo?.models.map(m => (
                  <option key={m} value={m}>{m}</option>
                ))}
                <option value="__custom__">Autre (saisir manuellement)</option>
              </select>
              {selectedModel === '__custom__' && (
                <input
                  type="text"
                  placeholder="Nom du modèle exact (ex: gpt-4o-mini)"
                  onChange={e => setSelectedModel(e.target.value === '__custom__' ? '' : e.target.value)}
                  style={{
                    marginTop: 8, width: '100%', padding: '9px 12px', borderRadius: 8,
                    border: '1px solid var(--border, #d1d5db)',
                    background: 'var(--input-bg, #fff)', fontSize: 14,
                    color: 'var(--text, #111)', boxSizing: 'border-box',
                  }}
                />
              )}
            </div>

            {/* Clé API */}
            <div>
              <label style={{ display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 6 }}>
                Clé API
                {providerInfo?.hint && (
                  <span style={{ fontWeight: 400, color: '#94a3b8', marginLeft: 8 }}>
                    {providerInfo.hint}
                  </span>
                )}
              </label>
              <div style={{ position: 'relative' }}>
                <input
                  type={showKey ? 'text' : 'password'}
                  value={apiKey}
                  onChange={e => { setApiKey(e.target.value); setTestResult(null); setSaveResult(null); }}
                  placeholder="Coller votre clé API ici"
                  style={{
                    width: '100%', padding: '9px 44px 9px 12px', borderRadius: 8,
                    border: '1px solid var(--border, #d1d5db)',
                    background: 'var(--input-bg, #fff)', fontSize: 14,
                    color: 'var(--text, #111)', fontFamily: 'monospace',
                    boxSizing: 'border-box',
                  }}
                />
                <button
                  onClick={() => setShowKey(v => !v)}
                  type="button"
                  style={{
                    position: 'absolute', right: 10, top: '50%', transform: 'translateY(-50%)',
                    background: 'none', border: 'none', cursor: 'pointer',
                    color: '#94a3b8', fontSize: 12, padding: '2px 4px',
                  }}
                >
                  {showKey ? 'Masquer' : 'Voir'}
                </button>
              </div>
            </div>

            {/* Prompt système (avancé) */}
            <div>
              <button
                type="button"
                onClick={() => setShowAdvanced(v => !v)}
                style={{
                  background: 'none', border: 'none', cursor: 'pointer',
                  color: 'var(--primary, #6366f1)', fontSize: 13, padding: 0,
                  display: 'flex', alignItems: 'center', gap: 4,
                }}
              >
                <span style={{ transform: showAdvanced ? 'rotate(90deg)' : 'none', display: 'inline-block', transition: '0.2s' }}>▶</span>
                Prompt système personnalisé (optionnel)
              </button>

              {showAdvanced && (
                <div style={{ marginTop: 10 }}>
                  <p style={{ fontSize: 12, color: '#94a3b8', margin: '0 0 8px' }}>
                    Laissez vide pour utiliser le prompt par défaut. Personnalisez le comportement de l'IA selon votre contexte métier.
                  </p>
                  <textarea
                    value={systemPrompt}
                    onChange={e => setSystemPrompt(e.target.value)}
                    rows={6}
                    placeholder="Tu es un assistant de support client pour [Nom de l'entreprise]..."
                    style={{
                      width: '100%', padding: '10px 12px', borderRadius: 8,
                      border: '1px solid var(--border, #d1d5db)',
                      background: 'var(--input-bg, #fff)', fontSize: 13,
                      color: 'var(--text, #111)', resize: 'vertical',
                      fontFamily: 'inherit', boxSizing: 'border-box',
                    }}
                  />
                </div>
              )}
            </div>
          </div>

          {/* ── Résultat test ───────────────────────────────────────── */}
          {testResult && (
            <div style={{
              marginTop: 16, padding: '12px 16px', borderRadius: 8,
              background: testResult.success ? '#f0fdf4' : '#fff1f2',
              border: `1px solid ${testResult.success ? '#86efac' : '#fecaca'}`,
              fontSize: 13,
            }}>
              <div style={{ fontWeight: 600, color: testResult.success ? '#15803d' : '#dc2626', marginBottom: testResult.preview ? 6 : 0 }}>
                {testResult.success ? '✅ ' : '❌ '}{testResult.message}
              </div>
              {testResult.preview && (
                <div style={{ color: '#374151', fontStyle: 'italic' }}>
                  « {testResult.preview} »
                </div>
              )}
            </div>
          )}

          {/* ── Résultat sauvegarde ─────────────────────────────────── */}
          {saveResult && (
            <div style={{
              marginTop: 16, padding: '12px 16px', borderRadius: 8,
              background: saveResult.success ? '#f0fdf4' : '#fff1f2',
              border: `1px solid ${saveResult.success ? '#86efac' : '#fecaca'}`,
              fontSize: 13, fontWeight: 600,
              color: saveResult.success ? '#15803d' : '#dc2626',
            }}>
              {saveResult.success ? '✅ ' : '❌ '}{saveResult.message}
            </div>
          )}

          {/* ── Boutons ─────────────────────────────────────────────── */}
          <div style={{ display: 'flex', gap: 10, marginTop: 20, flexWrap: 'wrap' }}>
            <button
              onClick={handleTest}
              disabled={!canTest || testing}
              style={{
                padding: '9px 20px', borderRadius: 8, border: '1.5px solid var(--primary, #6366f1)',
                background: 'transparent', color: 'var(--primary, #6366f1)',
                fontWeight: 600, fontSize: 14, cursor: canTest && !testing ? 'pointer' : 'not-allowed',
                opacity: canTest && !testing ? 1 : 0.5,
              }}
            >
              {testing ? 'Test en cours…' : 'Tester la connexion'}
            </button>

            <button
              onClick={handleSave}
              disabled={!canSave || saving}
              style={{
                padding: '9px 24px', borderRadius: 8, border: 'none',
                background: canSave && !saving ? 'var(--primary, #6366f1)' : '#c7d2fe',
                color: '#fff', fontWeight: 600, fontSize: 14,
                cursor: canSave && !saving ? 'pointer' : 'not-allowed',
              }}
            >
              {saving ? 'Sauvegarde…' : 'Sauvegarder la configuration'}
            </button>
          </div>

          <p style={{ fontSize: 12, color: '#94a3b8', marginTop: 12 }}>
            La clé API est chiffrée avant d'être stockée en base de données.
          </p>
        </div>
      )}

      {/* ── État vide ─────────────────────────────────────────────────── */}
      {!selectedProvider && !loadingConfig && (
        <div style={{
          textAlign: 'center', padding: '40px 20px',
          background: 'var(--card-bg, #fff)', borderRadius: 12,
          border: '1px dashed var(--border, #e2e8f0)',
          color: 'var(--text-secondary, #94a3b8)',
        }}>
          <div style={{ fontSize: 32, marginBottom: 12 }}>🔑</div>
          <p style={{ margin: 0, fontWeight: 500 }}>
            Cliquez sur un provider ci-dessus pour configurer l'IA
          </p>
          <p style={{ margin: '6px 0 0', fontSize: 13 }}>
            Commencez par Gemini ou Groq — tous deux sont gratuits sans CB
          </p>
        </div>
      )}
    </div>
  );
}
