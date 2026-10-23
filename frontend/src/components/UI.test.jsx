import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ConfirmButton } from './UI.jsx';

describe('confirmación de eliminación', () => {
  it('enfoca Cancelar y permite salir con Escape sin eliminar', () => {
    const onConfirm = vi.fn();
    render(<ConfirmButton question="¿Eliminar el curso?" onConfirm={onConfirm}>Eliminar</ConfirmButton>);
    const trigger = screen.getByRole('button', { name: 'Eliminar' });
    fireEvent.click(trigger);
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Cancelar' }));
    fireEvent.keyDown(document, { key: 'Escape' });
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(document.activeElement).toBe(trigger);
    expect(onConfirm).not.toHaveBeenCalled();
  });
});
