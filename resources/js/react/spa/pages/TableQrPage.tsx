import { useState } from 'react';
import { Link } from 'react-router-dom';
import { apiRequest } from '@/react/lib/api';
import { useResource } from '../hooks';
import { Badge, Button, ErrorBox, Loading, PageHeader, Panel } from '../ui';

interface TableQrItem {
    id: number;
    label: string;
    seats: number;
    is_active: boolean;
    scan_url: string;
}

export function TableQrPage() {
    const { data, loading, error, reload } = useResource<{ tables: TableQrItem[] }>('/api/meja/qr');
    const [busyId, setBusyId] = useState<number | null>(null);
    const [message, setMessage] = useState<string | null>(null);

    async function rotate(table: TableQrItem) {
        if (!window.confirm(`Ganti token QR ${table.label}? QR lama langsung tidak berlaku.`)) return;

        setBusyId(table.id);
        setMessage(null);
        const result = await apiRequest<TableQrItem>('POST', `/meja/${table.id}/qr-ulang`);
        setBusyId(null);

        if (result.ok) {
            setMessage(result.message || `Token QR ${table.label} berhasil diganti.`);
            reload();
        } else {
            setMessage(result.message || 'Token QR gagal diganti.');
        }
    }

    if (loading) return <Loading label="Memuat QR meja..." />;
    if (error || !data) return <ErrorBox message={error ?? 'Gagal memuat QR meja.'} />;

    return (
        <div>
            <PageHeader
                title="QR Meja Pelanggan"
                subtitle="Setiap QR mengarah ke meja yang benar dan pesanan langsung masuk ke antrean dapur."
                actions={<Link to="/meja/qr-cetak"><Button>Buka Lembar Cetak QR</Button></Link>}
            />
            {message && <p className="mb-4 rounded-input bg-ok-bg px-4 py-2 text-sm font-semibold text-ok">{message}</p>}
            <Panel>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[680px] text-sm">
                        <thead className="border-b border-line text-left text-[11px] uppercase tracking-wider text-ink-2">
                            <tr>
                                <th className="px-4 py-3">Meja</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">QR Code</th>
                                <th className="px-4 py-3">URL QR</th>
                                <th className="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {data.tables.map((table) => (
                                <tr key={table.id}>
                                    <td className="px-4 py-3 font-semibold">{table.label}</td>
                                    <td className="px-4 py-3"><Badge className={table.is_active ? 'bg-ok-bg text-ok border-ok/20' : 'bg-bad-bg text-bad border-bad/20'}>{table.is_active ? 'Aktif' : 'Nonaktif'}</Badge></td>
                                    <td className="px-4 py-3">
                                        {table.is_active ? <img src={`/meja/${table.id}/qr.svg`} alt={`QR Meja ${table.label}`} width="64" height="64" className="rounded-input border border-line bg-white p-1" /> : '—'}
                                    </td>
                                    <td className="max-w-xs break-all px-4 py-3 font-mono text-[11px]">{table.scan_url}</td>
                                    <td className="px-4 py-3 text-right"><Button variant="ghost" disabled={busyId === table.id} onClick={() => rotate(table)}>{busyId === table.id ? 'Memproses…' : 'Ganti Token'}</Button></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Panel>
        </div>
    );
}
