@extends('layouts.xpresspos')

@section('title', 'Kebijakan Cookie - XpressPOS')
@section('description', 'Kebijakan Cookie XpressPOS - Informasi tentang penggunaan cookies dan teknologi pelacakan')

@section('content')
<main class="overflow-hidden">
    <!-- Hero Section -->
    <section class="relative w-full bg-gradient-to-b from-blue-50 to-white py-16">
        <div class="mx-auto max-w-4xl px-6">
            <div class="text-center mb-12">
                <h1 class="text-4xl md:text-5xl font-bold text-blue-600 mb-4">
                    Kebijakan Cookie
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
                        Kebijakan Cookie ini menjelaskan bagaimana XpressPOS ("kami", "kita", atau "perusahaan") menggunakan cookies dan teknologi pelacakan serupa ketika Anda mengakses dan menggunakan platform kami. Kebijakan ini dibuat sesuai dengan peraturan perundang-undangan yang berlaku di Indonesia, termasuk Undang-Undang Nomor 11 Tahun 2008 tentang Informasi dan Transaksi Elektronik (UU ITE) dan Peraturan Pemerintah Nomor 71 Tahun 2019.
                    </p>
                    <p class="text-gray-700 leading-relaxed">
                        Dengan menggunakan platform XpressPOS, Anda menyetujui penggunaan cookies sesuai dengan kebijakan ini. Jika Anda tidak setuju dengan penggunaan cookies, Anda dapat menyesuaikan pengaturan browser Anda atau tidak menggunakan platform kami.
                    </p>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">2. Apa Itu Cookie?</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Cookie adalah file teks kecil yang ditempatkan di perangkat Anda (komputer, tablet, atau ponsel) ketika Anda mengunjungi sebuah website. Cookie memungkinkan website untuk mengingat tindakan dan preferensi Anda selama periode waktu tertentu, sehingga Anda tidak perlu memasukkan informasi tersebut setiap kali kembali ke website atau menjelajah dari satu halaman ke halaman lain.
                    </p>
                    <p class="text-gray-700 leading-relaxed">
                        Cookie dapat berupa "session cookies" yang dihapus ketika Anda menutup browser, atau "persistent cookies" yang tetap tersimpan di perangkat Anda sampai kedaluwarsa atau dihapus.
                    </p>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">3. Jenis Cookie yang Kami Gunakan</h2>
                    
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">3.1. Cookie Esensial (Essential Cookies)</h3>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Cookie ini diperlukan untuk berfungsinya platform dan tidak dapat dimatikan di sistem kami. Cookie ini biasanya hanya disetel sebagai respons terhadap tindakan yang Anda lakukan yang setara dengan permintaan layanan, seperti mengatur preferensi privasi, masuk ke akun, atau mengisi formulir.
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li><strong>Cookie Autentikasi:</strong> Mengingat status login Anda</li>
                        <li><strong>Cookie Keamanan:</strong> Melindungi dari serangan keamanan</li>
                        <li><strong>Cookie Sesi:</strong> Mempertahankan sesi pengguna selama browsing</li>
                        <li><strong>Cookie Preferensi:</strong> Menyimpan pengaturan bahasa dan wilayah</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">3.2. Cookie Fungsional (Functional Cookies)</h3>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Cookie ini memungkinkan platform untuk menyediakan fungsionalitas yang ditingkatkan dan personalisasi. Cookie ini dapat disetel oleh kami atau oleh penyedia layanan pihak ketiga yang layanannya telah kami tambahkan ke halaman kami.
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li><strong>Cookie Preferensi Pengguna:</strong> Mengingat pilihan dan preferensi Anda</li>
                        <li><strong>Cookie Personalisasi:</strong> Menyesuaikan konten dan pengalaman</li>
                        <li><strong>Cookie Fitur:</strong> Mengaktifkan fitur tambahan seperti chat support</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">3.3. Cookie Analitik (Analytics Cookies)</h3>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Cookie ini membantu kami memahami bagaimana pengunjung berinteraksi dengan platform kami dengan mengumpulkan dan melaporkan informasi secara anonim. Ini membantu kami meningkatkan cara platform kami bekerja.
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li><strong>Cookie Analitik Web:</strong> Melacak halaman yang dikunjungi dan waktu yang dihabiskan</li>
                        <li><strong>Cookie Perilaku Pengguna:</strong> Menganalisis pola penggunaan</li>
                        <li><strong>Cookie Kinerja:</strong> Mengukur performa platform</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">3.4. Cookie Pemasaran (Marketing Cookies)</h3>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Cookie ini digunakan untuk melacak pengunjung di berbagai website dengan maksud menampilkan iklan yang relevan dan menarik bagi pengguna individu. Cookie ini juga membantu mengukur efektivitas kampanye iklan.
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li><strong>Cookie Iklan:</strong> Menampilkan iklan yang relevan</li>
                        <li><strong>Cookie Retargeting:</strong> Mengingat kunjungan Anda untuk iklan lanjutan</li>
                        <li><strong>Cookie Afiliasi:</strong> Melacak rujukan dari mitra</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">4. Cookie Pihak Ketiga</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Kami juga menggunakan layanan pihak ketiga yang dapat menempatkan cookie di perangkat Anda. Cookie pihak ketiga ini digunakan untuk:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li><strong>Analitik:</strong> Google Analytics untuk menganalisis penggunaan platform</li>
                        <li><strong>Pembayaran:</strong> Xendit dan penyedia pembayaran lainnya untuk memproses transaksi</li>
                        <li><strong>Dukungan:</strong> Layanan chat dan dukungan pelanggan</li>
                        <li><strong>Keamanan:</strong> Layanan keamanan dan perlindungan dari serangan</li>
                        <li><strong>Hosting:</strong> Penyedia hosting dan infrastruktur cloud</li>
                    </ul>
                    <p class="text-gray-700 leading-relaxed">
                        Cookie pihak ketiga tunduk pada kebijakan privasi masing-masing penyedia. Kami tidak memiliki kontrol atas cookie pihak ketiga ini.
                    </p>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">5. Teknologi Pelacakan Lainnya</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Selain cookies, kami juga dapat menggunakan teknologi pelacakan lainnya:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li><strong>Web Beacons:</strong> Gambar kecil yang tertanam dalam halaman web atau email</li>
                        <li><strong>Pixel Tags:</strong> Kode kecil yang ditempatkan di halaman web untuk melacak konversi</li>
                        <li><strong>Local Storage:</strong> Penyimpanan data lokal di browser Anda</li>
                        <li><strong>Session Storage:</strong> Penyimpanan data sementara selama sesi browsing</li>
                        <li><strong>Fingerprinting:</strong> Teknologi untuk mengidentifikasi perangkat berdasarkan karakteristiknya</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">6. Tujuan Penggunaan Cookie</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Kami menggunakan cookies untuk tujuan berikut:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li><strong>Fungsionalitas Platform:</strong> Memastikan platform berfungsi dengan baik</li>
                        <li><strong>Autentikasi dan Keamanan:</strong> Memverifikasi identitas dan melindungi akun Anda</li>
                        <li><strong>Preferensi Pengguna:</strong> Mengingat pengaturan dan preferensi Anda</li>
                        <li><strong>Analitik dan Perbaikan:</strong> Memahami bagaimana platform digunakan untuk perbaikan</li>
                        <li><strong>Personalisasi:</strong> Menyediakan konten dan fitur yang relevan</li>
                        <li><strong>Pemasaran:</strong> Menampilkan iklan yang relevan (dengan persetujuan)</li>
                        <li><strong>Kinerja:</strong> Mengoptimalkan kecepatan dan performa platform</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">7. Durasi Penyimpanan Cookie</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Cookie yang kami gunakan memiliki durasi penyimpanan yang berbeda:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li><strong>Session Cookies:</strong> Dihapus ketika Anda menutup browser (sementara)</li>
                        <li><strong>Persistent Cookies:</strong> Tetap tersimpan untuk periode tertentu:
                            <ul class="list-circle pl-6 mt-2 space-y-1">
                                <li>Cookie Esensial: Hingga 1 tahun</li>
                                <li>Cookie Fungsional: Hingga 2 tahun</li>
                                <li>Cookie Analitik: Hingga 2 tahun</li>
                                <li>Cookie Pemasaran: Hingga 1 tahun</li>
                            </ul>
                        </li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">8. Pengelolaan Cookie</h2>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">8.1. Pengaturan Browser</h3>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Anda dapat mengontrol dan mengelola cookies melalui pengaturan browser Anda. Sebagian besar browser memungkinkan Anda untuk:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Melihat cookies yang tersimpan di perangkat Anda</li>
                        <li>Menghapus cookies yang ada</li>
                        <li>Memblokir cookies dari website tertentu atau semua website</li>
                        <li>Mengatur pemberitahuan ketika cookie ditetapkan</li>
                        <li>Menghapus semua cookies ketika browser ditutup</li>
                    </ul>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        <strong>Catatan Penting:</strong> Mematikan atau membatasi cookies dapat mempengaruhi fungsionalitas platform dan pengalaman Anda. Beberapa fitur mungkin tidak berfungsi dengan baik jika cookies dinonaktifkan.
                    </p>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">8.2. Pengaturan Platform</h3>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Anda dapat mengelola preferensi cookie melalui pengaturan akun di platform XpressPOS. Beberapa cookie esensial tidak dapat dinonaktifkan karena diperlukan untuk berfungsinya platform.
                    </p>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">8.3. Link Pengaturan Browser</h3>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Untuk informasi lebih lanjut tentang cara mengelola cookies di browser tertentu:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Google Chrome</a></li>
                        <li><a href="https://support.mozilla.org/id/kb/mengaktifkan-dan-menonaktifkan-cookie" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Mozilla Firefox</a></li>
                        <li><a href="https://support.apple.com/id-id/guide/safari/sfri11471/mac" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Safari</a></li>
                        <li><a href="https://support.microsoft.com/id-id/microsoft-edge/hapus-cookie-di-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Microsoft Edge</a></li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">9. Cookie dan Data Pribadi</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Beberapa cookies yang kami gunakan dapat mengumpulkan informasi pribadi. Penggunaan informasi pribadi ini diatur dalam <a href="{{ route('landing.privacy-policy') }}" class="text-blue-600 hover:underline">Kebijakan Privasi</a> kami. Kami memastikan bahwa:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Data yang dikumpulkan melalui cookies digunakan sesuai dengan Kebijakan Privasi</li>
                        <li>Kami tidak menjual data pribadi yang dikumpulkan melalui cookies</li>
                        <li>Data digunakan untuk meningkatkan layanan dan pengalaman pengguna</li>
                        <li>Kami menerapkan langkah-langkah keamanan yang sesuai</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">10. Perubahan Kebijakan Cookie</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Kami dapat memperbarui Kebijakan Cookie ini dari waktu ke waktu untuk mencerminkan perubahan dalam praktik kami atau karena alasan operasional, hukum, atau peraturan lainnya. Perubahan signifikan akan diberitahukan melalui email atau notifikasi di platform.
                    </p>
                    <p class="text-gray-700 leading-relaxed">
                        Kami menyarankan Anda untuk meninjau halaman ini secara berkala untuk tetap mengetahui tentang penggunaan cookies kami.
                    </p>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">11. Persetujuan</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Dengan menggunakan platform XpressPOS, Anda menyetujui penggunaan cookies sesuai dengan Kebijakan Cookie ini. Jika Anda tidak setuju dengan penggunaan cookies tertentu, Anda dapat:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Mengatur preferensi cookie melalui pengaturan browser</li>
                        <li>Mengatur preferensi cookie melalui pengaturan akun di platform</li>
                        <li>Menghubungi kami untuk informasi lebih lanjut</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">12. Kontak</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Jika Anda memiliki pertanyaan tentang Kebijakan Cookie ini atau penggunaan cookies oleh kami, silakan hubungi kami:
                    </p>
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <p class="text-gray-700 mb-2"><strong>Email:</strong> <a href="mailto:privacy@xpresspos.id" class="text-blue-600 hover:underline">privacy@xpresspos.id</a></p>
                        <p class="text-gray-700 mb-2"><strong>Alamat:</strong> Indonesia</p>
                        <p class="text-gray-700"><strong>Jam Operasional:</strong> Senin - Jumat, 09:00 - 17:00 WIB</p>
                    </div>
                </div>

                <div class="mb-8 p-6 bg-blue-50 rounded-lg border-l-4 border-blue-500">
                    <p class="text-gray-700 leading-relaxed">
                        <strong>Catatan Penting:</strong> Penggunaan cookies esensial diperlukan untuk berfungsinya platform. Jika Anda menonaktifkan cookies esensial, beberapa fitur platform mungkin tidak berfungsi dengan baik atau tidak dapat diakses.
                    </p>
                </div>

            </div>
        </div>
    </section>
</main>
@endsection

