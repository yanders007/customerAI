import { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { api, clearSession, saveProject, saveSession } from './api';
import AiConfigPage from './AiConfigPage';
import DOMPurify from 'dompurify';
import './styles/login.css';
import './styles/admin.css';
import './styles/client.css';

// ════════════════════════════════════════════════
//  HELPERS COMMUNS
// ════════════════════════════════════════════════
function Spinner() {
  return (
    <div style={{ display:'flex', alignItems:'center', justifyContent:'center', minHeight:'100vh' }}>
      <div style={{ textAlign:'center' }}>
        <div style={{ width:40, height:40, border:'3px solid #e2e8f0', borderTop:'3px solid #6366f1', borderRadius:'50%', animation:'spin 0.7s linear infinite', margin:'0 auto 1rem' }}/>
        <p style={{ color:'#64748b' }}>Chargement…</p>
      </div>
      <style>{`@keyframes spin{to{transform:rotate(360deg)}}`}</style>
    </div>
  );
}

function Toast({ message, kind = 'success', onClose }) {
  useEffect(() => { const t = setTimeout(onClose, 3500); return () => clearTimeout(t); }, [onClose]);
  const bg = kind === 'success' ? '#10b981' : kind === 'error' ? '#ef4444' : '#f59e0b';
  return (
    <div style={{ position:'fixed', bottom:'1.5rem', right:'1.5rem', zIndex:9999, background:bg, color:'#fff', padding:'0.75rem 1.25rem', borderRadius:10, fontWeight:600, fontSize:14, boxShadow:'0 10px 30px rgba(0,0,0,.2)' }}>
      {message}
    </div>
  );
}

// ════════════════════════════════════════════════
//  LOGIN CLIENT
// ════════════════════════════════════════════════
export function ClientLogin() {
  const [identifier, setIdentifier] = useState('');
  const [password, setPassword]     = useState('');
  const [error, setError]           = useState('');
  const [loading, setLoading]       = useState(false);
  const [checking, setChecking]     = useState(true);
  const [showPwd, setShowPwd]       = useState(false);
  const navigate = useNavigate();

  useEffect(() => {
    console.log('[ClientLogin] Vérification session existante...');
    api.get('/client/me').then(r => {
      console.log('[ClientLogin] Session trouvée, redirection');
      saveSession('client', r.data.client);
      navigate(r.data.project ? '/chat' : '/projects', { replace: true });
    }).catch((err) => {
      console.log('[ClientLogin] Pas de session active', err.response?.status);
      clearSession();
    }).finally(() => setChecking(false));
  }, [navigate]);

  const handleSubmit = async e => {
    e.preventDefault(); 
    setLoading(true); 
    setError('');
    
    console.log('[ClientLogin] Tentative de connexion...', { 
      identifier, 
      baseURL: api.defaults.baseURL,
      userAgent: navigator.userAgent 
    });
    
    try {
      const r = await api.post('/client/login', { client_identifier: identifier, password });
      console.log('[ClientLogin] Réponse reçue:', r.data);
      
      if (r.data.success) { 
        console.log('[ClientLogin] Connexion réussie');
        saveSession('client', r.data.client); 
        navigate('/projects'); 
      } else {
        // Cas où success=false dans la réponse
        setError(r.data.error || 'Échec de connexion.');
      }
    } catch (err) { 
      console.error('[ClientLogin] Erreur complète:', {
        message: err.message,
        code: err.code,
        status: err.response?.status,
        data: err.response?.data,
        config: {
          url: err.config?.url,
          method: err.config?.method,
          baseURL: err.config?.baseURL
        }
      });
      
      let errorMsg = 'Identifiants incorrects.';
      
      if (err.code === 'ECONNABORTED' || err.code === 'ERR_NETWORK') {
        errorMsg = 'Connexion impossible. Vérifiez votre réseau et réessayez.';
      } else if (err.response?.status === 401) {
        errorMsg = err.response?.data?.error || 'Identifiants invalides.';
      } else if (err.response?.status >= 500) {
        errorMsg = 'Erreur serveur. Réessayez dans quelques instants.';
      } else if (err.response?.data?.error) {
        errorMsg = err.response.data.error;
      }
      
      setError(errorMsg); 
    }
    finally { setLoading(false); }
  };

  if (checking) return <Spinner/>;
  return (
    <div className="login-container">
      <div className="login-left">
        <div className="login-brand">
          <div className="logo"><i className="fas fa-headset"/><span>Support Client</span></div>
          <p>Votre assistant support disponible 24h/24 pour répondre à toutes vos questions.</p>
        </div>
        <div className="login-features">
          <div className="feature-item">
            <i className="fas fa-bolt"/>
            <div><h4>Réponses Instantanées</h4><p>Obtenez des réponses précises en moins d'une seconde.</p></div>
          </div>
          <div className="feature-item">
            <i className="fas fa-book"/>
            <div><h4>Documentation Intégrée</h4><p>Accédez rapidement à toute la documentation nécessaire.</p></div>
          </div>
          <div className="feature-item">
            <i className="fas fa-shield-alt"/>
            <div><h4>Sécurisé & Privé</h4><p>Vos données sont chiffrées et protégées.</p></div>
          </div>
        </div>
      </div>
      <div className="login-right">
        <div className="login-box">
          <h2>Espace Client</h2>
          <p>Connectez-vous pour accéder au support</p>
          {error && <div style={{ background:'#fef2f2', color:'#991b1b', padding:'10px 14px', borderRadius:8, marginBottom:16, fontSize:14 }}>{error}</div>}
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label><i className="fas fa-id-card"/> Identifiant client</label>
              <input value={identifier} onChange={e => setIdentifier(e.target.value)} placeholder="CLIENT-XXXXXX" required/>
            </div>
            <div className="form-group">
              <label><i className="fas fa-lock"/> Mot de passe</label>
              <div className="password-input">
                <input type={showPwd ? 'text' : 'password'} value={password} onChange={e => setPassword(e.target.value)} placeholder="••••••••" required/>
                <button type="button" className="toggle-password" onClick={() => setShowPwd(!showPwd)}>
                  <i className={`fas fa-eye${showPwd ? '-slash' : ''}`}/>
                </button>
              </div>
            </div>
            <div className="form-options">
              <span/>
              <a href="/forgot-password?type=client" className="forgot-link">Mot de passe oublié ?</a>
            </div>
            <button type="submit" className="btn btn-primary btn-block btn-lg" disabled={loading}>
              <i className="fas fa-sign-in-alt"/> {loading ? 'Connexion…' : 'Se connecter'}
            </button>
          </form>
          <p style={{ textAlign:'center', marginTop:20, fontSize:13, color:'#64748b' }}>
            Espace administrateur ? <a href="/login-admin" style={{ color:'#6366f1', fontWeight:600 }}>Connexion Admin</a>
          </p>
        </div>
      </div>
    </div>
  );
}

// ════════════════════════════════════════════════
//  LOGIN ADMIN
// ════════════════════════════════════════════════
export function AdminLogin() {
  const [email, setEmail]       = useState('');
  const [password, setPassword] = useState('');
  const [error, setError]       = useState('');
  const [loading, setLoading]   = useState(false);
  const [checking, setChecking] = useState(true);
  const [showPwd, setShowPwd]   = useState(false);
  const navigate = useNavigate();

  useEffect(() => {
    console.log('[AdminLogin] Vérification session existante...');
    api.get('/admin/me').then(r => {
      console.log('[AdminLogin] Session trouvée, redirection vers /admin');
      saveSession('admin', r.data.admin);
      navigate('/admin', { replace: true });
    }).catch((err) => {
      console.log('[AdminLogin] Pas de session active', err.response?.status);
      clearSession();
    }).finally(() => setChecking(false));
  }, [navigate]);

  const handleSubmit = async e => {
    e.preventDefault(); 
    setLoading(true); 
    setError('');
    
    console.log('[AdminLogin] Tentative de connexion...', { email });
    
    try {
      const r = await api.post('/admin/login', { email, password });
      console.log('[AdminLogin] Réponse reçue:', r.data);
      
      if (r.data.success) { 
        console.log('[AdminLogin] Connexion réussie');
        saveSession('admin', r.data.admin); 
        navigate('/admin'); 
      }
    } catch (err) { 
      console.error('[AdminLogin] Erreur:', err);
      const errorMsg = err.response?.data?.error || err.message || 'Identifiants incorrects.';
      setError(errorMsg);
      
      // Timeout spécifique pour mobile
      if (err.code === 'ECONNABORTED') {
        setError('Connexion trop lente. Vérifiez votre connexion internet.');
      }
    }
    finally { setLoading(false); }
  };

  if (checking) return <Spinner/>;
  return (
    <div className="login-container">
      <div className="login-left">
        <div className="login-brand">
          <div className="logo"><i className="fas fa-headset"/><span>Support Client</span></div>
          <p>Plateforme d'administration de votre système de support client.</p>
        </div>
        <div className="login-features">
          <div className="feature-item">
            <i className="fas fa-chart-line"/>
            <div><h4>Analytics Avancés</h4><p>Suivez les performances et l'activité en temps réel.</p></div>
          </div>
          <div className="feature-item">
            <i className="fas fa-users"/>
            <div><h4>Gestion Clients</h4><p>Créez et gérez vos clients et leurs projets.</p></div>
          </div>
          <div className="feature-item">
            <i className="fas fa-cogs"/>
            <div><h4>Contrôle Total</h4><p>FAQ, documentations, escalades et paramètres.</p></div>
          </div>
        </div>
      </div>
      <div className="login-right">
        <div className="login-box">
          <h2>Espace Administrateur</h2>
          <p>Connectez-vous pour gérer votre plateforme</p>
          {error && <div style={{ background:'#fef2f2', color:'#991b1b', padding:'10px 14px', borderRadius:8, marginBottom:16, fontSize:14 }}>{error}</div>}
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label><i className="fas fa-envelope"/> Adresse Email</label>
              <input type="email" value={email} onChange={e => setEmail(e.target.value)} placeholder="admin@agence.com" required autoFocus/>
            </div>
            <div className="form-group">
              <label><i className="fas fa-lock"/> Mot de passe</label>
              <div className="password-input">
                <input type={showPwd ? 'text' : 'password'} value={password} onChange={e => setPassword(e.target.value)} placeholder="••••••••" required/>
                <button type="button" className="toggle-password" onClick={() => setShowPwd(!showPwd)}>
                  <i className={`fas fa-eye${showPwd ? '-slash' : ''}`}/>
                </button>
              </div>
            </div>
            <div className="form-options">
              <span/>
              <a href="/forgot-password?type=admin" className="forgot-link">Mot de passe oublié ?</a>
            </div>
            <button type="submit" className="btn btn-primary btn-block btn-lg" disabled={loading}>
              <i className="fas fa-sign-in-alt"/> {loading ? 'Connexion…' : 'Se connecter'}
            </button>
          </form>
          <p style={{ textAlign:'center', marginTop:20, fontSize:13, color:'#64748b' }}>
            Espace client ? <a href="/login-client" style={{ color:'#6366f1', fontWeight:600 }}>Connexion Client</a>
          </p>
        </div>
      </div>
    </div>
  );
}

// ════════════════════════════════════════════════
//  MOT DE PASSE OUBLIÉ / RESET
// ════════════════════════════════════════════════
export function ForgotPassword() {
  const [email, setEmail]     = useState('');
  const [sent, setSent]       = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError]     = useState('');
  const [params]              = useSearchParams();
  const type                  = params.get('type') || 'client';

  const handleSubmit = async e => {
    e.preventDefault(); setLoading(true); setError('');
    try { await api.post('/password/request', { email, type }); setSent(true); }
    catch { setError('Une erreur est survenue.'); }
    finally { setLoading(false); }
  };

  return (
    <div style={{ minHeight:'100vh', display:'flex', alignItems:'center', justifyContent:'center', background:'#f8fafc', padding:'2rem' }}>
      <div style={{ background:'#fff', borderRadius:16, padding:'2.5rem 2rem', width:'100%', maxWidth:420, boxShadow:'0 10px 30px rgba(0,0,0,.1)' }}>
        <div style={{ textAlign:'center', marginBottom:'1.5rem' }}>
          <i className="fas fa-comments" style={{ fontSize:32, color:'#6366f1' }}/>
          <h2 style={{ fontSize:24, fontWeight:800, marginTop:12 }}>Mot de passe oublié</h2>
        </div>
        {sent ? (
          <>
            <div style={{ background:'#ecfdf5', color:'#065f46', padding:'12px 16px', borderRadius:8, marginBottom:16, fontSize:14 }}>
              ✅ Lien de réinitialisation envoyé si cet email existe.
            </div>
            <a href={type === 'admin' ? '/login-admin' : '/login-client'} className="btn btn-primary btn-block" style={{ display:'flex' }}>
              Retour à la connexion
            </a>
          </>
        ) : (
          <>
            <p style={{ fontSize:14, color:'#64748b', marginBottom:'1.5rem', textAlign:'center' }}>
              Entrez votre email pour recevoir un lien de réinitialisation.
            </p>
            {error && <div style={{ background:'#fef2f2', color:'#991b1b', padding:'10px', borderRadius:8, marginBottom:12, fontSize:14 }}>{error}</div>}
            <form onSubmit={handleSubmit}>
              <div className="form-group">
                <label>Email</label>
                <input type="email" value={email} onChange={e => setEmail(e.target.value)} placeholder="votre@email.com" required autoFocus/>
              </div>
              <button type="submit" className="btn btn-primary btn-block" disabled={loading}>
                {loading ? 'Envoi…' : 'Envoyer le lien'}
              </button>
            </form>
          </>
        )}
      </div>
    </div>
  );
}

export function ResetPassword() {
  const [password, setPassword] = useState('');
  const [confirm, setConfirm]   = useState('');
  const [done, setDone]         = useState(false);
  const [loading, setLoading]   = useState(false);
  const [error, setError]       = useState('');
  const [params]                = useSearchParams();
  const token = params.get('token') || '';
  const type  = params.get('type')  || 'client';

  const handleSubmit = async e => {
    e.preventDefault();
    if (password !== confirm) { setError('Les mots de passe ne correspondent pas.'); return; }
    setLoading(true); setError('');
    try { await api.post('/password/reset', { token, type, password, password_confirmation: confirm }); setDone(true); }
    catch (err) { setError(err.response?.data?.error || 'Lien invalide ou expiré.'); }
    finally { setLoading(false); }
  };

  return (
    <div style={{ minHeight:'100vh', display:'flex', alignItems:'center', justifyContent:'center', background:'#f8fafc', padding:'2rem' }}>
      <div style={{ background:'#fff', borderRadius:16, padding:'2.5rem 2rem', width:'100%', maxWidth:420, boxShadow:'0 10px 30px rgba(0,0,0,.1)' }}>
        <div style={{ textAlign:'center', marginBottom:'1.5rem' }}>
          <i className="fas fa-lock" style={{ fontSize:32, color:'#6366f1' }}/>
          <h2 style={{ fontSize:24, fontWeight:800, marginTop:12 }}>Nouveau mot de passe</h2>
        </div>
        {done ? (
          <>
            <div style={{ background:'#ecfdf5', color:'#065f46', padding:'12px', borderRadius:8, marginBottom:16, fontSize:14 }}>✅ Mot de passe réinitialisé !</div>
            <a href={type === 'admin' ? '/login-admin' : '/login-client'} className="btn btn-primary btn-block" style={{ display:'flex' }}>Se connecter</a>
          </>
        ) : (
          <>
            {error && <div style={{ background:'#fef2f2', color:'#991b1b', padding:'10px', borderRadius:8, marginBottom:12, fontSize:14 }}>{error}</div>}
            <form onSubmit={handleSubmit}>
              <div className="form-group">
                <label>Nouveau mot de passe</label>
                <input type="password" value={password} onChange={e => setPassword(e.target.value)} placeholder="8 caractères minimum" required/>
              </div>
              <div className="form-group">
                <label>Confirmer</label>
                <input type="password" value={confirm} onChange={e => setConfirm(e.target.value)} placeholder="••••••••" required/>
              </div>
              <button type="submit" className="btn btn-primary btn-block" disabled={loading}>{loading ? 'Enregistrement…' : 'Réinitialiser'}</button>
            </form>
          </>
        )}
      </div>
    </div>
  );
}

