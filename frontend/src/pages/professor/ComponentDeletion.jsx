import React, { useEffect, useId, useRef, useState } from 'react';
import { Feedback } from '../../components/UI.jsx';
import { courseLabel } from '../../utils/format.js';

export default function ComponentDeletion({ api, course, component, onDeleted, onConflict }) {
  const [open, setOpen] = useState(false);
  const [newlyGraded, setNewlyGraded] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const triggerRef = useRef(null);
  const dialogRef = useRef(null);
  const cancelRef = useRef(null);
  const titleId = useId();
  const hasGrades = Boolean(component.tiene_calificaciones || newlyGraded);

  useEffect(() => {
    if (!open) return undefined;
    cancelRef.current?.focus();
    function onKeyDown(event) {
      if (event.key === 'Escape' && !busy) {
        event.preventDefault();
        setOpen(false);
        triggerRef.current?.focus();
      }
      if (event.key !== 'Tab') return;
      const controls = [...dialogRef.current.querySelectorAll('button:not(:disabled)')];
      if (!controls.length) return;
      const first = controls[0];
      const last = controls[controls.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault(); last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault(); first.focus();
      }
    }
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [open, busy]);

  function close() {
    if (busy) return;
    setOpen(false);
    setError('');
    triggerRef.current?.focus();
  }

  async function remove() {
    if (busy) return;
    setBusy(true);
    setError('');
    const path = `/profesor/cursos/${course.id}/componentes/${component.id}`;
    try {
      await api.delete(hasGrades ? `${path}?confirmar_con_notas=true` : path);
      setOpen(false);
      await onDeleted();
    } catch (cause) {
      if (cause.status === 409 && !hasGrades) {
        setNewlyGraded(true);
        setError('Se registraron calificaciones desde que abrió esta lista. Revise la advertencia y confirme de nuevo si desea eliminar el componente.');
        try { await onConflict(); } catch { /* La advertencia sigue vigente aunque falle la actualización de la lista. */ }
      } else {
        setError(cause.message);
      }
    } finally {
      setBusy(false);
    }
  }

  return <>
    <button ref={triggerRef} type="button" className="button-danger button-small" onClick={() => { setError(''); setNewlyGraded(false); setOpen(true); }}>
      Eliminar
    </button>
    {open && <div className="dialog-backdrop" onClick={close}>
      <div ref={dialogRef} className="dialog" role="dialog" aria-modal="true" aria-labelledby={titleId} onClick={(event) => event.stopPropagation()}>
        <h3 id={titleId}>Eliminar componente</h3>
        <p><strong>{component.titulo}</strong> · {course.nombre}, {courseLabel(course)}</p>
        <p>Se eliminarán este componente, sus actividades individuales, calificaciones y observaciones. Permanecerán el curso, las matrículas, la asistencia, los demás componentes y la actividad del catálogo.</p>
        {hasGrades && <div className="feedback feedback-error" role="alert">Este componente está en uso y tiene calificaciones registradas, incluida una posible nota de 0,00. Al eliminarlo se perderán esas notas y sus observaciones.</div>}
        <Feedback tone="error" message={error} />
        <div className="dialog-actions">
          <button ref={cancelRef} type="button" className="button-secondary" disabled={busy} onClick={close}>Cancelar</button>
          <button type="button" className="button-danger" disabled={busy} onClick={remove}>{busy ? 'Eliminando…' : 'Eliminar componente'}</button>
        </div>
      </div>
    </div>}
  </>;
}
