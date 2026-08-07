"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Rocket } from "lucide-react";
import { WebsiteOperationsNav } from "@/components/website-operations-nav";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { coolifyApi, deploymentApi, normalizeApiError, websiteApi } from "@/lib/api";

export default function WebsiteDeploymentsPage() {
  const params = useParams<{ id: string }>();
  const queryClient = useQueryClient();
  const website = useQuery({ queryKey: ["websites", params.id], queryFn: () => websiteApi.show(params.id) });
  const links = useQuery({ queryKey: ["websites", params.id, "coolify-links"], queryFn: () => coolifyApi.links(params.id) });
  const deployments = useQuery({ queryKey: ["deployments"], queryFn: deploymentApi.list, refetchInterval: 5000 });
  const create = useMutation({
    mutationFn: (linkId: number) => deploymentApi.create(params.id, { resource_link_id: linkId, branch: website.data?.repository_branch ?? "main", confirmed: true, typed_website_name: website.data?.name }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["deployments"] }),
  });
  const siteDeployments = deployments.data?.filter((item) => item.website_id === Number(params.id)) ?? [];

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold">{website.data?.name ?? "Website"} deployments</h1>
        <p className="mt-1 text-sm text-muted">Deploy linked Coolify applications with server-side authorization and approval checks.</p>
      </div>
      <WebsiteOperationsNav websiteId={params.id} />

      {create.isError ? <p className="text-sm text-danger">{normalizeApiError(create.error).message}</p> : null}

      <Card>
        <CardHeader><h2 className="font-semibold">Linked applications</h2></CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2">
          {links.data?.filter((link) => link.resource_type === "application").map((link) => (
            <div key={link.id} className="rounded-[var(--radius)] border border-border p-3">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <div className="font-medium">{link.display_name ?? link.coolify_uuid}</div>
                  <div className="text-xs text-muted">{link.environment ?? "environment unknown"} · {link.domains.join(", ") || "no domains"}</div>
                </div>
                <Badge value={link.last_status ?? "unknown"} />
              </div>
              <Button className="mt-3" onClick={() => create.mutate(link.id)} disabled={create.isPending}>
                <Rocket className="h-4 w-4" />Deploy website
              </Button>
            </div>
          ))}
          {!links.isLoading && links.data?.filter((link) => link.resource_type === "application").length === 0 ? <p className="text-sm text-muted">No Coolify application is linked yet.</p> : null}
        </CardContent>
      </Card>

      <Card>
        <CardHeader><h2 className="font-semibold">History</h2></CardHeader>
        <CardContent className="space-y-2">
          {siteDeployments.length === 0 ? <p className="text-sm text-muted">No deployments for this website yet.</p> : null}
          {siteDeployments.map((deployment) => (
            <Link key={deployment.uuid} href={`/websites/${params.id}/deployments/${deployment.uuid}`} className="flex items-center justify-between rounded-[var(--radius)] border border-border p-3 text-sm hover:bg-panel-muted">
              <span>{deployment.branch ?? "branch unknown"} · {deployment.commit_sha?.slice(0, 8) ?? "latest"}</span>
              <Badge value={deployment.status} />
            </Link>
          ))}
        </CardContent>
      </Card>
    </div>
  );
}
