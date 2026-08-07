"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Server } from "lucide-react";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { authApi, normalizeApiError } from "@/lib/api";
import { safeReturnTo } from "@/lib/routing";

export const loginSchema = z.object({
  email: z.string().email("Enter a valid email."),
  password: z.string().min(1, "Password is required."),
  remember: z.boolean().optional(),
});
const challengeSchema = z.object({
  code: z.string().min(1, "Enter your authenticator or recovery code."),
});

type LoginValues = z.infer<typeof loginSchema>;
type ChallengeValues = z.infer<typeof challengeSchema>;

export default function LoginPage() {
  return (
    <Suspense fallback={<main className="grid min-h-screen place-items-center px-6">Loading...</main>}>
      <LoginPanel />
    </Suspense>
  );
}

function LoginPanel() {
  const router = useRouter();
  const params = useSearchParams();
  const queryClient = useQueryClient();
  const returnTo = safeReturnTo(params.get("returnTo"));
  const [requiresTwoFactor, setRequiresTwoFactor] = useState(false);
  const form = useForm<LoginValues>({ resolver: zodResolver(loginSchema), defaultValues: { email: "", password: "", remember: false } });
  const challengeForm = useForm<ChallengeValues>({ resolver: zodResolver(challengeSchema), defaultValues: { code: "" } });
  const finishAuthentication = async (user: Awaited<ReturnType<typeof authApi.me>>) => {
    await queryClient.cancelQueries({ queryKey: ["auth", "me"] });
    queryClient.setQueryData(["auth", "me"], user);
    router.replace(returnTo);
    router.refresh();
  };
  const login = useMutation({
    mutationFn: authApi.login,
    onSuccess: async (result) => {
      if (result.type === "two_factor_required") {
        setRequiresTwoFactor(true);
        return;
      }

      await finishAuthentication(result.user);
    },
    onError: (error) => {
      const normalized = normalizeApiError(error);
      form.clearErrors();
      for (const [field, messages] of Object.entries(normalized.fields)) {
        if ((field === "email" || field === "password") && messages[0]) {
          form.setError(field, { type: "server", message: messages[0] });
        }
      }
    },
  });
  const challenge = useMutation({
    mutationFn: (values: ChallengeValues) => authApi.twoFactorChallenge(values.code.includes("-") ? { recovery_code: values.code } : { code: values.code }),
    onSuccess: async (user) => {
      await finishAuthentication(user);
    },
    onError: (error) => {
      const normalized = normalizeApiError(error);
      challengeForm.clearErrors();
      const message = normalized.fields.code?.[0] ?? normalized.fields.recovery_code?.[0];
      if (message) {
        challengeForm.setError("code", { type: "server", message });
      }
    },
  });
  const error = login.isError ? normalizeApiError(login.error) : challenge.isError ? normalizeApiError(challenge.error) : null;

  return (
    <main className="grid min-h-screen place-items-center px-6">
      <form
        className="w-full max-w-sm rounded-[var(--radius)] border border-border bg-panel p-6 shadow-sm"
        onSubmit={requiresTwoFactor
          ? challengeForm.handleSubmit((values) => {
            if (!challenge.isPending) {
              challenge.mutate(values);
            }
          })
          : form.handleSubmit((values) => {
            if (!login.isPending) {
              login.mutate(values);
            }
          })}
      >
        <div className="mb-6 flex items-center gap-3">
          <div className="grid h-10 w-10 place-items-center rounded-[var(--radius)] bg-accent text-accent-foreground">
            <Server className="h-5 w-5" />
          </div>
          <div>
            <h1 className="text-lg font-semibold">{requiresTwoFactor ? "Verify it is you" : "Sign in to YouPanel"}</h1>
            <p className="text-sm text-muted">{requiresTwoFactor ? "Enter your authenticator or recovery code." : "Private access for trusted users."}</p>
          </div>
        </div>
        {requiresTwoFactor ? (
          <>
            <label className="text-sm font-medium" htmlFor="code">Authentication code</label>
            <Input id="code" type="text" inputMode="numeric" autoComplete="one-time-code" className="mt-1" {...challengeForm.register("code")} />
            <p className="mt-1 min-h-5 text-xs text-danger">{challengeForm.formState.errors.code?.message}</p>
          </>
        ) : (
          <>
            <label className="text-sm font-medium" htmlFor="email">Email</label>
            <Input id="email" type="email" autoComplete="email" className="mt-1" {...form.register("email")} />
            <p className="mt-1 min-h-5 text-xs text-danger">{form.formState.errors.email?.message}</p>
            <label className="text-sm font-medium" htmlFor="password">Password</label>
            <Input id="password" type="password" autoComplete="current-password" className="mt-1" {...form.register("password")} />
            <p className="mt-1 min-h-5 text-xs text-danger">{form.formState.errors.password?.message}</p>
            <label className="mb-4 flex items-center gap-2 text-sm text-muted">
              <input type="checkbox" className="h-4 w-4 accent-[var(--accent)]" {...form.register("remember")} />
              Keep me signed in
            </label>
          </>
        )}
        {error ? <p className="mb-3 rounded-[var(--radius)] border border-danger/30 bg-danger/10 p-2 text-sm text-danger">{error.message}</p> : null}
        <Button type="submit" variant="primary" className="w-full" disabled={login.isPending || challenge.isPending}>
          {login.isPending || challenge.isPending ? "Checking..." : requiresTwoFactor ? "Verify and continue" : "Sign in"}
        </Button>
        {requiresTwoFactor ? (
          <button type="button" className="mt-4 block w-full text-center text-sm text-muted hover:text-foreground" onClick={() => setRequiresTwoFactor(false)}>Use a different account</button>
        ) : (
          <a href="/forgot-password" className="mt-4 block text-center text-sm text-accent">Forgot password?</a>
        )}
      </form>
    </main>
  );
}
