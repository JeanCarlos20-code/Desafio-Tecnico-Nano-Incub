import { router } from '@inertiajs/react';
import { withVisitFailureHandlers } from './inertiaVisit';

export function store(form, options = {}) {
    form.post('/reservations', withVisitFailureHandlers(options));
}

export function update(form, id, options = {}) {
    form.put(`/reservations/${id}`, withVisitFailureHandlers(options));
}

export function cancel(form, reservationId, options = {}) {
    form.patch(`/reservations/${reservationId}/cancel`, withVisitFailureHandlers(options));
}

export function visitIndex(query = {}, options = {}) {
    router.get('/reservations', query, withVisitFailureHandlers(options));
}
