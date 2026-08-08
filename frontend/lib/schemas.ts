import { z } from "zod";

export const roleSchema = z.enum(["owner", "developer", "editor", "viewer"]);
export type UserRole = z.infer<typeof roleSchema>;

export const userSchema = z.object({
  id: z.number(),
  name: z.string(),
  email: z.string().email(),
  role: roleSchema,
  avatar_url: z.string().nullable(),
  is_active: z.boolean(),
  timezone: z.string(),
  two_factor_enabled: z.boolean().default(false),
  last_login_at: z.string().nullable(),
  email_verified_at: z.string().nullable(),
});
export type User = z.infer<typeof userSchema>;

export const twoFactorStatusSchema = z.object({
  enabled: z.boolean(),
  confirmed_at: z.string().nullable().optional(),
  recovery_codes_remaining: z.number().optional(),
});
export type TwoFactorStatus = z.infer<typeof twoFactorStatusSchema>;

export const twoFactorSetupSchema = z.object({
  enabled: z.boolean(),
  secret: z.string(),
  otpauth_url: z.string(),
  qr_code_svg: z.string(),
  recovery_codes: z.array(z.string()),
});
export type TwoFactorSetup = z.infer<typeof twoFactorSetupSchema>;

export const serviceSchema = z.object({
  name: z.string(),
  label: z.string(),
  installed: z.boolean().optional(),
  status: z.enum(["running", "stopped", "failed", "degraded", "unknown", "unavailable"]),
  version: z.string().nullable().optional(),
  uptime_seconds: z.number().nullable().optional(),
  unit: z.string().nullable().optional(),
  read_only: z.boolean(),
  checked_at: z.string(),
});
export type ServiceStatus = z.infer<typeof serviceSchema>;

export const serverSchema = z.object({
  id: z.number(),
  name: z.string(),
  slug: z.string(),
  hostname: z.string(),
  description: z.string().nullable(),
  operating_system: z.string().nullable(),
  is_local: z.boolean(),
  status: z.string(),
  last_seen_at: z.string().nullable(),
});

export const websiteSchema = z.object({
  id: z.number(),
  server_id: z.number(),
  name: z.string(),
  slug: z.string(),
  domain: z.string().nullable(),
  framework: z.string().nullable(),
  status: z.string(),
  server: serverSchema.optional(),
  repository_url: z.string().nullable(),
  repository_branch: z.string(),
  assigned_port: z.number().nullable().optional(),
  display_path: z.string().nullable().optional(),
  modules: z.record(z.string(), z.string()),
  created_at: z.string().nullable(),
  updated_at: z.string().nullable(),
});
export type Website = z.infer<typeof websiteSchema>;

export const auditLogSchema = z.object({
  id: z.number(),
  action: z.string(),
  target_type: z.string().nullable(),
  target_identifier: z.string().nullable(),
  request_id: z.string().nullable(),
  created_at: z.string().nullable(),
  user: userSchema.nullable().optional(),
  website: websiteSchema.nullable().optional(),
});
export type AuditLog = z.infer<typeof auditLogSchema>;

export const metricsSchema = z.object({
  available: z.boolean(),
  reason: z.string().optional(),
  hostname: z.string().nullable(),
  os_name: z.string().nullable(),
  kernel_version: z.string().nullable(),
  architecture: z.string().nullable().optional(),
  uptime_seconds: z.number().nullable(),
  cpu: z.object({
    model: z.string().nullable().optional(),
    cores: z.number().nullable().optional(),
    usage_percent: z.number().nullable(),
    load_average: z.array(z.number()),
  }),
  memory: z.object({
    total_bytes: z.number().nullable(),
    used_bytes: z.number().nullable(),
    available_bytes: z.number().nullable().optional(),
    usage_percent: z.number().nullable(),
    swap_total_bytes: z.number().nullable().optional(),
    swap_used_bytes: z.number().nullable().optional(),
    swap_usage_percent: z.number().nullable().optional(),
  }),
  disk: z.object({
    total_bytes: z.number().nullable(),
    used_bytes: z.number().nullable(),
    free_bytes: z.number().nullable().optional(),
    usage_percent: z.number().nullable(),
    filesystems: z.array(z.object({
      mount: z.string(),
      device: z.string(),
      type: z.string(),
      total_bytes: z.number().nullable(),
      used_bytes: z.number().nullable(),
      free_bytes: z.number().nullable(),
      usage_percent: z.number().nullable(),
    })).optional(),
  }),
  network: z.object({
    rx_bytes: z.number().nullable(),
    tx_bytes: z.number().nullable(),
    interfaces: z.array(z.object({
      name: z.string(),
      rx_bytes: z.number(),
      tx_bytes: z.number(),
      is_loopback: z.boolean(),
    })).optional(),
  }),
  collected_at: z.string(),
});
export type Metrics = z.infer<typeof metricsSchema>;

