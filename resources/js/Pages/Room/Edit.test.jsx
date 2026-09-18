import { createElement } from 'react';
import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useForm, usePage } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import '@testing-library/jest-dom/vitest';
import Edit from './Edit';

vi.mock('@inertiajs/react', () => ({
    useForm: vi.fn(),
    usePage: vi.fn(),
    Link: ({ href, children, className, ...props }) => createElement('a', { href, className, ...props }, children),
}));

const activeRoom = {
    id: 'id-1',
    name: 'Sala Azul',
    capacity: 10,
    is_active: true,
};

const inactiveRoom = {
    ...activeRoom,
    is_active: false,
};

function createForm(room, overrides = {}) {
    const form = {
        data: {
            name: room.name,
            capacity: room.capacity,
            ...overrides.data,
        },
        errors: overrides.errors ?? {},
        processing: overrides.processing ?? false,
        setData: vi.fn(),
        put: vi.fn(),
        transform: vi.fn(),
        ...overrides,
    };

    form.transform.mockImplementation((callback) => {
        form.lastTransform = callback;

        return form;
    });

    return form;
}

function mockPage(room) {
    usePage.mockReturnValue({
        url: `/rooms/${room.id}/edit`,
        props: {
            auth: { user: { name: 'Ada Lovelace' } },
            flash: { success: null, error: null },
        },
    });
}

function renderEdit(room = activeRoom, pageProps = {}, formOverrides = {}) {
    const form = createForm(room, formOverrides);
    useForm.mockReturnValue(form);
    mockPage(room);

    return { form, ...render(<Edit room={room} {...pageProps} />) };
}

afterEach(() => {
    cleanup();
});

beforeEach(() => {
    useForm.mockReset();
    usePage.mockReset();
});

