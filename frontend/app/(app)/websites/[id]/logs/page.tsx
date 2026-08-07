"use client";

import { useParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { Copy, Pause, Play, Search } from "lucide-react";
import * as React from "react";
import { WebsiteOperationsNav } from "@/components/website-operations-nav";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { operationsApi } from "@/lib/api";
import { cn } from "@/lib/utils";

export default function WebsiteLogsPage() {
  const params = useParams<{ id: string }>();
  const websiteId = params.id;
  const [sourceId, setSourceId] = React.useState<number | null>(null);
  const [search, setSearch] = React.useState("");
  const [level, setLevel] = React.useState("");
  const [wrap, setWrap] = React.useState(true);
  const [paused, setPaused] = React.useState(false);
  const sources = useQuery({ queryKey: ["log-sources", websiteId], queryFn: () => operationsApi.logSources(websiteId) });
  const activeSource = sourceId ?? sources.data?.[0]?.id ?? null;
  const logs = useQuery({
    queryKey: ["logs", websiteId, activeSource, search, level],
    queryFn: () => operationsApi.logs(websiteId, activeSource!, { lines: 200, search: search || undefined, level: level || undefined }),
    enabled: Boolean(activeSource),
    refetchInterval: paused ? false : 5000,
  });

  if (sources.isLoading) {
    return <Skeleton className="h-[620px]" />;
  }

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold">Logs</h1>
        <p className="mt-1 text-sm text-muted">Allowlisted sources only. Output is redacted but should still be treated as sensitive.</p>
      </div>
      <WebsiteOperationsNav websiteId={websiteId} />
      <Card>
        <CardHeader className="space-y-3">
          <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <select className="h-10 rounded-[var(--radius)] border border-border bg-panel px-3 text-sm" value={activeSource ?? ""} onChange={(event) => setSourceId(Number(event.target.value))}>
              {sources.data?.map((source) => <option key={source.id} value={source.id}>{source.name}</option>)}
            </select>
            <div className="flex flex-wrap gap-2">
              <Button variant="secondary" onClick={() => setPaused((value) => !value)}>{paused ? <Play className="h-4 w-4" /> : <Pause className="h-4 w-4" />}{paused ? "Resume" : "Pause"}</Button>
              <Button variant="secondary" onClick={() => setWrap((value) => !value)}>Wrap {wrap ? "on" : "off"}</Button>
              <Button variant="secondary" onClick={() => navigator.clipboard?.writeText(logs.data?.lines.map((line) => line.text).join("\n") ?? "")}><Copy className="h-4 w-4" />Copy</Button>
            </div>
          </div>
          <div className="grid gap-2 md:grid-cols-[1fr_160px]">
            <div className="relative"><Search className="absolute left-3 top-3 h-4 w-4 text-muted" /><Input className="pl-9" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search loaded lines" /></div>
            <select className="h-10 rounded-[var(--radius)] border border-border bg-panel px-3 text-sm" value={level} onChange={(event) => setLevel(event.target.value)}>
              <option value="">All levels</option>
              <option value="error">Error</option>
              <option value="warning">Warning</option>
              <option value="info">Info</option>
              <option value="debug">Debug</option>
            </select>
          </div>
        </CardHeader>
        <CardContent>
          <div className="overflow-hidden rounded-[var(--radius)] border border-border bg-[#050606]">
            <div className="flex items-center justify-between border-b border-white/10 px-3 py-2 text-xs text-[#94a3b8]">
              <span>{logs.data?.source ?? "No source"}</span>
              <span>{logs.data?.redacted ? "redaction enabled" : "redaction unknown"}</span>
            </div>
            <div className="max-h-[620px] overflow-auto p-3 font-mono text-xs">
              {logs.isLoading ? <div className="text-[#94a3b8]">Loading logs...</div> : null}
              {logs.data?.lines.map((line) => (
                <div key={`${line.number}-${line.text}`} className={cn("grid gap-3 py-0.5 md:grid-cols-[64px_80px_1fr]", !wrap && "min-w-max")}>
                  <span className="text-[#64748b]">{line.number}</span>
                  <span className={tone(line.level)}>{line.level}</span>
                  <span className={cn("text-[#d8f3dc]", wrap ? "whitespace-pre-wrap break-words" : "whitespace-pre")}>{line.text}</span>
                </div>
              ))}
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

function tone(level: string) {
  if (level === "error") return "text-[#ff6b6b]";
  if (level === "warning") return "text-[#f4b75e]";
  if (level === "debug") return "text-[#93c5fd]";
  return "text-[#4ade80]";
}
