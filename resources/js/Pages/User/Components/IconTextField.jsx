export default function IconTextField({
    id,
    label,
    type,
    value,
    onChange,
    placeholder,
    autoComplete,
    error,
    icon,
}) {
    const errorId = `${id}-error`;

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
                    {icon}
                </span>
                <input
                    id={id}
                    type={type}
                    value={value}
                    onChange={onChange}
                    placeholder={placeholder}
                    autoComplete={autoComplete}
                    aria-required="true"
                    aria-invalid={error ? 'true' : 'false'}
                    aria-describedby={error ? errorId : undefined}
                    className={`w-full rounded-xl border bg-white py-3 pl-11 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                        error ? 'border-red-500' : 'border-slate-200'
                    }`}
                />
            </div>
            {error ? (
                <p id={errorId} className="text-sm text-red-600">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
