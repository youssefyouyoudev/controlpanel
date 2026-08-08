"use client";

import { useParams } from "next/navigation";
import { TerminalClient } from "@/components/terminal-client";

export default function WebsiteTerminalPage() {
  const params = useParams<{ id: string }>();

  return <TerminalClient websiteId={params.id} />;
}
