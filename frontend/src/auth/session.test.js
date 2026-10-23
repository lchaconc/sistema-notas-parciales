import { beforeEach, expect, test } from 'vitest';
import { clearSession, isSessionValid, newSession, restoreSession, saveSession } from './session.js';

beforeEach(() => localStorage.clear());

test('recupera la sesión hasta su vencimiento', () => {
  const session = newSession({ token: 'abc', rol: 'profesor', expires_in: 3600 }, Date.now());
  saveSession(session);
  expect(restoreSession()).toEqual(session);
  expect(isSessionValid(session, session.expiresAt)).toBe(false);
  clearSession();
  expect(restoreSession()).toBeNull();
});

test('rechaza una sesión vencida o alterada', () => {
  localStorage.setItem('snp.session', JSON.stringify({ token: 'abc', rol: 'estudiante', expiresAt: Date.now() - 1 }));
  expect(restoreSession()).toBeNull();
  localStorage.setItem('snp.session', '{malformed');
  expect(restoreSession()).toBeNull();
});
