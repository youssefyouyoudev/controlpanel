import type { ActionDefinition, ActionExecution, UserRole } from "@/lib/schemas";

export function canRunAction(role: UserRole | undefined, action: ActionDefinition): boolean {
  if (!role || !action.enabled) {
    return false;
  }

  if (action.required_role === "owner") {
    return role === "owner";
  }

  if (action.required_role === "developer") {
    return role === "owner" || role === "developer";
  }

  if (action.required_role === "editor") {
    return role === "owner" || role === "developer" || role === "editor";
  }

  return true;
}

export function confirmationMode(action: ActionDefinition): "none" | "standard" | "typed-password" | "disabled" {
  if (!action.enabled || action.risk_level === "critical") {
    return "disabled";
  }

  if (action.requires_password_confirmation || action.risk_level === "high") {
    return "typed-password";
  }

  if (action.requires_confirmation || action.risk_level === "medium") {
    return "standard";
  }

  return "none";
}

export function executionHumanState(execution: Pick<ActionExecution, "status" | "action_key">): string {
  const label = execution.action_key.replaceAll(".", " ");
  return matchStatus(execution.status, label);
}

function matchStatus(status: ActionExecution["status"], label: string): string {
  switch (status) {
    case "queued":
      return `${label} is queued`;
    case "preparing":
      return `${label} is preparing`;
    case "running":
      return `${label} is running`;
    case "succeeded":
      return `${label} completed`;
    case "failed":
      return `${label} failed`;
    case "timed_out":
      return `${label} timed out`;
    case "blocked":
      return `${label} was blocked`;
    case "cancelled":
      return `${label} was cancelled`;
  }
}

export function riskTone(risk: ActionDefinition["risk_level"]): string {
  return {
    low: "text-success",
    medium: "text-warning",
    high: "text-danger",
    critical: "text-danger",
  }[risk];
}
