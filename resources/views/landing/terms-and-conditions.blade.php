@extends('layouts.xpresspos')

@section('title', 'Syarat dan Ketentuan - XpressPOS')
@section('description', 'Syarat dan Ketentuan Penggunaan XpressPOS - Ketentuan Layanan sesuai Hukum Indonesia')

@section('content')
<main class="overflow-hidden">
    <!-- Hero Section -->
    <section class="relative w-full bg-gradient-to-b from-blue-50 to-white py-16">
        <div class="mx-auto max-w-4xl px-6">
            <div class="text-center mb-12">
                <h1 class="text-4xl md:text-5xl font-bold text-blue-600 mb-4">
                    Syarat dan Ketentuan
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
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">1. Penerimaan Syarat</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Dengan mengakses dan menggunakan layanan XpressPOS ("Layanan"), Anda menyetujui untuk terikat oleh Syarat dan Ketentuan ini. Jika Anda tidak setuju dengan syarat dan ketentuan ini, mohon untuk tidak menggunakan Layanan kami.
                    </p>
                    <p class="text-gray-700 leading-relaxed">
                        Syarat dan Ketentuan ini diatur oleh hukum Republik Indonesia dan tunduk pada yurisdiksi pengadilan di Indonesia.
                    </p>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">2. Definisi</h2>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li><strong>"Kami", "Kita", "Perusahaan":</strong> Merujuk pada penyedia layanan XpressPOS</li>
                        <li><strong>"Anda", "Pengguna":</strong> Merujuk pada individu atau entitas yang mengakses atau menggunakan Layanan</li>
                        <li><strong>"Layanan":</strong> Merujuk pada platform XpressPOS, termasuk semua fitur, konten, dan layanan yang tersedia</li>
                        <li><strong>"Akun":</strong> Merujuk pada akun pengguna yang dibuat untuk mengakses Layanan</li>
                        <li><strong>"Konten":</strong> Merujuk pada semua data, informasi, teks, gambar, dan materi lainnya yang tersedia melalui Layanan</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">3. Pendaftaran dan Akun</h2>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">3.1. Persyaratan Pendaftaran</h3>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Anda harus berusia minimal 18 tahun atau memiliki persetujuan dari wali yang sah</li>
                        <li>Anda harus memberikan informasi yang akurat, lengkap, dan terkini</li>
                        <li>Anda bertanggung jawab untuk menjaga kerahasiaan informasi akun Anda</li>
                        <li>Anda bertanggung jawab atas semua aktivitas yang terjadi di bawah akun Anda</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">3.2. Keamanan Akun</h3>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Anda wajib menggunakan password yang kuat dan unik</li>
                        <li>Anda tidak boleh membagikan kredensial akun kepada pihak lain</li>
                        <li>Anda harus segera memberitahu kami jika mengetahui adanya penggunaan akun yang tidak sah</li>
                        <li>Kami berhak menangguhkan atau menutup akun yang diduga melakukan pelanggaran</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">4. Penggunaan Layanan</h2>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">4.1. Penggunaan yang Diizinkan</h3>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Anda diizinkan untuk menggunakan Layanan hanya untuk tujuan yang sah dan sesuai dengan ketentuan ini. Anda dapat menggunakan Layanan untuk:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Mengelola bisnis dan operasional toko/restoran Anda</li>
                        <li>Memproses transaksi penjualan</li>
                        <li>Mengelola inventori dan produk</li>
                        <li>Mengakses laporan dan analitik bisnis</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">4.2. Penggunaan yang Dilarang</h3>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Anda dilarang untuk:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Menggunakan Layanan untuk tujuan ilegal atau melanggar hukum</li>
                        <li>Mengganggu, merusak, atau membahayakan Layanan atau sistem kami</li>
                        <li>Mencoba mendapatkan akses tidak sah ke sistem atau data kami</li>
                        <li>Menggunakan bot, script, atau metode otomatis untuk mengakses Layanan tanpa izin</li>
                        <li>Menyebarkan malware, virus, atau kode berbahaya lainnya</li>
                        <li>Melakukan reverse engineering, decompiling, atau disassembling Layanan</li>
                        <li>Menggunakan Layanan untuk mengirim spam atau komunikasi yang tidak diinginkan</li>
                        <li>Melanggar hak kekayaan intelektual kami atau pihak ketiga</li>
                        <li>Menggunakan Layanan untuk menipu atau menyesatkan pelanggan</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">5. Langganan dan Pembayaran</h2>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">5.1. Paket Langganan</h3>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Layanan tersedia dalam berbagai paket langganan dengan fitur dan harga yang berbeda</li>
                        <li>Harga dapat berubah sewaktu-waktu dengan pemberitahuan sebelumnya</li>
                        <li>Pembayaran dilakukan sesuai dengan siklus yang dipilih (bulanan atau tahunan)</li>
                        <li>Semua harga dinyatakan dalam Rupiah (IDR) dan termasuk PPN jika berlaku</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">5.2. Pembayaran</h3>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Pembayaran harus dilakukan sebelum akses ke Layanan diberikan</li>
                        <li>Kami menerima berbagai metode pembayaran yang tersedia di platform</li>
                        <li>Jika pembayaran gagal atau ditolak, akses ke Layanan dapat ditangguhkan</li>
                        <li>Anda bertanggung jawab untuk memastikan informasi pembayaran yang akurat</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">5.3. Perpanjangan dan Pembatalan</h3>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Langganan akan diperpanjang secara otomatis kecuali dibatalkan sebelum periode berakhir</li>
                        <li>Anda dapat membatalkan langganan kapan saja melalui pengaturan akun</li>
                        <li>Pembatalan akan berlaku efektif pada akhir periode langganan saat ini</li>
                        <li>Tidak ada pengembalian dana untuk periode yang sudah dibayar, kecuali diatur lain dalam kebijakan pengembalian dana</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">6. Konten Pengguna</h2>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">6.1. Kepemilikan Konten</h3>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Anda mempertahankan semua hak kepemilikan atas konten yang Anda unggah atau masukkan ke dalam Layanan. Dengan menggunakan Layanan, Anda memberikan kami lisensi non-eksklusif untuk menggunakan, menyimpan, dan memproses konten Anda untuk tujuan menyediakan Layanan.
                    </p>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">6.2. Tanggung Jawab Konten</h3>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Anda bertanggung jawab penuh atas semua konten yang Anda unggah atau masukkan</li>
                        <li>Anda menjamin bahwa konten Anda tidak melanggar hak pihak ketiga</li>
                        <li>Anda tidak boleh mengunggah konten yang ilegal, menyesatkan, atau melanggar hukum</li>
                        <li>Kami berhak menghapus konten yang melanggar ketentuan ini tanpa pemberitahuan</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">7. Hak Kekayaan Intelektual</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Semua hak kekayaan intelektual terkait Layanan, termasuk namun tidak terbatas pada software, desain, logo, merek dagang, dan konten lainnya, adalah milik kami atau pemberi lisensi kami. Anda tidak diperbolehkan untuk:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Menyalin, memodifikasi, atau membuat karya turunan dari Layanan</li>
                        <li>Menggunakan merek dagang atau logo kami tanpa izin tertulis</li>
                        <li>Menghapus atau mengubah pemberitahuan hak cipta</li>
                        <li>Menggunakan Layanan untuk menciptakan produk atau layanan yang bersaing</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">8. Ketersediaan Layanan</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Kami berusaha untuk memastikan Layanan tersedia 24/7, namun kami tidak menjamin bahwa Layanan akan selalu tersedia tanpa gangguan. Layanan dapat mengalami downtime untuk:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Pemeliharaan rutin atau darurat</li>
                        <li>Masalah teknis atau kegagalan sistem</li>
                        <li>Kejadian di luar kendali kami (force majeure)</li>
                        <li>Kegagalan penyedia layanan pihak ketiga</li>
                    </ul>
                    <p class="text-gray-700 leading-relaxed">
                        Kami tidak bertanggung jawab atas kerugian yang timbul akibat ketidaktersediaan Layanan.
                    </p>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">9. Batasan Tanggung Jawab</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Sejauh diizinkan oleh hukum yang berlaku:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Layanan disediakan "sebagaimana adanya" tanpa jaminan apa pun</li>
                        <li>Kami tidak menjamin bahwa Layanan akan memenuhi semua kebutuhan Anda</li>
                        <li>Kami tidak bertanggung jawab atas kerugian tidak langsung, insidental, atau konsekuensial</li>
                        <li>Tanggung jawab kami terbatas pada jumlah yang telah Anda bayar untuk Layanan dalam 12 bulan terakhir</li>
                        <li>Kami tidak bertanggung jawab atas kehilangan data yang disebabkan oleh kesalahan pengguna</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">10. Ganti Rugi</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Anda setuju untuk mengganti rugi dan membebaskan kami dari semua klaim, kerugian, kerusakan, kewajiban, dan biaya (termasuk biaya hukum) yang timbul dari:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Penggunaan atau penyalahgunaan Layanan oleh Anda</li>
                        <li>Pelanggaran terhadap Syarat dan Ketentuan ini</li>
                        <li>Pelanggaran hak pihak ketiga</li>
                        <li>Konten yang Anda unggah atau masukkan ke dalam Layanan</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">11. Pengakhiran</h2>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">11.1. Pengakhiran oleh Anda</h3>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Anda dapat mengakhiri akun dan langganan Anda kapan saja melalui pengaturan akun atau dengan menghubungi kami.
                    </p>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">11.2. Pengakhiran oleh Kami</h3>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Kami dapat mengakhiri atau menangguhkan akun Anda jika:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Anda melanggar Syarat dan Ketentuan ini</li>
                        <li>Anda melakukan aktivitas ilegal atau mencurigakan</li>
                        <li>Pembayaran Anda gagal atau ditolak</li>
                        <li>Akun Anda tidak aktif dalam periode yang lama</li>
                        <li>Diwajibkan oleh hukum atau perintah pengadilan</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">11.3. Dampak Pengakhiran</h3>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Setelah pengakhiran:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Akses Anda ke Layanan akan dihentikan</li>
                        <li>Data Anda dapat dihapus setelah periode penyimpanan tertentu</li>
                        <li>Anda tetap bertanggung jawab atas semua kewajiban yang timbul sebelum pengakhiran</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">12. Penyelesaian Sengketa</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Jika terjadi sengketa terkait Syarat dan Ketentuan ini:
                    </p>
                    <ul class="list-disc pl-6 text-gray-700 space-y-2 mb-4">
                        <li>Kami akan berusaha menyelesaikan sengketa melalui musyawarah dan mufakat</li>
                        <li>Jika musyawarah tidak berhasil, sengketa akan diselesaikan melalui mediasi</li>
                        <li>Jika mediasi tidak berhasil, sengketa akan diselesaikan melalui pengadilan di Indonesia</li>
                        <li>Hukum yang berlaku adalah hukum Republik Indonesia</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">13. Perubahan Syarat dan Ketentuan</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Kami berhak mengubah Syarat dan Ketentuan ini dari waktu ke waktu. Perubahan signifikan akan diberitahukan melalui email atau notifikasi di platform. Penggunaan Layanan setelah perubahan berlaku berarti Anda menyetujui perubahan tersebut.
                    </p>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">14. Ketentuan Lainnya</h2>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">14.1. Pemisahan Ketentuan</h3>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Jika suatu ketentuan dalam Syarat dan Ketentuan ini dinyatakan tidak valid atau tidak dapat dilaksanakan, ketentuan lainnya tetap berlaku penuh.
                    </p>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">14.2. Tidak Ada Penolakan</h3>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Kegagalan kami untuk menegakkan hak atau ketentuan tidak berarti kami melepaskan hak tersebut.
                    </p>

                    <h3 class="text-xl font-semibold text-gray-800 mb-3">14.3. Pemberitahuan</h3>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Pemberitahuan kepada Anda dapat dilakukan melalui email yang terdaftar di akun Anda atau melalui notifikasi di platform.
                    </p>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">15. Kontak</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        Jika Anda memiliki pertanyaan tentang Syarat dan Ketentuan ini, silakan hubungi kami:
                    </p>
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <p class="text-gray-700 mb-2"><strong>Email:</strong> <a href="mailto:legal@xpresspos.id" class="text-blue-600 hover:underline">legal@xpresspos.id</a></p>
                        <p class="text-gray-700 mb-2"><strong>Alamat:</strong> Indonesia</p>
                        <p class="text-gray-700"><strong>Jam Operasional:</strong> Senin - Jumat, 09:00 - 17:00 WIB</p>
                    </div>
                </div>

                <div class="mb-8 p-6 bg-blue-50 rounded-lg border-l-4 border-blue-500">
                    <p class="text-gray-700 leading-relaxed">
                        <strong>Catatan Penting:</strong> Dengan menggunakan Layanan XpressPOS, Anda mengakui bahwa Anda telah membaca, memahami, dan menyetujui untuk terikat oleh Syarat dan Ketentuan ini. Jika Anda tidak setuju dengan ketentuan ini, mohon untuk tidak menggunakan Layanan kami.
                    </p>
                </div>

            </div>
        </div>
    </section>
</main>
@endsection

