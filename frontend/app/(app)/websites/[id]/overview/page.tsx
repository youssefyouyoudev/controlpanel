"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useQueries, useQuery } from "@tanstack/react-query";
import { Activity, Archive, FileText, GitBranch, PlayCircle, RefreshCw } from "lucide-react";
import { WebsiteOperationsNav } from "@/components/website-operations-nav";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { operationsApi, websiteApi } from "@/lib/api";

export default function WebsiteOperationsOverviewPage() {
  const params = useParams<{ id: string }>();
  const websiteId = params.id;
  const website = useQuery({ queryKey: ["websites", websiteId], queryFn: () => websiteApi.show(websiteId) });
  const [components, health, git, backups, actions] = useQueries({
    queries: [
      { queryKey: ["components", websiteId], queryFn: () => operationsApi.components(websiteId) },
      { queryKey: ["health", websiteId], queryFn: () => operationsApi.health(websiteId) },
      { queryKey: ["git", websiteId], queryFn: () => operationsApi.gitStatus(websiteId), retry: false },
      { queryKey: ["backups", websiteId], queryFn: () => operationsApi.backups(websiteId) },
      { queryKey: ["action-executions"], queryFn: operationsApi.executions },
    ],
  });

  if (website.isLoading) {
    return <Skeleton className="h-[620px]" />;
  }

  if (!website.data) {
    return <Card><CardContent className="p-6 text-sm text-danger">Website not available.</CardContent></Card>;
  }

  const latestAction = actions.data?.find((execution) => execution.website_id === Number(websiteId));
  const latestBackup = backups.data?.[0];

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-2xl font-semibold">{website.data.name} operations</h1>
          <p className="mt-1 text-sm text-muted">Safe actions, logs, Git state, health and backups for this website.</p>
        </div>
        <Badge value={health.data?.status ?? website.data.status} />
      </div>
      <WebsiteOperationsNav websiteId={websiteId} />

      <div className="grid gap-4 lg:grid-cols-4">
        <Metric title="Health" value={health.data?.status ?? "unknown"} icon={<Activity className="h-4 w-4" />} href={`/websites/${websiteId}/overview`} />
        <Metric title="Branch" value={git.data?.branch ?? "unavailable"} icon={<GitBranch className="h-4 w-4" />} href={`/websites/${websiteId}/git`} />
        <Metric title="Last Action" value={latestAction?.status ?? "none"} icon={<PlayCircle className="h-4 w-4" />} href="/actions" />
        <Metric title="Latest Backup" value={latestBackup?.status ?? "none"} icon={<Archive className="h-4 w-4" />} href={`/websites/${websiteId}/backups`} />
      </div>

      <div className="grid gap-4 lg:grid-cols-[1fr_0.8fr]">
        <Card>
          <CardHeader><h2 className="font-semibold">Components</h2></CardHeader>
          <CardContent className="space-y-2">
            {components.data?.length === 0 ? <p className="text-sm text-muted">No components configured yet.</p> : null}
            {components.data?.map((component) => (
              <div key={component.id} className="flex items-center justify-between rounded-[var(--radius)] border border-border p-3 text-sm">
                <div>
                  <div className="font-medium">{component.name}</div>
                  <div className="text-xs text-muted">{component.type} · {component.relative_working_directory || "root"}</div>
                </div>
                <Badge value={component.status} />
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader><h2 className="font-semibold">Quick Actions</h2></CardHeader>
          <CardContent className="grid gap-2">
            <Link className="rounded-[var(--radius)] border border-border p-3 text-sm hover:bg-panel-muted" href={`/websites/${websiteId}/actions`}>
              <PlayCircle className="mb-2 h-4 w-4 text-accent" />
              Run an approved action
            </Link>
            <Link className="rounded-[var(--radius)] border border-border p-3 text-sm hover:bg-panel-muted" href={`/websites/${websiteId}/logs`}>
              <FileText className="mb-2 h-4 w-4 text-accent" />
              Open log console
            </Link>
            <Button variant="secondary" onClick={() => health.refetch()}>
              <RefreshCw className="h-4 w-4" />
              Refresh status
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

function Metric({ title, value, icon, href }: { title: string; value: string; icon: React.ReactNode; href: string }) {
  return (
    <Link href={href} className="rounded-[var(--radius)] border border-border bg-panel p-4 hover:bg-panel-muted">
      <div className="flex items-center justify-between text-muted">{icon}<span className="text-xs">{title}</span></div>
      <div className="mt-3 truncate text-lg font-semibold capitalize">{value.replaceAll("_", " ")}</div>
    </Link>
  );
}
