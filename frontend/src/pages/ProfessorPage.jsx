import React, { useCallback, useEffect, useRef, useState } from 'react';
import Shell from '../components/Shell.jsx';
import { CatalogPanel, CoursesPanel, QuarterPanel, StudentsPanel } from './professor/Management.jsx';
import CourseWorkspace from './professor/CourseWorkspace.jsx';

const menu = [
  { id: 'cursos', label: 'Cursos' },
  { id: 'cuatrimestres', label: 'Cuatrimestres' },
  { id: 'catalogo', label: 'Catálogo de actividades' },
  { id: 'estudiantes', label: 'Estudiantes' },
];

export default function ProfessorPage({ api, onLogout, theme, onToggleTheme }) {
  const [section, setSection] = useState('cursos');
  const [course, setCourse] = useState(null);
  const [dirtyGrades, setDirtyGrades] = useState(false);
  const [confirmLeave, setConfirmLeave] = useState(false);
  const pendingAction = useRef(null);
  const cancelButton = useRef(null);
  const leaveDialog = useRef(null);
  const markDirty = useCallback((dirty) => setDirtyGrades(dirty), []);
  function requestNavigation(action) {
    if (dirtyGrades) { pendingAction.current = action; setConfirmLeave(true); }
    else action();
  }
  function stay() { pendingAction.current = null; setConfirmLeave(false); }
  function leave() {
    const action = pendingAction.current;
    pendingAction.current = null;
    setConfirmLeave(false);
    setDirtyGrades(false);
    action?.();
  }
  useEffect(() => {
    if (!confirmLeave) return undefined;
    cancelButton.current?.focus();
    function onKeyDown(event) {
      if (event.key === 'Escape') stay();
      if (event.key !== 'Tab') return;
      const controls = [...leaveDialog.current.querySelectorAll('button:not(:disabled)')];
      const first = controls[0]; const last = controls[controls.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [confirmLeave]);
  function navigate(next) { requestNavigation(() => { setSection(next); setCourse(null); }); }
  return <Shell role="profesor" items={menu} active={section} onNavigate={navigate} onLogout={() => requestNavigation(onLogout)} theme={theme} onToggleTheme={onToggleTheme}>
    {section === 'cursos' && (course
      ? <CourseWorkspace key={course.id} api={api} course={course} onBack={() => setCourse(null)} onDirtyChange={markDirty} requestNavigation={requestNavigation} />
      : <CoursesPanel api={api} onSelect={setCourse} />)}
    {section === 'cuatrimestres' && <QuarterPanel api={api} />}
    {section === 'catalogo' && <CatalogPanel api={api} />}
    {section === 'estudiantes' && <StudentsPanel api={api} />}
    {confirmLeave && <div className="dialog-backdrop" onClick={stay}><div ref={leaveDialog} className="dialog" role="dialog" aria-modal="true" aria-label="Cambios sin guardar" onClick={(event) => event.stopPropagation()}>
      <h3>Cambios sin guardar</h3><p>Hay calificaciones u observaciones modificadas que todavía no se han guardado. Si sale, se descartarán esos cambios.</p>
      <div className="dialog-actions"><button ref={cancelButton} type="button" className="button-secondary" onClick={stay}>Continuar editando</button><button type="button" className="button-danger" onClick={leave}>Salir sin guardar</button></div>
    </div></div>}
  </Shell>;
}
