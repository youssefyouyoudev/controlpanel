import os from "node:os";
import { WebSocketServer } from "ws";
import pty from "node-pty";

const port = Number.parseInt(process.env.YOUPANEL_TERMINAL_GATEWAY_PORT ?? "8787", 10);
const apiUrl = (process.env.YOUPANEL_TERMINAL_API_URL ?? "http://127.0.0.1:8000").replace(/\/+$/, "");
const gatewaySecret = process.env.YOUPANEL_TERMINAL_GATEWAY_SECRET ?? "";
const authTimeoutMs = Number.parseInt(process.env.YOUPANEL_TERMINAL_AUTH_TIMEOUT_MS ?? "5000", 10);
const maxPayloadBytes = Number.parseInt(process.env.YOUPANEL_TERMINAL_MAX_MESSAGE_BYTES ?? "16384", 10);
const maxInputBytes = Number.parseInt(process.env.YOUPANEL_TERMINAL_MAX_INPUT_BYTES ?? "8192", 10);
const maxOutputBytes = Number.parseInt(process.env.YOUPANEL_TERMINAL_MAX_OUTPUT_BYTES ?? "1048576", 10);
const production = process.env.NODE_ENV === "production";
const weakSecrets = new Set(["secret", "password", "changeme", "test", "development"]);

if (!gatewaySecret) {
  throw new Error("YOUPANEL_TERMINAL_GATEWAY_SECRET is required.");
}

if (production && (gatewaySecret.length < 32 || weakSecrets.has(gatewaySecret.toLowerCase()))) {
  throw new Error("YOUPANEL_TERMINAL_GATEWAY_SECRET is too weak for production.");
}

const allowedOrigins = normalizeAllowedOrigins(process.env.YOUPANEL_TERMINAL_ALLOWED_ORIGINS ?? (production ? "" : "http://localhost:3000,http://127.0.0.1:3000"));
if (production && allowedOrigins.length === 0) {
  throw new Error("YOUPANEL_TERMINAL_ALLOWED_ORIGINS must be configured in production.");
}

const server = new WebSocketServer({
  port,
  path: "/terminal",
  maxPayload: maxPayloadBytes,
  verifyClient(info, done) {
    const origin = normalizeOrigin(info.origin);
    done(Boolean(origin && allowedOrigins.includes(origin)), 403, "Forbidden origin");
  },
});

server.on("connection", (socket, request) => {
  const url = new URL(request.url ?? "", `ws://${request.headers.host ?? "localhost"}`);
  if (url.search) {
    closeWithError(socket, "Terminal credentials are not accepted in the WebSocket URL.");
    return;
  }

  let authenticated = false;
  let terminal = null;
  let session = null;
  let outputBytes = 0;
  let idleTimer = null;
  let maxTimer = null;
  let cleanedUp = false;
  socket.isAlive = true;

  const authTimer = setTimeout(() => {
    closeWithError(socket, "Terminal authentication timed out.");
  }, Math.max(1000, authTimeoutMs));

  socket.on("pong", () => {
    socket.isAlive = true;
  });

  socket.on("message", async (payload, isBinary) => {
    if (isBinary || payload.length > maxPayloadBytes) {
      closeWithError(socket, "Invalid terminal message.");
      return;
    }

    let message;
    try {
      message = JSON.parse(payload.toString("utf8"));
    } catch {
      closeWithError(socket, "Invalid terminal message.");
      return;
    }

    if (!authenticated) {
      if (!isAuthenticateMessage(message)) {
        closeWithError(socket, "Terminal authentication is required.");
        return;
      }

      clearTimeout(authTimer);
      await authenticateAndStart(message, socket);
      return;
    }

    if (message.type === "input" && typeof message.data === "string") {
      if (Buffer.byteLength(message.data, "utf8") > maxInputBytes) {
        closeWithError(socket, "Terminal input message is too large.");
        return;
      }
      terminal?.write(message.data);
      resetIdleTimer();
      return;
    }

    if (message.type === "resize" && Number.isInteger(message.cols) && Number.isInteger(message.rows)) {
      const cols = clamp(message.cols, 20, 240);
      const rows = clamp(message.rows, 8, 80);
      terminal?.resize(cols, rows);
      resetIdleTimer();
      return;
    }

    if (message.type === "ping") {
      safeSend(socket, { type: "pong" });
    }
  });

  socket.on("close", () => {
    clearTimeout(authTimer);
    cleanup("terminal.session.disconnected");
  });

  socket.on("error", () => {
    cleanup("terminal.gateway.rejected", "socket_error");
  });

  async function authenticateAndStart(message, ws) {
    if (typeof process.getuid === "function" && process.getuid() === 0 && process.env.YOUPANEL_TERMINAL_ALLOW_ROOT !== "true") {
      await notifySessionEvent(message.session, "terminal.gateway.rejected", "gateway_running_as_root");
      closeWithError(ws, "Terminal gateway must not run as root.");
      return;
    }

    try {
      session = await validateSession(message.session, message.ticket);
    } catch {
      await notifySessionEvent(message.session, "terminal.gateway.rejected", "validation_failed");
      closeWithError(ws, "Terminal session validation failed.");
      return;
    }

    authenticated = true;
    terminal = pty.spawn(session.shell, [], {
      name: "xterm-256color",
      cols: 100,
      rows: 30,
      cwd: session.working_directory,
      env: terminalEnvironment(session.shell),
    });

    const idleTimeoutMs = Math.max(30, Number(session.idle_timeout_seconds ?? 600)) * 1000;
    const maxDurationMs = Math.max(60, Number(session.max_duration_seconds ?? 3600)) * 1000;
    idleTimer = setTimeout(() => endSession("Idle timeout.", "terminal.session.idle_timeout"), idleTimeoutMs);
    maxTimer = setTimeout(() => endSession("Session time limit reached.", "terminal.session.max_duration"), maxDurationMs);

    terminal.onData((data) => {
      outputBytes += Buffer.byteLength(data, "utf8");
      if (outputBytes > maxOutputBytes) {
        endSession("Terminal output limit reached.", "terminal.session.output_limit");
        return;
      }

      safeSend(ws, { type: "output", data });
    });

    terminal.onExit(({ exitCode }) => {
      cleanup("terminal.session.disconnected");
      safeSend(ws, { type: "exit", code: exitCode });
      ws.close();
    });

    safeSend(ws, { type: "authenticated" });
  }

  function resetIdleTimer() {
    if (!session || !idleTimer) {
      return;
    }

    clearTimeout(idleTimer);
    const idleTimeoutMs = Math.max(30, Number(session.idle_timeout_seconds ?? 600)) * 1000;
    idleTimer = setTimeout(() => endSession("Idle timeout.", "terminal.session.idle_timeout"), idleTimeoutMs);
  }

  function endSession(message, event) {
    safeSend(socket, { type: "error", message });
    cleanup(event);
    socket.close();
  }

  function cleanup(event, reason = null) {
    if (cleanedUp) {
      return;
    }

    cleanedUp = true;
    clearTimeout(idleTimer);
    clearTimeout(maxTimer);
    if (terminal) {
      terminal.kill();
      terminal = null;
    }
    if (session?.uuid && event) {
      void notifySessionEvent(session.uuid, event, reason);
    }
  }
});

