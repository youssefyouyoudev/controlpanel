"use client";

import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";

export default function AppError({ retry }: { error: Error & { digest?: string }; retry: () => void }) {
  return (
    <Card>
      <CardContent className="p-6">
        <h1 className="text-lg font-semibold">This panel could not render.</h1>
        <p className="mt-2 text-sm text-muted">Try again, or check the API connection state.</p>
        <Button className="mt-4" onClick={retry}>Retry</Button>
      </CardContent>
    </Card>
  );
}
