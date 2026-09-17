import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import '@testing-library/jest-dom/vitest';
import PasswordField from './PasswordField';

afterEach(() => {
    cleanup();
});

function renderField(overrides = {}) {
    return render(
        <PasswordField
            id="password"
            label="Senha"
            value={overrides.value ?? ''}
            onChange={overrides.onChange ?? vi.fn()}
            placeholder="Mínimo de 8 caracteres"
            autoComplete="new-password"
            error={overrides.error}
        />,
    );
}

describe('PasswordField', () => {
    it('renders a required password input with the specified placeholder and autocomplete', () => {
        renderField();

        const input = screen.getByLabelText(/^Senha/);

        expect(input).toHaveAttribute('type', 'password');
        expect(input).toHaveAttribute('placeholder', 'Mínimo de 8 caracteres');
        expect(input).toHaveAttribute('autocomplete', 'new-password');
        expect(input).toHaveAttribute('aria-required', 'true');
        expect(screen.getByRole('button', { name: 'Mostrar senha' })).toBeInTheDocument();
    });

    it('toggles visibility between password and text with Mostrar senha and Ocultar senha', async () => {
        const user = userEvent.setup();
        renderField();

        const input = screen.getByLabelText(/^Senha/);

        await user.click(screen.getByRole('button', { name: 'Mostrar senha' }));

        expect(input).toHaveAttribute('type', 'text');
        expect(screen.getByRole('button', { name: 'Ocultar senha' })).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Ocultar senha' }));

        expect(input).toHaveAttribute('type', 'password');
        expect(screen.getByRole('button', { name: 'Mostrar senha' })).toBeInTheDocument();
    });

    it('toggles visibility from the keyboard', async () => {
        const user = userEvent.setup();
        renderField();

        const input = screen.getByLabelText(/^Senha/);
        const toggle = screen.getByRole('button', { name: 'Mostrar senha' });

        toggle.focus();
        await user.keyboard('{Enter}');

        expect(input).toHaveAttribute('type', 'text');
        expect(screen.getByRole('button', { name: 'Ocultar senha' })).toBeInTheDocument();
    });

    it('shows a backend error with aria-invalid and aria-describedby', () => {
        renderField({ error: 'A senha deve possuir pelo menos 8 caracteres.' });

        const input = screen.getByLabelText(/^Senha/);
        const message = screen.getByText('A senha deve possuir pelo menos 8 caracteres.');

        expect(message).toHaveAttribute('id', 'password-error');
        expect(input).toHaveAttribute('aria-invalid', 'true');
        expect(input).toHaveAttribute('aria-describedby', 'password-error');
    });
});
