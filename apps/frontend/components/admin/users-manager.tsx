"use client";

import { useCallback, useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Loader2Icon, PlusIcon } from "lucide-react";
import { toast } from "sonner";
import { useAdminSession } from "@/components/admin/admin-session-provider";
import { fieldErrorsOf } from "@/lib/api/admin/client";
import { ApiError } from "@/lib/api/client";
import type { AdminUser } from "@/lib/api/admin/auth";
import { createUser, listUsers, setUserActive, updateUser } from "@/lib/api/admin/users";
import {
  adminUserCreateSchema,
  adminUserEditSchema,
  type AdminUserValues,
} from "@/lib/schemas/admin";
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
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

/** Maps backend field names to the RHF field names of adminUserSchema. */
const BACKEND_TO_FORM_FIELD: Record<string, keyof AdminUserValues> = {
  name: "name",
  email: "email",
  role: "role",
  password: "password",
};

const EMPTY_FORM: AdminUserValues = {
  name: "",
  email: "",
  role: "staff",
  password: "",
  passwordConfirmation: "",
};

export function UsersManager() {
  const { apiBaseUrl, user: currentUser, refresh } = useAdminSession();

  const [users, setUsers] = useState<AdminUser[] | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [editing, setEditing] = useState<AdminUser | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [pendingId, setPendingId] = useState<number | null>(null);

  const load = useCallback(
    async (signal?: AbortSignal) => {
      try {
        setUsers(await listUsers(apiBaseUrl, signal));
        setLoadError(null);
      } catch (error) {
        if (signal?.aborted) return;
        setLoadError(
          error instanceof ApiError
            ? error.message
            : "No pudimos cargar las cuentas. Intenta de nuevo.",
        );
      }
    },
    [apiBaseUrl],
  );

  useEffect(() => {
    const controller = new AbortController();
    void load(controller.signal);

    return () => controller.abort();
  }, [load]);

  const form = useForm<AdminUserValues>({
    resolver: zodResolver(editing ? adminUserEditSchema : adminUserCreateSchema),
    defaultValues: EMPTY_FORM,
  });

  function openCreate() {
    setEditing(null);
    form.reset(EMPTY_FORM);
    setFormOpen(true);
  }

  function openEdit(target: AdminUser) {
    setEditing(target);
    form.reset({
      name: target.name,
      email: target.email,
      role: target.role,
      password: "",
      passwordConfirmation: "",
    });
    setFormOpen(true);
  }

  async function onSubmit(values: AdminUserValues) {
    try {
      if (editing) {
        await updateUser(apiBaseUrl, editing.id, {
          name: values.name,
          email: values.email,
          role: values.role,
          // Blank means "keep the current password" — the backend treats an
          // absent key the same way.
          ...(values.password
            ? { password: values.password, password_confirmation: values.passwordConfirmation }
            : {}),
        });

        toast.success("Cuenta actualizada.");

        // Editing your own role or password re-reads the session, since the
        // backend drops the other sessions of that account.
        if (editing.id === currentUser?.id) {
          await refresh();
        }
      } else {
        await createUser(apiBaseUrl, {
          name: values.name,
          email: values.email,
          role: values.role,
          password: values.password,
          password_confirmation: values.passwordConfirmation,
        });

        toast.success("Cuenta creada.");
      }

      setFormOpen(false);
      await load();
    } catch (error) {
      const fields = fieldErrorsOf(error);
      let mapped = false;

      for (const [key, message] of Object.entries(fields)) {
        const formField = BACKEND_TO_FORM_FIELD[key];
        if (formField) {
          form.setError(formField, { message });
          mapped = true;
        }
      }

      if (!mapped) {
        toast.error(
          error instanceof ApiError ? error.message : "No pudimos guardar la cuenta.",
        );
      }
    }
  }

  async function toggleActive(target: AdminUser) {
    setPendingId(target.id);

    try {
      await setUserActive(apiBaseUrl, target.id, !target.is_active);
      toast.success(target.is_active ? "Cuenta desactivada." : "Cuenta reactivada.");
      await load();
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : "No pudimos cambiar el estado de la cuenta.",
      );
    } finally {
      setPendingId(null);
    }
  }

  const submitting = form.formState.isSubmitting;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Usuarios</h1>
          <p className="text-sm text-muted-foreground">
            Los operadores gestionan pedidos. Solo el dueño accede a catálogo y configuración.
          </p>
        </div>

        <Button onClick={openCreate}>
          <PlusIcon />
          Nueva cuenta
        </Button>
      </div>

      {formOpen ? (
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit(onSubmit)}
            className="space-y-4 rounded-lg border p-4"
          >
            <h2 className="font-medium">
              {editing ? `Editar ${editing.name}` : "Nueva cuenta"}
            </h2>

            <div className="grid gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Nombre</FormLabel>
                    <FormControl>
                      <Input placeholder="Nombre y apellido" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="email"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Correo</FormLabel>
                    <FormControl>
                      <Input type="email" autoComplete="off" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="role"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Rol</FormLabel>
                    <Select
                      value={field.value}
                      onValueChange={(value) => field.onChange(value ?? "staff")}
                    >
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        <SelectItem value="staff">Operador</SelectItem>
                        <SelectItem value="owner">Dueño</SelectItem>
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="password"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>
                      {editing ? "Nueva contraseña (opcional)" : "Contraseña"}
                    </FormLabel>
                    <FormControl>
                      <Input type="password" autoComplete="new-password" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="passwordConfirmation"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Confirmar contraseña</FormLabel>
                    <FormControl>
                      <Input type="password" autoComplete="new-password" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="flex gap-2">
              <Button type="submit" disabled={submitting}>
                {submitting ? <Loader2Icon className="animate-spin" /> : null}
                {editing ? "Guardar cambios" : "Crear cuenta"}
              </Button>
              <Button type="button" variant="ghost" onClick={() => setFormOpen(false)}>
                Cancelar
              </Button>
            </div>
          </form>
        </Form>
      ) : null}

      {loadError ? (
        <p className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
          {loadError}
        </p>
      ) : null}

      {users === null && !loadError ? (
        <div className="space-y-2">
          <Skeleton className="h-12 w-full" />
          <Skeleton className="h-12 w-full" />
        </div>
      ) : null}

      {users !== null ? (
        <div className="overflow-x-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead className="border-b bg-muted/50 text-left">
              <tr>
                <th className="px-4 py-2 font-medium">Nombre</th>
                <th className="px-4 py-2 font-medium">Correo</th>
                <th className="px-4 py-2 font-medium">Rol</th>
                <th className="px-4 py-2 font-medium">Estado</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {users.map((row) => {
                const isSelf = row.id === currentUser?.id;

                return (
                  <tr key={row.id} className="border-b last:border-0">
                    <td className="px-4 py-3">
                      {row.name}
                      {isSelf ? (
                        <span className="ml-2 text-xs text-muted-foreground">(tú)</span>
                      ) : null}
                    </td>
                    <td className="px-4 py-3 text-muted-foreground">{row.email}</td>
                    <td className="px-4 py-3">
                      <Badge variant={row.role === "owner" ? "default" : "secondary"}>
                        {row.role === "owner" ? "Dueño" : "Operador"}
                      </Badge>
                    </td>
                    <td className="px-4 py-3">
                      <Badge variant={row.is_active ? "outline" : "destructive"}>
                        {row.is_active ? "Activa" : "Desactivada"}
                      </Badge>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-1">
                        <Button variant="ghost" size="sm" onClick={() => openEdit(row)}>
                          Editar
                        </Button>
                        <Button
                          variant={row.is_active ? "destructive" : "outline"}
                          size="sm"
                          // Deactivating yourself would end your own session on
                          // the spot; the backend refuses it with a 403 and the
                          // button says so before you get there.
                          disabled={isSelf || pendingId === row.id}
                          title={isSelf ? "No puedes desactivar tu propia cuenta." : undefined}
                          onClick={() => toggleActive(row)}
                        >
                          {pendingId === row.id ? (
                            <Loader2Icon className="animate-spin" />
                          ) : null}
                          {row.is_active ? "Desactivar" : "Reactivar"}
                        </Button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
