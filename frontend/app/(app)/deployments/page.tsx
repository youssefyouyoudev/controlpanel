"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { RefreshCw, Rocket } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { deploymentApi } from "@/lib/api";

export default function DeploymentsPage() {
  const deployments = useQuery({ queryKey: ["deployments"], queryFn: deploymentApi.list, refetchInterval: 5000 });
  const items = deployments.data ?? [];

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Deployment Center</h1>
          <p className="mt-1 text-sm text-muted">Coolify-backed deployments across websites you can access.</p>
        </div>
        <Button variant="secondary" onClick={() => deployments.refetch()} disabled={deployments.isFetching}>
          <RefreshCw className="h-4 w-4" />Refresh
        </Button>
      </div>

      <div className="grid gap-3 md:grid-cols-4">
        <Stat label="Active" value={items.filter((item) => ["queued", "preparing", "building", "deploying", "running"].includes(item.status)).length} />
        <Stat label="Awaiting approval" value={items.filter((item) => item.status === "awaiting_approval").length} />
        <Stat label="Succeeded" value={items.filter((item) => item.status === "succeeded").length} />
        <Stat label="Failed" value={items.filter((item) => item.status === "failed").length} />
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center gap-2">
          <Rocket className="h-4 w-4 text-accent" />
          <h2 className="font-semibold">Recent deployments</h2>
        </CardHeader>
        <CardContent className="space-y-2">
          {deployments.isLoading ? <p className="text-sm text-muted">Loading deployments...</p> : null}
          {!deployments.isLoading && items.length === 0 ? <p className="text-sm text-muted">No deployments have been requested yet.</p> : null}
          {items.map((deployment) => (
            <Link key={deployment.uuid} href={`/deployments/${deployment.uuid}`} className="grid gap-3 rounded-[var(--radius)] border border-border p-3 text-sm hover:bg-panel-muted md:grid-cols-[1.5fr_1fr_1fr_auto]">
              <div>
                <div className="font-medium">{deployment.website?.name ?? `Website #${deployment.website_id}`}</div>
                <div className="text-xs text-muted">{deployment.component?.name ?? "Primary component"} · {deployment.provider}</div>
              </div>
              <div>{deployment.branch ?? "branch unknown"}<div className="text-xs text-muted">{deployment.commit_sha?.slice(0, 8) ?? "latest"}</div></div>
              <div className="text-muted">{deployment.requester?.name ?? "System"}</div>
              <Badge value={deployment.status} />
            </Link>
          ))}
        </CardContent>
      </Card>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: number }) {
  return <Card><CardContent><div className="text-xs uppercase tracking-wide text-muted">{label}</div><div className="mt-2 text-2xl font-semibold">{value}</div></CardContent></Card>;
}
