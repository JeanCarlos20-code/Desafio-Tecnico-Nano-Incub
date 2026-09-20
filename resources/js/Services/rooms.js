import { router } from '@inertiajs/react';
import { withVisitFailureHandlers } from './inertiaVisit';

export function store(form, options = {}) {
    form.post('/rooms', withVisitFailureHandlers(options));
}

export function update(form, roomId, options = {}) {
    form.put(`/rooms/${roomId}`, withVisitFailureHandlers(options));
}

export function destroy(form, roomId, options = {}) {
    form.delete(`/rooms/${roomId}`, withVisitFailureHandlers(options));
}

export function visitIndex(query = {}, options = {}) {
    router.get('/rooms', query, withVisitFailureHandlers(options));
}
