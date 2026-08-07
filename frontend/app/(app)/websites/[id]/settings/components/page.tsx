"use client";

import { useParams } from "next/navigation";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Save } from "lucide-react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { z } from "zod";
import { WebsiteOperationsNav } from "@/components/website-operations-nav";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { normalizeApiError, operationsApi } from "@/lib/api";

const componentForm = z.object({
  name: z.string().min(2),
  slug: z.string().min(2),
  type: z.enum(["laravel", "nextjs", "vite", "node", "static", "database", "worker", "custom"]),
  relative_working_directory: z.string(),
  runtime: z.string().optional(),
  process_manager: z.string().optional(),
  process_name: z.string().optional(),
});

type ComponentForm = z.infer<typeof componentForm>;

export default function ComponentSettingsPage() {
  const params = useParams<{ id: string }>();
  const websiteId = params.id;
  const queryClient = useQueryClient();
  const components = useQuery({ queryKey: ["components", websiteId], queryFn: () => operationsApi.components(websiteId) });
  const form = useForm<ComponentForm>({
    resolver: zodResolver(componentForm),
    defaultValues: { name: "", slug: "", type: "laravel", relative_working_directory: "", runtime: "", process_manager: "", process_name: "" },
  });
  const create = useMutation({
    mutationFn: (values: ComponentForm) => operationsApi.createComponent(websiteId, values),
    onSuccess: async () => {
      toast.success("Component saved.");
      form.reset();
      await queryClient.invalidateQueries({ queryKey: ["components", websiteId] });
    },
    onError: (error) => toast.error(normalizeApiError(error).message),
  });

  if (components.isLoading) {
    return <Skeleton className="h-[520px]" />;
  }

  return (
    <div className="space-y-5">
      <div><h1 className="text-2xl font-semibold">Component settings</h1><p className="mt-1 text-sm text-muted">Components map approved roots to frameworks, runtimes and process managers.</p></div>
      <WebsiteOperationsNav websiteId={websiteId} />
      <div className="grid gap-4 lg:grid-cols-[1fr_420px]">
        <Card>
          <CardHeader><h2 className="font-semibold">Configured Components</h2></CardHeader>
          <CardContent className="space-y-2">
            {components.data?.map((component) => (
              <div key={component.id} className="flex items-center justify-between rounded-[var(--radius)] border border-border p-3 text-sm">
                <div><div className="font-medium">{component.name}</div><div className="text-xs text-muted">{component.type} · {component.relative_working_directory || "root"}</div></div>
                <Badge value={component.status} />
              </div>
            ))}
          </CardContent>
        </Card>
        <Card>
          <CardHeader><h2 className="font-semibold">Add Component</h2></CardHeader>
          <CardContent>
            <form className="space-y-3" onSubmit={form.handleSubmit((values) => create.mutate(values))}>
              <Input placeholder="Name" {...form.register("name")} />
              <Input placeholder="slug" {...form.register("slug")} />
              <select className="h-10 w-full rounded-[var(--radius)] border border-border bg-panel px-3 text-sm" {...form.register("type")}>
                {["laravel", "nextjs", "vite", "node", "static", "database", "worker", "custom"].map((type) => <option key={type} value={type}>{type}</option>)}
              </select>
              <Input placeholder="Relative working directory" {...form.register("relative_working_directory")} />
              <Input placeholder="Runtime, for example php-8.3" {...form.register("runtime")} />
              <Input placeholder="Process manager, for example pm2" {...form.register("process_manager")} />
              <Input placeholder="Configured process name" {...form.register("process_name")} />
              <Button className="w-full" disabled={create.isPending}><Save className="h-4 w-4" />Save component</Button>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
