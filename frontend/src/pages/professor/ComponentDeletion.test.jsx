import React from 'react';
import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import { ComponentsPanel } from './CourseSetup.jsx';

afterEach(cleanup);

const course = { id: 7, nombre: 'Informática Aplicada', codigo: 'PD008', numero_grupo: 2, cuatrimestre_codigo: '3-2026' };

function component(tiene_calificaciones = false) {
  return {
    id: 12, titulo: 'Tareas', actividad_catalogo_id: 3, actividad_catalogo: 'Tarea',
    cantidad_actividades: 1, porcentaje_total: '10.00', puntaje_por_actividad: '10.00',
    actividades: [{ id: 34, numero: 1, puntaje_asignado: '10.00', porcentaje_asignado: '10.00' }],
    tiene_calificaciones,
  };
}

function setup({ graded = false, deletion, onComponentGet } = {}) {
  let items = [component(graded)];
  let componentGets = 0;
  const get = vi.fn((path) => {
    if (path.endsWith('/componentes')) {
      componentGets += 1;
      return onComponentGet ? onComponentGet(componentGets, items) : Promise.resolve(items);
    }
    return Promise.resolve([{ id: 3, nombre: 'Tarea', en_uso: items.length > 0 }]);
  });
  const remove = vi.fn(async (path) => {
    if (deletion) await deletion(path);
    else items = [];
    return { eliminado: true };
  });
  render(<ComponentsPanel api={{ get, delete: remove, post: vi.fn() }} course={course} />);
  return { get, remove, setItems: (next) => { items = next; } };
}

test('solicita confirmación aun sin notas; cancelar no envía DELETE y confirmar actualiza la lista', async () => {
  const { get, remove } = setup();
  const trigger = await screen.findByRole('button', { name: 'Eliminar', exact: true });
  fireEvent.click(trigger);
  const dialog = screen.getByRole('dialog', { name: 'Eliminar componente' });
  expect(within(dialog).getByText(/Informática Aplicada/)).toBeTruthy();
  expect(within(dialog).getByText(/matrículas, la asistencia, los demás componentes/)).toBeTruthy();
  expect(within(dialog).queryByText(/tiene calificaciones registradas/)).toBeNull();
  expect(document.activeElement).toBe(within(dialog).getByRole('button', { name: 'Cancelar' }));
  fireEvent.click(within(dialog).getByRole('button', { name: 'Cancelar' }));
  expect(remove).not.toHaveBeenCalled();
  expect(document.activeElement).toBe(trigger);
  fireEvent.click(trigger);
  fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Eliminar componente' }));
  await waitFor(() => expect(remove).toHaveBeenCalledWith('/profesor/cursos/7/componentes/12'));
  await waitFor(() => expect(screen.getByText('Este curso todavía no tiene componentes.')).toBeTruthy());
  expect(document.activeElement).toBe(screen.getByRole('heading', { name: 'Componentes del curso' }));
  expect(get).toHaveBeenCalledWith('/profesor/catalogo');
  expect(screen.getByText(/Componente eliminado/)).toBeTruthy();
});

test('una calificación registrada, incluida cero, exige el parámetro de confirmación', async () => {
  const { remove, setItems } = setup({ graded: true, deletion: () => setItems([]) });
  fireEvent.click(await screen.findByRole('button', { name: 'Eliminar', exact: true }));
  const dialog = screen.getByRole('dialog');
  expect(within(dialog).getByText(/nota de 0,00/)).toBeTruthy();
  fireEvent.click(within(dialog).getByRole('button', { name: 'Eliminar componente' }));
  await waitFor(() => expect(remove).toHaveBeenCalledWith('/profesor/cursos/7/componentes/12?confirmar_con_notas=true'));
});

test('si aparece una nota entre consulta y borrado, exige una segunda confirmación informada', async () => {
  let attempts = 0;
  let resolveRefresh;
  const refresh = new Promise((resolve) => { resolveRefresh = resolve; });
  const { remove, setItems } = setup({ deletion: async () => {
    attempts += 1;
    if (attempts === 1) {
      setItems([component(true)]);
      throw Object.assign(new Error('El componente tiene calificaciones; confirme su eliminación.'), { status: 409 });
    }
    setItems([]);
  }, onComponentGet: (count, items) => count === 2 ? refresh : Promise.resolve(items) });
  fireEvent.click(await screen.findByRole('button', { name: 'Eliminar', exact: true }));
  fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Eliminar componente' }));
  await waitFor(() => expect(screen.getByText(/Se registraron calificaciones desde que abrió esta lista/)).toBeTruthy());
  expect(screen.getByRole('dialog')).toBeTruthy();
  expect(screen.getByText(/nota de 0,00/)).toBeTruthy();
  expect(remove).toHaveBeenCalledTimes(1);
  resolveRefresh([component(true)]);
  await waitFor(() => expect(within(screen.getByRole('dialog')).getByRole('button', { name: 'Eliminar componente' }).disabled).toBe(false));
  fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Eliminar componente' }));
  await waitFor(() => expect(remove).toHaveBeenLastCalledWith('/profesor/cursos/7/componentes/12?confirmar_con_notas=true'));
});

test('un rechazo conserva el componente y muestra el motivo; Escape cierra el cuadro', async () => {
  const { remove } = setup({ deletion: () => { throw Object.assign(new Error('Componente no encontrado en este curso.'), { status: 404 }); } });
  fireEvent.click(await screen.findByRole('button', { name: 'Eliminar', exact: true }));
  fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Eliminar componente' }));
  await waitFor(() => expect(screen.getByText('Componente no encontrado en este curso.')).toBeTruthy());
  expect(screen.getByRole('heading', { name: 'Tareas' })).toBeTruthy();
  fireEvent.keyDown(document, { key: 'Escape' });
  expect(screen.queryByRole('dialog')).toBeNull();
  expect(remove).toHaveBeenCalledTimes(1);
});