// ════════════════════════════════════════════════
//  PROJECT SELECT (client)
// ════════════════════════════════════════════════
export function ProjectSelect() {
  const [projects, setProjects] = useState([]);
  const [loading, setLoading]   = useState(true);
  const [client, setClient]     = useState(null);
  const navigate = useNavigate();

  useEffect(() => {
    (async () => {
      console.log('[ProjectSelect] Chargement projets...');
      try {
        const [me, pr] = await Promise.all([api.get('/client/me'), api.get('/client/projets')]);
        console.log('[ProjectSelect] Authentifié:', me.data.client.name);
        console.log('[ProjectSelect] Projets:', pr.data.data?.length);
        saveSession('client', me.data.client);
        setClient(me.data.client);
        setProjects(pr.data.data || []);
      } catch (err) { 
        console.error('[ProjectSelect] Erreur:', err.response?.status);
        clearSession(); 
        navigate('/login-client', { replace: true }); 
      }
      finally { setLoading(false); }
    })();
  }, [navigate]);

  const pick = async p => { 
    saveProject(p); 
    // Également sélectionner le projet côté backend (session)
    try {
      await api.post('/client/select-project', { project_id: p.id });
    } catch (err) {
      console.error('Erreur sélection projet backend:', err);
    }
    navigate('/chat'); 
  };

  if (loading) return <Spinner/>;
  return (
    <div className="sidebar" style={{ position:'static', width:'100%', height:'auto', border:'none' }}>
      <div style={{ minHeight:'100vh', background:'#f8fafc' }}>
        <header style={{ background:'#fff', borderBottom:'1px solid #e2e8f0', padding:'0 2rem', height:64, display:'flex', alignItems:'center', justifyContent:'space-between' }}>
          <div className="logo"><i className="fas fa-headset"/><span>Support Client</span></div>
          <div style={{ display:'flex', alignItems:'center', gap:12 }}>
            <span style={{ fontSize:14, color:'#64748b' }}>Bonjour, <strong>{client?.name}</strong></span>
            <button className="logout-btn" onClick={async () => { await api.post('/client/logout'); clearSession(); navigate('/login-client'); }} style={{ width:'auto', padding:'8px 16px', background:'#fef2f2', color:'#ef4444', borderRadius:8, fontWeight:600, fontSize:13 }}>
              <i className="fas fa-sign-out-alt"/> Déconnexion
            </button>
          </div>
        </header>
        <main style={{ padding:'3rem 2rem', maxWidth:860, margin:'0 auto' }}>
          <h1 style={{ fontSize:28, fontWeight:800, color:'#1e1b4b', marginBottom:8 }}>Vos projets</h1>
          <p style={{ color:'#64748b', marginBottom:'2rem' }}>Sélectionnez un projet pour démarrer avec le support.</p>
          {projects.length === 0 ? (
            <div style={{ textAlign:'center', padding:'4rem', color:'#64748b' }}>
              <i className="fas fa-folder-open" style={{ fontSize:48, marginBottom:16, opacity:.3 }}/>
              <p>Aucun projet disponible pour votre compte.</p>
            </div>
          ) : (
            <div style={{ display:'grid', gridTemplateColumns:'repeat(auto-fill,minmax(240px,1fr))', gap:16 }}>
              {projects.map(p => (
                <button key={p.id} onClick={() => pick(p)} style={{ background:'#fff', border:'1.5px solid #e2e8f0', borderRadius:16, padding:'1.5rem', textAlign:'left', cursor:'pointer', transition:'all .2s' }}
                  onMouseEnter={e => { e.currentTarget.style.borderColor='#6366f1'; e.currentTarget.style.transform='translateY(-3px)'; e.currentTarget.style.boxShadow='0 10px 30px rgba(99,102,241,.15)'; }}
                  onMouseLeave={e => { e.currentTarget.style.borderColor='#e2e8f0'; e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow=''; }}>
                  <div style={{ width:44, height:44, background:'rgba(99,102,241,.1)', borderRadius:12, display:'flex', alignItems:'center', justifyContent:'center', marginBottom:12 }}>
                    <i className="fas fa-folder" style={{ color:'#6366f1', fontSize:20 }}/>
                  </div>
                  <h3 style={{ fontWeight:700, color:'#1e1b4b', marginBottom:4, fontSize:15 }}>{p.nom_projet}</h3>
                  <p style={{ fontSize:13, color:'#64748b' }}>Cliquez pour accéder à l'assistant</p>
                </button>
              ))}
            </div>
          )}
        </main>
      </div>
    </div>
  );
}

// ════════════════════════════════════════════════
//  CHAT CLIENT
// ════════════════════════════════════════════════
export function Chat() {
  const [conversations, setConversations] = useState([]);
  const [activeConvId, setActiveConvId]   = useState(null);
  const [activeConvUuid, setActiveConvUuid] = useState(null);
  const [messages, setMessages]           = useState([]);
  const [input, setInput]                 = useState('');
  const [loading, setLoading]             = useState(true);
  const [sending, setSending]             = useState(false);
  const [project, setProject]             = useState(null);
  const [client, setClient]               = useState(null);
  const [escalated, setEscalated]         = useState(false);
  const [sidebarOpen, setSidebarOpen]     = useState(true);
  const bottomRef = useRef(null);
  const inputRef  = useRef(null);
  const navigate  = useNavigate();

  useEffect(() => {
    (async () => {
      console.log('[Chat] Initialisation...');
      try {
        const me = await api.get('/client/me');
        console.log('[Chat] Authentifié:', me.data.client.name);
        saveSession('client', me.data.client);
        setClient(me.data.client);
        const stored = JSON.parse(localStorage.getItem('sia_session') || '{}');
        if (!stored.project) { 
          console.log('[Chat] Aucun projet sélectionné, redirection');
          navigate('/projects', { replace: true }); 
          return; 
        }
        console.log('[Chat] Projet:', stored.project.nom_projet);
        setProject(stored.project);
        const convRes = await api.get('/client/conversations');
        const convs = convRes.data.data || [];
        // Dédupliquer par UUID pour éviter les doublons
        const uniqueConvs = Array.from(new Map(convs.map(c => [c.uuid, c])).values());
        setConversations(uniqueConvs);
        const last = uniqueConvs.find(c => c.status === 'open') || uniqueConvs[0];
        if (last) await loadConversation(last.id);
        else await startNewConversation(stored.project);
      } catch (err) { 
        console.error('Erreur lors de l\'initialisation:', err);
        clearSession(); 
        navigate('/login-client', { replace: true }); 
      }
      finally { setLoading(false); }
    })();
  }, [navigate]);

  useEffect(() => { bottomRef.current?.scrollIntoView({ behavior: 'smooth' }); }, [messages]);

  // Polling pour vérifier les nouveaux messages (toutes les 3 secondes si escaladé)
  useEffect(() => {
    if (!escalated || !activeConvId) return;
    
    const interval = setInterval(async () => {
      try {
        const res = await api.get(`/client/conversations/${activeConvId}`);
        const conv = res.data.conversation;
        const newMessages = res.data.messages || [];
        
        // Si nouveau message du support, mettre à jour
        if (newMessages.length > messages.length) {
          setMessages(newMessages);
          
          // Si résolu, réactiver l'input
          if (conv?.status === 'resolved') {
            setEscalated(false);
          }
        }
      } catch (err) {
        console.error('Erreur polling:', err);
      }
    }, 3000);
    
    return () => clearInterval(interval);
  }, [escalated, activeConvId, messages.length]);

  const loadConversation = async id => {
    setActiveConvId(id);
    const res = await api.get(`/client/conversations/${id}`);
    const conv = res.data.conversation;
    setActiveConvUuid(conv?.uuid || null);
    setMessages(res.data.messages || []);
    const isEscalated = conv?.status === 'escalated';
    setEscalated(isEscalated);
    
    // Si la conversation est résolue par le support, la réactiver et réinitialiser escalated
    if (conv?.status === 'resolved') {
      setEscalated(false);
    }
  };

  const startNewConversation = async (proj = project) => {
    if (!proj) return;
    const res = await api.post('/client/conversations', { projet_id: proj.id });
    const newConv = res.data.conversation;
    setActiveConvId(newConv.id);
    setActiveConvUuid(newConv.uuid || null);
    setMessages([{ id:'welcome', role:'assistant', content:`Bonjour ! Je suis votre assistant pour le projet **${proj.nom_projet}**. Comment puis-je vous aider ?`, created_at: new Date().toISOString() }]);
    setEscalated(false);
    const convRes = await api.get('/client/conversations');
    setConversations(convRes.data.data || []);
    return newConv;
  };

  const send = async () => {
    if (!input.trim() || sending || escalated) return; // Bloquer si escaladé
    
    // Si pas de conversation active, créer une nouvelle
    let convId = activeConvId;
    let convUuid = activeConvUuid;
    
    if (!convId) {
      const newConv = await startNewConversation();
      if (!newConv) return;
      convId = newConv.id;
      convUuid = newConv.uuid;
    }
    
    const text = input.trim();
    setInput('');
    setSending(true);
    const userMsg = { id: Date.now(), role:'user', content:text, created_at: new Date().toISOString() };
    setMessages(prev => [...prev, userMsg]);
    
    try {
      const res = await api.post('/client/ask', { 
        conversation_id: convId, 
        conversation_uuid: convUuid, 
        question: text 
      });
      
      // Si en attente support, ne pas ajouter de message bot
      if (res.data.waiting_human) {
        setEscalated(true);
        return;
      }
      
      const botMsg = { 
        id: Date.now()+1, 
        role:'assistant', 
        content: res.data.answer || 'Réponse en attente...', 
        created_at: new Date().toISOString() 
      };
      setMessages(prev => [...prev, botMsg]);
      
      if (res.data.escalated) setEscalated(true);
      
      const convRes = await api.get('/client/conversations');
      setConversations(convRes.data.data || []);
    } catch (err) {
      console.error('Erreur lors de l\'envoi:', err);
      const errorMsg = err.response?.data?.error || 'Erreur de connexion. Veuillez réessayer.';
      setMessages(prev => [...prev, { 
        id: Date.now()+1, 
        role:'assistant', 
        content: `❌ ${errorMsg}`, 
        created_at: new Date().toISOString() 
      }]);
    } finally { 
      setSending(false); 
      inputRef.current?.focus(); 
    }
  };

  const formatTime = iso => { try { return new Date(iso).toLocaleTimeString('fr-FR', { hour:'2-digit', minute:'2-digit' }); } catch { return ''; }};
  const formatDate = iso => { try { return new Date(iso).toLocaleDateString('fr-FR', { day:'numeric', month:'short' }); } catch { return ''; }};
  // ✅ Sanitization XSS avec DOMPurify : nettoie le HTML avant de l'injecter
  const renderMd = text => {
    if (!text) return '';
    const htmlWithMarkdown = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    return DOMPurify.sanitize(htmlWithMarkdown, { 
      ALLOWED_TAGS: ['strong', 'b', 'i', 'em', 'u', 'br', 'p', 'a'],
      ALLOWED_ATTR: ['href', 'target', 'rel']
    });
  };

  if (loading) return <Spinner/>;

  const roleCls = { user:'user', assistant:'assistant', human_support:'human_support' };

  return (
    <div style={{ display:'flex', height:'100vh', overflow:'hidden', background:'#f8fafc' }}>
      {/* ─ Sidebar ─ */}
      <aside className={`sidebar ${sidebarOpen ? '' : 'collapsed'}`} style={{ position:'relative', height:'100vh', flexShrink:0 }}>
        <div className="sidebar-header">
          <div className="logo"><i className="fas fa-headset"/><span>Support Client</span></div>
          <button className="sidebar-toggle" onClick={() => setSidebarOpen(!sidebarOpen)}>
            <i className="fas fa-chevron-left"/>
          </button>
        </div>
        <nav className="sidebar-nav">
          <button className="nav-item active" onClick={() => startNewConversation()} style={{ width:'100%', textAlign:'left' }}>
            <i className="fas fa-plus-circle"/><span>Nouvelle conversation</span>
          </button>
          <div style={{ padding:'8px 12px', fontSize:11, fontWeight:700, color:'#94a3b8', textTransform:'uppercase', letterSpacing:'.06em', marginTop:8, display:'flex', alignItems:'center', justifyContent:'space-between' }}>
            <span>Historique</span>
            {conversations.length > 0 && (
              <button onClick={async () => {
                if (!confirm(`Supprimer toutes les ${conversations.length} conversations ?`)) return;
                try {
                  await Promise.all(conversations.map(c => api.delete(`/client/conversations/${c.id}`).catch(() => {})));
                  setConversations([]);
                  await startNewConversation();
                } catch (err) {
                  console.error('Erreur suppression:', err);
                }
              }} style={{ background:'transparent', border:'none', color:'#ef4444', cursor:'pointer', padding:4, fontSize:12 }} title="Supprimer tout l'historique">
                <i className="fas fa-trash"/>
              </button>
            )}
          </div>
          {conversations.map(c => (
            <button key={c.id} className={`nav-item ${c.id === activeConvId ? 'active' : ''}`} onClick={() => loadConversation(c.id)} style={{ width:'100%', textAlign:'left', display:'flex', alignItems:'center', gap:8 }}>
              <i className={`fas fa-${c.status === 'escalated' ? 'exclamation-circle' : 'comment'}`} style={{ color: c.status === 'escalated' ? '#ef4444' : '' }}/>
              <span style={{ overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap', flex:1 }}>Conv. #{c.id} · {formatDate(c.created_at)}</span>
              <button onClick={async (e) => {
                e.stopPropagation();
                if (!confirm('Supprimer cette conversation ?')) return;
                try {
                  await api.delete(`/client/conversations/${c.id}`);
                  setConversations(prev => prev.filter(conv => conv.id !== c.id));
                  if (c.id === activeConvId) {
                    const remaining = conversations.filter(conv => conv.id !== c.id);
                    if (remaining.length > 0) {
                      await loadConversation(remaining[0].id);
                    } else {
                      await startNewConversation();
                    }
                  }
                } catch (err) {
                  console.error('Erreur suppression:', err);
                }
              }} style={{ background:'transparent', border:'none', color:'#ef4444', cursor:'pointer', padding:4, fontSize:11, opacity:0, transition:'opacity .2s' }} className="delete-conv-btn" title="Supprimer">
                <i className="fas fa-times"/>
              </button>
            </button>
          ))}
        </nav>
        <div className="sidebar-footer">
          <div className="user-profile">
            <img src={`https://ui-avatars.com/api/?name=${encodeURIComponent(client?.name||'C')}&background=6366f1&color=fff`} alt="" className="user-avatar"/>
            <div className="user-info">
              <span className="user-name">{client?.name}</span>
              <span className="user-role">{project?.nom_projet}</span>
            </div>
          </div>
          <button className="btn btn-outline" onClick={() => { 
            const stored = JSON.parse(localStorage.getItem('sia_session') || '{}');
            delete stored.project;
            localStorage.setItem('sia_session', JSON.stringify(stored));
            navigate('/projects'); 
          }} style={{ width:'100%', padding:'8px 12px', marginBottom:8, fontSize:13, border:'1px solid #e2e8f0', background:'transparent', color:'#64748b', borderRadius:8, fontWeight:600, cursor:'pointer', display:'flex', alignItems:'center', justifyContent:'center', gap:6 }}>
            <i className="fas fa-exchange-alt"/> <span>Changer de projet</span>
          </button>
          <button className="logout-btn" onClick={async () => { await api.post('/client/logout'); clearSession(); navigate('/login-client'); }}>
            <i className="fas fa-sign-out-alt"/><span>Déconnexion</span>
          </button>
        </div>
      </aside>

      {/* ─ Main chat ─ */}
      <div style={{ flex:1, display:'flex', flexDirection:'column', overflow:'hidden' }}>
        <header style={{ background:'#fff', borderBottom:'1px solid #e2e8f0', padding:'0 1.5rem', height:64, display:'flex', alignItems:'center', justifyContent:'space-between', flexShrink:0 }}>
          <div>
            <h1 style={{ fontSize:18, fontWeight:800, color:'#1e1b4b' }}>{project?.nom_projet || 'Support'}</h1>
            <p style={{ fontSize:12, color:'#64748b' }}>Service client · Disponible 24h/24</p>
          </div>
          <button onClick={() => startNewConversation()} style={{ background:'rgba(99,102,241,.1)', color:'#6366f1', border:'1.5px solid #6366f1', padding:'8px 16px', borderRadius:8, fontWeight:600, fontSize:13, cursor:'pointer', display:'flex', alignItems:'center', gap:6 }}>
            <i className="fas fa-plus"/> Nouvelle conversation
          </button>
        </header>

        <div style={{ flex:1, overflowY:'auto', padding:'1.5rem', display:'flex', flexDirection:'column', gap:12 }}>
          {messages.length === 0 && (
            <div style={{ textAlign:'center', padding:'4rem', color:'#94a3b8' }}>
              <i className="fas fa-comments" style={{ fontSize:48, marginBottom:16, opacity:.3 }}/>
              <p>Posez votre première question à l'assistant</p>
            </div>
          )}
          {messages.map((m, i) => (
            <div key={m.id ?? i} style={{ display:'flex', justifyContent: m.role === 'user' ? 'flex-end' : 'flex-start' }}>
              {m.role !== 'user' && (
                <div style={{ width:32, height:32, borderRadius:'50%', background: m.role === 'human_support' ? '#7c3aed' : '#6366f1', display:'flex', alignItems:'center', justifyContent:'center', marginRight:8, flexShrink:0 }}>
                  <i className={`fas fa-${m.role === 'human_support' ? 'user-tie' : 'robot'}`} style={{ color:'#fff', fontSize:14 }}/>
                </div>
              )}
              <div style={{ maxWidth:'75%', padding:'10px 14px', borderRadius:16, background: m.role === 'user' ? '#6366f1' : m.role === 'human_support' ? '#7c3aed' : '#fff', color: m.role === 'user' || m.role === 'human_support' ? '#fff' : '#1e293b', border: m.role === 'assistant' ? '1px solid #e2e8f0' : 'none', borderBottomRightRadius: m.role === 'user' ? 4 : 16, borderBottomLeftRadius: m.role !== 'user' ? 4 : 16, boxShadow:'0 2px 8px rgba(0,0,0,.06)' }}>
                {m.role !== 'user' && <div style={{ fontSize:10, fontWeight:700, opacity:.6, marginBottom:4, textTransform:'uppercase', letterSpacing:'.05em' }}>{m.role === 'human_support' ? 'Support humain' : 'Assistant'}</div>}
                <div dangerouslySetInnerHTML={{ __html: renderMd(m.content) }} style={{ fontSize:14, lineHeight:1.6 }}/>
                <div style={{ fontSize:10, opacity:.5, marginTop:4, textAlign:'right' }}>{formatTime(m.created_at)}</div>
              </div>
            </div>
          ))}
          {sending && (
            <div style={{ display:'flex' }}>
              <div style={{ width:32, height:32, borderRadius:'50%', background:'#6366f1', display:'flex', alignItems:'center', justifyContent:'center', marginRight:8 }}><i className="fas fa-headset" style={{ color:'#fff', fontSize:14 }}/></div>
              <div style={{ background:'#fff', border:'1px solid #e2e8f0', borderRadius:16, borderBottomLeftRadius:4, padding:'12px 16px' }}>
                <div style={{ display:'flex', gap:4 }}>
                  {[0,1,2].map(i => <div key={i} style={{ width:8, height:8, borderRadius:'50%', background:'#94a3b8', animation:'bounce 1.2s infinite', animationDelay:`${i*.2}s` }}/>)}
                </div>
              </div>
            </div>
          )}
          {escalated && (
            <div style={{ background:'#fef3c7', border:'2px solid #f59e0b', borderRadius:12, padding:'16px 20px', textAlign:'center', fontSize:14, color:'#92400e', fontWeight:500, boxShadow:'0 4px 12px rgba(245,158,11,.15)' }}>
              <div style={{ display:'flex', alignItems:'center', justifyContent:'center', gap:8, marginBottom:8 }}>
                <i className="fas fa-user-headset" style={{ fontSize:20, color:'#f59e0b' }}/>
                <strong style={{ fontSize:15 }}>En attente du support humain</strong>
              </div>
              <p style={{ margin:0, fontSize:13, lineHeight:1.5 }}>
                Votre question a été transmise à notre équipe. Un agent va vous répondre sous peu. 
                <strong style={{ display:'block', marginTop:4 }}>Vous ne pouvez pas envoyer de nouveaux messages pendant l'attente.</strong>
              </p>
            </div>
          )}
          <div ref={bottomRef}/>
        </div>

        <div style={{ background:'#fff', borderTop:'1px solid #e2e8f0', padding:'1rem 1.5rem', display:'flex', gap:8, alignItems:'flex-end', flexShrink:0 }}>
          <textarea ref={inputRef} rows={1} style={{ flex:1, minHeight:44, maxHeight:160, resize:'none', padding:'10px 14px', border:'1.5px solid #e2e8f0', borderRadius:12, fontSize:14, fontFamily:'inherit', outline:'none', transition:'border-color .15s', overflowY:'auto', background: escalated ? '#f3f4f6' : '#fff', color: escalated ? '#9ca3af' : '#1e293b' }}
            placeholder={escalated ? "En attente du support humain..." : "Posez votre question… (Entrée pour envoyer)"}
            value={input} onChange={e => setInput(e.target.value)}
            onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey && !escalated) { e.preventDefault(); send(); }}}
            onFocus={e => { if (!escalated) e.target.style.borderColor='#6366f1'; }}
            onBlur={e => e.target.style.borderColor='#e2e8f0'}
            disabled={sending || escalated}/>
          <button onClick={send} disabled={sending || !input.trim() || escalated} style={{ width:44, height:44, background: (sending||!input.trim()||escalated) ? '#e2e8f0' : '#6366f1', color:'#fff', border:'none', borderRadius:10, cursor: (sending||!input.trim()||escalated) ? 'not-allowed' : 'pointer', display:'flex', alignItems:'center', justifyContent:'center', flexShrink:0, transition:'background .15s' }}>
            <i className="fas fa-paper-plane" style={{ fontSize:16 }}/>
          </button>
        </div>
      </div>
      <style>{`@keyframes bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}}`}</style>
    </div>
  );
}

