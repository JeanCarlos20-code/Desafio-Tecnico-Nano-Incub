import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { store } from '../../Services/users';
import BrandPanel, { Wordmark } from './Components/BrandPanel';
import IconTextField from './Components/IconTextField';
import PasswordField from './Components/PasswordField';

const GENERAL_FAILURE = 'Não foi possível criar o usuário. Tente novamente.';

export default function Create() {
    const form = useForm({
        name: '',
        email: '',
        password: '',
    });
    const [generalError, setGeneralError] = useState('');

    function showGeneralFailure() {
        setGeneralError(GENERAL_FAILURE);
    }

    function submit(event) {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        setGeneralError('');

        store(form, {
            onError: (errors) => {
                const order = ['name', 'email', 'password'];
                const first = order.find((field) => errors[field]);

                if (first) {
                    document.getElementById(first)?.focus();
                }
            },
            onHttpException: showGeneralFailure,
            onNetworkError: showGeneralFailure,
        });
    }

    return (
        <div className="min-h-screen overflow-x-hidden bg-slate-100 px-6 py-8">
            <div
                data-layout="register-card"
                className="mx-auto grid max-w-5xl overflow-hidden rounded-3xl bg-white shadow-xl lg:grid-cols-[minmax(0,44%)_minmax(0,56%)]"
            >
                <BrandPanel />

                <form onSubmit={submit} className="flex flex-col justify-center px-6 py-10 sm:px-10 lg:px-12">
                    <div className="mb-6 lg:hidden">
                        <Wordmark className="[&>span:first-child]:text-slate-900" />
                    </div>

                    <h1 className="text-3xl font-bold text-slate-900">Criar usuário</h1>
                    <p className="mt-2 text-sm leading-relaxed text-slate-500">
                        Preencha os dados para criar uma nova conta de administrador.
                    </p>

                    {generalError ? (
                        <p className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" aria-live="polite">
                            {generalError}
                        </p>
                    ) : null}

                    <div className="mt-8 space-y-5">
                        <IconTextField
                            id="name"
                            label="Nome"
                            type="text"
                            value={form.data.name}
                            onChange={(event) => form.setData('name', event.target.value)}
                            placeholder="Seu nome completo"
                            autoComplete="name"
                            error={form.errors.name}
                            icon={<UserIcon />}
                        />
                        <IconTextField
                            id="email"
                            label="E-mail"
                            type="email"
                            value={form.data.email}
                            onChange={(event) => form.setData('email', event.target.value)}
                            placeholder="seu@email.com"
                            autoComplete="email"
                            error={form.errors.email}
                            icon={<EnvelopeIcon />}
                        />
                        <PasswordField
                            id="password"
                            label="Senha"
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                            placeholder="Mínimo de 8 caracteres"
                            autoComplete="new-password"
                            error={form.errors.password}
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="mt-8 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60"
                    >
                        {form.processing ? (
                            <>
                                <Spinner />
                                Criando usuário...
                            </>
                        ) : (
                            'Criar usuário'
                        )}
                    </button>

                    <div className="mt-8 flex items-center gap-3 text-sm text-slate-400">
                        <span className="h-px flex-1 bg-slate-200" />
                        Já tem uma conta?
                        <span className="h-px flex-1 bg-slate-200" />
                    </div>

                    <Link
                        href="/login"
                        className="mt-4 inline-flex w-full items-center justify-center rounded-xl border border-sky-200 bg-white px-4 py-3 text-sm font-semibold text-blue-600 hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        Ir para o login
                    </Link>
                </form>
            </div>
        </div>
    );
}

function UserIcon() {
    return (
        <svg className="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="10" cy="7" r="3" stroke="currentColor" strokeWidth="1.6" />
            <path d="M4 16c1.3-2.5 3.4-4 6-4s4.7 1.5 6 4" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}

function EnvelopeIcon() {
    return (
        <svg className="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect x="3" y="5" width="14" height="10" rx="2" stroke="currentColor" strokeWidth="1.6" />
            <path d="m4 7 6 4 6-4" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}

function Spinner() {
    return (
        <svg className="h-4 w-4 animate-spin" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="10" cy="10" r="7" stroke="currentColor" strokeOpacity="0.25" strokeWidth="2" />
            <path d="M17 10a7 7 0 0 0-7-7" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}
