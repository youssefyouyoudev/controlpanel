"use client";

import { useQuery } from "@tanstack/react-query";
import { Activity, RefreshCw } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { operationsApi } from "@/lib/api";
import { executionHumanState } from "@/lib/operations";

export default function GlobalActionsPage() {
  const executions = useQuery({ queryKey: ["action-executions"], queryFn: operationsApi.executions, refetchInterval: 5000 });

  if (executions.isLoading) return <Skeleton className="h-[520px]" />;

  return (
    <div className="space-y-5">
      <div className="flex items-start justify-between gap-4">
        <div><h1 className="text-2xl font-semibold">Actions</h1><p className="mt-1 text-sm text-muted">Queued, running and completed operations across visible websites.</p></div>
        <Button variant="secondary" onClick={() => executions.refetch()}><RefreshCw className="h-4 w-4" />Refresh</Button>
      </div>
      <Card>
        <CardHeader><h2 className="font-semibold">Execution History</h2></CardHeader>
        <CardContent className="space-y-2">
          {executions.data?.length === 0 ? <p className="text-sm text-muted">No actions yet.</p> : null}
          {executions.data?.map((execution) => (
            <div key={execution.uuid} className="rounded-[var(--radius)] border border-border p-4">
              <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div className="flex items-center gap-3"><Activity className="h-4 w-4 text-accent" /><div><div className="font-medium">{executionHumanState(execution)}</div><div className="text-xs text-muted">{execution.created_at ? new Date(execution.created_at).toLocaleString() : "Unknown time"}</div></div></div>
                <Badge value={execution.status} />
              </div>
              {execution.summary ? <p className="mt-2 text-sm text-muted">{execution.summary}</p> : null}
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  );
}
