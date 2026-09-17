import { describe, expect, it, vi } from 'vitest';
import { store } from './users';

function createForm(overrides = {}) {
    return {
        reset: vi.fn(),
        post: vi.fn(),
        ...overrides,
    };
}

describe('users store service', () => {
    it('posts name, email, and password through Inertia to /register', () => {
        const form = createForm();

        store(form);

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(form.post).toHaveBeenCalledWith('/register', expect.any(Object));
    });

    it('clears the password field when validation fails', () => {
        const form = createForm();
        const onError = vi.fn();

        store(form, { onError });

        const options = form.post.mock.calls[0][1];
        options.onError({ email: 'Este e-mail já está cadastrado.' });

        expect(form.reset).toHaveBeenCalledWith('password');
        expect(onError).toHaveBeenCalledWith({ email: 'Este e-mail já está cadastrado.' });
    });

    it('returns false from onHttpException so a non-validation failure stays on the page', () => {
        const form = createForm();

        store(form);

        const options = form.post.mock.calls[0][1];

        expect(options.onHttpException()).toBe(false);
        expect(options.onNetworkError()).toBe(false);
    });
});
