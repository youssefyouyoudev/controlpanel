import type { UserRole } from "@/lib/schemas";

export function canManageUsers(role: UserRole | undefined) {
  return role === "owner";
}

export function canManageServer(role: UserRole | undefined) {
  return role === "owner";
}

export function canModifyAssignedWebsite(role: UserRole | undefined) {
  return role === "owner" || role === "developer" || role === "editor";
}
