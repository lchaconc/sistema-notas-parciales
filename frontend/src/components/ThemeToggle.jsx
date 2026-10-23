import React from 'react';

function SunIcon() {
  return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4" /><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" /></svg>;
}

function MoonIcon() {
  return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M20.9 13a9 9 0 1 1-9.9-9.9A7 7 0 0 0 20.9 13Z" /></svg>;
}

export default function ThemeToggle({ theme, onToggle }) {
  const label = theme === 'dark' ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro';
  return <button type="button" className="theme-toggle" aria-label={label} aria-pressed={theme === 'dark'} title={label} onClick={onToggle}>
    <span className={`theme-icon ${theme === 'light' ? 'theme-icon-active' : ''}`}><SunIcon /></span>
    <span className={`theme-icon ${theme === 'dark' ? 'theme-icon-active' : ''}`}><MoonIcon /></span>
  </button>;
}
