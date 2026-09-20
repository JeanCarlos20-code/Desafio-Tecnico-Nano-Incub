import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { update } from '../../Services/reservations';

const GENERAL_FAILURE = 'Não foi possível salvar a reserva. Tente novamente.';
const FIELDS = ['title', 'responsible'];

export default function Edit({
    id,
    title,
    responsible,
    room_id,
    room_name,
    date,
    start_time,
    end_time,
    participants,
}) {
    const form = useForm({
        title,
        responsible,
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

        form.transform((data) => ({
            title: data.title,
            responsible: data.responsible,
        }));

        update(form, id, {
            onError: (errors) => {
                const first = FIELDS.find((field) => errors[field]);

                if (first) {
                    document.getElementById(first)?.focus();
                }
            },
            onInvalid: showGeneralFailure,
            onException: showGeneralFailure,
        });
    }

    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-lg">
                <h1 className="text-2xl font-semibold text-slate-900">Editar reserva</h1>
                <p className="mt-1 text-sm text-slate-600">
                    Altere somente o título e o responsável. Data, horário, sala e participantes permanecem
                    bloqueados.
                </p>

                {generalError ? (
                    <p className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" aria-live="polite">
                        {generalError}
                    </p>
                ) : null}

                <form
                    onSubmit={submit}
                    className="mt-6 space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <Field id="room_id" label="Sala" required={false}>
                        <input
                            id="room_id"
                            type="text"
                            value={room_name}
                            data-room-id={room_id}
                            disabled
                            className="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-600"
                        />
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

                    <Field id="title" label="Título / finalidade" error={form.errors.title}>
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

                    <Field id="date" label="Data" required={false}>
                        <input
                            id="date"
                            type="date"
                            value={date}
                            disabled
                            className="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-600"
                        />
                    </Field>

                    <Field id="start_time" label="Horário de início" required={false}>
                        <input
                            id="start_time"
                            type="time"
                            value={start_time}
                            disabled
                            className="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-600"
                        />
                    </Field>

                    <Field id="end_time" label="Horário de término" required={false}>
                        <input
                            id="end_time"
                            type="time"
                            value={end_time}
                            disabled
                            className="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-600"
                        />
                    </Field>

                    <Field id="participants" label="Participantes" required={false}>
                        <input
                            id="participants"
                            type="number"
                            value={participants}
                            disabled
                            className="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-600"
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
                            {form.processing ? 'Salvando reserva...' : 'Salvar alterações'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

function Field({ id, label, error, required = true, children }) {
    return (
        <div>
            <label htmlFor={id} className="block text-sm font-medium text-slate-800">
                {label}
                {required ? (
                    <>
                        {' '}
                        <span className="text-red-500" aria-hidden="true">
                            *
                        </span>
                    </>
                ) : null}
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
