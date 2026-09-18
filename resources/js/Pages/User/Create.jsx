import { useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { store } from '../../Services/users';

export default function Create() {
    const form = useForm({
        name: '',
        email: '',
        password: '',
    });

    function submit(event) {
        event.preventDefault();
        store(form);
    }

    return (
        <AppLayout title="Cadastrar usuário">
            <form onSubmit={submit} className="space-y-4">
                <div className="space-y-1">
                    <label htmlFor="name" className="block text-sm font-medium">
                        Nome
                    </label>
                    <input
                        id="name"
                        type="text"
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                        className="w-full rounded-md border border-zinc-300 bg-white px-3 py-2"
                    />
                    {form.errors.name ? (
                        <p className="text-sm text-red-600">{form.errors.name}</p>
                    ) : null}
                </div>

                <div className="space-y-1">
                    <label htmlFor="email" className="block text-sm font-medium">
                        E-mail
                    </label>
                    <input
                        id="email"
                        type="email"
                        value={form.data.email}
                        onChange={(event) => form.setData('email', event.target.value)}
                        className="w-full rounded-md border border-zinc-300 bg-white px-3 py-2"
                    />
                    {form.errors.email ? (
                        <p className="text-sm text-red-600">{form.errors.email}</p>
                    ) : null}
                </div>

                <div className="space-y-1">
                    <label htmlFor="password" className="block text-sm font-medium">
                        Senha
                    </label>
                    <input
                        id="password"
                        type="password"
                        value={form.data.password}
                        onChange={(event) => form.setData('password', event.target.value)}
                        className="w-full rounded-md border border-zinc-300 bg-white px-3 py-2"
                    />
                    {form.errors.password ? (
                        <p className="text-sm text-red-600">{form.errors.password}</p>
                    ) : null}
                </div>

                <button
                    type="submit"
                    disabled={form.processing}
                    className="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Cadastrar
                </button>
            </form>
        </AppLayout>
    );
}
