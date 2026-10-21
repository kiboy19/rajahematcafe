export type ValidationErrors = Record<string, string[]>;

export type ApiResult<T = unknown> =
    | { ok: true; data?: T; message?: string; raw?: Record<string, unknown> }
    | { ok: false; message: string; errors: ValidationErrors };

export function csrfToken(): string {
    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
    return meta?.content ?? '';
}

export function firstError(errors: ValidationErrors, field: string): string | undefined {
    return errors[field]?.[0];
}

export function allErrors(errors: ValidationErrors): string[] {
    return Object.values(errors).flat();
}

export async function apiRequest<T = unknown>(
    method: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE',
    url: string,
    body?: unknown
): Promise<ApiResult<T>> {
    let response: Response;

    try {
        response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: body === undefined ? undefined : body instanceof FormData ? body : JSON.stringify(body),
        });
    } catch {
        return { ok: false, message: 'Koneksi gagal. Periksa jaringan Anda.', errors: {} };
    }

    const isJson = (response.headers.get('content-type') ?? '').includes('application/json');
    const payload = isJson ? await response.json().catch(() => null) : null;

    if (response.ok) {
        return { ok: true, data: payload?.data as T | undefined, message: payload?.message, raw: payload as Record<string, unknown> | undefined };
    }

    return {
        ok: false,
        message: payload?.message ?? 'Terjadi kesalahan. Silakan coba lagi.',
        errors: (payload?.errors as ValidationErrors) ?? {},
    };
}