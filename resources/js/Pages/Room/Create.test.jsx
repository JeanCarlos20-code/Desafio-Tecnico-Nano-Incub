import { createElement } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useForm, usePage } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import '@testing-library/jest-dom/vitest';
import Create from './Create';

vi.mock('@inertiajs/react', () => ({
    useForm: vi.fn(),
    usePage: vi.fn(),
    Link: ({ href, children, className, ...props }) => createElement('a', { href, className, ...props }, children),
}));

function createForm(overrides = {}) {
    return {
        data: {
            name: '',
            capacity: '',
            is_active: true,
            ...overrides.data,
        },
        errors: overrides.errors ?? {},
        processing: overrides.processing ?? false,
        setData: vi.fn(),
        post: vi.fn(),
        ...overrides,
    };
}

function mockPage() {
    usePage.mockReturnValue({
        url: '/rooms/create',
        props: {
            auth: { user: { name: 'Ada Lovelace' } },
            flash: { success: null, error: null },
        },
    });
}

afterEach(() => {
    cleanup();
});

beforeEach(() => {
    useForm.mockReset();
    usePage.mockReset();
    mockPage();
});

describe('Room/Create', () => {
    it('submits only name, capacity, and is_active to /rooms', async () => {
        const user = userEvent.setup();
        const form = createForm({
            data: {
                name: 'Sala Azul',
                capacity: 10,
                is_active: true,
            },
        });
        useForm.mockReturnValue(form);

        render(<Create />);

        expect(screen.getByLabelText('Nome')).toBeInTheDocument();
        expect(screen.getByLabelText('Capacidade')).toBeInTheDocument();
        expect(screen.getByLabelText('Situação')).toBeInTheDocument();
        expect(screen.queryByLabelText(/local/i)).not.toBeInTheDocument();
        expect(Object.keys(form.data).sort()).toEqual(['capacity', 'is_active', 'name']);

        await user.click(screen.getByRole('button', { name: 'Cadastrar sala' }));

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(form.post).toHaveBeenCalledWith('/rooms', expect.any(Object));
    });

    it('shows backend field errors', () => {
        useForm.mockReturnValue(
            createForm({
                errors: {
                    name: 'Informe o nome da sala.',
                    capacity: 'Informe a capacidade da sala.',
                },
            }),
        );

        render(<Create />);

        expect(screen.getByText('Informe o nome da sala.')).toBeInTheDocument();
        expect(screen.getByText('Informe a capacidade da sala.')).toBeInTheDocument();
        expect(screen.getByLabelText('Nome')).toHaveAttribute('aria-invalid', 'true');
    });
});
