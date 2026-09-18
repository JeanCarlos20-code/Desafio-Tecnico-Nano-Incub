import { describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/react';
import { destroy, store, update, visitIndex } from './rooms';

vi.mock('@inertiajs/react', () => ({
    router: {
        get: vi.fn(),
    },
}));

function createForm(overrides = {}) {
    return {
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
        ...overrides,
    };
}

describe('rooms service', () => {
    it('posts create through Inertia to /rooms', () => {
        const form = createForm();

        store(form);

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(form.post).toHaveBeenCalledWith('/rooms', expect.any(Object));
    });

    it('puts update through Inertia to /rooms/{id}', () => {
        const form = createForm();

        update(form, 'room-1');

        expect(form.put).toHaveBeenCalledTimes(1);
        expect(form.put).toHaveBeenCalledWith('/rooms/room-1', expect.any(Object));
    });

    it('deletes through Inertia to /rooms/{id}', () => {
        const form = createForm();

        destroy(form, 'room-1');

        expect(form.delete).toHaveBeenCalledTimes(1);
        expect(form.delete).toHaveBeenCalledWith('/rooms/room-1', expect.any(Object));
    });

    it('retries the list through Inertia GET /rooms', () => {
        visitIndex();

        expect(router.get).toHaveBeenCalledWith('/rooms', {}, expect.any(Object));
    });
});
