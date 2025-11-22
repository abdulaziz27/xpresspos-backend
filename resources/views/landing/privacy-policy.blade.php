@extends('layouts.xpresspos')

@section('title', 'Kebijakan Privasi - XpressPOS')
@section('description', 'Kebijakan Privasi XpressPOS - Perlindungan Data Pribadi sesuai UU ITE dan PP 71/2019')

@section('content')
<main class="overflow-hidden">
    <!-- Hero Section -->
    <section class="relative w-full bg-gradient-to-b from-blue-50 to-white py-16">
        <div class="mx-auto max-w-4xl px-6">
            <div class="text-center mb-12">
                <h1 class="text-4xl md:text-5xl font-bold text-blue-600 mb-4">
                    Kebijakan Privasi
                </h1>
                <p class="text-lg text-gray-600">
                    Terakhir diperbarui: {{ date('d F Y') }}
                </p>
            </div>
        </div>
    </section>

    <!-- Content Section -->
    <section class="py-12 bg-white">
        <div class="mx-auto max-w-4xl px-6">
            <div class="prose prose-lg max-w-none text-justify">
                
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">1. Pendahuluan</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        XpressPOS ("kami", "kita", atau "perusahaan") menghormati privasi Anda dan berkomitmen untuk melindungi data pribadi Anda. Kebijakan Privasi ini menjelaskan bagaimana kami mengumpulkan, menggunakan, menyimpan, dan melindungi informasi pribadi Anda ketika menggunakan layanan XpressPOS.
                    </p>
                    <p class="text-gray-700 leading-relaxed">
                        Kebijakan ini dibuat sesuai dengan Undang-Undang Nomor 11 Tahun 2008 tentang Informasi dan Transaksi Elektronik (UU ITE) sebagaimana telah diubah dengan Undang-Undang Nomor 19 Tahun 2016, Peraturan Pemerintah Nomor 71 Tahun 2019 tentang Penyelenggaraan Sistem dan Transaksi Elektronik, dan peraturan perundang-undangan terkait perlindungan data pribadi di Indonesia.
                    </p>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">2. Informasi yang Kami Kumpulkan</h2>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">2.1. Informasi yang Anda Berikan</h3>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li><strong>Data Identitas:</strong> Nama lengkap, alamat email, nomor telepon, alamat fisik</li>
                        <li><strong>Data Bisnis:</strong> Nama perusahaan/toko, NPWP, nomor rekening bank, informasi bisnis lainnya</li>
                        <li><strong>Data Akun:</strong> Username, password, dan informasi autentikasi lainnya</li>
                        <li><strong>Data Transaksi:</strong> Informasi pembayaran, riwayat transaksi, data pelanggan</li>
                        <li><strong>Data Komunikasi:</strong> Pesan, email, dan komunikasi lainnya yang Anda kirimkan kepada kami</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">2.2. Informasi yang Dikumpulkan Secara Otomatis</h3>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li><strong>Data Teknis:</strong> Alamat IP, jenis browser, sistem operasi, perangkat yang digunakan</li>
                        <li><strong>Data Penggunaan:</strong> Halaman yang dikunjungi, waktu akses, durasi penggunaan, fitur yang digunakan</li>
                        <li><strong>Data Lokasi:</strong> Informasi lokasi geografis (jika diizinkan)</li>
                        <li><strong>Cookies dan Teknologi Serupa:</strong> Data yang dikumpulkan melalui cookies, web beacons, dan teknologi pelacakan lainnya</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">3. Tujuan Penggunaan Data</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Kami menggunakan data pribadi Anda untuk tujuan berikut:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Menyediakan, memelihara, dan meningkatkan layanan XpressPOS</li>
                        <li>Memproses transaksi dan pembayaran Anda</li>
                        <li>Mengelola akun dan memberikan dukungan pelanggan</li>
                        <li>Mengirimkan notifikasi penting terkait layanan, pembaruan, dan perubahan kebijakan</li>
                        <li>Menganalisis penggunaan layanan untuk meningkatkan pengalaman pengguna</li>
                        <li>Mencegah penipuan, penyalahgunaan, dan aktivitas ilegal</li>
                        <li>Mematuhi kewajiban hukum dan peraturan yang berlaku</li>
                        <li>Mengirimkan komunikasi pemasaran (dengan persetujuan Anda)</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">4. Dasar Hukum Pengolahan Data</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Pengolahan data pribadi Anda dilakukan berdasarkan:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li><strong>Persetujuan (Consent):</strong> Anda telah memberikan persetujuan untuk pengolahan data pribadi</li>
                        <li><strong>Kontrak:</strong> Pengolahan data diperlukan untuk memenuhi kontrak layanan</li>
                        <li><strong>Kewajiban Hukum:</strong> Pengolahan data untuk memenuhi kewajiban hukum yang berlaku</li>
                        <li><strong>Kepentingan Legitim:</strong> Pengolahan data untuk kepentingan yang sah dari perusahaan</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">5. Pembagian Data dengan Pihak Ketiga</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Kami dapat membagikan data pribadi Anda dengan pihak ketiga dalam situasi berikut:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li><strong>Penyedia Layanan:</strong> Perusahaan yang membantu kami menyediakan layanan (hosting, pembayaran, analitik)</li>
                        <li><strong>Mitra Bisnis:</strong> Mitra yang bekerja sama dengan kami untuk menyediakan layanan</li>
                        <li><strong>Kewajiban Hukum:</strong> Jika diwajibkan oleh hukum atau perintah pengadilan</li>
                        <li><strong>Perlindungan Hak:</strong> Untuk melindungi hak, properti, atau keamanan kami dan pengguna lainnya</li>
                    </ul>
                    <p class="text-gray-700 leading-relaxed">
                        Kami memastikan bahwa semua pihak ketiga yang menerima data pribadi Anda terikat oleh perjanjian kerahasiaan dan perlindungan data yang ketat.
                    </p>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">6. Keamanan Data</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Kami menerapkan langkah-langkah keamanan teknis dan organisasi yang sesuai untuk melindungi data pribadi Anda dari akses, penggunaan, pengungkapan, perubahan, atau penghancuran yang tidak sah, termasuk:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Enkripsi data dalam transmisi dan penyimpanan</li>
                        <li>Kontrol akses yang ketat dan autentikasi multi-faktor</li>
                        <li>Pemantauan keamanan secara berkala</li>
                        <li>Backup data secara rutin</li>
                        <li>Pelatihan keamanan untuk staf</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">7. Penyimpanan Data</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Data pribadi Anda akan disimpan selama diperlukan untuk tujuan yang dijelaskan dalam kebijakan ini, atau selama diwajibkan oleh hukum. Setelah periode penyimpanan berakhir, data akan dihapus atau dianonimkan secara aman.
                    </p>
                    <p class="text-gray-700 leading-relaxed">
                        Data transaksi keuangan akan disimpan sesuai dengan ketentuan perpajakan Indonesia (minimal 10 tahun).
                    </p>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">8. Hak-Hak Anda</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Sesuai dengan peraturan perlindungan data pribadi di Indonesia, Anda memiliki hak-hak berikut:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li><strong>Hak Akses:</strong> Meminta akses ke data pribadi Anda yang kami simpan</li>
                        <li><strong>Hak Koreksi:</strong> Meminta perbaikan data pribadi yang tidak akurat atau tidak lengkap</li>
                        <li><strong>Hak Penghapusan:</strong> Meminta penghapusan data pribadi Anda dalam kondisi tertentu</li>
                        <li><strong>Hak Pembatasan:</strong> Meminta pembatasan pengolahan data pribadi Anda</li>
                        <li><strong>Hak Portabilitas:</strong> Meminta transfer data pribadi Anda ke penyedia layanan lain</li>
                        <li><strong>Hak Keberatan:</strong> Menolak pengolahan data pribadi Anda untuk tujuan tertentu</li>
                        <li><strong>Hak Penarikan Persetujuan:</strong> Menarik persetujuan yang telah diberikan kapan saja</li>
                    </ul>
                    <p class="text-gray-700 leading-relaxed">
                        Untuk menggunakan hak-hak Anda, silakan hubungi kami melalui email di <a href="mailto:privacy@xpresspos.id" class="text-blue-600 hover:underline">privacy@xpresspos.id</a>.
                    </p>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">9. Cookies dan Teknologi Pelacakan</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Kami menggunakan cookies dan teknologi pelacakan serupa untuk meningkatkan pengalaman Anda. Untuk informasi lebih lanjut, silakan lihat <a href="{{ route('landing.cookie-policy') }}" class="text-blue-600 hover:underline">Kebijakan Cookie</a> kami.
                    </p>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">10. Privasi Anak</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Layanan kami tidak ditujukan untuk anak di bawah usia 18 tahun. Kami tidak secara sengaja mengumpulkan data pribadi dari anak di bawah usia 18 tahun. Jika kami mengetahui bahwa kami telah mengumpulkan data dari anak di bawah usia tersebut, kami akan segera menghapusnya.
                    </p>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">11. Perubahan Kebijakan</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Kami dapat memperbarui Kebijakan Privasi ini dari waktu ke waktu. Perubahan signifikan akan diberitahukan melalui email atau notifikasi di platform. Kami menyarankan Anda untuk meninjau kebijakan ini secara berkala.
                    </p>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">12. Kontak</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Jika Anda memiliki pertanyaan, kekhawatiran, atau permintaan terkait Kebijakan Privasi ini atau pengolahan data pribadi Anda, silakan hubungi kami:
                    </p>
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <p class="text-gray-700 mb-2"><strong>Email:</strong> <a href="mailto:privacy@xpresspos.id" class="text-blue-600 hover:underline">privacy@xpresspos.id</a></p>
                        <p class="text-gray-700 mb-2"><strong>Alamat:</strong> Indonesia</p>
                        <p class="text-gray-700"><strong>Jam Operasional:</strong> Senin - Jumat, 09:00 - 17:00 WIB</p>
                    </div>
                </div>

                <div class="mb-8 p-6 bg-blue-50 rounded-lg border-l-4 border-blue-500">
                    <p class="text-gray-700 leading-relaxed">
                        <strong>Catatan Penting:</strong> Dengan menggunakan layanan XpressPOS, Anda menyetujui pengumpulan dan penggunaan informasi sesuai dengan Kebijakan Privasi ini. Jika Anda tidak setuju dengan kebijakan ini, mohon untuk tidak menggunakan layanan kami.
                    </p>
                </div>

            </div>
        </div>
    </section>
</main>
@endsection

