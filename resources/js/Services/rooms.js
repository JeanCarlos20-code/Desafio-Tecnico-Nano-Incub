import { router } from '@inertiajs/react';

export function store(form, options = {}) {
    form.post('/rooms', options);
}

export function update(form, roomId, options = {}) {
    form.put(`/rooms/${roomId}`, options);
}

export function destroy(form, roomId, options = {}) {
    form.delete(`/rooms/${roomId}`, options);
}

export function visitIndex(query = {}, options = {}) {
    router.get('/rooms', query, options);
}
