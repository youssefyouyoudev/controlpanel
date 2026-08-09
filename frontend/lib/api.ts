import axios, { AxiosError, type AxiosResponse } from "axios";
import { ZodError } from "zod";
import { env } from "@/lib/env";
import {
  auditLogSchema,
  actionDefinitionSchema,
  actionExecutionSchema,
  allowedPathSchema,
  backupScheduleSchema,
  backupSchema,
  consoleCommandSchema,
  consoleExecutionSchema,
  coolifyCapabilitySchema,
  coolifyResourceLinkSchema,
  coolifyStatusSchema,
  databaseOverviewSchema,
  databaseQueryResultSchema,
  databaseRowsSchema,
  databaseSummarySchema,
  databaseTableSchema,
  databaseTableSummarySchema,
  dashboardSummarySchema,
  deploymentSchema,
  discoveredCoolifyResourceSchema,
  discoveredWebsiteSchema,
  fileContentSchema,
  fileEntrySchema,
  fileRevisionSchema,
  healthSchema,
  logPayloadSchema,
  logSourceSchema,
  notificationSchema,
  serviceSchema,
  securityStatusSchema,
  terminalSessionSchema,
  trashEntrySchema,
  twoFactorSetupSchema,
  twoFactorStatusSchema,
  userSchema,
  websiteComponentSchema,
  websiteSchema,
  type ActionDefinition,
  type ActionExecution,
  type AppNotification,
  type Backup,
  type BackupSchedule,
  type ConsoleCommand,
  type ConsoleExecution,
  type CoolifyCapability,
  type CoolifyResourceLink,
  type CoolifyStatus,
  type DatabaseOverview,
  type DatabaseQueryResult,
  type DatabaseRows,
  type DatabaseSummary,
  type DatabaseTable,
  type DatabaseTableSummary,
  type DashboardSummary,
  type Deployment,
  type DiscoveredCoolifyResource,
  type DiscoveredWebsite,
  type FileContent,
  type FileEntry,
  type FileRevision,
  type LogPayload,
  type LogSource,
  type ServiceStatus,
  type SecurityStatus,
  type TerminalSession,
  type TrashEntry,
  type TwoFactorSetup,
  type TwoFactorStatus,
  type User,
  type Website,
  type AllowedPath,
  type WebsiteComponent,
  type WebsiteHealth,
} from "@/lib/schemas";

export type ApiError = {
  status: number;
  message: string;
  fields: Record<string, string[]>;
  requestId?: string;
};

type Envelope<T> = {
  ok: boolean;
  message: string;
  data: T;
  meta?: { request_id?: string; pagination?: unknown };
  errors?: Record<string, string[]>;
};

export const api = axios.create({
  baseURL: env.NEXT_PUBLIC_API_URL.replace(/\/+$/, ""),
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: "XSRF-TOKEN",
  xsrfHeaderName: "X-XSRF-TOKEN",
  headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
});

export type LoginResult =
  | { type: "authenticated"; user: User }
  | { type: "two_factor_required" };

export function normalizeApiError(error: unknown): ApiError {
  if (axios.isAxiosError(error)) {
    const axiosError = error as AxiosError<Envelope<null>>;
    const status = axiosError.response?.status ?? 0;
    return {
      status,
      message: status === 419
        ? "CSRF token mismatch. Refresh the page and sign in again."
        : axiosError.response?.data?.message ?? "Unable to reach YouPanel API.",
      fields: axiosError.response?.data?.errors ?? {},
      requestId: axiosError.response?.data?.meta?.request_id,
    };
  }

  if (error instanceof ZodError) {
    const issue = error.issues[0];
    const path = issue?.path.length ? issue.path.join(".") : "response";

    return {
      status: 0,
      message: issue ? `Unexpected API response shape at ${path}: ${issue.message}` : "Unexpected API response shape.",
      fields: {},
    };
  }

  return { status: 0, message: "Unexpected client error.", fields: {} };
}

async function unwrap<T>(promise: Promise<AxiosResponse<Envelope<T>>>) {
  const response = await promise;
  return response.data.data;
}

let csrfCookieReady = false;
let csrfCookiePromise: Promise<void> | null = null;

