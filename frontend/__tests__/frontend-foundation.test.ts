import { afterEach, describe, expect, it, vi } from "vitest";
import { NextRequest } from "next/server";
import { unstable_doesMiddlewareMatch } from "next/experimental/testing/server";
import { api, authApi, normalizeApiError } from "@/lib/api";
import { buildBreadcrumbs, canOpenInEditor, fileIconKind, formatBytes, isConflictError, joinClientPath, rootModeLabel } from "@/lib/file-workspace";
import { canRunAction, confirmationMode, executionHumanState } from "@/lib/operations";
import { canManageServer, canManageUsers, canModifyAssignedWebsite } from "@/lib/permissions";
import { allowedPathSchema, consoleCommandSchema, coolifyStatusSchema, dashboardSummarySchema, deploymentSchema, twoFactorSetupSchema, twoFactorStatusSchema, userSchema, type FileEntry } from "@/lib/schemas";
import { isProtectedRoute, safeReturnTo } from "@/lib/routing";
import { loginSchema } from "@/app/(auth)/login/page";
import { config as proxyConfig, proxy } from "@/proxy";
import nextConfig from "@/next.config";

afterEach(() => {
  vi.restoreAllMocks();
});

describe("login validation", () => {
  it("rejects invalid email and missing password", () => {
    const result = loginSchema.safeParse({ email: "bad", password: "" });
    expect(result.success).toBe(false);
  });
});

describe("routing classification", () => {
  it("keeps protected routes explicit and rejects unsafe return paths", () => {
    expect(isProtectedRoute("/dashboard")).toBe(true);
    expect(isProtectedRoute("/websites/1/files")).toBe(true);
    expect(isProtectedRoute("/")).toBe(false);
    expect(safeReturnTo("/dashboard?tab=summary")).toBe("/dashboard?tab=summary");
    expect(safeReturnTo("https://evil.example/dashboard")).toBe("/dashboard");
    expect(safeReturnTo("//evil.example/dashboard")).toBe("/dashboard");
    expect(safeReturnTo("javascript:alert(1)")).toBe("/dashboard");
    expect(safeReturnTo("/login")).toBe("/dashboard");
  });
});

describe("csp proxy", () => {
  it("adds a per-request nonce CSP without production unsafe-inline scripts", () => {
    const first = proxy(new NextRequest("http://localhost:3000/login"));
    const second = proxy(new NextRequest("http://localhost:3000/login"));
    const firstCsp = first.headers.get("Content-Security-Policy") ?? "";
    const secondCsp = second.headers.get("Content-Security-Policy") ?? "";
    const noncePattern = /'nonce-([^']+)'/;
    const firstNonce = firstCsp.match(noncePattern)?.[1];
    const secondNonce = secondCsp.match(noncePattern)?.[1];

    expect(firstNonce).toBeTruthy();
    expect(secondNonce).toBeTruthy();
    expect(firstNonce).not.toBe(secondNonce);
    expect(firstCsp).toContain("script-src 'self' 'nonce-");
    expect(firstCsp).toContain("style-src-elem 'self' 'unsafe-inline'");
    expect(firstCsp).toContain("style-src-attr 'unsafe-inline'");
    expect(firstCsp).toContain("'strict-dynamic'");
    expect(firstCsp).not.toContain("script-src 'self' 'unsafe-inline'");
    expect(firstCsp).not.toContain("style-src 'self' 'nonce-");
    expect(first.headers.get("X-Content-Type-Options")).toBe("nosniff");
  });

  it("does not treat missing frontend-domain cookies as proof of logout", () => {
    const response = proxy(new NextRequest("http://localhost:3000/dashboard?tab=metrics"));

    expect(response.status).toBe(200);
    expect(response.headers.get("location")).toBeNull();
    expect(response.headers.get("Content-Security-Policy")).toContain("script-src 'self' 'nonce-");
  });

  it("matches document routes and skips static assets", () => {
    expect(unstable_doesMiddlewareMatch({ config: proxyConfig, nextConfig, url: "/dashboard" })).toBe(true);
    expect(unstable_doesMiddlewareMatch({ config: proxyConfig, nextConfig, url: "/_next/static/chunk.js" })).toBe(false);
    expect(unstable_doesMiddlewareMatch({ config: proxyConfig, nextConfig, url: "/favicon.ico" })).toBe(false);
  });
});

