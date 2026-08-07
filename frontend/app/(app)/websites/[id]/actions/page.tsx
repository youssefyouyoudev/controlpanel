"use client";

import { useParams } from "next/navigation";
import { useMutation, useQueries, useQuery, useQueryClient } from "@tanstack/react-query";
import { Loader2, PlayCircle, RefreshCw, ShieldAlert } from "lucide-react";
import * as React from "react";
import { toast } from "sonner";
import { useAuth } from "@/components/auth-provider";
import { WebsiteOperationsNav } from "@/components/website-operations-nav";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { normalizeApiError, operationsApi } from "@/lib/api";
import { canRunAction, confirmationMode, executionHumanState, riskTone } from "@/lib/operations";
import type { ActionDefinition } from "@/lib/schemas";

export default function WebsiteActionsPage() {
  const params = useParams<{ id: string }>();
  const websiteId = params.id;
  const queryClient = useQueryClient();
  const { user } = useAuth();
  const [components, actions, executions] = useQueries({
    queries: [
      { queryKey: ["components", websiteId], queryFn: () => operationsApi.components(websiteId) },
      { queryKey: ["actions", websiteId], queryFn: () => operationsApi.actions(websiteId) },
      { queryKey: ["action-executions"], queryFn: operationsApi.executions, refetchInterval: 5000 },
    ],
  });
  const [selectedComponent, setSelectedComponent] = React.useState<number | undefined>(undefined);
  const [typedName, setTypedName] = React.useState("");
  const [password, setPassword] = React.useState("");
  const [openAction, setOpenAction] = React.useState<ActionDefinition | null>(null);

  const output = useQuery({
    queryKey: ["action-output", executions.data?.[0]?.uuid],
    queryFn: () => operationsApi.executionOutput(executions.data![0].uuid),
    enabled: Boolean(executions.data?.[0]),
    refetchInterval: 5000,
  });

  const runAction = useMutation({
    mutationFn: (action: ActionDefinition) => operationsApi.execute(websiteId, action.key, {
      website_component_id: selectedComponent,
      options: {
        confirmed: confirmationMode(action) !== "none",
        typed_website_name: typedName || undefined,
        password: password || undefined,
      },
    }),
    onSuccess: async () => {
      toast.success("Action queued.");
      setOpenAction(null);
      setTypedName("");
      setPassword("");
      await queryClient.invalidateQueries({ queryKey: ["action-executions"] });
    },
    onError: (error) => toast.error(normalizeApiError(error).message),
  });

  if (actions.isLoading || components.isLoading) {
    return <Skeleton className="h-[620px]" />;
  }

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold">Action center</h1>
        <p className="mt-1 text-sm text-muted">Run catalog-approved actions only. No raw commands are accepted.</p>
      </div>
      <WebsiteOperationsNav websiteId={websiteId} />

      <div className="grid gap-4 xl:grid-cols-[1fr_420px]">
        <Card>
          <CardHeader className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <h2 className="font-semibold">Approved Actions</h2>
            <select className="h-9 rounded-[var(--radius)] border border-border bg-panel px-3 text-sm" value={selectedComponent ?? ""} onChange={(event) => setSelectedComponent(event.target.value ? Number(event.target.value) : undefined)}>
              <option value="">Website root</option>
              {components.data?.map((component) => <option key={component.id} value={component.id}>{component.name}</option>)}
            </select>
          </CardHeader>
          <CardContent className="grid gap-3 md:grid-cols-2">
            {actions.data?.map((action) => {
              const disabled = !canRunAction(user?.role, action) || confirmationMode(action) === "disabled";
              return (
                <div key={action.key} className="rounded-[var(--radius)] border border-border p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <h3 className="font-medium">{action.name}</h3>
                      <p className="mt-1 text-sm text-muted">{action.description}</p>
                    </div>
                    <span className={`text-xs font-medium ${riskTone(action.risk_level)}`}>{action.risk_level}</span>
                  </div>
                  <div className="mt-3 flex items-center justify-between">
                    <span className="text-xs text-muted">{action.required_role}+ · {action.timeout_seconds}s</span>
                    <Button size="sm" disabled={disabled || runAction.isPending} onClick={() => confirmationMode(action) === "none" ? runAction.mutate(action) : setOpenAction(action)}>
                      <PlayCircle className="h-4 w-4" />
                      Run
                    </Button>
                  </div>
                </div>
              );
            })}
          </CardContent>
        </Card>

        <aside className="space-y-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between"><h2 className="font-semibold">Recent Runs</h2><Button size="icon" variant="ghost" onClick={() => executions.refetch()}><RefreshCw className="h-4 w-4" /></Button></CardHeader>
            <CardContent className="space-y-2">
              {executions.data?.slice(0, 8).map((execution) => (
                <div key={execution.uuid} className="rounded-[var(--radius)] border border-border p-3 text-sm">
                  <div className="flex items-center justify-between gap-3"><span className="truncate">{executionHumanState(execution)}</span><Badge value={execution.status} /></div>
                  {execution.summary ? <p className="mt-1 text-xs text-muted">{execution.summary}</p> : null}
                </div>
              ))}
            </CardContent>
          </Card>
          <Card>
            <CardHeader><h2 className="font-semibold">Latest Output</h2></CardHeader>
            <CardContent>
              <pre className="max-h-80 overflow-auto rounded-[var(--radius)] bg-[#050606] p-3 font-mono text-xs text-[#d8f3dc]">{output.data ?? "No output captured yet."}</pre>
            </CardContent>
          </Card>
        </aside>
      </div>

      {openAction ? (
        <div className="fixed inset-0 z-50 grid place-items-center bg-background/80 p-4 backdrop-blur-sm">
          <div className="w-full max-w-lg rounded-[var(--radius)] border border-border bg-panel p-5 shadow-xl">
            <div className="flex items-center gap-3"><ShieldAlert className="h-5 w-5 text-warning" /><h2 className="font-semibold">{openAction.name}</h2></div>
            <p className="mt-2 text-sm text-muted">{openAction.description}</p>
            <div className="mt-4 space-y-3">
              {confirmationMode(openAction) === "typed-password" ? (
                <>
                  <Input value={typedName} onChange={(event) => setTypedName(event.target.value)} placeholder="Type website name exactly" />
                  <Input type="password" value={password} onChange={(event) => setPassword(event.target.value)} placeholder="Password confirmation" />
                </>
              ) : null}
              <div className="flex justify-end gap-2">
                <Button variant="ghost" onClick={() => setOpenAction(null)}>Cancel</Button>
                <Button onClick={() => runAction.mutate(openAction)} disabled={runAction.isPending}>
                  {runAction.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <PlayCircle className="h-4 w-4" />}
                  Confirm
                </Button>
              </div>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