export async function csrfCookie(options: { force?: boolean } = {}) {
  if (options.force) {
    csrfCookieReady = false;
    csrfCookiePromise = null;
  }

  if (csrfCookieReady) {
    return;
  }

  csrfCookiePromise ??= api.get("/sanctum/csrf-cookie")
    .then(() => {
      csrfCookieReady = true;
    })
    .finally(() => {
      csrfCookiePromise = null;
    });

  await csrfCookiePromise;
}

export const authApi = {
  async login(values: { email: string; password: string; remember?: boolean }): Promise<LoginResult> {
    await csrfCookie({ force: true });
    const data = await unwrap(api.post<Envelope<{ user?: User; requires_two_factor?: boolean }>>("/api/v1/auth/login", values));
    if (data.requires_two_factor) {
      return { type: "two_factor_required" };
    }

    const user = await authApi.me();
    return { type: "authenticated", user };
  },
  async twoFactorChallenge(values: { code?: string; recovery_code?: string }): Promise<User> {
    await csrfCookie({ force: true });
    await unwrap(api.post<Envelope<{ user?: User }>>("/api/v1/auth/two-factor-challenge", values));
    return authApi.me();
  },
  async logout() {
    await csrfCookie();
    await api.post("/api/v1/auth/logout");
    csrfCookieReady = false;
  },
  async me() {
    const data = await unwrap(api.get<Envelope<{ user: User }>>("/api/v1/auth/user"));
    return userSchema.parse(data.user);
  },
  async forgotPassword(email: string) {
    await csrfCookie();
    await api.post("/api/v1/auth/forgot-password", { email });
  },
  async resetPassword(values: { email: string; token: string; password: string; password_confirmation: string }) {
    await csrfCookie();
    await api.post("/api/v1/auth/reset-password", values);
  },
  async updateProfile(values: { name: string; email: string; timezone: string }) {
    await csrfCookie();
    const data = await unwrap(api.put<Envelope<{ user: User }>>("/api/v1/auth/profile", values));
    return userSchema.parse(data.user);
  },
  async updatePassword(values: { current_password: string; password: string; password_confirmation: string }) {
    await csrfCookie();
    await api.put("/api/v1/auth/password", values);
  },
  async twoFactorStatus(): Promise<TwoFactorStatus> {
    const data = await unwrap(api.get<Envelope<TwoFactorStatus>>("/api/v1/auth/two-factor"));
    return twoFactorStatusSchema.parse(data);
  },
  async startTwoFactor(): Promise<TwoFactorSetup> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<TwoFactorSetup>>("/api/v1/auth/two-factor"));
    return twoFactorSetupSchema.parse(data);
  },
  async confirmTwoFactor(code: string) {
    await csrfCookie();
    await api.post("/api/v1/auth/two-factor/confirm", { code });
  },
  async regenerateRecoveryCodes(current_password: string): Promise<string[]> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ recovery_codes: string[] }>>("/api/v1/auth/two-factor/recovery-codes", { current_password }));
    return data.recovery_codes;
  },
  async disableTwoFactor(current_password: string) {
    await csrfCookie();
    await api.delete("/api/v1/auth/two-factor", { data: { current_password } });
  },
};

export const dashboardApi = {
  async summary(): Promise<DashboardSummary> {
    const data = await unwrap(api.get<Envelope<DashboardSummary>>("/api/v1/dashboard/summary"));
    return dashboardSummarySchema.parse(data);
  },
  async services(): Promise<ServiceStatus[]> {
    const data = await unwrap(api.get<Envelope<{ services: ServiceStatus[] }>>("/api/v1/dashboard/services"));
    return serviceSchema.array().parse(data.services);
  },
};

export const securityApi = {
  async status(): Promise<SecurityStatus> {
    const data = await unwrap(api.get<Envelope<{ status: unknown }>>("/api/v1/security/status"));
    return securityStatusSchema.parse(data.status);
  },
};

