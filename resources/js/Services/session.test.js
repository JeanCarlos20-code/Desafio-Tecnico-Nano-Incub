import { describe, expect, it, vi, beforeEach } from 'vitest';
import { router } from '@inertiajs/react';
import { login, logout } from './session';

vi.mock('@inertiajs/react', () => ({
    router: {
        on: vi.fn(() => vi.fn()),
    },
}));

function createForm(overrides = {}) {
    return {
        reset: vi.fn(),
        post: vi.fn(),
        ...overrides,
    };
}

function fireVisitEvent(name) {
    const listener = router.on.mock.calls.find(([event]) => event === name)?.[1];
    const event = { preventDefault: vi.fn() };

    listener?.(event);

    return event;
}

beforeEach(() => {
    router.on.mockReset();
    router.on.mockImplementation(() => vi.fn());
});

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

    it('treats omitted invalid/exception returns as false so a non-validation failure stays on the page', () => {
        const form = createForm();

        login(form);

        const invalidEvent = fireVisitEvent('invalid');
        const exceptionEvent = fireVisitEvent('exception');

        expect(invalidEvent.preventDefault).toHaveBeenCalledTimes(1);
        expect(exceptionEvent.preventDefault).toHaveBeenCalledTimes(1);
        expect(form.post.mock.calls[0][1].onHttpException).toBeUndefined();
        expect(form.post.mock.calls[0][1].onNetworkError).toBeUndefined();
    });
});
