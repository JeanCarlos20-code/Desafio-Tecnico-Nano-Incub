import { createElement } from 'react';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router, useForm, usePage } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import '@testing-library/jest-dom/vitest';
import Index from './Index';

vi.mock('@inertiajs/react', () => ({
    useForm: vi.fn(),
    usePage: vi.fn(),
    router: {
        get: vi.fn(),
    },
    Link: ({ href, children, className, ...props }) => createElement('a', { href, className, ...props }, children),
}));

const sampleReservations = {
    data: [
        {
            id: 'res-1',
            room_id: 'room-1',
            room_name: 'Sala Azul',
            responsible: 'Ada Lovelace',
            title: 'Daily',
            date: '21/09/2026',
            starts_at: '09:00',
            ends_at: '09:30',
            participants: 4,
            status: 'active',
            status_label: 'Ativa',
        },
        {
            id: 'res-2',
            room_id: 'room-1',
            room_name: 'Sala Azul',
            responsible: 'Grace Hopper',
            title: 'Planning',
            date: '21/09/2026',
            starts_at: '10:00',
            ends_at: '10:30',
            participants: 3,
            status: 'active',
            status_label: 'Ativa',
        },
    ],
    total: 2,
    current_page: 1,
    last_page: 1,
    per_page: 15,
};

const defaultFilters = { room_id: '', period: 'all', starts_on: '', ends_on: '' };
const filterRooms = [{ id: 'room-1', name: 'Sala Azul' }];

function createForm(overrides = {}) {
    return {
        processing: overrides.processing ?? false,
        patch: vi.fn(),
        ...overrides,
    };
}

function mockPage() {
    usePage.mockReturnValue({
        url: '/reservations',
        props: {
            auth: { user: { name: 'Ada Lovelace' } },
            flash: { success: null, error: null },
        },
    });
}

function renderIndex(overrides = {}) {
    return render(
        <Index
            reservations={overrides.reservations ?? sampleReservations}
            filters={overrides.filters ?? defaultFilters}
            filterRooms={overrides.filterRooms ?? filterRooms}
            hasAny={overrides.hasAny ?? true}
            loadError={overrides.loadError}
            loading={overrides.loading}
        />,
    );
}

afterEach(() => {
    cleanup();
    vi.useRealTimers();
});

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date(2026, 8, 21, 12, 0, 0));
    useForm.mockReset();
    usePage.mockReset();
    router.get.mockReset();
    useForm.mockReturnValue(createForm());
    mockPage();
});