export const dashboardSummarySchema = z.object({
  server: z.object({ status: z.string(), hostname: z.string().nullable() }),
  metrics: metricsSchema,
  website_counts: z.object({ total: z.number(), healthy: z.number(), degraded: z.number(), offline: z.number() }),
  services: z.array(serviceSchema),
  websites: z.array(websiteSchema),
  activity: z.array(auditLogSchema),
  coolify: z.object({
    enabled: z.boolean(),
    driver: z.string(),
    public_url: z.string().nullable(),
    linked_resources: z.number(),
    container_metrics_supported: z.boolean(),
  }).optional(),
  deployments: z.object({
    active: z.number(),
    awaiting_approval: z.number(),
    failed: z.number(),
    latest: z.array(z.unknown()),
  }).optional(),
  collected_at: z.string(),
});
export type DashboardSummary = z.infer<typeof dashboardSummarySchema>;

export const allowedPathSchema = z.object({
  id: z.number(),
  website_id: z.number(),
  name: z.string(),
  label: z.string(),
  absolute_path: z.string().optional(),
  is_primary: z.boolean(),
  is_active: z.boolean(),
  diagnostics: z.object({ status: z.string(), readable: z.boolean(), writable: z.boolean() }).nullable().optional(),
  capabilities: z.object({
    read: z.boolean(),
    write: z.boolean(),
    upload: z.boolean(),
    create: z.boolean(),
    rename: z.boolean(),
    move: z.boolean(),
    copy: z.boolean(),
    delete: z.boolean(),
    archive: z.boolean(),
    extract: z.boolean(),
  }),
  max_upload_bytes: z.number().nullable(),
  allowed_extensions: z.array(z.string()).nullable(),
  blocked_patterns: z.array(z.string()).nullable(),
});
export type AllowedPath = z.infer<typeof allowedPathSchema>;

export const fileEntrySchema = z.object({
  name: z.string(),
  relativePath: z.string(),
  type: z.enum(["file", "directory"]),
  size: z.number().nullable(),
  modifiedAt: z.string().nullable(),
  readable: z.boolean(),
  writable: z.boolean(),
  editable: z.boolean(),
  protected: z.boolean(),
});
export type FileEntry = z.infer<typeof fileEntrySchema>;

export const fileContentSchema = z.object({
  relativePath: z.string(),
  language: z.string(),
  encoding: z.string(),
  size: z.number(),
  modifiedAt: z.string().nullable(),
  checksum: z.string(),
  content: z.string(),
  readOnlyReason: z.string().nullable(),
});
export type FileContent = z.infer<typeof fileContentSchema>;

export const fileRevisionSchema = z.object({
  id: z.number(),
  website_id: z.number(),
  allowed_path_id: z.number(),
  relative_path: z.string(),
  operation: z.string(),
  original_size: z.number().nullable(),
  new_size: z.number().nullable(),
  original_checksum: z.string().nullable(),
  new_checksum: z.string().nullable(),
  created_at: z.string().nullable(),
  user: userSchema.optional(),
});
export type FileRevision = z.infer<typeof fileRevisionSchema>;

export const trashEntrySchema = z.object({
  id: z.number(),
  website_id: z.number(),
  allowed_path_id: z.number(),
  original_relative_path: z.string(),
  item_type: z.string(),
  original_size: z.number().nullable(),
  checksum: z.string().nullable(),
  expires_at: z.string().nullable(),
  created_at: z.string().nullable(),
  restored_at: z.string().nullable(),
});
export type TrashEntry = z.infer<typeof trashEntrySchema>;

