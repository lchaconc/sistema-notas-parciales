import React from 'react';
import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import GradesTable from './GradesTable.jsx';

afterEach(cleanup);

const person = {
  matricula_id: 10, nombre: 'Ana', primer_apellido: 'Solís', segundo_apellido: 'Rojas', correo: 'ana@ucatolica.ac.cr',
  calificaciones: [
    { actividad_id: 1, nota: null, porcentaje_obtenido: '0.00' },
    { actividad_id: 2, nota: '4.00', porcentaje_obtenido: '0.80' },
  ],
};
const activities = [{ id: 1, titulo: 'Tareas', numero: 1 }, { id: 2, titulo: 'Tareas', numero: 2 }];
const components = [{ actividades: activities.map((activity) => ({ ...activity, puntaje_asignado: '10.00' })) }];

test('guarda solo la celda indicada y conserva la observación previa de otra nota', async () => {
  const api = {
    get: vi.fn(async (path) => ({ puntos_obtenidos: path.endsWith('/1') ? null : '4.00', observacion: path.endsWith('/1') ? null : 'Texto anterior' })),
    put: vi.fn(async () => ({})),
  };
  const onReload = vi.fn(async () => ({ ok: true }));
  const onDirtyChange = vi.fn();
  render(<GradesTable api={api} report={{ actividades: activities, estudiantes: [person] }} components={components} onReload={onReload} onDirtyChange={onDirtyChange} />);
  const first = screen.getByRole('spinbutton', { name: /Nota de Ana Solís Rojas · Tareas · Actividad 1/ });
  const second = screen.getByRole('spinbutton', { name: /Nota de Ana Solís Rojas · Tareas · Actividad 2/ });
  fireEvent.change(first, { target: { value: '8.50' } });
  fireEvent.change(second, { target: { value: '6.00' } });
  await waitFor(() => expect(onDirtyChange).toHaveBeenLastCalledWith(true));
  fireEvent.click(screen.getByRole('button', { name: /Guardar nota y observación de Ana Solís Rojas · Tareas · Actividad 2/ }));
  await waitFor(() => expect(api.put).toHaveBeenCalledWith('/profesor/calificaciones/10/2', { puntos_obtenidos: '6.00', observacion: 'Texto anterior' }));
  expect(api.put).toHaveBeenCalledTimes(1);
  expect(first.value).toBe('8.50');
  expect(screen.getByRole('button', { name: /Guardar nota y observación de Ana Solís Rojas · Tareas · Actividad 1/ }).disabled).toBe(false);
  expect(onDirtyChange).toHaveBeenLastCalledWith(true);
});

test('abre el editor de una celda y guarda juntos su nota y observación', async () => {
  const api = { get: vi.fn(async () => ({ puntos_obtenidos: null, observacion: null })), put: vi.fn(async () => ({})) };
  render(<GradesTable api={api} report={{ actividades: [activities[0]], estudiantes: [{ ...person, calificaciones: [person.calificaciones[0]] }] }} components={components} onReload={vi.fn(async () => ({}))} onDirtyChange={vi.fn()} />);
  fireEvent.click(screen.getByRole('button', { name: 'Observación' }));
  const dialog = screen.getByRole('dialog', { name: 'Observación de la actividad' });
  await waitFor(() => expect(within(dialog).getByLabelText('Observación (opcional)')).toBeTruthy());
  fireEvent.change(within(dialog).getByLabelText('Observación (opcional)'), { target: { value: 'Buen trabajo' } });
  fireEvent.click(within(dialog).getByRole('button', { name: 'Volver a la tabla' }));
  fireEvent.click(screen.getByRole('button', { name: /Guardar nota y observación de Ana Solís Rojas · Tareas · Actividad 1/ }));
  expect(api.put).not.toHaveBeenCalled();
  expect(screen.getByText(/La nota debe estar entre/)).toBeTruthy();
  fireEvent.change(screen.getByRole('spinbutton', { name: /Nota de Ana Solís Rojas · Tareas · Actividad 1/ }), { target: { value: '8.50' } });
  fireEvent.click(screen.getByRole('button', { name: /Guardar nota y observación de Ana Solís Rojas · Tareas · Actividad 1/ }));
  await waitFor(() => expect(api.put).toHaveBeenCalledWith('/profesor/calificaciones/10/1', { puntos_obtenidos: '8.50', observacion: 'Buen trabajo' }));
});
