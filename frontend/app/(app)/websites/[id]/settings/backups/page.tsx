"use client";

import { useParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { WebsiteOperationsNav } from "@/components/website-operations-nav";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { operationsApi } from "@/lib/api";

export default function BackupSettingsPage() {
  const params = useParams<{ id: string }>();
  const websiteId = params.id;
  const schedules = useQuery({ queryKey: ["backup-schedules", websiteId], queryFn: () => operationsApi.backupSchedules(websiteId) });

  if (schedules.isLoading) return <Skeleton className="h-[420px]" />;

  return (
    <div className="space-y-5">
      <div><h1 className="text-2xl font-semibold">Backup settings</h1><p className="mt-1 text-sm text-muted">Use constrained daily or weekly schedules; no browser-supplied cleanup paths are accepted.</p></div>
      <WebsiteOperationsNav websiteId={websiteId} />
      <Card><CardHeader><h2 className="font-semibold">Schedules</h2></CardHeader><CardContent className="space-y-2">{schedules.data?.length === 0 ? <p className="text-sm text-muted">No schedules yet.</p> : null}{schedules.data?.map((schedule) => <div key={schedule.id} className="rounded-[var(--radius)] border border-border p-3 text-sm"><div className="font-medium">{schedule.name}</div><div className="text-xs text-muted">{schedule.cron_expression}</div></div>)}</CardContent></Card>
    </div>
  );
}
