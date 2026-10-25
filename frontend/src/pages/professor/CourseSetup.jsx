import React, { useRef, useState } from 'react';
import { Feedback, Field, ResourceView, SectionTitle } from '../../components/UI.jsx';
import { useResource } from '../../hooks/useResource.js';
import { decimal, decimalInput, fullName, percent, validDecimal, wordCount } from '../../utils/format.js';
import ComponentDeletion from './ComponentDeletion.jsx';

export function ComponentsPanel({ api, course }) {
  const components = useResource(() => api.get(`/profesor/cursos/${course.id}/componentes`), [api, course.id]);
  const catalog = useResource(() => api.get('/profesor/catalogo'), [api]);
  const initial = { actividad_catalogo_id: '', titulo: '', cantidad_actividades: '1', descripcion: '', porcentaje_total: '', puntaje_por_actividad: '' };
  const [form, setForm] = useState(initial); const [error, setError] = useState(''); const [message, setMessage] = useState(''); const [busy, setBusy] = useState(false);
  const listHeadingRef = useRef(null);
  async function submit(event) {
    event.preventDefault(); setError(''); setMessage('');
    if (wordCount(form.titulo) > 128) { setError('El título no puede superar 128 palabras.'); return; }
    if (!validDecimal(form.porcentaje_total, 0, 100) || !validDecimal(form.puntaje_por_actividad, 1, 100)) { setError('Use hasta dos decimales; el porcentaje debe estar entre 0 y 100 y el puntaje por actividad entre 1 y 100.'); return; }
    setBusy(true);
    try {
      await api.post(`/profesor/cursos/${course.id}/componentes`, {
        actividad_catalogo_id: Number(form.actividad_catalogo_id), titulo: form.titulo,
        cantidad_actividades: Number(form.cantidad_actividades), descripcion: form.descripcion,
        porcentaje_total: decimalInput(form.porcentaje_total), puntaje_por_actividad: decimalInput(form.puntaje_por_actividad),
      });
      await Promise.all([components.reload(), catalog.reload()]); setForm(initial); setMessage('Componente creado.');
    } catch (cause) { setError(cause.message); } finally { setBusy(false); }
  }
  async function componentDeleted() {
    await Promise.all([components.reload(), catalog.reload()]);
    setError('');
    setMessage('Componente eliminado. Las calificaciones y resultados se actualizarán al consultarlos.');
    listHeadingRef.current?.focus();
  }
  async function componentConflict() {
    const refreshed = await api.get(`/profesor/cursos/${course.id}/componentes`);
    components.setData(refreshed);
  }
  return <><SectionTitle title="Componentes de evaluación" description="Configure el porcentaje total y el puntaje que tendrá cada actividad." />
    <div className="two-column"><section className="panel"><h3>Nuevo componente</h3><form className="form-stack" onSubmit={submit}>
      <Field label="Actividad del catálogo"><select required value={form.actividad_catalogo_id} onChange={(e) => setForm({ ...form, actividad_catalogo_id: e.target.value })}><option value="">Seleccione una opción</option>{(catalog.data || []).map((item) => <option key={item.id} value={item.id}>{item.nombre}</option>)}</select></Field>
      <Field label="Título"><input required value={form.titulo} onChange={(e) => setForm({ ...form, titulo: e.target.value })} /></Field>
      <Field label="Descripción (opcional)"><textarea maxLength={256} rows={3} value={form.descripcion} onChange={(e) => setForm({ ...form, descripcion: e.target.value })} /></Field>
      <div className="form-row"><Field label="Cantidad de actividades"><input required type="number" min="1" max="15" step="1" value={form.cantidad_actividades} onChange={(e) => setForm({ ...form, cantidad_actividades: e.target.value })} /></Field><Field label="Porcentaje total"><input required inputMode="decimal" value={form.porcentaje_total} onChange={(e) => setForm({ ...form, porcentaje_total: e.target.value })} placeholder="10,00" /></Field></div>
      <Field label="Puntaje por actividad"><input required inputMode="decimal" value={form.puntaje_por_actividad} onChange={(e) => setForm({ ...form, puntaje_por_actividad: e.target.value })} placeholder="10,00" /></Field>
      <Feedback tone="error" message={error} /><Feedback tone="success" message={message} /><button className="button-primary" disabled={busy || catalog.loading}>{busy ? 'Guardando…' : 'Crear componente'}</button>
    </form></section><section className="panel"><h3 ref={listHeadingRef} tabIndex={-1}>Componentes del curso</h3><ResourceView resource={components} empty="Este curso todavía no tiene componentes.">{(items) => <div className="list-stack">{items.map((item) => <div className="component-item" key={item.id}><div className="component-top"><div><span className="eyebrow">{item.actividad_catalogo}</span><h4>{item.titulo}</h4></div><span className="badge">{percent(item.porcentaje_total)}</span></div>{item.descripcion && <p className="muted">{item.descripcion}</p>}<small>{item.cantidad_actividades} actividades · {decimal(item.puntaje_por_actividad)} puntos por actividad</small><div className="activity-lines">{item.actividades.map((activity) => <div key={activity.id}><span>Actividad {activity.numero}</span><span>{decimal(activity.puntaje_asignado)} pts · {percent(activity.porcentaje_asignado)}</span></div>)}</div><div className="component-actions"><ComponentDeletion api={api} course={course} component={item} onDeleted={componentDeleted} onConflict={componentConflict} /></div></div>)}</div>}</ResourceView></section></div>
  </>;
}