describe('Reservation/Index', () => {
    it('does not render a Cancelada row or badge', () => {
        const { container } = renderIndex();

        expect(screen.getByRole('heading', { name: 'Reservas' })).toBeInTheDocument();
        expect(screen.queryByRole('columnheader', { name: 'ID' })).not.toBeInTheDocument();
        expect(screen.queryByText('res-1')).not.toBeInTheDocument();
        expect(screen.queryByText(/ID:/)).not.toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Sala' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Responsável' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Título' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Início' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Fim' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Participantes' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Status' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Ações' })).toBeInTheDocument();
        expect(screen.getAllByText('Ativa').length).toBeGreaterThan(0);
        expect(screen.queryByText('Cancelada')).not.toBeInTheDocument();
        expect(screen.getAllByRole('button', { name: 'Cancelar' }).length).toBeGreaterThan(0);
        expect(container.querySelector('svg[data-calendar]')).toBeNull();
        expect(screen.queryByRole('link', { name: /detalhes/i })).not.toBeInTheDocument();
        expect(screen.getAllByRole('link', { name: 'Nova reserva' })[0]).toHaveAttribute(
            'href',
            '/reservations/create',
        );
    });

    it('writes the selected period, omits range dates, and resets page to 1', async () => {
        const user = userEvent.setup();

        renderIndex({
            filters: {
                room_id: 'room-1',
                period: 'all',
                starts_on: '2026-09-20',
                ends_on: '2026-09-21',
            },
        });

        await user.click(screen.getByRole('radio', { name: 'Hoje' }));

        expect(router.get).toHaveBeenCalledWith(
            '/reservations',
            { period: 'today', room_id: 'room-1', page: 1 },
            expect.any(Object),
        );
        expect(router.get.mock.calls.at(-1)[1]).not.toHaveProperty('starts_on');
        expect(router.get.mock.calls.at(-1)[1]).not.toHaveProperty('ends_on');
        expect(router.get.mock.calls.at(-1)[1]).not.toHaveProperty('date');
        expect(screen.getByLabelText('Data inicial')).toHaveValue('2026-09-21');
        expect(screen.getByLabelText('Data final')).toHaveValue('2026-09-21');
    });

    it('fills Data inicial and Data final from the selected preset', async () => {
        const user = userEvent.setup();
        const page = {
            reservations: sampleReservations,
            filterRooms,
            hasAny: true,
        };

        const { rerender } = renderIndex();

        expect(screen.getByLabelText('Data inicial')).toHaveValue('');
        expect(screen.getByLabelText('Data final')).toHaveValue('');

        await user.click(screen.getByRole('radio', { name: 'Hoje' }));
        rerender(<Index {...page} filters={{ ...defaultFilters, period: 'today' }} />);
        expect(screen.getByLabelText('Data inicial')).toHaveValue('2026-09-21');
        expect(screen.getByLabelText('Data final')).toHaveValue('2026-09-21');

        await user.click(screen.getByRole('radio', { name: 'Amanhã' }));
        rerender(<Index {...page} filters={{ ...defaultFilters, period: 'tomorrow' }} />);
        expect(screen.getByLabelText('Data inicial')).toHaveValue('2026-09-21');
        expect(screen.getByLabelText('Data final')).toHaveValue('2026-09-22');

        await user.click(screen.getByRole('radio', { name: '1 semana' }));
        rerender(<Index {...page} filters={{ ...defaultFilters, period: 'week' }} />);
        expect(screen.getByLabelText('Data inicial')).toHaveValue('2026-09-21');
        expect(screen.getByLabelText('Data final')).toHaveValue('2026-09-27');

        await user.click(screen.getByRole('radio', { name: 'Todos' }));
        rerender(<Index {...page} filters={{ ...defaultFilters, period: 'all' }} />);
        expect(screen.getByLabelText('Data inicial')).toHaveValue('');
        expect(screen.getByLabelText('Data final')).toHaveValue('');
        expect(router.get.mock.calls.at(-1)[1]).toEqual({ period: 'all', page: 1 });
        expect(router.get.mock.calls.at(-1)[1]).not.toHaveProperty('starts_on');
        expect(router.get.mock.calls.at(-1)[1]).not.toHaveProperty('ends_on');
    });

    it('shows preset dates on load without treating them as an applied range', () => {
        renderIndex({ filters: { ...defaultFilters, period: 'today' } });

        expect(screen.getByRole('radio', { name: 'Hoje' })).toBeChecked();
        expect(screen.getByLabelText('Data inicial')).toHaveValue('2026-09-21');
        expect(screen.getByLabelText('Data final')).toHaveValue('2026-09-21');
    });

    it('unchecks period radios when a range is active and Todos clears it to list all actives', async () => {
        const user = userEvent.setup();

        renderIndex({
            reservations: { data: [], total: 0, last_page: 1 },
            filters: {
                room_id: '',
                period: 'all',
                starts_on: '2026-09-20',
                ends_on: '2026-09-21',
            },
            hasAny: true,
        });

        expect(screen.getByRole('radio', { name: 'Todos' })).not.toBeChecked();
        expect(screen.getByRole('radio', { name: 'Hoje' })).not.toBeChecked();
        expect(screen.getByText('Nenhuma reserva encontrada para os filtros selecionados.')).toBeInTheDocument();

        await user.click(screen.getByRole('radio', { name: 'Todos' }));

        expect(router.get).toHaveBeenCalledWith(
            '/reservations',
            { period: 'all', page: 1 },
            expect.any(Object),
        );
        expect(router.get.mock.calls.at(-1)[1]).not.toHaveProperty('starts_on');
        expect(router.get.mock.calls.at(-1)[1]).not.toHaveProperty('ends_on');
        expect(router.get.mock.calls.at(-1)[1]).not.toHaveProperty('room_id');
        expect(screen.getByLabelText('Data inicial')).toHaveValue('');
        expect(screen.getByLabelText('Data final')).toHaveValue('');
    });

    it('aligns the period radios with the room select', () => {
        renderIndex();

        const room = screen.getByLabelText('Sala');
        const periodGroup = screen.getByRole('radiogroup', { name: 'Período' });
        const row = room.closest('div.flex');

        expect(screen.getByText('Período')).toBeInTheDocument();
        expect(row).not.toBeNull();
        expect(row.className.split(' ')).toEqual(expect.arrayContaining(['sm:flex-row', 'sm:items-end']));
        expect(row).toContainElement(periodGroup);
        expect(periodGroup.className.split(' ')).toEqual(expect.arrayContaining(['mt-1', 'min-h-10', 'items-center']));
    });

    it('writes a complete range as Y-m-d with no time and resets page to 1', async () => {
        const user = userEvent.setup();

        renderIndex({ filters: { ...defaultFilters, period: 'all', room_id: 'room-1' } });

        fireEvent.change(screen.getByLabelText('Data inicial'), { target: { value: '2026-09-22' } });
        expect(router.get).not.toHaveBeenCalled();

        fireEvent.change(screen.getByLabelText('Data final'), { target: { value: '2026-09-23' } });

        expect(router.get).toHaveBeenCalledWith(
            '/reservations',
            {
                period: 'all',
                room_id: 'room-1',
                starts_on: '2026-09-22',
                ends_on: '2026-09-23',
                page: 1,
            },
            expect.any(Object),
        );
        expect(router.get.mock.calls.at(-1)[1].starts_on).toMatch(/^\d{4}-\d{2}-\d{2}$/);
        expect(router.get.mock.calls.at(-1)[1].ends_on).toMatch(/^\d{4}-\d{2}-\d{2}$/);
        expect(router.get.mock.calls.at(-1)[1].starts_on).not.toMatch(/T|\s|:/);
        expect(router.get.mock.calls.at(-1)[1].ends_on).not.toMatch(/T|\s|:/);

        await user.selectOptions(screen.getByLabelText('Sala'), '');
        expect(router.get).toHaveBeenCalledWith(
            '/reservations',
            {
                period: 'all',
                starts_on: '2026-09-22',
                ends_on: '2026-09-23',
                page: 1,
            },
            expect.any(Object),
        );

        cleanup();
        router.get.mockReset();
        renderIndex({ filters: { ...defaultFilters, period: 'today', room_id: 'room-1' } });

        fireEvent.change(screen.getByLabelText('Data final'), { target: { value: '2026-09-23' } });
        expect(router.get).toHaveBeenCalledWith(
            '/reservations',
            {
                period: 'today',
                room_id: 'room-1',
                starts_on: '2026-09-21',
                ends_on: '2026-09-23',
                page: 1,
            },
            expect.any(Object),
        );
    });

    it('exposes Data inicial and Data final as date-only inputs with no time fields', () => {
        const { container } = renderIndex();

        expect(screen.getByLabelText('Data inicial')).toHaveAttribute('type', 'date');
        expect(screen.getByLabelText('Data final')).toHaveAttribute('type', 'date');
        expect(container.querySelector('input[type="time"]')).toBeNull();
        expect(container.querySelector('input[type="datetime-local"]')).toBeNull();
        expect(screen.queryByLabelText('Data')).not.toBeInTheDocument();
    });

    it('requests period=all with no room or range when Limpar filtros is used', async () => {
        const user = userEvent.setup();

        renderIndex({
            reservations: { data: [], total: 0 },
            filters: {
                room_id: 'room-1',
                period: 'today',
                starts_on: '2026-09-21',
                ends_on: '2026-09-21',
            },
            hasAny: true,
        });

        await user.click(screen.getByRole('button', { name: 'Limpar filtros' }));

        expect(router.get).toHaveBeenCalledWith(
            '/reservations',
            { period: 'all', page: 1 },
            expect.any(Object),
        );
        expect(router.get.mock.calls.at(-1)[1]).not.toHaveProperty('room_id');
        expect(router.get.mock.calls.at(-1)[1]).not.toHaveProperty('starts_on');
        expect(router.get.mock.calls.at(-1)[1]).not.toHaveProperty('ends_on');
    });

    it('opens the cancel dialog, focuses Voltar, Escape closes, and patches cancel only on confirm', async () => {
        const user = userEvent.setup();
        const form = createForm();
        useForm.mockReturnValue(form);

        renderIndex();

        const cancelButton = screen.getAllByRole('button', { name: 'Cancelar' })[0];
        await user.click(cancelButton);

        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(screen.getByRole('dialog')).toHaveTextContent('Sala Azul');
        expect(screen.getByRole('dialog')).toHaveTextContent('21/09/2026');
        expect(screen.getByRole('dialog')).toHaveTextContent('09:00');
        expect(screen.getByRole('dialog')).toHaveTextContent('09:30');
        expect(screen.getByRole('button', { name: 'Voltar' })).toHaveFocus();
        expect(form.patch).not.toHaveBeenCalled();

        await user.keyboard('{Escape}');
        expect(form.patch).not.toHaveBeenCalled();
        expect(cancelButton).toHaveFocus();

        await user.click(cancelButton);
        await user.click(screen.getByRole('button', { name: 'Cancelar reserva' }));

        expect(form.patch).toHaveBeenCalledTimes(1);
        expect(form.patch).toHaveBeenCalledWith('/reservations/res-1/cancel', expect.any(Object));
    });

    it('shows Cancelando... while processing', async () => {
        const user = userEvent.setup();
        const form = createForm();
        useForm.mockReturnValue(form);

        const { rerender } = renderIndex();

        await user.click(screen.getAllByRole('button', { name: 'Cancelar' })[0]);

        form.processing = true;
        useForm.mockReturnValue(form);
        rerender(
            <Index
                reservations={sampleReservations}
                filters={defaultFilters}
                filterRooms={filterRooms}
                hasAny
            />,
        );

        expect(screen.getByRole('button', { name: 'Cancelando...' })).toBeDisabled();
        expect(screen.getByRole('button', { name: 'Voltar' })).toBeDisabled();
    });

    it('distinguishes global empty vs filtered empty vs load error', async () => {
        const user = userEvent.setup();

        const { rerender } = render(
            <Index reservations={{ data: [], total: 0 }} filters={defaultFilters} hasAny={false} />,
        );

        expect(screen.getByText('Nenhuma reserva cadastrada.')).toBeInTheDocument();
        expect(screen.getAllByRole('link', { name: 'Nova reserva' }).length).toBeGreaterThan(1);

        rerender(
            <Index
                reservations={{ data: [], total: 0 }}
                filters={{ room_id: 'room-1', period: 'today', starts_on: '', ends_on: '' }}
                filterRooms={filterRooms}
                hasAny
            />,
        );

        expect(screen.getByText('Nenhuma reserva encontrada para os filtros selecionados.')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Limpar filtros' })).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Limpar filtros' }));

        expect(router.get).toHaveBeenCalledWith(
            '/reservations',
            { period: 'all', page: 1 },
            expect.any(Object),
        );

        rerender(
            <Index reservations={{ data: [] }} filters={defaultFilters} hasAny={false} loadError />,
        );

        expect(screen.getByText('Não foi possível carregar as reservas.')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Tente novamente' }));

        expect(router.get).toHaveBeenCalledWith(
            '/reservations',
            { period: 'all' },
            expect.any(Object),
        );

        const retryOptions = router.get.mock.calls.at(-1)[2];
        retryOptions.onError();
        expect(retryOptions.onHttpException()).toBe(false);
        expect(retryOptions.onNetworkError()).toBe(false);
        expect(screen.getByText('Não foi possível carregar as reservas.')).toBeInTheDocument();
    });

    it('shows unexpected cancel failure and does not close the dialog', async () => {
        const user = userEvent.setup();
        const form = createForm();
        useForm.mockReturnValue(form);

        let httpExceptionResult;
        let networkErrorResult;

        form.patch.mockImplementation((_url, options) => {
            options.onError();
            httpExceptionResult = options.onHttpException();
            networkErrorResult = options.onNetworkError();
        });

        renderIndex();

        await user.click(screen.getAllByRole('button', { name: 'Cancelar' })[0]);
        await user.click(screen.getByRole('button', { name: 'Cancelar reserva' }));

        expect(httpExceptionResult).toBe(false);
        expect(networkErrorResult).toBe(false);
        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(screen.getByText('Não foi possível cancelar a reserva. Tente novamente.')).toHaveAttribute(
            'role',
            'alert',
        );
    });
});
