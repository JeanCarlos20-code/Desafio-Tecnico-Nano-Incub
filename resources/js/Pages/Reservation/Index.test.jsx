import { createElement } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import '@testing-library/jest-dom/vitest';
import Index from './Index';

vi.mock('@inertiajs/react', () => ({
    useForm: () => ({
        processing: false,
        post: () => {},
    }),
    usePage: () => ({
        url: '/reservations',
        props: {
            auth: { user: { name: 'Ada Lovelace' } },
            flash: { success: null, error: null },
        },
    }),
    Link: ({ href, children, className, ...props }) => createElement('a', { href, className, ...props }, children),
}));

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
