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

function renderCreate(overrides = {}, pageRooms = rooms, timezone) {
    const form = createForm(overrides);
    useForm.mockReturnValue(form);
    const props = timezone === undefined ? { rooms: pageRooms } : { rooms: pageRooms, timezone };

    return { form, ...render(<Create {...props} />) };
}

afterEach(() => {
    cleanup();
    vi.useRealTimers();
});

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-09-21T08:00:00.000Z'));
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

    it('sets date min to today and start-time min to current HH:MM while the selected date is today', () => {
        renderCreate({ data: { date: '2026-09-21' } }, rooms, 'UTC');

        expect(screen.getByLabelText(/^Data/)).toHaveAttribute('min', '2026-09-21');
        expect(screen.getByLabelText(/Horário de início/)).toHaveAttribute('min', '08:00');
    });

    it('omits start-time min when the selected date is after today', () => {
        renderCreate({ data: { date: '2026-09-22' } });

        expect(screen.getByLabelText(/^Data/)).toHaveAttribute('min', '2026-09-21');
        expect(screen.getByLabelText(/Horário de início/)).not.toHaveAttribute('min');
    });

    it('shows A data não pode estar no passado. and does not call form.post for a past start', async () => {
        const user = userEvent.setup();
        const pastDate = renderCreate({
            data: {
                room_id: 'room-1',
                responsible: 'Ada',
                title: 'Daily',
                date: '2026-09-20',
                start_time: '10:00',
                end_time: '10:30',
                participants: 2,
            },
        });

        await user.click(screen.getByRole('button', { name: 'Criar reserva' }));

        expect(screen.getAllByText('A data não pode estar no passado.')).not.toHaveLength(0);
        expect(pastDate.form.post).not.toHaveBeenCalled();
        expect(pastDate.form.transform).not.toHaveBeenCalled();

        cleanup();

        const pastTime = renderCreate({
            data: {
                room_id: 'room-1',
                responsible: 'Ada',
                title: 'Daily',
                date: '2026-09-21',
                start_time: '07:00',
                end_time: '07:30',
                participants: 2,
            },
        });

        await user.click(screen.getByRole('button', { name: 'Criar reserva' }));

        expect(screen.getAllByText('A data não pode estar no passado.')).not.toHaveLength(0);
        expect(pastTime.form.post).not.toHaveBeenCalled();
        expect(pastTime.form.transform).not.toHaveBeenCalled();
    });

    it('shows O término deve ser posterior ao início. and does not call form.post when end is not after start', async () => {
        const user = userEvent.setup();
        const inverted = renderCreate({
            data: {
                room_id: 'room-1',
                responsible: 'Ada',
                title: 'Daily',
                date: '2026-09-21',
                start_time: '10:00',
                end_time: '09:00',
                participants: 2,
            },
        });

        expect(screen.getByLabelText(/Horário de término/)).toHaveAttribute('min', '10:00');

        await user.click(screen.getByRole('button', { name: 'Criar reserva' }));

        expect(screen.getByText('O término deve ser posterior ao início.')).toBeInTheDocument();
        expect(inverted.form.post).not.toHaveBeenCalled();
        expect(inverted.form.transform).not.toHaveBeenCalled();

        cleanup();

        const equal = renderCreate({
            data: {
                room_id: 'room-1',
                responsible: 'Ada',
                title: 'Daily',
                date: '2026-09-21',
                start_time: '10:00',
                end_time: '10:00',
                participants: 2,
            },
        });

        await user.click(screen.getByRole('button', { name: 'Criar reserva' }));

        expect(screen.getByText('O término deve ser posterior ao início.')).toBeInTheDocument();
        expect(equal.form.post).not.toHaveBeenCalled();
        expect(equal.form.transform).not.toHaveBeenCalled();
    });

    it('still posts the existing starts_at transform when today start equals now', async () => {
        const user = userEvent.setup();
        const { form } = renderCreate({
            data: {
                room_id: 'room-1',
                responsible: 'Ada',
                title: 'Daily',
                date: '2026-09-21',
                start_time: '08:00',
                end_time: '08:30',
                participants: 2,
            },
        });

        await user.click(screen.getByRole('button', { name: 'Criar reserva' }));

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(form.post).toHaveBeenCalledWith('/reservations', expect.any(Object));
        expect(form.lastTransform(form.data)).toEqual({
            room_id: 'room-1',
            responsible: 'Ada',
            title: 'Daily',
            starts_at: '2026-09-21 08:00:00',
            ends_at: '2026-09-21 08:30:00',
            participants: 2,
        });
    });
});
