import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { createApi } from './api/client.js';
import { clearSession, isSessionValid, restoreSession, saveSession } from './auth/session.js';
import { applyTheme, readTheme } from './theme/theme.js';
import LoginPage from './pages/LoginPage.jsx';
import ProfessorPage from './pages/ProfessorPage.jsx';
import StudentPage from './pages/StudentPage.jsx';

export default function App() {
  const [session, setSession] = useState(() => restoreSession());
  const [theme, setTheme] = useState(readTheme);
  const logout = useCallback(() => { clearSession(); setSession(null); }, []);
  const toggleTheme = useCallback(() => setTheme((current) => current === 'light' ? 'dark' : 'light'), []);

  useEffect(() => { applyTheme(theme); }, [theme]);

  useEffect(() => {
    if (!session) return undefined;
    if (!isSessionValid(session)) { logout(); return undefined; }
    const timer = window.setTimeout(logout, Math.min(session.expiresAt - Date.now(), 2147483647));
    return () => window.clearTimeout(timer);
  }, [session, logout]);

  const api = useMemo(() => session ? createApi(session.token, logout) : null, [session, logout]);
  if (!session) return <LoginPage theme={theme} onToggleTheme={toggleTheme} onLogin={(value) => { saveSession(value); setSession(value); }} />;
  if (session.rol === 'profesor') return <ProfessorPage api={api} onLogout={logout} theme={theme} onToggleTheme={toggleTheme} />;
  return <StudentPage api={api} onLogout={logout} theme={theme} onToggleTheme={toggleTheme} />;
}
