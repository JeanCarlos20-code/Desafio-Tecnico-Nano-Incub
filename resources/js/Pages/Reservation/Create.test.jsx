import { createElement } from 'react';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
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

const rooms = [{ id: 'room-1', name: 'Sala Azul', capacity: 8 }];

function createForm(overrides = {}) {
    return {
        data: {
            room_id: '',
            responsible: '',
            title: '',
            starts_at: '',
            ends_at: '',
            participants: '',
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
        url: '/reservations/create',
        props: {
            auth: { user: { name: 'Ada Lovelace' } },
            flash: { success: null, error: null },
        },
    });
}

function renderCreate(overrides = {}) {
    const form = createForm(overrides);
    useForm.mockReturnValue(form);

    return { form, ...render(<Create rooms={rooms} />) };
}

afterEach(() => {
    cleanup();
});

beforeEach(() => {
    useForm.mockReset();
    usePage.mockReset();
    mockPage();
});

describe('Reservation/Create', () => {
    it('shows required fields, posts to /reservations, shows errors, Salvando..., and Cancelar to /reservations', async () => {
        const user = userEvent.setup();
        const { form } = renderCreate({
            data: {
                room_id: 'room-1',
                responsible: 'Ada',
                title: 'Daily',
                starts_at: '2026-09-21T10:00',
                ends_at: '2026-09-21T10:30',
                participants: 2,
            },
        });

        expect(screen.getByRole('heading', { name: 'Nova reserva' })).toBeInTheDocument();
        expect(screen.getByLabelText(/Sala/)).toHaveAttribute('aria-required', 'true');
        expect(screen.getByLabelText(/Responsável/)).toHaveAttribute('aria-required', 'true');
        expect(screen.getByLabelText(/Título/)).toHaveAttribute('aria-required', 'true');
        expect(screen.getByLabelText(/Início/)).toHaveAttribute('aria-required', 'true');
        expect(screen.getByLabelText(/Fim/)).toHaveAttribute('aria-required', 'true');
        expect(screen.getByLabelText(/Participantes/)).toHaveAttribute('aria-required', 'true');
        expect(screen.getByRole('link', { name: 'Cancelar' })).toHaveAttribute('href', '/reservations');

        await user.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(form.post).toHaveBeenCalledWith('/reservations', expect.any(Object));
    });

    it('writes sala, responsável, título, início, fim, and participantes into the form', async () => {
        const user = userEvent.setup();
        const { form } = renderCreate();

        await user.selectOptions(screen.getByLabelText(/Sala/), 'room-1');
        fireEvent.change(screen.getByLabelText(/Responsável/), { target: { value: 'Ada' } });
        fireEvent.change(screen.getByLabelText(/Título/), { target: { value: 'Daily' } });
        fireEvent.change(screen.getByLabelText(/Início/), { target: { value: '2026-09-21T10:00' } });
        fireEvent.change(screen.getByLabelText(/Fim/), { target: { value: '2026-09-21T10:30' } });
        fireEvent.change(screen.getByLabelText(/Participantes/), { target: { value: '2' } });

        expect(form.setData).toHaveBeenCalledWith('room_id', 'room-1');
        expect(form.setData).toHaveBeenCalledWith('responsible', 'Ada');
        expect(form.setData).toHaveBeenCalledWith('title', 'Daily');
        expect(form.setData).toHaveBeenCalledWith('starts_at', '2026-09-21T10:00');
        expect(form.setData).toHaveBeenCalledWith('ends_at', '2026-09-21T10:30');
        expect(form.setData).toHaveBeenCalledWith('participants', '2');
    });

    it('shows backend field errors and focuses the first invalid field', async () => {
        const user = userEvent.setup();
        const { form, rerender } = renderCreate();

        form.post.mockImplementation((_url, options) => {
            options.onError({
                room_id: 'Informe a sala.',
                responsible: 'Informe o responsável.',
            });
        });

        await user.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(screen.getByLabelText(/Sala/)).toHaveFocus();

        form.errors = {
            room_id: 'Informe a sala.',
            responsible: 'Informe o responsável.',
        };
        useForm.mockReturnValue(form);
        rerender(<Create rooms={rooms} />);

        expect(screen.getByText('Informe a sala.')).toHaveAttribute('id', 'room_id-error');
        expect(screen.getByLabelText(/Sala/)).toHaveAttribute('aria-invalid', 'true');
    });

    it('shows Salvando... and disables actions while processing', async () => {
        const user = userEvent.setup();
        const { form } = renderCreate({ processing: true });

        const submit = screen.getByRole('button', { name: 'Salvando...' });
        const cancel = screen.getByRole('button', { name: 'Cancelar' });

        expect(submit).toBeDisabled();
        expect(cancel).toBeDisabled();
        expect(screen.queryByRole('link', { name: 'Cancelar' })).not.toBeInTheDocument();

        await user.click(submit);

        expect(form.post).not.toHaveBeenCalled();
    });

    it('shows unexpected save failure banner', async () => {
        const user = userEvent.setup();
        const { form } = renderCreate();

        form.post.mockImplementation((_url, options) => {
            options.onHttpException();
        });

        await user.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(screen.getByText('Não foi possível salvar a reserva. Tente novamente.')).toHaveAttribute(
            'aria-live',
            'polite',
        );
    });
});
