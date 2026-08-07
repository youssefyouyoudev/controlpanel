"use client";

import { useQuery } from "@tanstack/react-query";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { activityApi } from "@/lib/api";

export default function ActivityPage() {
  const activity = useQuery({ queryKey: ["activity"], queryFn: activityApi.list });

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold">Activity</h1>
        <p className="mt-1 text-sm text-muted">Owner sees global activity; other users see only their own or assigned website activity.</p>
      </div>
      <Card>
        <CardHeader><h2 className="font-semibold">Audit trail</h2></CardHeader>
        <CardContent className="space-y-3">
          {activity.data?.length === 0 ? <p className="text-sm text-muted">No audit records yet.</p> : null}
          {activity.data?.map((item) => (
            <div key={item.id} className="rounded-[var(--radius)] border border-border p-3 text-sm">
              <div className="font-medium">{item.action}</div>
              <div className="mt-1 text-xs text-muted">{item.user?.name ?? "System"} · {item.created_at}</div>
            </div>
          ))}
          {activity.isError ? <p className="text-sm text-danger">Unable to load activity.</p> : null}
        </CardContent>
      </Card>
    </div>
  );
}
