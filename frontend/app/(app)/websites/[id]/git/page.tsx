"use client";

import { useParams } from "next/navigation";
import { useMutation, useQueries, useQueryClient } from "@tanstack/react-query";
import { GitBranch, GitPullRequest, RefreshCw, ShieldAlert } from "lucide-react";
import { toast } from "sonner";
import { WebsiteOperationsNav } from "@/components/website-operations-nav";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { normalizeApiError, operationsApi } from "@/lib/api";

export default function WebsiteGitPage() {
  const params = useParams<{ id: string }>();
  const websiteId = params.id;
  const queryClient = useQueryClient();
  const [status, commits] = useQueries({
    queries: [
      { queryKey: ["git", websiteId], queryFn: () => operationsApi.gitStatus(websiteId), retry: false },
      { queryKey: ["git-commits", websiteId], queryFn: () => operationsApi.gitCommits(websiteId), retry: false },
    ],
  });
  const pull = useMutation({
    mutationFn: () => operationsApi.gitPull(websiteId),
    onSuccess: () => toast.success("Fast-forward pull queued."),
    onError: (error) => toast.error(normalizeApiError(error).message),
    onSettled: () => queryClient.invalidateQueries({ queryKey: ["git", websiteId] }),
  });

  if (status.isLoading) {
    return <Skeleton className="h-[520px]" />;
  }

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold">Git</h1>
        <p className="mt-1 text-sm text-muted">Status, commits, and fast-forward-only pulls. No force reset, clean, push or arbitrary paths.</p>
      </div>
      <WebsiteOperationsNav websiteId={websiteId} />
      <div className="grid gap-4 lg:grid-cols-[1fr_0.8fr]">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <h2 className="font-semibold">Repository Status</h2>
            <Button size="icon" variant="ghost" onClick={() => status.refetch()}><RefreshCw className="h-4 w-4" /></Button>
          </CardHeader>
          <CardContent className="space-y-4">
            {status.isError ? <p className="text-sm text-danger">Git status is unavailable for this component.</p> : null}
            {status.data ? (
              <>
                <div className="grid gap-3 sm:grid-cols-2">
                  <Info label="Branch" value={status.data.branch} />
                  <Info label="Latest commit" value={status.data.latest_commit ?? "Unknown"} />
                  <Info label="Remote" value={status.data.remote_url ?? "Hidden or unavailable"} />
                  <Info label="State" value={status.data.dirty ? "Local changes present" : "Clean"} />
                </div>
                {status.data.dirty ? (
                  <div className="rounded-[var(--radius)] border border-warning/40 bg-warning/10 p-3 text-sm text-warning">
                    <ShieldAlert className="mb-2 h-4 w-4" />
                    Pull is blocked until local changes are reviewed. YouPanel will not discard or stash files automatically.
                  </div>
                ) : null}
                <div className="rounded-[var(--radius)] border border-border p-3">
                  <h3 className="mb-2 text-sm font-medium">Changed files</h3>
                  {status.data.changes.length === 0 ? <p className="text-sm text-muted">No local changes.</p> : status.data.changes.map((change) => <div key={change} className="font-mono text-xs text-muted">{change}</div>)}
                </div>
                <Button disabled={status.data.dirty || pull.isPending} onClick={() => pull.mutate()}>
                  <GitPullRequest className="h-4 w-4" />
                  Fast-forward pull
                </Button>
              </>
            ) : null}
          </CardContent>
        </Card>
        <Card>
          <CardHeader><h2 className="font-semibold">Latest Commits</h2></CardHeader>
          <CardContent className="space-y-2">
            {commits.data?.map((commit) => (
              <div key={commit} className="flex items-center gap-2 rounded-[var(--radius)] border border-border p-3 text-sm">
                <GitBranch className="h-4 w-4 text-accent" />
                <span className="truncate font-mono text-xs">{commit}</span>
              </div>
            ))}
            {commits.isError ? <p className="text-sm text-muted">Commit history is unavailable.</p> : null}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-[var(--radius)] border border-border p-3">
      <div className="text-xs text-muted">{label}</div>
      <div className="mt-1 truncate text-sm font-medium">{value}</div>
    </div>
  );
}
