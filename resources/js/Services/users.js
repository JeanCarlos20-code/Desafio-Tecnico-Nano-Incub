export function store(form, options = {}) {
    form.post('/register', {
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
