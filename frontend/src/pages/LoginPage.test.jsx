import React from 'react';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import LoginPage from './LoginPage.jsx';

afterEach(() => { cleanup(); vi.unstubAllGlobals(); });

test('envía el correo sin alterar mayúsculas y abre sesión solo al autenticar', async () => {
  const onLogin = vi.fn();
  const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: { token: 'abc', rol: 'profesor', expires_in: 3600 } }), { status: 200 }));
  vi.stubGlobal('fetch', fetchMock);
  render(<LoginPage onLogin={onLogin} />);
  fireEvent.change(screen.getByLabelText('Correo electrónico'), { target: { value: 'Persona@ucatolica.ac.cr' } });
  fireEvent.change(screen.getByLabelText('Contraseña'), { target: { value: 'clave' } });
  fireEvent.click(screen.getByRole('button', { name: 'Iniciar sesión' }));
  await waitFor(() => expect(onLogin).toHaveBeenCalledOnce());
  expect(JSON.parse(fetchMock.mock.calls[0][1].body).correo).toBe('Persona@ucatolica.ac.cr');
});

test('muestra el error sin abrir sesión cuando fallan credenciales', async () => {
  const onLogin = vi.fn();
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({ error: 'Credenciales incorrectas.' }), { status: 401 })));
  render(<LoginPage onLogin={onLogin} />);
  fireEvent.change(screen.getByLabelText('Correo electrónico'), { target: { value: 'ana@ucatolica.ac.cr' } });
  fireEvent.change(screen.getByLabelText('Contraseña'), { target: { value: 'incorrecta' } });
  fireEvent.click(screen.getByRole('button', { name: 'Iniciar sesión' }));
  expect(await screen.findByRole('alert')).toHaveProperty('textContent', 'Credenciales incorrectas.');
  expect(onLogin).not.toHaveBeenCalled();
});
