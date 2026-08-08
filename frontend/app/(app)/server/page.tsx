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
          <Row label="Architecture" value={data?.metrics.architecture ?? "Unavailable"} />
          <Row label="CPU Model" value={data?.metrics.cpu.model ?? "Unavailable"} />
          <Row label="CPU Cores" value={data?.metrics.cpu.cores ? String(data.metrics.cpu.cores) : "Unavailable"} />
          <Row label="Uptime" value={formatUptime(data?.metrics.uptime_seconds)} />
          <Row label="CPU" value={formatPercent(data?.metrics.cpu.usage_percent)} />
          <Row label="Memory" value={`${formatPercent(data?.metrics.memory.usage_percent)} (${formatBytes(data?.metrics.memory.used_bytes)} used, ${formatBytes(data?.metrics.memory.available_bytes)} free)`} />
          <Row label="Swap" value={`${formatPercent(data?.metrics.memory.swap_usage_percent)} (${formatBytes(data?.metrics.memory.swap_used_bytes)} used)`} />
          <Row label="Disk" value={`${formatPercent(data?.metrics.disk.usage_percent)} (${formatBytes(data?.metrics.disk.used_bytes)} used, ${formatBytes(data?.metrics.disk.free_bytes)} free)`} />
          <Row label="Network Traffic" value={`${formatBytes(data?.metrics.network.rx_bytes)} received, ${formatBytes(data?.metrics.network.tx_bytes)} sent`} />
        </CardContent>
      </Card>
      <Card>
        <CardHeader><h2 className="font-semibold">Filesystems</h2></CardHeader>
        <CardContent className="space-y-2">
          {(data?.metrics.disk.filesystems ?? []).length === 0 ? <p className="text-sm text-muted">Filesystem details are unavailable on this host.</p> : null}
          {(data?.metrics.disk.filesystems ?? []).map((filesystem) => (
            <div key={`${filesystem.device}:${filesystem.mount}`} className="grid gap-2 rounded-[var(--radius)] border border-border p-3 text-sm md:grid-cols-[1fr_auto_auto] md:items-center">
              <div className="min-w-0">
                <div className="truncate font-medium">{filesystem.mount}</div>
                <div className="truncate text-xs text-muted">{filesystem.device} · {filesystem.type}</div>
              </div>
              <div>{formatPercent(filesystem.usage_percent)}</div>
              <div className="text-muted">{formatBytes(filesystem.used_bytes)} / {formatBytes(filesystem.total_bytes)}</div>
            </div>
          ))}
        </CardContent>
      </Card>
      <Card>
        <CardHeader><h2 className="font-semibold">Network Interfaces</h2></CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2">
          {(data?.metrics.network.interfaces ?? []).length === 0 ? <p className="text-sm text-muted">Network interface details are unavailable on this host.</p> : null}
          {(data?.metrics.network.interfaces ?? []).map((networkInterface) => (
            <Row
              key={networkInterface.name}
              label={networkInterface.name}
              value={`${formatBytes(networkInterface.rx_bytes)} received, ${formatBytes(networkInterface.tx_bytes)} sent${networkInterface.is_loopback ? " · loopback" : ""}`}
            />
          ))}
        </CardContent>
      </Card>
      <Card>
        <CardHeader><h2 className="font-semibold">Services</h2></CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-3">
          {data?.services.map((service) => (
            <div key={service.name} className="flex items-center justify-between gap-3 rounded-[var(--radius)] border border-border p-3">
              <span className="min-w-0">
                <span className="block truncate text-sm">{service.label}</span>
                <span className="block truncate text-xs text-muted">{service.installed === false ? "Not detected" : service.version ?? service.unit ?? "Version unavailable"}</span>
              </span>
              <Badge value={service.status} className="shrink-0" />
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
