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
    const form = {
        data: {
            room_id: '',
            responsible: '',
            title: '',
            date: '',
            start_time: '',
            end_time: '',
            participants: '',
            ...overrides.data,
        },
        errors: overrides.errors ?? {},
        processing: overrides.processing ?? false,
        setData: vi.fn(),
        post: vi.fn(),
        transform: vi.fn(),
        ...overrides,
    };

    form.setData.mockImplementation((key, value) => {
        form.data[key] = value;
    });

    form.transform.mockImplementation((callback) => {
        form.lastTransform = callback;

        return form;
    });

    return form;
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

function renderCreate(overrides = {}, pageRooms = rooms) {
    const form = createForm(overrides);
    useForm.mockReturnValue(form);

    return { form, ...render(<Create rooms={pageRooms} />) };
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
    it('shows documented fields, combines date and times, and posts Criar reserva', async () => {
        const user = userEvent.setup();
        const { form } = renderCreate({
            data: {
                room_id: 'room-1',
                responsible: 'Ada',
                title: 'Daily',
                date: '2026-09-21',
                start_time: '10:00',
                end_time: '10:30',
                participants: 2,
            },
        });

        expect(screen.getByRole('heading', { name: 'Nova reserva' })).toBeInTheDocument();
        expect(screen.getByText('Preencha os dados da reserva.')).toBeInTheDocument();
        expect(screen.getByLabelText(/Sala/)).toHaveAttribute('aria-required', 'true');
        expect(screen.getByLabelText(/Responsável/)).toHaveAttribute('aria-required', 'true');
        expect(screen.getByLabelText(/Título \/ finalidade/)).toHaveAttribute('aria-required', 'true');
        expect(screen.getByLabelText(/^Data/)).toHaveAttribute('aria-required', 'true');
        expect(screen.getByLabelText(/Horário de início/)).toHaveAttribute('aria-required', 'true');
        expect(screen.getByLabelText(/Horário de término/)).toHaveAttribute('aria-required', 'true');
        expect(screen.getByLabelText(/Participantes/)).toHaveAttribute('aria-required', 'true');
        expect(screen.getByRole('option', { name: 'Sala Azul — capacidade para 8 pessoas' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Cancelar' })).toHaveAttribute('href', '/reservations');
        expect(screen.getByRole('button', { name: 'Criar reserva' })).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Criar reserva' }));

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(form.post).toHaveBeenCalledWith('/reservations', expect.any(Object));
        expect(form.lastTransform(form.data)).toEqual({
            room_id: 'room-1',
            responsible: 'Ada',
            title: 'Daily',
            starts_at: '2026-09-21 10:00:00',
            ends_at: '2026-09-21 10:30:00',
            participants: 2,
        });
    });

    it('blocks a participants value above the selected room capacity', async () => {
        const user = userEvent.setup();
        const { form } = renderCreate({ data: { room_id: 'room-1' } });

        fireEvent.change(screen.getByLabelText(/Participantes/), { target: { value: '8' } });
        expect(form.setData).toHaveBeenCalledWith('participants', '8');

        form.setData.mockClear();
        fireEvent.change(screen.getByLabelText(/Participantes/), { target: { value: '9' } });
        expect(form.setData).not.toHaveBeenCalled();
        expect(screen.getByLabelText(/Participantes/)).toHaveAttribute('max', '8');
        expect(screen.getByLabelText(/Participantes/)).toHaveAttribute('min', '1');

        await user.selectOptions(screen.getByLabelText(/Sala/), 'room-1');
        expect(form.setData).toHaveBeenCalledWith('room_id', 'room-1');
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

        await user.click(screen.getByRole('button', { name: 'Criar reserva' }));

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

    it('shows Criando reserva... and disables actions while processing', async () => {
        const user = userEvent.setup();
        const { form } = renderCreate({ processing: true });

        const submit = screen.getByRole('button', { name: 'Criando reserva...' });
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

        await user.click(screen.getByRole('button', { name: 'Criar reserva' }));

        expect(screen.getByText('Não foi possível salvar a reserva. Tente novamente.')).toHaveAttribute(
            'aria-live',
            'polite',
        );
    });

    it('shows the empty active-rooms state when no room can be reserved', () => {
        renderCreate({}, []);

        expect(screen.getByText('Nenhuma sala ativa está disponível.')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Nova sala' })).toHaveAttribute('href', '/rooms/create');
        expect(screen.queryByRole('button', { name: 'Criar reserva' })).not.toBeInTheDocument();
    });
});
