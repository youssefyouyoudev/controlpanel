"use client";

import { useParams } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Copy, Play, Terminal, Trash2 } from "lucide-react";
import * as React from "react";
import { WebsiteOperationsNav } from "@/components/website-operations-nav";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { consoleApi, normalizeApiError, operationsApi, websiteApi } from "@/lib/api";

export default function RestrictedConsolePage() {
  const params = useParams<{ id: string }>();
  const queryClient = useQueryClient();
  const [selectedComponent, setSelectedComponent] = React.useState<number | undefined>();
  const [alias, setAlias] = React.useState("git.status");
  const [history, setHistory] = React.useState<string[]>([]);
  const website = useQuery({ queryKey: ["websites", params.id], queryFn: () => websiteApi.show(params.id) });
  const components = useQuery({ queryKey: ["components", params.id], queryFn: () => operationsApi.components(params.id) });
  const commands = useQuery({ queryKey: ["console", params.id, "commands", selectedComponent], queryFn: () => consoleApi.commands(params.id, selectedComponent) });
  const execute = useMutation({
    mutationFn: () => consoleApi.execute(params.id, { command_alias: alias, website_component_id: selectedComponent }),
    onSuccess: (execution) => {
      setHistory((items) => [alias, ...items.filter((item) => item !== alias)].slice(0, 8));
      queryClient.invalidateQueries({ queryKey: ["console-executions", execution.uuid] });
    },
  });
  const latest = useQuery({ queryKey: ["console-executions", execute.data?.uuid], queryFn: () => consoleApi.show(execute.data!.uuid), enabled: Boolean(execute.data?.uuid), refetchInterval: (query) => ["queued", "running"].includes(query.state.data?.status ?? "") ? 1500 : false });

  const available = commands.data?.commands.filter((command) => command.available) ?? [];

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold">{website.data?.name ?? "Website"} console</h1>
        <p className="mt-1 text-sm text-muted">Restricted project console - approved commands only.</p>
      </div>
      <WebsiteOperationsNav websiteId={params.id} />

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <div className="flex items-center gap-2"><Terminal className="h-4 w-4 text-accent" /><h2 className="font-semibold">Command console</h2></div>
          <Badge value="restricted" />
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-3 md:grid-cols-[220px_1fr_auto]">
            <select className="h-9 rounded-[var(--radius)] border border-border bg-background px-3 text-sm" value={selectedComponent ?? ""} onChange={(event) => setSelectedComponent(event.target.value ? Number(event.target.value) : undefined)}>
              <option value="">Primary component</option>
              {components.data?.map((component) => <option key={component.id} value={component.id}>{component.name}</option>)}
            </select>
            <input className="h-9 rounded-[var(--radius)] border border-border bg-background px-3 font-mono text-sm" value={alias} onChange={(event) => setAlias(event.target.value)} list="console-command-aliases" />
            <datalist id="console-command-aliases">{available.map((command) => <option key={command.alias} value={command.alias} />)}</datalist>
            <Button onClick={() => execute.mutate()} disabled={execute.isPending || !available.some((command) => command.alias === alias)}><Play className="h-4 w-4" />Run</Button>
          </div>
          {execute.isError ? <p className="text-sm text-danger">{normalizeApiError(execute.error).message}</p> : null}
          <div className="grid gap-3 md:grid-cols-2">
            {available.map((command) => (
              <button key={command.alias} className="rounded-[var(--radius)] border border-border p-3 text-left text-sm hover:bg-panel-muted" onClick={() => setAlias(command.alias)}>
                <div className="font-mono">{command.alias}</div>
                <div className="mt-1 text-xs text-muted">{command.description}</div>
              </button>
            ))}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <h2 className="font-semibold">Output</h2>
          <div className="flex gap-2">
            <Button size="sm" variant="secondary" onClick={() => navigator.clipboard.writeText(latest.data?.output_preview ?? "")}><Copy className="h-4 w-4" />Copy</Button>
            <Button size="sm" variant="secondary" onClick={() => queryClient.removeQueries({ queryKey: ["console-executions"] })}><Trash2 className="h-4 w-4" />Clear</Button>
          </div>
        </CardHeader>
        <CardContent>
          <pre className="min-h-[260px] overflow-auto rounded-[var(--radius)] bg-background p-4 text-xs leading-6 text-muted">{latest.data?.output_preview ?? "Run an approved command to see output here."}</pre>
          <p className="mt-2 text-xs text-muted">{commands.data?.container_terminal}</p>
        </CardContent>
      </Card>

      {history.length ? <div className="text-xs text-muted">History: {history.join(" · ")}</div> : null}
    </div>
  );
}
