import React, { useState } from 'react';
import ThemeToggle from './ThemeToggle.jsx';

export default function Shell({ role, items, active, onNavigate, onLogout, theme, onToggleTheme, children }) {
  const [menuOpen, setMenuOpen] = useState(false);
  return <div className="app-shell">
    {menuOpen && <button type="button" className="mobile-scrim" aria-label="Cerrar menú" onClick={() => setMenuOpen(false)} />}
    <aside className={`sidebar ${menuOpen ? 'sidebar-open' : ''}`}>
      <div className="sidebar-brand"><div className="brand-mark">SN</div><div><strong>Sistema de Notas</strong><small>Parciales</small></div></div>
      <div className="sidebar-role">Espacio del {role}</div>
      <nav aria-label="Navegación principal" className="sidebar-nav">
        {items.map((item) => <button type="button" key={item.id} className={`nav-link ${active === item.id ? 'nav-active' : ''}`} aria-current={active === item.id ? 'page' : undefined} onClick={() => { onNavigate(item.id); setMenuOpen(false); }}><span className="nav-dot" />{item.label}</button>)}
      </nav>
      <div className="sidebar-bottom"><button type="button" className="nav-link" onClick={onLogout}><span className="nav-dot" />Cerrar sesión</button></div>
    </aside>
    <div className="main-column"><header className="topbar"><button type="button" className="menu-button" aria-label="Abrir menú" aria-expanded={menuOpen} onClick={() => setMenuOpen(true)}>☰</button><span className="topbar-title">Sistema de Notas Parciales</span><div className="topbar-actions"><ThemeToggle theme={theme} onToggle={onToggleTheme} /><span className="role-pill">{role}</span></div></header><main className="workspace">{children}</main></div>
  </div>;
}
