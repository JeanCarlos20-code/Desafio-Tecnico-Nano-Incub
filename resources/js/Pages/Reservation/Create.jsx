import { Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { store } from '../../Services/reservations';
import {
    calendarDateInTimeZone,
    END_NOT_AFTER_START_MESSAGE,
    isEndNotAfterStart,
    isStartInPast,
    minStartTime,
    PAST_START_MESSAGE,
} from './minScheduleBounds';

const GENERAL_FAILURE = 'Não foi possível salvar a reserva. Tente novamente.';
const FIELDS = ['room_id', 'responsible', 'title', 'date', 'start_time', 'end_time', 'participants'];

export default function Create({ rooms = [], timezone = 'UTC' }) {
    const form = useForm({
        room_id: '',
        responsible: '',
        title: '',
        date: '',
        start_time: '',
        end_time: '',
        participants: '',
    });
    const [generalError, setGeneralError] = useState('');
    const [pastStartError, setPastStartError] = useState('');
    const [endOrderError, setEndOrderError] = useState('');
    const now = new Date();
    const minDate = calendarDateInTimeZone(now, timezone);
    const minStart = minStartTime(form.data.date, now, timezone);
    const selectedRoom = useMemo(
        () => rooms.find((room) => room.id === form.data.room_id) ?? null,
        [rooms, form.data.room_id],
    );
    const participantMax = selectedRoom?.capacity ?? undefined;

    function showGeneralFailure() {
        setGeneralError(GENERAL_FAILURE);

        return false;
    }

    function combineDateTime(date, time) {
        const normalized = time.length === 5 ? `${time}:00` : time;

        return `${date} ${normalized}`;
    }

    function setParticipants(value) {
        if (participantMax !== undefined) {
            const parsed = Number(value);

            if (Number.isInteger(parsed) && parsed > participantMax) {
                return;
            }
        }

        form.setData('participants', value);
    }

    function submit(event) {
        event.preventDefault();

        if (form.processing || rooms.length === 0) {
            return;
        }

        setGeneralError('');

        if (isStartInPast(form.data.date, form.data.start_time, new Date(), timezone)) {
            setPastStartError(PAST_START_MESSAGE);
            setEndOrderError('');
            const fieldId = form.data.date && form.data.date < minDate ? 'date' : 'start_time';
            document.getElementById(fieldId)?.focus();

            return;
        }

        setPastStartError('');

        if (isEndNotAfterStart(form.data.date, form.data.start_time, form.data.end_time)) {
            setEndOrderError(END_NOT_AFTER_START_MESSAGE);
            document.getElementById('end_time')?.focus();

            return;
        }

        setEndOrderError('');

        form.transform((data) => ({
            room_id: data.room_id,
            responsible: data.responsible,
            title: data.title,
            starts_at: combineDateTime(data.date, data.start_time),
            ends_at: combineDateTime(data.date, data.end_time),
            participants: data.participants,
        }));

        store(form, {
            onError: (errors) => {
                const first = [...FIELDS, 'starts_at', 'ends_at'].find((field) => errors[field]);

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
                <p className="mt-1 text-sm text-slate-600">Preencha os dados da reserva.</p>

                {generalError ? (
                    <p className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" aria-live="polite">
                        {generalError}
                    </p>
                ) : null}

                {rooms.length === 0 ? (
                    <div className="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <p className="text-slate-900">Nenhuma sala ativa está disponível.</p>
                        <p className="mt-2 text-sm text-slate-600">
                            Cadastre ou reative uma sala antes de criar uma reserva.
                        </p>
                        <Link
                            href="/rooms/create"
                            className="mt-4 inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            Nova sala
                        </Link>
                    </div>
                ) : (
                    <form
                        onSubmit={submit}
                        noValidate
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
                                        {room.name} — capacidade para {room.capacity} pessoas
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

                        <Field id="date" label="Data" error={form.errors.date || form.errors.starts_at || pastStartError}>
                            <input
                                id="date"
                                type="date"
                                value={form.data.date}
                                min={minDate}
                                onChange={(event) => form.setData('date', event.target.value)}
                                aria-required="true"
                                aria-invalid={form.errors.date || form.errors.starts_at || pastStartError ? 'true' : undefined}
                                className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </Field>

                        <Field id="start_time" label="Horário de início" error={form.errors.start_time || form.errors.starts_at || pastStartError}>
                            <input
                                id="start_time"
                                type="time"
                                value={form.data.start_time}
                                min={minStart || undefined}
                                onChange={(event) => form.setData('start_time', event.target.value)}
                                aria-required="true"
                                aria-invalid={form.errors.start_time || form.errors.starts_at || pastStartError ? 'true' : undefined}
                                className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </Field>

                        <Field id="end_time" label="Horário de término" error={form.errors.end_time || form.errors.ends_at || endOrderError}>
                            <input
                                id="end_time"
                                type="time"
                                value={form.data.end_time}
                                min={form.data.start_time || undefined}
                                onChange={(event) => form.setData('end_time', event.target.value)}
                                aria-required="true"
                                aria-invalid={form.errors.end_time || form.errors.ends_at || endOrderError ? 'true' : undefined}
                                className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </Field>

                        <Field id="participants" label="Participantes" error={form.errors.participants}>
                            <input
                                id="participants"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={participantMax}
                                value={form.data.participants}
                                onChange={(event) => setParticipants(event.target.value)}
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
                                {form.processing ? 'Criando reserva...' : 'Criar reserva'}
                            </button>
                        </div>
                    </form>
                )}
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
