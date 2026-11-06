import { forceRefreshToken, getAccessToken } from "./keycloak";

export class ApiError extends Error {
  status: number;
  body: {
    error?: string;
    message?: string;
    fields?: Record<string, string>;
  } | null;

  constructor(
    status: number,
    body: ApiError["body"],
  ) {
    super(body?.message || `Request failed (${status})`);
    this.name = "ApiError";
    this.status = status;
    this.body = body;
  }
}

export function errorText(error: unknown): string {
  if (error instanceof ApiError) {
    const fields = error.body?.fields ? Object.values(error.body.fields).join(" ") : "";
    return [error.message, fields].filter(Boolean).join(" — ");
  }
  if (error instanceof Error) return error.message;
  return "Something went wrong";
}

export async function api<T>(path: string, options: RequestInit = {}, retry = true): Promise<T> {
  const token = await getAccessToken();
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  headers.set("Authorization", `Bearer ${token}`);
  if (options.body && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  const base = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8080";
  const response = await fetch(`${base}${path}`, { ...options, headers });

  if (response.status === 401 && retry) {
    await forceRefreshToken();
    return api<T>(path, options, false);
  }

  const text = await response.text();
  let data: ApiError["body"] = null;
  if (text) {
    try {
      data = JSON.parse(text) as ApiError["body"];
    } catch {
      data = { message: text };
    }
  }

  if (!response.ok) {
    throw new ApiError(response.status, data);
  }

  return data as T;
}