export const websiteApi = {
  async list(): Promise<Website[]> {
    const data = await unwrap(api.get<Envelope<Website[]>>("/api/v1/websites"));
    return websiteSchema.array().parse(data);
  },
  async show(id: string): Promise<Website> {
    const data = await unwrap(api.get<Envelope<{ website: Website }>>(`/api/v1/websites/${id}`));
    return websiteSchema.parse(data.website);
  },
  async scan(): Promise<{ count: number; discovered: DiscoveredWebsite[] }> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ count: number; discovered: unknown[] }>>("/api/v1/websites/discovery/scan"));
    return { count: data.count, discovered: discoveredWebsiteSchema.array().parse(data.discovered) };
  },
  async sync(): Promise<{ created: number; updated: number; unchanged: number; discovered: DiscoveredWebsite[]; websites: Website[] }> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ created: number; updated: number; unchanged: number; discovered: unknown[]; websites: unknown[] }>>("/api/v1/websites/discovery/sync"));
    return {
      created: data.created,
      updated: data.updated,
      unchanged: data.unchanged,
      discovered: discoveredWebsiteSchema.array().parse(data.discovered),
      websites: websiteSchema.array().parse(data.websites),
    };
  },
};

export const activityApi = {
  async list() {
    const data = await unwrap(api.get<Envelope<unknown[]>>("/api/v1/dashboard/activity"));
    return auditLogSchema.array().parse(data);
  },
};

