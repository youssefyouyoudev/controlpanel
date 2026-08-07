"use client";

import * as React from "react";
import { cn } from "@/lib/utils";

export const Input = React.forwardRef<HTMLInputElement, React.InputHTMLAttributes<HTMLInputElement>>(
  ({ className, ...props }, ref) => (
    <input
      ref={ref}
      className={cn(
        "h-10 w-full rounded-[var(--radius)] border border-border bg-panel px-3 text-sm text-foreground placeholder:text-muted shadow-sm transition focus:border-accent",
        className,
      )}
      {...props}
    />
  ),
);

Input.displayName = "Input";
