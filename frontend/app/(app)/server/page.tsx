"use client";

import { useQuery } from "@tanstack/react-query";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { dashboardApi } from "@/lib/api";
import { formatBytes, formatPercent, formatUptime } from "@/lib/utils";

export default function ServerPage() {
  const summary = useQuery({ queryKey: ["dashboard", "summary"], queryFn: dashboardApi.summary });
  const data = summary.data;

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold">Server</h1>
        <p className="mt-1 text-sm text-muted">Read-only operational overview. Global controls are postponed.</p>
      </div>
      <Card>
        <CardHeader><h2 className="font-semibold">Local Metrics</h2></CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2">
          <Row label="Hostname" value={data?.metrics.hostname ?? "Unavailable"} />
          <Row label="OS" value={data?.metrics.os_name ?? "Unavailable"} />
          <Row label="Kernel" value={data?.metrics.kernel_version ?? "Unavailable"} />
          <Row label="Uptime" value={formatUptime(data?.metrics.uptime_seconds)} />
          <Row label="CPU" value={formatPercent(data?.metrics.cpu.usage_percent)} />
          <Row label="Memory" value={`${formatPercent(data?.metrics.memory.usage_percent)} (${formatBytes(data?.metrics.memory.used_bytes)})`} />
          <Row label="Disk" value={`${formatPercent(data?.metrics.disk.usage_percent)} (${formatBytes(data?.metrics.disk.used_bytes)})`} />
        </CardContent>
      </Card>
      <Card>
        <CardHeader><h2 className="font-semibold">Services</h2></CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-3">
          {data?.services.map((service) => (
            <div key={service.name} className="flex items-center justify-between rounded-[var(--radius)] border border-border p-3">
              <span className="text-sm">{service.label}</span><Badge value={service.status} />
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return <div className="rounded-[var(--radius)] bg-panel-muted p-3"><div className="text-xs text-muted">{label}</div><div className="mt-1 text-sm font-medium">{value}</div></div>;
}
