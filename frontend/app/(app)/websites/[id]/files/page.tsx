"use client";

import dynamic from "next/dynamic";
import Link from "next/link";
import { useParams } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  Archive,
  Braces,
  ChevronRight,
  Copy,
  Download,
  FileText,
  Folder,
  FolderPlus,
  Image as ImageIcon,
  Loader2,
  MoveRight,
  RefreshCw,
  RotateCcw,
  Save,
  Search,
  Settings,
  Trash2,
  Upload,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";
import { useAuth } from "@/components/auth-provider";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { fileApi, normalizeApiError, websiteApi } from "@/lib/api";
import {
  buildBreadcrumbs,
  canOpenInEditor,
  fileIconKind,
  formatBytes,
  joinClientPath,
  languageForPath,
  normalizeClientPath,
  parentPath,
  rootModeLabel,
} from "@/lib/file-workspace";
import type { AllowedPath, FileContent, FileEntry } from "@/lib/schemas";
import { cn } from "@/lib/utils";

const MonacoEditor = dynamic(() => import("@monaco-editor/react").then((mod) => mod.Editor), {
  ssr: false,
  loading: () => <div className="grid h-full place-items-center text-sm text-muted">Loading editor...</div>,
});

export default function WebsiteFilesPage() {
  const params = useParams<{ id: string }>();
  const websiteId = params.id;
  const queryClient = useQueryClient();
  const { user } = useAuth();
  const [rootId, setRootId] = React.useState<number | null>(null);
  const [currentPath, setCurrentPath] = React.useState("");
  const [selectedPath, setSelectedPath] = React.useState<string | null>(null);
  const [draft, setDraft] = React.useState("");
  const [dirtyPath, setDirtyPath] = React.useState<string | null>(null);
  const [query, setQuery] = React.useState("");
  const [newName, setNewName] = React.useState("");
  const [uploadProgress, setUploadProgress] = React.useState<number | null>(null);
  const uploadInputRef = React.useRef<HTMLInputElement>(null);
  const extractInputRef = React.useRef<HTMLInputElement>(null);

  const website = useQuery({ queryKey: ["websites", websiteId], queryFn: () => websiteApi.show(websiteId) });
  const roots = useQuery({ queryKey: ["websites", websiteId, "file-roots"], queryFn: () => fileApi.roots(websiteId) });
  const activeRootId = rootId ?? roots.data?.[0]?.id ?? null;
  const selectedRoot = roots.data?.find((root) => root.id === activeRootId);

  const entries = useQuery({
    queryKey: ["websites", websiteId, "files", selectedRoot?.id, currentPath],
    queryFn: () => fileApi.list(websiteId, selectedRoot!.id, currentPath),
    enabled: Boolean(selectedRoot),
  });

  const file = useQuery({
    queryKey: ["websites", websiteId, "files", selectedRoot?.id, selectedPath, "content"],
    queryFn: () => fileApi.content(websiteId, selectedRoot!.id, selectedPath!),
    enabled: Boolean(selectedRoot && selectedPath),
    retry: false,
  });

  const revisions = useQuery({
    queryKey: ["websites", websiteId, "files", selectedRoot?.id, selectedPath, "revisions"],
    queryFn: () => fileApi.revisions(websiteId, selectedRoot!.id, selectedPath!),
    enabled: Boolean(selectedRoot && selectedPath),
  });

  const trash = useQuery({ queryKey: ["websites", websiteId, "trash"], queryFn: () => fileApi.trashList(websiteId), enabled: Boolean(selectedRoot) });

  const refreshWorkspace = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ["websites", websiteId, "files"] }),
      queryClient.invalidateQueries({ queryKey: ["websites", websiteId, "trash"] }),
    ]);
  };

  const saveMutation = useMutation({
    mutationFn: () => fileApi.save(websiteId, selectedRoot!.id, selectedPath!, editorDraft, file.data?.checksum ?? ""),
    onSuccess: async () => {
      setDirtyPath(null);
      toast.success("File saved.");
      await refreshWorkspace();
      await queryClient.invalidateQueries({ queryKey: ["websites", websiteId, "files", selectedRoot?.id, selectedPath, "revisions"] });
      await file.refetch();
    },
    onError: (error) => toast.error(normalizeApiError(error).message),
  });

  const createMutation = useMutation({
    mutationFn: (kind: "file" | "directory") => {
      const path = joinClientPath(currentPath, newName);
      return kind === "file" ? fileApi.createFile(websiteId, selectedRoot!.id, path) : fileApi.createDirectory(websiteId, selectedRoot!.id, path);
    },
    onSuccess: async () => {
      setNewName("");
      toast.success("Created.");
      await refreshWorkspace();
    },
    onError: (error) => toast.error(normalizeApiError(error).message),
  });

  const actionMutation = useMutation({
    mutationFn: async (action: { type: "rename" | "copy" | "move" | "trash" | "restore"; path: string; value?: string; trashId?: number }) => {
      if (action.type === "rename") {
        return fileApi.rename(websiteId, selectedRoot!.id, action.path, action.value ?? "");
      }
      if (action.type === "copy") {
        return fileApi.copy(websiteId, selectedRoot!.id, action.path, action.value ?? "");
      }
      if (action.type === "move") {
        return fileApi.move(websiteId, selectedRoot!.id, action.path, action.value ?? "");
      }
      if (action.type === "restore" && action.trashId) {
        return fileApi.restoreTrash(websiteId, action.trashId);
      }

      return fileApi.trash(websiteId, selectedRoot!.id, action.path);
    },
    onSuccess: async () => {
      toast.success("Workspace updated.");
      setSelectedPath(null);
      await refreshWorkspace();
    },
    onError: (error) => toast.error(normalizeApiError(error).message),
  });

  const restoreRevision = useMutation({
    mutationFn: (revisionId: number) => fileApi.restoreRevision(websiteId, revisionId),
    onSuccess: async () => {
      toast.success("Revision restored.");
      await refreshWorkspace();
      await file.refetch();
    },
    onError: (error) => toast.error(normalizeApiError(error).message),
  });

  const permanentDelete = useMutation({
    mutationFn: (values: { trashId: number; password: string }) => fileApi.permanentDeleteTrash(websiteId, values.trashId, values.password),
    onSuccess: async () => {
      toast.success("Trash entry permanently deleted.");
      await refreshWorkspace();
    },
    onError: (error) => toast.error(normalizeApiError(error).message),
  });

  const visibleEntries = React.useMemo(() => {
    const term = query.trim().toLowerCase();
    return (entries.data ?? []).filter((entry) => entry.name.toLowerCase().includes(term));
  }, [entries.data, query]);

  const selectedEntry = (entries.data ?? []).find((entry) => entry.relativePath === selectedPath) ?? null;
  const editorDraft = dirtyPath === selectedPath ? draft : file.data?.content ?? "";
  const hasUnsavedChanges = dirtyPath === selectedPath && draft !== file.data?.content;
  const canWrite = Boolean(selectedRoot?.capabilities.write);
  const canUpload = Boolean(selectedRoot?.capabilities.upload);
  const canArchive = Boolean(selectedRoot?.capabilities.archive);
  const canExtract = Boolean(selectedRoot?.capabilities.extract);
  const canEdit = Boolean(selectedRoot?.capabilities.write && file.data && !file.data.readOnlyReason);

  async function openEntry(entry: FileEntry) {
    if (entry.type === "directory") {
      setCurrentPath(entry.relativePath);
      setSelectedPath(null);
      setDirtyPath(null);
      return;
    }

    setSelectedPath(entry.relativePath);
    setDirtyPath(null);
  }

  async function uploadFile(fileToUpload: File | null) {
    if (!fileToUpload || !selectedRoot) {
      return;
    }

    setUploadProgress(0);
    try {
      await fileApi.upload(websiteId, selectedRoot.id, currentPath, fileToUpload, true, setUploadProgress);
      toast.success("File uploaded.");
      await refreshWorkspace();
    } catch (error) {
      toast.error(normalizeApiError(error).message);
    } finally {
      setUploadProgress(null);
    }
  }

  async function extractFile(fileToExtract: File | null) {
    if (!fileToExtract || !selectedRoot) {
      return;
    }

    setUploadProgress(0);
    try {
      await fileApi.extract(websiteId, selectedRoot.id, currentPath, fileToExtract, false, setUploadProgress);
      toast.success("Archive extracted.");
      await refreshWorkspace();
    } catch (error) {
      toast.error(normalizeApiError(error).message);
    } finally {
      setUploadProgress(null);
    }
  }

  async function downloadArchive() {
    if (!selectedRoot) {
      return;
    }

    try {
      const blob = await fileApi.archive(websiteId, selectedRoot.id, selectedPath ?? currentPath);
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      const archiveName = (selectedPath ?? currentPath) || "workspace";
      link.download = `${archiveName.split("/").pop() ?? "workspace"}.zip`;
      link.click();
      URL.revokeObjectURL(url);
    } catch (error) {
      toast.error(normalizeApiError(error).message);
    }
  }

  function promptAction(type: "rename" | "copy" | "move", path: string) {
    const label = type === "rename" ? "New name" : "Destination path";
    const value = window.prompt(label, type === "rename" ? path.split("/").pop() : path);
    if (value) {
      actionMutation.mutate({ type, path, value: normalizeClientPath(value) });
    }
  }

  if (website.isLoading || roots.isLoading) {
    return <Skeleton className="h-[620px]" />;
  }

  if (website.isError || roots.isError || !website.data) {
    return <Card><CardContent className="p-6 text-sm text-danger">Unable to load the file workspace for this website.</CardContent></Card>;
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <div className="flex items-center gap-2 text-sm text-muted">
            <Link href={`/websites/${websiteId}`} className="hover:text-foreground">{website.data.name}</Link>
            <ChevronRight className="h-4 w-4" />
            <span>Files</span>
          </div>
          <h1 className="mt-2 text-2xl font-semibold">File workspace</h1>
          <p className="mt-1 text-sm text-muted">Approved roots only. Protected secrets and escaping paths are blocked by the API.</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" onClick={() => entries.refetch()}>
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
          <Link href={`/websites/${websiteId}/settings/files`} className="inline-flex h-9 items-center gap-2 rounded-[var(--radius)] border border-border bg-panel px-3 text-sm font-medium hover:bg-panel-muted">
            <Settings className="h-4 w-4" />
            File roots
          </Link>
        </div>
      </div>

      {!selectedRoot ? (
        <Card>
          <CardContent className="p-8 text-center">
            <h2 className="text-lg font-semibold">No file roots configured</h2>
            <p className="mx-auto mt-2 max-w-xl text-sm text-muted">An owner must approve a specific project directory before YouPanel can browse files.</p>
            <Link href={`/websites/${websiteId}/settings/files`} className="mt-4 inline-flex h-9 items-center gap-2 rounded-[var(--radius)] border border-accent bg-accent px-3 text-sm font-medium text-accent-foreground">
              Configure roots
            </Link>
          </CardContent>
        </Card>
      ) : (
        <div className="grid min-h-[680px] gap-4 xl:grid-cols-[360px_minmax(0,1fr)_320px]">
          <Card className="overflow-hidden">
            <CardHeader className="space-y-3">
              <div className="flex items-center justify-between">
                <h2 className="font-semibold">Workspace</h2>
                <Badge value={rootModeLabel(selectedRoot).toLowerCase().replace("/", "-")} />
              </div>
              <select
                className="h-10 w-full rounded-[var(--radius)] border border-border bg-panel px-3 text-sm"
                value={selectedRoot.id}
                onChange={(event) => {
                  setRootId(Number(event.target.value));
                  setCurrentPath("");
                  setSelectedPath(null);
                }}
              >
                {roots.data?.map((root) => <option key={root.id} value={root.id}>{root.label}</option>)}
              </select>
              <div className="flex flex-wrap items-center gap-1 text-xs text-muted">
                {buildBreadcrumbs(currentPath).map((crumb, index) => (
                  <React.Fragment key={crumb.path || "root"}>
                    {index > 0 ? <ChevronRight className="h-3 w-3" /> : null}
                    <button className="rounded px-1 py-0.5 hover:bg-panel-muted hover:text-foreground" onClick={() => setCurrentPath(crumb.path)}>{crumb.label}</button>
                  </React.Fragment>
                ))}
              </div>
              <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-muted" />
                <Input className="pl-9" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Filter current folder" />
              </div>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="flex gap-2">
                <Input value={newName} onChange={(event) => setNewName(event.target.value)} placeholder="new-file.ts" disabled={!canWrite} />
                <Button size="icon" disabled={!newName || !canWrite || createMutation.isPending} title="Create file" onClick={() => createMutation.mutate("file")}>
                  <FileText className="h-4 w-4" />
                </Button>
                <Button size="icon" disabled={!newName || !canWrite || createMutation.isPending} title="Create folder" onClick={() => createMutation.mutate("directory")}>
                  <FolderPlus className="h-4 w-4" />
                </Button>
              </div>

              <div
                className="rounded-[var(--radius)] border border-dashed border-border p-3 text-center text-xs text-muted"
                onDragOver={(event) => event.preventDefault()}
                onDrop={(event) => {
                  event.preventDefault();
                  void uploadFile(event.dataTransfer.files.item(0));
                }}
              >
                Drop uploads here
                {uploadProgress !== null ? <span className="ml-2 text-accent">{uploadProgress}%</span> : null}
              </div>

              <div className="max-h-[440px] overflow-auto rounded-[var(--radius)] border border-border">
                {currentPath ? (
                  <button className="flex h-10 w-full items-center gap-3 border-b border-border px-3 text-left text-sm hover:bg-panel-muted" onClick={() => setCurrentPath(parentPath(currentPath))}>
                    <Folder className="h-4 w-4 text-accent" />
                    ..
                  </button>
                ) : null}
                {entries.isLoading ? <div className="p-3"><Skeleton className="h-24" /></div> : null}
                {!entries.isLoading && visibleEntries.length === 0 ? <div className="p-6 text-center text-sm text-muted">No files in this view.</div> : null}
                {visibleEntries.map((entry) => (
                  <FileRow
                    key={entry.relativePath}
                    entry={entry}
                    selected={selectedPath === entry.relativePath}
                    root={selectedRoot}
                    onOpen={() => openEntry(entry)}
                    onRename={() => promptAction("rename", entry.relativePath)}
                    onCopy={() => promptAction("copy", entry.relativePath)}
                    onMove={() => promptAction("move", entry.relativePath)}
                    onTrash={() => actionMutation.mutate({ type: "trash", path: entry.relativePath })}
                  />
                ))}
              </div>
            </CardContent>
          </Card>

          <Card className="min-w-0 overflow-hidden">
            <CardHeader className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              <div className="min-w-0">
                <h2 className="truncate font-semibold">{selectedPath ?? "Select a file"}</h2>
                <p className="text-xs text-muted">{file.data ? `${languageForPath(file.data.relativePath)} · ${formatBytes(file.data.size)}` : "Current files only, no terminal access."}</p>
              </div>
              <div className="flex flex-wrap gap-2">
                <Button size="sm" disabled={!canEdit || saveMutation.isPending || !hasUnsavedChanges} onClick={() => saveMutation.mutate()}>
                  {saveMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                  Save
                </Button>
                <Button size="sm" disabled={!selectedPath || !canArchive} onClick={downloadArchive}>
                  <Archive className="h-4 w-4" />
                  Archive
                </Button>
                <input ref={uploadInputRef} type="file" className="hidden" onChange={(event) => void uploadFile(event.target.files?.item(0) ?? null)} />
                <Button size="sm" disabled={!canUpload} onClick={() => uploadInputRef.current?.click()}>
                  <Upload className="h-4 w-4" />
                  Upload
                </Button>
              </div>
            </CardHeader>
            <CardContent className="h-[560px] p-0">
              {!selectedPath ? (
                <div className="grid h-full place-items-center p-8 text-center">
                  <div>
                    <FileText className="mx-auto h-10 w-10 text-muted" />
                    <h3 className="mt-3 font-semibold">Choose a file to inspect</h3>
                    <p className="mt-1 max-w-md text-sm text-muted">Editable text files open in Monaco. Binary, oversized, protected, or unavailable files stay read-only.</p>
                  </div>
                </div>
              ) : file.isLoading ? (
                <div className="p-4"><Skeleton className="h-[520px]" /></div>
              ) : file.isError ? (
                <div className="grid h-full place-items-center p-8 text-center text-sm text-danger">{normalizeApiError(file.error).message}</div>
              ) : file.data ? (
                <EditorSurface
                  root={selectedRoot}
                  file={file.data}
                  entry={selectedEntry}
                  draft={editorDraft}
                  onChange={(value) => {
                    setDraft(value);
                    setDirtyPath(selectedPath);
                  }}
                />
              ) : null}
            </CardContent>
          </Card>

          <aside className="space-y-4">
            <Card>
              <CardHeader><h2 className="font-semibold">Actions</h2></CardHeader>
              <CardContent className="grid gap-2">
                <Button variant="secondary" disabled={!selectedPath} onClick={() => selectedPath && window.open(fileApi.downloadUrl(websiteId, selectedRoot.id, selectedPath), "_blank")}>
                  <Download className="h-4 w-4" />
                  Download
                </Button>
                <Button variant="secondary" disabled={!selectedPath || !selectedRoot.capabilities.rename} onClick={() => selectedPath && promptAction("rename", selectedPath)}>
                  <MoveRight className="h-4 w-4" />
                  Rename
                </Button>
                <Button variant="secondary" disabled={!selectedPath || !selectedRoot.capabilities.copy} onClick={() => selectedPath && promptAction("copy", selectedPath)}>
                  <Copy className="h-4 w-4" />
                  Copy
                </Button>
                <Button variant="danger" disabled={!selectedPath || !selectedRoot.capabilities.delete} onClick={() => selectedPath && actionMutation.mutate({ type: "trash", path: selectedPath })}>
                  <Trash2 className="h-4 w-4" />
                  Move to trash
                </Button>
                <input ref={extractInputRef} type="file" accept=".zip,application/zip" className="hidden" onChange={(event) => void extractFile(event.target.files?.item(0) ?? null)} />
                <Button variant="secondary" disabled={!canExtract} onClick={() => extractInputRef.current?.click()}>
                  <Archive className="h-4 w-4" />
                  Extract zip here
                </Button>
              </CardContent>
            </Card>

            <Card>
              <CardHeader><h2 className="font-semibold">Revisions</h2></CardHeader>
              <CardContent className="space-y-2">
                {!selectedPath ? <p className="text-sm text-muted">Select a file.</p> : null}
                {revisions.data?.length === 0 ? <p className="text-sm text-muted">No revisions yet.</p> : null}
                {revisions.data?.slice(0, 6).map((revision) => (
                  <div key={revision.id} className="rounded-[var(--radius)] border border-border p-3 text-sm">
                    <div className="flex items-center justify-between gap-3">
                      <span className="font-medium">{revision.operation}</span>
                      <Button size="icon" variant="ghost" title="Restore revision" onClick={() => restoreRevision.mutate(revision.id)}>
                        <RotateCcw className="h-4 w-4" />
                      </Button>
                    </div>
                    <p className="text-xs text-muted">{revision.created_at ? new Date(revision.created_at).toLocaleString() : "Unknown time"}</p>
                  </div>
                ))}
              </CardContent>
            </Card>

            <Card>
              <CardHeader><h2 className="font-semibold">Trash</h2></CardHeader>
              <CardContent className="space-y-2">
                {trash.data?.length === 0 ? <p className="text-sm text-muted">Trash is empty.</p> : null}
                {trash.data?.slice(0, 5).map((entry) => (
                  <div key={entry.id} className="rounded-[var(--radius)] border border-border p-3 text-sm">
                    <div className="truncate font-medium">{entry.original_relative_path}</div>
                    <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                      <span className="text-xs text-muted">{entry.item_type}</span>
                      <div className="flex gap-1">
                        <Button size="sm" variant="ghost" onClick={() => actionMutation.mutate({ type: "restore", path: entry.original_relative_path, trashId: entry.id })}>Restore</Button>
                        {user?.role === "owner" ? (
                          <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                              const password = window.prompt("Owner password required");
                              if (password) {
                                permanentDelete.mutate({ trashId: entry.id, password });
                              }
                            }}
                          >
                            Delete
                          </Button>
                        ) : null}
                      </div>
                    </div>
                  </div>
                ))}
              </CardContent>
            </Card>
          </aside>
        </div>
      )}
    </div>
  );
}

