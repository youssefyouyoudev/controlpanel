"use client";

import { useParams } from "next/navigation";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { Activity, Archive, FileTerminal, FileText, FolderCode, GitBranch, Globe2, PlayCircle, Settings } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { websiteApi } from "@/lib/api";

export default function WebsiteDetailPage() {
  const params = useParams<{ id: string }>();
  const website = useQuery({ queryKey: ["websites", params.id], queryFn: () => websiteApi.show(params.id) });

  if (website.isLoading) {
    return <Skeleton className="h-96" />;
  }

  if (website.isError || !website.data) {
    return <Card><CardContent className="p-6 text-sm text-danger">Unable to load this website or you do not have access.</CardContent></Card>;
  }

  const data = website.data;

  return (
    <div className="space-y-5">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold">{data.name}</h1>
          <p className="mt-1 text-sm text-muted">{data.domain ?? "No domain recorded"}</p>
        </div>
        <div className="flex flex-wrap justify-end gap-2">
          <Badge value={data.status} />
          <Link href={`/websites/${data.id}/files`} className="inline-flex h-9 items-center gap-2 rounded-[var(--radius)] border border-border bg-panel px-3 text-sm font-medium hover:bg-panel-muted">
            <FolderCode className="h-4 w-4" />
            Files
          </Link>
          <Link href={`/websites/${data.id}/overview`} className="inline-flex h-9 items-center gap-2 rounded-[var(--radius)] border border-accent bg-accent px-3 text-sm font-medium text-accent-foreground hover:brightness-95">
            <Activity className="h-4 w-4" />
            Operations
          </Link>
          <Link href={`/websites/${data.id}/terminal`} className="inline-flex h-9 items-center gap-2 rounded-[var(--radius)] border border-border bg-panel px-3 text-sm font-medium hover:bg-panel-muted">
            <FileTerminal className="h-4 w-4" />
            Terminal
          </Link>
        </div>
      </div>
      <div className="grid gap-4 lg:grid-cols-[1fr_0.8fr]">
        <Card>
          <CardHeader><h2 className="font-semibold">Overview</h2></CardHeader>
          <CardContent className="grid gap-3 text-sm">
            <Row label="Framework" value={data.framework ?? "Unknown"} />
            <Row label="Application type" value={data.discovery?.application_type ?? "Unavailable"} />
            <Row label="Server" value={data.server?.name ?? "Unknown"} />
            <Row label="HTTP status" value={typeof data.discovery?.http_status === "number" ? String(data.discovery.http_status) : "Unavailable"} />
            <Row label="SSL" value={data.discovery?.ssl_enabled ? "Enabled" : data.discovery?.ssl_enabled === false ? "Disabled" : "Unavailable"} />
            <Row label="Runtime" value={data.discovery?.runtime_association ?? data.discovery?.runtime ?? "Unavailable"} />
            <Row label="Repository branch" value={data.discovery?.git_branch ?? data.repository_branch} />
            {data.display_path ? <Row label="Owner display path" value={data.display_path} /> : null}
            {data.discovery?.proxy_destination ? <Row label="Proxy destination" value={data.discovery.proxy_destination} /> : null}
            <Row label="Updated" value={data.updated_at ? new Date(data.updated_at).toLocaleString() : "Unknown"} />
          </CardContent>
        </Card>
        <Card>
          <CardHeader><h2 className="font-semibold">Operations</h2></CardHeader>
          <CardContent className="space-y-2">
            <Link href={`/websites/${data.id}/overview`} className="flex items-center justify-between rounded-[var(--radius)] border border-accent/40 bg-accent/10 p-3 text-sm hover:bg-accent/15">
              <span className="flex items-center gap-2"><Activity className="h-4 w-4" /> Operations overview</span>
              <span className="text-xs text-accent">Available</span>
            </Link>
            <Link href={`/websites/${data.id}/actions`} className="flex items-center justify-between rounded-[var(--radius)] border border-border p-3 text-sm hover:bg-panel-muted">
              <span className="flex items-center gap-2"><PlayCircle className="h-4 w-4" /> Action center</span>
              <span className="text-xs text-muted">Safe catalog</span>
            </Link>
            <Link href={`/websites/${data.id}/terminal`} className="flex items-center justify-between rounded-[var(--radius)] border border-border p-3 text-sm hover:bg-panel-muted">
              <span className="flex items-center gap-2"><FileTerminal className="h-4 w-4" /> Terminal</span>
              <span className="text-xs text-muted">Owner</span>
            </Link>
            <Link href={`/websites/${data.id}/logs`} className="flex items-center justify-between rounded-[var(--radius)] border border-border p-3 text-sm hover:bg-panel-muted">
              <span className="flex items-center gap-2"><FileText className="h-4 w-4" /> Logs</span>
              <span className="text-xs text-muted">Allowlisted</span>
            </Link>
            <Link href={`/websites/${data.id}/git`} className="flex items-center justify-between rounded-[var(--radius)] border border-border p-3 text-sm hover:bg-panel-muted">
              <span className="flex items-center gap-2"><GitBranch className="h-4 w-4" /> Git</span>
              <span className="text-xs text-muted">FF only</span>
            </Link>
            <Link href={`/websites/${data.id}/settings/deployment`} className="flex items-center justify-between rounded-[var(--radius)] border border-border p-3 text-sm hover:bg-panel-muted">
              <span className="flex items-center gap-2"><Globe2 className="h-4 w-4" /> SSL & Domains</span>
              <span className="text-xs text-muted">Discovery</span>
            </Link>
            <Link href={`/websites/${data.id}/backups`} className="flex items-center justify-between rounded-[var(--radius)] border border-border p-3 text-sm hover:bg-panel-muted">
              <span className="flex items-center gap-2"><Archive className="h-4 w-4" /> Backups</span>
              <span className="text-xs text-muted">Recoverable</span>
            </Link>
            <Link href={`/websites/${data.id}/files`} className="flex items-center justify-between rounded-[var(--radius)] border border-accent/40 bg-accent/10 p-3 text-sm hover:bg-accent/15">
              <span className="flex items-center gap-2"><FolderCode className="h-4 w-4" /> File workspace</span>
              <span className="text-xs text-accent">Available</span>
            </Link>
            <Link href={`/websites/${data.id}/settings/files`} className="flex items-center justify-between rounded-[var(--radius)] border border-border p-3 text-sm hover:bg-panel-muted">
              <span className="flex items-center gap-2"><Settings className="h-4 w-4" /> File root settings</span>
              <span className="text-xs text-muted">Owner</span>
            </Link>
            {Object.entries(data.modules).map(([name, status]) => (
              <div key={name} className="flex items-center justify-between rounded-[var(--radius)] border border-border p-3 text-sm">
                <span className="capitalize">{name}</span>
                <span className="text-xs text-muted">{status}</span>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return <div className="flex justify-between gap-4 border-b border-border pb-2"><span className="text-muted">{label}</span><span className="text-right">{value}</span></div>;
}