// ════════════════════════════════════════════════
//  SUPPORT CONVERSATION
// ════════════════════════════════════════════════
export function SupportConversation() {
  const { uuid } = Object.fromEntries(useSearchParams()[0]) || {};
  const pathUuid = window.location.pathname.split('/').pop();
  const convUuid = uuid || pathUuid;
  const [conv, setConv]       = useState(null);
  const [reply, setReply]     = useState('');
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [error, setError]     = useState('');
  const bottomRef = useRef(null);

  const fetchConv = useCallback(async () => {
    try { const r = await api.get(`/support/conversation/${convUuid}`); setConv(r.data.conversation); }
    catch { setError('Conversation introuvable ou lien invalide.'); }
  }, [convUuid]);

  useEffect(() => { (async () => { await fetchConv(); setLoading(false); })(); }, [fetchConv]);
  useEffect(() => { const t = setInterval(fetchConv, 4000); return () => clearInterval(t); }, [fetchConv]);
  useEffect(() => { bottomRef.current?.scrollIntoView({ behavior:'smooth' }); }, [conv]);

  const handleReply = async (resolve = false) => {
    if (!reply.trim()) return;
    setSending(true); setError('');
    try { await api.post(`/support/conversation/${convUuid}/reply`, { reply, resolve }); setReply(''); await fetchConv(); }
    catch { setError("Erreur lors de l'envoi."); }
    finally { setSending(false); }
  };

  if (loading) return <Spinner/>;
  if (error && !conv) return (
    <div style={{ minHeight:'100vh', display:'flex', alignItems:'center', justifyContent:'center' }}>
      <div style={{ background:'#fef2f2', color:'#991b1b', padding:'1.5rem 2rem', borderRadius:12 }}>{error}</div>
    </div>
  );

  const roleCls = { user:'user', assistant:'assistant', human_support:'human_support' };
  const roleLabel = { user:'Client', assistant:'Assistant', human_support:'Support humain' };
  const isResolved = conv.status === 'resolved';

  return (
    <div style={{ display:'flex', flexDirection:'column', height:'100vh', background:'#f8fafc' }}>
      <header style={{ background:'#fff', borderBottom:'1px solid #e2e8f0', padding:'0 1.5rem', height:64, display:'flex', alignItems:'center', justifyContent:'space-between', flexShrink:0 }}>
        <div className="logo"><i className="fas fa-user-headset"/><span>Assistance humaine</span></div>
        <div>
          <span style={{ fontSize:14, color:'#64748b' }}>{conv.client} — {conv.projet}</span>
          <span style={{ marginLeft:12, padding:'4px 10px', borderRadius:20, fontSize:12, fontWeight:700, background: isResolved ? '#ecfdf5' : '#fef2f2', color: isResolved ? '#065f46' : '#991b1b' }}>
            {isResolved ? 'Résolue' : 'En attente'}
          </span>
        </div>
      </header>

      <div style={{ flex:1, overflowY:'auto', padding:'1.5rem', display:'flex', flexDirection:'column', gap:12 }}>
        {conv.messages?.map((m, i) => (
          <div key={m.id ?? i} style={{ display:'flex', justifyContent: m.role === 'user' ? 'flex-end' : 'flex-start' }}>
            <div style={{ maxWidth:'75%', padding:'10px 14px', borderRadius:16, background: m.role === 'user' ? '#6366f1' : m.role === 'human_support' ? '#7c3aed' : '#fff', color: m.role === 'user' || m.role === 'human_support' ? '#fff' : '#1e293b', border: m.role === 'assistant' ? '1px solid #e2e8f0' : 'none', borderBottomRightRadius: m.role === 'user' ? 4 : 16, borderBottomLeftRadius: m.role !== 'user' ? 4 : 16 }}>
              <div style={{ fontSize:10, fontWeight:700, opacity:.6, marginBottom:4, textTransform:'uppercase' }}>{roleLabel[m.role]}</div>
              {m.content}
            </div>
          </div>
        ))}
        <div ref={bottomRef}/>
      </div>

      <div style={{ background:'#fff', borderTop:'1px solid #e2e8f0', padding:'1.25rem 1.5rem', flexShrink:0 }}>
        {error && <div style={{ background:'#fef2f2', color:'#991b1b', padding:'8px 12px', borderRadius:8, marginBottom:12, fontSize:13 }}>{error}</div>}
        {isResolved && <div style={{ background:'#ecfdf5', color:'#065f46', padding:'8px 12px', borderRadius:8, marginBottom:12, fontSize:13 }}>Conversation résolue. Vous pouvez tout de même répondre si le client relance.</div>}
        <div style={{ background:'#ecfdf5', color:'#065f46', fontSize:12, marginBottom:10, display:'flex', alignItems:'center', gap:6 }}>
          <i className="fas fa-graduation-cap"/> Cette réponse sera ajoutée à la FAQ — le système saura répondre automatiquement la prochaine fois.
        </div>
        <textarea rows={3} style={{ width:'100%', padding:'10px 14px', border:'1.5px solid #e2e8f0', borderRadius:10, fontSize:14, fontFamily:'inherit', outline:'none', resize:'vertical', marginBottom:10 }}
          placeholder="Répondez au client ici…" value={reply} onChange={e => setReply(e.target.value)}/>
        <div style={{ display:'flex', gap:8 }}>
          <button className="btn btn-primary" disabled={!reply.trim() || sending} onClick={() => handleReply(false)}>
            {sending ? 'Envoi…' : 'Envoyer et continuer'}
          </button>
          <button className="btn" style={{ background:'#f1f5f9', color:'#475569' }} disabled={!reply.trim() || sending} onClick={() => handleReply(true)}>
            Envoyer et marquer résolue
          </button>
        </div>
      </div>
    </div>
  );
}

