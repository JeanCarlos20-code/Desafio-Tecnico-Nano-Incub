import { describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/react';
import { cancel, store, update, visitIndex } from './reservations';

vi.mock('@inertiajs/react', () => ({
    router: {
        get: vi.fn(),
        on: vi.fn(() => vi.fn()),
    },
}));

function createForm(overrides = {}) {
    return {
        post: vi.fn(),
        patch: vi.fn(),
        put: vi.fn(),
        ...overrides,
    };
}

describe('reservations service', () => {
    it('posts create through Inertia to /reservations', () => {
        const form = createForm();

        store(form);

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(form.post).toHaveBeenCalledWith('/reservations', expect.any(Object));
    });

    it('puts update through Inertia to /reservations/{id}', () => {
        const form = createForm();

        update(form, 'res-1');

        expect(form.put).toHaveBeenCalledTimes(1);
        expect(form.put).toHaveBeenCalledWith('/reservations/res-1', expect.any(Object));
    });

    it('patches cancel through Inertia to /reservations/{id}/cancel', () => {
        const form = createForm();

        cancel(form, 'res-1');

        expect(form.patch).toHaveBeenCalledTimes(1);
        expect(form.patch).toHaveBeenCalledWith('/reservations/res-1/cancel', expect.any(Object));
    });

    it('visits the list through Inertia GET /reservations with filters', () => {
        visitIndex({ room_id: 'room-1', date: '2026-09-21', page: 1 });

        expect(router.get).toHaveBeenCalledWith(
            '/reservations',
            { room_id: 'room-1', date: '2026-09-21', page: 1 },
            expect.any(Object),
        );
    });
});
