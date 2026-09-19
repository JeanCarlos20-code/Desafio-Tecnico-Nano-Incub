import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { store } from '../../Services/reservations';

const GENERAL_FAILURE = 'Não foi possível salvar a reserva. Tente novamente.';
const FIELDS = ['room_id', 'responsible', 'title', 'starts_at', 'ends_at', 'participants'];

export default function Create({ rooms = [] }) {
    const form = useForm({
        room_id: '',
        responsible: '',
        title: '',
        starts_at: '',
        ends_at: '',
        participants: '',
    });
    const [generalError, setGeneralError] = useState('');

    function showGeneralFailure() {
        setGeneralError(GENERAL_FAILURE);

        return false;
    }

    function submit(event) {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        setGeneralError('');

        store(form, {
            onError: (errors) => {
                const first = FIELDS.find((field) => errors[field]);

                if (first) {
                    document.getElementById(first)?.focus();
                }
            },
            onHttpException: showGeneralFailure,
            onNetworkError: showGeneralFailure,
        });
    }

    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-lg">
                <h1 className="text-2xl font-semibold text-slate-900">Nova reserva</h1>
                <p className="mt-1 text-sm text-slate-600">Preencha as informações da reserva da sala.</p>

                {generalError ? (
                    <p className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" aria-live="polite">
                        {generalError}
                    </p>
                ) : null}

                <form
                    onSubmit={submit}
                    className="mt-6 space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <Field id="room_id" label="Sala" error={form.errors.room_id}>
                        <select
                            id="room_id"
                            value={form.data.room_id}
                            onChange={(event) => form.setData('room_id', event.target.value)}
                            aria-required="true"
                            aria-invalid={form.errors.room_id ? 'true' : undefined}
                            aria-describedby={form.errors.room_id ? 'room_id-error' : undefined}
                            className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">Selecione a sala</option>
                            {rooms.map((room) => (
                                <option key={room.id} value={room.id}>
                                    {room.name}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field id="responsible" label="Responsável" error={form.errors.responsible}>
                        <input
                            id="responsible"
                            type="text"
                            value={form.data.responsible}
                            onChange={(event) => form.setData('responsible', event.target.value)}
                            aria-required="true"
                            aria-invalid={form.errors.responsible ? 'true' : undefined}
                            aria-describedby={form.errors.responsible ? 'responsible-error' : undefined}
                            className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </Field>

                    <Field id="title" label="Título" error={form.errors.title}>
                        <input
                            id="title"
                            type="text"
                            value={form.data.title}
                            onChange={(event) => form.setData('title', event.target.value)}
                            aria-required="true"
                            aria-invalid={form.errors.title ? 'true' : undefined}
                            aria-describedby={form.errors.title ? 'title-error' : undefined}
                            className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </Field>

                    <Field id="starts_at" label="Início" error={form.errors.starts_at}>
                        <input
                            id="starts_at"
                            type="datetime-local"
                            value={form.data.starts_at}
                            onChange={(event) => form.setData('starts_at', event.target.value)}
                            aria-required="true"
                            aria-invalid={form.errors.starts_at ? 'true' : undefined}
                            aria-describedby={form.errors.starts_at ? 'starts_at-error' : undefined}
                            className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </Field>

                    <Field id="ends_at" label="Fim" error={form.errors.ends_at}>
                        <input
                            id="ends_at"
                            type="datetime-local"
                            value={form.data.ends_at}
                            onChange={(event) => form.setData('ends_at', event.target.value)}
                            aria-required="true"
                            aria-invalid={form.errors.ends_at ? 'true' : undefined}
                            aria-describedby={form.errors.ends_at ? 'ends_at-error' : undefined}
                            className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </Field>

                    <Field id="participants" label="Participantes" error={form.errors.participants}>
                        <input
                            id="participants"
                            type="number"
                            inputMode="numeric"
                            value={form.data.participants}
                            onChange={(event) => form.setData('participants', event.target.value)}
                            aria-required="true"
                            aria-invalid={form.errors.participants ? 'true' : undefined}
                            aria-describedby={form.errors.participants ? 'participants-error' : undefined}
                            className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </Field>

                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        {form.processing ? (
                            <button
                                type="button"
                                disabled
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-800 disabled:opacity-60"
                            >
                                Cancelar
                            </button>
                        ) : (
                            <Link
                                href="/reservations"
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                Cancelar
                            </Link>
                        )}
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60"
                        >
                            {form.processing ? 'Salvando...' : 'Salvar'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

function Field({ id, label, error, children }) {
    return (
        <div>
            <label htmlFor={id} className="block text-sm font-medium text-slate-800">
                {label}{' '}
                <span className="text-red-500" aria-hidden="true">
                    *
                </span>
            </label>
            {children}
            {error ? (
                <p id={`${id}-error`} className="mt-1 text-sm text-red-700">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
