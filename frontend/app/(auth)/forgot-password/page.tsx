"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import Link from "next/link";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { authApi } from "@/lib/api";

const schema = z.object({ email: z.string().email("Enter a valid email.") });

export default function ForgotPasswordPage() {
  const form = useForm<z.infer<typeof schema>>({ resolver: zodResolver(schema), defaultValues: { email: "" } });
  const mutation = useMutation({ mutationFn: (values: z.infer<typeof schema>) => authApi.forgotPassword(values.email) });

  return (
    <main className="grid min-h-screen place-items-center px-6">
      <form className="w-full max-w-sm rounded-[var(--radius)] border border-border bg-panel p-6" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
        <h1 className="text-lg font-semibold">Reset your password</h1>
        <p className="mt-2 text-sm text-muted">If the account exists, YouPanel will send reset instructions.</p>
        <label className="mt-5 block text-sm font-medium" htmlFor="email">Email</label>
        <Input id="email" type="email" className="mt-1" {...form.register("email")} />
        <p className="mt-1 min-h-5 text-xs text-danger">{form.formState.errors.email?.message}</p>
        {mutation.isSuccess ? <p className="mb-3 text-sm text-success">Reset request accepted.</p> : null}
        <Button type="submit" variant="primary" className="w-full" disabled={mutation.isPending}>Send reset link</Button>
        <Link href="/login" className="mt-4 block text-center text-sm text-accent">Back to login</Link>
      </form>
    </main>
  );
}
