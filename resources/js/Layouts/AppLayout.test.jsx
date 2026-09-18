import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import '@testing-library/jest-dom/vitest';
import AppLayout from './AppLayout';

afterEach(() => {
    cleanup();
});

describe('AppLayout admin shell', () => {
    it('renders children inside a padded full-viewport shell without horizontal overflow', () => {
        const { container } = render(
            <AppLayout>
                <p>Child content</p>
            </AppLayout>,
        );

        expect(screen.getByText('Child content')).toBeInTheDocument();

        const shell = container.firstChild;
        const main = container.querySelector('main');

        expect(shell.className).toMatch(/min-h-screen/);
        expect(shell.className).toMatch(/overflow-x-hidden/);
        expect(main.className).toMatch(/max-w-/);
        expect(main.className).toMatch(/\bpx-/);
        expect(main.contains(screen.getByText('Child content'))).toBe(true);
    });

    it('renders an optional title heading', () => {
        render(
            <AppLayout title="Reservas">
                <p>Body</p>
            </AppLayout>,
        );

        expect(screen.getByRole('heading', { name: 'Reservas' })).toBeInTheDocument();
        expect(screen.getByText('Body')).toBeInTheDocument();
    });

    it('still renders children when title is omitted', () => {
        render(
            <AppLayout>
                <p>Only child</p>
            </AppLayout>,
        );

        expect(screen.queryByRole('heading')).not.toBeInTheDocument();
        expect(screen.getByText('Only child')).toBeInTheDocument();
    });
});
