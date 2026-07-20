# BAB X
# SISTEM INFORMASI

Bab ini membahas pengembangan sistem informasi berbasis web untuk mendukung pengelolaan *Change Request* pada proyek konstruksi. Sistem dirancang sebagai aplikasi operasional yang menghubungkan proses pencatatan perubahan, pemetaan *Work Breakdown Structure* (WBS), penilaian risiko, visualisasi proyek, persetujuan oleh *Project Manager*, serta pembentukan pengetahuan proyek melalui *knowledge base*. Dengan demikian, sistem tidak hanya diposisikan sebagai media dokumentasi, tetapi juga sebagai instrumen pengendalian proyek dan pendukung pengambilan keputusan.

Pengembangan sistem dilakukan dengan mempertimbangkan karakteristik proyek konstruksi yang dinamis, memiliki banyak aktor, bergantung pada dokumen teknis, dan rentan terhadap perubahan lapangan. Pada praktik konvensional, informasi perubahan sering tersebar dalam formulir, lembar kerja, foto lapangan, dokumen persetujuan, dan komunikasi informal. Kondisi tersebut dapat menimbulkan keterlambatan evaluasi, duplikasi data, ketidaksamaan versi dokumen, serta lemahnya keterlacakan keputusan. Oleh karena itu, sistem yang dikembangkan diarahkan untuk membentuk alur data yang lebih terpusat, terstruktur, dan dapat diaudit.

Secara teknis, sistem menggunakan arsitektur *client-server*. Antarmuka pengguna dikembangkan menggunakan HTML, CSS, dan JavaScript, sedangkan logika aplikasi dan akses data ditangani oleh API berbasis PHP. Basis data menggunakan MySQL dengan koneksi PDO. Untuk memperkuat penyajian informasi, sistem memanfaatkan Chart.js, integrasi S-Curve, serta visualisasi BIM melalui Autodesk Platform Services (APS) dan mode visual 3D berbasis Three.js. Pemilihan teknologi tersebut tidak semata-mata didasarkan pada ketersediaan teknis, tetapi juga pada pertimbangan kemudahan deployment, keterbacaan kode, kompatibilitas dengan lingkungan hosting umum, dan kebutuhan aplikasi yang berorientasi pada fungsi operasional proyek.

## X.1 Landasan Teori

### X.1.1 Manajemen Proyek Konstruksi dan Pengendalian Perubahan

Manajemen proyek konstruksi merupakan proses perencanaan, pelaksanaan, pengendalian, dan penutupan proyek dengan memperhatikan ruang lingkup, waktu, biaya, mutu, keselamatan, sumber daya, komunikasi, dan risiko. Dalam konteks konstruksi, perubahan pekerjaan hampir tidak dapat dihindari karena proyek berlangsung dalam lingkungan yang memiliki ketidakpastian tinggi. Ketidakpastian tersebut dapat berasal dari perubahan desain, kondisi aktual lapangan, kebutuhan pemilik proyek, keterlambatan material, perubahan metode kerja, keterbatasan alat, revisi dokumen teknis, atau koordinasi antarpaket pekerjaan yang tidak sinkron.

Pengendalian perubahan menjadi penting karena perubahan yang tidak terdokumentasi secara formal dapat berdampak pada klaim biaya, perpanjangan waktu, penurunan mutu, konflik kontraktual, dan ketidakjelasan tanggung jawab. *Change Request* digunakan sebagai instrumen formal untuk mendokumentasikan kebutuhan perubahan, penyebab, lokasi, WBS terdampak, risiko, bukti pendukung, serta keputusan akhir. Dalam sistem ini, *Change Request* tidak dipahami sekadar sebagai formulir administratif, melainkan sebagai unit informasi utama yang menghubungkan kondisi lapangan dengan proses evaluasi manajerial.

Pertimbangan akademik dalam merancang sistem pengendalian perubahan adalah perlunya menjaga keseimbangan antara kelengkapan data dan kemudahan penggunaan. Formulir yang terlalu sederhana dapat gagal menangkap kompleksitas dampak perubahan, sedangkan formulir yang terlalu panjang dapat menghambat adopsi pengguna lapangan. Oleh sebab itu, sistem membagi data perubahan ke dalam kelompok yang jelas, seperti identitas perubahan, WBS, lokasi, risiko, dampak biaya, dampak waktu, dampak lingkup, dampak mutu, dampak K3, bukti, dan data approval.

Dalam sistem yang dikembangkan, alur pengendalian perubahan meliputi:

1. *Site Engineer* mengajukan perubahan berdasarkan kondisi lapangan;
2. sistem menyimpan data WBS, lokasi, objek BIM, risiko, dampak, dan bukti;
3. sistem menghubungkan data perubahan dengan *knowledge repository*;
4. *Project Manager* mengevaluasi perubahan berdasarkan data dan rekomendasi;
5. keputusan disimpan sebagai `PENDING`, `APPROVED`, atau `REJECTED`;
6. dampak biaya dan waktu final dicatat;
7. perubahan yang disetujui dapat menjadi *lesson learned* dalam *knowledge base*.

### X.1.2 Work Breakdown Structure

*Work Breakdown Structure* atau WBS adalah struktur hierarkis yang menguraikan ruang lingkup proyek menjadi paket pekerjaan, aktivitas, dan subaktivitas. WBS digunakan untuk memastikan bahwa setiap elemen pekerjaan dapat direncanakan, dikendalikan, dan dievaluasi secara sistematis. Dalam konteks pengelolaan *Change Request*, WBS berfungsi sebagai referensi teknis untuk mengetahui bagian pekerjaan yang terdampak oleh perubahan.

Sistem menggunakan WBS karena perubahan konstruksi jarang berdampak secara abstrak. Perubahan biasanya terjadi pada aktivitas tertentu, misalnya pekerjaan mobilisasi material, pekerjaan K3, erection girder, pekerjaan perkerasan, atau aktivitas survei. Apabila perubahan hanya dicatat dalam bentuk uraian bebas, sistem akan sulit menghubungkan perubahan tersebut dengan biaya, jadwal, risiko, dan pengetahuan teknis yang relevan. Dengan WBS, perubahan dapat ditempatkan pada struktur pekerjaan yang konsisten.

Pada implementasi sistem, WBS disimpan melalui kolom `wbsLevel4`, `wbsLevel5`, dan `wbsLevel6`. Pemisahan level tersebut dipilih agar sistem tidak hanya menyimpan kode pekerjaan paling rinci, tetapi juga tetap dapat melakukan agregasi pada level yang lebih tinggi. Keputusan ini penting karena analisis manajerial sering membutuhkan ringkasan, misalnya WBS mana yang paling sering mengalami perubahan, sedangkan analisis teknis membutuhkan detail aktivitas yang lebih spesifik.

| Pertimbangan WBS | Alasan Pemilihan | Implikasi terhadap Sistem |
|---|---|---|
| Struktur hierarkis | Proyek konstruksi memiliki pekerjaan bertingkat dari paket hingga aktivitas rinci | Sistem dapat menelusuri perubahan pada beberapa level pekerjaan |
| Kode pekerjaan terstandar | Mengurangi ambiguitas deskripsi bebas | CR lebih mudah dicari, difilter, dan direkap |
| Keterhubungan dengan risiko | Risiko proyek umumnya melekat pada jenis aktivitas tertentu | WBS dapat dicocokkan dengan `knowledge_repository` |
| Agregasi dashboard | Manajemen membutuhkan ringkasan area pekerjaan bermasalah | Sistem dapat menampilkan WBS paling terdampak |
| Keterhubungan BIM | Objek model dapat diberi referensi pekerjaan | CR dapat dikaitkan dengan `bimObjectId` dan aktivitas terkait |

Keterbatasan pendekatan WBS adalah kualitas analisis sangat bergantung pada konsistensi pengisian kode. Jika pengguna memilih WBS yang tidak tepat, rekomendasi dan rekapitulasi sistem juga dapat menjadi kurang akurat. Oleh karena itu, penggunaan WBS perlu didukung oleh daftar referensi yang jelas, pelatihan pengguna, serta validasi data pada tahap pengajuan.

### X.1.3 Manajemen Risiko

Manajemen risiko digunakan untuk mengidentifikasi, menilai, dan mengendalikan ketidakpastian yang dapat memengaruhi tujuan proyek. Dalam sistem ini, risiko menjadi dimensi penting karena tidak semua perubahan memiliki tingkat urgensi yang sama. Perubahan dengan dampak biaya kecil dan risiko rendah tidak seharusnya diperlakukan sama dengan perubahan yang berpotensi mengganggu jalur kritis, keselamatan kerja, atau mutu struktur.

Secara konseptual, risiko dapat dinilai melalui kombinasi probabilitas dan dampak:

\[
R = P \times I
\]

dengan:

- \(R\) = skor risiko;
- \(P\) = probabilitas terjadinya risiko;
- \(I\) = besarnya dampak terhadap proyek.

Pada implementasi aplikasi, skor risiko disimpan pada kolom `risk`, sedangkan konteksnya dijelaskan melalui `riskCategory`, `riskVariable`, dan `riskDescription`. Pemisahan antara skor, kategori, variabel, dan deskripsi dipilih karena angka risiko saja tidak cukup menjelaskan sumber permasalahan. Skor menunjukkan prioritas, sedangkan kategori dan variabel risiko menjelaskan mengapa perubahan tersebut perlu diperhatikan.

| Rentang Skor | Klasifikasi | Makna Manajerial | Sikap Evaluasi |
|---|---|---|---|
| 1-2 | Aman | Dampak perubahan relatif kecil dan dapat dipantau dalam proses normal | Dicatat dan dimonitor |
| 3-4 | Rendah | Terdapat risiko, tetapi belum memerlukan eskalasi khusus | Dievaluasi melalui alur standar |
| 5-6 | Sedang | Risiko mulai memengaruhi sebagian target proyek | Memerlukan verifikasi teknis dan dokumen pendukung |
| 7-8 | Tinggi | Risiko berpotensi menimbulkan dampak signifikan terhadap waktu, biaya, mutu, atau K3 | Diprioritaskan untuk review PM dan mitigasi |
| 9-10 | Kritis | Risiko dapat mengganggu sasaran utama proyek atau membutuhkan keputusan strategis | Perlu eskalasi dan analisis menyeluruh |

Pendekatan numerik membantu dashboard menampilkan prioritas secara cepat, tetapi pendekatan ini juga memiliki kelemahan. Skor risiko dapat bersifat subjektif apabila probabilitas dan dampak tidak didefinisikan secara konsisten. Oleh karena itu, sistem melengkapi skor dengan deskripsi risiko dan rekomendasi berbasis *knowledge repository*. Tujuannya bukan menggantikan penilaian profesional, melainkan mengurangi ketergantungan pada intuisi yang tidak terdokumentasi.

### X.1.4 Sistem Pendukung Keputusan dan Sistem Pakar

Sistem pendukung keputusan atau *Decision Support System* merupakan sistem berbasis komputer yang membantu pengguna mengolah data menjadi informasi untuk pengambilan keputusan. Dalam sistem ini, dukungan keputusan diwujudkan melalui dashboard, skor risiko, visualisasi, dan *AI Recommendation Engine*. Istilah AI digunakan dalam pengertian terbatas, yaitu sebagai sistem rekomendasi berbasis aturan, bukan sebagai model pembelajaran mesin.

Pemilihan pendekatan berbasis aturan dilakukan karena konteks penelitian membutuhkan transparansi. Dalam pengambilan keputusan proyek konstruksi, pengguna perlu mengetahui dasar rekomendasi, bukan hanya menerima keluaran dari model yang sulit dijelaskan. Dengan mencocokkan kode WBS dan kode risiko, sistem dapat menunjukkan bahwa rekomendasi berasal dari relasi tertentu pada `knowledge_repository`. Pendekatan ini lebih mudah diaudit, lebih mudah diperbarui oleh pakar, dan lebih sesuai untuk tahap pengembangan awal ketika data historis belum cukup besar untuk melatih model prediktif.

| Alternatif Pendekatan | Kelebihan | Keterbatasan | Alasan Posisi Sistem |
|---|---|---|---|
| Penilaian manual penuh | Fleksibel dan bergantung pada pengalaman ahli | Tidak konsisten, sulit diaudit, lambat | Tidak cukup untuk kebutuhan dashboard dan basis data terpusat |
| Rule-based expert system | Transparan, mudah dijelaskan, dapat dikontrol pakar | Bergantung pada kelengkapan aturan | Dipilih sebagai pendekatan awal |
| Machine learning | Dapat menemukan pola historis | Membutuhkan data besar, validasi ketat, dan interpretabilitas | Dapat menjadi pengembangan lanjutan |
| Large Language Model | Mampu menghasilkan narasi dan ringkasan | Memerlukan kontrol akurasi, privasi, dan biaya | Belum digunakan dalam implementasi utama |

