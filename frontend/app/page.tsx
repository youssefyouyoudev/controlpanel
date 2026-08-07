"use client";

import Link from "next/link";
import { Activity, Archive, Code2, FileText, Globe2, Lock, Server } from "lucide-react";
import { motion } from "motion/react";
import { useAuth } from "@/components/auth-provider";
import { Button } from "@/components/ui/button";

const capabilities = [
  { label: "Monitor websites", icon: Globe2 },
  { label: "Manage files visually", icon: FileText },
  { label: "Deploy safely", icon: Code2 },
  { label: "View logs", icon: Activity },
  { label: "Create backups", icon: Archive },
];

export default function Home() {
  const { isAuthenticated, isLoading } = useAuth();

  return (
    <main className="min-h-screen bg-background text-foreground">
      <section className="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-5 py-5">
        <header className="flex items-center justify-between">
          <Link href="/" className="flex items-center gap-3">
            <div className="grid h-9 w-9 place-items-center rounded-[var(--radius)] bg-accent text-sm font-semibold text-accent-foreground">Y</div>
            <div>
              <div className="text-sm font-semibold">YouPanel</div>
              <div className="text-xs text-muted">Personal server cockpit</div>
            </div>
          </Link>
          <div className="flex items-center gap-2">
            {isAuthenticated ? (
              <Link href="/dashboard">
                <Button type="button" variant="secondary">Open dashboard</Button>
              </Link>
            ) : null}
            <Link href="/login">
              <Button type="button" variant="primary">Sign in</Button>
            </Link>
          </div>
        </header>

        <div className="grid flex-1 items-center gap-10 py-14 lg:grid-cols-[1.05fr_0.95fr]">
          <motion.div initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} className="max-w-2xl">
            <div className="mb-5 inline-flex items-center gap-2 rounded-[var(--radius)] border border-border bg-panel px-3 py-1 text-xs text-muted">
              <Lock className="h-3.5 w-3.5" />
              Private access for trusted users
            </div>
            <h1 className="text-4xl font-semibold leading-tight tracking-normal md:text-6xl">Your personal server, made simple</h1>
            <p className="mt-5 max-w-xl text-base leading-7 text-muted md:text-lg">
              YouPanel turns routine server care into a calm control surface for websites, files, deployments, logs and backups.
            </p>
            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
              <Link href="/login">
                <Button type="button" variant="primary" className="w-full sm:w-auto">Sign in</Button>
              </Link>
              {isAuthenticated ? (
                <Link href="/dashboard">
                  <Button type="button" variant="secondary" className="w-full sm:w-auto">Open dashboard</Button>
                </Link>
              ) : (
                <Button type="button" variant="secondary" className="w-full sm:w-auto" disabled>
                  {isLoading ? "Checking session..." : "Dashboard requires sign in"}
                </Button>
              )}
            </div>
          </motion.div>

          <div className="rounded-[var(--radius)] border border-border bg-panel p-4 shadow-sm">
            <div className="mb-4 flex items-center justify-between border-b border-border pb-3">
              <div className="flex items-center gap-2 text-sm font-medium">
                <Server className="h-4 w-4 text-accent" />
                YouPanel cockpit
              </div>
              <span className="text-xs text-muted">No public server data</span>
            </div>
            <div className="grid gap-3">
              {capabilities.map((capability) => {
                const Icon = capability.icon;
                return (
                  <div key={capability.label} className="flex items-center gap-3 rounded-[var(--radius)] border border-border bg-background p-3">
                    <div className="grid h-8 w-8 place-items-center rounded-[var(--radius)] bg-panel-muted text-accent">
                      <Icon className="h-4 w-4" />
                    </div>
                    <span className="text-sm font-medium">{capability.label}</span>
                  </div>
                );
              })}
            </div>
            <p className="mt-4 rounded-[var(--radius)] border border-border bg-panel-muted p-3 text-xs leading-5 text-muted">
              Public visitors see only this overview. Server metrics, paths, logs and operational controls require an active Laravel Sanctum session and backend authorization.
            </p>
          </div>
        </div>
      </section>
    </main>
  );
}