describe("two-factor response validation", () => {
  it("accepts safe two-factor user and setup shapes without secret leakage on the user", () => {
    const user = userSchema.parse({
      id: 1,
      name: "Youssef",
      email: "youssef@example.com",
      role: "owner",
      avatar_url: null,
      is_active: true,
      timezone: "UTC",
      two_factor_enabled: true,
      last_login_at: null,
      email_verified_at: null,
    });
    const status = twoFactorStatusSchema.parse({ enabled: true, confirmed_at: new Date().toISOString(), recovery_codes_remaining: 8 });
    const setup = twoFactorSetupSchema.parse({ enabled: false, secret: "ABC123", otpauth_url: "otpauth://totp/YouPanel", qr_code_svg: "<svg />", recovery_codes: ["ABCDE-FGHIJ"] });

    expect(user.two_factor_enabled).toBe(true);
    expect(status.recovery_codes_remaining).toBe(8);
    expect(setup.recovery_codes[0]).toContain("-");
  });
});

describe("operations helpers", () => {
  const action = {
    key: "laravel.migrate",
    name: "Run migrations",
    description: "Run migrations safely.",
    category: "laravel",
    risk_level: "high" as const,
    required_role: "developer" as const,
    requires_confirmation: true,
    requires_password_confirmation: true,
    timeout_seconds: 300,
    supports_streaming: true,
    enabled: true,
    backup_required: true,
  };

  it("keeps action controls role and risk aware", () => {
    expect(canRunAction("viewer", action)).toBe(false);
    expect(canRunAction("developer", action)).toBe(true);
    expect(confirmationMode(action)).toBe("typed-password");
  });

  it("presents human action states", () => {
    expect(executionHumanState({ action_key: "npm.build", status: "failed" })).toBe("npm build failed");
  });
});

describe("permission-aware navigation helpers", () => {
  it("keeps global controls owner-only and assigned edits role-aware", () => {
    expect(canManageUsers("owner")).toBe(true);
    expect(canManageServer("developer")).toBe(false);
    expect(canModifyAssignedWebsite("developer")).toBe(true);
    expect(canModifyAssignedWebsite("viewer")).toBe(false);
  });
});

describe("api error normalization", () => {
  it("configures Sanctum cookies and XSRF headers for cross-origin browser requests", () => {
    expect(api.defaults.withCredentials).toBe(true);
    expect(api.defaults.withXSRFToken).toBe(true);
    expect((api.defaults.headers as Record<string, unknown>)["X-Requested-With"]).toBe("XMLHttpRequest");
  });

  it("confirms successful login through the current-user endpoint before reporting authenticated", async () => {
    const user = {
      id: 1,
      name: "Youssef",
      email: "youssef@example.com",
      role: "owner",
      avatar_url: null,
      is_active: true,
      timezone: "UTC",
      two_factor_enabled: false,
      last_login_at: null,
      email_verified_at: null,
    };
    const calls: string[] = [];

    vi.spyOn(api, "get").mockImplementation(async (url) => {
      calls.push(`GET ${url}`);
      if (url === "/sanctum/csrf-cookie") {
        return { data: null } as never;
      }

      return { data: { ok: true, message: "OK", data: { user }, meta: {}, errors: null } } as never;
    });
    vi.spyOn(api, "post").mockImplementation(async (url) => {
      calls.push(`POST ${url}`);
      return { data: { ok: true, message: "Logged in.", data: { user }, meta: {}, errors: null } } as never;
    });

    await expect(authApi.login({ email: "youssef@example.com", password: "secret", remember: true })).resolves.toEqual({
      type: "authenticated",
      user,
    });
    expect(calls).toEqual(["GET /sanctum/csrf-cookie", "POST /api/v1/auth/login", "GET /api/v1/auth/user"]);
  });

  it("normalizes unknown client errors", () => {
    expect(normalizeApiError(new Error("boom"))).toEqual({
      status: 0,
      message: "Unexpected client error.",
      fields: {},
    });
  });
});

