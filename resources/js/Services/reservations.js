import { router } from '@inertiajs/react';

export function store(form, options = {}) {
    form.post('/reservations', options);
}

export function cancel(form, reservationId, options = {}) {
    form.patch(`/reservations/${reservationId}/cancel`, options);
}

export function visitIndex(query = {}, options = {}) {
    router.get('/reservations', query, options);
}
