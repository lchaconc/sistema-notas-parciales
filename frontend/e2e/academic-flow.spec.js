import { test, expect } from '@playwright/test';

async function navigate(page, label, mobile) {
  if (mobile) await page.getByRole('button', { name: 'Abrir menú' }).click();
  await page.getByRole('navigation', { name: 'Navegación principal' }).getByRole('button', { name: label }).click();
}

async function logout(page, mobile) {
  if (mobile) await page.getByRole('button', { name: 'Abrir menú' }).click();
  await page.getByRole('button', { name: 'Cerrar sesión' }).click();
}

test('profesor y estudiante completan sus recorridos con la API y base temporal', async ({ page }, testInfo) => {
  const mobile = testInfo.project.name === 'mobile';
  const quarterCode = mobile ? '2-2098' : '1-2098';
  const courseCode = mobile ? 'E2EM' : 'E2ED';
  const studentEmail = mobile ? 'e2e-movil@ucatolica.ac.cr' : 'e2e-escritorio@ucatolica.ac.cr';
  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill('profesor@ucatolica.ac.cr');
  await page.getByLabel('Contraseña').fill(process.env.E2E_PROFESSOR_PASSWORD || 'e2e-fixture-only');
  await page.getByRole('button', { name: 'Iniciar sesión' }).click();
  await expect(page.getByRole('heading', { name: 'Cursos y grupos' })).toBeVisible();

  await navigate(page, 'Cuatrimestres', mobile);
  await page.getByLabel('Código').fill(quarterCode);
  await page.getByLabel('Fecha de inicio').fill('2098-01-01');
  await page.getByLabel('Fecha de cierre').fill('2098-04-30');
  await page.getByRole('button', { name: 'Crear cuatrimestre' }).click();
  await expect(page.getByText('Cuatrimestre creado.')).toBeVisible();

  await navigate(page, 'Cursos', mobile);
  await page.getByLabel('Cuatrimestre').selectOption({ label: `${quarterCode} · 2098-01-01 a 2098-04-30` });
  await page.getByLabel('Nombre del curso').fill('Curso de integración');
  await page.getByLabel('Código').fill(courseCode);
  await page.getByLabel('Número de grupo').fill('1');
  await page.getByRole('button', { name: 'Crear curso' }).click();
  await expect(page.getByText('Curso creado.')).toBeVisible();
  await page.locator('.course-item').filter({ hasText: courseCode }).getByRole('button', { name: 'Abrir', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Componentes de evaluación' })).toBeVisible();

  await page.getByLabel('Actividad del catálogo').selectOption({ index: 1 });
  await page.getByLabel('Título').fill('Prueba de integración');
  await page.getByLabel('Cantidad de actividades').fill('1');
  await page.getByLabel('Porcentaje total').fill('2,00');
  await page.getByLabel('Puntaje por actividad').fill('10,00');
  await page.getByRole('button', { name: 'Crear componente' }).click();
  await expect(page.getByText('Componente creado.')).toBeVisible();

  await page.getByLabel('Actividad del catálogo').selectOption({ index: 1 });
  await page.getByLabel('Título').fill('Cinco actividades de diez puntos');
  await page.getByLabel('Cantidad de actividades').fill('5');
  await page.getByLabel('Porcentaje total').fill('10,00');
  await page.getByLabel('Puntaje por actividad').fill('10,00');
  await page.getByRole('button', { name: 'Crear componente' }).click();
  const fiveActivities = page.locator('.component-item').filter({ hasText: 'Cinco actividades de diez puntos' });
  await expect(fiveActivities.getByText('10,00 pts · 2,00 %')).toHaveCount(5);

  await page.getByRole('tab', { name: 'Matrícula' }).click();
  await page.getByLabel('Nombre', { exact: true }).fill('Ana');
  await page.getByLabel('Primer apellido').fill('Solís');
  await page.getByLabel('Segundo apellido').fill('Rojas');
  await page.getByLabel('Correo electrónico').fill(studentEmail);
  await page.getByRole('button', { name: 'Matricular estudiante' }).click();
  await expect(page.getByText('Ana Solís Rojas')).toBeVisible();

  await page.getByRole('tab', { name: 'Calificaciones' }).click();
  await page.getByLabel('Estudiante matriculado').selectOption({ index: 1 });
  await page.getByLabel('Actividad individual').selectOption({ index: 1 });
  await page.getByLabel('Puntos obtenidos').fill('8,50');
  await page.getByLabel('Observación (opcional)').fill('Buen trabajo');
  await page.getByRole('button', { name: 'Guardar calificación' }).click();
  await expect(page.getByText('Calificación guardada.')).toBeVisible();

  await page.getByRole('tab', { name: 'Asistencia' }).click();
  await page.getByLabel('Fecha').fill('2098-01-08');
  await page.getByLabel('Condición de Ana Solís Rojas').selectOption('presente');
  await page.getByRole('button', { name: 'Crear sesión' }).click();
  await expect(page.getByText('Asistencia guardada.')).toBeVisible();

  await page.getByRole('tab', { name: 'Concentrado' }).click();
  await expect(page.getByText('1,70 % obtenido')).toBeVisible();
  const downloadPromise = page.waitForEvent('download');
  await page.getByRole('button', { name: 'Descargar .xlsx' }).click();
  const download = await downloadPromise;
  expect(download.suggestedFilename()).toContain(courseCode);

  await navigate(page, 'Estudiantes', mobile);
  await page.getByRole('row').filter({ hasText: studentEmail }).getByRole('button', { name: 'Generar contraseña' }).click();
  await expect(page.getByText('Contraseña para Ana Solís Rojas')).toBeVisible();
  const studentPassword = await page.locator('.password-panel code').textContent();
  expect(studentPassword).toBeTruthy();
  await logout(page, mobile);

  await page.getByRole('button', { name: 'Cambiar a tema oscuro' }).click();
  await page.getByLabel('Correo electrónico').fill(studentEmail);
  await page.getByLabel('Contraseña').fill(studentPassword);
  await page.getByRole('button', { name: 'Iniciar sesión' }).click();
  await expect(page.getByRole('heading', { name: 'Mis cursos' })).toBeVisible();
  await page.getByRole('button', { name: /Curso de integración/ }).click();
  await expect(page.getByText('1,70 % obtenido')).toBeVisible();
  await expect(page.getByText('Buen trabajo')).toBeVisible();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await page.screenshot({ path: `test-results/${testInfo.project.name}-student-dark.png`, fullPage: true });
  await page.getByRole('tab', { name: 'Asistencia' }).click();
  await expect(page.getByText('presente')).toBeVisible();
  await page.getByRole('tab', { name: 'Concentrado' }).click();
  await expect(page.getByText('1,70 % obtenido')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Descargar .xlsx' })).toHaveCount(0);
  await navigate(page, 'Perfil', mobile);
  await page.getByLabel('Nueva contraseña').fill('nueva-clave-e2e');
  await page.getByRole('button', { name: 'Cambiar contraseña' }).click();
  await expect(page.getByText('Contraseña actualizada.')).toBeVisible();
  await logout(page, mobile);
  await expect(page.getByRole('button', { name: 'Iniciar sesión' })).toBeVisible();
});
