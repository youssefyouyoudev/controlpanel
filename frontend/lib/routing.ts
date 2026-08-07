export const publicRoutes = ["/", "/login", "/forgot-password", "/reset-password", "/unauthorized"] as const;

export const protectedRoutePrefixes = [
  "/actions",
  "/activity",
  "/backups",
  "/containers",
  "/dashboard",
  "/deployments",
  "/server",
  "/settings",
  "/websites",
] as const;

export function isPublicRoute(pathname: string) {
  return publicRoutes.some((route) => pathname === route);
}

export function isProtectedRoute(pathname: string) {
  return protectedRoutePrefixes.some((prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`));
}

export function safeReturnTo(value: string | null | undefined, fallback = "/dashboard") {
  if (!value) {
    return fallback;
  }

  if (!value.startsWith("/") || value.startsWith("//")) {
    return fallback;
  }

  try {
    const url = new URL(value, "http://youpanel.local");
    if (url.origin !== "http://youpanel.local") {
      return fallback;
    }

    if (!isProtectedRoute(url.pathname)) {
      return fallback;
    }

    return `${url.pathname}${url.search}${url.hash}`;
  } catch {
    return fallback;
  }
}
