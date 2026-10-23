import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import ThemeToggle from './ThemeToggle.jsx';

describe('control de tema', () => {
  it('indica el tema de destino y ejecuta el cambio solicitado', () => {
    const onToggle = vi.fn();
    const { rerender } = render(<ThemeToggle theme="light" onToggle={onToggle} />);
    const button = screen.getByRole('button', { name: 'Cambiar a tema oscuro' });
    expect(button.getAttribute('aria-pressed')).toBe('false');
    fireEvent.click(button);
    expect(onToggle).toHaveBeenCalledOnce();
    rerender(<ThemeToggle theme="dark" onToggle={onToggle} />);
    expect(screen.getByRole('button', { name: 'Cambiar a tema claro' }).getAttribute('aria-pressed')).toBe('true');
  });
});
