import { useEffect, useState } from 'react';

export const FLASH_TOAST_DURATION_MS = 4000;

export default function FlashToast({ message, durationMs = FLASH_TOAST_DURATION_MS }) {
    const [visible, setVisible] = useState(() => Boolean(message));

    useEffect(() => {
        if (!message) {
            return undefined;
        }

        const timer = window.setTimeout(() => {
            setVisible(false);
        }, durationMs);

        return () => window.clearTimeout(timer);
    }, [message, durationMs]);

    if (!visible || !message) {
        return null;
    }

    return (
        <div
            role="status"
            aria-live="polite"
            className="fixed bottom-4 right-4 z-50 flex max-w-sm items-start gap-3 rounded-lg border border-blue-700 bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-lg"
        >
            <p className="min-w-0 flex-1">{message}</p>
            <button
                type="button"
                aria-label="Fechar"
                onClick={() => setVisible(false)}
                className="inline-flex shrink-0 rounded-md p-0.5 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-white"
            >
                <svg className="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M5 5l10 10M15 5L5 15" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
                </svg>
            </button>
        </div>
    );
}
