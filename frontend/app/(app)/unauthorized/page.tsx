import Link from "next/link";

export default function UnauthorizedPage() {
  return (
    <div className="rounded-[var(--radius)] border border-border bg-panel p-8 text-center">
      <h1 className="text-xl font-semibold">Unauthorized</h1>
      <p className="mt-2 text-sm text-muted">Your account does not have permission to view that area.</p>
      <Link href="/dashboard" className="mt-4 inline-flex text-sm font-medium text-accent">Back to dashboard</Link>
    </div>
  );
}
