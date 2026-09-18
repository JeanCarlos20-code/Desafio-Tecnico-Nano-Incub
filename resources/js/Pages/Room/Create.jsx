import { Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { store } from '../../Services/rooms';

export default function Create() {
    const form = useForm({
        name: '',
        capacity: '',
        is_active: true,
    });

    function submit(event) {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        store(form);
    }

    return (
        <AppLayout>
            <h1 className="text-2xl font-semibold text-slate-900">Nova sala</h1>
            <p className="mt-1 text-sm text-slate-600">Informe os dados da sala de reunião.</p>
            <RoomForm form={form} onSubmit={submit} submitLabel="Cadastrar sala" processingLabel="Cadastrando..." />
        </AppLayout>
    );
}

export function RoomForm({ form, onSubmit, submitLabel, processingLabel }) {
    return (
        <form onSubmit={onSubmit} className="mt-6 max-w-lg space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <label htmlFor="name" className="block text-sm font-medium text-slate-800">
                    Nome
                </label>
                <input
                    id="name"
                    type="text"
                    value={form.data.name}
                    onChange={(event) => form.setData('name', event.target.value)}
                    aria-invalid={form.errors.name ? 'true' : undefined}
                    aria-describedby={form.errors.name ? 'name-error' : undefined}
                    className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                {form.errors.name ? (
                    <p id="name-error" className="mt-1 text-sm text-red-700">
                        {form.errors.name}
                    </p>
                ) : null}
            </div>
            <div>
                <label htmlFor="capacity" className="block text-sm font-medium text-slate-800">
                    Capacidade
                </label>
                <input
                    id="capacity"
                    type="number"
                    min="1"
                    value={form.data.capacity}
                    onChange={(event) => form.setData('capacity', event.target.value)}
                    aria-invalid={form.errors.capacity ? 'true' : undefined}
                    aria-describedby={form.errors.capacity ? 'capacity-error' : undefined}
                    className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                {form.errors.capacity ? (
                    <p id="capacity-error" className="mt-1 text-sm text-red-700">
                        {form.errors.capacity}
                    </p>
                ) : null}
            </div>
            <div>
                <label htmlFor="is_active" className="block text-sm font-medium text-slate-800">
                    Situação
                </label>
                <select
                    id="is_active"
                    value={form.data.is_active ? '1' : '0'}
                    onChange={(event) => form.setData('is_active', event.target.value === '1')}
                    className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="1">Ativa</option>
                    <option value="0">Inativa</option>
                </select>
            </div>
            <div className="flex gap-3">
                <button
                    type="submit"
                    disabled={form.processing}
                    className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60"
                >
                    {form.processing ? processingLabel : submitLabel}
                </button>
                <Link
                    href="/rooms"
                    className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    Cancelar
                </Link>
            </div>
        </form>
    );
}
