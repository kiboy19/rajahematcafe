import * as React from 'react';
import { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { apiRequest, type ValidationErrors } from '@/react/lib/api';
import { Button, Card, ErrorBox, FormField, inputClass } from '../ui-primitives';

export function StaffLoginPage() {
    return <LoginForm customer={false} />;
}

export function CustomerLoginPage() {
    return <LoginForm customer />;
}

function LoginForm({ customer }: { customer: boolean }) {
    const location = useLocation();
    const [identifier, setIdentifier] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const identifierRef = React.useRef<HTMLInputElement>(null);

    const submit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        const result = await apiRequest(
            'POST',
            customer ? '/masuk' : '/login',
            customer
                ? { identifier, password, remember }
                : { email: identifier, password, remember }
        );

        setSubmitting(false);

        if (!result.ok) {
            setError(result.message);
            identifierRef.current?.focus();
            return;
        }

        const redirect = new URLSearchParams(location.search).get('redirect');
        const serverRedirect = typeof result.raw?.redirect === 'string' ? result.raw.redirect : null;
        window.location.assign(redirect || (customer ? '/akun' : serverRedirect || '/dashboard'));
    };

    return (
        <main className="min-h-screen bg-bg flex items-center justify-center px-4 py-10">
            <div className="w-full max-w-sm motion-fade-up">
                <div className="text-center mb-6">
                    <img src="/images/logo.svg" alt="Rajahemat Cafe" className="inline-block h-12 w-12" />
                    <h1 className="font-display text-2xl font-bold mt-3">{customer ? 'Masuk Pelanggan' : 'Masuk Staf'}</h1>
                    <p className="text-sm text-ink-2 mt-1">
                        {customer ? 'Masuk untuk memesan dan melihat status pesanan.' : 'Gunakan akun yang diberikan manager.'}
                    </p>
                </div>

                <form onSubmit={submit} noValidate>
                    <Card className="p-5">
                        {error && <ErrorBox message={error} />}

                        <FormField
                            label={customer ? 'Email atau Nomor HP' : 'Email'}
                            htmlFor={customer ? 'identifier' : 'email'}
                            className="mt-1"
                        >
                            <input
                                ref={identifierRef}
                                id={customer ? 'identifier' : 'email'}
                                name={customer ? 'identifier' : 'email'}
                                type={customer ? 'text' : 'email'}
                                inputMode={customer ? 'email' : undefined}
                                className={inputClass}
                                autoComplete="username"
                                spellCheck={false}
                                value={identifier}
                                onChange={(event) => setIdentifier(event.target.value)}
                                aria-invalid={error ? true : undefined}
                                required
                                autoFocus
                            />
                        </FormField>

                        <FormField label="Kata Sandi" htmlFor="password" className="mt-4">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                className={inputClass}
                                autoComplete="current-password"
                                value={password}
                                onChange={(event) => setPassword(event.target.value)}
                                aria-invalid={error ? true : undefined}
                                required
                            />
                        </FormField>

                        <label htmlFor="remember" className="mt-4 flex items-center gap-2 text-xs text-ink-2">
                            <input id="remember" name="remember" type="checkbox" checked={remember} onChange={(event) => setRemember(event.target.checked)} />
                            Ingat saya
                        </label>

                        <Button type="submit" className="w-full mt-5" pending={submitting}>
                            Masuk
                        </Button>
                    </Card>
                </form>

                {customer && (
                    <div className="mt-4 text-center text-sm text-ink-2 space-y-1">
                        <div>
                            <Link to="/lupa-sandi" className="text-primary hover:underline">Lupa kata sandi?</Link>
                        </div>
                        <div>
                            Belum punya akun? <Link to="/daftar" className="font-semibold text-primary hover:underline">Daftar sekarang</Link>
                        </div>
                    </div>
                )}
            </div>
        </main>
    );
}

export function ForgotPasswordPage() {
    const [email, setEmail] = useState('');
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const emailRef = React.useRef<HTMLInputElement>(null);

    const submit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setSubmitting(true);
        setError(null);
        const result = await apiRequest('POST', '/lupa-sandi', { email });
        if (!result.ok) {
            setError(result.message);
            emailRef.current?.focus();
            return;
        }
        setMessage(result.message ?? 'Jika akun ditemukan, tautan reset telah dikirim.');
    };

    return <AuthCard title="Lupa Kata Sandi" subtitle="Masukkan email akun pelanggan Anda.">
        {error && <ErrorBox message={error} />}
        {message && <p role="status" className="rounded-card bg-ok-bg text-ok p-3 text-sm">{message}</p>}
        <form onSubmit={submit} noValidate className="mt-4 space-y-4">
            <FormField label="Email" htmlFor="email">
                <input
                    ref={emailRef}
                    id="email"
                    name="email"
                    type="email"
                    inputMode="email"
                    className={inputClass}
                    autoComplete="email"
                    spellCheck={false}
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                    required
                />
            </FormField>
            <Button type="submit" className="w-full" pending={submitting}>Kirim Tautan Reset</Button>
        </form>
        <Link to="/masuk" className="block mt-4 text-center text-sm text-primary font-semibold hover:underline">Kembali ke masuk</Link>
    </AuthCard>;
}

