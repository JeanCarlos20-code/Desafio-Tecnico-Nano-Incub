import { createElement } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
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
        on: vi.fn(() => vi.fn()),
    },
    Link: ({ href, children, className, ...props }) => createElement('a', { href, className, ...props }, children),
}));

const sampleRooms = {
    data: [
        {
            id: '1',
            name: 'Sala Azul',
            capacity: 10,
            is_active: true,
            status: 'Ativa',
            created_at: '18/09/2026',
            has_reservations: true,
        },
        {
            id: '2',
            name: 'Sala Cinza',
            capacity: 4,
            is_active: false,
            status: 'Inativa',
            created_at: '17/09/2026',
            has_reservations: false,
        },
    ],
    page: 1,
    limit: 20,
    total: 2,
};

function createForm(overrides = {}) {
    return {
        processing: overrides.processing ?? false,
        post: vi.fn(),
        delete: vi.fn(),
        ...overrides,
    };
}

function mockPage() {
    usePage.mockReturnValue({
        url: '/rooms',
        props: {
            auth: { user: { name: 'Ada Lovelace' } },
            flash: { success: null, error: null },
        },
    });
}

function firstDesktopRowCells(container) {
    return container.querySelector('table tbody tr').querySelectorAll('td');
}

afterEach(() => {
    cleanup();
});

beforeEach(() => {
    useForm.mockReset();
    usePage.mockReset();
    router.get.mockReset();
    router.on.mockReset();
    router.on.mockImplementation(() => vi.fn());
    useForm.mockReturnValue(createForm());
    mockPage();
});