const heartbeat = setInterval(() => {
  for (const socket of server.clients) {
    if (socket.isAlive === false) {
      socket.terminate();
      continue;
    }

    socket.isAlive = false;
    socket.ping();
  }
}, 30000);

server.on("close", () => {
  clearInterval(heartbeat);
});

async function validateSession(session, ticket) {
  const response = await fetch(`${apiUrl}/api/internal/terminal/sessions/validate`, {
    method: "POST",
    headers: {
      "Accept": "application/json",
      "Content-Type": "application/json",
      "X-YouPanel-Terminal-Gateway-Secret": gatewaySecret,
    },
    body: JSON.stringify({ session, ticket }),
  });

  if (!response.ok) {
    throw new Error(`Laravel rejected terminal session: ${response.status}`);
  }

  const payload = await response.json();
  return payload.data.session;
}

async function notifySessionEvent(session, event, reason = null) {
  if (!session || !event) {
    return;
  }

  try {
    await fetch(`${apiUrl}/api/internal/terminal/sessions/${encodeURIComponent(session)}/events`, {
      method: "POST",
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-YouPanel-Terminal-Gateway-Secret": gatewaySecret,
      },
      body: JSON.stringify({ event, reason }),
    });
  } catch {
    // Lifecycle audit failures must not keep zombie PTYs alive.
  }
}

function terminalEnvironment(shell) {
  const user = os.userInfo();

  return {
    HOME: user.homedir,
    USER: user.username,
    LOGNAME: user.username,
    SHELL: shell,
    PATH: "/usr/local/bin:/usr/bin:/bin",
    LANG: "C.UTF-8",
    LC_ALL: "C.UTF-8",
    TERM: "xterm-256color",
  };
}

function isAuthenticateMessage(message) {
  return message
    && message.type === "authenticate"
    && typeof message.session === "string"
    && /^[0-9a-fA-F-]{36}$/.test(message.session)
    && typeof message.ticket === "string"
    && message.ticket.length >= 32
    && message.ticket.length <= 256;
}

function normalizeAllowedOrigins(value) {
  return value
    .split(",")
    .map((origin) => origin.trim())
    .filter((origin) => origin && origin !== "*" && origin.toLowerCase() !== "null")
    .map(normalizeOrigin)
    .filter(Boolean);
}

function normalizeOrigin(origin) {
  if (!origin || origin === "*" || origin.toLowerCase() === "null") {
    return null;
  }

  try {
    return new URL(origin).origin;
  } catch {
    return null;
  }
}

function clamp(value, min, max) {
  return Math.min(max, Math.max(min, value));
}

function safeSend(socket, message) {
  if (socket.readyState === socket.OPEN) {
    socket.send(JSON.stringify(message));
  }
}

function closeWithError(socket, message) {
  safeSend(socket, { type: "error", message });
  socket.close();
}

console.log(`YouPanel terminal gateway listening on ws://127.0.0.1:${port}/terminal`);