describe("dashboard response validation", () => {
  it("accepts a loading-ready dashboard payload", () => {
    const parsed = dashboardSummarySchema.parse({
      server: { status: "healthy", hostname: "local" },
      metrics: {
        available: true,
        hostname: "local",
        os_name: "Linux",
        kernel_version: "6",
        uptime_seconds: 10,
        cpu: { usage_percent: 10, load_average: [0.1, 0.2, 0.3] },
        memory: { total_bytes: 100, used_bytes: 50, usage_percent: 50 },
        disk: { total_bytes: 100, used_bytes: 40, usage_percent: 40 },
        network: { rx_bytes: 1, tx_bytes: 2 },
        collected_at: new Date().toISOString(),
      },
      website_counts: { total: 0, healthy: 0, degraded: 0, offline: 0 },
      services: [],
      websites: [],
      activity: [],
      coolify: { enabled: false, driver: "mock", public_url: null, linked_resources: 0, container_metrics_supported: false },
      deployments: { active: 0, awaiting_approval: 0, failed: 0, latest: [] },
      collected_at: new Date().toISOString(),
    });

    expect(parsed.server.status).toBe("healthy");
  });
});

describe("phase four schemas", () => {
  it("accepts Coolify offline integration state", () => {
    expect(coolifyStatusSchema.parse({ enabled: false, driver: "mock", connected: false, health: "unreachable", token_configured: false }).connected).toBe(false);
  });

  it("accepts deployment approval state", () => {
    expect(deploymentSchema.parse({
      uuid: "deploy-1",
      website_id: 1,
      provider: "coolify",
      trigger: "manual",
      requested_by: 2,
      status: "awaiting_approval",
      commit_sha: null,
      commit_message: null,
      branch: "main",
      started_at: null,
      finished_at: null,
      duration_seconds: null,
      deployment_url: null,
      logs_preview: null,
      failure_reason: null,
      created_at: null,
    }).status).toBe("awaiting_approval");
  });

  it("marks restricted console commands unavailable when scoped out", () => {
    expect(consoleCommandSchema.parse({ alias: "npm.lint", label: "npm run lint", description: "Run lint", available: false }).available).toBe(false);
  });
});

describe("file workspace helpers", () => {
  const root = allowedPathSchema.parse({
    id: 1,
    website_id: 1,
    name: "Demo",
    label: "Demo",
    is_primary: true,
    is_active: true,
    diagnostics: { status: "writable", readable: true, writable: true },
    capabilities: {
      read: true,
      write: true,
      upload: false,
      create: false,
      rename: false,
      move: false,
      copy: true,
      delete: false,
      archive: true,
      extract: false,
    },
    max_upload_bytes: null,
    allowed_extensions: null,
    blocked_patterns: null,
  });

  it("builds normalized breadcrumbs and client paths", () => {
    expect(buildBreadcrumbs("/src//app/page.tsx")).toEqual([
      { label: "Root", path: "" },
      { label: "src", path: "src" },
      { label: "app", path: "src/app" },
      { label: "page.tsx", path: "src/app/page.tsx" },
    ]);
    expect(joinClientPath("src/app", "/page.tsx")).toBe("src/app/page.tsx");
  });

  it("keeps edit affordances capability-aware", () => {
    const entry: FileEntry = {
      name: "page.tsx",
      relativePath: "src/page.tsx",
      type: "file",
      size: 128,
      modifiedAt: null,
      readable: true,
      writable: true,
      editable: true,
      protected: false,
    };

    expect(canOpenInEditor(root, entry)).toBe(true);
    expect(fileIconKind(entry)).toBe("code");
    expect(rootModeLabel(root)).toBe("Read/write");
  });

  it("formats sizes and identifies conflict errors", () => {
    expect(formatBytes(1536)).toBe("1.5 KB");
    expect(isConflictError({ status: 409, message: "stale", fields: {} })).toBe(true);
  });
});
