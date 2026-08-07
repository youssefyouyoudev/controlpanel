"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { websiteApi } from "@/lib/api";

export default function WebsitesPage() {
  const websites = useQuery({ queryKey: ["websites"], queryFn: websiteApi.list });

  if (websites.isLoading) {
    return <Skeleton className="h-96" />;
  }

  if (websites.isError) {
    return <Card><CardContent className="p-6 text-sm text-danger">Unable to load websites.</CardContent></Card>;
  }

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold">Websites</h1>
        <p className="mt-1 text-sm text-muted">Only websites authorized for your account appear here.</p>
      </div>
      {websites.data?.length === 0 ? (
        <Card><CardContent className="p-8 text-center text-sm text-muted">No assigned websites yet.</CardContent></Card>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {websites.data?.map((website) => (
            <Link key={website.id} href={`/websites/${website.id}`}>
              <Card className="h-full transition hover:border-accent">
                <CardHeader className="flex-row items-start justify-between gap-3">
                  <div>
                    <h2 className="font-semibold">{website.name}</h2>
                    <p className="mt-1 text-sm text-muted">{website.domain ?? "No domain recorded"}</p>
                  </div>
                  <Badge value={website.status} />
                </CardHeader>
                <CardContent className="space-y-3 text-sm">
                  <div className="flex justify-between"><span className="text-muted">Framework</span><span>{website.framework ?? "Unknown"}</span></div>
                  <div className="flex justify-between"><span className="text-muted">Server</span><span>{website.server?.name ?? "Unknown"}</span></div>
                  <div className="flex justify-between"><span className="text-muted">Branch</span><span>{website.repository_branch}</span></div>
                  <p className="rounded-[var(--radius)] bg-panel-muted p-2 text-xs text-muted">Files, logs, deployments, and backups are coming in the next phase.</p>
                </CardContent>
              </Card>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
