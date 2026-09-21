import { createElement } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useForm, usePage } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import '@testing-library/jest-dom/vitest';
import Edit from './Edit';

vi.mock('@inertiajs/react', () => ({
    useForm: vi.fn(),
    usePage: vi.fn(),
    router: {
        on: vi.fn(() => vi.fn()),
    },
    Link: ({ href, children, className, ...props }) => createElement('a', { href, className, ...props }, children),
}));

const reservation = {
    id: '1',
    title: 'Daily',
    responsible: 'Ada Lovelace',
    room_id: '2',
    room_name: 'Sala Azul',
    date: '2026-09-21',
    start_time: '10:00',
    end_time: '10:30',
    participants: 4,
};

function createForm(overrides = {}) {
    const form = {
        data: {
            title: reservation.title,
            responsible: reservation.responsible,
            ...overrides.data,
        },
        errors: overrides.errors ?? {},
        processing: overrides.processing ?? false,
        setData: vi.fn(),
        put: vi.fn(),
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
        url: '/reservations/1/edit',
        props: {
            auth: { user: { name: 'Ada Lovelace' } },
            flash: { success: null, error: null },
        },
    });
}

function renderEdit(overrides = {}) {
    const form = createForm(overrides);
    useForm.mockReturnValue(form);

    return { form, ...render(<Edit {...reservation} />) };
}

afterEach(() => {
    cleanup();
});

beforeEach(() => {
    useForm.mockReset();
    usePage.mockReset();
    mockPage();
});

describe('Reservation/Edit', () => {
    it('exposes title and responsible and keeps occupancy disabled and out of the PUT body', async () => {
        const user = userEvent.setup();
        const { form } = renderEdit();

        expect(screen.getByRole('heading', { name: 'Editar reserva' })).toBeInTheDocument();
        expect(screen.getByLabelText(/Título \/ finalidade/)).toBeEnabled();
        expect(screen.getByLabelText(/Título \/ finalidade/)).not.toBeDisabled();
        expect(screen.getByLabelText(/Responsável/)).toBeEnabled();
        expect(screen.getByLabelText(/^Sala/)).toBeDisabled();
        expect(screen.getByLabelText(/^Data/)).toBeDisabled();
        expect(screen.getByLabelText(/Horário de início/)).toBeDisabled();
        expect(screen.getByLabelText(/Horário de término/)).toBeDisabled();
        expect(screen.getByLabelText(/Participantes/)).toBeDisabled();
        expect(screen.getByLabelText(/^Sala/)).toHaveValue('Sala Azul');
        expect(screen.getByLabelText(/^Data/)).toHaveValue('2026-09-21');
        expect(screen.getByLabelText(/^Data/)).toHaveAttribute('lang', 'pt-BR');
        expect(screen.getByLabelText(/Horário de início/)).toHaveValue('10:00');
        expect(screen.getByLabelText(/Horário de início/)).toHaveAttribute('lang', 'pt-BR');
        expect(screen.getByLabelText(/Horário de término/)).toHaveValue('10:30');
        expect(screen.getByLabelText(/Horário de término/)).toHaveAttribute('lang', 'pt-BR');
        expect(screen.getByLabelText(/Participantes/)).toHaveValue(4);

        await user.click(screen.getByRole('button', { name: 'Salvar alterações' }));

        expect(form.put).toHaveBeenCalledTimes(1);
        expect(form.put).toHaveBeenCalledWith('/reservations/1', expect.any(Object));
        expect(form.lastTransform(form.data)).toEqual({
            title: 'Daily',
            responsible: 'Ada Lovelace',
        });
        expect(form.lastTransform(form.data)).not.toHaveProperty('starts_at');
        expect(form.lastTransform(form.data)).not.toHaveProperty('ends_at');
        expect(form.lastTransform(form.data)).not.toHaveProperty('room_id');
        expect(form.lastTransform(form.data)).not.toHaveProperty('participants');
        expect(form.lastTransform(form.data)).not.toHaveProperty('cancelled_at');
        expect(form.lastTransform(form.data)).not.toHaveProperty('date');
        expect(form.lastTransform(form.data)).not.toHaveProperty('start_time');
        expect(form.lastTransform(form.data)).not.toHaveProperty('end_time');
    });
});
