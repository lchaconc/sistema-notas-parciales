import React, { useEffect, useState } from 'react';
import { Feedback, Field, ResourceView, SectionTitle } from '../../components/UI.jsx';
import ConcentrateTable from '../../components/ConcentrateTable.jsx';
import { useResource } from '../../hooks/useResource.js';
import { decimal, fullName } from '../../utils/format.js';
import GradesTable from './GradesTable.jsx';

export function GradesPanel({ api, course, onDirtyChange }) {
  const report = useResource(() => api.get(`/profesor/cursos/${course.id}/concentrado`), [api, course.id]);
  const components = useResource(() => api.get(`/profesor/cursos/${course.id}/componentes`), [api, course.id]);
  return <><SectionTitle title="Calificaciones" description="Escriba los puntos obtenidos en cada actividad y guarde cada celda con su disquete. El porcentaje mostrado procede de la API." />
    <section className="panel grades-panel">
      {(report.loading || components.loading) && !report.data && <div className="state-panel" role="status">Cargando calificaciones…</div>}
      <Feedback tone="error" message={report.error || components.error} />
      {report.data && components.data && <GradesTable api={api} report={report.data} components={components.data} onReload={report.reload} onDirtyChange={onDirtyChange} />}
    </section>
  </>;
}

const conditions = ['presente', 'ausente', 'justificado'];

export function AttendancePanel({ api, course }) {
  const people = useResource(() => api.get(`/profesor/cursos/${course.id}/matriculas`), [api, course.id]);
  const sessions = useResource(() => api.get(`/profesor/cursos/${course.id}/sesiones`), [api, course.id]);
  const [sessionId, setSessionId] = useState('');
  const [entries, setEntries] = useState({}); const [error, setError] = useState(''); const [message, setMessage] = useState(''); const [busy, setBusy] = useState(false);
  const current = (sessions.data || []).find((session) => String(session.id) === sessionId);
  useEffect(() => {
    const selected = (sessions.data || []).find((session) => String(session.id) === sessionId);
    if (selected) setEntries(Object.fromEntries(selected.asistencias.map((entry) => [entry.matricula_id, entry.condicion])));
  }, [sessionId, sessions.data]);
  async function submit(event) {
    event.preventDefault(); setError(''); setMessage('');
    if (!current || !people.data?.length) return;
    const list = (people.data || []).map((person) => ({ matricula_id: Number(person.id), condicion: entries[person.id] }));
    if (list.some((entry) => !entry.condicion)) { setError('Asigne una condición a todos los estudiantes.'); return; }
    setBusy(true);
    try {
      await api.put(`/profesor/sesiones/${current.id}/asistencias`, { asistencias: list });
      await sessions.reload();
      setMessage('Asistencia guardada.');
    } catch (cause) { setError(cause.message); } finally { setBusy(false); }
  }
  return <><SectionTitle title="Asistencia" description="Quince sesiones semanales programadas. Seleccione una sesión y registre una condición por estudiante." />
    <section className="panel"><div className="attendance-header"><Field label="Sesión"><select value={sessionId} onChange={(e) => { setSessionId(e.target.value); setEntries({}); setError(''); setMessage(''); }}><option value="">Seleccione una sesión</option>{(sessions.data || []).map((session) => <option key={session.id} value={session.id}>Sesión {session.numero} · {session.fecha} · {session.asistencia_registrada ? 'Registrada' : 'Pendiente'}</option>)}</select></Field><span className="session-count">{current ? `Sesión ${current.numero}: ${current.asistencia_registrada ? 'asistencia registrada' : 'asistencia pendiente'}` : '15 sesiones programadas'}</span></div>
      <Feedback tone="error" message={error || people.error || sessions.error} /><Feedback tone="success" message={message} />
      {people.loading || sessions.loading ? <div className="state-panel">Cargando asistencia…</div> : <form onSubmit={submit} className="form-stack">{!people.data?.length && <div className="state-panel">Matricule estudiantes antes de registrar asistencia.</div>}
        <div className="attendance-list">{(people.data || []).map((person) => <div className="attendance-person" key={person.id}><div><strong>{fullName(person)}</strong><small>{person.correo}</small></div><select aria-label={`Condición de ${fullName(person)}`} required value={entries[person.id] || ''} onChange={(e) => setEntries({ ...entries, [person.id]: e.target.value })}><option value="">Seleccione condición</option>{conditions.map((condition) => <option key={condition} value={condition}>{condition[0].toUpperCase() + condition.slice(1)}</option>)}</select></div>)}</div>
        <button className="button-primary" disabled={busy || !people.data?.length || !current}>{busy ? 'Guardando…' : current?.asistencia_registrada ? 'Guardar cambios' : 'Registrar asistencia'}</button>
      </form>}</section>
  </>;
}

export function ProfessorConcentratePanel({ api, course }) {
  const report = useResource(() => api.get(`/profesor/cursos/${course.id}/concentrado`), [api, course.id]);
  const [error, setError] = useState(''); const [busy, setBusy] = useState(false);
  async function download() {
    setError(''); setBusy(true);
    try {
      const blob = await api.download(`/profesor/cursos/${course.id}/concentrado.xlsx`);
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a'); anchor.href = url; anchor.download = `concentrado-${course.codigo}-grupo-${course.numero_grupo}.xlsx`; document.body.appendChild(anchor); anchor.click(); anchor.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (cause) { setError(cause.message); } finally { setBusy(false); }
  }
  return <><SectionTitle title="Concentrado de calificaciones" description="Notas y porcentaje obtenido por actividad para este curso y grupo." action={<div className="button-row"><button type="button" className="button-secondary" onClick={report.reload}>Actualizar</button><button type="button" className="button-primary" disabled={busy || !report.data} onClick={download}>{busy ? 'Descargando…' : 'Descargar .xlsx'}</button></div>} />
    <Feedback tone="error" message={error} /><section className="panel"><ResourceView resource={report}>{(data) => <ConcentrateTable report={data} />}</ResourceView></section>
  </>;
}
