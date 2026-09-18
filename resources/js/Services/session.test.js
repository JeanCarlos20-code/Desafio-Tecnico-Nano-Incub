import { describe, expect, it, vi } from 'vitest';
import { login, logout } from './session';

function createForm(overrides = {}) {
    return {
        reset: vi.fn(),
        post: vi.fn(),
        ...overrides,
    };
}

describe('session service', () => {
    it('posts the Inertia form to /login', () => {
        const form = createForm();

        login(form);

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(form.post).toHaveBeenCalledWith('/login', expect.any(Object));
    });

    it('posts the Inertia form to /logout', () => {
        const form = createForm();

        logout(form);

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(form.post).toHaveBeenCalledWith('/logout', expect.any(Object));
    });

    it('clears the password field when login validation or credentials fail', () => {
        const form = createForm();
        const onError = vi.fn();

        login(form, { onError });

        const options = form.post.mock.calls[0][1];
        options.onError({ credentials: 'E-mail ou senha inválidos.' });

        expect(form.reset).toHaveBeenCalledWith('password');
        expect(onError).toHaveBeenCalledWith({ credentials: 'E-mail ou senha inválidos.' });
    });

    it('returns false from onHttpException so a non-validation failure stays on the page', () => {
        const form = createForm();

        login(form);

        const options = form.post.mock.calls[0][1];

        expect(options.onHttpException()).toBe(false);
        expect(options.onNetworkError()).toBe(false);
    });
});
