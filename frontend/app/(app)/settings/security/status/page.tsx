"use client";

import { useQuery } from "@tanstack/react-query";
import { AlertTriangle, CheckCircle2, ShieldCheck, XCircle } from "lucide-react";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { normalizeApiError, securityApi } from "@/lib/api";

export default function SecurityStatusPage() {
  const status = useQuery({ queryKey: ["security", "status"], queryFn: securityApi.status, retry: false });

  if (status.isLoading) {
    return <Skeleton className="h-96" />;
  }

  if (status.isError) {
    return <div className="rounded-[var(--radius)] border border-danger/30 bg-danger/10 px-3 py-2 text-sm text-danger">{normalizeApiError(status.error).message}</div>;
  }

  const checks = status.data?.checks ?? [];
  const score = status.data?.score;

  return (
    <div className="space-y-5">
      <div>
        <h1 className="flex items-center gap-2 text-2xl font-semibold"><ShieldCheck className="h-5 w-5" />Security Status</h1>
        <p className="mt-1 text-sm text-muted">Owner-only configuration posture checks. No secrets are displayed.</p>
      </div>

      <div className="grid gap-3 md:grid-cols-3">
        <Counter label="pass" value={score?.passed ?? 0} tone="pass" />
        <Counter label="warning" value={score?.warnings ?? 0} tone="warning" />
        <Counter label="danger" value={score?.failed ?? 0} tone="danger" />
      </div>

      <Card>
        <CardHeader>
          <div className="font-medium">Checks</div>
        </CardHeader>
        <CardContent className="space-y-2">
          {checks.map((check) => (
            <div key={check.name} className="grid gap-2 rounded-[var(--radius)] border border-border px-3 py-2 md:grid-cols-[180px_1fr]">
              <div className={`flex items-center gap-2 text-sm font-medium ${toneClass(check.status)}`}>
                {check.status === "pass" ? <CheckCircle2 className="h-4 w-4" /> : check.status === "warning" ? <AlertTriangle className="h-4 w-4" /> : <XCircle className="h-4 w-4" />}
                {check.name}
              </div>
              <div className="text-sm text-muted">{check.message}</div>
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  );
}

function Counter({ label, value, tone }: { label: string; value: number; tone: "pass" | "warning" | "danger" }) {
  return (
    <div className="rounded-[var(--radius)] border border-border bg-panel px-4 py-3">
      <div className={`text-2xl font-semibold ${toneClass(tone)}`}>{value}</div>
      <div className="text-xs uppercase text-muted">{label}</div>
    </div>
  );
}

function toneClass(tone: "pass" | "warning" | "danger") {
  if (tone === "pass") return "text-success";
  if (tone === "warning") return "text-warning";
  return "text-danger";
}
