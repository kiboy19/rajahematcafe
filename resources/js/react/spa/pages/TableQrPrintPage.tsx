import { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useResource } from '../hooks';
import { ErrorBox, Loading } from '../ui';

interface TableQrItem {
    id: number;
    label: string;
    seats: number;
    is_active: boolean;
    scan_url: string;
}

export function TableQrPrintPage() {
    const { data, loading, error } = useResource<{ tables: TableQrItem[] }>('/api/meja/qr-cetak');

    useEffect(() => {
        document.title = 'Lembar Cetak QR Meja — Rajahemat Cafe';
    }, []);

    if (loading) {
        return <Loading label="Memuat data QR meja untuk dicetak..." />;
    }

    if (error || !data) {
        return <ErrorBox message={error ?? 'Gagal memuat daftar meja.'} />;
    }

    const tables = data.tables ?? [];

    return (
        <div className="bg-white min-h-screen text-black">
            {/* Toolbar Layar (disembunyikan saat mencetak) */}
            <header className="print:hidden sticky top-0 z-10 bg-[#FAF7F0] border-b border-[#E7E0D5] px-5 py-3 flex items-center justify-between text-xs">
                <div>
                    <span className="font-bold text-sm">Lembar Cetak QR Meja</span> — {tables.length} meja terdaftar. Siapkan kertas A4, lalu klik Cetak.
                </div>
                <div className="flex items-center gap-3">
                    <Link
                        to="/kelola-meja"
                        className="text-[#6B5A4A] underline font-medium hover:text-black"
                    >
                        Kembali ke Kelola Meja
                    </Link>
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="h-9 px-4 rounded-md bg-[#2C241E] text-white font-semibold hover:bg-black cursor-pointer"
                    >
                        Cetak Halaman Ini
                    </button>
                </div>
            </header>

            {/* Grid Kartu Meja Cetak A4 */}
            <main className="p-4 sm:p-8 max-w-[210mm] mx-auto print:p-0 print:max-w-none">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 print:grid-cols-2 print:gap-4">
                    {tables.map((table) => (
                        <div
                            key={table.id}
                            className="border-[1.5px] border-black rounded-lg p-5 flex flex-col items-center justify-between text-center min-h-[105mm] break-inside-avoid print:break-inside-avoid bg-white"
                        >
                            <div>
                                <h1 className="font-display font-bold text-xl leading-tight tracking-wide">
                                    Rajahemat
                                </h1>
                                <p className="text-[10px] uppercase font-semibold tracking-widest mt-0.5 text-neutral-600">
                                    Coffee &amp; Eatery
                                </p>
                                <div className="inline-block my-2 px-4 py-1 border-[1.5px] border-black rounded-full font-extrabold text-base tracking-wider uppercase">
                                    Meja {table.label}
                                </div>
                            </div>

                            <div className="p-2 bg-white flex items-center justify-center my-2">
                                <img
                                    src={`/meja/${table.id}/qr.svg`}
                                    alt={`QR Meja ${table.label}`}
                                    className="w-40 h-40 print:w-44 print:h-44 object-contain"
                                />
                            </div>

                            <div>
                                <p className="text-[11px] leading-snug max-w-[65mm] mx-auto">
                                    <span className="font-bold block mb-0.5">Pindai untuk melihat menu &amp; memesan</span>
                                    Buka kamera HP atau aplikasi QR, arahkan ke kode di atas.
                                </p>
                                <p className="text-[9px] font-mono mt-1 text-neutral-500 break-all">
                                    {table.scan_url}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            </main>
        </div>
    );
}
