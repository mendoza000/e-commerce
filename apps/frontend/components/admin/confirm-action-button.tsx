"use client";

import { useState } from "react";
import type { VariantProps } from "class-variance-authority";
import { Loader2Icon } from "lucide-react";
import { Button, type buttonVariants } from "@/components/ui/button";
import {
  AlertDialog,
  AlertDialogClose,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";

type ButtonVariant = VariantProps<typeof buttonVariants>["variant"];

interface ConfirmActionButtonProps {
  trigger: {
    label: React.ReactNode;
    variant?: ButtonVariant;
    disabled?: boolean;
    title?: string;
  };
  title: string;
  description?: React.ReactNode;
  confirmLabel: string;
  cancelLabel?: string;
  destructive?: boolean;
  /**
   * Must throw on failure — this component keeps the dialog open by relying
   * on that rejection, and never toasts on its own. Toast at the call site
   * (same catch-toast pattern as users-manager.tsx / order-detail.tsx), then
   * rethrow so this component knows the action didn't succeed.
   */
  onConfirm: () => Promise<void>;
}

/**
 * Generic confirm-then-act button: a trigger, an alert dialog, a safe cancel
 * and a confirm button that shows a spinner while in flight. Reusable as-is
 * for option/value/variant/image deletes in later milestones.
 */
export function ConfirmActionButton({
  trigger,
  title,
  description,
  confirmLabel,
  cancelLabel = "Cancelar",
  destructive = false,
  onConfirm,
}: ConfirmActionButtonProps) {
  const [open, setOpen] = useState(false);
  const [pending, setPending] = useState(false);

  async function handleConfirm() {
    setPending(true);
    try {
      await onConfirm();
      setOpen(false);
    } finally {
      setPending(false);
    }
  }

  return (
    <AlertDialog open={open} onOpenChange={setOpen}>
      <AlertDialogTrigger
        render={
          <Button
            variant={trigger.variant ?? "outline"}
            size="sm"
            disabled={trigger.disabled}
            title={trigger.title}
          />
        }
      >
        {trigger.label}
      </AlertDialogTrigger>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{title}</AlertDialogTitle>
          {description ? <AlertDialogDescription>{description}</AlertDialogDescription> : null}
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogClose render={<Button type="button" variant="ghost" disabled={pending} />}>
            {cancelLabel}
          </AlertDialogClose>
          <Button
            type="button"
            variant={destructive ? "destructive" : "default"}
            disabled={pending}
            onClick={handleConfirm}
          >
            {pending ? <Loader2Icon className="animate-spin" /> : null}
            {confirmLabel}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