// ════════════════════════════════════════════════
//  ADMIN PANEL
// ════════════════════════════════════════════════
export function AdminPanel() {
  const [section, setSection]         = useState('dashboard');
  const [admin, setAdmin]             = useState(null);
  const [clients, setClients]         = useState([]);
  const [dashStats, setDashStats]     = useState(null);
  const [tokenStats, setTokenStats]   = useState(null);
  const [convs, setConvs]             = useState([]);
  const [selected, setSelected]       = useState(null);
  const [clientStats, setClientStats] = useState(null);
  const [projets, setProjets]         = useState([]);
  const [docs, setDocs]               = useState([]);
  const [faqs, setFaqs]               = useState([]);
  const [selProjet, setSelProjet]     = useState(null);
  const [selDoc, setSelDoc]           = useState(null);
  const [subTab, setSubTab]           = useState('projets');
  const [loading, setLoading]         = useState(true);
  const [sidebarOpen, setSidebarOpen] = useState(true);
  const [toast, setToast]             = useState(null);
  const [escalatedCount, setEscalatedCount] = useState(0);
  const [tokenPeriod, setTokenPeriod] = useState('week');
  const [showNewClient, setShowNewClient] = useState(false);
  const [showWizard, setShowWizard]   = useState(false);
  const navigate = useNavigate();

  const notify = (text, kind='success') => { setToast({ text, kind }); setTimeout(() => setToast(null), 3500); };

  useEffect(() => {
    (async () => {
      console.log('[AdminPanel] Vérification authentification...');
      try {
        const r = await api.get('/admin/me');
        console.log('[AdminPanel] Authentifié:', r.data.admin.email);
        saveSession('admin', r.data.admin);
        setAdmin(r.data.admin);
        await Promise.all([fetchDashboard(), fetchClients(), fetchConvs()]);
      } catch (err) { 
        console.error('[AdminPanel] Non authentifié, redirection...', err.response?.status);
        clearSession(); 
        navigate('/login-admin', { replace: true }); 
      }
      finally { setLoading(false); }
    })();
  }, [navigate]);

  useEffect(() => { const t = setInterval(fetchConvs, 15000); return () => clearInterval(t); }, []);

  const fetchDashboard = async () => {
    const r = await api.get('/admin/dashboard');
    setDashStats(r.data.stats);
    setTokenStats(r.data.tokens);
  };
  const fetchClients    = async () => { const r = await api.get('/admin/clients'); setClients(r.data.data || []); };
  const fetchConvs      = async () => {
    const r = await api.get('/admin/conversations');
    const all = r.data.data || [];
    setConvs(all);
    setEscalatedCount(all.filter(c => c.status === 'escalated').length);
  };
  const fetchProjets    = async cid => { const r = await api.get(`/admin/docs?type=projet&client_id=${cid}`); setProjets(r.data.data || []); };
  const fetchDocs       = async pid => { const r = await api.get(`/admin/docs?type=doc&projet_id=${pid}`); setDocs(r.data.data || []); };
  const fetchFaqs       = async did => { const r = await api.get(`/admin/docs?type=faq&documentation_id=${did}`); setFaqs(r.data.data || []); };
  const fetchClientStats = async id => { const r = await api.get(`/admin/dashboard/${id}`); setClientStats(r.data.stats); };

  const pickClient = async c => {
    setSelected(c); setSelProjet(null); setDocs([]); setSelDoc(null); setFaqs([]); setSubTab('projets');
    await Promise.all([fetchProjets(c.id), fetchClientStats(c.id)]);
    setSection('panel');
  };

  const logout = async () => { await api.post('/admin/logout'); clearSession(); navigate('/login-admin'); };

  // Prépare les données graphiques pour les tokens
  const getChartData = () => {
    if (!tokenStats?.week_data) return null;
    const data = tokenPeriod === 'month' ? (tokenStats.month30_data || []) : (tokenStats.week_data || []);
    return {
      labels: data.map(d => {
        const date = new Date(d.date);
        return date.toLocaleDateString('fr-FR', { day:'numeric', month:'short' });
      }),
      datasets: [
        { label:'Tokens input', data: data.map(d => d.input || d.total || 0), backgroundColor:'rgba(99,102,241,.7)', borderRadius:6 },
        { label:'Tokens output', data: data.map(d => d.output || 0), backgroundColor:'rgba(16,185,129,.7)', borderRadius:6 },
      ]
    };
  };

  const navItems = [
    { key:'dashboard', icon:'chart-pie',   label:'Dashboard' },
    { key:'clients',   icon:'users',        label:'Clients' },
    { key:'convs',     icon:'comments',     label:'Conversations', badge: escalatedCount },
    { key:'tokens',    icon:'coins',        label:'Tokens & RAG' },
    { key:'faqs-global', icon:'book',       label:'FAQ enregistrées' },
    { key:'ai-config', icon:'cog',          label:'Configuration API' },
  ];

  if (loading) return <Spinner/>;

  return (
    <div className="admin-container">
      {/* ─ Sidebar ─ */}
      <aside className={`admin-sidebar ${sidebarOpen ? '' : 'collapsed'}`}>
        <div className="sidebar-header">
          <div className="logo"><i className="fas fa-headset"/><span>Support Client</span></div>
          <button className="toggle-btn" onClick={() => setSidebarOpen(!sidebarOpen)}>
            <i className="fas fa-chevron-left"/>
          </button>
        </div>
        <nav className="sidebar-nav">
          {navItems.map(({ key, icon, label, badge }) => (
            <a key={key} href={`#${key}`} className={`nav-item ${section === key || (section === 'panel' && key === 'clients') ? 'active' : ''}`}
              onClick={e => { e.preventDefault(); setSection(key); }}>
              <i className={`fas fa-${icon}`}/>
              <span>{label}</span>
              {badge > 0 && <span style={{ marginLeft:'auto', background:'#ef4444', color:'#fff', fontSize:11, fontWeight:700, padding:'2px 7px', borderRadius:20, minWidth:18 }}>{badge}</span>}
            </a>
          ))}
        </nav>
        <div className="sidebar-footer">
          <div className="admin-info">
            <img src={`https://ui-avatars.com/api/?name=${encodeURIComponent(admin?.name||'A')}&background=10b981&color=fff`} alt="" className="admin-avatar"/>
            <div><div className="admin-name">{admin?.name}</div><div className="admin-role">Administrateur</div></div>
          </div>
          <button className="logout-btn" onClick={logout}><i className="fas fa-sign-out-alt"/><span>Déconnexion</span></button>
        </div>
      </aside>

      {/* ─ Main ─ */}
      <main className="admin-main">
        <header className="admin-header">
          <div className="header-title">
            <button className="mobile-menu-btn" onClick={() => setSidebarOpen(!sidebarOpen)}><i className="fas fa-bars"/></button>
            <h1 id="pageTitle">{{ dashboard:'Tableau de bord', clients:'Clients', convs:'Conversations', tokens:'Tokens & RAG', 'faqs-global':'FAQ enregistrées', 'ai-config':'Configuration API', panel:'Détail client' }[section] || 'Admin'}</h1>
          </div>
          <div className="header-actions">
            <div className="header-user">
              <img src={`https://ui-avatars.com/api/?name=${encodeURIComponent(admin?.name||'A')}&background=10b981&color=fff`} alt=""/>
              <span style={{ fontSize:14, fontWeight:600 }}>{admin?.name}</span>
            </div>
          </div>
        </header>

        <div style={{ padding:'2rem', maxWidth:1400 }}>

          {/* ══ DASHBOARD ══ */}
          {section === 'dashboard' && dashStats && (
            <div>
              <div className="section-header">
                <div><h2>Vue d'ensemble</h2><p>Toutes les métriques clés de votre plateforme</p></div>
              </div>

              {/* 6 stat cards */}
              <div className="stats-grid-6">
                {[
                  { icon:'coins',      color:'purple', value: (tokenStats?.today||0).toLocaleString(), label:'Tokens aujourd\'hui' },
                  { icon:'users',      color:'blue',   value: dashStats.clients,                       label:'Clients' },
                  { icon:'file-alt',   color:'green',  value: dashStats.documentations,                label:'Documentations' },
                  { icon:'question-circle', color:'orange', value: dashStats.faqs,                    label:'FAQ' },
                  { icon:'check-circle', color:'teal', value: `${tokenStats?.rag_efficiency||0}%`,    label:'Efficacité RAG' },
                  { icon:'exclamation-triangle', color:'pink', value: dashStats.escalated,            label:'En escalade' },
                ].map(({ icon, color, value, label }) => (
                  <div key={label} className="stat-card-large">
                    <div className={`stat-icon-bg ${color}`}><i className={`fas fa-${icon}`}/></div>
                    <div className="stat-details">
                      <span className="stat-number">{value}</span>
                      <span className="stat-label">{label}</span>
                    </div>
                  </div>
                ))}
              </div>

              {/* Graphique tokens semaine */}
              <div className="charts-row" style={{ marginTop:24 }}>
                <div className="chart-card large">
                  <div className="chart-header">
                    <h3>Consommation de Tokens</h3>
                    <div style={{ display:'flex', gap:8 }}>
                      {['week','month'].map(p => (
                        <button key={p} onClick={() => setTokenPeriod(p)} style={{ padding:'4px 12px', borderRadius:8, border:'1px solid', borderColor: tokenPeriod===p ? '#6366f1' : '#e2e8f0', background: tokenPeriod===p ? '#6366f1' : 'transparent', color: tokenPeriod===p ? '#fff' : '#64748b', fontSize:12, fontWeight:600, cursor:'pointer' }}>
                          {p === 'week' ? '7 jours' : '30 jours'}
                        </button>
                      ))}
                    </div>
                  </div>
                  <div className="chart-body">
                    <TokenChart data={getChartData()} period={tokenPeriod}/>
                  </div>
                </div>

                <div className="chart-card">
                  <div className="chart-header"><h3>Sources RAG (30j)</h3></div>
                  <div className="chart-body">
                    <RagPieChart data={tokenStats?.by_source}/>
                  </div>
                </div>
              </div>

              {/* Graphique en ligne - Évolution tokens */}
              <div style={{ background:'#fff', borderRadius:16, border:'1px solid #e2e8f0', padding:'1.5rem', marginTop:24, boxShadow:'0 1px 4px rgba(0,0,0,.05)' }}>
                <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', marginBottom:'1.5rem' }}>
                  <div>
                    <h3 style={{ fontWeight:700, fontSize:18, marginBottom:4, display:'flex', alignItems:'center', gap:8 }}>
                      <div style={{ width:32, height:32, borderRadius:10, background:'rgba(99,102,241,.1)', display:'flex', alignItems:'center', justifyContent:'center' }}>
                        <i className="fas fa-chart-line" style={{ color:'#6366f1', fontSize:14 }}/>
                      </div>
                      Évolution des Tokens (Graphique en ligne)
                    </h3>
                    <p style={{ fontSize:13, color:'#64748b' }}>Tendance de consommation sur {tokenPeriod === 'week' ? '7 jours' : '30 jours'}</p>
                  </div>
                </div>
                <div style={{ height:220 }}>
                  <TokenLineChart data={{
                    labels: (tokenPeriod === 'month' ? (tokenStats?.month30_data || []) : (tokenStats?.week_data || [])).map(d => {
                      const date = new Date(d.date);
                      return date.toLocaleDateString('fr-FR', { day:'numeric', month:'short' });
                    }),
                    values: (tokenPeriod === 'month' ? (tokenStats?.month30_data || []) : (tokenStats?.week_data || [])).map(d => d.total || 0)
                  }}/>
                </div>
              </div>

              {/* Tableau détaillé de consommation par jour */}
              {tokenStats && tokenStats.month30_data && (
                <div style={{ background:'#fff', borderRadius:16, border:'1px solid #e2e8f0', padding:'1.5rem', marginTop:24, boxShadow:'0 1px 4px rgba(0,0,0,.05)' }}>
                  <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', marginBottom:'1.5rem' }}>
                    <div>
                      <h3 style={{ fontWeight:700, fontSize:18, marginBottom:4 }}>Consommation Détaillée par Jour</h3>
                      <p style={{ fontSize:13, color:'#64748b' }}>Analyse complète des tokens sur les 30 derniers jours</p>
                    </div>
                    <div style={{ display:'flex', gap:8, fontSize:12 }}>
                      <div style={{ padding:'6px 12px', background:'#f8fafc', borderRadius:8, border:'1px solid #e2e8f0' }}>
                        <span style={{ color:'#64748b', marginRight:6 }}>Moyenne/jour:</span>
                        <strong style={{ color:'#1e1b4b' }}>{tokenStats.avg_per_day?.toLocaleString() || 0}</strong>
                      </div>
                      <div style={{ padding:'6px 12px', background:'#f8fafc', borderRadius:8, border:'1px solid #e2e8f0' }}>
                        <span style={{ color:'#64748b', marginRight:6 }}>Max:</span>
                        <strong style={{ color:'#10b981' }}>{tokenStats.max_day?.toLocaleString() || 0}</strong>
                      </div>
                      <div style={{ padding:'6px 12px', background:'#f8fafc', borderRadius:8, border:'1px solid #e2e8f0' }}>
                        <span style={{ color:'#64748b', marginRight:6 }}>Min:</span>
                        <strong style={{ color:'#f59e0b' }}>{tokenStats.min_day?.toLocaleString() || 0}</strong>
                      </div>
                    </div>
                  </div>

                  <div style={{ overflowX:'auto' }}>
                    <table style={{ width:'100%', fontSize:13, borderCollapse:'collapse' }}>
                      <thead>
                        <tr style={{ background:'#f8fafc', borderBottom:'2px solid #e2e8f0' }}>
                          <th style={{ padding:'12px 16px', textAlign:'left', fontWeight:700, color:'#1e1b4b' }}>Date</th>
                          <th style={{ padding:'12px 16px', textAlign:'right', fontWeight:700, color:'#1e1b4b' }}>Input</th>
                          <th style={{ padding:'12px 16px', textAlign:'right', fontWeight:700, color:'#1e1b4b' }}>Output</th>
                          <th style={{ padding:'12px 16px', textAlign:'right', fontWeight:700, color:'#1e1b4b' }}>Total</th>
                          <th style={{ padding:'12px 16px', textAlign:'center', fontWeight:700, color:'#1e1b4b' }}>Source Dominante</th>
                          <th style={{ padding:'12px 16px', textAlign:'right', fontWeight:700, color:'#1e1b4b' }}>Variation</th>
                        </tr>
                      </thead>
                      <tbody>
                        {tokenStats.month30_data.slice().reverse().map((day, idx) => {
                          const sourceColors = {
                            chunks: { bg:'#eff6ff', text:'#1e40af', label:'Chunks' },
                            fallback: { bg:'#fef3c7', text:'#92400e', label:'Fallback' },
                            faq: { bg:'#d1fae5', text:'#065f46', label:'FAQ' },
                            smalltalk: { bg:'#f1f5f9', text:'#475569', label:'Small-talk' }
                          };
                          const sourceStyle = sourceColors[day.dominant_source] || sourceColors.smalltalk;
                          const isIncrease = day.variation > 0;
                          const isDecrease = day.variation < 0;
                          const isStable = day.variation === 0;
                          
                          return (
                            <tr key={idx} style={{ borderBottom:'1px solid #f1f5f9', transition:'background .2s' }}
                                onMouseEnter={e => e.currentTarget.style.background = '#f8fafc'}
                                onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                              <td style={{ padding:'12px 16px', fontWeight:600, color:'#334155' }}>
                                {new Date(day.date).toLocaleDateString('fr-FR', { weekday:'short', day:'numeric', month:'short' })}
                              </td>
                              <td style={{ padding:'12px 16px', textAlign:'right', color:'#6366f1', fontWeight:600 }}>
                                {(day.input || 0).toLocaleString()}
                              </td>
                              <td style={{ padding:'12px 16px', textAlign:'right', color:'#10b981', fontWeight:600 }}>
                                {(day.output || 0).toLocaleString()}
                              </td>
                              <td style={{ padding:'12px 16px', textAlign:'right', color:'#1e1b4b', fontWeight:700, fontSize:14 }}>
                                {(day.total || 0).toLocaleString()}
                              </td>
                              <td style={{ padding:'12px 16px', textAlign:'center' }}>
                                <span style={{ 
                                  display:'inline-block', 
                                  padding:'4px 10px', 
                                  borderRadius:20, 
                                  background:sourceStyle.bg, 
                                  color:sourceStyle.text, 
                                  fontSize:11, 
                                  fontWeight:700,
                                  textTransform:'uppercase',
                                  letterSpacing:'.03em'
                                }}>
                                  {sourceStyle.label}
                                </span>
                              </td>
                              <td style={{ padding:'12px 16px', textAlign:'right', fontWeight:700 }}>
                                {isStable && <span style={{ color:'#94a3b8' }}>—</span>}
                                {isIncrease && (
                                  <span style={{ color:'#ef4444', display:'flex', alignItems:'center', justifyContent:'flex-end', gap:4 }}>
                                    <i className="fas fa-arrow-up" style={{ fontSize:10 }}/>
                                    +{day.variation}%
                                  </span>
                                )}
                                {isDecrease && (
                                  <span style={{ color:'#10b981', display:'flex', alignItems:'center', justifyContent:'flex-end', gap:4 }}>
                                    <i className="fas fa-arrow-down" style={{ fontSize:10 }}/>
                                    {day.variation}%
                                  </span>
                                )}
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>

                  {/* Légende */}
                  <div style={{ marginTop:'1.5rem', padding:'1rem', background:'#f8fafc', borderRadius:10, display:'flex', flexWrap:'wrap', gap:16, alignItems:'center', fontSize:12 }}>
                    <div style={{ fontWeight:700, color:'#475569' }}>Légende des sources :</div>
                    <div style={{ display:'flex', alignItems:'center', gap:6 }}>
                      <div style={{ width:12, height:12, borderRadius:'50%', background:'#3b82f6' }}/>
                      <span style={{ color:'#64748b' }}>Chunks RAG = Efficace (segments pertinents)</span>
                    </div>
                    <div style={{ display:'flex', alignItems:'center', gap:6 }}>
                      <div style={{ width:12, height:12, borderRadius:'50%', background:'#10b981' }}/>
                      <span style={{ color:'#64748b' }}>FAQ = Optimal (réponse directe)</span>
                    </div>
                    <div style={{ display:'flex', alignItems:'center', gap:6 }}>
                      <div style={{ width:12, height:12, borderRadius:'50%', background:'#f59e0b' }}/>
                      <span style={{ color:'#64748b' }}>Fallback = Coûteux (doc complète)</span>
                    </div>
                    <div style={{ display:'flex', alignItems:'center', gap:6 }}>
                      <div style={{ width:12, height:12, borderRadius:'50%', background:'#94a3b8' }}/>
                      <span style={{ color:'#64748b' }}>Small-talk = 0 token</span>
                    </div>
                  </div>
                </div>
              )}

              {/* Clients récents */}
              <div style={{ marginTop:24 }}>
                <div className="section-header">
                  <h3 style={{ fontWeight:700 }}>Clients récents</h3>
                  <button className="btn btn-primary btn-sm" onClick={() => setSection('clients')}>Voir tous →</button>
                </div>
                <div style={{ background:'#fff', borderRadius:16, border:'1px solid #e2e8f0', overflow:'hidden', boxShadow:'0 1px 4px rgba(0,0,0,.05)' }}>
                  <table className="data-table" style={{ width:'100%' }}>
                    <thead><tr><th>Client</th><th>Projets</th><th>Statut</th><th>Actions</th></tr></thead>
                    <tbody>
                      {clients.slice(0,5).map(c => (
                        <tr key={c.id}>
                          <td><div className="user-cell"><img src={`https://ui-avatars.com/api/?name=${encodeURIComponent(c.name)}&background=6366f1&color=fff`} alt=""/><span>{c.name}</span></div></td>
                          <td>{c.projets_count || '—'}</td>
                          <td>
                            <span className={`status-badge ${c.is_active ? 'success' : 'inactive'}`} title={c.last_login ? `Dernière connexion: ${new Date(c.last_login).toLocaleDateString('fr-FR')}` : 'Jamais connecté'}>
                              {c.is_active ? 'Actif' : 'Inactif'}
                            </span>
                          </td>
                          <td><button className="btn btn-sm btn-outline" onClick={() => pickClient(c)}>Gérer →</button></td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          )}

          {/* ══ TOKENS ══ */}
          {section === 'tokens' && tokenStats && (
            <div>
              <div className="section-header">
                <div><h2>Tokens & Efficacité RAG</h2><p>Analyse détaillée de votre consommation</p></div>
                <div style={{ display:'flex', gap:8 }}>
                  {['week','month'].map(p => (
                    <button key={p} onClick={() => setTokenPeriod(p)} className={`btn ${tokenPeriod===p ? 'btn-primary' : 'btn-outline btn-sm'}`}>
                      {p === 'week' ? '7 jours' : '30 jours'}
                    </button>
                  ))}
                </div>
              </div>

              {/* KPIs tokens */}
              <div className="stats-grid-6">
                {[
                  { icon:'coins',     color:'purple', value:(tokenStats.today||0).toLocaleString(),  label:"Tokens aujourd'hui" },
                  { icon:'calendar',  color:'blue',   value:(tokenStats.month||0).toLocaleString(),  label:'Tokens ce mois' },
                  { icon:'check',     color:'green',  value:`${tokenStats.rag_efficiency||0}%`,      label:'Efficacité RAG' },
                  { icon:'times',     color:'orange', value:tokenStats.by_source?.fallback||0,       label:'Réponses fallback' },
                  { icon:'chart-line',color:'teal',   value:(tokenStats.avg_per_day||0).toLocaleString(), label:'Moyenne par jour' },
                  { icon:'database',  color:'pink',   value:tokenStats.by_source?.faq||0,            label:'Réponses FAQ directes' },
                ].map(({ icon, color, value, label }) => (
                  <div key={label} className="stat-card-large">
                    <div className={`stat-icon-bg ${color}`}><i className={`fas fa-${icon}`}/></div>
                    <div className="stat-details"><span className="stat-number">{value}</span><span className="stat-label">{label}</span></div>
                  </div>
                ))}
              </div>

              <div className="charts-row" style={{ marginTop:24 }}>
                <div className="chart-card large">
                  <div className="chart-header"><h3>Tokens par jour (input vs output)</h3></div>
                  <div className="chart-body"><TokenChart data={getChartData()} period={tokenPeriod}/></div>
                </div>
                <div className="chart-card">
                  <div className="chart-header"><h3>Répartition des sources RAG</h3></div>
                  <div className="chart-body"><RagPieChart data={tokenStats.by_source}/></div>
                </div>
              </div>

              {/* Graphique en ligne dans la section Tokens */}
              <div style={{ background:'#fff', borderRadius:16, border:'1px solid #e2e8f0', padding:'1.5rem', marginTop:24, boxShadow:'0 1px 4px rgba(0,0,0,.05)' }}>
                <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', marginBottom:'1.5rem' }}>
                  <div>
                    <h3 style={{ fontWeight:700, fontSize:18, marginBottom:4, display:'flex', alignItems:'center', gap:8 }}>
                      <div style={{ width:32, height:32, borderRadius:10, background:'linear-gradient(135deg, #8b5cf6, #6366f1)', display:'flex', alignItems:'center', justifyContent:'center' }}>
                        <i className="fas fa-chart-area" style={{ color:'#fff', fontSize:14 }}/>
                      </div>
                      Tendance en Temps Réel
                    </h3>
                    <p style={{ fontSize:13, color:'#64748b' }}>Suivi continu de la consommation de tokens</p>
                  </div>
                  <div style={{ display:'flex', alignItems:'center', gap:8, padding:'6px 12px', background:'#f8fafc', borderRadius:8, border:'1px solid #e2e8f0' }}>
                    <div style={{ width:8, height:8, borderRadius:'50%', background:'#10b981', animation:'pulse 2s infinite' }}/>
                    <span style={{ fontSize:12, fontWeight:600, color:'#64748b' }}>Mise à jour en direct</span>
                  </div>
                </div>
                <div style={{ height:240 }}>
                  <TokenLineChart data={{
                    labels: (tokenPeriod === 'month' ? (tokenStats?.month30_data || []) : (tokenStats?.week_data || [])).map(d => {
                      const date = new Date(d.date);
                      return date.toLocaleDateString('fr-FR', { day:'numeric', month:'short' });
                    }),
                    values: (tokenPeriod === 'month' ? (tokenStats?.month30_data || []) : (tokenStats?.week_data || [])).map(d => d.total || 0)
                  }}/>
                </div>
              </div>
              
              <style>{`@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }`}</style>

              <div style={{ background:'#fff', borderRadius:16, border:'1px solid #e2e8f0', padding:'1.5rem', marginTop:24, boxShadow:'0 1px 4px rgba(0,0,0,.05)' }}>
                <h3 style={{ fontWeight:700, marginBottom:'1rem' }}>Comment lire ces données ?</h3>
                <div style={{ display:'grid', gridTemplateColumns:'repeat(auto-fill,minmax(220px,1fr))', gap:16 }}>
                  {[
                    { color:'#6366f1', label:'Chunks RAG', desc:'Le système a reçu uniquement les passages pertinents (efficace)' },
                    { color:'#10b981', label:'FAQ directe', desc:'La réponse était dans la FAQ — zéro token d\'input doc' },
                    { color:'#f59e0b', label:'Fallback',    desc:'Toute la documentation a été envoyée (coûteux)' },
                    { color:'#94a3b8', label:'Small-talk',  desc:'Salutation ou message hors doc — aucun token consommé' },
                  ].map(({ color, label, desc }) => (
                    <div key={label} style={{ padding:'1rem', background:'#f8fafc', borderRadius:10, borderLeft:`4px solid ${color}` }}>
                      <div style={{ fontWeight:700, color, marginBottom:4 }}>{label}</div>
                      <div style={{ fontSize:13, color:'#64748b' }}>{desc}</div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {/* ══ CLIENTS ══ */}
          {section === 'clients' && (
            <ClientsSection 
              clients={clients} 
              onPickClient={pickClient} 
              onCreated={() => { fetchClients(); fetchDashboard(); }} 
              onSuccess={msg => notify(msg, 'success')}
              onError={e => notify(e, 'error')}
              onDeleteClient={(id) => setClients(clients.filter(c => c.id !== id))}
            />
          )}

          {/* ══ CONVERSATIONS ══ */}
          {section === 'convs' && (
            <div>
              <div className="section-header">
                <div><h2>Conversations</h2><p>Supervision des échanges clients</p></div>
                <button className="btn btn-outline btn-sm" onClick={() => fetchConvs()}><i className="fas fa-sync"/> Actualiser</button>
              </div>
              
              {/* Liste des clients avec compteur conversations */}
              <div style={{ display:'grid', gridTemplateColumns:'repeat(auto-fill,minmax(260px,1fr))', gap:16, marginBottom:'2rem' }}>
                {clients.map(c => {
                  const clientConvs = convs.filter(cv => cv.client_name === c.name);
                  const escalated = clientConvs.filter(cv => cv.status === 'escalated').length;
                  return (
                    <button key={c.id} onClick={() => {
                      setSelected(c);
                      const filtered = convs.filter(cv => cv.client_name === c.name);
                      document.getElementById('client-conversations-section')?.scrollIntoView({ behavior: 'smooth' });
                    }} style={{ background:'#fff', border:'1.5px solid #e2e8f0', borderRadius:12, padding:'1.25rem', textAlign:'left', cursor:'pointer', transition:'all .2s' }}
                      onMouseEnter={e => { e.currentTarget.style.borderColor='#6366f1'; e.currentTarget.style.transform='translateY(-2px)'; }}
                      onMouseLeave={e => { e.currentTarget.style.borderColor='#e2e8f0'; e.currentTarget.style.transform=''; }}>
                      <div style={{ display:'flex', alignItems:'center', gap:12, marginBottom:12 }}>
                        <img src={`https://ui-avatars.com/api/?name=${encodeURIComponent(c.name)}&background=6366f1&color=fff`} alt="" style={{ width:40, height:40, borderRadius:'50%' }}/>
                        <div style={{ flex:1, minWidth:0 }}>
                          <div style={{ fontWeight:700, fontSize:14, color:'#1e1b4b', overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap' }}>{c.name}</div>
                          <div style={{ fontSize:12, color:'#64748b' }}>{c.email}</div>
                        </div>
                      </div>
                      <div style={{ display:'flex', gap:8, marginTop:8 }}>
                        <div style={{ flex:1, background:'#f1f5f9', borderRadius:8, padding:'6px 10px', textAlign:'center' }}>
                          <div style={{ fontSize:18, fontWeight:800, color:'#1e1b4b' }}>{clientConvs.length}</div>
                          <div style={{ fontSize:10, color:'#64748b', textTransform:'uppercase', letterSpacing:'.05em', fontWeight:600 }}>Conversations</div>
                        </div>
                        {escalated > 0 && (
                          <div style={{ flex:1, background:'#fef2f2', borderRadius:8, padding:'6px 10px', textAlign:'center' }}>
                            <div style={{ fontSize:18, fontWeight:800, color:'#ef4444' }}>{escalated}</div>
                            <div style={{ fontSize:10, color:'#991b1b', textTransform:'uppercase', letterSpacing:'.05em', fontWeight:600 }}>Escaladées</div>
                          </div>
                        )}
                      </div>
                    </button>
                  );
                })}
              </div>

              {/* Conversations du client sélectionné */}
              <div id="client-conversations-section">
                {selected ? (
                  <div>
                    <div style={{ display:'flex', alignItems:'center', gap:12, marginBottom:'1.5rem', padding:'1rem', background:'#f8fafc', borderRadius:12, border:'1px solid #e2e8f0' }}>
                      <button onClick={() => setSelected(null)} style={{ background:'transparent', border:'none', color:'#6366f1', cursor:'pointer', fontSize:20 }}>
                        <i className="fas fa-arrow-left"/>
                      </button>
                      <img src={`https://ui-avatars.com/api/?name=${encodeURIComponent(selected.name)}&background=6366f1&color=fff`} alt="" style={{ width:48, height:48, borderRadius:'50%' }}/>
                      <div style={{ flex:1 }}>
                        <h3 style={{ fontWeight:700, fontSize:16, color:'#1e1b4b', marginBottom:4 }}>Conversations de {selected.name}</h3>
                        <p style={{ fontSize:13, color:'#64748b' }}>{convs.filter(c => c.client_name === selected.name).length} conversation(s) au total</p>
                      </div>
                    </div>

                    {convs.filter(c => c.client_name === selected.name).length === 0 ? (
                      <div style={{ textAlign:'center', padding:'3rem', color:'#94a3b8', background:'#fff', borderRadius:12, border:'1px solid #e2e8f0' }}>
                        <i className="fas fa-comments" style={{ fontSize:48, opacity:.3, marginBottom:16 }}/>
                        <p>Aucune conversation pour ce client</p>
                      </div>
                    ) : (
                      <div style={{ display:'flex', flexDirection:'column', gap:8 }}>
                        {convs.filter(c => c.client_name === selected.name).map(c => (
                          <div key={c.id} style={{ background:'#fff', borderRadius:12, border:`1.5px solid ${c.status==='escalated'?'#ef4444':'#e2e8f0'}`, padding:'1rem 1.25rem', display:'flex', alignItems:'center', gap:'1rem' }}>
                            <div style={{ width:10, height:10, borderRadius:'50%', background: c.status==='escalated'?'#ef4444':c.status==='open'?'#10b981':'#94a3b8', flexShrink:0, boxShadow: c.status==='escalated'?'0 0 8px #ef4444':'' }}/>
                            <div style={{ flex:1, minWidth:0 }}>
                              <strong style={{ fontSize:14 }}>{c.projet_name||'Projet'} — Conv. #{c.id}</strong>
                              <p style={{ fontSize:12, color:'#64748b', marginTop:2, overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap' }}>{c.last_message||'Aucun message'}</p>
                            </div>
                            <div style={{ fontSize:12, color:'#94a3b8', textAlign:'right', flexShrink:0, display:'flex', flexDirection:'column', alignItems:'flex-end', gap:6 }}>
                              <span style={{ padding:'3px 10px', borderRadius:20, fontSize:11, fontWeight:700, background: c.status==='escalated'?'#fef2f2':c.status==='open'?'#ecfdf5':'#f1f5f9', color: c.status==='escalated'?'#991b1b':c.status==='open'?'#065f46':'#475569' }}>{c.status}</span>
                              <div style={{ marginTop:4 }}>{new Date(c.updated_at).toLocaleDateString('fr-FR')}</div>
                              <div style={{ display:'flex', gap:6 }}>
                                {c.uuid && <a href={`/support/conversation/${c.uuid}`} target="_blank" rel="noreferrer" style={{ display:'inline-block', padding:'4px 10px', border:'1px solid #e2e8f0', borderRadius:6, color:'#6366f1', fontSize:11, fontWeight:600, textDecoration:'none' }}>Ouvrir →</a>}
                                {c.status === 'escalated' && (
                                  <button onClick={async () => {
                                    if (!confirm(`Supprimer la conversation escaladée #${c.id} ?`)) return;
                                    try {
                                      await api.delete(`/admin/conversations/${c.id}`);
                                      notify('Conversation supprimée');
                                      await fetchConvs();
                                    } catch (err) {
                                      console.error('Erreur suppression:', err);
                                      notify('Erreur lors de la suppression', 'error');
                                    }
                                  }} style={{ padding:'4px 10px', border:'1px solid #ef4444', borderRadius:6, color:'#ef4444', fontSize:11, fontWeight:600, background:'transparent', cursor:'pointer' }} title="Supprimer cette conversation">
                                    <i className="fas fa-trash"/> Supprimer
                                  </button>
                                )}
                              </div>
                            </div>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                ) : (
                  <div style={{ textAlign:'center', padding:'3rem', color:'#94a3b8', background:'#fff', borderRadius:12, border:'1px solid #e2e8f0' }}>
                    <i className="fas fa-users" style={{ fontSize:48, opacity:.3, marginBottom:16 }}/>
                    <p>Sélectionnez un client ci-dessus pour voir ses conversations</p>
                  </div>
                )}
              </div>
            </div>
          )}

          {/* ══ FAQ GLOBALE ══ */}
          {section === 'faqs-global' && <FaqGlobalSection onNotify={notify}/>}

          {/* ══ CONFIGURATION API ══ */}
          {section === 'ai-config' && <AiConfigPage api={api}/>}

          {/* ══ PANEL CLIENT ══ */}
          {section === 'panel' && selected && (
            <ClientPanel client={selected} clientStats={clientStats} projets={projets} docs={docs} faqs={faqs} selProjet={selProjet} selDoc={selDoc} subTab={subTab}
              setSubTab={setSubTab} setSelProjet={setSelProjet} setSelDoc={setSelDoc}
              onPickProjet={async p => { setSelProjet(p); setSelDoc(null); setFaqs([]); setSubTab('docs'); await fetchDocs(p.id); }}
              onPickDoc={async d => { setSelDoc(d); setSubTab('faq'); await fetchFaqs(d.id); }}
              onDeleteProjet={async id => { if (!confirm('Supprimer ce projet ?')) return; await api.delete('/admin/docs?type=projet', { data:{ id } }); notify('Projet supprimé'); fetchProjets(selected.id); fetchClientStats(selected.id); fetchDashboard(); }}
              onDeleteDoc={async id => { if (!confirm('Supprimer ?')) return; await api.delete('/admin/docs?type=doc', { data:{ id } }); notify('Documentation supprimée'); fetchDocs(selProjet.id); }}
              onDeleteFaq={async id => { if (!confirm('Supprimer ?')) return; await api.delete('/admin/docs?type=faq', { data:{ id } }); notify('FAQ supprimée'); fetchFaqs(selDoc.id); }}
              onBack={() => setSection('clients')}
              onNewProjet={() => setShowWizard(true)}
              onNotify={notify}
            />
          )}

        </div>
      </main>

      {toast && <Toast message={toast.text} kind={toast.kind} onClose={() => setToast(null)}/>}

      {showWizard && selected && (
        <WizardModal client={selected} onDone={async () => { setShowWizard(false); await fetchProjets(selected.id); await fetchClientStats(selected.id); await fetchDashboard(); notify('Projet créé avec succès !'); }}
          onCancel={() => setShowWizard(false)} onError={e => notify(e, 'error')}/>
      )}
    </div>
  );
}

// ════════════════════════════════════════════════
//  SOUS-COMPOSANTS ADMIN
// ════════════════════════════════════════════════

function TokenChart({ data, period }) {
  const canvasRef = useRef(null);
  const chartRef  = useRef(null);

  useEffect(() => {
    if (!data || !canvasRef.current) return;
    if (chartRef.current) chartRef.current.destroy();
    const ctx = canvasRef.current.getContext('2d');
    chartRef.current = new window.Chart(ctx, {
      type: 'bar',
      data,
      options: {
        responsive: true, 
        maintainAspectRatio: false,
        plugins: { 
          legend: { 
            position: 'top',
            labels: {
              font: { size: 13, weight: '600' },
              padding: 16,
              usePointStyle: true,
              pointStyle: 'circle'
            }
          },
          tooltip: {
            backgroundColor: 'rgba(30,27,75,.95)',
            padding: 12,
            titleFont: { size: 14, weight: '700' },
            bodyFont: { size: 13 },
            borderColor: '#6366f1',
            borderWidth: 1,
            cornerRadius: 8,
            displayColors: true,
            callbacks: {
              label: function(context) {
                return context.dataset.label + ': ' + context.parsed.y.toLocaleString() + ' tokens';
              }
            }
          }
        },
        scales: { 
          x: { 
            grid: { display: false },
            ticks: { font: { size: 12, weight: '600' }, color: '#64748b' }
          }, 
          y: { 
            grid: { color: '#f1f5f9', drawBorder: false },
            ticks: { 
              font: { size: 12 }, 
              color: '#64748b',
              callback: function(value) {
                return value.toLocaleString();
              }
            },
            beginAtZero: true 
          }
        },
        interaction: {
          mode: 'index',
          intersect: false
        }
      }
    });
    return () => { if (chartRef.current) chartRef.current.destroy(); };
  }, [data, period]);

  if (!data) return <div style={{ height:220, display:'flex', alignItems:'center', justifyContent:'center', color:'#94a3b8' }}>Aucune donnée</div>;
  return <canvas ref={canvasRef} style={{ height:220 }}/>;
}

function TokenLineChart({ data }) {
  const canvasRef = useRef(null);
  const chartRef  = useRef(null);

  useEffect(() => {
    if (!data || !canvasRef.current) return;
    if (chartRef.current) chartRef.current.destroy();
    
    const ctx = canvasRef.current.getContext('2d');
    
    // Créer un gradient pour la ligne
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.3)');
    gradient.addColorStop(1, 'rgba(99, 102, 241, 0.01)');
    
    chartRef.current = new window.Chart(ctx, {
      type: 'line',
      data: {
        labels: data.labels,
        datasets: [{
          label: 'Tokens totaux',
          data: data.values,
          borderColor: '#6366f1',
          backgroundColor: gradient,
          borderWidth: 3,
          fill: true,
          tension: 0.4,
          pointRadius: 5,
          pointHoverRadius: 8,
          pointBackgroundColor: '#fff',
          pointBorderColor: '#6366f1',
          pointBorderWidth: 3,
          pointHoverBackgroundColor: '#6366f1',
          pointHoverBorderColor: '#fff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(30,27,75,.95)',
            padding: 12,
            titleFont: { size: 14, weight: '700' },
            bodyFont: { size: 13 },
            borderColor: '#6366f1',
            borderWidth: 1,
            cornerRadius: 8,
            callbacks: {
              label: function(context) {
                return 'Tokens: ' + context.parsed.y.toLocaleString();
              }
            }
          }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { font: { size: 12, weight: '600' }, color: '#64748b' }
          },
          y: {
            grid: { color: '#f1f5f9', drawBorder: false },
            ticks: { 
              font: { size: 12 }, 
              color: '#64748b',
              callback: function(value) {
                return value >= 1000 ? (value/1000).toFixed(1) + 'k' : value;
              }
            },
            beginAtZero: true
          }
        },
        interaction: {
          mode: 'index',
          intersect: false
        }
      }
    });
    
    return () => { if (chartRef.current) chartRef.current.destroy(); };
  }, [data]);

  if (!data) return <div style={{ height:220, display:'flex', alignItems:'center', justifyContent:'center', color:'#94a3b8' }}>Aucune donnée</div>;
  return <canvas ref={canvasRef} style={{ height:220 }}/>;
}

function RagPieChart({ data }) {
  const canvasRef = useRef(null);
  const chartRef  = useRef(null);

  useEffect(() => {
    if (!data || !canvasRef.current) return;
    if (chartRef.current) chartRef.current.destroy();
    const ctx = canvasRef.current.getContext('2d');
    chartRef.current = new window.Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Chunks RAG', 'Fallback', 'FAQ directe', 'Small-talk'],
        datasets: [{
          data: [data.chunks||0, data.fallback||0, data.faq||0, data.smalltalk||0],
          backgroundColor: ['#6366f1','#f59e0b','#10b981','#94a3b8'],
          borderWidth: 0,
          hoverOffset: 8,
          hoverBorderColor: '#fff',
          hoverBorderWidth: 3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              font: { size: 12, weight: '600' },
              padding: 12,
              usePointStyle: true,
              pointStyle: 'circle',
              generateLabels: function(chart) {
                const data = chart.data;
                const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                return data.labels.map((label, i) => {
                  const value = data.datasets[0].data[i];
                  const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                  return {
                    text: `${label}: ${value} (${percentage}%)`,
                    fillStyle: data.datasets[0].backgroundColor[i],
                    hidden: false,
                    index: i
                  };
                });
              }
            }
          },
          tooltip: {
            backgroundColor: 'rgba(30,27,75,.95)',
            padding: 12,
            titleFont: { size: 14, weight: '700' },
            bodyFont: { size: 13 },
            borderColor: '#6366f1',
            borderWidth: 1,
            cornerRadius: 8,
            callbacks: {
              label: function(context) {
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const value = context.parsed;
                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                return context.label + ': ' + value + ' (' + percentage + '%)';
              }
            }
          }
        },
        cutout: '65%'
      }
    });
    return () => { if (chartRef.current) chartRef.current.destroy(); };
  }, [data]);

  if (!data) return <div style={{ height:180, display:'flex', alignItems:'center', justifyContent:'center', color:'#94a3b8' }}>Aucune donnée</div>;
  
  const total = (data.chunks||0) + (data.fallback||0) + (data.faq||0) + (data.smalltalk||0);
  
  return (
    <div style={{ position:'relative', height:180 }}>
      <canvas ref={canvasRef}/>
      <div style={{ position:'absolute', top:'50%', left:'50%', transform:'translate(-50%, -50%)', textAlign:'center', pointerEvents:'none' }}>
        <div style={{ fontSize:32, fontWeight:800, color:'#1e1b4b', lineHeight:1 }}>{total}</div>
        <div style={{ fontSize:11, color:'#64748b', fontWeight:600, marginTop:4 }}>Total</div>
      </div>
    </div>
  );
}

function ClientsSection({ clients, onPickClient, onCreated, onError, onSuccess, onDeleteClient }) {
  const [search, setSearch] = useState('');
  const filtered = clients.filter(c => c.name.toLowerCase().includes(search.toLowerCase()) || c.email.toLowerCase().includes(search.toLowerCase()));

  const handleDelete = async (client) => {
    if (!confirm(`⚠️ Supprimer le client "${client.name}" ?\n\nCette action supprimera également:\n- Tous ses projets\n- Toutes ses documentations\n- Toutes ses FAQs\n- Toutes ses conversations\n\nCette action est irréversible !`)) return;
    
    try {
      await api.delete('/admin/clients', { data: { id: client.id } });
      onDeleteClient(client.id);
      onCreated(); // Rafraîchir la liste
      onSuccess(`Client "${client.name}" supprimé avec succès`);
    } catch (e) {
      onError(e.response?.data?.error || 'Erreur lors de la suppression');
    }
  };

  return (
    <div>
      <div className="section-header">
        <div><h2>Gestion des Clients</h2><p>{clients.length} clients enregistrés</p></div>
        <NewClientForm onCreated={onCreated} onError={onError} onSuccess={onSuccess}/>
      </div>
      
      {/* Aide navigation */}
      {clients.length > 0 && (
        <div style={{ background:'linear-gradient(135deg, #eff6ff 0%, #f5f3ff 100%)', border:'1px solid #c7d2fe', borderRadius:12, padding:'12px 16px', marginBottom:'1rem', display:'flex', alignItems:'center', gap:12 }}>
          <i className="fas fa-lightbulb" style={{ color:'#6366f1', fontSize:18 }}/>
          <div style={{ fontSize:13, color:'#4338ca', lineHeight:1.5, flex:1 }}>
            <strong>Astuce :</strong> Double-cliquez sur un client pour accéder directement à sa gestion
          </div>
        </div>
      )}
      
      <div style={{ background:'#fff', borderRadius:16, border:'1px solid #e2e8f0', overflow:'hidden', boxShadow:'0 1px 4px rgba(0,0,0,.05)' }}>
        <div style={{ padding:'1rem 1.5rem', borderBottom:'1px solid #f1f5f9' }}>
          <input placeholder="🔍 Rechercher un client…" value={search} onChange={e => setSearch(e.target.value)} style={{ width:'100%', maxWidth:360, padding:'8px 14px', border:'1px solid #e2e8f0', borderRadius:8, fontSize:14, outline:'none' }}/>
        </div>
        <table className="data-table" style={{ width:'100%' }}>
          <thead><tr><th>Client</th><th>Email</th><th>Identifiant</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
            {filtered.map(c => (
              <tr 
                key={c.id}
                onDoubleClick={() => onPickClient(c)}
                style={{ cursor: 'pointer', transition: 'background 0.2s' }}
                title="Double-cliquez pour gérer ce client"
              >
                <td><div className="user-cell"><img src={`https://ui-avatars.com/api/?name=${encodeURIComponent(c.name)}&background=6366f1&color=fff`} alt=""/><span>{c.name}</span></div></td>
                <td style={{ color:'#64748b', fontSize:14 }}>{c.email}</td>
                <td><span style={{ fontFamily:'monospace', background:'rgba(99,102,241,.1)', color:'#6366f1', padding:'2px 8px', borderRadius:6, fontSize:13 }}>{c.client_identifier}</span></td>
                <td>
                  <span 
                    className={`status-badge ${c.is_active ? 'success' : 'inactive'}`}
                    title={c.last_login ? `Dernière connexion: ${new Date(c.last_login).toLocaleDateString('fr-FR', { day:'numeric', month:'long', year:'numeric', hour:'2-digit', minute:'2-digit' })}` : 'Jamais connecté'}
                    style={{ cursor:'help' }}
                  >
                    {c.is_active ? 'Actif' : 'Inactif'}
                  </span>
                </td>
                <td style={{ display:'flex', gap:6 }}>
                  <button className="btn btn-sm btn-outline" onClick={() => onPickClient(c)}>
                    <i className="fas fa-cog"/> Gérer
                  </button>
                  <button className="btn btn-sm" style={{ background:'#fef2f2', color:'#ef4444', border:'1px solid #fecaca' }} onClick={() => handleDelete(c)} title="Supprimer ce client">
                    <i className="fas fa-trash"/>
                  </button>
                </td>
              </tr>
            ))}
            {filtered.length === 0 && <tr><td colSpan={5} style={{ textAlign:'center', padding:'2rem', color:'#94a3b8' }}>Aucun client trouvé</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function NewClientForm({ onCreated, onError, onSuccess }) {
  const [open, setOpen]       = useState(false);
  const [form, setForm]       = useState({ name:'', email:'', support_email:'' });
  const [loading, setLoading] = useState(false);

  const handleCreate = async () => {
    if (!form.name.trim() || !form.email.trim()) {
      onError('Nom et email requis');
      return;
    }
    
    setLoading(true);
    try { 
      await api.post('/admin/clients', form);
      setForm({ name:'', email:'', support_email:'' }); 
      setOpen(false);
      onCreated(); // Rafraîchir la liste
      onSuccess(`Client "${form.name}" créé ! Les identifiants ont été envoyés par email à ${form.email}`);
    }
    catch (e) { 
      onError(e.response?.data?.error || 'Erreur lors de la création.'); 
    }
    finally {
      setLoading(false);
    }
  };

  return !open ? (
    <button className="btn btn-primary" onClick={() => setOpen(true)}><i className="fas fa-plus"/> Ajouter un Client</button>
  ) : (
    <div style={{ position:'fixed', inset:0, background:'rgba(0,0,0,.5)', zIndex:500, display:'flex', alignItems:'center', justifyContent:'center' }} onClick={() => !loading && setOpen(false)}>
      <div style={{ background:'#fff', borderRadius:16, padding:'2rem', width:'100%', maxWidth:560, boxShadow:'0 25px 60px rgba(0,0,0,.2)' }} onClick={e => e.stopPropagation()}>
        <h3 style={{ fontWeight:800, marginBottom:4 }}>Nouveau client</h3>
        <p style={{ fontSize:13, color:'#64748b', marginBottom:'1.5rem' }}>
          Les identifiants seront générés automatiquement et envoyés par email.
        </p>
        <div style={{ background:'#eff6ff', border:'1px solid #bfdbfe', borderRadius:10, padding:'12px 16px', marginBottom:'1.5rem', display:'flex', gap:10 }}>
          <i className="fas fa-envelope" style={{ color:'#3b82f6', fontSize:18, flexShrink:0, marginTop:2 }}/>
          <div style={{ fontSize:13, color:'#1e40af', lineHeight:1.5 }}>
            <strong>Envoi automatique :</strong> Un email contenant l'identifiant et le mot de passe sera envoyé à l'adresse indiquée.
          </div>
        </div>
        <div className="form-group">
          <label>Nom complet <span style={{ color:'#ef4444' }}>*</span></label>
          <input value={form.name} onChange={e => setForm({...form,name:e.target.value})} placeholder="Nom du client" autoFocus disabled={loading}/>
        </div>
        <div className="form-group">
          <label>Email du client <span style={{ color:'#ef4444' }}>*</span></label>
          <input type="email" value={form.email} onChange={e => setForm({...form,email:e.target.value})} placeholder="client@entreprise.com" disabled={loading}/>
        </div>
        <div className="form-group">
          <label>Email support (escalades) <span style={{ fontSize:11, color:'#94a3b8', fontWeight:400 }}>optionnel</span></label>
          <input type="email" value={form.support_email} onChange={e => setForm({...form,support_email:e.target.value})} placeholder="support@votreagence.com" disabled={loading}/>
          <small style={{ fontSize:11, color:'#64748b', marginTop:4, display:'block' }}>
            Cet email recevra les demandes d'escalade vers un humain
          </small>
        </div>
        <div style={{ display:'flex', gap:8, marginTop:'1rem' }}>
          <button className="btn btn-outline" style={{ flex:1 }} onClick={() => setOpen(false)} disabled={loading}>Annuler</button>
          <button className="btn btn-primary" style={{ flex:1 }} disabled={loading} onClick={handleCreate}>
            {loading ? 'Création…' : '📧 Créer et Envoyer'}
          </button>
        </div>
      </div>
    </div>
  );
}

function FaqGlobalSection({ onNotify }) {
  const [faqs, setFaqs]     = useState([]);
  const [loading, setLoading] = useState(true);
  const [editId, setEditId] = useState(null);
  const [editForm, setEditForm] = useState({ question:'', reponse:'' });
  const [search, setSearch] = useState('');

  useEffect(() => {
    api.get('/admin/docs?type=faq').then(r => setFaqs(r.data.data || [])).catch(() => onNotify('Erreur chargement FAQ', 'error')).finally(() => setLoading(false));
  }, []);

  const update = async () => {
    try { await api.put('/admin/docs?type=faq', { id: editId, ...editForm }); onNotify('FAQ mise à jour'); setEditId(null); const r = await api.get('/admin/docs?type=faq'); setFaqs(r.data.data || []); }
    catch (e) { onNotify(e.response?.data?.error || 'Erreur', 'error'); }
  };
  const del = async id => {
    if (!confirm('Supprimer cette FAQ ?')) return;
    try { await api.delete('/admin/docs?type=faq', { data:{ id } }); onNotify('FAQ supprimée'); setFaqs(f => f.filter(x => x.id !== id)); }
    catch (e) { onNotify(e.response?.data?.error || 'Erreur', 'error'); }
  };

  const filtered = faqs.filter(f => f.question?.toLowerCase().includes(search.toLowerCase()) || f.reponse?.toLowerCase().includes(search.toLowerCase()));
  if (loading) return <Spinner/>;

  return (
    <div>
      <div className="section-header"><div><h2>FAQ enregistrées</h2><p>{faqs.length} entrées</p></div></div>
      <input placeholder="🔍 Rechercher une FAQ…" value={search} onChange={e => setSearch(e.target.value)} style={{ marginBottom:16, maxWidth:400, padding:'8px 14px', border:'1px solid #e2e8f0', borderRadius:8, fontSize:14, outline:'none', width:'100%' }}/>
      <div style={{ display:'flex', flexDirection:'column', gap:8 }}>
        {filtered.map((f,i) => (
          <div key={f.id} style={{ background:'#fff', borderRadius:12, border:'1px solid #e2e8f0', padding:'1rem 1.25rem', boxShadow:'0 1px 4px rgba(0,0,0,.04)' }}>
            {editId === f.id ? (
              <div style={{ display:'flex', flexDirection:'column', gap:10 }}>
                <div className="form-group" style={{ margin:0 }}><label style={{ fontSize:12 }}>Question</label><input value={editForm.question} onChange={e => setEditForm({...editForm,question:e.target.value})} style={{ padding:'8px 12px', border:'1px solid #e2e8f0', borderRadius:8, width:'100%', fontSize:14, outline:'none' }}/></div>
                <div className="form-group" style={{ margin:0 }}><label style={{ fontSize:12 }}>Réponse</label><textarea rows={3} value={editForm.reponse} onChange={e => setEditForm({...editForm,reponse:e.target.value})} style={{ padding:'8px 12px', border:'1px solid #e2e8f0', borderRadius:8, width:'100%', fontSize:14, outline:'none', resize:'vertical' }}/></div>
                <div style={{ display:'flex', gap:8 }}>
                  <button className="btn btn-primary btn-sm" onClick={update}>Enregistrer</button>
                  <button className="btn btn-outline btn-sm" onClick={() => setEditId(null)}>Annuler</button>
                </div>
              </div>
            ) : (
              <div style={{ display:'flex', gap:12, alignItems:'flex-start' }}>
                <div style={{ width:24, height:24, borderRadius:'50%', background:'rgba(99,102,241,.1)', color:'#6366f1', fontSize:12, fontWeight:700, display:'flex', alignItems:'center', justifyContent:'center', flexShrink:0 }}>{i+1}</div>
                <div style={{ flex:1 }}>
                  <div style={{ fontWeight:600, fontSize:14, color:'#1e293b' }}>{f.question}</div>
                  <div style={{ fontSize:13, color:'#64748b', marginTop:4 }}>{f.reponse}</div>
                  {f.projet_name && <span style={{ display:'inline-block', marginTop:4, fontSize:11, background:'rgba(99,102,241,.1)', color:'#6366f1', padding:'2px 8px', borderRadius:6 }}>📁 {f.projet_name}</span>}
                </div>
                <div style={{ display:'flex', gap:6, flexShrink:0 }}>
                  <button className="btn btn-outline btn-sm" onClick={() => { setEditId(f.id); setEditForm({ question:f.question, reponse:f.reponse }); }}>Modifier</button>
                  <button className="btn btn-sm" style={{ background:'#fef2f2', color:'#ef4444' }} onClick={() => del(f.id)}>Supprimer</button>
                </div>
              </div>
            )}
          </div>
        ))}
        {filtered.length === 0 && <div style={{ textAlign:'center', padding:'3rem', color:'#94a3b8' }}>Aucune FAQ trouvée.</div>}
      </div>
    </div>
  );
}

function ClientPanel({ client, clientStats, projets, docs, faqs, selProjet, selDoc, subTab, setSubTab, setSelProjet, setSelDoc, onPickProjet, onPickDoc, onDeleteProjet, onDeleteDoc, onDeleteFaq, onBack, onNewProjet, onNotify }) {
  return (
    <div>
      <button onClick={onBack} style={{ color:'#6366f1', background:'none', border:'none', cursor:'pointer', fontSize:14, fontWeight:600, marginBottom:16, display:'flex', alignItems:'center', gap:6 }}>
        <i className="fas fa-arrow-left"/> Retour clients
      </button>
      <div style={{ background:'linear-gradient(135deg,#6366f1,#4f46e5)', borderRadius:16, padding:'1.5rem 2rem', marginBottom:24, color:'#fff', display:'flex', alignItems:'center', gap:16 }}>
        <img src={`https://ui-avatars.com/api/?name=${encodeURIComponent(client.name)}&background=ffffff&color=6366f1&size=60`} alt="" style={{ width:52, height:52, borderRadius:12 }}/>
        <div style={{ flex:1 }}>
          <h2 style={{ fontWeight:800, fontSize:20 }}>{client.name}</h2>
          <div style={{ opacity:.85, fontSize:13 }}>{client.email} · <span style={{ fontFamily:'monospace' }}>{client.client_identifier}</span></div>
        </div>
        <button onClick={onNewProjet} style={{ background:'rgba(255,255,255,.2)', color:'#fff', border:'1.5px solid rgba(255,255,255,.4)', padding:'8px 16px', borderRadius:10, fontWeight:600, fontSize:13, cursor:'pointer', display:'flex', alignItems:'center', gap:6 }}>
          <i className="fas fa-plus"/> Nouveau projet
        </button>
      </div>

      {clientStats && (
        <div style={{ display:'grid', gridTemplateColumns:'repeat(auto-fill,minmax(160px,1fr))', gap:12, marginBottom:24 }}>
          {[['Projets',clientStats.projets,'folder','#6366f1'],['Docs',clientStats.documentations,'file-alt','#10b981'],['FAQ',clientStats.faqs,'question-circle','#f59e0b'],['Conversations',clientStats.conversations,'comments','#3b82f6'],['Tokens',clientStats.tokens_total?.toLocaleString()||0,'coins','#8b5cf6']].map(([label,val,icon,color]) => (
            <div key={label} style={{ background:'#fff', border:'1px solid #e2e8f0', borderRadius:12, padding:'1rem', textAlign:'center', boxShadow:'0 1px 4px rgba(0,0,0,.05)' }}>
              <div style={{ width:36, height:36, borderRadius:10, background:`${color}20`, display:'flex', alignItems:'center', justifyContent:'center', margin:'0 auto 8px' }}>
                <i className={`fas fa-${icon}`} style={{ color, fontSize:16 }}/>
              </div>
              <div style={{ fontWeight:800, fontSize:20 }}>{val}</div>
              <div style={{ fontSize:12, color:'#64748b' }}>{label}</div>
            </div>
          ))}
        </div>
      )}

      <div style={{ display:'flex', gap:4, marginBottom:20, borderBottom:'2px solid #e2e8f0', paddingBottom:0 }}>
        {['projets', selProjet && 'docs', selDoc && 'faq'].filter(Boolean).map(tab => (
          <button key={tab} onClick={() => setSubTab(tab)} style={{ padding:'8px 16px', border:'none', background:'none', fontWeight:600, fontSize:14, color: subTab===tab ? '#6366f1' : '#64748b', borderBottom: subTab===tab ? '2px solid #6366f1' : '2px solid transparent', cursor:'pointer', marginBottom:-2, textTransform:'capitalize' }}>
            {tab === 'projets' ? 'Projets' : tab === 'docs' ? `Docs — ${selProjet?.nom_projet}` : `FAQ — ${selDoc?.titre}`}
          </button>
        ))}
      </div>

      {subTab === 'projets' && (
        <div style={{ display:'flex', flexDirection:'column', gap:8 }}>
          {projets.length === 0 && <div style={{ textAlign:'center', padding:'3rem', color:'#94a3b8' }}><i className="fas fa-folder-open" style={{ fontSize:36, marginBottom:12, opacity:.3 }}/><p>Aucun projet.</p><button className="btn btn-primary btn-sm" onClick={onNewProjet} style={{ marginTop:12 }}>Créer le premier projet</button></div>}
          {projets.map(p => (
            <div key={p.id} style={{ background:'#fff', border:'1px solid #e2e8f0', borderRadius:12, padding:'1rem 1.25rem', display:'flex', alignItems:'center', gap:12 }}>
              <div style={{ width:36, height:36, background:'rgba(99,102,241,.1)', borderRadius:10, display:'flex', alignItems:'center', justifyContent:'center' }}><i className="fas fa-folder" style={{ color:'#6366f1', fontSize:16 }}/></div>
              <div style={{ flex:1 }}><strong style={{ fontSize:14 }}>{p.nom_projet}</strong><div style={{ fontSize:12, color:'#64748b' }}>{new Date(p.updated_at).toLocaleDateString('fr-FR')}</div></div>
              <div style={{ display:'flex', gap:6 }}>
                <button className="btn btn-outline btn-sm" onClick={() => onPickProjet(p)}>Docs →</button>
                <button className="btn btn-sm" style={{ background:'#fef2f2', color:'#ef4444' }} onClick={() => onDeleteProjet(p.id)}>Supprimer</button>
              </div>
            </div>
          ))}
        </div>
      )}

      {subTab === 'docs' && selProjet && <DocTabPanel projetId={selProjet.id} docs={docs} onPickDoc={onPickDoc} onDeleteDoc={onDeleteDoc} onRefresh={async () => { const r = await api.get(`/admin/docs?type=doc&projet_id=${selProjet.id}`); }} onNotify={onNotify}/>}
      {subTab === 'faq' && selDoc && <FaqTabPanel docId={selDoc.id} faqs={faqs} onDeleteFaq={onDeleteFaq} onRefresh={async () => {}} onNotify={onNotify}/>}
    </div>
  );
}

function DocTabPanel({ projetId, docs, onPickDoc, onDeleteDoc, onRefresh, onNotify }) {
  const [form, setForm]   = useState({ titre:'', contenu:'' });
  const [file, setFile]   = useState(null);
  const [loading, setLoading] = useState(false);
  const [localDocs, setLocalDocs] = useState(docs);
  const [viewDoc, setViewDoc] = useState(null);
  const [editDoc, setEditDoc] = useState(null);
  const [editForm, setEditForm] = useState({ titre:'', contenu:'' });

  useEffect(() => { setLocalDocs(docs); }, [docs]);

  const create = async () => {
    setLoading(true);
    try {
      if (file) {
        const fd = new FormData(); fd.append('projet_id', projetId); fd.append('titre', form.titre); fd.append('file', file);
        const r = await api.post('/admin/docs?type=doc', fd, { headers:{ 'Content-Type':'multipart/form-data' }});
        setLocalDocs(d => [...d, r.data.data]);
      } else {
        const r = await api.post('/admin/docs?type=doc', { projet_id: projetId, titre: form.titre, contenu: form.contenu });
        setLocalDocs(d => [...d, r.data.data]);
      }
      onNotify('Documentation ajoutée et indexation en cours…');
      setForm({ titre:'', contenu:'' }); setFile(null);
    } catch (e) { onNotify(e.response?.data?.error || 'Erreur', 'error'); }
    finally { setLoading(false); }
  };

  const viewDocument = async (doc) => {
    try {
      const r = await api.get(`/admin/docs/${doc.id}`);
      setViewDoc(r.data.data);
    } catch (e) {
      onNotify('Erreur lors du chargement du document', 'error');
    }
  };

  const startEdit = (doc) => {
    setEditDoc(doc);
    setEditForm({ titre: doc.titre, contenu: doc.contenu });
    setViewDoc(null);
  };

  const saveEdit = async () => {
    try {
      await api.put('/admin/docs?type=doc', { id: editDoc.id, ...editForm });
      onNotify('Document mis à jour et ré-indexé !');
      setEditDoc(null);
      // Mettre à jour localement
      setLocalDocs(docs => docs.map(d => d.id === editDoc.id ? { ...d, ...editForm } : d));
    } catch (e) {
      onNotify(e.response?.data?.error || 'Erreur lors de la mise à jour', 'error');
    }
  };

  return (
    <div>
      {/* Modal de visualisation */}
      {viewDoc && (
        <div style={{ position:'fixed', inset:0, background:'rgba(0,0,0,.6)', zIndex:700, display:'flex', alignItems:'center', justifyContent:'center', padding:'2rem' }} onClick={() => setViewDoc(null)}>
          <div style={{ background:'#fff', borderRadius:20, width:'100%', maxWidth:900, maxHeight:'90vh', display:'flex', flexDirection:'column', boxShadow:'0 25px 80px rgba(0,0,0,.3)' }} onClick={e => e.stopPropagation()}>
            <div style={{ padding:'1.5rem 2rem', borderBottom:'2px solid #e2e8f0', display:'flex', alignItems:'center', justifyContent:'space-between' }}>
              <div>
                <h3 style={{ fontSize:20, fontWeight:800, color:'#1e1b4b', marginBottom:4 }}>{viewDoc.titre}</h3>
                <p style={{ fontSize:13, color:'#64748b' }}>Projet: {viewDoc.projet?.nom_projet}</p>
              </div>
              <div style={{ display:'flex', gap:8 }}>
                <button className="btn btn-primary btn-sm" onClick={() => startEdit(viewDoc)}>
                  <i className="fas fa-edit"/> Modifier
                </button>
                <button onClick={() => setViewDoc(null)} style={{ width:32, height:32, borderRadius:8, border:'none', background:'#f1f5f9', color:'#64748b', cursor:'pointer', display:'flex', alignItems:'center', justifyContent:'center' }}>
                  <i className="fas fa-times"/>
                </button>
              </div>
            </div>
            <div style={{ flex:1, overflowY:'auto', padding:'2rem' }}>
              <div style={{ background:'#f8fafc', borderRadius:12, padding:'1.5rem', fontFamily:'Georgia, serif', fontSize:15, lineHeight:1.8, color:'#1e293b', whiteSpace:'pre-wrap' }}>
                {viewDoc.contenu}
              </div>
              {viewDoc.file_path && (
                <div style={{ marginTop:'1rem', padding:'1rem', background:'#eff6ff', borderRadius:10, border:'1px solid #bfdbfe' }}>
                  <i className="fas fa-file-pdf" style={{ color:'#3b82f6', marginRight:8 }}/>
                  <span style={{ fontSize:13, color:'#1e40af' }}>Fichier source: {viewDoc.file_path.split('/').pop()}</span>
                </div>
              )}
            </div>
            <div style={{ padding:'1rem 2rem', borderTop:'1px solid #e2e8f0', display:'flex', justifyContent:'space-between', alignItems:'center', background:'#f8fafc' }}>
              <span style={{ fontSize:12, color:'#64748b' }}>
                Mis à jour le {new Date(viewDoc.updated_at).toLocaleDateString('fr-FR', { day:'numeric', month:'long', year:'numeric' })}
              </span>
              <button className="btn btn-outline btn-sm" onClick={() => setViewDoc(null)}>Fermer</button>
            </div>
          </div>
        </div>
      )}

      {/* Modal d'édition */}
      {editDoc && (
        <div style={{ position:'fixed', inset:0, background:'rgba(0,0,0,.6)', zIndex:700, display:'flex', alignItems:'center', justifyContent:'center', padding:'2rem' }} onClick={() => setEditDoc(null)}>
          <div style={{ background:'#fff', borderRadius:20, width:'100%', maxWidth:900, maxHeight:'90vh', display:'flex', flexDirection:'column', boxShadow:'0 25px 80px rgba(0,0,0,.3)' }} onClick={e => e.stopPropagation()}>
            <div style={{ padding:'1.5rem 2rem', borderBottom:'2px solid #e2e8f0' }}>
              <h3 style={{ fontSize:20, fontWeight:800, color:'#1e1b4b' }}>Modifier le document</h3>
            </div>
            <div style={{ flex:1, overflowY:'auto', padding:'2rem' }}>
              <div className="form-group">
                <label style={{ fontWeight:600, marginBottom:8, display:'block' }}>Titre</label>
                <input value={editForm.titre} onChange={e => setEditForm({...editForm, titre:e.target.value})} style={{ width:'100%', padding:'10px 14px', border:'1.5px solid #e2e8f0', borderRadius:8, fontSize:15 }}/>
              </div>
              <div className="form-group">
                <label style={{ fontWeight:600, marginBottom:8, display:'block' }}>Contenu</label>
                <textarea rows={15} value={editForm.contenu} onChange={e => setEditForm({...editForm, contenu:e.target.value})} style={{ width:'100%', padding:'12px 14px', border:'1.5px solid #e2e8f0', borderRadius:8, fontSize:14, fontFamily:'monospace', lineHeight:1.6, resize:'vertical' }}/>
              </div>
            </div>
            <div style={{ padding:'1rem 2rem', borderTop:'1px solid #e2e8f0', display:'flex', gap:8, justifyContent:'flex-end', background:'#f8fafc' }}>
              <button className="btn btn-outline" onClick={() => setEditDoc(null)}>Annuler</button>
              <button className="btn btn-primary" onClick={saveEdit}>
                <i className="fas fa-save"/> Enregistrer les modifications
              </button>
            </div>
          </div>
        </div>
      )}

      <div style={{ background:'#fff', border:'1px solid #e2e8f0', borderRadius:16, padding:'1.5rem', marginBottom:16 }}>
        <h3 style={{ fontWeight:700, marginBottom:'1rem', display:'flex', alignItems:'center', gap:8 }}>
          <div style={{ width:32, height:32, borderRadius:10, background:'rgba(16,185,129,.1)', display:'flex', alignItems:'center', justifyContent:'center' }}>
            <i className="fas fa-plus" style={{ color:'#10b981', fontSize:14 }}/>
          </div>
          Ajouter une documentation
        </h3>
        <div className="form-group"><label>Titre</label><input value={form.titre} onChange={e => setForm({...form,titre:e.target.value})} placeholder="Titre du document"/></div>
        <div className="form-group"><label>Contenu texte</label><textarea rows={5} value={form.contenu} onChange={e => setForm({...form,contenu:e.target.value})} placeholder="Collez le contenu de la documentation…"/></div>
        <div className="form-group"><label>Ou importer (PDF/TXT)</label><input type="file" accept=".pdf,.txt" onChange={e => setFile(e.target.files[0])}/>{file && <span style={{ fontSize:12, color:'#6366f1' }}>📎 {file.name}</span>}</div>
        <button className="btn btn-primary btn-sm" disabled={loading} onClick={create}>{loading ? 'Indexation en cours…' : 'Ajouter la documentation'}</button>
      </div>

      <div style={{ display:'grid', gridTemplateColumns:'repeat(auto-fill, minmax(280px, 1fr))', gap:12 }}>
        {localDocs.length === 0 && <div style={{ gridColumn:'1/-1', textAlign:'center', padding:'3rem', color:'#94a3b8', background:'#fff', borderRadius:12, border:'1px solid #e2e8f0' }}><i className="fas fa-file-alt" style={{ fontSize:48, opacity:.2, marginBottom:16 }}/><p>Aucune documentation.</p></div>}
        {localDocs.map(d => (
          <div key={d.id} style={{ background:'#fff', border:'1.5px solid #e2e8f0', borderRadius:14, padding:'1.25rem', transition:'all .2s', cursor:'pointer' }}
            onMouseEnter={e => { e.currentTarget.style.borderColor='#10b981'; e.currentTarget.style.transform='translateY(-3px)'; e.currentTarget.style.boxShadow='0 12px 30px rgba(16,185,129,.15)'; }}
            onMouseLeave={e => { e.currentTarget.style.borderColor='#e2e8f0'; e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow=''; }}>
            <div style={{ display:'flex', alignItems:'flex-start', gap:12, marginBottom:12 }}>
              <div style={{ width:44, height:44, background:'rgba(16,185,129,.1)', borderRadius:12, display:'flex', alignItems:'center', justifyContent:'center', flexShrink:0 }}>
                <i className="fas fa-file-alt" style={{ color:'#10b981', fontSize:18 }}/>
              </div>
              <div style={{ flex:1, minWidth:0 }}>
                <strong style={{ fontSize:15, fontWeight:700, color:'#1e1b4b', display:'block', overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap' }}>{d.titre}</strong>
                <div style={{ fontSize:12, color:'#94a3b8', marginTop:2 }}>{new Date(d.updated_at).toLocaleDateString('fr-FR')}</div>
              </div>
            </div>
            <div style={{ fontSize:13, color:'#64748b', lineHeight:1.5, marginBottom:12, overflow:'hidden', textOverflow:'ellipsis', display:'-webkit-box', WebkitLineClamp:3, WebkitBoxOrient:'vertical' }}>
              {d.contenu?.slice(0, 150)}{(d.contenu?.length || 0) > 150 ? '…' : ''}
            </div>
            <div style={{ display:'flex', gap:6, flexWrap:'wrap' }}>
              <button className="btn btn-outline btn-sm" style={{ flex:1 }} onClick={(e) => { e.stopPropagation(); viewDocument(d); }}>
                <i className="fas fa-eye"/> Voir
              </button>
              <button className="btn btn-outline btn-sm" style={{ flex:1 }} onClick={(e) => { e.stopPropagation(); startEdit(d); }}>
                <i className="fas fa-edit"/> Modifier
              </button>
              <button className="btn btn-outline btn-sm" onClick={(e) => { e.stopPropagation(); onPickDoc(d); }}>
                <i className="fas fa-question-circle"/> FAQ
              </button>
              <button className="btn btn-sm" style={{ background:'#fef2f2', color:'#ef4444', border:'1px solid #fecaca' }} onClick={(e) => { e.stopPropagation(); if(confirm('Supprimer ce document ?')) { onDeleteDoc(d.id); setLocalDocs(docs => docs.filter(x => x.id !== d.id)); } }}>
                <i className="fas fa-trash"/>
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function FaqTabPanel({ docId, faqs, onDeleteFaq, onNotify }) {
  const [form, setForm]   = useState({ question:'', reponse:'' });
  const [loading, setLoading] = useState(false);
  const [localFaqs, setLocalFaqs] = useState(faqs);
  const [editId, setEditId] = useState(null);
  const [editForm, setEditForm] = useState({ question:'', reponse:'' });
  const [showForm, setShowForm] = useState(false);

  useEffect(() => { setLocalFaqs(faqs); }, [faqs]);

  const create = async () => {
    if (!form.question.trim() || !form.reponse.trim()) {
      onNotify('Question et réponse requises', 'error');
      return;
    }
    
    setLoading(true);
    try {
      const r = await api.post('/admin/docs?type=faq', { documentation_id: docId, ...form });
      setLocalFaqs(f => [...f, r.data.data]);
      onNotify('FAQ ajoutée avec succès !');
      setForm({ question:'', reponse:'' });
      setShowForm(false);
    } catch (e) { onNotify(e.response?.data?.error || 'Erreur', 'error'); }
    finally { setLoading(false); }
  };

  const updateFaq = async () => {
    try {
      await api.put('/admin/docs?type=faq', { id: editId, ...editForm });
      setLocalFaqs(faqs => faqs.map(f => f.id === editId ? { ...f, ...editForm } : f));
      onNotify('FAQ mise à jour !');
      setEditId(null);
    } catch (e) {
      onNotify(e.response?.data?.error || 'Erreur', 'error');
    }
  };

  return (
    <div>
      {/* Bouton d'ajout moderne */}
      {!showForm ? (
        <button onClick={() => setShowForm(true)} style={{ width:'100%', background:'linear-gradient(135deg, #f59e0b, #f97316)', color:'#fff', border:'none', borderRadius:16, padding:'1.5rem', cursor:'pointer', marginBottom:'1.5rem', display:'flex', alignItems:'center', justifyContent:'center', gap:12, fontSize:16, fontWeight:700, boxShadow:'0 8px 24px rgba(245,158,11,.25)', transition:'all .2s' }}
          onMouseEnter={e => { e.currentTarget.style.transform='translateY(-2px)'; e.currentTarget.style.boxShadow='0 12px 32px rgba(245,158,11,.35)'; }}
          onMouseLeave={e => { e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow='0 8px 24px rgba(245,158,11,.25)'; }}>
          <i className="fas fa-plus-circle" style={{ fontSize:24 }}/>
          <span>Ajouter une nouvelle FAQ</span>
        </button>
      ) : (
        <div style={{ background:'linear-gradient(135deg, #fef3c7, #fed7aa)', border:'2px solid #f59e0b', borderRadius:16, padding:'1.5rem', marginBottom:'1.5rem' }}>
          <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', marginBottom:'1rem' }}>
            <h3 style={{ fontWeight:800, fontSize:18, color:'#92400e', display:'flex', alignItems:'center', gap:8 }}>
              <i className="fas fa-sparkles"/>
              Nouvelle FAQ
            </h3>
            <button onClick={() => setShowForm(false)} style={{ width:28, height:28, borderRadius:8, border:'none', background:'rgba(146,64,14,.1)', color:'#92400e', cursor:'pointer', display:'flex', alignItems:'center', justifyContent:'center' }}>
              <i className="fas fa-times"/>
            </button>
          </div>
          <div className="form-group" style={{ marginBottom:'1rem' }}>
            <label style={{ fontWeight:700, color:'#92400e', marginBottom:8, display:'flex', alignItems:'center', gap:6 }}>
              <i className="fas fa-question-circle"/>
              Question
            </label>
            <input value={form.question} onChange={e => setForm({...form, question:e.target.value})} placeholder="Ex: Comment réinitialiser mon mot de passe ?" style={{ width:'100%', padding:'12px 16px', border:'2px solid #fbbf24', borderRadius:10, fontSize:15, outline:'none', transition:'border-color .2s' }} onFocus={e => e.target.style.borderColor='#f59e0b'} onBlur={e => e.target.style.borderColor='#fbbf24'}/>
          </div>
          <div className="form-group">
            <label style={{ fontWeight:700, color:'#92400e', marginBottom:8, display:'flex', alignItems:'center', gap:6 }}>
              <i className="fas fa-comment-dots"/>
              Réponse
            </label>
            <textarea rows={4} value={form.reponse} onChange={e => setForm({...form, reponse:e.target.value})} placeholder="Réponse claire et concise pour l'utilisateur…" style={{ width:'100%', padding:'12px 16px', border:'2px solid #fbbf24', borderRadius:10, fontSize:14, lineHeight:1.6, outline:'none', resize:'vertical', transition:'border-color .2s' }} onFocus={e => e.target.style.borderColor='#f59e0b'} onBlur={e => e.target.style.borderColor='#fbbf24'}/>
          </div>
          <div style={{ display:'flex', gap:8, marginTop:'1rem' }}>
            <button className="btn btn-outline" onClick={() => { setShowForm(false); setForm({ question:'', reponse:'' }); }} style={{ flex:1 }}>
              Annuler
            </button>
            <button className="btn btn-primary" onClick={create} disabled={loading} style={{ flex:2, background:'linear-gradient(135deg, #f59e0b, #f97316)', border:'none' }}>
              {loading ? 'Ajout en cours…' : '✨ Ajouter cette FAQ'}
            </button>
          </div>
        </div>
      )}

      {/* Liste des FAQs avec design moderne */}
      <div style={{ display:'flex', flexDirection:'column', gap:12 }}>
        {localFaqs.length === 0 && (
          <div style={{ textAlign:'center', padding:'4rem 2rem', color:'#94a3b8', background:'#fff', borderRadius:16, border:'2px dashed #e2e8f0' }}>
            <i className="fas fa-question-circle" style={{ fontSize:56, opacity:.2, marginBottom:20 }}/>
            <p style={{ fontSize:16, fontWeight:600, marginBottom:8 }}>Aucune FAQ pour cette documentation</p>
            <p style={{ fontSize:14 }}>Commencez par ajouter votre première FAQ !</p>
          </div>
        )}
        {localFaqs.map((f,i) => (
          <div key={f.id} style={{ background:'#fff', border:'2px solid #e2e8f0', borderRadius:14, padding:'1.5rem', transition:'all .2s', position:'relative', overflow:'hidden' }}
            onMouseEnter={e => { e.currentTarget.style.borderColor='#f59e0b'; e.currentTarget.style.boxShadow='0 8px 24px rgba(245,158,11,.1)'; }}
            onMouseLeave={e => { e.currentTarget.style.borderColor='#e2e8f0'; e.currentTarget.style.boxShadow=''; }}>
            <div style={{ position:'absolute', top:12, right:12, width:36, height:36, borderRadius:10, background:'linear-gradient(135deg, #fef3c7, #fed7aa)', display:'flex', alignItems:'center', justifyContent:'center', fontWeight:800, color:'#92400e', fontSize:14 }}>
              #{i+1}
            </div>
            
            {editId === f.id ? (
              <div>
                <div className="form-group" style={{ marginBottom:'1rem' }}>
                  <label style={{ fontWeight:600, fontSize:12, color:'#64748b', marginBottom:6 }}>Question</label>
                  <input value={editForm.question} onChange={e => setEditForm({...editForm, question:e.target.value})} style={{ width:'100%', padding:'10px 14px', border:'1.5px solid #e2e8f0', borderRadius:8, fontSize:14 }}/>
                </div>
                <div className="form-group">
                  <label style={{ fontWeight:600, fontSize:12, color:'#64748b', marginBottom:6 }}>Réponse</label>
                  <textarea rows={4} value={editForm.reponse} onChange={e => setEditForm({...editForm, reponse:e.target.value})} style={{ width:'100%', padding:'10px 14px', border:'1.5px solid #e2e8f0', borderRadius:8, fontSize:14, resize:'vertical' }}/>
                </div>
                <div style={{ display:'flex', gap:6, marginTop:'1rem' }}>
                  <button className="btn btn-outline btn-sm" onClick={() => setEditId(null)}>Annuler</button>
                  <button className="btn btn-primary btn-sm" onClick={updateFaq}>
                    <i className="fas fa-save"/> Enregistrer
                  </button>
                </div>
              </div>
            ) : (
              <div>
                <div style={{ display:'flex', alignItems:'flex-start', gap:12, marginBottom:12 }}>
                  <div style={{ width:44, height:44, borderRadius:12, background:'linear-gradient(135deg, #fef3c7, #fed7aa)', display:'flex', alignItems:'center', justifyContent:'center', flexShrink:0 }}>
                    <i className="fas fa-question" style={{ color:'#f59e0b', fontSize:20 }}/>
                  </div>
                  <div style={{ flex:1, paddingRight:'3rem' }}>
                    <div style={{ fontWeight:700, fontSize:16, color:'#1e1b4b', marginBottom:8, lineHeight:1.4 }}>
                      {f.question}
                    </div>
                    <div style={{ fontSize:14, color:'#475569', lineHeight:1.7, background:'#f8fafc', padding:'12px 16px', borderRadius:10, borderLeft:'4px solid #f59e0b' }}>
                      {f.reponse}
                    </div>
                  </div>
                </div>
                <div style={{ display:'flex', gap:6, marginTop:'1rem', paddingTop:'1rem', borderTop:'1px solid #f1f5f9' }}>
                  <button className="btn btn-outline btn-sm" onClick={() => { setEditId(f.id); setEditForm({ question:f.question, reponse:f.reponse }); }}>
                    <i className="fas fa-edit"/> Modifier
                  </button>
                  <button className="btn btn-sm" style={{ background:'#fef2f2', color:'#ef4444', border:'1px solid #fecaca' }} onClick={() => { if(confirm('Supprimer cette FAQ ?')) { onDeleteFaq(f.id); setLocalFaqs(fqs => fqs.filter(x => x.id !== f.id)); } }}>
                    <i className="fas fa-trash"/> Supprimer
                  </button>
                </div>
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}

function WizardModal({ client, onDone, onCancel, onError }) {
  const [step, setStep]         = useState(1);
  const [form, setForm]         = useState({ nom_projet:'', titre:'', contenu:'' });
  const [file, setFile]         = useState(null);
  const [faqs, setFaqs]         = useState([]);
  const [faqForm, setFaqForm]   = useState({ question:'', reponse:'' });
  const [loading, setLoading]   = useState(false);

  // ✅ Ne valide que les données, ne crée PAS en base
  const nextStep1 = () => {
    if (!form.nom_projet) { onError('Nom du projet requis.'); return; }
    setStep(2);
  };

  const nextStep2 = () => {
    if (!form.titre) { onError('Titre requis.'); return; }
    if (!file && !form.contenu) { onError('Contenu ou fichier requis.'); return; }
    setStep(3);
  };

  const addFaqLocal = () => {
    if (!faqForm.question || !faqForm.reponse) { onError('Question et réponse requises.'); return; }
    setFaqs(f => [...f, faqForm]);
    setFaqForm({ question:'', reponse:'' });
  };

  // ✅ Création en base UNIQUEMENT quand on clique "Terminer"
  const createAll = async () => {
    setLoading(true);
    try {
      // 1. Créer le projet
      const rProjet = await api.post('/admin/docs?type=projet', { client_id: client.id, nom_projet: form.nom_projet });
      const projetId = rProjet.data.data.id;

      // 2. Créer la documentation
      let docId;
      if (file) {
        const fd = new FormData();
        fd.append('projet_id', projetId);
        fd.append('titre', form.titre);
        fd.append('file', file);
        const rDoc = await api.post('/admin/docs?type=doc', fd, { headers:{ 'Content-Type':'multipart/form-data' }});
        docId = rDoc.data.data.id;
      } else {
        const rDoc = await api.post('/admin/docs?type=doc', { projet_id: projetId, titre: form.titre, contenu: form.contenu });
        docId = rDoc.data.data.id;
      }

      // 3. Créer les FAQs
      for (const faq of faqs) {
        await api.post('/admin/docs?type=faq', { documentation_id: docId, ...faq });
      }

      onDone();
    } catch (e) {
      onError(e.response?.data?.error || 'Erreur lors de la création');
    } finally {
      setLoading(false);
    }
  };

  const STEPS = ['Projet', 'Documentation', 'FAQ'];

  return (
    <div style={{ position:'fixed', inset:0, background:'rgba(0,0,0,.5)', zIndex:600, display:'flex', alignItems:'center', justifyContent:'center', padding:'1rem' }}>
      <div style={{ background:'#fff', borderRadius:20, width:'100%', maxWidth:560, boxShadow:'0 25px 80px rgba(0,0,0,.25)', overflow:'hidden' }}>
        <div style={{ background:'linear-gradient(135deg,#6366f1,#4f46e5)', padding:'1.5rem 2rem', color:'#fff' }}>
          <h2 style={{ fontWeight:800, fontSize:20, marginBottom:4 }}>Nouveau projet pour {client.name}</h2>
          <div style={{ display:'flex', gap:8 }}>
            {STEPS.map((s,i) => (
              <div key={s} style={{ display:'flex', alignItems:'center', gap:6 }}>
                <div style={{ width:24, height:24, borderRadius:'50%', background: step > i+1 ? '#10b981' : step === i+1 ? 'rgba(255,255,255,.9)' : 'rgba(255,255,255,.3)', color: step === i+1 ? '#6366f1' : '#fff', fontSize:12, fontWeight:700, display:'flex', alignItems:'center', justifyContent:'center' }}>
                  {step > i+1 ? '✓' : i+1}
                </div>
                <span style={{ fontSize:13, opacity: step === i+1 ? 1 : .7 }}>{s}</span>
                {i < STEPS.length-1 && <span style={{ opacity:.4 }}>→</span>}
              </div>
            ))}
          </div>
        </div>
        <div style={{ padding:'2rem' }}>
          {step === 1 && (
            <div>
              <div className="form-group"><label>Nom du projet</label><input value={form.nom_projet} onChange={e => setForm({...form,nom_projet:e.target.value})} placeholder="ex : Site e-commerce, App mobile…" autoFocus/></div>
              <div style={{ display:'flex', gap:8, marginTop:'1rem' }}>
                <button className="btn btn-outline" style={{ flex:1 }} onClick={onCancel}>Annuler</button>
                <button className="btn btn-primary" style={{ flex:1 }} onClick={nextStep1}>Suivant →</button>
              </div>
            </div>
          )}
          {step === 2 && (
            <div>
              <div className="form-group"><label>Titre</label><input value={form.titre} onChange={e => setForm({...form,titre:e.target.value})} placeholder="Documentation technique, Guide utilisateur…" autoFocus/></div>
              <div className="form-group"><label>Contenu texte</label><textarea rows={5} value={form.contenu} onChange={e => setForm({...form,contenu:e.target.value})} placeholder="Collez le contenu…"/></div>
              <div className="form-group"><label>Ou importer (PDF/TXT)</label><input type="file" accept=".pdf,.txt" onChange={e => setFile(e.target.files[0])}/>{file && <span style={{ fontSize:12, color:'#6366f1' }}>📎 {file.name}</span>}</div>
              <div style={{ display:'flex', gap:8, marginTop:'1rem' }}>
                <button className="btn btn-outline" style={{ flex:1 }} onClick={() => setStep(1)}>← Retour</button>
                <button className="btn btn-primary" style={{ flex:1 }} onClick={nextStep2}>Suivant →</button>
              </div>
            </div>
          )}
          {step === 3 && (
            <div>
              <p style={{ fontSize:14, color:'#64748b', marginBottom:'1rem' }}>Optionnel — les FAQs permettent au système de répondre directement sans chercher dans la documentation.</p>
              {faqs.length > 0 && (
                <div style={{ background:'#f8fafc', borderRadius:10, padding:'0.75rem 1rem', marginBottom:'1rem' }}>
                  {faqs.map((f,i) => <div key={i} style={{ fontSize:13, borderBottom:'1px solid #e2e8f0', paddingBottom:6, marginBottom:6 }}><strong>Q :</strong> {f.question}</div>)}
                </div>
              )}
              <div className="form-group"><label>Question</label><input value={faqForm.question} onChange={e => setFaqForm({...faqForm,question:e.target.value})} placeholder="Question fréquente…"/></div>
              <div className="form-group"><label>Réponse</label><textarea rows={3} value={faqForm.reponse} onChange={e => setFaqForm({...faqForm,reponse:e.target.value})} placeholder="Réponse concise…"/></div>
              <button className="btn btn-outline btn-sm" onClick={addFaqLocal}>+ Ajouter cette FAQ</button>
              <div style={{ display:'flex', gap:8, marginTop:'1.5rem' }}>
                <button className="btn btn-outline" style={{ flex:1 }} onClick={() => setStep(2)}>← Retour</button>
                <button className="btn btn-primary" style={{ flex:1 }} disabled={loading} onClick={createAll}>{loading ? 'Création en cours…' : '✓ Terminer'}</button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
