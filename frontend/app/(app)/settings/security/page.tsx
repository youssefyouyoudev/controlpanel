"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { KeyRound, ShieldCheck } from "lucide-react";
import Image from "next/image";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { authApi, normalizeApiError } from "@/lib/api";

const schema = z.object({
  current_password: z.string().min(1),
  password: z.string().min(12),
  password_confirmation: z.string().min(12),
}).refine((values) => values.password === values.password_confirmation, {
  message: "Passwords must match.",
  path: ["password_confirmation"],
});

const twoFactorCodeSchema = z.object({ code: z.string().min(1, "Enter the code from your authenticator app.") });
const passwordOnlySchema = z.object({ current_password: z.string().min(1, "Current password is required.") });

function svgDataUrl(svg: string): string {
  return `data:image/svg+xml;base64,${btoa(unescape(encodeURIComponent(svg)))}`;
}

export default function SecuritySettingsPage() {
  const queryClient = useQueryClient();
  const [setup, setSetup] = useState<Awaited<ReturnType<typeof authApi.startTwoFactor>> | null>(null);
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const form = useForm<z.infer<typeof schema>>({ resolver: zodResolver(schema) });
  const codeForm = useForm<z.infer<typeof twoFactorCodeSchema>>({ resolver: zodResolver(twoFactorCodeSchema), defaultValues: { code: "" } });
  const disableForm = useForm<z.infer<typeof passwordOnlySchema>>({ resolver: zodResolver(passwordOnlySchema), defaultValues: { current_password: "" } });
  const regenerateForm = useForm<z.infer<typeof passwordOnlySchema>>({ resolver: zodResolver(passwordOnlySchema), defaultValues: { current_password: "" } });
  const twoFactor = useQuery({ queryKey: ["auth", "two-factor"], queryFn: authApi.twoFactorStatus });
  const mutation = useMutation({
    mutationFn: authApi.updatePassword,
    onSuccess: () => {
      form.reset();
      toast.success("Password updated.");
    },
  });
  const startTwoFactor = useMutation({
    mutationFn: authApi.startTwoFactor,
    onSuccess: (data) => {
      setSetup(data);
      setRecoveryCodes(data.recovery_codes);
      toast.success("Scan the QR code and confirm your first code.");
    },
  });
  const confirmTwoFactor = useMutation({
    mutationFn: authApi.confirmTwoFactor,
    onSuccess: async () => {
      setSetup(null);
      codeForm.reset();
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["auth", "two-factor"] }),
        queryClient.invalidateQueries({ queryKey: ["auth", "me"] }),
      ]);
      toast.success("Two-factor authentication enabled.");
    },
  });
  const disableTwoFactor = useMutation({
    mutationFn: (values: z.infer<typeof passwordOnlySchema>) => authApi.disableTwoFactor(values.current_password),
    onSuccess: async () => {
      disableForm.reset();
      setRecoveryCodes([]);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["auth", "two-factor"] }),
        queryClient.invalidateQueries({ queryKey: ["auth", "me"] }),
      ]);
      toast.success("Two-factor authentication disabled.");
    },
  });
  const regenerateCodes = useMutation({
    mutationFn: (values: z.infer<typeof passwordOnlySchema>) => authApi.regenerateRecoveryCodes(values.current_password),
    onSuccess: (codes) => {
      regenerateForm.reset();
      setRecoveryCodes(codes);
      queryClient.invalidateQueries({ queryKey: ["auth", "two-factor"] });
      toast.success("Recovery codes regenerated.");
    },
  });
  const error = mutation.isError ? normalizeApiError(mutation.error) : null;
  const twoFactorError = startTwoFactor.isError
    ? normalizeApiError(startTwoFactor.error)
    : confirmTwoFactor.isError
      ? normalizeApiError(confirmTwoFactor.error)
      : disableTwoFactor.isError
        ? normalizeApiError(disableTwoFactor.error)
        : regenerateCodes.isError
          ? normalizeApiError(regenerateCodes.error)
          : null;

  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-semibold">Security Settings</h1>
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between gap-3">
            <div>
              <h2 className="font-semibold">Two-factor authentication</h2>
              <p className="text-sm text-muted">Protect YouPanel sign-ins with a time-based authenticator app.</p>
            </div>
            <span className={`rounded-[var(--radius)] border px-2 py-1 text-xs ${twoFactor.data?.enabled ? "border-success/30 bg-success/10 text-success" : "border-border text-muted"}`}>
              {twoFactor.data?.enabled ? "Enabled" : "Disabled"}
            </span>
          </div>
        </CardHeader>
        <CardContent>
          {twoFactor.isLoading ? <p className="text-sm text-muted">Checking two-factor status...</p> : null}
          {!twoFactor.data?.enabled && !setup ? (
            <Button type="button" variant="primary" onClick={() => startTwoFactor.mutate()} disabled={startTwoFactor.isPending}>
              <ShieldCheck className="h-4 w-4" />
              {startTwoFactor.isPending ? "Preparing..." : "Set up two-factor"}
            </Button>
          ) : null}

          {setup ? (
            <div className="grid gap-4 md:grid-cols-[240px_1fr]">
              <div className="rounded-[var(--radius)] border border-border bg-background p-3">
                <Image src={svgDataUrl(setup.qr_code_svg)} alt="Two-factor setup QR code" width={216} height={216} unoptimized className="h-auto w-full" />
              </div>
              <div className="space-y-3">
                <div>
                  <div className="text-sm font-medium">Manual setup key</div>
                  <code className="mt-1 block break-all rounded-[var(--radius)] border border-border bg-panel-muted p-2 text-xs">{setup.secret}</code>
                </div>
                <form className="flex max-w-sm gap-2" onSubmit={codeForm.handleSubmit((values) => confirmTwoFactor.mutate(values.code))}>
                  <Input placeholder="123456" autoComplete="one-time-code" {...codeForm.register("code")} />
                  <Button type="submit" variant="primary" disabled={confirmTwoFactor.isPending}>Confirm</Button>
                </form>
                <p className="text-xs text-danger">{codeForm.formState.errors.code?.message}</p>
              </div>
            </div>
          ) : null}

          {recoveryCodes.length > 0 ? (
            <div className="mt-4 rounded-[var(--radius)] border border-warning/30 bg-warning/10 p-3">
              <div className="mb-2 flex items-center gap-2 text-sm font-medium text-warning"><KeyRound className="h-4 w-4" /> Save these recovery codes now</div>
              <div className="grid gap-2 sm:grid-cols-2">
                {recoveryCodes.map((code) => <code key={code} className="rounded bg-background px-2 py-1 text-xs">{code}</code>)}
              </div>
            </div>
          ) : null}

          {twoFactor.data?.enabled ? (
            <div className="grid gap-4 md:grid-cols-2">
              <form className="space-y-2" onSubmit={regenerateForm.handleSubmit((values) => regenerateCodes.mutate(values))}>
                <label className="text-sm font-medium" htmlFor="regen_password">Regenerate recovery codes</label>
                <Input id="regen_password" type="password" placeholder="Current password" {...regenerateForm.register("current_password")} />
                <Button type="submit" variant="secondary" disabled={regenerateCodes.isPending}>Regenerate codes</Button>
              </form>
              <form className="space-y-2" onSubmit={disableForm.handleSubmit((values) => disableTwoFactor.mutate(values))}>
                <label className="text-sm font-medium" htmlFor="disable_password">Disable two-factor</label>
                <Input id="disable_password" type="password" placeholder="Current password" {...disableForm.register("current_password")} />
                <Button type="submit" variant="danger" disabled={disableTwoFactor.isPending}>Disable two-factor</Button>
              </form>
            </div>
          ) : null}
          {twoFactorError ? <p className="mt-3 rounded-[var(--radius)] border border-danger/30 bg-danger/10 p-2 text-sm text-danger">{twoFactorError.message}</p> : null}
        </CardContent>
      </Card>
      <Card>
        <CardHeader><h2 className="font-semibold">Password</h2></CardHeader>
        <CardContent>
          <form className="grid max-w-lg gap-3" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
            <label className="text-sm font-medium" htmlFor="current_password">Current password</label>
            <Input id="current_password" type="password" {...form.register("current_password")} />
            <label className="text-sm font-medium" htmlFor="password">New password</label>
            <Input id="password" type="password" {...form.register("password")} />
            <label className="text-sm font-medium" htmlFor="password_confirmation">Confirm new password</label>
            <Input id="password_confirmation" type="password" {...form.register("password_confirmation")} />
            <p className="min-h-5 text-sm text-danger">{form.formState.errors.password_confirmation?.message ?? error?.message}</p>
            <Button type="submit" variant="primary" className="w-fit" disabled={mutation.isPending}>Update password</Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
