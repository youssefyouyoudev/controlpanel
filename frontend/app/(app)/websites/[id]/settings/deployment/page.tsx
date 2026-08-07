"use client";

import { useParams } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Link2, ShieldCheck } from "lucide-react";
import { WebsiteOperationsNav } from "@/components/website-operations-nav";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { coolifyApi, normalizeApiError, operationsApi } from "@/lib/api";

export default function DeploymentSettingsPage() {
  const params = useParams<{ id: string }>();
  const queryClient = useQueryClient();
  const components = useQuery({ queryKey: ["components", params.id], queryFn: () => operationsApi.components(params.id) });
  const links = useQuery({ queryKey: ["websites", params.id, "coolify-links"], queryFn: () => coolifyApi.links(params.id) });
  const resources = useQuery({ queryKey: ["coolify", "resources", "application"], queryFn: () => coolifyApi.resources("application"), retry: false });
  const createLink = useMutation({
    mutationFn: (values: Record<string, unknown>) => coolifyApi.createLink(params.id, values),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["websites", params.id, "coolify-links"] }),
  });
  const firstComponent = components.data?.[0];

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold">Deployment policy</h1>
        <p className="mt-1 text-sm text-muted">Phase 4 stores deployment policy foundations. Production deployments default to approval-required.</p>
      </div>
      <WebsiteOperationsNav websiteId={params.id} />
      <Card>
        <CardHeader className="flex flex-row items-center gap-2"><ShieldCheck className="h-4 w-4 text-accent" /><h2 className="font-semibold">Current defaults</h2></CardHeader>
        <CardContent className="grid gap-3 text-sm md:grid-cols-2">
          {["Allowed branch: main", "Production approval: required", "Health check after deploy: enabled", "Auto rollback: disabled", "Concurrent deployments: one per resource", "Database migrations: never automatic"].map((item) => (
            <div key={item} className="rounded-[var(--radius)] border border-border p-3">{item}</div>
          ))}
        </CardContent>
      </Card>
      <Card>
        <CardHeader className="flex flex-row items-center gap-2"><Link2 className="h-4 w-4 text-accent" /><h2 className="font-semibold">Resource linking</h2></CardHeader>
        <CardContent className="space-y-4">
          {createLink.isError ? <p className="text-sm text-danger">{normalizeApiError(createLink.error).message}</p> : null}
          <div className="grid gap-3 md:grid-cols-2">
            {resources.data?.map((resource) => (
              <div key={resource.coolify_uuid} className="rounded-[var(--radius)] border border-border p-3 text-sm">
                <div className="flex items-center justify-between gap-3"><span className="font-medium">{resource.display_name}</span><Badge value={resource.status} /></div>
                <div className="mt-1 text-xs text-muted">{resource.domains.join(", ") || resource.coolify_uuid}</div>
                <Button className="mt-3" size="sm" onClick={() => createLink.mutate({ resource_type: "application", coolify_uuid: resource.coolify_uuid, website_component_id: firstComponent?.id, is_primary: true })} disabled={createLink.isPending || links.data?.some((link) => link.coolify_uuid === resource.coolify_uuid)}>
                  Link resource
                </Button>
              </div>
            ))}
          </div>
          <div>
            <h3 className="text-sm font-medium">Current links</h3>
            <div className="mt-2 grid gap-2">
              {links.data?.map((link) => <div key={link.id} className="flex items-center justify-between rounded-[var(--radius)] border border-border p-3 text-sm"><span>{link.display_name ?? link.coolify_uuid}</span><Badge value={link.last_status ?? "unknown"} /></div>)}
              {!links.isLoading && links.data?.length === 0 ? <p className="text-sm text-muted">No Coolify resources linked yet.</p> : null}
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
