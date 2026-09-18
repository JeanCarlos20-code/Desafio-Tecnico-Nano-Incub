import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import '@testing-library/jest-dom/vitest';
import Index from './Index';

afterEach(() => {
    cleanup();
});

describe('Reservation/Index stub', () => {
    it('renders the post-register placeholder heading', () => {
        render(<Index />);

        expect(screen.getByRole('heading', { name: 'Reservas' })).toBeInTheDocument();
    });

    it('wraps the stub heading in the AppLayout viewport shell', () => {
        render(<Index />);

        const heading = screen.getByRole('heading', { name: 'Reservas' });
        const shell = heading.closest('[class*="min-h-screen"]');

        expect(shell).toBeTruthy();
        expect(shell.className).toMatch(/min-h-screen/);
        expect(shell.className).toMatch(/overflow-x-hidden/);
        expect(shell.contains(heading)).toBe(true);
    });
});