Dengan demikian, sistem mendudukkan rekomendasi sebagai informasi pendukung, bukan keputusan otomatis. Keputusan akhir tetap berada pada *Project Manager* karena konteks kontraktual, teknis, dan organisasi proyek tidak dapat sepenuhnya direduksi menjadi aturan database.

### X.1.5 Knowledge Management

*Knowledge management* digunakan untuk menangkap, menyimpan, dan menggunakan kembali pengalaman proyek. Dalam proyek konstruksi, pengetahuan sering melekat pada individu, misalnya pengalaman PM, pengawas, atau engineer dalam menghadapi perubahan tertentu. Jika pengalaman tersebut tidak didokumentasikan, organisasi dapat mengulang kesalahan yang sama pada proyek berikutnya.

Sistem membedakan dua bentuk pengetahuan. Pertama, `knowledge_repository` berisi pengetahuan referensial berupa kombinasi WBS, kode risiko, insight, dan saran. Kedua, `knowledge_base` berisi pengetahuan aktual yang berasal dari dokumen atau *lesson learned* proyek. Pembedaan ini penting karena rekomendasi awal dan pengalaman aktual memiliki sifat yang berbeda. Repository bersifat normatif dan disusun sebagai rujukan awal, sedangkan knowledge base bersifat historis dan merekam kejadian nyata.

| Lapisan Pengetahuan | Karakter | Peran dalam Sistem |
|---|---|---|
| `knowledge_repository` | Referensi WBS-risiko yang disusun sebelumnya | Menyediakan insight dan saran saat CR direview |
| `knowledge_base` | Lesson learned dan dokumen pengetahuan proyek | Menyimpan pengalaman aktual setelah keputusan |
| `associatedKnowledge` pada CR | Penghubung antara perubahan dan dokumen pengetahuan | Menjaga keterlacakan keputusan dan pembelajaran |

Keputusan untuk membuat *lesson learned* otomatis dari CR yang disetujui memiliki nilai strategis. Sistem tidak menunggu proses dokumentasi manual di akhir proyek, melainkan membangun pengetahuan saat peristiwa perubahan masih segar dan datanya masih tersedia. Namun, pendekatan ini tetap membutuhkan validasi manusia agar *lesson learned* yang terbentuk tidak hanya lengkap secara data, tetapi juga benar secara substansi.

### X.1.6 Building Information Modeling

Building Information Modeling atau BIM merupakan representasi digital dari informasi fisik dan fungsional suatu aset konstruksi. Dalam sistem ini, BIM digunakan sebagai konteks visual untuk mengaitkan *Change Request* dengan elemen model. Integrasi ini penting karena perubahan konstruksi tidak hanya bersifat administratif, tetapi juga melekat pada lokasi dan objek fisik tertentu.

Pemilihan BIM sebagai bagian dari sistem didasarkan pada kebutuhan keterlacakan visual. Dalam laporan berbasis teks, pengguna harus membayangkan lokasi atau objek terdampak dari uraian WBS dan lokasi. Dengan BIM, pengguna dapat melihat objek yang terkait, membaca properti elemen, dan menelusuri CR yang berhubungan dengan `bimObjectId`. Hal ini dapat meningkatkan pemahaman lintas aktor, terutama ketika PM atau Admin tidak berada langsung di lapangan.

Sistem menggunakan dua mode visualisasi:

| Mode Visualisasi | Teknologi | Pertimbangan Pemilihan | Keterbatasan |
|---|---|---|---|
| Autodesk BIM / APS | Autodesk Platform Services dan Autodesk Viewer | Mendukung model BIM asli yang telah diterjemahkan menjadi *viewable* dan dapat mengambil properti elemen | Bergantung pada konfigurasi APS, token, URN, ukuran model, dan parameter objek |
| Three.js | Visualisasi 3D berbasis JavaScript | Memberi mode visual cadangan ketika model APS belum tersedia atau tidak dapat dimuat | Tidak menggantikan kelengkapan informasi model BIM asli |

Integrasi BIM menggunakan `bimObjectId` sebagai penghubung antara data CR dan objek visual. Mekanisme ini sengaja dibuat eksplisit karena pencocokan otomatis berbasis geometri atau semantik model membutuhkan kompleksitas yang lebih tinggi. Dengan `bimObjectId`, relasi data lebih mudah dijelaskan dan diaudit, meskipun tetap membutuhkan kedisiplinan pemberian parameter pada model.

### X.1.7 Software Development Life Cycle

Pengembangan sistem mengikuti pendekatan iteratif yang selaras dengan *Rapid Application Development* atau RAD. Model ini dipilih karena kebutuhan aplikasi pengelolaan perubahan proyek cenderung berkembang setelah pengguna melihat alur kerja nyata. Pada tahap awal, pengguna sering kali belum dapat mendefinisikan seluruh kebutuhan secara lengkap, terutama untuk dashboard, form dampak, dan kebutuhan visualisasi. Oleh karena itu, pendekatan iteratif lebih sesuai dibandingkan pendekatan linear yang kaku.

RAD juga dipilih karena sistem yang dikembangkan berorientasi pada aplikasi operasional dengan banyak komponen antarmuka. Keputusan ini memungkinkan evaluasi dilakukan secara bertahap terhadap halaman login, dashboard Admin, dashboard PM, form Site Engineer, knowledge base, dan viewer BIM. Namun, pendekatan RAD memiliki risiko, yaitu dokumentasi dan arsitektur dapat menjadi kurang konsisten jika iterasi tidak dikendalikan. Untuk mengurangi risiko tersebut, bab ini memformalkan kembali hasil pengembangan ke dalam arsitektur, struktur data, alur proses, dan pengujian.

### X.1.8 Kerangka Standardisasi yang Mendasari Sistem

Pengembangan sistem informasi pengendalian perubahan perlu ditempatkan dalam kerangka standardisasi agar rancangan tidak hanya menjawab kebutuhan teknis aplikasi, tetapi juga dapat dipertanggungjawabkan sebagai instrumen tata kelola proyek. Standardisasi tidak digunakan sebagai salinan prosedur, melainkan sebagai rujukan konseptual untuk membangun konsistensi istilah, alur keputusan, struktur data, dan evaluasi sistem.

Rujukan manajemen proyek yang relevan adalah *PMBOK Guide* dari Project Management Institute dan ISO 21502. PMBOK menekankan nilai, tata kelola, domain kinerja, risiko, pemangku kepentingan, dan penyesuaian praktik sesuai konteks proyek. ISO 21502 memberikan panduan umum manajemen proyek yang dapat diterapkan pada berbagai organisasi, jenis proyek, pendekatan delivery, ukuran, biaya, dan durasi. Dalam sistem yang dikembangkan, kedua rujukan tersebut diterjemahkan menjadi kebutuhan agar setiap perubahan memiliki pemilik proses, status, bukti, dampak, mekanisme review, dan hubungan dengan tujuan proyek.

Pada dimensi risiko, ISO 31000 digunakan sebagai kerangka prinsip karena menempatkan risiko sebagai bagian dari tata kelola, strategi, perencanaan, pelaporan, kebijakan, nilai, dan budaya organisasi. Oleh sebab itu, risiko dalam sistem tidak hanya disimpan sebagai angka, tetapi juga sebagai kategori, variabel, deskripsi, rekomendasi, dan bahan diskusi keputusan. Dengan demikian, sistem mendukung proses identifikasi, analisis, evaluasi, perlakuan, pemantauan, dan komunikasi risiko secara lebih terstruktur.

Pada dimensi informasi konstruksi dan BIM, ISO 19650 menjadi rujukan untuk memahami bahwa BIM bukan sekadar model visual, tetapi bagian dari manajemen informasi aset terbangun. Prinsip yang relevan adalah kebutuhan informasi yang jelas, pertukaran informasi, pengelolaan versi, dan penggunaan informasi sebagai dasar keputusan. Sistem ini belum menggantikan *Common Data Environment* penuh, tetapi mengadopsi sebagian prinsipnya melalui penyimpanan terpusat, identitas CR, keterkaitan WBS, bukti, dan `bimObjectId`.

Pada dimensi kualitas, ISO 9001 dan ISO 10006 mendukung pemahaman bahwa sistem proyek perlu memiliki proses yang terdokumentasi, konsisten, dapat diperiksa, dan mengalami perbaikan berkelanjutan. Sistem yang dikembangkan mengarah pada kualitas proses dengan cara menstandardisasi input, status, approval, dashboard, dan lesson learned. Sementara itu, ISO/IEC 27001 digunakan sebagai rujukan awal untuk keamanan informasi, terutama karena sistem menyimpan data proyek, akun pengguna, bukti lapangan, dan kredensial integrasi eksternal.

Pada dimensi rekayasa perangkat lunak, ISO/IEC/IEEE 12207 dapat digunakan untuk menempatkan kegiatan pengembangan, operasi, pemeliharaan, dan evaluasi sistem ke dalam siklus hidup perangkat lunak. Untuk kualitas produk perangkat lunak, ISO/IEC 25010 memberi dasar konseptual agar sistem tidak hanya dinilai dari "berjalan atau tidak", tetapi juga dari kesesuaian fungsi, efisiensi kinerja, kompatibilitas, usability, reliabilitas, keamanan, maintainability, dan portabilitas. Untuk antarmuka, ISO 9241-210 dan WCAG 2.2 mendukung rancangan yang berpusat pada pengguna, mudah dipahami, dan lebih inklusif.

| Domain Standardisasi | Rujukan | Prinsip yang Diadopsi | Implementasi dalam Sistem |
|---|---|---|---|
| Manajemen proyek | PMBOK Guide, ISO 21502 | Tata kelola, value delivery, domain kinerja, stakeholder, tailoring | Role Admin/PM/SE, dashboard, approval, laporan, pengendalian CR |
| Risiko | ISO 31000 | Identifikasi, analisis, evaluasi, treatment, monitoring, komunikasi | Skor risiko, kategori, variabel, deskripsi, rekomendasi, risk matrix |
| Kualitas proyek | ISO 9001, ISO 10006 | Konsistensi proses, dokumentasi, perbaikan berkelanjutan | Standardisasi form CR, status, bukti, review, lesson learned |
| BIM dan informasi aset | ISO 19650, IFC/ISO 16739 | Informasi sebagai dasar keputusan, interoperabilitas, keterlacakan objek | `bimObjectId`, APS properties, viewer BIM, hubungan model-CR |
| Keamanan informasi | ISO/IEC 27001 | Kerahasiaan, integritas, ketersediaan, kontrol risiko informasi | Session, `.env`, `.htaccess`, PDO, pembatasan helper backend |
| Rekayasa perangkat lunak | ISO/IEC/IEEE 12207, ISO/IEC 25010 | Siklus hidup software dan kualitas produk | RAD terkontrol, pengujian, maintainability, portabilitas |
| Usability dan aksesibilitas | ISO 9241-210, WCAG 2.2 | Rancangan berpusat pada pengguna dan aksesibilitas web | Form per role, feedback status, navigasi, visual dashboard |

## X.2 Metodologi Pengembangan Sistem

### X.2.1 Model Pengembangan

Model pengembangan yang digunakan adalah RAD dengan pendekatan iteratif. Pada tahap awal, pengembangan difokuskan pada pembentukan alur pengguna dan antarmuka aplikasi utama. Setelah alur pengguna terbentuk, sistem dihubungkan dengan backend PHP, basis data MySQL, validasi role, dan layanan dashboard. Selanjutnya, sistem diperluas dengan knowledge base, S-Curve, approval, serta integrasi BIM.

Pemilihan RAD tidak berarti bahwa sistem dikembangkan tanpa struktur. RAD digunakan karena sesuai dengan sifat kebutuhan yang berubah, tetapi setiap iterasi tetap dievaluasi terhadap kebutuhan fungsional, kebutuhan nonfungsional, dan konsistensi data. Dengan kata lain, RAD dipakai sebagai strategi pengembangan cepat, sedangkan struktur akademik sistem tetap dijaga melalui pemodelan data, pemetaan fitur, dan pengujian.

| Tahap RAD | Aktivitas | Pertimbangan Kritis | Artefak Sistem |
|---|---|---|---|
| Requirement planning | Mengidentifikasi aktor, proses CR, kebutuhan approval, risiko, WBS, BIM, dan knowledge base | Kebutuhan pengguna lapangan dan manajemen sering berbeda, sehingga perlu dipetakan per role | Daftar kebutuhan fungsional dan nonfungsional |
| User design | Merancang halaman Admin, PM, Site Engineer, modal detail, form CR, dashboard, dan tabel | Desain harus cukup lengkap untuk data proyek, tetapi tetap mudah dipakai di lapangan | Rancangan antarmuka aplikasi utama |
| Construction | Mengimplementasikan API PHP, koneksi PDO, query dashboard, penyimpanan CR, approval, dan knowledge base | Integrasi data harus menjaga konsistensi status, WBS, risiko, dan assignment proyek | File HTML, JavaScript, PHP API, dan database MySQL |
| Cutover | Melakukan pengujian fungsi, validasi role, pengujian data, dan perbaikan | Sistem perlu diuji bukan hanya apakah berjalan, tetapi juga apakah alurnya sesuai proses proyek | Sistem web yang siap dievaluasi pengguna |

