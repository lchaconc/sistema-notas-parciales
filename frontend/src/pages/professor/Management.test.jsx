import React from 'react';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import { CatalogPanel } from './Management.jsx';

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
