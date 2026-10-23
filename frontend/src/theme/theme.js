const STORAGE_KEY = 'snp.theme';

export function readTheme(storage = localStorage) {
  try {
    return storage.getItem(STORAGE_KEY) === 'dark' ? 'dark' : 'light';
  } catch {
    return 'light';
  }
}

export function applyTheme(theme, root = document.documentElement, storage = localStorage) {
  root.dataset.theme = theme;
  try { storage.setItem(STORAGE_KEY, theme); } catch { /* La interfaz sigue funcionando sin almacenamiento local. */ }
}
