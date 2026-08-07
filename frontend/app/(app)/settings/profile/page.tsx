"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { z } from "zod";
import { useAuth } from "@/components/auth-provider";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { authApi, normalizeApiError } from "@/lib/api";

const schema = z.object({
  name: z.string().min(2).max(120),
  email: z.string().email(),
  timezone: z.string().min(1),
});

export default function ProfileSettingsPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const form = useForm<z.infer<typeof schema>>({
    resolver: zodResolver(schema),
    values: { name: user?.name ?? "", email: user?.email ?? "", timezone: user?.timezone ?? "UTC" },
  });
  const mutation = useMutation({
    mutationFn: authApi.updateProfile,
    onSuccess: (updated) => {
      queryClient.setQueryData(["auth", "me"], updated);
      toast.success("Profile updated.");
    },
  });
  const error = mutation.isError ? normalizeApiError(mutation.error) : null;

  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-semibold">Profile Settings</h1>
      <Card>
        <CardHeader><h2 className="font-semibold">Current profile</h2></CardHeader>
        <CardContent>
          <form className="grid max-w-lg gap-3" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
            <label className="text-sm font-medium" htmlFor="name">Name</label>
            <Input id="name" {...form.register("name")} />
            <label className="text-sm font-medium" htmlFor="email">Email</label>
            <Input id="email" type="email" {...form.register("email")} />
            <label className="text-sm font-medium" htmlFor="timezone">Timezone</label>
            <Input id="timezone" {...form.register("timezone")} />
            {error ? <p className="text-sm text-danger">{error.message}</p> : null}
            <Button type="submit" variant="primary" className="w-fit" disabled={mutation.isPending}>Save profile</Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
