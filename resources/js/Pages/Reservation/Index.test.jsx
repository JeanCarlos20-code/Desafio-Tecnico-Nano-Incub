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

const defaultFilters = { room_id: '', date: '2026-09-21' };
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
});

beforeEach(() => {
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
        expect(screen.getByRole('columnheader', { name: 'ID' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Sala' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Responsável' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Título' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Início' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Fim' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Participantes' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Status' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Ações' })).toBeInTheDocument();
        expect(screen.getAllByText('res-1').length).toBeGreaterThan(0);
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

    it('applies room_id and date filters through the Inertia service and resets page to 1', async () => {
        const user = userEvent.setup();

        renderIndex();

        await user.selectOptions(screen.getByLabelText('Sala'), 'room-1');

        expect(router.get).toHaveBeenCalledWith(
            '/reservations',
            { room_id: 'room-1', date: '2026-09-21', page: 1 },
            expect.any(Object),
        );

        fireEvent.change(screen.getByLabelText('Data'), { target: { value: '2026-09-22' } });

        expect(router.get).toHaveBeenCalledWith(
            '/reservations',
            { date: '2026-09-22', page: 1 },
            expect.any(Object),
        );
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
                filters={{ room_id: 'room-1', date: '2026-09-21' }}
                filterRooms={filterRooms}
                hasAny
            />,
        );

        expect(screen.getByText('Nenhuma reserva encontrada para os filtros selecionados.')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Limpar filtros' })).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Limpar filtros' }));

        expect(router.get).toHaveBeenCalledWith(
            '/reservations',
            { date: '2026-09-21', page: 1 },
            expect.any(Object),
        );

        rerender(
            <Index reservations={{ data: [] }} filters={defaultFilters} hasAny={false} loadError />,
        );

        expect(screen.getByText('Não foi possível carregar as reservas.')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Tente novamente' }));

        expect(router.get).toHaveBeenCalledWith(
            '/reservations',
            { date: '2026-09-21' },
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
