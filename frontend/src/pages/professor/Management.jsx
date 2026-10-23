import React, { useState } from 'react';
import { ConfirmButton, Feedback, Field, ResourceView, SectionTitle } from '../../components/UI.jsx';
import { useResource } from '../../hooks/useResource.js';
import { courseLabel, fullName } from '../../utils/format.js';

export function QuarterPanel({ api }) {
  const quarters = useResource(() => api.get('/profesor/cuatrimestres'), [api]);
  const [form, setForm] = useState({ codigo: '', fecha_inicio: '', fecha_cierre: '' });
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  async function submit(event) {
    event.preventDefault(); setBusy(true); setError(''); setMessage('');
    if (form.fecha_inicio > form.fecha_cierre) { setError('La fecha de inicio no puede ser posterior al cierre.'); setBusy(false); return; }
    try { await api.post('/profesor/cuatrimestres', form); await quarters.reload(); setForm({ codigo: '', fecha_inicio: '', fecha_cierre: '' }); setMessage('Cuatrimestre creado.'); }
    catch (cause) { setError(cause.message); } finally { setBusy(false); }
  }
  return <><SectionTitle eyebrow="Organización académica" title="Cuatrimestres" description="Defina las fechas que comparten los cursos del mismo cuatrimestre." />
    <div className="two-column"><section className="panel"><h3>Nuevo cuatrimestre</h3><form className="form-stack" onSubmit={submit}>
      <Field label="Código" hint="Formato N-AAAA, por ejemplo 2-2026"><input required pattern="[1-3]-[0-9]{4}" value={form.codigo} onChange={(e) => setForm({ ...form, codigo: e.target.value })} placeholder="2-2026" /></Field>
      <Field label="Fecha de inicio"><input required type="date" value={form.fecha_inicio} onChange={(e) => setForm({ ...form, fecha_inicio: e.target.value })} /></Field>
      <Field label="Fecha de cierre"><input required type="date" value={form.fecha_cierre} onChange={(e) => setForm({ ...form, fecha_cierre: e.target.value })} /></Field>
      <Feedback tone="error" message={error} /><Feedback tone="success" message={message} /><button className="button-primary" disabled={busy}>{busy ? 'Guardando…' : 'Crear cuatrimestre'}</button>
    </form></section><section className="panel"><h3>Cuatrimestres registrados</h3><ResourceView resource={quarters} empty="No hay cuatrimestres registrados.">{(items) => <div className="list-stack">{items.map((q) => <div className="list-item" key={q.id}><strong>{q.codigo}</strong><span>{q.fecha_inicio} — {q.fecha_cierre}</span></div>)}</div>}</ResourceView></section></div>
  </>;
}

export function CoursesPanel({ api, onSelect }) {
  const courses = useResource(() => api.get('/profesor/cursos'), [api]);
  const quarters = useResource(() => api.get('/profesor/cuatrimestres'), [api]);
  const [form, setForm] = useState({ cuatrimestre_id: '', nombre: '', codigo: '', numero_grupo: '1' });
  const [error, setError] = useState(''); const [message, setMessage] = useState(''); const [busy, setBusy] = useState(false);
  async function submit(event) {
    event.preventDefault(); setBusy(true); setError(''); setMessage('');
    try { await api.post('/profesor/cursos', { ...form, cuatrimestre_id: Number(form.cuatrimestre_id), numero_grupo: Number(form.numero_grupo) }); await courses.reload(); setForm({ cuatrimestre_id: '', nombre: '', codigo: '', numero_grupo: '1' }); setMessage('Curso creado.'); }
    catch (cause) { setError(cause.message); } finally { setBusy(false); }
  }
  async function remove(course) {
    setError(''); setMessage('');
    try { await api.delete(`/profesor/cursos/${course.id}`); await courses.reload(); setMessage('Curso eliminado.'); }
    catch (cause) { setError(cause.message); }
  }
  return <><SectionTitle eyebrow="Área del profesor" title="Cursos y grupos" description="Seleccione un grupo para administrar sus evaluaciones, matrícula y asistencia." />
    <div className="two-column"><section className="panel"><h3>Nuevo curso</h3><form className="form-stack" onSubmit={submit}>
      <Field label="Cuatrimestre"><select required value={form.cuatrimestre_id} onChange={(e) => setForm({ ...form, cuatrimestre_id: e.target.value })}><option value="">Seleccione un cuatrimestre</option>{(quarters.data || []).map((q) => <option key={q.id} value={q.id}>{q.codigo} · {q.fecha_inicio} a {q.fecha_cierre}</option>)}</select></Field>
      <Field label="Nombre del curso"><input required maxLength={255} value={form.nombre} onChange={(e) => setForm({ ...form, nombre: e.target.value })} /></Field>
      <div className="form-row"><Field label="Código"><input required maxLength={64} value={form.codigo} onChange={(e) => setForm({ ...form, codigo: e.target.value })} /></Field><Field label="Número de grupo"><input required type="number" step="1" value={form.numero_grupo} onChange={(e) => setForm({ ...form, numero_grupo: e.target.value })} /></Field></div>
      <Feedback tone="error" message={error} /><Feedback tone="success" message={message} /><button className="button-primary" disabled={busy || quarters.loading}>{busy ? 'Guardando…' : 'Crear curso'}</button>
    </form></section><section className="panel panel-list"><h3>Cursos registrados</h3><ResourceView resource={courses} empty="Todavía no hay cursos. Cree un cuatrimestre y luego un curso.">{(items) => <div className="course-list">{items.map((course) => <div className="course-item" key={course.id}><div className="course-icon">{course.codigo.slice(0, 2).toUpperCase()}</div><div className="course-copy"><strong>{course.nombre}</strong><span>{courseLabel(course)}</span><small>{course.fecha_inicio} — {course.fecha_cierre}</small></div><div className="course-actions"><button type="button" className="button-secondary" onClick={() => onSelect(course)}>Abrir</button><ConfirmButton question={`Se eliminará ${course.nombre}, ${courseLabel(course)}, con sus matrículas, componentes, calificaciones y asistencia. Los estudiantes, el cuatrimestre y el catálogo se conservan.`} onConfirm={() => remove(course)}>Eliminar</ConfirmButton></div></div>)}</div>}</ResourceView></section></div>
  </>;
}

