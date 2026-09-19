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
            is_active: room.is_active,
            scheduled_meetings_action: '',
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

    return { form, ...render(<Edit room={room} future_active_count={0} {...pageProps} />) };
}

afterEach(() => {
    cleanup();
});

beforeEach(() => {
    useForm.mockReset();
    usePage.mockReset();
});

describe('Room/Edit', () => {
    it('opens the deactivation dialog with keep default and submits scheduled_meetings_action only after Desativar', async () => {
        const user = userEvent.setup();
        const { form } = renderEdit(activeRoom, { future_active_count: 2 }, { data: { is_active: false } });

        await user.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(form.put).not.toHaveBeenCalled();
        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Desativar sala?' })).toBeInTheDocument();
        expect(screen.getByText('O que deseja fazer com as reuniões programadas?')).toBeInTheDocument();
        expect(screen.getByLabelText('Manter reuniões programadas')).toBeChecked();
        expect(screen.getByLabelText('Cancelar reuniões programadas')).not.toBeChecked();

        await user.click(screen.getByLabelText('Cancelar reuniões programadas'));
        await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Desativar' }));

        expect(form.put).toHaveBeenCalledTimes(1);
        expect(form.put).toHaveBeenCalledWith('/rooms/id-1', expect.any(Object));
        expect(form.lastTransform({ name: 'Sala Azul', capacity: 10, is_active: false })).toEqual({
            name: 'Sala Azul',
            capacity: 10,
            is_active: false,
            scheduled_meetings_action: 'cancel',
        });
    });

    it('shows the inline Inativa warning and hides it when Status is Ativa', async () => {
        const user = userEvent.setup();
        const { rerender } = renderEdit(activeRoom, {}, { data: { is_active: false } });

        expect(screen.getByText(/Ao desativar esta sala, novas reservas serão bloqueadas/)).toBeInTheDocument();
        expect(screen.getByLabelText(/Status/)).toHaveValue('false');

        const form = createForm(activeRoom, { data: { is_active: true } });
        useForm.mockReturnValue(form);
        rerender(<Edit room={activeRoom} future_active_count={0} />);

        expect(screen.queryByText(/Ao desativar esta sala, novas reservas serão bloqueadas/)).not.toBeInTheDocument();
        expect(screen.getByLabelText(/Status/)).toHaveValue('true');

        await user.selectOptions(screen.getByLabelText(/Status/), 'false');
        expect(form.setData).toHaveBeenCalledWith('is_active', false);
    });

    it('saves without a dialog when there are no future actives', async () => {
        const user = userEvent.setup();
        const { form } = renderEdit(activeRoom, { future_active_count: 0 }, { data: { is_active: false } });

        await user.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(form.put).toHaveBeenCalledTimes(1);
        expect(form.lastTransform({ name: 'Sala Azul', capacity: 10, is_active: false })).toEqual({
            name: 'Sala Azul',
            capacity: 10,
            is_active: false,
        });
    });

    it('does not open the dialog when the room is already inactive', async () => {
        const user = userEvent.setup();
        const { form } = renderEdit(inactiveRoom, { future_active_count: 3 });

        await user.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(form.put).toHaveBeenCalledTimes(1);
    });

    it('closes the confirmation from Cancelar or Escape without putting', async () => {
        const user = userEvent.setup();
        const { form } = renderEdit(activeRoom, { future_active_count: 1 }, { data: { is_active: false } });

        await user.click(screen.getByRole('button', { name: 'Salvar' }));
        await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Cancelar' }));

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(form.put).not.toHaveBeenCalled();
        expect(screen.getByRole('button', { name: 'Salvar' })).toHaveFocus();

        await user.click(screen.getByRole('button', { name: 'Salvar' }));
        await user.keyboard('{Escape}');

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(form.put).not.toHaveBeenCalled();
    });

    it('reopens the dialog when the backend returns a 422 decision error', async () => {
        const user = userEvent.setup();
        const { form } = renderEdit(activeRoom, { future_active_count: 0 }, { data: { is_active: false } });

        form.put.mockImplementation((_url, options) => {
            options.onError({
                scheduled_meetings_action: 'Informe o que deseja fazer com as reuniões programadas.',
                future_active_count: '2',
            });
        });

        await user.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(screen.getByText(/Há 2 reuniões futuras/)).toBeInTheDocument();
        expect(screen.getByLabelText('Manter reuniões programadas')).toBeChecked();
    });
});
