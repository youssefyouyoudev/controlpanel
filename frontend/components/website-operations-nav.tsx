"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Activity, Archive, Container, FileTerminal, FileText, GitBranch, PlayCircle, Rocket, Settings2 } from "lucide-react";
import { cn } from "@/lib/utils";

const items = [
  { suffix: "overview", label: "Overview", icon: Activity },
  { suffix: "actions", label: "Actions", icon: PlayCircle },
  { suffix: "deployments", label: "Deployments", icon: Rocket },
  { suffix: "containers", label: "Containers", icon: Container },
  { suffix: "console", label: "Console", icon: FileTerminal },
  { suffix: "logs", label: "Logs", icon: FileText },
  { suffix: "git", label: "Git", icon: GitBranch },
  { suffix: "backups", label: "Backups", icon: Archive },
  { suffix: "settings/components", label: "Settings", icon: Settings2 },
];

export function WebsiteOperationsNav({ websiteId }: { websiteId: string }) {
  const pathname = usePathname();

  return (
    <nav className="flex gap-1 overflow-x-auto border-b border-border pb-2">
      {items.map((item) => {
        const href = `/websites/${websiteId}/${item.suffix}`;
        const Icon = item.icon;
        const active = pathname === href || pathname.startsWith(`${href}/`);
        return (
          <Link
            key={item.suffix}
            href={href}
            className={cn(
              "inline-flex h-9 shrink-0 items-center gap-2 rounded-[var(--radius)] px-3 text-sm text-muted hover:bg-panel-muted hover:text-foreground",
              active && "bg-panel-muted text-foreground",
            )}
          >
            <Icon className="h-4 w-4" />
            {item.label}
          </Link>
        );
      })}
    </nav>
  );
}
