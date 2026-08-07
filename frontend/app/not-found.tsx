import Link from "next/link";

export default function NotFound() {
  return (
    <main className="grid min-h-screen place-items-center px-6">
      <div className="max-w-md text-center">
        <p className="text-sm font-medium text-accent">404</p>
        <h1 className="mt-2 text-2xl font-semibold">That panel does not exist.</h1>
        <p className="mt-2 text-sm text-muted">The route may belong to a later YouPanel phase.</p>
        <Link href="/dashboard" className="mt-5 inline-flex text-sm font-medium text-accent">
          Return to dashboard
        </Link>
      </div>
    </main>
  );
}
