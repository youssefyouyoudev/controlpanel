import type { AllowedPath, FileEntry } from "@/lib/schemas";
import type { ApiError } from "@/lib/api";

export type PathCrumb = {
  label: string;
  path: string;
};

const languageByExtension: Record<string, string> = {
  css: "css",
  env: "dotenv",
  html: "html",
  js: "javascript",
  json: "json",
  md: "markdown",
  php: "php",
  ts: "typescript",
  tsx: "typescript",
  txt: "plaintext",
  xml: "xml",
  yml: "yaml",
  yaml: "yaml",
};

export function buildBreadcrumbs(relativePath: string): PathCrumb[] {
  const segments = normalizeClientPath(relativePath).split("/").filter(Boolean);
  const crumbs: PathCrumb[] = [{ label: "Root", path: "" }];

  segments.forEach((segment, index) => {
    crumbs.push({ label: segment, path: segments.slice(0, index + 1).join("/") });
  });

  return crumbs;
}

export function joinClientPath(directory: string, name: string): string {
  return normalizeClientPath([directory, name].filter(Boolean).join("/"));
}

export function parentPath(relativePath: string): string {
  const parts = normalizeClientPath(relativePath).split("/").filter(Boolean);
  parts.pop();

  return parts.join("/");
}

export function normalizeClientPath(path: string): string {
  return path.replace(/\\/g, "/").replace(/^\/+/, "").replace(/\/+/g, "/").replace(/\/$/, "");
}

export function languageForPath(path: string): string {
  const normalized = normalizeClientPath(path);
  if (normalized.endsWith(".blade.php")) {
    return "php";
  }

  const extension = normalized.split(".").pop()?.toLowerCase() ?? "";

  return languageByExtension[extension] ?? "plaintext";
}

export function fileIconKind(entry: Pick<FileEntry, "name" | "type" | "editable">): "folder" | "code" | "text" | "image" | "archive" | "binary" {
  if (entry.type === "directory") {
    return "folder";
  }

  const extension = entry.name.split(".").pop()?.toLowerCase() ?? "";
  if (["png", "jpg", "jpeg", "gif", "webp", "svg"].includes(extension)) {
    return "image";
  }

  if (["zip", "tar", "gz"].includes(extension)) {
    return "archive";
  }

  if (entry.editable) {
    return ["php", "ts", "tsx", "js", "jsx", "json", "css", "html", "yml", "yaml"].includes(extension) ? "code" : "text";
  }

  return "binary";
}

export function formatBytes(bytes: number | null): string {
  if (bytes === null) {
    return "-";
  }

  if (bytes < 1024) {
    return `${bytes} B`;
  }

  const units = ["KB", "MB", "GB"];
  let value = bytes / 1024;
  let unitIndex = 0;

  while (value >= 1024 && unitIndex < units.length - 1) {
    value /= 1024;
    unitIndex += 1;
  }

  return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[unitIndex]}`;
}

export function rootModeLabel(root: AllowedPath): string {
  if (!root.is_active) {
    return "Inactive";
  }

  if (root.capabilities.write || root.capabilities.upload || root.capabilities.delete) {
    return "Read/write";
  }

  return "Read-only";
}

export function canOpenInEditor(root: AllowedPath | undefined, entry: FileEntry | null): boolean {
  return Boolean(root?.capabilities.read && entry?.type === "file" && entry.editable);
}

export function isConflictError(error: ApiError | null | undefined): boolean {
  return error?.status === 409;
}
