import { Link, useForm } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { destroy, visitIndex } from '../../Services/rooms';

export default function Index({
    rooms,
    filters = { status: 'all' },
    hasAny = false,
    loadError = false,
    loading = false,
}) {
    const data = rooms?.data ?? [];
    const [failed, setFailed] = useState(Boolean(loadError));
    const [pending, setPending] = useState(null);
    const form = useForm({});
    const cancelRef = useRef(null);
    const triggerRef = useRef(null);
    const titleId = useId();

    useEffect(() => {
        if (pending) {
            cancelRef.current?.focus();
        }
    }, [pending]);

    function openDelete(room, event) {
        triggerRef.current = event.currentTarget;
        setPending(room);
    }

    function closeDelete() {
        if (form.processing) {
            return;
        }

        setPending(null);
        triggerRef.current?.focus();
    }

    function confirmDelete() {
        if (!pending || form.processing) {
            return;
        }

        destroy(form, pending.id);
    }

    function onDialogKeyDown(event) {
        if (event.key === 'Escape') {
            closeDelete();
        }
    }

    function retry() {
        setFailed(false);
        visitIndex(statusQuery(filters.status), {
            onError: () => setFailed(true),
            onHttpException: () => {
                setFailed(true);

                return false;
            },
            onNetworkError: () => {
                setFailed(true);

                return false;
            },
        });
    }

    function applyStatus(status) {
        visitIndex({ status, page: 1 });
    }

    const empty = !failed && !loading && data.length === 0 && !hasAny;
    const filteredEmpty = !failed && !loading && data.length === 0 && hasAny;

    return (
        <AppLayout>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Salas</h1>
                    <p className="mt-1 text-sm text-slate-600">Cadastre e gerencie as salas de reunião.</p>
                </div>
                <Link
                    href="/rooms/create"
                    className="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <PlusIcon />
                    Nova sala
                </Link>
            </div>

            {loading ? (
                <p className="mt-6 text-sm text-slate-600" aria-live="polite">
                    Carregando salas...
                </p>
            ) : null}

            {failed ? (
                <div className="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" role="alert">
                    <p className="text-slate-900">Não foi possível carregar as salas.</p>
                    <button
                        type="button"
                        onClick={retry}
                        className="mt-3 text-sm font-semibold text-blue-700 hover:text-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        Tente novamente
                    </button>
                </div>
            ) : null}

            <div className="mt-6 max-w-xs">
                <label htmlFor="status" className="block text-sm font-medium text-slate-800">
                    Status
                </label>
                <select
                    id="status"
                    value={filters.status ?? 'all'}
                    onChange={(event) => applyStatus(event.target.value)}
                    className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="all">Todas</option>
                    <option value="active">Ativas</option>
                    <option value="inactive">Inativas</option>
                </select>
            </div>

            {empty ? (
                <div className="mt-6 rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                    <p className="text-base font-medium text-slate-900">Nenhuma sala cadastrada.</p>
                    <p className="mt-2 text-sm text-slate-600">
                        Cadastre a primeira sala para começar a organizar as reservas.
                    </p>
                    <Link
                        href="/rooms/create"
                        className="mt-4 inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        Nova sala
                    </Link>
                </div>
            ) : null}

            {filteredEmpty ? (
                <div className="mt-6 rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                    <p className="text-base font-medium text-slate-900">
                        Nenhuma sala encontrada para o filtro selecionado.
                    </p>
                </div>
            ) : null}

            {!failed && !empty && !filteredEmpty && !loading ? (
                <>
                    <div className="mt-6 hidden min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm md:block">
                        <div className="overflow-x-auto">
                            <table className="w-full table-auto text-left text-sm">
                                <caption className="sr-only">Salas de reunião cadastradas</caption>
                                <thead className="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th scope="col" className="min-w-0 w-full px-4 py-3 font-medium">
                                            Nome
                                        </th>
                                        <th scope="col" className="w-0 whitespace-nowrap px-4 py-3 text-center font-medium xl:w-[16%]">
                                            Capacidade
                                        </th>
                                        <th scope="col" className="w-0 whitespace-nowrap px-4 py-3 font-medium xl:w-[16%]">
                                            Status
                                        </th>
                                        <th scope="col" className="w-0 whitespace-nowrap px-4 py-3 font-medium xl:w-[16%]">
                                            Criada em
                                        </th>
                                        <th scope="col" className="w-0 whitespace-nowrap px-4 py-3 text-center font-medium xl:w-[16%]">
                                            Ações
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.map((room) => (
                                        <tr key={room.id} className="border-t border-slate-100">
                                            <td className="min-w-0 w-full px-4 py-3 text-slate-900">{room.name}</td>
                                            <td className="w-0 whitespace-nowrap px-4 py-3 text-center text-slate-700 xl:w-[16%]">
                                                {room.capacity}
                                            </td>
                                            <td className="w-0 whitespace-nowrap px-4 py-3 xl:w-[16%]">
                                                <StatusBadge active={room.is_active} label={room.status} />
                                            </td>
                                            <td className="w-0 whitespace-nowrap px-4 py-3 text-slate-700 xl:w-[16%]">
                                                {room.created_at}
                                            </td>
                                            <td className="w-0 whitespace-nowrap px-4 py-3 text-center xl:w-[16%]">
                                                <RowActions room={room} onDelete={openDelete} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <ul className="mt-6 space-y-3 md:hidden">
                        {data.map((room) => (
                            <li key={room.id} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <p className="font-medium text-slate-900">{room.name}</p>
                                <p className="mt-2 text-sm text-slate-700">Capacidade: {room.capacity}</p>
                                <div className="mt-2">
                                    <StatusBadge active={room.is_active} label={room.status} />
                                </div>
                                <p className="mt-2 text-sm text-slate-700">Criada em: {room.created_at}</p>
                                <div className="mt-3">
                                    <RowActions room={room} onDelete={openDelete} />
                                </div>
                            </li>
                        ))}
                    </ul>
                </>
            ) : null}

            {rooms?.last_page > 1 ? (
                <nav className="mt-6 flex gap-4" aria-label="Paginação">
                    {rooms.prev_page_url ? (
                        <Link href={rooms.prev_page_url} className="text-sm font-medium text-blue-700">
                            Anterior
                        </Link>
                    ) : null}
                    {rooms.next_page_url ? (
                        <Link href={rooms.next_page_url} className="text-sm font-medium text-blue-700">
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
                            Excluir sala?
                        </h2>
                        <p className="mt-2 text-sm text-slate-700">
                            Tem certeza de que deseja excluir a sala “{pending.name}”?
                        </p>
                        {pending.has_reservations ? (
                            <p className="mt-2 text-sm text-slate-700">
                                As reuniões ativas desta sala serão canceladas e deixarão de aparecer na listagem.
                            </p>
                        ) : null}
                        <p className="mt-1 text-sm text-slate-600">Esta ação não poderá ser desfeita.</p>
                        <div className="mt-6 flex justify-end gap-3">
                            <button
                                ref={cancelRef}
                                type="button"
                                onClick={closeDelete}
                                disabled={form.processing}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={confirmDelete}
                                disabled={form.processing}
                                className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-60"
                            >
                                {form.processing ? 'Excluindo...' : 'Excluir sala'}
                            </button>
                        </div>
                    </div>
                </div>
            ) : null}
        </AppLayout>
    );
}

function statusQuery(status) {
    return status && status !== 'all' ? { status } : {};
}

function RowActions({ room, onDelete }) {
    return (
        <div className="inline-flex gap-2">
            <Link
                href={`/rooms/${room.id}/edit`}
                aria-label={`Editar ${room.name}`}
                className="inline-flex h-9 w-9 items-center justify-center rounded-md bg-blue-50 text-blue-700 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
                <PencilIcon />
            </Link>
            <button
                type="button"
                aria-label={`Excluir ${room.name}`}
                onClick={(event) => onDelete(room, event)}
                className="inline-flex h-9 w-9 items-center justify-center rounded-md bg-red-50 text-red-700 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500"
            >
                <TrashIcon />
            </button>
        </div>
    );
}

function StatusBadge({ active, label }) {
    const text = label || (active ? 'Ativa' : 'Inativa');

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

function PencilIcon() {
    return (
        <svg className="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path
                d="M4 13.5V16h2.5L15 7.5 12.5 5 4 13.5Z"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function TrashIcon() {
    return (
        <svg className="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M5 7h10M8 7V5h4v2M7 7v8h6V7" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}
