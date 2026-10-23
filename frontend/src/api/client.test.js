import { afterEach, expect, test, vi } from 'vitest';
import { request } from './client.js';

afterEach(() => vi.unstubAllGlobals());

test('envía JSON y token Bearer solo cuando se proporciona', async () => {
  const fetchMock = vi.fn().mockImplementation(async () => new Response(JSON.stringify({ data: { ok: true } }), { status: 201 }));
  vi.stubGlobal('fetch', fetchMock);
  await request('/profesor/catalogo', { method: 'POST', token: 'abc', body: { nombre: 'Proyecto' } });
  expect(fetchMock.mock.calls[0][1].headers.Authorization).toBe('Bearer abc');
  expect(fetchMock.mock.calls[0][1].body).toBe('{"nombre":"Proyecto"}');
  await request('/login', { method: 'POST', body: { correo: 'A@ucatolica.ac.cr', contrasena: 'x' } });
  expect(fetchMock.mock.calls[1][1].headers.Authorization).toBeUndefined();
  expect(fetchMock.mock.calls[1][1].body).toContain('A@ucatolica.ac.cr');
});

test('retira sesión en 401 y conserva el mensaje de error', async () => {
  const onUnauthorized = vi.fn();
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({ error: 'Token vencido.' }), { status: 401 })));
  await expect(request('/profesor/cursos', { token: 'expired', onUnauthorized })).rejects.toMatchObject({ message: 'Token vencido.', status: 401 });
  expect(onUnauthorized).toHaveBeenCalledOnce();
});

test('trata fallo de red y archivo binario', async () => {
  vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network')));
  await expect(request('/profesor/cursos')).rejects.toThrow('No se pudo conectar');
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(new Blob(['xlsx']), { status: 200 })));
  expect(await request('/profesor/concentrado.xlsx', { binary: true })).toBeInstanceOf(Blob);
});
