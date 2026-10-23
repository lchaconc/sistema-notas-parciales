import React from 'react';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import { AttendancePanel } from './CourseRecords.jsx';

afterEach(cleanup);

test('la carga de sesiones no borra la fecha escrita para una sesión nueva', async () => {
  let resolveSessions;
  const sessions = new Promise((resolve) => { resolveSessions = resolve; });
  const api = { get: vi.fn((path) => path.endsWith('/matriculas') ? Promise.resolve([{ id: 1, nombre: 'Ana', primer_apellido: 'Solís', segundo_apellido: 'Rojas', correo: 'ana@ucatolica.ac.cr' }]) : sessions) };
  render(<AttendancePanel api={api} course={{ id: 1, fecha_inicio: '2098-01-01', fecha_cierre: '2098-04-30' }} />);
  fireEvent.change(screen.getByLabelText('Fecha'), { target: { value: '2098-01-08' } });
  resolveSessions([]);
  await waitFor(() => expect(screen.getByLabelText('Fecha').value).toBe('2098-01-08'));
});