export function CatalogPanel({ api }) {
  const catalog = useResource(() => api.get('/profesor/catalogo'), [api]);
  const [name, setName] = useState(''); const [editing, setEditing] = useState(null);
  const [error, setError] = useState(''); const [message, setMessage] = useState(''); const [busy, setBusy] = useState(false);
  async function submit(event) {
    event.preventDefault(); setBusy(true); setError(''); setMessage('');
    try { if (editing) await api.put(`/profesor/catalogo/${editing}`, { nombre: name }); else await api.post('/profesor/catalogo', { nombre: name }); await catalog.reload(); setName(''); setEditing(null); setMessage('Actividad guardada.'); }
    catch (cause) { setError(cause.message); await catalog.reload(); } finally { setBusy(false); }
  }
  async function remove(id) {
    setError(''); setMessage('');
    try { await api.delete(`/profesor/catalogo/${id}`); await catalog.reload(); setMessage('Actividad eliminada.'); }
    catch (cause) { setError(cause.message); await catalog.reload(); }
  }
  return <><SectionTitle eyebrow="Configuración" title="Catálogo de actividades" description="Las actividades asociadas a un componente quedan bloqueadas para edición y eliminación." />
    <div className="two-column"><section className="panel"><h3>{editing ? 'Editar actividad' : 'Nueva actividad'}</h3><form className="form-stack" onSubmit={submit}><Field label="Nombre de la actividad"><input required maxLength={255} value={name} onChange={(e) => setName(e.target.value)} /></Field><Feedback tone="error" message={error} /><Feedback tone="success" message={message} /><div className="button-row"><button className="button-primary" disabled={busy}>{busy ? 'Guardando…' : editing ? 'Guardar cambios' : 'Crear actividad'}</button>{editing && <button type="button" className="button-secondary" onClick={() => { setEditing(null); setName(''); }}>Cancelar</button>}</div></form></section>
      <section className="panel"><h3>Opciones disponibles</h3><ResourceView resource={catalog}>{(items) => <div className="list-stack">{items.map((item) => <div className="list-item list-actions" key={item.id}><div><strong>{item.nombre}</strong>{item.en_uso && <small>En uso · edición bloqueada</small>}</div><div className="button-row"><button type="button" className="button-secondary button-small" disabled={item.en_uso} onClick={() => { setEditing(item.id); setName(item.nombre); setError(''); setMessage(''); }}>Editar</button><ConfirmButton className="button-danger button-small" disabled={item.en_uso} question={`¿Eliminar «${item.nombre}» del catálogo?`} onConfirm={() => remove(item.id)}>Eliminar</ConfirmButton></div></div>)}</div>}</ResourceView></section></div>
  </>;
}

export function StudentsPanel({ api }) {
  const students = useResource(() => api.get('/profesor/estudiantes'), [api]);
  const [password, setPassword] = useState(null); const [error, setError] = useState(''); const [busy, setBusy] = useState(null);
  async function generate(student) {
    setPassword(null); setError(''); setBusy(student.usuario_id);
    try { const result = await api.post(`/profesor/estudiantes/${student.usuario_id}/contrasena`, {}); setPassword({ student: fullName(student), value: result.contrasena }); }
    catch (cause) { setError(cause.message); } finally { setBusy(null); }
  }
  return <><SectionTitle eyebrow="Personas" title="Estudiantes" description="Consulte las cuentas registradas y genere una contraseña cuando sea necesario." />
    <Feedback tone="error" message={error} />
    {password && <div className="password-panel" role="status"><div><strong>Contraseña para {password.student}</strong><p>Compártala por correo con el estudiante. Al cerrar este aviso, dejará de mostrarse.</p><code>{password.value}</code></div><button type="button" className="button-secondary" onClick={() => setPassword(null)}>Cerrar</button></div>}
    <section className="panel"><ResourceView resource={students} empty="Todavía no hay estudiantes. Se registran al matricularlos en un curso.">{(items) => <div className="table-scroll"><table><thead><tr><th>Estudiante</th><th>Correo</th><th>Acción</th></tr></thead><tbody>{items.map((student) => <tr key={student.usuario_id}><td>{fullName(student)}</td><td>{student.correo}</td><td><button type="button" className="button-secondary button-small" disabled={busy === student.usuario_id} onClick={() => generate(student)}>Generar contraseña</button></td></tr>)}</tbody></table></div>}</ResourceView></section>
  </>;
}
