"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { WebsiteOperationsNav } from "@/components/website-operations-nav";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { deploymentApi } from "@/lib/api";

export default function WebsiteDeploymentDetailPage() {
  const params = useParams<{ id: string; deployment: string }>();
  const deployment = useQuery({ queryKey: ["deployments", params.deployment], queryFn: () => deploymentApi.show(params.deployment), refetchInterval: 5000 });
  const logs = useQuery({ queryKey: ["deployments", params.deployment, "logs"], queryFn: () => deploymentApi.logs(params.deployment), enabled: Boolean(deployment.data) });

  return (
    <div className="space-y-5">
      <div>
        <Link href={`/websites/${params.id}/deployments`} className="text-sm text-muted hover:text-foreground">Back to deployments</Link>
        <h1 className="mt-2 text-2xl font-semibold">Website deployment</h1>
      </div>
      <WebsiteOperationsNav websiteId={params.id} />
      {deployment.data ? (
        <>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between"><h2 className="font-semibold">{deployment.data.website?.name ?? "Deployment"}</h2><Badge value={deployment.data.status} /></CardHeader>
            <CardContent className="grid gap-3 text-sm md:grid-cols-3">
              <div><span className="text-muted">Branch</span><div>{deployment.data.branch ?? "Unknown"}</div></div>
              <div><span className="text-muted">Commit</span><div>{deployment.data.commit_sha?.slice(0, 12) ?? "Latest"}</div></div>
              <div><span className="text-muted">Requested by</span><div>{deployment.data.requester?.name ?? "System"}</div></div>
            </CardContent>
          </Card>
          <Card><CardHeader><h2 className="font-semibold">Output</h2></CardHeader><CardContent><pre className="max-h-[420px] overflow-auto rounded-[var(--radius)] bg-background p-4 text-xs leading-6 text-muted">{logs.data?.logs ?? deployment.data.logs_preview ?? "Logs are not available yet."}</pre></CardContent></Card>
        </>
      ) : <Card><CardContent className="text-sm text-muted">Loading deployment...</CardContent></Card>}
    </div>
  );
}
