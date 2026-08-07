"use client";

import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";

export function ApiConnectionIndicator() {
  const health = useQuery({
    queryKey: ["api-health"],
    queryFn: async () => (await api.get("/api/health")).data,
    retry: 1,
    staleTime: 30_000,
  });

  const online = health.isSuccess;

  return (
    <div className="flex items-center gap-2 text-xs text-muted">
      <span className={`h-2 w-2 rounded-full ${online ? "bg-success" : "bg-danger"}`} />
      API {online ? "connected" : "offline"}
    </div>
  );
}
