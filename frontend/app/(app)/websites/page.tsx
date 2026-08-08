"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ExternalLink, FileTerminal, GitBranch, ListFilter, RefreshCcw, RotateCw, Search } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/components/auth-provider";
import { websiteApi, normalizeApiError } from "@/lib/api";
import { canManageServer } from "@/lib/permissions";
import { formatBytes } from "@/lib/utils";
import type { Website } from "@/lib/schemas";

const filters = ["all", "healthy", "degraded", "offline"] as const;

export default function WebsitesPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [filter, setFilter] = useState<(typeof filters)[number]>("all");
  const websites = useQuery({ queryKey: ["websites"], queryFn: websiteApi.list });
  const scan = useMutation({ mutationFn: websiteApi.scan });
  const sync = useMutation({
    mutationFn: websiteApi.sync,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["websites"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });

  const data = useMemo(() => websites.data ?? [], [websites.data]);
  const counts = {
    total: data.length,
    healthy: data.filter((website) => website.status === "healthy").length,
    degraded: data.filter((website) => website.status === "degraded").length,
    offline: data.filter((website) => website.status === "offline").length,
  };
  const visible = useMemo(() => data.filter((website) => {
    const text = [
      website.name,
      website.domain,
      website.framework,
      website.discovery?.application_type,
      website.discovery?.domain_aliases.join(" "),
    ].filter(Boolean).join(" ").toLowerCase();

    return (filter === "all" || website.status === filter) && text.includes(search.toLowerCase());
  }), [data, filter, search]);
  const canSync = canManageServer(user?.role);
  const discoveryMessage = scan.data
    ? `${scan.data.count} website${scan.data.count === 1 ? "" : "s"} found in Nginx.`
    : sync.data
      ? `${sync.data.created} created, ${sync.data.updated} updated, ${sync.data.unchanged} unchanged.`
      : null;
  const mutationError = scan.error ?? sync.error;

  if (websites.isLoading) {
    return <Skeleton className="h-96" />;
  }

  if (websites.isError) {
    return <Card><CardContent className="p-6 text-sm text-danger">Unable to load websites.</CardContent></Card>;
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
        <div>
          <h1 className="text-2xl font-semibold">Websites</h1>
          <div className="mt-2 flex flex-wrap gap-2 text-sm">
            <Counter label="discovered" value={counts.total} />
            <Counter label="healthy" value={counts.healthy} />
            <Counter label="degraded" value={counts.degraded} />
            <Counter label="offline" value={counts.offline} />
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {canSync ? (
            <>
              <Button variant="secondary" onClick={() => scan.mutate()} disabled={scan.isPending || sync.isPending}>
                <Search className="h-4 w-4" />
                Scan server
              </Button>
              <Button onClick={() => sync.mutate()} disabled={scan.isPending || sync.isPending}>
                <RotateCw className="h-4 w-4" />
                Sync websites
              </Button>
            </>
          ) : null}
          <Button variant="ghost" onClick={() => websites.refetch()} disabled={websites.isFetching}>
            <RefreshCcw className="h-4 w-4" />
            Refresh
          </Button>
        </div>
      </div>

      {discoveryMessage ? <div className="rounded-[var(--radius)] border border-accent/30 bg-accent/10 px-3 py-2 text-sm text-accent">{discoveryMessage}</div> : null}
      {mutationError ? <div className="rounded-[var(--radius)] border border-danger/30 bg-danger/10 px-3 py-2 text-sm text-danger">{normalizeApiError(mutationError).message}</div> : null}

      <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <label className="relative block lg:w-96">
          <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-muted" />
          <Input className="pl-9" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search websites, domains, stacks" />
        </label>
        <div className="flex items-center gap-1 overflow-x-auto">
          <ListFilter className="mr-1 h-4 w-4 shrink-0 text-muted" />
          {filters.map((item) => (
            <button
              key={item}
              onClick={() => setFilter(item)}
              className={`h-9 rounded-[var(--radius)] px-3 text-sm capitalize ${filter === item ? "bg-panel-muted text-foreground" : "text-muted hover:bg-panel-muted"}`}
            >
              {item}
            </button>
          ))}
        </div>
      </div>

      {visible.length === 0 ? (
        <Card><CardContent className="p-8 text-center text-sm text-muted">{data.length === 0 ? "No websites are synchronized yet. Scan the server, then sync discovered websites." : "No websites match the current search and filters."}</CardContent></Card>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {visible.map((website) => <WebsiteCard key={website.id} website={website} />)}
        </div>
      )}
    </div>
  );
}

