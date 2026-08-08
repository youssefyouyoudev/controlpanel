import { NextResponse, type NextRequest } from "next/server";

function nonce() {
  return Buffer.from(crypto.randomUUID()).toString("base64");
}

function csp(nonceValue: string) {
  const isDev = process.env.NODE_ENV === "development";

  const apiUrl =
    process.env.NEXT_PUBLIC_API_URL ??
    (isDev
      ? "http://localhost:8000"
      : "https://control-api.youssefyouyou.com");

  let apiOrigin = "https://control-api.youssefyouyou.com";

  try {
    apiOrigin = new URL(apiUrl).origin;
  } catch {
    // Safe production fallback if NEXT_PUBLIC_API_URL is malformed.
    apiOrigin = isDev
      ? "http://localhost:8000"
      : "https://control-api.youssefyouyou.com";
  }

  const websocketOrigin = apiOrigin
    .replace(/^https:/, "wss:")
    .replace(/^http:/, "ws:");

  const connectSources = [
    "'self'",
    apiOrigin,
    websocketOrigin,
  ];

  if (isDev) {
    connectSources.push(
      "http://localhost:8000",
      "http://127.0.0.1:8000",
      "http://localhost:3000",
      "http://127.0.0.1:3000",
      "ws://localhost:3000",
      "ws://127.0.0.1:3000",
      "ws://localhost:8787",
      "ws://127.0.0.1:8787",
    );
  }

  const directives = [
    "default-src 'self'",
    "base-uri 'self'",
    "object-src 'none'",
    "frame-ancestors 'none'",
    "form-action 'self'",
    `script-src 'self' 'nonce-${nonceValue}' 'strict-dynamic'${
      isDev ? " 'unsafe-eval'" : ""
    }`,
    "style-src 'self'",
    "style-src-elem 'self' 'unsafe-inline'",
    "style-src-attr 'unsafe-inline'",
    "img-src 'self' data: blob:",
    "font-src 'self' data:",
    `connect-src ${Array.from(new Set(connectSources)).join(" ")}`,
    "worker-src 'self' blob:",
    "manifest-src 'self'",
  ];

  if (!isDev) {
    directives.push("upgrade-insecure-requests");
  }

  return directives.join("; ");
}

export function proxy(request: NextRequest) {
  const nonceValue = nonce();
  const cspValue = csp(nonceValue);

  const requestHeaders = new Headers(request.headers);

  requestHeaders.set("x-nonce", nonceValue);
  requestHeaders.set("Content-Security-Policy", cspValue);

  const response = NextResponse.next({
    request: {
      headers: requestHeaders,
    },
  });

  response.headers.set("Content-Security-Policy", cspValue);
  response.headers.set(
    "Referrer-Policy",
    "strict-origin-when-cross-origin",
  );
  response.headers.set("X-Content-Type-Options", "nosniff");
  response.headers.set("X-Frame-Options", "DENY");
  response.headers.set(
    "Permissions-Policy",
    "camera=(), microphone=(), geolocation=(), payment=()",
  );

  if (
    process.env.NODE_ENV === "production" &&
    request.nextUrl.protocol === "https:"
  ) {
    response.headers.set(
      "Strict-Transport-Security",
      "max-age=31536000; includeSubDomains",
    );
  }

  return response;
}

export const config = {
  matcher: [
    {
      source:
        "/((?!api|_next/static|_next/image|favicon.ico|robots.txt|sitemap.xml|.*\\.(?:png|jpg|jpeg|gif|webp|svg|ico|css|js|map|woff|woff2)$).*)",
      missing: [
        {
          type: "header",
          key: "next-router-prefetch",
        },
        {
          type: "header",
          key: "purpose",
          value: "prefetch",
        },
      ],
    },
  ],
};