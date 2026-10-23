import React, { useEffect, useId, useRef, useState } from 'react';

export function Feedback({ message, tone = 'info' }) {
  if (!message) return null;
  return <div className={`feedback feedback-${tone}`} role={tone === 'error' ? 'alert' : 'status'}>{message}</div>;
}

export function ResourceView({ resource, empty = 'Todavía no hay registros.', children }) {
  if (resource.loading) return <div className="state-panel" role="status">Cargando información…</div>;
  if (resource.error) return <div className="state-panel state-error" role="alert">{resource.error} <button type="button" className="text-button" onClick={resource.reload}>Reintentar</button></div>;
  if (!resource.data || resource.data.length === 0) return <div className="state-panel">{empty}</div>;
  return children(resource.data);
}

export function SectionTitle({ eyebrow, title, description, action }) {
  return <div className="section-title"><div>{eyebrow && <span className="eyebrow">{eyebrow}</span>}<h2>{title}</h2>{description && <p>{description}</p>}</div>{action && <div className="section-action">{action}</div>}</div>;
}

export function Field({ label, children, hint }) {
  return <label className="field"><span className="field-label">{label}</span>{children}{hint && <small>{hint}</small>}</label>;
}

export function ConfirmButton({ children, question, onConfirm, className = 'button-danger', disabled = false }) {
  const [open, setOpen] = useState(false);
  const triggerRef = useRef(null);
  const cancelRef = useRef(null);
  const titleId = useId();
  useEffect(() => {
    if (!open) return undefined;
    cancelRef.current?.focus();
    function onKeyDown(event) {
      if (event.key === 'Escape') { setOpen(false); triggerRef.current?.focus(); }
    }
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [open]);
  function close() { setOpen(false); triggerRef.current?.focus(); }
  return <>
    <button ref={triggerRef} type="button" className={className} disabled={disabled} onClick={() => setOpen(true)}>{children}</button>
    {open && <div className="dialog-backdrop" onClick={close}><div className="dialog" role="dialog" aria-modal="true" aria-labelledby={titleId} onClick={(event) => event.stopPropagation()}>
      <h3 id={titleId}>Confirmar eliminación</h3><p>{question}</p>
      <div className="dialog-actions"><button ref={cancelRef} type="button" className="button-secondary" onClick={close}>Cancelar</button><button type="button" className="button-danger" onClick={() => { close(); onConfirm(); }}>Sí, eliminar</button></div>
    </div></div>}
  </>;
}

export function TabBar({ tabs, active, onChange }) {
  return <div className="tab-scroll"><div className="tabs" role="tablist" aria-label="Secciones del curso">{tabs.map((tab) => <button key={tab.id} type="button" role="tab" aria-selected={active === tab.id} className={`tab ${active === tab.id ? 'active' : ''}`} onClick={() => onChange(tab.id)}>{tab.label}</button>)}</div></div>;
}