export const websiteComponentSchema = z.object({
  id: z.number(),
  website_id: z.number(),
  name: z.string(),
  slug: z.string(),
  type: z.enum(["laravel", "nextjs", "vite", "node", "static", "database", "worker", "custom"]),
  relative_working_directory: z.string(),
  runtime: z.string().nullable(),
  process_manager: z.string().nullable(),
  process_name: z.string().nullable(),
  build_command_key: z.string().nullable(),
  start_command_key: z.string().nullable(),
  health_url: z.string().nullable(),
  status: z.string(),
  is_active: z.boolean(),
  created_at: z.string().nullable(),
  updated_at: z.string().nullable(),
});
export type WebsiteComponent = z.infer<typeof websiteComponentSchema>;

export const actionDefinitionSchema = z.object({
  key: z.string(),
  name: z.string(),
  description: z.string(),
  category: z.string(),
  risk_level: z.enum(["low", "medium", "high", "critical"]),
  required_role: roleSchema,
  requires_confirmation: z.boolean(),
  requires_password_confirmation: z.boolean(),
  timeout_seconds: z.number(),
  supports_streaming: z.boolean(),
  enabled: z.boolean(),
  backup_required: z.boolean(),
});
export type ActionDefinition = z.infer<typeof actionDefinitionSchema>;

export const actionExecutionSchema = z.object({
  uuid: z.string(),
  website_id: z.number(),
  website: websiteSchema.optional(),
  component: websiteComponentSchema.nullable().optional(),
  action_key: z.string(),
  requested_by: z.number(),
  requester: userSchema.optional(),
  status: z.enum(["queued", "preparing", "running", "succeeded", "failed", "cancelled", "timed_out", "blocked"]),
  risk_level: z.enum(["low", "medium", "high", "critical"]),
  started_at: z.string().nullable(),
  finished_at: z.string().nullable(),
  exit_code: z.number().nullable(),
  summary: z.string().nullable(),
  output_preview: z.string().nullable(),
  failure_reason: z.string().nullable(),
  created_at: z.string().nullable(),
  updated_at: z.string().nullable(),
});
export type ActionExecution = z.infer<typeof actionExecutionSchema>;

export const backupSchema = z.object({
  uuid: z.string(),
  website_id: z.number(),
  component: websiteComponentSchema.nullable().optional(),
  type: z.string(),
  status: z.string(),
  requested_by: z.number().nullable(),
  size_bytes: z.number().nullable(),
  checksum: z.string().nullable(),
  started_at: z.string().nullable(),
  finished_at: z.string().nullable(),
  expires_at: z.string().nullable(),
  error_message: z.string().nullable(),
  created_at: z.string().nullable(),
});
export type Backup = z.infer<typeof backupSchema>;

export const backupScheduleSchema = z.object({
  id: z.number(),
  website_id: z.number(),
  name: z.string(),
  backup_type: z.string(),
  cron_expression: z.string(),
  retention_count: z.number(),
  retention_days: z.number().nullable(),
  is_enabled: z.boolean(),
  last_run_at: z.string().nullable(),
  next_run_at: z.string().nullable(),
});
export type BackupSchedule = z.infer<typeof backupScheduleSchema>;

export const logSourceSchema = z.object({
  id: z.number(),
  website_id: z.number(),
  component: websiteComponentSchema.nullable().optional(),
  name: z.string(),
  slug: z.string(),
  type: z.string(),
  download_enabled: z.boolean(),
  is_sensitive: z.boolean(),
  is_active: z.boolean(),
});
export type LogSource = z.infer<typeof logSourceSchema>;

export const logPayloadSchema = z.object({
  source: z.string(),
  redacted: z.boolean(),
  lines: z.array(z.object({ number: z.number(), level: z.string(), text: z.string() })),
});
export type LogPayload = z.infer<typeof logPayloadSchema>;

export const healthSchema = z.object({
  id: z.number(),
  website_id: z.number(),
  url: z.string(),
  expected_status: z.number(),
  status: z.string(),
  consecutive_failures: z.number(),
  last_checked_at: z.string().nullable(),
  failure_reason: z.string().nullable(),
  is_active: z.boolean(),
});
export type WebsiteHealth = z.infer<typeof healthSchema>;

export const notificationSchema = z.object({
  id: z.string(),
  title: z.string(),
  body: z.string(),
  severity: z.string(),
  url: z.string().nullable(),
  read_at: z.string().nullable(),
  created_at: z.string().nullable(),
});
export type AppNotification = z.infer<typeof notificationSchema>;

