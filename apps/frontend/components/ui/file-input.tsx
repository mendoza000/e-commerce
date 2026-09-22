import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * A plain native `<input type="file">` — no Base UI primitive needed here,
 * it's just styled to match Input/Textarea's look via the `file:` variant.
 */
function FileInput({ className, ...props }: Omit<React.ComponentProps<"input">, "type">) {
  return (
    <input
      type="file"
      data-slot="file-input"
      className={cn(
        "flex h-9 w-full min-w-0 cursor-pointer rounded-lg border border-input bg-transparent text-sm text-muted-foreground transition-colors outline-none file:mr-3 file:h-full file:cursor-pointer file:rounded-l-md file:border-0 file:border-r file:border-input file:bg-muted file:px-3 file:text-sm file:font-medium file:text-foreground hover:file:bg-muted/80 focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40",
        className
      )}
      {...props}
    />
  )
}

export { FileInput }
