import { z } from "zod";

const publicEnvSchema = z.object({
  NEXT_PUBLIC_API_URL: z.string().url(),
  NEXT_PUBLIC_DEMO_MODE: z.enum(["true", "false"]).default("false"),
  NEXT_PUBLIC_SESSION_COOKIE_NAME: z.string().min(1).default("youpanel-session"),
});

const defaultApiUrl = process.env.NODE_ENV === "production"
  ? "https://control-api.youssefyouyou.com"
  : "http://localhost:8000";

export const env = publicEnvSchema.parse({
  NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL ?? defaultApiUrl,
  NEXT_PUBLIC_DEMO_MODE: process.env.NEXT_PUBLIC_DEMO_MODE,
  NEXT_PUBLIC_SESSION_COOKIE_NAME: process.env.NEXT_PUBLIC_SESSION_COOKIE_NAME,
});

export const isDemoMode = env.NEXT_PUBLIC_DEMO_MODE === "true";
