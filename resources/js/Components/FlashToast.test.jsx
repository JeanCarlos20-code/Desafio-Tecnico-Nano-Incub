import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import '@testing-library/jest-dom/vitest';
import FlashToast, { FLASH_TOAST_DURATION_MS } from './FlashToast';

afterEach(() => {
    cleanup();
    vi.useRealTimers();
});

describe('FlashToast', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    it('shows the flash message in a bottom-right bluish popup with a live region', () => {
        render(<FlashToast message="Sala atualizada com sucesso." />);

        const toast = screen.getByRole('status');

        expect(toast).toHaveTextContent('Sala atualizada com sucesso.');
        expect(toast).toHaveAttribute('aria-live', 'polite');
        expect(toast.className).toMatch(/\bfixed\b/);
        expect(toast.className).toMatch(/bottom-4/);
        expect(toast.className).not.toMatch(/top-4/);
        expect(toast.className).toMatch(/right-4/);
        expect(toast.className).toMatch(/bg-blue-600/);
        expect(toast.className).toMatch(/text-white/);
        expect(toast.className).toMatch(/font-semibold/);
    });

    it('auto-dismisses after a few seconds', () => {
        render(<FlashToast message="Sala atualizada com sucesso." />);

        expect(screen.getByText('Sala atualizada com sucesso.')).toBeInTheDocument();

        act(() => {
            vi.advanceTimersByTime(FLASH_TOAST_DURATION_MS - 1);
        });

        expect(screen.getByText('Sala atualizada com sucesso.')).toBeInTheDocument();

        act(() => {
            vi.advanceTimersByTime(1);
        });

        expect(screen.queryByText('Sala atualizada com sucesso.')).not.toBeInTheDocument();
        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });

    it('dismisses immediately when the close control is used', () => {
        render(<FlashToast message="Sala atualizada com sucesso." />);

        fireEvent.click(screen.getByRole('button', { name: 'Fechar' }));

        expect(screen.queryByText('Sala atualizada com sucesso.')).not.toBeInTheDocument();
        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });

    it('renders nothing when there is no flash message', () => {
        render(<FlashToast message={null} />);

        expect(screen.queryByRole('status')).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Fechar' })).not.toBeInTheDocument();
    });
});
