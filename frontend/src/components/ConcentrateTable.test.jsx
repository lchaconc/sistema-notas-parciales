import React from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, expect, test } from 'vitest';
import ConcentrateTable from './ConcentrateTable.jsx';

afterEach(cleanup);

const report = {
  actividades: [{ id: 4, titulo: 'Prueba parcial', numero: 1 }],
  estudiantes: [{ matricula_id: 2, nombre: 'Ana', primer_apellido: 'Solís', segundo_apellido: 'Rojas', correo: 'ana@ucatolica.ac.cr', calificaciones: [{ actividad_id: 4, nota: null, porcentaje_obtenido: '0.00' }] }],
};

test('separa nota pendiente de nota cero y muestra el porcentaje recibido', () => {
  const { rerender } = render(<ConcentrateTable report={report} />);
  expect(screen.getByText('Sin calificar')).toBeTruthy();
  expect(screen.getByText('0,00 % obtenido')).toBeTruthy();
  rerender(<ConcentrateTable report={{ ...report, estudiantes: [{ ...report.estudiantes[0], calificaciones: [{ actividad_id: 4, nota: '0.00', porcentaje_obtenido: '0.00' }] }] }} />);
  expect(screen.getByText('0,00')).toBeTruthy();
  expect(screen.queryByText('Sin calificar')).toBeNull();
});

test('tabla del estudiante no ofrece datos de otros alumnos', () => {
  render(<ConcentrateTable report={report} studentOnly />);
  expect(screen.queryByText('Ana Solís Rojas')).toBeNull();
  expect(screen.getByText('Prueba parcial · 1')).toBeTruthy();
});
