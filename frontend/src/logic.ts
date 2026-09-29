export function normalizeCode(value: string) {
  const trimmed = value.trim().toUpperCase();
  if (/^\d+$/.test(trimmed)) {
    const normalized = trimmed.replace(/^0+(?=\d)/, "");
    return normalized === "0" ? null : normalized.padStart(4, "0");
  }
  return /^[A-Z0-9]{3}-[A-Z0-9]{4}$/.test(trimmed) ? trimmed : null;
}

export function safeNextPath(value: string | null) {
  return value?.startsWith("/") && !value.startsWith("//") ? value : "/";
}
