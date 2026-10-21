import { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { apiRequest, type ValidationErrors } from '@/react/lib/api';
import { Button, ErrorBox, Field, inputClass } from '../ui';

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
            return;
        }

        const redirect = new URLSearchParams(location.search).get('redirect');
        const serverRedirect = typeof result.raw?.redirect === 'string' ? result.raw.redirect : null;
        window.location.assign(redirect || (customer ? '/akun' : serverRedirect || '/dashboard'));
    };

    return (
        <div className="min-h-screen bg-bg flex items-center justify-center px-4 py-10">
            <div className="w-full max-w-sm">
                <div className="text-center mb-6">
                    <span className="w-12 h-12 rounded-full bg-accent text-ink-on-dark inline-flex items-center justify-center font-display font-bold text-lg">R</span>
                    <h1 className="font-display text-2xl font-bold mt-3">{customer ? 'Masuk Pelanggan' : 'Masuk Staf'}</h1>
                    <p className="text-sm text-ink-2 mt-1">
                        {customer ? 'Masuk untuk memesan dan melihat status pesanan.' : 'Gunakan akun yang diberikan manager.'}
                    </p>
                </div>

                <form onSubmit={submit} className="bg-surface border border-line rounded-card shadow-1 p-5">
                    {error && <ErrorBox message={error} />}

                    <Field label={customer ? 'Email atau Nomor HP' : 'Email'} className="mt-1">
                        <input
                            className={inputClass}
                            autoComplete={customer ? 'username' : 'email'}
                            value={identifier}
                            onChange={(event) => setIdentifier(event.target.value)}
                            required
                            autoFocus
                        />
                    </Field>

                    <Field label="Kata Sandi" className="mt-4">
                        <input
                            type="password"
                            className={inputClass}
                            autoComplete="current-password"
                            value={password}
                            onChange={(event) => setPassword(event.target.value)}
                            required
                        />
                    </Field>

                    <label className="mt-4 flex items-center gap-2 text-xs text-ink-2">
                        <input type="checkbox" checked={remember} onChange={(event) => setRemember(event.target.checked)} />
                        Ingat saya
                    </label>

                    <Button type="submit" className="w-full mt-5" disabled={submitting}>
                        {submitting ? 'Memeriksa...' : 'Masuk'}
                    </Button>
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
        </div>
    );
}

export function ForgotPasswordPage() {
    const [email, setEmail] = useState('');
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const submit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        const result = await apiRequest('POST', '/lupa-sandi', { email });
        if (!result.ok) {
            setError(result.message);
            return;
        }
        setMessage(result.message ?? 'Jika akun ditemukan, tautan reset telah dikirim.');
    };

    return <AuthCard title="Lupa Kata Sandi" subtitle="Masukkan email akun pelanggan Anda.">
        {error && <ErrorBox message={error} />}
        {message && <p className="rounded-card bg-success/10 text-success p-3 text-sm">{message}</p>}
        <form onSubmit={submit} className="mt-4 space-y-4">
            <Field label="Email"><input className={inputClass} type="email" value={email} onChange={(event) => setEmail(event.target.value)} required /></Field>
            <Button type="submit" className="w-full">Kirim Tautan Reset</Button>
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

    const submit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        const result = await apiRequest('POST', '/atur-ulang-sandi', { ...form, token });
        if (!result.ok) {
            setError(result.message);
            return;
        }
        window.location.assign('/masuk');
    };

    return <AuthCard title="Atur Ulang Kata Sandi" subtitle="Buat kata sandi baru untuk akun Anda.">
        {error && <ErrorBox message={error} />}
        <form onSubmit={submit} className="mt-4 space-y-4">
            <Field label="Email"><input className={inputClass} type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} required /></Field>
            <Field label="Kata Sandi Baru"><input className={inputClass} type="password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} required /></Field>
            <Field label="Ulangi Kata Sandi"><input className={inputClass} type="password" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} required /></Field>
            <Button type="submit" className="w-full">Simpan Kata Sandi</Button>
        </form>
    </AuthCard>;
}

