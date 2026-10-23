import React, { useState } from 'react';
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
  function navigate(next) { setSection(next); setCourse(null); }
  return <Shell role="profesor" items={menu} active={section} onNavigate={navigate} onLogout={onLogout} theme={theme} onToggleTheme={onToggleTheme}>
    {section === 'cursos' && (course
      ? <CourseWorkspace key={course.id} api={api} course={course} onBack={() => setCourse(null)} />
      : <CoursesPanel api={api} onSelect={setCourse} />)}
    {section === 'cuatrimestres' && <QuarterPanel api={api} />}
    {section === 'catalogo' && <CatalogPanel api={api} />}
    {section === 'estudiantes' && <StudentsPanel api={api} />}
  </Shell>;
}
