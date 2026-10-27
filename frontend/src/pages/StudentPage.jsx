import React, { useState } from 'react';
import Shell from '../components/Shell.jsx';
import ConcentrateTable from '../components/ConcentrateTable.jsx';
import { Feedback, Field, ResourceView, SectionTitle, TabBar } from '../components/UI.jsx';
import { useResource } from '../hooks/useResource.js';
import { courseLabel, decimal, percent } from '../utils/format.js';

const menu = [{ id: 'cursos', label: 'Mis cursos' }, { id: 'perfil', label: 'Perfil' }];
const tabs = [{ id: 'calificaciones', label: 'Calificaciones' }, { id: 'asistencia', label: 'Asistencia' }, { id: 'concentrado', label: 'Concentrado' }];

export default function StudentPage({ api, onLogout, theme, onToggleTheme }) {
  const [section, setSection] = useState('cursos'); const [course, setCourse] = useState(null);
  function navigate(next) { setSection(next); setCourse(null); }
  return <Shell role="estudiante" items={menu} active={section} onNavigate={navigate} onLogout={onLogout} theme={theme} onToggleTheme={onToggleTheme}>
    {section === 'perfil' ? <ProfilePanel api={api} /> : course ? <StudentCourse key={course.id} api={api} course={course} onBack={() => setCourse(null)} /> : <StudentCourses api={api} onSelect={setCourse} />}
  </Shell>;
}

function StudentCourses({ api, onSelect }) {
  const courses = useResource(() => api.get('/estudiante/cursos'), [api]);
  return <><SectionTitle eyebrow="Área del estudiante" title="Mis cursos" description="Seleccione un curso para consultar sus calificaciones y asistencia." />
    <ResourceView resource={courses} empty="Todavía no tiene cursos matriculados.">{(items) => <div className="student-course-grid">{items.map((course) => <button type="button" className="student-course-card" key={course.id} onClick={() => onSelect(course)}><span className="course-card-code">{course.codigo}</span><strong>{course.nombre}</strong><span>{courseLabel(course)}</span><small>{course.fecha_inicio} — {course.fecha_cierre}</small><span className="card-arrow">Abrir curso →</span></button>)}</div>}</ResourceView>
  </>;
}

function StudentCourse({ api, course, onBack }) {
  const [tab, setTab] = useState('calificaciones');
  return <><button type="button" className="back-link" onClick={onBack}>← Volver a mis cursos</button><SectionTitle eyebrow={courseLabel(course)} title={course.nombre} description={`${course.fecha_inicio} — ${course.fecha_cierre}`} /><TabBar tabs={tabs} active={tab} onChange={setTab} />
    <div className="tab-content">{tab === 'calificaciones' && <StudentGrades api={api} course={course} />}{tab === 'asistencia' && <StudentAttendance api={api} course={course} />}{tab === 'concentrado' && <StudentConcentrate api={api} course={course} />}</div>
  </>;
}

function StudentGrades({ api, course }) {
  const grades = useResource(() => api.get(`/estudiante/cursos/${course.id}/calificaciones`), [api, course.id]);
  return <><SectionTitle title="Mis calificaciones" description="Se muestra el porcentaje que obtuvo en cada actividad." />
    {grades.loading && <div className="state-panel">Cargando calificaciones…</div>}{grades.error && <div className="state-panel state-error" role="alert">{grades.error} <button type="button" className="text-button" onClick={grades.reload}>Reintentar</button></div>}
    {grades.data && <><div className="result-strip"><div><span className="eyebrow">Resultado del curso</span><strong>{percent(grades.data.resultado)}</strong></div><p>La suma de los aportes obtenidos en las actividades.</p></div>{grades.data.tarjetas.length ? <div className="grade-grid">{grades.data.tarjetas.map((card) => <article className="grade-card" key={card.id}><div className="grade-card-top"><span className="eyebrow">{card.actividad_catalogo}{card.cantidad_actividades > 1 ? ` ${card.numero}` : ''}</span><span className="badge">{percent(card.porcentaje_obtenido)} obtenido</span></div><h3>{card.titulo}</h3><div className="grade-value"><small>Nota obtenida</small><strong>{decimal(card.nota)}</strong></div><div className="observation"><small>Observación</small><p>{card.observacion || 'Sin observación registrada.'}</p></div></article>)}</div> : <div className="state-panel">Todavía no hay actividades de evaluación.</div>}</>}
  </>;
}

function StudentAttendance({ api, course }) {
  const attendance = useResource(() => api.get(`/estudiante/cursos/${course.id}/asistencia`), [api, course.id]);
  return <><SectionTitle title="Mi asistencia" description="Condición registrada para cada sesión de este curso." /><section className="panel"><ResourceView resource={attendance} empty="Todavía no hay sesiones de asistencia registradas.">{(items) => <div className="list-stack">{items.map((item) => <div className="list-item list-actions" key={item.sesion_id}><strong>{item.fecha}</strong><span className={`status-chip status-${item.condicion}`}>{item.condicion}</span></div>)}</div>}</ResourceView></section></>;
}

function StudentConcentrate({ api, course }) {
  const report = useResource(() => api.get(`/estudiante/cursos/${course.id}/concentrado`), [api, course.id]);
  return <><SectionTitle title="Mi concentrado" description="Todas sus notas y porcentajes obtenidos por actividad en este curso." /><section className="panel"><ResourceView resource={report}>{(data) => <ConcentrateTable report={data} studentOnly />}</ResourceView></section></>;
}

function ProfilePanel({ api }) {
  const [password, setPassword] = useState(''); const [error, setError] = useState(''); const [message, setMessage] = useState(''); const [busy, setBusy] = useState(false);
  async function submit(event) {
    event.preventDefault(); setBusy(true); setError(''); setMessage('');
    try { await api.put('/estudiante/perfil/contrasena', { contrasena_nueva: password }); setPassword(''); setMessage('Contraseña actualizada.'); }
    catch (cause) { setError(cause.message); } finally { setBusy(false); }
  }
  return <><SectionTitle eyebrow="Cuenta" title="Perfil" description="Actualice la contraseña con la que accede al sistema." /><section className="panel panel-narrow"><h3>Cambiar contraseña</h3><form className="form-stack" onSubmit={submit}><Field label="Nueva contraseña"><input type="password" autoComplete="new-password" required value={password} onChange={(e) => setPassword(e.target.value)} /></Field><Feedback tone="error" message={error} /><Feedback tone="success" message={message} /><button className="button-primary" disabled={busy}>{busy ? 'Actualizando…' : 'Cambiar contraseña'}</button></form></section></>;
}