export const fileApi = {
  async roots(websiteId: string): Promise<AllowedPath[]> {
    const data = await unwrap(api.get<Envelope<unknown[]>>(`/api/v1/websites/${websiteId}/file-roots`));
    return allowedPathSchema.array().parse(data);
  },
  async createRoot(websiteId: string, values: Record<string, unknown>): Promise<AllowedPath> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ root: unknown }>>(`/api/v1/websites/${websiteId}/file-roots`, values));
    return allowedPathSchema.parse(data.root);
  },
  async updateRoot(websiteId: string, rootId: number, values: Record<string, unknown>): Promise<AllowedPath> {
    await csrfCookie();
    const data = await unwrap(api.put<Envelope<{ root: unknown }>>(`/api/v1/websites/${websiteId}/file-roots/${rootId}`, values));
    return allowedPathSchema.parse(data.root);
  },
  async validateRoot(websiteId: string, rootId: number) {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ diagnostics: unknown }>>(`/api/v1/websites/${websiteId}/file-roots/${rootId}/validate`));
    return data.diagnostics;
  },
  async list(websiteId: string, allowedPathId: number, path = "", sort = "name"): Promise<FileEntry[]> {
    const data = await unwrap(api.get<Envelope<{ entries: unknown[] }>>(`/api/v1/websites/${websiteId}/files`, { params: { allowed_path_id: allowedPathId, path, sort } }));
    return fileEntrySchema.array().parse(data.entries);
  },
  async content(websiteId: string, allowedPathId: number, path: string): Promise<FileContent> {
    const data = await unwrap(api.get<Envelope<{ file: unknown }>>(`/api/v1/websites/${websiteId}/files/content`, { params: { allowed_path_id: allowedPathId, path } }));
    return fileContentSchema.parse(data.file);
  },
  async save(websiteId: string, allowedPathId: number, path: string, content: string, checksum: string) {
    await csrfCookie();
    const data = await unwrap(api.put<Envelope<{ result: { metadata?: { checksum?: string } } }>>(`/api/v1/websites/${websiteId}/files/content`, { allowed_path_id: allowedPathId, path, content, checksum }));
    return data.result;
  },
  async createFile(websiteId: string, allowedPathId: number, path: string) {
    await csrfCookie();
    return unwrap(api.post<Envelope<unknown>>(`/api/v1/websites/${websiteId}/files/create`, { allowed_path_id: allowedPathId, path, content: "" }));
  },
  async createDirectory(websiteId: string, allowedPathId: number, path: string) {
    await csrfCookie();
    return unwrap(api.post<Envelope<unknown>>(`/api/v1/websites/${websiteId}/directories`, { allowed_path_id: allowedPathId, path }));
  },
  async rename(websiteId: string, allowedPathId: number, path: string, name: string) {
    await csrfCookie();
    return unwrap(api.post<Envelope<unknown>>(`/api/v1/websites/${websiteId}/files/rename`, { allowed_path_id: allowedPathId, path, name }));
  },
  async copy(websiteId: string, allowedPathId: number, path: string, destination: string) {
    await csrfCookie();
    return unwrap(api.post<Envelope<unknown>>(`/api/v1/websites/${websiteId}/files/copy`, { allowed_path_id: allowedPathId, path, destination }));
  },
  async move(websiteId: string, allowedPathId: number, path: string, destination: string) {
    await csrfCookie();
    return unwrap(api.post<Envelope<unknown>>(`/api/v1/websites/${websiteId}/files/move`, { allowed_path_id: allowedPathId, path, destination }));
  },
  async trash(websiteId: string, allowedPathId: number, path: string) {
    await csrfCookie();
    return api.delete(`/api/v1/websites/${websiteId}/files`, { data: { allowed_path_id: allowedPathId, path } });
  },
  async archive(websiteId: string, allowedPathId: number, path: string): Promise<Blob> {
    await csrfCookie();
    const response = await api.post(
      `/api/v1/websites/${websiteId}/files/archive`,
      { allowed_path_id: allowedPathId, path },
      { responseType: "blob" },
    );
    return response.data;
  },
  async extract(websiteId: string, allowedPathId: number, path: string, file: File, overwrite: boolean, onProgress?: (progress: number) => void) {
    await csrfCookie();
    const form = new FormData();
    form.append("allowed_path_id", String(allowedPathId));
    form.append("path", path);
    form.append("overwrite", overwrite ? "1" : "0");
    form.append("archive", file);
    return api.post(`/api/v1/websites/${websiteId}/files/extract`, form, {
      onUploadProgress: (event) => onProgress?.(event.total ? Math.round((event.loaded / event.total) * 100) : 0),
    });
  },
  downloadUrl(websiteId: string, allowedPathId: number, path: string) {
    return `${api.defaults.baseURL}/api/v1/websites/${websiteId}/files/download?allowed_path_id=${allowedPathId}&path=${encodeURIComponent(path)}`;
  },
  async upload(websiteId: string, allowedPathId: number, directory: string, file: File, overwrite: boolean, onProgress?: (progress: number) => void) {
    await csrfCookie();
    const form = new FormData();
    form.append("allowed_path_id", String(allowedPathId));
    form.append("directory", directory);
    form.append("overwrite", overwrite ? "1" : "0");
    form.append("file", file);
    return api.post(`/api/v1/websites/${websiteId}/files/upload`, form, {
      onUploadProgress: (event) => onProgress?.(event.total ? Math.round((event.loaded / event.total) * 100) : 0),
    });
  },
  async revisions(websiteId: string, allowedPathId: number, path: string): Promise<FileRevision[]> {
    const data = await unwrap(api.get<Envelope<unknown[]>>(`/api/v1/websites/${websiteId}/files/revisions`, { params: { allowed_path_id: allowedPathId, path } }));
    return fileRevisionSchema.array().parse(data);
  },
  async revision(websiteId: string, revisionId: number) {
    return unwrap(api.get<Envelope<{ revision: FileRevision; content: string | null }>>(`/api/v1/websites/${websiteId}/files/revisions/${revisionId}`));
  },
  async restoreRevision(websiteId: string, revisionId: number) {
    await csrfCookie();
    return unwrap(api.post<Envelope<unknown>>(`/api/v1/websites/${websiteId}/files/revisions/${revisionId}/restore`));
  },
  async trashList(websiteId: string): Promise<TrashEntry[]> {
    const data = await unwrap(api.get<Envelope<unknown[]>>(`/api/v1/websites/${websiteId}/trash`));
    return trashEntrySchema.array().parse(data);
  },
  async restoreTrash(websiteId: string, trashId: number) {
    await csrfCookie();
    return unwrap(api.post<Envelope<unknown>>(`/api/v1/websites/${websiteId}/trash/${trashId}/restore`));
  },
  async permanentDeleteTrash(websiteId: string, trashId: number, password: string) {
    await csrfCookie();
    return unwrap(api.delete<Envelope<unknown>>(`/api/v1/websites/${websiteId}/trash/${trashId}`, { data: { password } }));
  },
};

