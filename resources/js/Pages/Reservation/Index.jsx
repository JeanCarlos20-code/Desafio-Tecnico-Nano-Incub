import { Link, useForm } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { cancel, visitIndex } from '../../Services/reservations';

export default function Index({
    reservations,
    filters = { room_id: '', date: '' },
    filterRooms = [],
    hasAny = false,
    loadError = false,
    loading = false,
}) {
    const data = reservations?.data ?? [];
    const [clientFailed, setClientFailed] = useState(false);
    const failed = Boolean(loadError) || clientFailed;
    const [pending, setPending] = useState(null);
    const [cancelError, setCancelError] = useState('');
    const form = useForm({});
    const backRef = useRef(null);
    const triggerRef = useRef(null);
    const titleId = useId();

    useEffect(() => {
        if (pending) {
            backRef.current?.focus();
        }
    }, [pending]);

    function openCancel(reservation, event) {
        triggerRef.current = event.currentTarget;
        setCancelError('');
        setPending(reservation);
    }

    function closeCancel() {
        if (form.processing) {
            return;
        }

        setPending(null);
        setCancelError('');
        triggerRef.current?.focus();
    }

    function confirmCancel() {
        if (!pending || form.processing) {
            return;
        }

        cancel(form, pending.id, {
            onError: () => {
                setCancelError('Não foi possível cancelar a reserva. Tente novamente.');
            },
            onHttpException: () => {
                setCancelError('Não foi possível cancelar a reserva. Tente novamente.');

                return false;
            },
            onNetworkError: () => {
                setCancelError('Não foi possível cancelar a reserva. Tente novamente.');

                return false;
            },
        });
    }

    function onDialogKeyDown(event) {
        if (event.key === 'Escape') {
            closeCancel();
        }
    }

    function applyFilters(next) {
        const query = {
            date: next.date ?? filters.date,
            page: 1,
        };

        const roomId = next.room_id === undefined ? filters.room_id : next.room_id;

        if (roomId) {
            query.room_id = roomId;
        }

        visitIndex(query);
    }

    function clearFilters() {
        applyFilters({ room_id: '', date: filters.date });
    }

    function retry() {
        setClientFailed(false);
        visitIndex(
            {
                ...(filters.room_id ? { room_id: filters.room_id } : {}),
                ...(filters.date ? { date: filters.date } : {}),
            },
            {
                onError: () => setClientFailed(true),
                onHttpException: () => {
                    setClientFailed(true);

                    return false;
                },
                onNetworkError: () => {
                    setClientFailed(true);

                    return false;
                },
            },
        );
    }

    const empty = !failed && !loading && data.length === 0 && !hasAny;
    const filteredEmpty = !failed && !loading && data.length === 0 && hasAny;

    return (
        <AppLayout>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Reservas</h1>
                    <p className="mt-1 text-sm text-slate-600">Acompanhe e gerencie as reservas de salas.</p>
                </div>
                <Link
                    href="/reservations/create"
                    className="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <PlusIcon />
                    Nova reserva
                </Link>
            </div>

            <div className="mt-6 flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row">
                <div className="sm:w-1/2">
                    <label htmlFor="room_id" className="block text-sm font-medium text-slate-800">
                        Sala
                    </label>
                    <select
                        id="room_id"
                        value={filters.room_id ?? ''}
                        onChange={(event) => applyFilters({ room_id: event.target.value })}
                        className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">Todas as salas</option>
                        {filterRooms.map((room) => (
                            <option key={room.id} value={room.id}>
                                {room.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="sm:w-1/2">
                    <label htmlFor="date" className="block text-sm font-medium text-slate-800">
                        Data
                    </label>
                    <input
                        id="date"
                        type="date"
                        value={filters.date ?? ''}
                        onChange={(event) => applyFilters({ date: event.target.value })}
                        className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>

            {loading ? (
                <p className="mt-6 text-sm text-slate-600" aria-live="polite">
                    Carregando reservas...
                </p>
            ) : null}

            {failed ? (
                <div className="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" role="alert">
                    <p className="text-slate-900">Não foi possível carregar as reservas.</p>
                    <button
                        type="button"
                        onClick={retry}
                        className="mt-3 text-sm font-semibold text-blue-700 hover:text-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        Tente novamente
                    </button>
                </div>
            ) : null}

            {empty ? (
                <div className="mt-6 rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                    <p className="text-base font-medium text-slate-900">Nenhuma reserva cadastrada.</p>
                    <p className="mt-2 text-sm text-slate-600">
                        Crie a primeira reserva para começar a organizar as salas.
                    </p>
                    <Link
                        href="/reservations/create"
                        className="mt-4 inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        Nova reserva
                    </Link>
                </div>
            ) : null}

            {filteredEmpty ? (
                <div className="mt-6 rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                    <p className="text-base font-medium text-slate-900">
                        Nenhuma reserva encontrada para os filtros selecionados.
                    </p>
                    <button
                        type="button"
                        onClick={clearFilters}
                        className="mt-4 text-sm font-semibold text-blue-700 hover:text-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        Limpar filtros
                    </button>
                </div>
            ) : null}

            {!failed && !empty && !filteredEmpty && !loading ? (
                <>
                    <div className="mt-6 hidden min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm md:block">
                        <div className="overflow-x-auto">
                            <table className="w-full table-auto text-left text-sm">
                                <caption className="sr-only">Reservas de salas</caption>
                                <thead className="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th scope="col" className="px-4 py-3 font-medium">
                                            ID
                                        </th>
                                        <th scope="col" className="px-4 py-3 font-medium">
                                            Sala
                                        </th>
                                        <th scope="col" className="px-4 py-3 font-medium">
                                            Responsável
                                        </th>
                                        <th scope="col" className="px-4 py-3 font-medium">
                                            Título
                                        </th>
                                        <th scope="col" className="px-4 py-3 font-medium">
                                            Início
                                        </th>
                                        <th scope="col" className="px-4 py-3 font-medium">
                                            Fim
                                        </th>
                                        <th scope="col" className="px-4 py-3 font-medium">
                                            Participantes
                                        </th>
                                        <th scope="col" className="px-4 py-3 font-medium">
                                            Status
                                        </th>
                                        <th scope="col" className="px-4 py-3 text-center font-medium">
                                            Ações
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.map((reservation) => (
                                        <tr key={reservation.id} className="border-t border-slate-100">
                                            <td className="px-4 py-3 text-slate-900">{reservation.id}</td>
                                            <td className="px-4 py-3 text-slate-900">{reservation.room_name}</td>
                                            <td className="px-4 py-3 text-slate-700">{reservation.responsible}</td>
                                            <td className="px-4 py-3 text-slate-700">{reservation.title}</td>
                                            <td className="px-4 py-3 text-slate-700">{reservation.starts_at}</td>
                                            <td className="px-4 py-3 text-slate-700">{reservation.ends_at}</td>
                                            <td className="px-4 py-3 text-slate-700">{reservation.participants}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    active={reservation.status === 'active'}
                                                    label={reservation.status_label}
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <RowActions reservation={reservation} onCancel={openCancel} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <ul className="mt-6 space-y-3 md:hidden">
                        {data.map((reservation) => (
                            <li key={reservation.id} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <p className="font-medium text-slate-900">{reservation.title}</p>
                                <p className="mt-1 text-sm text-slate-700">ID: {reservation.id}</p>
                                <p className="mt-1 text-sm text-slate-700">Sala: {reservation.room_name}</p>
                                <p className="mt-1 text-sm text-slate-700">Responsável: {reservation.responsible}</p>
                                <p className="mt-1 text-sm text-slate-700">
                                    {reservation.starts_at} – {reservation.ends_at}
                                </p>
                                <p className="mt-1 text-sm text-slate-700">Participantes: {reservation.participants}</p>
                                <div className="mt-2">
                                    <StatusBadge
                                        active={reservation.status === 'active'}
                                        label={reservation.status_label}
                                    />
                                </div>
                                <div className="mt-3">
                                    <RowActions reservation={reservation} onCancel={openCancel} />
                                </div>
                            </li>
                        ))}
                    </ul>
                </>
            ) : null}

            {reservations?.last_page > 1 ? (
                <nav className="mt-6 flex gap-4" aria-label="Paginação">
                    {reservations.prev_page_url ? (
                        <Link href={reservations.prev_page_url} className="text-sm font-medium text-blue-700">
                            Anterior
                        </Link>
                    ) : null}
                    {reservations.next_page_url ? (
                        <Link href={reservations.next_page_url} className="text-sm font-medium text-blue-700">
                            Próxima
                        </Link>
                    ) : null}
                </nav>
            ) : null}

            {pending ? (
                <div
                    className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4"
                    onKeyDown={onDialogKeyDown}
                >
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby={titleId}
                        className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl"
                    >
                        <h2 id={titleId} className="text-lg font-semibold text-slate-900">
                            Cancelar reserva?
                        </h2>
                        <p className="mt-2 text-sm text-slate-700">
                            Tem certeza de que deseja cancelar a reserva da {pending.room_name} em {pending.date}, das{' '}
                            {pending.starts_at} às {pending.ends_at}?
                        </p>
                        <p className="mt-1 text-sm text-slate-600">O horário ficará disponível para uma nova reserva.</p>
                        {cancelError ? (
                            <p className="mt-3 text-sm text-red-700" role="alert">
                                {cancelError}
                            </p>
                        ) : null}
                        <div className="mt-6 flex justify-end gap-3">
                            <button
                                ref={backRef}
                                type="button"
                                onClick={closeCancel}
                                disabled={form.processing}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60"
                            >
                                Voltar
                            </button>
                            <button
                                type="button"
                                onClick={confirmCancel}
                                disabled={form.processing}
                                className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-60"
                            >
                                {form.processing ? 'Cancelando...' : 'Cancelar reserva'}
                            </button>
                        </div>
                    </div>
                </div>
            ) : null}
        </AppLayout>
    );
}

function RowActions({ reservation, onCancel }) {
    if (reservation.status !== 'active') {
        return <span className="text-slate-400">—</span>;
    }

    return (
        <button
            type="button"
            onClick={(event) => onCancel(reservation, event)}
            className="rounded-md bg-red-50 px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500"
        >
            Cancelar
        </button>
    );
}

function StatusBadge({ active, label }) {
    const text = label || (active ? 'Ativa' : 'Ativa');

    return (
        <span
            className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${
                active ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-700'
            }`}
        >
            {text}
        </span>
    );
}

function PlusIcon() {
    return (
        <svg className="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M10 4v12M4 10h12" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
        </svg>
    );
}
