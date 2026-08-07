"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { Container } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { websiteApi, coolifyApi } from "@/lib/api";

export default function ContainersPage() {
  const websites = useQuery({ queryKey: ["websites"], queryFn: websiteApi.list });
  const resourceQueries = useQuery({
    queryKey: ["containers", "all", websites.data?.map((website) => website.id).join(",")],
    queryFn: async () => {
      const lists = await Promise.all((websites.data ?? []).map((website) => coolifyApi.resourcesForWebsite(String(website.id)).then((resources) => resources.map((resource) => ({ ...resource, website })))));
      return lists.flat();
    },
    enabled: Boolean(websites.data),
  });

  const resources = resourceQueries.data ?? [];

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold">Containers</h1>
        <p className="mt-1 text-sm text-muted">Read-only Coolify resource visibility for websites you can access.</p>
      </div>
      <Card>
        <CardHeader className="flex flex-row items-center gap-2"><Container className="h-4 w-4 text-accent" /><h2 className="font-semibold">Linked resources</h2></CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2">
          {resources.map((resource) => (
            <Link key={resource.id} href={`/websites/${resource.website_id}/containers`} className="rounded-[var(--radius)] border border-border p-3 text-sm hover:bg-panel-muted">
              <div className="flex items-center justify-between gap-3"><span className="font-medium">{resource.display_name ?? resource.coolify_uuid}</span><Badge value={resource.last_status ?? "unknown"} /></div>
              <div className="mt-1 text-xs text-muted">{resource.website.name} · {resource.resource_type} · metrics unavailable through verified API</div>
            </Link>
          ))}
          {!resourceQueries.isLoading && resources.length === 0 ? <p className="text-sm text-muted">No linked Coolify resources are visible yet.</p> : null}
        </CardContent>
      </Card>
    </div>
  );
}
