"use client";

import { useMemo, useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Database, KeyRound, Play, RefreshCcw, Table2 } from "lucide-react";
import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { databaseApi, normalizeApiError } from "@/lib/api";
import { formatBytes } from "@/lib/utils";

export default function DatabasesPage() {
  const [selectedDatabase, setSelectedDatabase] = useState<string | null>(null);
  const [selectedTable, setSelectedTable] = useState<string | null>(null);
  const requestedDatabase = typeof window === "undefined" ? null : new URLSearchParams(window.location.search).get("database");
  const [sql, setSql] = useState("select 1 as ok");
  const [password, setPassword] = useState("");
  const overview = useQuery({ queryKey: ["databases", "overview"], queryFn: databaseApi.overview, retry: false });
  const databases = useQuery({ queryKey: ["databases"], queryFn: databaseApi.list, retry: false });
  const activeDatabase = selectedDatabase ?? requestedDatabase ?? databases.data?.find((item) => !item.system)?.name ?? databases.data?.[0]?.name ?? null;
  const tables = useQuery({
    queryKey: ["databases", activeDatabase, "tables"],
    queryFn: () => databaseApi.tables(activeDatabase!),
    enabled: Boolean(activeDatabase),
    retry: false,
  });
  const activeTable = selectedTable ?? tables.data?.[0]?.name ?? null;
  const table = useQuery({
    queryKey: ["databases", activeDatabase, activeTable, "schema"],
    queryFn: () => databaseApi.table(activeDatabase!, activeTable!),
    enabled: Boolean(activeDatabase && activeTable),
    retry: false,
  });
  const rows = useQuery({
    queryKey: ["databases", activeDatabase, activeTable, "rows"],
    queryFn: () => databaseApi.rows(activeDatabase!, activeTable!, 1, 100),
    enabled: Boolean(activeDatabase && activeTable),
    retry: false,
  });
  const query = useMutation({ mutationFn: () => databaseApi.query(activeDatabase!, { sql, current_password: password, limit: 100 }) });

  const linkedWebsites = useMemo(() => overview.data?.website_links.filter((link) => !activeDatabase || link.database_name === activeDatabase) ?? [], [overview.data?.website_links, activeDatabase]);
  const loadError = overview.error ?? databases.error;

  if (overview.isLoading || databases.isLoading) {
    return <Skeleton className="h-96" />;
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
        <div>
          <h1 className="text-2xl font-semibold">Databases</h1>
          <p className="mt-1 text-sm text-muted">MySQL and MariaDB workbench for owner-controlled inspection.</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Counter label="databases" value={databases.data?.length ?? 0} />
          <Counter label="linked apps" value={overview.data?.website_links.length ?? 0} />
          <Button variant="ghost" onClick={() => { overview.refetch(); databases.refetch(); tables.refetch(); rows.refetch(); }}>
            <RefreshCcw className="h-4 w-4" />
            Refresh
          </Button>
        </div>
      </div>

      {loadError ? <div className="rounded-[var(--radius)] border border-danger/30 bg-danger/10 px-3 py-2 text-sm text-danger">{normalizeApiError(loadError).message}</div> : null}

      <div className="grid gap-4 xl:grid-cols-[280px_1fr]">
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2 font-medium"><Database className="h-4 w-4" />Catalog</div>
          </CardHeader>
          <CardContent className="space-y-2">
            {(databases.data ?? []).map((item) => (
              <button
                key={item.name}
                onClick={() => { setSelectedDatabase(item.name); setSelectedTable(null); }}
                className={`flex w-full items-center justify-between rounded-[var(--radius)] px-3 py-2 text-left text-sm ${activeDatabase === item.name ? "bg-panel-muted text-foreground" : "text-muted hover:bg-panel-muted"}`}
              >
                <span className="truncate">{item.name}</span>
                {item.system ? <span className="text-xs text-muted">system</span> : null}
              </button>
            ))}
          </CardContent>
        </Card>

        <div className="space-y-4">
          <div className="grid gap-4 lg:grid-cols-3">
            <Fact label="Driver" value={overview.data?.driver ?? "mysql"} />
            <Fact label="Server" value={`${overview.data?.host ?? "unknown"}:${overview.data?.port ?? "?"}`} />
            <Fact label="Version" value={overview.data?.version ?? "Unavailable"} />
          </div>

          <div className="grid gap-4 xl:grid-cols-[320px_1fr]">
            <Card>
              <CardHeader>
                <div className="flex items-center gap-2 font-medium"><Table2 className="h-4 w-4" />Tables</div>
              </CardHeader>
              <CardContent className="max-h-[560px] space-y-2 overflow-auto">
                {tables.isError ? <div className="text-sm text-danger">{normalizeApiError(tables.error).message}</div> : null}
                {(tables.data ?? []).map((item) => (
                  <button
                    key={item.name}
                    onClick={() => setSelectedTable(item.name)}
                    className={`w-full rounded-[var(--radius)] px-3 py-2 text-left text-sm ${activeTable === item.name ? "bg-panel-muted text-foreground" : "text-muted hover:bg-panel-muted"}`}
                  >
                    <span className="block truncate font-medium">{item.name}</span>
                    <span className="mt-1 block text-xs text-muted">{item.engine ?? item.type ?? "table"} - {formatBytes(item.size_bytes ?? null)}</span>
                  </button>
                ))}
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex-row items-center justify-between">
                <div>
                  <div className="font-medium">{activeTable ?? "Select a table"}</div>
                  <div className="mt-1 text-xs text-muted">{activeDatabase ?? "No database selected"}</div>
                </div>
                <Badge value={rows.data ? `${rows.data.rows.length} rows` : "schema"} />
              </CardHeader>
              <CardContent className="space-y-4">
                {rows.isError ? <div className="text-sm text-danger">{normalizeApiError(rows.error).message}</div> : null}
                <DataGrid columns={rows.data?.columns ?? []} rows={rows.data?.rows ?? []} />
                {table.data?.columns.length ? (
                  <div className="overflow-auto rounded-[var(--radius)] border border-border">
                    <table className="w-full min-w-[520px] text-left text-xs">
                      <thead className="bg-panel-muted text-muted">
                        <tr><th className="px-3 py-2">Column</th><th className="px-3 py-2">Type</th><th className="px-3 py-2">Key</th><th className="px-3 py-2">Extra</th></tr>
                      </thead>
                      <tbody>
                        {table.data.columns.map((column, index) => (
                          <tr key={`${String(column.name)}-${index}`} className="border-t border-border">
                            <td className="px-3 py-2 font-medium">{String(column.name ?? "")}</td>
                            <td className="px-3 py-2 text-muted">{String(column.type ?? "")}</td>
                            <td className="px-3 py-2 text-muted">{String(column.column_key ?? "")}</td>
                            <td className="px-3 py-2 text-muted">{String(column.extra ?? "")}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : null}
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      <div className="grid gap-4 xl:grid-cols-[1fr_360px]">
        <Card>
          <CardHeader className="flex-row items-center justify-between">
            <div className="font-medium">SQL Console</div>
            <Badge value="read only" />
          </CardHeader>
          <CardContent className="space-y-3">
            <textarea value={sql} onChange={(event) => setSql(event.target.value)} className="min-h-36 w-full rounded-[var(--radius)] border border-border bg-background p-3 font-mono text-sm outline-none focus:border-accent" spellCheck={false} />
            <div className="flex flex-col gap-2 sm:flex-row">
              <label className="relative flex-1">
                <KeyRound className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-muted" />
                <Input className="pl-9" type="password" value={password} onChange={(event) => setPassword(event.target.value)} placeholder="Current password" autoComplete="current-password" />
              </label>
              <Button onClick={() => query.mutate()} disabled={!activeDatabase || query.isPending}>
                <Play className="h-4 w-4" />
                Run
              </Button>
            </div>
            {query.error ? <div className="rounded-[var(--radius)] border border-danger/30 bg-danger/10 px-3 py-2 text-sm text-danger">{normalizeApiError(query.error).message}</div> : null}
            {query.data ? <DataGrid columns={query.data.columns} rows={query.data.rows} /> : null}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <div className="font-medium">Website Links</div>
          </CardHeader>
          <CardContent className="space-y-2">
            {linkedWebsites.length === 0 ? <div className="text-sm text-muted">No websites are linked to this database yet.</div> : null}
            {linkedWebsites.map((link) => (
              <Link key={link.id} href={`/websites/${link.website_id}`} className="block rounded-[var(--radius)] border border-border p-3 text-sm hover:bg-panel-muted">
                <span className="block truncate font-medium">{link.website_name ?? link.website_domain ?? link.database_name}</span>
                <span className="mt-1 block truncate text-xs text-muted">{link.database_name} from {link.source_relative_path ?? ".env"}</span>
              </Link>
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

function Counter({ label, value }: { label: string; value: number }) {
  return <span className="rounded-[var(--radius)] border border-border bg-panel px-2.5 py-1 text-sm text-muted"><strong className="text-foreground">{value}</strong> {label}</span>;
}

function Fact({ label, value }: { label: string; value: string }) {
  return (
    <Card>
      <CardContent className="p-4">
        <div className="text-xs text-muted">{label}</div>
        <div className="mt-1 truncate font-medium">{value}</div>
      </CardContent>
    </Card>
  );
}

function DataGrid({ columns, rows }: { columns: string[]; rows: Record<string, unknown>[] }) {
  if (!columns.length) {
    return <div className="rounded-[var(--radius)] border border-border bg-panel-muted p-4 text-sm text-muted">No rows to display.</div>;
  }

  return (
    <div className="overflow-auto rounded-[var(--radius)] border border-border">
      <table className="w-full min-w-[640px] text-left text-xs">
        <thead className="bg-panel-muted text-muted">
          <tr>{columns.map((column) => <th key={column} className="px-3 py-2 font-medium">{column}</th>)}</tr>
        </thead>
        <tbody>
          {rows.map((row, index) => (
            <tr key={index} className="border-t border-border">
              {columns.map((column) => <td key={column} className="max-w-64 truncate px-3 py-2">{String(row[column] ?? "")}</td>)}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
