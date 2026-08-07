"use client";

import { useParams } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CheckCircle2, ExternalLink, PauseCircle, RefreshCw, XCircle } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { deploymentApi, normalizeApiError } from "@/lib/api";

export default function DeploymentDetailPage() {
  const params = useParams<{ id: string }>();
  const queryClient = useQueryClient();
  const deployment = useQuery({ queryKey: ["deployments", params.id], queryFn: () => deploymentApi.show(params.id), refetchInterval: 5000 });
  const logs = useQuery({ queryKey: ["deployments", params.id, "logs"], queryFn: () => deploymentApi.logs(params.id), enabled: Boolean(deployment.data), refetchInterval: (query) => query.state.data?.complete ? false : 5000 });
  const approve = useMutation({ mutationFn: () => deploymentApi.approve(params.id), onSuccess: () => queryClient.invalidateQueries({ queryKey: ["deployments", params.id] }) });
  const reject = useMutation({ mutationFn: () => deploymentApi.reject(params.id, "Rejected from YouPanel."), onSuccess: () => queryClient.invalidateQueries({ queryKey: ["deployments", params.id] }) });
  const cancel = useMutation({ mutationFn: () => deploymentApi.cancel(params.id), onSuccess: () => queryClient.invalidateQueries({ queryKey: ["deployments", params.id] }) });

  if (deployment.isLoading) return <Card><CardContent className="text-sm text-muted">Loading deployment...</CardContent></Card>;
  if (!deployment.data) return <Card><CardContent className="text-sm text-danger">Deployment unavailable.</CardContent></Card>;

  const item = deployment.data;

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Deployment</h1>
          <p className="mt-1 text-sm text-muted">{item.website?.name ?? `Website #${item.website_id}`} · {item.component?.name ?? "Primary component"}</p>
        </div>
        <div className="flex gap-2">
          {item.status === "awaiting_approval" ? <Button onClick={() => approve.mutate()}><CheckCircle2 className="h-4 w-4" />Approve</Button> : null}
          {item.status === "awaiting_approval" ? <Button variant="secondary" onClick={() => reject.mutate()}><XCircle className="h-4 w-4" />Reject</Button> : null}
          {["queued", "preparing", "building", "deploying", "running"].includes(item.status) ? <Button variant="secondary" onClick={() => cancel.mutate()}><PauseCircle className="h-4 w-4" />Cancel</Button> : null}
        </div>
      </div>

      {[approve, reject, cancel].map((mutation, index) => mutation.isError ? <p key={index} className="text-sm text-danger">{normalizeApiError(mutation.error).message}</p> : null)}

      <div className="grid gap-4 lg:grid-cols-[1fr_360px]">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <h2 className="font-semibold">Status timeline</h2>
            <Badge value={item.status} />
          </CardHeader>
          <CardContent className="space-y-3 text-sm">
            {["Queued", "Preparing", "Building frontend", "Starting new container", "Waiting for health check", "Deployment completed"].map((label) => (
              <div key={label} className="flex items-center gap-3 rounded-[var(--radius)] border border-border p-3">
                <div className="h-2 w-2 rounded-full bg-accent" />
                <span>{label}</span>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader><h2 className="font-semibold">Details</h2></CardHeader>
          <CardContent className="space-y-3 text-sm">
            <Row label="Branch" value={item.branch ?? "Unknown"} />
            <Row label="Commit" value={item.commit_sha?.slice(0, 12) ?? "Latest from Coolify"} />
            <Row label="Requested by" value={item.requester?.name ?? "System"} />
            <Row label="Duration" value={item.duration_seconds ? `${item.duration_seconds}s` : "In progress"} />
            {item.resource_link?.open_url ? <a href={item.resource_link.open_url} target="_blank" className="inline-flex items-center gap-2 text-accent"><ExternalLink className="h-4 w-4" />Open in Coolify</a> : null}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <h2 className="font-semibold">Build output</h2>
          <Button size="sm" variant="secondary" onClick={() => logs.refetch()}><RefreshCw className="h-4 w-4" />Refresh</Button>
        </CardHeader>
        <CardContent>
          <pre className="max-h-[420px] overflow-auto rounded-[var(--radius)] bg-background p-4 text-xs leading-6 text-muted">{logs.data?.logs ?? item.logs_preview ?? "Logs are not available yet."}</pre>
          {logs.data?.redacted ? <p className="mt-2 text-xs text-warning">Some log-like secrets were redacted. Redaction is best-effort, not a guarantee.</p> : null}
        </CardContent>
      </Card>
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return <div className="flex justify-between gap-3"><span className="text-muted">{label}</span><span className="text-right">{value}</span></div>;
}
