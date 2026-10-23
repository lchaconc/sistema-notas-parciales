import React, { useState } from 'react';
import { motion } from 'motion/react';
import { request } from '../api/client.js';
import { newSession } from '../auth/session.js';
import { Feedback, Field } from '../components/UI.jsx';
import ThemeToggle from '../components/ThemeToggle.jsx';

export default function LoginPage({ onLogin, theme, onToggleTheme }) {
  const [correo, setCorreo] = useState('');
  const [contrasena, setContrasena] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(event) {
    event.preventDefault();
    setBusy(true);
    setError('');
    try {
      const data = await request('/login', { method: 'POST', body: { correo, contrasena } });
      onLogin(newSession(data));
    } catch (cause) { setError(cause.message); }
    finally { setBusy(false); }
  }

  return <div className="login-layout">
    <div className="login-theme-control"><ThemeToggle theme={theme} onToggle={onToggleTheme} /></div>
    <div className="login-brand"><div className="brand-mark">SN</div><span>Sistema de Notas Parciales</span></div>
    <motion.main className="login-card" initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.25 }}>
      <span className="eyebrow">Acceso al sistema</span>
      <h1>Bienvenido</h1>
      <p className="muted">Ingrese con su correo institucional y contraseña.</p>
      <form onSubmit={submit} className="form-stack">
        <Field label="Correo electrónico"><input type="email" autoComplete="username" value={correo} onChange={(event) => setCorreo(event.target.value)} required /></Field>
        <Field label="Contraseña"><input type="password" autoComplete="current-password" value={contrasena} onChange={(event) => setContrasena(event.target.value)} required /></Field>
        <Feedback tone="error" message={error} />
        <button className="button-primary button-wide" disabled={busy}>{busy ? 'Ingresando…' : 'Iniciar sesión'}</button>
      </form>
    </motion.main>
    <p className="login-footer">Gestión académica · Acceso para profesor y estudiantes</p>
  </div>;
}
