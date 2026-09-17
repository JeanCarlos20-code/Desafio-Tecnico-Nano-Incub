import { createElement } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useForm } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import '@testing-library/jest-dom/vitest';
import Create from './Create';

vi.mock('@inertiajs/react', () => ({
    useForm: vi.fn(),
    Link: ({ href, children, className }) =>
        createElement('a', { href, className }, children),
}));

function createForm(overrides = {}) {
    return {
        data: {
            name: '',
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

function renderCreate(overrides = {}) {
    const form = createForm(overrides);
    useForm.mockReturnValue(form);

    return { form, ...render(<Create />) };
}

afterEach(() => {
    cleanup();
});

beforeEach(() => {
    useForm.mockReset();
});

describe('User/Create register screen', () => {
    it('shows the specified heading, supporting text, primary control, divider, and login control', () => {
        renderCreate();

        expect(screen.getByRole('heading', { name: 'Criar usuário' })).toBeInTheDocument();
        expect(
            screen.getByText('Preencha os dados para criar uma nova conta de administrador.'),
        ).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Criar usuário' })).toBeInTheDocument();
        expect(screen.getByText('Já tem uma conta?')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Ir para o login' })).toBeInTheDocument();
    });

    it('renders required Nome, E-mail, and Senha fields with placeholders, autocomplete, and aria-required', () => {
        renderCreate();

        const name = screen.getByLabelText(/Nome/);
        const email = screen.getByLabelText(/E-mail/);
        const password = screen.getByLabelText(/^Senha/);

        expect(name).toHaveAttribute('placeholder', 'Seu nome completo');
        expect(name).toHaveAttribute('autocomplete', 'name');
        expect(name).toHaveAttribute('aria-required', 'true');

        expect(email).toHaveAttribute('placeholder', 'seu@email.com');
        expect(email).toHaveAttribute('autocomplete', 'email');
        expect(email).toHaveAttribute('aria-required', 'true');

        expect(password).toHaveAttribute('placeholder', 'Mínimo de 8 caracteres');
        expect(password).toHaveAttribute('autocomplete', 'new-password');
        expect(password).toHaveAttribute('aria-required', 'true');
        expect(password).toHaveAttribute('type', 'password');
    });

    it('shows the ReservaSalas brand copy', () => {
        renderCreate();

        expect(
            screen.getAllByText((content, element) => element?.textContent === 'ReservaSalas').length,
        ).toBeGreaterThan(0);
        expect(screen.getByText(/Salas organizadas/)).toBeInTheDocument();
        expect(screen.getByText(/Reuniões que acontecem/)).toBeInTheDocument();
        expect(
            screen.getByText(/Comece agora e ajude/),
        ).toBeInTheDocument();
        expect(screen.getByText(/mais produtivo/)).toBeInTheDocument();
    });

    it('navigates to /login from Ir para o login', () => {
        renderCreate();

        expect(screen.getByRole('link', { name: 'Ir para o login' })).toHaveAttribute('href', '/login');
    });

    it('posts name, email, and password to /register through the users service', async () => {
        const user = userEvent.setup();
        const { form } = renderCreate({
            data: {
                name: 'Ada Lovelace',
                email: 'ada@example.com',
                password: 'secret123',
            },
        });

        await user.click(screen.getByRole('button', { name: 'Criar usuário' }));

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(form.post).toHaveBeenCalledWith('/register', expect.any(Object));
    });

    it('toggles password visibility and exposes Mostrar senha or Ocultar senha', async () => {
        const user = userEvent.setup();
        renderCreate();

        const password = screen.getByLabelText(/^Senha/);
        const toggle = screen.getByRole('button', { name: 'Mostrar senha' });

        expect(password).toHaveAttribute('type', 'password');

        await user.click(toggle);

        expect(password).toHaveAttribute('type', 'text');
        expect(screen.getByRole('button', { name: 'Ocultar senha' })).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Ocultar senha' }));

        expect(password).toHaveAttribute('type', 'password');
        expect(screen.getByRole('button', { name: 'Mostrar senha' })).toBeInTheDocument();
    });

    it('toggles password visibility from the keyboard', async () => {
        const user = userEvent.setup();
        renderCreate();

        const password = screen.getByLabelText(/^Senha/);
        const toggle = screen.getByRole('button', { name: 'Mostrar senha' });

        toggle.focus();
        await user.keyboard('{Enter}');

        expect(password).toHaveAttribute('type', 'text');
        expect(screen.getByRole('button', { name: 'Ocultar senha' })).toBeInTheDocument();
    });

    it('disables the primary button and sets label Criando usuário... while processing', () => {
        renderCreate({ processing: true });

        const submit = screen.getByRole('button', { name: 'Criando usuário...' });
        expect(submit).toBeDisabled();
        expect(screen.queryByRole('button', { name: 'Criar usuário' })).not.toBeInTheDocument();
    });

    it('does not send a second request while processing', async () => {
        const user = userEvent.setup();
        const { form } = renderCreate({ processing: true });

        await user.click(screen.getByRole('button', { name: 'Criando usuário...' }));

        expect(form.post).not.toHaveBeenCalled();
    });

    it('shows backend field errors with aria-invalid and aria-describedby', () => {
        renderCreate({
            errors: {
                name: 'Informe seu nome.',
                email: 'Informe um endereço de e-mail válido.',
                password: 'A senha deve possuir pelo menos 8 caracteres.',
            },
        });

        const name = screen.getByLabelText(/Nome/);
        const email = screen.getByLabelText(/E-mail/);
        const password = screen.getByLabelText(/^Senha/);

        expect(screen.getByText('Informe seu nome.')).toHaveAttribute('id', 'name-error');
        expect(name).toHaveAttribute('aria-invalid', 'true');
        expect(name).toHaveAttribute('aria-describedby', 'name-error');

        expect(screen.getByText('Informe um endereço de e-mail válido.')).toHaveAttribute('id', 'email-error');
        expect(email).toHaveAttribute('aria-invalid', 'true');
        expect(email).toHaveAttribute('aria-describedby', 'email-error');

        expect(screen.getByText('A senha deve possuir pelo menos 8 caracteres.')).toHaveAttribute(
            'id',
            'password-error',
        );
        expect(password).toHaveAttribute('aria-invalid', 'true');
        expect(password).toHaveAttribute('aria-describedby', 'password-error');
    });

    it('keeps name and email values when validation errors are shown', () => {
        renderCreate({
            data: {
                name: 'Ada Lovelace',
                email: 'ada@example.com',
                password: '',
            },
            errors: {
                password: 'A senha deve possuir pelo menos 8 caracteres.',
            },
        });

        expect(screen.getByLabelText(/Nome/)).toHaveValue('Ada Lovelace');
        expect(screen.getByLabelText(/E-mail/)).toHaveValue('ada@example.com');
        expect(screen.getByLabelText(/^Senha/)).toHaveValue('');
    });

    it('clears the password field when validation fails', async () => {
        const user = userEvent.setup();
        const { form } = renderCreate({
            data: {
                name: 'Ada Lovelace',
                email: 'ada@example.com',
                password: 'secret123',
            },
        });

        form.post.mockImplementation((_url, options) => {
            options.onError({ email: 'Este e-mail já está cadastrado.' });
        });

        await user.click(screen.getByRole('button', { name: 'Criar usuário' }));

        expect(form.reset).toHaveBeenCalledWith('password');
    });

    it('moves focus to the first invalid field when validation errors are shown', async () => {
        const user = userEvent.setup();
        const { form } = renderCreate();

        form.post.mockImplementation((_url, options) => {
            options.onError({
                email: 'Informe um endereço de e-mail válido.',
                password: 'A senha deve possuir pelo menos 8 caracteres.',
            });
        });

        await user.click(screen.getByRole('button', { name: 'Criar usuário' }));

        expect(screen.getByLabelText(/E-mail/)).toHaveFocus();
    });

    it('shows a polite general banner on a non-validation server failure', async () => {
        const user = userEvent.setup();
        const { form } = renderCreate();

        let httpExceptionResult;

        form.post.mockImplementation((_url, options) => {
            httpExceptionResult = options.onHttpException();
        });

        await user.click(screen.getByRole('button', { name: 'Criar usuário' }));

        expect(httpExceptionResult).toBe(false);

        const banner = screen.getByText('Não foi possível criar o usuário. Tente novamente.');
        expect(banner).toHaveAttribute('aria-live', 'polite');
        expect(screen.getByRole('heading', { name: 'Criar usuário' })).toBeInTheDocument();
        expect(screen.getByLabelText(/Nome/)).toBeInTheDocument();
    });

    it('uses a two-column card on wide viewports and one column without the hero on narrow viewports', () => {
        const { container } = renderCreate();

        const card = container.querySelector('[data-layout="register-card"]');
        const hero = container.querySelector('[data-layout="register-hero"]');

        expect(card.className).toMatch(/lg:grid-cols-/);
        expect(hero.className).toMatch(/hidden/);
        expect(hero.className).toMatch(/lg:flex/);
        expect(screen.getByRole('button', { name: 'Criar usuário' }).className).toMatch(/w-full/);
        expect(screen.getByRole('link', { name: 'Ir para o login' }).className).toMatch(/w-full/);
        expect(container.firstChild.className).toMatch(/overflow-x-hidden/);
    });
});
