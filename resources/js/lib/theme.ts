export const theme = {
  button: {
    defaultSize: "default" as const,
    iconSize: "icon" as const,
  },
  table: {
    defaultPerPage: 10,
    perPageOptions: [10, 20, 50, 100],
  },
  toast: {
    position: "top-center" as const,
    duration: 4000,
  },
  animation: {
    duration: 200,
  },
  dateFormat: {
    display: "MMM dd, yyyy",
    displayTime: "MMM dd, yyyy HH:mm",
    input: "yyyy-MM-dd",
    inputTime: "yyyy-MM-dd HH:mm",
  },
} as const;

export type Theme = typeof theme;

// Expose an easy way to customize the dashboard theme dynamically without editing app.css
export const customThemeColors = {
  // Primary brand color (HSL format: "H S% L%")
  primary: "oklch(0.59 0.16 241.96)",

  // Sidebar Colors
  // sidebarBackground: 'oklch(0.205 0 0)',
  // sidebarForeground: 'oklch(0.985 0 0)',
  // sidebarPrimary: 'oklch(0.985 0 0)',
  // sidebarPrimaryForeground: 'oklch(0.985 0 0)',
  // sidebarAccent: 'var(--primary)',
  // sidebarAccentForeground: 'oklch(0.985 0 0)',
  // sidebarBorder: 'oklch(0.269 0 0)',
  // sidebarRing: 'oklch(0.439 0 0)',
};

export function applyCustomTheme(colors = customThemeColors) {
  if (typeof document === "undefined") return;
  const root = document.documentElement;

  if (colors.primary) {
    root.style.setProperty("--primary", colors.primary);
    // Also update the ring focus color to match primary by default if it's customized
    root.style.setProperty("--ring", colors.primary);
  }

  if (colors.sidebarBackground)
    root.style.setProperty("--sidebar", colors.sidebarBackground);
  if (colors.sidebarForeground)
    root.style.setProperty("--sidebar-foreground", colors.sidebarForeground);
  if (colors.sidebarPrimary)
    root.style.setProperty("--sidebar-primary", colors.sidebarPrimary);
  if (colors.sidebarPrimaryForeground)
    root.style.setProperty(
      "--sidebar-primary-foreground",
      colors.sidebarPrimaryForeground,
    );
  if (colors.sidebarAccent)
    root.style.setProperty("--sidebar-accent", colors.sidebarAccent);
  if (colors.sidebarAccentForeground)
    root.style.setProperty(
      "--sidebar-accent-foreground",
      colors.sidebarAccentForeground,
    );
  if (colors.sidebarBorder)
    root.style.setProperty("--sidebar-border", colors.sidebarBorder);
  if (colors.sidebarRing)
    root.style.setProperty("--sidebar-ring", colors.sidebarRing);
}
