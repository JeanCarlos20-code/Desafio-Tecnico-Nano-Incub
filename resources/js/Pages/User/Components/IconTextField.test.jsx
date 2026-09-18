import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import '@testing-library/jest-dom/vitest';
import IconTextField from './IconTextField';

afterEach(() => {
    cleanup();
});

function renderField(overrides = {}) {
    const onChange = overrides.onChange ?? vi.fn();

    return {
        onChange,
        ...render(
            <IconTextField
                id="email"
                label="E-mail"
                type="email"
                value={overrides.value ?? ''}
                onChange={onChange}
                placeholder="seu@email.com"
                autoComplete="email"
                error={overrides.error}
                icon={<span data-testid="field-icon" />}
            />,
        ),
    };
}

describe('IconTextField', () => {
    it('associates the label, placeholder, autocomplete, and aria-required with the input', () => {
        renderField();

        const input = screen.getByLabelText(/E-mail/);

        expect(input).toHaveAttribute('id', 'email');
        expect(input).toHaveAttribute('placeholder', 'seu@email.com');
        expect(input).toHaveAttribute('autocomplete', 'email');
        expect(input).toHaveAttribute('aria-required', 'true');
        expect(input).toHaveAttribute('type', 'email');
        expect(input).toHaveAttribute('aria-invalid', 'false');
        expect(input).not.toHaveAttribute('aria-describedby');
        expect(screen.getByTestId('field-icon')).toBeInTheDocument();
    });

    it('shows a backend error with aria-invalid and aria-describedby', () => {
        renderField({ error: 'Informe um endereço de e-mail válido.' });

        const input = screen.getByLabelText(/E-mail/);
        const message = screen.getByText('Informe um endereço de e-mail válido.');

        expect(message).toHaveAttribute('id', 'email-error');
        expect(input).toHaveAttribute('aria-invalid', 'true');
        expect(input).toHaveAttribute('aria-describedby', 'email-error');
    });

    it('calls onChange when the visitor types', async () => {
        const user = userEvent.setup();
        const { onChange } = renderField();

        await user.type(screen.getByLabelText(/E-mail/), 'a');

        expect(onChange).toHaveBeenCalled();
    });
});
