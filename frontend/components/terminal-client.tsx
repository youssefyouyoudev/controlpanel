"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { useMutation } from "@tanstack/react-query";
import { Maximize2, PlugZap, Square, Terminal as TerminalIcon } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { normalizeApiError, terminalApi } from "@/lib/api";

type TerminalEnvelope =
  | { type: "output"; data: string }
  | { type: "error"; message: string }
  | { type: "exit"; code?: number };

export function TerminalClient({ websiteId }: { websiteId?: string }) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const terminalRef = useRef<import("@xterm/xterm").Terminal | null>(null);
  const fitRef = useRef<import("@xterm/addon-fit").FitAddon | null>(null);
  const socketRef = useRef<WebSocket | null>(null);
  const [password, setPassword] = useState("");
  const [connection, setConnection] = useState<"idle" | "connecting" | "connected" | "closed" | "error">("idle");
  const [terminalReady, setTerminalReady] = useState(false);
  const [socketReady, setSocketReady] = useState(false);
  const [sessionLabel, setSessionLabel] = useState<string | null>(null);
  const sendResize = useCallback(() => {
    const terminal = terminalRef.current;
    const socket = socketRef.current;
    if (terminal && socket?.readyState === WebSocket.OPEN) {
      socket.send(JSON.stringify({ type: "resize", cols: terminal.cols, rows: terminal.rows }));
    }
  }, []);
  const resize = useCallback(() => {
    fitRef.current?.fit();
    sendResize();
  }, [sendResize]);
  const createSession = useMutation({
    mutationFn: () => websiteId ? terminalApi.createForWebsite(websiteId, password) : terminalApi.create(password),
    onSuccess: async (payload) => {
      setPassword("");
      setSessionLabel(`${payload.session.scope} · ${payload.session.working_directory}`);
      await openTerminal(payload.websocket_url, payload.session.uuid, payload.token);
    },
  });

  useEffect(() => {
    window.addEventListener("resize", resize);

    return () => {
      window.removeEventListener("resize", resize);
      socketRef.current?.close();
      terminalRef.current?.dispose();
    };
  }, [resize]);

  async function openTerminal(websocketUrl: string, sessionUuid: string, token: string) {
    setConnection("connecting");
    const [{ Terminal }, { FitAddon }] = await Promise.all([import("@xterm/xterm"), import("@xterm/addon-fit")]);
    terminalRef.current?.dispose();
    const terminal = new Terminal({
      cursorBlink: true,
      convertEol: true,
      fontFamily: "JetBrains Mono, Consolas, monospace",
      fontSize: 13,
      scrollback: 5000,
      theme: { background: "#07080b", foreground: "#f5f7fb", cursor: "#7dd3fc" },
    });
    const fit = new FitAddon();
    terminal.loadAddon(fit);
    terminal.open(containerRef.current!);
    fit.fit();
    terminal.focus();
    terminal.writeln("Connecting to YouPanel terminal...");
    terminalRef.current = terminal;
    fitRef.current = fit;
    setTerminalReady(true);

    const url = new URL(websocketUrl);
    url.searchParams.set("session", sessionUuid);
    url.searchParams.set("token", token);
    const socket = new WebSocket(url);
    socketRef.current = socket;

    socket.addEventListener("open", () => {
      setConnection("connected");
      setSocketReady(true);
      terminal.writeln("\r\nConnected.");
      sendResize();
    });
    socket.addEventListener("message", (event) => {
      try {
        const message = JSON.parse(String(event.data)) as TerminalEnvelope;
        if (message.type === "output") {
          terminal.write(message.data);
        } else if (message.type === "error") {
          terminal.writeln(`\r\n${message.message}`);
          setConnection("error");
        } else if (message.type === "exit") {
          terminal.writeln(`\r\nSession ended${typeof message.code === "number" ? ` with code ${message.code}` : ""}.`);
          setConnection("closed");
        }
      } catch {
        terminal.write(String(event.data));
      }
    });
    socket.addEventListener("close", () => {
      setSocketReady(false);
      setConnection((value) => value === "error" ? "error" : "closed");
    });
    socket.addEventListener("error", () => {
      setSocketReady(false);
      setConnection("error");
    });
    terminal.onData((data) => {
      if (socket.readyState === WebSocket.OPEN) {
        socket.send(JSON.stringify({ type: "input", data }));
      }
    });
  }

  function closeTerminal() {
    socketRef.current?.close();
    setSocketReady(false);
    setConnection("closed");
  }

  const error = createSession.error ? normalizeApiError(createSession.error).message : null;

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
            <div>
              <h1 className="flex items-center gap-2 text-2xl font-semibold"><TerminalIcon className="h-5 w-5" />Terminal</h1>
              <p className="mt-1 text-sm text-muted">{sessionLabel ?? "Create a short-lived terminal session with recent password confirmation."}</p>
            </div>
            <div className="flex items-center gap-2">
              <span className="rounded-[var(--radius)] border border-border px-2 py-1 text-xs capitalize text-muted">{connection}</span>
              <Button variant="ghost" onClick={resize} disabled={!terminalReady} title="Fit terminal">
                <Maximize2 className="h-4 w-4" />
                Fit
              </Button>
              <Button variant="ghost" onClick={closeTerminal} disabled={!socketReady} title="Close terminal">
                <Square className="h-4 w-4" />
                Close
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {connection === "idle" || connection === "closed" || connection === "error" ? (
            <form className="mb-4 flex flex-col gap-2 sm:flex-row" onSubmit={(event) => { event.preventDefault(); createSession.mutate(); }}>
              <Input type="password" value={password} onChange={(event) => setPassword(event.target.value)} placeholder="Confirm password to open terminal" autoComplete="current-password" />
              <Button type="submit" disabled={createSession.isPending || password.length === 0}>
                <PlugZap className="h-4 w-4" />
                Open terminal
              </Button>
            </form>
          ) : null}
          {error ? <div className="mb-4 rounded-[var(--radius)] border border-danger/30 bg-danger/10 px-3 py-2 text-sm text-danger">{error}</div> : null}
          <div ref={containerRef} className="min-h-[520px] overflow-hidden rounded-[var(--radius)] border border-border bg-black p-2" />
        </CardContent>
      </Card>
    </div>
  );
}
