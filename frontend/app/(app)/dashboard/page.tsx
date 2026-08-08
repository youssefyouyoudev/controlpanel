"use client";

import { RefreshCcw } from "lucide-react";
import { useQuery } from "@tanstack/react-query";
import { motion } from "motion/react";
import { ApiConnectionIndicator } from "@/components/api-connection";
import { useAuth } from "@/components/auth-provider";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { dashboardApi, normalizeApiError } from "@/lib/api";
import { formatBytes, formatPercent } from "@/lib/utils";

export default function DashboardPage() {
  const { user, isAuthenticated, isLoading } = useAuth();
  const summary = useQuery({
    queryKey: ["dashboard", "summary"],
    queryFn: dashboardApi.summary,
    enabled: isAuthenticated,
    refetchInterval: isAuthenticated ? 30_000 : false,
  });

  if (isLoading || !isAuthenticated || summary.isLoading) {
    return <DashboardSkeleton />;
  }

  if (summary.isError) {
    const error = normalizeApiError(summary.error);
    return (
      <Card>
        <CardContent className="p-6">
          <h1 className="text-lg font-semibold">Dashboard unavailable</h1>
          <p className="mt-2 text-sm text-muted">{error.message}</p>
          <Button className="mt-4" onClick={() => summary.refetch()}>Retry</Button>
        </CardContent>
      </Card>
    );
  }

  const data = summary.data;

  if (!data) {
    return <DashboardSkeleton />;
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
          <h1 className="text-2xl font-semibold">Good evening, {user?.name?.split(" ")[0] ?? "Youssef"}.</h1>
          <p className="mt-1 text-sm text-muted">Server health, website operations and Coolify deployments in one calm cockpit.</p>
        </div>
        <div className="flex items-center gap-3">
          <ApiConnectionIndicator />
          <Button onClick={() => summary.refetch()} disabled={summary.isFetching}>
            <RefreshCcw className="h-4 w-4" />
            Refresh
          </Button>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-4">
        <Metric title="Server" value={data.server.status} detail={data.server.hostname ?? "local"} badge />
        <Metric title="CPU" value={formatPercent(data.metrics.cpu.usage_percent)} detail={`${data.metrics.cpu.cores ?? "Unknown"} cores · Load ${data.metrics.cpu.load_average.join(" / ") || "unavailable"}`} />
        <Metric title="Memory" value={formatPercent(data.metrics.memory.usage_percent)} detail={`${formatBytes(data.metrics.memory.used_bytes)} used · ${formatBytes(data.metrics.memory.available_bytes)} free`} />
        <Metric title="Disk" value={formatPercent(data.metrics.disk.usage_percent)} detail={`${formatBytes(data.metrics.disk.used_bytes)} used · ${formatBytes(data.metrics.disk.free_bytes)} free`} />
      </div>

      <div className="grid gap-4 md:grid-cols-4">
        <Metric title="Coolify" value={data.coolify?.driver ?? "mock"} detail={data.coolify?.enabled ? "real adapter enabled" : "mock/degraded safe mode"} />
        <Metric title="Linked Resources" value={String(data.coolify?.linked_resources ?? 0)} detail="Mapped through stored links" />
        <Metric title="Active Deployments" value={String(data.deployments?.active ?? 0)} detail={`${data.deployments?.awaiting_approval ?? 0} waiting approval`} />
        <Metric title="Failed Deployments" value={String(data.deployments?.failed ?? 0)} detail="Needs review when non-zero" />
      </div>

      <div className="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <h2 className="font-semibold">Service Status</h2>
              <span className="text-xs text-muted">Read-only</span>
            </div>
          </CardHeader>
          <CardContent className="grid gap-3 sm:grid-cols-2">
            {data.services.map((service) => (
              <div key={service.name} className="flex items-center justify-between rounded-[var(--radius)] border border-border bg-panel-muted p-3">
                <div className="min-w-0">
                  <div className="text-sm font-medium">{service.label}</div>
                  <div className="truncate text-xs text-muted">{service.version ?? (service.installed === false ? "Not detected" : service.unit ?? "Read-only probe")}</div>
                </div>
                <Badge value={service.status} className="shrink-0" />
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <h2 className="font-semibold">Websites Overview</h2>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="grid grid-cols-4 gap-2 text-center text-sm">
              {Object.entries(data.website_counts).map(([key, value]) => (
                <div key={key} className="rounded-[var(--radius)] bg-panel-muted p-3">
                  <div className="text-xl font-semibold">{value}</div>
                  <div className="capitalize text-muted">{key}</div>
                </div>
              ))}
            </div>
            {data.websites.length === 0 ? <p className="text-sm text-muted">No websites are visible yet.</p> : data.websites.map((website) => (
              <a key={website.id} href={`/websites/${website.id}`} className="flex items-center justify-between rounded-[var(--radius)] border border-border p-3 text-sm hover:bg-panel-muted">
                <span>{website.name}</span>
                <Badge value={website.status} />
              </a>
            ))}
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-[0.9fr_1.1fr]">
        <Card>
          <CardHeader><h2 className="font-semibold">Quick Actions</h2></CardHeader>
          <CardContent className="grid gap-2">
            {["File manager", "Deployment center", "Restricted console", "Advanced Coolify controls"].map((item) => (
              <button key={item} className="flex items-center justify-between rounded-[var(--radius)] border border-border p-3 text-left text-sm text-muted" disabled>
                {item}<span>{item === "Advanced Coolify controls" ? "Open Coolify" : "Available in pages"}</span>
              </button>
            ))}
          </CardContent>
        </Card>
        <Card>
          <CardHeader><h2 className="font-semibold">Recent Activity</h2></CardHeader>
          <CardContent className="space-y-3">
            {data.activity.length === 0 ? <p className="text-sm text-muted">No activity has been recorded yet.</p> : data.activity.map((item) => (
              <motion.div initial={{ opacity: 0, y: 4 }} animate={{ opacity: 1, y: 0 }} key={item.id} className="rounded-[var(--radius)] border border-border p-3 text-sm">
                <div className="font-medium">{item.action}</div>
                <div className="text-xs text-muted">{item.created_at}</div>
              </motion.div>
            ))}
          </CardContent>
        </Card>
      </div>

      <p className="text-xs text-muted">Last updated {new Date(data.collected_at).toLocaleString()}.</p>
    </div>
  );
}

function Metric({ title, value, detail, badge }: { title: string; value: string; detail: string; badge?: boolean }) {
  return (
    <Card>
      <CardContent>
        <div className="text-sm text-muted">{title}</div>
        <div className="mt-3 text-2xl font-semibold">{badge ? <Badge value={value} /> : value}</div>
        <div className="mt-2 truncate text-xs text-muted">{detail}</div>
      </CardContent>
    </Card>
  );
}

function DashboardSkeleton() {
  return (
    <div className="space-y-6">
      <Skeleton className="h-16 w-full" />
      <div className="grid gap-4 md:grid-cols-4">{Array.from({ length: 4 }).map((_, index) => <Skeleton key={index} className="h-28" />)}</div>
      <Skeleton className="h-80" />
    </div>
  );
}
