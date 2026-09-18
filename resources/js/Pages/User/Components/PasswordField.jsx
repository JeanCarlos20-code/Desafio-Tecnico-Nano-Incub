import { useState } from 'react';

export default function PasswordField({ id, label, value, onChange, placeholder, autoComplete, error }) {
    const [visible, setVisible] = useState(false);
    const errorId = `${id}-error`;
    const inputType = visible ? 'text' : 'password';
    const toggleLabel = visible ? 'Ocultar senha' : 'Mostrar senha';

    return (
        <div className="space-y-1.5">
            <label htmlFor={id} className="block text-sm font-medium text-slate-800">
                {label}{' '}
                <span className="text-red-500" aria-hidden="true">
                    *
                </span>
            </label>
            <div className="relative">
                <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <LockIcon />
                </span>
                <input
                    id={id}
                    type={inputType}
                    value={value}
                    onChange={onChange}
                    placeholder={placeholder}
                    autoComplete={autoComplete}
                    aria-required="true"
                    aria-invalid={error ? 'true' : 'false'}
                    aria-describedby={error ? errorId : undefined}
                    className={`w-full rounded-xl border bg-white py-3 pl-11 pr-12 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                        error ? 'border-red-500' : 'border-slate-200'
                    }`}
                />
                <button
                    type="button"
                    onClick={() => setVisible((current) => !current)}
                    aria-label={toggleLabel}
                    className="absolute inset-y-0 right-2 flex items-center rounded-md px-2 text-slate-400 hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    {visible ? <EyeOffIcon /> : <EyeIcon />}
                </button>
            </div>
            {error ? (
                <p id={errorId} className="text-sm text-red-600">
                    {error}
                </p>
            ) : null}
        </div>
    );
}

function LockIcon() {
    return (
        <svg className="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect x="4" y="9" width="12" height="8" rx="2" stroke="currentColor" strokeWidth="1.6" />
            <path d="M7 9V7a3 3 0 0 1 6 0v2" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}

function EyeIcon() {
    return (
        <svg className="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path
                d="M2.5 10s2.5-5 7.5-5 7.5 5 7.5 5-2.5 5-7.5 5-7.5-5-7.5-5Z"
                stroke="currentColor"
                strokeWidth="1.6"
            />
            <circle cx="10" cy="10" r="2.2" stroke="currentColor" strokeWidth="1.6" />
        </svg>
    );
}

function EyeOffIcon() {
    return (
        <svg className="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path
                d="M2.5 10s2.5-5 7.5-5 7.5 5 7.5 5-2.5 5-7.5 5-7.5-5-7.5-5Z"
                stroke="currentColor"
                strokeWidth="1.6"
            />
            <circle cx="10" cy="10" r="2.2" stroke="currentColor" strokeWidth="1.6" />
            <path d="M4 16 16 4" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}
