"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { authApi, normalizeApiError } from "@/lib/api";

const schema = z.object({
  email: z.string().email(),
  token: z.string().min(1),
  password: z.string().min(12),
  password_confirmation: z.string().min(12),
}).refine((values) => values.password === values.password_confirmation, {
  message: "Passwords must match.",
  path: ["password_confirmation"],
});

export default function ResetPasswordPage() {
  return <Suspense fallback={<main className="grid min-h-screen place-items-center px-6">Loading...</main>}><ResetPasswordPanel /></Suspense>;
}

function ResetPasswordPanel() {
  const params = useSearchParams();
  const form = useForm<z.infer<typeof schema>>({
    resolver: zodResolver(schema),
    defaultValues: { email: params.get("email") ?? "", token: params.get("token") ?? "", password: "", password_confirmation: "" },
  });
  const mutation = useMutation({ mutationFn: authApi.resetPassword });
  const error = mutation.isError ? normalizeApiError(mutation.error) : null;

  return (
    <main className="grid min-h-screen place-items-center px-6">
      <form className="w-full max-w-sm rounded-[var(--radius)] border border-border bg-panel p-6" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
        <h1 className="text-lg font-semibold">Set a new password</h1>
        <p className="mt-2 text-sm text-muted">Use the secure reset link from your email.</p>
        <label className="mt-4 block text-sm font-medium" htmlFor="email">Email</label>
        <Input id="email" type="email" className="mt-1" {...form.register("email")} />
        <label className="mt-4 block text-sm font-medium" htmlFor="token">Reset token</label>
        <Input id="token" className="mt-1" {...form.register("token")} />
        <label className="mt-4 block text-sm font-medium" htmlFor="password">New password</label>
        <Input id="password" type="password" className="mt-1" {...form.register("password")} />
        <label className="mt-4 block text-sm font-medium" htmlFor="password_confirmation">Confirm password</label>
        <Input id="password_confirmation" type="password" className="mt-1" {...form.register("password_confirmation")} />
        <p className="mt-2 min-h-5 text-xs text-danger">{form.formState.errors.password_confirmation?.message ?? error?.message}</p>
        {mutation.isSuccess ? <p className="mb-3 text-sm text-success">Password reset. You can sign in now.</p> : null}
        <Button type="submit" variant="primary" className="w-full" disabled={mutation.isPending}>Reset password</Button>
        <Link href="/login" className="mt-4 block text-center text-sm text-accent">Back to login</Link>
      </form>
    </main>
  );
}
