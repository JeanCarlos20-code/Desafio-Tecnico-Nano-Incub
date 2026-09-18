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
    },
    Link: ({ href, children, className, ...props }) => createElement('a', { href, className, ...props }, children),
}));

const sampleRooms = {
    data: [
        {
            id: 'id-1',
            name: 'Sala Azul',
            capacity: 10,
            is_active: true,
            status: 'Ativa',
            created_at: '18/09/2026',
        },
        {
            id: 'id-2',
            name: 'Sala Cinza',
            capacity: 4,
            is_active: false,
            status: 'Inativa',
            created_at: '17/09/2026',
        },
    ],
    total: 2,
    current_page: 1,
    last_page: 1,
    per_page: 15,
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

describe('Room/Index', () => {
    it('shows name, capacity, Ativa/Inativa text, DD/MM/YYYY date, edit and delete actions, and does not display the room id', () => {
        render(<Index rooms={sampleRooms} />);

        expect(screen.queryByRole('columnheader', { name: 'ID' })).not.toBeInTheDocument();
        expect(screen.queryByText('id-1')).not.toBeInTheDocument();
        expect(screen.queryByText('id-2')).not.toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Nome' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Capacidade' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Status' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Criada em' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Ações' })).toBeInTheDocument();
        expect(screen.getAllByText('Sala Azul').length).toBeGreaterThan(0);
        expect(screen.getAllByText('10').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Ativa').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Inativa').length).toBeGreaterThan(0);
        expect(screen.getAllByText('18/09/2026').length).toBeGreaterThan(0);
        expect(screen.getAllByRole('link', { name: 'Editar Sala Azul' })[0]).toHaveAttribute(
            'href',
            '/rooms/id-1/edit',
        );
        expect(screen.getAllByRole('button', { name: 'Excluir Sala Azul' }).length).toBeGreaterThan(0);
        expect(screen.getAllByRole('link', { name: 'Nova sala' })[0]).toHaveAttribute('href', '/rooms/create');
    });

    it('keeps compact columns from stretching and allows horizontal table scroll', () => {
        const { container } = render(<Index rooms={sampleRooms} />);

        const table = container.querySelector('table');
        const scrollRegion = table.parentElement;
        const desktopSurface = scrollRegion.parentElement;
        const actionsHeader = screen.getByRole('columnheader', { name: 'Ações' });
        const nameHeader = screen.getByRole('columnheader', { name: 'Nome' });
        const mobileList = container.querySelector('ul');

        expect(table.className).toMatch(/\bw-full\b/);
        expect(table.className).toMatch(/table-auto/);
        expect(scrollRegion.className).toMatch(/overflow-x-auto/);
        expect(desktopSurface.className).toMatch(/min-w-0/);
        expect(desktopSurface.className).toMatch(/md:block/);
        expect(nameHeader.className).toMatch(/min-w-0/);
        expect(actionsHeader.className).toMatch(/\bw-0\b/);
        expect(actionsHeader.className).toMatch(/whitespace-nowrap/);
        expect(actionsHeader.className).toMatch(/text-right/);
        expect(screen.getByRole('columnheader', { name: 'Capacidade' }).className).toMatch(/whitespace-nowrap/);
        expect(screen.getByRole('columnheader', { name: 'Status' }).className).toMatch(/whitespace-nowrap/);
        expect(screen.getByRole('columnheader', { name: 'Criada em' }).className).toMatch(/whitespace-nowrap/);
        expect(mobileList.className).toMatch(/md:hidden/);
        expect(screen.queryByRole('columnheader', { name: 'ID' })).not.toBeInTheDocument();
    });

    it('shows the empty state and Nova sala when there are no rooms', () => {
        render(<Index rooms={{ data: [], total: 0 }} />);

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

    it('confirm calls DELETE once and shows Excluindo... while processing', async () => {
        const user = userEvent.setup();
        const form = createForm();
        useForm.mockReturnValue(form);

        const { rerender } = render(<Index rooms={sampleRooms} />);

        await user.click(screen.getAllByRole('button', { name: 'Excluir Sala Azul' })[0]);
        await user.click(screen.getByRole('button', { name: 'Excluir sala' }));

        expect(form.delete).toHaveBeenCalledTimes(1);
        expect(form.delete).toHaveBeenCalledWith('/rooms/id-1', expect.any(Object));

        form.processing = true;
        useForm.mockReturnValue(form);
        rerender(<Index rooms={sampleRooms} />);

        expect(screen.getByRole('button', { name: 'Excluindo...' })).toBeDisabled();
        expect(screen.getByRole('button', { name: 'Cancelar' })).toBeDisabled();
    });

    it('shows Previous on the last page so the administrator can go back', () => {
        render(
            <Index
                rooms={{
                    ...sampleRooms,
                    current_page: 2,
                    last_page: 2,
                    prev_page_url: 'http://localhost/rooms?foo=bar&page=1',
                    next_page_url: null,
                }}
            />,
        );

        const previous = screen.getByRole('link', { name: 'Anterior' });
        expect(previous).toHaveAttribute('href', 'http://localhost/rooms?foo=bar&page=1');
        expect(screen.queryByRole('link', { name: 'Próxima' })).not.toBeInTheDocument();
    });

    it('shows Next on the first page when more than one page exists', () => {
        render(
            <Index
                rooms={{
                    ...sampleRooms,
                    current_page: 1,
                    last_page: 2,
                    prev_page_url: null,
                    next_page_url: 'http://localhost/rooms?foo=bar&page=2',
                }}
            />,
        );

        expect(screen.queryByRole('link', { name: 'Anterior' })).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Próxima' })).toHaveAttribute(
            'href',
            'http://localhost/rooms?foo=bar&page=2',
        );
    });

    it('shows the load-failure copy with retry', async () => {
        const user = userEvent.setup();

        render(<Index rooms={{ data: [] }} loadError />);

        expect(screen.getByText('Não foi possível carregar as salas.')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Tente novamente' }));

        expect(router.get).toHaveBeenCalledWith('/rooms', {}, expect.any(Object));
    });
});
