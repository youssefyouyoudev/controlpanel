"use client";

import { useParams } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Archive, CheckCircle2, RefreshCw, RotateCcw } from "lucide-react";
import { toast } from "sonner";
import { WebsiteOperationsNav } from "@/components/website-operations-nav";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { normalizeApiError, operationsApi } from "@/lib/api";
import { formatBytes } from "@/lib/file-workspace";

export default function WebsiteBackupsPage() {
  const params = useParams<{ id: string }>();
  const websiteId = params.id;
  const queryClient = useQueryClient();
  const backups = useQuery({ queryKey: ["backups", websiteId], queryFn: () => operationsApi.backups(websiteId), refetchInterval: 8000 });
  const schedules = useQuery({ queryKey: ["backup-schedules", websiteId], queryFn: () => operationsApi.backupSchedules(websiteId) });
  const create = useMutation({
    mutationFn: () => operationsApi.createBackup(websiteId, "files"),
    onSuccess: async () => {
      toast.success("Backup queued.");
      await queryClient.invalidateQueries({ queryKey: ["backups", websiteId] });
    },
    onError: (error) => toast.error(normalizeApiError(error).message),
  });
  const verify = useMutation({
    mutationFn: (uuid: string) => operationsApi.verifyBackup(websiteId, uuid),
    onSuccess: (ok) => toast[ok ? "success" : "error"](ok ? "Checksum verified." : "Checksum verification failed."),
    onError: (error) => toast.error(normalizeApiError(error).message),
  });

  if (backups.isLoading) {
    return <Skeleton className="h-[560px]" />;
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Backups</h1>
          <p className="mt-1 text-sm text-muted">Recoverable archives stored outside public web roots.</p>
        </div>
        <Button onClick={() => create.mutate()} disabled={create.isPending}><Archive className="h-4 w-4" />Create file backup</Button>
      </div>
      <WebsiteOperationsNav websiteId={websiteId} />
      <div className="grid gap-4 lg:grid-cols-[1fr_360px]">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between"><h2 className="font-semibold">Backup History</h2><Button size="icon" variant="ghost" onClick={() => backups.refetch()}><RefreshCw className="h-4 w-4" /></Button></CardHeader>
          <CardContent className="space-y-3">
            {backups.data?.length === 0 ? <p className="text-sm text-muted">No backups yet.</p> : null}
            {backups.data?.map((backup) => (
              <div key={backup.uuid} className="rounded-[var(--radius)] border border-border p-4">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                  <div>
                    <div className="flex items-center gap-2"><span className="font-medium capitalize">{backup.type.replaceAll("_", " ")}</span><Badge value={backup.status} /></div>
                    <p className="mt-1 text-xs text-muted">{backup.created_at ? new Date(backup.created_at).toLocaleString() : "Unknown time"} · {formatBytes(backup.size_bytes)}</p>
                    {backup.error_message ? <p className="mt-2 text-sm text-danger">{backup.error_message}</p> : null}
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Button size="sm" variant="secondary" disabled={!backup.checksum} onClick={() => verify.mutate(backup.uuid)}><CheckCircle2 className="h-4 w-4" />Verify</Button>
                    <Button size="sm" variant="secondary" disabled title="Restore requires staged owner confirmation in the API">
                      <RotateCcw className="h-4 w-4" />
                      Restore
                    </Button>
                  </div>
                </div>
                {backup.checksum ? <div className="mt-3 truncate font-mono text-xs text-muted">{backup.checksum}</div> : null}
              </div>
            ))}
          </CardContent>
        </Card>
        <Card>
          <CardHeader><h2 className="font-semibold">Schedules</h2></CardHeader>
          <CardContent className="space-y-2">
            {schedules.data?.length === 0 ? <p className="text-sm text-muted">No schedules configured.</p> : null}
            {schedules.data?.map((schedule) => (
              <div key={schedule.id} className="rounded-[var(--radius)] border border-border p-3 text-sm">
                <div className="font-medium">{schedule.name}</div>
                <div className="mt-1 text-xs text-muted">{schedule.backup_type} · {schedule.cron_expression} · retain {schedule.retention_count}</div>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
