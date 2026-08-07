"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { Archive } from "lucide-react";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { websiteApi } from "@/lib/api";

export default function GlobalBackupsPage() {
  const websites = useQuery({ queryKey: ["websites"], queryFn: websiteApi.list });

  if (websites.isLoading) return <Skeleton className="h-[420px]" />;

  return (
    <div className="space-y-5">
      <div><h1 className="text-2xl font-semibold">Backups</h1><p className="mt-1 text-sm text-muted">Choose a website to review backup history and schedules.</p></div>
      <Card>
        <CardHeader><h2 className="font-semibold">Visible Websites</h2></CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2">
          {websites.data?.map((website) => (
            <Link key={website.id} href={`/websites/${website.id}/backups`} className="rounded-[var(--radius)] border border-border p-4 hover:bg-panel-muted">
              <Archive className="mb-3 h-4 w-4 text-accent" />
              <div className="font-medium">{website.name}</div>
              <div className="mt-1 text-sm text-muted">{website.domain ?? "No domain"}</div>
            </Link>
          ))}
        </CardContent>
      </Card>
    </div>
  );
}
