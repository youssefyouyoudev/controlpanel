"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ExternalLink, PlugZap, RefreshCw } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { coolifyApi, normalizeApiError } from "@/lib/api";

export default function CoolifyIntegrationPage() {
  const queryClient = useQueryClient();
  const status = useQuery({ queryKey: ["coolify", "status"], queryFn: coolifyApi.status, retry: false });
  const capabilities = useQuery({ queryKey: ["coolify", "capabilities"], queryFn: coolifyApi.capabilities });
  const resources = useQuery({ queryKey: ["coolify", "resources"], queryFn: () => coolifyApi.resources("application"), retry: false });
  const test = useMutation({ mutationFn: coolifyApi.test, onSuccess: () => queryClient.invalidateQueries({ queryKey: ["coolify"] }) });
  const sync = useMutation({ mutationFn: coolifyApi.synchronize, onSuccess: () => queryClient.invalidateQueries({ queryKey: ["coolify"] }) });

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Coolify integration</h1>
          <p className="mt-1 text-sm text-muted">Backend-only connection diagnostics for Coolify 4.1.2.</p>
        </div>
        <div className="flex gap-2">
          <Button variant="secondary" onClick={() => test.mutate()} disabled={test.isPending}><PlugZap className="h-4 w-4" />Test connection</Button>
          <Button onClick={() => sync.mutate()} disabled={sync.isPending}><RefreshCw className="h-4 w-4" />Synchronize</Button>
        </div>
      </div>

      {status.isError ? <p className="text-sm text-danger">{normalizeApiError(status.error).message}</p> : null}
      {test.isError ? <p className="text-sm text-danger">{normalizeApiError(test.error).message}</p> : null}

      <div className="grid gap-4 lg:grid-cols-[1fr_360px]">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <h2 className="font-semibold">Connection</h2>
            <Badge value={status.data?.connected ? "running" : "unavailable"} />
          </CardHeader>
          <CardContent className="space-y-3 text-sm">
            <Row label="Enabled" value={String(status.data?.enabled ?? false)} />
            <Row label="Driver" value={status.data?.driver ?? "unknown"} />
            <Row label="Version" value={status.data?.version ?? "Unavailable"} />
            <Row label="Internal URL" value={status.data?.internal_url ?? "Configured in backend"} />
            <Row label="Public URL" value={status.data?.public_url ?? "Unavailable"} />
            <Row label="Token" value={status.data?.token_configured ? "Configured, never displayed" : "Not configured"} />
            <Row label="Terminal" value="Unavailable through verified API" />
            {status.data?.public_url ? <a href={status.data.public_url} target="_blank" className="inline-flex items-center gap-2 text-accent"><ExternalLink className="h-4 w-4" />Open Coolify</a> : null}
          </CardContent>
        </Card>

        <Card>
          <CardHeader><h2 className="font-semibold">Discovered applications</h2></CardHeader>
          <CardContent className="space-y-2">
            {resources.data?.slice(0, 5).map((resource) => (
              <div key={resource.coolify_uuid} className="rounded-[var(--radius)] border border-border p-3 text-sm">
                <div className="font-medium">{resource.display_name}</div>
                <div className="text-xs text-muted">{resource.coolify_uuid}</div>
                <div className="mt-2"><Badge value={resource.status} /></div>
              </div>
            ))}
            {!resources.isLoading && resources.data?.length === 0 ? <p className="text-sm text-muted">No applications discovered.</p> : null}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader><h2 className="font-semibold">Capability matrix</h2></CardHeader>
        <CardContent className="grid gap-2 md:grid-cols-2">
          {capabilities.data?.map((capability) => (
            <div key={capability.capability} className="rounded-[var(--radius)] border border-border p-3 text-sm">
              <div className="flex items-center justify-between gap-3">
                <span className="font-medium">{capability.capability}</span>
                <Badge value={capability.supported && capability.implemented ? "running" : "unavailable"} />
              </div>
              <div className="mt-1 text-xs text-muted">{capability.endpoint ?? capability.fallback}</div>
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return <div className="flex justify-between gap-3"><span className="text-muted">{label}</span><span className="text-right">{value}</span></div>;
}
