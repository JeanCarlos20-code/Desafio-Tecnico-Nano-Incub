import { withVisitFailureHandlers } from './inertiaVisit';

export function login(form, options = {}) {
    form.post(
        '/login',
        withVisitFailureHandlers({
            onError: (errors) => {
                form.reset('password');
                options.onError?.(errors);
            },
            onInvalid: (response) => {
                return options.onInvalid?.(response) ?? false;
            },
            onException: (error) => {
                return options.onException?.(error) ?? false;
            },
        }),
    );
}

export function logout(form, options = {}) {
    form.post('/logout', withVisitFailureHandlers(options));
}
