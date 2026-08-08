import { WebSocketServer } from "ws";
import pty from "node-pty";

const port = Number.parseInt(process.env.YOUPANEL_TERMINAL_GATEWAY_PORT ?? "8787", 10);
const apiUrl = (process.env.YOUPANEL_TERMINAL_API_URL ?? "http://127.0.0.1:8000").replace(/\/+$/, "");
const gatewaySecret = process.env.YOUPANEL_TERMINAL_GATEWAY_SECRET ?? "";
const allowedOrigins = (process.env.YOUPANEL_TERMINAL_ALLOWED_ORIGINS ?? "http://localhost:3000,http://127.0.0.1:3000")
  .split(",")
  .map((origin) => origin.trim())
  .filter(Boolean);

if (!gatewaySecret) {
  throw new Error("YOUPANEL_TERMINAL_GATEWAY_SECRET is required.");
}

const server = new WebSocketServer({
  port,
  path: "/terminal",
  verifyClient(info, done) {
    const origin = info.origin;
    done(Boolean(origin && allowedOrigins.includes(origin)), 403, "Forbidden origin");
  },
});

server.on("connection", async (socket, request) => {
  const url = new URL(request.url ?? "", `ws://${request.headers.host ?? "localhost"}`);
  const sessionUuid = url.searchParams.get("session");
  const token = url.searchParams.get("token");

  if (!sessionUuid || !token) {
    closeWithError(socket, "Missing terminal session credentials.");
    return;
  }

  let session;
  try {
    session = await validateSession(sessionUuid, token);
  } catch {
    closeWithError(socket, "Terminal session validation failed.");
    return;
  }

  if (typeof process.getuid === "function" && process.getuid() === 0 && process.env.YOUPANEL_TERMINAL_ALLOW_ROOT !== "true") {
    closeWithError(socket, "Terminal gateway must not run as root.");
    return;
  }

  const terminal = pty.spawn(session.shell, [], {
    name: "xterm-256color",
    cols: 100,
    rows: 30,
    cwd: session.working_directory,
    env: { ...process.env, TERM: "xterm-256color" },
  });

  const idleTimeoutMs = Math.max(30, Number(session.idle_timeout_seconds ?? 600)) * 1000;
  const maxDurationMs = Math.max(60, Number(session.max_duration_seconds ?? 3600)) * 1000;
  let idleTimer = setTimeout(() => endSession("Idle timeout."), idleTimeoutMs);
  const maxTimer = setTimeout(() => endSession("Session time limit reached."), maxDurationMs);

  terminal.onData((data) => {
    if (socket.readyState === socket.OPEN) {
      socket.send(JSON.stringify({ type: "output", data }));
    }
  });

  terminal.onExit(({ exitCode }) => {
    clearTimeout(idleTimer);
    clearTimeout(maxTimer);
    if (socket.readyState === socket.OPEN) {
      socket.send(JSON.stringify({ type: "exit", code: exitCode }));
      socket.close();
    }
  });

  socket.on("message", (payload) => {
    resetIdleTimer();
    let message;
    try {
      message = JSON.parse(String(payload));
    } catch {
      return;
    }

    if (message.type === "input" && typeof message.data === "string") {
      terminal.write(message.data);
    }

    if (message.type === "resize" && Number.isInteger(message.cols) && Number.isInteger(message.rows)) {
      terminal.resize(Math.max(20, message.cols), Math.max(8, message.rows));
    }
  });

  socket.on("close", () => {
    clearTimeout(idleTimer);
    clearTimeout(maxTimer);
    terminal.kill();
  });

  function resetIdleTimer() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(() => endSession("Idle timeout."), idleTimeoutMs);
  }

  function endSession(message) {
    if (socket.readyState === socket.OPEN) {
      socket.send(JSON.stringify({ type: "error", message }));
      socket.close();
    }
    terminal.kill();
  }
});

async function validateSession(session, token) {
  const response = await fetch(`${apiUrl}/api/internal/terminal/sessions/validate`, {
    method: "POST",
    headers: {
      "Accept": "application/json",
      "Content-Type": "application/json",
      "X-YouPanel-Terminal-Gateway-Secret": gatewaySecret,
    },
    body: JSON.stringify({ session, token }),
  });

  if (!response.ok) {
    throw new Error(`Laravel rejected terminal session: ${response.status}`);
  }

  const payload = await response.json();
  return payload.data.session;
}

function closeWithError(socket, message) {
  if (socket.readyState === socket.OPEN) {
    socket.send(JSON.stringify({ type: "error", message }));
  }
  socket.close();
}

console.log(`YouPanel terminal gateway listening on ws://127.0.0.1:${port}/terminal`);
