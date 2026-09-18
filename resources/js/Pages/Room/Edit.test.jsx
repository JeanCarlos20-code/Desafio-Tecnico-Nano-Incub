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
    Link: ({ href, children, className, ...props }) => createElement('a', { href, className, ...props }, children),
}));

const room = {
    id: 'id-1',
    name: 'Sala Azul',
    capacity: 10,
    is_active: false,
};

function createForm(overrides = {}) {
    return {
        data: {
            name: room.name,
            capacity: room.capacity,
            is_active: room.is_active,
            ...overrides.data,
        },
        errors: overrides.errors ?? {},
        processing: overrides.processing ?? false,
        setData: vi.fn(),
        put: vi.fn(),
        ...overrides,
    };
}

function mockPage() {
    usePage.mockReturnValue({
        url: `/rooms/${room.id}/edit`,
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
    mockPage();
});

describe('Room/Edit', () => {
    it('prefills and submits only name, capacity, and is_active', async () => {
        const user = userEvent.setup();
        const form = createForm();
        useForm.mockReturnValue(form);

        render(<Edit room={room} />);

        expect(screen.getByLabelText('Nome')).toHaveValue('Sala Azul');
        expect(screen.getByLabelText('Capacidade')).toHaveValue(10);
        expect(screen.getByLabelText('Situação')).toHaveValue('0');
        expect(Object.keys(form.data).sort()).toEqual(['capacity', 'is_active', 'name']);

        await user.click(screen.getByRole('button', { name: 'Salvar alterações' }));

        expect(form.put).toHaveBeenCalledTimes(1);
        expect(form.put).toHaveBeenCalledWith('/rooms/id-1', expect.any(Object));
    });
});