export function ResetPasswordPage() {
    const location = useLocation();
    const token = location.pathname.split('/').pop() ?? '';
    const params = new URLSearchParams(location.search);
    const [form, setForm] = useState({ email: params.get('email') ?? '', password: '', password_confirmation: '' });
    const [error, setError] = useState<string | null>(null);
    const [fieldErrors, setFieldErrors] = useState<ValidationErrors>({});
    const emailRef = React.useRef<HTMLInputElement>(null);

    const submit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setFieldErrors({});
        const result = await apiRequest('POST', '/atur-ulang-sandi', { ...form, token });
        if (!result.ok) {
            setError(result.message);
            setFieldErrors(result.errors ?? {});
            focusFirstError(result.errors ?? {}, emailRef);
            return;
        }
        window.location.assign('/masuk');
    };

    const errorFor = (key: 'email' | 'password' | 'password_confirmation') => fieldErrors[key]?.[0];

    return <AuthCard title="Atur Ulang Kata Sandi" subtitle="Buat kata sandi baru untuk akun Anda.">
        {error && <ErrorBox message={error} />}
        <form onSubmit={submit} noValidate className="mt-4 space-y-4">
            <FormField label="Email" htmlFor="email" error={errorFor('email')} errorId="email-error">
                <input
                    id="email"
                    name="email"
                    type="email"
                    inputMode="email"
                    className={inputClass}
                    autoComplete="email"
                    spellCheck={false}
                    value={form.email}
                    onChange={(event) => setForm({ ...form, email: event.target.value })}
                    aria-invalid={errorFor('email') ? true : undefined}
                    aria-describedby={errorFor('email') ? 'email-error' : undefined}
                    ref={emailRef}
                    required
                />
            </FormField>
            <FormField label="Kata Sandi Baru" htmlFor="password" hint="Minimal 8 karakter." error={errorFor('password')} errorId="password-error">
                <input
                    id="password"
                    name="password"
                    type="password"
                    className={inputClass}
                    autoComplete="new-password"
                    value={form.password}
                    onChange={(event) => setForm({ ...form, password: event.target.value })}
                    aria-invalid={errorFor('password') ? true : undefined}
                    aria-describedby={errorFor('password') ? 'password-error' : undefined}
                    required
                />
            </FormField>
            <FormField label="Ulangi Kata Sandi" htmlFor="password_confirmation" error={errorFor('password_confirmation')} errorId="password_confirmation-error">
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    className={inputClass}
                    autoComplete="new-password"
                    value={form.password_confirmation}
                    onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })}
                    aria-invalid={errorFor('password_confirmation') ? true : undefined}
                    aria-describedby={errorFor('password_confirmation') ? 'password_confirmation-error' : undefined}
                    required
                />
            </FormField>
            <Button type="submit" className="w-full">Simpan Kata Sandi</Button>
        </form>
    </AuthCard>;
}

export function VerifyEmailPage() {
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const resend = async () => {
        setSubmitting(true);
        setError(null);
        const result = await apiRequest('POST', '/verifikasi-email/kirim-ulang');
        setSubmitting(false);
        if (!result.ok) {
            setError(result.message);
            return;
        }
        setMessage(result.message ?? 'Tautan verifikasi telah dikirim.');
    };

    return <AuthCard title="Verifikasi Email" subtitle="Verifikasi email Anda untuk menjaga keamanan akun.">
        {error && <ErrorBox message={error} />}
        {message && <p role="status" className="rounded-card bg-ok-bg text-ok p-3 text-sm">{message}</p>}
        <Button className="w-full mt-4" onClick={resend} pending={submitting}>Kirim Ulang Tautan</Button>
        <Link to="/akun" className="block mt-4 text-center text-sm text-primary font-semibold hover:underline">Kembali ke akun</Link>
    </AuthCard>;
}

/** Fokus ke field pertama yang gagal validasi; aman dipanggil tanpa error. */
function focusFirstError(
    errors: ValidationErrors,
    fallbackRef: React.RefObject<HTMLInputElement | null>
) {
    const first = Object.keys(errors)[0];
    if (!first) {
        fallbackRef.current?.focus();
        return;
    }
    requestAnimationFrame(() => {
        const element = document.getElementById(first);
        if (element instanceof HTMLElement) {
            element.focus();
            return;
        }
        fallbackRef.current?.focus();
    });
}