function EditorSurface({
  root,
  file,
  entry,
  draft,
  onChange,
}: {
  root: AllowedPath;
  file: FileContent;
  entry: FileEntry | null;
  draft: string;
  onChange: (value: string) => void;
}) {
  const readOnly = !canOpenInEditor(root, entry) || Boolean(file.readOnlyReason) || !root.capabilities.write;

  return (
    <div className="h-full">
      {readOnly ? (
        <div className="border-b border-border bg-panel-muted px-4 py-2 text-xs text-muted">{file.readOnlyReason ?? "This file is read-only for your role or root permissions."}</div>
      ) : null}
      <MonacoEditor
        height={readOnly ? "520px" : "560px"}
        language={file.language || languageForPath(file.relativePath)}
        value={draft}
        theme="vs-dark"
        options={{
          automaticLayout: true,
          fontSize: 13,
          minimap: { enabled: false },
          readOnly,
          scrollBeyondLastLine: false,
          wordWrap: "on",
        }}
        onChange={(value) => onChange(value ?? "")}
      />
    </div>
  );
}

function FileRow({
  entry,
  selected,
  root,
  onOpen,
  onRename,
  onCopy,
  onMove,
  onTrash,
}: {
  entry: FileEntry;
  selected: boolean;
  root: AllowedPath;
  onOpen: () => void;
  onRename: () => void;
  onCopy: () => void;
  onMove: () => void;
  onTrash: () => void;
}) {
  return (
    <div className={cn("group grid grid-cols-[1fr_auto] items-center border-b border-border last:border-b-0", selected && "bg-panel-muted")}>
      <button className="flex min-w-0 items-center gap-3 px-3 py-2 text-left text-sm hover:bg-panel-muted" onClick={onOpen}>
        {renderFileIcon(entry)}
        <span className="min-w-0 flex-1 truncate">{entry.name}</span>
        {entry.protected ? <span className="rounded border border-border px-1.5 py-0.5 text-[10px] text-muted">protected</span> : null}
        <span className="hidden text-xs text-muted sm:inline">{formatBytes(entry.size)}</span>
      </button>
      <div className="flex items-center gap-1 pr-2 opacity-100 sm:opacity-0 sm:transition sm:group-hover:opacity-100">
        <Button size="icon" variant="ghost" title="Rename" disabled={!root.capabilities.rename} onClick={onRename}><MoveRight className="h-3.5 w-3.5" /></Button>
        <Button size="icon" variant="ghost" title="Copy" disabled={!root.capabilities.copy} onClick={onCopy}><Copy className="h-3.5 w-3.5" /></Button>
        <Button size="icon" variant="ghost" title="Move" disabled={!root.capabilities.move} onClick={onMove}><Folder className="h-3.5 w-3.5" /></Button>
        <Button size="icon" variant="ghost" title="Trash" disabled={!root.capabilities.delete} onClick={onTrash}><Trash2 className="h-3.5 w-3.5" /></Button>
      </div>
    </div>
  );
}

function renderFileIcon(entry: FileEntry) {
  const kind = fileIconKind(entry);
  if (kind === "folder") {
    return <Folder className="h-4 w-4 shrink-0 text-accent" />;
  }
  if (kind === "code") {
    return <Braces className="h-4 w-4 shrink-0 text-muted" />;
  }
  if (kind === "image") {
    return <ImageIcon className="h-4 w-4 shrink-0 text-muted" />;
  }
  if (kind === "archive") {
    return <Archive className="h-4 w-4 shrink-0 text-muted" />;
  }

  return <FileText className="h-4 w-4 shrink-0 text-muted" />;
}
