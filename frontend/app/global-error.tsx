"use client";

import { Button } from "@/components/ui/button";

export default function GlobalError({ retry }: { error: Error & { digest?: string }; retry: () => void }) {
  return (
    <html lang="en">
      <body className="grid min-h-screen place-items-center bg-background text-foreground">
        <div className="max-w-md rounded-[var(--radius)] border border-border bg-panel p-6">
          <h1 className="text-lg font-semibold">YouPanel hit an unexpected error.</h1>
          <p className="mt-2 text-sm text-muted">The app shell stayed small enough to recover. Try again.</p>
          <Button className="mt-4" onClick={retry}>
            Retry
          </Button>
        </div>
      </body>
    </html>
  );
}
