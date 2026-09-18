export default function AppLayout({ children, title }) {
    return (
        <div className="min-h-screen bg-zinc-50 text-zinc-900">
            <main className="mx-auto max-w-lg px-4 py-12">
                {title ? <h1 className="mb-8 text-2xl font-semibold">{title}</h1> : null}
                {children}
            </main>
        </div>
    );
}
