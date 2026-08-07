"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, CheckCircle2, RefreshCw, Save, ShieldAlert } from "lucide-react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { z } from "zod";
import { useAuth } from "@/components/auth-provider";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { fileApi, normalizeApiError, websiteApi } from "@/lib/api";
import { rootModeLabel } from "@/lib/file-workspace";
import type { AllowedPath } from "@/lib/schemas";

const rootSchema = z.object({
  name: z.string().min(2, "Name is required."),
  relative_label: z.string().optional(),
  absolute_path: z.string().min(1, "Absolute path is required."),
  is_active: z.boolean(),
  is_primary: z.boolean(),
  can_read: z.boolean(),
  can_write: z.boolean(),
  can_upload: z.boolean(),
  can_create: z.boolean(),
  can_rename: z.boolean(),
  can_move: z.boolean(),
  can_copy: z.boolean(),
  can_delete: z.boolean(),
  can_archive: z.boolean(),
  can_extract: z.boolean(),
  max_upload_bytes: z.coerce.number().int().positive().optional(),
  allowed_extensions: z.string().optional(),
  blocked_patterns: z.string().optional(),
});

type RootForm = z.infer<typeof rootSchema>;

const capabilityFields: Array<keyof RootForm> = [
  "can_read",
  "can_write",
  "can_upload",
  "can_create",
  "can_rename",
  "can_move",
  "can_copy",
  "can_delete",
  "can_archive",
  "can_extract",
];

