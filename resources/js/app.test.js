import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const appSource = readFileSync(resolve(dirname(fileURLToPath(import.meta.url)), './app.jsx'), 'utf8');

describe('Inertia page resolve map', () => {
    it('does not eager-import test or spec files into the client bootstrap', () => {
        expect(appSource).toMatch(/import\.meta\.glob\(/);
        expect(appSource).toContain("'./Pages/**/*.jsx'");
        expect(appSource).toContain("'!./Pages/**/*.test.jsx'");
        expect(appSource).toContain("'!./Pages/**/*.spec.jsx'");
        expect(appSource).not.toMatch(/import\.meta\.glob\(\s*['"]\.\/Pages\/\*\*\/\*\.jsx['"]\s*,/);
    });
});
