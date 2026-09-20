import { router } from '@inertiajs/react';

export function withVisitFailureHandlers(options = {}) {
    const { onInvalid, onException, onFinish, ...rest } = options;

    if (!onInvalid && !onException) {
        return options;
    }

    const unsubscribers = [];

    if (onInvalid) {
        unsubscribers.push(
            router.on('invalid', (event) => {
                if (onInvalid(event) === false) {
                    event.preventDefault();
                }
            }),
        );
    }

    if (onException) {
        unsubscribers.push(
            router.on('exception', (event) => {
                if (onException(event) === false) {
                    event.preventDefault();
                }
            }),
        );
    }

    return {
        ...rest,
        onFinish: (...args) => {
            unsubscribers.forEach((unsubscribe) => unsubscribe());
            onFinish?.(...args);
        },
    };
}
