import { test, expect } from '@playwright/test';

test('la sesión sobrevive a una recarga y se cierra al vencer', async ({ page }) => {
  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill('profesor@ucatolica.ac.cr');
  await page.getByLabel('Contraseña').fill(process.env.E2E_PROFESSOR_PASSWORD || 'e2e-fixture-only');
  await page.getByRole('button', { name: 'Iniciar sesión' }).click();
  await expect(page.getByRole('heading', { name: 'Cursos y grupos' })).toBeVisible();
  await page.reload();
  await expect(page.getByRole('heading', { name: 'Cursos y grupos' })).toBeVisible();
  await page.evaluate(() => {
    const session = JSON.parse(localStorage.getItem('snp.session'));
    localStorage.setItem('snp.session', JSON.stringify({ ...session, expiresAt: Date.now() - 1 }));
  });
  await page.reload();
  await expect(page.getByRole('button', { name: 'Iniciar sesión' })).toBeVisible();
});

test('el tema cambia con teclado y se conserva al entrar, salir y recargar', async ({ page }, testInfo) => {
  await page.goto('/');
  const darkButton = page.getByRole('button', { name: 'Cambiar a tema oscuro' });
  await darkButton.focus();
  await page.keyboard.press('Enter');
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await page.reload();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');

  await page.getByLabel('Correo electrónico').fill('profesor@ucatolica.ac.cr');
  await page.getByLabel('Contraseña').fill(process.env.E2E_PROFESSOR_PASSWORD || 'e2e-fixture-only');
  await page.getByRole('button', { name: 'Iniciar sesión' }).click();
  await expect(page.getByRole('heading', { name: 'Cursos y grupos' })).toBeVisible();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await page.screenshot({ path: `test-results/${testInfo.project.name}-professor-dark.png`, fullPage: true });
  if (testInfo.project.name === 'mobile') await page.getByRole('button', { name: 'Abrir menú' }).click();
  await page.getByRole('button', { name: 'Cerrar sesión' }).click();
  await expect(page.getByRole('button', { name: 'Cambiar a tema claro' })).toBeVisible();
  await page.getByRole('button', { name: 'Cambiar a tema claro' }).click();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
});