export function VerifyEmailPage() {
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const resend = async () => {
        setError(null);
        const result = await apiRequest('POST', '/verifikasi-email/kirim-ulang');
        if (!result.ok) {
            setError(result.message);
            return;
        }
        setMessage(result.message ?? 'Tautan verifikasi telah dikirim.');
    };

    return <AuthCard title="Verifikasi Email" subtitle="Verifikasi email Anda untuk menjaga keamanan akun.">
        {error && <ErrorBox message={error} />}
        {message && <p className="rounded-card bg-success/10 text-success p-3 text-sm">{message}</p>}
        <Button className="w-full mt-4" onClick={resend}>Kirim Ulang Tautan</Button>
        <Link to="/akun" className="block mt-4 text-center text-sm text-primary font-semibold hover:underline">Kembali ke akun</Link>
    </AuthCard>;
}

function AuthCard({ title, subtitle, children }: { title: string; subtitle: string; children: React.ReactNode }) {
    return <div className="min-h-screen bg-bg flex items-center justify-center px-4 py-10"><div className="w-full max-w-md"><div className="text-center mb-6"><span className="w-12 h-12 rounded-full bg-accent text-ink-on-dark inline-flex items-center justify-center font-display font-bold text-lg">R</span><h1 className="font-display text-2xl font-bold mt-3">{title}</h1><p className="text-sm text-ink-2 mt-1">{subtitle}</p></div><div className="bg-surface border border-line rounded-card shadow-1 p-5">{children}</div></div></div>;
}

export function RegisterPage() {
    const [form, setForm] = useState({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
    const [error, setError] = useState<string | null>(null);
    const [fieldErrors, setFieldErrors] = useState<ValidationErrors>({});
    const [submitting, setSubmitting] = useState(false);

    const submit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setSubmitting(true);
        setError(null);
        setFieldErrors({});
        const result = await apiRequest('POST', '/daftar', form);
        setSubmitting(false);
        if (!result.ok) {
            setError(result.message);
            setFieldErrors(result.errors ?? {});
            return;
        }
        const intended = (result.raw?.intended as string) || '/akun';
        window.location.assign(intended);
    };

    const fieldError = (key: keyof typeof form) => fieldErrors[key]?.[0];

    return (
        <div className="min-h-screen bg-bg flex items-center justify-center px-4 py-10">
            <form onSubmit={submit} className="w-full max-w-md bg-surface border border-line rounded-card shadow-1 p-6">
                <h1 className="font-display text-2xl font-bold">Daftar Pelanggan</h1>
                <p className="mt-1 text-sm text-ink-2">Buat akun untuk memesan melalui QR meja.</p>
                {error && <div className="mt-4"><ErrorBox message={error} /></div>}
                <div className="mt-5 grid gap-4">
                    {([['name', 'Nama'], ['email', 'Email'], ['phone', 'Nomor HP']] as const).map(([key, label]) => (
                        <Field key={key} label={label}>
                            <input
                                className={inputClass}
                                type={key === 'email' ? 'email' : 'text'}
                                value={form[key]}
                                onChange={(event) => setForm({ ...form, [key]: event.target.value })}
                                required
                            />
                            {fieldError(key) && <p className="mt-1 text-[11px] text-bad">{fieldError(key)}</p>}
                        </Field>
                    ))}
                    <Field label="Kata Sandi">
                        <input className={inputClass} type="password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} required />
                        {fieldError('password') && <p className="mt-1 text-[11px] text-bad">{fieldError('password')}</p>}
                    </Field>
                    <Field label="Ulangi Kata Sandi">
                        <input className={inputClass} type="password" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} required />
                        {fieldError('password_confirmation') && <p className="mt-1 text-[11px] text-bad">{fieldError('password_confirmation')}</p>}
                    </Field>
                </div>
                <Button type="submit" className="w-full mt-5" disabled={submitting}>{submitting ? 'Membuat akun...' : 'Buat Akun'}</Button>
                <Link to="/masuk" className="block mt-4 text-center text-sm text-primary font-semibold hover:underline">Sudah punya akun? Masuk</Link>
            </form>
        </div>
    );
}