function Counter({ label, value }: { label: string; value: number }) {
  return <span className="rounded-[var(--radius)] border border-border bg-panel px-2.5 py-1 text-muted"><strong className="text-foreground">{value}</strong> {label}</span>;
}

function WebsiteCard({ website }: { website: Website }) {
  const discovery = website.discovery;
  const openUrl = website.domain ? `${discovery?.https_enabled ? "https" : "http"}://${website.domain}` : null;

  return (
    <Card className="h-full transition hover:border-accent">
      <CardHeader className="flex-row items-start justify-between gap-3">
        <div className="min-w-0">
          <h2 className="truncate font-semibold">{website.name}</h2>
          <p className="mt-1 truncate text-sm text-muted">{website.domain ?? discovery?.proxy_destination ?? "No domain recorded"}</p>
        </div>
        <Badge value={website.status} />
      </CardHeader>
      <CardContent className="space-y-3 text-sm">
        <div className="grid grid-cols-2 gap-2">
          <Fact label="Type" value={discovery?.application_type ?? website.framework ?? "Unavailable"} />
          <Fact label="HTTP" value={typeof discovery?.http_status === "number" ? `${discovery.http_status}${discovery.response_time_ms ? ` · ${discovery.response_time_ms}ms` : ""}` : "Unavailable"} />
          <Fact label="SSL" value={discovery?.ssl_enabled ? "Enabled" : discovery?.ssl_enabled === false ? "Disabled" : "Unavailable"} />
          <Fact label="Runtime" value={discovery?.runtime_association ?? discovery?.runtime ?? "Unavailable"} />
          <Fact label="Branch" value={discovery?.git_branch ?? website.repository_branch ?? "Unavailable"} />
          <Fact label="Size" value={formatBytes(discovery?.directory_size_bytes)} />
        </div>
        {discovery?.domain_aliases?.length ? <p className="truncate rounded-[var(--radius)] bg-panel-muted p-2 text-xs text-muted">Aliases: {discovery.domain_aliases.join(", ")}</p> : null}
        <div className="flex flex-wrap gap-2 pt-1">
          {openUrl ? <a href={openUrl} target="_blank" rel="noreferrer" className="inline-flex h-8 items-center gap-1 rounded-[var(--radius)] border border-border px-2.5 text-xs hover:bg-panel-muted"><ExternalLink className="h-3.5 w-3.5" />Open</a> : null}
          <Link href={`/websites/${website.id}`} className="inline-flex h-8 items-center rounded-[var(--radius)] border border-border px-2.5 text-xs hover:bg-panel-muted">Details</Link>
          <Link href={`/websites/${website.id}/terminal`} className="inline-flex h-8 items-center gap-1 rounded-[var(--radius)] border border-border px-2.5 text-xs hover:bg-panel-muted"><FileTerminal className="h-3.5 w-3.5" />Terminal</Link>
          <Link href={`/websites/${website.id}/logs`} className="inline-flex h-8 items-center rounded-[var(--radius)] border border-border px-2.5 text-xs hover:bg-panel-muted">Logs</Link>
          <Link href={`/websites/${website.id}/deployments`} className="inline-flex h-8 items-center rounded-[var(--radius)] border border-border px-2.5 text-xs hover:bg-panel-muted">Deploy</Link>
          <Link href={`/websites/${website.id}/git`} className="inline-flex h-8 items-center gap-1 rounded-[var(--radius)] border border-border px-2.5 text-xs hover:bg-panel-muted"><GitBranch className="h-3.5 w-3.5" />Git</Link>
        </div>
      </CardContent>
    </Card>
  );
}

function Fact({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-[var(--radius)] bg-panel-muted p-2">
      <div className="text-xs text-muted">{label}</div>
      <div className="mt-1 truncate font-medium">{value}</div>
    </div>
  );
}