### X.2.2 Kebutuhan Pengguna

Kebutuhan pengguna dianalisis berdasarkan peran karena setiap aktor memiliki tanggung jawab berbeda dalam proses perubahan proyek. Admin membutuhkan kendali data dan konfigurasi sistem. Project Manager membutuhkan informasi ringkas tetapi cukup mendalam untuk mengambil keputusan. Site Engineer membutuhkan form yang memungkinkan pencatatan kondisi lapangan secara cepat dan lengkap.

Pembagian role juga dipilih untuk menghindari akses data yang terlalu terbuka. Dalam proyek konstruksi, tidak semua pengguna perlu melihat atau mengubah seluruh data. Pembatasan akses diperlukan untuk menjaga integritas keputusan dan mengurangi risiko perubahan data yang tidak sah.

| Aktor | Kebutuhan Utama | Fitur Sistem | Alasan Kebutuhan |
|---|---|---|---|
| Admin | Mengelola proyek, pengguna, assignment, CR, knowledge base, dan laporan | `admin.html`, `api_user_management.php`, `api_add_project.php`, `api_save_project.php`, `api_knowledge_base.php` | Admin bertanggung jawab pada tata kelola data dan konfigurasi akses |
| Project Manager | Meninjau CR, membaca risiko, melihat rekomendasi, memberi keputusan, memantau dampak biaya/waktu | `project-manager.html`, `api_get_dashboard_stats.php`, `save_approval.php`, `api_pm_knowledge_base.php` | PM membutuhkan informasi yang sudah diringkas tetapi tetap dapat ditelusuri |
| Site Engineer | Mengajukan CR, mengisi WBS, risiko, dampak, bukti, lokasi, dan objek BIM | `site-engineer.html`, `save_data.php`, `update_data.php`, `get_next_id.php` | SE menjadi sumber data lapangan sehingga form harus mendukung bukti dan detail teknis |

### X.2.3 Kebutuhan Fungsional

Kebutuhan fungsional dirancang berdasarkan alur perubahan dari pengajuan hingga pembelajaran organisasi. Setiap fungsi dipetakan ke komponen implementasi agar hubungan antara kebutuhan dan sistem dapat ditelusuri.

| Kode | Kebutuhan Fungsional | Implementasi | Pertimbangan |
|---|---|---|---|
| F-01 | Login dan pengenalan role pengguna | `api_login.php`, sesi PHP | Diperlukan agar akses sistem tidak terbuka untuk semua pengguna |
| F-02 | Manajemen data proyek | `api_add_project.php`, `api_save_project.php`, `api_update_project.php`, tabel `projects` | Proyek menjadi konteks utama seluruh data CR |
| F-03 | Manajemen pengguna dan assignment proyek | `api_user_management.php`, tabel `users`, `project_assignments` | Mencegah pengguna mengakses proyek yang bukan tanggung jawabnya |
| F-04 | Input Change Request | Form Site Engineer dan `save_data.php` | Menangkap data lapangan secara terstruktur |
| F-05 | Tampilan daftar dan detail CR | Tabel CR, modal detail, `api_get_change_requests.php` | Memudahkan review dan pelacakan status |
| F-06 | Approval CR oleh PM | Modal approval dan `save_approval.php` | Menjamin keputusan dicatat formal |
| F-07 | Rekomendasi berbasis WBS dan risiko | Query `LEFT JOIN` dengan `knowledge_repository` | Memberikan dukungan keputusan yang dapat dijelaskan |
| F-08 | Knowledge base dan lesson learned | `api_knowledge_base.php`, `knowledge_base_auto.php` | Mengubah pengalaman proyek menjadi pengetahuan |
| F-09 | Dashboard statistik proyek | `api_get_dashboard_stats.php`, Chart.js | Membantu monitoring cepat dan prioritisasi |
| F-10 | S-Curve proyek | `api_get_scurve.php`, tabel `project_scurve` | Menghubungkan CR dengan kinerja progres proyek |
| F-11 | BIM object linkage | `bimObjectId`, `api_aps_token.php`, `api_aps_properties.php` | Memberikan konteks visual terhadap objek terdampak |
| F-12 | Upload bukti lapangan | Kolom `photoEvidence` pada `change_requests` | Menjaga bukti pendukung tetap terhubung dengan CR |

### X.2.4 Kebutuhan Nonfungsional

Kebutuhan nonfungsional menjadi penting karena keberhasilan sistem tidak hanya ditentukan oleh kelengkapan fitur. Sistem harus aman, dapat digunakan, cukup responsif, dan dapat dijalankan pada lingkungan pengembangan maupun hosting.

| Aspek | Kebutuhan | Implementasi | Pertimbangan Kritis |
|---|---|---|---|
| Keamanan akses | Pengguna hanya mengakses data sesuai role dan assignment | Validasi `$_SESSION`, pengecekan `project_assignments` | Data proyek bersifat sensitif dan keputusan approval harus terlindungi |
| Keamanan database | Mengurangi risiko SQL Injection | PDO dan *prepared statement* | Input pengguna berasal dari form dan parameter API |
| Portabilitas | Dapat berjalan lokal dan hosting | Konfigurasi hybrid pada `db_user.php` | Mendukung pengembangan lokal dan implementasi daring |
| Responsivitas | Halaman dapat digunakan pada berbagai ukuran layar | CSS responsif pada halaman HTML | Pengguna lapangan dapat memakai perangkat berbeda |
| Keterbacaan data | Informasi risiko harus cepat dipahami | Badge status, grafik, tabel, KPI | Dashboard harus membantu pemindaian cepat, bukan menambah beban kognitif |
| Skalabilitas data | Query tetap dapat dioptimalkan saat data bertambah | Indeks dan struktur relasional | Join WBS-risiko berpotensi berat jika data terus meningkat |
| Ketersediaan visual BIM | Viewer tetap berguna saat APS tidak siap | Fallback Three.js | Sistem tidak boleh sepenuhnya bergantung pada layanan eksternal |

### X.2.5 Traceability Kebutuhan, Standar, dan Implementasi

Keterlacakan (*traceability*) digunakan untuk memastikan bahwa setiap fitur sistem memiliki dasar kebutuhan dan dapat ditelusuri ke implementasi. Dalam pengembangan aplikasi untuk konteks proyek konstruksi, traceability penting karena sistem tidak hanya dinilai dari keberadaan fitur, tetapi juga dari kemampuannya mendukung proses manajerial yang dapat diaudit. Oleh karena itu, kebutuhan pengguna, standar pendukung, tabel database, endpoint API, dan keluaran antarmuka dipetakan secara eksplisit.

| Kebutuhan | Rujukan Konseptual | Data/Endpoint | Keluaran Sistem | Indikator Evaluasi |
|---|---|---|---|---|
| Pengajuan CR terstruktur | PMBOK, ISO 21502, ISO 10006 | `change_requests`, `backend/save_data.php` | CR baru dengan WBS, risiko, dampak, bukti | Kelengkapan data dan keberhasilan penyimpanan |
| Review dan approval | PMBOK, ISO 21502 | `backend/save_approval.php` | Status `PENDING`, `APPROVED`, `REJECTED` | Konsistensi status dan catatan keputusan |
| Penilaian risiko | ISO 31000 | `risk`, `riskCategory`, `riskVariable` | Badge risiko, risk matrix, prioritas review | Kesesuaian klasifikasi dan interpretasi pengguna |
| Dashboard pengendalian | PMBOK, ISO 10006 | `backend/api_get_dashboard_stats.php` | KPI, chart, ringkasan dampak | Kecepatan pemantauan dan pemahaman informasi |
| Keterkaitan WBS | PMBOK, ISO 21502 | `wbsLevel4`, `wbsLevel5`, `wbsLevel6` | Rekap WBS terdampak dan filter | Konsistensi kode dan kemampuan agregasi |
| Knowledge base | ISO 9001, ISO 10006 | `knowledge_repository`, `knowledge_base` | Insight, saran, lesson learned | Kegunaan rekomendasi dan keterlacakan pembelajaran |
| BIM linkage | ISO 19650, IFC/ISO 16739 | `bimObjectId`, `backend/api_aps_properties.php` | Objek BIM dan CR terkait | Ketepatan pencocokan parameter model |
| Keamanan data | ISO/IEC 27001 | Session, `.env`, `.htaccess`, PDO | Pembatasan akses dan proteksi konfigurasi | Tidak bocornya file sensitif dan role berjalan |
| Usability | ISO 9241-210, WCAG 2.2 | Halaman per role | Form, navigasi, feedback visual | SUS, UAT, dan observasi skenario |

Traceability tersebut juga berguna untuk membatasi klaim. Misalnya, sistem dapat diklaim mendukung manajemen risiko karena menyediakan struktur input, klasifikasi, visualisasi, dan rekomendasi; tetapi belum dapat diklaim sebagai sistem manajemen risiko organisasi yang sepenuhnya memenuhi ISO 31000 karena belum mencakup seluruh kebijakan, kultur, dan proses enterprise. Demikian pula, sistem mendukung prinsip manajemen informasi BIM, tetapi belum menjadi CDE penuh seperti yang lazim dibahas dalam ISO 19650.

### X.2.6 Posisi Artefak Sistem sebagai Produk Riset Terapan

Artefak sistem dikembangkan sebagai media untuk menguji bagaimana proses pengendalian perubahan dapat ditingkatkan melalui integrasi data digital. Posisi ini penting karena aplikasi bukan sekadar produk perangkat lunak, melainkan instrumen konseptual untuk memformalkan proses pengajuan, review, approval, dan pembelajaran proyek.

Terdapat tiga kontribusi utama dari artefak sistem. Pertama, kontribusi proses, yaitu perubahan yang sebelumnya tersebar dalam dokumen dan komunikasi informal dipetakan ke alur digital yang konsisten. Kedua, kontribusi data, yaitu WBS, risiko, BIM object, bukti, dan keputusan disimpan sebagai atribut yang dapat dianalisis. Ketiga, kontribusi pengetahuan, yaitu data perubahan yang disetujui dapat dikonversi menjadi lesson learned sehingga pengalaman proyek tidak hilang setelah keputusan dibuat.

Dengan kerangka tersebut, evaluasi sistem tidak cukup hanya menggunakan pengujian fungsi. Evaluasi perlu mencakup apakah sistem meningkatkan keterlacakan perubahan, memperjelas prioritas risiko, mempercepat akses informasi, mendukung review manajerial, dan membentuk basis pengetahuan proyek. Oleh karena itu, pengujian black-box, UAT, SUS, dan analisis traceability dipakai secara saling melengkapi.

## X.3 Arsitektur Sistem

### X.3.1 Gambaran Umum Arsitektur

Sistem menggunakan arsitektur *client-server* karena pola ini sesuai untuk aplikasi web yang diakses oleh banyak pengguna dengan data terpusat. Pengguna berinteraksi melalui browser, sedangkan pemrosesan data dan penyimpanan dilakukan pada server. Arsitektur ini dipilih karena memudahkan kontrol akses, pemeliharaan kode, dan konsistensi data dibandingkan penyimpanan berbasis file lokal.

Alur umum arsitektur adalah:

**Pengguna -> Browser -> HTML/CSS/JavaScript -> API PHP -> PDO -> MySQL -> JSON -> Browser**

Untuk fitur BIM, alur diperluas menjadi:

**Browser -> API APS Token -> Autodesk Platform Services -> Model Properties -> Pencocokan `bimObjectId` -> Change Request lokal**

Penggunaan API PHP yang mengembalikan JSON dipilih karena memisahkan logika tampilan dari logika data. Pemisahan ini membuat halaman dapat memperbarui tabel, grafik, atau modal secara asinkron tanpa memuat ulang seluruh halaman. Namun, pola ini juga menuntut pengelolaan validasi yang lebih disiplin karena setiap endpoint API dapat menjadi pintu masuk data.

### X.3.2 Lapisan Sistem

Sistem dibagi ke dalam beberapa lapisan agar tanggung jawab tiap komponen lebih jelas. Pembagian lapisan ini bukan hanya berguna untuk dokumentasi, tetapi juga untuk mengidentifikasi risiko teknis. Misalnya, kegagalan pada APS tidak seharusnya membuat fungsi approval tidak berjalan, karena keduanya berada pada lapisan dan tujuan yang berbeda.

