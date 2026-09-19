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

const INACTIVE_WARNING =
    'Ao desativar esta sala, novas reservas serão bloqueadas. Caso existam reuniões futuras, você poderá mantê-las ou cancelá-las.';

export default function RoomForm({
    mode,
    form,
    generalError,
    isActive = true,
    futureActiveCount = 0,
    reopenDialog = false,
    onSubmit,
    onConfirmDeactivate,
    onDismissReopen,
}) {
    const copy = COPY[mode];
    const isEdit = mode === 'edit';
    const [dialog, setDialog] = useState(null);
    const [meetingAction, setMeetingAction] = useState('keep');
    const [pendingAction, setPendingAction] = useState(null);
    const cancelRef = useRef(null);
    const saveRef = useRef(null);
    const titleId = useId();
    const descriptionId = useId();
    const processing = form.processing;
    const deactivating = processing && pendingAction === 'deactivate';
    const saveLabel = processing && !deactivating ? 'Salvando...' : 'Salvar';
    const selectedInactive = isEdit && form.data.is_active === false;
    const showDeactivateDialog = dialog === 'deactivate' || (reopenDialog && futureActiveCount > 0);

    useEffect(() => {
        if (showDeactivateDialog) {
            cancelRef.current?.focus();
        }
    }, [showDeactivateDialog]);

    function handleSubmit(event) {
        event.preventDefault();

        if (processing) {
            return;
        }

        const switchingToInactive = isEdit && isActive && form.data.is_active === false;

        if (switchingToInactive && futureActiveCount > 0) {
            setMeetingAction('keep');
            setDialog('deactivate');

            return;
        }

        setPendingAction('save');
        onSubmit();
    }

    function closeDialog() {
        if (processing) {
            return;
        }

        setDialog(null);
        onDismissReopen?.();
        saveRef.current?.focus();
    }

    function confirmDeactivate() {
        if (processing) {
            return;
        }

        setPendingAction('deactivate');
        onConfirmDeactivate(meetingAction);
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
                <Field id="name" label="Nome" error={form.errors.name}>
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

                <Field id="capacity" label="Capacidade" error={form.errors.capacity}>
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
                        <label htmlFor="is_active" className="block text-sm font-medium text-slate-800">
                            Status{' '}
                            <span className="text-red-500" aria-hidden="true">
                                *
                            </span>
                        </label>
                        <select
                            id="is_active"
                            value={form.data.is_active ? 'true' : 'false'}
                            onChange={(event) => form.setData('is_active', event.target.value === 'true')}
                            aria-required="true"
                            className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="true">Ativa</option>
                            <option value="false">Inativa</option>
                        </select>
                        {selectedInactive ? (
                            <p
                                className="mt-3 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                                aria-live="polite"
                            >
                                <WarningIcon />
                                <span>{INACTIVE_WARNING}</span>
                            </p>
                        ) : null}
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
                        ref={saveRef}
                        type="submit"
                        disabled={processing}
                        className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60"
                    >
                        {saveLabel}
                    </button>
                </div>
            </form>

            {showDeactivateDialog ? (
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
                            Há {futureActiveCount} reuniões futuras agendadas nesta sala. O que você deseja fazer com
                            elas?
                        </p>
                        <fieldset className="mt-4 space-y-2" disabled={processing}>
                            <legend className="text-sm font-medium text-slate-800">
                                O que deseja fazer com as reuniões programadas?
                            </legend>
                            <Radio
                                name="deactivation-choice"
                                value="keep"
                                checked={meetingAction === 'keep'}
                                onChange={() => setMeetingAction('keep')}
                                label="Manter reuniões programadas"
                            />
                            <Radio
                                name="deactivation-choice"
                                value="cancel"
                                checked={meetingAction === 'cancel'}
                                onChange={() => setMeetingAction('cancel')}
                                label="Cancelar reuniões programadas"
                            />
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

function Radio({ name, value, checked, onChange, label }) {
    return (
        <label className="flex items-center gap-2 text-sm text-slate-800">
            <input type="radio" name={name} value={value} checked={checked} onChange={onChange} />
            {label}
        </label>
    );
}

function WarningIcon() {
    return (
        <svg className="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path
                d="M10 4.5 17 16H3L10 4.5Z"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinejoin="round"
            />
            <path d="M10 8.5v3.5M10 14h.01" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}
