export const PAST_START_MESSAGE = 'A data não pode estar no passado.';
export const END_NOT_AFTER_START_MESSAGE = 'O término deve ser posterior ao início.';

function pad(value) {
    return String(value).padStart(2, '0');
}

function partsInTimeZone(now, timeZone) {
    const formatter = new Intl.DateTimeFormat('en-US', {
        timeZone: timeZone || 'UTC',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23',
    });

    return Object.fromEntries(formatter.formatToParts(now).map((part) => [part.type, part.value]));
}

export function calendarDateInTimeZone(now, timeZone) {
    const parts = partsInTimeZone(now, timeZone);

    return `${parts.year}-${pad(parts.month)}-${pad(parts.day)}`;
}

export function clockTimeInTimeZone(now, timeZone) {
    const parts = partsInTimeZone(now, timeZone);

    return `${pad(parts.hour)}:${pad(parts.minute)}`;
}

export function minStartTime(selectedDate, now, timeZone) {
    const today = calendarDateInTimeZone(now, timeZone);

    return selectedDate === today ? clockTimeInTimeZone(now, timeZone) : '';
}

function normalizeClockTime(startTime) {
    return startTime.length === 5 ? `${startTime}:00` : startTime;
}

export function isStartInPast(date, startTime, now, timeZone) {
    if (!date || !startTime) {
        return false;
    }

    const today = calendarDateInTimeZone(now, timeZone);

    if (date < today) {
        return true;
    }

    if (date > today) {
        return false;
    }

    const startStamp = `${date} ${normalizeClockTime(startTime)}`;
    const parts = partsInTimeZone(now, timeZone);
    const nowStamp = `${parts.year}-${pad(parts.month)}-${pad(parts.day)} ${pad(parts.hour)}:${pad(parts.minute)}:${pad(parts.second)}`;

    return startStamp < nowStamp;
}

export function isEndNotAfterStart(date, startTime, endTime) {
    if (!date || !startTime || !endTime) {
        return false;
    }

    const startStamp = `${date} ${normalizeClockTime(startTime)}`;
    const endStamp = `${date} ${normalizeClockTime(endTime)}`;

    return endStamp <= startStamp;
}
