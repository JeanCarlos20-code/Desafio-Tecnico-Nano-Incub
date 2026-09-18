export function login(form, options = {}) {
    form.post('/login', {
        onError: (errors) => {
            form.reset('password');
            options.onError?.(errors);
        },
        onHttpException: (response) => {
            return options.onHttpException?.(response) ?? false;
        },
        onNetworkError: (error) => {
            return options.onNetworkError?.(error) ?? false;
        },
    });
}

export function logout(form, options = {}) {
    form.post('/logout', options);
}
