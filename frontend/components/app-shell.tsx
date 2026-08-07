"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useTheme } from "next-themes";
import {
  Activity,
  Archive,
  Bell,
  Command,
  Container,
  Rocket,
  Globe2,
  LayoutDashboard,
  LogOut,
  Menu,
  Moon,
  Search,
  Server,
  ShieldAlert,
  Sun,
  User,
  X,
} from "lucide-react";
import * as React from "react";
import { motion } from "motion/react";
import { useAuth } from "@/components/auth-provider";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { canManageServer } from "@/lib/permissions";
import { notificationApi } from "@/lib/api";
import { useQuery } from "@tanstack/react-query";
import { isDemoMode } from "@/lib/env";

const navItems = [
  { href: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
  { href: "/websites", label: "Websites", icon: Globe2 },
  { href: "/actions", label: "Actions", icon: Activity },
  { href: "/deployments", label: "Deployments", icon: Rocket },
  { href: "/containers", label: "Containers", icon: Container },
  { href: "/backups", label: "Backups", icon: Archive },
  { href: "/server", label: "Server", icon: Server, ownerOnly: true },
  { href: "/activity", label: "Activity", icon: Activity },
  { href: "/settings/integrations/coolify", label: "Coolify", icon: Rocket, ownerOnly: true },
  { href: "/settings/profile", label: "Profile", icon: User },
  { href: "/settings/security", label: "Security", icon: ShieldAlert },
];

export function AppShell({ children }: { children: React.ReactNode }) {
  const [mobileOpen, setMobileOpen] = React.useState(false);
  const pathname = usePathname();
  const { user, status, logout } = useAuth();
  const { resolvedTheme, setTheme } = useTheme();
  const [notificationsOpen, setNotificationsOpen] = React.useState(false);
  const notifications = useQuery({ queryKey: ["notifications"], queryFn: notificationApi.list, enabled: Boolean(user), refetchInterval: 15000 });
  const visibleItems = navItems.filter((item) => !item.ownerOnly || canManageServer(user?.role));

  if (status === "loading") {
    return (
      <main className="grid min-h-screen place-items-center px-6">
        <div className="rounded-[var(--radius)] border border-border bg-panel p-6 text-sm text-muted shadow-sm">Checking your YouPanel session...</div>
      </main>
    );
  }

  if (status === "unauthenticated") {
    return (
      <main className="grid min-h-screen place-items-center px-6">
        <div className="rounded-[var(--radius)] border border-border bg-panel p-6 text-sm text-muted shadow-sm">Redirecting to sign in...</div>
      </main>
    );
  }

  if (status === "error") {
    return (
      <main className="grid min-h-screen place-items-center px-6">
        <div className="max-w-md rounded-[var(--radius)] border border-danger/30 bg-danger/10 p-6 text-sm text-danger shadow-sm">
          Unable to verify your session. Check that the Laravel API is running and reachable.
        </div>
      </main>
    );
  }

  return (
    <div className="min-h-screen lg:grid lg:grid-cols-[248px_1fr]">
      <aside className="hidden border-r border-border bg-panel lg:block">
        <Sidebar pathname={pathname} items={visibleItems} />
      </aside>

      {mobileOpen ? (
        <div className="fixed inset-0 z-40 bg-background/80 backdrop-blur-sm lg:hidden">
          <motion.aside
            initial={{ x: -280 }}
            animate={{ x: 0 }}
            className="h-full w-[280px] border-r border-border bg-panel"
          >
            <div className="flex justify-end p-3">
              <Button size="icon" variant="ghost" aria-label="Close navigation" onClick={() => setMobileOpen(false)}>
                <X className="h-4 w-4" />
              </Button>
            </div>
            <Sidebar pathname={pathname} items={visibleItems} onNavigate={() => setMobileOpen(false)} />
          </motion.aside>
        </div>
      ) : null}

      <div className="min-w-0">
        <header className="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-border bg-background/90 px-4 backdrop-blur">
          <div className="flex items-center gap-3">
            <Button size="icon" variant="ghost" aria-label="Open navigation" className="lg:hidden" onClick={() => setMobileOpen(true)}>
              <Menu className="h-4 w-4" />
            </Button>
            <Breadcrumb pathname={pathname} />
            {isDemoMode ? <span className="rounded-[var(--radius)] border border-warning/30 bg-warning/10 px-2 py-1 text-xs text-warning">Read-only demo</span> : null}
          </div>
          <div className="flex items-center gap-2">
            <button className="hidden h-9 items-center gap-2 rounded-[var(--radius)] border border-border bg-panel px-3 text-sm text-muted md:flex">
              <Command className="h-4 w-4" />
              Search YouPanel
              <span className="ml-8 rounded border border-border px-1.5 text-xs">Ctrl K</span>
            </button>
            <div className="relative">
              <Button size="icon" variant="ghost" aria-label="Notifications" title="Notifications" onClick={() => setNotificationsOpen((value) => !value)}>
                <Bell className="h-4 w-4" />
                {notifications.data?.unread_count ? <span className="absolute right-1 top-1 h-2 w-2 rounded-full bg-danger" /> : null}
              </Button>
              {notificationsOpen ? (
                <div className="absolute right-0 top-11 z-50 w-80 rounded-[var(--radius)] border border-border bg-panel p-2 shadow-xl">
                  <div className="flex items-center justify-between px-2 py-1 text-sm font-medium">
                    Notifications
                    <button className="text-xs text-muted hover:text-foreground" onClick={() => notificationApi.readAll().then(() => notifications.refetch())}>Mark all read</button>
                  </div>
                  <div className="max-h-80 overflow-auto">
                    {notifications.data?.notifications.length === 0 ? <div className="p-3 text-sm text-muted">No notifications.</div> : null}
                    {notifications.data?.notifications.slice(0, 8).map((item) => (
                      <button key={item.id} className="block w-full rounded-[var(--radius)] p-2 text-left text-sm hover:bg-panel-muted" onClick={() => notificationApi.read(item.id).then(() => notifications.refetch())}>
                        <span className={cn("font-medium", !item.read_at && "text-accent")}>{item.title}</span>
                        <span className="mt-1 block text-xs text-muted">{item.body}</span>
                      </button>
                    ))}
                  </div>
                </div>
              ) : null}
            </div>
            <Button
              size="icon"
              variant="ghost"
              aria-label="Toggle theme"
              onClick={() => setTheme(resolvedTheme === "dark" ? "light" : "dark")}
            >
              {resolvedTheme === "dark" ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
            </Button>
            <Button variant="ghost" size="sm" onClick={logout}>
              <LogOut className="h-4 w-4" />
              <span className="hidden sm:inline">Logout</span>
            </Button>
          </div>
        </header>

        <main className="mx-auto w-full max-w-7xl px-4 py-6 md:px-6">{children}</main>
      </div>
    </div>
  );
}

function Sidebar({
  pathname,
  items,
  onNavigate,
}: {
  pathname: string;
  items: typeof navItems;
  onNavigate?: () => void;
}) {
  const { user } = useAuth();

  return (
    <div className="flex h-full min-h-screen flex-col p-4">
      <Link href="/dashboard" className="mb-6 flex items-center gap-3" onClick={onNavigate}>
        <div className="grid h-9 w-9 place-items-center rounded-[var(--radius)] bg-accent text-accent-foreground font-semibold">Y</div>
        <div>
          <div className="text-sm font-semibold">YouPanel</div>
          <div className="text-xs text-muted">Personal OS</div>
        </div>
      </Link>

      <nav className="space-y-1">
        {items.map((item) => {
          const Icon = item.icon;
          const active = pathname === item.href || pathname.startsWith(`${item.href}/`);
          return (
            <Link
              key={item.href}
              href={item.href}
              onClick={onNavigate}
              className={cn(
                "flex h-9 items-center gap-3 rounded-[var(--radius)] px-3 text-sm text-muted transition hover:bg-panel-muted hover:text-foreground",
                active && "bg-panel-muted text-foreground",
              )}
            >
              <Icon className="h-4 w-4" />
              {item.label}
            </Link>
          );
        })}
      </nav>

      <div className="mt-auto rounded-[var(--radius)] border border-border bg-panel-muted p-3">
        <div className="text-sm font-medium">{user?.name ?? "Loading"}</div>
        <div className="truncate text-xs text-muted">{user?.email}</div>
        <div className="mt-2 text-xs capitalize text-accent">{user?.role}</div>
      </div>
    </div>
  );
}

function Breadcrumb({ pathname }: { pathname: string }) {
  const parts = pathname.split("/").filter(Boolean);
  const label = parts.length === 0 ? "Dashboard" : parts.map((part) => part.replace("-", " ")).join(" / ");

  return (
    <div className="flex items-center gap-2 text-sm text-muted">
      <Search className="h-4 w-4" />
      <span className="capitalize">{label}</span>
    </div>
  );
}
