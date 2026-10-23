import { describe, expect, it } from 'vitest';
import { applyTheme, readTheme } from './theme.js';

describe('preferencia visual local', () => {
  it('usa claro sin elección previa y conserva oscuro para la siguiente carga', () => {
    const values = new Map();
    const storage = { getItem: (key) => values.get(key), setItem: (key, value) => values.set(key, value) };
    const root = { dataset: {} };
    expect(readTheme(storage)).toBe('light');
    applyTheme('dark', root, storage);
    expect(root.dataset.theme).toBe('dark');
    expect(readTheme(storage)).toBe('dark');
  });
});