export const coolifyCapabilitySchema = z.object({
  capability: z.string(),
  supported: z.boolean(),
  endpoint: z.string().nullable(),
  permission: z.string(),
  implemented: z.boolean(),
  fallback: z.string(),
});
export type CoolifyCapability = z.infer<typeof coolifyCapabilitySchema>;

export const coolifyStatusSchema = z.object({
  enabled: z.boolean(),
  driver: z.string(),
  connected: z.boolean(),
  version: z.string().nullable().optional(),
  health: z.string(),
  internal_url: z.string().optional(),
  public_url: z.string().optional(),
  token_configured: z.boolean().optional(),
  terminal_enabled: z.boolean().optional(),
  message: z.string().optional(),
});
export type CoolifyStatus = z.infer<typeof coolifyStatusSchema>;

export const coolifyResourceLinkSchema = z.object({
  id: z.number(),
  website_id: z.number(),
  component: websiteComponentSchema.nullable().optional(),
  resource_type: z.enum(["application", "service", "database", "server", "project", "environment"]),
  coolify_uuid: z.string(),
  display_name: z.string().nullable(),
  is_primary: z.boolean(),
  is_active: z.boolean(),
  last_synced_at: z.string().nullable(),
  last_status: z.string().nullable(),
  domains: z.array(z.string()),
  project: z.string().nullable().optional(),
  environment: z.string().nullable().optional(),
  image: z.string().nullable().optional(),
  restart_count: z.number().nullable().optional(),
  metrics: z.object({ cpu: z.unknown().nullable(), memory: z.unknown().nullable() }).optional(),
  open_url: z.string().nullable().optional(),
});
export type CoolifyResourceLink = z.infer<typeof coolifyResourceLinkSchema>;

export const discoveredCoolifyResourceSchema = z.object({
  resource_type: z.string(),
  coolify_uuid: z.string(),
  display_name: z.string(),
  status: z.string(),
  project_uuid: z.string().nullable().optional(),
  environment_uuid: z.string().nullable().optional(),
  project: z.string().nullable().optional(),
  environment: z.string().nullable().optional(),
  domains: z.array(z.string()),
  repository: z.string().nullable().optional(),
  branch: z.string().nullable().optional(),
  image: z.string().nullable().optional(),
});
export type DiscoveredCoolifyResource = z.infer<typeof discoveredCoolifyResourceSchema>;

export const deploymentApprovalSchema = z.object({
  id: z.number(),
  status: z.string(),
  requested_by: z.number(),
  approved_by: z.number().nullable(),
  reason: z.string().nullable(),
  expires_at: z.string().nullable(),
  approved_at: z.string().nullable(),
});

export const deploymentSchema = z.object({
  uuid: z.string(),
  website_id: z.number(),
  website: websiteSchema.optional(),
  component: websiteComponentSchema.nullable().optional(),
  resource_link: coolifyResourceLinkSchema.nullable().optional(),
  provider: z.string(),
  trigger: z.string(),
  requested_by: z.number().nullable(),
  requester: userSchema.optional(),
  status: z.string(),
  commit_sha: z.string().nullable(),
  commit_message: z.string().nullable(),
  branch: z.string().nullable(),
  started_at: z.string().nullable(),
  finished_at: z.string().nullable(),
  duration_seconds: z.number().nullable(),
  deployment_url: z.string().nullable(),
  logs_preview: z.string().nullable(),
  failure_reason: z.string().nullable(),
  preflight: z.array(z.object({ key: z.string(), label: z.string(), passed: z.boolean(), detail: z.string() })).optional(),
  approval: deploymentApprovalSchema.nullable().optional(),
  created_at: z.string().nullable(),
});
export type Deployment = z.infer<typeof deploymentSchema>;

export const consoleCommandSchema = z.object({
  alias: z.string(),
  label: z.string(),
  description: z.string(),
  available: z.boolean(),
});
export type ConsoleCommand = z.infer<typeof consoleCommandSchema>;

export const consoleExecutionSchema = z.object({
  uuid: z.string(),
  website_id: z.number(),
  component: websiteComponentSchema.nullable().optional(),
  requested_by: z.number(),
  command_alias: z.string(),
  status: z.string(),
  started_at: z.string().nullable(),
  finished_at: z.string().nullable(),
  exit_code: z.number().nullable(),
  output_preview: z.string().nullable(),
  failure_reason: z.string().nullable(),
  created_at: z.string().nullable(),
});
export type ConsoleExecution = z.infer<typeof consoleExecutionSchema>;
