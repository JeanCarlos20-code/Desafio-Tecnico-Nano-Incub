export default function BrandPanel({ footer = 'Comece agora e ajude a manter o seu time mais produtivo.' }) {
    return (
        <aside
            data-layout="register-hero"
            className="relative hidden min-h-[28rem] flex-col justify-between overflow-hidden bg-[#0b1b3a] px-10 py-12 text-white lg:flex"
        >
            <div
                className="absolute inset-0 bg-cover bg-center opacity-40"
                style={{ backgroundImage: "url('/images/register-hero.jpg')" }}
                aria-hidden="true"
            />
            <div className="absolute inset-0 bg-[#0b1b3a]/70" aria-hidden="true" />

            <div className="relative flex flex-1 flex-col items-center justify-center text-center">
                <CalendarClockIcon />
                <Wordmark />
                <p className="mt-4 max-w-xs text-sm leading-relaxed text-slate-200">
                    Salas organizadas.
                    <br />
                    Reuniões que acontecem.
                </p>
            </div>

            <p className="relative mt-10 max-w-[14rem] text-left text-sm leading-relaxed text-slate-200">
                {footer}
            </p>
        </aside>
    );
}

export function Wordmark({ className = '' }) {
    return (
        <p className={`text-2xl font-semibold tracking-tight ${className}`.trim()}>
            <span className="text-white">Reserva</span>
            <span className="text-sky-400">Salas</span>
        </p>
    );
}

function CalendarClockIcon() {
    return (
        <svg
            className="mb-4 h-12 w-12 text-sky-400"
            viewBox="0 0 48 48"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <rect x="8" y="12" width="32" height="28" rx="4" stroke="currentColor" strokeWidth="2.5" />
            <path d="M8 20h32" stroke="currentColor" strokeWidth="2.5" />
            <path d="M16 8v8M32 8v8" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" />
            <circle cx="31" cy="31" r="7" stroke="currentColor" strokeWidth="2.5" />
            <path d="M31 28v3.5l2.5 1.5" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" />
        </svg>
    );
}