export default function FileRootSettingsPage() {
  const params = useParams<{ id: string }>();
  const websiteId = params.id;
  const queryClient = useQueryClient();
  const { user } = useAuth();
  const website = useQuery({ queryKey: ["websites", websiteId], queryFn: () => websiteApi.show(websiteId) });
  const roots = useQuery({ queryKey: ["websites", websiteId, "file-roots"], queryFn: () => fileApi.roots(websiteId) });
  const isOwner = user?.role === "owner";

  const form = useForm<RootForm>({
    resolver: zodResolver(rootSchema),
    defaultValues: defaultValues(),
  });

  const saveRoot = useMutation({
    mutationFn: (values: RootForm) =>
      fileApi.createRoot(websiteId, {
        ...values,
        allowed_extensions: splitList(values.allowed_extensions),
        blocked_patterns: splitList(values.blocked_patterns),
      }),
    onSuccess: async () => {
      toast.success("File root saved.");
      form.reset(defaultValues());
      await queryClient.invalidateQueries({ queryKey: ["websites", websiteId, "file-roots"] });
    },
    onError: (error) => toast.error(normalizeApiError(error).message),
  });

  const validateRoot = useMutation({
    mutationFn: (rootId: number) => fileApi.validateRoot(websiteId, rootId),
    onSuccess: async () => {
      toast.success("Root diagnostics refreshed.");
      await queryClient.invalidateQueries({ queryKey: ["websites", websiteId, "file-roots"] });
    },
    onError: (error) => toast.error(normalizeApiError(error).message),
  });

  if (website.isLoading || roots.isLoading) {
    return <Skeleton className="h-[560px]" />;
  }

  if (!isOwner) {
    return (
      <Card>
        <CardContent className="p-8 text-center">
          <ShieldAlert className="mx-auto h-10 w-10 text-warning" />
          <h1 className="mt-3 text-lg font-semibold">Owner access required</h1>
          <p className="mt-2 text-sm text-muted">File roots expose server paths and can only be configured by the owner.</p>
          <Link href={`/websites/${websiteId}/files`} className="mt-4 inline-flex h-9 items-center rounded-[var(--radius)] border border-border px-3 text-sm hover:bg-panel-muted">Back to files</Link>
        </CardContent>
      </Card>
    );
  }

  if (website.isError || roots.isError || !website.data) {
    return <Card><CardContent className="p-6 text-sm text-danger">Unable to load file root settings.</CardContent></Card>;
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <Link href={`/websites/${websiteId}/files`} className="inline-flex items-center gap-2 text-sm text-muted hover:text-foreground">
            <ArrowLeft className="h-4 w-4" />
            File workspace
          </Link>
          <h1 className="mt-2 text-2xl font-semibold">File roots</h1>
          <p className="mt-1 text-sm text-muted">Approve exact directories before they appear in the browser workspace.</p>
        </div>
        <Badge value={website.data.status} />
      </div>

      <div className="grid gap-4 lg:grid-cols-[1fr_420px]">
        <Card>
          <CardHeader><h2 className="font-semibold">Configured roots</h2></CardHeader>
          <CardContent className="space-y-3">
            {roots.data?.length === 0 ? <p className="text-sm text-muted">No roots are configured yet.</p> : null}
            {roots.data?.map((root) => (
              <RootCard key={root.id} root={root} onValidate={() => validateRoot.mutate(root.id)} validating={validateRoot.isPending} />
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <h2 className="font-semibold">Add approved root</h2>
            <p className="mt-1 text-xs text-muted">Use project-specific directories only. System paths and secrets are rejected by the API.</p>
          </CardHeader>
          <CardContent>
            <form className="space-y-4" onSubmit={form.handleSubmit((values) => saveRoot.mutate(values))}>
              <Field label="Name" error={form.formState.errors.name?.message}>
                <Input {...form.register("name")} placeholder="Production frontend" />
              </Field>
              <Field label="Display label" error={form.formState.errors.relative_label?.message}>
                <Input {...form.register("relative_label")} placeholder="frontend" />
              </Field>
              <Field label="Absolute path" error={form.formState.errors.absolute_path?.message}>
                <Input {...form.register("absolute_path")} placeholder="/var/www/example.com/current" />
              </Field>
              <Field label="Max upload bytes" error={form.formState.errors.max_upload_bytes?.message}>
                <Input type="number" {...form.register("max_upload_bytes")} />
              </Field>
              <Field label="Allowed extensions">
                <Input {...form.register("allowed_extensions")} placeholder="php, ts, tsx, css, json, md" />
              </Field>
              <Field label="Blocked patterns">
                <Input {...form.register("blocked_patterns")} placeholder=".env, *.key, *.pem" />
              </Field>

              <div className="grid grid-cols-2 gap-2">
                <Toggle label="Active" {...form.register("is_active")} />
                <Toggle label="Primary" {...form.register("is_primary")} />
                {capabilityFields.map((field) => (
                  <Toggle key={field} label={field.replace("can_", "").replace("_", " ")} {...form.register(field)} />
                ))}
              </div>

              <Button type="submit" className="w-full" disabled={saveRoot.isPending}>
                <Save className="h-4 w-4" />
                Save root
              </Button>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

function RootCard({ root, onValidate, validating }: { root: AllowedPath; onValidate: () => void; validating: boolean }) {
  return (
    <div className="rounded-[var(--radius)] border border-border p-4">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            <h3 className="truncate font-medium">{root.label}</h3>
            {root.is_primary ? <CheckCircle2 className="h-4 w-4 text-success" /> : null}
          </div>
          <p className="mt-1 truncate font-mono text-xs text-muted">{root.absolute_path ?? "Path hidden from this role"}</p>
        </div>
        <Badge value={rootModeLabel(root).toLowerCase().replace("/", "-")} />
      </div>
      <div className="mt-3 grid gap-2 text-xs text-muted sm:grid-cols-3">
        <span>Readable: {root.diagnostics?.readable ? "yes" : "no"}</span>
        <span>Writable: {root.diagnostics?.writable ? "yes" : "no"}</span>
        <span>Status: {root.diagnostics?.status ?? "unknown"}</span>
      </div>
      <div className="mt-3 flex flex-wrap gap-1">
        {Object.entries(root.capabilities).map(([name, enabled]) => (
          <span key={name} className="rounded border border-border px-2 py-0.5 text-xs text-muted">{name}: {enabled ? "on" : "off"}</span>
        ))}
      </div>
      <Button className="mt-3" size="sm" variant="secondary" onClick={onValidate} disabled={validating}>
        <RefreshCw className="h-4 w-4" />
        Refresh diagnostics
      </Button>
    </div>
  );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
  return (
    <label className="block space-y-1 text-sm">
      <span className="font-medium">{label}</span>
      {children}
      {error ? <span className="block text-xs text-danger">{error}</span> : null}
    </label>
  );
}

function Toggle({ label, ...props }: { label: string } & React.InputHTMLAttributes<HTMLInputElement>) {
  return (
    <label className="flex items-center gap-2 rounded-[var(--radius)] border border-border p-2 text-sm capitalize">
      <input type="checkbox" className="h-4 w-4 accent-[var(--accent)]" {...props} />
      {label}
    </label>
  );
}

function defaultValues(): RootForm {
  return {
    name: "",
    relative_label: "",
    absolute_path: "",
    is_active: true,
    is_primary: false,
    can_read: true,
    can_write: false,
    can_upload: false,
    can_create: false,
    can_rename: false,
    can_move: false,
    can_copy: true,
    can_delete: false,
    can_archive: true,
    can_extract: false,
    max_upload_bytes: 10 * 1024 * 1024,
    allowed_extensions: "php, ts, tsx, js, css, json, md, txt, yml, yaml, blade.php",
    blocked_patterns: ".env, .env.*, *.key, *.pem, id_rsa, id_ed25519",
  };
}

function splitList(value: string | undefined): string[] | null {
  const items = value?.split(",").map((item) => item.trim()).filter(Boolean) ?? [];

  return items.length > 0 ? items : null;
}
