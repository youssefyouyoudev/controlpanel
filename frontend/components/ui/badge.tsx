import { cn } from "@/lib/utils";

const toneMap = {
  healthy: "border-success/30 bg-success/10 text-success",
  running: "border-success/30 bg-success/10 text-success",
  degraded: "border-warning/30 bg-warning/10 text-warning",
  offline: "border-danger/30 bg-danger/10 text-danger",
  stopped: "border-danger/30 bg-danger/10 text-danger",
  failed: "border-danger/30 bg-danger/10 text-danger",
  unknown: "border-border bg-panel-muted text-muted",
  unavailable: "border-border bg-panel-muted text-muted",
};

export function Badge({ value, className }: { value: string; className?: string }) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-[6px] border px-2 py-0.5 text-xs font-medium capitalize",
        toneMap[value as keyof typeof toneMap] ?? toneMap.unknown,
        className,
      )}
    >
      {value.replace("-", " ")}
    </span>
  );
}
