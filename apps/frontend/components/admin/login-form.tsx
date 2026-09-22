"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Loader2Icon } from "lucide-react";
import { useAdminSession } from "@/components/admin/admin-session-provider";
import { fieldErrorsOf } from "@/lib/api/admin/client";
import { adminLoginSchema, type AdminLoginValues } from "@/lib/schemas/admin";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";

export function LoginForm() {
  const { login, status } = useAdminSession();
  const router = useRouter();
  const [submitError, setSubmitError] = useState<string | null>(null);

  const form = useForm<AdminLoginValues>({
    resolver: zodResolver(adminLoginSchema),
    defaultValues: { email: "", password: "" },
  });

  // Someone who is already signed in has no business on the login screen —
  // this also covers coming back to /admin/login with a live session.
  useEffect(() => {
    if (status === "authenticated") {
      router.replace("/admin");
    }
  }, [status, router]);

  async function onSubmit(values: AdminLoginValues) {
    setSubmitError(null);

    try {
      await login(values);
      router.replace("/admin");
    } catch (error) {
      const fields = fieldErrorsOf(error);

      // The backend answers every failed login on the `email` field, on
      // purpose: it never says whether the account exists.
      if (fields.email) {
        form.setError("email", { message: fields.email });
        return;
      }

      setSubmitError("No pudimos iniciar sesión. Intenta de nuevo en un momento.");
    }
  }

  const submitting = form.formState.isSubmitting;

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
        <FormField
          control={form.control}
          name="email"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Correo</FormLabel>
              <FormControl>
                <Input
                  type="email"
                  autoComplete="username"
                  placeholder="admin@tienda.com"
                  {...field}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="password"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Contraseña</FormLabel>
              <FormControl>
                <Input type="password" autoComplete="current-password" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        {submitError ? (
          <p className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
            {submitError}
          </p>
        ) : null}

        <Button type="submit" disabled={submitting} className="w-full">
          {submitting ? <Loader2Icon className="animate-spin" /> : null}
          {submitting ? "Entrando..." : "Entrar"}
        </Button>
      </form>
    </Form>
  );
}
