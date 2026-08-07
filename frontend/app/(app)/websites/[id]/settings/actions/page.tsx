"use client";

import { useParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { WebsiteOperationsNav } from "@/components/website-operations-nav";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { operationsApi } from "@/lib/api";

export default function ActionSettingsPage() {
  const params = useParams<{ id: string }>();
  const websiteId = params.id;
  const actions = useQuery({ queryKey: ["actions", websiteId], queryFn: () => operationsApi.actions(websiteId) });

  if (actions.isLoading) return <Skeleton className="h-[520px]" />;

  return (
    <div className="space-y-5">
      <div><h1 className="text-2xl font-semibold">Action settings</h1><p className="mt-1 text-sm text-muted">Phase 3 uses a server-side catalog. Per-website toggles are ready in the API for later refinement.</p></div>
      <WebsiteOperationsNav websiteId={websiteId} />
      <Card><CardHeader><h2 className="font-semibold">Catalog</h2></CardHeader><CardContent className="grid gap-3 md:grid-cols-2">{actions.data?.map((action) => <div key={action.key} className="rounded-[var(--radius)] border border-border p-3 text-sm"><div className="flex items-center justify-between gap-3"><span className="font-medium">{action.name}</span><Badge value={action.enabled ? action.risk_level : "unavailable"} /></div><p className="mt-1 text-xs text-muted">{action.key}</p></div>)}</CardContent></Card>
    </div>
  );
}
