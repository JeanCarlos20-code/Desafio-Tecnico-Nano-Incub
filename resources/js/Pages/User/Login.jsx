import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { login } from '../../Services/session';
import BrandPanel, { Wordmark } from './Components/BrandPanel';
import IconTextField from './Components/IconTextField';
import PasswordField from './Components/PasswordField';

const GENERAL_FAILURE = 'Não foi possível entrar. Tente novamente.';

export default function Login() {
    const form = useForm({
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

        login(form, {
            onError: (errors) => {
                if (errors.credentials) {
                    document.getElementById('password')?.focus();
                    return;
                }

                const order = ['email', 'password'];
                const first = order.find((field) => errors[field]);

                if (first) {
                    document.getElementById(first)?.focus();
                }
            },
            onHttpException: showGeneralFailure,
            onNetworkError: showGeneralFailure,
        });
    }

    const credentialsError = form.errors.credentials;
    const bannerMessage = generalError || credentialsError;

    return (
        <div className="flex min-h-dvh items-center justify-center-safe overflow-x-hidden bg-slate-100 px-6 py-8">
            <div
                data-layout="login-card"
                className="mx-auto grid max-w-5xl overflow-hidden rounded-3xl bg-white shadow-xl lg:grid-cols-[minmax(0,46%)_minmax(0,54%)]"
            >
                <BrandPanel footer="Mais produtividade para o seu time." />

                <form onSubmit={submit} className="flex flex-col justify-center px-6 py-10 sm:px-10 lg:px-12">
                    <div className="mb-6 lg:hidden">
                        <Wordmark className="[&>span:first-child]:text-slate-900" />
                    </div>

                    <h1 className="text-3xl font-bold text-slate-900">Acesse sua conta</h1>
                    <p className="mt-2 text-sm leading-relaxed text-slate-500">
                        Entre para gerenciar as salas e reservas.
                    </p>

                    {bannerMessage ? (
                        <p className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" aria-live="polite">
                            {bannerMessage}
                        </p>
                    ) : null}

                    <div className="mt-8 space-y-5">
                        <IconTextField
                            id="email"
                            label="E-mail"
                            type="email"
                            value={form.data.email}
                            onChange={(event) => form.setData('email', event.target.value)}
                            placeholder="seu@email.com"
                            autoComplete="email"
                            autoFocus={!form.data.email}
                            error={form.errors.email}
                            icon={<EnvelopeIcon />}
                        />
                        <PasswordField
                            id="password"
                            label="Senha"
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                            autoComplete="current-password"
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
                                Entrando...
                            </>
                        ) : (
                            'Entrar'
                        )}
                    </button>

                    <div className="mt-8 flex items-center gap-3 text-sm text-slate-400">
                        <span className="h-px flex-1 bg-slate-200" />
                        Não tem uma conta?
                        <span className="h-px flex-1 bg-slate-200" />
                    </div>

                    <Link
                        href="/register"
                        className="mt-4 inline-flex w-full items-center justify-center rounded-xl border border-sky-200 bg-white px-4 py-3 text-sm font-semibold text-blue-600 hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        Ir para o cadastro
                    </Link>
                </form>
            </div>
        </div>
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