| Lapisan | Komponen | Fungsi | Pertimbangan Desain |
|---|---|---|---|
| Presentation layer | `admin.html`, `project-manager.html`, `site-engineer.html`, `login.html`, `index.html` | Menampilkan UI, form, tabel, dashboard, modal, dan viewer | File HTML terpisah per role memudahkan penyesuaian alur pengguna |
| Client logic layer | JavaScript ES6+, Fetch API, Chart.js, Three.js, Autodesk Viewer | Mengambil data, merender tabel, grafik, badge, modal, dan visual BIM | Vanilla JavaScript dipilih agar ringan dan mudah dideploy tanpa build process |
| Application layer | API PHP | Menangani request, validasi, otorisasi, query, dan format JSON | PHP sesuai dengan lingkungan hosting umum dan integrasi MySQL |
| Data access layer | PDO pada `db_user.php` | Menghubungkan PHP dengan MySQL | PDO mendukung prepared statement dan konfigurasi koneksi yang lebih aman |
| Data layer | MySQL | Menyimpan proyek, pengguna, assignment, CR, S-Curve, repository, dan knowledge base | RDBMS sesuai untuk data terstruktur dan relasi antarentitas |
| External service layer | Autodesk Platform Services | Memberikan token viewer dan properti objek model BIM | Layanan eksternal dipakai untuk kapabilitas BIM yang sulit direplikasi lokal |

### X.3.3 Komponen Frontend

Komponen frontend disusun berdasarkan peran pengguna. Pemisahan halaman Admin, Project Manager, dan Site Engineer dipilih karena masing-masing role memiliki kepadatan informasi dan tindakan yang berbeda. Jika seluruh fungsi digabungkan dalam satu halaman, antarmuka berisiko menjadi terlalu kompleks dan membingungkan. Dengan pemisahan ini, setiap halaman dapat dioptimalkan sesuai konteks kerja penggunanya.

| File | Peran | Pengguna | Pertimbangan |
|---|---|---|---|
| `login.html` | Halaman autentikasi | Semua pengguna | Menjadi gerbang awal sebelum akses role |
| `index.html` | Halaman awal aplikasi | Semua pengguna | Memberi titik masuk sebelum pengguna diarahkan ke fitur utama |
| `admin.html` | Dashboard admin, project registry, knowledge base, user management, report | Admin | Admin membutuhkan kontrol data lintas proyek |
| `project-manager.html` | Dashboard review, antrian CR, approval, insight risiko | Project Manager | PM membutuhkan tampilan prioritas dan keputusan |
| `site-engineer.html` | Riwayat CR, form pengajuan, detail CR, bukti lapangan, viewer BIM | Site Engineer | SE membutuhkan form detail dan akses bukti lapangan |

Artefak eksplorasi desain yang tidak tersambung dengan environment utama tidak dimasukkan sebagai bagian dari arsitektur implementasi. Batasan ini penting agar dokumentasi sistem hanya membahas komponen yang benar-benar menjadi bagian dari alur aplikasi utama, sehingga analisis tidak mencampurkan komponen operasional dengan artefak percobaan.

### X.3.4 Komponen Backend/API

Backend disusun sebagai kumpulan endpoint PHP. Pendekatan ini dipilih karena sederhana, mudah dipetakan dengan kebutuhan fitur, dan sesuai untuk aplikasi yang tidak memerlukan framework berat. Akan tetapi, pendekatan banyak endpoint juga memiliki konsekuensi: konsistensi validasi, format respons, dan pengelolaan error harus dijaga agar tidak berbeda antarfile.

| File API | Fungsi Utama | Pertimbangan Kritis |
|---|---|---|
| `api_login.php` | Autentikasi pengguna dan pembentukan sesi | Menjadi dasar seluruh kontrol akses |
| `api_logout.php` | Mengakhiri sesi pengguna | Mengurangi risiko sesi aktif tertinggal |
| `api_get_projects.php` | Mengambil daftar proyek sesuai role/assignment | Mencegah PM/SE melihat proyek yang tidak terkait |
| `api_add_project.php` | Menambahkan proyek | Digunakan oleh Admin untuk memperluas data proyek |
| `api_save_project.php` | Menyimpan data proyek | Memastikan registry proyek terpusat |
| `api_update_project.php` | Memperbarui data proyek | Mendukung perubahan status dan progres proyek |
| `api_get_project_detail.php` | Mengambil detail proyek | Menjadi basis modal/detail dashboard |
| `api_get_dashboard_stats.php` | Menghasilkan statistik dashboard, pending CR, distribusi risiko, WBS terdampak, adendum, risk matrix, dan data visual | Endpoint kompleks yang menggabungkan data operasional dan analitik |
| `api_get_change_requests.php` | Mengambil daftar CR dengan insight dan saran dari knowledge repository | Memperlihatkan integrasi transaksi dan pengetahuan |
| `save_data.php` | Menyimpan pengajuan CR dari Site Engineer | Endpoint kritis karena menerima input paling banyak |
| `update_data.php` | Memperbarui data CR | Perlu menjaga agar perubahan tidak merusak histori |
| `save_approval.php` | Menyimpan keputusan PM dan memicu pembuatan lesson learned jika CR disetujui | Menghubungkan keputusan dengan pembelajaran |
| `api_knowledge_base.php` | Menampilkan, menyimpan, menghapus, dan menyinkronkan knowledge base | Mengelola pengetahuan eksplisit proyek |
| `knowledge_base_auto.php` | Fungsi pembentukan dokumen lesson learned otomatis | Mengurangi kehilangan pengetahuan setelah approval |
| `api_get_scurve.php` | Mengambil data S-Curve dan total dampak keterlambatan CR approved | Menghubungkan perubahan dengan progres |
| `api_aps_token.php` | Mengambil token Autodesk Platform Services secara server-side | Kredensial APS tidak dibuka langsung ke client |
| `api_aps_properties.php` | Mengambil properti elemen BIM dan mencocokkannya dengan CR lokal | Menghubungkan model BIM dengan data lokal |
| `api_user_management.php` | Manajemen pengguna dan assignment proyek | Menopang tata kelola akses |

### X.3.5 Alur Data Change Request

Alur data *Change Request* dirancang sebagai siklus dari data lapangan menuju keputusan dan pengetahuan. Siklus ini penting karena perubahan proyek tidak selesai ketika data disimpan; perubahan baru bermakna setelah dievaluasi, diberi keputusan, dan hasilnya dapat dipelajari kembali.

| Tahap | Aktor/Komponen | Data Masuk | Proses | Data Keluar | Risiko yang Dikendalikan |
|---|---|---|---|---|---|
| Pengajuan | Site Engineer | WBS, kategori perubahan, risiko, dampak, lokasi, bukti, objek BIM | Validasi form dan penyimpanan | Baris baru pada `change_requests` | Kehilangan data lapangan |
| Review awal | Dashboard PM | Project ID dan status CR | Query CR pending dan join repository | Daftar CR dengan insight dan saran | Review tanpa konteks risiko |
| Evaluasi | Project Manager | Status keputusan, catatan, dampak biaya/waktu | Validasi keputusan dan update CR | Status `APPROVED`, `REJECTED`, atau `PENDING` | Keputusan tidak terdokumentasi |
| Pembelajaran | Backend | CR approved | Pencocokan repository dan pembuatan KM | Dokumen pada `knowledge_base` | Hilangnya pengalaman proyek |
| Monitoring | Admin/PM | Data CR, proyek, S-Curve | Rekap KPI, distribusi risiko, WBS terdampak | Dashboard visual dan tabel | Keterlambatan deteksi masalah |

### X.3.6 Struktur Deployment dan Pemisahan Aset

Struktur deployment sistem dirancang agar file publik, endpoint backend, dan konfigurasi server memiliki batas yang lebih jelas. Pada kondisi pengembangan terkini, seluruh file PHP ditempatkan dalam folder `backend/`, sedangkan file media publik ditempatkan dalam folder `assets/`. Pemisahan ini memberi keuntungan pada aspek maintainability, keamanan, dan keterbacaan struktur project.

```text
Browser
  -> HTML pages: index, login, admin, project-manager, site-engineer
  -> Public assets: assets/wylcore.png, assets/Video Home Page.mp4
  -> API endpoints: backend/api_*.php, backend/save_*.php, backend/get_*.php
  -> Database: MySQL through PDO
```

Aturan `.htaccess` digunakan untuk menyembunyikan ekstensi `.html` sehingga halaman dapat diakses dengan URL yang lebih bersih, misalnya `/login`, `/admin`, `/project-manager`, dan `/site-engineer`. Selain itu, directory listing dimatikan dan beberapa helper backend diblokir dari akses publik. Keputusan ini penting karena folder `backend/` memuat dua jenis file: endpoint yang memang harus dapat dipanggil oleh aplikasi, dan helper internal yang tidak boleh dibuka langsung oleh pengguna.

| Komponen | Lokasi | Status Akses | Alasan |
|---|---|---|---|
| Halaman HTML | Root project | Publik | Menjadi antarmuka pengguna |
| Asset media | `assets/` | Publik | Dibutuhkan browser untuk favicon, gambar, dan video |
| Endpoint API | `backend/api_*.php`, `backend/save_*.php`, `backend/get_*.php` | Terbatas secara logika aplikasi | Dipanggil oleh HTML melalui Fetch API |
| Helper database/env | `backend/db_user.php`, `backend/env.php` | Diblokir dari direct access | Mengandung logika koneksi dan konfigurasi |
| File rahasia | `.env`, `backend/.env` | Tidak masuk git dan diblokir server | Menyimpan kredensial server-side |
| Upload runtime | `uploads/` | Diabaikan git | Berisi bukti lapangan yang terbentuk saat aplikasi berjalan |

Pemisahan ini belum menggantikan kontrol keamanan aplikasi secara menyeluruh. Proteksi server perlu dilengkapi dengan validasi session, pembatasan role, validasi input, pembatasan ukuran upload, CSRF protection, audit trail, dan konfigurasi HTTPS pada deployment produksi. Namun, dari sisi struktur awal, pemisahan folder membantu mengurangi risiko file sensitif tercampur dengan aset publik.

## X.4 Perancangan Basis Data

### X.4.1 Entitas Utama

Basis data menggunakan MySQL dengan pendekatan relasional. RDBMS dipilih karena data sistem memiliki hubungan yang jelas, seperti pengguna dengan proyek, proyek dengan S-Curve, CR dengan proyek, serta knowledge base dengan repository. Pendekatan relasional juga memudahkan penerapan *primary key*, *foreign key*, dan indeks untuk menjaga integritas serta performa query.

| Tabel | Fungsi | Primary Key | Relasi Penting | Alasan Desain |
|---|---|---|---|---|
| `users` | Menyimpan akun dan role pengguna | `id` | Direferensikan oleh `project_assignments.user_id` | Role menjadi dasar otorisasi |
| `projects` | Menyimpan data proyek | `project_id` | Direferensikan oleh `project_assignments.project_id`, `project_scurve.project_id`, dan `change_requests.projectArea` | Proyek menjadi konteks utama seluruh data |
| `project_assignments` | Menyimpan pemetaan pengguna ke proyek dan role | `id` | Menghubungkan `users` dan `projects` | Mendukung akses berbasis penugasan |
| `change_requests` | Menyimpan seluruh pengajuan perubahan | `changeId` | Terhubung logis ke `projects`, `knowledge_repository`, dan `knowledge_base` | Menjadi tabel transaksi inti |
| `knowledge_repository` | Menyimpan kombinasi WBS-risiko-insight-saran | `id` | Direferensikan oleh `knowledge_base.repository_reference_id` | Menjadi basis aturan rekomendasi |
| `knowledge_base` | Menyimpan dokumen pengetahuan dan lesson learned | `docId` | Terhubung ke `knowledge_repository` dan `change_requests` | Menyimpan pembelajaran aktual |
| `project_scurve` | Menyimpan rencana dan realisasi progres kumulatif | `id` | Terhubung ke `projects` | Menyediakan konteks progres proyek |

### X.4.2 Struktur Data Change Request

Tabel `change_requests` merupakan inti sistem karena menyimpan peristiwa perubahan dari awal hingga keputusan. Struktur tabel dirancang cukup luas karena CR tidak hanya membutuhkan identitas, tetapi juga dampak multidimensi. Dampak biaya, waktu, lingkup, mutu, dan K3 disimpan secara terpisah agar analisis tidak tercampur dalam satu uraian bebas.

