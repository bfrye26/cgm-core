declare global {
  interface Window {
    cgmCoreBoot: {
      restPath: string;
      adminUrl: string;
      pluginVersion: string;
      nonce: string;
      caps: {
        manage: boolean;
        manageQueries: boolean;
        manageRelationships: boolean;
        manageConfig: boolean;
        inspectData: boolean;
      };
    };
  }
}

const boot = () => window.cgmCoreBoot;

export class ApiError extends Error {
  status: number;
  data: unknown;
  constructor(response: Response, data: unknown) {
    const msg = (data as { message?: string })?.message ?? `API error: ${response.status}`;
    super(msg);
    this.status = response.status;
    this.data = data;
  }
}

function base(): string {
  return (boot()?.restPath ?? '/wp-json/cgm-core/v1').replace(/\/$/, '');
}

export interface ApiOptions {
  method?: string;
  body?: unknown;
}

export async function api<T>(path: string, options: ApiOptions = {}): Promise<T> {
  const method = (options.method ?? 'GET').toUpperCase();
  const headers: Record<string, string> = {};
  const nonce = boot()?.nonce;
  if (nonce) headers['X-WP-Nonce'] = nonce;

  let body: BodyInit | undefined;
  if (method !== 'GET' && options.body !== undefined) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(options.body);
  }

  const url = `${base()}${path.startsWith('/') ? path : `/${path}`}`;
  const res = await fetch(url, { method, headers, body, credentials: 'same-origin' });
  const data = await res.json().catch(() => null);

  if (!res.ok) throw new ApiError(res, data);
  return data as T;
}

export function apiGet<T>(path: string): Promise<T> {
  return api<T>(path, { method: 'GET' });
}
export function apiPost<T>(path: string, body?: unknown): Promise<T> {
  return api<T>(path, { method: 'POST', body });
}

export async function apiDownload(path: string): Promise<Blob> {
  const headers: Record<string, string> = {};
  const nonce = boot()?.nonce;
  if (nonce) headers['X-WP-Nonce'] = nonce;
  const url = `${base()}${path.startsWith('/') ? path : `/${path}`}`;
  const res = await fetch(url, { headers, credentials: 'same-origin' });
  if (!res.ok) throw new ApiError(res, null);
  return res.blob();
}

export function caps() {
  return (
    boot()?.caps ?? {
      manage: false,
      manageQueries: false,
      manageRelationships: false,
      manageConfig: false,
      inspectData: false,
    }
  );
}
export function pluginVersion(): string {
  return boot()?.pluginVersion ?? '';
}
