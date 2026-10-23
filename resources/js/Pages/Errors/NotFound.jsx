import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Compass, Home, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function NotFound() {
    return (
        <>
            <Head title="Page not found" />
            <main className="relative flex min-h-screen items-center justify-center overflow-hidden bg-background px-6 py-16">
                <div aria-hidden="true" className="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_hsl(var(--primary)/0.12),_transparent_55%)]" />
                <div className="relative w-full max-w-xl rounded-3xl border bg-card/90 p-8 text-center shadow-xl shadow-primary/5 backdrop-blur sm:p-12">
                    <div className="mx-auto mb-7 flex size-16 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-lg shadow-primary/20">
                        <Compass className="size-8" strokeWidth={1.7} />
                    </div>
                    <p className="font-mono text-sm font-semibold tracking-[0.3em] text-primary">404</p>
                    <h1 className="mt-3 text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
                        This page took a wrong turn.
                    </h1>
                    <p className="mx-auto mt-4 max-w-md leading-7 text-muted-foreground">
                        The address may be mistyped or the page may have moved. Check the URL, or head back to sign in.
                    </p>
                    <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        <Button asChild>
                            <Link href={route('login.page')}>
                                <Home className="mr-2 size-4" />
                                Go to sign in
                            </Link>
                        </Button>
                        <Button type="button" variant="outline" onClick={() => window.history.back()}>
                            <ArrowLeft className="mr-2 size-4" />
                            Go back
                        </Button>
                    </div>
                    <div className="mt-9 flex items-center justify-center gap-2 border-t pt-5 text-xs text-muted-foreground">
                        <Search className="size-3.5" />
                        <span>If you typed the address, check it for spelling errors.</span>
                    </div>
                </div>
            </main>
        </>
    );
}