| Kelompok Data | Kolom | Keterangan | Pertimbangan |
|---|---|---|---|
| Identitas CR | `changeId`, `changeDate`, `submittedBy`, `projectArea` | Menunjukkan nomor, tanggal, pengaju, dan proyek | Dibutuhkan untuk penelusuran administratif |
| WBS | `wbsLevel4`, `wbsLevel5`, `wbsLevel6` | Menunjukkan aktivitas terdampak | Mendukung analisis hierarkis |
| Lokasi dan BIM | `locationFormat`, `location`, `bimObjectId` | Menghubungkan CR dengan lokasi fisik dan objek model | Menjembatani data lapangan dan model |
| Risiko | `riskCategory`, `riskVariable`, `riskDescription`, `risk` | Menyimpan kategori, kode, deskripsi, dan skor risiko | Menggabungkan angka prioritas dan konteks risiko |
| Deskripsi perubahan | `changeCategory`, `priority`, `description`, `descriptionDetail`, `ownerRequest`, `changeDrivers` | Menjelaskan latar belakang dan jenis perubahan | Membantu PM memahami alasan perubahan |
| Analisis dampak | `impactCost`, `impactTime`, `impactScope`, `impactQuality`, `impactSafety` | Menyimpan rincian dampak dalam format JSON | Fleksibel untuk jumlah item dampak yang berubah |
| Bukti | `photoEvidence` | Menyimpan referensi file bukti | Memperkuat validitas data lapangan |
| Approval | `status`, `reviewId`, `approvalDate`, `approvalNotes`, `timeImpact`, `costImpact` | Menyimpan keputusan PM dan dampak final | Menjaga jejak keputusan |
| Knowledge link | `associatedKnowledge` | Menghubungkan CR dengan dokumen knowledge base | Mendukung pembelajaran dan audit |

Pemakaian JSON pada beberapa kolom dampak memiliki keuntungan fleksibilitas, tetapi juga memiliki konsekuensi. Data JSON lebih sulit diindeks dan dianalisis dibandingkan tabel relasional terpisah. Pada tahap sistem saat ini, JSON dipilih karena variasi dampak cukup tinggi dan struktur input perlu tetap fleksibel. Untuk pengembangan lanjutan, data dampak dapat dinormalisasi ke tabel turunan jika kebutuhan analitik menjadi lebih kompleks.

### X.4.3 Struktur Knowledge Repository dan Knowledge Base

Desain knowledge repository dan knowledge base sengaja dipisahkan untuk membedakan pengetahuan referensial dan pengetahuan historis. Jika keduanya digabung, sistem akan sulit membedakan rekomendasi umum dari pengalaman aktual proyek. Pemisahan ini juga memudahkan proses validasi: repository dapat dikurasi sebagai basis aturan, sedangkan knowledge base dapat berkembang dari keputusan proyek.

| Aspek | `knowledge_repository` | `knowledge_base` |
|---|---|---|
| Tujuan | Basis aturan dan rekomendasi awal | Dokumentasi pengetahuan aktual proyek |
| Sumber data | Disusun dari kombinasi WBS dan risiko | Dibuat manual atau otomatis dari CR approved |
| Kunci utama | `id` | `docId` |
| Kolom penting | `wbs_kode`, `wbs_nama`, `risk_kode`, `risk_nama`, `risk_kategori`, `insight`, `saran` | `documentName`, `knowledgeCategory`, `validationStatus`, `changeRequestLink`, `actual_impact`, `applied_solution` |
| Peran dalam sistem | Memberi rekomendasi saat CR direview | Menyimpan lesson learned untuk rujukan masa depan |
| Risiko desain | Dapat usang jika tidak diperbarui | Dapat kurang valid jika tidak dikurasi |

### X.4.4 Relasi Data

Relasi data digunakan untuk menjaga keterlacakan antarentitas. Tidak semua hubungan dinyatakan sebagai *foreign key* fisik, terutama pada hubungan berbasis kode seperti WBS dan risiko. Beberapa relasi bersifat logis karena data berasal dari kode yang dicocokkan melalui query.

| Relasi | Jenis | Keterangan | Pertimbangan |
|---|---|---|---|
| `projects.project_id` -> `project_assignments.project_id` | One-to-many | Satu proyek dapat memiliki banyak assignment pengguna | Mendukung multi-user per proyek |
| `users.id` -> `project_assignments.user_id` | One-to-many | Satu pengguna dapat ditugaskan pada beberapa proyek | Mendukung fleksibilitas organisasi |
| `projects.project_id` -> `project_scurve.project_id` | One-to-many | Satu proyek memiliki beberapa periode S-Curve | Mendukung monitoring progres berkala |
| `change_requests.riskVariable` + `wbsLevel5` -> `knowledge_repository.risk_kode` + `wbs_kode` | Logical match | Digunakan untuk mengambil insight dan saran | Memberi rekomendasi berbasis konteks |
| `knowledge_repository.id` -> `knowledge_base.repository_reference_id` | One-to-many | Satu referensi repository dapat menghasilkan beberapa dokumen pengetahuan | Menghubungkan aturan dan lesson learned |
| `change_requests.changeId` -> `knowledge_base.changeRequestLink` | Logical link | CR approved dapat menjadi lesson learned | Menjaga jejak pembelajaran |

## X.5 Implementasi Sistem

### X.5.1 Implementasi Autentikasi dan Otorisasi

Autentikasi dilakukan melalui `api_login.php`, sedangkan status pengguna disimpan dalam sesi PHP. Pendekatan berbasis sesi dipilih karena sesuai dengan aplikasi web tradisional berbasis PHP dan relatif mudah diterapkan pada lingkungan hosting umum. Role pengguna terdiri atas Admin, Project Manager, dan Site Engineer.

Otorisasi tidak hanya berhenti pada role, tetapi juga memperhatikan assignment proyek. Hal ini penting karena dua pengguna dengan role yang sama belum tentu memiliki akses ke proyek yang sama. Misalnya, seorang Project Manager hanya boleh mengakses proyek yang ditugaskan kepadanya, kecuali Admin yang memiliki kewenangan lintas proyek.

| Role | Akses Data | Mekanisme Pembatasan | Risiko yang Dikurangi |
|---|---|---|---|
| Admin | Semua proyek, pengguna, assignment, CR, knowledge base | Validasi role Admin | Data governance tidak terpecah |
| Project Manager | Proyek yang diassign dan CR untuk direview | Cek `project_assignments` | PM tidak melihat data proyek di luar tanggung jawab |
| Site Engineer | Pengajuan dan riwayat CR sesuai proyek/akses | Cek sesi dan data form | Input lapangan lebih terkendali |

Keterbatasan implementasi berbasis sesi adalah perlunya pengaturan keamanan tambahan pada deployment, seperti konfigurasi cookie, HTTPS, timeout sesi, dan validasi CSRF untuk form sensitif. Dengan demikian, implementasi saat ini dapat dianggap sebagai fondasi yang perlu diperkuat jika sistem digunakan pada lingkungan produksi penuh.

### X.5.2 Implementasi Dashboard Admin

Dashboard Admin berfungsi sebagai pusat tata kelola sistem. Admin dapat mengelola proyek, pengguna, assignment, data CR, knowledge base, dan laporan. Pemusatan fungsi ini dipilih karena Admin bertanggung jawab pada integritas data dan konfigurasi akses, bukan pada keputusan teknis setiap CR.

Fitur Admin meliputi:

- project data registry;
- pengelolaan project assignment;
- user management;
- dashboard risiko global;
- knowledge base management;
- laporan dan rekapitulasi CR;
- viewer BIM read-only untuk konteks objek proyek.

Dari sudut pandang desain sistem, dashboard Admin perlu menyeimbangkan kelengkapan dan keteraturan. Admin memerlukan banyak fungsi, tetapi antarmuka yang terlalu padat dapat meningkatkan risiko kesalahan konfigurasi. Oleh karena itu, fungsi dikelompokkan ke dalam menu seperti project management, knowledge base, user management, dan report.

### X.5.3 Implementasi Dashboard Project Manager

Dashboard Project Manager dirancang untuk mendukung proses review dan pengambilan keputusan. PM tidak hanya membutuhkan daftar CR, tetapi juga membutuhkan indikator prioritas. Karena itu, data dashboard mencakup jumlah CR yang menunggu review, jumlah CR berisiko tinggi, jumlah approved, rejected, distribusi risiko, WBS terdampak, dan ringkasan dampak biaya/waktu.

Pemilihan format dashboard didasarkan pada kebutuhan PM untuk melakukan *scanning* cepat. Pada proyek aktif, PM tidak selalu memiliki waktu untuk membaca setiap detail CR dari awal. KPI dan grafik membantu mengarahkan perhatian pada CR yang paling penting. Namun, dashboard tidak boleh menggantikan detail; karena itu sistem tetap menyediakan modal atau tampilan detail untuk membaca uraian, bukti, WBS, risiko, dan rekomendasi.

| Data Dashboard | Sumber | Fungsi | Alasan Kritis |
|---|---|---|---|
| `underReviewCount` | CR status `PENDING` | Menunjukkan jumlah CR yang menunggu review | Mengukur beban keputusan PM |
| `needMitigationCount` | CR pending dengan risiko >= 7 | Menunjukkan CR prioritas tinggi | Mengarahkan perhatian pada risiko signifikan |
| `approvedCount` | CR status `APPROVED` | Menunjukkan CR yang telah disetujui | Menilai volume perubahan yang diterima |
| `rejectedCount` | CR status `REJECTED` | Menunjukkan CR yang ditolak | Mengindikasikan perubahan yang tidak layak |
| `pendingRequests` | Join CR dan repository | Menampilkan antrean review dengan insight | Menggabungkan data transaksi dan pengetahuan |
| `riskDistribution` | Agregasi skor risiko | Menampilkan komposisi risiko | Memudahkan pemantauan kondisi proyek |
| `wbsImpacted` | Rekap WBS | Menunjukkan area pekerjaan paling terdampak | Membantu prioritisasi koordinasi teknis |
| `addendumSummary` | CR approved | Menghitung kandidat adendum, dampak biaya, dan dampak waktu | Menghubungkan CR dengan konsekuensi kontraktual |
| `riskMatrix` | Data risiko CR | Menyediakan visualisasi matriks risiko | Membantu klasifikasi prioritas |

### X.5.4 Implementasi Site Engineer

Halaman Site Engineer digunakan untuk membuat dan mengelola pengajuan CR. Form input dibagi menjadi beberapa bagian agar pengguna lapangan dapat memasukkan data secara bertahap. Pembagian ini merupakan pertimbangan UX yang penting karena data CR cukup kompleks. Jika seluruh input ditampilkan tanpa struktur, pengguna dapat melewatkan data penting atau mengisi data secara tidak konsisten.

Data dampak biaya, waktu, lingkup, mutu, dan K3 disimpan dalam kolom JSON seperti `impactCost`, `impactTime`, `impactScope`, `impactQuality`, dan `impactSafety`. Pendekatan ini dipilih karena setiap CR dapat memiliki jumlah item dampak yang berbeda. Misalnya, satu CR mungkin hanya berdampak pada biaya, sedangkan CR lain berdampak pada waktu dan K3. Struktur JSON memberi fleksibilitas untuk menyimpan daftar item tanpa membuat banyak tabel tambahan pada tahap awal.

Namun, fleksibilitas JSON juga perlu dibaca secara kritis. Jika sistem kelak membutuhkan laporan rinci per item biaya atau per item mutu, maka struktur JSON dapat membatasi kemampuan query. Oleh karena itu, desain ini tepat untuk tahap awal aplikasi, tetapi dapat dikembangkan menjadi tabel relasional terpisah bila kebutuhan analitik meningkat.

### X.5.5 Implementasi AI Recommendation Engine

Rekomendasi sistem diperoleh melalui pencocokan data CR dengan `knowledge_repository`. Sistem menggunakan pendekatan `LEFT JOIN` berdasarkan `riskVariable` dan potongan kode `wbsLevel5`. Pola ini dipilih karena setiap rekomendasi seharusnya mempertimbangkan dua hal sekaligus: jenis risiko dan aktivitas pekerjaan yang terdampak.

```php
SELECT
    cr.changeId,
    cr.changeCategory,
    cr.risk,
    cr.wbsLevel5,
    cr.riskVariable,
    kr.risk_nama,
    kr.insight,
    kr.saran
FROM change_requests cr
LEFT JOIN knowledge_repository kr
    ON TRIM(cr.riskVariable) COLLATE utf8mb4_unicode_ci = TRIM(kr.risk_kode) COLLATE utf8mb4_unicode_ci
    AND SUBSTRING(TRIM(cr.wbsLevel5), 1, 7) COLLATE utf8mb4_unicode_ci = TRIM(kr.wbs_kode) COLLATE utf8mb4_unicode_ci
WHERE UPPER(cr.status) = 'PENDING'
ORDER BY cr.changeDate DESC;
```

Penggunaan `LEFT JOIN` merupakan keputusan desain yang penting. Jika menggunakan `INNER JOIN`, CR yang tidak memiliki pasangan repository tidak akan tampil, padahal CR tersebut tetap perlu direview. Dengan `LEFT JOIN`, sistem menjaga agar data transaksi tetap menjadi prioritas, sementara rekomendasi diperlakukan sebagai pelengkap. Jika rekomendasi tidak ditemukan, sistem dapat menampilkan pesan default agar PM tetap melakukan peninjauan mandiri.

