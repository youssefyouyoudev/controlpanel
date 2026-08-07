"use client";

import * as React from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { authApi, normalizeApiError } from "@/lib/api";
import { isProtectedRoute } from "@/lib/routing";
import type { User } from "@/lib/schemas";

type AuthStatus = "loading" | "authenticated" | "unauthenticated" | "error";

type AuthContextValue = {
  user: User | null;
  status: AuthStatus;
  isLoading: boolean;
  isAuthenticated: boolean;
  refetchUser: () => Promise<User | null>;
  logout: () => Promise<void>;
};

const AuthContext = React.createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const router = useRouter();
  const queryClient = useQueryClient();

  const userQuery = useQuery({
    queryKey: ["auth", "me"],
    queryFn: authApi.me,
    retry: false,
    staleTime: 60_000,
  });

  const authError = userQuery.isError ? normalizeApiError(userQuery.error) : null;
  const status: AuthStatus = userQuery.isPending
    ? "loading"
    : userQuery.data
      ? "authenticated"
      : authError?.status === 401
        ? "unauthenticated"
        : "error";

  React.useEffect(() => {
    if (status === "unauthenticated" && isProtectedRoute(pathname)) {
      const query = searchParams.toString();
      router.replace(`/login?returnTo=${encodeURIComponent(`${pathname}${query ? `?${query}` : ""}`)}`);
    }

    if (status === "authenticated" && pathname === "/login") {
      router.replace("/dashboard");
    }
  }, [pathname, router, searchParams, status]);

  const refetchUser = React.useCallback(async () => (await userQuery.refetch()).data ?? null, [userQuery]);

  const value = React.useMemo<AuthContextValue>(
    () => ({
      user: userQuery.data ?? null,
      status,
      isLoading: status === "loading",
      isAuthenticated: status === "authenticated",
      refetchUser,
      logout: async () => {
        await authApi.logout();
        queryClient.clear();
        router.replace("/login");
      },
    }),
    [queryClient, refetchUser, router, status, userQuery.data],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = React.useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used inside AuthProvider");
  }
  return context;
}
