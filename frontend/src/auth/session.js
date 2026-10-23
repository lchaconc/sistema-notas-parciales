const STORAGE_KEY = 'snp.session';

export function newSession(data, now = Date.now()) {
  return { token: data.token, rol: data.rol, expiresAt: now + Number(data.expires_in) * 1000 };
}

export function isSessionValid(value, now = Date.now()) {
  return Boolean(value && typeof value.token === 'string' && value.token &&
    ['profesor', 'estudiante'].includes(value.rol) && Number.isFinite(value.expiresAt) && value.expiresAt > now);
}

export function restoreSession(storage = localStorage) {
  try {
    const value = JSON.parse(storage.getItem(STORAGE_KEY));
    if (isSessionValid(value)) return value;
  } catch { /* No hay sesión recuperable. */ }
  storage.removeItem(STORAGE_KEY);
  return null;
}

export function saveSession(session, storage = localStorage) {
  storage.setItem(STORAGE_KEY, JSON.stringify(session));
}

export function clearSession(storage = localStorage) {
  storage.removeItem(STORAGE_KEY);
}
