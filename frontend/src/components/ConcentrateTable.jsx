import React from 'react';
import { decimal, fullName, percent } from '../utils/format.js';

export default function ConcentrateTable({ report, studentOnly = false }) {
  const activities = report.actividades || [];
  const people = report.estudiantes || [];
  if (!activities.length) return <div className="state-panel">Todavía no hay actividades para mostrar.</div>;
  if (!people.length) return <div className="state-panel">Todavía no hay estudiantes matriculados.</div>;
  return <div className="table-scroll" tabIndex="0" aria-label="Concentrado de calificaciones; desplace horizontalmente para ver todas las actividades"><table className="concentrate-table"><thead><tr>{!studentOnly && <th className="sticky-column">Estudiante</th>}{activities.map((activity) => <th key={activity.id}>{activity.titulo} · {activity.numero}</th>)}</tr></thead><tbody>{people.map((person) => <tr key={person.matricula_id}>{!studentOnly && <th className="sticky-column" scope="row"><strong>{fullName(person)}</strong><small>{person.correo}</small></th>}{activities.map((activity) => { const cell = person.calificaciones.find((entry) => Number(entry.actividad_id) === Number(activity.id)); return <td key={activity.id}><span className={cell?.nota === null || cell?.nota === undefined ? 'pending-note' : 'grade-number'}>{decimal(cell?.nota)}</span><small>{percent(cell?.porcentaje_obtenido ?? '0.00')} obtenido</small></td>; })}</tr>)}</tbody></table></div>;
}