export const operationsApi = {
  async components(websiteId: string): Promise<WebsiteComponent[]> {
    const data = await unwrap(api.get<Envelope<unknown[]>>(`/api/v1/websites/${websiteId}/components`));
    return websiteComponentSchema.array().parse(data);
  },
  async createComponent(websiteId: string, values: Record<string, unknown>): Promise<WebsiteComponent> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ component: unknown }>>(`/api/v1/websites/${websiteId}/components`, values));
    return websiteComponentSchema.parse(data.component);
  },
  async actions(websiteId: string): Promise<ActionDefinition[]> {
    const data = await unwrap(api.get<Envelope<unknown[]>>(`/api/v1/websites/${websiteId}/actions`));
    return actionDefinitionSchema.array().parse(data);
  },
  async execute(websiteId: string, actionKey: string, values: { website_component_id?: number; options?: Record<string, unknown> }): Promise<ActionExecution> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ execution: unknown }>>(`/api/v1/websites/${websiteId}/actions/${actionKey}/execute`, values));
    return actionExecutionSchema.parse(data.execution);
  },
  async executions(): Promise<ActionExecution[]> {
    const data = await unwrap(api.get<Envelope<unknown[]>>("/api/v1/action-executions"));
    return actionExecutionSchema.array().parse(data);
  },
  async execution(uuid: string): Promise<ActionExecution> {
    const data = await unwrap(api.get<Envelope<{ execution: unknown }>>(`/api/v1/action-executions/${uuid}`));
    return actionExecutionSchema.parse(data.execution);
  },
  async executionOutput(uuid: string): Promise<string> {
    const data = await unwrap(api.get<Envelope<{ output: string }>>(`/api/v1/action-executions/${uuid}/output`));
    return data.output;
  },
  async cancel(uuid: string): Promise<ActionExecution> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ execution: unknown }>>(`/api/v1/action-executions/${uuid}/cancel`));
    return actionExecutionSchema.parse(data.execution);
  },
  async gitStatus(websiteId: string) {
    const data = await unwrap(api.get<Envelope<{ git: unknown }>>(`/api/v1/websites/${websiteId}/git/status`));
    return data.git as { branch: string; latest_commit: string | null; remote_url: string | null; dirty: boolean; changes: string[] };
  },
  async gitCommits(websiteId: string): Promise<string[]> {
    const data = await unwrap(api.get<Envelope<{ commits: string[] }>>(`/api/v1/websites/${websiteId}/git/commits`));
    return data.commits;
  },
  async gitPull(websiteId: string) {
    await csrfCookie();
    return unwrap(api.post<Envelope<{ execution: string }>>(`/api/v1/websites/${websiteId}/git/pull`));
  },
  async logSources(websiteId: string): Promise<LogSource[]> {
    const data = await unwrap(api.get<Envelope<unknown[]>>(`/api/v1/websites/${websiteId}/logs/sources`));
    return logSourceSchema.array().parse(data);
  },
  async logs(websiteId: string, sourceId: number, params: { lines?: number; search?: string; level?: string }): Promise<LogPayload> {
    const data = await unwrap(api.get<Envelope<unknown>>(`/api/v1/websites/${websiteId}/logs/${sourceId}`, { params }));
    return logPayloadSchema.parse(data);
  },
  async backups(websiteId: string): Promise<Backup[]> {
    const data = await unwrap(api.get<Envelope<unknown[]>>(`/api/v1/websites/${websiteId}/backups`));
    return backupSchema.array().parse(data);
  },
  async createBackup(websiteId: string, type = "files"): Promise<Backup> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ backup: unknown }>>(`/api/v1/websites/${websiteId}/backups`, { type }));
    return backupSchema.parse(data.backup);
  },
  async verifyBackup(websiteId: string, uuid: string): Promise<boolean> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ verified: boolean }>>(`/api/v1/websites/${websiteId}/backups/${uuid}/verify`));
    return data.verified;
  },
  async backupSchedules(websiteId: string): Promise<BackupSchedule[]> {
    const data = await unwrap(api.get<Envelope<unknown[]>>(`/api/v1/websites/${websiteId}/backup-schedules`));
    return backupScheduleSchema.array().parse(data);
  },
  async health(websiteId: string): Promise<WebsiteHealth | null> {
    const data = await unwrap(api.get<Envelope<{ health: unknown | null }>>(`/api/v1/websites/${websiteId}/health`));
    return data.health ? healthSchema.parse(data.health) : null;
  },
  async runHealthCheck(websiteId: string) {
    await csrfCookie();
    return unwrap(api.post<Envelope<{ queued: boolean }>>(`/api/v1/websites/${websiteId}/health/check`));
  },
};

