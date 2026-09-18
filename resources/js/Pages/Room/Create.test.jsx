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

function renderCreate(overrides = {}) {
    const form = createForm(overrides);
    useForm.mockReturnValue(form);

    return { form, ...render(<Create />) };
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
    it('shows Nova sala copy, only Nome and Capacidade with required asterisks, and no Status field', () => {
        renderCreate();

        expect(screen.getByRole('heading', { name: 'Nova sala' })).toBeInTheDocument();
        expect(screen.getByText('Preencha as informações da sala de reunião.')).toBeInTheDocument();

        const name = screen.getByLabelText(/Nome/);
        const capacity = screen.getByLabelText(/Capacidade/);

        expect(name).toHaveAttribute('aria-required', 'true');
        expect(capacity).toHaveAttribute('aria-required', 'true');
        expect(capacity).toHaveAttribute('inputmode', 'numeric');
        expect(name).toHaveValue('');
        expect(capacity).toHaveValue(null);

        expect(screen.getAllByText('*')).toHaveLength(2);
        expect(screen.queryByLabelText('Situação')).not.toBeInTheDocument();
        expect(screen.queryByText('Status')).not.toBeInTheDocument();
        expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
        expect(useForm).toHaveBeenCalledWith({ name: '', capacity: '' });
    });

    it('posts only name and capacity to /rooms and does not send is_active', async () => {
        const user = userEvent.setup();
        const { form } = renderCreate({
            data: {
                name: 'Sala Azul',
                capacity: 10,
            },
        });

        expect(Object.keys(form.data).sort()).toEqual(['capacity', 'name']);

        await user.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(form.post).toHaveBeenCalledWith('/rooms', expect.any(Object));
        expect(form.data).not.toHaveProperty('is_active');
    });

    it('shows backend field errors with aria-invalid and focuses the first invalid field', async () => {
        const user = userEvent.setup();
        const { form, rerender } = renderCreate({
            data: {
                name: 'Sala Azul',
                capacity: 10,
            },
        });

        form.post.mockImplementation((_url, options) => {
            options.onError({
                name: 'Informe o nome da sala.',
                capacity: 'Informe a capacidade da sala.',
            });
        });

        await user.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(screen.getByLabelText(/Nome/)).toHaveFocus();

        form.errors = {
            name: 'Informe o nome da sala.',
            capacity: 'Informe a capacidade da sala.',
        };
        useForm.mockReturnValue(form);
        rerender(<Create />);

        expect(screen.getByText('Informe o nome da sala.')).toHaveAttribute('id', 'name-error');
        expect(screen.getByText('Informe a capacidade da sala.')).toHaveAttribute('id', 'capacity-error');
        expect(screen.getByLabelText(/Nome/)).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByLabelText(/Nome/)).toHaveAttribute('aria-describedby', 'name-error');
        expect(screen.getByLabelText(/Capacidade/)).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByLabelText(/Capacidade/)).toHaveAttribute('aria-describedby', 'capacity-error');
        expect(screen.getByLabelText(/Nome/)).toHaveValue('Sala Azul');
        expect(screen.getByLabelText(/Capacidade/)).toHaveValue(10);
    });

    it('shows Salvando... and disables Salvar and Cancelar while processing', async () => {
        const user = userEvent.setup();
        const { form } = renderCreate({ processing: true });

        const submit = screen.getByRole('button', { name: 'Salvando...' });
        const cancel = screen.getByRole('button', { name: 'Cancelar' });

        expect(submit).toBeDisabled();
        expect(cancel).toBeDisabled();
        expect(screen.queryByRole('button', { name: 'Salvar' })).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Cancelar' })).not.toBeInTheDocument();

        await user.click(submit);

        expect(form.post).not.toHaveBeenCalled();
    });

    it('goes to /rooms from Cancelar without posting', () => {
        const { form } = renderCreate();

        expect(screen.getByRole('link', { name: 'Cancelar' })).toHaveAttribute('href', '/rooms');
        expect(form.post).not.toHaveBeenCalled();
    });

    it('shows Não foi possível salvar a sala. Tente novamente. on unexpected failure', async () => {
        const user = userEvent.setup();
        const { form } = renderCreate({
            data: {
                name: 'Sala Azul',
                capacity: 10,
            },
        });

        let httpExceptionResult;

        form.post.mockImplementation((_url, options) => {
            httpExceptionResult = options.onHttpException();
        });

        await user.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(httpExceptionResult).toBe(false);

        const banner = screen.getByText('Não foi possível salvar a sala. Tente novamente.');
        expect(banner).toHaveAttribute('aria-live', 'polite');
        expect(screen.getByLabelText(/Nome/)).toHaveValue('Sala Azul');
        expect(screen.getByLabelText(/Capacidade/)).toHaveValue(10);
    });
});