describe('Room/Index', () => {
    it('shows name, capacity, Ativa/Inativa text, DD/MM/YYYY date, edit and delete actions, and displays the persisted room id', () => {
        render(<Index rooms={sampleRooms} />);

        expect(screen.getByRole('columnheader', { name: 'ID' })).toBeInTheDocument();
        expect(screen.getAllByText('1').length).toBeGreaterThan(0);
        expect(screen.getAllByText('2').length).toBeGreaterThan(0);
        expect(screen.getByRole('columnheader', { name: 'Nome' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Capacidade' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Situação' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Criada em' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Ações' })).toBeInTheDocument();
        expect(screen.getAllByText('Sala Azul').length).toBeGreaterThan(0);
        expect(screen.getAllByText('10').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Ativa').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Inativa').length).toBeGreaterThan(0);
        expect(screen.getAllByText('18/09/2026').length).toBeGreaterThan(0);
        expect(screen.getAllByRole('link', { name: 'Editar Sala Azul' })[0]).toHaveAttribute(
            'href',
            '/rooms/1/edit',
        );
        expect(screen.getAllByRole('button', { name: 'Excluir Sala Azul' }).length).toBeGreaterThan(0);
        expect(screen.getAllByRole('link', { name: 'Nova sala' })[0]).toHaveAttribute('href', '/rooms/create');
    });

    it('Capacidade header and capacity cells share text-center', () => {
        const { container } = render(<Index rooms={sampleRooms} />);

        const capacityHeader = screen.getByRole('columnheader', { name: 'Capacidade' });
        const capacityCell = firstDesktopRowCells(container)[2];

        expect(capacityHeader.className).toMatch(/\btext-center\b/);
        expect(capacityCell.className).toMatch(/\btext-center\b/);
        expect(capacityHeader.className).not.toMatch(/\btext-left\b/);
        expect(capacityCell.className).not.toMatch(/\btext-left\b/);
        expect(capacityHeader.className).not.toMatch(/\btext-right\b/);
        expect(capacityCell.className).not.toMatch(/\btext-right\b/);
        expect(capacityCell).toHaveTextContent('10');
    });

    it('Nome keeps leftover-width priority via w-full and min-w-0 without xl:w-[16%]', () => {
        const { container } = render(<Index rooms={sampleRooms} />);

        const nameHeader = screen.getByRole('columnheader', { name: 'Nome' });
        const nameCell = firstDesktopRowCells(container)[1];

        expect(nameHeader.className).toMatch(/min-w-0/);
        expect(nameCell.className).toMatch(/min-w-0/);
        expect(nameHeader.className).toMatch(/\bw-full\b/);
        expect(nameCell.className).toMatch(/\bw-full\b/);
        expect(nameHeader.className).not.toMatch(/xl:w-\[16%\]/);
        expect(nameCell.className).not.toMatch(/xl:w-\[16%\]/);
        expect(nameHeader.className).not.toMatch(/max-w-/);
        expect(nameCell.className).not.toMatch(/max-w-/);
    });

    it('Capacidade, Situação, Criada em, and Ações stay compact with w-0 and use xl:w-[16%] nowrap', () => {
        const { container } = render(<Index rooms={sampleRooms} />);

        const headers = [
            screen.getByRole('columnheader', { name: 'Capacidade' }),
            screen.getByRole('columnheader', { name: 'Situação' }),
            screen.getByRole('columnheader', { name: 'Criada em' }),
            screen.getByRole('columnheader', { name: 'Ações' }),
        ];
        const cells = [...firstDesktopRowCells(container)].slice(2);

        headers.forEach((header) => {
            expect(header.className).toMatch(/\bw-0\b/);
            expect(header.className).toMatch(/xl:w-\[16%\]/);
            expect(header.className).toMatch(/whitespace-nowrap/);
        });
        cells.forEach((cell) => {
            expect(cell.className).toMatch(/\bw-0\b/);
            expect(cell.className).toMatch(/xl:w-\[16%\]/);
        });
    });

    it('Ações header and cells share text-center and do not use text-right', () => {
        const { container } = render(<Index rooms={sampleRooms} />);

        const actionsHeader = screen.getByRole('columnheader', { name: 'Ações' });
        const actionsCell = firstDesktopRowCells(container)[5];

        expect(actionsHeader.className).toMatch(/\btext-center\b/);
        expect(actionsCell.className).toMatch(/\btext-center\b/);
        expect(actionsHeader.className).not.toMatch(/\btext-right\b/);
        expect(actionsCell.className).not.toMatch(/\btext-right\b/);
    });

    it('still uses table-auto, overflow-x-auto, md:block table, and md:hidden mobile cards', () => {
        const { container } = render(<Index rooms={sampleRooms} />);

        const table = container.querySelector('table');
        const scrollRegion = table.parentElement;
        const desktopSurface = scrollRegion.parentElement;
        const mobileList = container.querySelector('ul');

        expect(table.className).toMatch(/\bw-full\b/);
        expect(table.className).toMatch(/table-auto/);
        expect(table.className).not.toMatch(/table-fixed/);
        expect(scrollRegion.className).toMatch(/overflow-x-auto/);
        expect(desktopSurface.className).toMatch(/min-w-0/);
        expect(desktopSurface.className).toMatch(/md:block/);
        expect(mobileList.className).toMatch(/md:hidden/);
    });

    it('applies status through the Inertia service, resets page to 1, and warns on delete when has_reservations', async () => {
        const user = userEvent.setup();
        const form = createForm();
        useForm.mockReturnValue(form);

        render(<Index rooms={sampleRooms} filters={{ status: 'all' }} hasAny />);

        expect(screen.getByLabelText('Status')).toHaveValue('all');
        await user.selectOptions(screen.getByLabelText('Status'), 'inactive');

        expect(router.get).toHaveBeenCalledWith('/rooms', { status: 'inactive', page: 1, limit: 20 }, {});

        await user.click(screen.getAllByRole('button', { name: 'Excluir Sala Azul' })[0]);

        expect(screen.getByRole('dialog')).toHaveTextContent(
            'As reuniões ativas desta sala serão canceladas e deixarão de aparecer na listagem.',
        );
        expect(form.delete).not.toHaveBeenCalled();

        await user.click(screen.getByRole('button', { name: 'Cancelar' }));
        await user.click(screen.getAllByRole('button', { name: 'Excluir Sala Cinza' })[0]);

        expect(screen.getByRole('dialog')).not.toHaveTextContent(
            'As reuniões ativas desta sala serão canceladas e deixarão de aparecer na listagem.',
        );
    });

    it('shows the empty state and Nova sala when there are no rooms', () => {
        render(<Index rooms={{ data: [], total: 0 }} hasAny={false} />);

        expect(screen.getByText('Nenhuma sala cadastrada.')).toBeInTheDocument();
        expect(screen.getAllByRole('link', { name: 'Nova sala' }).length).toBeGreaterThan(1);
        expect(screen.getAllByRole('link', { name: 'Nova sala' })[1]).toHaveAttribute('href', '/rooms/create');
    });

    it('opens the delete dialog with the room name and does not call delete until confirm', async () => {
        const user = userEvent.setup();
        const form = createForm();
        useForm.mockReturnValue(form);

        render(<Index rooms={sampleRooms} />);

        await user.click(screen.getAllByRole('button', { name: 'Excluir Sala Azul' })[0]);

        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(screen.getByRole('dialog')).toHaveTextContent('Sala Azul');
        expect(form.delete).not.toHaveBeenCalled();
    });

    it('hides the delete dialog on Cancelar and does not DELETE', async () => {
        const user = userEvent.setup();
        const form = createForm();
        useForm.mockReturnValue(form);

        render(<Index rooms={sampleRooms} />);

        await user.click(screen.getAllByRole('button', { name: 'Excluir Sala Azul' })[0]);
        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(form.delete).not.toHaveBeenCalled();

        await user.click(screen.getByRole('button', { name: 'Cancelar' }));

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(form.delete).not.toHaveBeenCalled();
    });

    it('does not call delete on cancel or Escape and returns focus to the delete control', async () => {
        const user = userEvent.setup();
        const form = createForm();
        useForm.mockReturnValue(form);

        render(<Index rooms={sampleRooms} />);

        const deleteButton = screen.getAllByRole('button', { name: 'Excluir Sala Azul' })[0];
        await user.click(deleteButton);
        expect(screen.getByRole('button', { name: 'Cancelar' })).toHaveFocus();

        await user.click(screen.getByRole('button', { name: 'Cancelar' }));

        expect(form.delete).not.toHaveBeenCalled();
        expect(deleteButton).toHaveFocus();

        await user.click(deleteButton);
        await user.keyboard('{Escape}');

        expect(form.delete).not.toHaveBeenCalled();
        expect(deleteButton).toHaveFocus();
    });

    it('hides the delete dialog after a successful delete onSuccess', async () => {
        const user = userEvent.setup();
        const form = createForm();
        useForm.mockReturnValue(form);

        form.delete.mockImplementation((_url, options) => {
            options.onSuccess();
        });

        render(<Index rooms={sampleRooms} />);

        await user.click(screen.getAllByRole('button', { name: 'Excluir Sala Azul' })[0]);
        expect(screen.getByRole('dialog')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Excluir sala' }));

        expect(form.delete).toHaveBeenCalledWith('/rooms/1', expect.any(Object));
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('confirm calls DELETE once and shows Excluindo... while processing', async () => {
        const user = userEvent.setup();
        const form = createForm();
        useForm.mockReturnValue(form);

        const { rerender } = render(<Index rooms={sampleRooms} />);

        await user.click(screen.getAllByRole('button', { name: 'Excluir Sala Azul' })[0]);
        await user.click(screen.getByRole('button', { name: 'Excluir sala' }));

        expect(form.delete).toHaveBeenCalledTimes(1);
        expect(form.delete).toHaveBeenCalledWith('/rooms/1', expect.any(Object));

        form.processing = true;
        useForm.mockReturnValue(form);
        rerender(<Index rooms={sampleRooms} />);

        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Excluindo...' })).toBeDisabled();
        expect(screen.getByRole('button', { name: 'Cancelar' })).toBeDisabled();
    });

    it('shows Próxima from page, limit, and total and keeps the current status filter', () => {
        render(
            <Index
                rooms={{
                    ...sampleRooms,
                    page: 1,
                    limit: 2,
                    total: 3,
                }}
                filters={{ status: 'inactive' }}
            />,
        );

        expect(screen.queryByRole('link', { name: 'Anterior' })).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Próxima' })).toHaveAttribute(
            'href',
            '/rooms?page=2&limit=2&status=inactive',
        );
    });

    it('shows Anterior on the last page and hides Próxima', () => {
        render(
            <Index
                rooms={{
                    ...sampleRooms,
                    page: 2,
                    limit: 2,
                    total: 3,
                }}
                filters={{ status: 'all' }}
            />,
        );

        expect(screen.getByRole('link', { name: 'Anterior' })).toHaveAttribute(
            'href',
            '/rooms?page=1&limit=2&status=all',
        );
        expect(screen.queryByRole('link', { name: 'Próxima' })).not.toBeInTheDocument();
    });

    it('defaults the Status select to Ativas when filters.status is omitted', () => {
        render(<Index rooms={sampleRooms} />);

        expect(screen.getByLabelText('Status')).toHaveValue('active');
        expect(screen.getByRole('option', { name: 'Ativas' })).toHaveValue('active');
    });

    it('visits Ativas without status, Todas with status=all, Inativas with status=inactive, and resets page to 1', async () => {
        const user = userEvent.setup();

        render(
            <Index
                rooms={{
                    ...sampleRooms,
                    page: 2,
                    limit: 2,
                    total: 3,
                }}
                filters={{ status: 'inactive' }}
                hasAny
            />,
        );

        await user.selectOptions(screen.getByLabelText('Status'), 'active');
        expect(router.get).toHaveBeenLastCalledWith('/rooms', { page: 1, limit: 2 }, {});
        expect(router.get.mock.calls.at(-1)[1]).not.toHaveProperty('status');

        await user.selectOptions(screen.getByLabelText('Status'), 'all');
        expect(router.get).toHaveBeenLastCalledWith('/rooms', { status: 'all', page: 1, limit: 2 }, {});

        await user.selectOptions(screen.getByLabelText('Status'), 'inactive');
        expect(router.get).toHaveBeenLastCalledWith('/rooms', { status: 'inactive', page: 1, limit: 2 }, {});
    });

    it('pagination href omits status when Ativas and includes status=all when Todas', () => {
        const { rerender } = render(
            <Index
                rooms={{
                    ...sampleRooms,
                    page: 1,
                    limit: 2,
                    total: 3,
                }}
                filters={{ status: 'active' }}
            />,
        );

        expect(screen.getByRole('link', { name: 'Próxima' })).toHaveAttribute('href', '/rooms?page=2&limit=2');
        expect(screen.getByRole('link', { name: 'Próxima' }).getAttribute('href')).not.toContain('status=');

        rerender(
            <Index
                rooms={{
                    ...sampleRooms,
                    page: 1,
                    limit: 2,
                    total: 3,
                }}
                filters={{ status: 'all' }}
            />,
        );

        expect(screen.getByRole('link', { name: 'Próxima' })).toHaveAttribute(
            'href',
            '/rooms?page=2&limit=2&status=all',
        );
    });

    it('visits page 1 and the current limit when the status filter changes', async () => {
        const user = userEvent.setup();

        render(
            <Index
                rooms={{
                    ...sampleRooms,
                    page: 2,
                    limit: 2,
                    total: 3,
                }}
                filters={{ status: 'all' }}
                hasAny
            />,
        );

        await user.selectOptions(screen.getByLabelText('Status'), 'inactive');

        expect(router.get).toHaveBeenCalledWith('/rooms', { status: 'inactive', page: 1, limit: 2 }, {});
    });

    it('shows the load-failure copy with retry', async () => {
        const user = userEvent.setup();

        render(<Index rooms={{ data: [] }} loadError />);

        expect(screen.getByText('Não foi possível carregar as salas.')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Tente novamente' }));

        expect(router.get).toHaveBeenCalledWith('/rooms', {}, expect.any(Object));
    });
});