Keterbatasan pendekatan ini terletak pada sensitivitas pencocokan kode. Perbedaan format WBS atau risk code dapat menyebabkan rekomendasi tidak muncul. Oleh karena itu, fungsi normalisasi kode, validasi input, dan kurasi repository menjadi bagian penting dari keberlanjutan sistem.

### X.5.6 Implementasi Approval dan Lesson Learned Otomatis

Approval dilakukan melalui `save_approval.php`. PM mengisi `reviewId`, `approvalDate`, `approvalNotes`, `costImpact`, `timeImpact`, dan keputusan. Sistem memvalidasi status agar hanya bernilai `PENDING`, `APPROVED`, atau `REJECTED`. Validasi status diperlukan karena status approval menjadi dasar dashboard, rekapitulasi, dan pembuatan *lesson learned*.

Jika status menjadi `APPROVED`, sistem memanggil fungsi pada `knowledge_base_auto.php` untuk:

1. mengambil data CR;
2. memastikan status CR adalah `APPROVED`;
3. mencari pasangan WBS dan risiko pada `knowledge_repository`;
4. membuat `docId` baru dengan format `KM-xxx`;
5. menyimpan deskripsi, dampak aktual, bukti, dan solusi;
6. memperbarui `associatedKnowledge` pada CR.

Alur ini menjadikan sistem sebagai mekanisme pembelajaran proyek. Secara kritis, otomasi ini memiliki manfaat dan risiko. Manfaatnya adalah pengetahuan tidak menunggu dokumentasi manual di akhir proyek. Risikonya adalah dokumen pengetahuan dapat terbentuk dari data yang belum cukup matang apabila proses review tidak disiplin. Oleh karena itu, knowledge base tetap perlu dikurasi melalui status validasi dan pemeriksaan Admin atau pakar.

### X.5.7 Implementasi S-Curve

S-Curve digunakan untuk membandingkan rencana dan realisasi progres kumulatif proyek. Data disimpan pada tabel `project_scurve` dengan kolom `periode_ke`, `tanggal_target`, `rencana_kumulatif`, dan `realisasi_kumulatif`. Pemilihan S-Curve didasarkan pada praktik umum pengendalian proyek konstruksi yang menggunakan kurva kumulatif untuk melihat deviasi progres.

API `api_get_scurve.php` juga menghitung total dampak keterlambatan dari CR yang telah disetujui melalui agregasi `timeImpact`. Hal ini penting karena perubahan yang disetujui dapat memiliki konsekuensi terhadap waktu pelaksanaan. Dengan menghubungkan S-Curve dan CR approved, sistem tidak hanya menampilkan progres, tetapi juga mulai mengaitkan perubahan dengan dampaknya terhadap jadwal.

Namun, hubungan tersebut masih bersifat indikatif. Total `timeImpact` tidak selalu identik dengan keterlambatan proyek aktual karena keterlambatan dapat terserap oleh percepatan, perubahan urutan kerja, atau aktivitas non-kritis. Oleh sebab itu, S-Curve dalam sistem diposisikan sebagai indikator pendukung, bukan sebagai analisis penjadwalan kritis yang menggantikan CPM atau perangkat penjadwalan khusus.

### X.5.8 Implementasi BIM dan APS

Integrasi BIM dilakukan melalui dua komponen API:

| API | Fungsi | Pertimbangan |
|---|---|---|
| `api_aps_token.php` | Mengambil token Autodesk Platform Services menggunakan kredensial server-side | Kredensial tidak ditempatkan langsung pada client |
| `api_aps_properties.php` | Mengambil properti elemen model berdasarkan `dbId`, token, dan URN | Properti model dapat dicocokkan dengan CR lokal |

`api_aps_properties.php` mencari parameter seperti `BIM Object ID` atau `Mark` pada properti Revit. Jika ditemukan, nilai tersebut dicocokkan dengan `change_requests.bimObjectId`. Hasilnya, pengguna dapat melihat CR yang terhubung dengan elemen BIM tertentu.

Pemilihan APS didasarkan pada kemampuannya membaca model BIM yang telah diproses menjadi format *viewable* dan menyediakan API properti elemen. Akan tetapi, integrasi ini memiliki dependensi eksternal. Sistem membutuhkan kredensial, koneksi internet, URN model, serta parameter model yang konsisten. Karena itu, fitur BIM harus dianggap sebagai pengayaan konteks visual, bukan satu-satunya cara mengakses data CR.

### X.5.9 Implementasi Visualisasi Data

Visualisasi data menggunakan Chart.js untuk grafik distribusi risiko, S-Curve, tren CR, dan grafik lain yang mendukung dashboard. Chart.js dipilih karena relatif ringan, mudah digunakan pada halaman HTML tanpa proses build, dan cukup memadai untuk kebutuhan grafik dashboard. Untuk visual 3D, sistem menggunakan Autodesk Viewer dan Three.js.

Visualisasi dipakai karena data perubahan proyek memiliki banyak dimensi. Tabel dapat menampilkan detail, tetapi kurang efektif untuk melihat pola secara cepat. Grafik dan KPI membantu pengguna mengenali prioritas, sedangkan tabel dan modal detail tetap diperlukan untuk verifikasi. Dengan demikian, visualisasi tidak menggantikan data mentah, tetapi menjadi lapisan interpretasi.

| Visualisasi | Data | Tujuan | Catatan Kritis |
|---|---|---|---|
| KPI cards | Jumlah CR, approved, rejected, pending, mitigation | Ringkasan kondisi proyek | Harus dihubungkan dengan data detail |
| Donut chart risiko | Distribusi skor risiko | Memahami komposisi risiko | Tidak menunjukkan penyebab risiko |
| Risk matrix | Likelihood dan impact hasil turunan skor | Memetakan prioritas risiko | Bergantung pada kualitas skor awal |
| Tornado chart | Dampak biaya dan waktu | Menunjukkan faktor perubahan dominan | Membutuhkan interpretasi konteks proyek |
| S-Curve | Rencana dan realisasi progres | Memantau kinerja proyek | Tidak menggantikan analisis CPM |
| Viewer BIM | Objek model dan `bimObjectId` | Mengaitkan data CR dengan konteks fisik | Bergantung pada kualitas model dan parameter |

## X.6 Pengujian dan Evaluasi Sistem

### X.6.1 Black-Box Testing

Pengujian *black-box* dilakukan untuk memastikan fungsi sistem berjalan sesuai kebutuhan dari sudut pandang pengguna. Pendekatan ini dipilih karena penelitian berfokus pada kesesuaian fungsi aplikasi terhadap alur pengelolaan CR, bukan pada pembuktian formal struktur internal kode. Meski demikian, hasil *black-box testing* tetap perlu dikombinasikan dengan pemeriksaan teknis seperti validasi query, keamanan akses, dan konsistensi data.

| No | Fungsi yang Diuji | Skenario | Keluaran yang Diharapkan | Status |
|---|---|---|---|---|
| 1 | Login | Pengguna memasukkan username dan password valid | Sistem masuk sesuai role | Diisi hasil uji |
| 2 | Validasi role | PM mengakses dashboard proyek assigned | Data proyek tampil | Diisi hasil uji |
| 3 | Pembatasan role | Site Engineer mencoba mengakses API Admin | Akses ditolak | Diisi hasil uji |
| 4 | Tambah proyek | Admin mengisi data proyek baru | Data tersimpan pada `projects` | Diisi hasil uji |
| 5 | Assignment proyek | Admin menetapkan PM/SE ke proyek | Data tersimpan pada `project_assignments` | Diisi hasil uji |
| 6 | Input CR | SE mengisi form CR lengkap | CR tersimpan pada `change_requests` | Diisi hasil uji |
| 7 | Upload bukti | SE menambahkan foto/dokumen bukti | Referensi file tersimpan | Diisi hasil uji |
| 8 | Tampil daftar CR | Admin/PM membuka daftar CR | Data tampil berurutan terbaru | Diisi hasil uji |
| 9 | Rekomendasi AI | CR memiliki pasangan WBS dan risiko | Insight dan saran tampil | Diisi hasil uji |
| 10 | Rekomendasi kosong | CR tidak memiliki pasangan repository | Pesan default tampil | Diisi hasil uji |
| 11 | Approval | PM menyetujui CR | Status menjadi `APPROVED` | Diisi hasil uji |
| 12 | Reject | PM menolak CR | Status menjadi `REJECTED` | Diisi hasil uji |
| 13 | Lesson learned otomatis | CR disetujui dan punya pasangan repository | Dokumen `KM-xxx` dibuat | Diisi hasil uji |
| 14 | S-Curve | Pengguna membuka detail proyek | Grafik rencana dan realisasi tampil | Diisi hasil uji |
| 15 | BIM linkage | Pengguna memilih objek BIM yang punya `bimObjectId` | CR terkait tampil | Diisi hasil uji |

### X.6.2 User Acceptance Test

UAT dilakukan untuk menilai apakah sistem sesuai dengan kebutuhan pengguna akhir. Pengujian ini penting karena sistem yang benar secara teknis belum tentu diterima secara operasional. Pengguna proyek cenderung menilai sistem dari kemudahan input, kecepatan memperoleh informasi, kejelasan dashboard, dan relevansi rekomendasi.

| Aspek UAT | Pertanyaan Evaluasi | Skala | Alasan Evaluasi |
|---|---|---|---|
| Kesesuaian proses | Apakah alur pengajuan dan approval sesuai proses proyek? | 1-5 | Memastikan sistem tidak bertentangan dengan SOP |
| Kemudahan input | Apakah form CR mudah dipahami? | 1-5 | Menentukan potensi adopsi oleh pengguna lapangan |
| Kejelasan dashboard | Apakah KPI, tabel, dan grafik membantu pemantauan? | 1-5 | Menguji manfaat visualisasi |
| Kejelasan risiko | Apakah badge dan skor risiko mudah dimaknai? | 1-5 | Menguji interpretasi risiko |
| Manfaat rekomendasi | Apakah insight dan saran membantu PM mengambil keputusan? | 1-5 | Menguji relevansi knowledge repository |
| Keterlacakan data | Apakah WBS, bukti, status, dan BIM memudahkan penelusuran? | 1-5 | Menguji fungsi audit dan koordinasi |
| Knowledge base | Apakah lesson learned bermanfaat untuk referensi proyek berikutnya? | 1-5 | Menguji nilai pembelajaran organisasi |

### X.6.3 Pengujian Performa

Pengujian performa dilakukan untuk mengetahui waktu respons halaman dan API. Performa menjadi penting karena dashboard menggabungkan beberapa query, agregasi, dan visualisasi. Apabila jumlah CR meningkat, query join dengan repository dan agregasi dashboard dapat menjadi beban sistem.

| Parameter | Metode Ukur | Target Awal | Catatan Kritis |
|---|---|---|---|
| Waktu muat halaman login | Browser developer tools | Di bawah 3 detik pada jaringan lokal | Dipengaruhi ukuran aset halaman |
| Waktu respons API dashboard | Network tab atau JMeter | Di bawah 1 detik untuk data uji kecil-menengah | Perlu diuji ulang saat data bertambah |
| Waktu render tabel CR | Observasi browser | Tidak terjadi freeze pada data uji | Bergantung pada jumlah baris dan manipulasi DOM |
| Waktu query join repository | Profiling database | Stabil setelah indeks WBS dan risiko digunakan | Perlu indeks dan normalisasi kode |
| Waktu load viewer BIM | Network dan viewer events | Bergantung ukuran model dan koneksi APS | Tidak sepenuhnya dikendalikan server lokal |

### X.6.4 System Usability Scale

Pengujian SUS dilakukan dengan 10 pernyataan standar yang diberi skala 1 sampai 5. SUS dipilih karena ringkas, mudah diterapkan, dan menghasilkan skor yang dapat dibandingkan secara umum. Namun, SUS tidak menjelaskan penyebab detail dari masalah usability. Oleh sebab itu, hasil SUS sebaiknya dilengkapi dengan komentar pengguna atau observasi saat pengguna menjalankan skenario.

| No | Pernyataan SUS | Skor Responden |
|---|---|---|
| 1 | Saya akan sering menggunakan sistem ini apabila tersedia dalam proyek | Diisi hasil kuesioner |
| 2 | Sistem ini terasa terlalu kompleks | Diisi hasil kuesioner |
| 3 | Sistem ini mudah digunakan | Diisi hasil kuesioner |
| 4 | Saya membutuhkan bantuan teknis untuk menggunakan sistem ini | Diisi hasil kuesioner |
| 5 | Fungsi-fungsi sistem terintegrasi dengan baik | Diisi hasil kuesioner |
| 6 | Terdapat terlalu banyak inkonsistensi pada sistem | Diisi hasil kuesioner |
| 7 | Sebagian besar pengguna akan cepat mempelajari sistem ini | Diisi hasil kuesioner |
| 8 | Sistem ini terasa rumit digunakan | Diisi hasil kuesioner |
| 9 | Saya merasa percaya diri menggunakan sistem ini | Diisi hasil kuesioner |
| 10 | Saya perlu belajar banyak sebelum dapat menggunakan sistem ini | Diisi hasil kuesioner |

