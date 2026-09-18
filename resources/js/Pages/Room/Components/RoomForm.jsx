import { Link } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';

const COPY = {
    create: {
        title: 'Nova sala',
        support: 'Preencha as informações da sala de reunião.',
    },
    edit: {
        title: 'Editar sala',
        support: 'Atualize as informações da sala de reunião.',
    },
};

export default function RoomForm({
    mode,
    form,
    generalError,
    isActive = true,
    hasRegisteredMeetings = false,
    onSubmit,
    onConfirmDeactivate,
    onConfirmActivate,
}) {
    const copy = COPY[mode];
    const isEdit = mode === 'edit';
    const [dialog, setDialog] = useState(null);
    const [pendingAction, setPendingAction] = useState(null);
    const cancelRef = useRef(null);
    const deactivateRef = useRef(null);
    const activateRef = useRef(null);
    const titleId = useId();
    const descriptionId = useId();
    const processing = form.processing;
    const deactivating = processing && pendingAction === 'deactivate';
    const activating = processing && pendingAction === 'activate';
    const saveLabel = processing && !deactivating && !activating ? 'Salvando...' : 'Salvar';

    useEffect(() => {
        if (dialog) {
            cancelRef.current?.focus();
        }
    }, [dialog]);

    function handleSubmit(event) {
        event.preventDefault();

        if (processing) {
            return;
        }

        setPendingAction('save');
        onSubmit();
    }

    function openDeactivate() {
        if (processing) {
            return;
        }

        setDialog('deactivate');
    }

    function openActivate() {
        if (processing) {
            return;
        }

        setDialog('activate');
    }

    function closeDialog() {
        if (processing) {
            return;
        }

        const previous = dialog;
        setDialog(null);

        if (previous === 'deactivate') {
            deactivateRef.current?.focus();
        }

        if (previous === 'activate') {
            activateRef.current?.focus();
        }
    }

    function confirmDeactivate() {
        if (processing) {
            return;
        }

        setPendingAction('deactivate');
        onConfirmDeactivate();
    }

    function confirmActivate() {
        if (processing) {
            return;
        }

        setPendingAction('activate');
        onConfirmActivate();
    }

    function onDialogKeyDown(event) {
        if (event.key === 'Escape') {
            closeDialog();
        }
    }

    return (
        <div className="mx-auto w-full max-w-lg">
            <h1 className="text-2xl font-semibold text-slate-900">{copy.title}</h1>
            <p className="mt-1 text-sm text-slate-600">{copy.support}</p>

            {generalError ? (
                <p className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" aria-live="polite">
                    {generalError}
                </p>
            ) : null}

            <form
                onSubmit={handleSubmit}
                className="mt-6 space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
            >
                <Field
                    id="name"
                    label="Nome"
                    error={form.errors.name}
                >
                    <input
                        id="name"
                        type="text"
                        maxLength={255}
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                        aria-required="true"
                        aria-invalid={form.errors.name ? 'true' : undefined}
                        aria-describedby={form.errors.name ? 'name-error' : undefined}
                        className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </Field>

                <Field
                    id="capacity"
                    label="Capacidade"
                    error={form.errors.capacity}
                >
                    <input
                        id="capacity"
                        type="number"
                        inputMode="numeric"
                        value={form.data.capacity}
                        onChange={(event) => form.setData('capacity', event.target.value)}
                        aria-required="true"
                        aria-invalid={form.errors.capacity ? 'true' : undefined}
                        aria-describedby={form.errors.capacity ? 'capacity-error' : undefined}
                        className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </Field>

                {isEdit ? (
                    <div>
                        <p className="text-sm font-medium text-slate-800">Status</p>
                        <p className="mt-1 text-sm text-slate-900">{isActive ? 'Ativa' : 'Inativa'}</p>
                        {isActive ? (
                            <button
                                ref={deactivateRef}
                                type="button"
                                onClick={openDeactivate}
                                disabled={processing}
                                className="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-800 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-60"
                            >
                                Desativar sala
                            </button>
                        ) : (
                            <button
                                ref={activateRef}
                                type="button"
                                onClick={openActivate}
                                disabled={processing}
                                className="mt-3 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60"
                            >
                                Ativar sala
                            </button>
                        )}
                    </div>
                ) : null}

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    {processing ? (
                        <button
                            type="button"
                            disabled
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-800 disabled:opacity-60"
                        >
                            Cancelar
                        </button>
                    ) : (
                        <Link
                            href="/rooms"
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            Cancelar
                        </Link>
                    )}
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60"
                    >
                        {saveLabel}
                    </button>
                </div>
            </form>

            {dialog === 'deactivate' ? (
                <div
                    className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4"
                    onKeyDown={onDialogKeyDown}
                >
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby={titleId}
                        aria-describedby={descriptionId}
                        className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl"
                    >
                        <h2 id={titleId} className="text-lg font-semibold text-slate-900">
                            Desativar sala?
                        </h2>
                        <p id={descriptionId} className="mt-2 text-sm text-slate-700">
                            {hasRegisteredMeetings
                                ? 'Há reuniões futuras agendadas nesta sala. O que você deseja fazer com elas?'
                                : 'Ao desativar esta sala, novas reservas serão bloqueadas.'}
                        </p>
                        <fieldset className="mt-4 space-y-2" disabled={processing}>
                            <legend className="text-sm font-medium text-slate-800">
                                {hasRegisteredMeetings
                                    ? 'O que deseja fazer com as reuniões programadas?'
                                    : 'Confirmação'}
                            </legend>
                            {hasRegisteredMeetings ? (
                                <>
                                    <Radio
                                        name="deactivation-choice"
                                        value="keep"
                                        defaultChecked
                                        label="Manter reuniões programadas"
                                    />
                                    <Radio
                                        name="deactivation-choice"
                                        value="cancel"
                                        label="Cancelar reuniões programadas"
                                    />
                                </>
                            ) : (
                                <Radio
                                    name="deactivation-choice"
                                    value="none"
                                    defaultChecked
                                    label="Desativar sala sem reunião"
                                />
                            )}
                        </fieldset>
                        <div className="mt-6 flex justify-end gap-3">
                            <button
                                ref={cancelRef}
                                type="button"
                                onClick={closeDialog}
                                disabled={processing}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={confirmDeactivate}
                                disabled={processing}
                                className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-60"
                            >
                                {deactivating ? 'Desativando...' : 'Desativar'}
                            </button>
                        </div>
                    </div>
                </div>
            ) : null}

            {dialog === 'activate' ? (
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
                            Ativar sala?
                        </h2>
                        <p className="mt-2 text-sm text-slate-700">A sala voltará a ficar disponível para reservas.</p>
                        <div className="mt-6 flex justify-end gap-3">
                            <button
                                ref={cancelRef}
                                type="button"
                                onClick={closeDialog}
                                disabled={processing}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={confirmActivate}
                                disabled={processing}
                                className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60"
                            >
                                {activating ? 'Salvando...' : 'Ativar'}
                            </button>
                        </div>
                    </div>
                </div>
            ) : null}
        </div>
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

function Radio({ name, value, defaultChecked = false, label }) {
    return (
        <label className="flex items-center gap-2 text-sm text-slate-800">
            <input type="radio" name={name} value={value} defaultChecked={defaultChecked} />
            {label}
        </label>
    );
}
