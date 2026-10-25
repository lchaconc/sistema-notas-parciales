import React, { useState } from 'react';
import { SectionTitle, TabBar } from '../../components/UI.jsx';
import { courseLabel } from '../../utils/format.js';
import { ComponentsPanel, EnrollmentPanel } from './CourseSetup.jsx';
import { GradesPanel, AttendancePanel, ProfessorConcentratePanel } from './CourseRecords.jsx';

const tabs = [
  { id: 'componentes', label: 'Componentes' },
  { id: 'matricula', label: 'Matrícula' },
  { id: 'calificaciones', label: 'Calificaciones' },
  { id: 'asistencia', label: 'Asistencia' },
  { id: 'concentrado', label: 'Concentrado' },
];

export default function CourseWorkspace({ api, course, onBack, onDirtyChange, requestNavigation }) {
  const [tab, setTab] = useState('componentes');
  return <>
    <button type="button" className="back-link" onClick={() => requestNavigation(onBack)}>← Volver a cursos</button>
    <SectionTitle eyebrow={courseLabel(course)} title={course.nombre} description={`${course.fecha_inicio} — ${course.fecha_cierre}`} />
    <TabBar tabs={tabs} active={tab} onChange={(next) => requestNavigation(() => setTab(next))} />
    <div className="tab-content">
      {tab === 'componentes' && <ComponentsPanel api={api} course={course} />}
      {tab === 'matricula' && <EnrollmentPanel api={api} course={course} />}
      {tab === 'calificaciones' && <GradesPanel api={api} course={course} onDirtyChange={onDirtyChange} />}
      {tab === 'asistencia' && <AttendancePanel api={api} course={course} />}
      {tab === 'concentrado' && <ProfessorConcentratePanel api={api} course={course} />}
    </div>
  </>;
}