export const notificationApi = {
  async list(): Promise<{ unread_count: number; notifications: AppNotification[] }> {
    const data = await unwrap(api.get<Envelope<{ unread_count: number; notifications: unknown[] }>>("/api/v1/notifications"));
    return { unread_count: data.unread_count, notifications: notificationSchema.array().parse(data.notifications) };
  },
  async read(id: string) {
    await csrfCookie();
    await api.post(`/api/v1/notifications/${id}/read`);
  },
  async readAll() {
    await csrfCookie();
    await api.post("/api/v1/notifications/read-all");
  },
};

export const coolifyApi = {
  async status(): Promise<CoolifyStatus> {
    const data = await unwrap(api.get<Envelope<unknown>>("/api/v1/integrations/coolify/status"));
    return coolifyStatusSchema.parse(data);
  },
  async test(): Promise<CoolifyStatus> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ connection: unknown }>>("/api/v1/integrations/coolify/test"));
    return coolifyStatusSchema.parse(data.connection);
  },
  async capabilities(): Promise<CoolifyCapability[]> {
    const data = await unwrap(api.get<Envelope<unknown[]>>("/api/v1/integrations/coolify/capabilities"));
    return coolifyCapabilitySchema.array().parse(data);
  },
  async resources(type?: string): Promise<DiscoveredCoolifyResource[]> {
    const data = await unwrap(api.get<Envelope<unknown[]>>("/api/v1/integrations/coolify/resources", { params: { type } }));
    return discoveredCoolifyResourceSchema.array().parse(data);
  },
  async synchronize() {
    await csrfCookie();
    return unwrap(api.post<Envelope<{ queued: boolean }>>("/api/v1/integrations/coolify/synchronize"));
  },
  async links(websiteId: string): Promise<CoolifyResourceLink[]> {
    const data = await unwrap(api.get<Envelope<unknown[]>>(`/api/v1/websites/${websiteId}/coolify-links`));
    return coolifyResourceLinkSchema.array().parse(data);
  },
  async createLink(websiteId: string, values: Record<string, unknown>): Promise<CoolifyResourceLink> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ link: unknown }>>(`/api/v1/websites/${websiteId}/coolify-links`, values));
    return coolifyResourceLinkSchema.parse(data.link);
  },
  async verifyLink(websiteId: string, linkId: number): Promise<CoolifyResourceLink> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ link: unknown }>>(`/api/v1/websites/${websiteId}/coolify-links/${linkId}/verify`));
    return coolifyResourceLinkSchema.parse(data.link);
  },
  async resourcesForWebsite(websiteId: string): Promise<CoolifyResourceLink[]> {
    const data = await unwrap(api.get<Envelope<unknown[]>>(`/api/v1/websites/${websiteId}/resources`));
    return coolifyResourceLinkSchema.array().parse(data);
  },
  async resourceAction(websiteId: string, linkId: number, action: "start" | "stop" | "restart", confirmed = false) {
    await csrfCookie();
    return unwrap(api.post<Envelope<{ result: unknown }>>(`/api/v1/websites/${websiteId}/resources/${linkId}/${action}`, { confirmed }));
  },
};

export const deploymentApi = {
  async list(): Promise<Deployment[]> {
    const data = await unwrap(api.get<Envelope<unknown[]>>("/api/v1/deployments"));
    return deploymentSchema.array().parse(data);
  },
  async show(uuid: string): Promise<Deployment> {
    const data = await unwrap(api.get<Envelope<{ deployment: unknown }>>(`/api/v1/deployments/${uuid}`));
    return deploymentSchema.parse(data.deployment);
  },
  async create(websiteId: string, values: Record<string, unknown>): Promise<Deployment> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ deployment: unknown }>>(`/api/v1/websites/${websiteId}/deployments`, values));
    return deploymentSchema.parse(data.deployment);
  },
  async approve(uuid: string): Promise<Deployment> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ deployment: unknown }>>(`/api/v1/deployments/${uuid}/approve`));
    return deploymentSchema.parse(data.deployment);
  },
  async reject(uuid: string, reason?: string): Promise<Deployment> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ deployment: unknown }>>(`/api/v1/deployments/${uuid}/reject`, { reason }));
    return deploymentSchema.parse(data.deployment);
  },
  async cancel(uuid: string): Promise<Deployment> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ deployment: unknown }>>(`/api/v1/deployments/${uuid}/cancel`));
    return deploymentSchema.parse(data.deployment);
  },
  async logs(uuid: string): Promise<{ logs: string; complete: boolean; redacted: boolean }> {
    const data = await unwrap(api.get<Envelope<{ logs: string; complete: boolean; redacted: boolean }>>(`/api/v1/deployments/${uuid}/logs`));
    return data;
  },
};

