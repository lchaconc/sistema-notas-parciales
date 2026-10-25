import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import { decimal, decimalInput, fullName, percent, validDecimal } from '../../utils/format.js';

function SaveIcon() {
  return <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M4 3h13l3 3v15H4z" /><path d="M7 3v6h9V3M7 21v-8h10v8M10 6h3" /></svg>;
}

function GradeCell({ api, person, activity, grade, onSaved, onDirty }) {
  const key = `${person.matricula_id}:${activity.id}`;
  const label = `${fullName(person)} · ${activity.titulo} · Actividad ${activity.numero}`;
  const initialPoints = grade?.nota ?? '';
  const [points, setPoints] = useState(initialPoints);
  const [savedPoints, setSavedPoints] = useState(initialPoints);
  const [observation, setObservation] = useState('');
  const [savedObservation, setSavedObservation] = useState('');
  const [observationLoaded, setObservationLoaded] = useState(false);
  const [editorOpen, setEditorOpen] = useState(false);
  const [loadingObservation, setLoadingObservation] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const editorButton = useRef(null);
  const editorClose = useRef(null);
  const editorDialog = useRef(null);
  const titleId = useId();
  const dirty = String(points) !== String(savedPoints) || (observationLoaded && observation !== savedObservation);

  useEffect(() => { onDirty(key, dirty); }, [key, dirty, onDirty]);
  useEffect(() => {
    const next = grade?.nota ?? '';
    if (!dirty && !busy) { setSavedPoints(next); setPoints(next); }
  }, [grade?.nota]); // Se sincroniza solo cuando llega otra nota confirmada del servidor.

  useEffect(() => {
    if (!editorOpen) return undefined;
    editorClose.current?.focus();
    function onKeyDown(event) {
      if (event.key === 'Escape') { setEditorOpen(false); editorButton.current?.focus(); }
      if (event.key !== 'Tab') return;
      const controls = [...editorDialog.current.querySelectorAll('button:not(:disabled), textarea:not(:disabled)')];
      const first = controls[0]; const last = controls[controls.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [editorOpen]);

  async function loadObservation() {
    setError('');
    setLoadingObservation(true);
    try {
      const saved = await api.get(`/profesor/calificaciones/${person.matricula_id}/${activity.id}`);
      if (!dirty) {
        setPoints(saved.puntos_obtenidos ?? '');
        setSavedPoints(saved.puntos_obtenidos ?? '');
      }
      setSavedObservation(saved.observacion ?? '');
      setObservation(saved.observacion ?? '');
      setObservationLoaded(true);
    } catch (cause) { setError(cause.message); }
    finally { setLoadingObservation(false); }
  }

  function openEditor() {
    setEditorOpen(true);
    if (!observationLoaded) loadObservation();
  }

  async function save() {
    setError(''); setSuccess('');
    if (!validDecimal(points, 0, Number(activity.puntaje_asignado))) {
      setError(`La nota debe estar entre 0 y ${decimal(activity.puntaje_asignado)} puntos, con hasta dos decimales.`);
      return;
    }
    if (observation.length > 125) { setError('La observación admite como máximo 125 caracteres.'); return; }
    setBusy(true);
    try {
      let currentObservation = observation;
      if (!observationLoaded) {
        const saved = await api.get(`/profesor/calificaciones/${person.matricula_id}/${activity.id}`);
        currentObservation = saved.observacion ?? '';
        setObservation(currentObservation);
        setSavedObservation(currentObservation);
        setObservationLoaded(true);
      }
      const normalizedPoints = decimalInput(points);
      await api.put(`/profesor/calificaciones/${person.matricula_id}/${activity.id}`, { puntos_obtenidos: normalizedPoints, observacion: currentObservation });
      setPoints(normalizedPoints);
      setSavedPoints(normalizedPoints);
      setSavedObservation(currentObservation);
      const refreshed = await onSaved();
      setSuccess(refreshed ? 'Calificación guardada.' : 'Calificación guardada; no se pudo actualizar el porcentaje.');
    } catch (cause) { setError(cause.message); }
    finally { setBusy(false); }
  }

  return <td className={`grade-edit-cell${dirty ? ' grade-edit-dirty' : ''}`}>
    <div className="grade-edit-controls">
      <input type="number" min="0" max={activity.puntaje_asignado} step="0.01" inputMode="decimal" value={points} disabled={busy} aria-label={`Nota de ${label}`} placeholder="Sin nota" onChange={(event) => { setPoints(event.target.value); setError(''); setSuccess(''); }} />
      <button type="button" className="grade-save-button" aria-label={`Guardar nota y observación de ${label}`} title="Guardar esta celda" disabled={busy || loadingObservation || !dirty} onClick={save}><SaveIcon /></button>
    </div>
    <div className="grade-edit-meta"><span title={dirty ? 'Porcentaje de la última nota guardada' : 'Porcentaje obtenido'}>{percent(grade?.porcentaje_obtenido ?? '0.00')} obtenido{dirty ? ' · guardado' : ''}</span><button ref={editorButton} type="button" className="text-button" onClick={openEditor} disabled={busy}>Observación{observationLoaded && observation ? ' ●' : ''}</button></div>
    {busy && <small role="status">Guardando…</small>}
    {error && <small className="grade-cell-error" role="alert">{error}</small>}
    {success && <small className="grade-cell-success" role="status">{success}</small>}
    {editorOpen && <div className="dialog-backdrop" onClick={() => setEditorOpen(false)}><div ref={editorDialog} className="dialog" role="dialog" aria-modal="true" aria-labelledby={titleId} onClick={(event) => event.stopPropagation()}>
      <h3 id={titleId}>Observación de la actividad</h3><p>{label}</p>
      {loadingObservation ? <div className="state-panel" role="status">Cargando observación…</div> : observationLoaded ? <><label className="field"><span className="field-label">Observación (opcional)</span><textarea rows={4} maxLength={125} value={observation} onChange={(event) => { setObservation(event.target.value); setSuccess(''); }} /></label><small>{observation.length}/125 caracteres</small></> : <button type="button" className="button-secondary" onClick={loadObservation}>Reintentar lectura</button>}
      {error && <div className="feedback feedback-error" role="alert">{error}</div>}
      <div className="dialog-actions"><button ref={editorClose} type="button" className="button-secondary" onClick={() => { setEditorOpen(false); editorButton.current?.focus(); }}>Volver a la tabla</button></div>
      <p className="grade-editor-hint">Guarde la nota y esta observación con el disquete de la celda.</p>
    </div></div>}
  </td>;
}

export default function GradesTable({ api, report, components, onReload, onDirtyChange }) {
  const [dirtyCells, setDirtyCells] = useState({});
  const activities = report.actividades || [];
  const people = report.estudiantes || [];
  const activityDetails = new Map(components.flatMap((component) => component.actividades.map((activity) => [Number(activity.id), activity])));
  const dirty = Object.values(dirtyCells).some(Boolean);
  useEffect(() => { onDirtyChange(dirty); }, [dirty, onDirtyChange]);
  useEffect(() => () => onDirtyChange(false), [onDirtyChange]);
  const markDirty = useCallback((key, changed) => { setDirtyCells((current) => current[key] === changed ? current : { ...current, [key]: changed }); }, []);

  if (!activities.length) return <div className="state-panel">Agregue componentes y actividades antes de calificar.</div>;
  if (!people.length) return <div className="state-panel">Matricule estudiantes antes de calificar.</div>;
  return <div className="table-scroll grades-scroll" tabIndex="0" aria-label="Tabla editable de calificaciones; desplace horizontalmente para ver todas las actividades"><table className="grades-table"><thead><tr><th className="sticky-column" scope="col">Estudiante</th>{activities.map((activity) => <th key={activity.id} scope="col"><strong>{activity.titulo} · Actividad {activity.numero}</strong><small>Máximo {decimal(activityDetails.get(Number(activity.id))?.puntaje_asignado)} pts</small></th>)}</tr></thead><tbody>{people.map((person) => <tr key={person.matricula_id}><th className="sticky-column" scope="row"><strong>{fullName(person)}</strong><small>{person.correo}</small></th>{activities.map((activity) => <GradeCell key={`${person.matricula_id}:${activity.id}`} api={api} person={person} activity={{ ...activity, puntaje_asignado: activityDetails.get(Number(activity.id))?.puntaje_asignado }} grade={person.calificaciones.find((entry) => Number(entry.actividad_id) === Number(activity.id))} onSaved={onReload} onDirty={markDirty} />)}</tr>)}</tbody></table></div>;
}