function PasswordStrength({ password }: { password: string }) {
    let score = 0;
    if (password.length >= 8) score++;
    if (/[A-Z]/.test(password) && /[a-z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    const levels = [
        { label: 'Sangat Lemah', color: 'bg-bad text-bad', width: 'w-1/4' },
        { label: 'Lemah', color: 'bg-warn text-warn', width: 'w-2/4' },
        { label: 'Cukup', color: 'bg-warn text-warn', width: 'w-3/4' },
        { label: 'Kuat', color: 'bg-ok text-ok', width: 'w-full' },
    ];

    const current = levels[Math.min(score, levels.length - 1)];

    return (
        <div id="password-strength" className="mt-2 flex items-center gap-2" aria-live="polite">
            <div className="flex-1 h-1.5 rounded-full bg-surface-2 overflow-hidden" role="progressbar" aria-valuenow={score} aria-valuemin={0} aria-valuemax={4} aria-label="Kekuatan kata sandi">
                <div className={`h-full ${current.color.split(' ')[0]} ${current.width} transition-all duration-300`} />
            </div>
            <span className={`text-xs font-semibold ${current.color.split(' ')[1]}`}>{current.label}</span>
        </div>
    );
}

function AuthCard({ title, subtitle, children }: { title: string; subtitle: string; children: React.ReactNode }) {
    return (
        <main className="min-h-screen bg-bg flex items-center justify-center px-4 py-10">
            <div className="w-full max-w-md motion-fade-up">
                <div className="text-center mb-6">
                    <img src="/images/logo.svg" alt="Rajahemat Cafe" className="inline-block h-12 w-12" />
                    <h1 className="font-display text-2xl font-bold mt-3">{title}</h1>
                    <p className="text-sm text-ink-2 mt-1">{subtitle}</p>
                </div>
                <Card className="p-5">{children}</Card>
            </div>
        </main>
    );
}

export function RegisterPage() {
    const [form, setForm] = useState({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
    const [error, setError] = useState<string | null>(null);
    const [fieldErrors, setFieldErrors] = useState<ValidationErrors>({});
    const [submitting, setSubmitting] = useState(false);
    const nameRef = React.useRef<HTMLInputElement>(null);

    const submit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setSubmitting(true);
        setError(null);
        setFieldErrors({});
        const result = await apiRequest('POST', '/daftar', form);
        setSubmitting(false);
        if (!result.ok) {
            setError(result.message);
            const errors = result.errors ?? {};
            setFieldErrors(errors);
            focusFirstError(errors, nameRef);
            return;
        }
        const intended = (result.raw?.intended as string) || '/akun';
        window.location.assign(intended);
    };

    const fieldError = (key: keyof typeof form) => fieldErrors[key]?.[0];

    const fieldMeta = {
        name: { label: 'Nama', type: 'text', autoComplete: 'name' },
        email: { label: 'Email', type: 'email', autoComplete: 'email' },
        phone: { label: 'Nomor HP', type: 'tel', autoComplete: 'tel' },
    } as const;

    return (
        <main className="min-h-screen bg-bg flex items-center justify-center px-4 py-10">
            <form onSubmit={submit} noValidate className="w-full max-w-md motion-fade-up">
                <Card className="p-6">
                    <h1 className="font-display text-2xl font-bold">Daftar Pelanggan</h1>
                    <p className="mt-1 text-sm text-ink-2">Buat akun untuk memesan melalui QR meja.</p>
                    {error && <div className="mt-4"><ErrorBox message={error} /></div>}
                    <div className="mt-5 grid gap-4">
                        {(['name', 'email', 'phone'] as const).map((key) => (
                            <FormField
                                key={key}
                                label={fieldMeta[key].label}
                                htmlFor={key}
                                error={fieldError(key)}
                                errorId={`${key}-error`}
                            >
                                <input
                                    ref={key === 'name' ? nameRef : undefined}
                                    id={key}
                                    name={key}
                                    type={fieldMeta[key].type}
                                    inputMode={key === 'phone' ? 'tel' : key === 'email' ? 'email' : undefined}
                                    className={inputClass}
                                    autoComplete={fieldMeta[key].autoComplete}
                                    spellCheck={key === 'name' ? undefined : false}
                                    value={form[key]}
                                    onChange={(event) => setForm({ ...form, [key]: event.target.value })}
                                    aria-invalid={fieldError(key) ? true : undefined}
                                    aria-describedby={fieldError(key) ? `${key}-error` : undefined}
                                    required
                                />
                            </FormField>
                        ))}
                        <FormField label="Kata Sandi" htmlFor="password" hint="Minimal 8 karakter." error={fieldError('password')} errorId="password-error">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                className={inputClass}
                                autoComplete="new-password"
                                value={form.password}
                                onChange={(event) => setForm({ ...form, password: event.target.value })}
                                aria-invalid={fieldError('password') ? true : undefined}
                                aria-describedby={fieldError('password') ? 'password-error' : 'password-strength'}
                                required
                            />
                            {form.password && <PasswordStrength password={form.password} />}
                        </FormField>
                        <FormField label="Ulangi Kata Sandi" htmlFor="password_confirmation" error={fieldError('password_confirmation')} errorId="password_confirmation-error">
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                className={inputClass}
                                autoComplete="new-password"
                                value={form.password_confirmation}
                                onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })}
                                aria-invalid={fieldError('password_confirmation') ? true : undefined}
                                aria-describedby={fieldError('password_confirmation') ? 'password_confirmation-error' : undefined}
                                required
                            />
                        </FormField>
                    </div>
                    <Button type="submit" className="w-full mt-5" pending={submitting}>Buat Akun</Button>
                </Card>
                <Link to="/masuk" className="block mt-4 text-center text-sm text-primary font-semibold hover:underline">Sudah punya akun? Masuk</Link>
            </form>
        </main>
    );
}
