import React from 'react';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import { CatalogPanel, QuarterPanel } from './Management.jsx';

afterEach(cleanup);

test('catálogo bloquea editar y eliminar cuando en_uso es verdadero', async () => {
  const api = { get: vi.fn().mockResolvedValue([{ id: 1, nombre: 'Prueba parcial', en_uso: true }, { id: 2, nombre: 'Proyecto', en_uso: false }]) };
  render(<CatalogPanel api={api} />);
  await waitFor(() => expect(screen.getByText('Prueba parcial')).toBeTruthy());
  const row = screen.getByText('Prueba parcial').closest('.list-item');
  expect(row.querySelector('button:nth-child(1)').disabled).toBe(true);
  expect(row.querySelector('button:nth-child(2)').disabled).toBe(true);
  const free = screen.getByText('Proyecto').closest('.list-item');
  expect(free.querySelector('button:nth-child(1)').disabled).toBe(false);
  expect(free.querySelector('button:nth-child(2)').disabled).toBe(false);
});

test('rechaza un cierre fuera de los 98 días sin enviar el cuatrimestre', async () => {
  const api = { get: vi.fn().mockResolvedValue([]), post: vi.fn() };
  render(<QuarterPanel api={api} />);
  fireEvent.change(screen.getByLabelText(/^Código/), { target: { value: '3-2026' } });
  fireEvent.change(screen.getByLabelText('Fecha de inicio'), { target: { value: '2026-09-02' } });
  fireEvent.change(screen.getByLabelText(/^Fecha de cierre/), { target: { value: '2026-12-08' } });
  fireEvent.click(screen.getByRole('button', { name: 'Crear cuatrimestre' }));
  await screen.findByText(/exactamente 98 días/);
  expect(api.post).not.toHaveBeenCalled();
});
