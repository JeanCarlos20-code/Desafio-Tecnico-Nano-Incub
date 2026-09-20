import { describe, expect, it, vi, beforeEach } from 'vitest';
import { router } from '@inertiajs/react';
import { withVisitFailureHandlers } from './inertiaVisit';

vi.mock('@inertiajs/react', () => ({
    router: {
        on: vi.fn(() => vi.fn()),
    },
}));

beforeEach(() => {
    router.on.mockReset();
    router.on.mockImplementation(() => vi.fn());
});

describe('withVisitFailureHandlers', () => {
    it('subscribes to router.on("invalid") and preventDefault when the handler returns false', () => {
        const onInvalid = vi.fn(() => false);

        withVisitFailureHandlers({ onInvalid });

        expect(router.on).toHaveBeenCalledWith('invalid', expect.any(Function));
        expect(router.on.mock.calls.some(([event]) => event === 'exception')).toBe(false);

        const listener = router.on.mock.calls.find(([event]) => event === 'invalid')[1];
        const event = { preventDefault: vi.fn() };

        listener(event);

        expect(onInvalid).toHaveBeenCalledWith(event);
        expect(event.preventDefault).toHaveBeenCalledTimes(1);
    });

    it('subscribes to router.on("exception") and preventDefault when the handler returns false', () => {
        const onException = vi.fn(() => false);

        withVisitFailureHandlers({ onException });

        expect(router.on).toHaveBeenCalledWith('exception', expect.any(Function));
        expect(router.on.mock.calls.some(([event]) => event === 'invalid')).toBe(false);

        const listener = router.on.mock.calls.find(([event]) => event === 'exception')[1];
        const event = { preventDefault: vi.fn() };

        listener(event);

        expect(onException).toHaveBeenCalledWith(event);
        expect(event.preventDefault).toHaveBeenCalledTimes(1);
    });

    it('unsubscribes on onFinish and still calls the original onFinish', () => {
        const unsubscribeInvalid = vi.fn();
        const unsubscribeException = vi.fn();
        const onFinish = vi.fn();

        router.on.mockImplementation((event) => (event === 'invalid' ? unsubscribeInvalid : unsubscribeException));

        const visit = withVisitFailureHandlers({
            onInvalid: () => false,
            onException: () => false,
            onFinish,
            onSuccess: 'keep',
        });

        expect(visit.onSuccess).toBe('keep');
        expect(visit.onInvalid).toBeUndefined();
        expect(visit.onException).toBeUndefined();

        visit.onFinish('visit');

        expect(unsubscribeInvalid).toHaveBeenCalledTimes(1);
        expect(unsubscribeException).toHaveBeenCalledTimes(1);
        expect(onFinish).toHaveBeenCalledWith('visit');
        expect(unsubscribeInvalid.mock.invocationCallOrder[0]).toBeLessThan(onFinish.mock.invocationCallOrder[0]);
    });

    it('does not register listeners when both handlers are omitted', () => {
        const onFinish = vi.fn();
        const options = { onError: vi.fn(), onFinish };

        const visit = withVisitFailureHandlers(options);

        expect(router.on).not.toHaveBeenCalled();
        expect(visit).toBe(options);
        expect(visit.onFinish).toBe(onFinish);
    });
});
