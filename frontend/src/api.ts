export type UserRole = "admin" | "vendedor" | "manutencao";
export type Session = { authenticated: boolean; username: string | null; role: UserRole | null; csrfToken: string };

let csrfToken = "";

export function setCsrfToken(token: string) {
  csrfToken = token;
}

export async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const method = (options.method ?? "GET").toUpperCase();
  const response = await fetch(path, {
    ...options,
    credentials: "same-origin",
    headers: {
      ...(options.body ? { "content-type": "application/json" } : {}),
      ...(!["GET", "HEAD"].includes(method) ? { "x-csrf-token": csrfToken } : {}),
      ...options.headers,
    },
  });
  const body = await response.json().catch(() => ({}));
  if (!response.ok) {
    if (response.status === 401) window.dispatchEvent(new Event("qr:unauthorized"));
    const error = new Error(body.error ?? "Não foi possível concluir a operação.") as Error & { status?: number };
    error.status = response.status;
    throw error;
  }
  return body as T;
}

export async function download(path: string, options: RequestInit = {}) {
  const method = (options.method ?? "GET").toUpperCase();
  const response = await fetch(path, {
    ...options,
    credentials: "same-origin",
    headers: {
      ...(options.body ? { "content-type": "application/json" } : {}),
      ...(!["GET", "HEAD"].includes(method) ? { "x-csrf-token": csrfToken } : {}),
      ...options.headers,
    },
  });
  if (!response.ok) {
    if (response.status === 401) window.dispatchEvent(new Event("qr:unauthorized"));
    const body = await response.json().catch(() => ({}));
    throw new Error(body.error ?? "Não foi possível gerar o arquivo.");
  }
  const blob = await response.blob();
  const disposition = response.headers.get("content-disposition") ?? "";
  const filename = disposition.match(/filename="?([^";]+)"?/)?.[1] ?? "qrcodes.zip";
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  URL.revokeObjectURL(url);
}
