import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import '@testing-library/jest-dom/vitest';
import BrandPanel from './BrandPanel';

afterEach(() => {
    cleanup();
});

describe('BrandPanel', () => {
    it('shows the ReservaSalas wordmark and specified brand copy', () => {
        render(<BrandPanel />);

        expect(
            screen.getAllByText((content, element) => element?.textContent === 'ReservaSalas').length,
        ).toBeGreaterThan(0);
        expect(screen.getByText(/Salas organizadas/)).toBeInTheDocument();
        expect(screen.getByText(/Reuniões que acontecem/)).toBeInTheDocument();
        expect(
            screen.getByText('Comece agora e ajude a manter o seu time mais produtivo.'),
        ).toBeInTheDocument();
    });

    it('hides the hero on narrow viewports and shows it on wide ones', () => {
        const { container } = render(<BrandPanel />);
        const hero = container.querySelector('[data-layout="register-hero"]');

        expect(hero.tagName).toBe('ASIDE');
        expect(hero.className).toMatch(/hidden/);
        expect(hero.className).toMatch(/lg:flex/);
    });
});