## X.7 Pembahasan

### X.7.1 Kelebihan Sistem

Kelebihan sistem terletak pada integrasi proses yang sebelumnya cenderung terpisah. Data CR, WBS, risiko, bukti, approval, dashboard, BIM, dan knowledge base berada dalam satu alur informasi. Integrasi ini berpotensi mengurangi fragmentasi dokumen dan membantu proyek membangun *single source of truth*.

Pertama, sistem meningkatkan keterlacakan perubahan. Setiap CR memiliki identitas, WBS, lokasi, bukti, status, dan catatan approval. Kedua, sistem meningkatkan kecepatan pemantauan melalui dashboard dan visualisasi risiko. Ketiga, sistem menyediakan rekomendasi berbasis repository sehingga keputusan PM dapat didukung oleh referensi yang lebih eksplisit. Keempat, sistem membangun pembelajaran organisasi melalui lesson learned otomatis.

Namun, kelebihan tersebut perlu dibaca secara proporsional. Sistem tidak menghilangkan kebutuhan koordinasi lapangan, rapat teknis, pemeriksaan dokumen kontrak, atau validasi engineering. Sistem membantu mengorganisasi informasi dan mempercepat akses data, tetapi kualitas keputusan tetap bergantung pada kualitas input dan kompetensi pengguna.

### X.7.2 Keterbatasan Sistem

Sistem masih memiliki sejumlah keterbatasan. Pertama, rekomendasi masih berbasis aturan dan pencocokan data. Artinya, sistem hanya dapat memberikan rekomendasi yang tersedia atau dapat dicocokkan pada `knowledge_repository`. Jika repository tidak lengkap, tidak mutakhir, atau tidak sesuai konteks proyek, rekomendasi dapat menjadi kurang relevan.

Kedua, integrasi BIM bergantung pada konfigurasi Autodesk Platform Services, ketersediaan model yang telah diterjemahkan, dan konsistensi parameter `BIM Object ID` atau `Mark` pada elemen model. Tanpa kedisiplinan data model, hubungan antara objek BIM dan CR tidak akan terbentuk dengan baik.

Ketiga, sistem bergantung pada koneksi jaringan. Pengguna lapangan yang berada di area dengan konektivitas rendah dapat mengalami kendala saat memuat dashboard, mengunggah bukti, atau membuka viewer BIM. Keempat, pengujian performa dan usability perlu dilakukan dengan jumlah data dan responden yang lebih besar agar kesimpulan lebih kuat.

Kelima, sistem belum memiliki beberapa fitur tata kelola yang lazim pada sistem produksi penuh, seperti audit trail terperinci, kontrol versi dokumen, notifikasi otomatis, CSRF protection eksplisit, dan workflow approval bertingkat. Keterbatasan ini tidak meniadakan nilai sistem, tetapi menjadi batas interpretasi hasil dan arah pengembangan selanjutnya.

### X.7.3 Perbandingan dengan Sistem Konvensional

Perbandingan dengan sistem konvensional diperlukan untuk menunjukkan posisi kontribusi sistem. Sistem konvensional tidak selalu buruk karena mudah digunakan, familiar, dan fleksibel. Namun, kelemahannya muncul ketika volume data meningkat, aktor bertambah, dan kebutuhan audit menjadi penting.

| Aspek | Sistem Konvensional | Sistem yang Dikembangkan | Analisis Kritis |
|---|---|---|---|
| Media pencatatan | Excel, dokumen terpisah, email, pesan instan | Database terpusat berbasis web | Web app lebih kuat untuk konsistensi data, tetapi membutuhkan infrastruktur |
| Status approval | Dicek manual melalui dokumen/komunikasi | Status langsung terlihat pada dashboard | Dashboard mempercepat pemantauan, tetapi input status harus disiplin |
| WBS | Sering hanya dicatat sebagai teks | Tersimpan sebagai atribut terstruktur | Struktur WBS mendukung analisis, tetapi bergantung pada pemilihan kode yang benar |
| Risiko | Dinilai manual dan tidak selalu konsisten | Skor risiko ditampilkan dan divisualisasikan | Visualisasi membantu prioritas, tetapi skor tetap perlu standar penilaian |
| Rekomendasi | Bergantung pada pengalaman individu | Berdasarkan repository WBS-risiko | Mengurangi ketergantungan personal, tetapi repository harus dikurasi |
| Bukti lapangan | Terpisah dari dokumen review | Terhubung dengan CR | Keterlacakan meningkat, tetapi penyimpanan file perlu dikelola |
| BIM | Umumnya tidak terhubung | Dapat dikaitkan dengan `bimObjectId` | Konteks visual meningkat, tetapi perlu kesiapan model |
| Lesson learned | Sering dibuat setelah proyek selesai | Dapat terbentuk otomatis dari CR approved | Pengetahuan lebih cepat terbentuk, tetapi perlu validasi substansi |
| Monitoring progres | Terpisah pada laporan S-Curve | S-Curve tersedia dalam dashboard | Integrasi membantu pembacaan, tetapi belum menggantikan analisis jadwal detail |
| Kontrol akses | Bergantung pada distribusi file | Role dan project assignment | Lebih terkendali, tetapi perlu keamanan aplikasi yang baik |

### X.7.4 Implikasi Pengembangan

Sistem ini menunjukkan bahwa pengelolaan perubahan proyek dapat ditingkatkan melalui integrasi data transaksi, risiko, WBS, BIM, dan pengetahuan proyek. Implikasi utamanya adalah perubahan tidak lagi diperlakukan sebagai dokumen terpisah, tetapi sebagai data yang dapat diolah, divisualisasikan, direview, dan digunakan kembali.

Dari sisi manajerial, sistem dapat membantu PM memprioritaskan CR berdasarkan risiko dan dampaknya. Dari sisi teknis, WBS dan BIM membantu menghubungkan perubahan dengan objek pekerjaan. Dari sisi organisasi, knowledge base dapat menjadi fondasi pembelajaran lintas proyek.

Pengembangan lanjutan dapat diarahkan pada:

- integrasi BIM yang lebih dalam dengan model 4D/5D;
- integrasi dengan aplikasi penjadwalan seperti Primavera P6 atau Microsoft Project;
- integrasi dengan sistem estimasi biaya;
- fitur notifikasi dan SLA review;
- *offline synchronization* untuk area proyek dengan jaringan terbatas;
- analitik historis menggunakan pembelajaran mesin setelah data mencukupi;
- audit trail untuk setiap perubahan data dan keputusan;
- workflow approval bertingkat sesuai struktur organisasi proyek.

## X.8 Kerangka Ilustrasi dan Prompt Pendamping

Bagian ini berisi daftar ilustrasi yang disarankan untuk mendampingi penulisan bab. Ilustrasi dipilih bukan hanya sebagai pelengkap visual, tetapi sebagai alat untuk memperjelas relasi antarproses, batas sistem, alur data, dan pertimbangan desain. Dengan demikian, gambar yang disajikan sebaiknya bersifat analitis, bukan dekoratif.

| No | Ilustrasi | Lokasi dalam Bab | Isi Visual | Draf Prompt Pembuatan |
|---|---|---|---|---|
| 1 | Diagram arsitektur sistem | X.3.1 | User, browser, frontend HTML/CSS/JS, PHP API, PDO, MySQL, APS, Chart.js, Three.js | Buat diagram arsitektur web app akademis bergaya bersih. Tampilkan alur Pengguna -> Browser -> Frontend HTML CSS JavaScript -> PHP API -> PDO -> MySQL. Tambahkan cabang ke Autodesk Platform Services untuk BIM viewer dan cabang Chart.js/Three.js untuk visualisasi. Gunakan warna netral, label bahasa Indonesia, layout horizontal, resolusi tinggi. |
| 2 | Use Case Diagram | X.2.2 | Admin, Project Manager, Site Engineer dan fitur utama | Buat use case diagram UML untuk sistem informasi Change Request konstruksi. Aktor: Admin, Project Manager, Site Engineer. Use case Admin: kelola proyek, kelola user, assignment proyek, knowledge base, laporan. Use case Project Manager: lihat dashboard, review CR, approve/reject, lihat rekomendasi AI, lihat BIM. Use case Site Engineer: input CR, upload evidence, pilih WBS, pilih risiko, lihat status. Gaya akademis hitam putih. |
| 3 | Activity Diagram CR | X.3.5 | Alur input, validasi, review, approval, lesson learned | Buat activity diagram proses Change Request. Mulai dari Site Engineer mengajukan CR, sistem validasi data, sistem menyimpan CR, sistem mencocokkan WBS dan risiko ke knowledge repository, Project Manager melakukan review, keputusan approved/rejected/pending, jika approved sistem membuat lesson learned di knowledge base, selesai. Gunakan swimlane Site Engineer, Sistem, Project Manager. |
| 4 | ERD Database | X.4.1 | Tabel utama dan relasi | Buat ERD sistem Change Request konstruksi. Tabel: users, projects, project_assignments, change_requests, knowledge_repository, knowledge_base, project_scurve. Tampilkan primary key dan foreign key utama. Jelaskan relasi users-project_assignments-projects, projects-project_scurve, knowledge_repository-knowledge_base, change_requests-knowledge_base sebagai logical link. Gaya database diagram profesional. |
| 5 | Data Flow Diagram Level 1 | X.3.5 | Input CR, approval, knowledge base, dashboard | Buat DFD level 1 untuk web app Change Request. Proses: Autentikasi, Pengajuan Change Request, Review dan Approval, AI Recommendation, Knowledge Base, Dashboard Analytics. Data store: users, projects, change_requests, knowledge_repository, knowledge_base, project_scurve. Entitas eksternal: Admin, Site Engineer, Project Manager, Autodesk Platform Services. |
| 6 | Wireframe Dashboard PM | X.5.3 | KPI, donut risk, pending queue, modal detail | Buat wireframe dashboard Project Manager untuk review Change Request konstruksi. Elemen: kartu KPI Under Review, Need Mitigation, Approved, Rejected; chart distribusi risiko; tabel pending CR; panel rekomendasi AI; tombol approve/reject. Gaya UI dashboard modern, bersih, bukan landing page. |
| 7 | Wireframe Site Engineer Form | X.5.4 | Form CR bertahap dan upload bukti | Buat wireframe form Site Engineer untuk input Change Request. Bagian: identitas CR, WBS Level 4-6, lokasi, BIM Object ID, kategori risiko, variabel risiko, dampak biaya, dampak waktu, dampak lingkup, mutu, K3, upload evidence. Gaya aplikasi operasional konstruksi, rapi, responsif. |
| 8 | Diagram AI Recommendation Engine | X.5.5 | Matching CR dengan repository | Buat diagram proses AI Recommendation Engine berbasis aturan. Input: Change Request, WBS Level 5, Risk Variable. Proses: normalisasi kode, LEFT JOIN, pencocokan risk_kode dan wbs_kode. Output: insight, saran, badge risiko. Tambahkan catatan bahwa AI berbasis rule-based expert system, bukan machine learning. |
| 9 | Diagram Knowledge Lifecycle | X.5.6 | CR approved menjadi KM | Buat diagram siklus knowledge management proyek konstruksi. Tahap: Change Request submitted, reviewed by PM, approved, actual impact recorded, lesson learned generated as KM-xxx, stored in knowledge_base, reused as recommendation for future projects. Gaya infografis akademis. |
| 10 | Ilustrasi BIM Linkage | X.5.8 | Elemen BIM terhubung ke CR | Buat ilustrasi konsep BIM linkage. Tampilkan model jembatan/precast sederhana dengan satu elemen disorot. Panel samping menampilkan BIM Object ID, WBS, status Change Request, dan rekomendasi risiko. Sertakan alur Autodesk Viewer -> API properties -> local change_requests. |
| 11 | Grafik S-Curve | X.5.7 | Rencana vs realisasi plus delay CR | Buat grafik S-Curve proyek konstruksi dengan dua garis: rencana kumulatif dan realisasi kumulatif. Tambahkan anotasi total delay dari approved Change Request. Gaya laporan akademis, axis jelas, warna profesional. |
| 12 | Matriks Risiko 5x5 | X.1.3 atau X.5.9 | Likelihood vs impact | Buat matriks risiko 5x5 dengan sumbu Likelihood dan Impact. Gunakan gradasi hijau, kuning, oranye, merah. Tandai contoh CR-003 pada area kritis dan CR-006 pada area aman/rendah. Label bahasa Indonesia. |
| 13 | Perbandingan Sistem | X.7.3 | Konvensional vs web app | Buat infografis komparasi dua kolom. Kolom kiri Sistem Konvensional: Excel, email, dokumen terpisah, risiko manual. Kolom kanan Sistem Web App: database terpusat, dashboard, WBS, AI recommendation, BIM, knowledge base. Gaya akademis minimal. |
| 14 | Deployment View | X.3.1 | Lokal dan hosting | Buat deployment diagram aplikasi web. Lingkungan lokal: XAMPP, Apache, PHP, MySQL. Lingkungan hosting: web server, PHP, MySQL, environment variables. Client browser mengakses melalui HTTP/HTTPS. Tambahkan API eksternal Autodesk Platform Services. |

