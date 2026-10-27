import React from 'react';
import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import StudentPage from './StudentPage.jsx';

afterEach(cleanup);

test('las tarjetas muestran el nombre del catálogo solo o con el número de su componente', async () => {
  const course = {
    id: 7, nombre: 'Informática Aplicada', codigo: 'PD008', numero_grupo: 1,
    cuatrimestre_codigo: '3-2026', fecha_inicio: '2026-09-01', fecha_cierre: '2026-12-15',
  };
  const cards = [
    { id: 10, titulo: 'Proyecto en Word', numero: 1, actividad_catalogo: 'proyecto', cantidad_actividades: 1 },
    { id: 11, titulo: 'Tareas', numero: 1, actividad_catalogo: 'tarea', cantidad_actividades: 2 },
    { id: 12, titulo: 'Tareas', numero: 2, actividad_catalogo: 'tarea', cantidad_actividades: 2 },
    { id: 13, titulo: 'Otro proyecto', numero: 1, actividad_catalogo: 'proyecto', cantidad_actividades: 2 },
  ];
  const api = {
    get: vi.fn((path) => Promise.resolve(path === '/estudiante/cursos'
      ? [course]
      : { resultado: '0.00', tarjetas: cards.map((card) => ({ ...card, nota: null, porcentaje_obtenido: '0.00', observacion: null })) })),
  };

  render(<StudentPage api={api} onLogout={() => {}} theme="dark" onToggleTheme={() => {}} />);
  fireEvent.click((await screen.findByText('Informática Aplicada')).closest('button'));
  await waitFor(() => expect(document.querySelectorAll('.grade-card')).toHaveLength(4));
  const renderedCards = [...document.querySelectorAll('.grade-card')];
  expect(within(renderedCards[0]).getByText('proyecto')).toBeTruthy();
  expect(within(renderedCards[0]).getByText('Proyecto en Word')).toBeTruthy();
  expect(within(renderedCards[1]).getByText('tarea 1')).toBeTruthy();
  expect(within(renderedCards[2]).getByText('tarea 2')).toBeTruthy();
  expect(within(renderedCards[3]).getByText('proyecto 1')).toBeTruthy();
  expect(screen.queryByText('Actividad 1')).toBeNull();
  expect(api.get).toHaveBeenCalledWith('/estudiante/cursos/7/calificaciones');
});
