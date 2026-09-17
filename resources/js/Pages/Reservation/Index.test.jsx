import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import '@testing-library/jest-dom/vitest';
import Index from './Index';

afterEach(() => {
    cleanup();
});

describe('Reservation/Index stub', () => {
    it('renders the post-register placeholder heading', () => {
        render(<Index />);

        expect(screen.getByRole('heading', { name: 'Reservas' })).toBeInTheDocument();
    });
});