## X.9 Rekomendasi Struktur Penyajian Gambar dan Tabel

Agar bab mudah dibaca, gambar dan tabel sebaiknya ditempatkan dekat dengan pembahasan terkait. Tabel digunakan untuk membandingkan keputusan desain, sedangkan gambar digunakan untuk menjelaskan relasi yang sulit dipahami melalui teks saja.

| Bagian | Tabel/Gambar yang Disarankan | Fungsi Akademik |
|---|---|---|
| X.1 Landasan Teori | Tabel klasifikasi risiko, matriks risiko 5x5 | Menjelaskan dasar konseptual penilaian risiko |
| X.2 Metodologi | Tabel kebutuhan pengguna, use case diagram | Menghubungkan kebutuhan dengan aktor |
| X.3 Arsitektur | Diagram arsitektur, DFD, deployment diagram | Menjelaskan batas sistem dan alur data |
| X.4 Basis Data | ERD, tabel entitas utama | Memperlihatkan struktur penyimpanan dan relasi |
| X.5 Implementasi | Screenshot dashboard Admin, PM, Site Engineer, diagram recommendation engine, diagram BIM linkage | Membuktikan implementasi fitur utama |
| X.6 Pengujian | Tabel black-box testing, tabel UAT, tabel SUS, grafik hasil SUS | Menyajikan evaluasi sistem secara terukur |
| X.7 Pembahasan | Tabel perbandingan sistem konvensional dan sistem web app | Menunjukkan kontribusi dan batasan sistem |

## X.10 Matriks Kesesuaian dengan Literatur dan Standar

Matriks kesesuaian digunakan untuk menunjukkan bahwa pengembangan sistem memiliki basis literatur yang dapat ditelusuri. Kesesuaian dalam bab ini tidak dimaknai sebagai sertifikasi atau klaim kepatuhan formal, melainkan sebagai pemanfaatan prinsip standar untuk memperkuat desain sistem, struktur data, dan evaluasi.

| Rujukan | Fokus Utama | Relevansi terhadap Sistem | Bukti Implementasi |
|---|---|---|---|
| PMBOK Guide | Tata kelola proyek, domain kinerja, risiko, stakeholder, value delivery | CR menjadi mekanisme pengendalian perubahan dan pendukung keputusan | Role PM/Admin/SE, approval, dashboard, laporan |
| ISO 21502 | Panduan praktik manajemen proyek lintas organisasi dan pendekatan delivery | Struktur proses proyek dapat diterapkan secara fleksibel | Alur pengajuan, review, approval, monitoring |
| ISO 31000 | Prinsip dan proses manajemen risiko | Risiko dinilai, diklasifikasikan, divisualisasikan, dan dikomunikasikan | Skor risiko, risk category, risk variable, risk matrix |
| ISO 10006 | Quality management dalam proyek | Kualitas proses proyek diperkuat melalui dokumentasi dan evaluasi | Form CR, bukti, status, lesson learned |
| ISO 9001 | Sistem manajemen mutu dan perbaikan berkelanjutan | Data perubahan menjadi dasar kontrol proses dan pembelajaran | Knowledge base, audit data, standardisasi input |
| ISO 19650 | Manajemen informasi menggunakan BIM | Informasi model dan data proyek perlu terhubung untuk keputusan | `bimObjectId`, APS properties, viewer BIM |
| IFC/ISO 16739 | Interoperabilitas data aset terbangun | Model BIM idealnya memakai struktur terbuka dan dapat dipertukarkan | Kebutuhan lanjutan untuk interoperabilitas BIM |
| ISO/IEC 27001 | Keamanan informasi | Data proyek dan kredensial perlu dilindungi | `.env`, `.htaccess`, session, PDO |
| ISO/IEC/IEEE 12207 | Siklus hidup perangkat lunak | Pengembangan perlu mencakup development, operation, maintenance | RAD, dokumentasi, struktur folder, pengujian |
| ISO/IEC 25010 | Model kualitas perangkat lunak | Evaluasi sistem mencakup fungsi, performa, keamanan, portabilitas | Black-box, performa, maintainability, usability |
| ISO 9241-210 | Human-centred design | Antarmuka perlu disusun berdasarkan kebutuhan pengguna | Halaman per role, UAT, SUS |
| WCAG 2.2 | Aksesibilitas web | UI web sebaiknya dapat diakses dan dipahami lebih luas | Rekomendasi lanjutan untuk label, kontras, keyboard navigation |

Matriks tersebut memperlihatkan bahwa sistem memiliki posisi integratif. Dari sisi manajemen proyek, sistem berfungsi sebagai alat pengendalian perubahan. Dari sisi risiko, sistem mendukung klasifikasi dan komunikasi risiko. Dari sisi BIM, sistem menghubungkan model dan data transaksi. Dari sisi kualitas, sistem mengubah peristiwa proyek menjadi data yang dapat diperiksa dan dipelajari. Dari sisi perangkat lunak, sistem dapat dievaluasi sebagai produk digital dengan atribut kualitas yang jelas.

## X.11 Bab Pendukung yang Disarankan

Bab sistem informasi ini sebaiknya tidak berdiri sendiri. Agar argumentasi penelitian lebih kuat, beberapa bab atau subbab pendukung perlu disusun sebelum dan sesudah bab ini. Bab pendukung tersebut berfungsi untuk menempatkan aplikasi sebagai bagian dari kerangka riset yang lebih luas, bukan hanya sebagai lampiran teknis.

| Bab/Subbab Pendukung | Fokus Bahasan | Hubungan dengan Bab Sistem Informasi |
|---|---|---|
| Kerangka Konseptual Pengendalian Perubahan | Model hubungan antara perubahan, WBS, risiko, BIM, approval, dan knowledge management | Menjadi dasar mengapa fitur sistem dirancang seperti sekarang |
| Metodologi Pengembangan Artefak Digital | Pendekatan RAD, requirement analysis, desain prototipe, validasi ahli, dan iterasi | Menjelaskan bagaimana aplikasi dibangun dan dievaluasi |
| Model Data dan Ontologi Informasi Proyek | Definisi entitas CR, WBS, risiko, proyek, pengguna, BIM object, dan lesson learned | Menguatkan desain basis data dan interoperabilitas |
| Standar Manajemen Risiko Konstruksi | Adaptasi ISO 31000 ke konteks proyek konstruksi | Menjadi dasar skoring risiko, risk matrix, dan rekomendasi |
| Standar Manajemen Informasi BIM | ISO 19650, CDE, EIR, BEP, IFC, dan information exchange | Mendukung argumen integrasi BIM dan `bimObjectId` |
| Validasi Pakar dan User Acceptance Test | Metode penilaian kegunaan, kelayakan proses, dan relevansi rekomendasi | Menguji apakah sistem sesuai kebutuhan praktisi |
| Evaluasi Kinerja Sistem | Waktu respons API, waktu muat halaman, performa query, dan skalabilitas data | Menunjukkan kesiapan teknis sistem |
| Implikasi Manajerial | Dampak sistem terhadap keputusan PM, koordinasi, dokumentasi, dan pembelajaran organisasi | Menghubungkan hasil implementasi dengan kontribusi manajerial |
| Keterbatasan dan Roadmap | Audit trail, workflow bertingkat, integrasi biaya/jadwal, 4D/5D BIM, analitik prediktif | Memberi arah pengembangan lanjutan |

Rangkaian bab pendukung tersebut dapat disusun sebagai alur: teori dan standar, model konseptual, metodologi pengembangan, implementasi sistem, pengujian, pembahasan, lalu implikasi. Dengan susunan demikian, web app tidak hanya dijelaskan sebagai produk akhir, tetapi sebagai artefak ilmiah yang lahir dari kebutuhan proses, dirancang berdasarkan standar, dan dievaluasi melalui indikator yang terukur.

## X.12 Referensi Pendukung

Referensi berikut ditulis menggunakan gaya APA. Beberapa standar bersifat berbayar sehingga tautan diarahkan ke halaman resmi atau halaman ringkasan resmi penerbit standar.

buildingSMART International. (n.d.). *Industry Foundation Classes (IFC), ISO 16739*. https://www.buildingsmart.org/standards/bsi-standards/industry-foundation-classes/

buildingSMART Technical. (n.d.). *IFC schema specifications*. https://technical.buildingsmart.org/standards/ifc/ifc-schema-specifications/

International Organization for Standardization. (2015). *ISO 9001:2015: Quality management systems - Requirements*. https://www.iso.org/standard/62085.html

International Organization for Standardization. (2017). *ISO 10006:2017: Quality management - Guidelines for quality management in projects*. https://www.iso.org/standard/70376.html

International Organization for Standardization. (2018a). *ISO 19650-1:2018: Organization and digitization of information about buildings and civil engineering works, including building information modelling (BIM) - Information management using building information modelling - Part 1: Concepts and principles*. https://www.iso.org/standard/68078.html

International Organization for Standardization. (2018b). *ISO 31000:2018: Risk management - Guidelines*. https://www.iso.org/standard/65694.html

International Organization for Standardization. (2019). *ISO 9241-210:2019: Ergonomics of human-system interaction - Part 210: Human-centred design for interactive systems*. https://www.iso.org/standard/77520.html

International Organization for Standardization. (2020). *ISO 21502:2020: Project, programme and portfolio management - Guidance on project management*. https://www.iso.org/standard/74947.html

International Organization for Standardization. (2022). *ISO/IEC 27001:2022: Information security, cybersecurity and privacy protection - Information security management systems - Requirements*. https://www.iso.org/standard/27001

International Organization for Standardization. (2023). *ISO/IEC 25010:2023: Systems and software engineering - Systems and software Quality Requirements and Evaluation (SQuaRE) - Product quality model*. https://www.iso.org/standard/78176.html

International Organization for Standardization. (2024). *ISO 55000:2024: Asset management - Vocabulary, overview and principles*. https://www.iso.org/standard/83053.html

International Organization for Standardization. (2026). *ISO/IEC/IEEE 12207:2026: Systems and software engineering - Software life cycle processes*. https://www.iso.org/standard/90219.html

Project Management Institute. (n.d.). *PMBOK guide*. https://www.pmi.org/standards/pmbok

World Wide Web Consortium. (2023). *Web Content Accessibility Guidelines (WCAG) 2.2*. https://www.w3.org/TR/WCAG22/

World Wide Web Consortium Web Accessibility Initiative. (n.d.). *WCAG 2 overview*. https://www.w3.org/WAI/standards-guidelines/wcag/

## X.13 Kesimpulan Bab

Sistem informasi yang dikembangkan merupakan aplikasi web untuk mendukung pengelolaan *Change Request* pada proyek konstruksi. Sistem mengintegrasikan pengajuan perubahan, WBS, risiko, approval, dashboard, S-Curve, BIM, dan knowledge base dalam satu arsitektur berbasis PHP, MySQL, HTML, CSS, JavaScript, Chart.js, Three.js, dan Autodesk Platform Services.

Dari sisi proses bisnis, sistem membantu *Site Engineer* mencatat perubahan secara terstruktur dan membantu *Project Manager* mengevaluasi perubahan berdasarkan risiko, dampak, rekomendasi, dan data pendukung. Dari sisi organisasi, sistem mendukung pembentukan *lesson learned* melalui knowledge base sehingga pengalaman proyek dapat digunakan kembali. Dari sisi teknis, sistem menunjukkan bahwa teknologi web sederhana dapat membentuk integrasi data yang cukup kuat apabila struktur data, role, API, dan visualisasi dirancang secara konsisten.

Meskipun demikian, sistem tetap perlu dibaca dengan batasan yang jelas. Rekomendasi masih berbasis aturan, integrasi BIM bergantung pada kesiapan model dan konfigurasi APS, performa perlu diuji pada data dan pengguna yang lebih besar, serta keamanan produksi masih dapat diperkuat. Pengembangan selanjutnya dapat diarahkan pada integrasi 4D/5D BIM, sistem biaya, sistem penjadwalan, notifikasi, audit trail, sinkronisasi offline, workflow approval bertingkat, dan analitik prediktif berbasis data historis.