export const consoleApi = {
  async commands(websiteId: string, componentId?: number): Promise<{ commands: ConsoleCommand[]; banner: string; container_terminal: string }> {
    const data = await unwrap(api.get<Envelope<{ commands: unknown[]; banner: string; container_terminal: string }>>(`/api/v1/websites/${websiteId}/console/commands`, { params: { website_component_id: componentId } }));
    return { commands: consoleCommandSchema.array().parse(data.commands), banner: data.banner, container_terminal: data.container_terminal };
  },
  async execute(websiteId: string, values: { command_alias: string; website_component_id?: number }): Promise<ConsoleExecution> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ execution: unknown }>>(`/api/v1/websites/${websiteId}/console/execute`, values));
    return consoleExecutionSchema.parse(data.execution);
  },
  async show(uuid: string): Promise<ConsoleExecution> {
    const data = await unwrap(api.get<Envelope<{ execution: unknown }>>(`/api/v1/console-executions/${uuid}`));
    return consoleExecutionSchema.parse(data.execution);
  },
};

export const terminalApi = {
  async create(current_password: string): Promise<{ session: TerminalSession; ticket: string; websocket_url: string }> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ session: unknown; ticket: string; websocket_url: string }>>("/api/v1/terminal/sessions", { current_password }));
    return { session: terminalSessionSchema.parse(data.session), ticket: data.ticket, websocket_url: data.websocket_url };
  },
  async createForWebsite(websiteId: string, current_password: string): Promise<{ session: TerminalSession; ticket: string; websocket_url: string }> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ session: unknown; ticket: string; websocket_url: string }>>(`/api/v1/websites/${websiteId}/terminal/sessions`, { current_password }));
    return { session: terminalSessionSchema.parse(data.session), ticket: data.ticket, websocket_url: data.websocket_url };
  },
  async close(uuid: string): Promise<TerminalSession> {
    await csrfCookie();
    const data = await unwrap(api.delete<Envelope<{ session: unknown }>>(`/api/v1/terminal/sessions/${uuid}`));
    return terminalSessionSchema.parse(data.session);
  },
};

export const databaseApi = {
  async overview(): Promise<DatabaseOverview> {
    const data = await unwrap(api.get<Envelope<{ overview: unknown }>>("/api/v1/databases/overview"));
    return databaseOverviewSchema.parse(data.overview);
  },
  async list(): Promise<DatabaseSummary[]> {
    const data = await unwrap(api.get<Envelope<{ databases: unknown[] }>>("/api/v1/databases"));
    return databaseSummarySchema.array().parse(data.databases);
  },
  async tables(database: string): Promise<DatabaseTableSummary[]> {
    const data = await unwrap(api.get<Envelope<{ tables: unknown[] }>>(`/api/v1/databases/${encodeURIComponent(database)}/tables`));
    return databaseTableSummarySchema.array().parse(data.tables);
  },
  async table(database: string, table: string): Promise<DatabaseTable> {
    const data = await unwrap(api.get<Envelope<{ table: unknown }>>(`/api/v1/databases/${encodeURIComponent(database)}/tables/${encodeURIComponent(table)}`));
    return databaseTableSchema.parse(data.table);
  },
  async rows(database: string, table: string, page = 1, per_page = 100): Promise<DatabaseRows> {
    const data = await unwrap(api.get<Envelope<{ rows: unknown }>>(`/api/v1/databases/${encodeURIComponent(database)}/tables/${encodeURIComponent(table)}/rows`, { params: { page, per_page } }));
    return databaseRowsSchema.parse(data.rows);
  },
  async query(database: string, values: { sql: string; current_password: string; limit?: number }): Promise<DatabaseQueryResult> {
    await csrfCookie();
    const data = await unwrap(api.post<Envelope<{ result: unknown }>>(`/api/v1/databases/${encodeURIComponent(database)}/query`, values));
    return databaseQueryResultSchema.parse(data.result);
  },
};
