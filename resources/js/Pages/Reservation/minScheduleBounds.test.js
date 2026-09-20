import { describe, expect, it } from 'vitest';
import {
    calendarDateInTimeZone,
    clockTimeInTimeZone,
    END_NOT_AFTER_START_MESSAGE,
    isEndNotAfterStart,
    isStartInPast,
    minStartTime,
    PAST_START_MESSAGE,
} from './minScheduleBounds';

const NOW_UTC = new Date('2026-09-21T08:00:00.000Z');

describe('minScheduleBounds', () => {
    it('reports today YYYY-MM-DD and current HH:MM in the given timezone', () => {
        expect(calendarDateInTimeZone(NOW_UTC, 'UTC')).toBe('2026-09-21');
        expect(clockTimeInTimeZone(NOW_UTC, 'UTC')).toBe('08:00');
        expect(calendarDateInTimeZone(NOW_UTC, 'America/Sao_Paulo')).toBe('2026-09-21');
        expect(clockTimeInTimeZone(NOW_UTC, 'America/Sao_Paulo')).toBe('05:00');

        const beforeMidnightUtc = new Date('2026-09-21T02:00:00.000Z');
        expect(calendarDateInTimeZone(beforeMidnightUtc, 'America/Sao_Paulo')).toBe('2026-09-20');
        expect(clockTimeInTimeZone(beforeMidnightUtc, 'America/Sao_Paulo')).toBe('23:00');
    });

    it('returns a start-time min only when the selected date is today', () => {
        expect(minStartTime('2026-09-21', NOW_UTC, 'UTC')).toBe('08:00');
        expect(minStartTime('2026-09-22', NOW_UTC, 'UTC')).toBe('');
        expect(minStartTime('', NOW_UTC, 'UTC')).toBe('');
        expect(minStartTime('2026-09-21', NOW_UTC, 'America/Sao_Paulo')).toBe('05:00');
    });

    it('treats starts_at before now as past and equal-to-now as not past', () => {
        expect(PAST_START_MESSAGE).toBe('A data não pode estar no passado.');
        expect(isStartInPast('2026-09-20', '23:00', NOW_UTC, 'UTC')).toBe(true);
        expect(isStartInPast('2026-09-21', '07:00', NOW_UTC, 'UTC')).toBe(true);
        expect(isStartInPast('2026-09-21', '08:00', NOW_UTC, 'UTC')).toBe(false);
        expect(isStartInPast('2026-09-21', '08:00:00', NOW_UTC, 'UTC')).toBe(false);
        expect(isStartInPast('2026-09-22', '00:00', NOW_UTC, 'UTC')).toBe(false);
        expect(isStartInPast('', '08:00', NOW_UTC, 'UTC')).toBe(false);
        expect(isStartInPast('2026-09-21', '', NOW_UTC, 'UTC')).toBe(false);

        const nowWithSeconds = new Date('2026-09-21T08:00:30.000Z');
        expect(isStartInPast('2026-09-21', '08:00', nowWithSeconds, 'UTC')).toBe(true);
    });

    it('treats end not after start as inverted and end after start as valid', () => {
        expect(END_NOT_AFTER_START_MESSAGE).toBe('O término deve ser posterior ao início.');
        expect(isEndNotAfterStart('2026-09-21', '10:00', '09:00')).toBe(true);
        expect(isEndNotAfterStart('2026-09-21', '10:00', '10:00')).toBe(true);
        expect(isEndNotAfterStart('2026-09-21', '10:00', '10:30')).toBe(false);
        expect(isEndNotAfterStart('2026-09-21', '10:00:00', '10:30:00')).toBe(false);
        expect(isEndNotAfterStart('', '10:00', '09:00')).toBe(false);
        expect(isEndNotAfterStart('2026-09-21', '', '09:00')).toBe(false);
        expect(isEndNotAfterStart('2026-09-21', '10:00', '')).toBe(false);
    });
});
