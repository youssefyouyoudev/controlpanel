"use client";

import { useParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { WebsiteOperationsNav } from "@/components/website-operations-nav";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { operationsApi } from "@/lib/api";

export default function LogSettingsPage() {
  const params = useParams<{ id: string }>();
  const websiteId = params.id;
  const sources = useQuery({ queryKey: ["log-sources", websiteId], queryFn: () => operationsApi.logSources(websiteId) });

  if (sources.isLoading) return <Skeleton className="h-[420px]" />;

  return (
    <div className="space-y-5">
      <div><h1 className="text-2xl font-semibold">Log source settings</h1><p className="mt-1 text-sm text-muted">Log sources are allowlisted by owners. The browser never submits raw log paths for reading.</p></div>
      <WebsiteOperationsNav websiteId={websiteId} />
      <Card><CardHeader><h2 className="font-semibold">Sources</h2></CardHeader><CardContent className="space-y-2">{sources.data?.map((source) => <div key={source.id} className="flex items-center justify-between rounded-[var(--radius)] border border-border p-3 text-sm"><span>{source.name}</span><Badge value={source.is_sensitive ? "degraded" : "healthy"} /></div>)}</CardContent></Card>
    </div>
  );
}