describe('Room/Edit', () => {
    it('prefills name and capacity, shows read-only Ativa or Inativa, and reuses the shared form', () => {
        renderEdit(activeRoom);

        expect(screen.getByRole('heading', { name: 'Editar sala' })).toBeInTheDocument();
        expect(screen.getByText('Atualize as informações da sala de reunião.')).toBeInTheDocument();
        expect(screen.getByLabelText(/Nome/)).toHaveValue('Sala Azul');
        expect(screen.getByLabelText(/Capacidade/)).toHaveValue(10);
        expect(screen.getByLabelText(/Nome/)).toHaveAttribute('aria-required', 'true');
        expect(screen.getByLabelText(/Capacidade/)).toHaveAttribute('aria-required', 'true');
        expect(screen.getByText('Status')).toBeInTheDocument();
        expect(screen.getByText('Ativa')).toBeInTheDocument();
        expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Situação')).not.toBeInTheDocument();
        expect(screen.queryByText('id-1')).not.toBeInTheDocument();
        expect(useForm).toHaveBeenCalledWith({ name: 'Sala Azul', capacity: 10 });
    });

    it('shows Desativar sala while the room is active and opens confirmation before any PUT', async () => {
        const user = userEvent.setup();
        const { form } = renderEdit(activeRoom);

        await user.click(screen.getByRole('button', { name: 'Desativar sala' }));

        expect(form.put).not.toHaveBeenCalled();
        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Desativar sala?' })).toBeInTheDocument();
    });

    it('keeps radio Desativar sala sem reunião selected and hides meeting questions when has_registered_meetings is false', async () => {
        const user = userEvent.setup();
        renderEdit(activeRoom, { has_registered_meetings: false });

        await user.click(screen.getByRole('button', { name: 'Desativar sala' }));

        expect(screen.getByLabelText('Desativar sala sem reunião')).toBeChecked();
        expect(screen.queryByLabelText('Manter reuniões programadas')).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Cancelar reuniões programadas')).not.toBeInTheDocument();
    });

    it('shows keep/cancel meeting radios and hides Desativar sala sem reunião when has_registered_meetings is true', async () => {
        const user = userEvent.setup();
        const { form } = renderEdit(activeRoom, { has_registered_meetings: true });

        await user.click(screen.getByRole('button', { name: 'Desativar sala' }));

        expect(screen.getByLabelText('Manter reuniões programadas')).toBeChecked();
        expect(screen.getByLabelText('Cancelar reuniões programadas')).toBeInTheDocument();
        expect(screen.queryByLabelText('Desativar sala sem reunião')).not.toBeInTheDocument();

        await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Desativar' }));

        expect(form.lastTransform({ name: 'Sala Azul', capacity: 10 })).toEqual({
            name: 'Sala Azul',
            capacity: 10,
            is_active: false,
        });
    });

    it('puts name, capacity, and is_active false once on confirmed deactivation', async () => {
        const user = userEvent.setup();
        const { form } = renderEdit(activeRoom);

        await user.click(screen.getByRole('button', { name: 'Desativar sala' }));
        await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Desativar' }));

        expect(form.put).toHaveBeenCalledTimes(1);
        expect(form.put).toHaveBeenCalledWith('/rooms/id-1', expect.any(Object));
        expect(form.lastTransform({ name: 'Sala Azul', capacity: 10 })).toEqual({
            name: 'Sala Azul',
            capacity: 10,
            is_active: false,
        });
        expect(form.lastTransform({ name: 'Sala Azul', capacity: 10 })).not.toHaveProperty('keep_meetings');
        expect(form.lastTransform({ name: 'Sala Azul', capacity: 10 })).not.toHaveProperty('reservations');
    });

    it('puts only name and capacity on Salvar and does not send is_active', async () => {
        const user = userEvent.setup();
        const { form } = renderEdit(activeRoom);

        await user.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(form.put).toHaveBeenCalledTimes(1);
        expect(form.put).toHaveBeenCalledWith('/rooms/id-1', expect.any(Object));
        expect(form.lastTransform({ name: 'Sala Azul', capacity: 10, is_active: true })).toEqual({
            name: 'Sala Azul',
            capacity: 10,
        });
        expect(Object.keys(form.data).sort()).toEqual(['capacity', 'name']);
    });

    it('closes the confirmation from Cancelar without putting', async () => {
        const user = userEvent.setup();
        const { form } = renderEdit(activeRoom);

        await user.click(screen.getByRole('button', { name: 'Desativar sala' }));
        await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Cancelar' }));

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(form.put).not.toHaveBeenCalled();
        expect(screen.getByRole('button', { name: 'Desativar sala' })).toHaveFocus();
    });

    it('closes the confirmation from Escape without putting', async () => {
        const user = userEvent.setup();
        const { form } = renderEdit(activeRoom);

        await user.click(screen.getByRole('button', { name: 'Desativar sala' }));
        await user.keyboard('{Escape}');

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(form.put).not.toHaveBeenCalled();
        expect(screen.getByRole('button', { name: 'Desativar sala' })).toHaveFocus();
    });

    it('hides Desativar sala and shows Ativar sala when the room is already inactive', async () => {
        const user = userEvent.setup();
        const { form } = renderEdit(inactiveRoom);

        expect(screen.getByText('Inativa')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Desativar sala' })).not.toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Ativar sala' }));

        expect(screen.queryByLabelText('Desativar sala sem reunião')).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Manter reuniões programadas')).not.toBeInTheDocument();

        await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Ativar' }));

        expect(form.put).toHaveBeenCalledTimes(1);
        expect(form.lastTransform({ name: 'Sala Azul', capacity: 10 })).toEqual({
            name: 'Sala Azul',
            capacity: 10,
            is_active: true,
        });
    });

    it('shows Desativando... and does not send a second PUT while deactivation is processing', async () => {
        const user = userEvent.setup();
        const { form, rerender } = renderEdit(activeRoom);

        await user.click(screen.getByRole('button', { name: 'Desativar sala' }));
        await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Desativar' }));

        form.processing = true;
        useForm.mockReturnValue(form);
        rerender(<Edit room={activeRoom} />);

        const confirm = screen.getByRole('button', { name: 'Desativando...' });
        expect(confirm).toBeDisabled();
        expect(within(screen.getByRole('dialog')).getByRole('button', { name: 'Cancelar' })).toBeDisabled();

        await user.click(confirm);

        expect(form.put).toHaveBeenCalledTimes(1);
    });
});
