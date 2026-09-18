import { Link, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import FlashToast from '../Components/FlashToast';
import { logout } from '../Services/session';

function initialsFromName(name) {
    const parts = String(name ?? '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
}

function pathOf(url) {
    return String(url ?? '').split('?')[0];
}

export default function AppLayout({ children, title }) {
    const page = usePage();
    const form = useForm({});
    const [menuOpen, setMenuOpen] = useState(false);
    const [navOpen, setNavOpen] = useState(false);

    const userName = page.props?.auth?.user?.name ?? '';
    const flash = page.props?.flash ?? {};
    const currentPath = pathOf(page.url);
    const onRooms = currentPath === '/rooms' || currentPath.startsWith('/rooms/');
    const onReservations = currentPath === '/reservations' || currentPath.startsWith('/reservations/');
    const initials = useMemo(() => initialsFromName(userName), [userName]);
    const flashMessage = flash.success || flash.error;

    function submitLogout(event) {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        logout(form);
    }

    return (
        <div className="flex h-screen min-h-screen overflow-x-hidden bg-slate-100 text-slate-900">
            {navOpen ? (
                <button
                    type="button"
                    className="fixed inset-0 z-20 bg-slate-900/40 md:hidden"
                    aria-label="Fechar navegação"
                    onClick={() => setNavOpen(false)}
                />
            ) : null}

            <aside
                className={`fixed inset-y-0 left-0 z-30 flex h-full w-64 shrink-0 flex-col bg-slate-900 text-white transition-transform md:static md:h-full md:translate-x-0 ${
                    navOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
                }`}
            >
                <div className="flex items-center gap-2 px-5 py-6 text-lg font-semibold tracking-tight">
                    <CalendarIcon />
                    ReservaSalas
                </div>
                <nav aria-label="Principal" className="flex w-full flex-1 flex-col gap-1 px-3">
                    <NavItem href="/reservations" current={onReservations}>
                        Reservas
                    </NavItem>
                    <NavItem href="/rooms" current={onRooms}>
                        Salas
                    </NavItem>
                </nav>
            </aside>

            <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                <header className="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
                    <button
                        type="button"
                        className="rounded-md px-2 py-1 text-sm font-medium text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 md:hidden"
                        onClick={() => setNavOpen(true)}
                    >
                        Abrir navegação
                    </button>
                    <div className="relative ml-auto">
                        <button
                            type="button"
                            aria-haspopup="menu"
                            aria-expanded={menuOpen}
                            onClick={() => setMenuOpen((open) => !open)}
                            className="inline-flex items-center gap-2 rounded-md px-2 py-1 text-sm font-medium text-slate-800 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <span
                                aria-hidden="true"
                                className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-800"
                            >
                                {initials}
                            </span>
                            {userName}
                        </button>
                        {menuOpen ? (
                            <div
                                role="menu"
                                className="absolute right-0 z-10 mt-2 w-40 rounded-md border border-slate-200 bg-white py-1 shadow-lg"
                            >
                                <form onSubmit={submitLogout}>
                                    <button
                                        type="submit"
                                        role="menuitem"
                                        disabled={form.processing}
                                        className="block w-full px-3 py-2 text-left text-sm text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        Sair
                                    </button>
                                </form>
                            </div>
                        ) : null}
                    </div>
                </header>

                <main className="min-h-0 flex-1 overflow-y-auto px-4 py-6 sm:px-6">
                    {title ? <h1 className="mb-6 text-2xl font-semibold text-slate-900">{title}</h1> : null}
                    {children}
                </main>
            </div>

            <FlashToast key={flashMessage || 'flash-empty'} message={flashMessage} />
        </div>
    );
}

function NavItem({ href, current, children }) {
    return (
        <Link
            href={href}
            aria-current={current ? 'page' : undefined}
            className={`block w-full rounded-md px-3 py-2 text-left text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-400 ${
                current ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white'
            }`}
        >
            {children}
        </Link>
    );
}

function CalendarIcon() {
    return (
        <svg className="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect x="3" y="4" width="14" height="13" rx="2" stroke="currentColor" strokeWidth="1.6" />
            <path d="M3 8h14" stroke="currentColor" strokeWidth="1.6" />
            <path d="M7 2v3M13 2v3" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}
