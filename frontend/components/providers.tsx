"use client";

import * as React from "react";
import { QueryCache, QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { ThemeProvider } from "next-themes";
import { Toaster, toast } from "sonner";
import { AuthProvider } from "@/components/auth-provider";
import { normalizeApiError } from "@/lib/api";

export function Providers({ children, nonce }: { children: React.ReactNode; nonce?: string }) {
  const [queryClient] = React.useState(
    () =>
      new QueryClient({
        queryCache: new QueryCache({
          onError: (error) => {
            const normalized = normalizeApiError(error);
            if (normalized.status >= 500 || normalized.status === 0) {
              toast.error(normalized.message);
            }
          },
        }),
        defaultOptions: {
          queries: {
            retry: (failureCount, error) => {
              const status = normalizeApiError(error).status;
              return status >= 500 && failureCount < 2;
            },
            staleTime: 30_000,
            refetchOnWindowFocus: false,
          },
        },
      }),
  );

  return (
    <ThemeProvider attribute="class" defaultTheme="system" enableSystem enableColorScheme={false} nonce={nonce}>
      <QueryClientProvider client={queryClient}>
        <AuthProvider>{children}</AuthProvider>
        <Toaster richColors position="top-right" />
      </QueryClientProvider>
    </ThemeProvider>
  );
}
