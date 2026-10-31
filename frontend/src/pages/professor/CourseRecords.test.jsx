import React from 'react';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import { AttendancePanel } from './CourseRecords.jsx';

afterEach(cleanup);

test('registra asistencia en una sesión programada sin crear fechas manuales', async () => {
  const people = [{ id: 1, nombre: 'Ana', primer_apellido: 'Solís', segundo_apellido: 'Rojas', correo: 'ana@ucatolica.ac.cr' }];
  const session = { id: 7, curso_id: 1, numero: 1, fecha: '2026-09-02', asistencia_registrada: false, asistencias: [] };
  const api = {
    get: vi.fn((path) => Promise.resolve(path.endsWith('/matriculas') ? people : [session])),
    put: vi.fn(() => Promise.resolve({ ...session, asistencia_registrada: true })),
    post: vi.fn(),
  };
  render(<AttendancePanel api={api} course={{ id: 1 }} />);
  await screen.findByRole('option', { name: /Sesión 1.*2026-09-02.*Pendiente/ });
  expect(screen.queryByText('Nueva sesión')).toBeNull();
  expect(screen.queryByLabelText('Fecha')).toBeNull();
  fireEvent.change(screen.getByLabelText('Sesión'), { target: { value: '7' } });
  fireEvent.change(screen.getByLabelText('Condición de Ana Solís Rojas'), { target: { value: 'presente' } });
  fireEvent.click(screen.getByRole('button', { name: 'Registrar asistencia' }));
  await waitFor(() => expect(api.put).toHaveBeenCalledWith('/profesor/sesiones/7/asistencias', {
    asistencias: [{ matricula_id: 1, condicion: 'presente' }],
  }));
  expect(api.post).not.toHaveBeenCalled();
});
