import { describe, expect, test } from 'vitest';
import { courseLabel, decimal, decimalInput, percent, validDecimal, wordCount } from './format.js';

describe('presentación y validación de datos', () => {
  test('distingue nota ausente de cero', () => {
    expect(decimal(null)).toBe('Sin calificar');
    expect(decimal('0.00')).toBe('0,00');
    expect(percent('1.70')).toBe('1,70 %');
  });
  test('admite coma o punto y rechaza más de dos decimales y valores fuera de rango', () => {
    expect(decimalInput(' 8,50 ')).toBe('8.50');
    expect(validDecimal('8,50', 0, 10)).toBe(true);
    expect(validDecimal('10.01', 0, 10)).toBe(false);
    expect(validDecimal('-1', 0, 10)).toBe(false);
    expect(validDecimal('8.501', 0, 10)).toBe(false);
  });
  test('cuenta palabras y distingue un curso por grupo y cuatrimestre', () => {
    expect(wordCount('  prueba   parcial ')).toBe(2);
    expect(courseLabel({ codigo: 'MAT1', numero_grupo: 2, cuatrimestre_codigo: '1-2026' })).toBe('MAT1 · Grupo 2 · 1-2026');
  });
});
