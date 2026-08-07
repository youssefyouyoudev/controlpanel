"use client";

import { useParams } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { RotateCw, Square, Triangle } from "lucide-react";
import { WebsiteOperationsNav } from "@/components/website-operations-nav";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { coolifyApi, normalizeApiError, websiteApi } from "@/lib/api";

export default function WebsiteContainersPage() {
  const params = useParams<{ id: string }>();
  const queryClient = useQueryClient();
  const website = useQuery({ queryKey: ["websites", params.id], queryFn: () => websiteApi.show(params.id) });
  const resources = useQuery({ queryKey: ["websites", params.id, "resources"], queryFn: () => coolifyApi.resourcesForWebsite(params.id), refetchInterval: 15000 });
  const action = useMutation({
    mutationFn: ({ linkId, kind, confirmed }: { linkId: number; kind: "start" | "stop" | "restart"; confirmed: boolean }) => coolifyApi.resourceAction(params.id, linkId, kind, confirmed),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["websites", params.id, "resources"] }),
  });
  const runAction = (linkId: number, kind: "start" | "stop" | "restart") => {
    const confirmed = kind === "start" ? false : window.confirm(`Confirm ${kind}?`);
    if (kind !== "start" && !confirmed) return;
    action.mutate({ linkId, kind, confirmed });
  };

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold">{website.data?.name ?? "Website"} resources</h1>
        <p className="mt-1 text-sm text-muted">Container-like visibility from stored Coolify links only. No Docker socket access.</p>
      </div>
      <WebsiteOperationsNav websiteId={params.id} />
      {action.isError ? <p className="text-sm text-danger">{normalizeApiError(action.error).message}</p> : null}
      <div className="grid gap-3 md:grid-cols-2">
        {resources.data?.map((resource) => (
          <Card key={resource.id}>
            <CardHeader className="flex flex-row items-center justify-between"><h2 className="font-semibold">{resource.display_name ?? resource.coolify_uuid}</h2><Badge value={resource.last_status ?? "unknown"} /></CardHeader>
            <CardContent className="space-y-3 text-sm">
              <div className="grid gap-2 text-xs text-muted">
                <span>UUID: {resource.coolify_uuid}</span>
                <span>Type: {resource.resource_type}</span>
                <span>Domains: {resource.domains.join(", ") || "none"}</span>
                <span>CPU: unavailable through verified Coolify API</span>
                <span>Memory: unavailable through verified Coolify API</span>
              </div>
              <div className="flex flex-wrap gap-2">
                <Button size="sm" variant="secondary" onClick={() => runAction(resource.id, "start")}><Triangle className="h-3.5 w-3.5" />Start</Button>
                <Button size="sm" variant="secondary" onClick={() => runAction(resource.id, "restart")}><RotateCw className="h-3.5 w-3.5" />Restart</Button>
                <Button size="sm" variant="danger" onClick={() => runAction(resource.id, "stop")}><Square className="h-3.5 w-3.5" />Stop</Button>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}
