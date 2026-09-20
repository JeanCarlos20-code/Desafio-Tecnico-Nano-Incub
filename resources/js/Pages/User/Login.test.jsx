import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useForm } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import '@testing-library/jest-dom/vitest';
import Login from './Login';

vi.mock('@inertiajs/react', () => ({
    useForm: vi.fn(),
}));

function createForm(overrides = {}) {
    return {
        data: {
            email: '',
            password: '',
            ...overrides.data,
        },
        errors: overrides.errors ?? {},
        processing: overrides.processing ?? false,
        setData: vi.fn(),
        reset: vi.fn(),
        post: vi.fn(),
    };
}

function renderLogin(overrides = {}) {
    const form = createForm(overrides);
    useForm.mockReturnValue(form);

    return { form, ...render(<Login />) };
}

afterEach(() => {
    cleanup();
});

beforeEach(() => {
    useForm.mockReset();
});

describe('User/Login screen', () => {
    it('shows the specified heading, supporting text, and primary control', () => {
        renderLogin();

        expect(screen.getByRole('heading', { name: 'Acesse sua conta' })).toBeInTheDocument();
        expect(screen.getByText('Entre para gerenciar as salas e reservas.')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Entrar' })).toBeInTheDocument();
    });

    it('renders required E-mail and Senha fields with placeholder, autocomplete, and aria-required', () => {
        renderLogin();

        const email = screen.getByLabelText(/E-mail/);
        const password = screen.getByLabelText(/^Senha/);

        expect(email).toHaveAttribute('placeholder', 'seu@email.com');
        expect(email).toHaveAttribute('autocomplete', 'email');
        expect(email).toHaveAttribute('aria-required', 'true');

        expect(password).not.toHaveAttribute('placeholder');
        expect(password).toHaveAttribute('autocomplete', 'current-password');
        expect(password).toHaveAttribute('aria-required', 'true');
        expect(password).toHaveAttribute('type', 'password');
    });

    it('shows the ReservaSalas brand copy and login footer', () => {
        renderLogin();

        expect(
            screen.getAllByText((content, element) => element?.textContent === 'ReservaSalas').length,
        ).toBeGreaterThan(0);
        expect(screen.getByText(/Salas organizadas/)).toBeInTheDocument();
        expect(screen.getByText(/Reuniões que acontecem/)).toBeInTheDocument();
        expect(screen.getByText('Mais produtividade para o seu time.')).toBeInTheDocument();
    });

    it('does not render Não tem uma conta?, Ir para o cadastro, or a /register link', () => {
        renderLogin();

        expect(screen.queryByText('Não tem uma conta?')).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Ir para o cadastro' })).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: /register/i })).not.toBeInTheDocument();
        expect(document.querySelector('a[href="/register"]')).toBeNull();
    });

    it('does not render a password-recovery control', () => {
        renderLogin();

        expect(screen.queryByText(/esqueci/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/recuper/i)).not.toBeInTheDocument();
    });

    it('posts email and password to /login through the session service', async () => {
        const user = userEvent.setup();
        const { form } = renderLogin({
            data: {
                email: 'ada@example.com',
                password: 'secret123',
            },
        });

        await user.click(screen.getByRole('button', { name: 'Entrar' }));

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(form.post).toHaveBeenCalledWith('/login', expect.any(Object));
    });

    it('toggles password visibility and exposes Mostrar senha or Ocultar senha', async () => {
        const user = userEvent.setup();
        renderLogin();

        const password = screen.getByLabelText(/^Senha/);
        const toggle = screen.getByRole('button', { name: 'Mostrar senha' });

        expect(password).toHaveAttribute('type', 'password');

        await user.click(toggle);

        expect(password).toHaveAttribute('type', 'text');
        expect(screen.getByRole('button', { name: 'Ocultar senha' })).toBeInTheDocument();
    });

    it('toggles password visibility from the keyboard', async () => {
        const user = userEvent.setup();
        renderLogin();

        const password = screen.getByLabelText(/^Senha/);
        const toggle = screen.getByRole('button', { name: 'Mostrar senha' });

        toggle.focus();
        await user.keyboard('{Enter}');

        expect(password).toHaveAttribute('type', 'text');
        expect(screen.getByRole('button', { name: 'Ocultar senha' })).toBeInTheDocument();
    });

    it('disables Entrar, shows Entrando..., and a loading indicator while processing', () => {
        renderLogin({ processing: true });

        const submit = screen.getByRole('button', { name: 'Entrando...' });
        expect(submit).toBeDisabled();
        expect(submit.querySelector('svg')).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Entrar' })).not.toBeInTheDocument();
        expect(screen.queryByText('Não tem uma conta?')).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Ir para o cadastro' })).not.toBeInTheDocument();
        expect(document.querySelector('a[href="/register"]')).toBeNull();
    });

    it('does not send a second request while processing', async () => {
        const user = userEvent.setup();
        const { form } = renderLogin({ processing: true });

        await user.click(screen.getByRole('button', { name: 'Entrando...' }));

        expect(form.post).not.toHaveBeenCalled();
    });

    it('shows Laravel field errors with aria-invalid and aria-describedby', () => {
        renderLogin({
            errors: {
                email: 'Informe seu e-mail.',
                password: 'Informe sua senha.',
            },
        });

        const email = screen.getByLabelText(/E-mail/);
        const password = screen.getByLabelText(/^Senha/);

        expect(screen.getByText('Informe seu e-mail.')).toHaveAttribute('id', 'email-error');
        expect(email).toHaveAttribute('aria-invalid', 'true');
        expect(email).toHaveAttribute('aria-describedby', 'email-error');

        expect(screen.getByText('Informe sua senha.')).toHaveAttribute('id', 'password-error');
        expect(password).toHaveAttribute('aria-invalid', 'true');
        expect(password).toHaveAttribute('aria-describedby', 'password-error');
    });

    it('shows the generic credentials error in an aria-live region without marking the email invalid', () => {
        renderLogin({
            data: {
                email: 'ada@example.com',
                password: '',
            },
            errors: {
                credentials: 'E-mail ou senha inválidos.',
            },
        });

        const banner = screen.getByText('E-mail ou senha inválidos.');
        expect(banner).toHaveAttribute('aria-live', 'polite');
        expect(screen.getByLabelText(/E-mail/)).toHaveAttribute('aria-invalid', 'false');
        expect(screen.getByLabelText(/E-mail/)).toHaveValue('ada@example.com');
    });

    it('moves focus to the password field when credentials fail', async () => {
        const user = userEvent.setup();
        const { form } = renderLogin();

        form.post.mockImplementation((_url, options) => {
            options.onError({ credentials: 'E-mail ou senha inválidos.' });
        });

        await user.click(screen.getByRole('button', { name: 'Entrar' }));

        expect(screen.getByLabelText(/^Senha/)).toHaveFocus();
    });

    it('clears the password field when login fails', async () => {
        const user = userEvent.setup();
        const { form } = renderLogin({
            data: {
                email: 'ada@example.com',
                password: 'secret123',
            },
        });

        form.post.mockImplementation((_url, options) => {
            options.onError({ credentials: 'E-mail ou senha inválidos.' });
        });

        await user.click(screen.getByRole('button', { name: 'Entrar' }));

        expect(form.reset).toHaveBeenCalledWith('password');
    });

    it('moves focus to the first invalid field when validation errors are shown', async () => {
        const user = userEvent.setup();
        const { form } = renderLogin();

        form.post.mockImplementation((_url, options) => {
            options.onError({
                email: 'Informe um endereço de e-mail válido.',
                password: 'Informe sua senha.',
            });
        });

        await user.click(screen.getByRole('button', { name: 'Entrar' }));

        expect(screen.getByLabelText(/E-mail/)).toHaveFocus();
    });

    it('shows a polite general banner on a non-validation server failure', async () => {
        const user = userEvent.setup();
        const { form } = renderLogin();

        let httpExceptionResult;

        form.post.mockImplementation((_url, options) => {
            httpExceptionResult = options.onHttpException();
        });

        await user.click(screen.getByRole('button', { name: 'Entrar' }));

        expect(httpExceptionResult).toBe(false);

        const banner = screen.getByText('Não foi possível entrar. Tente novamente.');
        expect(banner).toHaveAttribute('aria-live', 'polite');
        expect(screen.getByRole('heading', { name: 'Acesse sua conta' })).toBeInTheDocument();
        expect(screen.getByLabelText(/E-mail/)).toBeInTheDocument();
    });

    it('uses a two-column card on wide viewports and one column without the hero on narrow viewports', () => {
        const { container } = renderLogin();

        const card = container.querySelector('[data-layout="login-card"]');
        const hero = container.querySelector('[data-layout="register-hero"]');
        const shell = container.firstChild;

        expect(card.className).toMatch(/\bw-full\b/);
        expect(card.className).toMatch(/\bmax-w-6xl\b/);
        expect(card.className).toMatch(/\bxl:max-w-7xl\b/);
        expect(card.className).not.toMatch(/\bmax-w-5xl\b/);
        expect(card.className).toMatch(/lg:grid-cols-/);
        expect(card.className).toMatch(/46%/);
        expect(card.className).toMatch(/54%/);
        expect(hero.className).toMatch(/hidden/);
        expect(hero.className).toMatch(/lg:flex/);
        expect(screen.getByRole('button', { name: 'Entrar' }).className).toMatch(/w-full/);
        expect(shell.className).toMatch(/\bpx-6\b/);
        expect(shell.className).toMatch(/overflow-x-hidden/);
        const form = screen.getByRole('button', { name: 'Entrar' }).closest('form');
        expect(form.textContent).toContain('Reserva');
        expect(form.textContent).toContain('Salas');
    });
});
