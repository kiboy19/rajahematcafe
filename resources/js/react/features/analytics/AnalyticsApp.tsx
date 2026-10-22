import * as React from 'react';
import { BarChart3, Loader2, Users, Eye, Monitor, Smartphone, Tablet } from 'lucide-react';
import { apiRequest } from '@/react/lib/api';
import { Button } from '../../spa/ui-primitives';

interface AnalyticsPayload {
    range_days: number;
    totals: { views: number; unique_visitors: number; views_per_visitor: number };
    devices: Array<{ device: string; label: string; total: number }>;
    top_pages: Array<{ path: string; views: number; unique_visitors: number }>;
    top_referers: Array<{ referer: string; views: number }>;
    series: Array<{ date: string; views: number; unique: number }>;
}

const DEVICE_ICONS = { mobile: Smartphone, tablet: Tablet, desktop: Monitor } as const;

export function AnalyticsApp({ initialData }: { initialData?: AnalyticsPayload }) {
    const [range, setRange] = React.useState(7);
    const [data, setData] = React.useState<AnalyticsPayload | null>(initialData ?? null);
    const [loading, setLoading] = React.useState(!initialData);
    const [error, setError] = React.useState<string | null>(null);

    async function load(nextRange: number): Promise<void> {
        setLoading(true);
        setError(null);
        const result = await apiRequest<{ data: AnalyticsPayload }>('GET', `/api/analitik-web?range=${nextRange}`);
        setLoading(false);

        if (result.ok && result.raw?.data) {
            setData(result.raw.data as AnalyticsPayload);
        } else if (!result.ok) {
            setError(result.message);
        }
    }

    React.useEffect(() => {
        void load(range);
    }, [range]);

    const maximum = Math.max(...(data?.series ?? []).map((point) => point.views), 1);

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wider text-primary">Website</p>
                    <h1 className="font-display text-2xl font-bold">Analitik Website</h1>
                </div>
                <div className="flex items-center gap-2">
                    {[7, 30, 90].map((days) => (
                        <Button key={days} variant={range === days ? 'default' : 'outline'} size="sm" onClick={() => setRange(days)}>
                            {days} hari
                        </Button>
                    ))}
                </div>
            </div>

            {error && <div role="alert" className="rounded-input border border-bad/30 bg-bad-bg p-3 text-sm text-bad">{error}</div>}
            {loading && !data ? <div className="flex items-center justify-center py-20 text-sm text-ink-2"><Loader2 className="mr-2 h-5 w-5 animate-spin" /> Memuat analitik…</div> : data && <>
                <div className="grid gap-4 sm:grid-cols-3">
                    <MetricCard icon={Eye} label="Total kunjungan" value={data.totals.views} />
                    <MetricCard icon={Users} label="Pengunjung unik" value={data.totals.unique_visitors} />
                    <MetricCard icon={BarChart3} label="Kunjungan / pengunjung" value={data.totals.views_per_visitor} />
                </div>

                <div className="grid gap-5 lg:grid-cols-3">
                    <section className="rounded-card border border-line bg-surface p-5 shadow-1 lg:col-span-2">
                        <h2 className="font-display text-lg font-bold">Tren Kunjungan</h2>
                        <div className="mt-6 flex h-52 items-end gap-1 border-b border-line px-1">
                            {data.series.map((point) => <div key={point.date} className="group flex h-full flex-1 items-end" title={`${point.date}: ${point.views} kunjungan`}><div className="w-full rounded-t bg-primary/80 transition-all group-hover:bg-primary" style={{ height: `${Math.max((point.views / maximum) * 100, point.views ? 4 : 1)}%` }} /></div>)}
                        </div>
                        <div className="mt-2 flex justify-between text-[10px] text-ink-2"><span>{data.series[0]?.date}</span><span>{data.series.at(-1)?.date}</span></div>
                    </section>
                    <section className="rounded-card border border-line bg-surface p-5 shadow-1">
                        <h2 className="font-display text-lg font-bold">Perangkat</h2>
                        <div className="mt-5 flex flex-col gap-4">{data.devices.map((device) => { const Icon = DEVICE_ICONS[device.device as keyof typeof DEVICE_ICONS] ?? Monitor; return <div key={device.device} className="flex items-center gap-3"><Icon className="h-4 w-4 text-primary" /><span className="flex-1 text-sm">{device.label}</span><span className="tabular text-sm font-semibold">{device.total}</span></div>; })}</div>
                    </section>
                </div>

                <section className="rounded-card border border-line bg-surface p-5 shadow-1">
                    <h2 className="font-display text-lg font-bold">Halaman Terpopuler</h2>
                    <div className="mt-4 flex flex-col divide-y divide-line">{data.top_pages.map((page) => <div key={page.path} className="flex items-center gap-3 py-3 text-sm"><span className="min-w-0 flex-1 truncate font-medium">{page.path}</span><span className="tabular text-ink-2">{page.unique_visitors} unik</span><span className="tabular font-semibold">{page.views} kunjungan</span></div>)}</div>
                </section>
            </>}
        </div>
    );
}

function MetricCard({ icon: Icon, label, value }: { icon: React.ElementType; label: string; value: number }) {
    return <div className="rounded-card border border-line bg-surface p-5 shadow-1"><Icon className="h-5 w-5 text-primary" /><p className="mt-4 text-xs font-semibold uppercase tracking-wider text-ink-2">{label}</p><p className="mt-1 font-display text-2xl font-bold tabular">{value}</p></div>;
}
