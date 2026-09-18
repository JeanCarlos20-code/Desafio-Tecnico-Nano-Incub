import { createElement } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useForm, usePage } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import '@testing-library/jest-dom/vitest';
import AppLayout from './AppLayout';

vi.mock('@inertiajs/react', () => ({
    useForm: vi.fn(),
    usePage: vi.fn(),
    Link: ({ href, children, className, ...props }) => createElement('a', { href, className, ...props }, children),
}));

function createForm(overrides = {}) {
    return {
        processing: overrides.processing ?? false,
        post: vi.fn(),
        ...overrides,
    };
}

function mockPage({ url = '/rooms', name = 'Ada Lovelace', flash = { success: null, error: null } } = {}) {
    usePage.mockReturnValue({
        url,
        props: {
            auth: { user: { name } },
            flash,
        },
    });
}

afterEach(() => {
    cleanup();
});

beforeEach(() => {
    useForm.mockReset();
    usePage.mockReset();
    useForm.mockReturnValue(createForm());
    mockPage();
});

function classTokens(element) {
    return String(element?.className ?? '').split(/\s+/);
}

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

        expect(classTokens(shell)).toEqual(expect.arrayContaining(['h-screen', 'min-h-screen']));
        expect(shell.className).toMatch(/overflow-x-hidden/);
        expect(main.className).toMatch(/\bpx-/);
        expect(classTokens(main)).toEqual(expect.arrayContaining(['overflow-y-auto']));
        expect(main.contains(screen.getByText('Child content'))).toBe(true);
    });

    it('keeps the left nav at full viewport height independent of children height', () => {
        const { container, unmount } = render(
            <AppLayout>
                <p>Short stub</p>
            </AppLayout>,
        );

        const shortShell = container.firstChild;
        const shortAside = container.querySelector('aside');
        const shortNavClasses = shortAside.className;

        expect(classTokens(shortShell)).toEqual(expect.arrayContaining(['h-screen', 'min-h-screen']));
        expect(classTokens(shortAside)).toEqual(expect.arrayContaining(['h-full']));
        expect(screen.getByText('ReservaSalas')).toBeInTheDocument();
        expect(classTokens(screen.getByRole('link', { name: 'Reservas' }))).toEqual(
            expect.arrayContaining(['w-full']),
        );
        expect(classTokens(screen.getByRole('link', { name: 'Salas' }))).toEqual(
            expect.arrayContaining(['w-full']),
        );

        unmount();

        const tall = render(
            <AppLayout>
                <div>
                    {Array.from({ length: 40 }, (_, index) => (
                        <p key={index}>Tall row {index}</p>
                    ))}
                </div>
            </AppLayout>,
        );

        const tallAside = tall.container.querySelector('aside');

        expect(classTokens(tall.container.firstChild)).toEqual(
            expect.arrayContaining(['h-screen', 'min-h-screen']),
        );
        expect(tallAside.className).toBe(shortNavClasses);
        expect(classTokens(tallAside)).toEqual(expect.arrayContaining(['h-full']));
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

    it('marks Salas with aria-current on /rooms, shows the shared user name, and posts logout from the account menu', async () => {
        const user = userEvent.setup();
        const form = createForm();
        useForm.mockReturnValue(form);
        mockPage({ url: '/rooms/create', name: 'Ada Lovelace' });

        render(
            <AppLayout>
                <p>Child content</p>
            </AppLayout>,
        );

        const salas = screen.getByRole('link', { name: 'Salas' });
        expect(salas).toHaveAttribute('href', '/rooms');
        expect(salas).toHaveAttribute('aria-current', 'page');
        expect(screen.getByRole('link', { name: 'Reservas' })).toHaveAttribute('href', '/reservations');
        expect(screen.getByRole('link', { name: 'Reservas' })).not.toHaveAttribute('aria-current');
        expect(screen.getByText('Ada Lovelace')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: /Ada Lovelace/ }));
        await user.click(screen.getByRole('menuitem', { name: 'Sair' }));

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(form.post).toHaveBeenCalledWith('/logout', expect.any(Object));
    });
});
