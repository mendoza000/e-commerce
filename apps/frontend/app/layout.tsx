import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import { CurrencyProvider } from "@/components/providers/currency-provider";
import { CartHydration } from "@/components/providers/cart-hydration";
import { getCurrencies } from "@/lib/api/currencies";
import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: "Ecommerce Template",
  description: "Tienda online multimoneda para Venezuela",
};

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  const currencies = await getCurrencies().catch(() => []);

  return (
    <html
      lang="es"
      className={`${geistSans.variable} ${geistMono.variable} h-full antialiased`}
    >
      <body className="min-h-full flex flex-col">
        <CurrencyProvider currencies={currencies}>{children}</CurrencyProvider>
        <CartHydration />
      </body>
    </html>
  );
}