export function EnrollmentPanel({ api, course }) {
  const enrollments = useResource(() => api.get(`/profesor/cursos/${course.id}/matriculas`), [api, course.id]);
  const initial = { nombre: '', primer_apellido: '', segundo_apellido: '', correo: '' };
  const [form, setForm] = useState(initial); const [error, setError] = useState(''); const [message, setMessage] = useState(''); const [busy, setBusy] = useState(false);
  async function submit(event) {
    event.preventDefault(); setBusy(true); setError(''); setMessage('');
    try { await api.post(`/profesor/cursos/${course.id}/matriculas`, form); await enrollments.reload(); setForm(initial); setMessage('Estudiante matriculado. Si había sesiones anteriores, su asistencia inicial es justificada.'); }
    catch (cause) { setError(cause.message); } finally { setBusy(false); }
  }
  return <><SectionTitle title="Matrícula" description="Al matricular un correo existente, se reutiliza la misma cuenta de estudiante." />
    <div className="two-column"><section className="panel"><h3>Agregar estudiante</h3><form className="form-stack" onSubmit={submit}>
      <Field label="Nombre"><input required maxLength={255} value={form.nombre} onChange={(e) => setForm({ ...form, nombre: e.target.value })} /></Field>
      <div className="form-row"><Field label="Primer apellido"><input required maxLength={255} value={form.primer_apellido} onChange={(e) => setForm({ ...form, primer_apellido: e.target.value })} /></Field><Field label="Segundo apellido"><input required maxLength={255} value={form.segundo_apellido} onChange={(e) => setForm({ ...form, segundo_apellido: e.target.value })} /></Field></div>
      <Field label="Correo electrónico"><input required type="email" maxLength={254} value={form.correo} onChange={(e) => setForm({ ...form, correo: e.target.value })} /></Field>
      <Feedback tone="error" message={error} /><Feedback tone="success" message={message} /><button className="button-primary" disabled={busy}>{busy ? 'Matriculando…' : 'Matricular estudiante'}</button>
    </form></section><section className="panel"><h3>Estudiantes matriculados</h3><ResourceView resource={enrollments} empty="Todavía no hay estudiantes matriculados.">{(items) => <div className="list-stack">{items.map((person) => <div className="list-item" key={person.id}><strong>{fullName(person)}</strong><span>{person.correo}</span></div>)}</div>}</ResourceView></section></div>
  </>;
}
