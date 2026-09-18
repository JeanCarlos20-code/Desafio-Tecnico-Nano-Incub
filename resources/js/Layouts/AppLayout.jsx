import { useForm } from '@inertiajs/react';
import { logout } from '../Services/session';

export default function AppLayout({ children, title }) {
    const form = useForm({});

    function submitLogout(event) {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        logout(form);
    }

    return (
        <div className="min-h-screen overflow-x-hidden bg-zinc-50 text-zinc-900">
            <header className="mx-auto flex max-w-lg items-center justify-end px-4 pt-6">
                <form onSubmit={submitLogout}>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="text-sm font-medium text-zinc-700 hover:text-zinc-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        Sair
                    </button>
                </form>
            </header>
            <main className="mx-auto max-w-lg px-4 py-12">
                {title ? <h1 className="mb-8 text-2xl font-semibold">{title}</h1> : null}
                {children}
            </main>
        </div>
    );
}
